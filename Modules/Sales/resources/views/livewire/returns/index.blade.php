<div class="card">
    <div class="card-body p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
            <tr>
                <th>{{ __('sales::invoices.fields.number') }}</th>
                <th>{{ __('sales::invoices.fields.date') }}</th>
                <th>{{ __('sales::invoices.fields.customer') }}</th>
                <th>{{ __('sales::returns.fields.invoice') }}</th>
                <th class="text-end">{{ __('sales::invoices.fields.total') }}</th>
                <th>{{ __('sales::invoices.fields.status') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($returns as $return)
                <tr wire:key="sr-{{ $return->id }}">
                    <td class="ltr-value"><a href="{{ route('sales.returns.show', $return->id) }}">{{ $return->number ?? __('core::documents.status.draft').' #'.$return->id }}</a></td>
                    <td class="ltr-value">{{ $return->date->toDateString() }}</td>
                    <td>{{ $return->partner->name }}</td>
                    <td class="ltr-value"><a href="{{ route('sales.invoices.show', $return->sales_invoice_id) }}">{{ $return->invoice->number }}</a></td>
                    <td class="text-end ltr-value">@money($return->total, $return->currency->decimal_places) {{ $return->currency->code }}</td>
                    <td><span class="badge {{ $return->status->badge() }}">{{ $return->status->label() }}</span></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('core::ui.no_records') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($returns->hasPages())
        <div class="card-footer">{{ $returns->links() }}</div>
    @endif
</div>
