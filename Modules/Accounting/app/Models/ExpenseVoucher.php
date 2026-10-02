<?php

namespace Modules\Accounting\Models;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Audit\Auditable;
use Modules\Core\Casts\AsDecimal;
use Modules\Core\Documents\DocumentStatus;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Partner;

/**
 * @property int $id
 * @property string|null $number
 * @property int $branch_id
 * @property \Carbon\CarbonImmutable $date
 * @property DocumentStatus $status
 * @property int $currency_id
 * @property BigDecimal $exchange_rate
 * @property BigDecimal $subtotal
 * @property BigDecimal $tax_total
 * @property BigDecimal $total
 * @property int|null $journal_entry_id
 */
class ExpenseVoucher extends Model
{
    use Auditable;

    public const SEQUENCE = 'accounting.expense_voucher';

    protected $fillable = [
        'number', 'branch_id', 'date', 'status', 'payment_method_id', 'partner_id', 'currency_id', 'exchange_rate',
        'subtotal', 'tax_total', 'total', 'reference', 'description', 'journal_entry_id', 'created_by',
        'posted_by', 'posted_at', 'cancelled_by', 'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'status' => DocumentStatus::class,
            'exchange_rate' => AsDecimal::class.':6',
            'subtotal' => AsDecimal::class.':4',
            'tax_total' => AsDecimal::class.':4',
            'total' => AsDecimal::class.':4',
            'posted_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ExpenseVoucherLine::class)->orderBy('line_no');
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
}
