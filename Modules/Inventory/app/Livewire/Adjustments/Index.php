<?php

namespace Modules\Inventory\Livewire\Adjustments;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Documents\DocumentStatus;
use Modules\Inventory\Models\StockAdjustment;

#[Layout('core::layouts.app')]
class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        Gate::authorize('inventory.adjustments.view');
    }

    public function render()
    {
        $user = auth()->user();

        return view('inventory::livewire.adjustments.index', [
            'adjustments' => StockAdjustment::with('warehouse')
                ->when(! $user->can('core.branches.all_access'), fn ($q) => $q->whereIn('branch_id', $user->branches()->select('branches.id')))
                ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
                ->orderByDesc('date')->orderByDesc('id')
                ->paginate(30),
            'statuses' => DocumentStatus::cases(),
        ])->title(__('inventory::documents.adjustments'));
    }
}
