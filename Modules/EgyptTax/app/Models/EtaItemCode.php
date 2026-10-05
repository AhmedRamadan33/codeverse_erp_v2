<?php

namespace Modules\EgyptTax\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Products\Models\Product;

/**
 * @property int $id
 * @property int $product_id
 * @property string $code_type EGS or GS1
 * @property string $item_code
 */
class EtaItemCode extends Model
{
    public const TYPES = ['EGS', 'GS1'];

    protected $table = 'eta_item_codes';

    protected $fillable = ['product_id', 'code_type', 'item_code'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
