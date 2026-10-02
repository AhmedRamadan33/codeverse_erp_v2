<?php

namespace Modules\Accounting\Enums;

enum TaxType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';

    public function label(): string
    {
        return __('accounting::taxes.types.'.$this->value);
    }
}
