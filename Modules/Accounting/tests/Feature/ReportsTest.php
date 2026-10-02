<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Ledger\Ledger;
use Modules\Accounting\Posting\JournalLineData as Line;
use Modules\Accounting\Tests\Concerns\PostsEntries;
use Modules\Core\Models\Partner;
use Tests\Concerns\InstallsErp;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use InstallsErp, PostsEntries, RefreshDatabase;

    private Partner $customer;

    private CarbonImmutable $start;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installErp();
        $this->customer = Partner::factory()->create(['name' => 'Nile Traders']);
        $this->start = CarbonImmutable::now()->startOfYear();

        $receivable = $this->account('1203')->id;
        $this->postEntry([
            Line::debit($receivable, '1000', partnerId: $this->customer->id, description: 'Invoice A'),
            Line::credit($this->account('4101')->id, '1000'),
        ], $this->start->toDateString());
        $this->postEntry([
            Line::debit($receivable, '400', partnerId: $this->customer->id, description: 'Invoice B'),
            Line::credit($this->account('4101')->id, '400'),
        ], $this->start->addMonth()->toDateString());
        $this->postEntry([
            Line::debit($this->account('120101')->id, '700'),
            Line::credit($receivable, '700', partnerId: $this->customer->id, description: 'Payment'),
        ], $this->start->addMonth()->addDays(5)->toDateString());
    }

    public function test_the_statement_runs_the_balance_from_the_opening(): void
    {
        $statement = app(Ledger::class)->statement(
            [$this->account('1203')->id],
            $this->start->addMonth(),
            $this->start->addMonths(2),
            partnerId: $this->customer->id,
        );

        $this->assertSame('1000.0000', (string) $statement['opening']);
        $this->assertSame(['1400.0000', '700.0000'], $statement['rows']->map(fn ($r) => (string) $r->balance)->all());
        $this->assertSame('700.0000', (string) $statement['closing']);
    }

    public function test_report_pages_render_with_figures(): void
    {
        $this->actingAs($this->admin);
        $range = ['from' => $this->start->toDateString(), 'to' => $this->start->addMonths(3)->toDateString()];

        $this->get('/accounting/reports/trial-balance?'.http_build_query($range))
            ->assertOk()->assertSee('1,400.00')->assertSee('700.00');

        $this->get('/accounting/reports/general-ledger?'.http_build_query($range + ['accountId' => $this->account('4101')->id]))
            ->assertOk()->assertSee('-1,400.00');

        $this->get('/accounting/reports/partner-statement?'.http_build_query($range + ['partnerId' => $this->customer->id]))
            ->assertOk()->assertSee('Nile Traders')->assertSee('Invoice A')->assertSee('700.00');
    }

    public function test_reports_need_the_reports_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/accounting/reports/trial-balance')->assertForbidden();
    }
}
