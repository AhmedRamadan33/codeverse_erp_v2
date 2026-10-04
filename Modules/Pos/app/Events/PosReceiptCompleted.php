<?php

namespace Modules\Pos\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\Pos\Models\Receipt;

/**
 * A sale or return receipt was completed. Optional modules react to it, e.g. EgyptTax submits
 * the E-Receipt (core-design.md §3.3).
 */
class PosReceiptCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Receipt $receipt) {}
}
