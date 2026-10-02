<?php

namespace Modules\Accounting\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Casts\AsDecimal;

/**
 * @property int $account_id
 * @property BigDecimal $amount
 * @property int|null $tax_id
 * @property BigDecimal|null $tax_rate
 * @property BigDecimal $tax_amount
 */
class ExpenseVoucherLine extends Model
{
    protected $fillable = ['expense_voucher_id', 'line_no', 'account_id', 'description', 'amount', 'tax_id', 'tax_rate', 'tax_amount'];

    protected function casts(): array
    {
        return [
            'amount' => AsDecimal::class.':4',
            'tax_rate' => AsDecimal::class.':4',
            'tax_amount' => AsDecimal::class.':4',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(ExpenseVoucher::class, 'expense_voucher_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }
}
