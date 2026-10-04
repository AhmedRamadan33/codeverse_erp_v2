<?php

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Modules\Core\Backups\BackupDatabase;
use Modules\Core\Modules\ModuleManager;
use RuntimeException;

class UpgradeCommand extends Command
{
    protected $signature = 'erp:upgrade
        {--no-backup : Skip the database backup (only when another backup was just taken)}';

    protected $description = 'Back up the database, then migrate enabled modules and run their upgrade hooks after deploying new code';

    public function handle(ModuleManager $manager, BackupDatabase $backup): int
    {
        $this->call('down', ['--retry' => 60]);

        try {
            // In maintenance mode, so nothing is written between the backup and the migrations.
            if (! $this->option('no-backup')) {
                try {
                    $this->components->info(__('core::backups.created', ['file' => $backup->handle('before-upgrade')]));
                } catch (RuntimeException $e) {
                    $this->components->error(__('core::backups.upgrade_aborted', ['error' => $e->getMessage()]));

                    return self::FAILURE;
                }
            }

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
