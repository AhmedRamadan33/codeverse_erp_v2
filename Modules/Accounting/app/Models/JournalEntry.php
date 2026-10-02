<?php

namespace Modules\Accounting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Accounting\Enums\EntryStatus;
use Modules\Accounting\Enums\JournalType;
use Modules\Accounting\Exceptions\PostedEntryImmutable;
use Modules\Core\Models\Branch;

/**
 * A posted entry is immutable: only `reversed_by_id` may be set on it, once, when it is reversed.
 *
 * @property int $id
 * @property string|null $number
 * @property \Carbon\CarbonImmutable $date
 * @property int $branch_id
 * @property JournalType $journal_type
 * @property string|null $description
 * @property EntryStatus $status
 * @property int|null $reversal_of_id
 * @property int|null $reversed_by_id
 */
class JournalEntry extends Model
{
    protected $fillable = [
        'number', 'date', 'branch_id', 'journal_type', 'description', 'source_type', 'source_id',
        'status', 'reversal_of_id', 'reversed_by_id', 'created_by', 'posted_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'journal_type' => JournalType::class,
            'status' => EntryStatus::class,
            'posted_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $entry) {
            if ($entry->getOriginal('status') !== EntryStatus::Posted) {
                return;
            }

            $changed = array_keys($entry->getDirty());
            $allowed = $entry->getOriginal('reversed_by_id') === null ? ['reversed_by_id', 'updated_at'] : [];

            if (array_diff($changed, $allowed) !== []) {
                throw new PostedEntryImmutable($entry);
            }
        });

        static::deleting(function (self $entry) {
            if ($entry->status === EntryStatus::Posted) {
                throw new PostedEntryImmutable($entry);
            }
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('line_no');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function isPosted(): bool
    {
        return $this->status === EntryStatus::Posted;
    }
}
