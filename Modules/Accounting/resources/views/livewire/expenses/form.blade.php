<form wire:submit="save" class="card">
    <div class="card-body">
        @error('voucher') <div class="alert alert-danger">{{ $message }}</div> @enderror
        <div class="row g-3 mb-3">
            <div class="col-md-2">
                <label class="form-label">{{ __('accounting::vouchers.fields.date') }}</label>
                <input type="date" wire:model="form.date" class="form-control @error('form.date') is-invalid @enderror">
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('accounting::vouchers.fields.branch') }}</label>
                <select wire:model="form.branch_id" class="form-select @error('form.branch_id') is-invalid @enderror">
                    @foreach ($branches as $branch) <option value="{{ $branch->id }}">{{ $branch->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('accounting::vouchers.fields.payment_method') }}</label>
                <select wire:model="form.payment_method_id" class="form-select">
                    @foreach ($methods as $method) <option value="{{ $method->id }}">{{ $method->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('accounting::expenses.fields.payee') }}</label>
                <select wire:model="form.partner_id" class="form-select">
                    <option value="">—</option>
                    @foreach ($partners as $partner) <option value="{{ $partner->id }}">{{ $partner->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">{{ __('accounting::vouchers.fields.currency') }}</label>
                <select wire:model.live="form.currency_id" class="form-select">
                    @foreach ($currencies as $currency) <option value="{{ $currency->id }}">{{ $currency->code }}</option> @endforeach
                </select>
            </div>
            @unless ($isBase)
                <div class="col-md-1">
                    <label class="form-label">{{ __('accounting::vouchers.fields.exchange_rate') }}</label>
                    <input type="text" inputmode="decimal" wire:model="form.exchange_rate" class="form-control ltr-value @error('form.exchange_rate') is-invalid @enderror">
                </div>
            @endunless
            <div class="col-md-4">
                <label class="form-label">{{ __('accounting::vouchers.fields.reference') }}</label>
                <input type="text" wire:model="form.reference" class="form-control ltr-value">
            </div>
            <div class="col-md-8">
                <label class="form-label">{{ __('accounting::vouchers.fields.description') }}</label>
                <input type="text" wire:model="form.description" class="form-control">
            </div>
        </div>

        <table class="table table-sm table-bordered align-middle">
            <thead class="table-light">
            <tr>
                <th style="width: 32%">{{ __('accounting::expenses.fields.account') }}</th>
                <th>{{ __('accounting::vouchers.fields.description') }}</th>
                <th style="width: 15%">{{ __('accounting::expenses.fields.net') }}</th>
                <th style="width: 18%">{{ __('accounting::expenses.fields.tax') }}</th>
                <th style="width: 3rem"></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($form['lines'] as $i => $line)
                <tr wire:key="ev-line-{{ $i }}">
                    <td>
                        <select wire:model="form.lines.{{ $i }}.account_id" class="form-select form-select-sm @error('form.lines.'.$i.'.account_id') is-invalid @enderror">
                            <option value="">—</option>
                            @foreach ($accounts as $account) <option value="{{ $account->id }}">{{ $account->label() }}</option> @endforeach
                        </select>
                        @error('form.lines.'.$i.'.account_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </td>
                    <td><input type="text" wire:model="form.lines.{{ $i }}.description" class="form-control form-control-sm"></td>
                    <td><input type="text" inputmode="decimal" wire:model.live.debounce.400ms="form.lines.{{ $i }}.amount" class="form-control form-control-sm ltr-value @error('form.lines.'.$i.'.amount') is-invalid @enderror"></td>
                    <td>
                        <select wire:model.live="form.lines.{{ $i }}.tax_id" class="form-select form-select-sm">
                            <option value="">{{ __('accounting::expenses.no_tax') }}</option>
                            @foreach ($taxes as $tax) <option value="{{ $tax->id }}">{{ $tax->name }}</option> @endforeach
                        </select>
                    </td>
                    <td class="text-center">
                        @if (count($form['lines']) > 1)
                            <button type="button" class="btn btn-sm btn-link text-danger" wire:click="removeLine({{ $i }})"><i class="bi bi-x-lg"></i></button>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr>
                <td colspan="2"><button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addLine"><i class="bi bi-plus"></i> {{ __('accounting::entries.add_line') }}</button></td>
                <td colspan="3" class="ltr-value small">
                    {{ __('accounting::expenses.fields.subtotal') }}: @money($preview['subtotal'], $scale) ·
                    {{ __('accounting::expenses.fields.tax_amount') }}: @money($preview['tax'], $scale) ·
                    <strong>{{ __('accounting::expenses.fields.total') }}: @money($preview['subtotal']->plus($preview['tax']), $scale)</strong>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary">{{ __('accounting::entries.save_draft') }}</button>
        <a href="{{ route('accounting.expenses.index') }}" class="btn btn-outline-secondary">{{ __('core::ui.cancel') }}</a>
    </div>
</form>
