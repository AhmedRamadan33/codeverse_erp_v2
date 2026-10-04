<div>
    @if ($showForm)
        <form wire:submit="save" class="card mb-3">
            <div class="card-body row g-3">
                <div class="col-md-2">
                    <label class="form-label">{{ __('pos::registers.fields.code') }}</label>
                    <input type="text" wire:model="form.code" class="form-control ltr-value @error('form.code') is-invalid @enderror">
                    @error('form.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('core::ui.name_ar') }}</label>
                    <input type="text" wire:model="form.name_ar" class="form-control @error('form.name_ar') is-invalid @enderror">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('core::ui.name_en') }}</label>
                    <input type="text" wire:model="form.name_en" class="form-control ltr-value">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('pos::registers.fields.warehouse') }}</label>
                    <select wire:model="form.warehouse_id" class="form-select @error('form.warehouse_id') is-invalid @enderror">
                        @foreach ($warehouses as $warehouse) <option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option> @endforeach
                    </select>
                    @error('form.warehouse_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('pos::registers.fields.cash_method') }}</label>
                    <select wire:model="form.cash_payment_method_id" class="form-select @error('form.cash_payment_method_id') is-invalid @enderror">
                        @foreach ($methods as $method) <option value="{{ $method->id }}">{{ $method->name }}</option> @endforeach
                    </select>
                    @error('form.cash_payment_method_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('pos::registers.fields.price_list') }}</label>
                    <select wire:model="form.price_list_id" class="form-select">
                        <option value="">{{ __('pos::registers.default_price_list') }}</option>
                        @foreach ($priceLists as $list) <option value="{{ $list->id }}">{{ $list->name }}</option> @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input id="reg_active" type="checkbox" wire:model="form.is_active" class="form-check-input">
                        <label for="reg_active" class="form-check-label">{{ __('core::ui.active') }}</label>
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
                    <th>{{ __('pos::registers.fields.code') }}</th>
                    <th>{{ __('pos::registers.fields.name') }}</th>
                    <th>{{ __('pos::registers.fields.warehouse') }}</th>
                    <th>{{ __('pos::registers.fields.cash_method') }}</th>
                    <th>{{ __('pos::registers.fields.price_list') }}</th>
                    <th>{{ __('pos::registers.fields.open_shift') }}</th>
                    <th>{{ __('core::ui.status') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($registers as $register)
                    @php($open = $register->shifts->first())
                    <tr wire:key="reg-{{ $register->id }}">
                        <td class="ltr-value">{{ $register->code }}</td>
                        <td>{{ $register->name }}</td>
                        <td>{{ $register->warehouse->name }}</td>
                        <td>{{ $register->cashMethod->name }}</td>
                        <td>{{ $register->priceList?->name ?? __('pos::registers.default_price_list') }}</td>
                        <td>@if ($open)<a class="ltr-value" href="{{ route('pos.shifts.show', $open->id) }}">{{ $open->number }}</a> — {{ $open->cashier->name }}@endif</td>
                        <td><span @class(['badge', 'text-bg-success' => $register->is_active, 'text-bg-secondary' => ! $register->is_active])>{{ $register->is_active ? __('core::ui.active') : __('core::ui.inactive') }}</span></td>
                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" wire:click="edit({{ $register->id }})">{{ __('core::ui.edit') }}</button></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-body-secondary py-4">{{ __('core::ui.no_records') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
