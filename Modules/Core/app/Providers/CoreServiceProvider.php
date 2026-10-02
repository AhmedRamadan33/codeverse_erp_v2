<?php

namespace Modules\Core\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Modules\Core\Console\DisableModuleCommand;
use Modules\Core\Console\EnableModuleCommand;
use Modules\Core\Console\InstallCommand;
use Modules\Core\Console\UpgradeCommand;
use Modules\Core\Currencies\Currencies;
use Modules\Core\Menu\Menu;
use Modules\Core\Menu\MenuItem;
use Modules\Core\Modules\ModuleManager;
use Modules\Core\Settings\Settings;
use Modules\Core\Support\ErpModuleServiceProvider;
use Spatie\Translatable\Facades\Translatable;
use Throwable;

class CoreServiceProvider extends ErpModuleServiceProvider
{
    protected string $name = 'Core';

    protected string $nameLower = 'core';

    /**
     * @var string[]
     */
    protected array $commands = [
        InstallCommand::class,
        EnableModuleCommand::class,
        DisableModuleCommand::class,
        UpgradeCommand::class,
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

        $this->app->singleton(ModuleManager::class);
        $this->app->singleton(Settings::class);
        $this->app->singleton(Currencies::class);
        $this->app->singleton(Menu::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Arabic is the required translation of master data; show it when a locale is missing.
        Translatable::fallback(fallbackLocale: 'ar', fallbackAny: true);

        // Super admins pass every permission check; inactive users pass none.
        Gate::before(function (User $user) {
            if (! $user->is_active) {
                return false;
            }

            return $user->isSuperAdmin() ? true : null;
        });

        View::composer('core::layouts.*', function ($view) {
            try {
                $view->with('companyName', $this->app->make(Settings::class)->get('core.company_name'));
            } catch (Throwable) {
                // Not installed yet: the layout falls back to the app name.
            }
        });

        $this->registerMenu($this->app->make(Menu::class));
    }

    private function registerMenu(Menu $menu): void
    {
        $menu->group('contacts', 'core::menu.contacts', 'bi-people', order: 10);
        $menu->group('administration', 'core::menu.administration', 'bi-gear', order: 900);

        $menu->add(new MenuItem('contacts', 'core::menu.partners', 'core.partners.index', 'bi-person-lines-fill', 'core.partners.view', 10));

        $menu->add(new MenuItem('administration', 'core::menu.settings', 'core.settings.edit', 'bi-building', 'core.settings.manage', 10));
        $menu->add(new MenuItem('administration', 'core::menu.branches', 'core.branches.index', 'bi-diagram-3', 'core.branches.view', 20));
        $menu->add(new MenuItem('administration', 'core::menu.users', 'core.users.index', 'bi-person-badge', 'core.users.view', 30));
        $menu->add(new MenuItem('administration', 'core::menu.roles', 'core.roles.index', 'bi-shield-lock', 'core.roles.manage', 40));
        $menu->add(new MenuItem('administration', 'core::menu.currencies', 'core.currencies.index', 'bi-currency-exchange', 'core.currencies.manage', 50));
        $menu->add(new MenuItem('administration', 'core::menu.exchange_rates', 'core.exchange-rates.index', 'bi-graph-up', 'core.exchange_rates.manage', 60));
        $menu->add(new MenuItem('administration', 'core::menu.sequences', 'core.sequences.index', 'bi-123', 'core.sequences.manage', 70));
        $menu->add(new MenuItem('administration', 'core::menu.audit', 'core.audit.index', 'bi-clock-history', 'core.audit.view', 80));
        $menu->add(new MenuItem('administration', 'core::menu.modules', 'core.modules.index', 'bi-puzzle', 'core.modules.manage', 90));
    }
}
