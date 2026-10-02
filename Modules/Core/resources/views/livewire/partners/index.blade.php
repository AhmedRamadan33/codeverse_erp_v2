<div>
    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center">
            <input type="search" wire:model.live.debounce.400ms="search" class="form-control w-auto" placeholder="{{ __('core::ui.search') }}">
            <select wire:model.live="role" class="form-select w-auto">
                <option value="">{{ __('core::ui.all') }}</option>
                <option value="customers">{{ __('core::partners.fields.is_customer') }}</option>
                <option value="suppliers">{{ __('core::partners.fields.is_supplier') }}</option>
            </select>
            @can('core.partners.create')
                <a href="{{ route('core.partners.create') }}" class="btn btn-primary ms-auto">
                    <i class="bi bi-plus-lg"></i> {{ __('core::partners.new') }}
                </a>
            @endcan
        </div>
        <div class="card-body p-0">
            @error('partner') <div class="alert alert-danger m-3">{{ $message }}</div> @enderror
            <table class="table table-striped mb-0">
                <thead>
                <tr>
                    <th>{{ __('core::partners.fields.name') }}</th>
                    <th>{{ __('core::partners.fields.roles') }}</th>
                    <th>{{ __('core::ui.phone') }}</th>
                    <th>{{ __('core::partners.fields.tax_number') }}</th>
                    <th>{{ __('core::partners.fields.branch') }}</th>
                    <th>{{ __('core::ui.status') }}</th>
                    <th class="text-end">{{ __('core::ui.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($partners as $partner)
                    <tr wire:key="partner-{{ $partner->id }}">
                        <td>{{ $partner->name }}</td>
                        <td>
                            @if ($partner->is_customer) <span class="badge text-bg-info">{{ __('core::partners.fields.is_customer') }}</span> @endif
                            @if ($partner->is_supplier) <span class="badge text-bg-secondary">{{ __('core::partners.fields.is_supplier') }}</span> @endif
                        </td>
                        <td class="ltr-value">{{ $partner->phone }}</td>
                        <td class="ltr-value">{{ $partner->tax_number }}</td>
                        <td>{{ $partner->branch?->name ?? __('core::partners.all_branches') }}</td>
                        <td>
                            <span @class(['badge', 'text-bg-success' => $partner->is_active, 'text-bg-danger' => ! $partner->is_active])>
                                {{ $partner->is_active ? __('core::ui.active') : __('core::ui.inactive') }}
                            </span>
                        </td>
                        <td class="text-end text-nowrap">
                            @can('core.partners.update')
                                <a href="{{ route('core.partners.edit', $partner) }}" class="btn btn-sm btn-outline-primary">{{ __('core::ui.edit') }}</a>
                            @endcan
                            @can('core.partners.delete')
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        wire:click="delete({{ $partner->id }})"
                                        wire:confirm="{{ __('core::ui.confirm_delete') }}">{{ __('core::ui.delete') }}</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-body-secondary py-4">{{ __('core::ui.no_records') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($partners->hasPages())
            <div class="card-footer">{{ $partners->links() }}</div>
        @endif
    </div>
</div>
