<?php

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Modules\Core\Modules\ModuleException;
use Modules\Core\Modules\ModuleManager;

class EnableModuleCommand extends Command
{
    protected $signature = 'erp:module:enable {name : The module name, e.g. Sales}';

    protected $description = 'Install (if needed) and enable a module on this installation';

    public function handle(ModuleManager $manager): int
    {
        try {
            $manager->enable($this->argument('name'));
        } catch (ModuleException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(__('core::modules.enabled', ['module' => $this->argument('name')]));

        return self::SUCCESS;
    }
}
