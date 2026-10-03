<form wire:submit="save" class="card">
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger">@foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach</div>
        @endif
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="form-label">{{ __('sales::invoices.fields.customer') }}</label>
                <select wire:model.live="form.partner_id" class="form-select @error('partner_id') is-invalid @enderror">
                    <option value="">—</option>
                    @foreach ($customers as $customer) <option value="{{ $customer->id }}">{{ $customer->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('sales::invoices.fields.price_list') }}</label>
                <select wire:model.live="form.price_list_id" class="form-select">
                    <option value="">{{ __('sales::invoices.default_price_list') }}</option>
                    @foreach ($priceLists as $list) <option value="{{ $list->id }}">{{ $list->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('sales::invoices.fields.date') }}</label>
                <input type="date" wire:model="form.date" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('sales::invoices.fields.due_date') }}</label>
                <input type="date" wire:model="form.due_date" class="form-control @error('due_date') is-invalid @enderror">
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('sales::invoices.fields.warehouse') }}</label>
                <select wire:model="form.warehouse_id" class="form-select">
                    @foreach ($warehouses as $warehouse) <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('sales::invoices.fields.currency') }}</label>
                <select wire:model.live="form.currency_id" class="form-select">
                    @foreach ($currencies as $currency) <option value="{{ $currency->id }}">{{ $currency->code }}</option> @endforeach
                </select>
            </div>
            @unless ($isBase)
                <div class="col-md-2">
                    <label class="form-label">{{ __('sales::invoices.fields.exchange_rate') }}</label>
                    <input type="text" inputmode="decimal" wire:model="form.exchange_rate" class="form-control ltr-value @error('exchange_rate') is-invalid @enderror">
                </div>
            @endunless
            <div class="col-md-8">
                <label class="form-label">{{ __('sales::invoices.fields.description') }}</label>
                <input type="text" wire:model="form.description" class="form-control">
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle">
                <thead class="table-light">
                <tr>
                    <th style="min-width: 16rem">{{ __('sales::invoices.fields.product') }}</th>
                    <th style="min-width: 7rem">{{ __('sales::invoices.fields.unit') }}</th>
                    <th style="min-width: 5rem">{{ __('sales::invoices.fields.quantity') }}</th>
                    <th style="min-width: 6rem">{{ __('sales::invoices.fields.unit_price') }}</th>
                    <th style="min-width: 9rem">{{ __('sales::invoices.fields.line_discount') }}</th>
                    <th style="min-width: 8rem">{{ __('sales::invoices.fields.tax') }}</th>
                    <th style="min-width: 9rem">{{ __('sales::invoices.fields.batch') }} / {{ __('sales::invoices.fields.serials') }}</th>
                    <th class="text-end" style="min-width: 7rem">{{ __('sales::invoices.fields.line_total') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($form['lines'] as $i => $line)
                    @php($product = $products[$line['product_id']] ?? null)
                    <tr wire:key="pl-{{ $i }}">
                        <td><livewire:products::picker :index="$i" :product-id="$line['product_id']" :key="'picker-'.$i.'-'.($line['product_id'] ?? 0)" /></td>
                        <td>
                            <select wire:model.live="form.lines.{{ $i }}.unit_id" class="form-select form-select-sm">
                                @foreach ($product?->units ?? [] as $unit) <option value="{{ $unit->unit_id }}">{{ $unit->unit->name }}</option> @endforeach
                            </select>
                        </td>
                        <td><input type="text" inputmode="decimal" wire:model.live.debounce.400ms="form.lines.{{ $i }}.quantity" class="form-control form-control-sm ltr-value @error('lines.'.$i.'.quantity') is-invalid @enderror"></td>
                        <td><input type="text" inputmode="decimal" wire:model.live.debounce.400ms="form.lines.{{ $i }}.unit_price" class="form-control form-control-sm ltr-value @error('lines.'.$i.'.unit_price') is-invalid @enderror"></td>
                        <td>
                            <div class="input-group input-group-sm">
                                <input type="text" inputmode="decimal" wire:model.live.debounce.400ms="form.lines.{{ $i }}.discount_value" class="form-control ltr-value">
                                <select wire:model.live="form.lines.{{ $i }}.discount_type" class="form-select" style="max-width: 4.5rem">
                                    <option value="percent">%</option>
                                    <option value="amount">{{ __('sales::invoices.discount_types.amount') }}</option>
                                </select>
                            </div>
                        </td>
                        <td>
                            <select wire:model.live="form.lines.{{ $i }}.tax_id" class="form-select form-select-sm">
                                <option value="">{{ __('sales::invoices.no_tax') }}</option>
                                @foreach ($taxes as $tax) <option value="{{ $tax->id }}">{{ $tax->name }}</option> @endforeach
                            </select>
                        </td>
                        <td>
                            @if ($product?->tracking->value === 'batch')
                                <input type="text" wire:model="form.lines.{{ $i }}.batch_number" class="form-control form-control-sm ltr-value" placeholder="{{ __('sales::invoices.batch_auto') }}">
                            @elseif ($product?->tracking->value === 'serial')
                                <input type="text" wire:model="form.lines.{{ $i }}.serials" class="form-control form-control-sm ltr-value" placeholder="{{ __('sales::invoices.fields.serials') }}">
                            @endif
                        </td>
                        <td class="text-end ltr-value">@if ($preview) @money($preview->lines[$i]->total ?? null, $scale) @endif</td>
                        <td class="text-center">
                            @if (count($form['lines']) > 1)
                                <button type="button" class="btn btn-sm btn-link text-danger" wire:click="removeLine({{ $i }})"><i class="bi bi-x-lg"></i></button>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addLine"><i class="bi bi-plus"></i> {{ __('sales::invoices.add_line') }}</button>

        <div class="row justify-content-end mt-3">
            <div class="col-md-5">
                <table class="table table-sm mb-0">
                    <tr>
                        <th>{{ __('sales::invoices.fields.discount') }}</th>
                        <td>
                            <div class="input-group input-group-sm">
                                <input type="text" inputmode="decimal" wire:model.live.debounce.400ms="form.discount_value" class="form-control ltr-value">
                                <select wire:model.live="form.discount_type" class="form-select" style="max-width: 4.5rem">
                                    <option value="amount">{{ __('sales::invoices.discount_types.amount') }}</option>
                                    <option value="percent">%</option>
                                </select>
                            </div>
                        </td>
                    </tr>
                    @if ($preview)
                        <tr><th>{{ __('sales::invoices.fields.subtotal') }}</th><td class="text-end ltr-value">@money($preview->subtotal(), $scale)</td></tr>
                        <tr><th>{{ __('sales::invoices.fields.discount_total') }}</th><td class="text-end ltr-value">@money($preview->discountTotal(), $scale)</td></tr>
                        <tr><th>{{ __('sales::invoices.fields.tax_total') }}</th><td class="text-end ltr-value">@money($preview->taxTotal(), $scale)</td></tr>
                        <tr class="fw-semibold"><th>{{ __('sales::invoices.fields.total') }}</th><td class="text-end ltr-value">@money($preview->total(), $scale)</td></tr>
                    @endif
                    <tr>
                        <th>{{ __('sales::invoices.fields.paid_now') }}</th>
                        <td>
                            <div class="input-group input-group-sm">
                                <input type="text" inputmode="decimal" wire:model="form.paid_amount" class="form-control ltr-value @error('paid_amount') is-invalid @enderror">
                                <select wire:model="form.payment_method_id" class="form-select" style="max-width: 9rem">
                                    @foreach ($methods as $method) <option value="{{ $method->id }}">{{ $method->name }}</option> @endforeach
                                </select>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary">{{ __('sales::invoices.save_draft') }}</button>
        <a href="{{ route('sales.invoices.index') }}" class="btn btn-outline-secondary">{{ __('core::ui.cancel') }}</a>
    </div>
</form>
