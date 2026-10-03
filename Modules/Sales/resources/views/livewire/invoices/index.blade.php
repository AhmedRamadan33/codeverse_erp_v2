<div class="card">
    <div class="card-header d-flex flex-wrap gap-2">
        <input type="search" wire:model.live.debounce.400ms="search" class="form-control w-auto" placeholder="{{ __('core::ui.search') }}">
        <select wire:model.live="status" class="form-select w-auto">
            <option value="">{{ __('sales::invoices.fields.status') }}: {{ __('core::ui.all') }}</option>
            @foreach ($statuses as $status) <option value="{{ $status->value }}">{{ $status->label() }}</option> @endforeach
        </select>
        @can('sales.invoices.create')
            <a href="{{ route('sales.invoices.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> {{ __('sales::invoices.new') }}</a>
        @endcan
    </div>
    <div class="card-body p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
            <tr>
                <th>{{ __('sales::invoices.fields.number') }}</th>
                <th>{{ __('sales::invoices.fields.date') }}</th>
                <th>{{ __('sales::invoices.fields.customer') }}</th>
                <th>{{ __('sales::invoices.fields.due_date') }}</th>
                <th class="text-end">{{ __('sales::invoices.fields.total') }}</th>
                <th>{{ __('sales::invoices.fields.status') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($invoices as $invoice)
                <tr wire:key="si-{{ $invoice->id }}">
                    <td class="ltr-value"><a href="{{ route('sales.invoices.show', $invoice->id) }}">{{ $invoice->number ?? __('core::documents.status.draft').' #'.$invoice->id }}</a></td>
                    <td class="ltr-value">{{ $invoice->date->toDateString() }}</td>
                    <td>{{ $invoice->partner->name }}</td>
                    <td class="ltr-value">{{ $invoice->due_date?->toDateString() }}</td>
                    <td class="text-end ltr-value">@money($invoice->total, $invoice->currency->decimal_places) {{ $invoice->currency->code }}</td>
                    <td><span class="badge {{ $invoice->status->badge() }}">{{ $invoice->status->label() }}</span></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('core::ui.no_records') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($invoices->hasPages())
        <div class="card-footer">{{ $invoices->links() }}</div>
    @endif
</div>
