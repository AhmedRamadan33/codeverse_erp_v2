<?php

namespace Modules\Inventory\Stock;

use Brick\Math\BigDecimal;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * One product line of a stock operation, in base units.
 */
final readonly class StockLineData
{
    public BigDecimal $quantity;

    public ?BigDecimal $unitCost;

    /**
     * @param  BigDecimal|string  $quantity  positive, base units
     * @param  BigDecimal|string|null  $unitCost  per base unit; required for receipts
     * @param  string[]  $serials  serial-tracked products: one per unit
     * @param  string|null  $batchNumber  batch-tracked products: required on receipt; optional on issue (FEFO)
     */
    public function __construct(
        public int $productId,
        public int $warehouseId,
        BigDecimal|string $quantity,
        BigDecimal|string|null $unitCost = null,
        public ?string $batchNumber = null,
        public ?DateTimeInterface $expiryDate = null,
        public array $serials = [],
        public ?Model $sourceLine = null,
    ) {
        $this->quantity = BigDecimal::of($quantity)->toScale(4);
        $this->unitCost = $unitCost === null ? null : BigDecimal::of($unitCost)->toScale(4);
    }
}
