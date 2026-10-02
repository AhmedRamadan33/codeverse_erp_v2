<?php

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Modules\Core\Modules\ModuleException;
use Modules\Core\Modules\ModuleManager;

class DisableModuleCommand extends Command
{
    protected $signature = 'erp:module:disable {name : The module name, e.g. Sales}';

    protected $description = 'Disable a module on this installation (its data is kept)';

    public function handle(ModuleManager $manager): int
    {
        try {
            $manager->disable($this->argument('name'));
        } catch (ModuleException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(__('core::modules.disabled', ['module' => $this->argument('name')]));

        return self::SUCCESS;
    }
}
