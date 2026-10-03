<?php

namespace Modules\Sales\Livewire\Invoices;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Accounting\Reconciliation\Reconciler;
use Modules\Sales\Actions\InvoiceActions;
use Modules\Sales\Models\SalesInvoice;

#[Layout('core::layouts.app')]
class Show extends Component
{
    public int $invoiceId;

    public bool $showCancel = false;

    public string $cancelReason = '';

    /** The credit-limit warning to confirm before posting (warn mode). */
    public ?string $overLimitWarning = null;

    public function mount(int $id): void
    {
        Gate::authorize('sales.invoices.view');

        $invoice = SalesInvoice::findOrFail($id);
        abort_unless(auth()->user()->canAccessBranch($invoice->branch_id), 404);
        $this->invoiceId = $invoice->id;
    }

    public function post(InvoiceActions $actions, bool $confirmOverLimit = false): void
    {
        try {
            $invoice = $actions->post(auth()->user(), SalesInvoice::findOrFail($this->invoiceId), $confirmOverLimit);
        } catch (ValidationException $e) {
            $warning = $e->errors()['credit_limit_confirm'][0] ?? null;
            if ($warning === null) {
                throw $e;
            }
            $this->overLimitWarning = $warning;

            return;
        }

        $this->overLimitWarning = null;
        session()->flash('status', __('sales::invoices.posted', ['number' => $invoice->number]));
    }

    public function cancel(InvoiceActions $actions): void
    {
        $this->validate(['cancelReason' => ['required', 'string', 'max:255']]);
        $actions->cancel(auth()->user(), SalesInvoice::findOrFail($this->invoiceId), $this->cancelReason);

        $this->showCancel = false;
        session()->flash('status', __('sales::invoices.cancelled'));
    }

    public function delete(InvoiceActions $actions): void
    {
        $actions->delete(auth()->user(), SalesInvoice::findOrFail($this->invoiceId));
        $this->redirectRoute('sales.invoices.index');
    }

    public function render(Reconciler $reconciler)
    {
        $invoice = SalesInvoice::with(['lines.product', 'lines.unit', 'lines.tax', 'partner', 'warehouse', 'currency', 'priceList', 'paymentMethod', 'journalEntry', 'returns'])->findOrFail($this->invoiceId);

        // What is still owed, from the customer line of the entry (base currency).
        $receivable = $invoice->journalEntry?->lines()->where('partner_id', $invoice->partner_id)->where('debit', '>', 0)->first();

        return view('sales::livewire.invoices.show', [
            'invoice' => $invoice,
            'open' => $receivable ? $reconciler->residual($receivable) : BigDecimal::zero(),
            'scale' => $invoice->currency->decimal_places,
            'showCost' => auth()->user()->can('sales.invoices.view_cost'),
        ])->title(($invoice->number ?? __('core::documents.status.draft')).' — '.__('sales::invoices.one'));
    }
}
