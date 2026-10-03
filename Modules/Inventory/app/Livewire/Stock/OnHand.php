<?php

namespace Modules\Inventory\Livewire\Stock;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Inventory\Models\ProductCost;
use Modules\Inventory\Models\StockBalance;
use Modules\Inventory\Models\Warehouse;

#[Layout('core::layouts.app')]
class OnHand extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $warehouseId = null;

    public function mount(): void
    {
        Gate::authorize('inventory.stock.view');
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'warehouseId'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Warehouses of the branches the user may see.
     */
    private function warehouses()
    {
        $user = auth()->user();

        return Warehouse::query()
            ->when(! $user->can('core.branches.all_access'), fn ($q) => $q->whereIn('branch_id', $user->branches()->select('branches.id')))
            ->orderBy('code')->get();
    }

    public function render()
    {
        $warehouses = $this->warehouses();
        $term = trim($this->search);

        $balances = StockBalance::query()
            ->with(['product.baseUnit', 'warehouse', 'batch'])
            ->where('quantity', '!=', 0)
            ->whereIn('warehouse_id', $this->warehouseId ? [$this->warehouseId] : $warehouses->pluck('id'))
            ->when($term !== '', fn ($q) => $q->whereHas('product', fn ($p) => $p->where(fn ($p) => $p
                ->where('sku', 'like', "%{$term}%")
                ->orWhereRaw("json_unquote(json_extract(name, '$.ar')) like ?", ["%{$term}%"])
                ->orWhereRaw("json_unquote(json_extract(name, '$.en')) like ?", ["%{$term}%"]))))
            ->orderBy('product_id')->orderBy('warehouse_id')
            ->paginate(50);

        return view('inventory::livewire.stock.on-hand', [
            'balances' => $balances,
            'costs' => ProductCost::whereIn('product_id', $balances->pluck('product_id'))->get()->keyBy('product_id'),
            'warehouses' => $warehouses,
        ])->title(__('inventory::stock.on_hand'));
    }
}
