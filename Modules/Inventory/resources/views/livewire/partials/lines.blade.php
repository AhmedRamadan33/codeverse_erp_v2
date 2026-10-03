{{-- Product lines for stock documents. Expects $form, $products and $withCost. --}}
<table class="table table-sm table-bordered align-middle">
    <thead class="table-light">
    <tr>
        <th style="width: 28%">{{ __('inventory::documents.fields.product') }}</th>
        <th style="width: 12%">{{ __('inventory::documents.fields.unit') }}</th>
        <th style="width: 11%">{{ __('inventory::documents.fields.quantity') }}</th>
        @if ($withCost) <th style="width: 11%">{{ __('inventory::documents.fields.unit_cost') }}</th> @endif
        <th>{{ __('inventory::documents.fields.batch') }}</th>
        @if ($withCost) <th>{{ __('inventory::documents.fields.expiry') }}</th> @endif
        <th>{{ __('inventory::documents.fields.serials') }}</th>
        <th style="width: 3rem"></th>
    </tr>
    </thead>
    <tbody>
    @foreach ($form['lines'] as $i => $line)
        @php($product = $products->firstWhere('id', (int) $line['product_id']))
        <tr wire:key="line-{{ $i }}">
            <td>
                <select wire:model.live="form.lines.{{ $i }}.product_id" class="form-select form-select-sm @error('lines.'.$i.'.product_id') is-invalid @enderror">
                    <option value="">—</option>
                    @foreach ($products as $option) <option value="{{ $option->id }}">{{ $option->label() }}</option> @endforeach
                </select>
            </td>
            <td>
                <select wire:model="form.lines.{{ $i }}.unit_id" class="form-select form-select-sm @error('lines.'.$i.'.unit_id') is-invalid @enderror">
                    @foreach ($product?->units ?? [] as $unit) <option value="{{ $unit->unit_id }}">{{ $unit->unit->name }}</option> @endforeach
                </select>
            </td>
            <td><input type="text" inputmode="decimal" wire:model="form.lines.{{ $i }}.quantity" class="form-control form-control-sm ltr-value @error('lines.'.$i.'.quantity') is-invalid @enderror"></td>
            @if ($withCost)
                <td><input type="text" inputmode="decimal" wire:model="form.lines.{{ $i }}.unit_cost" class="form-control form-control-sm ltr-value @error('lines.'.$i.'.unit_cost') is-invalid @enderror"></td>
            @endif
            <td><input type="text" wire:model="form.lines.{{ $i }}.batch_number" class="form-control form-control-sm ltr-value" @disabled($product && $product->tracking->value !== 'batch')></td>
            @if ($withCost)
                <td><input type="date" wire:model="form.lines.{{ $i }}.expiry_date" class="form-control form-control-sm" @disabled($product && $product->tracking->value !== 'batch')></td>
            @endif
            <td><input type="text" wire:model="form.lines.{{ $i }}.serials" class="form-control form-control-sm ltr-value" @disabled($product && $product->tracking->value !== 'serial')></td>
            <td class="text-center">
                @if (count($form['lines']) > 1)
                    <button type="button" class="btn btn-sm btn-link text-danger" wire:click="removeLine({{ $i }})"><i class="bi bi-x-lg"></i></button>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
<button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addLine"><i class="bi bi-plus"></i> {{ __('inventory::documents.add_line') }}</button>
