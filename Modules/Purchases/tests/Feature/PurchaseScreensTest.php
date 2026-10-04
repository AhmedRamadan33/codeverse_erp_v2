<?php

namespace Modules\Purchases\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Accounting\Models\Tax;
use Modules\Core\Models\Partner;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Tests\Concerns\MovesStock;
use Modules\Products\Livewire\Picker;
use Modules\Products\Models\Product;
use Modules\Products\Models\Unit;
use Modules\Purchases\Actions\ReturnActions;
use Modules\Purchases\Livewire\Invoices\Form;
use Modules\Purchases\Livewire\Invoices\Show;
use Modules\Purchases\Livewire\Reports\PurchasesAnalysis;
use Modules\Purchases\Livewire\Returns\Form as ReturnForm;
use Modules\Purchases\Models\PurchaseInvoice;
use Modules\Purchases\Models\PurchaseReturn;
use Tests\Concerns\InstallsErp;
use Tests\TestCase;

class PurchaseScreensTest extends TestCase
{
    use InstallsErp, MovesStock, RefreshDatabase;

    private Partner $supplier;

    private Product $product;

    private Unit $carton;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installErp();
        $this->actingAs($this->admin);
        $this->supplier = Partner::factory()->supplier()->create();
        $this->carton = Unit::where('name->en', 'Carton')->firstOrFail();
        $this->product = Product::factory()->withUnit($this->carton, '6')->create([
            'purchase_price' => '20', 'purchase_tax_id' => Tax::firstWhere('code', 'VAT14')->id,
        ]);
        $this->product->units()->where('unit_id', $this->carton->id)->first()->barcodes()->create(['product_id' => $this->product->id, 'barcode' => '622999']);
    }

    public function test_picking_a_product_fills_unit_price_and_tax_and_the_invoice_posts(): void
    {
        Livewire::test(Form::class)
            ->set('form.partner_id', $this->supplier->id)
            ->dispatch('product-picked', index: 0, productId: $this->product->id, unitId: $this->carton->id)
            ->assertSet('form.lines.0.unit_id', $this->carton->id)
            ->assertSet('form.lines.0.unit_price', '120')
            ->assertSet('form.lines.0.tax_id', $this->product->purchase_tax_id)
            ->set('form.lines.0.quantity', '2')
            ->call('save')
            ->assertHasNoErrors();

        $invoice = PurchaseInvoice::sole();
        $this->assertSame('273.6000', (string) $invoice->total);

        Livewire::test(Show::class, ['id' => $invoice->id])->call('post')->assertHasNoErrors();

        $warehouse = Warehouse::where('branch_id', $this->branch->id)->first();
        $this->assertSame('12.0000', $this->onHand($this->product->id, $warehouse->id));

        Livewire::withQueryParams(['invoice' => $invoice->id])->test(ReturnForm::class)
            ->set('lines.'.$invoice->lines()->value('id').'.quantity', '1')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('136.8000', (string) PurchaseReturn::sole()->total);

        $this->get(route('purchases.invoices.print', $invoice->id))->assertOk()->assertSee($invoice->fresh()->number)->assertSee($this->supplier->name)->assertSee('273.60');
        $this->get(route('purchases.returns.print', PurchaseReturn::sole()->id))->assertOk();

        // The return is still a draft, so the report shows both cartons: 12 units, 240 net.
        Livewire::test(PurchasesAnalysis::class)->assertSee($this->product->label())->assertSee('240.00')
            ->set('groupBy', 'supplier')->assertSee($this->supplier->name);

        // Once posted, the returned carton comes off: 120 net.
        app(ReturnActions::class)->post($this->admin, PurchaseReturn::sole());
        Livewire::test(PurchasesAnalysis::class)->assertSee('120.00')->assertDontSee('240.00');
        $this->actingAs(User::factory()->create())->get(route('purchases.reports.analysis'))->assertForbidden();
    }

    public function test_the_picker_scans_a_barcode_into_its_unit(): void
    {
        Livewire::test(Picker::class, ['index' => 3])
            ->set('search', '622999')
            ->call('pickFirst')
            ->assertDispatched('product-picked', index: 3, productId: $this->product->id, unitId: $this->carton->id);
    }

    public function test_pages_render_and_need_permissions(): void
    {
        foreach (['/purchases/invoices', '/purchases/invoices/create', '/purchases/returns'] as $url) {
            $this->get($url)->assertOk();
            $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
            $this->actingAs($this->admin);
        }
    }
}
