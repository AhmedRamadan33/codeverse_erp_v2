<?php

namespace Modules\Accounting\Tests\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\JournalType;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Posting\JournalEntryData;
use Modules\Accounting\Posting\JournalLineData;
use Modules\Accounting\Posting\PostJournalEntry;

trait PostsEntries
{
    protected function account(string $code): Account
    {
        return Account::where('code', $code)->firstOrFail();
    }

    /**
     * @param  JournalLineData[]  $lines
     */
    protected function postEntry(array $lines, string $date = 'today', JournalType $type = JournalType::General): JournalEntry
    {
        return DB::transaction(fn () => app(PostJournalEntry::class)->handle(new JournalEntryData(
            date: CarbonImmutable::parse($date),
            branchId: $this->branch->id,
            journalType: $type,
            lines: $lines,
            postedBy: $this->admin,
        )));
    }

    /**
     * Σ debit = Σ credit for every posted entry, and in total.
     */
    protected function assertLedgerBalanced(): void
    {
        $unbalanced = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.status', 'posted')
            ->groupBy('journal_entry_id')
            ->havingRaw('sum(debit) <> sum(credit)')
            ->pluck('journal_entry_id');

        $this->assertSame([], $unbalanced->all(), 'Unbalanced journal entries: '.$unbalanced->implode(', '));
    }
}
