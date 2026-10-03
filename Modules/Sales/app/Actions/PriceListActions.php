<?php

namespace Modules\Sales\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Partner;
use Modules\Products\Models\ProductUnit;
use Modules\Sales\Models\CustomerProfile;
use Modules\Sales\Models\PriceList;

class PriceListActions
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'items' => ['array', 'max:5000'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'items.*.unit_id' => ['required', 'integer'],
            'items.*.price' => ['required', 'decimal:0,4', 'min:0', 'max:99999999999999'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     */
    public function save(User $actor, array $data, ?PriceList $list = null): PriceList
    {
        Gate::forUser($actor)->authorize('sales.price_lists.manage');

        $items = array_values($data['items'] ?? []);
        $seen = [];

        foreach ($items as $i => $item) {
            $key = $item['product_id'].'-'.$item['unit_id'];
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(["items.{$i}.product_id" => __('sales::price_lists.duplicate_item')]);
            }
            $seen[$key] = true;

            if (! ProductUnit::where(['product_id' => $item['product_id'], 'unit_id' => $item['unit_id']])->exists()) {
                throw ValidationException::withMessages(["items.{$i}.unit_id" => __('sales::price_lists.unit_not_for_product')]);
            }
        }

        return DB::transaction(function () use ($data, $list, $items) {
            $list ??= new PriceList;
            $list->fill([
                'name' => array_filter(['ar' => $data['name_ar'], 'en' => $data['name_en'] ?? null]),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ])->save();

            $list->items()->delete();
            $list->items()->createMany(array_map(fn ($item) => [
                'product_id' => $item['product_id'], 'unit_id' => $item['unit_id'], 'price' => (string) $item['price'],
            ], $items));

            return $list;
        });
    }

    /**
     * Assigns (or clears) a customer's price list.
     */
    public function assign(User $actor, Partner $customer, ?int $priceListId): void
    {
        Gate::forUser($actor)->authorize('sales.price_lists.manage');

        DB::transaction(fn () => CustomerProfile::updateOrCreate(['partner_id' => $customer->id], ['price_list_id' => $priceListId]));
    }
}
