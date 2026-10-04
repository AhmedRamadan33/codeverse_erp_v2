<form wire:submit="save" class="card">
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger">@foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach</div>
        @endif
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label">{{ __('pos::receipts.fields.original') }}</label>
                <input type="text" wire:model.live.debounce.500ms="number" class="form-control ltr-value @if ($notFound) is-invalid @endif" placeholder="R-…" autofocus>
                @if ($notFound)<div class="invalid-feedback">{{ __('pos::receipts.receipt_not_found') }}</div>@endif
            </div>
            @if ($original)
                <div class="col-md-4"><div class="small text-body-secondary">{{ __('pos::receipts.fields.customer') }}</div><div>{{ $original->partner->name }}</div></div>
                <div class="col-md-4">
                    @if ($original->is_credit)
                        <div class="small text-body-secondary">{{ __('pos::receipts.fields.refunds') }}</div>
                        <div>{{ __('pos::receipts.credit_return') }}</div>
                    @else
                        <label class="form-label">{{ __('pos::receipts.refund_by') }}</label>
                        <select wire:model="refundMethodId" class="form-select">
                            @foreach ($methods as $method) <option value="{{ $method->id }}">{{ $method->name }}</option> @endforeach
                        </select>
                    @endif
                </div>
            @endif
        </div>

        @if ($original)
            <table class="table table-sm table-bordered align-middle">
                <thead class="table-light">
                <tr>
                    <th>{{ __('pos::receipts.fields.product') }}</th>
                    <th>{{ __('pos::receipts.fields.unit') }}</th>
                    <th class="text-end">{{ __('pos::receipts.fields.quantity') }}</th>
                    <th class="text-end">{{ __('pos::receipts.fields.line_total') }}</th>
                    <th class="text-end">{{ __('pos::receipts.fields.returnable') }}</th>
                    <th style="width: 9rem">{{ __('pos::receipts.fields.return_quantity') }}</th>
                    <th>{{ __('pos::receipts.fields.serials') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($original->lines as $line)
                    <tr wire:key="rl-{{ $line->id }}">
                        <td>{{ $line->product->label() }}</td>
                        <td>{{ $line->unit->name }}</td>
                        <td class="text-end ltr-value">@money($line->quantity, 2)</td>
                        <td class="text-end ltr-value">@money($line->line_total, $scale)</td>
                        <td class="text-end ltr-value">@money($returnable[$line->id], 2)</td>
                        <td><input type="text" inputmode="decimal" wire:model="lines.{{ $line->id }}.quantity" class="form-control form-control-sm ltr-value" @disabled(! $returnable[$line->id]->isPositive())></td>
                        <td>
                            @if ($line->product->tracking->value === 'serial')
                                <input type="text" wire:model="lines.{{ $line->id }}.serials" class="form-control form-control-sm ltr-value" placeholder="{{ implode(', ', $line->serials ?? []) }}">
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary" @disabled(! $original)>{{ __('pos::receipts.complete_return') }}</button>
        <a href="{{ route('pos.terminal') }}" class="btn btn-outline-secondary">{{ __('core::ui.cancel') }}</a>
    </div>
</form>
