<form wire:submit="save" class="card">
    <div class="card-body">
        <div class="mb-3 col-md-4">
            <label class="form-label">{{ __('core::users.fields.role_name') }}</label>
            <input type="text" wire:model="name" class="form-control @error('name') is-invalid @enderror">
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <label class="form-label">{{ __('core::users.fields.permissions') }}</label>
        <table class="table table-sm table-bordered">
            <tbody>
            @foreach ($groups as $key => $group)
                <tr wire:key="group-{{ $key }}">
                    <th class="w-25">{{ $group['label'] }}</th>
                    <td>
                        @foreach ($group['permissions'] as $permission => $label)
                            <div class="form-check form-check-inline">
                                <input id="perm-{{ $permission }}" type="checkbox" value="{{ $permission }}" wire:model="permissions" class="form-check-input">
                                <label for="perm-{{ $permission }}" class="form-check-label">{{ $label }}</label>
                            </div>
                        @endforeach
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary">{{ __('core::ui.save') }}</button>
        <a href="{{ route('core.roles.index') }}" class="btn btn-outline-secondary">{{ __('core::ui.cancel') }}</a>
    </div>
</form>
