<div>
    @if ($errors->any())
        <div class="alert alert-danger">@foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach</div>
    @endif

    <div class="card mb-3">
        <div class="card-body row g-2">
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('accounting::vouchers.fields.number') }}</div><div class="ltr-value">{{ $voucher->number ?? '—' }}</div></div>
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('accounting::vouchers.fields.date') }}</div><div class="ltr-value">{{ $voucher->date->toDateString() }}</div></div>
            <div class="col-md-3"><div class="small text-body-secondary">{{ __('accounting::vouchers.fields.partner') }}</div><div>{{ $voucher->partner->name }}</div></div>
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('accounting::vouchers.fields.payment_method') }}</div><div>{{ $voucher->paymentMethod->name }}</div></div>
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('accounting::vouchers.fields.amount') }}</div><div class="ltr-value fw-semibold">@money($voucher->amount, $voucher->currency->decimal_places) {{ $voucher->currency->code }}</div></div>
            <div class="col-md-1"><div class="small text-body-secondary">{{ __('accounting::vouchers.fields.status') }}</div><span class="badge {{ $voucher->status->badge() }}">{{ $voucher->status->label() }}</span></div>
            @if ($voucher->reference)
                <div class="col-md-3"><div class="small text-body-secondary">{{ __('accounting::vouchers.fields.reference') }}</div><div class="ltr-value">{{ $voucher->reference }}</div></div>
            @endif
            @if ($voucher->description)
                <div class="col-md-6"><div class="small text-body-secondary">{{ __('accounting::vouchers.fields.description') }}</div><div>{{ $voucher->description }}</div></div>
            @endif
            @if ($voucher->journalEntry)
                <div class="col-md-3"><div class="small text-body-secondary">{{ __('accounting::vouchers.fields.entry') }}</div>
                    <a class="ltr-value" href="{{ route('accounting.entries.show', $voucher->journal_entry_id) }}">{{ $voucher->journalEntry->number }}</a>
                </div>
            @endif
            @if ($voucher->cancel_reason)
                <div class="col-12 text-danger">{{ __('accounting::vouchers.fields.cancel_reason') }}: {{ $voucher->cancel_reason }}</div>
            @endif
        </div>
    </div>

    @if ($voucher->status->value !== 'cancelled' && $openItems->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center gap-2">
                <strong>{{ __('accounting::vouchers.open_items') }}</strong>
                <span class="small text-body-secondary">{{ __('accounting::vouchers.open_items_hint') }}</span>
                <span class="ms-auto ltr-value">{{ __('accounting::vouchers.fields.unallocated') }}: @money($available)</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="fillInOrder">{{ __('accounting::vouchers.fill') }}</button>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th>{{ __('accounting::vouchers.item') }}</th>
                        <th>{{ __('accounting::vouchers.fields.date') }}</th>
                        <th>{{ __('accounting::vouchers.fields.description') }}</th>
                        <th class="text-end">{{ __('accounting::vouchers.open_amount') }}</th>
                        <th style="width: 12rem">{{ __('accounting::vouchers.allocate') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($openItems as $item)
                        <tr wire:key="item-{{ $item['line']->id }}">
                            <td class="ltr-value"><a href="{{ route('accounting.entries.show', $item['line']->journal_entry_id) }}">{{ $item['line']->entry->number }}</a></td>
                            <td class="ltr-value">{{ $item['line']->entry->date->toDateString() }}</td>
                            <td>{{ $item['line']->description ?? $item['line']->entry->description }}</td>
                            <td class="text-end ltr-value">@money($item['residual'])</td>
                            <td><input type="text" inputmode="decimal" wire:model="allocations.{{ $item['line']->id }}" class="form-control form-control-sm ltr-value"></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="d-flex gap-2">
        @if ($voucher->status->value === 'draft')
            @can('accounting.vouchers.post')
                <button type="button" class="btn btn-success" wire:click="post">{{ __('accounting::vouchers.post') }}</button>
            @endcan
            @can('accounting.vouchers.create')
                <a href="{{ route($kindEnum->routePrefix().'edit', $voucher->id) }}" class="btn btn-outline-primary">{{ __('core::ui.edit') }}</a>
                <button type="button" class="btn btn-outline-danger" wire:click="delete" wire:confirm="{{ __('core::ui.confirm_delete') }}">{{ __('core::ui.delete') }}</button>
            @endcan
        @elseif ($voucher->status->value === 'posted')
            @can('accounting.vouchers.post')
                @if ($openItems->isNotEmpty() && $available->isPositive())
                    <button type="button" class="btn btn-primary" wire:click="allocate">{{ __('accounting::vouchers.allocate') }}</button>
                @endif
            @endcan
            @can('accounting.vouchers.cancel')
                <button type="button" class="btn btn-outline-danger" wire:click="$toggle('showCancel')">{{ __('accounting::vouchers.cancel') }}</button>
            @endcan
        @endif
        <a href="{{ route($kindEnum->routePrefix().'index') }}" class="btn btn-outline-secondary ms-auto">{{ __('core::ui.cancel') }}</a>
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
