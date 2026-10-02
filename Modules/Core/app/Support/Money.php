<?php

namespace Modules\Core\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Display formatting for money and quantities without going through float.
 */
final class Money
{
    /**
     * "1234567.8" → "1,234,567.80"; negative amounts keep their minus sign.
     */
    public static function format(BigDecimal|string|int|null $amount, int $scale = 2): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        $value = BigDecimal::of($amount)->toScale($scale, RoundingMode::HalfUp);
        $negative = $value->isNegative();
        [$integer, $fraction] = array_pad(explode('.', (string) $value->abs()), 2, '');

        $formatted = strrev(implode(',', str_split(strrev($integer), 3)));

        return ($negative ? '-' : '').$formatted.($fraction !== '' ? '.'.$fraction : '');
    }
}
