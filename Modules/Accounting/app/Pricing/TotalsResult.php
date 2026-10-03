<?php

namespace Modules\Accounting\Pricing;

use Brick\Math\BigDecimal;

final readonly class TotalsResult
{
    /**
     * @param  array<int, LineTotals>  $lines  same keys as the input lines
     */
    public function __construct(public array $lines, public int $scale) {}

    private function sum(callable $pick): BigDecimal
    {
        return array_reduce($this->lines, fn (BigDecimal $c, LineTotals $l) => $c->plus($pick($l)), BigDecimal::zero()->toScale($this->scale));
    }

    public function subtotal(): BigDecimal
    {
        return $this->sum(fn (LineTotals $l) => $l->gross);
    }

    public function discountTotal(): BigDecimal
    {
        return $this->sum(fn (LineTotals $l) => $l->lineDiscount->plus($l->documentDiscount));
    }

    public function netTotal(): BigDecimal
    {
        return $this->sum(fn (LineTotals $l) => $l->net);
    }

    public function taxTotal(): BigDecimal
    {
        return $this->sum(fn (LineTotals $l) => $l->tax);
    }

    public function total(): BigDecimal
    {
        return $this->sum(fn (LineTotals $l) => $l->total);
    }
}
