<?php

namespace Modules\Inventory\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Casts\AsDecimal;
use Modules\Products\Models\Product;
use Modules\Products\Models\Unit;

/**
 * @property int $product_id
 * @property int $unit_id
 * @property BigDecimal $quantity
 * @property BigDecimal $base_quantity
 * @property BigDecimal|null $total_cost
 * @property string|null $batch_number
 * @property array<int, string>|null $serials
 */
class StockTransferLine extends Model
{
    protected $fillable = [
        'stock_transfer_id', 'line_no', 'product_id', 'unit_id', 'quantity', 'base_quantity', 'total_cost', 'batch_number', 'serials',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => AsDecimal::class.':4',
            'base_quantity' => AsDecimal::class.':4',
            'total_cost' => AsDecimal::class.':4',
            'serials' => 'array',
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
}
