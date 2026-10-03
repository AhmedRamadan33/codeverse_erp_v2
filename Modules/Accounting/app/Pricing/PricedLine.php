<?php

namespace Modules\Accounting\Pricing;

use Brick\Math\BigDecimal;
use Modules\Accounting\Models\Tax;

final readonly class PricedLine
{
    public BigDecimal $quantity;

    public BigDecimal $unitPrice;

    public function __construct(
        BigDecimal|string $quantity,
        BigDecimal|string $unitPrice,
        public ?Discount $discount = null,
        public ?Tax $tax = null,
    ) {
        $this->quantity = BigDecimal::of($quantity);
        $this->unitPrice = BigDecimal::of($unitPrice);
    }
}
