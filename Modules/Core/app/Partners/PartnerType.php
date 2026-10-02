<?php

namespace Modules\Core\Partners;

enum PartnerType: string
{
    case Person = 'person';
    case Company = 'company';

    public function label(): string
    {
        return __('core::partners.type.'.$this->value);
    }
}
