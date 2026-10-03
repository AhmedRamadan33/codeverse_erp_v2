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
                    <label class="form-label">{{ __('products::catalog.symbol_ar') }}</label>
                    <input type="text" wire:model="form.symbol_ar" class="form-control @error('form.symbol_ar') is-invalid @enderror">
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('products::catalog.symbol_en') }}</label>
                    <input type="text" wire:model="form.symbol_en" class="form-control ltr-value">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input id="unit_active" type="checkbox" wire:model="form.is_active" class="form-check-input">
                        <label for="unit_active" class="form-check-label">{{ __('core::ui.active') }}</label>
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
                <thead><tr><th>{{ __('core::ui.name_ar') }}</th><th>{{ __('products::catalog.symbol_ar') }}</th><th>{{ __('core::ui.status') }}</th><th></th></tr></thead>
                <tbody>
                @foreach ($units as $unit)
                    <tr wire:key="unit-{{ $unit->id }}">
                        <td>{{ $unit->name }}</td>
                        <td>{{ $unit->symbol }}</td>
                        <td><span @class(['badge', 'text-bg-success' => $unit->is_active, 'text-bg-secondary' => ! $unit->is_active])>{{ $unit->is_active ? __('core::ui.active') : __('core::ui.inactive') }}</span></td>
                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" wire:click="edit({{ $unit->id }})">{{ __('core::ui.edit') }}</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
