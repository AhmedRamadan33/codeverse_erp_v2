<?php

namespace Modules\Products\Support;

use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;
use Modules\Core\Support\Attributes\ModuleApi;
use Modules\Products\Models\Product;
use Modules\Products\Models\ProductUnit;

/**
 * Document unit to base unit. The base unit is the smallest, so conversion is always a
 * multiplication (core-design.md §8); replaces v1's base_unit_is_largest multiplier.
 */
#[ModuleApi]
class UnitConverter
{
    public function productUnit(Product $product, int $unitId): ProductUnit
    {
        $unit = $product->relationLoaded('units')
            ? $product->units->firstWhere('unit_id', $unitId)
            : $product->units()->where('unit_id', $unitId)->first();

        return $unit ?? throw ValidationException::withMessages([
            'unit_id' => __('products::products.unit_not_for_product', ['product' => $product->name]),
        ]);
    }

    public function toBase(Product $product, int $unitId, BigDecimal|string $quantity): BigDecimal
    {
        return BigDecimal::of($quantity)->multipliedBy($this->productUnit($product, $unitId)->factor)->toScale(4);
    }

    /**
     * Price of one document unit from a per-base-unit price (e.g. a carton's cost).
     */
    public function priceFromBase(Product $product, int $unitId, BigDecimal|string $basePrice): BigDecimal
    {
        return BigDecimal::of($basePrice)->multipliedBy($this->productUnit($product, $unitId)->factor)->toScale(4);
    }
}
