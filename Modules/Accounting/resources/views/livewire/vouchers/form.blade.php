<form wire:submit="save" class="card">
    <div class="card-body row g-3">
        @error('voucher') <div class="col-12"><div class="alert alert-danger mb-0">{{ $message }}</div></div> @enderror
        <div class="col-md-3">
            <label class="form-label">{{ __('accounting::vouchers.fields.date') }}</label>
            <input type="date" wire:model="form.date" class="form-control @error('form.date') is-invalid @enderror">
        </div>
        <div class="col-md-3">
            <label class="form-label">{{ __('accounting::vouchers.fields.branch') }}</label>
            <select wire:model="form.branch_id" class="form-select @error('form.branch_id') is-invalid @enderror">
                @foreach ($branches as $branch) <option value="{{ $branch->id }}">{{ $branch->name }}</option> @endforeach
            </select>
            @error('form.branch_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">{{ __('accounting::vouchers.fields.partner') }}</label>
            <select wire:model="form.partner_id" class="form-select @error('form.partner_id') is-invalid @enderror">
                <option value="">—</option>
                @foreach ($partners as $partner) <option value="{{ $partner->id }}">{{ $partner->name }}</option> @endforeach
            </select>
            @error('form.partner_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3">
            <label class="form-label">{{ __('accounting::vouchers.fields.payment_method') }}</label>
            <select wire:model="form.payment_method_id" class="form-select @error('form.payment_method_id') is-invalid @enderror">
                @foreach ($methods as $method) <option value="{{ $method->id }}">{{ $method->name }}</option> @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">{{ __('accounting::vouchers.fields.currency') }}</label>
            <select wire:model.live="form.currency_id" class="form-select">
                @foreach ($currencies as $currency) <option value="{{ $currency->id }}">{{ $currency->code }}</option> @endforeach
            </select>
        </div>
        @unless ($isBase)
            <div class="col-md-2">
                <label class="form-label">{{ __('accounting::vouchers.fields.exchange_rate') }}</label>
                <input type="text" inputmode="decimal" wire:model="form.exchange_rate" class="form-control ltr-value @error('form.exchange_rate') is-invalid @enderror">
                @error('form.exchange_rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        @endunless
        <div class="col-md-3">
            <label class="form-label">{{ __('accounting::vouchers.fields.amount') }}</label>
            <input type="text" inputmode="decimal" wire:model="form.amount" class="form-control ltr-value @error('form.amount') is-invalid @enderror">
            @error('form.amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('accounting::vouchers.fields.reference') }}</label>
            <input type="text" wire:model="form.reference" class="form-control ltr-value">
        </div>
        <div class="col-md-8">
            <label class="form-label">{{ __('accounting::vouchers.fields.description') }}</label>
            <input type="text" wire:model="form.description" class="form-control">
        </div>
    </div>
    <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary">{{ __('accounting::entries.save_draft') }}</button>
        <a href="{{ route($kindEnum->routePrefix().'index') }}" class="btn btn-outline-secondary">{{ __('core::ui.cancel') }}</a>
    </div>
</form>
