<div class="position-relative">
    <input type="text" wire:model.live.debounce.250ms="search" wire:keydown.enter.prevent="pickFirst"
           class="form-control form-control-sm" placeholder="{{ __('products::products.picker_placeholder') }}" autocomplete="off">
    @if ($open && $results->isNotEmpty())
        <div class="list-group position-absolute w-100 shadow" style="z-index: 1050">
            @foreach ($results as $product)
                <button type="button" class="list-group-item list-group-item-action py-1 small" wire:click="pick({{ $product->id }})" wire:key="pick-{{ $index }}-{{ $product->id }}">
                    <span class="ltr-value">{{ $product->sku }}</span> — {{ $product->name }}
                </button>
            @endforeach
        </div>
    @endif
</div>
