<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Partner;

/**
 * Sales settings of a customer (Core's partners table stays module-agnostic).
 *
 * @property int $partner_id
 * @property int|null $price_list_id
 */
class CustomerProfile extends Model
{
    protected $primaryKey = 'partner_id';

    public $incrementing = false;

    protected $fillable = ['partner_id', 'price_list_id'];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }
}
