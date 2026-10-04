<div>
    @if ($errors->any())
        <div class="alert alert-danger">@foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach</div>
    @endif

    <div class="card mb-3">
        <div class="card-body row g-2">
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('purchases::invoices.fields.number') }}</div><div class="ltr-value">{{ $invoice->number ?? '—' }}</div></div>
            <div class="col-md-3"><div class="small text-body-secondary">{{ __('purchases::invoices.fields.supplier') }}</div><div>{{ $invoice->partner->name }}</div></div>
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('purchases::invoices.fields.supplier_reference') }}</div><div class="ltr-value">{{ $invoice->supplier_reference ?? '—' }}</div></div>
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('purchases::invoices.fields.date') }}</div><div class="ltr-value">{{ $invoice->date->toDateString() }}</div></div>
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('purchases::invoices.fields.due_date') }}</div><div class="ltr-value">{{ $invoice->due_date?->toDateString() }}</div></div>
            <div class="col-md-1"><div class="small text-body-secondary">{{ __('purchases::invoices.fields.status') }}</div><span class="badge {{ $invoice->status->badge() }}">{{ $invoice->status->label() }}</span></div>
            <div class="col-md-3"><div class="small text-body-secondary">{{ __('purchases::invoices.fields.warehouse') }}</div><div>{{ $invoice->warehouse->name }}</div></div>
            @if ($invoice->journalEntry)
                <div class="col-md-3"><div class="small text-body-secondary">{{ __('accounting::vouchers.fields.entry') }}</div><a class="ltr-value" href="{{ route('accounting.entries.show', $invoice->journal_entry_id) }}">{{ $invoice->journalEntry->number }}</a></div>
                <div class="col-md-3"><div class="small text-body-secondary">{{ __('purchases::invoices.fields.open_amount') }}</div><div class="ltr-value fw-semibold">@money($open)</div></div>
            @endif
            @if ($invoice->description)<div class="col-12">{{ $invoice->description }}</div>@endif
            @if ($invoice->cancel_reason)<div class="col-12 text-danger">{{ __('purchases::invoices.fields.cancel_reason') }}: {{ $invoice->cancel_reason }}</div>@endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                <tr>
                    <th>{{ __('purchases::invoices.fields.product') }}</th>
                    <th>{{ __('purchases::invoices.fields.unit') }}</th>
                    <th class="text-end">{{ __('purchases::invoices.fields.quantity') }}</th>
                    <th class="text-end">{{ __('purchases::invoices.fields.unit_price') }}</th>
                    <th class="text-end">{{ __('purchases::invoices.fields.line_discount') }}</th>
                    <th class="text-end">{{ __('purchases::invoices.fields.net') }}</th>
                    <th class="text-end">{{ __('purchases::invoices.fields.tax_amount') }}</th>
                    <th class="text-end">{{ __('purchases::invoices.fields.line_total') }}</th>
                    <th>{{ __('purchases::invoices.fields.batch') }}</th>
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
                    </tr>
                @endforeach
                </tbody>
                <tfoot class="table-light">
                <tr><th colspan="7" class="text-end">{{ __('purchases::invoices.fields.subtotal') }}</th><td class="text-end ltr-value">@money($invoice->subtotal, $scale)</td><td></td></tr>
                <tr><th colspan="7" class="text-end">{{ __('purchases::invoices.fields.discount_total') }}</th><td class="text-end ltr-value">@money($invoice->discount_total, $scale)</td><td></td></tr>
                <tr><th colspan="7" class="text-end">{{ __('purchases::invoices.fields.tax_total') }}</th><td class="text-end ltr-value">@money($invoice->tax_total, $scale)</td><td></td></tr>
                <tr><th colspan="7" class="text-end">{{ __('purchases::invoices.fields.total') }}</th><td class="text-end ltr-value fw-semibold">@money($invoice->total, $scale) {{ $invoice->currency->code }}</td><td></td></tr>
                </tfoot>
            </table>
        </div>
    </div>

    @if ($invoice->returns->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header">{{ __('purchases::returns.title') }}</div>
            <ul class="list-group list-group-flush">
                @foreach ($invoice->returns as $return)
                    <li class="list-group-item d-flex gap-3">
                        <a class="ltr-value" href="{{ route('purchases.returns.show', $return->id) }}">{{ $return->number ?? __('core::documents.status.draft').' #'.$return->id }}</a>
                        <span class="ltr-value">@money($return->total, $scale)</span>
                        <span class="badge {{ $return->status->badge() }}">{{ $return->status->label() }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="d-flex flex-wrap gap-2">
        @if ($invoice->status->value === 'draft')
            @can('purchases.invoices.post')
                <button type="button" class="btn btn-success" wire:click="post">{{ __('purchases::invoices.post') }}</button>
            @endcan
            @can('purchases.invoices.create')
                <a href="{{ route('purchases.invoices.edit', $invoice->id) }}" class="btn btn-outline-primary">{{ __('core::ui.edit') }}</a>
                <button type="button" class="btn btn-outline-danger" wire:click="delete" wire:confirm="{{ __('core::ui.confirm_delete') }}">{{ __('core::ui.delete') }}</button>
            @endcan
        @elseif ($invoice->status->value === 'posted')
            @if ($open->isPositive())
                @can('accounting.vouchers.create')
                    <a href="{{ route('accounting.payments.create', ['partner_id' => $invoice->partner_id]) }}" class="btn btn-primary">{{ __('purchases::invoices.pay') }}</a>
                @endcan
            @endif
            @can('purchases.returns.create')
                <a href="{{ route('purchases.returns.create', ['invoice' => $invoice->id]) }}" class="btn btn-outline-secondary">{{ __('purchases::invoices.return') }}</a>
            @endcan
            @can('purchases.invoices.cancel')
                <button type="button" class="btn btn-outline-danger" wire:click="$toggle('showCancel')">{{ __('purchases::invoices.cancel') }}</button>
            @endcan
        @endif
        <a href="{{ route('purchases.invoices.print', $invoice->id) }}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-printer"></i> {{ __('core::ui.print') }}</a>
        <a href="{{ route('purchases.invoices.index') }}" class="btn btn-outline-secondary ms-auto">{{ __('core::ui.cancel') }}</a>
    </div>

    @if ($showCancel)
        <form wire:submit="cancel" class="card mt-3">
            <div class="card-body">
                <label class="form-label">{{ __('purchases::invoices.fields.cancel_reason') }}</label>
                <input type="text" wire:model="cancelReason" class="form-control @error('cancelReason') is-invalid @enderror">
            </div>
            <div class="card-footer"><button type="submit" class="btn btn-danger">{{ __('purchases::invoices.cancel') }}</button></div>
        </form>
    @endif
</div>
