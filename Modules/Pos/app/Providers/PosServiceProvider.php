<?php

namespace Modules\Pos\Providers;

use Modules\Core\Menu\Menu;
use Modules\Core\Menu\MenuItem;
use Modules\Core\Support\ErpModuleServiceProvider;
use Modules\Pos\Models\ReceiptLine;
use Modules\Products\Models\Product;
use Modules\Products\Support\ProductUsage;

class PosServiceProvider extends ErpModuleServiceProvider
{
    protected string $name = 'Pos';

    protected string $nameLower = 'pos';

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

        $this->app->make(ProductUsage::class)->register(fn (Product $product) => ReceiptLine::where('product_id', $product->id)->exists());

        $menu = $this->app->make(Menu::class);
        $menu->group('pos', 'pos::menu.pos', 'bi-shop', order: 14);
        $menu->add(new MenuItem('pos', 'pos::menu.terminal', 'pos.terminal', 'bi-upc-scan', 'pos.terminal.sell', 10));
        $menu->add(new MenuItem('pos', 'pos::menu.receipts', 'pos.receipts.index', 'bi-receipt-cutoff', 'pos.receipts.view', 20));
        $menu->add(new MenuItem('pos', 'pos::menu.shifts', 'pos.shifts.index', 'bi-clock-history', 'pos.shifts.view', 30));
        $menu->add(new MenuItem('pos', 'pos::menu.registers', 'pos.registers.index', 'bi-pc-display-horizontal', 'pos.registers.manage', 40));
    }
}
