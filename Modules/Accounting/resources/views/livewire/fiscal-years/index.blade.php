<div>
    @if ($errors->any())
        <div class="alert alert-danger">@foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach</div>
    @endif

    <form wire:submit="saveLockDate" class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">{{ __('accounting::fiscal.lock_date') }}</label>
                <input type="date" wire:model="lockDate" class="form-control">
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary">{{ __('core::ui.save') }}</button></div>
            <div class="col-md-7 form-text">{{ __('accounting::fiscal.lock_date_hint') }}</div>
        </div>
    </form>

    <div class="d-flex mb-3">
        <button type="button" class="btn btn-primary ms-auto" wire:click="createNext"><i class="bi bi-plus-lg"></i> {{ __('accounting::fiscal.create_next') }}</button>
    </div>

    @foreach ($years as $year)
        <div class="card mb-3" wire:key="year-{{ $year->id }}">
            <div class="card-header d-flex">
                <strong>{{ $year->name }}</strong>
                <span class="ms-2 ltr-value text-body-secondary">{{ $year->start_date->toDateString() }} → {{ $year->end_date->toDateString() }}</span>
                <span @class(['badge ms-auto', 'text-bg-success' => $year->status->value === 'open', 'text-bg-secondary' => $year->status->value !== 'open'])>{{ $year->status->label() }}</span>
            </div>
            <div class="card-body d-flex flex-wrap gap-2">
                @foreach ($year->periods as $period)
                    <button type="button" wire:key="period-{{ $period->id }}" wire:click="toggle({{ $period->id }})"
                            @class(['btn btn-sm', 'btn-outline-success' => $period->status->value === 'open', 'btn-secondary' => $period->status->value !== 'open'])
                            title="{{ $period->status->label() }}">
                        <i @class(['bi', 'bi-unlock' => $period->status->value === 'open', 'bi-lock' => $period->status->value !== 'open'])></i>
                        <span class="ltr-value">{{ $period->start_date->format('Y-m') }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
