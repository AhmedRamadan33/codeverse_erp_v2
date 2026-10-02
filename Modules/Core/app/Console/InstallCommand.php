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
        {--admin-password= : Administrator password}';

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

        $validator = Validator::make($input, [
            'company' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3', 'in:'.implode(',', array_column(require module_path('Core', 'database/data/currencies.php'), 'code'))],
            'locale' => ['required', 'in:ar,en'],
            'branch_ar' => ['required', 'string', 'max:255'],
            'branch_en' => ['nullable', 'string', 'max:255'],
            'branch_code' => ['required', 'alpha_dash', 'max:16'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email'],
            'admin_password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        try {
            $installer->handle(new InstallationData(
                companyName: $input['company'],
                baseCurrency: $input['currency'],
                locale: $input['locale'],
                branchNameAr: $input['branch_ar'],
                branchNameEn: $input['branch_en'],
                branchCode: $input['branch_code'],
                adminName: $input['admin_name'],
                adminEmail: $input['admin_email'],
                adminPassword: $input['admin_password'],
            ));
        } catch (ModuleException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(__('core::install.done'));

        return self::SUCCESS;
    }
}
