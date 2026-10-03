<?php

namespace Modules\Inventory\Stock;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Settings\Settings;
use Modules\Inventory\Enums\SerialStatus;
use Modules\Inventory\Enums\StockMoveType;
use Modules\Inventory\Models\ProductCost;
use Modules\Inventory\Models\StockBalance;
use Modules\Inventory\Models\StockBatch;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockSerial;
use Modules\Inventory\Models\Warehouse;
use Modules\Products\Enums\Tracking;
use Modules\Products\Models\Product;

/**
 * The only writer of stock moves, balances and product costs (internal to Inventory).
 *
 * Weighted average cost per product, company wide (core-design.md §9.3). An issue takes a
 * proportional share of the stock value, and the last unit out takes exactly what is left,
 * so Σ move costs always equals the stock value with no rounding residue.
 */
class StockEngine
{
    /** @var array<int, ProductCost> locked cost rows by product id */
    private array $costs = [];

    public function __construct(private readonly Settings $settings) {}

    /**
     * Lock the cost rows of the products, then their balance rows in the warehouses,
     * always in id order (core-design.md §3.4) so concurrent postings cannot deadlock.
     *
     * @param  int[]  $productIds
     * @param  int[]  $warehouseIds
     */
    public function lock(array $productIds, array $warehouseIds): void
    {
        $productIds = array_values(array_unique($productIds));
        sort($productIds);

        $now = now();
        ProductCost::insertOrIgnore(array_map(fn ($id) => ['product_id' => $id, 'created_at' => $now, 'updated_at' => $now], $productIds));

        $this->costs = ProductCost::whereIn('product_id', $productIds)->orderBy('product_id')->lockForUpdate()->get()->keyBy('product_id')->all();

        // Unbatched balance rows exist before locking, so first receipts are locked too.
        $rows = [];
        foreach ($productIds as $productId) {
            foreach (array_unique($warehouseIds) as $warehouseId) {
                $rows[] = ['product_id' => $productId, 'warehouse_id' => $warehouseId, 'batch_id' => null, 'quantity' => 0, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        StockBalance::insertOrIgnore($rows);

        StockBalance::whereIn('product_id', $productIds)
            ->whereIn('warehouse_id', array_unique($warehouseIds))
            ->orderBy('product_id')->orderBy('warehouse_id')->orderBy('batch_scope')
            ->lockForUpdate()->get();
    }

    /**
     * @return StockMove[]
     */
    public function receive(StockOperationData $op, StockLineData $line, Product $product): array
    {
        $unitCost = $line->unitCost ?? $this->averageCost($product->id);
        $batch = $this->batchForReceipt($product, $line);
        $serials = $this->serialsForReceipt($product, $line, $batch);

        $total = $line->quantity->multipliedBy($unitCost)->toScale(4, RoundingMode::HalfUp);
        $move = $this->apply($op, $line, $product, $line->quantity, $total, $batch?->id);

        $this->attachSerials($move, $serials, SerialStatus::InStock, $line->warehouseId);

        return [$move];
    }

    /**
     * @return StockMove[]
     */
    public function issue(StockOperationData $op, StockLineData $line, Product $product): array
    {
        $moves = [];

        if ($product->tracking === Tracking::Serial) {
            $serials = $this->serialsForIssue($product, $line);
            $move = $this->issueQuantity($op, $line, $product, $line->quantity, null);
            $this->attachSerials($move, $serials, SerialStatus::Out, null);

            return [$move];
        }

        if ($product->tracking === Tracking::Batch) {
            foreach ($this->batchesForIssue($op, $product, $line) as [$batchId, $quantity]) {
                $moves[] = $this->issueQuantity($op, $line, $product, $quantity, $batchId);
            }

            return $moves;
        }

        $this->assertAvailable($op, $line, $product, null, $line->quantity);

        return [$this->issueQuantity($op, $line, $product, $line->quantity, null)];
    }

    /**
     * Moves stock between warehouses at its current value; the product's average is unchanged.
     *
     * @return StockMove[] the out and in moves
     */
    public function transfer(StockOperationData $op, StockLineData $line, Product $product, int $toWarehouseId): array
    {
        $outMoves = $this->issue(new StockOperationData(
            $op->date, $op->branchId, StockMoveType::TransferOut, $op->source, [], $op->counterAccountKey, postedBy: $op->postedBy,
        ), $line, $product);

        $inMoves = [];
        foreach ($outMoves as $out) {
            $in = new StockLineData($product->id, $toWarehouseId, $out->quantity->negated(), sourceLine: $line->sourceLine);
            $inMoves[] = $move = $this->apply(new StockOperationData(
                $op->date, $op->branchId, StockMoveType::TransferIn, $op->source, [], $op->counterAccountKey, postedBy: $op->postedBy,
            ), $in, $product, $out->quantity->negated(), $out->total_cost->negated(), $out->batch_id);

            if ($product->tracking === Tracking::Serial) {
                $this->attachSerials($move, $out->serials->all(), SerialStatus::InStock, $toWarehouseId);
            }
        }

        return [...$outMoves, ...$inMoves];
    }

    /**
     * The exact opposite of a move, at the same cost.
     */
    public function reverse(StockMove $original, StockOperationData $op): StockMove
    {
        $product = Product::findOrFail($original->product_id);
        $quantity = $original->quantity->negated();
        // Keep the original type so a reversed transfer leaves the average untouched too.
        $op = new StockOperationData($op->date, $op->branchId, $original->type, $op->source, [], $op->counterAccountKey, postedBy: $op->postedBy);

        if ($quantity->isNegative()) {
            $line = new StockLineData($product->id, $original->warehouse_id, $quantity->abs());
            $this->assertAvailable($op, $line, $product, $original->batch_id, $quantity->abs());
        }

        $move = $this->apply($op, new StockLineData($product->id, $original->warehouse_id, $quantity->abs()), $product, $quantity, $original->total_cost->negated(), $original->batch_id, $original);

        $serials = $original->serials()->get()->all();
        if ($serials !== []) {
            $this->attachSerials($move, $serials, $quantity->isPositive() ? SerialStatus::InStock : SerialStatus::Out, $quantity->isPositive() ? $original->warehouse_id : null);
        }

        return $move;
    }

    private function issueQuantity(StockOperationData $op, StockLineData $line, Product $product, BigDecimal $quantity, ?int $batchId): StockMove
    {
        return $this->apply($op, $line, $product, $quantity->negated(), $this->costOfIssue($product->id, $quantity)->negated(), $batchId);
    }

    /**
     * Value leaving stock for an issue of $quantity at the weighted average.
     */
    private function costOfIssue(int $productId, BigDecimal $quantity): BigDecimal
    {
        $cost = $this->costs[$productId];
        $onHand = $cost->quantity_on_hand;

        if (! $onHand->isPositive()) {
            // Negative stock (when allowed): last known average.
            return $quantity->multipliedBy($cost->average_cost)->toScale(4, RoundingMode::HalfUp);
        }

        if ($quantity->isGreaterThanOrEqualTo($onHand)) {
            $beyond = $quantity->minus($onHand)->multipliedBy($cost->average_cost);

            return $cost->total_value->plus($beyond)->toScale(4, RoundingMode::HalfUp);
        }

        return $cost->total_value->multipliedBy($quantity)->dividedBy($onHand, 4, RoundingMode::HalfUp);
    }

    private function averageCost(int $productId): BigDecimal
    {
        return $this->costs[$productId]->average_cost;
    }

    private function apply(StockOperationData $op, StockLineData $line, Product $product, BigDecimal $quantity, BigDecimal $totalCost, ?int $batchId, ?StockMove $reversalOf = null): StockMove
    {
        $move = StockMove::create([
            'product_id' => $product->id,
            'warehouse_id' => $line->warehouseId,
            'batch_id' => $batchId,
            'quantity' => $quantity->toScale(4),
            'unit_cost' => $totalCost->abs()->dividedBy($quantity->abs(), 4, RoundingMode::HalfUp),
            'total_cost' => $totalCost->toScale(4),
            'type' => $op->type,
            'source_type' => $op->source->getMorphClass(),
            'source_id' => $op->source->getKey(),
            'source_line_type' => $line->sourceLine?->getMorphClass(),
            'source_line_id' => $line->sourceLine?->getKey(),
            'reversal_of_id' => $reversalOf?->id,
            'date' => $op->date,
            'created_by' => $op->postedBy?->id,
        ]);

        $balance = StockBalance::firstOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $line->warehouseId, 'batch_id' => $batchId],
            ['quantity' => '0'],
        );
        $balance->update(['quantity' => $balance->quantity->plus($quantity)]);

        if (! in_array($op->type, [StockMoveType::TransferIn, StockMoveType::TransferOut], true)) {
            $cost = $this->costs[$product->id];
            $onHand = $cost->quantity_on_hand->plus($quantity);
            $value = $cost->total_value->plus($totalCost);

            $cost->update([
                'quantity_on_hand' => $onHand,
                'total_value' => $value,
                'average_cost' => $onHand->isPositive()
                    ? $value->dividedBy($onHand, 4, RoundingMode::HalfUp)
                    : ($quantity->isPositive() ? $move->unit_cost : $cost->average_cost),
            ]);
        }

        return $move;
    }

    private function assertAvailable(StockOperationData $op, StockLineData $line, Product $product, ?int $batchId, BigDecimal $quantity): void
    {
        if ($this->settings->get('inventory.allow_negative_stock', $op->branchId)) {
            return;
        }

        $available = BigDecimal::of(StockBalance::where('product_id', $product->id)
            ->where('warehouse_id', $line->warehouseId)
            ->when($batchId !== null, fn ($q) => $q->where('batch_id', $batchId))
            ->sum('quantity') ?: 0);

        if ($available->isLessThan($quantity)) {
            throw ValidationException::withMessages(['lines' => __('inventory::moves.insufficient', [
                'product' => $product->name,
                'warehouse' => Warehouse::whereKey($line->warehouseId)->first()?->name,
                'available' => (string) $available->toScale(4)->strippedOfTrailingZeros(),
                'requested' => (string) $quantity->strippedOfTrailingZeros(),
            ])]);
        }
    }

    private function batchForReceipt(Product $product, StockLineData $line): ?StockBatch
    {
        if ($product->tracking !== Tracking::Batch) {
            return null;
        }

        if (blank($line->batchNumber)) {
            throw ValidationException::withMessages(['lines' => __('inventory::moves.batch_required', ['product' => $product->name])]);
        }

        $batch = StockBatch::firstOrCreate(
            ['product_id' => $product->id, 'batch_number' => trim($line->batchNumber)],
            ['expiry_date' => $line->expiryDate],
        );

        if ($batch->expiry_date === null && $line->expiryDate !== null) {
            $batch->update(['expiry_date' => $line->expiryDate]);
        }

        return $batch;
    }

    /**
     * Batches to issue from: the one given, or the earliest expiry first (FEFO).
     *
     * @return array<int, array{int, BigDecimal}> [batch id, quantity]
     */
    private function batchesForIssue(StockOperationData $op, Product $product, StockLineData $line): array
    {
        if (! blank($line->batchNumber)) {
            $batch = StockBatch::where('product_id', $product->id)->where('batch_number', trim($line->batchNumber))->first()
                ?? throw ValidationException::withMessages(['lines' => __('inventory::moves.batch_unknown', ['batch' => $line->batchNumber])]);

            $this->assertAvailable($op, $line, $product, $batch->id, $line->quantity);

            return [[$batch->id, $line->quantity]];
        }

        /** @var Collection<int, StockBalance> $available */
        $available = StockBalance::query()
            ->join('stock_batches', 'stock_batches.id', '=', 'stock_balances.batch_id')
            ->where('stock_balances.product_id', $product->id)
            ->where('stock_balances.warehouse_id', $line->warehouseId)
            ->where('stock_balances.quantity', '>', 0)
            ->orderByRaw('stock_batches.expiry_date is null')
            ->orderBy('stock_batches.expiry_date')
            ->orderBy('stock_batches.id')
            ->get(['stock_balances.batch_id', 'stock_balances.quantity']);

        $remaining = $line->quantity;
        $plan = [];

        foreach ($available as $balance) {
            if (! $remaining->isPositive()) {
                break;
            }
            $take = $remaining->isLessThan($balance->quantity) ? $remaining : $balance->quantity;
            $plan[] = [$balance->batch_id, $take];
            $remaining = $remaining->minus($take);
        }

        if ($remaining->isPositive()) {
            throw ValidationException::withMessages(['lines' => __('inventory::moves.insufficient', [
                'product' => $product->name,
                'warehouse' => Warehouse::find($line->warehouseId)?->name,
                'available' => (string) $line->quantity->minus($remaining)->strippedOfTrailingZeros(),
                'requested' => (string) $line->quantity->strippedOfTrailingZeros(),
            ])]);
        }

        return $plan;
    }

    /**
     * @return StockSerial[]
     */
    private function serialsForReceipt(Product $product, StockLineData $line, ?StockBatch $batch): array
    {
        if ($product->tracking !== Tracking::Serial) {
            return [];
        }

        $numbers = $this->checkSerialCount($product, $line);
        $serials = [];

        foreach ($numbers as $number) {
            $serial = StockSerial::firstOrNew(['product_id' => $product->id, 'serial_number' => $number]);

            if ($serial->exists && $serial->status === SerialStatus::InStock) {
                throw ValidationException::withMessages(['lines' => __('inventory::moves.serial_in_stock', ['serial' => $number])]);
            }

            $serial->fill(['status' => SerialStatus::InStock, 'warehouse_id' => $line->warehouseId, 'batch_id' => $batch?->id])->save();
            $serials[] = $serial;
        }

        return $serials;
    }

    /**
     * @return StockSerial[]
     */
    private function serialsForIssue(Product $product, StockLineData $line): array
    {
        $numbers = $this->checkSerialCount($product, $line);

        $serials = StockSerial::where('product_id', $product->id)->whereIn('serial_number', $numbers)
            ->lockForUpdate()->get()->keyBy('serial_number');

        foreach ($numbers as $number) {
            $serial = $serials[$number] ?? null;

            if ($serial === null || $serial->status !== SerialStatus::InStock || $serial->warehouse_id !== $line->warehouseId) {
                throw ValidationException::withMessages(['lines' => __('inventory::moves.serial_not_available', ['serial' => $number])]);
            }
        }

        return $serials->values()->all();
    }

    /**
     * @return string[]
     */
    private function checkSerialCount(Product $product, StockLineData $line): array
    {
        $numbers = array_values(array_unique(array_filter(array_map('trim', $line->serials))));

        if (! $line->quantity->isEqualTo(count($numbers))) {
            throw ValidationException::withMessages(['lines' => __('inventory::moves.serial_count', [
                'product' => $product->name, 'quantity' => (string) $line->quantity->strippedOfTrailingZeros(),
            ])]);
        }

        return $numbers;
    }

    /**
     * @param  StockSerial[]  $serials
     */
    private function attachSerials(StockMove $move, array $serials, SerialStatus $status, ?int $warehouseId): void
    {
        if ($serials === []) {
            return;
        }

        foreach ($serials as $serial) {
            $serial->update(['status' => $status, 'warehouse_id' => $warehouseId]);
        }

        $move->serials()->attach(array_map(fn (StockSerial $s) => $s->id, $serials));
    }
}
