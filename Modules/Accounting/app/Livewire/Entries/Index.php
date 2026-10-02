<?php

namespace Modules\Accounting\Livewire\Entries;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Accounting\Enums\EntryStatus;
use Modules\Accounting\Enums\JournalType;
use Modules\Accounting\Models\JournalEntry;

#[Layout('core::layouts.app')]
class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url]
    public string $search = '';

    #[Url]
    public string $type = '';

    #[Url]
    public string $status = '';

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    public function mount(): void
    {
        Gate::authorize('accounting.entries.view');
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'type', 'status', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $user = auth()->user();

        $entries = JournalEntry::query()
            ->with('branch')
            ->addSelect(['total' => DB::table('journal_lines')->selectRaw('sum(debit)')->whereColumn('journal_entry_id', 'journal_entries.id')])
            ->when(! $user->can('core.branches.all_access'), fn ($q) => $q->whereIn('branch_id', $user->branches()->select('branches.id')))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('number', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%")))
            ->when($this->type !== '', fn ($q) => $q->where('journal_type', $this->type))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->from, fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($this->to, fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate(30);

        return view('accounting::livewire.entries.index', [
            'entries' => $entries,
            'types' => JournalType::cases(),
            'statuses' => EntryStatus::cases(),
        ])->title(__('accounting::entries.title'));
    }
}
