<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Accounting\Enums\EntryStatus;
use Modules\Accounting\Enums\JournalType;
use Modules\Accounting\Enums\PeriodStatus;
use Modules\Accounting\Exceptions\PostedEntryImmutable;
use Modules\Accounting\Exceptions\PostingException;
use Modules\Accounting\Models\FiscalPeriod;
use Modules\Accounting\Posting\JournalEntryData;
use Modules\Accounting\Posting\JournalLineData as Line;
use Modules\Accounting\Posting\PostJournalEntry;
use Modules\Accounting\Posting\ReverseJournalEntry;
use Modules\Accounting\Tests\Concerns\PostsEntries;
use Modules\Core\Models\Partner;
use Modules\Core\Settings\Settings;
use Tests\Concerns\InstallsErp;
use Tests\TestCase;

class PostJournalEntryTest extends TestCase
{
    use InstallsErp, PostsEntries, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installErp();
    }

    private function cashSale(string $amount = '1140.00'): array
    {
        return [
            Line::debit($this->account('120101')->id, $amount),
            Line::credit($this->account('4101')->id, '1000.00'),
            Line::credit($this->account('2103')->id, '140.00'),
        ];
    }

    private function assertRejected(callable $post, string $messageKey): void
    {
        try {
            $post();
            $this->fail("Expected the posting to be rejected with [{$messageKey}].");
        } catch (PostingException $e) {
            $this->assertStringContainsString(
                strtok(__('accounting::posting.'.$messageKey), ':'),
                collect($e->errors())->flatten()->first(),
            );
        }
    }

    public function test_a_balanced_entry_is_posted_numbered_and_frozen(): void
    {
        $entry = $this->postEntry($this->cashSale(), '2026-03-15');

        $this->assertSame(EntryStatus::Posted, $entry->status);
        $this->assertSame('JE-2026-000001', $entry->number);
        $this->assertCount(3, $entry->lines);
        $this->assertSame('1140.0000', (string) $entry->lines[0]->debit);
        $this->assertSame($this->branch->id, $entry->lines[0]->branch_id);
        $this->assertLedgerBalanced();
    }

    public function test_an_unbalanced_entry_is_rejected_and_nothing_is_saved(): void
    {
        $this->assertRejected(fn () => $this->postEntry($this->cashSale('1139.99')), 'unbalanced');

        $this->assertSame(0, DB::table('journal_entries')->count());
    }

    public function test_each_line_has_exactly_one_positive_side(): void
    {
        $this->assertRejected(fn () => $this->postEntry([
            new Line($this->account('120101')->id, '10', '10'),
            Line::credit($this->account('4101')->id, '0'),
        ]), 'one_side_per_line');
    }

    public function test_group_accounts_cannot_be_posted_to(): void
    {
        $this->assertRejected(fn () => $this->postEntry([
            Line::debit($this->account('1201')->id, '10'),
            Line::credit($this->account('4101')->id, '10'),
        ]), 'account_not_postable');
    }

    public function test_receivable_lines_need_a_partner(): void
    {
        $this->assertRejected(fn () => $this->postEntry([
            Line::debit($this->account('1203')->id, '10'),
            Line::credit($this->account('4101')->id, '10'),
        ]), 'partner_required');

        $customer = Partner::factory()->create();
        $entry = $this->postEntry([
            Line::debit($this->account('1203')->id, '10', partnerId: $customer->id),
            Line::credit($this->account('4101')->id, '10'),
        ]);

        $this->assertSame($customer->id, $entry->lines[0]->partner_id);
    }

    public function test_more_than_four_decimals_is_rejected(): void
    {
        $this->assertRejected(fn () => $this->postEntry([
            Line::debit($this->account('120101')->id, '10.00001'),
            Line::credit($this->account('4101')->id, '10.00001'),
        ]), 'too_many_decimals');
    }

    public function test_dates_outside_any_fiscal_year_or_in_a_closed_period_are_rejected(): void
    {
        $this->assertRejected(fn () => $this->postEntry($this->cashSale(), '2020-01-01'), 'no_fiscal_period');

        FiscalPeriod::whereDate('start_date', CarbonImmutable::now()->startOfYear())->update(['status' => PeriodStatus::Closed]);

        $this->assertRejected(fn () => $this->postEntry($this->cashSale(), CarbonImmutable::now()->startOfYear()->toDateString()), 'period_closed');
    }

    public function test_the_lock_date_needs_the_adjusting_permission(): void
    {
        $date = CarbonImmutable::now()->startOfYear()->addDays(5);
        app(Settings::class)->set('accounting.lock_date', $date->addDay()->toDateString());

        $clerk = User::factory()->create();
        $post = fn (User $by) => DB::transaction(fn () => app(PostJournalEntry::class)->handle(new JournalEntryData(
            date: $date, branchId: $this->branch->id, journalType: JournalType::General, lines: $this->cashSale(), postedBy: $by,
        )));

        $this->assertRejected(fn () => $post($clerk), 'before_lock_date');

        $clerk->givePermissionTo('accounting.entries.post_before_lock');
        $this->assertTrue($post($clerk->fresh())->isPosted());
    }

    public function test_posting_requires_the_callers_transaction(): void
    {
        $this->expectException(LogicException::class);

        app(PostJournalEntry::class)->handle(new JournalEntryData(
            date: CarbonImmutable::now(), branchId: $this->branch->id, journalType: JournalType::General, lines: $this->cashSale(),
        ));
    }

    public function test_posted_entries_and_lines_cannot_be_changed_or_deleted(): void
    {
        $entry = $this->postEntry($this->cashSale());

        foreach ([
            fn () => $entry->update(['description' => 'changed']),
            fn () => $entry->delete(),
            fn () => $entry->lines[0]->update(['debit' => '1']),
            fn () => $entry->lines[0]->delete(),
        ] as $case => $change) {
            try {
                $change();
                $this->fail("A posted entry was changed (case {$case}).");
            } catch (PostedEntryImmutable) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_reversal_mirrors_the_entry_and_links_both_ways(): void
    {
        $customer = Partner::factory()->create();
        $original = $this->postEntry([
            Line::debit($this->account('1203')->id, '500', partnerId: $customer->id),
            Line::credit($this->account('4101')->id, '500'),
        ]);

        $reversal = DB::transaction(fn () => app(ReverseJournalEntry::class)->handle($original, CarbonImmutable::now(), 'Wrong customer', $this->admin));

        $this->assertSame($original->id, $reversal->reversal_of_id);
        $this->assertSame($reversal->id, $original->fresh()->reversed_by_id);
        $this->assertSame('500.0000', (string) $reversal->lines[0]->credit);
        $this->assertSame($customer->id, $reversal->lines[0]->partner_id);

        $balance = DB::table('journal_lines')->where('account_id', $this->account('1203')->id)->selectRaw('sum(debit) - sum(credit) as b')->value('b');
        $this->assertEquals(0, $balance);
        $this->assertLedgerBalanced();

        $this->assertRejected(fn () => DB::transaction(fn () => app(ReverseJournalEntry::class)->handle($original->fresh(), CarbonImmutable::now(), 'again')), 'already_reversed');
    }
}
