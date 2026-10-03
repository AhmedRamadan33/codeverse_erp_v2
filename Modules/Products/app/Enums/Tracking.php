<?php

namespace Modules\Products\Enums;

enum Tracking: string
{
    case None = 'none';
    // Batch / lot with optional expiry date.
    case Batch = 'batch';
    // One serial number per unit.
    case Serial = 'serial';

    public function label(): string
    {
        return __('products::products.tracking.'.$this->value);
    }
}
