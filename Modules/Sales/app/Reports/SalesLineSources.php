<?php

namespace Modules\Sales\Reports;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Query\Builder;

/**
 * Where sold lines come from, for the sales reports. Sales registers its invoices and returns;
 * modules that sell on their own (POS) register theirs, so the reports cover every channel
 * without Sales depending on them.
 *
 * Each source returns a query with these columns, returns negative:
 * channel, date, branch_id, partner_id, product_id, quantity (base units), net (base currency), cost.
 */
class SalesLineSources
{
    /** @var array<string, Closure(CarbonImmutable, CarbonImmutable, ?int): Builder> */
    private array $sources = [];

    /**
     * @param  Closure(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId): Builder  $query
     */
    public function register(string $key, Closure $query): void
    {
        $this->sources[$key] = $query;
    }

    /**
     * All sources as one query (UNION ALL), ready to wrap and group.
     */
    public function union(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId): ?Builder
    {
        $union = null;
        foreach ($this->sources as $query) {
            $part = $query($from, $to, $branchId);
            $union = $union ? $union->unionAll($part) : $part;
        }

        return $union;
    }
}
