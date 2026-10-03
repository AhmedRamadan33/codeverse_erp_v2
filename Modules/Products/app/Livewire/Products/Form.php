<?php

namespace Modules\Products\Livewire\Products;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Accounting\Enums\TaxScope;
use Modules\Accounting\Models\Tax;
use Modules\Products\Actions\SaveProduct;
use Modules\Products\Enums\ProductType;
use Modules\Products\Enums\Tracking;
use Modules\Products\Models\Product;
use Modules\Products\Models\ProductCategory;
use Modules\Products\Models\ProductUnit;
use Modules\Products\Models\Unit;

#[Layout('core::layouts.app')]
class Form extends Component
{
    public ?int $productId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(?int $id = null): void
    {
        if ($id === null) {
            Gate::authorize('products.products.create');

            $this->form = [
                'sku' => '', 'name_ar' => '', 'name_en' => '', 'category_id' => null,
                'type' => ProductType::Stockable->value, 'tracking' => Tracking::None->value,
                'base_unit_id' => Unit::where('is_active', true)->value('id'),
                'sale_price' => '', 'purchase_price' => '', 'sale_tax_id' => null, 'purchase_tax_id' => null,
                'description' => '', 'is_active' => true,
                'base_barcodes' => '', 'units' => [],
                'default_sale_unit_id' => null, 'default_purchase_unit_id' => null,
            ];

            return;
        }

        Gate::authorize('products.products.update');
        $product = Product::with(['units.barcodes'])->findOrFail($id);
        $this->productId = $product->id;

        $base = $product->units->firstWhere('unit_id', $product->base_unit_id);
        $others = $product->units->where('unit_id', '!=', $product->base_unit_id);

        $this->form = [
            'sku' => $product->sku,
            'name_ar' => $product->getTranslation('name', 'ar', false),
            'name_en' => $product->getTranslation('name', 'en', false),
            'category_id' => $product->category_id,
            'type' => $product->type->value,
            'tracking' => $product->tracking->value,
            'base_unit_id' => $product->base_unit_id,
            'sale_price' => (string) $product->sale_price->strippedOfTrailingZeros(),
            'purchase_price' => (string) $product->purchase_price->strippedOfTrailingZeros(),
            'sale_tax_id' => $product->sale_tax_id,
            'purchase_tax_id' => $product->purchase_tax_id,
            'description' => $product->description,
            'is_active' => $product->is_active,
            'base_barcodes' => $base?->barcodes->pluck('barcode')->implode(', ') ?? '',
            'units' => $others->map(fn (ProductUnit $u) => [
                'unit_id' => $u->unit_id,
                'factor' => (string) $u->factor->strippedOfTrailingZeros(),
                'sale_price' => $u->sale_price ? (string) $u->sale_price->strippedOfTrailingZeros() : '',
                'barcodes' => $u->barcodes->pluck('barcode')->implode(', '),
            ])->values()->all(),
            'default_sale_unit_id' => $product->units->firstWhere('is_default_sale', true)?->unit_id,
            'default_purchase_unit_id' => $product->units->firstWhere('is_default_purchase', true)?->unit_id,
        ];
    }

    public function addUnit(): void
    {
        $this->form['units'][] = ['unit_id' => null, 'factor' => '', 'sale_price' => '', 'barcodes' => ''];
    }

    public function removeUnit(int $i): void
    {
        unset($this->form['units'][$i]);
        $this->form['units'] = array_values($this->form['units']);
    }

    /**
     * Barcodes are typed comma separated; the action takes lists.
     *
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $split = fn (?string $value) => array_values(array_filter(array_map('trim', explode(',', (string) $value))));

        $data = $this->form;
        $data['base_barcodes'] = $split($data['base_barcodes']);
        $data['units'] = array_map(fn ($u) => ['barcodes' => $split($u['barcodes'] ?? '')] + $u, $data['units']);

        return $data;
    }

    public function save(SaveProduct $action): void
    {
        $product = $this->productId ? Product::findOrFail($this->productId) : null;
        $validated = validator($this->payload(), SaveProduct::rules($product))->validate();

        $action->handle(auth()->user(), $validated, $product);

        session()->flash('status', __('core::ui.saved'));
        $this->redirectRoute('products.products.index');
    }

    public function render()
    {
        return view('products::livewire.products.form', [
            'units' => Unit::where('is_active', true)->orderBy('id')->get(),
            'categories' => ProductCategory::where('is_active', true)->orderBy('id')->get(),
            'types' => ProductType::cases(),
            'trackings' => Tracking::cases(),
            'saleTaxes' => Tax::where('is_active', true)->where('scope', '!=', TaxScope::Purchases)->get(),
            'purchaseTaxes' => Tax::where('is_active', true)->where('scope', '!=', TaxScope::Sales)->get(),
        ])->title($this->productId ? __('products::products.edit') : __('products::products.new'));
    }
}
