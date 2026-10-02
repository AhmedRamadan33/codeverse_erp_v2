<?php

namespace Modules\Core\Partners\Actions;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Partner;

/**
 * Deletes a partner that nothing references yet. Once documents or journal lines point
 * to a partner (restrict foreign keys), it can only be deactivated.
 */
class DeletePartner
{
    public function handle(User $actor, Partner $partner): void
    {
        Gate::forUser($actor)->authorize('core.partners.delete');

        try {
            DB::transaction(fn () => $partner->delete());
        } catch (QueryException $e) {
            // 1451: cannot delete a parent row, a foreign key constraint fails.
            if (($e->errorInfo[1] ?? null) === 1451) {
                throw ValidationException::withMessages(['partner' => __('core::partners.in_use')]);
            }

            throw $e;
        }
    }
}
