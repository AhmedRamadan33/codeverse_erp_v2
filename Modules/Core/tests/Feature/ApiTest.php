<?php

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Models\Partner;
use Modules\Core\Permissions\PermissionSynchronizer;
use Nwidart\Modules\Facades\Module;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionSynchronizer::class)->sync(Module::find('Core'));
    }

    public function test_a_token_is_issued_for_valid_credentials_only(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass']);

        $this->postJson('/api/v1/auth/token', ['email' => $user->email, 'password' => 'wrong', 'device_name' => 'phone'])
            ->assertUnprocessable();

        $token = $this->postJson('/api/v1/auth/token', ['email' => $user->email, 'password' => 'secret-pass', 'device_name' => 'phone'])
            ->assertCreated()
            ->json('token');

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.email', $user->email);
    }

    public function test_api_routes_require_a_token(): void
    {
        $this->getJson('/api/v1/partners')->assertUnauthorized();
    }

    public function test_partner_crud_through_the_api(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['core.partners.view', 'core.partners.create', 'core.partners.update', 'core.partners.delete']);
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/v1/partners', [
            'type' => 'company',
            'name' => 'Delta Trading',
            'is_customer' => true,
            'credit_limit' => '2500.75',
        ])->assertCreated()
            ->assertJsonPath('data.credit_limit', '2500.7500')
            ->json('data.id');

        $this->putJson("/api/v1/partners/{$id}", ['type' => 'company', 'name' => 'Delta Co', 'is_customer' => true])
            ->assertOk()
            ->assertJsonPath('data.name', 'Delta Co')
            ->assertJsonPath('data.credit_limit', null);

        $this->getJson('/api/v1/partners?search=Delta')->assertOk()->assertJsonCount(1, 'data');

        $this->deleteJson("/api/v1/partners/{$id}")->assertNoContent();
        $this->assertSame(0, Partner::count());
    }

    public function test_the_api_enforces_permissions(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/partners')->assertForbidden();
        $this->postJson('/api/v1/partners', ['type' => 'company', 'name' => 'X', 'is_customer' => true])->assertForbidden();
    }

    public function test_validation_and_messages_follow_accept_language(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('core.partners.create');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/partners', ['type' => 'company', 'name' => 'X'], ['Accept-Language' => 'ar'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.is_customer.0', __('core::partners.role_required', [], 'ar'));
    }
}
