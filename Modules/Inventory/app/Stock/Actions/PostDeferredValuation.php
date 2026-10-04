<?php

namespace Modules\Inventory\Stock\Actions;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Modules\Accounting\Models\JournalEntry;
use Modules\Core\Support\Attributes\ModuleApi;
use Modules\Core\Support\TransactionGuard;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Stock\Valuation;

/**
 * Posts one valuation entry for moves whose valuation was deferred (POS receipts of a shift,
 * core-design.md §7.1), from the costs already fixed on the moves.
 */
#[ModuleApi]
class PostDeferredValuation
{
    public function __construct(private readonly Valuation $valuation) {}

    /**
     * @param  array<string, int[]>  $sources  ids of the documents whose moves to value, by morph class
     * @param  Model  $entrySource  the document the entry belongs to (e.g. the shift)
     */
    public function handle(array $sources, Model $entrySource, CarbonImmutable $date, int $branchId, string $counterAccountKey, ?User $postedBy = null): ?JournalEntry
    {
        TransactionGuard::assertActive('PostDeferredValuation');

        // Without sources the filter below would match every deferred move.
        $sources = array_filter($sources);
        if ($sources === []) {
            return null;
        }

        $moves = StockMove::query()
            ->whereNull('journal_entry_id')
            ->where(function ($query) use ($sources) {
                foreach ($sources as $type => $ids) {
                    $query->orWhere(fn ($q) => $q->where('source_type', $type)->whereIn('source_id', $ids));
                }
            })
            ->lockForUpdate()
            ->get()
            ->all();

        if ($moves === []) {
            return null;
        }

        return $this->valuation->post($moves, $date, $branchId, $entrySource, $counterAccountKey, postedBy: $postedBy);
    }
}
