<?php

namespace Modules\Core\Livewire\Branches;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Branches\Actions\SaveBranch;
use Modules\Core\Models\Branch;

#[Layout('core::layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        Gate::authorize('core.branches.view');
    }

    public function create(): void
    {
        Gate::authorize('core.branches.manage');

        $this->resetValidation();
        $this->editingId = null;
        $this->form = ['name_ar' => '', 'name_en' => '', 'code' => '', 'address' => '', 'phone' => '', 'is_active' => true];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('core.branches.manage');

        $branch = Branch::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $branch->id;
        $this->form = [
            'name_ar' => $branch->getTranslation('name', 'ar', false),
            'name_en' => $branch->getTranslation('name', 'en', false),
            'code' => $branch->code,
            'address' => $branch->address,
            'phone' => $branch->phone,
            'is_active' => $branch->is_active,
        ];
        $this->showForm = true;
    }

    public function save(SaveBranch $action): void
    {
        $branch = $this->editingId ? Branch::findOrFail($this->editingId) : null;
        $rules = collect(SaveBranch::rules($branch))->mapWithKeys(fn ($r, $k) => ["form.{$k}" => $r])->all();

        $action->handle(auth()->user(), $this->validate($rules)['form'], $branch);

        $this->showForm = false;
        session()->flash('status', __('core::ui.saved'));
    }

    public function render()
    {
        return view('core::livewire.branches.index', ['branches' => Branch::orderBy('code')->get()])
            ->title(__('core::menu.branches'));
    }
}
