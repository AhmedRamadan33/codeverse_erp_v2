<?php

namespace Modules\Accounting\Livewire\Expenses;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Accounting\Expenses\Actions\PostExpenseVoucher;
use Modules\Accounting\Models\ExpenseVoucher;
use Modules\Core\Documents\DocumentStatus;

#[Layout('core::layouts.app')]
class Show extends Component
{
    public int $voucherId;

    public bool $showCancel = false;

    public string $cancelReason = '';

    public function mount(int $id): void
    {
        Gate::authorize('accounting.vouchers.view');

        $voucher = ExpenseVoucher::findOrFail($id);
        abort_unless(auth()->user()->canAccessBranch($voucher->branch_id), 404);
        $this->voucherId = $voucher->id;
    }

    public function post(PostExpenseVoucher $action): void
    {
        $voucher = $action->handle(auth()->user(), ExpenseVoucher::findOrFail($this->voucherId));
        session()->flash('status', __('accounting::vouchers.posted', ['number' => $voucher->number]));
    }

    public function cancel(PostExpenseVoucher $action): void
    {
        $this->validate(['cancelReason' => ['required', 'string', 'max:255']]);

        $action->cancel(auth()->user(), ExpenseVoucher::findOrFail($this->voucherId), $this->cancelReason);
        $this->showCancel = false;
        session()->flash('status', __('accounting::vouchers.cancelled'));
    }

    public function delete(): void
    {
        Gate::authorize('accounting.vouchers.create');

        $voucher = ExpenseVoucher::findOrFail($this->voucherId);

        if ($voucher->status !== DocumentStatus::Draft) {
            throw ValidationException::withMessages(['voucher' => __('core::documents.not_draft')]);
        }

        DB::transaction(fn () => $voucher->delete());
        $this->redirectRoute('accounting.expenses.index');
    }

    public function render()
    {
        $voucher = ExpenseVoucher::with(['lines.account', 'lines.tax', 'paymentMethod', 'currency', 'partner', 'journalEntry', 'branch'])->findOrFail($this->voucherId);

        return view('accounting::livewire.expenses.show', ['voucher' => $voucher])
            ->title(($voucher->number ?? __('core::documents.status.draft')).' — '.__('accounting::expenses.one'));
    }
}
