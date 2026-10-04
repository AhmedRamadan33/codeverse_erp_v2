<?php

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Modules\Core\Installation\InstallationData;
use Modules\Core\Installation\InstallErp;
use Modules\Core\Modules\ModuleException;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class InstallCommand extends Command
{
    protected $signature = 'erp:install
        {--company= : Company name}
        {--currency=EGP : Base currency (ISO 4217); cannot change after the first posting}
        {--locale=ar : Default language (ar or en)}
        {--branch-ar= : Main branch name in Arabic}
        {--branch-en= : Main branch name in English (optional)}
        {--branch-code=MAIN : Main branch code}
        {--admin-name= : Administrator name}
        {--admin-email= : Administrator email}
        {--admin-password= : Administrator password}
        {--modules= : Optional modules to enable too, comma separated (e.g. Pos); their requirements are added}';

    protected $description = 'Set up a new installation: database, base modules, main branch and administrator';

    public function handle(InstallErp $installer): int
    {
        if ($installer->isInstalled()) {
            $this->components->error(__('core::modules.already_installed'));

            return self::FAILURE;
        }

        $input = [
            'company' => $this->option('company') ?? text(__('core::install.company'), required: true),
            'currency' => strtoupper($this->option('currency')),
            'locale' => $this->option('locale') ?? select(__('core::install.locale'), ['ar' => 'العربية', 'en' => 'English']),
            'branch_ar' => $this->option('branch-ar') ?? text(__('core::install.branch_ar'), default: 'الفرع الرئيسي', required: true),
            'branch_en' => $this->option('branch-en'),
            'branch_code' => strtoupper($this->option('branch-code')),
            'admin_name' => $this->option('admin-name') ?? text(__('core::install.admin_name'), required: true),
            'admin_email' => $this->option('admin-email') ?? text(__('core::install.admin_email'), required: true),
            'admin_password' => $this->option('admin-password') ?? password(__('core::install.admin_password'), required: true),
        ];

        $input['modules'] = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('modules')))));
        $validator = Validator::make($input, InstallationData::rules());

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        try {
            $installer->handle(InstallationData::fromInput($validator->validated()));
        } catch (ModuleException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(__('core::install.done'));

        return self::SUCCESS;
    }
}
