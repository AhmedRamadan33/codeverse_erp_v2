<?php

namespace Modules\Pos\Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Accounting\Enums\AccountSubtype;
use Modules\Accounting\Ledger\Ledger;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Accounting\Models\Tax;
use Modules\Accounting\Tests\Concerns\PostsEntries;
use Modules\Core\Models\Partner;
use Modules\Inventory\Enums\StockMoveType;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Stock\StockLineData;
use Modules\Inventory\Tests\Concerns\MovesStock;
use Modules\Pos\Actions\ReceiptActions;
use Modules\Pos\Actions\RegisterActions;
use Modules\Pos\Actions\ShiftActions;
use Modules\Pos\Enums\ShiftStatus;
use Modules\Pos\Events\PosReceiptCompleted;
use Modules\Pos\Models\Receipt;
use Modules\Pos\Models\Register;
use Modules\Pos\Models\Shift;
use Modules\Products\Models\Product;
use Modules\Sales\Livewire\Reports\SalesAnalysis;
use Tests\Concerns\InstallsErp;
use Tests\TestCase;

class PosTest extends TestCase
{
    use InstallsErp, MovesStock, PostsEntries, RefreshDatabase;

    private Warehouse $warehouse;

    private Product $product;

    private Register $register;

    private Shift $shift;

    private int $cash;

    private int $bank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installErp();
        $this->warehouse = Warehouse::where('branch_id', $this->branch->id)->firstOrFail();
        $this->cash = PaymentMethod::where('type', 'cash')->value('id');
        $this->bank = PaymentMethod::where('type', 'bank_transfer')->value('id');
        $this->product = Product::factory()->create(['sale_price' => '10', 'sale_tax_id' => Tax::firstWhere('code', 'VAT14')->id]);

        // 100 units at 6 each.
        $this->receive([new StockLineData($this->product->id, $this->warehouse->id, '100', '6')], null, StockMoveType::Opening, 'opening_balance_equity');

