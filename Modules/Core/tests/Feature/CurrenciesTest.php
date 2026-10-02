<?php

namespace Modules\Core\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\CoreInstaller;
use Modules\Core\Currencies\Currencies;
use Modules\Core\Currencies\MissingExchangeRate;
use Modules\Core\Models\Currency;
use Modules\Core\Models\ExchangeRate;
use Tests\TestCase;

class CurrenciesTest extends TestCase
{
    use RefreshDatabase;

    private Currencies $currencies;

    protected function setUp(): void
    {
        parent::setUp();

        app(CoreInstaller::class)->install();
        $this->currencies = app(Currencies::class);
    }

    public function test_installation_activates_only_the_base_currency(): void
    {
        $this->assertSame(['EGP'], Currency::where('is_active', true)->pluck('code')->all());
        $this->assertSame('EGP', $this->currencies->base()->code);
    }

    public function test_the_base_currency_rate_is_always_one(): void
    {
        $rate = $this->currencies->rate($this->currencies->base(), CarbonImmutable::now());

        $this->assertSame('1.000000', (string) $rate);
    }

    public function test_the_rate_is_the_latest_one_on_or_before_the_date(): void
    {
        $usd = Currency::where('code', 'USD')->first();
        ExchangeRate::create(['currency_id' => $usd->id, 'date' => '2026-09-01', 'rate' => '48.250000']);
        ExchangeRate::create(['currency_id' => $usd->id, 'date' => '2026-09-15', 'rate' => '48.900000']);

        $this->assertSame('48.250000', (string) $this->currencies->rate($usd, CarbonImmutable::parse('2026-09-14')));
        $this->assertSame('48.900000', (string) $this->currencies->rate($usd, CarbonImmutable::parse('2026-09-15')));
    }

    public function test_a_missing_rate_is_an_error_not_a_silent_one(): void
    {
        $usd = Currency::where('code', 'USD')->first();

        $this->expectException(MissingExchangeRate::class);

        $this->currencies->rate($usd, CarbonImmutable::parse('2026-01-01'));
    }

    public function test_currency_names_fall_back_to_arabic(): void
    {
        $currency = Currency::create(['code' => 'XAA', 'symbol' => 'x', 'name' => ['ar' => 'عملة']]);

        app()->setLocale('en');

        $this->assertSame('عملة', $currency->fresh()->name);
    }
}
