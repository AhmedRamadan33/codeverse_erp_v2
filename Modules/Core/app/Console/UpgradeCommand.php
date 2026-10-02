<?php

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Modules\Core\Modules\ModuleManager;

class UpgradeCommand extends Command
{
    protected $signature = 'erp:upgrade';

    protected $description = 'Migrate enabled modules and run their upgrade hooks after deploying new code';

    public function handle(ModuleManager $manager): int
    {
        // Back up the database before running this in production (docs/architecture/core-design.md §11.2).
        $this->call('down', ['--retry' => 60]);

        try {
            $upgraded = $manager->upgrade();
            $this->call('optimize:clear');
        } finally {
            $this->call('up');
        }

        if ($upgraded === []) {
            $this->components->info(__('core::modules.nothing_to_upgrade'));
        }

        foreach ($upgraded as $module => $versions) {
            $this->components->info(__('core::modules.upgraded', ['module' => $module] + $versions));
        }

        return self::SUCCESS;
    }
}
