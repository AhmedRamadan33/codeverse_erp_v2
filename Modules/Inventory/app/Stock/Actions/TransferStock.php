<?php

namespace Modules\Inventory\Stock\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Support\Attributes\ModuleApi;
use Modules\Core\Support\TransactionGuard;
use Modules\Inventory\Stock\StockEngine;
use Modules\Inventory\Stock\StockOperationData;
use Modules\Inventory\Stock\StockResult;
use Modules\Inventory\Stock\Valuation;

/**
 * Moves stock from the lines' warehouse to another one at its current value. An entry is
 * posted only when the two warehouses use different inventory accounts.
 */
#[ModuleApi]
class TransferStock
{
    public function __construct(
        private readonly StockEngine $engine,
        private readonly Valuation $valuation,
        private readonly StockLines $lines,
    ) {}

    public function handle(StockOperationData $op, int $toWarehouseId): StockResult
    {
        TransactionGuard::assertActive('TransferStock');

        foreach ($op->lines as $line) {
            if ($line->warehouseId === $toWarehouseId) {
                throw ValidationException::withMessages(['to_warehouse_id' => __('inventory::moves.same_warehouse')]);
            }
        }

        $products = $this->lines->prepare($op, [$toWarehouseId]);
        $movesByLine = [];

        foreach ($op->lines as $i => $line) {
            $movesByLine[$i] = $this->engine->transfer($op, $line, $products[$line->productId], $toWarehouseId);
        }

        $result = new StockResult($movesByLine);
        $result->journalEntry = $this->valuation->post($result->moves(), $op->date, $op->branchId, $op->source, $op->counterAccountKey, [], $op->description, $op->postedBy);

        return $result;
    }
}
