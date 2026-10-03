<?php

namespace Modules\Products\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Casts\AsDecimal;

/**
 * @property int $id
 * @property int $product_id
 * @property int $unit_id
 * @property BigDecimal $factor base units in one of this unit
 * @property BigDecimal|null $sale_price
 * @property bool $is_default_sale
 * @property bool $is_default_purchase
 */
class ProductUnit extends Model
{
    protected $fillable = ['product_id', 'unit_id', 'factor', 'sale_price', 'is_default_sale', 'is_default_purchase'];

    protected function casts(): array
    {
        return [
            'factor' => AsDecimal::class.':4',
            'sale_price' => AsDecimal::class.':4',
            'is_default_sale' => 'boolean',
            'is_default_purchase' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }

    /**
     * The unit's own price, or the base price times the factor.
     */
    public function effectiveSalePrice(): BigDecimal
    {
        return $this->sale_price ?? $this->product->sale_price->multipliedBy($this->factor)->toScale(4);
    }
}
