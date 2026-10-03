<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center">
        <input type="search" wire:model.live.debounce.400ms="search" class="form-control w-auto" placeholder="{{ __('core::ui.search') }}">
        <select wire:model.live="categoryId" class="form-select w-auto">
            <option value="">{{ __('products::products.fields.category') }}: {{ __('core::ui.all') }}</option>
            @foreach ($categories as $category) <option value="{{ $category->id }}">{{ $category->name }}</option> @endforeach
        </select>
        <select wire:model.live="type" class="form-select w-auto">
            <option value="">{{ __('products::products.fields.type') }}: {{ __('core::ui.all') }}</option>
            @foreach ($types as $type) <option value="{{ $type->value }}">{{ $type->label() }}</option> @endforeach
        </select>
        <div class="form-check form-switch">
            <input id="show_inactive" type="checkbox" wire:model.live="showInactive" class="form-check-input">
            <label for="show_inactive" class="form-check-label">{{ __('core::ui.inactive') }}</label>
        </div>
        @can('products.products.create')
            <a href="{{ route('products.products.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> {{ __('products::products.new') }}</a>
        @endcan
    </div>
    <div class="card-body p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
            <tr>
                <th>{{ __('products::products.fields.sku') }}</th>
                <th>{{ __('products::products.fields.name') }}</th>
                <th>{{ __('products::products.fields.category') }}</th>
                <th>{{ __('products::products.fields.type') }}</th>
                <th>{{ __('products::products.fields.base_unit') }}</th>
                <th class="text-end">{{ __('products::products.fields.sale_price') }}</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($products as $product)
                <tr wire:key="product-{{ $product->id }}">
                    <td class="ltr-value">{{ $product->sku }}</td>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->category?->name }}</td>
                    <td>{{ $product->type->label() }}</td>
                    <td>{{ $product->baseUnit->name }}</td>
                    <td class="text-end ltr-value">@money($product->sale_price)</td>
                    <td class="text-end">
                        @can('products.products.update')
                            <a href="{{ route('products.products.edit', $product->id) }}" class="btn btn-sm btn-outline-primary">{{ __('core::ui.edit') }}</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-body-secondary py-4">{{ __('core::ui.no_records') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($products->hasPages())
        <div class="card-footer">{{ $products->links() }}</div>
    @endif
</div>
