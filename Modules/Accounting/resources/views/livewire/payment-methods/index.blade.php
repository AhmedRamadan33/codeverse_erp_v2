<div>
    @if ($showForm)
        <form wire:submit="save" class="card mb-3">
            <div class="card-body row g-3">
                <div class="col-md-3">
                    <label class="form-label">{{ __('core::ui.name_ar') }}</label>
                    <input type="text" wire:model="form.name_ar" class="form-control @error('form.name_ar') is-invalid @enderror">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('core::ui.name_en') }}</label>
                    <input type="text" wire:model="form.name_en" class="form-control ltr-value">
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('accounting::payment_methods.fields.type') }}</label>
                    <select wire:model="form.type" class="form-select">
                        @foreach ($types as $type) <option value="{{ $type->value }}">{{ $type->label() }}</option> @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('accounting::payment_methods.fields.account') }}</label>
                    <select wire:model="form.account_id" class="form-select @error('form.account_id') is-invalid @enderror">
                        <option value="">—</option>
                        @foreach ($accounts as $account) <option value="{{ $account->id }}">{{ $account->label() }}</option> @endforeach
                    </select>
                    @error('form.account_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-1">
                    <label class="form-label">{{ __('accounting::payment_methods.fields.sort') }}</label>
                    <input type="number" min="0" wire:model="form.sort" class="form-control">
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input id="pm_active" type="checkbox" wire:model="form.is_active" class="form-check-input">
                        <label for="pm_active" class="form-check-label">{{ __('core::ui.active') }}</label>
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
            <button type="button" class="btn btn-primary ms-auto" wire:click="create"><i class="bi bi-plus-lg"></i> {{ __('core::ui.add') }}</button>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                <tr>
                    <th>{{ __('accounting::payment_methods.fields.name') }}</th>
                    <th>{{ __('accounting::payment_methods.fields.type') }}</th>
                    <th>{{ __('accounting::payment_methods.fields.account') }}</th>
                    <th>{{ __('core::ui.status') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($methods as $method)
                    <tr wire:key="pm-{{ $method->id }}">
                        <td>{{ $method->name }}</td>
                        <td>{{ $method->type->label() }}</td>
                        <td>{{ $method->account->label() }}</td>
                        <td><span @class(['badge', 'text-bg-success' => $method->is_active, 'text-bg-secondary' => ! $method->is_active])>{{ $method->is_active ? __('core::ui.active') : __('core::ui.inactive') }}</span></td>
                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" wire:click="edit({{ $method->id }})">{{ __('core::ui.edit') }}</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
