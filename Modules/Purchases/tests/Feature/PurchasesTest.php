<?php

namespace Modules\Purchases\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Enums\AccountSubtype;
use Modules\Accounting\Ledger\Ledger;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Accounting\Models\Tax;
use Modules\Accounting\Reconciliation\Reconciler;
use Modules\Accounting\Tests\Concerns\PostsEntries;
use Modules\Accounting\Vouchers\Actions\PostVoucher;
use Modules\Accounting\Vouchers\Actions\SaveVoucher;
use Modules\Accounting\Vouchers\VoucherKind;
use Modules\Core\Documents\DocumentStatus;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Partner;
use Modules\Inventory\Enums\StockMoveType;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Stock\StockLineData;
use Modules\Inventory\Tests\Concerns\MovesStock;
use Modules\Products\Enums\ProductType;
use Modules\Products\Models\Product;
use Modules\Products\Models\Unit;
use Modules\Purchases\Actions\InvoiceActions;
use Modules\Purchases\Actions\ReturnActions;
use Modules\Purchases\Models\PurchaseInvoice;
use Tests\Concerns\InstallsErp;
use Tests\TestCase;

class PurchasesTest extends TestCase
{
    use InstallsErp, MovesStock, PostsEntries, RefreshDatabase;

    private Partner $supplier;

    private Warehouse $warehouse;

    private Product $rice;

    private Product $freight;

    private Unit $carton;

