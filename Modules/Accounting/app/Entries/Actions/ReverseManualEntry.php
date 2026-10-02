<?php

namespace Modules\Accounting\Entries\Actions;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Posting\ReverseJournalEntry;

/**
 * Reverses a posted manual entry. Entries produced by documents are reversed by cancelling
 * the document, so the document and its entries never disagree.
 */
class ReverseManualEntry
{
    public function __construct(private readonly ReverseJournalEntry $reverse) {}

    public function handle(User $actor, JournalEntry $entry, CarbonImmutable $date, string $reason): JournalEntry
    {
        Gate::forUser($actor)->authorize('accounting.entries.reverse');

        if ($entry->source_type !== null) {
            throw ValidationException::withMessages(['entry' => __('accounting::entries.reverse_from_document')]);
        }

        return DB::transaction(fn () => $this->reverse->handle($entry, $date, $reason, $actor));
    }
}
