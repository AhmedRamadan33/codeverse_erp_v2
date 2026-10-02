<?php

namespace Modules\Core\Support;

use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Base service provider for every ERP module.
 */
abstract class ErpModuleServiceProvider extends ModuleServiceProvider
{
    /**
     * Load the module's lang files under its own namespace, e.g. __('core::modules.enabled').
     */
    protected function registerTranslations(): void
    {
        $langPath = module_path($this->name, config('modules.paths.generator.lang.path', 'lang'));

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->nameLower);
        }
    }
}
