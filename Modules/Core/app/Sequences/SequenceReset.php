<?php

namespace Modules\Core\Sequences;

use DateTimeInterface;

enum SequenceReset: string
{
    case Never = 'never';
    case Yearly = 'yearly';
    case Monthly = 'monthly';

    public function period(DateTimeInterface $date): string
    {
        return match ($this) {
            self::Never => '',
            self::Yearly => $date->format('Y'),
            self::Monthly => $date->format('Y-m'),
        };
    }

    public function label(): string
    {
        return __('core::sequences.reset.'.$this->value);
    }
}
