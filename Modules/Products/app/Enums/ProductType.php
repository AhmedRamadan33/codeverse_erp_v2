<?php

namespace Modules\Products\Enums;

enum ProductType: string
{
    // Tracked in stock with moves and costing.
    case Stockable = 'stockable';
    // Bought and used without stock tracking (cleaning supplies, packaging...).
    case Consumable = 'consumable';
    case Service = 'service';

    public function tracksStock(): bool
    {
        return $this === self::Stockable;
    }

    public function label(): string
    {
        return __('products::products.types.'.$this->value);
    }
}
