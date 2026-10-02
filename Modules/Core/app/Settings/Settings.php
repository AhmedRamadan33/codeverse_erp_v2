<?php

namespace Modules\Core\Settings;

use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Modules\Core\Models\Setting;

/**
 * Typed access to installation settings.
 *
 * Keys are "<module>.<key>", e.g. "sales.credit_limit_mode". Every key must have a
 * default in its module's config/settings.php (read as config('<module>.settings')),
 * so unknown keys fail fast. Lookup order: branch override, installation value, default.
 */
class Settings
{
    private const CACHE_KEY = 'core.settings';

    /** @var array<string, mixed>|null "module.key@branch" => value */
    private ?array $values = null;

    public function get(string $key, ?int $branchId = null): mixed
    {
        [$module, $name] = $this->split($key);
        $values = $this->values();

        if ($branchId !== null && array_key_exists("{$module}.{$name}@{$branchId}", $values)) {
            return $values["{$module}.{$name}@{$branchId}"];
        }

        if (array_key_exists("{$module}.{$name}@", $values)) {
            return $values["{$module}.{$name}@"];
        }

        return $this->default($module, $name);
    }

    public function set(string $key, mixed $value, ?int $branchId = null): void
    {
        [$module, $name] = $this->split($key);
        $this->default($module, $name);

        Setting::updateOrCreate(
            ['module' => $module, 'key' => $name, 'branch_id' => $branchId],
            ['value' => $value],
        );

        $this->flush();
    }

    /**
     * Remove a branch override (or the installation value) so the next level applies.
     */
    public function forget(string $key, ?int $branchId = null): void
    {
        [$module, $name] = $this->split($key);

        Setting::where(['module' => $module, 'key' => $name, 'branch_id' => $branchId])->delete();

        $this->flush();
    }

    public function flush(): void
    {
        $this->values = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{string, string}
     */
    private function split(string $key): array
    {
        $parts = explode('.', $key, 2);

        if (count($parts) !== 2) {
            throw new InvalidArgumentException("Setting key [{$key}] must be \"<module>.<key>\".");
        }

        return $parts;
    }

    private function default(string $module, string $name): mixed
    {
        $defaults = config("{$module}.settings", []);

        if (! array_key_exists($name, $defaults)) {
            throw new InvalidArgumentException("Unknown setting [{$module}.{$name}]; declare its default in the module's config/settings.php.");
        }

        return $defaults[$name];
    }

    /**
     * @return array<string, mixed>
     */
    private function values(): array
    {
        return $this->values ??= Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()
            ->get(['module', 'key', 'branch_id', 'value'])
            ->mapWithKeys(fn (Setting $s) => ["{$s->module}.{$s->key}@{$s->branch_id}" => $s->value])
            ->all());
    }
}
