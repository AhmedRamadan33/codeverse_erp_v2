<?php

namespace Modules\Accounting\Posting;

use Brick\Math\BigDecimal;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * One line of an entry to post. Amounts are base currency; pass strings or BigDecimal, never floats.
 */
final readonly class JournalLineData
{
    public BigDecimal $debit;

    public BigDecimal $credit;

    public ?BigDecimal $amountCurrency;

    public function __construct(
        public int $accountId,
        BigDecimal|string|int $debit = 0,
        BigDecimal|string|int $credit = 0,
        public ?int $partnerId = null,
        public ?int $branchId = null,
        public ?int $currencyId = null,
        BigDecimal|string|int|null $amountCurrency = null,
        public ?DateTimeInterface $dueDate = null,
        public ?string $description = null,
        public ?Model $sourceLine = null,
    ) {
        $this->debit = BigDecimal::of($debit);
        $this->credit = BigDecimal::of($credit);
        $this->amountCurrency = $amountCurrency === null ? null : BigDecimal::of($amountCurrency);
    }

    /**
     * @param  mixed  ...$named  other constructor arguments by name (partnerId:, description:, ...)
     */
    public static function debit(int $accountId, BigDecimal|string|int $amount, mixed ...$named): self
    {
        return new self(...['accountId' => $accountId, 'debit' => $amount, 'credit' => 0, ...$named]);
    }

    /**
     * @param  mixed  ...$named  other constructor arguments by name (partnerId:, description:, ...)
     */
    public static function credit(int $accountId, BigDecimal|string|int $amount, mixed ...$named): self
    {
        return new self(...['accountId' => $accountId, 'debit' => 0, 'credit' => $amount, ...$named]);
    }

    /**
     * The same line on the opposite side, used by reversals.
     */
    public function reversed(): self
    {
        return new self(
            $this->accountId, $this->credit, $this->debit, $this->partnerId, $this->branchId,
            $this->currencyId, $this->amountCurrency?->negated(), $this->dueDate, $this->description, $this->sourceLine,
        );
    }
}
