<?php

namespace Modules\Inventory\Enums;

enum StockMoveType: string
{
    case Purchase = 'purchase';
    case PurchaseReturn = 'purchase_return';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case Adjustment = 'adjustment';
    case Opening = 'opening';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case ProductionIn = 'production_in';
    case ProductionOut = 'production_out';

    public function label(): string
    {
        return __('inventory::moves.types.'.$this->value);
    }
}
