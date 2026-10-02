<?php

namespace Modules\Core\Livewire\Partners;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Models\Partner;
use Modules\Core\Partners\Actions\DeletePartner;

#[Layout('core::layouts.app')]
class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url]
    public string $search = '';

    /** '', 'customers' or 'suppliers' */
    #[Url]
    public string $role = '';

    public function mount(): void
    {
        Gate::authorize('core.partners.view');
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'role'], true)) {
            $this->resetPage();
        }
    }

    public function delete(int $id, DeletePartner $action): void
    {
        $partner = Partner::visibleTo(auth()->user())->findOrFail($id);
        $action->handle(auth()->user(), $partner);

        session()->flash('status', __('core::ui.deleted'));
    }

    public function render()
    {
        $partners = Partner::query()
            ->visibleTo(auth()->user())
            ->with('branch')
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")
                ->orWhere('tax_number', 'like', "%{$this->search}%")))
            ->when($this->role === 'customers', fn ($q) => $q->customers())
            ->when($this->role === 'suppliers', fn ($q) => $q->suppliers())
            ->orderBy('name')
            ->paginate(25);

        return view('core::livewire.partners.index', ['partners' => $partners])
            ->title(__('core::partners.title'));
    }
}
