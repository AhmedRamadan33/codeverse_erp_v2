<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Core\Casts\AsDecimal;

/**
 * @property int|null $price_list_id
 * @property int|null $payment_method_id
 * @property \Brick\Math\BigDecimal|null $paid_amount
 * @property \Carbon\CarbonImmutable|null $due_date
 * @property string|null $discount_type
 * @property \Brick\Math\BigDecimal|null $discount_value
 */
class SalesInvoice extends SalesDocument
{
    public const SEQUENCE = 'sales.invoice';

    protected $fillable = [
        'number', 'price_list_id', 'payment_method_id', 'paid_amount', 'branch_id', 'warehouse_id', 'partner_id', 'date', 'due_date', 'status',
        'currency_id', 'exchange_rate', 'discount_type', 'discount_value', 'subtotal', 'discount_total', 'tax_total',
        'total', 'description', 'journal_entry_id', 'created_by', 'posted_by', 'posted_at', 'cancelled_by',
        'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return $this->headerCasts() + [
            'due_date' => 'immutable_date',
            'discount_value' => AsDecimal::class.':4',
            'paid_amount' => AsDecimal::class.':4',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesInvoiceLine::class)->orderBy('line_no');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }
}
