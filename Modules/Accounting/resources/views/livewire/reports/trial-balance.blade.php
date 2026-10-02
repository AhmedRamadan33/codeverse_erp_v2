<div>
    <div class="card mb-3 d-print-none">
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">{{ __('accounting::reports.from') }}</label>
                <input type="date" wire:model.live="from" class="form-control @error('from') is-invalid @enderror">
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('accounting::reports.to') }}</label>
                <input type="date" wire:model.live="to" class="form-control @error('to') is-invalid @enderror">
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('accounting::entries.fields.branch') }}</label>
                <select wire:model.live="branchId" class="form-select">
                    @can('core.branches.all_access') <option value="">{{ __('core::ui.all') }}</option> @endcan
                    @foreach ($branches as $branch) <option value="{{ $branch->id }}">{{ $branch->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-3 text-end">
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> {{ __('accounting::reports.print') }}</button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-none d-print-block">
            <h5>{{ __('accounting::menu.trial_balance') }}</h5>
            <div class="ltr-value">{{ $from }} → {{ $to }}</div>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-bordered mb-0">
                <thead class="table-light">
                <tr>
                    <th rowspan="2">{{ __('accounting::accounts.fields.account') }}</th>
                    <th colspan="2" class="text-center">{{ __('accounting::reports.opening') }}</th>
                    <th colspan="2" class="text-center">{{ __('accounting::reports.movement') }}</th>
                    <th colspan="2" class="text-center">{{ __('accounting::reports.closing') }}</th>
                </tr>
                <tr>
                    @foreach (range(1, 3) as $_)
                        <th class="text-end">{{ __('accounting::entries.fields.debit') }}</th>
                        <th class="text-end">{{ __('accounting::entries.fields.credit') }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td><a href="{{ route('accounting.reports.general-ledger', ['accountId' => $row->account->id, 'from' => $from, 'to' => $to]) }}">{{ $row->account->label() }}</a></td>
                        <td class="text-end ltr-value">{{ $row->opening->isPositive() ? \Modules\Core\Support\Money::format($row->opening) : '' }}</td>
                        <td class="text-end ltr-value">{{ $row->opening->isNegative() ? \Modules\Core\Support\Money::format($row->opening->negated()) : '' }}</td>
                        <td class="text-end ltr-value">{{ $row->debit->isZero() ? '' : \Modules\Core\Support\Money::format($row->debit) }}</td>
                        <td class="text-end ltr-value">{{ $row->credit->isZero() ? '' : \Modules\Core\Support\Money::format($row->credit) }}</td>
                        <td class="text-end ltr-value">{{ $row->closing->isPositive() ? \Modules\Core\Support\Money::format($row->closing) : '' }}</td>
                        <td class="text-end ltr-value">{{ $row->closing->isNegative() ? \Modules\Core\Support\Money::format($row->closing->negated()) : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-body-secondary py-4">{{ __('core::ui.no_records') }}</td></tr>
                @endforelse
                </tbody>
                <tfoot class="table-light fw-semibold">
                <tr>
                    <td>{{ __('accounting::entries.fields.total') }}</td>
                    @foreach (['opening_debit', 'opening_credit', 'debit', 'credit', 'closing_debit', 'closing_credit'] as $key)
                        <td class="text-end ltr-value">@money($totals[$key])</td>
                    @endforeach
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
