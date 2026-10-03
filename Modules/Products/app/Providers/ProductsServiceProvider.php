<?php

namespace Modules\Products\Providers;

use Modules\Core\Menu\Menu;
use Modules\Core\Menu\MenuItem;
use Modules\Core\Support\ErpModuleServiceProvider;
use Modules\Products\Support\ProductUsage;

class ProductsServiceProvider extends ErpModuleServiceProvider
{
    protected string $name = 'Products';

    protected string $nameLower = 'products';

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

        $this->app->singleton(ProductUsage::class);
    }

    public function boot(): void
    {
        parent::boot();

        $menu = $this->app->make(Menu::class);
        $menu->group('products', 'products::menu.products', 'bi-box-seam', order: 20);
        $menu->add(new MenuItem('products', 'products::menu.list', 'products.products.index', 'bi-boxes', 'products.products.view', 10));
        $menu->add(new MenuItem('products', 'products::menu.categories', 'products.categories.index', 'bi-diagram-3', 'products.catalog.manage', 20));
        $menu->add(new MenuItem('products', 'products::menu.units', 'products.units.index', 'bi-rulers', 'products.catalog.manage', 30));
    }
}
