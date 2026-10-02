<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center">
        <input type="search" wire:model.live.debounce.400ms="search" class="form-control w-auto" placeholder="{{ __('core::ui.search') }}">
        @can('core.users.manage')
            <a href="{{ route('core.users.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> {{ __('core::users.new') }}</a>
        @endcan
    </div>
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead>
            <tr>
                <th>{{ __('core::users.fields.name') }}</th>
                <th>{{ __('core::ui.email') }}</th>
                <th>{{ __('core::users.fields.roles') }}</th>
                <th>{{ __('core::users.fields.branches') }}</th>
                <th>{{ __('core::ui.status') }}</th>
                <th class="text-end">{{ __('core::ui.actions') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($users as $user)
                <tr wire:key="user-{{ $user->id }}">
                    <td>{{ $user->name }}</td>
                    <td class="ltr-value">{{ $user->email }}</td>
                    <td>
                        @foreach ($user->roles as $role)
                            <span class="badge text-bg-secondary">{{ $role->name }}</span>
                        @endforeach
                    </td>
                    <td>{{ $user->branches->pluck('name')->join('، ') }}</td>
                    <td>
                        <span @class(['badge', 'text-bg-success' => $user->is_active, 'text-bg-danger' => ! $user->is_active])>
                            {{ $user->is_active ? __('core::ui.active') : __('core::ui.inactive') }}
                        </span>
                    </td>
                    <td class="text-end">
                        @can('core.users.manage')
                            <a href="{{ route('core.users.edit', $user->id) }}" class="btn btn-sm btn-outline-primary">{{ __('core::ui.edit') }}</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('core::ui.no_records') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($users->hasPages())
        <div class="card-footer">{{ $users->links() }}</div>
    @endif
</div>
