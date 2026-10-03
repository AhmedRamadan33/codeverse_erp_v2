<?php

namespace Modules\Purchases\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Models\Tax;
use Modules\Core\Casts\AsDecimal;
use Modules\Products\Models\Product;
use Modules\Products\Models\Unit;

/**
 * @property int $purchase_invoice_line_id
 * @property int $product_id
 * @property int $unit_id
 * @property BigDecimal $quantity
 * @property BigDecimal $base_quantity
 * @property BigDecimal $net
 * @property BigDecimal $tax_amount
 * @property BigDecimal $line_total
 * @property BigDecimal|null $stock_cost
 * @property array<int, string>|null $serials
 */
class PurchaseReturnLine extends Model
{
    protected $fillable = [
        'purchase_return_id', 'purchase_invoice_line_id', 'line_no', 'product_id', 'unit_id', 'quantity',
        'base_quantity', 'net', 'tax_id', 'tax_rate', 'tax_amount', 'line_total', 'batch_number', 'serials', 'stock_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => AsDecimal::class.':4',
            'base_quantity' => AsDecimal::class.':4',
            'net' => AsDecimal::class.':4',
            'tax_rate' => AsDecimal::class.':4',
            'tax_amount' => AsDecimal::class.':4',
            'line_total' => AsDecimal::class.':4',
            'stock_cost' => AsDecimal::class.':4',
            'serials' => 'array',
        ];
    }

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoiceLine::class, 'purchase_invoice_line_id');
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
}
