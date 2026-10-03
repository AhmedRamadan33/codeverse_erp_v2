<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Branch;
use Modules\Inventory\Database\Factories\WarehouseFactory;
use Spatie\Translatable\HasTranslations;

/**
 * Also an account-mapping scope: a warehouse may use its own inventory account.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property int $branch_id
 * @property bool $is_active
 */
class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use HasFactory, HasTranslations;

    /** @var string[] */
    public array $translatable = ['name'];

    protected $fillable = ['name', 'code', 'branch_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    protected static function newFactory(): WarehouseFactory
    {
        return WarehouseFactory::new();
    }
}
