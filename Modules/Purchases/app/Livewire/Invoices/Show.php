<?php

namespace Modules\Purchases\Livewire\Invoices;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Accounting\Reconciliation\Reconciler;
use Modules\Purchases\Actions\InvoiceActions;
use Modules\Purchases\Models\PurchaseInvoice;

#[Layout('core::layouts.app')]
class Show extends Component
{
    public int $invoiceId;

    public bool $showCancel = false;

    public string $cancelReason = '';

    public function mount(int $id): void
    {
        Gate::authorize('purchases.invoices.view');

        $invoice = PurchaseInvoice::findOrFail($id);
        abort_unless(auth()->user()->canAccessBranch($invoice->branch_id), 404);
        $this->invoiceId = $invoice->id;
    }

    public function post(InvoiceActions $actions): void
    {
        $invoice = $actions->post(auth()->user(), PurchaseInvoice::findOrFail($this->invoiceId));
        session()->flash('status', __('purchases::invoices.posted', ['number' => $invoice->number]));
    }

    public function cancel(InvoiceActions $actions): void
    {
        $this->validate(['cancelReason' => ['required', 'string', 'max:255']]);
        $actions->cancel(auth()->user(), PurchaseInvoice::findOrFail($this->invoiceId), $this->cancelReason);

        $this->showCancel = false;
        session()->flash('status', __('purchases::invoices.cancelled'));
    }

    public function delete(InvoiceActions $actions): void
    {
        $actions->delete(auth()->user(), PurchaseInvoice::findOrFail($this->invoiceId));
        $this->redirectRoute('purchases.invoices.index');
    }

    public function render(Reconciler $reconciler)
    {
        $invoice = PurchaseInvoice::with(['lines.product', 'lines.unit', 'lines.tax', 'partner', 'warehouse', 'currency', 'journalEntry', 'returns'])->findOrFail($this->invoiceId);

        // What is still owed, from the supplier line of the entry (base currency).
        $payable = $invoice->journalEntry?->lines()->where('partner_id', $invoice->partner_id)->where('credit', '>', 0)->first();

        return view('purchases::livewire.invoices.show', [
            'invoice' => $invoice,
            'open' => $payable ? $reconciler->residual($payable) : BigDecimal::zero(),
            'scale' => $invoice->currency->decimal_places,
        ])->title(($invoice->number ?? __('core::documents.status.draft')).' — '.__('purchases::invoices.one'));
    }
}
