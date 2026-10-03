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
                <label class="form-label">{{ __('inventory::documents.fields.from_warehouse') }}</label>
                <select wire:model="form.from_warehouse_id" class="form-select">
                    @foreach ($sources as $warehouse) <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('inventory::documents.fields.to_warehouse') }}</label>
                <select wire:model="form.to_warehouse_id" class="form-select @error('to_warehouse_id') is-invalid @enderror">
                    <option value="">—</option>
                    @foreach ($destinations as $warehouse) <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('inventory::documents.fields.description') }}</label>
                <input type="text" wire:model="form.description" class="form-control">
            </div>
        </div>

        @include('inventory::livewire.partials.lines', ['withCost' => false])
    </div>
    <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary">{{ __('inventory::documents.save_draft') }}</button>
        <a href="{{ route('inventory.transfers.index') }}" class="btn btn-outline-secondary">{{ __('core::ui.cancel') }}</a>
    </div>
</form>
