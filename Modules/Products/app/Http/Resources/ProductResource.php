<?php

namespace Modules\Products\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Modules\Products\Models\Product
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'category_id' => $this->category_id,
            'type' => $this->type->value,
            'tracking' => $this->tracking->value,
            'base_unit_id' => $this->base_unit_id,
            // Decimals as strings, never floats.
            'sale_price' => (string) $this->sale_price,
            'sale_tax_id' => $this->sale_tax_id,
            'is_active' => $this->is_active,
            'units' => $this->units->map(fn ($unit) => [
                'unit_id' => $unit->unit_id,
                'name' => $unit->unit->name,
                'symbol' => $unit->unit->symbol,
                'factor' => (string) $unit->factor,
                'sale_price' => (string) $unit->effectiveSalePrice(),
                'is_default_sale' => $unit->is_default_sale,
                'barcodes' => $unit->barcodes->pluck('barcode'),
            ]),
        ];
    }
}
