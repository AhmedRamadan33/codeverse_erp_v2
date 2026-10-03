<?php

namespace Modules\Sales\Livewire\PriceLists;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Modules\Core\Models\Partner;
use Modules\Products\Models\Product;
use Modules\Sales\Actions\PriceListActions;
use Modules\Sales\Models\CustomerProfile;
use Modules\Sales\Models\PriceList;
use Modules\Sales\Pricing\PriceResolver;

/**
 * A price list's prices, and the customers who buy at them.
 */
#[Layout('core::layouts.app')]
class Form extends Component
{
    public ?int $listId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public ?int $customerToAssign = null;

    public function mount(?int $id = null): void
    {
        Gate::authorize('sales.price_lists.manage');

        if ($id === null) {
            $this->form = ['name_ar' => '', 'name_en' => '', 'is_active' => true, 'items' => [$this->blankItem()]];

            return;
        }

        $list = PriceList::with('items')->findOrFail($id);
        $this->listId = $list->id;
        $this->form = [
            'name_ar' => $list->getTranslation('name', 'ar', false),
            'name_en' => $list->getTranslation('name', 'en', false),
            'is_active' => $list->is_active,
            'items' => $list->items->map(fn ($item) => [
                'product_id' => $item->product_id, 'unit_id' => $item->unit_id, 'price' => (string) $item->price->strippedOfTrailingZeros(),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blankItem(): array
    {
        return ['product_id' => null, 'unit_id' => null, 'price' => ''];
    }

    public function addItem(): void
    {
        $this->form['items'][] = $this->blankItem();
    }

    public function removeItem(int $i): void
    {
        unset($this->form['items'][$i]);
        $this->form['items'] = array_values($this->form['items']);
    }

    /**
     * From the product picker: start from the product's own price for the unit.
     */
    #[On('product-picked')]
    public function productPicked(int $index, int $productId, ?int $unitId = null): void
    {
        $product = Product::with('units')->findOrFail($productId);
        $unitId ??= $product->units->firstWhere('is_default_sale', true)?->unit_id ?? $product->base_unit_id;

        $this->form['items'][$index] = [
            'product_id' => $product->id,
            'unit_id' => $unitId,
            'price' => (string) app(PriceResolver::class)->price($product, $unitId)->strippedOfTrailingZeros(),
        ];
    }

    public function save(PriceListActions $actions): void
    {
        $data = $this->form;
        $data['items'] = array_values(array_filter($data['items'], fn ($item) => ! empty($item['product_id'])));
        $data = validator($data, PriceListActions::rules())->validate();

        $list = $actions->save(auth()->user(), $data, $this->listId ? PriceList::findOrFail($this->listId) : null);

        session()->flash('status', __('core::ui.saved'));
        $this->redirectRoute('sales.price-lists.edit', $list->id);
    }

    public function assign(PriceListActions $actions): void
    {
        $this->validate(['customerToAssign' => ['required', 'integer']]);
        $customer = Partner::visibleTo(auth()->user())->customers()->findOrFail($this->customerToAssign);

        $actions->assign(auth()->user(), $customer, $this->listId);
        $this->customerToAssign = null;
    }

    public function unassign(PriceListActions $actions, int $partnerId): void
    {
        $actions->assign(auth()->user(), Partner::visibleTo(auth()->user())->findOrFail($partnerId), null);
    }

    public function render()
    {
        $assigned = $this->listId
            ? Partner::whereIn('id', CustomerProfile::where('price_list_id', $this->listId)->select('partner_id'))->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('sales::livewire.price-lists.form', [
            'products' => Product::with('units.unit')->whereKey(array_filter(array_column($this->form['items'], 'product_id')))->get()->keyBy('id'),
            'assigned' => $assigned,
            'customers' => $this->listId
                ? Partner::visibleTo(auth()->user())->customers()->where('is_active', true)->whereNotIn('id', $assigned->pluck('id'))->orderBy('name')->get(['id', 'name'])
                : collect(),
        ])->title($this->listId ? __('sales::price_lists.edit') : __('sales::price_lists.new'));
    }
}
