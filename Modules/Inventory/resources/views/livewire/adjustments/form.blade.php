<form wire:submit="save" class="card">
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger">@foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach</div>
        @endif
        <div class="row g-3 mb-3">
            <div class="col-md-2">
                <label class="form-label">{{ __('inventory::documents.fields.date') }}</label>
                <input type="date" wire:model="form.date" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('inventory::documents.fields.warehouse') }}</label>
                <select wire:model="form.warehouse_id" class="form-select">
                    @foreach ($warehouses as $warehouse) <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('inventory::documents.fields.kind') }}</label>
                <select wire:model.live="form.kind" class="form-select">
                    <option value="adjustment">{{ __('inventory::documents.kinds.adjustment') }}</option>
                    <option value="opening">{{ __('inventory::documents.kinds.opening') }}</option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">{{ __('inventory::documents.fields.description') }}</label>
                <input type="text" wire:model="form.description" class="form-control">
            </div>
            <div class="col-12 form-text mt-0">{{ __('inventory::documents.fields.quantity_hint') }} {{ __('inventory::documents.fields.unit_cost_hint') }}</div>
        </div>

        @include('inventory::livewire.partials.lines', ['withCost' => true])
    </div>
    <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary">{{ __('inventory::documents.save_draft') }}</button>
        <a href="{{ route('inventory.adjustments.index') }}" class="btn btn-outline-secondary">{{ __('core::ui.cancel') }}</a>
    </div>
</form>
