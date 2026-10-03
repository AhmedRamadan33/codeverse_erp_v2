<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Products\Models\Product;

/**
 * @property int $id
 * @property int $product_id
 * @property string $batch_number
 * @property \Carbon\CarbonImmutable|null $expiry_date
 */
class StockBatch extends Model
{
    protected $fillable = ['product_id', 'batch_number', 'expiry_date', 'manufactured_at'];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'immutable_date',
            'manufactured_at' => 'immutable_date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
