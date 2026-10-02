<?php

namespace Modules\Accounting\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Enums\PeriodStatus;
use Modules\Accounting\FiscalYears\Actions\CloseFiscalYear;
use Modules\Accounting\Ledger\Ledger;
use Modules\Accounting\Models\FiscalYear;
use Modules\Accounting\Posting\JournalLineData as Line;
use Modules\Accounting\Tests\Concerns\PostsEntries;
use Tests\Concerns\InstallsErp;
use Tests\TestCase;

class CloseFiscalYearTest extends TestCase
{
    use InstallsErp, PostsEntries, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installErp();
    }

    public function test_closing_moves_profit_to_retained_earnings_and_locks_the_year(): void
    {
        $year = FiscalYear::sole();
        $date = $year->start_date->addMonths(2)->toDateString();

        $this->postEntry([Line::debit($this->account('120101')->id, '5000'), Line::credit($this->account('4101')->id, '5000')], $date);
        $this->postEntry([Line::debit($this->account('5202')->id, '1200'), Line::credit($this->account('120101')->id, '1200')], $date);

        app(CloseFiscalYear::class)->handle($this->admin, $year);

        $ledger = app(Ledger::class);
        $this->assertTrue($ledger->accountBalance($this->account('4101'))->isZero());
        $this->assertTrue($ledger->accountBalance($this->account('5202'))->isZero());
        $this->assertSame('-3800.0000', (string) $ledger->accountBalance($this->account('3102')));
        $this->assertSame('3800.0000', (string) $ledger->accountBalance($this->account('120101')));

        $year->refresh();
        $this->assertSame(PeriodStatus::Closed, $year->status);
        $this->assertTrue($year->periods->every(fn ($p) => $p->status === PeriodStatus::Closed));
        $this->assertLedgerBalanced();

        $this->expectException(ValidationException::class);
        $this->postEntry([Line::debit($this->account('120101')->id, '1'), Line::credit($this->account('4101')->id, '1')], $date);
    }
}
