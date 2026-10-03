<?php

namespace Modules\Inventory\Stock;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Modules\Inventory\Enums\StockMoveType;

final readonly class StockOperationData
{
    /**
     * @param  StockLineData[]  $lines
     * @param  string  $counterAccountKey  mapping key of the other side of the valuation entry,
     *                                     e.g. "purchases.grni" for a receipt, "inventory.cogs" for a sale
     * @param  array<int, Model|null>  $counterScopes  extra mapping scopes for the counter account (e.g. the partner)
     * @param  bool  $deferValuation  moves and costs are written, the entry is posted later (POS, core-design.md §7.1)
     */
    public function __construct(
        public CarbonImmutable $date,
        public int $branchId,
        public StockMoveType $type,
        public Model $source,
        public array $lines,
        public string $counterAccountKey,
        public array $counterScopes = [],
        public ?string $description = null,
        public ?User $postedBy = null,
        public bool $deferValuation = false,
    ) {}
}
