<?php

namespace Modules\Sales\Providers;

use Modules\Core\Menu\Menu;
use Modules\Core\Menu\MenuItem;
use Modules\Core\Support\ErpModuleServiceProvider;
use Modules\Products\Models\Product;
use Modules\Products\Support\ProductUsage;
use Modules\Sales\Models\SalesInvoiceLine;

class SalesServiceProvider extends ErpModuleServiceProvider
{
    protected string $name = 'Sales';

    protected string $nameLower = 'sales';

    /**
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        $this->app->make(ProductUsage::class)->register(fn (Product $product) => SalesInvoiceLine::where('product_id', $product->id)->exists());

        $menu = $this->app->make(Menu::class);
        $menu->group('sales', 'sales::menu.sales', 'bi-bag-check', order: 15);
        $menu->add(new MenuItem('sales', 'sales::menu.invoices', 'sales.invoices.index', 'bi-receipt', 'sales.invoices.view', 10));
        $menu->add(new MenuItem('sales', 'sales::menu.returns', 'sales.returns.index', 'bi-arrow-return-left', 'sales.returns.view', 20));
        $menu->add(new MenuItem('sales', 'sales::menu.price_lists', 'sales.price-lists.index', 'bi-tags', 'sales.price_lists.manage', 30));
    }
}
