<div>
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
                <div class="col-md-3">
                    <label class="form-label">{{ __('products::catalog.parent') }}</label>
                    <select wire:model="form.parent_id" class="form-select @error('form.parent_id') is-invalid @enderror">
                        <option value="">—</option>
                        @foreach ($categories as $option)
                            @if ($option->id !== $editingId) <option value="{{ $option->id }}">{{ $option->name }}</option> @endif
                        @endforeach
                    </select>
                    @error('form.parent_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input id="cat_active" type="checkbox" wire:model="form.is_active" class="form-check-input">
                        <label for="cat_active" class="form-check-label">{{ __('core::ui.active') }}</label>
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
                <thead><tr><th>{{ __('products::products.fields.name') }}</th><th>{{ __('products::catalog.parent') }}</th><th>{{ __('products::products.title') }}</th><th>{{ __('core::ui.status') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($categories as $category)
                    <tr wire:key="cat-{{ $category->id }}">
                        <td>{{ $category->name }}</td>
                        <td>{{ $category->parent?->name }}</td>
                        <td>{{ $category->products_count }}</td>
                        <td><span @class(['badge', 'text-bg-success' => $category->is_active, 'text-bg-secondary' => ! $category->is_active])>{{ $category->is_active ? __('core::ui.active') : __('core::ui.inactive') }}</span></td>
                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" wire:click="edit({{ $category->id }})">{{ __('core::ui.edit') }}</button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('core::ui.no_records') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
