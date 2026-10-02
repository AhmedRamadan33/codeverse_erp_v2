<?php

namespace Modules\Core\Support;

use Livewire\Livewire;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Base service provider for every ERP module.
 */
abstract class ErpModuleServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        $this->registerLivewire();
    }

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

    /**
     * Livewire components of a module live in Modules\<Name>\Livewire and are named "<module>::<component>".
     */
    protected function registerLivewire(): void
    {
        Livewire::addNamespace(
            $this->nameLower,
            classNamespace: "Modules\\{$this->name}\\Livewire",
            classPath: module_path($this->name, 'app/Livewire'),
            classViewPath: module_path($this->name, 'resources/views/livewire'),
        );
    }
}
