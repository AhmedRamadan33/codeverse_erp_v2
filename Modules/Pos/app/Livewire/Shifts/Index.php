<?php

namespace Modules\Pos\Livewire\Shifts;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Pos\Enums\ShiftStatus;
use Modules\Pos\Models\Shift;

#[Layout('core::layouts.app')]
class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        Gate::authorize('pos.shifts.view');
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $user = auth()->user();

        return view('pos::livewire.shifts.index', [
            'shifts' => Shift::with(['register', 'cashier'])
                ->when(! $user->can('core.branches.all_access'), fn ($q) => $q->whereIn('branch_id', $user->branches()->select('branches.id')))
                ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
                ->orderByDesc('id')
                ->paginate(30),
            'statuses' => ShiftStatus::cases(),
        ])->title(__('pos::shifts.title'));
    }
}
