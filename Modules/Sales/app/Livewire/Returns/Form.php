<?php

namespace Modules\Sales\Livewire\Returns;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Documents\DocumentStatus;
use Modules\Sales\Actions\ReturnActions;
use Modules\Sales\Models\SalesInvoice;
use Modules\Sales\Models\SalesReturn;

/**
 * A return starts from its invoice: every invoice line with what can still be returned.
 */
#[Layout('core::layouts.app')]
class Form extends Component
{
    public int $invoiceId;

    public ?int $returnId = null;

    public string $date = '';

    public string $description = '';

    /** @var array<int, array{quantity: string, serials: string}> invoice line id => input */
    public array $lines = [];

    public function mount(?int $id = null): void
    {
        Gate::authorize('sales.returns.create');

        if ($id !== null) {
            $return = SalesReturn::with('lines')->findOrFail($id);
            abort_unless($return->status === DocumentStatus::Draft, 404);
            $this->returnId = $return->id;
            $this->invoiceId = $return->sales_invoice_id;
            $this->date = $return->date->toDateString();
            $this->description = (string) $return->description;
            foreach ($return->lines as $line) {
                $this->lines[$line->sales_invoice_line_id] = [
                    'quantity' => (string) $line->quantity->strippedOfTrailingZeros(),
                    'serials' => implode(', ', $line->serials ?? []),
                ];
            }
        } else {
            $this->invoiceId = request()->integer('invoice');
            $this->date = now()->toDateString();
        }

        $invoice = SalesInvoice::findOrFail($this->invoiceId);
        abort_unless($invoice->status === DocumentStatus::Posted && auth()->user()->canAccessBranch($invoice->branch_id), 404);
    }

    public function save(ReturnActions $actions): void
    {
        $lines = [];
        foreach ($this->lines as $lineId => $input) {
            if (($input['quantity'] ?? '') !== '' && $input['quantity'] !== '0') {
                $lines[] = [
                    'sales_invoice_line_id' => $lineId,
                    'quantity' => $input['quantity'],
                    'serials' => array_values(array_filter(array_map('trim', explode(',', (string) ($input['serials'] ?? ''))))),
                ];
            }
        }

        $data = validator(['date' => $this->date, 'description' => $this->description, 'lines' => $lines], ReturnActions::rules())->validate();
        $return = $actions->save(auth()->user(), SalesInvoice::findOrFail($this->invoiceId), $data, $this->returnId ? SalesReturn::findOrFail($this->returnId) : null);

        session()->flash('status', __('core::ui.saved'));
        $this->redirectRoute('sales.returns.show', $return->id);
    }

    public function render()
    {
        $invoice = SalesInvoice::with(['lines.product', 'lines.unit', 'partner'])->findOrFail($this->invoiceId);

        return view('sales::livewire.returns.form', [
            'invoice' => $invoice,
            'returnable' => $invoice->lines->mapWithKeys(fn ($l) => [$l->id => $l->returnableQuantity($this->returnId)]),
        ])->title(__('sales::returns.new'));
    }
}
