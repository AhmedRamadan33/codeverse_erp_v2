<?php

namespace Modules\Accounting\Tests\Feature;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Accounting\Enums\AccountSubtype;
use Modules\Accounting\Ledger\Aging;
use Modules\Accounting\Livewire\Reports\AgedBalances;
use Modules\Accounting\Posting\JournalLineData;
use Modules\Accounting\Reconciliation\Reconciler;
use Modules\Accounting\Tests\Concerns\PostsEntries;
use Modules\Core\Models\Partner;
use Tests\Concerns\InstallsErp;
use Tests\TestCase;

class AgingTest extends TestCase
{
    use InstallsErp, PostsEntries, RefreshDatabase;

    private Partner $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installErp();
        $this->customer = Partner::factory()->create(['name' => 'Aging Customer']);
    }

    /**
     * An "invoice": Dr receivable (with due date) / Cr revenue.
     */
    private function invoice(string $amount, string $dueDate): int
    {
        $entry = $this->postEntry([
            new JournalLineData($this->account('1203')->id, debit: $amount, partnerId: $this->customer->id, dueDate: CarbonImmutable::parse($dueDate)),
            new JournalLineData($this->account('4101')->id, credit: $amount),
        ]);

        return $entry->lines()->where('debit', '>', 0)->value('id');
    }

    public function test_open_invoices_fall_in_buckets_and_unallocated_payments_reduce_the_balance(): void
    {
        $this->invoice('100', 'today +5 days');
        $this->invoice('200', 'today -10 days');
        $old = $this->invoice('300', 'today -45 days');
        $this->invoice('400', 'today -120 days');

        // A payment of 350: 250 allocated to the 45-day invoice, 100 left unallocated.
        $payment = $this->postEntry([
            new JournalLineData($this->account('120101')->id, debit: '350'),
            new JournalLineData($this->account('1203')->id, credit: '350', partnerId: $this->customer->id),
        ]);
        DB::transaction(fn () => app(Reconciler::class)->reconcile(
            \Modules\Accounting\Models\JournalLine::findOrFail($old),
            $payment->lines()->where('credit', '>', 0)->firstOrFail(),
            BigDecimal::of('250'),
        ));

        $row = app(Aging::class)->report(AccountSubtype::Receivable, CarbonImmutable::today())->sole();

        $this->assertSame('100.0000', (string) $row->amounts['current']->toScale(4));
        $this->assertSame('200.0000', (string) $row->amounts['d30']->toScale(4));
        $this->assertSame('50.0000', (string) $row->amounts['d60']->toScale(4));
        $this->assertSame('0', (string) $row->amounts['d90']);
        $this->assertSame('400.0000', (string) $row->amounts['older']->toScale(4));
        $this->assertSame('100.0000', (string) $row->amounts['unallocated']->toScale(4));
        // Equals the ledger balance: 1000 invoiced − 350 paid.
        $this->assertSame('650.0000', (string) $row->total()->toScale(4));
        $this->assertTrue(app(Aging::class)->report(AccountSubtype::Payable, CarbonImmutable::today())->isEmpty());

        $this->actingAs($this->admin);
        Livewire::test(AgedBalances::class)->assertSee('Aging Customer')->assertSee('650.00');
    }
}
