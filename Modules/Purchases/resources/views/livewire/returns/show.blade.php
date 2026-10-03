<div>
    @if ($errors->any())
        <div class="alert alert-danger">@foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach</div>
    @endif

    <div class="card mb-3">
        <div class="card-body row g-2">
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('purchases::invoices.fields.number') }}</div><div class="ltr-value">{{ $return->number ?? '—' }}</div></div>
            <div class="col-md-3"><div class="small text-body-secondary">{{ __('purchases::invoices.fields.supplier') }}</div><div>{{ $return->partner->name }}</div></div>
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('purchases::returns.fields.invoice') }}</div><a class="ltr-value" href="{{ route('purchases.invoices.show', $return->purchase_invoice_id) }}">{{ $return->invoice->number }}</a></div>
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('purchases::invoices.fields.date') }}</div><div class="ltr-value">{{ $return->date->toDateString() }}</div></div>
            <div class="col-md-1"><div class="small text-body-secondary">{{ __('purchases::invoices.fields.status') }}</div><span class="badge {{ $return->status->badge() }}">{{ $return->status->label() }}</span></div>
            @if ($return->journalEntry)
                <div class="col-md-2"><div class="small text-body-secondary">{{ __('accounting::vouchers.fields.entry') }}</div><a class="ltr-value" href="{{ route('accounting.entries.show', $return->journal_entry_id) }}">{{ $return->journalEntry->number }}</a></div>
            @endif
            @if ($return->cancel_reason)<div class="col-12 text-danger">{{ __('purchases::invoices.fields.cancel_reason') }}: {{ $return->cancel_reason }}</div>@endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                <tr>
                    <th>{{ __('purchases::invoices.fields.product') }}</th>
                    <th>{{ __('purchases::invoices.fields.unit') }}</th>
                    <th class="text-end">{{ __('purchases::invoices.fields.quantity') }}</th>
                    <th class="text-end">{{ __('purchases::invoices.fields.net') }}</th>
                    <th class="text-end">{{ __('purchases::invoices.fields.tax_amount') }}</th>
                    <th class="text-end">{{ __('purchases::invoices.fields.line_total') }}</th>
                    <th class="text-end">{{ __('purchases::returns.fields.stock_cost') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($return->lines as $line)
                    <tr>
                        <td>{{ $line->product->label() }}</td>
                        <td>{{ $line->unit->name }}</td>
                        <td class="text-end ltr-value">@money($line->quantity, 2)</td>
                        <td class="text-end ltr-value">@money($line->net, $scale)</td>
                        <td class="text-end ltr-value">@money($line->tax_amount, $scale)</td>
                        <td class="text-end ltr-value">@money($line->line_total, $scale)</td>
                        <td class="text-end ltr-value">@money($line->stock_cost)</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot class="table-light">
                <tr><th colspan="5" class="text-end">{{ __('purchases::invoices.fields.total') }}</th><td class="text-end ltr-value fw-semibold">@money($return->total, $scale)</td><td></td></tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="d-flex gap-2">
        @if ($return->status->value === 'draft')
            @can('purchases.returns.post')
                <button type="button" class="btn btn-success" wire:click="post">{{ __('purchases::returns.post') }}</button>
            @endcan
            @can('purchases.returns.create')
                <a href="{{ route('purchases.returns.edit', $return->id) }}" class="btn btn-outline-primary">{{ __('core::ui.edit') }}</a>
                <button type="button" class="btn btn-outline-danger" wire:click="delete" wire:confirm="{{ __('core::ui.confirm_delete') }}">{{ __('core::ui.delete') }}</button>
            @endcan
        @elseif ($return->status->value === 'posted')
            @can('purchases.returns.cancel')
                <button type="button" class="btn btn-outline-danger" wire:click="$toggle('showCancel')">{{ __('purchases::returns.cancel') }}</button>
            @endcan
        @endif
        <a href="{{ route('purchases.returns.index') }}" class="btn btn-outline-secondary ms-auto">{{ __('core::ui.cancel') }}</a>
    </div>

    @if ($showCancel)
        <form wire:submit="cancel" class="card mt-3">
            <div class="card-body">
                <label class="form-label">{{ __('purchases::invoices.fields.cancel_reason') }}</label>
                <input type="text" wire:model="cancelReason" class="form-control @error('cancelReason') is-invalid @enderror">
            </div>
            <div class="card-footer"><button type="submit" class="btn btn-danger">{{ __('purchases::returns.cancel') }}</button></div>
        </form>
    @endif
</div>
