<?php

namespace Modules\Inventory\Providers;

use Modules\Core\Support\ErpModuleServiceProvider;

class InventoryServiceProvider extends ErpModuleServiceProvider
{
    protected string $name = 'Inventory';

    protected string $nameLower = 'inventory';

    /**
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
