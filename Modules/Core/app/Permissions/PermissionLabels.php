<?php

namespace Modules\Core\Permissions;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

/**
 * Human labels for "<module>.<resource>.<action>" permissions, grouped for the role editor.
 * Each module translates its resources in <module>::permissions.resources.<resource>;
 * actions come from <module>::permissions.actions.<action>, falling back to Core's.
 */
class PermissionLabels
{
    /**
     * @return Collection<string, array{label: string, permissions: array<string, string>}> keyed by "module.resource"
     */
    public function grouped(): Collection
    {
        return Permission::orderBy('name')->pluck('name')
            ->groupBy(fn (string $name) => implode('.', array_slice(explode('.', $name), 0, 2)))
            ->map(fn (Collection $names, string $group) => [
                'label' => $this->resource($group),
                'permissions' => $names->mapWithKeys(fn (string $name) => [$name => $this->action($name)])->all(),
            ]);
    }

    private function resource(string $group): string
    {
        [$module, $resource] = explode('.', $group, 2);
        $key = "{$module}::permissions.resources.{$resource}";

        return __($key) === $key ? $group : __($key);
    }

    private function action(string $name): string
    {
        [$module, , $action] = array_pad(explode('.', $name, 3), 3, '');

        foreach (["{$module}::permissions.actions.{$action}", "core::permissions.actions.{$action}"] as $key) {
            if (__($key) !== $key) {
                return __($key);
            }
        }

        return $action;
    }
}
