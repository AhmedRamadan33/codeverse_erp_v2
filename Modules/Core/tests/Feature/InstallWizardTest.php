<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;
use Modules\Core\Livewire\Install\Wizard;
use Modules\Core\Models\InstalledModule;
use Modules\Core\Settings\Settings;
use Tests\TestCase;

class InstallWizardTest extends TestCase
{
    // Needs a database without an installation, unlike the shared seeded one.
    use DatabaseMigrations;

    protected bool $seed = false;

    protected $seeder = false;

    /**
     * One test: each one here migrates a fresh database, which is slow.
     */
    public function test_pages_lead_to_the_wizard_which_installs_the_chosen_modules_and_then_disappears(): void
    {
        $this->get('/login')->assertRedirect(route('core.install'));
        $this->get('/dashboard')->assertRedirect(route('core.install'));
        $this->get(route('core.install'))->assertOk()->assertSee(__('core::install.title'));

        $wizard = Livewire::test(Wizard::class)
            ->call('next')
            ->assertSet('step', 1)
            ->call('next')
            ->assertHasErrors('company')
            ->set('form.company', 'Nile Stores')
            ->set('form.currency', 'EGP')
            ->call('next')
            ->assertSet('step', 2)
            ->set('form.admin_name', 'Owner')
            ->set('form.admin_email', 'owner@example.com')
            ->set('form.admin_password', 'secret-pass')
            ->set('form.admin_password_confirmation', 'different')
            ->call('next')
            ->assertHasErrors('admin_password')
            ->set('form.admin_password_confirmation', 'secret-pass')
            ->call('next')
            ->assertSet('step', 3)
            ->assertSee('Pos');

        $wizard->set('form.modules', ['Pos'])->call('install')->assertHasNoErrors()->assertRedirect(route('login'));

        $this->assertSame('Nile Stores', app(Settings::class)->get('core.company_name'));
        // POS and everything it requires.
        $this->assertEqualsCanonicalizing(
            ['Core', 'Accounting', 'Products', 'Inventory', 'Sales', 'Pos'],
            InstalledModule::where('enabled', true)->pluck('name')->all(),
        );
        $this->assertFalse(InstalledModule::whereKey('Purchases')->exists());

        $this->get(route('core.install'))->assertNotFound();
        $this->get('/login')->assertOk();
    }
}
