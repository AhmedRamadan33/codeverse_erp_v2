<?php

namespace Modules\Inventory\Livewire\Adjustments;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Documents\DocumentStatus;
use Modules\Inventory\Documents\Actions\AdjustmentActions;
use Modules\Inventory\Livewire\Concerns\EditsStockLines;
use Modules\Inventory\Models\StockAdjustment;
use Modules\Inventory\Models\Warehouse;

#[Layout('core::layouts.app')]
class Form extends Component
{
    use EditsStockLines;

    public ?int $adjustmentId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(?int $id = null): void
    {
        Gate::authorize('inventory.adjustments.create');

        if ($id === null) {
            $this->form = [
                'date' => now()->toDateString(),
                'warehouse_id' => $this->warehouses()->value('id'),
                'kind' => request()->query('kind') === 'opening' ? 'opening' : 'adjustment',
                'description' => '',
                'lines' => [$this->blankLine()],
            ];

            return;
        }

        $adjustment = StockAdjustment::with('lines')->findOrFail($id);
        abort_unless($adjustment->status === DocumentStatus::Draft && auth()->user()->canAccessBranch($adjustment->branch_id), 404);

        $this->adjustmentId = $adjustment->id;
        $this->form = [
            'date' => $adjustment->date->toDateString(),
            'warehouse_id' => $adjustment->warehouse_id,
            'kind' => $adjustment->kind,
            'description' => $adjustment->description,
            'lines' => $adjustment->lines->map(fn ($l) => [
                'product_id' => $l->product_id,
                'unit_id' => $l->unit_id,
                'quantity' => (string) $l->quantity->strippedOfTrailingZeros(),
                'unit_cost' => $l->unit_cost ? (string) $l->unit_cost->strippedOfTrailingZeros() : '',
                'batch_number' => $l->batch_number,
                'expiry_date' => $l->expiry_date?->toDateString(),
                'serials' => implode(', ', $l->serials ?? []),
                'description' => $l->description,
            ])->all(),
        ];
    }

    private function warehouses()
    {
        $user = auth()->user();

        return Warehouse::where('is_active', true)
            ->when(! $user->can('core.branches.all_access'), fn ($q) => $q->whereIn('branch_id', $user->branches()->select('branches.id')))
            ->orderBy('code');
    }

    public function save(AdjustmentActions $actions): void
    {
        $data = validator($this->payload(), AdjustmentActions::rules())->validate();
        $adjustment = $actions->save(auth()->user(), $data, $this->adjustmentId ? StockAdjustment::findOrFail($this->adjustmentId) : null);

        session()->flash('status', __('core::ui.saved'));
        $this->redirectRoute('inventory.adjustments.show', $adjustment->id);
    }

    public function render()
    {
        return view('inventory::livewire.adjustments.form', [
            'warehouses' => $this->warehouses()->get(),
            'products' => $this->productOptions(),
        ])->title(__('inventory::documents.new_adjustment'));
    }
}
