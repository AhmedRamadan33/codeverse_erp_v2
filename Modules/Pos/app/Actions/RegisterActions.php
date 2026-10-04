<?php

namespace Modules\Pos\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Enums\PaymentMethodType;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Inventory\Models\Warehouse;
use Modules\Pos\Models\Register;

class RegisterActions
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(?Register $register = null): array
    {
        return [
            'code' => ['required', 'string', 'max:32', Rule::unique('pos_registers', 'code')->ignore($register?->id)],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'cash_payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('is_active', true)],
            'price_list_id' => ['nullable', 'integer', Rule::exists('price_lists', 'id')],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     */
    public function save(User $actor, array $data, ?Register $register = null): Register
    {
        Gate::forUser($actor)->authorize('pos.registers.manage');

        if (PaymentMethod::find($data['cash_payment_method_id'])->type !== PaymentMethodType::Cash) {
            throw ValidationException::withMessages(['cash_payment_method_id' => __('pos::registers.drawer_not_cash')]);
        }

        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        if ($register && $register->warehouse_id !== $warehouse->id && $register->openShift()) {
            throw ValidationException::withMessages(['warehouse_id' => __('pos::registers.has_open_shift')]);
        }

        return DB::transaction(function () use ($data, $register, $warehouse) {
            $register ??= new Register;
            $register->fill([
                'code' => $data['code'],
                'name' => array_filter(['ar' => $data['name_ar'], 'en' => $data['name_en'] ?? null]),
                'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->id,
                'cash_payment_method_id' => $data['cash_payment_method_id'],
                'price_list_id' => $data['price_list_id'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ])->save();

            return $register;
        });
    }
}
