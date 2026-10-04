<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Modules\Core\Backups\BackupDatabase;
use RuntimeException;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('framework/testing/backups');
        File::deleteDirectory($this->directory);
        config(['core.backup.directory' => $this->directory, 'core.backup.keep' => 2]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    /**
     * mysqldump stand-in: writes its --result-file like the real one.
     */
    private function fakeDump(): void
    {
        Process::fake(function (PendingProcess $process) {
            $file = substr(collect($process->command)->first(fn ($arg) => str_starts_with($arg, '--result-file=')), strlen('--result-file='));
            file_put_contents($file, "-- dump\nCREATE TABLE t (id int);\n");

            return Process::result();
        });
    }

    public function test_a_backup_is_a_gzipped_consistent_dump_and_old_ones_are_pruned(): void
    {
        $this->fakeDump();

        $file = app(BackupDatabase::class)->handle();

        $this->assertStringEndsWith('-manual.sql.gz', $file);
        $this->assertStringContainsString('CREATE TABLE t', gzdecode(file_get_contents($file)));
        $this->assertSame([], glob("{$this->directory}/*.sql"));
        Process::assertRan(fn (PendingProcess $p) => in_array('--single-transaction', $p->command, true)
            // The password travels in the environment, never as a visible argument.
            && array_key_exists('MYSQL_PWD', $p->environment)
            && ! collect($p->command)->contains(fn ($arg) => str_starts_with($arg, '--password') || $arg === '-p'));

        // Keep the two newest.
        foreach (['20200101-000000', '20210101-000000'] as $stamp) {
            touch("{$this->directory}/".DB::connection()->getDatabaseName()."-{$stamp}-manual.sql.gz");
        }
        app(BackupDatabase::class)->handle();
        $this->assertCount(2, glob("{$this->directory}/*.sql.gz"));
    }

    public function test_a_failed_dump_leaves_nothing_and_stops_the_upgrade_before_any_change(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'Access denied', exitCode: 2)]);

        try {
            app(BackupDatabase::class)->handle();
            $this->fail('A failed dump was reported as a backup.');
        } catch (RuntimeException $e) {
            $this->assertSame('Access denied', $e->getMessage());
        }
        $this->assertSame([], glob("{$this->directory}/*"));

        $this->artisan('erp:upgrade')->assertFailed();
        $this->assertFalse(app()->isDownForMaintenance());
    }
}
