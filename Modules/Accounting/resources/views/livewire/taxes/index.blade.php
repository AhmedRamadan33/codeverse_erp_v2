<div>
    @if ($showForm)
        <form wire:submit="save" class="card mb-3">
            <div class="card-body row g-3">
                <div class="col-md-2">
                    <label class="form-label">{{ __('core::ui.code') }}</label>
                    <input type="text" wire:model="form.code" class="form-control ltr-value @error('form.code') is-invalid @enderror">
                    @error('form.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('core::ui.name_ar') }}</label>
                    <input type="text" wire:model="form.name_ar" class="form-control @error('form.name_ar') is-invalid @enderror">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('core::ui.name_en') }}</label>
                    <input type="text" wire:model="form.name_en" class="form-control ltr-value">
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('accounting::taxes.fields.rate') }}</label>
                    <input type="text" inputmode="decimal" wire:model="form.rate" class="form-control ltr-value @error('form.rate') is-invalid @enderror">
                    @error('form.rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('accounting::taxes.fields.type') }}</label>
                    <select wire:model="form.type" class="form-select">
                        @foreach ($types as $type) <option value="{{ $type->value }}">{{ $type->label() }}</option> @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('accounting::taxes.fields.scope') }}</label>
                    <select wire:model="form.scope" class="form-select">
                        @foreach ($scopes as $scope) <option value="{{ $scope->value }}">{{ $scope->label() }}</option> @endforeach
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end gap-4">
                    <div class="form-check form-switch">
                        <input id="tax_incl" type="checkbox" wire:model="form.included_in_price" class="form-check-input">
                        <label for="tax_incl" class="form-check-label">{{ __('accounting::taxes.fields.included_in_price') }}</label>
                    </div>
                    <div class="form-check form-switch">
                        <input id="tax_active" type="checkbox" wire:model="form.is_active" class="form-check-input">
                        <label for="tax_active" class="form-check-label">{{ __('core::ui.active') }}</label>
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
                    <th>{{ __('core::ui.code') }}</th>
                    <th>{{ __('accounting::taxes.fields.name') }}</th>
                    <th>{{ __('accounting::taxes.fields.rate') }}</th>
                    <th>{{ __('accounting::taxes.fields.scope') }}</th>
                    <th>{{ __('core::ui.status') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($taxes as $tax)
                    <tr wire:key="tax-{{ $tax->id }}">
                        <td class="ltr-value">{{ $tax->code }}</td>
                        <td>{{ $tax->name }}</td>
                        <td class="ltr-value">{{ $tax->rate->strippedOfTrailingZeros() }}{{ $tax->type->value === 'percent' ? '%' : '' }}</td>
                        <td>{{ $tax->scope->label() }}</td>
                        <td><span @class(['badge', 'text-bg-success' => $tax->is_active, 'text-bg-secondary' => ! $tax->is_active])>{{ $tax->is_active ? __('core::ui.active') : __('core::ui.inactive') }}</span></td>
                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" wire:click="edit({{ $tax->id }})">{{ __('core::ui.edit') }}</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
