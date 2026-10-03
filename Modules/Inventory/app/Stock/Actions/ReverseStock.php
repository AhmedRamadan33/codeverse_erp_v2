<?php

namespace Modules\Inventory\Stock\Actions;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Posting\ReverseJournalEntry;
use Modules\Core\Support\Attributes\ModuleApi;
use Modules\Core\Support\TransactionGuard;
use Modules\Inventory\Enums\StockMoveType;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Stock\StockEngine;
use Modules\Inventory\Stock\StockOperationData;
use Modules\Inventory\Stock\StockResult;

/**
 * Undoes every stock move of a document (used when the document is cancelled): opposite moves
 * at the same cost, and the valuation entries reversed.
 */
#[ModuleApi]
class ReverseStock
{
    public function __construct(
        private readonly StockEngine $engine,
        private readonly ReverseJournalEntry $reverseEntry,
    ) {}

    public function handle(Model $source, CarbonImmutable $date, string $reason, int $branchId, ?User $postedBy = null): StockResult
    {
        TransactionGuard::assertActive('ReverseStock');

        $originals = StockMove::where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->whereNull('reversal_of_id')
            ->whereNotIn('id', StockMove::whereNotNull('reversal_of_id')->select('reversal_of_id'))
            ->orderBy('id')
            ->get();

        if ($originals->isEmpty()) {
            return new StockResult([]);
        }

        $this->engine->lock($originals->pluck('product_id')->all(), $originals->pluck('warehouse_id')->all());

        $op = new StockOperationData($date, $branchId, StockMoveType::Adjustment, $source, [], '', postedBy: $postedBy);

        // Reverse incoming moves last so stock that came in can be taken out again in the same pass.
        $reversals = $originals->sortBy(fn (StockMove $m) => $m->quantity->isPositive() ? 1 : 0)
            ->map(fn (StockMove $move) => [$move, $this->engine->reverse($move, $op)])
            ->values();

        foreach ($originals->pluck('journal_entry_id')->filter()->unique() as $entryId) {
            $reversal = $this->reverseEntry->handle(JournalEntry::findOrFail($entryId), $date, $reason, $postedBy);

            foreach ($reversals as [$original, $move]) {
                if ($original->journal_entry_id === $entryId) {
                    $move->update(['journal_entry_id' => $reversal->id]);
                }
            }
        }

        return new StockResult([$reversals->map(fn ($pair) => $pair[1])->all()]);
    }
}
