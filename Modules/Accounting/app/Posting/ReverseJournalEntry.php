<?php

namespace Modules\Accounting\Posting;

use App\Models\User;
use Carbon\CarbonImmutable;
use Modules\Accounting\Exceptions\PostingException;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalLine;
use Modules\Core\Support\Attributes\ModuleApi;
use Modules\Core\Support\TransactionGuard;

/**
 * Cancels a posted entry by posting its mirror image; the original stays untouched
 * except for the link to its reversal.
 */
#[ModuleApi]
class ReverseJournalEntry
{
    public function __construct(private readonly PostJournalEntry $post) {}

    public function handle(JournalEntry $entry, CarbonImmutable $date, string $reason, ?User $postedBy = null): JournalEntry
    {
        TransactionGuard::assertActive('ReverseJournalEntry');

        $entry = JournalEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();

        if (! $entry->isPosted()) {
            throw PostingException::because('reverse_draft');
        }

        if ($entry->reversed_by_id !== null || $entry->reversal_of_id !== null) {
            throw PostingException::because('already_reversed', ['number' => $entry->number]);
        }

        if ($date->lt($entry->date)) {
            throw PostingException::because('reversal_before_original', ['date' => $entry->date->toDateString()], 'date');
        }

        $lines = $entry->lines->map(fn (JournalLine $line) => (new JournalLineData(
            accountId: $line->account_id,
            debit: $line->debit,
            credit: $line->credit,
            partnerId: $line->partner_id,
            branchId: $line->branch_id,
            currencyId: $line->currency_id,
            amountCurrency: $line->amount_currency,
            dueDate: $line->due_date,
            description: $line->description,
        ))->reversed())->all();

        $reversal = $this->post->handle(new JournalEntryData(
            date: $date,
            branchId: $entry->branch_id,
            journalType: $entry->journal_type,
            lines: $lines,
            description: $reason,
            source: $entry->source_type ? $entry->source : null,
            postedBy: $postedBy,
            reversalOfId: $entry->id,
        ));

        $entry->update(['reversed_by_id' => $reversal->id]);

        return $reversal;
    }
}
