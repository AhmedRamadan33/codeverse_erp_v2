@extends('core::layouts.print')

@php($isInvoice = $kind === 'invoice')

@section('title', ($isInvoice ? __('purchases::invoices.one') : __('purchases::returns.one')).' '.($document->number ?? ''))

@section('status')
    @if ($document->status->value !== 'posted')
        <div style="text-align: end; font-weight: bold; color: #b00">{{ $document->status->label() }}</div>
    @endif
@endsection

@section('content')
    <div class="meta">
        <div><span>{{ __('purchases::invoices.fields.number') }}</span><b class="ltr">{{ $document->number ?? '—' }}</b></div>
        <div><span>{{ __('purchases::invoices.fields.date') }}</span><b class="ltr">{{ $document->date->toDateString() }}</b></div>
        @if ($isInvoice)
            <div><span>{{ __('purchases::invoices.fields.due_date') }}</span><b class="ltr">{{ $document->due_date?->toDateString() }}</b></div>
            <div><span>{{ __('purchases::invoices.fields.supplier_reference') }}</span><b class="ltr">{{ $document->supplier_reference ?? '—' }}</b></div>
        @else
            <div><span>{{ __('purchases::returns.fields.invoice') }}</span><b class="ltr">{{ $document->invoice->number }}</b></div>
        @endif
        <div>
            <span>{{ __('purchases::invoices.fields.supplier') }}</span>
            <b>{{ $document->partner->name }}</b>
            @if ($document->partner->tax_number)<div class="muted">{{ __('core::ui.tax_number') }}: <span class="ltr">{{ $document->partner->tax_number }}</span></div>@endif
            @if ($document->partner->address)<div class="muted">{{ $document->partner->address }}</div>@endif
            @if ($document->partner->phone)<div class="muted ltr">{{ $document->partner->phone }}</div>@endif
        </div>
        <div><span>{{ __('purchases::invoices.fields.warehouse') }}</span>{{ $document->warehouse->name }}</div>
        <div><span>{{ __('purchases::invoices.fields.currency') }}</span>{{ $document->currency->code }}</div>
    </div>

    <table class="lines">
        <thead>
        <tr>
            <th>#</th>
            <th>{{ __('purchases::invoices.fields.product') }}</th>
            <th>{{ __('purchases::invoices.fields.unit') }}</th>
            <th class="num">{{ __('purchases::invoices.fields.quantity') }}</th>
            @if ($isInvoice)
                <th class="num">{{ __('purchases::invoices.fields.unit_price') }}</th>
                <th class="num">{{ __('purchases::invoices.fields.line_discount') }}</th>
            @endif
            <th class="num">{{ __('purchases::invoices.fields.net') }}</th>
            <th class="num">{{ __('purchases::invoices.fields.tax_amount') }}</th>
            <th class="num">{{ __('purchases::invoices.fields.line_total') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($document->lines as $i => $line)
            <tr>
                <td class="num">{{ $i + 1 }}</td>
                <td>
                    {{ $line->product->label() }}
                    @if ($line->batch_number || $line->serials)<div class="muted ltr">{{ $line->batch_number }} {{ $line->expiry_date?->toDateString() }} {{ implode(', ', $line->serials ?? []) }}</div>@endif
                </td>
                <td>{{ $line->unit->name }}</td>
                <td class="num ltr">{{ $line->quantity->strippedOfTrailingZeros() }}</td>
                @if ($isInvoice)
                    <td class="num ltr">@money($line->unit_price, $scale)</td>
                    <td class="num ltr">@money($line->line_discount->plus($line->document_discount), $scale)</td>
                @endif
                <td class="num ltr">@money($line->net, $scale)</td>
                <td class="num ltr">@money($line->tax_amount, $scale)</td>
                <td class="num ltr">@money($line->line_total, $scale)</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>{{ __('purchases::invoices.fields.subtotal') }}</td><td class="num ltr">@money($document->subtotal, $scale)</td></tr>
        @if ($document->discount_total->isPositive())
            <tr><td>{{ __('purchases::invoices.fields.discount_total') }}</td><td class="num ltr">@money($document->discount_total, $scale)</td></tr>
        @endif
        <tr><td>{{ __('purchases::invoices.fields.tax_total') }}</td><td class="num ltr">@money($document->tax_total, $scale)</td></tr>
        <tr class="grand"><td>{{ __('purchases::invoices.fields.total') }}</td><td class="num ltr">@money($document->total, $scale) {{ $document->currency->code }}</td></tr>
    </table>

    @if ($document->description)<div class="notes">{{ $document->description }}</div>@endif

    <div class="signatures">
        <div>{{ __('core::ui.signature_prepared') }}</div>
        <div>{{ __('core::ui.signature_received') }}</div>
    </div>
@endsection
