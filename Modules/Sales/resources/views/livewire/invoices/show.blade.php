<div>
    @if ($errors->any())
        <div class="alert alert-danger">@foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach</div>
    @endif

    <div class="card mb-3">
        <div class="card-body row g-2">
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('sales::invoices.fields.number') }}</div><div class="ltr-value">{{ $invoice->number ?? '—' }}</div></div>
            <div class="col-md-3"><div class="small text-body-secondary">{{ __('sales::invoices.fields.customer') }}</div><div>{{ $invoice->partner->name }}</div></div>
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('sales::invoices.fields.price_list') }}</div><div>{{ $invoice->priceList?->name ?? __('sales::invoices.default_price_list') }}</div></div>
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('sales::invoices.fields.date') }}</div><div class="ltr-value">{{ $invoice->date->toDateString() }}</div></div>
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('sales::invoices.fields.due_date') }}</div><div class="ltr-value">{{ $invoice->due_date?->toDateString() }}</div></div>
            <div class="col-md-1"><div class="small text-body-secondary">{{ __('sales::invoices.fields.status') }}</div><span class="badge {{ $invoice->status->badge() }}">{{ $invoice->status->label() }}</span></div>
            <div class="col-md-3"><div class="small text-body-secondary">{{ __('sales::invoices.fields.warehouse') }}</div><div>{{ $invoice->warehouse->name }}</div></div>
            @if ($invoice->journalEntry)
                <div class="col-md-3"><div class="small text-body-secondary">{{ __('accounting::vouchers.fields.entry') }}</div><a class="ltr-value" href="{{ route('accounting.entries.show', $invoice->journal_entry_id) }}">{{ $invoice->journalEntry->number }}</a></div>
                <div class="col-md-3"><div class="small text-body-secondary">{{ __('sales::invoices.fields.open_amount') }}</div><div class="ltr-value fw-semibold">@money($open)</div></div>
            @endif
            @if ($invoice->paid_amount)
                <div class="col-md-3"><div class="small text-body-secondary">{{ __('sales::invoices.fields.paid_now') }}</div><div class="ltr-value">@money($invoice->paid_amount, $scale) — {{ $invoice->paymentMethod?->name }}</div></div>
            @endif
            @if ($invoice->description)<div class="col-12">{{ $invoice->description }}</div>@endif
            @if ($invoice->cancel_reason)<div class="col-12 text-danger">{{ __('sales::invoices.fields.cancel_reason') }}: {{ $invoice->cancel_reason }}</div>@endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                <tr>
                    <th>{{ __('sales::invoices.fields.product') }}</th>
                    <th>{{ __('sales::invoices.fields.unit') }}</th>
                    <th class="text-end">{{ __('sales::invoices.fields.quantity') }}</th>
                    <th class="text-end">{{ __('sales::invoices.fields.unit_price') }}</th>
                    <th class="text-end">{{ __('sales::invoices.fields.line_discount') }}</th>
                    <th class="text-end">{{ __('sales::invoices.fields.net') }}</th>
                    <th class="text-end">{{ __('sales::invoices.fields.tax_amount') }}</th>
                    <th class="text-end">{{ __('sales::invoices.fields.line_total') }}</th>
                    <th>{{ __('sales::invoices.fields.batch') }}</th>
                    @if ($showCost)
                        <th class="text-end">{{ __('sales::invoices.fields.cost') }}</th>
                        <th class="text-end">{{ __('sales::invoices.fields.margin') }}</th>
                    @endif
                </tr>
                </thead>
                <tbody>
                @foreach ($invoice->lines as $line)
                    <tr>
                        <td>{{ $line->product->label() }}</td>
                        <td>{{ $line->unit->name }}</td>
                        <td class="text-end ltr-value">@money($line->quantity, 2)</td>
                        <td class="text-end ltr-value">@money($line->unit_price, $scale)</td>
                        <td class="text-end ltr-value">@money($line->line_discount->plus($line->document_discount), $scale)</td>
                        <td class="text-end ltr-value">@money($line->net, $scale)</td>
                        <td class="text-end ltr-value">@money($line->tax_amount, $scale)</td>
                        <td class="text-end ltr-value">@money($line->line_total, $scale)</td>
                        <td class="ltr-value small">{{ $line->batch_number }} {{ implode(', ', $line->serials ?? []) }}</td>
                        @if ($showCost)
                            <td class="text-end ltr-value">@money($line->cost)</td>
                            <td class="text-end ltr-value">@if ($line->cost) @money($line->net->multipliedBy($invoice->exchange_rate)->minus($line->cost)) @endif</td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
                <tfoot class="table-light">
                <tr><th colspan="7" class="text-end">{{ __('sales::invoices.fields.subtotal') }}</th><td class="text-end ltr-value">@money($invoice->subtotal, $scale)</td><td @if ($showCost) colspan="3" @endif></td></tr>
                <tr><th colspan="7" class="text-end">{{ __('sales::invoices.fields.discount_total') }}</th><td class="text-end ltr-value">@money($invoice->discount_total, $scale)</td><td @if ($showCost) colspan="3" @endif></td></tr>
                <tr><th colspan="7" class="text-end">{{ __('sales::invoices.fields.tax_total') }}</th><td class="text-end ltr-value">@money($invoice->tax_total, $scale)</td><td @if ($showCost) colspan="3" @endif></td></tr>
                <tr><th colspan="7" class="text-end">{{ __('sales::invoices.fields.total') }}</th><td class="text-end ltr-value fw-semibold">@money($invoice->total, $scale) {{ $invoice->currency->code }}</td><td @if ($showCost) colspan="3" @endif></td></tr>
                </tfoot>
            </table>
        </div>
    </div>

    @if ($invoice->returns->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header">{{ __('sales::returns.title') }}</div>
            <ul class="list-group list-group-flush">
                @foreach ($invoice->returns as $return)
                    <li class="list-group-item d-flex gap-3">
                        <a class="ltr-value" href="{{ route('sales.returns.show', $return->id) }}">{{ $return->number ?? __('core::documents.status.draft').' #'.$return->id }}</a>
                        <span class="ltr-value">@money($return->total, $scale)</span>
                        <span class="badge {{ $return->status->badge() }}">{{ $return->status->label() }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($overLimitWarning)
        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2">
            <strong>{{ __('sales::invoices.over_limit_title') }}:</strong> {{ $overLimitWarning }}
            <button type="button" class="btn btn-warning btn-sm ms-auto" wire:click="post(true)">{{ __('sales::invoices.post_anyway') }}</button>
        </div>
    @endif

    <div class="d-flex flex-wrap gap-2">
        @if ($invoice->status->value === 'draft')
            @can('sales.invoices.post')
                <button type="button" class="btn btn-success" wire:click="post">{{ __('sales::invoices.post') }}</button>
            @endcan
            @can('sales.invoices.create')
                <a href="{{ route('sales.invoices.edit', $invoice->id) }}" class="btn btn-outline-primary">{{ __('core::ui.edit') }}</a>
                <button type="button" class="btn btn-outline-danger" wire:click="delete" wire:confirm="{{ __('core::ui.confirm_delete') }}">{{ __('core::ui.delete') }}</button>
            @endcan
        @elseif ($invoice->status->value === 'posted')
            @if ($open->isPositive())
                @can('accounting.vouchers.create')
                    <a href="{{ route('accounting.receipts.create', ['partner_id' => $invoice->partner_id]) }}" class="btn btn-primary">{{ __('sales::invoices.collect') }}</a>
                @endcan
            @endif
            @can('sales.returns.create')
                <a href="{{ route('sales.returns.create', ['invoice' => $invoice->id]) }}" class="btn btn-outline-secondary">{{ __('sales::invoices.return') }}</a>
            @endcan
            @can('sales.invoices.cancel')
                <button type="button" class="btn btn-outline-danger" wire:click="$toggle('showCancel')">{{ __('sales::invoices.cancel') }}</button>
            @endcan
        @endif
        <a href="{{ route('sales.invoices.print', $invoice->id) }}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-printer"></i> {{ __('core::ui.print') }}</a>
        <a href="{{ route('sales.invoices.index') }}" class="btn btn-outline-secondary ms-auto">{{ __('core::ui.cancel') }}</a>
    </div>

    @if ($showCancel)
        <form wire:submit="cancel" class="card mt-3">
            <div class="card-body">
                <label class="form-label">{{ __('sales::invoices.fields.cancel_reason') }}</label>
                <input type="text" wire:model="cancelReason" class="form-control @error('cancelReason') is-invalid @enderror">
            </div>
            <div class="card-footer"><button type="submit" class="btn btn-danger">{{ __('sales::invoices.cancel') }}</button></div>
        </form>
    @endif
</div>
