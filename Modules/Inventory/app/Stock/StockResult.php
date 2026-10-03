<?php

namespace Modules\Inventory\Stock;

use Brick\Math\BigDecimal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Inventory\Models\StockMove;

/**
 * What a stock operation did, so the calling document can keep each line's cost
 * (needed for returns at original cost and for margin reports).
 */
final class StockResult
{
    /**
     * @param  array<int, StockMove[]>  $movesByLine  input line index => moves (a line may split across batches)
     */
    public function __construct(
        public readonly array $movesByLine,
        public ?JournalEntry $journalEntry = null,
    ) {}

    /**
     * Total cost of a line, positive.
     */
    public function lineCost(int $index): BigDecimal
    {
        return array_reduce($this->movesByLine[$index] ?? [], fn (BigDecimal $c, StockMove $m) => $c->plus($m->total_cost->abs()), BigDecimal::zero()->toScale(4));
    }

    public function totalCost(): BigDecimal
    {
        return array_reduce(array_keys($this->movesByLine), fn (BigDecimal $c, int $i) => $c->plus($this->lineCost($i)), BigDecimal::zero()->toScale(4));
    }

    /**
     * @return StockMove[]
     */
    public function moves(): array
    {
        return array_merge(...array_values($this->movesByLine) ?: [[]]);
    }
}
