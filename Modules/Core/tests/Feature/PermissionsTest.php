<?php

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Branch;
use Modules\Core\Permissions\PermissionSynchronizer;
use Nwidart\Modules\Facades\Module;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionSynchronizer::class)->sync(Module::find('Core'));
    }

    public function test_core_permissions_and_the_super_admin_role_are_synced(): void
    {
        $this->assertTrue(Permission::where('name', 'core.partners.create')->exists());
        $this->assertSame(
            count(require module_path('Core', 'config/permissions.php')),
            Permission::where('name', 'like', 'core.%')->count(),
        );

        // Syncing again is idempotent.
        app(PermissionSynchronizer::class)->sync(Module::find('Core'));
        $this->assertSame(count(require module_path('Core', 'config/permissions.php')), Permission::where('name', 'like', 'core.%')->count());
    }

    public function test_a_user_has_only_granted_permissions_and_a_super_admin_has_all(): void
    {
        $clerk = User::factory()->create();
        $clerk->givePermissionTo('core.partners.view');

        $admin = User::factory()->create();
        $admin->assignRole(PermissionSynchronizer::SUPER_ADMIN);

        $this->assertTrue($clerk->can('core.partners.view'));
        $this->assertFalse($clerk->can('core.partners.delete'));
        $this->assertTrue($admin->can('core.partners.delete'));
    }

    public function test_an_inactive_user_is_denied_everything(): void
    {
        $admin = User::factory()->create(['is_active' => false]);
        $admin->assignRole(PermissionSynchronizer::SUPER_ADMIN);

        $this->assertFalse($admin->can('core.partners.view'));
    }

    public function test_branch_access_needs_assignment_or_all_access(): void
    {
        $cairo = Branch::factory()->create();
        $alex = Branch::factory()->create();

        $user = User::factory()->create();
        $user->branches()->attach($cairo, ['is_default' => true]);

        $this->assertTrue($user->canAccessBranch($cairo->id));
        $this->assertFalse($user->canAccessBranch($alex->id));

        $user->givePermissionTo('core.branches.all_access');

        $this->assertTrue($user->fresh()->canAccessBranch($alex->id));
    }
}
