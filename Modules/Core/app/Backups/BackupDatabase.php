<?php

namespace Modules\Core\Backups;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Dumps the installation's database with mysqldump into a gzipped file, then removes the oldest
 * backups beyond the configured number. A consistent snapshot (--single-transaction) is taken
 * without locking InnoDB tables, so the system can stay up while it runs.
 */
class BackupDatabase
{
    /**
     * @return string the backup file path
     *
     * @throws RuntimeException when mysqldump fails; nothing is left behind
     */
    public function handle(string $reason = 'manual'): string
    {
        $connection = DB::connection();
        $config = $connection->getConfig();
        $directory = config('core.backup.directory');
        File::ensureDirectoryExists($directory);

        $name = sprintf('%s-%s-%s', $config['database'], now()->format('Ymd-His'), preg_replace('/[^a-z0-9-]/', '', strtolower($reason)));
        $sql = "{$directory}/{$name}.sql";

        $command = array_values(array_filter([
            config('core.backup.mysqldump'),
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--user='.$config['username'],
            '--single-transaction',
            '--routines',
            '--triggers',
            '--default-character-set=utf8mb4',
            '--result-file='.$sql,
            $config['database'],
        ]));

        // The password goes through the environment, not the command line other users can see.
        $result = Process::env(['MYSQL_PWD' => (string) $config['password']])->timeout(3600)->run($command);

        if ($result->failed() || ! is_file($sql)) {
            File::delete($sql);

            throw new RuntimeException(trim($result->errorOutput()) ?: __('core::backups.failed'));
        }

        $gzip = $this->compress($sql);
        $this->prune($directory);

        return $gzip;
    }

    private function compress(string $sql): string
    {
        $target = "{$sql}.gz";
        $in = fopen($sql, 'rb');
        $out = gzopen($target, 'wb6');

        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1 << 20));
        }

        fclose($in);
        gzclose($out);
        File::delete($sql);

        return $target;
    }

    private function prune(string $directory): void
    {
        $keep = (int) config('core.backup.keep');
        if ($keep <= 0) {
            return;
        }

        // Names start with the database name and a sortable timestamp.
        $files = collect(File::glob("{$directory}/*.sql.gz"))->sortDesc()->values();
        $files->slice($keep)->each(fn (string $f) => File::delete($f));
    }
}
