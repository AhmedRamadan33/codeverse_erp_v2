<?php

namespace Modules\Core\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Casts\AsDecimal;

/**
 * @property int $currency_id
 * @property \Carbon\CarbonImmutable $date
 * @property BigDecimal $rate Base-currency units per 1 unit of the currency.
 */
class ExchangeRate extends Model
{
    protected $fillable = ['currency_id', 'date', 'rate', 'created_by'];

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'rate' => AsDecimal::class.':6',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
