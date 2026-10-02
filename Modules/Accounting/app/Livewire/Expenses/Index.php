<?php

namespace Modules\Accounting\Livewire\Expenses;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Accounting\Models\ExpenseVoucher;
use Modules\Core\Documents\DocumentStatus;

#[Layout('core::layouts.app')]
class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        Gate::authorize('accounting.vouchers.view');
    }

    public function render()
    {
        $user = auth()->user();

        $vouchers = ExpenseVoucher::query()
            ->with(['paymentMethod', 'currency', 'partner'])
            ->when(! $user->can('core.branches.all_access'), fn ($q) => $q->whereIn('branch_id', $user->branches()->select('branches.id')))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('number', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%")
                ->orWhere('reference', 'like', "%{$this->search}%")))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate(30);

        return view('accounting::livewire.expenses.index', [
            'vouchers' => $vouchers,
            'statuses' => DocumentStatus::cases(),
        ])->title(__('accounting::expenses.title'));
    }
}
