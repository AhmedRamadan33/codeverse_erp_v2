<?php

namespace Modules\Core\Livewire\Install;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Installation\InstallationData;
use Modules\Core\Installation\InstallErp;
use Modules\Core\Modules\ModuleException;
use Modules\Core\Modules\ModuleManager;
use Throwable;

/**
 * First-time setup from the browser (core-design.md §11.2): checks the server, then collects
 * what `erp:install` asks for and calls the same action. Reachable only before installation.
 */
#[Layout('core::layouts.guest')]
class Wizard extends Component
{
    public const STEPS = ['checks', 'company', 'admin', 'modules'];

    public int $step = 0;

    /** @var array<string, mixed> */
    public array $form = [
        'company' => '', 'currency' => 'EGP', 'locale' => 'ar',
        'branch_ar' => 'الفرع الرئيسي', 'branch_en' => 'Main branch', 'branch_code' => 'MAIN',
        'admin_name' => '', 'admin_email' => '', 'admin_password' => '', 'admin_password_confirmation' => '',
        'modules' => [],
    ];

    /**
     * @return array<string, bool> requirement label => met
     */
    private function checks(): array
    {
        $database = false;
        try {
            $version = (string) DB::connection()->selectOne('select version() as v')->v;
            $database = DB::connection()->getDriverName() === 'mysql' && version_compare($version, '8.0', '>=') && ! str_contains(strtolower($version), 'mariadb');
        } catch (Throwable) {
            // shown as not met
        }

        $checks = [
            __('core::install.checks.php', ['version' => '8.3']) => version_compare(PHP_VERSION, '8.3.0', '>='),
            __('core::install.checks.database') => $database,
            __('core::install.checks.writable', ['path' => 'storage']) => is_writable(storage_path()),
            __('core::install.checks.writable', ['path' => 'bootstrap/cache']) => is_writable(base_path('bootstrap/cache')),
        ];
        foreach (['pdo_mysql', 'mbstring', 'openssl', 'fileinfo'] as $extension) {
            $checks[__('core::install.checks.extension', ['name' => $extension])] = extension_loaded($extension);
        }

        return $checks;
    }

    /**
     * Fields validated on each step before moving on.
     *
     * @return string[]
     */
    private function fieldsOf(int $step): array
    {
        return match (self::STEPS[$step]) {
            'company' => ['company', 'currency', 'locale', 'branch_ar', 'branch_en', 'branch_code'],
            'admin' => ['admin_name', 'admin_email', 'admin_password'],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        $rules = InstallationData::rules();
        $rules['admin_password'][] = 'confirmed';

        return $rules;
    }

    public function next(): void
    {
        if (self::STEPS[$this->step] === 'checks' && in_array(false, $this->checks(), true)) {
            return;
        }

        $fields = $this->fieldsOf($this->step);
        if ($fields !== []) {
            $rules = array_intersect_key($this->rules(), array_flip($fields));
            Validator::make($this->form, $rules)->validate();
        }

        $this->step = min($this->step + 1, count(self::STEPS) - 1);
    }

    public function back(): void
    {
        $this->step = max($this->step - 1, 0);
    }

    public function install(InstallErp $installer): void
    {
        $data = Validator::make($this->form, $this->rules())->validate();

        try {
            $installer->handle(InstallationData::fromInput($data));
        } catch (ModuleException $e) {
            $this->addError('install', $e->getMessage());

            return;
        }

        // The next request uses the real session store, so sign in there.
        $this->redirectRoute('login');
    }

    /**
     * Optional modules with their names read from their lang files: they are not loaded yet.
     *
     * @return array<string, array{name: string, description: string, requires: string[]}>
     */
    private function modules(ModuleManager $manager): array
    {
        $locale = app()->getLocale();

        return collect($manager->optional())->mapWithKeys(function (array $requires, string $name) use ($locale) {
            $file = module_path($name, "lang/{$locale}/module.php");
            $lang = is_file($file) ? require $file : [];

            return [$name => ['name' => $lang['name'] ?? $name, 'description' => $lang['description'] ?? '', 'requires' => $requires]];
        })->all();
    }

    public function render(ModuleManager $manager)
    {
        return view('core::livewire.install.wizard', [
            'stepName' => self::STEPS[$this->step],
            'checks' => self::STEPS[$this->step] === 'checks' ? $this->checks() : [],
            'currencies' => require module_path('Core', 'database/data/currencies.php'),
            'modules' => self::STEPS[$this->step] === 'modules' ? $this->modules($manager) : [],
        ])->title(__('core::install.title'));
    }
}
