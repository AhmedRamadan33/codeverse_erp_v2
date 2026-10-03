<div class="card">
    <div class="card-header d-flex flex-wrap gap-2">
        <input type="search" wire:model.live.debounce.400ms="search" class="form-control w-auto" placeholder="{{ __('core::ui.search') }}">
        <select wire:model.live="warehouseId" class="form-select w-auto">
            <option value="">{{ __('inventory::stock.all_warehouses') }}</option>
            @foreach ($warehouses as $warehouse) <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option> @endforeach
        </select>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-striped mb-0">
            <thead>
            <tr>
                <th>{{ __('inventory::stock.fields.product') }}</th>
                <th>{{ __('inventory::stock.fields.warehouse') }}</th>
                <th>{{ __('inventory::stock.fields.batch') }}</th>
                <th>{{ __('inventory::stock.fields.expiry') }}</th>
                <th class="text-end">{{ __('inventory::stock.fields.quantity') }}</th>
                <th class="text-end">{{ __('inventory::stock.fields.average_cost') }}</th>
                <th class="text-end">{{ __('inventory::stock.fields.value') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($balances as $balance)
                @php($average = $costs[$balance->product_id]->average_cost ?? \Brick\Math\BigDecimal::zero())
                <tr wire:key="bal-{{ $balance->id }}">
                    <td><a href="{{ route('inventory.moves.index', ['productId' => $balance->product_id]) }}">{{ $balance->product->label() }}</a></td>
                    <td>{{ $balance->warehouse->name }}</td>
                    <td class="ltr-value">{{ $balance->batch?->batch_number }}</td>
                    <td class="ltr-value">{{ $balance->batch?->expiry_date?->toDateString() }}</td>
                    <td class="text-end ltr-value">@money($balance->quantity, 2) {{ $balance->product->baseUnit->symbol }}</td>
                    <td class="text-end ltr-value">@money($average)</td>
                    <td class="text-end ltr-value">@money($balance->quantity->multipliedBy($average))</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-body-secondary py-4">{{ __('core::ui.no_records') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($balances->hasPages())
        <div class="card-footer">{{ $balances->links() }}</div>
    @endif
</div>
