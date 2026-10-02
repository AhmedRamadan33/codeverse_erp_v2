<?php

namespace Modules\Accounting\Posting;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use Modules\Accounting\Exceptions\PostingException;
use Modules\Accounting\Models\Account;
use Modules\Core\Currencies\Currencies;

/**
 * The double-entry rules every posted entry satisfies (core-design.md §6.2).
 */
class LineValidator
{
    public function __construct(private readonly Currencies $currencies) {}

    /**
     * @param  JournalLineData[]  $lines
     */
    public function validate(array $lines): void
    {
        if (count($lines) < 2) {
            throw PostingException::because('too_few_lines');
        }

        $accounts = Account::whereKey(array_map(fn (JournalLineData $l) => $l->accountId, $lines))->get()->keyBy('id');
        $baseCurrencyId = $this->currencies->base()->id;
        $debits = BigDecimal::zero();
        $credits = BigDecimal::zero();

        foreach (array_values($lines) as $i => $line) {
            $no = $i + 1;
            $debit = $this->amount($line->debit, $no);
            $credit = $this->amount($line->credit, $no);

            if ($debit->isNegative() || $credit->isNegative()) {
                throw PostingException::because('negative_amount', ['line' => $no]);
            }

            if ($debit->isPositive() === $credit->isPositive()) {
                throw PostingException::because('one_side_per_line', ['line' => $no]);
            }

            $account = $accounts->get($line->accountId);

            if ($account === null || ! $account->isPostable()) {
                throw PostingException::because('account_not_postable', ['line' => $no, 'account' => $account?->label() ?? $line->accountId]);
            }

            if ($account->requires_partner && $line->partnerId === null) {
                throw PostingException::because('partner_required', ['line' => $no, 'account' => $account->label()]);
            }

            $lineCurrency = $line->currencyId ?? $baseCurrencyId;

            if ($account->currency_id !== null && $account->currency_id !== $lineCurrency) {
                throw PostingException::because('account_currency', ['line' => $no, 'account' => $account->label()]);
            }

            if ($line->currencyId !== null && $line->currencyId !== $baseCurrencyId && $line->amountCurrency === null) {
                throw PostingException::because('amount_currency_required', ['line' => $no]);
            }

            $debits = $debits->plus($debit);
            $credits = $credits->plus($credit);
        }

        if (! $debits->isEqualTo($credits)) {
            throw PostingException::because('unbalanced', ['debit' => (string) $debits, 'credit' => (string) $credits]);
        }
    }

    private function amount(BigDecimal $amount, int $line): BigDecimal
    {
        try {
            return $amount->toScale(4, RoundingMode::Unnecessary);
        } catch (RoundingNecessaryException) {
            throw PostingException::because('too_many_decimals', ['line' => $line]);
        }
    }
}
