<?php

namespace Modules\Purchases\Providers;

use Modules\Core\Menu\Menu;
use Modules\Core\Menu\MenuItem;
use Modules\Core\Support\ErpModuleServiceProvider;
use Modules\Products\Models\Product;
use Modules\Products\Support\ProductUsage;
use Modules\Purchases\Models\PurchaseInvoiceLine;

class PurchasesServiceProvider extends ErpModuleServiceProvider
{
    protected string $name = 'Purchases';

    protected string $nameLower = 'purchases';

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

        // Invoice lines keep their unit and base quantity, so the product's base unit must stay.
        $this->app->make(ProductUsage::class)->register(fn (Product $product) => PurchaseInvoiceLine::where('product_id', $product->id)->exists());

        $menu = $this->app->make(Menu::class);
        $menu->group('purchases', 'purchases::menu.purchases', 'bi-cart-plus', order: 35);
        $menu->add(new MenuItem('purchases', 'purchases::menu.invoices', 'purchases.invoices.index', 'bi-receipt-cutoff', 'purchases.invoices.view', 10));
        $menu->add(new MenuItem('purchases', 'purchases::menu.returns', 'purchases.returns.index', 'bi-arrow-return-left', 'purchases.returns.view', 20));
        $menu->add(new MenuItem('purchases', 'purchases::menu.report', 'purchases.reports.analysis', 'bi-graph-up', 'purchases.reports.view', 30));
    }
}
