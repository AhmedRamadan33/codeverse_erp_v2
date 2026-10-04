<div class="card" style="min-width: min(32rem, 92vw)">
    <div class="card-header">
        <div class="fw-semibold">{{ __('core::install.title') }}</div>
        <div class="small text-body-secondary">{{ __('core::install.step', ['current' => $step + 1, 'total' => count(\Modules\Core\Livewire\Install\Wizard::STEPS)]) }} — {{ __("core::install.steps.{$stepName}") }}</div>
    </div>

    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger">@foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach</div>
        @endif

        @if ($stepName === 'checks')
            <ul class="list-group mb-2">
                @foreach ($checks as $label => $met)
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ $label }}</span>
                        <i @class(['bi', 'bi-check-circle-fill text-success' => $met, 'bi-x-circle-fill text-danger' => ! $met])></i>
                    </li>
                @endforeach
            </ul>
            @if (in_array(false, $checks, true))
                <div class="alert alert-warning small mb-0">{{ __('core::install.checks.fix') }}</div>
            @endif
        @elseif ($stepName === 'company')
            <div class="mb-3">
                <label class="form-label">{{ __('core::install.company') }}</label>
                <input type="text" wire:model="form.company" class="form-control @error('company') is-invalid @enderror">
            </div>
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label">{{ __('core::install.currency') }}</label>
                    <select wire:model="form.currency" class="form-select">
                        @foreach ($currencies as $currency) <option value="{{ $currency['code'] }}">{{ $currency['code'] }} — {{ $currency['name'][app()->getLocale()] ?? $currency['name']['ar'] }}</option> @endforeach
                    </select>
                    <div class="form-text">{{ __('core::install.currency_hint') }}</div>
                </div>
                <div class="col-6">
                    <label class="form-label">{{ __('core::install.locale') }}</label>
                    <select wire:model="form.locale" class="form-select">
                        <option value="ar">العربية</option>
                        <option value="en">English</option>
                    </select>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label">{{ __('core::install.branch_ar') }}</label>
                    <input type="text" wire:model="form.branch_ar" class="form-control @error('branch_ar') is-invalid @enderror">
                </div>
                <div class="col-6">
                    <label class="form-label">{{ __('core::install.branch_en') }}</label>
                    <input type="text" wire:model="form.branch_en" class="form-control ltr-value">
                </div>
                <div class="col-6">
                    <label class="form-label">{{ __('core::install.branch_code') }}</label>
                    <input type="text" wire:model="form.branch_code" class="form-control ltr-value @error('branch_code') is-invalid @enderror">
                </div>
            </div>
        @elseif ($stepName === 'admin')
            <div class="mb-3">
                <label class="form-label">{{ __('core::install.admin_name') }}</label>
                <input type="text" wire:model="form.admin_name" class="form-control @error('admin_name') is-invalid @enderror">
            </div>
            <div class="mb-3">
                <label class="form-label">{{ __('core::install.admin_email') }}</label>
                <input type="email" wire:model="form.admin_email" class="form-control ltr-value @error('admin_email') is-invalid @enderror">
            </div>
            <div class="mb-3">
                <label class="form-label">{{ __('core::install.admin_password') }}</label>
                <input type="password" wire:model="form.admin_password" class="form-control @error('admin_password') is-invalid @enderror" autocomplete="new-password">
            </div>
            <div>
                <label class="form-label">{{ __('core::install.admin_password_confirmation') }}</label>
                <input type="password" wire:model="form.admin_password_confirmation" class="form-control" autocomplete="new-password">
            </div>
        @else
            <p class="text-body-secondary small">{{ __('core::install.modules_hint') }}</p>
            @foreach ($modules as $name => $module)
                <div class="form-check mb-2" wire:key="mod-{{ $name }}">
                    <input id="mod-{{ $name }}" type="checkbox" class="form-check-input" value="{{ $name }}" wire:model="form.modules">
                    <label for="mod-{{ $name }}" class="form-check-label">
                        <span class="fw-semibold">{{ $module['name'] }}</span>
                        <span class="d-block small text-body-secondary">{{ $module['description'] }}</span>
                        @if ($module['requires'])
                            <span class="d-block small text-body-secondary">{{ __('core::install.requires', ['modules' => implode(', ', array_map(fn ($r) => $modules[$r]['name'] ?? $r, $module['requires']))]) }}</span>
                        @endif
                    </label>
                </div>
            @endforeach
        @endif
    </div>

    <div class="card-footer d-flex gap-2">
        @if ($step > 0)
            <button type="button" class="btn btn-outline-secondary" wire:click="back">{{ __('core::install.back') }}</button>
        @endif
        @if ($stepName === 'modules')
            <button type="button" class="btn btn-primary ms-auto" wire:click="install" wire:loading.attr="disabled">
                <span wire:loading wire:target="install" class="spinner-border spinner-border-sm"></span>
                {{ __('core::install.install') }}
            </button>
        @else
            <button type="button" class="btn btn-primary ms-auto" wire:click="next" @disabled($stepName === 'checks' && in_array(false, $checks, true))>{{ __('core::install.next') }}</button>
        @endif
    </div>
</div>
