<?php

namespace Modules\Accounting\Entries\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\JournalEntry;

class DeleteDraftEntry
{
    public function handle(User $actor, JournalEntry $draft): void
    {
        Gate::forUser($actor)->authorize('accounting.entries.create');

        if ($draft->isPosted() || $draft->source_type !== null) {
            throw ValidationException::withMessages(['entry' => __('accounting::entries.not_editable')]);
        }

        DB::transaction(function () use ($draft) {
            $draft->lines()->delete();
            $draft->delete();
        });
    }
}
