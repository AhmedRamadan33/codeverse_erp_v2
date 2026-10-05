<?php

namespace Modules\EgyptTax\Livewire;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Accounting\Models\Tax;
use Modules\EgyptTax\Actions\SettingsActions;
use Modules\EgyptTax\Models\EtaItemCode;
use Modules\EgyptTax\Models\EtaTaxCode;
use Modules\EgyptTax\Models\EtaUnitCode;
use Modules\Products\Models\Product;
use Modules\Products\Models\Unit;

/**
 * ETA codes of products, units and taxes. Products without a code are listed first by default.
 */
#[Layout('core::layouts.app')]
class Codes extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url]
    public string $tab = 'items';

    #[Url]
    public string $search = '';

    #[Url]
    public bool $missingOnly = true;

    /** @var array<int, array{code_type: string, item_code: string}> product id => code */
    public array $items = [];

    /** @var array<int, string> unit id => ETA unit type */
    public array $units = [];

    /** @var array<int, array{tax_type: string, sub_type: string}> */
    public array $taxes = [];

    public function mount(): void
    {
        Gate::authorize('egypttax.settings.manage');

        $this->units = EtaUnitCode::pluck('unit_type', 'unit_id')->all();
        $this->taxes = EtaTaxCode::get()->mapWithKeys(fn ($c) => [$c->tax_id => ['tax_type' => $c->tax_type, 'sub_type' => $c->sub_type]])->all();
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'missingOnly', 'tab'], true)) {
            $this->resetPage();
        }
    }

    public function saveItem(int $productId, SettingsActions $actions): void
    {
        $row = $this->items[$productId] ?? [];
        $actions->saveItemCode(auth()->user(), $productId, $row['code_type'] ?? 'EGS', $row['item_code'] ?? null);
        session()->flash('status', __('core::ui.saved'));
    }

    public function saveUnit(int $unitId, SettingsActions $actions): void
    {
        $actions->saveUnitCode(auth()->user(), $unitId, $this->units[$unitId] ?? null);
        session()->flash('status', __('core::ui.saved'));
    }

    public function saveTax(int $taxId, SettingsActions $actions): void
    {
        $row = $this->taxes[$taxId] ?? [];
        $actions->saveTaxCode(auth()->user(), $taxId, $row['tax_type'] ?? null, $row['sub_type'] ?? null);
        session()->flash('status', __('core::ui.saved'));
    }

    public function render()
    {
        $products = null;
        if ($this->tab === 'items') {
            $products = Product::query()
                ->leftJoin('eta_item_codes as c', 'c.product_id', '=', 'products.id')
                ->select('products.*', 'c.code_type', 'c.item_code')
                ->where('products.is_active', true)
                ->when($this->missingOnly, fn ($q) => $q->whereNull('c.id'))
                ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                    ->where('products.sku', 'like', "%{$this->search}%")
                    ->orWhereRaw("json_unquote(json_extract(products.name, '$.ar')) like ?", ["%{$this->search}%"])
                    ->orWhereRaw("json_unquote(json_extract(products.name, '$.en')) like ?", ["%{$this->search}%"])
                    ->orWhere('c.item_code', 'like', "%{$this->search}%")))
                ->orderBy('products.sku')
                ->paginate(30);

            foreach ($products as $product) {
                $this->items[$product->id] ??= ['code_type' => $product->code_type ?? 'EGS', 'item_code' => (string) $product->item_code];
            }
        }

        return view('egypttax::livewire.codes', [
            'products' => $products,
            'unitList' => $this->tab === 'units' ? Unit::orderBy('id')->get() : collect(),
            'taxList' => $this->tab === 'taxes' ? Tax::orderBy('code')->get() : collect(),
            'missingCount' => Product::where('is_active', true)->whereNotIn('id', EtaItemCode::select('product_id'))->count(),
            'types' => EtaItemCode::TYPES,
        ])->title(__('egypttax::codes.title'));
    }
}
