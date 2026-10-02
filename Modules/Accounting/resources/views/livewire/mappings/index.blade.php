<div class="card">
    <div class="card-body p-0">
        @error('account_id') <div class="alert alert-danger m-3">{{ $message }}</div> @enderror
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
            <tr>
                <th>{{ __('accounting::mappings.purpose') }}</th>
                <th>{{ __('accounting::accounts.fields.account') }}</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($mappings as $mapping)
                <tr wire:key="mapping-{{ $mapping->id }}">
                    <td>
                        {{ \Modules\Accounting\Livewire\Mappings\Index::label($mapping->key) }}
                        <div class="small text-body-secondary ltr-value">{{ $mapping->key }}</div>
                    </td>
                    <td>
                        <select wire:model="selected.{{ $mapping->id }}" class="form-select form-select-sm">
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->label() }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="save({{ $mapping->id }})">{{ __('core::ui.save') }}</button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
