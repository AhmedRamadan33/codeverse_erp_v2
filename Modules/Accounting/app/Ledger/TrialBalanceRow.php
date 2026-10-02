<?php

namespace Modules\Accounting\Ledger;

use Brick\Math\BigDecimal;
use Modules\Accounting\Models\Account;

final readonly class TrialBalanceRow
{
    public BigDecimal $closing;

    /**
     * @param  BigDecimal  $opening  debit minus credit before the period
     */
    public function __construct(
        public Account $account,
        public BigDecimal $opening,
        public BigDecimal $debit,
        public BigDecimal $credit,
    ) {
        $this->closing = $opening->plus($debit)->minus($credit);
    }
}
