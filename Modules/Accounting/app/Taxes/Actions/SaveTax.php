<?php

namespace Modules\Accounting\Taxes\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Accounting\Enums\TaxScope;
use Modules\Accounting\Enums\TaxType;
use Modules\Accounting\Models\Tax;

/**
 * Taxes are deactivated rather than deleted; documents keep the rate they were posted with.
 */
class SaveTax
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(?Tax $tax = null): array
    {
        return [
            'code' => ['required', 'alpha_dash', 'max:32', Rule::unique('taxes', 'code')->ignore($tax)],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'rate' => ['required', 'decimal:0,4', 'min:0', 'max:99999'],
            'type' => ['required', Rule::enum(TaxType::class)],
            'scope' => ['required', Rule::enum(TaxScope::class)],
            'included_in_price' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     */
    public function handle(User $actor, array $data, ?Tax $tax = null): Tax
    {
        Gate::forUser($actor)->authorize('accounting.taxes.manage');

        return DB::transaction(function () use ($data, $tax) {
            $tax ??= new Tax;
            $tax->fill([
                'code' => strtoupper($data['code']),
                'name' => array_filter(['ar' => $data['name_ar'], 'en' => $data['name_en'] ?? null]),
                'rate' => (string) $data['rate'],
                'type' => $data['type'],
                'scope' => $data['scope'],
                'included_in_price' => (bool) ($data['included_in_price'] ?? false),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ])->save();

            return $tax;
        });
    }
}
