<?php

namespace Modules\Accounting\Posting;

use Modules\Accounting\Enums\EntryStatus;
use Modules\Accounting\Models\JournalEntry;
use Modules\Core\Sequences\NextNumber;
use Modules\Core\Support\Attributes\ModuleApi;
use Modules\Core\Support\TransactionGuard;

/**
 * Posts a balanced journal entry for a document. Called by the posting action of the
 * document's module, inside that action's transaction (core-design.md D2, §3.2).
 */
#[ModuleApi]
class PostJournalEntry
{
    public const SEQUENCE = 'accounting.journal_entry';

    public function __construct(
        private readonly LineValidator $validator,
        private readonly PeriodGuard $periods,
        private readonly NextNumber $numbers,
    ) {}

    public function handle(JournalEntryData $data): JournalEntry
    {
        TransactionGuard::assertActive('PostJournalEntry');

        $this->validator->validate($data->lines);
        $this->periods->assertOpen($data->date, $data->postedBy);

        $entry = JournalEntry::create([
            'date' => $data->date,
            'branch_id' => $data->branchId,
            'journal_type' => $data->journalType,
            'description' => $data->description,
            'source_type' => $data->source?->getMorphClass(),
            'source_id' => $data->source?->getKey(),
            'status' => EntryStatus::Draft,
            'reversal_of_id' => $data->reversalOfId,
            'created_by' => $data->postedBy?->id,
        ]);

        foreach (array_values($data->lines) as $i => $line) {
            $entry->lines()->create([
                'line_no' => $i + 1,
                'account_id' => $line->accountId,
                'debit' => $line->debit->toScale(4),
                'credit' => $line->credit->toScale(4),
                'currency_id' => $line->currencyId,
                'amount_currency' => $line->amountCurrency?->toScale(4),
                'partner_id' => $line->partnerId,
                'branch_id' => $line->branchId ?? $data->branchId,
                'due_date' => $line->dueDate,
                'description' => $line->description,
                'source_line_type' => $line->sourceLine?->getMorphClass(),
                'source_line_id' => $line->sourceLine?->getKey(),
            ]);
        }

        return $this->markPosted($entry, $data->postedBy?->id);
    }

    /**
     * Number and freeze an entry whose lines are already saved and validated.
     */
    public function markPosted(JournalEntry $entry, ?int $postedBy): JournalEntry
    {
        // The sequence lock is taken last (core-design.md §3.4).
        $entry->update([
            'number' => $this->numbers->handle(self::SEQUENCE, $entry->branch_id, $entry->date),
            'status' => EntryStatus::Posted,
            'posted_by' => $postedBy,
            'posted_at' => now(),
        ]);

        return $entry;
    }
}
