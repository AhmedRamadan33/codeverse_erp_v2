<?php

namespace Modules\Inventory\Livewire\Transfers;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Documents\DocumentStatus;
use Modules\Inventory\Models\StockTransfer;

#[Layout('core::layouts.app')]
class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        Gate::authorize('inventory.transfers.view');
    }

    public function render()
    {
        $user = auth()->user();

        return view('inventory::livewire.transfers.index', [
            'transfers' => StockTransfer::with(['fromWarehouse', 'toWarehouse'])
                ->when(! $user->can('core.branches.all_access'), fn ($q) => $q->where(fn ($q) => $q
                    ->whereIn('branch_id', $user->branches()->select('branches.id'))
                    ->orWhereHas('toWarehouse', fn ($w) => $w->whereIn('branch_id', $user->branches()->select('branches.id')))))
                ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
                ->orderByDesc('date')->orderByDesc('id')
                ->paginate(30),
            'statuses' => DocumentStatus::cases(),
        ])->title(__('inventory::documents.transfers'));
    }
}
