<div class="card">
    <div class="card-body p-0">
        @error('is_active') <div class="alert alert-danger m-3">{{ $message }}</div> @enderror
        <table class="table table-striped mb-0">
            <thead>
            <tr>
                <th>{{ __('core::currencies.fields.code') }}</th>
                <th>{{ __('core::currencies.fields.name') }}</th>
                <th>{{ __('core::currencies.fields.symbol') }}</th>
                <th>{{ __('core::currencies.fields.decimal_places') }}</th>
                <th>{{ __('core::ui.status') }}</th>
                <th class="text-end">{{ __('core::ui.actions') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($currencies as $currency)
                <tr wire:key="currency-{{ $currency->id }}">
                    <td class="ltr-value">
                        {{ $currency->code }}
                        @if ($currency->code === $baseCode) <span class="badge text-bg-primary">{{ __('core::currencies.base') }}</span> @endif
                    </td>
                    <td>{{ $currency->name }}</td>
                    <td>
                        @if ($editingId === $currency->id)
                            <form wire:submit="save" class="d-flex gap-1">
                                <input type="text" wire:model="symbol" class="form-control form-control-sm w-auto @error('symbol') is-invalid @enderror" size="6">
                                <button type="submit" class="btn btn-sm btn-primary">{{ __('core::ui.save') }}</button>
                            </form>
                        @else
                            {{ $currency->symbol }}
                        @endif
                    </td>
                    <td>{{ $currency->decimal_places }}</td>
                    <td>
                        <span @class(['badge', 'text-bg-success' => $currency->is_active, 'text-bg-secondary' => ! $currency->is_active])>
                            {{ $currency->is_active ? __('core::ui.active') : __('core::ui.inactive') }}
                        </span>
                    </td>
                    <td class="text-end text-nowrap">
                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="edit({{ $currency->id }})">{{ __('core::ui.edit') }}</button>
                        @if ($currency->code !== $baseCode)
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="toggle({{ $currency->id }})">
                                {{ $currency->is_active ? __('core::ui.inactive') : __('core::ui.active') }}
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
