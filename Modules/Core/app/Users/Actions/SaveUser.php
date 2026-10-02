<?php

namespace Modules\Core\Users\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Modules\Core\Http\Middleware\SetLocale;
use Modules\Core\Permissions\PermissionSynchronizer as Permissions;
use Spatie\Permission\Models\Role;

/**
 * Creates or updates a user with roles and branch access. Users are deactivated, not deleted.
 */
class SaveUser
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => [$user ? 'nullable' : 'required', 'string', Password::min(8)],
            'locale' => ['nullable', Rule::in(SetLocale::SUPPORTED)],
            'is_active' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'branches' => ['array'],
            'branches.*' => ['integer', Rule::exists('branches', 'id')],
            'default_branch_id' => ['nullable', 'integer', 'in_array:branches.*'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     */
    public function handle(User $actor, array $data, ?User $user = null): User
    {
        Gate::forUser($actor)->authorize('core.users.manage');

        $roles = array_values($data['roles'] ?? []);
        $isActive = (bool) ($data['is_active'] ?? true);

        $this->guardSuperAdmin($actor, $user, $roles, $isActive);

        if ($user?->is($actor) && ! $isActive) {
            throw ValidationException::withMessages(['is_active' => __('core::users.cannot_deactivate_self')]);
        }

        return DB::transaction(function () use ($data, $user, $roles, $isActive) {
            $user ??= new User;
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'locale' => $data['locale'] ?? null,
                'is_active' => $isActive,
            ]);

            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }

            $user->save();
            $user->syncRoles($roles);

            $default = $data['default_branch_id'] ?? ($data['branches'][0] ?? null);
            $user->branches()->sync(collect($data['branches'] ?? [])
                ->mapWithKeys(fn ($id) => [(int) $id => ['is_default' => (int) $id === (int) $default]])
                ->all());

            return $user;
        });
    }

    /**
     * Only super admins grant or revoke super-admin, and the last active super admin stays.
     *
     * @param  string[]  $roles
     */
    private function guardSuperAdmin(User $actor, ?User $user, array $roles, bool $isActive): void
    {
        $wants = in_array(Permissions::SUPER_ADMIN, $roles, true);
        $has = $user?->isSuperAdmin() ?? false;

        if ($wants !== $has && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages(['roles' => __('core::users.super_admin_only')]);
        }

        if ($has && (! $wants || ! $isActive)) {
            $others = User::role(Permissions::SUPER_ADMIN)->where('is_active', true)->whereKeyNot($user->id)->exists();

            if (! $others) {
                throw ValidationException::withMessages(['roles' => __('core::users.last_super_admin')]);
            }
        }
    }
}
