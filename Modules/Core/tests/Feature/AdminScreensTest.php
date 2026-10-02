<?php

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Installation\InstallationData;
use Modules\Core\Installation\InstallErp;
use Modules\Core\Models\Branch;
use Modules\Core\Sequences\Sequences;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every Core page renders for a super admin and is refused to a user without permissions.
 */
class AdminScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = app(InstallErp::class)->handle(new InstallationData(
            companyName: 'Acme', baseCurrency: 'EGP', locale: 'ar',
            branchNameAr: 'الرئيسي', branchNameEn: 'Main', branchCode: 'MAIN',
            adminName: 'Admin', adminEmail: 'admin@example.com', adminPassword: 'password123',
        ));
        app(Sequences::class)->define('core.test', 'T-{yyyy}-');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pages(): array
    {
        return [
            'dashboard' => ['/dashboard'],
            'partners' => ['/partners'],
            'partner form' => ['/partners/create'],
            'users' => ['/admin/users'],
            'user form' => ['/admin/users/create'],
            'roles' => ['/admin/roles'],
            'role form' => ['/admin/roles/create'],
            'branches' => ['/admin/branches'],
            'settings' => ['/admin/settings'],
            'currencies' => ['/admin/currencies'],
            'exchange rates' => ['/admin/exchange-rates'],
            'sequences' => ['/admin/sequences'],
            'audit' => ['/admin/audit'],
            'modules' => ['/admin/modules'],
        ];
    }

    #[DataProvider('pages')]
    public function test_a_super_admin_can_open_the_page_in_both_languages(string $url): void
    {
        $this->actingAs($this->admin)->get($url)->assertOk()->assertSee('dir="rtl"', false);

        $this->admin->update(['locale' => 'en']);
        $this->actingAs($this->admin->fresh())->get($url)->assertOk()->assertSee('dir="ltr"', false);
    }

    #[DataProvider('pages')]
    public function test_a_user_without_permissions_is_refused(string $url): void
    {
        $user = User::factory()->create();
        $user->branches()->attach(Branch::first());

        $response = $this->actingAs($user)->get($url);

        $url === '/dashboard' ? $response->assertOk() : $response->assertForbidden();
    }

    public function test_the_menu_shows_only_permitted_items(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('core.partners.view');

        $this->actingAs($user)->get('/dashboard')
            ->assertSee(route('core.partners.index'))
            ->assertDontSee(route('core.users.index'));
    }
}
