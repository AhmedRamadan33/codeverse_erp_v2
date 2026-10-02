<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Setting;
use Modules\Core\Settings\Settings;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private Settings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = app(Settings::class);
    }

    public function test_it_returns_the_module_default_when_nothing_is_stored(): void
    {
        $this->assertSame('EGP', $this->settings->get('core.base_currency'));
    }

    public function test_a_branch_override_wins_over_the_installation_value(): void
    {
        $cairo = Branch::factory()->create();
        $alex = Branch::factory()->create();

        $this->settings->set('core.default_locale', 'en');
        $this->settings->set('core.default_locale', 'ar', $alex->id);

        $this->assertSame('en', $this->settings->get('core.default_locale'));
        $this->assertSame('en', $this->settings->get('core.default_locale', $cairo->id));
        $this->assertSame('ar', $this->settings->get('core.default_locale', $alex->id));

        $this->settings->forget('core.default_locale', $alex->id);

        $this->assertSame('en', $this->settings->get('core.default_locale', $alex->id));
    }

    public function test_setting_a_value_twice_updates_the_same_row(): void
    {
        $this->settings->set('core.company_name', 'Acme');
        $this->settings->set('core.company_name', 'CodeVerse');

        $this->assertSame(1, Setting::where(['module' => 'core', 'key' => 'company_name'])->count());
        $this->assertSame('CodeVerse', $this->settings->get('core.company_name'));
    }

    public function test_the_database_rejects_a_duplicate_installation_wide_value(): void
    {
        Setting::create(['module' => 'core', 'key' => 'company_phone', 'value' => 'A']);

        $this->expectException(UniqueConstraintViolationException::class);

        Setting::create(['module' => 'core', 'key' => 'company_phone', 'value' => 'B']);
    }

    public function test_unknown_keys_fail_fast(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->settings->get('core.no_such_setting');
    }
}
