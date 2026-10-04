<?php

namespace Modules\Pos\Livewire\Receipts;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Currencies\Currencies;
use Modules\Pos\Models\Receipt;

#[Layout('core::layouts.app')]
class Show extends Component
{
    public int $receiptId;

    public function mount(int $id): void
    {
        Gate::authorize('pos.receipts.view');

        $receipt = Receipt::findOrFail($id);
        abort_unless($receipt->number !== null && auth()->user()->canAccessBranch($receipt->branch_id), 404);
        $this->receiptId = $receipt->id;
    }

    public function render(Currencies $currencies)
    {
        $receipt = Receipt::with(['lines.product', 'lines.unit', 'payments.method', 'partner', 'register', 'shift', 'creator', 'original', 'returns', 'journalEntry'])
            ->findOrFail($this->receiptId);

        return view('pos::livewire.receipts.show', [
            'receipt' => $receipt,
            'scale' => $currencies->base()->decimal_places,
            'canReturn' => ! $receipt->isReturn() && auth()->user()->can('pos.returns.create')
                && $receipt->lines->contains(fn ($line) => $line->returnableQuantity()->isPositive()),
        ])->title($receipt->number.' — '.$receipt->kind->label());
    }
}
