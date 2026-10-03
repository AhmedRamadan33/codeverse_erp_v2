<?php

namespace Modules\Inventory\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Modules\Accounting\Ledger\Ledger;
use Modules\Accounting\Tests\Concerns\PostsEntries;
use Modules\Core\Settings\Settings;
use Modules\Inventory\Enums\SerialStatus;
use Modules\Inventory\Enums\StockMoveType;
use Modules\Inventory\Models\StockBatch;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockSerial;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Stock\Actions\ReverseStock;
use Modules\Inventory\Stock\Actions\TransferStock;
use Modules\Inventory\Stock\StockLineData as Line;
use Modules\Inventory\Stock\StockOperationData;
use Modules\Inventory\Tests\Concerns\MovesStock;
use Modules\Products\Enums\Tracking;
use Modules\Products\Models\Product;
use Modules\Products\Support\ProductUsage;
use Tests\Concerns\InstallsErp;
use Tests\TestCase;

class StockEngineTest extends TestCase
{
    use InstallsErp, MovesStock, PostsEntries, RefreshDatabase;

    private Warehouse $main;

    private Warehouse $second;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installErp();
        $this->main = Warehouse::factory()->create(['branch_id' => $this->branch->id]);
        $this->second = Warehouse::factory()->create(['branch_id' => $this->branch->id]);
        $this->product = Product::factory()->create();
    }

    public function test_receipts_build_the_weighted_average_and_post_inventory_against_grni(): void
    {
        $this->receive([new Line($this->product->id, $this->main->id, '10', '100')]);
        $result = $this->receive([new Line($this->product->id, $this->main->id, '30', '120')]);

        $cost = $this->cost($this->product->id);
        $this->assertSame('40.0000', (string) $cost->quantity_on_hand);
        $this->assertSame('4600.0000', (string) $cost->total_value);
        $this->assertSame('115.0000', (string) $cost->average_cost);
        $this->assertSame('3600.0000', (string) $result->totalCost());
        $this->assertNotNull($result->journalEntry);

        $ledger = app(Ledger::class);
        $this->assertSame('4600.0000', (string) $ledger->accountBalance($this->account('1205')));
        $this->assertSame('-4600.0000', (string) $ledger->accountBalance($this->account('2105')));
        $this->assertStockConsistent();
        $this->assertLedgerBalanced();
    }

    public function test_issues_take_a_proportional_share_and_the_last_unit_takes_what_is_left(): void
    {
        foreach (['10', '10', '10.01'] as $unitCost) {
            $this->receive([new Line($this->product->id, $this->main->id, '1', $unitCost)]);
        }

        $costs = [];
        foreach (range(1, 3) as $_) {
            $costs[] = (string) $this->issue([new Line($this->product->id, $this->main->id, '1')])->totalCost();
        }

        $this->assertSame(['10.0033', '10.0034', '10.0033'], $costs);
        $this->assertSame('0.0000', (string) $this->cost($this->product->id)->total_value);
        $this->assertSame('30.0100', (string) app(Ledger::class)->accountBalance($this->account('5101')));
        $this->assertTrue(app(Ledger::class)->accountBalance($this->account('1205'))->isZero());
        $this->assertStockConsistent();
    }

    public function test_issuing_more_than_on_hand_is_refused_unless_negative_stock_is_allowed(): void
    {
        $this->receive([new Line($this->product->id, $this->main->id, '5', '20')]);

        try {
            $this->issue([new Line($this->product->id, $this->main->id, '6')]);
            $this->fail('Issued more than on hand.');
        } catch (ValidationException) {
            $this->assertSame('5.0000', $this->onHand($this->product->id, $this->main->id));
        }

        // Stock in another warehouse does not count.
        $this->receive([new Line($this->product->id, $this->second->id, '10', '20')]);
        $this->expectException(ValidationException::class);
        $this->issue([new Line($this->product->id, $this->main->id, '6')]);
    }

    public function test_negative_stock_when_allowed_is_costed_at_the_last_average(): void
    {
        app(Settings::class)->set('inventory.allow_negative_stock', true);
        $this->receive([new Line($this->product->id, $this->main->id, '2', '50')]);

        $this->issue([new Line($this->product->id, $this->main->id, '3')]);

        $this->assertSame('-1.0000', $this->onHand($this->product->id, $this->main->id));
        $this->assertSame('-50.0000', (string) $this->cost($this->product->id)->total_value);
        $this->assertStockConsistent();
    }

    public function test_batches_are_issued_first_expiry_first(): void
    {
        $milk = Product::factory()->tracking(Tracking::Batch)->create();
        $this->receive([
            new Line($milk->id, $this->main->id, '5', '10', batchNumber: 'LATE', expiryDate: CarbonImmutable::parse('2027-06-01')),
            new Line($milk->id, $this->main->id, '5', '10', batchNumber: 'SOON', expiryDate: CarbonImmutable::parse('2027-01-01')),
        ]);

        $moves = $this->issue([new Line($milk->id, $this->main->id, '7')])->moves();

        $soon = StockBatch::firstWhere('batch_number', 'SOON');
        $late = StockBatch::firstWhere('batch_number', 'LATE');
        $this->assertSame([$soon->id, $late->id], array_map(fn ($m) => $m->batch_id, $moves));
        $this->assertSame('0.0000', $this->onHand($milk->id, $this->main->id, $soon->id));
        $this->assertSame('3.0000', $this->onHand($milk->id, $this->main->id, $late->id));

        $this->expectException(ValidationException::class);
        $this->receive([new Line($milk->id, $this->main->id, '1', '10')]); // batch number required
    }

    public function test_serials_follow_each_unit(): void
    {
        $phone = Product::factory()->tracking(Tracking::Serial)->create();
        $this->receive([new Line($phone->id, $this->main->id, '2', '9000', serials: ['IMEI-1', 'IMEI-2'])]);

        $this->issue([new Line($phone->id, $this->main->id, '1', serials: ['IMEI-2'])]);

        $this->assertSame(SerialStatus::InStock, StockSerial::firstWhere('serial_number', 'IMEI-1')->status);
        $this->assertSame(SerialStatus::Out, StockSerial::firstWhere('serial_number', 'IMEI-2')->status);

        foreach ([
            fn () => $this->issue([new Line($phone->id, $this->main->id, '1', serials: ['IMEI-2'])]),
            fn () => $this->issue([new Line($phone->id, $this->main->id, '1', serials: ['IMEI-1', 'IMEI-3'])]),
            fn () => $this->receive([new Line($phone->id, $this->main->id, '1', '9000', serials: ['IMEI-1'])]),
        ] as $i => $rejected) {
            try {
                $rejected();
                $this->fail("Serial case {$i} was accepted.");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_transfers_move_stock_without_changing_value_or_posting(): void
    {
        $this->receive([new Line($this->product->id, $this->main->id, '10', '30')]);
        $entriesBefore = DB::table('journal_entries')->count();

        $result = DB::transaction(fn () => app(TransferStock::class)->handle(new StockOperationData(
            CarbonImmutable::today(), $this->branch->id, StockMoveType::TransferOut, $this->newSource(),
            [new Line($this->product->id, $this->main->id, '4')], 'inventory.asset', postedBy: $this->admin,
        ), $this->second->id));

        $this->assertNull($result->journalEntry);
        $this->assertSame($entriesBefore, DB::table('journal_entries')->count());
        $this->assertSame('6.0000', $this->onHand($this->product->id, $this->main->id));
        $this->assertSame('4.0000', $this->onHand($this->product->id, $this->second->id));
        $this->assertSame('300.0000', (string) $this->cost($this->product->id)->total_value);
        $this->assertStockConsistent();
    }

    public function test_reversing_a_document_restores_stock_and_reverses_its_entry(): void
    {
        $this->receive([new Line($this->product->id, $this->main->id, '10', '30')]);
        $sale = $this->newSource();
        $this->issue([new Line($this->product->id, $this->main->id, '4')], $sale);

        DB::transaction(fn () => app(ReverseStock::class)->handle($sale, CarbonImmutable::today(), 'Cancelled', $this->branch->id, $this->admin));

        $this->assertSame('10.0000', $this->onHand($this->product->id, $this->main->id));
        $this->assertSame('300.0000', (string) $this->cost($this->product->id)->total_value);
        $this->assertTrue(app(Ledger::class)->accountBalance($this->account('5101'))->isZero());
        $this->assertStockConsistent();
        $this->assertLedgerBalanced();

        // Reversing twice does nothing.
        DB::transaction(fn () => app(ReverseStock::class)->handle($sale, CarbonImmutable::today(), 'Again', $this->branch->id, $this->admin));
        $this->assertSame('10.0000', $this->onHand($this->product->id, $this->main->id));
    }

    public function test_moves_are_append_only_and_lock_the_products_core_fields(): void
    {
        $this->receive([new Line($this->product->id, $this->main->id, '1', '5')]);

        $this->assertTrue(app(ProductUsage::class)->inUse($this->product));

        $this->expectException(LogicException::class);
        StockMove::first()->update(['quantity' => '2']);
    }
}
