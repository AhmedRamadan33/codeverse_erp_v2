<?php

namespace Modules\Inventory\Stock\Actions;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Stock\StockEngine;
use Modules\Inventory\Stock\StockOperationData;
use Modules\Products\Models\Product;

/**
 * Shared checks before any stock operation, then the locks.
 */
class StockLines
{
    public function __construct(private readonly StockEngine $engine) {}

    /**
     * @param  int[]  $extraWarehouseIds  e.g. the destination of a transfer
     * @return Collection<int, Product> keyed by id
     */
    public function prepare(StockOperationData $op, array $extraWarehouseIds = []): Collection
    {
        if ($op->lines === []) {
            throw ValidationException::withMessages(['lines' => __('inventory::moves.no_lines')]);
        }

        $productIds = array_map(fn ($l) => $l->productId, $op->lines);
        $warehouseIds = array_merge(array_map(fn ($l) => $l->warehouseId, $op->lines), $extraWarehouseIds);
        $products = Product::whereKey($productIds)->get()->keyBy('id');
        $warehouses = Warehouse::whereKey($warehouseIds)->get()->keyBy('id');

        foreach ($op->lines as $line) {
            $product = $products[$line->productId] ?? null;

            if ($product === null || ! $product->tracksStock()) {
                throw ValidationException::withMessages(['lines' => __('inventory::moves.not_stockable', ['product' => $product?->name ?? $line->productId])]);
            }

            if (! $line->quantity->isPositive()) {
                throw ValidationException::withMessages(['lines' => __('inventory::moves.quantity_positive', ['product' => $product->name])]);
            }

            $warehouse = $warehouses[$line->warehouseId] ?? null;
            if ($warehouse === null || ! $warehouse->is_active) {
                throw ValidationException::withMessages(['lines' => __('inventory::moves.warehouse_inactive')]);
            }
        }

        $this->engine->lock($productIds, $warehouseIds);

        return $products;
    }
}
