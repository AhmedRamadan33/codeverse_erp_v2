<?php

namespace Modules\Sales\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Core\Documents\DocumentStatus;
use Modules\Core\Menu\Menu;
use Modules\Core\Menu\MenuItem;
use Modules\Core\Support\ErpModuleServiceProvider;
use Modules\Products\Models\Product;
use Modules\Products\Support\ProductUsage;
use Modules\Sales\Models\SalesInvoiceLine;
use Modules\Sales\Reports\SalesLineSources;

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

    public function register(): void
    {
        parent::register();

        $this->app->singleton(SalesLineSources::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(ProductUsage::class)->register(fn (Product $product) => SalesInvoiceLine::where('product_id', $product->id)->exists());
        $this->registerReportSources($this->app->make(SalesLineSources::class));

        $menu = $this->app->make(Menu::class);
        $menu->group('sales', 'sales::menu.sales', 'bi-bag-check', order: 15);
        $menu->add(new MenuItem('sales', 'sales::menu.invoices', 'sales.invoices.index', 'bi-receipt', 'sales.invoices.view', 10));
        $menu->add(new MenuItem('sales', 'sales::menu.returns', 'sales.returns.index', 'bi-arrow-return-left', 'sales.returns.view', 20));
        $menu->add(new MenuItem('sales', 'sales::menu.price_lists', 'sales.price-lists.index', 'bi-tags', 'sales.price_lists.manage', 30));
        $menu->add(new MenuItem('sales', 'sales::menu.report', 'sales.reports.analysis', 'bi-graph-up', 'sales.reports.view', 40));
    }

    /**
     * Posted invoices and returns, in base currency (amounts × the document's rate).
     */
    private function registerReportSources(SalesLineSources $sources): void
    {
        $lines = fn (string $lineTable, string $docTable, string $fk, int $sign) => fn (CarbonImmutable $from, CarbonImmutable $to, ?int $branchId) => DB::table("{$lineTable} as l")
            ->join("{$docTable} as d", 'd.id', '=', "l.{$fk}")
            ->where('d.status', DocumentStatus::Posted->value)
            ->whereBetween('d.date', [$from->toDateString(), $to->toDateString()])
            ->when($branchId, fn ($q) => $q->where('d.branch_id', $branchId))
            ->selectRaw("'invoices' as channel, d.date, d.branch_id, d.partner_id, l.product_id, l.base_quantity * {$sign} as quantity, round(l.net * d.exchange_rate, 4) * {$sign} as net, l.cost * {$sign} as cost");

        $sources->register('invoices', $lines('sales_invoice_lines', 'sales_invoices', 'sales_invoice_id', 1));
        $sources->register('returns', $lines('sales_return_lines', 'sales_returns', 'sales_return_id', -1));
    }
}
