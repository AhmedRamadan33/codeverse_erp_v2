<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $key
 * @property string|null $scope_type
 * @property int|null $scope_id
 * @property int $account_id
 */
class AccountMapping extends Model
{
    protected $fillable = ['key', 'scope_type', 'scope_id', 'account_id'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
