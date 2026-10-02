<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Modules\Core\Support\TransactionGuard;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase wraps each test in a transaction that application code must not count as its own.
        TransactionGuard::$baseline = in_array(RefreshDatabase::class, class_uses_recursive($this), true) ? 1 : 0;
    }
}
