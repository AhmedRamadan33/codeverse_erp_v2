<?php

namespace Modules\Products\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Products\Enums\ProductType;
use Modules\Products\Enums\Tracking;
use Modules\Products\Models\Product;
use Modules\Products\Support\ProductUsage;

/**
 * Creates or updates a product with its units and barcodes.
 */
class SaveProduct
{
    public function __construct(private readonly ProductUsage $usage) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(?Product $product = null): array
    {
        return [
            'sku' => ['required', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($product)],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', Rule::exists('product_categories', 'id')],
            'type' => ['required', Rule::enum(ProductType::class)],
            'tracking' => ['required', Rule::enum(Tracking::class)],
            'base_unit_id' => ['required', 'integer', Rule::exists('units', 'id')],
            'sale_price' => ['nullable', 'decimal:0,4', 'min:0', 'max:99999999999999'],
            'purchase_price' => ['nullable', 'decimal:0,4', 'min:0', 'max:99999999999999'],
            'sale_tax_id' => ['nullable', 'integer', Rule::exists('taxes', 'id')],
            'purchase_tax_id' => ['nullable', 'integer', Rule::exists('taxes', 'id')],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            // Units other than the base unit.
            'units' => ['array', 'max:20'],
            'units.*.unit_id' => ['required', 'integer', 'distinct', Rule::exists('units', 'id')],
            'units.*.factor' => ['required', 'decimal:0,4', 'gt:1', 'max:1000000'],
            'units.*.sale_price' => ['nullable', 'decimal:0,4', 'min:0', 'max:99999999999999'],
            'units.*.barcodes' => ['array'],
            'units.*.barcodes.*' => ['string', 'max:64', 'distinct'],
            'base_barcodes' => ['array'],
            'base_barcodes.*' => ['string', 'max:64', 'distinct'],
            'default_sale_unit_id' => ['nullable', 'integer'],
            'default_purchase_unit_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     */
    public function handle(User $actor, array $data, ?Product $product = null): Product
    {
        Gate::forUser($actor)->authorize($product ? 'products.products.update' : 'products.products.create');

        $type = ProductType::from($data['type']);
        $tracking = $type === ProductType::Stockable ? Tracking::from($data['tracking']) : Tracking::None;
        $units = array_values($data['units'] ?? []);

        if (in_array((int) $data['base_unit_id'], array_map(fn ($u) => (int) $u['unit_id'], $units), true)) {
            throw ValidationException::withMessages(['units' => __('products::products.base_unit_repeated')]);
        }

        if ($product && ($product->base_unit_id !== (int) $data['base_unit_id'] || $product->type !== $type || $product->tracking !== $tracking)
            && $this->usage->inUse($product)) {
            throw ValidationException::withMessages(['base_unit_id' => __('products::products.in_use_core_fields')]);
        }

        $barcodes = array_merge($data['base_barcodes'] ?? [], ...array_map(fn ($u) => $u['barcodes'] ?? [], $units));
        $barcodes = array_map('trim', $barcodes);

        if (count($barcodes) !== count(array_unique($barcodes))) {
            throw ValidationException::withMessages(['barcodes' => __('products::products.barcode_repeated')]);
        }

        $taken = DB::table('product_barcodes')->whereIn('barcode', $barcodes)
            ->when($product, fn ($q) => $q->where('product_id', '!=', $product->id))
            ->value('barcode');

        if ($taken) {
            throw ValidationException::withMessages(['barcodes' => __('products::products.barcode_taken', ['barcode' => $taken])]);
        }

        return DB::transaction(function () use ($data, $product, $type, $tracking, $units) {
            $product ??= new Product;
            $product->fill([
                'sku' => trim($data['sku']),
                'name' => array_filter(['ar' => $data['name_ar'], 'en' => $data['name_en'] ?? null]),
                'category_id' => $data['category_id'] ?? null,
                'type' => $type,
                'tracking' => $tracking,
                'base_unit_id' => $data['base_unit_id'],
                'sale_price' => (string) ($data['sale_price'] ?? '') ?: '0',
                'purchase_price' => (string) ($data['purchase_price'] ?? '') ?: '0',
                'sale_tax_id' => $data['sale_tax_id'] ?? null,
                'purchase_tax_id' => $data['purchase_tax_id'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ])->save();

            $this->syncUnits($product, $data, $units);

            return $product->load(['units.unit', 'barcodes']);
        });
    }

    /**
     * Units are matched by unit id, so existing ones keep their id (documents may reference them).
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $units
     */
    private function syncUnits(Product $product, array $data, array $units): void
    {
        $rows = [['unit_id' => (int) $data['base_unit_id'], 'factor' => '1', 'sale_price' => null, 'barcodes' => $data['base_barcodes'] ?? []]];
        foreach ($units as $unit) {
            $rows[] = [
                'unit_id' => (int) $unit['unit_id'],
                'factor' => (string) BigDecimal::of((string) $unit['factor'])->toScale(4),
                'sale_price' => isset($unit['sale_price']) && $unit['sale_price'] !== '' ? (string) $unit['sale_price'] : null,
                'barcodes' => $unit['barcodes'] ?? [],
            ];
        }

        $defaultSale = (int) ($data['default_sale_unit_id'] ?? 0) ?: (int) $data['base_unit_id'];
        $defaultPurchase = (int) ($data['default_purchase_unit_id'] ?? 0) ?: (int) $data['base_unit_id'];
        $keep = array_column($rows, 'unit_id');

        $product->barcodes()->delete();
        $product->units()->whereNotIn('unit_id', $keep)->delete();

        foreach ($rows as $row) {
            $productUnit = $product->units()->updateOrCreate(['unit_id' => $row['unit_id']], [
                'factor' => $row['factor'],
                'sale_price' => $row['sale_price'],
                'is_default_sale' => $row['unit_id'] === $defaultSale,
                'is_default_purchase' => $row['unit_id'] === $defaultPurchase,
            ]);

            foreach ($row['barcodes'] as $barcode) {
                $productUnit->barcodes()->create(['product_id' => $product->id, 'barcode' => trim($barcode)]);
            }
        }
    }
}
