<?php

namespace Modules\Core\Support;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Module API actions never open their own transaction; they require the caller's
 * (docs/architecture/core-design.md §3.2).
 */
final class TransactionGuard
{
    /**
     * Transactions opened by the environment rather than by application code.
     * The test suite sets this to 1 because RefreshDatabase wraps each test in a transaction.
     */
    public static int $baseline = 0;

    public static function assertActive(string $action): void
    {
        if (DB::transactionLevel() <= self::$baseline) {
            throw new LogicException("{$action} must run inside the caller's DB transaction.");
        }
    }
}
