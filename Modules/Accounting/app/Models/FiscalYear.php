<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Enums\PeriodStatus;

/**
 * @property int $id
 * @property string $name
 * @property \Carbon\CarbonImmutable $start_date
 * @property \Carbon\CarbonImmutable $end_date
 * @property PeriodStatus $status
 */
class FiscalYear extends Model
{
    protected $fillable = ['name', 'start_date', 'end_date', 'status', 'closed_at', 'closed_by'];

    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'status' => PeriodStatus::class,
            'closed_at' => 'immutable_datetime',
        ];
    }

    public function periods(): HasMany
    {
        return $this->hasMany(FiscalPeriod::class)->orderBy('start_date');
    }
}
