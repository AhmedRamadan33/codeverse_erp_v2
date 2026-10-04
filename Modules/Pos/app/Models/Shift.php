<?php

namespace Modules\Pos\Models;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Models\JournalEntry;
use Modules\Core\Audit\Auditable;
use Modules\Core\Casts\AsDecimal;
use Modules\Core\Models\Branch;
use Modules\Pos\Enums\ShiftStatus;

/**
 * @property int $id
 * @property string $number
 * @property int $register_id
 * @property int $branch_id
 * @property int $user_id
 * @property ShiftStatus $status
 * @property \Carbon\CarbonImmutable $opened_at
 * @property BigDecimal $opening_float
 * @property \Carbon\CarbonImmutable|null $closed_at
 * @property BigDecimal|null $expected_cash
 * @property BigDecimal|null $counted_cash
 * @property BigDecimal|null $cash_difference
 * @property int|null $journal_entry_id
 * @property int|null $valuation_entry_id
 */
class Shift extends Model
{
    use Auditable;

    public const SEQUENCE = 'pos.shift';

    protected $table = 'pos_shifts';

    protected $fillable = [
        'number', 'register_id', 'branch_id', 'user_id', 'status', 'opened_at', 'opening_float', 'closed_at', 'closed_by',
        'expected_cash', 'counted_cash', 'cash_difference', 'journal_entry_id', 'valuation_entry_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShiftStatus::class,
            'opened_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'opening_float' => AsDecimal::class.':4',
            'expected_cash' => AsDecimal::class.':4',
            'counted_cash' => AsDecimal::class.':4',
            'cash_difference' => AsDecimal::class.':4',
        ];
    }

    public function isOpen(): bool
    {
        return $this->status === ShiftStatus::Open;
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function valuationEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'valuation_entry_id');
    }
}
