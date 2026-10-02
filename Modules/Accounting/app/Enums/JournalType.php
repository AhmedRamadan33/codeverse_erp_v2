<?php

namespace Modules\Accounting\Enums;

enum JournalType: string
{
    case Sales = 'sales';
    case Purchases = 'purchases';
    case Inventory = 'inventory';
    case Cash = 'cash';
    case Bank = 'bank';
    case General = 'general';
    case Opening = 'opening';
    case Closing = 'closing';

    public function label(): string
    {
        return __('accounting::journal.types.'.$this->value);
    }
}
