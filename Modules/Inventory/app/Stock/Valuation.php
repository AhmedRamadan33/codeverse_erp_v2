<?php

namespace Modules\Inventory\Stock;

use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Modules\Accounting\Enums\JournalType;
use Modules\Accounting\Mappings\AccountResolver;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Posting\JournalEntryData;
use Modules\Accounting\Posting\JournalLineData;
use Modules\Accounting\Posting\PostJournalEntry;
use Modules\Inventory\Enums\StockMoveType;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\Warehouse;
use Modules\Products\Models\Product;

/**
 * Builds the valuation entry for stock moves: Inventory owns valuation (core-design.md §7);
 * the calling module only names the counter account.
 */
class Valuation
{
    public function __construct(
        private readonly AccountResolver $accounts,
        private readonly PostJournalEntry $post,
    ) {}

    /**
     * @param  StockMove[]  $moves
     * @param  array<int, Model|null>  $counterScopes
     */
    public function post(
        array $moves,
        CarbonImmutable $date,
        int $branchId,
        Model $source,
        string $counterAccountKey,
        array $counterScopes = [],
        ?string $description = null,
        ?User $postedBy = null,
    ): ?JournalEntry {
        /** @var array<string, BigDecimal> $totals "accountId:side" => amount */
        $totals = [];
        $add = function (int $accountId, string $side, BigDecimal $amount) use (&$totals) {
            $key = "{$accountId}:{$side}";
            $totals[$key] = ($totals[$key] ?? BigDecimal::zero())->plus($amount);
        };

        $products = Product::with('category')->whereKey(array_map(fn (StockMove $m) => $m->product_id, $moves))->get()->keyBy('id');
        $warehouses = Warehouse::whereKey(array_map(fn (StockMove $m) => $m->warehouse_id, $moves))->get()->keyBy('id');

        $inventoryAccount = fn (StockMove $m) => $this->accounts->resolve('inventory.asset', [
            $products[$m->product_id], $products[$m->product_id]->category, $warehouses[$m->warehouse_id],
        ])->id;

        foreach ($moves as $move) {
            $amount = $move->total_cost->abs();
            if ($amount->isZero()) {
                continue;
            }

            $in = $move->quantity->isPositive();
            $add($inventoryAccount($move), $in ? 'debit' : 'credit', $amount);

            // Transfers move value between inventory accounts only; both legs are in $moves.
            if (! in_array($move->type, [StockMoveType::TransferIn, StockMoveType::TransferOut], true)) {
                $counter = $this->accounts->resolve($counterAccountKey, [
                    $products[$move->product_id], $products[$move->product_id]->category, ...$counterScopes, $warehouses[$move->warehouse_id],
                ])->id;
                $add($counter, $in ? 'credit' : 'debit', $amount);
            }
        }

        // Net each account so a transfer within one inventory account posts nothing.
        $net = [];
        foreach ($totals as $key => $amount) {
            [$accountId, $side] = explode(':', $key);
            $net[$accountId] = ($net[$accountId] ?? BigDecimal::zero())->plus($side === 'debit' ? $amount : $amount->negated());
        }

        $lines = [];
        foreach ($net as $accountId => $amount) {
            if (! $amount->isZero()) {
                $lines[] = $amount->isPositive()
                    ? JournalLineData::debit((int) $accountId, $amount)
                    : JournalLineData::credit((int) $accountId, $amount->negated());
            }
        }

        if ($lines === []) {
            return null;
        }

        $entry = $this->post->handle(new JournalEntryData(
            date: $date,
            branchId: $branchId,
            journalType: JournalType::Inventory,
            lines: $lines,
            description: $description,
            source: $source,
            postedBy: $postedBy,
        ));

        foreach ($moves as $move) {
            $move->update(['journal_entry_id' => $entry->id]);
        }

        return $entry;
    }
}
