<?php

namespace Modules\Core\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Console\DisableModuleCommand;
use Modules\Core\Currencies\Currencies;
use Modules\Core\Settings\Settings;
use Spatie\Translatable\Facades\Translatable;
use Modules\Core\Console\EnableModuleCommand;
use Modules\Core\Console\InstallCommand;
use Modules\Core\Console\UpgradeCommand;
use Modules\Core\Modules\ModuleManager;
use Modules\Core\Support\ErpModuleServiceProvider;

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
    }
}
