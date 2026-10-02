<?php

namespace Modules\Core\Permissions;

use Nwidart\Modules\Module;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the permissions a module declares in its config/permissions.php.
 * Permissions removed from a module's list are kept, so roles never silently lose access;
 * removing one is an explicit step in that module's upgrade hook.
 */
class PermissionSynchronizer
{
    public const SUPER_ADMIN = 'super-admin';

    public const GUARD = 'web';

    public function __construct(private readonly PermissionRegistrar $registrar) {}

    /**
     * @return string[] the module's permission names
     */
    public function sync(Module $module): array
    {
        // Read from the file: the module may not be booted yet while it is being enabled.
        $file = $module->getExtraPath('config/permissions.php');
        $names = is_file($file) ? require $file : [];

        foreach ($names as $name) {
            Permission::findOrCreate($name, self::GUARD);
        }

        Role::findOrCreate(self::SUPER_ADMIN, self::GUARD);

        $this->registrar->forgetCachedPermissions();

        return $names;
    }
}
