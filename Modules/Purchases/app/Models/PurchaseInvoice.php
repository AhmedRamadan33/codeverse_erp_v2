<?php

namespace Modules\Purchases\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Casts\AsDecimal;

/**
 * @property string|null $supplier_reference
 * @property \Carbon\CarbonImmutable|null $due_date
 * @property string|null $discount_type
 * @property \Brick\Math\BigDecimal|null $discount_value
 */
class PurchaseInvoice extends PurchaseDocument
{
    public const SEQUENCE = 'purchases.invoice';

    protected $fillable = [
        'number', 'supplier_reference', 'branch_id', 'warehouse_id', 'partner_id', 'date', 'due_date', 'status',
        'currency_id', 'exchange_rate', 'discount_type', 'discount_value', 'subtotal', 'discount_total', 'tax_total',
        'total', 'description', 'journal_entry_id', 'created_by', 'posted_by', 'posted_at', 'cancelled_by',
        'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return $this->headerCasts() + [
            'due_date' => 'immutable_date',
            'discount_value' => AsDecimal::class.':4',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceLine::class)->orderBy('line_no');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }
}
