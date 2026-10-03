<div>
    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">{{ __('inventory::stock.fields.product') }}</label>
                <select wire:model.live="productId" class="form-select">
                    <option value="">—</option>
                    @foreach ($products as $option) <option value="{{ $option->id }}">{{ $option->sku }} - {{ $option->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('inventory::stock.fields.warehouse') }}</label>
                <select wire:model.live="warehouseId" class="form-select">
                    <option value="">{{ __('inventory::stock.all_warehouses') }}</option>
                    @foreach ($warehouses as $warehouse) <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('accounting::reports.from') }}</label>
                <input type="date" wire:model.live="from" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('accounting::reports.to') }}</label>
                <input type="date" wire:model.live="to" class="form-control">
            </div>
        </div>
    </div>

    @if ($product)
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>{{ __('inventory::stock.fields.date') }}</th>
                        <th>{{ __('inventory::stock.fields.type') }}</th>
                        <th>{{ __('inventory::stock.fields.warehouse') }}</th>
                        <th>{{ __('inventory::stock.fields.batch') }}</th>
                        <th class="text-end">{{ __('inventory::stock.fields.in') }}</th>
                        <th class="text-end">{{ __('inventory::stock.fields.out') }}</th>
                        <th class="text-end">{{ __('inventory::stock.fields.unit_cost') }}</th>
                        <th class="text-end">{{ __('inventory::stock.fields.balance') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr class="table-secondary">
                        <td colspan="7">{{ __('accounting::reports.opening_balance') }}</td>
                        <td class="text-end ltr-value">@money($opening, 2)</td>
                    </tr>
                    @foreach ($rows as $row)
                        @php($move = $row['move'])
                        <tr>
                            <td class="ltr-value">{{ $move->date->toDateString() }}</td>
                            <td>{{ $move->type->label() }} @if ($move->reversal_of_id) <span class="badge text-bg-secondary">↺</span> @endif</td>
                            <td>{{ $move->warehouse->name }}</td>
                            <td class="ltr-value">{{ $move->batch?->batch_number }}</td>
                            <td class="text-end ltr-value">{{ $move->quantity->isPositive() ? \Modules\Core\Support\Money::format($move->quantity) : '' }}</td>
                            <td class="text-end ltr-value">{{ $move->quantity->isNegative() ? \Modules\Core\Support\Money::format($move->quantity->negated()) : '' }}</td>
                            <td class="text-end ltr-value">@money($move->unit_cost)</td>
                            <td class="text-end ltr-value">@money($row['balance'], 2)</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="alert alert-info">{{ __('inventory::stock.choose_product') }}</div>
    @endif
</div>
