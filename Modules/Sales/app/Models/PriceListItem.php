<?php

namespace Modules\Sales\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Casts\AsDecimal;
use Modules\Products\Models\Product;
use Modules\Products\Models\Unit;

/**
 * @property int $price_list_id
 * @property int $product_id
 * @property int $unit_id
 * @property BigDecimal $price
 */
class PriceListItem extends Model
{
    protected $fillable = ['price_list_id', 'product_id', 'unit_id', 'price'];

    protected function casts(): array
    {
        return ['price' => AsDecimal::class.':4'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
