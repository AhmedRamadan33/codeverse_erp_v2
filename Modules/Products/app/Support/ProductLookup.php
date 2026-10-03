<?php

namespace Modules\Products\Support;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Support\Attributes\ModuleApi;
use Modules\Products\Models\Product;
use Modules\Products\Models\ProductBarcode;
use Modules\Products\Models\ProductUnit;

/**
 * Finds products for document lines and scanners.
 */
#[ModuleApi]
class ProductLookup
{
    /**
     * A scanned barcode gives the product and the unit it stands for.
     *
     * @return array{product: Product, unit: ProductUnit}|null
     */
    public function byBarcode(string $barcode): ?array
    {
        $match = ProductBarcode::with(['product.units', 'productUnit'])->where('barcode', trim($barcode))->first();

        return $match ? ['product' => $match->product, 'unit' => $match->productUnit] : null;
    }

    /**
     * Active products whose SKU, barcode or name (any language) contains the term.
     *
     * @return Builder<Product>
     */
    public function search(string $term, bool $activeOnly = true): Builder
    {
        $term = trim($term);

        return Product::query()
            ->when($activeOnly, fn (Builder $q) => $q->where('is_active', true))
            ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('sku', 'like', "%{$term}%")
                // Names are JSON with escaped unicode; search the decoded values.
                ->orWhereRaw("json_unquote(json_extract(name, '$.ar')) like ?", ["%{$term}%"])
                ->orWhereRaw("json_unquote(json_extract(name, '$.en')) like ?", ["%{$term}%"])
                ->orWhereHas('barcodes', fn (Builder $b) => $b->where('barcode', $term))));
    }
}
