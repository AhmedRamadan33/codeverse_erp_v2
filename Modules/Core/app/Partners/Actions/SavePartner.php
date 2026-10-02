<?php

namespace Modules\Core\Partners\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Partner;
use Modules\Core\Partners\PartnerData;

/**
 * Creates a partner, or updates one when $partner is given. Used by the web UI and the API.
 */
class SavePartner
{
    public function handle(User $actor, PartnerData $data, ?Partner $partner = null): Partner
    {
        Gate::forUser($actor)->authorize($partner ? 'core.partners.update' : 'core.partners.create');

        if (! $data->isCustomer && ! $data->isSupplier) {
            throw ValidationException::withMessages(['is_customer' => __('core::partners.role_required')]);
        }

        if ($data->branchId !== null && ! $actor->canAccessBranch($data->branchId)) {
            throw ValidationException::withMessages(['branch_id' => __('core::partners.branch_not_allowed')]);
        }

        return DB::transaction(function () use ($data, $partner) {
            $partner ??= new Partner;
            $partner->fill($data->toAttributes())->save();

            return $partner;
        });
    }
}
