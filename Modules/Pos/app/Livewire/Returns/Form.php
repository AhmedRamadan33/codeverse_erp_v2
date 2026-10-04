<?php

namespace Modules\Pos\Livewire\Returns;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Core\Currencies\Currencies;
use Modules\Pos\Actions\ReceiptActions;
use Modules\Pos\Actions\ShiftActions;
use Modules\Pos\Enums\ReceiptKind;
use Modules\Pos\Models\Receipt;

/**
 * Returns against a sale receipt found by its number, refunded from the cashier's open shift.
 */
#[Layout('core::layouts.app')]
class Form extends Component
{
    #[Url(as: 'receipt')]
    public string $number = '';

    /** @var array<int, array{quantity: string, serials: string}> original line id => input */
    public array $lines = [];

    public ?int $refundMethodId = null;

    public function mount(ShiftActions $shifts): void
    {
        Gate::authorize('pos.returns.create');

        $this->refundMethodId = $shifts->current(auth()->user())?->register->cash_payment_method_id;
    }

    private function original(): ?Receipt
    {
        $number = trim($this->number);
        if ($number === '') {
            return null;
        }

        $receipt = Receipt::with(['lines.product', 'lines.unit', 'partner'])->where('number', $number)->where('kind', ReceiptKind::Sale)->first();

        return $receipt && auth()->user()->canAccessBranch($receipt->branch_id) ? $receipt : null;
    }

    public function updatedNumber(): void
    {
        $this->lines = [];
        $this->resetErrorBag();
    }

    public function save(ReceiptActions $actions): void
    {
        $original = $this->original() ?? abort(404);
        $lines = [];

        foreach ($this->lines as $lineId => $input) {
            if (($input['quantity'] ?? '') !== '' && $input['quantity'] !== '0') {
                $lines[] = [
                    'original_line_id' => $lineId,
                    'quantity' => $input['quantity'],
                    'serials' => array_values(array_filter(array_map('trim', explode(',', (string) ($input['serials'] ?? ''))))),
                ];
            }
        }

        $data = validator(['lines' => $lines, 'refund_method_id' => $this->refundMethodId], ReceiptActions::returnRules())->validate();
        $return = $actions->return(auth()->user(), $original, $data);

        session()->flash('status', __('pos::receipts.returned', ['number' => $return->number]));
        $this->redirectRoute('pos.receipts.show', $return->id);
    }

    public function render(Currencies $currencies)
    {
        $original = $this->original();

        return view('pos::livewire.returns.form', [
            'original' => $original,
            'returnable' => $original?->lines->mapWithKeys(fn ($l) => [$l->id => $l->returnableQuantity()]) ?? collect(),
            'methods' => PaymentMethod::where('is_active', true)->orderBy('sort')->get(),
            'scale' => $currencies->base()->decimal_places,
            'notFound' => trim($this->number) !== '' && $original === null,
        ])->title(__('pos::receipts.new_return'));
    }
}
