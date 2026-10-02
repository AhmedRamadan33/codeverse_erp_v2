<?php

namespace Modules\Core\Livewire\Roles;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Permissions\PermissionLabels;
use Modules\Core\Users\Actions\SaveRole;
use Spatie\Permission\Models\Role;

#[Layout('core::layouts.app')]
class Form extends Component
{
    public ?Role $role = null;

    public string $name = '';

    /** @var string[] */
    public array $permissions = [];

    public function mount(?int $id = null): void
    {
        Gate::authorize('core.roles.manage');

        if ($id !== null) {
            $this->role = Role::findOrFail($id);
            $this->name = $this->role->name;
            $this->permissions = $this->role->permissions->pluck('name')->all();
        }
    }

    public function save(SaveRole $action): void
    {
        $action->handle(auth()->user(), $this->validate(SaveRole::rules($this->role)), $this->role);

        session()->flash('status', __('core::ui.saved'));
        $this->redirectRoute('core.roles.index');
    }

    public function render(PermissionLabels $labels)
    {
        return view('core::livewire.roles.form', ['groups' => $labels->grouped()])
            ->title($this->role ? __('core::users.edit_role') : __('core::users.new_role'));
    }
}
