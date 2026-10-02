<?php

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Branches\Actions\SaveBranch;
use Modules\Core\Currencies\Actions\SaveExchangeRate;
use Modules\Core\Currencies\Actions\UpdateCurrency;
use Modules\Core\Installation\InstallationData;
use Modules\Core\Installation\InstallErp;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Sequence;
use Modules\Core\Permissions\PermissionSynchronizer;
use Modules\Core\Sequences\Actions\SaveSequence;
use Modules\Core\Sequences\Sequences;
use Modules\Core\Users\Actions\SaveRole;
use Modules\Core\Users\Actions\SaveUser;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = app(InstallErp::class)->handle(new InstallationData(
            companyName: 'Acme', baseCurrency: 'EGP', locale: 'ar',
            branchNameAr: 'الرئيسي', branchNameEn: null, branchCode: 'MAIN',
            adminName: 'Admin', adminEmail: 'admin@example.com', adminPassword: 'password123',
        ));
    }

    private function assertRejected(callable $action, string $field): void
    {
        try {
            $action();
            $this->fail("Expected a validation error on [{$field}].");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    private function userData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Clerk', 'email' => 'clerk@example.com', 'password' => 'password123',
            'is_active' => true, 'roles' => [], 'branches' => [Branch::first()->id],
        ], $overrides);
    }

    public function test_creating_a_user_with_roles_and_a_default_branch(): void
    {
        $role = app(SaveRole::class)->handle($this->admin, ['name' => 'cashier', 'permissions' => ['core.partners.view']]);
        $second = Branch::factory()->create();

        $user = app(SaveUser::class)->handle($this->admin, $this->userData([
            'roles' => [$role->name],
            'branches' => [Branch::first()->id, $second->id],
            'default_branch_id' => $second->id,
        ]));

        $this->assertTrue($user->can('core.partners.view'));
        $this->assertFalse($user->can('core.partners.create'));
        $this->assertSame($second->id, $user->branches()->wherePivot('is_default', true)->value('branches.id'));
    }

    public function test_only_a_super_admin_grants_super_admin_and_the_last_one_stays(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo('core.users.manage');

        $this->assertRejected(fn () => app(SaveUser::class)->handle($manager, $this->userData([
            'roles' => [PermissionSynchronizer::SUPER_ADMIN],
        ])), 'roles');

        $this->assertRejected(fn () => app(SaveUser::class)->handle($this->admin, $this->userData([
            'name' => 'Admin', 'email' => 'admin@example.com', 'password' => null, 'roles' => [],
        ]), $this->admin), 'roles');
    }

    public function test_a_user_cannot_deactivate_themself(): void
    {
        $other = User::factory()->create();
        $other->assignRole(PermissionSynchronizer::SUPER_ADMIN);

        $this->assertRejected(fn () => app(SaveUser::class)->handle($this->admin, $this->userData([
            'name' => 'Admin', 'email' => 'admin@example.com', 'password' => null,
            'roles' => [PermissionSynchronizer::SUPER_ADMIN], 'is_active' => false,
        ]), $this->admin), 'is_active');
    }

    public function test_the_super_admin_role_is_fixed_and_used_roles_cannot_be_deleted(): void
    {
        $this->assertRejected(fn () => app(SaveRole::class)->handle(
            $this->admin, ['name' => 'renamed', 'permissions' => []], Role::findByName(PermissionSynchronizer::SUPER_ADMIN),
        ), 'name');

        $this->assertRejected(fn () => app(SaveRole::class)->delete($this->admin, Role::findByName(PermissionSynchronizer::SUPER_ADMIN)), 'role');
    }

    public function test_the_last_active_branch_cannot_be_deactivated(): void
    {
        $branch = Branch::first();

        $this->assertRejected(fn () => app(SaveBranch::class)->handle($this->admin, [
            'name_ar' => 'الرئيسي', 'code' => 'MAIN', 'is_active' => false,
        ], $branch), 'is_active');
    }

    public function test_the_base_currency_stays_active_and_has_no_rate(): void
    {
        $egp = Currency::firstWhere('code', 'EGP');

        $this->assertRejected(fn () => app(UpdateCurrency::class)->handle($this->admin, $egp, ['symbol' => 'ج.م', 'is_active' => false]), 'is_active');
        $this->assertRejected(fn () => app(SaveExchangeRate::class)->handle($this->admin, [
            'currency_id' => $egp->id, 'date' => '2026-10-01', 'rate' => '1',
        ]), 'currency_id');
    }

    public function test_saving_a_rate_for_the_same_date_replaces_it(): void
    {
        $usd = Currency::firstWhere('code', 'USD');
        $usd->update(['is_active' => true]);

        app(SaveExchangeRate::class)->handle($this->admin, ['currency_id' => $usd->id, 'date' => '2026-10-01', 'rate' => '48.5']);
        app(SaveExchangeRate::class)->handle($this->admin, ['currency_id' => $usd->id, 'date' => '2026-10-01', 'rate' => '48.75']);

        $this->assertSame(['48.750000'], $usd->exchangeRates()->get()->map(fn ($r) => (string) $r->rate)->all());
    }

    public function test_a_resetting_sequence_needs_a_period_token_in_its_prefix(): void
    {
        $sequence = app(Sequences::class)->define('core.test', 'T-{yyyy}-');

        $this->assertRejected(fn () => app(SaveSequence::class)->handle($this->admin, [
            'key' => 'core.test', 'prefix' => 'T-', 'padding' => 5, 'reset' => 'yearly',
        ], $sequence), 'prefix');

        $branchSequence = app(SaveSequence::class)->handle($this->admin, [
            'key' => 'core.test', 'branch_id' => Branch::first()->id, 'prefix' => '{branch}-', 'padding' => 4, 'reset' => 'never',
        ]);
        $this->assertSame(2, Sequence::where('key', 'core.test')->count());
        $this->assertSame(Branch::first()->id, $branchSequence->branch_id);
    }

    public function test_lookup_endpoints_return_the_users_branches_and_active_currencies(): void
    {
        $user = User::factory()->create();
        $user->branches()->attach(Branch::first(), ['is_default' => true]);
        Branch::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/branches')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_default', true);

        $this->getJson('/api/v1/currencies')->assertOk()
            ->assertJsonPath('data.0.code', 'EGP')
            ->assertJsonPath('data.0.is_base', true);
    }
}
