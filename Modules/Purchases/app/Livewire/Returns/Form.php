<?php

namespace Modules\Purchases\Livewire\Returns;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Documents\DocumentStatus;
use Modules\Purchases\Actions\ReturnActions;
use Modules\Purchases\Models\PurchaseInvoice;
use Modules\Purchases\Models\PurchaseReturn;

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
        Gate::authorize('purchases.returns.create');

        if ($id !== null) {
            $return = PurchaseReturn::with('lines')->findOrFail($id);
            abort_unless($return->status === DocumentStatus::Draft, 404);
            $this->returnId = $return->id;
            $this->invoiceId = $return->purchase_invoice_id;
            $this->date = $return->date->toDateString();
            $this->description = (string) $return->description;
            foreach ($return->lines as $line) {
                $this->lines[$line->purchase_invoice_line_id] = [
                    'quantity' => (string) $line->quantity->strippedOfTrailingZeros(),
                    'serials' => implode(', ', $line->serials ?? []),
                ];
            }
        } else {
            $this->invoiceId = request()->integer('invoice');
            $this->date = now()->toDateString();
        }

        $invoice = PurchaseInvoice::findOrFail($this->invoiceId);
        abort_unless($invoice->status === DocumentStatus::Posted && auth()->user()->canAccessBranch($invoice->branch_id), 404);
    }

    public function save(ReturnActions $actions): void
    {
        $lines = [];
        foreach ($this->lines as $lineId => $input) {
            if (($input['quantity'] ?? '') !== '' && $input['quantity'] !== '0') {
                $lines[] = [
                    'purchase_invoice_line_id' => $lineId,
                    'quantity' => $input['quantity'],
                    'serials' => array_values(array_filter(array_map('trim', explode(',', (string) ($input['serials'] ?? ''))))),
                ];
            }
        }

        $data = validator(['date' => $this->date, 'description' => $this->description, 'lines' => $lines], ReturnActions::rules())->validate();
        $return = $actions->save(auth()->user(), PurchaseInvoice::findOrFail($this->invoiceId), $data, $this->returnId ? PurchaseReturn::findOrFail($this->returnId) : null);

        session()->flash('status', __('core::ui.saved'));
        $this->redirectRoute('purchases.returns.show', $return->id);
    }

    public function render()
    {
        $invoice = PurchaseInvoice::with(['lines.product', 'lines.unit', 'partner'])->findOrFail($this->invoiceId);

        return view('purchases::livewire.returns.form', [
            'invoice' => $invoice,
            'returnable' => $invoice->lines->mapWithKeys(fn ($l) => [$l->id => $l->returnableQuantity($this->returnId)]),
        ])->title(__('purchases::returns.new'));
    }
}
