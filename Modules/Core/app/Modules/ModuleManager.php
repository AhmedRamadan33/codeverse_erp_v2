<?php

namespace Modules\Core\Modules;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\InstalledModule;
use Modules\Core\Permissions\PermissionSynchronizer;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\Contracts\RepositoryInterface;
use Nwidart\Modules\Module;

/**
 * The only way to install, enable, disable and upgrade modules on an installation.
 */
class ModuleManager
{
    public function __construct(
        private readonly RepositoryInterface $modules,
        private readonly ActivatorInterface $activator,
        private readonly PermissionSynchronizer $permissions,
    ) {}

    /**
     * Install the module if needed, then enable it. Required modules must already be enabled.
     */
    public function enable(string $name): void
    {
        $module = $this->find($name);
        $record = InstalledModule::find($module->getName());

        if ($record?->enabled) {
            return;
        }

        foreach ($this->requires($module) as $required) {
            if (! $this->isEnabled($required)) {
                throw ModuleException::make('requires_missing', ['module' => $module->getName(), 'required' => $required]);
            }
        }

        // A disabled module's providers were never registered: load them now so its config
        // (setting defaults), translations and routes exist for the installer and this request.
        // Registering an already-registered provider does nothing.
        $module->register();
        $module->boot();

        // DDL commits implicitly on MySQL, so migrations run outside the transaction.
        $this->migrate($module);

        DB::transaction(function () use ($module, $record) {
            $this->permissions->sync($module);

            if ($record === null) {
                $this->installer($module)?->install();

                InstalledModule::create([
                    'name' => $module->getName(),
                    'version' => $this->version($module),
                    'enabled' => true,
                    'installed_at' => now(),
                ]);
            } else {
                $record->update(['enabled' => true]);
            }
        });

        $this->activator->reset();
    }

    /**
     * Disable a module. Its tables and data are kept.
     */
    public function disable(string $name): void
    {
        $module = $this->find($name);

        if (in_array($module->getName(), config('modules.activators.database.always-enabled', []), true)) {
            throw ModuleException::make('cannot_disable_required', ['module' => $module->getName()]);
        }

        foreach ($this->enabledModules() as $other) {
            if (in_array($module->getName(), $this->requires($other), true)) {
                throw ModuleException::make('required_by', ['module' => $module->getName(), 'dependent' => $other->getName()]);
            }
        }

        InstalledModule::whereKey($module->getName())->update(['enabled' => false]);

        $this->activator->reset();
    }

    /**
     * Run pending migrations of enabled modules, then each module's upgrade hook
     * when its code version is newer than the installed one.
     *
     * @return array<string, array{from: string, to: string}>
     */
    public function upgrade(): array
    {
        Artisan::call('migrate', ['--force' => true]);

        $upgraded = [];

        foreach ($this->sortByDependencies($this->enabledModules()) as $module) {
            $this->permissions->sync($module);

            $record = InstalledModule::find($module->getName());
            $from = $record?->version;
            $to = $this->version($module);

            if ($record === null || version_compare($to, $from, '<=')) {
                continue;
            }

            DB::transaction(function () use ($module, $record, $from, $to) {
                $this->installer($module)?->upgrade($from, $to);
                $record->update(['version' => $to, 'upgraded_at' => now()]);
            });

            $upgraded[$module->getName()] = ['from' => $from, 'to' => $to];
        }

        $this->activator->reset();

        return $upgraded;
    }

    public function isEnabled(string $name): bool
    {
        return $this->activator->hasStatus($name, true);
    }

    /**
     * @return string[]
     */
    public function requires(Module $module): array
    {
        return array_values($module->get('requires', []));
    }

    /**
     * Order modules so every module comes after the modules it requires.
     *
     * @param  Module[]  $modules
     * @return Module[]
     */
    public function sortByDependencies(array $modules): array
    {
        $byName = [];
        foreach ($modules as $module) {
            $byName[$module->getName()] = $module;
        }

        $sorted = [];
        $visiting = [];

        $visit = function (string $name) use (&$visit, &$sorted, &$visiting, $byName): void {
            if (isset($sorted[$name]) || ! isset($byName[$name])) {
                return;
            }
            if (isset($visiting[$name])) {
                throw ModuleException::make('circular_dependency', ['module' => $name]);
            }

            $visiting[$name] = true;
            foreach ($this->requires($byName[$name]) as $required) {
                $visit($required);
            }
            unset($visiting[$name]);

            $sorted[$name] = $byName[$name];
        };

        foreach (array_keys($byName) as $name) {
            $visit($name);
        }

        return array_values($sorted);
    }

    /**
     * @return Module[]
     */
    private function enabledModules(): array
    {
        return array_values(array_filter(
            $this->modules->all(),
            fn (Module $module) => $this->isEnabled($module->getName()),
        ));
    }

    private function find(string $name): Module
    {
        return $this->modules->find($name)
            ?? throw ModuleException::make('not_found', ['module' => $name]);
    }

    private function version(Module $module): string
    {
        return (string) $module->get('version', '0.0.0');
    }

    private function installer(Module $module): ?ModuleInstaller
    {
        $class = $module->get('installer');

        return $class ? app($class) : null;
    }

    private function migrate(Module $module): void
    {
        $path = $module->getExtraPath(config('modules.paths.generator.migration.path', 'database/migrations'));

        if (is_dir($path)) {
            Artisan::call('migrate', ['--path' => $path, '--realpath' => true, '--force' => true]);
        }
    }
}
