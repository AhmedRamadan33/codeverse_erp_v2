<?php

namespace Modules\Accounting\Pricing;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Core\Support\Attributes\ModuleApi;

/**
 * Line and document totals for sales and purchase documents (core-design.md §5.1):
 * gross → line discount → share of the document discount → net → tax → line total.
 * Every step is rounded to the currency's decimals, and the document discount is
 * distributed so the shares add up to it exactly.
 */
#[ModuleApi]
class DocumentTotals
{
    /**
     * @param  PricedLine[]  $lines
     */
    public function calculate(array $lines, int $scale, ?Discount $documentDiscount = null): TotalsResult
    {
        $round = fn (BigDecimal $v) => $v->toScale($scale, RoundingMode::HalfUp);
        $computed = [];

        foreach ($lines as $i => $line) {
            $gross = $round($line->quantity->multipliedBy($line->unitPrice));
            $lineDiscount = $line->discount ? $line->discount->amountOf($gross, $scale) : BigDecimal::zero()->toScale($scale);
            $computed[$i] = ['gross' => $gross, 'line_discount' => $lineDiscount, 'net_before' => $gross->minus($lineDiscount)];
        }

        $base = array_reduce($computed, fn (BigDecimal $c, array $l) => $c->plus($l['net_before']), BigDecimal::zero()->toScale($scale));
        $docDiscount = $documentDiscount && $base->isPositive() ? $documentDiscount->amountOf($base, $scale) : BigDecimal::zero()->toScale($scale);

        $shares = [];
        foreach ($computed as $i => $l) {
            $shares[$i] = $base->isPositive()
                ? $docDiscount->multipliedBy($l['net_before'])->dividedBy($base, $scale, RoundingMode::HalfUp)
                : BigDecimal::zero()->toScale($scale);
        }

        $remainder = $docDiscount->minus(array_reduce($shares, fn (BigDecimal $c, BigDecimal $s) => $c->plus($s), BigDecimal::zero()));
        if (! $remainder->isZero() && $computed !== []) {
            $largest = array_key_first($computed);
            foreach ($computed as $i => $l) {
                if ($l['net_before']->isGreaterThan($computed[$largest]['net_before'])) {
                    $largest = $i;
                }
            }
            $shares[$largest] = $shares[$largest]->plus($remainder);
        }

        $results = [];
        foreach ($lines as $i => $line) {
            $net = $computed[$i]['net_before']->minus($shares[$i]);
            $tax = $line->tax ? $line->tax->amountOn($net, $scale, $line->quantity) : BigDecimal::zero()->toScale($scale);

            $results[$i] = new LineTotals(
                gross: $computed[$i]['gross'],
                lineDiscount: $computed[$i]['line_discount'],
                documentDiscount: $shares[$i]->toScale($scale),
                net: $net->toScale($scale),
                tax: $tax->toScale($scale),
                total: $net->plus($tax)->toScale($scale),
            );
        }

        return new TotalsResult($results, $scale);
    }
}
