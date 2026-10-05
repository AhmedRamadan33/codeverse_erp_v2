<?php

namespace Modules\Pos\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Core\Menu\Menu;
use Modules\Core\Menu\MenuItem;
use Modules\Core\Support\ErpModuleServiceProvider;
use Modules\Pos\Models\ReceiptLine;
use Modules\Pos\Printing\ReceiptPrintExtras;
use Modules\Products\Models\Product;
use Modules\Products\Support\ProductUsage;
use Modules\Sales\Reports\SalesLineSources;

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

    public function register(): void
    {
        parent::register();

        $this->app->singleton(ReceiptPrintExtras::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(ProductUsage::class)->register(fn (Product $product) => ReceiptLine::where('product_id', $product->id)->exists());

        // POS receipts in the sales reports (base currency only; returns negative).
        $this->app->make(SalesLineSources::class)->register('pos', fn (CarbonImmutable $from, CarbonImmutable $to, ?int $branchId) => DB::table('pos_receipt_lines as l')
            ->join('pos_receipts as r', 'r.id', '=', 'l.receipt_id')
            ->whereNotNull('r.number')
            ->whereBetween('r.date', [$from->toDateString(), $to->toDateString()])
            ->when($branchId, fn ($q) => $q->where('r.branch_id', $branchId))
            ->selectRaw("'pos' as channel, r.date, r.branch_id, r.partner_id, l.product_id, "
                ."l.base_quantity * case r.kind when 'return' then -1 else 1 end as quantity, "
                ."l.net * case r.kind when 'return' then -1 else 1 end as net, "
                ."l.cost * case r.kind when 'return' then -1 else 1 end as cost"));

        $menu = $this->app->make(Menu::class);
        $menu->group('pos', 'pos::menu.pos', 'bi-shop', order: 14);
        $menu->add(new MenuItem('pos', 'pos::menu.terminal', 'pos.terminal', 'bi-upc-scan', 'pos.terminal.sell', 10));
        $menu->add(new MenuItem('pos', 'pos::menu.receipts', 'pos.receipts.index', 'bi-receipt-cutoff', 'pos.receipts.view', 20));
        $menu->add(new MenuItem('pos', 'pos::menu.shifts', 'pos.shifts.index', 'bi-clock-history', 'pos.shifts.view', 30));
        $menu->add(new MenuItem('pos', 'pos::menu.registers', 'pos.registers.index', 'bi-pc-display-horizontal', 'pos.registers.manage', 40));
    }
}
