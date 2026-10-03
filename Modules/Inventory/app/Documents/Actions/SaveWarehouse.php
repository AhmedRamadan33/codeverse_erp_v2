<?php

namespace Modules\Inventory\Documents\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Core\Settings\Settings;
use Modules\Inventory\Models\Warehouse;

/**
 * Warehouses are deactivated, never deleted: stock moves keep pointing to them.
 */
class SaveWarehouse
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(?Warehouse $warehouse = null): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'alpha_dash', 'max:16', Rule::unique('warehouses', 'code')->ignore($warehouse)],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     */
    public function handle(User $actor, array $data, ?Warehouse $warehouse = null): Warehouse
    {
        Gate::forUser($actor)->authorize('inventory.warehouses.manage');

        return DB::transaction(function () use ($data, $warehouse) {
            $warehouse ??= new Warehouse;
            $warehouse->fill([
                'name' => array_filter(['ar' => $data['name_ar'], 'en' => $data['name_en'] ?? null]),
                'code' => strtoupper($data['code']),
                'branch_id' => $data['branch_id'],
                'is_active' => (bool) ($data['is_active'] ?? true),
            ])->save();

            return $warehouse;
        });
    }

    public function setNegativeStock(User $actor, bool $allowed): void
    {
        Gate::forUser($actor)->authorize('inventory.warehouses.manage');

        $this->settings->set('inventory.allow_negative_stock', $allowed);
    }
}
