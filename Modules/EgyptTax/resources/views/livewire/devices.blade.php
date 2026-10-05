<div>
    @if ($showForm)
        <form wire:submit="save" class="card mb-3">
            <div class="card-body row g-3">
                <div class="col-md-3">
                    <label class="form-label">{{ __('egypttax::devices.fields.register') }}</label>
                    <select wire:model="form.register_id" class="form-select @error('form.register_id') is-invalid @enderror">
                        <option value=""></option>
                        @foreach ($registers as $register) <option value="{{ $register->id }}">{{ $register->code }} — {{ $register->name }}</option> @endforeach
                    </select>
                    @error('form.register_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                @foreach (['serial' => 'col-md-3', 'os_version' => 'col-md-2', 'model_framework' => 'col-md-2', 'branch_code' => 'col-md-2'] as $field => $class)
                    <div class="{{ $class }}">
                        <label class="form-label">{{ __('egypttax::devices.fields.'.$field) }}</label>
                        <input type="text" wire:model="form.{{ $field }}" class="form-control ltr-value @error('form.'.$field) is-invalid @enderror">
                        @error('form.'.$field) <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                @endforeach
                <div class="col-md-4">
                    <label class="form-label">{{ __('egypttax::devices.fields.pre_shared_key') }}</label>
                    <input type="password" wire:model="form.pre_shared_key" class="form-control ltr-value" autocomplete="new-password"
                           placeholder="{{ $hasKey ? __('egypttax::settings.secret_kept') : '' }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('egypttax::devices.fields.client_id') }}</label>
                    <input type="text" wire:model="form.client_id" class="form-control ltr-value" autocomplete="off">
                    <div class="form-text">{{ __('egypttax::devices.credentials_hint') }}</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('egypttax::devices.fields.client_secret') }}</label>
                    <input type="password" wire:model="form.client_secret" class="form-control ltr-value" autocomplete="new-password"
                           placeholder="{{ $hasSecret ? __('egypttax::settings.secret_kept') : '' }}">
                </div>
                <div class="col-12"><h6 class="mb-0">{{ __('egypttax::devices.address') }}</h6></div>
                @foreach ($addressFields as $field)
                    <div class="col-md-3">
                        <label class="form-label">{{ __('egypttax::devices.address_fields.'.$field) }}</label>
                        <input type="text" wire:model="form.address.{{ $field }}" class="form-control @error('form.address.'.$field) is-invalid @enderror">
                        @error('form.address.'.$field) <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                @endforeach
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input id="dev_active" type="checkbox" wire:model="form.is_active" class="form-check-input">
                        <label for="dev_active" class="form-check-label">{{ __('core::ui.active') }}</label>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary">{{ __('core::ui.save') }}</button>
                <button type="button" class="btn btn-outline-secondary" wire:click="$set('showForm', false)">{{ __('core::ui.cancel') }}</button>
            </div>
        </form>
    @endif

    <div class="card">
        <div class="card-header d-flex">
            <span class="text-body-secondary">{{ __('egypttax::devices.hint') }}</span>
            <button type="button" class="btn btn-primary ms-auto" wire:click="create"><i class="bi bi-plus-lg"></i> {{ __('core::ui.add') }}</button>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                <tr>
                    <th>{{ __('egypttax::devices.fields.register') }}</th>
                    <th>{{ __('egypttax::devices.fields.serial') }}</th>
                    <th>{{ __('egypttax::devices.fields.os_version') }}</th>
                    <th>{{ __('egypttax::devices.fields.branch_code') }}</th>
                    <th>{{ __('egypttax::devices.fields.credentials') }}</th>
                    <th>{{ __('core::ui.status') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($devices as $device)
                    <tr wire:key="dev-{{ $device->id }}">
                        <td>{{ $device->register->code }} — {{ $device->register->name }}</td>
                        <td class="ltr-value">{{ $device->serial }}</td>
                        <td class="ltr-value">{{ $device->os_version }}</td>
                        <td class="ltr-value">{{ $device->branch_code }}</td>
                        <td>{{ filled($device->client_id) ? __('egypttax::devices.own_credentials') : __('egypttax::devices.company_credentials') }}</td>
                        <td><span @class(['badge', 'text-bg-success' => $device->is_active, 'text-bg-secondary' => ! $device->is_active])>{{ $device->is_active ? __('core::ui.active') : __('core::ui.inactive') }}</span></td>
                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" wire:click="edit({{ $device->id }})">{{ __('core::ui.edit') }}</button></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-body-secondary py-4">{{ __('core::ui.no_records') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
