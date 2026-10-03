<div>
    <form wire:submit="save" class="card mb-3">
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">@foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach</div>
            @endif
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">{{ __('core::ui.name_ar') }}</label>
                    <input type="text" wire:model="form.name_ar" class="form-control @error('name_ar') is-invalid @enderror">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('core::ui.name_en') }}</label>
                    <input type="text" wire:model="form.name_en" class="form-control ltr-value">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input id="pl_active" type="checkbox" wire:model="form.is_active" class="form-check-input">
                        <label for="pl_active" class="form-check-label">{{ __('core::ui.active') }}</label>
                    </div>
                </div>
            </div>

            <table class="table table-sm table-bordered align-middle">
                <thead class="table-light">
                <tr>
                    <th style="min-width: 16rem">{{ __('sales::price_lists.fields.product') }}</th>
                    <th style="min-width: 8rem">{{ __('sales::price_lists.fields.unit') }}</th>
                    <th style="min-width: 8rem">{{ __('sales::price_lists.fields.price') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($form['items'] as $i => $item)
                    @php($product = $products[$item['product_id']] ?? null)
                    <tr wire:key="pli-{{ $i }}">
                        <td><livewire:products::picker :index="$i" :product-id="$item['product_id']" :key="'picker-'.$i.'-'.($item['product_id'] ?? 0)" /></td>
                        <td>
                            <select wire:model="form.items.{{ $i }}.unit_id" class="form-select form-select-sm @error('items.'.$i.'.unit_id') is-invalid @enderror">
                                @foreach ($product?->units ?? [] as $unit) <option value="{{ $unit->unit_id }}">{{ $unit->unit->name }}</option> @endforeach
                            </select>
                        </td>
                        <td><input type="text" inputmode="decimal" wire:model="form.items.{{ $i }}.price" class="form-control form-control-sm ltr-value @error('items.'.$i.'.price') is-invalid @enderror"></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-link text-danger" wire:click="removeItem({{ $i }})"><i class="bi bi-x-lg"></i></button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addItem"><i class="bi bi-plus"></i> {{ __('sales::price_lists.add_item') }}</button>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ __('core::ui.save') }}</button>
            <a href="{{ route('sales.price-lists.index') }}" class="btn btn-outline-secondary">{{ __('core::ui.cancel') }}</a>
        </div>
    </form>

    @if ($listId)
        <div class="card">
            <div class="card-header">{{ __('sales::price_lists.fields.customers') }}</div>
            <div class="card-body">
                <form wire:submit="assign" class="d-flex gap-2 mb-3">
                    <select wire:model="customerToAssign" class="form-select w-auto @error('customerToAssign') is-invalid @enderror">
                        <option value="">—</option>
                        @foreach ($customers as $customer) <option value="{{ $customer->id }}">{{ $customer->name }}</option> @endforeach
                    </select>
                    <button type="submit" class="btn btn-outline-primary">{{ __('core::ui.add') }}</button>
                </form>
                <ul class="list-group">
                    @forelse ($assigned as $customer)
                        <li class="list-group-item d-flex align-items-center" wire:key="plc-{{ $customer->id }}">
                            {{ $customer->name }}
                            <button type="button" class="btn btn-sm btn-link text-danger ms-auto" wire:click="unassign({{ $customer->id }})"><i class="bi bi-x-lg"></i></button>
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary">{{ __('core::ui.no_records') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    @endif
</div>
