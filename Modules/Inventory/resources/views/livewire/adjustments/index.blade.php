<div class="card">
    <div class="card-header d-flex gap-2">
        <select wire:model.live="status" class="form-select w-auto">
            <option value="">{{ __('inventory::documents.fields.status') }}: {{ __('core::ui.all') }}</option>
            @foreach ($statuses as $status) <option value="{{ $status->value }}">{{ $status->label() }}</option> @endforeach
        </select>
        @can('inventory.adjustments.create')
            <a href="{{ route('inventory.adjustments.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> {{ __('inventory::documents.new_adjustment') }}</a>
        @endcan
    </div>
    <div class="card-body p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
            <tr>
                <th>{{ __('inventory::documents.fields.number') }}</th>
                <th>{{ __('inventory::documents.fields.date') }}</th>
                <th>{{ __('inventory::documents.fields.kind') }}</th>
                <th>{{ __('inventory::documents.fields.warehouse') }}</th>
                <th>{{ __('inventory::documents.fields.description') }}</th>
                <th>{{ __('inventory::documents.fields.status') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($adjustments as $adjustment)
                <tr wire:key="adj-{{ $adjustment->id }}">
                    <td class="ltr-value"><a href="{{ route('inventory.adjustments.show', $adjustment->id) }}">{{ $adjustment->number ?? __('core::documents.status.draft').' #'.$adjustment->id }}</a></td>
                    <td class="ltr-value">{{ $adjustment->date->toDateString() }}</td>
                    <td>{{ __('inventory::documents.kinds.'.$adjustment->kind) }}</td>
                    <td>{{ $adjustment->warehouse->name }}</td>
                    <td>{{ $adjustment->description }}</td>
                    <td><span class="badge {{ $adjustment->status->badge() }}">{{ $adjustment->status->label() }}</span></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('core::ui.no_records') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($adjustments->hasPages())
        <div class="card-footer">{{ $adjustments->links() }}</div>
    @endif
</div>
