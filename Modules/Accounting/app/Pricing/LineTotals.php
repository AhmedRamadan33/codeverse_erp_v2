<?php

namespace Modules\Accounting\Pricing;

use Brick\Math\BigDecimal;

final readonly class LineTotals
{
    public function __construct(
        public BigDecimal $gross,
        public BigDecimal $lineDiscount,
        public BigDecimal $documentDiscount,
        public BigDecimal $net,
        public BigDecimal $tax,
        public BigDecimal $total,
    ) {}
}
