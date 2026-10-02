<?php

namespace Modules\Core\Livewire\Roles;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Permissions\PermissionSynchronizer;
use Modules\Core\Users\Actions\SaveRole;
use Spatie\Permission\Models\Role;

#[Layout('core::layouts.app')]
class Index extends Component
{
    public function mount(): void
    {
        Gate::authorize('core.roles.manage');
    }

    public function delete(int $id, SaveRole $action): void
    {
        $action->delete(auth()->user(), Role::findOrFail($id));

        session()->flash('status', __('core::ui.deleted'));
    }

    public function render()
    {
        return view('core::livewire.roles.index', [
            'roles' => Role::withCount(['users', 'permissions'])->orderBy('name')->get(),
            'superAdmin' => PermissionSynchronizer::SUPER_ADMIN,
        ])->title(__('core::menu.roles'));
    }
}
