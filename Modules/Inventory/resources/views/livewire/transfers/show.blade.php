<div>
    @if ($errors->any())
        <div class="alert alert-danger">@foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach</div>
    @endif

    <div class="card mb-3">
        <div class="card-body row g-2">
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('inventory::documents.fields.number') }}</div><div class="ltr-value">{{ $transfer->number ?? '—' }}</div></div>
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('inventory::documents.fields.date') }}</div><div class="ltr-value">{{ $transfer->date->toDateString() }}</div></div>
            <div class="col-md-3"><div class="small text-body-secondary">{{ __('inventory::documents.fields.from_warehouse') }}</div><div>{{ $transfer->fromWarehouse->name }}</div></div>
            <div class="col-md-3"><div class="small text-body-secondary">{{ __('inventory::documents.fields.to_warehouse') }}</div><div>{{ $transfer->toWarehouse->name }}</div></div>
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('inventory::documents.fields.status') }}</div><span class="badge {{ $transfer->status->badge() }}">{{ $transfer->status->label() }}</span></div>
            @if ($transfer->description)<div class="col-12">{{ $transfer->description }}</div>@endif
            @if ($transfer->cancel_reason)<div class="col-12 text-danger">{{ __('inventory::documents.fields.cancel_reason') }}: {{ $transfer->cancel_reason }}</div>@endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                <tr>
                    <th>{{ __('inventory::documents.fields.product') }}</th>
                    <th>{{ __('inventory::documents.fields.unit') }}</th>
                    <th class="text-end">{{ __('inventory::documents.fields.quantity') }}</th>
                    <th>{{ __('inventory::documents.fields.batch') }}</th>
                    <th>{{ __('inventory::documents.fields.serials') }}</th>
                    <th class="text-end">{{ __('inventory::documents.fields.total_cost') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($transfer->lines as $line)
                    <tr>
                        <td>{{ $line->product->label() }}</td>
                        <td>{{ $line->unit->name }}</td>
                        <td class="text-end ltr-value">@money($line->quantity, 2)</td>
                        <td class="ltr-value">{{ $line->batch_number }}</td>
                        <td class="ltr-value small">{{ implode(', ', $line->serials ?? []) }}</td>
                        <td class="text-end ltr-value">@money($line->total_cost)</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex gap-2">
        @if ($transfer->status->value === 'draft')
            @can('inventory.transfers.post')
                <button type="button" class="btn btn-success" wire:click="post">{{ __('inventory::documents.post') }}</button>
            @endcan
            @can('inventory.transfers.create')
                <a href="{{ route('inventory.transfers.edit', $transfer->id) }}" class="btn btn-outline-primary">{{ __('core::ui.edit') }}</a>
                <button type="button" class="btn btn-outline-danger" wire:click="delete" wire:confirm="{{ __('core::ui.confirm_delete') }}">{{ __('core::ui.delete') }}</button>
            @endcan
        @elseif ($transfer->status->value === 'posted')
            @can('inventory.transfers.cancel')
                <button type="button" class="btn btn-outline-danger" wire:click="$toggle('showCancel')">{{ __('inventory::documents.cancel') }}</button>
            @endcan
        @endif
        <a href="{{ route('inventory.transfers.index') }}" class="btn btn-outline-secondary ms-auto">{{ __('core::ui.cancel') }}</a>
    </div>

    @if ($showCancel)
        <form wire:submit="cancel" class="card mt-3">
            <div class="card-body">
                <label class="form-label">{{ __('inventory::documents.fields.cancel_reason') }}</label>
                <input type="text" wire:model="cancelReason" class="form-control @error('cancelReason') is-invalid @enderror">
            </div>
            <div class="card-footer"><button type="submit" class="btn btn-danger">{{ __('inventory::documents.cancel') }}</button></div>
        </form>
    @endif
</div>
