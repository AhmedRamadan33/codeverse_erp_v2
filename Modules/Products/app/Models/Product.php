<?php

namespace Modules\Products\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Models\Tax;
use Modules\Core\Audit\Auditable;
use Modules\Core\Casts\AsDecimal;
use Modules\Products\Database\Factories\ProductFactory;
use Modules\Products\Enums\ProductType;
use Modules\Products\Enums\Tracking;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $sku
 * @property string $name
 * @property int|null $category_id
 * @property ProductType $type
 * @property Tracking $tracking
 * @property int $base_unit_id
 * @property BigDecimal $sale_price
 * @property BigDecimal $purchase_price
 * @property int|null $sale_tax_id
 * @property int|null $purchase_tax_id
 * @property bool $is_active
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use Auditable, HasFactory, HasTranslations;

    /** @var string[] */
    public array $translatable = ['name'];

    protected $fillable = [
        'sku', 'name', 'category_id', 'type', 'tracking', 'base_unit_id', 'sale_price', 'purchase_price',
        'sale_tax_id', 'purchase_tax_id', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'tracking' => Tracking::class,
            'sale_price' => AsDecimal::class.':4',
            'purchase_price' => AsDecimal::class.':4',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class)->orderBy('factor');
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }

    public function saleTax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'sale_tax_id');
    }

    public function purchaseTax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'purchase_tax_id');
    }

    public function tracksStock(): bool
    {
        return $this->type->tracksStock();
    }

    public function label(): string
    {
        return "{$this->sku} - {$this->name}";
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}
