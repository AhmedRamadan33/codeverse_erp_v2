<?php

namespace Modules\Core\Providers;

use Modules\Core\Console\DisableModuleCommand;
use Modules\Core\Console\EnableModuleCommand;
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
    }
}
