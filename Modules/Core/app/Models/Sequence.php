<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Sequences\SequenceReset;

/**
 * @property int $id
 * @property string $key
 * @property int|null $branch_id
 * @property string $prefix
 * @property int $padding
 * @property SequenceReset $reset
 */
class Sequence extends Model
{
    protected $fillable = ['key', 'branch_id', 'prefix', 'padding', 'reset'];

    protected function casts(): array
    {
        return [
            'padding' => 'integer',
            'reset' => SequenceReset::class,
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function counters(): HasMany
    {
        return $this->hasMany(SequenceCounter::class);
    }
}
