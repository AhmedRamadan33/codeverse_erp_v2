<div>
    @if ($errors->any())
        <div class="alert alert-danger">@foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach</div>
    @endif

    <div class="card mb-3">
        <div class="card-body row g-2">
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('pos::shifts.fields.number') }}</div><div class="ltr-value">{{ $shift->number }}</div></div>
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('pos::shifts.fields.register') }}</div><div>{{ $shift->register->code }} — {{ $shift->register->name }}</div></div>
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('pos::shifts.fields.cashier') }}</div><div>{{ $shift->cashier->name }}</div></div>
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('pos::shifts.fields.status') }}</div><span class="badge {{ $shift->status->badge() }}">{{ $shift->status->label() }}</span></div>
            <div class="col-md-2"><div class="small text-body-secondary">{{ __('pos::shifts.fields.opened_at') }}</div><div class="ltr-value">{{ $shift->opened_at->format('Y-m-d H:i') }}</div></div>
            @if ($shift->closed_at)
                <div class="col-md-2"><div class="small text-body-secondary">{{ __('pos::shifts.fields.closed_at') }}</div><div class="ltr-value">{{ $shift->closed_at->format('Y-m-d H:i') }}</div></div>
            @endif
            @if ($shift->journalEntry)
                <div class="col-md-2"><div class="small text-body-secondary">{{ __('pos::shifts.fields.entry') }}</div><a class="ltr-value" href="{{ route('accounting.entries.show', $shift->journal_entry_id) }}">{{ $shift->journalEntry->number }}</a></div>
            @endif
            @if ($shift->valuationEntry)
                <div class="col-md-2"><div class="small text-body-secondary">{{ __('pos::shifts.fields.valuation_entry') }}</div><a class="ltr-value" href="{{ route('accounting.entries.show', $shift->valuation_entry_id) }}">{{ $shift->valuationEntry->number }}</a></div>
            @endif
            @if ($shift->notes)<div class="col-12">{{ $shift->notes }}</div>@endif
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">{{ __('pos::shifts.report') }}</div>
                <table class="table table-sm mb-0">
                    <tr><th>{{ __('pos::shifts.fields.sales') }}</th><td class="ltr-value">{{ $summary->sales }}</td><td class="text-end ltr-value">@money($summary->salesTotal, $scale)</td></tr>
                    <tr><th>{{ __('pos::shifts.fields.returns') }}</th><td class="ltr-value">{{ $summary->returns }}</td><td class="text-end ltr-value">@money($summary->returnsTotal, $scale)</td></tr>
                    <tr><th>{{ __('pos::shifts.fields.on_account') }}</th><td></td><td class="text-end ltr-value">@money($summary->creditTotal, $scale)</td></tr>
                </table>
                @can('pos.receipts.view')
                    <div class="card-footer"><a href="{{ route('pos.receipts.index', ['shift' => $shift->id]) }}">{{ __('pos::receipts.title') }}</a></div>
                @endcan
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>{{ __('pos::shifts.fields.method') }}</th>
                        <th class="text-end">{{ __('pos::shifts.fields.received') }}</th>
                        <th class="text-end">{{ __('pos::shifts.fields.refunded') }}</th>
                        <th class="text-end">{{ __('pos::shifts.fields.net') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($summary->byMethod as $methodId => $amounts)
                        <tr>
                            <td>{{ $methods[$methodId]?->name }}</td>
                            <td class="text-end ltr-value">@money($amounts['in'], $scale)</td>
                            <td class="text-end ltr-value">@money($amounts['out'], $scale)</td>
                            <td class="text-end ltr-value">@money($amounts['in']->minus($amounts['out']), $scale)</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary">{{ __('core::ui.no_records') }}</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot>
                    <tr><th colspan="3">{{ __('pos::shifts.fields.opening_float') }}</th><td class="text-end ltr-value">@money($shift->opening_float, $scale)</td></tr>
                    <tr class="fw-semibold"><th colspan="3">{{ __('pos::shifts.fields.expected_cash') }} ({{ $shift->register->cashMethod->name }})</th><td class="text-end ltr-value">@money($shift->expected_cash ?? $summary->expectedCash, $scale)</td></tr>
                    @unless ($shift->isOpen())
                        <tr><th colspan="3">{{ __('pos::shifts.fields.counted_cash') }}</th><td class="text-end ltr-value">@money($shift->counted_cash, $scale)</td></tr>
                        <tr @class(['fw-semibold', 'text-danger' => $shift->cash_difference->isNegative(), 'text-success' => $shift->cash_difference->isPositive()])>
                            <th colspan="3">{{ __('pos::shifts.fields.cash_difference') }}</th><td class="text-end ltr-value">@money($shift->cash_difference, $scale)</td>
                        </tr>
                    @endunless
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    @if ($canClose)
        <form wire:submit="close" class="card">
            <div class="card-header">{{ __('pos::shifts.close') }}</div>
            <div class="card-body row g-3">
                <div class="col-md-3">
                    <label class="form-label">{{ __('pos::shifts.fields.counted_cash') }}</label>
                    <input type="text" inputmode="decimal" wire:model="countedCash" class="form-control ltr-value @error('countedCash') is-invalid @enderror">
                </div>
                <div class="col-md-9">
                    <label class="form-label">{{ __('pos::shifts.fields.notes') }}</label>
                    <input type="text" wire:model="notes" class="form-control">
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-danger" wire:confirm="{{ __('pos::shifts.confirm_close') }}">{{ __('pos::shifts.close') }}</button>
                @if ($shift->user_id === auth()->id())
                    <a href="{{ route('pos.terminal') }}" class="btn btn-outline-secondary">{{ __('pos::menu.terminal') }}</a>
                @endif
            </div>
        </form>
    @endif
</div>
