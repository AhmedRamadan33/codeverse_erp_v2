<?php

namespace Modules\Sales\Livewire\Returns;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Sales\Models\SalesReturn;

#[Layout('core::layouts.app')]
class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public function mount(): void
    {
        Gate::authorize('sales.returns.view');
    }

    public function render()
    {
        $user = auth()->user();

        return view('sales::livewire.returns.index', [
            'returns' => SalesReturn::with(['partner', 'invoice', 'currency'])
                ->when(! $user->can('core.branches.all_access'), fn ($q) => $q->whereIn('branch_id', $user->branches()->select('branches.id')))
                ->orderByDesc('date')->orderByDesc('id')
                ->paginate(30),
        ])->title(__('sales::returns.title'));
    }
}
