<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $sequence_id
 * @property string $period
 * @property int $next_number
 */
class SequenceCounter extends Model
{
    protected $fillable = ['sequence_id', 'period', 'next_number'];

    protected function casts(): array
    {
        return ['next_number' => 'integer'];
    }
}
