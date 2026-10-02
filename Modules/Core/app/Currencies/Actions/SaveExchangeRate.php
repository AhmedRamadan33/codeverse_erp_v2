<?php

namespace Modules\Core\Currencies\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Core\Currencies\Currencies;
use Modules\Core\Models\Currency;
use Modules\Core\Models\ExchangeRate;

/**
 * Records the rate of a foreign currency for a date; saving the same date again replaces it.
 * Posted documents keep the rate they were posted with, so changing a rate never alters them.
 */
class SaveExchangeRate
{
    public function __construct(private readonly Currencies $currencies) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')->where('is_active', true)],
            'date' => ['required', 'date'],
            'rate' => ['required', 'decimal:0,6', 'gt:0', 'max:999999999999'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     */
    public function handle(User $actor, array $data): ExchangeRate
    {
        Gate::forUser($actor)->authorize('core.exchange_rates.manage');

        $currency = Currency::findOrFail($data['currency_id']);

        if ($this->currencies->isBase($currency)) {
            throw ValidationException::withMessages(['currency_id' => __('core::currencies.no_rate_for_base')]);
        }

        return DB::transaction(fn () => ExchangeRate::updateOrCreate(
            ['currency_id' => $currency->id, 'date' => $data['date']],
            // Normalise to 6 places; more precision than that is rejected by the validation rule.
            ['rate' => \Brick\Math\BigDecimal::of((string) $data['rate'])->toScale(6), 'created_by' => $actor->id],
        ));
    }

    public function delete(User $actor, ExchangeRate $rate): void
    {
        Gate::forUser($actor)->authorize('core.exchange_rates.manage');

        DB::transaction(fn () => $rate->delete());
    }
}
