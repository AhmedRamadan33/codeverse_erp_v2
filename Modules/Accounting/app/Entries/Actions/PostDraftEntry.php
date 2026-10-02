<?php

namespace Modules\Accounting\Entries\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Accounting\Exceptions\PostingException;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Posting\JournalLineData;
use Modules\Accounting\Posting\LineValidator;
use Modules\Accounting\Posting\PeriodGuard;
use Modules\Accounting\Posting\PostJournalEntry;

/**
 * Posts a manual draft entry after applying the same rules as document postings.
 */
class PostDraftEntry
{
    public function __construct(
        private readonly LineValidator $validator,
        private readonly PeriodGuard $periods,
        private readonly PostJournalEntry $post,
    ) {}

    public function handle(User $actor, JournalEntry $draft): JournalEntry
    {
        Gate::forUser($actor)->authorize('accounting.entries.post');

        return DB::transaction(function () use ($actor, $draft) {
            $entry = JournalEntry::whereKey($draft->id)->lockForUpdate()->firstOrFail();

            if ($entry->isPosted()) {
                throw PostingException::because('already_posted', ['number' => $entry->number]);
            }

            $lines = $entry->lines->map(fn (JournalLine $line) => new JournalLineData(
                accountId: $line->account_id,
                debit: $line->debit,
                credit: $line->credit,
                partnerId: $line->partner_id,
                branchId: $line->branch_id,
            ))->all();

            $this->validator->validate($lines);
            $this->periods->assertOpen($entry->date, $actor);

            return $this->post->markPosted($entry, $actor->id);
        });
    }
}
