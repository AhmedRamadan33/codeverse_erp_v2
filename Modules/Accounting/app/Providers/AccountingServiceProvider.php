<?php

namespace Modules\Accounting\Providers;

use Modules\Accounting\Mappings\AccountResolver;
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
}
