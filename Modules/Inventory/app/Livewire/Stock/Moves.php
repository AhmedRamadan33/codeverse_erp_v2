<?php

namespace Modules\Inventory\Livewire\Stock;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\Warehouse;
use Modules\Products\Models\Product;

/**
 * Item ledger ("كارت الصنف"): every move of a product with a running quantity.
 */
#[Layout('core::layouts.app')]
class Moves extends Component
{
    #[Url]
    public ?int $productId = null;

    #[Url]
    public ?int $warehouseId = null;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        Gate::authorize('inventory.stock.view');

        $this->from = $this->from ?: now()->startOfYear()->toDateString();
        $this->to = $this->to ?: now()->toDateString();
    }

    public function render()
    {
        $user = auth()->user();
        $warehouses = Warehouse::query()
            ->when(! $user->can('core.branches.all_access'), fn ($q) => $q->whereIn('branch_id', $user->branches()->select('branches.id')))
            ->orderBy('code')->get();
        $warehouseIds = $this->warehouseId ? [$this->warehouseId] : $warehouses->pluck('id')->all();

        $product = $this->productId ? Product::with('baseUnit')->find($this->productId) : null;
        $opening = BigDecimal::zero();
        $rows = collect();

        if ($product) {
            $scope = fn ($q) => $q->where('product_id', $product->id)->whereIn('warehouse_id', $warehouseIds);

            $opening = BigDecimal::of(StockMove::query()->tap($scope)->whereDate('date', '<', $this->from)->sum('quantity') ?: 0);
            $balance = $opening;

            $rows = StockMove::query()->tap($scope)
                ->with(['warehouse', 'batch'])
                ->whereBetween('date', [$this->from, $this->to])
                ->orderBy('date')->orderBy('id')
                ->get()
                ->map(function (StockMove $move) use (&$balance) {
                    $balance = $balance->plus($move->quantity);

                    return ['move' => $move, 'balance' => $balance];
                });
        }

        return view('inventory::livewire.stock.moves', [
            'product' => $product,
            'opening' => $opening,
            'rows' => $rows,
            'warehouses' => $warehouses,
            'products' => Product::where('type', 'stockable')->orderBy('sku')->get(['id', 'sku', 'name']),
        ])->title(__('inventory::stock.moves'));
    }
}
