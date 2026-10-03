<?php

namespace Modules\Inventory\Enums;

enum SerialStatus: string
{
    case InStock = 'in_stock';
    case Out = 'out';

    public function label(): string
    {
        return __('inventory::moves.serial_status.'.$this->value);
    }
}
