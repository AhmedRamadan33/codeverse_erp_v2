<?php

namespace Modules\Core\Tests\Fixtures;

use Illuminate\Support\Facades\DB;
use Modules\Core\Modules\ModuleInstaller;

class AlphaInstaller implements ModuleInstaller
{
    /** @var array<int, array{string, string}> */
    public static array $upgrades = [];

    public function install(): void
    {
        // Reads the module's config, so enabling must load the module before installing it.
        DB::table('alpha_items')->insert(['name' => config('alpha.item_name')]);
    }

    public function upgrade(string $fromVersion, string $toVersion): void
    {
        static::$upgrades[] = [$fromVersion, $toVersion];
    }
}
