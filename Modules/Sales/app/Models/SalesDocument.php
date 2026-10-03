<?php

namespace Modules\Sales\Models;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Models\JournalEntry;
use Modules\Core\Audit\Auditable;
use Modules\Core\Casts\AsDecimal;
use Modules\Core\Documents\DocumentStatus;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Partner;
use Modules\Inventory\Models\Warehouse;

/**
 * Header columns shared by sales invoices and returns (each has its own table).
 *
 * @property int $id
 * @property string|null $number
 * @property int $branch_id
 * @property int $warehouse_id
 * @property int $partner_id
 * @property \Carbon\CarbonImmutable $date
 * @property DocumentStatus $status
 * @property int $currency_id
 * @property BigDecimal $exchange_rate
 * @property BigDecimal $subtotal
 * @property BigDecimal $discount_total
 * @property BigDecimal $tax_total
 * @property BigDecimal $total
 * @property int|null $journal_entry_id
 */
abstract class SalesDocument extends Model
{
    use Auditable;

    protected function headerCasts(): array
    {
        return [
            'date' => 'immutable_date',
            'status' => DocumentStatus::class,
            'exchange_rate' => AsDecimal::class.':6',
            'subtotal' => AsDecimal::class.':4',
            'discount_total' => AsDecimal::class.':4',
            'tax_total' => AsDecimal::class.':4',
            'total' => AsDecimal::class.':4',
            'posted_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
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
