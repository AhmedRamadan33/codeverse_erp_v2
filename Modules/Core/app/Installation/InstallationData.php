<?php

namespace Modules\Core\Installation;

use Illuminate\Validation\Rule;
use Modules\Core\Modules\ModuleManager;

final readonly class InstallationData
{
    /**
     * @param  string[]  $modules  optional modules to enable as well (their requirements are added)
     */
    public function __construct(
        public string $companyName,
        public string $baseCurrency,
        public string $locale,
        public string $branchNameAr,
        public ?string $branchNameEn,
        public string $branchCode,
        public string $adminName,
        public string $adminEmail,
        public string $adminPassword,
        public array $modules = [],
    ) {}

    /**
     * Shared by `erp:install` and the web setup wizard.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'company' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3', 'in:'.implode(',', array_column(require module_path('Core', 'database/data/currencies.php'), 'code'))],
            'locale' => ['required', 'in:ar,en'],
            'branch_ar' => ['required', 'string', 'max:255'],
            'branch_en' => ['nullable', 'string', 'max:255'],
            'branch_code' => ['required', 'alpha_dash', 'max:16'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email'],
            'admin_password' => ['required', 'string', 'min:8'],
            'modules' => ['array'],
            // Checked before anything is installed, so a typo cannot leave a half-done installation.
            'modules.*' => ['string', Rule::in(array_keys(app(ModuleManager::class)->optional()))],
        ];
    }

    /**
     * @param  array<string, mixed>  $input  validated against rules()
     */
    public static function fromInput(array $input): self
    {
        return new self(
            companyName: $input['company'],
            baseCurrency: strtoupper($input['currency']),
            locale: $input['locale'],
            branchNameAr: $input['branch_ar'],
            branchNameEn: $input['branch_en'] ?? null,
            branchCode: strtoupper($input['branch_code']),
            adminName: $input['admin_name'],
            adminEmail: $input['admin_email'],
            adminPassword: $input['admin_password'],
            modules: array_values($input['modules'] ?? []),
        );
    }
}
