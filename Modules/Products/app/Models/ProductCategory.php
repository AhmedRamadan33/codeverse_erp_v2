<?php

namespace Modules\Products\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * Also an account-mapping scope: e.g. a category can post to its own revenue account.
 *
 * @property int $id
 * @property string $name
 * @property int|null $parent_id
 * @property bool $is_active
 */
class ProductCategory extends Model
{
    use HasTranslations;

    /** @var string[] */
    public array $translatable = ['name'];

    protected $fillable = ['name', 'parent_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
