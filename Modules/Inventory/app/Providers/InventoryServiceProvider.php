<?php

namespace Modules\Inventory\Providers;

use Modules\Core\Menu\Menu;
use Modules\Core\Menu\MenuItem;
use Modules\Core\Support\ErpModuleServiceProvider;
use Modules\Inventory\Console\CheckStockCommand;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Stock\StockEngine;
use Modules\Products\Models\Product;
use Modules\Products\Support\ProductUsage;

class InventoryServiceProvider extends ErpModuleServiceProvider
{
    protected string $name = 'Inventory';

    protected string $nameLower = 'inventory';

    /**
     * @var string[]
     */
    protected array $commands = [
        CheckStockCommand::class,
    ];

    /**
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        // One engine per request/job: the actions and their helpers share the locked cost rows.
        $this->app->scoped(StockEngine::class);
    }

    public function boot(): void
    {
        parent::boot();

        // A product with stock moves keeps its base unit, type and tracking.
        $this->app->make(ProductUsage::class)->register(fn (Product $product) => StockMove::where('product_id', $product->id)->exists());

        $menu = $this->app->make(Menu::class);
        $menu->group('inventory', 'inventory::menu.inventory', 'bi-building', order: 30);
        $menu->add(new MenuItem('inventory', 'inventory::menu.stock', 'inventory.stock.index', 'bi-clipboard-data', 'inventory.stock.view', 10));
        $menu->add(new MenuItem('inventory', 'inventory::menu.moves', 'inventory.moves.index', 'bi-arrow-left-right', 'inventory.stock.view', 20));
        $menu->add(new MenuItem('inventory', 'inventory::menu.adjustments', 'inventory.adjustments.index', 'bi-sliders2', 'inventory.adjustments.view', 30));
        $menu->add(new MenuItem('inventory', 'inventory::menu.transfers', 'inventory.transfers.index', 'bi-truck', 'inventory.transfers.view', 40));
        $menu->add(new MenuItem('inventory', 'inventory::menu.warehouses', 'inventory.warehouses.index', 'bi-houses', 'inventory.warehouses.manage', 50));
    }
}
