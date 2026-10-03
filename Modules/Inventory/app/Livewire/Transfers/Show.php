<?php

namespace Modules\Inventory\Livewire\Transfers;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Inventory\Documents\Actions\TransferActions;
use Modules\Inventory\Models\StockTransfer;

#[Layout('core::layouts.app')]
class Show extends Component
{
    public int $transferId;

    public bool $showCancel = false;

    public string $cancelReason = '';

    public function mount(int $id): void
    {
        Gate::authorize('inventory.transfers.view');

        $transfer = StockTransfer::with('toWarehouse')->findOrFail($id);
        $user = auth()->user();
        abort_unless($user->canAccessBranch($transfer->branch_id) || $user->canAccessBranch($transfer->toWarehouse->branch_id), 404);
        $this->transferId = $transfer->id;
    }

    public function post(TransferActions $actions): void
    {
        $transfer = $actions->post(auth()->user(), StockTransfer::findOrFail($this->transferId));
        session()->flash('status', __('inventory::documents.posted', ['number' => $transfer->number]));
    }

    public function cancel(TransferActions $actions): void
    {
        $this->validate(['cancelReason' => ['required', 'string', 'max:255']]);
        $actions->cancel(auth()->user(), StockTransfer::findOrFail($this->transferId), $this->cancelReason);

        $this->showCancel = false;
        session()->flash('status', __('inventory::documents.cancelled'));
    }

    public function delete(TransferActions $actions): void
    {
        $actions->delete(auth()->user(), StockTransfer::findOrFail($this->transferId));
        $this->redirectRoute('inventory.transfers.index');
    }

    public function render()
    {
        $transfer = StockTransfer::with(['lines.product', 'lines.unit', 'fromWarehouse', 'toWarehouse'])->findOrFail($this->transferId);

        return view('inventory::livewire.transfers.show', ['transfer' => $transfer])
            ->title(($transfer->number ?? __('core::documents.status.draft')).' — '.__('inventory::documents.transfer'));
    }
}
