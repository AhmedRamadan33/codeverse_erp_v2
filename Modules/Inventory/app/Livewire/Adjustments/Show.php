<?php

namespace Modules\Inventory\Livewire\Adjustments;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Inventory\Documents\Actions\AdjustmentActions;
use Modules\Inventory\Models\StockAdjustment;

#[Layout('core::layouts.app')]
class Show extends Component
{
    public int $adjustmentId;

    public bool $showCancel = false;

    public string $cancelReason = '';

    public function mount(int $id): void
    {
        Gate::authorize('inventory.adjustments.view');

        $adjustment = StockAdjustment::findOrFail($id);
        abort_unless(auth()->user()->canAccessBranch($adjustment->branch_id), 404);
        $this->adjustmentId = $adjustment->id;
    }

    public function post(AdjustmentActions $actions): void
    {
        $adjustment = $actions->post(auth()->user(), StockAdjustment::findOrFail($this->adjustmentId));
        session()->flash('status', __('inventory::documents.posted', ['number' => $adjustment->number]));
    }

    public function cancel(AdjustmentActions $actions): void
    {
        $this->validate(['cancelReason' => ['required', 'string', 'max:255']]);
        $actions->cancel(auth()->user(), StockAdjustment::findOrFail($this->adjustmentId), $this->cancelReason);

        $this->showCancel = false;
        session()->flash('status', __('inventory::documents.cancelled'));
    }

    public function delete(AdjustmentActions $actions): void
    {
        $actions->delete(auth()->user(), StockAdjustment::findOrFail($this->adjustmentId));
        $this->redirectRoute('inventory.adjustments.index');
    }

    public function render()
    {
        $adjustment = StockAdjustment::with(['lines.product', 'lines.unit', 'warehouse'])->findOrFail($this->adjustmentId);

        return view('inventory::livewire.adjustments.show', ['adjustment' => $adjustment])
            ->title(($adjustment->number ?? __('core::documents.status.draft')).' — '.__('inventory::documents.adjustment'));
    }
}
