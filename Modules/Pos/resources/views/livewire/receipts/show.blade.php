<div>
    <div class="card mb-3">
        <div class="card-body row g-2">
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('pos::receipts.fields.number') }}</div><div class="ltr-value">{{ $receipt->number }}</div></div>
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('pos::receipts.fields.date') }}</div><div class="ltr-value">{{ $receipt->created_at->format('Y-m-d H:i') }}</div></div>
            <div class="col-md-2">
                <div class="small text-body-secondary">{{ __('pos::receipts.fields.kind') }}</div>
                <span @class(['badge', 'text-bg-info' => ! $receipt->isReturn(), 'text-bg-warning' => $receipt->isReturn()])>{{ $receipt->kind->label() }}</span>
                @if ($receipt->is_credit)<span class="badge text-bg-secondary">{{ __('pos::receipts.credit') }}</span>@endif
            </div>
            <div class="col-md-3"><div class="small text-body-secondary">{{ __('pos::receipts.fields.customer') }}</div><div>{{ $receipt->partner->name }}</div></div>
            <div class="col-md-3"><div class="small text-body-secondary">{{ __('pos::receipts.fields.cashier') }}</div><div>{{ $receipt->creator?->name }}</div></div>
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('pos::receipts.fields.register') }}</div><div>{{ $receipt->register->code }}</div></div>
            <div class="col-md-2">
                <div class="small text-body-secondary">{{ __('pos::receipts.fields.shift') }}</div>
                @can('pos.shifts.view')
                    <a class="ltr-value" href="{{ route('pos.shifts.show', $receipt->shift_id) }}">{{ $receipt->shift->number }}</a>
                @else
                    <span class="ltr-value">{{ $receipt->shift->number }}</span>
                @endcan
            </div>
            @if ($receipt->original)
                <div class="col-md-2"><div class="small text-body-secondary">{{ __('pos::receipts.fields.original') }}</div><a class="ltr-value" href="{{ route('pos.receipts.show', $receipt->original_receipt_id) }}">{{ $receipt->original->number }}</a></div>
            @endif
            @if ($receipt->due_date)
                <div class="col-md-2"><div class="small text-body-secondary">{{ __('pos::receipts.fields.due_date') }}</div><div class="ltr-value">{{ $receipt->due_date->toDateString() }}</div></div>
            @endif
            @if ($receipt->journalEntry)
                <div class="col-md-2"><div class="small text-body-secondary">{{ __('accounting::vouchers.fields.entry') }}</div><a class="ltr-value" href="{{ route('accounting.entries.show', $receipt->journal_entry_id) }}">{{ $receipt->journalEntry->number }}</a></div>
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                <tr>
                    <th>{{ __('pos::receipts.fields.product') }}</th>
                    <th>{{ __('pos::receipts.fields.unit') }}</th>
                    <th class="text-end">{{ __('pos::receipts.fields.quantity') }}</th>
                    <th class="text-end">{{ __('pos::receipts.fields.unit_price') }}</th>
                    <th class="text-end">{{ __('pos::receipts.fields.discount') }}</th>
                    <th class="text-end">{{ __('pos::receipts.fields.tax') }}</th>
                    <th class="text-end">{{ __('pos::receipts.fields.line_total') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($receipt->lines as $line)
                    <tr>
                        <td>{{ $line->product->label() }} <span class="small text-body-secondary ltr-value">{{ implode(', ', $line->serials ?? []) }}</span></td>
                        <td>{{ $line->unit->name }}</td>
                        <td class="text-end ltr-value">@money($line->quantity, 2)</td>
                        <td class="text-end ltr-value">@money($line->unit_price, $scale)</td>
                        <td class="text-end ltr-value">@money($line->line_discount->plus($line->document_discount), $scale)</td>
                        <td class="text-end ltr-value">@money($line->tax_amount, $scale)</td>
                        <td class="text-end ltr-value">@money($line->line_total, $scale)</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot class="table-light">
                <tr><th colspan="6" class="text-end">{{ __('pos::receipts.fields.total') }}</th><td class="text-end ltr-value fw-semibold">@money($receipt->total, $scale)</td></tr>
                @foreach ($receipt->payments as $payment)
                    <tr><th colspan="6" class="text-end">{{ $payment->method->name }}</th><td class="text-end ltr-value">@money($payment->amount, $scale)</td></tr>
                @endforeach
                @if ($receipt->change->isPositive())
                    <tr><th colspan="6" class="text-end">{{ __('pos::receipts.fields.change') }}</th><td class="text-end ltr-value">@money($receipt->change, $scale)</td></tr>
                @endif
                @if ($receipt->is_credit && ! $receipt->isReturn())
                    <tr><th colspan="6" class="text-end">{{ __('pos::receipts.fields.on_account') }}</th><td class="text-end ltr-value">@money($receipt->total->minus($receipt->paid_total), $scale)</td></tr>
                @endif
                </tfoot>
            </table>
        </div>
    </div>

    @if ($receipt->returns->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header">{{ __('pos::receipts.kind.return') }}</div>
            <ul class="list-group list-group-flush">
                @foreach ($receipt->returns as $return)
                    <li class="list-group-item d-flex gap-3">
                        <a class="ltr-value" href="{{ route('pos.receipts.show', $return->id) }}">{{ $return->number }}</a>
                        <span class="ltr-value">@money($return->total, $scale)</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="d-flex gap-2">
        <a href="{{ route('pos.receipts.print', $receipt->id) }}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-printer"></i> {{ __('pos::receipts.print') }}</a>
        @if ($canReturn)
            <a href="{{ route('pos.returns.create', ['receipt' => $receipt->number]) }}" class="btn btn-outline-secondary">{{ __('pos::receipts.return') }}</a>
        @endif
        <a href="{{ route('pos.receipts.index') }}" class="btn btn-outline-secondary ms-auto">{{ __('core::ui.cancel') }}</a>
    </div>
</div>
