<?php

namespace Modules\Core\Modules;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\Module;
use Throwable;

/**
 * Reads module status from the `installed_modules` table, through a compiled
 * PHP cache file so booting does not hit the database on every request.
 *
 * Modules are enabled and disabled only through ModuleManager, which also runs
 * migrations and installers; the nwidart write methods are therefore refused.
 */
class DatabaseActivator implements ActivatorInterface
{
    /** @var array<string, bool>|null */
    private ?array $statuses = null;

    private ?string $cacheFile;

    /** @var string[] */
    private array $alwaysEnabled;

    private bool $enableAll;

    public function __construct(Container $app)
    {
        $config = $app['config']->get('modules.activators.database', []);

        $this->cacheFile = $config['cache-file'] ?? null;
        $this->alwaysEnabled = $config['always-enabled'] ?? ['Core'];
        $this->enableAll = (bool) ($config['enable-all'] ?? false);
    }

    public function hasStatus(Module|string $module, bool $status): bool
    {
        $name = $module instanceof Module ? $module->getName() : $module;

        return $this->isEnabled($name) === $status;
    }

    public function isEnabled(string $name): bool
    {
        if ($this->enableAll || in_array($name, $this->alwaysEnabled, true)) {
            return true;
        }

        return $this->statuses()[$name] ?? false;
    }

    /**
     * Forget cached statuses; called by ModuleManager after every change.
     */
    public function reset(): void
    {
        $this->statuses = null;

        if ($this->cacheFile !== null && is_file($this->cacheFile)) {
            @unlink($this->cacheFile);
        }
    }

    public function enable(Module $module): void
    {
        $this->refuseDirectWrite();
    }

    public function disable(Module $module): void
    {
        $this->refuseDirectWrite();
    }

    public function setActive(Module $module, bool $active): void
    {
        $this->refuseDirectWrite();
    }

    /**
     * Called by `module:make` after generating code. Generating a module does not install it
     * on this installation, so this is deliberately a no-op; use `erp:module:enable`.
     */
    public function setActiveByName(string $name, bool $active): void
    {
        //
    }

    public function delete(Module $module): void
    {
        $this->refuseDirectWrite();
    }

    /**
     * @return array<string, bool>
     */
    private function statuses(): array
    {
        if ($this->statuses !== null) {
            return $this->statuses;
        }

        if ($this->cacheFile !== null && is_file($this->cacheFile)) {
            return $this->statuses = require $this->cacheFile;
        }

        $this->statuses = $this->readDatabase();

        if ($this->statuses !== [] && $this->cacheFile !== null) {
            $this->writeCache($this->statuses);
        }

        return $this->statuses;
    }

    /**
     * @return array<string, bool>
     */
    private function readDatabase(): array
    {
        try {
            if (! Schema::hasTable('installed_modules')) {
                return [];
            }

            return DB::table('installed_modules')
                ->pluck('enabled', 'name')
                ->map(fn ($enabled) => (bool) $enabled)
                ->all();
        } catch (Throwable) {
            // Not installed yet or the database is unreachable: only always-enabled modules boot.
            return [];
        }
    }

    /**
     * @param  array<string, bool>  $statuses
     */
    private function writeCache(array $statuses): void
    {
        $temp = $this->cacheFile.'.'.uniqid('', true).'.tmp';

        file_put_contents($temp, '<?php return '.var_export($statuses, true).';'.PHP_EOL);
        rename($temp, $this->cacheFile);
    }

    private function refuseDirectWrite(): never
    {
        throw new LogicException('Use `php artisan erp:module:enable|disable`; module status is managed by ModuleManager.');
    }
}
