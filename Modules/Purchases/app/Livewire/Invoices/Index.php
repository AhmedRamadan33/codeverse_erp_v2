<?php

namespace Modules\Purchases\Livewire\Invoices;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Documents\DocumentStatus;
use Modules\Purchases\Models\PurchaseInvoice;

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
        Gate::authorize('purchases.invoices.view');
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $user = auth()->user();

        $invoices = PurchaseInvoice::with(['partner', 'currency'])
            ->when(! $user->can('core.branches.all_access'), fn ($q) => $q->whereIn('branch_id', $user->branches()->select('branches.id')))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('number', 'like', "%{$this->search}%")
                ->orWhere('supplier_reference', 'like', "%{$this->search}%")
                ->orWhereHas('partner', fn ($p) => $p->where('name', 'like', "%{$this->search}%"))))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate(30);

        return view('purchases::livewire.invoices.index', [
            'invoices' => $invoices,
            'statuses' => DocumentStatus::cases(),
        ])->title(__('purchases::invoices.title'));
    }
}
