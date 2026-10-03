<?php

namespace Modules\Inventory\Documents\Actions;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Core\Documents\DocumentStatus;
use Modules\Core\Sequences\NextNumber;
use Modules\Inventory\Documents\DocumentLines;
use Modules\Inventory\Enums\StockMoveType;
use Modules\Inventory\Models\StockTransfer;
use Modules\Inventory\Models\StockTransferLine;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Stock\Actions\ReverseStock;
use Modules\Inventory\Stock\Actions\TransferStock;
use Modules\Inventory\Stock\StockLineData;
use Modules\Inventory\Stock\StockOperationData;

/**
 * Moves stock between warehouses (also across branches) at its current cost.
 */
class TransferActions
{
    public function __construct(
        private readonly DocumentLines $documentLines,
        private readonly TransferStock $transfer,
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
            'from_warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'to_warehouse_id' => ['required', 'integer', 'different:from_warehouse_id', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'description' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'lines.*.unit_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'decimal:0,4', 'gt:0', 'max:99999999999'],
            'lines.*.batch_number' => ['nullable', 'string', 'max:64'],
            'lines.*.serials' => ['nullable', 'array'],
            'lines.*.serials.*' => ['string', 'max:128'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     */
    public function save(User $actor, array $data, ?StockTransfer $transfer = null): StockTransfer
    {
        Gate::forUser($actor)->authorize('inventory.transfers.create');

        if ($transfer && $transfer->status !== DocumentStatus::Draft) {
            throw ValidationException::withMessages(['transfer' => __('core::documents.not_draft')]);
        }

        $from = Warehouse::findOrFail($data['from_warehouse_id']);
        if (! $actor->canAccessBranch($from->branch_id)) {
            throw ValidationException::withMessages(['from_warehouse_id' => __('inventory::documents.warehouse_not_allowed')]);
        }

        $converted = $this->documentLines->convert($data['lines']);

        return DB::transaction(function () use ($actor, $data, $transfer, $from, $converted) {
            $transfer ??= new StockTransfer(['status' => DocumentStatus::Draft, 'created_by' => $actor->id]);
            $transfer->fill([
                'date' => $data['date'],
                'branch_id' => $from->branch_id,
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $data['to_warehouse_id'],
                'description' => $data['description'] ?? null,
            ])->save();

            $transfer->lines()->delete();

            foreach (array_values($data['lines']) as $i => $line) {
                $transfer->lines()->create([
                    'line_no' => $i + 1,
                    'product_id' => $line['product_id'],
                    'unit_id' => $line['unit_id'],
                    'quantity' => $converted[$i]['quantity'],
                    'base_quantity' => $converted[$i]['base_quantity'],
                    'batch_number' => $line['batch_number'] ?? null,
                    'serials' => array_values(array_filter($line['serials'] ?? [])) ?: null,
                ]);
            }

            return $transfer;
        });
    }

    public function post(User $actor, StockTransfer $transfer): StockTransfer
    {
        Gate::forUser($actor)->authorize('inventory.transfers.post');

        return DB::transaction(function () use ($actor, $transfer) {
            $transfer = StockTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if ($transfer->status !== DocumentStatus::Draft) {
                throw ValidationException::withMessages(['transfer' => __('core::documents.not_draft')]);
            }

            $lines = $transfer->lines()->get();

            $result = $this->transfer->handle(new StockOperationData(
                date: $transfer->date,
                branchId: $transfer->branch_id,
                type: StockMoveType::TransferOut,
                source: $transfer,
                lines: $lines->map(fn (StockTransferLine $line) => new StockLineData(
                    productId: $line->product_id,
                    warehouseId: $transfer->from_warehouse_id,
                    quantity: $line->base_quantity,
                    batchNumber: $line->batch_number,
                    serials: $line->serials ?? [],
                    sourceLine: $line,
                ))->all(),
                counterAccountKey: 'inventory.asset',
                description: $transfer->description ?? __('inventory::documents.transfer'),
                postedBy: $actor,
            ), $transfer->to_warehouse_id);

            foreach ($lines as $i => $line) {
                // Each line has an out and an in move of the same value; keep the value once.
                $line->update(['total_cost' => $result->lineCost($i)->dividedBy(2, 4)]);
            }

            $transfer->update([
                'status' => DocumentStatus::Posted,
                'number' => $this->numbers->handle(StockTransfer::SEQUENCE, $transfer->branch_id, $transfer->date),
                'posted_by' => $actor->id,
                'posted_at' => now(),
            ]);

            return $transfer;
        });
    }

    public function cancel(User $actor, StockTransfer $transfer, string $reason): StockTransfer
    {
        Gate::forUser($actor)->authorize('inventory.transfers.cancel');

        return DB::transaction(function () use ($actor, $transfer, $reason) {
            $transfer = StockTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if ($transfer->status !== DocumentStatus::Posted) {
                throw ValidationException::withMessages(['transfer' => __('core::documents.not_posted')]);
            }

            $this->reverse->handle($transfer, CarbonImmutable::today()->max($transfer->date), $reason, $transfer->branch_id, $actor);
            $transfer->update(['status' => DocumentStatus::Cancelled, 'cancelled_by' => $actor->id, 'cancelled_at' => now(), 'cancel_reason' => $reason]);

            return $transfer;
        });
    }

    public function delete(User $actor, StockTransfer $transfer): void
    {
        Gate::forUser($actor)->authorize('inventory.transfers.create');

        if ($transfer->status !== DocumentStatus::Draft) {
            throw ValidationException::withMessages(['transfer' => __('core::documents.not_draft')]);
        }

        DB::transaction(fn () => $transfer->delete());
    }
}
