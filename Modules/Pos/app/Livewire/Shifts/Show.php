<?php

namespace Modules\Pos\Livewire\Shifts;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Core\Currencies\Currencies;
use Modules\Pos\Actions\ShiftActions;
use Modules\Pos\Models\Shift;
use Modules\Pos\Support\ShiftSummary;

/**
 * The shift report (X while open, Z once closed) and, for an open shift, the cash count and close.
 */
#[Layout('core::layouts.app')]
class Show extends Component
{
    public int $shiftId;

    public string $countedCash = '';

    public string $notes = '';

    public function mount(int $id): void
    {
        $shift = Shift::findOrFail($id);

        // A cashier always sees and closes their own shift.
        if ($shift->user_id !== auth()->id()) {
            Gate::authorize('pos.shifts.view');
        } else {
            Gate::authorize('pos.terminal.sell');
        }
        abort_unless(auth()->user()->canAccessBranch($shift->branch_id), 404);

        $this->shiftId = $shift->id;
    }

    public function close(ShiftActions $actions): void
    {
        $this->validate([
            'countedCash' => ['required', 'decimal:0,4', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $shift = $actions->close(auth()->user(), Shift::findOrFail($this->shiftId), $this->countedCash, $this->notes ?: null);
        session()->flash('status', __('pos::shifts.closed', ['number' => $shift->number]));
    }

    public function render(Currencies $currencies)
    {
        $shift = Shift::with(['register.cashMethod', 'cashier', 'closer', 'journalEntry', 'valuationEntry'])->findOrFail($this->shiftId);
        $user = auth()->user();

        return view('pos::livewire.shifts.show', [
            'shift' => $shift,
            'summary' => ShiftSummary::of($shift),
            'methods' => PaymentMethod::orderBy('sort')->get()->keyBy('id'),
            'scale' => $currencies->base()->decimal_places,
            'canClose' => $shift->isOpen() && ($shift->user_id === $user->id ? $user->can('pos.terminal.sell') : $user->can('pos.shifts.manage')),
        ])->title(__('pos::shifts.report').' — '.$shift->number);
    }
}
