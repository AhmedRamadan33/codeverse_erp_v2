<div class="card">
    <div class="card-header d-flex">
        <a href="{{ route('core.roles.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> {{ __('core::users.new_role') }}</a>
    </div>
    <div class="card-body p-0">
        @error('role') <div class="alert alert-danger m-3">{{ $message }}</div> @enderror
        <table class="table table-striped mb-0">
            <thead>
            <tr>
                <th>{{ __('core::users.fields.role_name') }}</th>
                <th>{{ __('core::users.fields.permissions') }}</th>
                <th>{{ __('core::users.fields.users_count') }}</th>
                <th class="text-end">{{ __('core::ui.actions') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($roles as $role)
                <tr wire:key="role-{{ $role->id }}">
                    <td>{{ $role->name }}</td>
                    <td>{{ $role->name === $superAdmin ? __('core::users.super_admin_note') : $role->permissions_count }}</td>
                    <td>{{ $role->users_count }}</td>
                    <td class="text-end text-nowrap">
                        @if ($role->name !== $superAdmin)
                            <a href="{{ route('core.roles.edit', $role->id) }}" class="btn btn-sm btn-outline-primary">{{ __('core::ui.edit') }}</a>
                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="delete({{ $role->id }})" wire:confirm="{{ __('core::ui.confirm_delete') }}">{{ __('core::ui.delete') }}</button>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
