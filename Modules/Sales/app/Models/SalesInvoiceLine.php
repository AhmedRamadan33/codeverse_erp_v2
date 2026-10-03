<?php

namespace Modules\Sales\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Models\Tax;
use Modules\Core\Casts\AsDecimal;
use Modules\Core\Documents\DocumentStatus;
use Modules\Products\Models\Product;
use Modules\Products\Models\Unit;

/**
 * @property int $id
 * @property int $product_id
 * @property int $unit_id
 * @property BigDecimal $quantity
 * @property BigDecimal $base_quantity
 * @property BigDecimal $unit_price
 * @property BigDecimal $gross
 * @property BigDecimal $line_discount
 * @property BigDecimal $document_discount
 * @property BigDecimal $net
 * @property int|null $tax_id
 * @property BigDecimal|null $tax_rate
 * @property BigDecimal $tax_amount
 * @property BigDecimal $line_total
 * @property string|null $batch_number
 * @property array<int, string>|null $serials
 * @property BigDecimal|null $cost
 */
class SalesInvoiceLine extends Model
{
    protected $fillable = [
        'sales_invoice_id', 'line_no', 'product_id', 'unit_id', 'description', 'quantity', 'base_quantity',
        'unit_price', 'discount_type', 'discount_value', 'gross', 'line_discount', 'document_discount', 'net',
        'tax_id', 'tax_rate', 'tax_amount', 'line_total', 'batch_number', 'serials', 'cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => AsDecimal::class.':4',
            'base_quantity' => AsDecimal::class.':4',
            'unit_price' => AsDecimal::class.':4',
            'discount_value' => AsDecimal::class.':4',
            'gross' => AsDecimal::class.':4',
            'line_discount' => AsDecimal::class.':4',
            'document_discount' => AsDecimal::class.':4',
            'net' => AsDecimal::class.':4',
            'tax_rate' => AsDecimal::class.':4',
            'tax_amount' => AsDecimal::class.':4',
            'line_total' => AsDecimal::class.':4',
            'cost' => AsDecimal::class.':4',
            'serials' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    public function returnLines(): HasMany
    {
        return $this->hasMany(SalesReturnLine::class);
    }

    /**
     * Quantity (in this line's unit) still returnable: posted and draft returns both count,
     * so two drafts cannot return the same goods twice.
     */
    public function returnableQuantity(?int $exceptReturnId = null): BigDecimal
    {
        $returned = SalesReturnLine::where('sales_invoice_line_id', $this->id)
            ->whereHas('salesReturn', fn ($q) => $q->where('status', '!=', DocumentStatus::Cancelled)
                ->when($exceptReturnId, fn ($q) => $q->whereKeyNot($exceptReturnId)))
            ->sum('quantity');

        return $this->quantity->minus(BigDecimal::of($returned ?: 0))->toScale(4);
    }
}
