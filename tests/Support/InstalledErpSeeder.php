<?php

namespace Tests\Support;

use Illuminate\Database\Seeder;
use Modules\Core\Installation\InstallationData;
use Modules\Core\Installation\InstallErp;

/**
 * Seeds the test database once per run with a real installation (base modules, chart of
 * accounts, mappings, sequences, current fiscal year, main branch, admin). Each test then
 * runs inside a transaction on top of it, which is much faster than installing per test.
 */
class InstalledErpSeeder extends Seeder
{
    public const ADMIN_EMAIL = 'admin@example.com';

    public const BRANCH_CODE = 'MAIN';

    public function run(InstallErp $installer): void
    {
        $installer->handle(new InstallationData(
            companyName: 'Acme',
            baseCurrency: 'EGP',
            locale: 'ar',
            branchNameAr: 'الفرع الرئيسي',
            branchNameEn: 'Main branch',
            branchCode: self::BRANCH_CODE,
            adminName: 'Admin',
            adminEmail: self::ADMIN_EMAIL,
            adminPassword: 'password123',
        ));
    }
}
