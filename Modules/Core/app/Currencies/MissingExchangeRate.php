<?php

namespace Modules\Core\Currencies;

use DateTimeInterface;
use Modules\Core\Models\Currency;
use RuntimeException;

class MissingExchangeRate extends RuntimeException
{
    public static function for(Currency $currency, DateTimeInterface $date): self
    {
        return new self(__('core::currencies.missing_rate', [
            'currency' => $currency->code,
            'date' => $date->format('Y-m-d'),
        ]));
    }
}
