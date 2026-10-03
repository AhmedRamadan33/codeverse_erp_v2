<?php

namespace Modules\Core\Tests\Fixtures;

use Illuminate\Support\ServiceProvider;

/**
 * Stands for a module's own config: only loaded once the module's providers are registered.
 */
class AlphaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config(['alpha.item_name' => 'seeded']);
    }
}
