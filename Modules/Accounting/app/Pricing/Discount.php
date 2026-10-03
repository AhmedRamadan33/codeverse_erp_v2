<?php

namespace Modules\Accounting\Pricing;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * A percentage or a fixed amount; never more than what it applies to.
 */
final readonly class Discount
{
    public BigDecimal $value;

    public function __construct(public bool $isPercent, BigDecimal|string $value)
    {
        $this->value = BigDecimal::of($value);
    }

    public static function percent(BigDecimal|string $value): self
    {
        return new self(true, $value);
    }

    public static function amount(BigDecimal|string $value): self
    {
        return new self(false, $value);
    }

    /**
     * Builds a discount from form input, or null when empty or zero.
     */
    public static function fromInput(?string $type, mixed $value): ?self
    {
        if ($value === null || $value === '' || BigDecimal::of((string) $value)->isZero()) {
            return null;
        }

        return new self($type === 'percent', (string) $value);
    }

    public function amountOf(BigDecimal $base, int $scale): BigDecimal
    {
        $amount = $this->isPercent
            ? $base->multipliedBy($this->value)->dividedBy(100, $scale, RoundingMode::HalfUp)
            : $this->value->toScale($scale, RoundingMode::HalfUp);

        return $amount->isGreaterThan($base) ? $base : $amount;
    }
}
