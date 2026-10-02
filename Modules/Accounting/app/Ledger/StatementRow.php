<?php

namespace Modules\Accounting\Ledger;

use Brick\Math\BigDecimal;

final readonly class StatementRow
{
    /**
     * @param  BigDecimal  $balance  running debit-minus-credit balance after this line
     */
    public function __construct(
        public int $entryId,
        public ?string $number,
        public string $date,
        public ?string $description,
        public ?string $partnerName,
        public string $accountCode,
        public BigDecimal $debit,
        public BigDecimal $credit,
        public BigDecimal $balance,
    ) {}
}
