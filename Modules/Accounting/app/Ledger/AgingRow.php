<?php

namespace Modules\Accounting\Ledger;

use Brick\Math\BigDecimal;

final readonly class AgingRow
{
    /**
     * @param  array<string, BigDecimal>  $amounts  current, d30, d60, d90, older (open documents) and unallocated (payments / credit notes not matched yet)
     */
    public function __construct(
        public int $partnerId,
        public string $partnerName,
        public array $amounts,
    ) {}

    /**
     * What the partner owes (or is owed): open documents less unallocated payments.
     */
    public function total(): BigDecimal
    {
        $open = BigDecimal::zero();
        foreach ($this->amounts as $bucket => $amount) {
            $open = $bucket === 'unallocated' ? $open : $open->plus($amount);
        }

        return $open->minus($this->amounts['unallocated']);
    }
}
