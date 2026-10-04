<div>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <span class="badge text-bg-primary fs-6">{{ $shift->register->code }} — {{ $shift->register->name }}</span>
        <span class="text-body-secondary small">{{ __('pos::shifts.one') }} <span class="ltr-value">{{ $shift->number }}</span></span>
        <div class="ms-auto d-flex gap-2">
            @can('pos.returns.create')
                <a href="{{ route('pos.returns.create') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-return-left"></i> {{ __('pos::receipts.return') }}</a>
            @endcan
            <a href="{{ route('pos.shifts.show', $shift->id) }}" class="btn btn-outline-danger btn-sm"><i class="bi bi-door-closed"></i> {{ __('pos::shifts.close') }}</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">@foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach</div>
    @endif

    @if ($lastReceipt)
        <div class="alert alert-success d-flex flex-wrap align-items-center gap-3">
            <span>{{ __('pos::terminal.completed', ['number' => $lastReceipt->number]) }}</span>
            @if ($lastReceipt->change->isPositive())
                <span class="fs-5 fw-semibold">{{ __('pos::receipts.fields.change') }}: <span class="ltr-value">@money($lastReceipt->change, $scale)</span></span>
            @endif
            <a href="{{ route('pos.receipts.print', $lastReceipt->id) }}" target="_blank" class="btn btn-sm btn-outline-success ms-auto"><i class="bi bi-printer"></i> {{ __('pos::receipts.print') }}</a>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header position-relative">
                    <input type="text" wire:model.live.debounce.250ms="search" wire:keydown.enter.prevent="scan" autofocus autocomplete="off"
                           class="form-control form-control-lg @error('search') is-invalid @enderror" placeholder="{{ __('pos::terminal.scan') }}"
                           x-data x-on:receipt-reset.window="$el.focus()">
                    @if ($results->isNotEmpty())
                        <div class="list-group position-absolute start-0 end-0 mx-3 shadow" style="z-index: 1050">
                            @foreach ($results as $result)
                                <button type="button" class="list-group-item list-group-item-action" wire:click="add({{ $result->id }})" wire:key="res-{{ $result->id }}">
                                    <span class="ltr-value">{{ $result->sku }}</span> — {{ $result->name }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>{{ __('pos::receipts.fields.product') }}</th>
                            <th style="width: 8rem">{{ __('pos::receipts.fields.unit') }}</th>
                            <th style="width: 9rem">{{ __('pos::receipts.fields.quantity') }}</th>
                            <th style="width: 7rem">{{ __('pos::receipts.fields.unit_price') }}</th>
                            @if ($canDiscount)<th style="width: 6rem">{{ __('pos::receipts.fields.discount') }} %</th>@endif
                            <th class="text-end" style="width: 7rem">{{ __('pos::receipts.fields.line_total') }}</th>
                            <th style="width: 2rem"></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($cart as $i => $line)
                            @php($product = $products[$line['product_id']] ?? null)
                            <tr wire:key="cart-{{ $i }}-{{ $line['product_id'] }}">
                                <td>
                                    {{ $product?->name }}
                                    @if ($product?->tracking->value === 'serial')
                                        <input type="text" wire:model.blur="cart.{{ $i }}.serials" class="form-control form-control-sm ltr-value mt-1" placeholder="{{ __('pos::receipts.fields.serials') }}">
                                    @endif
                                </td>
                                <td>
                                    <select wire:model.live="cart.{{ $i }}.unit_id" class="form-select form-select-sm">
                                        @foreach ($product?->units ?? [] as $unit) <option value="{{ $unit->unit_id }}">{{ $unit->unit->name }}</option> @endforeach
                                    </select>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <button type="button" class="btn btn-outline-secondary" wire:click="increment({{ $i }}, -1)">−</button>
                                        <input type="text" inputmode="decimal" wire:model.live.debounce.400ms="cart.{{ $i }}.quantity" class="form-control text-center ltr-value">
                                        <button type="button" class="btn btn-outline-secondary" wire:click="increment({{ $i }}, 1)">+</button>
                                    </div>
                                </td>
                                <td>
                                    @if ($canPrice)
                                        <input type="text" inputmode="decimal" wire:model.live.debounce.400ms="cart.{{ $i }}.unit_price" class="form-control form-control-sm ltr-value">
                                    @else
                                        <span class="ltr-value">@money($line['unit_price'], $scale)</span>
                                    @endif
                                </td>
                                @if ($canDiscount)
                                    <td><input type="text" inputmode="decimal" wire:model.live.debounce.400ms="cart.{{ $i }}.discount_value" class="form-control form-control-sm ltr-value"></td>
                                @endif
                                <td class="text-end ltr-value">@if ($preview) @money($preview->lines[$i]->total ?? null, $scale) @endif</td>
                                <td><button type="button" class="btn btn-sm btn-link text-danger" wire:click="remove({{ $i }})"><i class="bi bi-x-lg"></i></button></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-body-secondary py-5">{{ __('pos::terminal.empty') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <label class="form-label">{{ __('pos::receipts.fields.customer') }}</label>
                    <select wire:model.live="partnerId" class="form-select mb-3">
                        @foreach ($customers as $customer) <option value="{{ $customer->id }}">{{ $customer->name }}</option> @endforeach
                    </select>

                    @if ($canDiscount)
                        <label class="form-label">{{ __('pos::terminal.receipt_discount') }}</label>
                        <div class="input-group mb-3">
                            <input type="text" inputmode="decimal" wire:model.live.debounce.400ms="discountValue" class="form-control ltr-value">
                            <select wire:model.live="discountType" class="form-select" style="max-width: 6rem">
                                <option value="percent">%</option>
                                <option value="amount">{{ __('pos::terminal.amount') }}</option>
                            </select>
                        </div>
                    @endif

                    <table class="table table-sm mb-3">
                        <tr><th>{{ __('pos::receipts.fields.subtotal') }}</th><td class="text-end ltr-value">@money($preview?->subtotal(), $scale)</td></tr>
                        <tr><th>{{ __('pos::receipts.fields.discount_total') }}</th><td class="text-end ltr-value">@money($preview?->discountTotal(), $scale)</td></tr>
                        <tr><th>{{ __('pos::receipts.fields.tax_total') }}</th><td class="text-end ltr-value">@money($preview?->taxTotal(), $scale)</td></tr>
                        <tr class="fs-4 fw-bold"><th>{{ __('pos::receipts.fields.total') }}</th><td class="text-end ltr-value">@money($preview?->total(), $scale)</td></tr>
                    </table>

                    <div class="d-flex flex-wrap gap-2 mb-2">
                        @foreach ($methods as $method)
                            <button type="button" class="btn btn-outline-primary btn-sm" wire:click="pay({{ $method->id }})" @disabled($cart === [])>{{ $method->name }}</button>
                        @endforeach
                    </div>
                    @foreach ($payments as $p => $payment)
                        <div class="input-group input-group-sm mb-1" wire:key="pay-{{ $p }}">
                            <span class="input-group-text" style="min-width: 7rem">{{ $methods->firstWhere('id', $payment['payment_method_id'])?->name }}</span>
                            <input type="text" inputmode="decimal" wire:model.live.debounce.400ms="payments.{{ $p }}.amount" class="form-control ltr-value">
                            <button type="button" class="btn btn-outline-danger" wire:click="removePayment({{ $p }})"><i class="bi bi-x"></i></button>
                        </div>
                    @endforeach
                    @if ($cart !== [])
                        <div class="d-flex justify-content-between mt-2">
                            @if ($due->isNegative())
                                <span>{{ __('pos::receipts.fields.change') }}</span><span class="ltr-value fw-semibold text-success">@money($due->negated(), $scale)</span>
                            @else
                                <span>{{ __('pos::terminal.due') }}</span><span class="ltr-value fw-semibold">@money($due, $scale)</span>
                            @endif
                        </div>
                    @endif
                </div>

                @if ($overLimitWarning)
                    <div class="alert alert-warning mx-3">
                        {{ $overLimitWarning }}
                        <button type="button" class="btn btn-warning btn-sm mt-2 w-100" wire:click="complete(true)">{{ __('sales::invoices.post_anyway') }}</button>
                    </div>
                @endif

                <div class="card-footer">
                    <button type="button" class="btn btn-success btn-lg w-100" wire:click="complete" @disabled($cart === [])>
                        <i class="bi bi-check2-circle"></i> {{ __('pos::terminal.complete') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
