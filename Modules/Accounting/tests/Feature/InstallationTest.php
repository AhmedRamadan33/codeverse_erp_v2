<?php

namespace Modules\Accounting\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Exceptions\PostingException;
use Modules\Accounting\Mappings\AccountResolver;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\AccountMapping;
use Modules\Accounting\Models\FiscalYear;
use Modules\Core\Models\InstalledModule;
use Modules\Core\Models\Partner;
use Tests\Concerns\InstallsErp;
use Tests\TestCase;

class InstallationTest extends TestCase
{
    use InstallsErp, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installErp();
    }

    public function test_accounting_is_installed_with_the_chart_mappings_and_current_fiscal_year(): void
    {
        $this->assertTrue(InstalledModule::whereKey('Accounting')->exists());

        $receivable = Account::firstWhere('code', '1203');
        $this->assertTrue($receivable->requires_partner);
        $this->assertTrue($receivable->is_system);
        $this->assertSame('العملاء', $receivable->getTranslation('name', 'ar'));
        $this->assertSame('12', $receivable->parent->code);

        $year = FiscalYear::with('periods')->sole();
        $this->assertTrue($year->start_date->equalTo(CarbonImmutable::now()->startOfYear()->startOfDay()));
        $this->assertCount(12, $year->periods);
    }

    public function test_every_mapping_points_to_a_postable_account(): void
    {
        foreach (AccountMapping::with('account')->get() as $mapping) {
            $this->assertTrue($mapping->account->isPostable(), "Mapping {$mapping->key} points to a group account.");
        }
    }

    public function test_the_resolver_prefers_the_most_specific_scope(): void
    {
        $resolver = app(AccountResolver::class);
        $partner = Partner::factory()->create();
        $special = Account::factory()->create(['code' => '120399']);

        $this->assertSame('1203', $resolver->resolve('sales.receivable', [$partner])->code);

        AccountMapping::create(['key' => 'sales.receivable', 'scope_type' => $partner->getMorphClass(), 'scope_id' => $partner->id, 'account_id' => $special->id]);
        $resolver->forget();

        $this->assertSame('120399', $resolver->resolve('sales.receivable', [$partner])->code);
        $this->assertSame('1203', $resolver->resolve('sales.receivable', [Partner::factory()->create()])->code);
    }

    public function test_an_unknown_mapping_key_is_a_clear_error(): void
    {
        $this->expectException(PostingException::class);

        app(AccountResolver::class)->resolve('no.such.key');
    }
}
