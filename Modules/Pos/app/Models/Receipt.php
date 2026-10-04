<?php

namespace Modules\Pos\Models;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Models\JournalEntry;
use Modules\Core\Casts\AsDecimal;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Partner;
use Modules\Inventory\Models\Warehouse;
use Modules\Pos\Enums\ReceiptKind;

/**
 * A completed POS sale or return. Receipts are posted when created and never change
 * (core-design.md §7.1).
 *
 * @property int $id
 * @property string $number
 * @property int $shift_id
 * @property int $register_id
 * @property int $branch_id
 * @property int $warehouse_id
 * @property int $partner_id
 * @property ReceiptKind $kind
 * @property int|null $original_receipt_id
 * @property \Carbon\CarbonImmutable $date
 * @property int|null $price_list_id
 * @property BigDecimal $subtotal
 * @property BigDecimal $discount_total
 * @property BigDecimal $tax_total
 * @property BigDecimal $total
 * @property BigDecimal $paid_total
 * @property BigDecimal|null $tendered
 * @property BigDecimal $change
 * @property bool $is_credit
 * @property \Carbon\CarbonImmutable|null $due_date
 * @property int|null $journal_entry_id
 */
class Receipt extends Model
{
    public const SEQUENCE = 'pos.receipt';

    protected $table = 'pos_receipts';

    protected $fillable = [
        'number', 'shift_id', 'register_id', 'branch_id', 'warehouse_id', 'partner_id', 'kind', 'original_receipt_id', 'date',
        'price_list_id', 'discount_type', 'discount_value', 'subtotal', 'discount_total', 'tax_total', 'total', 'paid_total',
        'tendered', 'change', 'is_credit', 'due_date', 'journal_entry_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'kind' => ReceiptKind::class,
            'date' => 'immutable_date',
            'due_date' => 'immutable_date',
            'discount_value' => AsDecimal::class.':4',
            'subtotal' => AsDecimal::class.':4',
            'discount_total' => AsDecimal::class.':4',
            'tax_total' => AsDecimal::class.':4',
            'total' => AsDecimal::class.':4',
            'paid_total' => AsDecimal::class.':4',
            'tendered' => AsDecimal::class.':4',
            'change' => AsDecimal::class.':4',
            'is_credit' => 'boolean',
        ];
    }

    public function isReturn(): bool
    {
        return $this->kind === ReceiptKind::Return;
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ReceiptLine::class)->orderBy('line_no');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ReceiptPayment::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function original(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_receipt_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(self::class, 'original_receipt_id');
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
