<?php

namespace Modules\Core\Users\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Core\Permissions\PermissionSynchronizer as Permissions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles are data: created in the UI and granted permissions. The super-admin role is fixed.
 */
class SaveRole
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(?Role $role = null): array
    {
        return [
            'name' => ['required', 'string', 'max:64', Rule::unique('roles', 'name')->ignore($role)],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     */
    public function handle(User $actor, array $data, ?Role $role = null): Role
    {
        Gate::forUser($actor)->authorize('core.roles.manage');

        if ($role?->name === Permissions::SUPER_ADMIN || $data['name'] === Permissions::SUPER_ADMIN) {
            throw ValidationException::withMessages(['name' => __('core::users.super_admin_fixed')]);
        }

        return DB::transaction(function () use ($data, $role) {
            $role ??= new Role(['guard_name' => Permissions::GUARD]);
            $role->name = $data['name'];
            $role->save();
            $role->syncPermissions($data['permissions'] ?? []);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $role;
        });
    }

    public function delete(User $actor, Role $role): void
    {
        Gate::forUser($actor)->authorize('core.roles.manage');

        if ($role->name === Permissions::SUPER_ADMIN) {
            throw ValidationException::withMessages(['role' => __('core::users.super_admin_fixed')]);
        }

        if ($role->users()->exists()) {
            throw ValidationException::withMessages(['role' => __('core::users.role_in_use')]);
        }

        DB::transaction(fn () => $role->delete());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
