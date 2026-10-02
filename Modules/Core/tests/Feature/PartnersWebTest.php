<?php

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Livewire\Partners\Form;
use Modules\Core\Livewire\Partners\Index;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Partner;
use Modules\Core\Permissions\PermissionSynchronizer;
use Nwidart\Modules\Facades\Module;
use Tests\TestCase;

class PartnersWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionSynchronizer::class)->sync(Module::find('Core'));
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    public function test_the_list_requires_the_view_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/partners')->assertForbidden();
        $this->actingAs($this->userWith('core.partners.view'))->get('/partners')->assertOk();
    }

    public function test_creating_a_partner_from_the_form(): void
    {
        $this->actingAs($this->userWith('core.partners.view', 'core.partners.create'));

        Livewire::test(Form::class)
            ->set('form.name', 'شركة النور')
            ->set('form.is_supplier', true)
            ->set('form.credit_limit', '15000.50')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('core.partners.index'));

        $partner = Partner::firstWhere('name', 'شركة النور');
        $this->assertTrue($partner->is_customer && $partner->is_supplier);
        $this->assertSame('15000.5000', (string) $partner->credit_limit);
    }

    public function test_a_partner_needs_at_least_one_role(): void
    {
        $this->actingAs($this->userWith('core.partners.create'));

        Livewire::test(Form::class)
            ->set('form.name', 'X')
            ->set('form.is_customer', false)
            ->set('form.is_supplier', false)
            ->call('save')
            ->assertHasErrors('is_customer');

        $this->assertSame(0, Partner::count());
    }

    public function test_users_only_see_shared_partners_and_those_of_their_branches(): void
    {
        $cairo = Branch::factory()->create();
        $alex = Branch::factory()->create();
        Partner::factory()->create(['name' => 'Shared']);
        Partner::factory()->create(['name' => 'Cairo only', 'branch_id' => $cairo->id]);
        $alexPartner = Partner::factory()->create(['name' => 'Alex only', 'branch_id' => $alex->id]);

        $user = $this->userWith('core.partners.view', 'core.partners.update');
        $user->branches()->attach($cairo);
        $this->actingAs($user);

        Livewire::test(Index::class)
            ->assertSee('Shared')
            ->assertSee('Cairo only')
            ->assertDontSee('Alex only');

        $this->get("/partners/{$alexPartner->id}/edit")->assertNotFound();
    }

    public function test_deleting_requires_the_delete_permission(): void
    {
        $partner = Partner::factory()->create();
        $this->actingAs($this->userWith('core.partners.view'));

        Livewire::test(Index::class)->call('delete', $partner->id)->assertForbidden();

        $this->assertModelExists($partner);
    }
}
