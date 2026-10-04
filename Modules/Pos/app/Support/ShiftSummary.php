<?php

namespace Modules\Pos\Support;

use Brick\Math\BigDecimal;
use Modules\Pos\Enums\ReceiptKind;
use Modules\Pos\Models\ReceiptPayment;
use Modules\Pos\Models\Shift;

/**
 * Figures of a shift for its close and its X/Z report: money in and out per payment method
 * (all receipts, credit ones included, because their cash is in the drawer too) and the
 * cash the drawer should hold.
 */
final readonly class ShiftSummary
{
    /**
     * @param  array<int, array{in: BigDecimal, out: BigDecimal}>  $byMethod  payment method id => received / refunded
     */
    private function __construct(
        public int $sales,
        public int $returns,
        public BigDecimal $salesTotal,
        public BigDecimal $returnsTotal,
        public BigDecimal $creditTotal,
        public array $byMethod,
        public BigDecimal $expectedCash,
    ) {}

    public static function of(Shift $shift): self
    {
        $receipts = $shift->receipts()->get(['id', 'kind', 'total', 'paid_total', 'is_credit']);
        $sum = fn ($rows, string $column) => $rows->reduce(fn (BigDecimal $c, $r) => $c->plus($r->{$column}), BigDecimal::zero());
        [$sales, $returns] = $receipts->partition(fn ($r) => $r->kind === ReceiptKind::Sale);

        $byMethod = [];
        $kinds = $receipts->pluck('kind', 'id');
        foreach (ReceiptPayment::whereIn('receipt_id', $receipts->modelKeys())->get() as $payment) {
            $side = $kinds[$payment->receipt_id] === ReceiptKind::Sale ? 'in' : 'out';
            $byMethod[$payment->payment_method_id] ??= ['in' => BigDecimal::zero(), 'out' => BigDecimal::zero()];
            $byMethod[$payment->payment_method_id][$side] = $byMethod[$payment->payment_method_id][$side]->plus($payment->amount);
        }

        $cash = $byMethod[$shift->register->cash_payment_method_id] ?? ['in' => BigDecimal::zero(), 'out' => BigDecimal::zero()];

        return new self(
            sales: $sales->count(),
            returns: $returns->count(),
            salesTotal: $sum($sales, 'total'),
            returnsTotal: $sum($returns, 'total'),
            creditTotal: $sum($sales->where('is_credit', true), 'total')->minus($sum($sales->where('is_credit', true), 'paid_total')),
            byMethod: $byMethod,
            expectedCash: $shift->opening_float->plus($cash['in'])->minus($cash['out'])->toScale(4),
        );
    }
}
