<div>
    <div class="card mb-3 d-print-none">
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">{{ __('accounting::reports.aging.kind') }}</label>
                <select wire:model.live="kind" class="form-select">
                    <option value="receivable">{{ __('accounting::reports.aging.receivable') }}</option>
                    <option value="payable">{{ __('accounting::reports.aging.payable') }}</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('accounting::entries.fields.branch') }}</label>
                <select wire:model.live="branchId" class="form-select">
                    @can('core.branches.all_access') <option value="">{{ __('core::ui.all') }}</option> @endcan
                    @foreach ($branches as $branch) <option value="{{ $branch->id }}">{{ $branch->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-6 text-end">
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> {{ __('accounting::reports.print') }}</button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            {{ __("accounting::reports.aging.{$kind}") }} — <span class="ltr-value">{{ now()->toDateString() }}</span>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead class="table-light">
                <tr>
                    <th>{{ __('accounting::reports.aging.partner') }}</th>
                    @foreach ($columns as $column)
                        <th class="text-end">{{ __("accounting::reports.aging.columns.{$column}") }}</th>
                    @endforeach
                    <th class="text-end">{{ __('accounting::reports.aging.balance') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr wire:key="age-{{ $row->partnerId }}">
                        <td><a href="{{ route('accounting.reports.partner-statement', ['partnerId' => $row->partnerId]) }}">{{ $row->partnerName }}</a></td>
                        @foreach ($columns as $column)
                            <td class="text-end ltr-value">@if (! $row->amounts[$column]->isZero()) @money($column === 'unallocated' ? $row->amounts[$column]->negated() : $row->amounts[$column]) @endif</td>
                        @endforeach
                        <td class="text-end ltr-value fw-semibold">@money($row->total())</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columns) + 2 }}" class="text-center text-body-secondary py-4">{{ __('core::ui.no_records') }}</td></tr>
                @endforelse
                </tbody>
                <tfoot class="table-light fw-semibold">
                <tr>
                    <td>{{ __('accounting::reports.total') }}</td>
                    @foreach ($columns as $column)
                        <td class="text-end ltr-value">@money($column === 'unallocated' ? $totals[$column]->negated() : $totals[$column])</td>
                    @endforeach
                    <td class="text-end ltr-value">@money($grandTotal)</td>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
