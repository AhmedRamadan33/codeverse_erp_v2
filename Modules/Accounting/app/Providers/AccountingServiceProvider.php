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
        $menu->add(new MenuItem('accounting', 'accounting::menu.receipts', 'accounting.receipts.index', 'bi-box-arrow-in-down', 'accounting.vouchers.view', 30));
        $menu->add(new MenuItem('accounting', 'accounting::menu.payments', 'accounting.payments.index', 'bi-box-arrow-up', 'accounting.vouchers.view', 40));
        $menu->add(new MenuItem('accounting', 'accounting::expenses.title', 'accounting.expenses.index', 'bi-receipt', 'accounting.vouchers.view', 50));

        $menu->group('accounting_reports', 'accounting::menu.reports', 'bi-bar-chart', order: 60);
        $menu->add(new MenuItem('accounting_reports', 'accounting::menu.trial_balance', 'accounting.reports.trial-balance', 'bi-table', 'accounting.reports.view', 10));
        $menu->add(new MenuItem('accounting_reports', 'accounting::menu.general_ledger', 'accounting.reports.general-ledger', 'bi-book', 'accounting.reports.view', 20));
        $menu->add(new MenuItem('accounting_reports', 'accounting::menu.partner_statement', 'accounting.reports.partner-statement', 'bi-person-vcard', 'accounting.reports.view', 30));
        $menu->add(new MenuItem('accounting_reports', 'accounting::menu.aged_balances', 'accounting.reports.aged-balances', 'bi-hourglass-split', 'accounting.reports.view', 40));

        $menu->group('accounting_settings', 'accounting::menu.accounting_settings', 'bi-sliders', order: 800);
        $menu->add(new MenuItem('accounting_settings', 'accounting::menu.fiscal_years', 'accounting.fiscal-years.index', 'bi-calendar3', 'accounting.fiscal_years.manage', 10));
        $menu->add(new MenuItem('accounting_settings', 'accounting::menu.mappings', 'accounting.mappings.index', 'bi-signpost-split', 'accounting.mappings.manage', 20));
        $menu->add(new MenuItem('accounting_settings', 'accounting::menu.taxes', 'accounting.taxes.index', 'bi-percent', 'accounting.taxes.manage', 30));
        $menu->add(new MenuItem('accounting_settings', 'accounting::menu.payment_methods', 'accounting.payment-methods.index', 'bi-credit-card', 'accounting.payment_methods.manage', 40));
    }
}
