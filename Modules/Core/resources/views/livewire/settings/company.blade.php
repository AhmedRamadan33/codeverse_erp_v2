<form wire:submit="save" class="card">
    <div class="card-body row g-3">
        @foreach (['company_name' => 'col-md-6', 'company_phone' => 'col-md-3 ltr-value', 'company_tax_number' => 'col-md-3 ltr-value', 'company_commercial_register' => 'col-md-3 ltr-value', 'company_address' => 'col-md-9'] as $key => $class)
            <div class="{{ str_replace(' ltr-value', '', $class) }}">
                <label class="form-label">{{ __('core::settings.fields.'.$key) }}</label>
                <input type="text" wire:model="form.{{ $key }}" @class(['form-control', 'ltr-value' => str_contains($class, 'ltr-value'), 'is-invalid' => $errors->has('form.'.$key)])>
                @error('form.'.$key) <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        @endforeach
        <div class="col-md-3">
            <label class="form-label">{{ __('core::settings.fields.default_locale') }}</label>
            <select wire:model="form.default_locale" class="form-select">
                <option value="ar">العربية</option>
                <option value="en">English</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">{{ __('core::settings.fields.base_currency') }}</label>
            <input type="text" value="{{ $baseCurrency }}" class="form-control ltr-value" disabled>
            <div class="form-text">{{ __('core::settings.base_currency_locked') }}</div>
        </div>
    </div>
    <div class="card-footer">
        <button type="submit" class="btn btn-primary">{{ __('core::ui.save') }}</button>
    </div>
</form>
