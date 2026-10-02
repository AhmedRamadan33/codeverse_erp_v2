<?php

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Livewire\Auth\Login;
use Tests\TestCase;

class WebAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee(__('core::auth.login'));
    }

    public function test_a_user_can_sign_in_and_out(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass']);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'secret-pass')
            ->call('login')
            ->assertRedirect(route('core.dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_wrong_password_and_inactive_users_cannot_sign_in(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass']);
        $inactive = User::factory()->create(['password' => 'secret-pass', 'is_active' => false]);

        Livewire::test(Login::class)
            ->set('email', $user->email)->set('password', 'wrong')
            ->call('login')->assertHasErrors('email');

        Livewire::test(Login::class)
            ->set('email', $inactive->email)->set('password', 'secret-pass')
            ->call('login')->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_user_deactivated_while_signed_in_is_signed_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->update(['is_active' => false]);

        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_the_language_switch_is_saved_on_the_user_and_switches_direction(): void
    {
        $user = User::factory()->create(['locale' => 'ar']);
        $this->actingAs($user);

        $this->get('/dashboard')->assertSee('dir="rtl"', false);

        $this->post('/locale', ['locale' => 'en'])->assertRedirect();

        $this->assertSame('en', $user->fresh()->locale);
        $this->get('/dashboard')->assertSee('dir="ltr"', false);
    }
}
