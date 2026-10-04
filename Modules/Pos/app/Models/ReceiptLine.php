<?php

namespace Modules\Pos\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Models\Tax;
use Modules\Core\Casts\AsDecimal;
use Modules\Products\Models\Product;
use Modules\Products\Models\Unit;

/**
 * @property int $id
 * @property int $receipt_id
 * @property int|null $original_line_id
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
class ReceiptLine extends Model
{
    protected $table = 'pos_receipt_lines';

    protected $fillable = [
        'receipt_id', 'original_line_id', 'line_no', 'product_id', 'unit_id', 'quantity', 'base_quantity', 'unit_price',
        'discount_type', 'discount_value', 'gross', 'line_discount', 'document_discount', 'net', 'tax_id', 'tax_rate',
        'tax_amount', 'line_total', 'batch_number', 'serials', 'cost',
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

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
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
        return $this->hasMany(self::class, 'original_line_id');
    }

    /**
     * Quantity (in this line's unit) not yet returned.
     */
    public function returnableQuantity(): BigDecimal
    {
        $returned = self::where('original_line_id', $this->id)->sum('quantity');

        return $this->quantity->minus(BigDecimal::of($returned ?: 0))->toScale(4);
    }
}
