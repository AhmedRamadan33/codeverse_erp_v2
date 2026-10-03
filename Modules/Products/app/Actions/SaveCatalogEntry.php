<?php

namespace Modules\Products\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Products\Models\ProductCategory;
use Modules\Products\Models\Unit;

/**
 * Units and categories: small translatable lists managed with the same permission.
 */
class SaveCatalogEntry
{
    /**
     * @return array<string, mixed>
     */
    public static function unitRules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:64'],
            'name_en' => ['nullable', 'string', 'max:64'],
            'symbol_ar' => ['required', 'string', 'max:16'],
            'symbol_en' => ['nullable', 'string', 'max:16'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function categoryRules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', Rule::exists('product_categories', 'id')],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function unit(User $actor, array $data, ?Unit $unit = null): Unit
    {
        Gate::forUser($actor)->authorize('products.catalog.manage');

        return DB::transaction(function () use ($data, $unit) {
            $unit ??= new Unit;
            $unit->fill([
                'name' => array_filter(['ar' => $data['name_ar'], 'en' => $data['name_en'] ?? null]),
                'symbol' => array_filter(['ar' => $data['symbol_ar'], 'en' => $data['symbol_en'] ?? null]),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ])->save();

            return $unit;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function category(User $actor, array $data, ?ProductCategory $category = null): ProductCategory
    {
        Gate::forUser($actor)->authorize('products.catalog.manage');

        $parentId = $data['parent_id'] ?? null;

        // Walk up from the chosen parent: reaching the category itself would make a loop.
        for ($node = $parentId ? ProductCategory::find($parentId) : null; $category && $node; $node = $node->parent) {
            if ($node->is($category)) {
                throw ValidationException::withMessages(['parent_id' => __('products::catalog.parent_cycle')]);
            }
        }

        return DB::transaction(function () use ($data, $category, $parentId) {
            $category ??= new ProductCategory;
            $category->fill([
                'name' => array_filter(['ar' => $data['name_ar'], 'en' => $data['name_en'] ?? null]),
                'parent_id' => $parentId,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ])->save();

            return $category;
        });
    }
}
