<?php

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Modules\Core\Backups\BackupDatabase;
use RuntimeException;

class BackupCommand extends Command
{
    protected $signature = 'erp:backup';

    protected $description = 'Back up the database to storage/app/backups (gzipped mysqldump); schedule it daily on each installation';

    public function handle(BackupDatabase $backup): int
    {
        try {
            $this->components->info(__('core::backups.created', ['file' => $backup->handle()]));
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
