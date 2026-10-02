<?php

namespace Modules\Core\Installation;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\Branch;
use Modules\Core\Models\InstalledModule;
use Modules\Core\Modules\ModuleException;
use Modules\Core\Modules\ModuleManager;
use Modules\Core\Permissions\PermissionSynchronizer;
use Modules\Core\Settings\Settings;

/**
 * First-time setup of an installation. Used by `erp:install` and, later, the web setup wizard.
 */
class InstallErp
{
    /**
     * Modules every installation has (docs/architecture/core-design.md D1), enabled in this order.
     * Accounting joins this list when it exists.
     */
    public const BASE_MODULES = ['Core'];

    public function __construct(
        private readonly ModuleManager $modules,
        private readonly Settings $settings,
    ) {}

    public function isInstalled(): bool
    {
        return Schema::hasTable('installed_modules') && InstalledModule::whereKey('Core')->exists();
    }

    public function handle(InstallationData $data): User
    {
        if ($this->isInstalled()) {
            throw ModuleException::make('already_installed');
        }

        Artisan::call('migrate', ['--force' => true]);

        // Settings first: module installers read them (e.g. Core activates the base currency).
        DB::transaction(function () use ($data) {
            $this->settings->set('core.company_name', $data->companyName);
            $this->settings->set('core.base_currency', $data->baseCurrency);
            $this->settings->set('core.default_locale', $data->locale);
        });

        foreach (self::BASE_MODULES as $module) {
            $this->modules->enable($module);
        }

        return DB::transaction(function () use ($data) {
            $branch = Branch::create([
                'name' => array_filter(['ar' => $data->branchNameAr, 'en' => $data->branchNameEn]),
                'code' => $data->branchCode,
            ]);

            $admin = User::create([
                'name' => $data->adminName,
                'email' => $data->adminEmail,
                'password' => $data->adminPassword,
                'locale' => $data->locale,
            ]);
            $admin->assignRole(PermissionSynchronizer::SUPER_ADMIN);
            $admin->branches()->attach($branch, ['is_default' => true]);

            return $admin;
        });
    }
}
