<?php

namespace Modules\Sales\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Enums\AccountSubtype;
use Modules\Accounting\Ledger\Ledger;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Accounting\Models\ReceiptVoucher;
use Modules\Accounting\Models\Tax;
use Modules\Accounting\Reconciliation\Reconciler;
use Modules\Accounting\Tests\Concerns\PostsEntries;
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
use Modules\Sales\Actions\ReturnActions;
use Modules\Sales\Models\SalesInvoice;
use Tests\Concerns\InstallsErp;
use Tests\TestCase;

class SalesTest extends TestCase
{
    use InstallsErp, MovesStock, PostsEntries, RefreshDatabase;

    private Partner $customer;

    private Warehouse $warehouse;

    private Product $product;

    private Unit $carton;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installErp();
        $this->customer = Partner::factory()->create(['payment_term_days' => 15]);
        $this->warehouse = Warehouse::where('branch_id', $this->branch->id)->firstOrFail();
        $this->carton = Unit::where('name->en', 'Carton')->firstOrFail();
        $this->product = Product::factory()->withUnit($this->carton, '12')->create(['sale_price' => '15']);

        // 120 units in stock at 10 each.
        $this->receive([new StockLineData($this->product->id, $this->warehouse->id, '120', '10')], null, StockMoveType::Opening, 'opening_balance_equity');
    }

    private function data(array $overrides = [], ?array $lines = null): array
    {
        return array_merge([
            'date' => now()->toDateString(),
            'partner_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => Currency::where('code', 'EGP')->value('id'),
            'lines' => $lines ?? [[
                'product_id' => $this->product->id, 'unit_id' => $this->carton->id, 'quantity' => '2',
                'tax_id' => Tax::firstWhere('code', 'VAT14')->id,
            ]],
        ], $overrides);
    }

    private function sell(array $overrides = [], ?array $lines = null, bool $confirm = false): SalesInvoice
    {
        $actions = app(InvoiceActions::class);

        return $actions->post($this->admin, $actions->save($this->admin, $this->data($overrides, $lines)), $confirm);
    }

    public function test_an_invoice_posts_revenue_tax_and_cost_of_goods(): void
    {
        $invoice = $this->sell();

        // 2 cartons at 15 × 12 = 360, VAT 50.40; cost 24 units × 10 = 240.
        $this->assertStringStartsWith('SI-', $invoice->number);
        $this->assertSame('410.4000', (string) $invoice->total);
        $this->assertSame(now()->addDays(15)->toDateString(), $invoice->due_date->toDateString());
        $this->assertSame('240.0000', (string) $invoice->lines()->first()->cost);
        $this->assertSame('96.0000', $this->onHand($this->product->id, $this->warehouse->id));

        $ledger = app(Ledger::class);
        $this->assertSame('410.4000', (string) $ledger->partnerBalance($this->customer, AccountSubtype::Receivable));
        $this->assertSame('-360.0000', (string) $ledger->accountBalance($this->account('4101')));
        $this->assertSame('-50.4000', (string) $ledger->accountBalance($this->account('2103')));
        $this->assertSame('240.0000', (string) $ledger->accountBalance($this->account('5101')));
        $this->assertStockConsistent();
        $this->assertLedgerBalanced();
    }

    public function test_the_customers_price_list_sets_the_default_price(): void
    {
        $list = app(PriceListActions::class)->save($this->admin, [
            'name_ar' => 'جملة', 'items' => [['product_id' => $this->product->id, 'unit_id' => $this->carton->id, 'price' => '150']],
        ]);
        app(PriceListActions::class)->assign($this->admin, $this->customer, $list->id);

        $invoice = app(InvoiceActions::class)->save($this->admin, $this->data());

        $this->assertSame($list->id, $invoice->price_list_id);
        $this->assertSame('150.0000', (string) $invoice->lines()->first()->unit_price);
    }

    public function test_payment_taken_at_posting_becomes_an_allocated_receipt(): void
    {
        $invoice = $this->sell(['payment_method_id' => PaymentMethod::where('type', 'cash')->value('id'), 'paid_amount' => '410.40']);

        $receipt = ReceiptVoucher::sole();
        $this->assertSame(DocumentStatus::Posted, $receipt->status);
        $this->assertSame($invoice->number, $receipt->reference);
        $this->assertTrue(app(Ledger::class)->partnerBalance($this->customer, AccountSubtype::Receivable)->isZero());
        $this->assertTrue(app(Reconciler::class)->residual($invoice->journalEntry->lines()->where('debit', '>', 0)->where('partner_id', $this->customer->id)->first())->isZero());
        $this->assertSame('410.4000', (string) app(Ledger::class)->accountBalance($this->account('120101')));
    }

    public function test_the_credit_limit_warns_blocks_and_can_be_overridden(): void
    {
        $this->customer->update(['credit_limit' => '300']);

        // warn (default): needs confirmation
        try {
            $this->sell();
            $this->fail('Posted past the limit without confirmation.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('credit_limit_confirm', $e->errors());
        }
        $this->assertSame(DocumentStatus::Posted, $this->sell(confirm: true)->status);

        // block: refused even when confirmed, unless the user may override
        app(Settings::class)->set('sales.credit_limit_mode', 'block');
        $clerk = User::factory()->create();
        $clerk->givePermissionTo(['sales.invoices.create', 'sales.invoices.post']);
        $clerk->branches()->attach($this->branch);

        $draft = app(InvoiceActions::class)->save($clerk, $this->data());
        try {
            app(InvoiceActions::class)->post($clerk, $draft, true);
            $this->fail('Posted past a blocking limit.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('credit_limit', $e->errors());
        }

        $clerk->givePermissionTo('sales.credit_limit.override');
        $this->assertSame(DocumentStatus::Posted, app(InvoiceActions::class)->post($clerk->fresh(), $draft, true)->status);
    }

    public function test_a_return_brings_stock_back_at_its_original_cost_and_reduces_the_invoice(): void
    {
        $invoice = $this->sell();
        // A later, more expensive receipt must not change what the returned goods are worth.
        $this->receive([new StockLineData($this->product->id, $this->warehouse->id, '96', '20')], null, StockMoveType::Opening, 'opening_balance_equity');

        $returns = app(ReturnActions::class);
        $return = $returns->post($this->admin, $returns->save($this->admin, $invoice, [
            'date' => now()->toDateString(),
            'lines' => [['sales_invoice_line_id' => $invoice->lines()->value('id'), 'quantity' => '1']],
        ]));

        $this->assertStringStartsWith('SR-', $return->number);
        $this->assertSame('120.0000', (string) $return->lines()->first()->cost);
        $this->assertSame('120.0000', (string) app(Ledger::class)->accountBalance($this->account('5101')));
        $this->assertSame('180.0000', (string) app(Ledger::class)->accountBalance($this->account('4102')));
        $this->assertSame('205.2000', (string) app(Ledger::class)->partnerBalance($this->customer, AccountSubtype::Receivable));
        $this->assertSame('205.2000', (string) app(Reconciler::class)->residual($invoice->journalEntry->lines()->where('debit', '>', 0)->where('partner_id', $this->customer->id)->first()));
        $this->assertStockConsistent();
        $this->assertLedgerBalanced();
    }

    public function test_selling_more_than_in_stock_is_refused_and_nothing_is_posted(): void
    {
        try {
            $this->sell(lines: [['product_id' => $this->product->id, 'unit_id' => $this->carton->id, 'quantity' => '11']]);
            $this->fail('Sold more than on hand.');
        } catch (ValidationException) {
            $this->assertSame(DocumentStatus::Draft, SalesInvoice::sole()->status);
            $this->assertSame('120.0000', $this->onHand($this->product->id, $this->warehouse->id));
        }
    }

    public function test_a_paid_invoice_cannot_be_cancelled_but_an_unpaid_one_restores_stock(): void
    {
        $paid = $this->sell(['payment_method_id' => PaymentMethod::where('type', 'cash')->value('id'), 'paid_amount' => '100']);

        try {
            app(InvoiceActions::class)->cancel($this->admin, $paid, 'x');
            $this->fail('A paid invoice was cancelled.');
        } catch (ValidationException) {
            $this->assertSame(DocumentStatus::Posted, $paid->fresh()->status);
        }

        $unpaid = $this->sell();
        app(InvoiceActions::class)->cancel($this->admin, $unpaid, 'Wrong customer');

        $this->assertSame(DocumentStatus::Cancelled, $unpaid->fresh()->status);
        $this->assertSame('96.0000', $this->onHand($this->product->id, $this->warehouse->id));
        $this->assertStockConsistent();
    }

    public function test_the_installer_created_a_walk_in_customer(): void
    {
        $walkIn = Partner::findOrFail(app(Settings::class)->get('sales.walk_in_partner_id'));

        $this->assertTrue($walkIn->is_customer);
        $this->assertSame('عميل نقدي', $walkIn->name);
    }
}
