<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Enums\SerialStatus;
use Modules\Products\Models\Product;

/**
 * @property int $id
 * @property int $product_id
 * @property string $serial_number
 * @property SerialStatus $status
 * @property int|null $warehouse_id
 */
class StockSerial extends Model
{
    protected $fillable = ['product_id', 'serial_number', 'status', 'warehouse_id', 'batch_id'];

    protected function casts(): array
    {
        return ['status' => SerialStatus::class];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
