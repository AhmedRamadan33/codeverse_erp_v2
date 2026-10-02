<?php

namespace Modules\Accounting\Models;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Vouchers\VoucherKind;
use Modules\Core\Audit\Auditable;
use Modules\Core\Casts\AsDecimal;
use Modules\Core\Documents\DocumentStatus;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Partner;

/**
 * Shared behaviour of receipt and payment vouchers; each kind has its own table.
 *
 * @property int $id
 * @property string|null $number
 * @property int $branch_id
 * @property \Carbon\CarbonImmutable $date
 * @property DocumentStatus $status
 * @property int $partner_id
 * @property int $payment_method_id
 * @property int $currency_id
 * @property BigDecimal $exchange_rate
 * @property BigDecimal $amount
 * @property int|null $journal_entry_id
 */
abstract class CashVoucher extends Model
{
    use Auditable;

    protected $fillable = [
        'number', 'branch_id', 'date', 'status', 'partner_id', 'payment_method_id', 'currency_id',
        'exchange_rate', 'amount', 'reference', 'description', 'journal_entry_id', 'created_by',
        'posted_by', 'posted_at', 'cancelled_by', 'cancelled_at', 'cancel_reason',
    ];

    abstract public function kind(): VoucherKind;

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'status' => DocumentStatus::class,
            'exchange_rate' => AsDecimal::class.':6',
            'amount' => AsDecimal::class.':4',
            'posted_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The journal line on the receivable/payable account, once posted.
     */
    public function partnerLine(): ?JournalLine
    {
        if ($this->journal_entry_id === null) {
            return null;
        }

        return JournalLine::where('journal_entry_id', $this->journal_entry_id)
            ->where('partner_id', $this->partner_id)
            ->where($this->kind()->partnerLineIsCredit() ? 'credit' : 'debit', '>', 0)
            ->first();
    }
}
