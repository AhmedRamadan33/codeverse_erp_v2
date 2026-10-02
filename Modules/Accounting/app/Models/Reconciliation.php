<?php

namespace Modules\Accounting\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Casts\AsDecimal;

/**
 * @property int $debit_line_id
 * @property int $credit_line_id
 * @property BigDecimal $amount
 */
class Reconciliation extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['debit_line_id', 'credit_line_id', 'amount', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => AsDecimal::class.':4'];
    }

    public function debitLine(): BelongsTo
    {
        return $this->belongsTo(JournalLine::class, 'debit_line_id');
    }

    public function creditLine(): BelongsTo
    {
        return $this->belongsTo(JournalLine::class, 'credit_line_id');
    }
}
