<?php

namespace Modules\Core;

use Modules\Core\Models\Currency;
use Modules\Core\Modules\ModuleInstaller;
use Modules\Core\Settings\Settings;

class CoreInstaller implements ModuleInstaller
{
    public function __construct(private readonly Settings $settings) {}

    public function install(): void
    {
        $this->seedCurrencies();
    }

    public function upgrade(string $fromVersion, string $toVersion): void
    {
        //
    }

    private function seedCurrencies(): void
    {
        $base = $this->settings->get('core.base_currency');

        foreach (require module_path('Core', 'database/data/currencies.php') as $currency) {
            Currency::firstOrCreate(['code' => $currency['code']], $currency + ['is_active' => $currency['code'] === $base]);
        }
    }
}
