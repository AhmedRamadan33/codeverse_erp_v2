<?php

namespace Tests\Concerns;

use App\Models\User;
use Modules\Core\Installation\InstallationData;
use Modules\Core\Installation\InstallErp;
use Modules\Core\Models\Branch;

/**
 * Runs the real installer, so tests start from what a customer gets: base modules,
 * chart of accounts, mappings, sequences, the current fiscal year, a main branch and an admin.
 */
trait InstallsErp
{
    protected User $admin;

    protected Branch $branch;

    protected function installErp(string $currency = 'EGP', string $locale = 'ar'): void
    {
        $this->admin = app(InstallErp::class)->handle(new InstallationData(
            companyName: 'Acme',
            baseCurrency: $currency,
            locale: $locale,
            branchNameAr: 'الفرع الرئيسي',
            branchNameEn: 'Main branch',
            branchCode: 'MAIN',
            adminName: 'Admin',
            adminEmail: 'admin@example.com',
            adminPassword: 'password123',
        ));

        $this->branch = Branch::firstWhere('code', 'MAIN');
    }
}
