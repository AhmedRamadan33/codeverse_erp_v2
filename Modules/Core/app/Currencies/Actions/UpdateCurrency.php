<?php

namespace Modules\Core\Currencies\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Core\Currencies\Currencies;
use Modules\Core\Models\Currency;

/**
 * Activates or deactivates a currency and edits its symbol. Decimal places are fixed
 * by ISO 4217 and not editable, since rounding of stored amounts depends on them.
 */
class UpdateCurrency
{
    public function __construct(private readonly Currencies $currencies) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'symbol' => ['required', 'string', 'max:8'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     */
    public function handle(User $actor, Currency $currency, array $data): Currency
    {
        Gate::forUser($actor)->authorize('core.currencies.manage');

        $isActive = (bool) ($data['is_active'] ?? $currency->is_active);

        if (! $isActive && $this->currencies->isBase($currency)) {
            throw ValidationException::withMessages(['is_active' => __('core::currencies.base_must_be_active')]);
        }

        DB::transaction(fn () => $currency->update(['symbol' => $data['symbol'], 'is_active' => $isActive]));

        return $currency;
    }
}
