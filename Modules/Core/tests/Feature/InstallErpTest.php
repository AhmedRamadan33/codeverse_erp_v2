<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Installation\InstallationData;
use Modules\Core\Installation\InstallErp;
use Modules\Core\Models\Currency;
use Modules\Core\Models\InstalledModule;
use Modules\Core\Modules\ModuleException;
use Modules\Core\Settings\Settings;
use Tests\TestCase;

class InstallErpTest extends TestCase
{
    use RefreshDatabase;

    private function data(string $currency = 'SAR'): InstallationData
    {
        return new InstallationData(
            companyName: 'Acme',
            baseCurrency: $currency,
            locale: 'ar',
            branchNameAr: 'الفرع الرئيسي',
            branchNameEn: null,
            branchCode: 'MAIN',
            adminName: 'Admin',
            adminEmail: 'admin@example.com',
            adminPassword: 'password123',
        );
    }

    public function test_installation_sets_up_core_the_main_branch_and_a_super_admin(): void
    {
        $admin = app(InstallErp::class)->handle($this->data());

        $this->assertTrue(InstalledModule::whereKey('Core')->exists());
        $this->assertSame('SAR', app(Settings::class)->get('core.base_currency'));
        $this->assertSame(['SAR'], Currency::where('is_active', true)->pluck('code')->all());
        $this->assertTrue($admin->isSuperAdmin());
        $this->assertSame('MAIN', $admin->branches()->first()->code);
        $this->assertSame('الفرع الرئيسي', $admin->branches()->first()->getTranslation('name', 'en'));
    }

    public function test_an_installation_cannot_be_set_up_twice(): void
    {
        app(InstallErp::class)->handle($this->data());

        $this->expectException(ModuleException::class);

        app(InstallErp::class)->handle($this->data());
    }
}
