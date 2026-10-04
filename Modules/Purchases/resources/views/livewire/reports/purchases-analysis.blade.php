<div>
    <div class="card mb-3 d-print-none">
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label">{{ __('purchases::reports.from') }}</label>
                <input type="date" wire:model.live="from" class="form-control @error('from') is-invalid @enderror">
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('purchases::reports.to') }}</label>
                <input type="date" wire:model.live="to" class="form-control @error('to') is-invalid @enderror">
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('purchases::reports.branch') }}</label>
                <select wire:model.live="branchId" class="form-select">
                    @can('core.branches.all_access') <option value="">{{ __('core::ui.all') }}</option> @endcan
                    @foreach ($branches as $branch) <option value="{{ $branch->id }}">{{ $branch->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('purchases::reports.group_by') }}</label>
                <select wire:model.live="groupBy" class="form-select">
                    @foreach ($groups as $group) <option value="{{ $group }}">{{ __("purchases::reports.groups.{$group}") }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-2 text-end">
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> {{ __('core::ui.print') }}</button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">{{ __('purchases::reports.title') }} — <span class="ltr-value">{{ $from }} → {{ $to }}</span></div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead class="table-light">
                <tr>
                    <th>{{ __("purchases::reports.groups.{$groupBy}") }}</th>
                    @if ($groupBy === 'product')<th class="text-end">{{ __('purchases::reports.quantity') }}</th>@endif
                    <th class="text-end">{{ __('purchases::reports.net') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $labels[$row->key] ?? $row->key ?? '—' }}</td>
                        @if ($groupBy === 'product')<td class="text-end ltr-value">{{ \Brick\Math\BigDecimal::of($row->quantity)->strippedOfTrailingZeros() }}</td>@endif
                        <td class="text-end ltr-value">@money($row->net)</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-body-secondary py-4">{{ __('core::ui.no_records') }}</td></tr>
                @endforelse
                </tbody>
                <tfoot class="table-light fw-semibold">
                <tr>
                    <td>{{ __('purchases::reports.total') }}</td>
                    @if ($groupBy === 'product')<td></td>@endif
                    <td class="text-end ltr-value">@money($total)</td>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
