<?php

namespace Modules\Pos\Livewire\Receipts;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Pos\Enums\ReceiptKind;
use Modules\Pos\Models\Receipt;

#[Layout('core::layouts.app')]
class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url]
    public string $search = '';

    #[Url]
    public string $kind = '';

    #[Url]
    public ?int $shift = null;

    public function mount(): void
    {
        Gate::authorize('pos.receipts.view');
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'kind'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $user = auth()->user();

        $receipts = Receipt::with(['partner', 'register', 'creator'])
            ->whereNotNull('number')
            ->when(! $user->can('core.branches.all_access'), fn ($q) => $q->whereIn('branch_id', $user->branches()->select('branches.id')))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('number', 'like', "%{$this->search}%")
                ->orWhereHas('partner', fn ($p) => $p->where('name', 'like', "%{$this->search}%"))))
            ->when($this->kind !== '', fn ($q) => $q->where('kind', $this->kind))
            ->when($this->shift, fn ($q) => $q->where('shift_id', $this->shift))
            ->orderByDesc('id')
            ->paginate(30);

        return view('pos::livewire.receipts.index', ['receipts' => $receipts, 'kinds' => ReceiptKind::cases()])
            ->title(__('pos::receipts.title'));
    }
}
