<div>
    @if ($showForm)
        <form wire:submit="save" class="card mb-3">
            <div class="card-header">{{ $editingId ? __('core::ui.edit') : __('core::ui.add') }}</div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">{{ __('core::ui.name_ar') }}</label>
                    <input type="text" wire:model="form.name_ar" class="form-control @error('form.name_ar') is-invalid @enderror">
                    @error('form.name_ar') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('core::ui.name_en') }}</label>
                    <input type="text" wire:model="form.name_en" class="form-control ltr-value @error('form.name_en') is-invalid @enderror">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('core::ui.code') }}</label>
                    <input type="text" wire:model="form.code" class="form-control ltr-value @error('form.code') is-invalid @enderror">
                    @error('form.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('core::ui.address') }}</label>
                    <input type="text" wire:model="form.address" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('core::ui.phone') }}</label>
                    <input type="text" wire:model="form.phone" class="form-control ltr-value">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input id="branch_active" type="checkbox" wire:model="form.is_active" class="form-check-input @error('form.is_active') is-invalid @enderror">
                        <label for="branch_active" class="form-check-label">{{ __('core::ui.active') }}</label>
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
        @can('core.branches.manage')
            <div class="card-header d-flex">
                <button type="button" class="btn btn-primary ms-auto" wire:click="create"><i class="bi bi-plus-lg"></i> {{ __('core::ui.add') }}</button>
            </div>
        @endcan
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                <tr>
                    <th>{{ __('core::ui.code') }}</th>
                    <th>{{ __('core::ui.name_ar') }}</th>
                    <th>{{ __('core::ui.phone') }}</th>
                    <th>{{ __('core::ui.status') }}</th>
                    <th class="text-end">{{ __('core::ui.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($branches as $branch)
                    <tr wire:key="branch-{{ $branch->id }}">
                        <td class="ltr-value">{{ $branch->code }}</td>
                        <td>{{ $branch->name }}</td>
                        <td class="ltr-value">{{ $branch->phone }}</td>
                        <td>
                            <span @class(['badge', 'text-bg-success' => $branch->is_active, 'text-bg-danger' => ! $branch->is_active])>
                                {{ $branch->is_active ? __('core::ui.active') : __('core::ui.inactive') }}
                            </span>
                        </td>
                        <td class="text-end">
                            @can('core.branches.manage')
                                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="edit({{ $branch->id }})">{{ __('core::ui.edit') }}</button>
                            @endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
