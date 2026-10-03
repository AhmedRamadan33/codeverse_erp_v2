<?php

namespace Modules\Inventory\Documents\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Core\Documents\DocumentStatus;
use Modules\Core\Sequences\NextNumber;
use Modules\Inventory\Documents\DocumentLines;
use Modules\Inventory\Enums\StockMoveType;
use Modules\Inventory\Models\StockAdjustment;
use Modules\Inventory\Models\StockAdjustmentLine;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Stock\Actions\IssueStock;
use Modules\Inventory\Stock\Actions\ReceiveStock;
use Modules\Inventory\Stock\Actions\ReverseStock;
use Modules\Inventory\Stock\StockLineData;
use Modules\Inventory\Stock\StockOperationData;

/**
 * Stock adjustments (count differences, damage, samples) and opening stock.
 * Increases: Dr inventory / Cr stock gains (or opening balances); decreases: Dr stock losses / Cr inventory.
 */
class AdjustmentActions
{
    public function __construct(
        private readonly DocumentLines $documentLines,
        private readonly ReceiveStock $receive,
        private readonly IssueStock $issue,
        private readonly ReverseStock $reverse,
        private readonly NextNumber $numbers,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'kind' => ['required', Rule::in(['adjustment', 'opening'])],
            'description' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'lines.*.unit_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'decimal:0,4', 'not_in:0', 'min:-99999999999', 'max:99999999999'],
            'lines.*.unit_cost' => ['nullable', 'decimal:0,4', 'min:0', 'max:99999999999999'],
            'lines.*.batch_number' => ['nullable', 'string', 'max:64'],
            'lines.*.expiry_date' => ['nullable', 'date'],
            'lines.*.serials' => ['nullable', 'array'],
            'lines.*.serials.*' => ['string', 'max:128'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     */
    public function save(User $actor, array $data, ?StockAdjustment $adjustment = null): StockAdjustment
    {
        Gate::forUser($actor)->authorize('inventory.adjustments.create');

        if ($adjustment && $adjustment->status !== DocumentStatus::Draft) {
            throw ValidationException::withMessages(['adjustment' => __('core::documents.not_draft')]);
        }

        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        if (! $actor->canAccessBranch($warehouse->branch_id)) {
            throw ValidationException::withMessages(['warehouse_id' => __('inventory::documents.warehouse_not_allowed')]);
        }

        $converted = $this->documentLines->convert($data['lines']);

        foreach (array_values($data['lines']) as $i => $line) {
            $quantity = BigDecimal::of((string) $line['quantity']);

            if ($data['kind'] === 'opening' && ($quantity->isNegative() || ($line['unit_cost'] ?? '') === '' || $line['unit_cost'] === null)) {
                throw ValidationException::withMessages(["lines.{$i}.unit_cost" => __('inventory::documents.opening_needs_cost')]);
            }
        }

        return DB::transaction(function () use ($actor, $data, $adjustment, $warehouse, $converted) {
            $adjustment ??= new StockAdjustment(['status' => DocumentStatus::Draft, 'created_by' => $actor->id]);
            $adjustment->fill([
                'date' => $data['date'],
                'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->id,
                'kind' => $data['kind'],
                'description' => $data['description'] ?? null,
            ])->save();

            $adjustment->lines()->delete();

            foreach (array_values($data['lines']) as $i => $line) {
                $adjustment->lines()->create([
                    'line_no' => $i + 1,
                    'product_id' => $line['product_id'],
                    'unit_id' => $line['unit_id'],
                    'quantity' => $converted[$i]['quantity'],
                    'base_quantity' => $converted[$i]['base_quantity'],
                    'unit_cost' => isset($line['unit_cost']) && $line['unit_cost'] !== '' ? (string) $line['unit_cost'] : null,
                    'batch_number' => $line['batch_number'] ?? null,
                    'expiry_date' => $line['expiry_date'] ?? null,
                    'serials' => array_values(array_filter($line['serials'] ?? [])) ?: null,
                    'description' => $line['description'] ?? null,
                ]);
            }

            return $adjustment;
        });
    }

    public function post(User $actor, StockAdjustment $adjustment): StockAdjustment
    {
        Gate::forUser($actor)->authorize('inventory.adjustments.post');

        return DB::transaction(function () use ($actor, $adjustment) {
            $adjustment = StockAdjustment::whereKey($adjustment->id)->lockForUpdate()->firstOrFail();

            if ($adjustment->status !== DocumentStatus::Draft) {
                throw ValidationException::withMessages(['adjustment' => __('core::documents.not_draft')]);
            }

            $lines = $adjustment->lines()->with('product.units')->get();
            $toLine = fn (StockAdjustmentLine $line, bool $withCost) => new StockLineData(
                productId: $line->product_id,
                warehouseId: $adjustment->warehouse_id,
                quantity: $line->base_quantity->abs(),
                unitCost: $withCost && $line->unit_cost !== null
                    ? $this->documentLines->baseCost($line->unit_cost, $line->product->units->firstWhere('unit_id', $line->unit_id)->factor)
                    : null,
                batchNumber: $line->batch_number,
                expiryDate: $line->expiry_date,
                serials: $line->serials ?? [],
                sourceLine: $line,
            );

            $increases = $lines->filter(fn ($l) => $l->base_quantity->isPositive())->values();
            $decreases = $lines->filter(fn ($l) => $l->base_quantity->isNegative())->values();
            $opening = $adjustment->isOpening();

            $operation = fn ($items, string $counter, bool $withCost) => new StockOperationData(
                date: $adjustment->date,
                branchId: $adjustment->branch_id,
                type: $opening ? StockMoveType::Opening : StockMoveType::Adjustment,
                source: $adjustment,
                lines: $items->map(fn ($l) => $toLine($l, $withCost))->all(),
                counterAccountKey: $counter,
                description: $adjustment->description ?? __('inventory::documents.adjustment'),
                postedBy: $actor,
            );

            $costs = [];
            if ($increases->isNotEmpty()) {
                $result = $this->receive->handle($operation($increases, $opening ? 'opening_balance_equity' : 'inventory.adjustment_gain', true));
                foreach ($increases as $i => $line) {
                    $costs[$line->id] = $result->lineCost($i);
                }
            }
            if ($decreases->isNotEmpty()) {
                $result = $this->issue->handle($operation($decreases, 'inventory.adjustment_loss', false));
                foreach ($decreases as $i => $line) {
                    $costs[$line->id] = $result->lineCost($i);
                }
            }

            foreach ($lines as $line) {
                $line->update(['total_cost' => $costs[$line->id]]);
            }

            $adjustment->update([
                'status' => DocumentStatus::Posted,
                'number' => $this->numbers->handle(StockAdjustment::SEQUENCE, $adjustment->branch_id, $adjustment->date),
                'posted_by' => $actor->id,
                'posted_at' => now(),
            ]);

            return $adjustment;
        });
    }

    public function cancel(User $actor, StockAdjustment $adjustment, string $reason): StockAdjustment
    {
        Gate::forUser($actor)->authorize('inventory.adjustments.cancel');

        return DB::transaction(function () use ($actor, $adjustment, $reason) {
            $adjustment = StockAdjustment::whereKey($adjustment->id)->lockForUpdate()->firstOrFail();

            if ($adjustment->status !== DocumentStatus::Posted) {
                throw ValidationException::withMessages(['adjustment' => __('core::documents.not_posted')]);
            }

            $this->reverse->handle($adjustment, CarbonImmutable::today()->max($adjustment->date), $reason, $adjustment->branch_id, $actor);
            $adjustment->update(['status' => DocumentStatus::Cancelled, 'cancelled_by' => $actor->id, 'cancelled_at' => now(), 'cancel_reason' => $reason]);

            return $adjustment;
        });
    }

    public function delete(User $actor, StockAdjustment $adjustment): void
    {
        Gate::forUser($actor)->authorize('inventory.adjustments.create');

        if ($adjustment->status !== DocumentStatus::Draft) {
            throw ValidationException::withMessages(['adjustment' => __('core::documents.not_draft')]);
        }

        DB::transaction(fn () => $adjustment->delete());
    }
}
