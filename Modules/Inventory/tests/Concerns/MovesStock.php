<?php

namespace Modules\Inventory\Tests\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Partner;
use Modules\Inventory\Enums\StockMoveType;
use Modules\Inventory\Models\ProductCost;
use Modules\Inventory\Models\StockBalance;
use Modules\Inventory\Stock\Actions\IssueStock;
use Modules\Inventory\Stock\Actions\ReceiveStock;
use Modules\Inventory\Stock\StockConsistency;
use Modules\Inventory\Stock\StockLineData;
use Modules\Inventory\Stock\StockOperationData;
use Modules\Inventory\Stock\StockResult;

trait MovesStock
{
    /**
     * A distinct source document per operation (any model works in engine tests).
     */
    protected function newSource(): Model
    {
        return Partner::factory()->create();
    }

    /**
     * @param  StockLineData[]  $lines
     */
    protected function receive(array $lines, ?Model $source = null, StockMoveType $type = StockMoveType::Purchase, string $counter = 'purchases.grni'): StockResult
    {
        return DB::transaction(fn () => app(ReceiveStock::class)->handle(new StockOperationData(
            CarbonImmutable::today(), $this->branch->id, $type, $source ?? $this->newSource(), $lines, $counter, postedBy: $this->admin,
        )));
    }

    /**
     * @param  StockLineData[]  $lines
     */
    protected function issue(array $lines, ?Model $source = null, StockMoveType $type = StockMoveType::Sale, string $counter = 'inventory.cogs'): StockResult
    {
        return DB::transaction(fn () => app(IssueStock::class)->handle(new StockOperationData(
            CarbonImmutable::today(), $this->branch->id, $type, $source ?? $this->newSource(), $lines, $counter, postedBy: $this->admin,
        )));
    }

    protected function onHand(int $productId, int $warehouseId, ?int $batchId = null): string
    {
        return (string) (StockBalance::where(['product_id' => $productId, 'warehouse_id' => $warehouseId, 'batch_id' => $batchId])->first()?->quantity ?? '0.0000');
    }

    protected function cost(int $productId): ProductCost
    {
        return ProductCost::findOrFail($productId);
    }

    protected function assertStockConsistent(): void
    {
        $this->assertSame([], app(StockConsistency::class)->differences());
    }
}
