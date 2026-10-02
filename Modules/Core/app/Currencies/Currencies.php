<?php

namespace Modules\Core\Currencies;

use Brick\Math\BigDecimal;
use DateTimeInterface;
use Modules\Core\Models\Currency;
use Modules\Core\Models\ExchangeRate;
use Modules\Core\Settings\Settings;

class Currencies
{
    public function __construct(private readonly Settings $settings) {}

    public function base(): Currency
    {
        return Currency::where('code', $this->settings->get('core.base_currency'))->firstOrFail();
    }

    public function isBase(Currency $currency): bool
    {
        return $currency->code === $this->settings->get('core.base_currency');
    }

    /**
     * The rate in effect on the given date: the latest one on or before it.
     *
     * @throws MissingExchangeRate
     */
    public function rate(Currency $currency, DateTimeInterface $date): BigDecimal
    {
        if ($this->isBase($currency)) {
            return BigDecimal::one()->toScale(6);
        }

        $rate = ExchangeRate::where('currency_id', $currency->id)
            ->whereDate('date', '<=', $date)
            ->orderByDesc('date')
            ->first();

        return $rate?->rate ?? throw MissingExchangeRate::for($currency, $date);
    }
}
