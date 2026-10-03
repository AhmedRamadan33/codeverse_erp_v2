<div>
    <div class="card mb-3">
        <div class="card-body">
            <div class="form-check form-switch">
                <input id="allow_negative" type="checkbox" wire:model.live="allowNegative" class="form-check-input">
                <label for="allow_negative" class="form-check-label">{{ __('inventory::stock.negative_stock') }}</label>
            </div>
            <div class="form-text">{{ __('inventory::stock.negative_stock_hint') }}</div>
        </div>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="card mb-3">
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">{{ __('core::ui.name_ar') }}</label>
                    <input type="text" wire:model="form.name_ar" class="form-control @error('form.name_ar') is-invalid @enderror">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('core::ui.name_en') }}</label>
                    <input type="text" wire:model="form.name_en" class="form-control ltr-value">
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('inventory::stock.fields.code') }}</label>
                    <input type="text" wire:model="form.code" class="form-control ltr-value @error('form.code') is-invalid @enderror">
                    @error('form.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('inventory::stock.fields.branch') }}</label>
                    <select wire:model="form.branch_id" class="form-select">
                        @foreach ($branches as $branch) <option value="{{ $branch->id }}">{{ $branch->name }}</option> @endforeach
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input id="wh_active" type="checkbox" wire:model="form.is_active" class="form-check-input">
                        <label for="wh_active" class="form-check-label">{{ __('core::ui.active') }}</label>
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
                <thead><tr><th>{{ __('inventory::stock.fields.code') }}</th><th>{{ __('inventory::stock.fields.warehouse') }}</th><th>{{ __('inventory::stock.fields.branch') }}</th><th>{{ __('core::ui.status') }}</th><th></th></tr></thead>
                <tbody>
                @foreach ($warehouses as $warehouse)
                    <tr wire:key="wh-{{ $warehouse->id }}">
                        <td class="ltr-value">{{ $warehouse->code }}</td>
                        <td>{{ $warehouse->name }}</td>
                        <td>{{ $warehouse->branch->name }}</td>
                        <td><span @class(['badge', 'text-bg-success' => $warehouse->is_active, 'text-bg-secondary' => ! $warehouse->is_active])>{{ $warehouse->is_active ? __('core::ui.active') : __('core::ui.inactive') }}</span></td>
                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" wire:click="edit({{ $warehouse->id }})">{{ __('core::ui.edit') }}</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
