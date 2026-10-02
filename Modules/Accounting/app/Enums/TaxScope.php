<?php

namespace Modules\Accounting\Enums;

enum TaxScope: string
{
    case Sales = 'sales';
    case Purchases = 'purchases';
    case Both = 'both';

    public function label(): string
    {
        return __('accounting::taxes.scopes.'.$this->value);
    }
}
