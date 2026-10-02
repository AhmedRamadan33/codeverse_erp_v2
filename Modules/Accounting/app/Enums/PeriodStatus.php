<?php

namespace Modules\Accounting\Enums;

enum PeriodStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return __('accounting::fiscal.status.'.$this->value);
    }
}
