@php($rtl = \Modules\Core\Http\Middleware\SetLocale::isRtl())
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @page { size: A4; margin: 14mm 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font: 12px/1.45 Tahoma, Arial, sans-serif; color: #111; }
        .sheet { max-width: 186mm; margin: 0 auto; }
        .ltr { direction: ltr; unicode-bidi: embed; }
        header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 12px; }
        header .company { font-size: 18px; font-weight: bold; }
        header .muted, .muted { color: #555; font-size: 11px; }
        h1 { font-size: 18px; margin: 0 0 4px; text-align: end; }
        .meta { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px 16px; margin-bottom: 12px; }
        .meta div span { display: block; color: #555; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        table.lines th, table.lines td { border: 1px solid #bbb; padding: 4px 6px; }
        table.lines th { background: #f0f0f0; font-weight: bold; }
        td.num, th.num { text-align: end; white-space: nowrap; }
        table.totals { width: 45%; margin-inline-start: auto; margin-top: 10px; }
        table.totals td { padding: 3px 6px; }
        table.totals tr.grand td { font-weight: bold; font-size: 14px; border-top: 2px solid #333; }
        .notes { margin-top: 14px; }
        .signatures { display: flex; justify-content: space-between; margin-top: 48px; }
        .signatures div { width: 30%; border-top: 1px solid #333; text-align: center; padding-top: 4px; }
        .toolbar { text-align: center; margin: 10px; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
<div class="toolbar"><button type="button" onclick="window.print()">{{ __('core::ui.print') }}</button></div>
<div class="sheet">
    <header>
        <div>
            <div class="company">{{ $company['name'] ?? config('app.name') }}</div>
            <div class="muted">
                @if ($company['address'])<div>{{ $company['address'] }}</div>@endif
                @if ($company['phone'])<div class="ltr">{{ $company['phone'] }}</div>@endif
                @if ($company['tax_number'])<div>{{ __('core::ui.tax_number') }}: <span class="ltr">{{ $company['tax_number'] }}</span></div>@endif
                @if ($company['commercial_register'])<div>{{ __('core::ui.commercial_register') }}: <span class="ltr">{{ $company['commercial_register'] }}</span></div>@endif
            </div>
        </div>
        <div>
            <h1>@yield('title')</h1>
            @yield('status')
        </div>
    </header>

    @yield('content')
</div>
</body>
</html>
