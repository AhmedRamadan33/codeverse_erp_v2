<?php

namespace Tests\Concerns;

use App\Models\User;
use Modules\Core\Models\Branch;
use Tests\Support\InstalledErpSeeder;

/**
 * Gives tests the admin and main branch of the installation that InstalledErpSeeder
 * creates once per test run (see Tests\TestCase).
 */
trait InstallsErp
{
    protected User $admin;

    protected Branch $branch;

    protected function installErp(): void
    {
        $this->admin = User::where('email', InstalledErpSeeder::ADMIN_EMAIL)->firstOrFail();
        $this->branch = Branch::where('code', InstalledErpSeeder::BRANCH_CODE)->firstOrFail();
    }
}
