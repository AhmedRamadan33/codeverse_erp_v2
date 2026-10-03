<?php

namespace Modules\Products;

use Modules\Core\Modules\ModuleInstaller;
use Modules\Products\Models\Unit;

class ProductsInstaller implements ModuleInstaller
{
    public function install(): void
    {
        foreach (require module_path('Products', 'database/data/units.php') as $unit) {
            if (! Unit::where('name->en', $unit['name']['en'])->exists()) {
                Unit::create($unit);
            }
        }
    }

    public function upgrade(string $fromVersion, string $toVersion): void
    {
        //
    }
}
