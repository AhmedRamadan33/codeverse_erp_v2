<?php

namespace Modules\Purchases\Livewire\Returns;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Purchases\Actions\ReturnActions;
use Modules\Purchases\Models\PurchaseReturn;

#[Layout('core::layouts.app')]
class Show extends Component
{
    public int $returnId;

    public bool $showCancel = false;

    public string $cancelReason = '';

    public function mount(int $id): void
    {
        Gate::authorize('purchases.returns.view');

        $return = PurchaseReturn::findOrFail($id);
        abort_unless(auth()->user()->canAccessBranch($return->branch_id), 404);
        $this->returnId = $return->id;
    }

    public function post(ReturnActions $actions): void
    {
        $return = $actions->post(auth()->user(), PurchaseReturn::findOrFail($this->returnId));
        session()->flash('status', __('purchases::returns.posted', ['number' => $return->number]));
    }

    public function cancel(ReturnActions $actions): void
    {
        $this->validate(['cancelReason' => ['required', 'string', 'max:255']]);
        $actions->cancel(auth()->user(), PurchaseReturn::findOrFail($this->returnId), $this->cancelReason);

        $this->showCancel = false;
        session()->flash('status', __('purchases::returns.cancelled'));
    }

    public function delete(ReturnActions $actions): void
    {
        $return = PurchaseReturn::findOrFail($this->returnId);
        $actions->delete(auth()->user(), $return);
        $this->redirectRoute('purchases.invoices.show', $return->purchase_invoice_id);
    }

    public function render()
    {
        $return = PurchaseReturn::with(['lines.product', 'lines.unit', 'partner', 'invoice', 'currency', 'journalEntry'])->findOrFail($this->returnId);

        return view('purchases::livewire.returns.show', ['return' => $return, 'scale' => $return->currency->decimal_places])
            ->title(($return->number ?? __('core::documents.status.draft')).' — '.__('purchases::returns.one'));
    }
}
