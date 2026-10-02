<?php

namespace Modules\Accounting\Mappings\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Mappings\AccountResolver;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\AccountMapping;

/**
 * Changes which account a purpose posts to. Only future postings are affected.
 */
class SaveMapping
{
    public function __construct(private readonly AccountResolver $resolver) {}

    public function handle(User $actor, AccountMapping $mapping, int $accountId): AccountMapping
    {
        Gate::forUser($actor)->authorize('accounting.mappings.manage');

        $account = Account::findOrFail($accountId);

        if (! $account->isPostable()) {
            throw ValidationException::withMessages(['account_id' => __('accounting::mappings.not_postable')]);
        }

        DB::transaction(fn () => $mapping->update(['account_id' => $account->id]));
        $this->resolver->forget();

        return $mapping;
    }
}
