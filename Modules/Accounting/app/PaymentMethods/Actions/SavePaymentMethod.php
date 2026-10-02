<?php

namespace Modules\Accounting\PaymentMethods\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Accounting\Enums\AccountSubtype;
use Modules\Accounting\Enums\PaymentMethodType;
use Modules\Accounting\Models\PaymentMethod;

class SavePaymentMethod
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(PaymentMethodType::class)],
            // Money moves through a cash box or a bank account only.
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')
                ->where('is_group', false)->where('is_active', true)
                ->whereIn('subtype', [AccountSubtype::Cash->value, AccountSubtype::Bank->value])],
            'sort' => ['integer', 'min:0', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     */
    public function handle(User $actor, array $data, ?PaymentMethod $method = null): PaymentMethod
    {
        Gate::forUser($actor)->authorize('accounting.payment_methods.manage');

        return DB::transaction(function () use ($data, $method) {
            $method ??= new PaymentMethod;
            $method->fill([
                'name' => array_filter(['ar' => $data['name_ar'], 'en' => $data['name_en'] ?? null]),
                'type' => $data['type'],
                'account_id' => $data['account_id'],
                'sort' => (int) ($data['sort'] ?? 0),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ])->save();

            return $method;
        });
    }
}
