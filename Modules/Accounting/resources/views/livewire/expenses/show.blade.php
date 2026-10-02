<div>
    @if ($errors->any())
        <div class="alert alert-danger">@foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach</div>
    @endif
    @php($scale = $voucher->currency->decimal_places)

    <div class="card mb-3">
        <div class="card-body row g-2">
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('accounting::vouchers.fields.number') }}</div><div class="ltr-value">{{ $voucher->number ?? '—' }}</div></div>
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('accounting::vouchers.fields.date') }}</div><div class="ltr-value">{{ $voucher->date->toDateString() }}</div></div>
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('accounting::vouchers.fields.payment_method') }}</div><div>{{ $voucher->paymentMethod->name }}</div></div>
            <div class="col-md-3"><div class="small text-body-secondary">{{ __('accounting::expenses.fields.payee') }}</div><div>{{ $voucher->partner?->name ?? '—' }}</div></div>
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('accounting::vouchers.fields.status') }}</div><span class="badge {{ $voucher->status->badge() }}">{{ $voucher->status->label() }}</span></div>
            <div class="col-md-1"><div class="small text-body-secondary">{{ __('accounting::vouchers.fields.currency') }}</div><div>{{ $voucher->currency->code }}</div></div>
            @if ($voucher->description)<div class="col-md-8"><div class="small text-body-secondary">{{ __('accounting::vouchers.fields.description') }}</div><div>{{ $voucher->description }}</div></div>@endif
            @if ($voucher->journalEntry)<div class="col-md-4"><div class="small text-body-secondary">{{ __('accounting::vouchers.fields.entry') }}</div><a class="ltr-value" href="{{ route('accounting.entries.show', $voucher->journal_entry_id) }}">{{ $voucher->journalEntry->number }}</a></div>@endif
            @if ($voucher->cancel_reason)<div class="col-12 text-danger">{{ __('accounting::vouchers.fields.cancel_reason') }}: {{ $voucher->cancel_reason }}</div>@endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                <tr>
                    <th>{{ __('accounting::expenses.fields.account') }}</th>
                    <th>{{ __('accounting::vouchers.fields.description') }}</th>
                    <th class="text-end">{{ __('accounting::expenses.fields.net') }}</th>
                    <th>{{ __('accounting::expenses.fields.tax') }}</th>
                    <th class="text-end">{{ __('accounting::expenses.fields.tax_amount') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($voucher->lines as $line)
                    <tr>
                        <td>{{ $line->account->label() }}</td>
                        <td>{{ $line->description }}</td>
                        <td class="text-end ltr-value">@money($line->amount, $scale)</td>
                        <td>{{ $line->tax?->name }}</td>
                        <td class="text-end ltr-value">@money($line->tax_amount, $scale)</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot class="table-light">
                <tr><th colspan="4" class="text-end">{{ __('accounting::expenses.fields.subtotal') }}</th><th class="text-end ltr-value">@money($voucher->subtotal, $scale)</th></tr>
                <tr><th colspan="4" class="text-end">{{ __('accounting::expenses.fields.tax_amount') }}</th><th class="text-end ltr-value">@money($voucher->tax_total, $scale)</th></tr>
                <tr><th colspan="4" class="text-end">{{ __('accounting::expenses.fields.total') }}</th><th class="text-end ltr-value">@money($voucher->total, $scale)</th></tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="d-flex gap-2">
        @if ($voucher->status->value === 'draft')
            @can('accounting.vouchers.post')
                <button type="button" class="btn btn-success" wire:click="post">{{ __('accounting::vouchers.post') }}</button>
            @endcan
            @can('accounting.vouchers.create')
                <a href="{{ route('accounting.expenses.edit', $voucher->id) }}" class="btn btn-outline-primary">{{ __('core::ui.edit') }}</a>
                <button type="button" class="btn btn-outline-danger" wire:click="delete" wire:confirm="{{ __('core::ui.confirm_delete') }}">{{ __('core::ui.delete') }}</button>
            @endcan
        @elseif ($voucher->status->value === 'posted')
            @can('accounting.vouchers.cancel')
                <button type="button" class="btn btn-outline-danger" wire:click="$toggle('showCancel')">{{ __('accounting::vouchers.cancel') }}</button>
            @endcan
        @endif
        <a href="{{ route('accounting.expenses.index') }}" class="btn btn-outline-secondary ms-auto">{{ __('core::ui.cancel') }}</a>
    </div>

    @if ($showCancel)
        <form wire:submit="cancel" class="card mt-3">
            <div class="card-body">
                <label class="form-label">{{ __('accounting::vouchers.fields.cancel_reason') }}</label>
                <input type="text" wire:model="cancelReason" class="form-control @error('cancelReason') is-invalid @enderror">
            </div>
            <div class="card-footer"><button type="submit" class="btn btn-danger">{{ __('accounting::vouchers.cancel') }}</button></div>
        </form>
    @endif
</div>
