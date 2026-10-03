<?php

namespace Modules\Sales\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Accounting\Models\Tax;
use Modules\Core\Documents\DocumentStatus;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Partner;
use Modules\Core\Settings\Settings;
use Modules\Inventory\Enums\StockMoveType;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Stock\StockLineData;
use Modules\Inventory\Tests\Concerns\MovesStock;
use Modules\Products\Models\Product;
use Modules\Products\Models\Unit;
use Modules\Sales\Actions\InvoiceActions;
use Modules\Sales\Actions\PriceListActions;
use Modules\Sales\Livewire\Invoices\Form;
use Modules\Sales\Livewire\Invoices\Show;
use Modules\Sales\Livewire\PriceLists\Form as PriceListForm;
use Modules\Sales\Livewire\Returns\Form as ReturnForm;
use Modules\Sales\Models\CustomerProfile;
use Modules\Sales\Models\PriceList;
use Modules\Sales\Models\SalesInvoice;
use Modules\Sales\Models\SalesReturn;
use Tests\Concerns\InstallsErp;
use Tests\TestCase;

class SalesScreensTest extends TestCase
{
    use InstallsErp, MovesStock, RefreshDatabase;

    private Partner $customer;

    private Product $product;

    private Unit $carton;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installErp();
        $this->actingAs($this->admin);
        $this->customer = Partner::factory()->create();
        $this->carton = Unit::where('name->en', 'Carton')->firstOrFail();
        $this->product = Product::factory()->withUnit($this->carton, '6')->create([
            'sale_price' => '20', 'sale_tax_id' => Tax::firstWhere('code', 'VAT14')->id,
        ]);
        $this->warehouse = Warehouse::where('branch_id', $this->branch->id)->firstOrFail();
        $this->receive([new StockLineData($this->product->id, $this->warehouse->id, '60', '10')], null, StockMoveType::Opening, 'opening_balance_equity');
    }

    private function draft(): SalesInvoice
    {
        return app(InvoiceActions::class)->save($this->admin, [
            'date' => now()->toDateString(), 'partner_id' => $this->customer->id, 'warehouse_id' => $this->warehouse->id,
            'currency_id' => Currency::where('code', 'EGP')->value('id'),
            'lines' => [['product_id' => $this->product->id, 'unit_id' => $this->carton->id, 'quantity' => '1']],
        ]);
    }

    public function test_an_invoice_is_priced_saved_posted_with_payment_and_returned(): void
    {
        Livewire::test(Form::class)
            ->assertSet('form.partner_id', app(Settings::class)->get('sales.walk_in_partner_id'))
            ->set('form.partner_id', $this->customer->id)
            ->dispatch('product-picked', index: 0, productId: $this->product->id, unitId: $this->carton->id)
            ->assertSet('form.lines.0.unit_price', '120')
            ->assertSet('form.lines.0.tax_id', $this->product->sale_tax_id)
            ->set('form.lines.0.quantity', '2')
            ->set('form.paid_amount', '100')
            ->call('save')
            ->assertHasNoErrors();

        $invoice = SalesInvoice::sole();
        $this->assertSame('273.6000', (string) $invoice->total);
        $this->assertSame(PaymentMethod::where('is_active', true)->orderBy('sort')->value('id'), $invoice->payment_method_id);

        Livewire::test(Show::class, ['id' => $invoice->id])->call('post')->assertHasNoErrors()->assertSee('100.00');

        $this->assertSame(DocumentStatus::Posted, $invoice->fresh()->status);
        $this->assertSame('48.0000', $this->onHand($this->product->id, $this->warehouse->id));

        Livewire::withQueryParams(['invoice' => $invoice->id])->test(ReturnForm::class)
            ->set('lines.'.$invoice->lines()->value('id').'.quantity', '1')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('136.8000', (string) SalesReturn::sole()->total);
    }

    public function test_changing_the_customer_reprices_lines_from_their_price_list(): void
    {
        $list = app(PriceListActions::class)->save($this->admin, [
            'name_ar' => 'جملة', 'items' => [['product_id' => $this->product->id, 'unit_id' => $this->carton->id, 'price' => '100']],
        ]);
        app(PriceListActions::class)->assign($this->admin, $this->customer, $list->id);

        Livewire::test(Form::class)
            ->dispatch('product-picked', index: 0, productId: $this->product->id, unitId: $this->carton->id)
            ->assertSet('form.lines.0.unit_price', '120')
            ->set('form.partner_id', $this->customer->id)
            ->assertSet('form.price_list_id', $list->id)
            ->assertSet('form.lines.0.unit_price', '100')
            ->set('form.price_list_id', '')
            ->assertSet('form.lines.0.unit_price', '120');
    }

    public function test_the_credit_limit_warning_asks_before_posting(): void
    {
        $this->customer->update(['credit_limit' => '50']);
        $invoice = $this->draft();

        $page = Livewire::test(Show::class, ['id' => $invoice->id])->call('post');
        $page->assertSet('overLimitWarning', fn ($warning) => is_string($warning));
        $this->assertSame(DocumentStatus::Draft, $invoice->fresh()->status);

        $page->call('post', true)->assertSet('overLimitWarning', null);
        $this->assertSame(DocumentStatus::Posted, $invoice->fresh()->status);
    }

    public function test_a_price_list_is_saved_and_assigned_to_customers(): void
    {
        Livewire::test(PriceListForm::class)
            ->set('form.name_ar', 'جملة')
            ->dispatch('product-picked', index: 0, productId: $this->product->id, unitId: $this->carton->id)
            ->assertSet('form.items.0.price', '120')
            ->set('form.items.0.price', '99')
            ->call('save')
            ->assertHasNoErrors();

        $list = PriceList::sole();
        $this->assertSame('99.0000', (string) $list->items()->value('price'));

        Livewire::test(PriceListForm::class, ['id' => $list->id])
            ->set('customerToAssign', $this->customer->id)
            ->call('assign')
            ->assertHasNoErrors();
        $this->assertSame($list->id, CustomerProfile::find($this->customer->id)?->price_list_id);

        Livewire::test(PriceListForm::class, ['id' => $list->id])->call('unassign', $this->customer->id);
        $this->assertNull(CustomerProfile::find($this->customer->id)?->price_list_id);
    }

    public function test_pages_render_and_need_permissions(): void
    {
        $invoice = $this->draft();

        foreach (['/sales/invoices', '/sales/invoices/create', "/sales/invoices/{$invoice->id}", "/sales/invoices/{$invoice->id}/edit", '/sales/returns', '/sales/price-lists', '/sales/price-lists/create'] as $url) {
            $this->get($url)->assertOk();
            $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
            $this->actingAs($this->admin);
        }
    }
}