        $this->register = app(RegisterActions::class)->save($this->admin, [
            'code' => 'R1', 'name_ar' => 'كاشير 1', 'warehouse_id' => $this->warehouse->id, 'cash_payment_method_id' => $this->cash,
        ]);
        $this->shift = app(ShiftActions::class)->open($this->admin, $this->register, '100');
    }

    /**
     * @param  array<int, array{0: int, 1: string}>  $payments  [method id, amount]
     */
    private function sell(string $quantity = '2', array $payments = [], ?Partner $customer = null, array $extra = [], ?User $as = null): Receipt
    {
        return app(ReceiptActions::class)->sell($as ?? $this->admin, array_merge([
            'partner_id' => $customer?->id,
            'lines' => [['product_id' => $this->product->id, 'unit_id' => $this->product->base_unit_id, 'quantity' => $quantity]],
            'payments' => array_map(fn ($p) => ['payment_method_id' => $p[0], 'amount' => $p[1]], $payments),
        ], $extra));
    }

    private function returnOf(Receipt $sale, string $quantity, array $payments = []): Receipt
    {
        return app(ReceiptActions::class)->return($this->admin, $sale, [
            'lines' => [['original_line_id' => $sale->lines()->value('id'), 'quantity' => $quantity]],
            'payments' => $payments,
        ]);
    }

    private function close(string $counted): Shift
    {
        return app(ShiftActions::class)->close($this->admin, $this->shift, $counted);
    }

    private function balance(string $code): string
    {
        return (string) app(Ledger::class)->accountBalance($this->account($code));
    }

    public function test_a_paid_sale_gives_change_and_posts_nothing_until_the_shift_closes(): void
    {
        Event::fake([PosReceiptCompleted::class]);

        // 2 × 10 + 14% = 22.80, paid with 50 cash.
        $receipt = $this->sell('2', [[$this->cash, '50']]);

        $this->assertStringStartsWith('R-', $receipt->number);
        $this->assertSame('22.8000', (string) $receipt->total);
        $this->assertSame('22.8000', (string) $receipt->paid_total);
        $this->assertSame('27.2000', (string) $receipt->change);
        $this->assertSame('22.8000', (string) $receipt->payments()->sole()->amount);
        $this->assertFalse($receipt->is_credit);
        $this->assertNull($receipt->journal_entry_id);
        $this->assertSame('12.0000', (string) $receipt->lines()->sole()->cost);
        $this->assertSame('98.0000', $this->onHand($this->product->id, $this->warehouse->id));
        $this->assertNull(StockMove::where('source_type', $receipt->getMorphClass())->sole()->journal_entry_id);
        $this->assertSame('0.0000', $this->balance('4101'));
        Event::assertDispatched(PosReceiptCompleted::class);
    }

    public function test_closing_the_shift_posts_payments_revenue_tax_cost_and_the_cash_short(): void
    {
        $this->sell('2', [[$this->cash, '10'], [$this->bank, '12.80']]);
        $this->sell('1', [[$this->cash, '11.40']]);

        // Expected: 100 float + 21.40 cash; one pound missing.
        $shift = $this->close('120.40');

        $this->assertSame(ShiftStatus::Closed, $shift->status);
        $this->assertSame('121.4000', (string) $shift->expected_cash);
        $this->assertSame('-1.0000', (string) $shift->cash_difference);
        $this->assertSame('20.4000', $this->balance('120101'));
        $this->assertSame('12.8000', $this->balance('120201'));
        $this->assertSame('-30.0000', $this->balance('4101'));
        $this->assertSame('-4.2000', $this->balance('2103'));
        $this->assertSame('1.0000', $this->balance('5403'));
        $this->assertSame('18.0000', $this->balance('5101'));
        $this->assertNotNull($shift->journal_entry_id);
        $this->assertSame(0, StockMove::where('source_type', (new Receipt)->getMorphClass())->whereNull('journal_entry_id')->count());
        $this->assertLedgerBalanced();
        $this->assertStockConsistent();
    }

    public function test_a_credit_sale_posts_its_own_entries_and_only_its_cash_reaches_the_drawer(): void
    {
        $customer = Partner::factory()->create(['payment_term_days' => 30]);

        $receipt = $this->sell('5', [[$this->cash, '7']], $customer);

        // 57 total, 7 paid now, 50 on account.
        $this->assertTrue($receipt->is_credit);
        $this->assertSame(now()->addDays(30)->toDateString(), $receipt->due_date->toDateString());
        $this->assertSame('50.0000', (string) app(Ledger::class)->partnerBalance($customer, AccountSubtype::Receivable));
        $this->assertSame('-50.0000', $this->balance('4101'));
        $this->assertSame('30.0000', $this->balance('5101'));

        $shift = $this->close('107');

        $this->assertSame('107.0000', (string) $shift->expected_cash);
        $this->assertNull($shift->journal_entry_id);
        $this->assertNull($shift->valuation_entry_id);
        $this->assertSame('7.0000', $this->balance('120101'));
        $this->assertLedgerBalanced();
    }

    public function test_the_walk_in_customer_cannot_buy_on_account(): void
    {
        $this->expectException(ValidationException::class);

        $this->sell('2', [[$this->cash, '10']]);
    }

    public function test_only_cash_can_be_overpaid(): void
    {
        $this->expectException(ValidationException::class);

        $this->sell('2', [[$this->bank, '30']]);
    }

    public function test_a_return_in_the_shift_is_refunded_and_netted_at_the_original_cost(): void
    {
        $sale = $this->sell('4', [[$this->cash, '45.60']]);
        // A dearer receipt later must not change what the returned goods are worth.
        $this->receive([new StockLineData($this->product->id, $this->warehouse->id, '10', '20')], null, StockMoveType::Opening, 'opening_balance_equity');

        $return = $this->returnOf($sale, '1');

        $this->assertSame('11.4000', (string) $return->total);
        $this->assertSame('6.0000', (string) $return->lines()->sole()->cost);
        $this->assertSame($this->cash, $return->payments()->sole()->payment_method_id);

        $shift = $this->close('134.20');

        $this->assertSame('0.0000', (string) $shift->cash_difference);
        $this->assertSame('34.2000', $this->balance('120101'));
        $this->assertSame('-40.0000', $this->balance('4101'));
        $this->assertSame('10.0000', $this->balance('4102'));
        $this->assertSame('-4.2000', $this->balance('2103'));
        $this->assertSame('18.0000', $this->balance('5101'));
        $this->assertSame('1.0000', (string) $sale->lines()->sole()->returnableQuantity()->minus('2'));

        try {
            $this->returnOf($sale, '4');
            $this->fail('Returned more than was sold.');
        } catch (ValidationException) {
            $this->assertLedgerBalanced();
            $this->assertStockConsistent();
        }
    }

    public function test_returning_a_credit_sale_reduces_what_the_customer_owes(): void
    {
        $customer = Partner::factory()->create();
        $sale = $this->sell('5', [], $customer);

        $return = $this->returnOf($sale, '2');

        $this->assertTrue($return->is_credit);
        $this->assertSame(0, $return->payments()->count());
        $this->assertSame('34.2000', (string) app(Ledger::class)->partnerBalance($customer, AccountSubtype::Receivable));
        $this->assertSame('18.0000', $this->balance('5101'));
        $this->assertLedgerBalanced();
        $this->assertStockConsistent();
    }

    public function test_prices_and_discounts_need_their_permissions(): void
    {
        $cashier = User::factory()->create();
        $cashier->givePermissionTo('pos.terminal.sell');
        $cashier->branches()->attach($this->branch);
        $other = app(RegisterActions::class)->save($this->admin, [
            'code' => 'R2', 'name_ar' => 'كاشير 2', 'warehouse_id' => $this->warehouse->id, 'cash_payment_method_id' => $this->cash,
        ]);
        app(ShiftActions::class)->open($cashier, $other, '0');

        $line = ['product_id' => $this->product->id, 'unit_id' => $this->product->base_unit_id, 'quantity' => '1'];
        foreach ([['lines' => [$line + ['unit_price' => '8']]], ['lines' => [$line], 'discount_type' => 'percent', 'discount_value' => '10']] as $extra) {
            try {
                $this->sell('1', [[$this->cash, '20']], null, $extra, $cashier);
                $this->fail('Sold without the permission.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $cashier->givePermissionTo(['pos.prices.override', 'pos.discounts.give']);
        $receipt = $this->sell('1', [[$this->cash, '20']], null, ['lines' => [$line + ['unit_price' => '8']], 'discount_type' => 'percent', 'discount_value' => '10'], $cashier->fresh());
        // 8 less 10% = 7.20, tax 1.008 rounds to 1.01.
        $this->assertSame('8.2100', (string) $receipt->total);
    }

    public function test_a_register_and_a_cashier_have_at_most_one_open_shift(): void
    {
        $other = app(RegisterActions::class)->save($this->admin, [
            'code' => 'R2', 'name_ar' => 'كاشير 2', 'warehouse_id' => $this->warehouse->id, 'cash_payment_method_id' => $this->cash,
        ]);
        $cashier = User::factory()->create();
        $cashier->givePermissionTo('pos.terminal.sell');
        $cashier->branches()->attach($this->branch);

        foreach ([[$this->admin, $other], [$cashier, $this->register]] as [$user, $register]) {
            try {
                app(ShiftActions::class)->open($user, $register, '0');
                $this->fail('Opened a second shift.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        // Someone else's shift needs pos.shifts.manage to close.
        $this->expectException(AuthorizationException::class);
        app(ShiftActions::class)->close($cashier, $this->shift, '100');
    }

    public function test_pos_sales_and_returns_appear_in_the_sales_report(): void
    {
        $sale = $this->sell('3', [[$this->cash, '34.20']]);
        $this->returnOf($sale, '1');

        // 2 units net: 20 sales, 12 cost.
        $this->actingAs($this->admin);
        Livewire::test(SalesAnalysis::class)
            ->set('groupBy', 'channel')
            ->assertSee(__('sales::reports.channels.pos'))
            ->assertSee('20.00')
            ->assertSee('12.00');
    }

    public function test_selling_without_an_open_shift_is_refused(): void
    {
        $this->close('100');

        $this->expectException(ValidationException::class);
        $this->sell('1', [[$this->cash, '11.40']]);
    }
}
