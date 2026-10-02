<?php

namespace Modules\Accounting\Providers;

use Modules\Accounting\Mappings\AccountResolver;
use Modules\Core\Menu\Menu;
use Modules\Core\Menu\MenuItem;
use Modules\Core\Support\ErpModuleServiceProvider;

class AccountingServiceProvider extends ErpModuleServiceProvider
{
    protected string $name = 'Accounting';

    protected string $nameLower = 'accounting';

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

        $this->app->scoped(AccountResolver::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerMenu($this->app->make(Menu::class));
    }

    private function registerMenu(Menu $menu): void
    {
        $menu->group('accounting', 'accounting::menu.accounting', 'bi-journal-text', order: 50);
        $menu->add(new MenuItem('accounting', 'accounting::menu.accounts', 'accounting.accounts.index', 'bi-diagram-2', 'accounting.accounts.view', 10));
        $menu->add(new MenuItem('accounting', 'accounting::menu.entries', 'accounting.entries.index', 'bi-journal-plus', 'accounting.entries.view', 20));

        $menu->group('accounting_settings', 'accounting::menu.accounting_settings', 'bi-sliders', order: 800);
        $menu->add(new MenuItem('accounting_settings', 'accounting::menu.fiscal_years', 'accounting.fiscal-years.index', 'bi-calendar3', 'accounting.fiscal_years.manage', 10));
        $menu->add(new MenuItem('accounting_settings', 'accounting::menu.mappings', 'accounting.mappings.index', 'bi-signpost-split', 'accounting.mappings.manage', 20));
        $menu->add(new MenuItem('accounting_settings', 'accounting::menu.taxes', 'accounting.taxes.index', 'bi-percent', 'accounting.taxes.manage', 30));
        $menu->add(new MenuItem('accounting_settings', 'accounting::menu.payment_methods', 'accounting.payment-methods.index', 'bi-credit-card', 'accounting.payment_methods.manage', 40));
    }
}
