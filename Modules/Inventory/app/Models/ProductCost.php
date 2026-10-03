<?php

namespace Modules\Inventory\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Casts\AsDecimal;

/**
 * Cache of Σ stock_moves (quantity, total_cost) per product: the weighted average cost.
 *
 * @property int $product_id
 * @property BigDecimal $quantity_on_hand
 * @property BigDecimal $total_value
 * @property BigDecimal $average_cost
 */
class ProductCost extends Model
{
    protected $primaryKey = 'product_id';

    public $incrementing = false;

    protected $fillable = ['product_id', 'quantity_on_hand', 'total_value', 'average_cost'];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => AsDecimal::class.':4',
            'total_value' => AsDecimal::class.':4',
            'average_cost' => AsDecimal::class.':4',
        ];
    }
}
