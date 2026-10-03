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
     * @param  array<int, array{0: string, 1: int}>  $sources  [morph class, id] of the documents whose moves to value
     * @param  Model  $entrySource  the document the entry belongs to (e.g. the shift)
     */
    public function handle(array $sources, Model $entrySource, CarbonImmutable $date, int $branchId, string $counterAccountKey, ?User $postedBy = null): ?JournalEntry
    {
        TransactionGuard::assertActive('PostDeferredValuation');

        $moves = StockMove::query()
            ->whereNull('journal_entry_id')
            ->where(function ($query) use ($sources) {
                foreach ($sources as [$type, $id]) {
                    $query->orWhere(fn ($q) => $q->where('source_type', $type)->where('source_id', $id));
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
