<div>
    <ul class="nav nav-tabs mb-3">
        @foreach (['items', 'units', 'taxes'] as $name)
            <li class="nav-item">
                <button type="button" wire:click="$set('tab', '{{ $name }}')" @class(['nav-link', 'active' => $tab === $name])>
                    {{ __('egypttax::codes.tabs.'.$name) }}
                    @if ($name === 'items' && $missingCount > 0)<span class="badge text-bg-warning">{{ $missingCount }}</span>@endif
                </button>
            </li>
        @endforeach
    </ul>

    @error('item_code') <div class="alert alert-danger">{{ $message }}</div> @enderror
    @error('code_type') <div class="alert alert-danger">{{ $message }}</div> @enderror
    @error('unit_type') <div class="alert alert-danger">{{ $message }}</div> @enderror
    @error('tax_type') <div class="alert alert-danger">{{ $message }}</div> @enderror
    @error('sub_type') <div class="alert alert-danger">{{ $message }}</div> @enderror

    @if ($tab === 'items')
        <div class="card">
            <div class="card-header d-flex flex-wrap gap-2 align-items-center">
                <input type="search" wire:model.live.debounce.400ms="search" class="form-control w-auto" placeholder="{{ __('core::ui.search') }}">
                <div class="form-check form-switch">
                    <input id="missing_only" type="checkbox" wire:model.live="missingOnly" class="form-check-input">
                    <label for="missing_only" class="form-check-label">{{ __('egypttax::codes.missing_only') }}</label>
                </div>
                <span class="ms-auto text-body-secondary small">{{ __('egypttax::codes.items_hint') }}</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>{{ __('egypttax::codes.fields.sku') }}</th>
                        <th>{{ __('egypttax::codes.fields.product') }}</th>
                        <th style="width: 8rem">{{ __('egypttax::codes.fields.code_type') }}</th>
                        <th>{{ __('egypttax::codes.fields.item_code') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($products as $product)
                        <tr wire:key="p-{{ $product->id }}">
                            <td class="ltr-value">{{ $product->sku }}</td>
                            <td>{{ $product->name }}</td>
                            <td>
                                <select wire:model="items.{{ $product->id }}.code_type" class="form-select form-select-sm">
                                    @foreach ($types as $type) <option value="{{ $type }}">{{ $type }}</option> @endforeach
                                </select>
                            </td>
                            <td><input type="text" wire:model="items.{{ $product->id }}.item_code" class="form-control form-control-sm ltr-value"></td>
                            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" wire:click="saveItem({{ $product->id }})">{{ __('core::ui.save') }}</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('core::ui.no_records') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($products->hasPages())
                <div class="card-footer">{{ $products->links() }}</div>
            @endif
        </div>
    @elseif ($tab === 'units')
        <div class="card">
            <div class="card-header text-body-secondary small">{{ __('egypttax::codes.units_hint') }}</div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0 align-middle">
                    <thead><tr><th>{{ __('egypttax::codes.fields.unit') }}</th><th>{{ __('egypttax::codes.fields.unit_type') }}</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($unitList as $unit)
                        <tr wire:key="u-{{ $unit->id }}">
                            <td>{{ $unit->name }} <span class="text-body-secondary">({{ $unit->symbol }})</span></td>
                            <td style="width: 12rem"><input type="text" wire:model="units.{{ $unit->id }}" class="form-control form-control-sm ltr-value"></td>
                            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" wire:click="saveUnit({{ $unit->id }})">{{ __('core::ui.save') }}</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-header text-body-secondary small">{{ __('egypttax::codes.taxes_hint') }}</div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0 align-middle">
                    <thead><tr><th>{{ __('egypttax::codes.fields.tax') }}</th><th>{{ __('egypttax::codes.fields.tax_type') }}</th><th>{{ __('egypttax::codes.fields.sub_type') }}</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($taxList as $tax)
                        <tr wire:key="t-{{ $tax->id }}">
                            <td><span class="ltr-value">{{ $tax->code }}</span> — {{ $tax->name }}</td>
                            <td style="width: 10rem"><input type="text" wire:model="taxes.{{ $tax->id }}.tax_type" class="form-control form-control-sm ltr-value"></td>
                            <td style="width: 10rem"><input type="text" wire:model="taxes.{{ $tax->id }}.sub_type" class="form-control form-control-sm ltr-value"></td>
                            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" wire:click="saveTax({{ $tax->id }})">{{ __('core::ui.save') }}</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
