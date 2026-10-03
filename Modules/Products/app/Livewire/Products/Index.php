<?php

namespace Modules\Products\Livewire\Products;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Products\Enums\ProductType;
use Modules\Products\Models\Product;
use Modules\Products\Models\ProductCategory;
use Modules\Products\Support\ProductLookup;

#[Layout('core::layouts.app')]
class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $categoryId = null;

    #[Url]
    public string $type = '';

    #[Url]
    public bool $showInactive = false;

    public function mount(): void
    {
        Gate::authorize('products.products.view');
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'categoryId', 'type', 'showInactive'], true)) {
            $this->resetPage();
        }
    }

    public function render(ProductLookup $lookup)
    {
        $products = $lookup->search($this->search, activeOnly: ! $this->showInactive)
            ->with(['category', 'baseUnit'])
            ->when($this->categoryId, fn ($q, $id) => $q->where('category_id', $id))
            ->when($this->type !== '', fn ($q) => $q->where('type', $this->type))
            ->orderBy('sku')
            ->paginate(30);

        return view('products::livewire.products.index', [
            'products' => $products,
            'categories' => ProductCategory::orderBy('id')->get(),
            'types' => ProductType::cases(),
        ])->title(__('products::products.title'));
    }
}
