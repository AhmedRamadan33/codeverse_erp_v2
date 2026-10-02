<?php

namespace Modules\Core\Livewire\Users;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Models\Branch;
use Modules\Core\Users\Actions\SaveUser;
use Spatie\Permission\Models\Role;

#[Layout('core::layouts.app')]
class Form extends Component
{
    public ?User $user = null;

    /** @var array<string, mixed> */
    public array $form = [
        'name' => '',
        'email' => '',
        'phone' => null,
        'password' => '',
        'locale' => null,
        'is_active' => true,
        'roles' => [],
        'branches' => [],
        'default_branch_id' => null,
    ];

    public function mount(?int $id = null): void
    {
        Gate::authorize('core.users.manage');

        if ($id === null) {
            return;
        }

        $this->user = User::with(['roles', 'branches'])->findOrFail($id);
        $this->form = [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'phone' => $this->user->phone,
            'password' => '',
            'locale' => $this->user->locale,
            'is_active' => $this->user->is_active,
            'roles' => $this->user->roles->pluck('name')->all(),
            'branches' => $this->user->branches->pluck('id')->map(fn ($id) => (string) $id)->all(),
            'default_branch_id' => $this->user->branches->firstWhere('pivot.is_default', true)?->id,
        ];
    }

    public function save(SaveUser $action): void
    {
        $rules = collect(SaveUser::rules($this->user))
            ->mapWithKeys(fn ($rule, $key) => ['form.'.$key => $key === 'default_branch_id' ? ['nullable', 'integer', 'in_array:form.branches.*'] : $rule])
            ->all();

        $action->handle(auth()->user(), $this->validate($rules)['form'], $this->user);

        session()->flash('status', __('core::ui.saved'));
        $this->redirectRoute('core.users.index');
    }

    public function render()
    {
        return view('core::livewire.users.form', [
            'roles' => Role::orderBy('name')->pluck('name'),
            'branches' => Branch::orderBy('code')->get(),
        ])->title($this->user ? __('core::users.edit') : __('core::users.new'));
    }
}
