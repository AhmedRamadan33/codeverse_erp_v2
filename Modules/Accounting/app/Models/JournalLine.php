<?php

namespace Modules\Accounting\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Accounting\Enums\EntryStatus;
use Modules\Accounting\Exceptions\PostedEntryImmutable;
use Modules\Core\Casts\AsDecimal;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Partner;

/**
 * @property int $journal_entry_id
 * @property int $line_no
 * @property int $account_id
 * @property BigDecimal $debit
 * @property BigDecimal $credit
 * @property int|null $currency_id
 * @property BigDecimal|null $amount_currency
 * @property int|null $partner_id
 * @property int $branch_id
 */
class JournalLine extends Model
{
    protected $fillable = [
        'journal_entry_id', 'line_no', 'account_id', 'debit', 'credit', 'currency_id', 'amount_currency',
        'partner_id', 'branch_id', 'due_date', 'description', 'source_line_type', 'source_line_id',
    ];

    protected function casts(): array
    {
        return [
            'debit' => AsDecimal::class.':4',
            'credit' => AsDecimal::class.':4',
            'amount_currency' => AsDecimal::class.':4',
            'due_date' => 'immutable_date',
        ];
    }

    protected static function booted(): void
    {
        $guard = function (self $line) {
            // Read the stored status, not the in-memory model, which may be stale.
            $status = JournalEntry::whereKey($line->journal_entry_id)->toBase()->value('status');

            if ($status === EntryStatus::Posted->value) {
                throw new PostedEntryImmutable($line->entry);
            }
        };

        // Lines are written while the entry is a draft; posting then freezes them.
        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function sourceLine(): MorphTo
    {
        return $this->morphTo();
    }
}
