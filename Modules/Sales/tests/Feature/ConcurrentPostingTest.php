<?php

namespace Modules\Sales\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Documents\DocumentStatus;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Partner;
use Modules\Core\Support\TransactionGuard;
use Modules\Inventory\Enums\StockMoveType;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Stock\StockLineData;
use Modules\Inventory\Tests\Concerns\MovesStock;
use Modules\Products\Models\Product;
use Modules\Sales\Actions\InvoiceActions;
use Modules\Sales\Models\SalesInvoice;
use Tests\Concerns\InstallsErp;
use Tests\TestCase;

/**
 * Two invoices for the last unit, posted by two separate PHP processes at the same moment:
 * exactly one must win and stock must never go negative (core-design.md §14).
 *
 * The other processes must see the data, so this test runs without the usual test
 * transaction and removes every row it created in tearDown.
 */
class ConcurrentPostingTest extends TestCase
{
    use InstallsErp, MovesStock, RefreshDatabase;

    /**
     * Tables written by the test, children first; rows above the starting id are deleted.
     */
    private const TABLES = [
        'audit_logs', 'stock_move_serials' => null, 'stock_moves', 'stock_balances', 'journal_lines', 'journal_entries',
        'sales_invoice_lines', 'sales_invoices', 'product_units', 'products', 'partners',
    ];

    /** @var array<string, int> */
    private array $watermarks = [];

    public function beginDatabaseTransaction(): void
    {
        //
    }

    protected function setUp(): void
    {
        parent::setUp();

        TransactionGuard::$baseline = 0;
        $this->installErp();

        foreach ($this->tables() as $table) {
            $this->watermarks[$table] = (int) DB::table($table)->max('id');
        }
    }

    /**
     * @return string[]
     */
    private function tables(): array
    {
        return array_values(array_filter(array_map(fn ($k, $v) => is_int($k) ? $v : null, array_keys(self::TABLES), self::TABLES)));
    }

    public function test_two_processes_selling_the_last_unit_cannot_both_succeed(): void
    {
        $warehouse = Warehouse::where('branch_id', $this->branch->id)->firstOrFail();
        $product = Product::factory()->create();
        $customer = Partner::factory()->create();
        $this->receive([new StockLineData($product->id, $warehouse->id, '1', '10')], null, StockMoveType::Opening, 'opening_balance_equity');

        $invoiceIds = [];
        foreach (range(1, 2) as $_) {
            $invoiceIds[] = app(InvoiceActions::class)->save($this->admin, [
                'date' => now()->toDateString(), 'partner_id' => $customer->id, 'warehouse_id' => $warehouse->id,
                'currency_id' => Currency::where('code', 'EGP')->value('id'),
                'lines' => [['product_id' => $product->id, 'unit_id' => $product->base_unit_id, 'quantity' => '1']],
            ])->id;
        }

        // Hold the product's cost row so both processes queue on it, then release them together.
        DB::beginTransaction();
        DB::table('product_costs')->where('product_id', $product->id)->lockForUpdate()->first();

        $processes = array_map(fn (int $id) => $this->start($id), $invoiceIds);
        sleep(3);
        DB::commit();

        $outputs = array_map(fn (array $p) => $this->finish($p), $processes);
        sort($outputs);
        $summary = implode(' | ', $outputs);

        $this->assertSame('posted', $outputs[0], $summary);
        $this->assertStringStartsWith('refused', $outputs[1], $summary);
        $this->assertSame(1, SalesInvoice::whereKey($invoiceIds)->where('status', DocumentStatus::Posted)->count());
        $this->assertSame('0.0000', $this->onHand($product->id, $warehouse->id));
        $this->assertStockConsistent();

        DB::table('product_costs')->where('product_id', $product->id)->delete();
    }

    /**
     * @return array{0: resource, 1: array<int, resource>}
     */
    private function start(int $invoiceId): array
    {
        $env = array_merge(array_filter(getenv(), 'is_scalar'), [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => config('database.default'),
            'DB_DATABASE' => DB::connection()->getDatabaseName(),
            'MODULES_ENABLE_ALL' => 'true',
            'MODULES_STATUS_CACHE' => 'false',
        ]);

        $process = proc_open(
            [PHP_BINARY, base_path('tests/Support/post-sales-invoice.php'), (string) $invoiceId, (string) $this->admin->id],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
            $env,
        );

        return [$process, $pipes];
    }

    /**
     * @param  array{0: resource, 1: array<int, resource>}  $started
     */
    private function finish(array $started): string
    {
        [$process, $pipes] = $started;
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        proc_close($process);

        return trim($output);
    }

    protected function tearDown(): void
    {
        DB::statement('set foreign_key_checks = 0');
        foreach ($this->tables() as $table) {
            DB::table($table)->where('id', '>', $this->watermarks[$table] ?? PHP_INT_MAX)->delete();
        }
        DB::statement('set foreign_key_checks = 1');

        parent::tearDown();
    }
}
