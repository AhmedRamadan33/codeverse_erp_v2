<form wire:submit="save" class="card">
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger">@foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach</div>
        @endif
        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="small text-body-secondary">{{ __('purchases::returns.fields.invoice') }}</div><div class="ltr-value">{{ $invoice->number }}</div></div>
            <div class="col-md-3"><div class="small text-body-secondary">{{ __('purchases::invoices.fields.supplier') }}</div><div>{{ $invoice->partner->name }}</div></div>
            <div class="col-md-2">
                <label class="form-label">{{ __('purchases::invoices.fields.date') }}</label>
                <input type="date" wire:model="date" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('purchases::invoices.fields.description') }}</label>
                <input type="text" wire:model="description" class="form-control">
            </div>
        </div>
        <table class="table table-sm table-bordered align-middle">
            <thead class="table-light">
            <tr>
                <th>{{ __('purchases::invoices.fields.product') }}</th>
                <th>{{ __('purchases::invoices.fields.unit') }}</th>
                <th class="text-end">{{ __('purchases::returns.fields.invoiced') }}</th>
                <th class="text-end">{{ __('purchases::returns.fields.returnable') }}</th>
                <th style="width: 9rem">{{ __('purchases::returns.fields.quantity') }}</th>
                <th>{{ __('purchases::invoices.fields.serials') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($invoice->lines as $line)
                <tr wire:key="rl-{{ $line->id }}">
                    <td>{{ $line->product->label() }}</td>
                    <td>{{ $line->unit->name }}</td>
                    <td class="text-end ltr-value">@money($line->quantity, 2)</td>
                    <td class="text-end ltr-value">@money($returnable[$line->id], 2)</td>
                    <td><input type="text" inputmode="decimal" wire:model="lines.{{ $line->id }}.quantity" class="form-control form-control-sm ltr-value" @disabled(! $returnable[$line->id]->isPositive())></td>
                    <td>
                        @if ($line->product->tracking->value === 'serial')
                            <input type="text" wire:model="lines.{{ $line->id }}.serials" class="form-control form-control-sm ltr-value" placeholder="{{ implode(', ', $line->serials ?? []) }}">
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary">{{ __('purchases::invoices.save_draft') }}</button>
        <a href="{{ route('purchases.invoices.show', $invoice->id) }}" class="btn btn-outline-secondary">{{ __('core::ui.cancel') }}</a>
    </div>
</form>
