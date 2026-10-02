<div>
    <form wire:submit="save" class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">{{ __('core::currencies.fields.currency') }}</label>
                <select wire:model="form.currency_id" class="form-select @error('form.currency_id') is-invalid @enderror">
                    <option value="">—</option>
                    @foreach ($foreign as $currency)
                        <option value="{{ $currency->id }}">{{ $currency->code }} — {{ $currency->name }}</option>
                    @endforeach
                </select>
                @error('form.currency_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('core::ui.date') }}</label>
                <input type="date" wire:model="form.date" class="form-control @error('form.date') is-invalid @enderror">
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('core::currencies.fields.rate') }} ({{ $baseCode }})</label>
                <input type="text" inputmode="decimal" wire:model="form.rate" class="form-control ltr-value @error('form.rate') is-invalid @enderror">
                @error('form.rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">{{ __('core::ui.save') }}</button>
            </div>
            <div class="col-12 form-text mt-0">{{ __('core::currencies.fields.rate_hint') }}</div>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <select wire:model.live="currencyFilter" class="form-select w-auto">
                <option value="">{{ __('core::ui.all') }}</option>
                @foreach ($foreign as $currency)
                    <option value="{{ $currency->id }}">{{ $currency->code }}</option>
                @endforeach
            </select>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                <tr>
                    <th>{{ __('core::ui.date') }}</th>
                    <th>{{ __('core::currencies.fields.currency') }}</th>
                    <th>{{ __('core::currencies.fields.rate') }}</th>
                    <th class="text-end">{{ __('core::ui.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rates as $rate)
                    <tr wire:key="rate-{{ $rate->id }}">
                        <td class="ltr-value">{{ $rate->date->toDateString() }}</td>
                        <td class="ltr-value">{{ $rate->currency->code }}</td>
                        <td class="ltr-value">{{ $rate->rate }}</td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="delete({{ $rate->id }})" wire:confirm="{{ __('core::ui.confirm_delete') }}">{{ __('core::ui.delete') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-body-secondary py-4">{{ __('core::ui.no_records') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($rates->hasPages())
            <div class="card-footer">{{ $rates->links() }}</div>
        @endif
    </div>
</div>
