<?php

namespace Modules\Purchases\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $purchase_invoice_id
 */
class PurchaseReturn extends PurchaseDocument
{
    public const SEQUENCE = 'purchases.return';

    protected $fillable = [
        'number', 'purchase_invoice_id', 'branch_id', 'warehouse_id', 'partner_id', 'date', 'status',
        'currency_id', 'exchange_rate', 'subtotal', 'discount_total', 'tax_total', 'total', 'description',
        'journal_entry_id', 'created_by', 'posted_by', 'posted_at', 'cancelled_by', 'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return $this->headerCasts();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseReturnLine::class)->orderBy('line_no');
    }
}
