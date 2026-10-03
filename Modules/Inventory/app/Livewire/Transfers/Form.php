<?php

namespace Modules\Inventory\Livewire\Transfers;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Documents\DocumentStatus;
use Modules\Inventory\Documents\Actions\TransferActions;
use Modules\Inventory\Livewire\Concerns\EditsStockLines;
use Modules\Inventory\Models\StockTransfer;
use Modules\Inventory\Models\Warehouse;

#[Layout('core::layouts.app')]
class Form extends Component
{
    use EditsStockLines;

    public ?int $transferId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(?int $id = null): void
    {
        Gate::authorize('inventory.transfers.create');

        if ($id === null) {
            $this->form = [
                'date' => now()->toDateString(),
                'from_warehouse_id' => $this->sourceWarehouses()->value('id'),
                'to_warehouse_id' => null,
                'description' => '',
                'lines' => [$this->blankLine()],
            ];

            return;
        }

        $transfer = StockTransfer::with('lines')->findOrFail($id);
        abort_unless($transfer->status === DocumentStatus::Draft && auth()->user()->canAccessBranch($transfer->branch_id), 404);

        $this->transferId = $transfer->id;
        $this->form = [
            'date' => $transfer->date->toDateString(),
            'from_warehouse_id' => $transfer->from_warehouse_id,
            'to_warehouse_id' => $transfer->to_warehouse_id,
            'description' => $transfer->description,
            'lines' => $transfer->lines->map(fn ($l) => [
                'product_id' => $l->product_id,
                'unit_id' => $l->unit_id,
                'quantity' => (string) $l->quantity->strippedOfTrailingZeros(),
                'unit_cost' => '',
                'batch_number' => $l->batch_number,
                'expiry_date' => null,
                'serials' => implode(', ', $l->serials ?? []),
                'description' => '',
            ])->all(),
        ];
    }

    /**
     * Stock leaves only warehouses of the user's branches; it may go to any active warehouse.
     */
    private function sourceWarehouses()
    {
        $user = auth()->user();

        return Warehouse::where('is_active', true)
            ->when(! $user->can('core.branches.all_access'), fn ($q) => $q->whereIn('branch_id', $user->branches()->select('branches.id')))
            ->orderBy('code');
    }

    public function save(TransferActions $actions): void
    {
        $data = validator($this->payload(), TransferActions::rules())->validate();
        $transfer = $actions->save(auth()->user(), $data, $this->transferId ? StockTransfer::findOrFail($this->transferId) : null);

        session()->flash('status', __('core::ui.saved'));
        $this->redirectRoute('inventory.transfers.show', $transfer->id);
    }

    public function render()
    {
        return view('inventory::livewire.transfers.form', [
            'sources' => $this->sourceWarehouses()->get(),
            'destinations' => Warehouse::where('is_active', true)->orderBy('code')->get(),
            'products' => $this->productOptions(),
        ])->title(__('inventory::documents.new_transfer'));
    }
}
