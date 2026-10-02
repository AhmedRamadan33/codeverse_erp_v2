<?php

namespace Modules\Accounting\Enums;

enum EntryStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';

    public function label(): string
    {
        return __('accounting::journal.status.'.$this->value);
    }
}
