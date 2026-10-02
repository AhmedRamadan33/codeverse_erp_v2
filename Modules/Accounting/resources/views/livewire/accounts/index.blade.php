<div>
    @if ($showForm)
        <form wire:submit="save" class="card mb-3">
            <div class="card-header">{{ $editingId ? __('core::ui.edit') : __('core::ui.add') }}</div>
            <div class="card-body row g-3">
                <div class="col-md-2">
                    <label class="form-label">{{ __('accounting::accounts.fields.code') }}</label>
                    <input type="text" wire:model="form.code" class="form-control ltr-value @error('form.code') is-invalid @enderror">
                    @error('form.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-5">
                    <label class="form-label">{{ __('core::ui.name_ar') }}</label>
                    <input type="text" wire:model="form.name_ar" class="form-control @error('form.name_ar') is-invalid @enderror">
                    @error('form.name_ar') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-5">
                    <label class="form-label">{{ __('core::ui.name_en') }}</label>
                    <input type="text" wire:model="form.name_en" class="form-control ltr-value">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('accounting::accounts.fields.parent') }}</label>
                    <select wire:model.live="form.parent_id" class="form-select @error('form.parent_id') is-invalid @enderror">
                        <option value="">—</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}">{{ $group->label() }}</option>
                        @endforeach
                    </select>
                    @error('form.parent_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('accounting::accounts.fields.type') }}</label>
                    <select wire:model.live="form.type" class="form-select @error('form.type') is-invalid @enderror" @disabled($form['parent_id'] ?? null)>
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('form.type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('accounting::accounts.fields.subtype') }}</label>
                    <select wire:model="form.subtype" class="form-select @error('form.subtype') is-invalid @enderror" @disabled($form['is_group'] ?? false)>
                        <option value="">—</option>
                        @foreach ($subtypes as $subtype)
                            <option value="{{ $subtype->value }}">{{ $subtype->label() }}</option>
                        @endforeach
                    </select>
                    @error('form.subtype') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('accounting::accounts.fields.currency') }}</label>
                    <select wire:model="form.currency_id" class="form-select" @disabled($form['is_group'] ?? false)>
                        <option value="">{{ __('accounting::accounts.any_currency') }}</option>
                        @foreach ($currencies as $currency)
                            <option value="{{ $currency->id }}">{{ $currency->code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 d-flex gap-4">
                    <div class="form-check form-switch">
                        <input id="acc_group" type="checkbox" wire:model.live="form.is_group" class="form-check-input @error('form.is_group') is-invalid @enderror">
                        <label for="acc_group" class="form-check-label">{{ __('accounting::accounts.fields.is_group') }}</label>
                        @error('form.is_group') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-check form-switch">
                        <input id="acc_active" type="checkbox" wire:model="form.is_active" class="form-check-input @error('form.is_active') is-invalid @enderror">
                        <label for="acc_active" class="form-check-label">{{ __('core::ui.active') }}</label>
                        @error('form.is_active') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
        <div class="card-header d-flex gap-2">
            <input type="search" wire:model.live.debounce.300ms="search" class="form-control w-auto" placeholder="{{ __('core::ui.search') }}">
            @can('accounting.accounts.manage')
                <button type="button" class="btn btn-primary ms-auto" wire:click="create"><i class="bi bi-plus-lg"></i> {{ __('core::ui.add') }}</button>
            @endcan
        </div>
        <div class="card-body p-0">
            @error('account') <div class="alert alert-danger m-3">{{ $message }}</div> @enderror
            <table class="table table-sm table-hover mb-0">
                <thead>
                <tr>
                    <th>{{ __('accounting::accounts.fields.account') }}</th>
                    <th>{{ __('accounting::accounts.fields.type') }}</th>
                    <th class="text-end">{{ __('accounting::accounts.fields.balance') }}</th>
                    <th class="text-end">{{ __('core::ui.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($rows as $row)
                    @php($account = $row['account'])
                    <tr wire:key="account-{{ $account->id }}" @class(['fw-semibold' => $account->is_group, 'text-body-secondary' => ! $account->is_active])>
                        <td style="padding-inline-start: {{ 0.5 + $row['depth'] * 1.25 }}rem">
                            <span class="ltr-value">{{ $account->code }}</span> — {{ $account->name }}
                            @if (! $account->is_active) <span class="badge text-bg-secondary">{{ __('core::ui.inactive') }}</span> @endif
                        </td>
                        <td>{{ $account->subtype?->label() ?? $account->type->label() }}</td>
                        <td class="text-end ltr-value">@money($balances[$account->id] ?? null)</td>
                        <td class="text-end text-nowrap">
                            @can('accounting.accounts.manage')
                                @if ($account->is_group)
                                    <button type="button" class="btn btn-sm btn-link" wire:click="create({{ $account->id }})" title="{{ __('accounting::accounts.add_child') }}"><i class="bi bi-plus-circle"></i></button>
                                @endif
                                <button type="button" class="btn btn-sm btn-link" wire:click="edit({{ $account->id }})"><i class="bi bi-pencil"></i></button>
                                @unless ($account->is_system)
                                    <button type="button" class="btn btn-sm btn-link text-danger" wire:click="delete({{ $account->id }})" wire:confirm="{{ __('core::ui.confirm_delete') }}"><i class="bi bi-trash"></i></button>
                                @endunless
                            @endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