    private Tax $vat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installErp();
        $this->supplier = Partner::factory()->supplier()->create(['payment_term_days' => 30]);
        $this->warehouse = Warehouse::where('branch_id', $this->branch->id)->firstOrFail();
        $this->carton = Unit::where('name->en', 'Carton')->firstOrFail();
        $this->rice = Product::factory()->withUnit($this->carton, '10')->create();
        $this->freight = Product::factory()->create(['type' => ProductType::Service]);
        $this->vat = Tax::firstWhere('code', 'VAT14');
    }

    private function invoice(array $overrides = [], ?array $lines = null): PurchaseInvoice
    {
        $actions = app(InvoiceActions::class);

        return $actions->post($this->admin, $actions->save($this->admin, array_merge([
            'date' => now()->toDateString(),
            'partner_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => Currency::where('code', 'EGP')->value('id'),
            'supplier_reference' => 'S-77',
            'discount_type' => 'amount', 'discount_value' => '100',
            'lines' => $lines ?? [
                // 5 cartons × 500 = 2,500 less 10% = 2,250
                ['product_id' => $this->rice->id, 'unit_id' => $this->carton->id, 'quantity' => '5', 'unit_price' => '500', 'discount_type' => 'percent', 'discount_value' => '10', 'tax_id' => $this->vat->id],
                // service 250, no tax
                ['product_id' => $this->freight->id, 'unit_id' => $this->freight->base_unit_id, 'quantity' => '1', 'unit_price' => '250'],
            ],
        ], $overrides)));
    }

    public function test_an_invoice_receives_stock_at_net_cost_and_owes_the_supplier(): void
    {
        $invoice = $this->invoice();

        // Document discount 100 split 2250:250 → 90 and 10. Rice net 2,160, tax 302.40; freight 240.
        $this->assertSame(DocumentStatus::Posted, $invoice->status);
        $this->assertStringStartsWith('PI-', $invoice->number);
        $this->assertSame('2702.4000', (string) $invoice->total);
        $this->assertSame(now()->addDays(30)->toDateString(), $invoice->due_date->toDateString());

        $this->assertSame('50.0000', $this->onHand($this->rice->id, $this->warehouse->id));
        $this->assertSame('43.2000', (string) $this->cost($this->rice->id)->average_cost);

        $ledger = app(Ledger::class);
        $this->assertSame('2160.0000', (string) $ledger->accountBalance($this->account('1205')));
        $this->assertTrue($ledger->accountBalance($this->account('2105'))->isZero(), 'GRNI nets to zero');
        $this->assertSame('302.4000', (string) $ledger->accountBalance($this->account('1206')));
        $this->assertSame('240.0000', (string) $ledger->accountBalance($this->account('5211')));
        $this->assertSame('-2702.4000', (string) $ledger->partnerBalance($this->supplier, AccountSubtype::Payable));
        $this->assertStockConsistent();
        $this->assertLedgerBalanced();
    }

    public function test_returns_go_out_at_average_cost_and_reduce_the_open_invoice(): void
    {
        $invoice = $this->invoice(['discount_value' => null], [
            ['product_id' => $this->rice->id, 'unit_id' => $this->carton->id, 'quantity' => '4', 'unit_price' => '400', 'tax_id' => $this->vat->id],
        ]);
        // A cheaper receipt lowers the average below the invoice price: 40 + 30 → 35 per unit.
        $this->receive([new StockLineData($this->rice->id, $this->warehouse->id, '40', '30')], null, StockMoveType::Opening, 'opening_balance_equity');
        $line = $invoice->lines()->first();

        $returns = app(ReturnActions::class);
        $return = $returns->post($this->admin, $returns->save($this->admin, $invoice, [
            'date' => now()->toDateString(),
            'lines' => [['purchase_invoice_line_id' => $line->id, 'quantity' => '1']],
        ]));

        // One carton: net 400 + tax 56. Stock leaves at 10 × 35 = 350; 50 is a price-difference gain.
        $this->assertSame('456.0000', (string) $return->total);
        $this->assertSame('350.0000', (string) $return->lines()->first()->stock_cost);
        $this->assertSame('-50.0000', (string) app(Ledger::class)->accountBalance($this->account('5102')));
        $this->assertSame('1368.0000', (string) app(Reconciler::class)->residual($invoice->journalEntry->lines()->where('credit', '>', 0)->first()));
        $this->assertTrue(app(Ledger::class)->accountBalance($this->account('2105'))->isZero());

        // The rest returns exactly what is left of the line.
        $last = $returns->post($this->admin, $returns->save($this->admin, $invoice, [
            'date' => now()->toDateString(),
            'lines' => [['purchase_invoice_line_id' => $line->id, 'quantity' => '3']],
        ]));
        $this->assertSame('1200.0000', (string) $last->lines()->first()->net);

        $this->expectException(ValidationException::class);
        $returns->save($this->admin, $invoice, ['date' => now()->toDateString(), 'lines' => [['purchase_invoice_line_id' => $line->id, 'quantity' => '1']]]);
    }

    public function test_a_paid_invoice_cannot_be_cancelled_but_an_unpaid_one_can(): void
    {
        $paid = $this->invoice();
        $voucher = app(SaveVoucher::class)->handle($this->admin, VoucherKind::Payment, [
            'date' => now()->toDateString(), 'branch_id' => $this->branch->id, 'partner_id' => $this->supplier->id,
            'payment_method_id' => PaymentMethod::where('type', 'cash')->value('id'),
            'currency_id' => Currency::where('code', 'EGP')->value('id'), 'amount' => '1000',
        ]);
        app(PostVoucher::class)->handle($this->admin, $voucher, [$paid->journalEntry->lines()->where('credit', '>', 0)->where('partner_id', $this->supplier->id)->value('id') => '1000']);

        try {
            app(InvoiceActions::class)->cancel($this->admin, $paid, 'Wrong');
            $this->fail('A paid invoice was cancelled.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $unpaid = $this->invoice(['supplier_reference' => 'S-78']);
        app(InvoiceActions::class)->cancel($this->admin, $unpaid, 'Duplicate');

        $this->assertSame(DocumentStatus::Cancelled, $unpaid->fresh()->status);
        $this->assertSame('50.0000', $this->onHand($this->rice->id, $this->warehouse->id));
        $this->assertStockConsistent();
        $this->assertLedgerBalanced();
    }

    public function test_a_foreign_currency_invoice_posts_base_amounts_with_the_foreign_total(): void
    {
        $usd = Currency::firstWhere('code', 'USD');
        $usd->update(['is_active' => true]);

        $invoice = $this->invoice(['currency_id' => $usd->id, 'exchange_rate' => '48.5', 'discount_value' => null], [
            ['product_id' => $this->rice->id, 'unit_id' => $this->rice->base_unit_id, 'quantity' => '3', 'unit_price' => '33.33'],
        ]);

        $payable = $invoice->journalEntry->lines()->where('partner_id', $this->supplier->id)->first();
        $this->assertSame('4849.5200', (string) $payable->credit);
        $this->assertSame('-99.9900', (string) $payable->amount_currency);
        $this->assertSame('4849.5200', (string) $this->cost($this->rice->id)->total_value);
    }

    public function test_only_suppliers_can_be_invoiced(): void
    {
        $this->expectException(ValidationException::class);

        $customer = Partner::factory()->create();
        validator(['partner_id' => $customer->id] + ['date' => now()->toDateString(), 'warehouse_id' => $this->warehouse->id, 'currency_id' => 1, 'lines' => []], InvoiceActions::rules())->validate();
    }
}
