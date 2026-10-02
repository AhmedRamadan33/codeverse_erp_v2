<?php

namespace Modules\Core\Casts;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Money and quantities as BigDecimal, never float. Usage: AsDecimal::class.':4'
 *
 * @implements CastsAttributes<BigDecimal, BigDecimal|string|int>
 */
class AsDecimal implements CastsAttributes
{
    public function __construct(private readonly int $scale = 4) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?BigDecimal
    {
        return $value === null ? null : BigDecimal::of($value)->toScale($this->scale, RoundingMode::Unnecessary);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value)) {
            throw new \InvalidArgumentException("Float given for decimal attribute [{$key}]; pass a string or BigDecimal.");
        }

        // Rounding here would silently lose money; callers must round explicitly first.
        return (string) BigDecimal::of($value)->toScale($this->scale, RoundingMode::Unnecessary);
    }
}
