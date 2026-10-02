<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Support\TransactionGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Modules\Core\Models\InstalledModule;
use Modules\Core\Modules\DatabaseActivator;
use Modules\Core\Modules\ModuleException;
use Modules\Core\Modules\ModuleManager;
use Modules\Core\Permissions\PermissionSynchronizer;
use Modules\Core\Tests\Fixtures\AlphaInstaller;
use Nwidart\Modules\Laravel\LaravelFileRepository;
use Tests\TestCase;

/**
 * Enabling a module runs DDL, which commits implicitly on MySQL, so these tests cannot run
 * inside the usual test transaction. They use the shared migrated database without one and
 * undo what the fixture modules created in tearDown (much faster than re-migrating per test).
 */
class ModuleManagerTest extends TestCase
{
    use RefreshDatabase;

    private DatabaseActivator $activator;

    private ModuleManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'modules.activators.database.enable-all' => false,
            'modules.activators.database.cache-file' => null,
        ]);

        $this->activator = new DatabaseActivator($this->app);
        $this->app->instance(\Nwidart\Modules\Contracts\ActivatorInterface::class, $this->activator);

        $repository = new LaravelFileRepository($this->app, __DIR__.'/../Fixtures/modules');
        $this->manager = new ModuleManager($repository, $this->activator, app(PermissionSynchronizer::class));

        AlphaInstaller::$upgrades = [];
        TransactionGuard::$baseline = 0;
    }

    /**
     * Migrate (once per run) like RefreshDatabase, but without wrapping the test in a transaction.
     */
    public function beginDatabaseTransaction(): void
    {
        //
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('alpha_items');
        DB::table('migrations')->where('migration', 'like', '%create_alpha_items_table')->delete();
        InstalledModule::whereIn('name', ['Alpha', 'Beta'])->delete();
        // test_core_cannot_be_disabled may leave Core untouched; nothing else to undo.

        parent::tearDown();
    }

    public function test_enabling_a_module_migrates_it_runs_its_installer_and_records_it(): void
    {
        $this->manager->enable('Alpha');

        $this->assertTrue(Schema::hasTable('alpha_items'));
        $this->assertSame(['seeded'], DB::table('alpha_items')->pluck('name')->all());
        $this->assertTrue($this->manager->isEnabled('Alpha'));
        $this->assertSame('1.0.0', InstalledModule::find('Alpha')->version);
    }

    public function test_a_module_cannot_be_enabled_before_the_modules_it_requires(): void
    {
        $this->expectException(ModuleException::class);

        $this->manager->enable('Beta');
    }

    public function test_a_required_module_cannot_be_disabled_while_a_dependent_is_enabled(): void
    {
        $this->manager->enable('Alpha');
        $this->manager->enable('Beta');

        $this->expectException(ModuleException::class);

        $this->manager->disable('Alpha');
    }

    public function test_re_enabling_a_disabled_module_does_not_run_its_installer_again(): void
    {
        $this->manager->enable('Alpha');
        $this->manager->disable('Alpha');

        $this->assertFalse($this->manager->isEnabled('Alpha'));

        $this->manager->enable('Alpha');

        $this->assertTrue($this->manager->isEnabled('Alpha'));
        $this->assertSame(1, DB::table('alpha_items')->count());
    }

    public function test_core_cannot_be_disabled(): void
    {
        $repository = new LaravelFileRepository($this->app, base_path('Modules'));
        $manager = new ModuleManager($repository, $this->activator, app(PermissionSynchronizer::class));

        $this->expectException(ModuleException::class);

        $manager->disable('Core');
    }

    public function test_upgrade_runs_the_hook_when_the_code_version_is_newer(): void
    {
        $this->manager->enable('Alpha');
        InstalledModule::whereKey('Alpha')->update(['version' => '0.9.0']);

        $upgraded = $this->manager->upgrade();

        $this->assertSame(['Alpha' => ['from' => '0.9.0', 'to' => '1.0.0']], $upgraded);
        $this->assertSame([['0.9.0', '1.0.0']], AlphaInstaller::$upgrades);
        $this->assertSame('1.0.0', InstalledModule::find('Alpha')->version);
    }

    public function test_modules_are_sorted_after_the_modules_they_require(): void
    {
        $repository = new LaravelFileRepository($this->app, __DIR__.'/../Fixtures/modules');
        $sorted = $this->manager->sortByDependencies([$repository->find('Beta'), $repository->find('Alpha')]);

        $this->assertSame(['Alpha', 'Beta'], array_map(fn ($m) => $m->getName(), $sorted));
    }

    public function test_module_status_cannot_be_changed_through_nwidart_directly(): void
    {
        $this->expectException(LogicException::class);

        $this->activator->setActive($this->app['modules']->find('Core'), true);
    }
}
