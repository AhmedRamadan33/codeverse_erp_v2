<div>
    <div class="card mb-3 d-print-none">
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">{{ __('accounting::accounts.fields.account') }}</label>
                <select wire:model.live="accountId" class="form-select">
                    <option value="">—</option>
                    @foreach ($accounts as $option)
                        <option value="{{ $option->id }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('accounting::reports.from') }}</label>
                <input type="date" wire:model.live="from" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('accounting::reports.to') }}</label>
                <input type="date" wire:model.live="to" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('accounting::entries.fields.branch') }}</label>
                <select wire:model.live="branchId" class="form-select">
                    @can('core.branches.all_access') <option value="">{{ __('core::ui.all') }}</option> @endcan
                    @foreach ($branches as $branch) <option value="{{ $branch->id }}">{{ $branch->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-2 text-end">
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> {{ __('accounting::reports.print') }}</button>
            </div>
        </div>
    </div>

    @if ($statement)
        <div class="card">
            <div class="card-header">
                <strong>{{ $account->label() }}</strong>
                <span class="ms-2 ltr-value text-body-secondary">{{ $from }} → {{ $to }}</span>
            </div>
            <div class="card-body p-0">
                @include('accounting::livewire.reports.partials.statement', ['statement' => $statement, 'showPartner' => true])
            </div>
        </div>
    @endif
</div>
