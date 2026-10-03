<?php

namespace Modules\Accounting\Posting;

use Brick\Math\BigDecimal;
use Modules\Core\Support\Attributes\ModuleApi;

/**
 * Collects amounts per account and side, then produces journal lines (one per account and side).
 * A negative amount moves to the other side.
 */
#[ModuleApi]
class EntryBuilder
{
    /** @var array<string, array{account: int, side: string, amount: BigDecimal, currency: BigDecimal, partner: ?int, due: mixed}> */
    private array $rows = [];

    public function __construct(private readonly ?int $foreignCurrencyId = null) {}

    public function debit(int $accountId, BigDecimal $amount, ?BigDecimal $amountCurrency = null, ?int $partnerId = null, mixed $dueDate = null): void
    {
        $this->add($accountId, 'debit', $amount, $amountCurrency, $partnerId, $dueDate);
    }

    public function credit(int $accountId, BigDecimal $amount, ?BigDecimal $amountCurrency = null, ?int $partnerId = null, mixed $dueDate = null): void
    {
        $this->add($accountId, 'credit', $amount, $amountCurrency, $partnerId, $dueDate);
    }

    private function add(int $accountId, string $side, BigDecimal $amount, ?BigDecimal $amountCurrency, ?int $partnerId, mixed $dueDate): void
    {
        if ($amount->isZero()) {
            return;
        }

        // A negative amount belongs on the other side.
        if ($amount->isNegative()) {
            $side = $side === 'debit' ? 'credit' : 'debit';
            $amount = $amount->negated();
            $amountCurrency = $amountCurrency?->negated();
        }

        $key = "{$accountId}|{$side}|{$partnerId}";
        $row = $this->rows[$key] ?? ['account' => $accountId, 'side' => $side, 'amount' => BigDecimal::zero(), 'currency' => BigDecimal::zero(), 'partner' => $partnerId, 'due' => $dueDate];
        $row['amount'] = $row['amount']->plus($amount);
        $row['currency'] = $row['currency']->plus($amountCurrency ?? BigDecimal::zero());
        $this->rows[$key] = $row;
    }

    public function totalDebit(): BigDecimal
    {
        return $this->sum('debit');
    }

    public function totalCredit(): BigDecimal
    {
        return $this->sum('credit');
    }

    private function sum(string $side): BigDecimal
    {
        return array_reduce(array_filter($this->rows, fn ($r) => $r['side'] === $side), fn (BigDecimal $c, $r) => $c->plus($r['amount']), BigDecimal::zero());
    }

    /**
     * @return JournalLineData[]
     */
    public function lines(): array
    {
        return array_values(array_map(function (array $row) {
            $foreign = $this->foreignCurrencyId
                ? ['currencyId' => $this->foreignCurrencyId, 'amountCurrency' => $row['side'] === 'debit' ? $row['currency'] : $row['currency']->negated()]
                : [];

            return new JournalLineData(...$foreign, ...[
                'accountId' => $row['account'],
                'debit' => $row['side'] === 'debit' ? $row['amount'] : 0,
                'credit' => $row['side'] === 'credit' ? $row['amount'] : 0,
                'partnerId' => $row['partner'],
                'dueDate' => $row['due'],
            ]);
        }, $this->rows));
    }
}
