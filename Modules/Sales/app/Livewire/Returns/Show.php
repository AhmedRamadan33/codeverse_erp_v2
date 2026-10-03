<?php

namespace Modules\Sales\Livewire\Returns;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Sales\Actions\ReturnActions;
use Modules\Sales\Models\SalesReturn;

#[Layout('core::layouts.app')]
class Show extends Component
{
    public int $returnId;

    public bool $showCancel = false;

    public string $cancelReason = '';

    public function mount(int $id): void
    {
        Gate::authorize('sales.returns.view');

        $return = SalesReturn::findOrFail($id);
        abort_unless(auth()->user()->canAccessBranch($return->branch_id), 404);
        $this->returnId = $return->id;
    }

    public function post(ReturnActions $actions): void
    {
        $return = $actions->post(auth()->user(), SalesReturn::findOrFail($this->returnId));
        session()->flash('status', __('sales::returns.posted', ['number' => $return->number]));
    }

    public function cancel(ReturnActions $actions): void
    {
        $this->validate(['cancelReason' => ['required', 'string', 'max:255']]);
        $actions->cancel(auth()->user(), SalesReturn::findOrFail($this->returnId), $this->cancelReason);

        $this->showCancel = false;
        session()->flash('status', __('sales::returns.cancelled'));
    }

    public function delete(ReturnActions $actions): void
    {
        $return = SalesReturn::findOrFail($this->returnId);
        $actions->delete(auth()->user(), $return);
        $this->redirectRoute('sales.invoices.show', $return->sales_invoice_id);
    }

    public function render()
    {
        $return = SalesReturn::with(['lines.product', 'lines.unit', 'partner', 'invoice', 'currency', 'journalEntry'])->findOrFail($this->returnId);

        return view('sales::livewire.returns.show', ['return' => $return, 'scale' => $return->currency->decimal_places])
            ->title(($return->number ?? __('core::documents.status.draft')).' — '.__('sales::returns.one'));
    }
}
