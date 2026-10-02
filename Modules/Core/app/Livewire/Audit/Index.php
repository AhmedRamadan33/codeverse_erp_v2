<?php

namespace Modules\Core\Livewire\Audit;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Models\AuditLog;

#[Layout('core::layouts.app')]
class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url]
    public ?int $userId = null;

    #[Url]
    public string $type = '';

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    public ?int $expanded = null;

    public function mount(): void
    {
        Gate::authorize('core.audit.view');
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['userId', 'type', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function toggle(int $id): void
    {
        $this->expanded = $this->expanded === $id ? null : $id;
    }

    public function render()
    {
        $logs = AuditLog::with('user')
            ->when($this->userId, fn ($q, $id) => $q->where('user_id', $id))
            ->when($this->type !== '', fn ($q) => $q->where('auditable_type', $this->type))
            ->when($this->from, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($this->to, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest('id')
            ->paginate(50);

        return view('core::livewire.audit.index', [
            'logs' => $logs,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'types' => AuditLog::distinct()->orderBy('auditable_type')->pluck('auditable_type'),
        ])->title(__('core::menu.audit'));
    }
}
