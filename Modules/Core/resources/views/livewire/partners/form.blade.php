<form wire:submit="save" class="card">
    <div class="card-body">
        @error('form.is_customer') <div class="alert alert-danger">{{ $message }}</div> @enderror
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">{{ __('core::partners.fields.type') }}</label>
                <select wire:model="form.type" class="form-select @error('form.type') is-invalid @enderror">
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('core::partners.fields.name') }}</label>
                <input type="text" wire:model="form.name" class="form-control @error('form.name') is-invalid @enderror">
                @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3 d-flex align-items-end gap-3">
                <div class="form-check">
                    <input id="is_customer" type="checkbox" wire:model="form.is_customer" class="form-check-input">
                    <label for="is_customer" class="form-check-label">{{ __('core::partners.fields.is_customer') }}</label>
                </div>
                <div class="form-check">
                    <input id="is_supplier" type="checkbox" wire:model="form.is_supplier" class="form-check-input">
                    <label for="is_supplier" class="form-check-label">{{ __('core::partners.fields.is_supplier') }}</label>
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label">{{ __('core::ui.phone') }}</label>
                <input type="text" wire:model="form.phone" class="form-control ltr-value @error('form.phone') is-invalid @enderror">
                @error('form.phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('core::ui.email') }}</label>
                <input type="email" wire:model="form.email" class="form-control ltr-value @error('form.email') is-invalid @enderror">
                @error('form.email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('core::ui.address') }}</label>
                <input type="text" wire:model="form.address" class="form-control @error('form.address') is-invalid @enderror">
            </div>

            <div class="col-md-4">
                <label class="form-label">{{ __('core::partners.fields.tax_number') }}</label>
                <input type="text" wire:model="form.tax_number" class="form-control ltr-value @error('form.tax_number') is-invalid @enderror">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('core::partners.fields.national_id') }}</label>
                <input type="text" wire:model="form.national_id" class="form-control ltr-value @error('form.national_id') is-invalid @enderror">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('core::partners.fields.commercial_register') }}</label>
                <input type="text" wire:model="form.commercial_register" class="form-control ltr-value @error('form.commercial_register') is-invalid @enderror">
            </div>

            <div class="col-md-4">
                <label class="form-label">{{ __('core::partners.fields.credit_limit') }}</label>
                <input type="text" inputmode="decimal" wire:model="form.credit_limit" class="form-control ltr-value @error('form.credit_limit') is-invalid @enderror">
                @error('form.credit_limit') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('core::partners.fields.payment_term_days') }}</label>
                <input type="number" min="0" wire:model="form.payment_term_days" class="form-control @error('form.payment_term_days') is-invalid @enderror">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('core::partners.fields.branch') }}</label>
                <select wire:model="form.branch_id" class="form-select @error('form.branch_id') is-invalid @enderror">
                    <option value="">{{ __('core::partners.all_branches') }}</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
                @error('form.branch_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-12">
                <div class="form-check form-switch">
                    <input id="is_active" type="checkbox" wire:model="form.is_active" class="form-check-input">
                    <label for="is_active" class="form-check-label">{{ __('core::ui.active') }}</label>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('core::ui.save') }}</button>
        <a href="{{ route('core.partners.index') }}" class="btn btn-outline-secondary">{{ __('core::ui.cancel') }}</a>
    </div>
</form>
