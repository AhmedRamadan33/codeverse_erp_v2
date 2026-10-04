<?php

namespace Modules\Pos\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Core\Casts\AsDecimal;

/**
 * @property int $id
 * @property int $receipt_id
 * @property int $payment_method_id
 * @property BigDecimal $amount
 */
class ReceiptPayment extends Model
{
    protected $table = 'pos_receipt_payments';

    protected $fillable = ['receipt_id', 'payment_method_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => AsDecimal::class.':4'];
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }
}
