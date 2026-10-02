<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Modules\Core\Support\TransactionGuard;
use Tests\Support\InstalledErpSeeder;

abstract class TestCase extends BaseTestCase
{
    /**
     * The test database is migrated and installed once per run; tests that need a
     * database without an installation use DatabaseMigrations and set $seed = false.
     */
    protected bool $seed = true;

    /**
     * Laravel runs a configured seeder even when $seed is false: tests without an
     * installation set both $seed and $seeder to false.
     *
     * @var class-string|false
     */
    protected $seeder = InstalledErpSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase wraps each test in a transaction that application code must not count as its own.
        TransactionGuard::$baseline = in_array(RefreshDatabase::class, class_uses_recursive($this), true) ? 1 : 0;
    }
}
