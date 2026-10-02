<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Database\Factories\AccountFactory;
use Modules\Accounting\Enums\AccountSubtype;
use Modules\Accounting\Enums\AccountType;
use Modules\Core\Audit\Auditable;
use Modules\Core\Models\Currency;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int|null $parent_id
 * @property bool $is_group
 * @property AccountType $type
 * @property AccountSubtype|null $subtype
 * @property int|null $currency_id
 * @property bool $requires_partner
 * @property bool $is_active
 * @property bool $is_system
 */
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use Auditable, HasFactory, HasTranslations;

    /** @var string[] */
    public array $translatable = ['name'];

    protected $fillable = [
        'code', 'name', 'parent_id', 'is_group', 'type', 'subtype', 'currency_id',
        'requires_partner', 'is_active', 'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_group' => 'boolean',
            'type' => AccountType::class,
            'subtype' => AccountSubtype::class,
            'requires_partner' => 'boolean',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function isPostable(): bool
    {
        return $this->is_active && ! $this->is_group;
    }

    public function label(): string
    {
        return "{$this->code} - {$this->name}";
    }

    protected static function newFactory(): AccountFactory
    {
        return AccountFactory::new();
    }
}
