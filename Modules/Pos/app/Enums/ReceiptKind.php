<?php

namespace Modules\Pos\Enums;

enum ReceiptKind: string
{
    case Sale = 'sale';
    case Return = 'return';

    public function label(): string
    {
        return __("pos::receipts.kind.{$this->value}");
    }
}
