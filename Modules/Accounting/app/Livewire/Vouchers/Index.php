<?php

namespace Modules\Accounting\Livewire\Vouchers;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Accounting\Vouchers\VoucherKind;
use Modules\Core\Documents\DocumentStatus;

#[Layout('core::layouts.app')]
class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public string $kind;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    public function mount(string $kind): void
    {
        Gate::authorize('accounting.vouchers.view');

        $this->kind = VoucherKind::from($kind)->value;
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $kind = VoucherKind::from($this->kind);
        $user = auth()->user();

        $vouchers = $kind->model()::query()
            ->with(['partner', 'paymentMethod', 'currency'])
            ->when(! $user->can('core.branches.all_access'), fn ($q) => $q->whereIn('branch_id', $user->branches()->select('branches.id')))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('number', 'like', "%{$this->search}%")
                ->orWhere('reference', 'like', "%{$this->search}%")
                ->orWhereHas('partner', fn ($p) => $p->where('name', 'like', "%{$this->search}%"))))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate(30);

        return view('accounting::livewire.vouchers.index', [
            'vouchers' => $vouchers,
            'kindEnum' => $kind,
            'statuses' => DocumentStatus::cases(),
        ])->title(__('accounting::menu.'.$kind->value.'s'));
    }
}
