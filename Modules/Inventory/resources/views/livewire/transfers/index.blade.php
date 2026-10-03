<div class="card">
    <div class="card-header d-flex gap-2">
        <select wire:model.live="status" class="form-select w-auto">
            <option value="">{{ __('inventory::documents.fields.status') }}: {{ __('core::ui.all') }}</option>
            @foreach ($statuses as $status) <option value="{{ $status->value }}">{{ $status->label() }}</option> @endforeach
        </select>
        @can('inventory.transfers.create')
            <a href="{{ route('inventory.transfers.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> {{ __('inventory::documents.new_transfer') }}</a>
        @endcan
    </div>
    <div class="card-body p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
            <tr>
                <th>{{ __('inventory::documents.fields.number') }}</th>
                <th>{{ __('inventory::documents.fields.date') }}</th>
                <th>{{ __('inventory::documents.fields.from_warehouse') }}</th>
                <th>{{ __('inventory::documents.fields.to_warehouse') }}</th>
                <th>{{ __('inventory::documents.fields.status') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($transfers as $transfer)
                <tr wire:key="trf-{{ $transfer->id }}">
                    <td class="ltr-value"><a href="{{ route('inventory.transfers.show', $transfer->id) }}">{{ $transfer->number ?? __('core::documents.status.draft').' #'.$transfer->id }}</a></td>
                    <td class="ltr-value">{{ $transfer->date->toDateString() }}</td>
                    <td>{{ $transfer->fromWarehouse->name }}</td>
                    <td>{{ $transfer->toWarehouse->name }}</td>
                    <td><span class="badge {{ $transfer->status->badge() }}">{{ $transfer->status->label() }}</span></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('core::ui.no_records') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($transfers->hasPages())
        <div class="card-footer">{{ $transfers->links() }}</div>
    @endif
</div>
