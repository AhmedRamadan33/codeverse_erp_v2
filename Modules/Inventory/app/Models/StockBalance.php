<?php

namespace Modules\Inventory\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Casts\AsDecimal;
use Modules\Products\Models\Product;

/**
 * Cache of Σ stock_moves.quantity per product, warehouse and batch.
 *
 * @property int $product_id
 * @property int $warehouse_id
 * @property int|null $batch_id
 * @property BigDecimal $quantity
 */
class StockBalance extends Model
{
    protected $fillable = ['product_id', 'warehouse_id', 'batch_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => AsDecimal::class.':4'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }
}
