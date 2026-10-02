<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Enums\PaymentMethodType;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $name
 * @property PaymentMethodType $type
 * @property int $account_id
 * @property bool $is_active
 */
class PaymentMethod extends Model
{
    use HasTranslations;

    /** @var string[] */
    public array $translatable = ['name'];

    protected $fillable = ['name', 'type', 'account_id', 'sort', 'is_active'];

    protected function casts(): array
    {
        return [
            'type' => PaymentMethodType::class,
            'is_active' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
