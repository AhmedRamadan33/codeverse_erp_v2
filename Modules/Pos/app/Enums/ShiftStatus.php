<?php

namespace Modules\Pos\Enums;

enum ShiftStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return __("pos::shifts.status.{$this->value}");
    }

    public function badge(): string
    {
        return $this === self::Open ? 'text-bg-success' : 'text-bg-secondary';
    }
}
