<?php

namespace Modules\Products\Livewire;

use Livewire\Component;
use Modules\Products\Models\Product;
use Modules\Products\Support\ProductLookup;

/**
 * Search-as-you-type product picker for document lines. A scanned barcode (Enter)
 * picks the product and the unit the barcode stands for. Tells the parent through
 * the "product-picked" event: index, productId, unitId.
 */
class Picker extends Component
{
    public int $index;

    public ?int $productId = null;

    public string $search = '';

    public bool $open = false;

    /** Restrict to products that track stock (e.g. stock documents). */
    public bool $stockOnly = false;

    public function mount(int $index, ?int $productId = null, bool $stockOnly = false): void
    {
        $this->index = $index;
        $this->productId = $productId;
        $this->stockOnly = $stockOnly;
        $this->search = $productId ? (Product::find($productId)?->label() ?? '') : '';
    }

    public function updatedSearch(): void
    {
        $this->open = trim($this->search) !== '';
    }

    public function pick(int $productId, ?int $unitId = null): void
    {
        $product = Product::findOrFail($productId);
        $this->productId = $product->id;
        $this->search = $product->label();
        $this->open = false;

        $this->dispatch('product-picked', index: $this->index, productId: $product->id, unitId: $unitId);
    }

    /**
     * Enter: an exact barcode wins, otherwise the first match.
     */
    public function pickFirst(ProductLookup $lookup): void
    {
        if ($scan = $lookup->byBarcode($this->search)) {
            $this->pick($scan['product']->id, $scan['unit']->unit_id);

            return;
        }

        $first = $this->query($lookup)->first();
        if ($first) {
            $this->pick($first->id);
        }
    }

    private function query(ProductLookup $lookup)
    {
        return $lookup->search($this->search)
            ->when($this->stockOnly, fn ($q) => $q->where('type', 'stockable'))
            ->orderBy('sku')
            ->limit(8);
    }

    public function render(ProductLookup $lookup)
    {
        return view('products::livewire.picker', [
            'results' => $this->open ? $this->query($lookup)->get() : collect(),
        ]);
    }
}
