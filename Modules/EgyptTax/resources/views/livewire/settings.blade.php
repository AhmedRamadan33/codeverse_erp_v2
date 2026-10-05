<form wire:submit="save" class="card">
    <div class="card-body row g-3">
        <div class="col-md-3">
            <label class="form-label">{{ __('egypttax::settings.fields.environment') }}</label>
            <select wire:model="form.environment" class="form-select">
                @foreach ($environments as $env) <option value="{{ $env }}">{{ __('egypttax::settings.environments.'.$env) }}</option> @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">{{ __('egypttax::settings.fields.rin') }}</label>
            <input type="text" wire:model="form.rin" class="form-control ltr-value @error('form.rin') is-invalid @enderror">
            @error('form.rin') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('egypttax::settings.fields.company_trade_name') }}</label>
            <input type="text" wire:model="form.company_trade_name" class="form-control @error('form.company_trade_name') is-invalid @enderror">
            @error('form.company_trade_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-2">
            <label class="form-label">{{ __('egypttax::settings.fields.activity_code') }}</label>
            <input type="text" wire:model="form.activity_code" class="form-control ltr-value @error('form.activity_code') is-invalid @enderror">
            @error('form.activity_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">{{ __('egypttax::settings.fields.client_id') }}</label>
            <input type="text" wire:model="form.client_id" class="form-control ltr-value @error('form.client_id') is-invalid @enderror" autocomplete="off">
            @error('form.client_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">{{ __('egypttax::settings.fields.client_secret') }}</label>
            <input type="password" wire:model="form.client_secret" class="form-control ltr-value @error('form.client_secret') is-invalid @enderror" autocomplete="new-password"
                   placeholder="{{ $hasSecret ? __('egypttax::settings.secret_kept') : '' }}">
            @error('form.client_secret') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="form-text">{{ __('egypttax::settings.credentials_hint') }}</div>
        </div>
        <div class="col-md-3">
            <label class="form-label">{{ __('egypttax::settings.fields.exempt_tax_type') }}</label>
            <input type="text" wire:model="form.exempt_tax_type" class="form-control ltr-value @error('form.exempt_tax_type') is-invalid @enderror">
        </div>
        <div class="col-md-3">
            <label class="form-label">{{ __('egypttax::settings.fields.exempt_sub_type') }}</label>
            <input type="text" wire:model="form.exempt_sub_type" class="form-control ltr-value @error('form.exempt_sub_type') is-invalid @enderror">
            <div class="form-text">{{ __('egypttax::settings.exempt_hint') }}</div>
        </div>
        <div class="col-md-6 d-flex align-items-end">
            <div class="form-check form-switch">
                <input id="eta_active" type="checkbox" wire:model="form.is_active" class="form-check-input">
                <label for="eta_active" class="form-check-label">{{ __('egypttax::settings.fields.is_active') }}</label>
            </div>
        </div>
    </div>
    <div class="card-footer">
        <button type="submit" class="btn btn-primary">{{ __('core::ui.save') }}</button>
    </div>
</form>
