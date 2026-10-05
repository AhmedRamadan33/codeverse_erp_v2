@php($rtl = \Modules\Core\Http\Middleware\SetLocale::isRtl())
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $receipt->number }}</title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body { width: 80mm; margin: 0 auto; padding: 4mm; font: 12px/1.4 Tahoma, Arial, sans-serif; color: #000; }
        h1 { font-size: 15px; margin: 0; text-align: center; }
        .center { text-align: center; }
        .muted { font-size: 11px; }
        .ltr { direction: ltr; unicode-bidi: embed; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        td.num { text-align: end; white-space: nowrap; }
        .total td { font-weight: bold; font-size: 14px; }
        @media screen { body { box-shadow: 0 0 4px #999; margin-top: 10px; } }
    </style>
</head>
<body onload="window.print()">
    <h1>{{ $company }}</h1>
    <div class="center muted">
        {{ $receipt->branch->name }}
        @if ($phone)<br><span class="ltr">{{ $phone }}</span>@endif
        @if ($taxNumber)<br>{{ __('pos::print.tax_number') }}: <span class="ltr">{{ $taxNumber }}</span>@endif
    </div>
    <hr>
    <div class="center"><strong>{{ $receipt->kind->label() }}</strong> <span class="ltr">{{ $receipt->number }}</span></div>
    <div class="muted">
        <span class="ltr">{{ $receipt->created_at->format('Y-m-d H:i') }}</span> — {{ $receipt->register->code }} — {{ $receipt->creator?->name }}<br>
        {{ __('pos::receipts.fields.customer') }}: {{ $receipt->partner->name }}
        @if ($receipt->original)<br>{{ __('pos::receipts.fields.original') }}: <span class="ltr">{{ $receipt->original->number }}</span>@endif
    </div>
    <hr>
    <table>
        @foreach ($receipt->lines as $line)
            <tr><td colspan="2">{{ $line->product->name }}</td></tr>
            <tr>
                <td class="muted"><span class="ltr">{{ $line->quantity->strippedOfTrailingZeros() }} × @money($line->unit_price, $scale)</span> {{ $line->unit->name }}</td>
                <td class="num ltr">@money($line->line_total, $scale)</td>
            </tr>
        @endforeach
    </table>
    <hr>
    <table>
        <tr><td>{{ __('pos::receipts.fields.subtotal') }}</td><td class="num ltr">@money($receipt->subtotal, $scale)</td></tr>
        @if ($receipt->discount_total->isPositive())
            <tr><td>{{ __('pos::receipts.fields.discount_total') }}</td><td class="num ltr">-@money($receipt->discount_total, $scale)</td></tr>
        @endif
        <tr><td>{{ __('pos::receipts.fields.tax_total') }}</td><td class="num ltr">@money($receipt->tax_total, $scale)</td></tr>
        <tr class="total"><td>{{ __('pos::receipts.fields.total') }}</td><td class="num ltr">@money($receipt->total, $scale) {{ $currency }}</td></tr>
        @foreach ($receipt->payments as $payment)
            <tr><td>{{ $payment->method->name }}</td><td class="num ltr">@money($payment->amount, $scale)</td></tr>
        @endforeach
        @if ($receipt->tendered)
            <tr><td>{{ __('pos::receipts.fields.tendered') }}</td><td class="num ltr">@money($receipt->tendered, $scale)</td></tr>
            <tr><td>{{ __('pos::receipts.fields.change') }}</td><td class="num ltr">@money($receipt->change, $scale)</td></tr>
        @endif
        @if ($receipt->is_credit && ! $receipt->isReturn())
            <tr><td>{{ __('pos::receipts.fields.on_account') }}</td><td class="num ltr">@money($receipt->total->minus($receipt->paid_total), $scale)</td></tr>
        @endif
    </table>
    <hr>
    {{-- Trusted HTML from other modules (ReceiptPrintExtras). --}}
    {!! $extras !!}
    <div class="center">{{ __('pos::receipts.thanks') }}</div>
</body>
</html>
