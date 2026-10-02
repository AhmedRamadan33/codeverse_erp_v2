<?php

namespace Modules\Core\Branches\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Branch;

/**
 * Creates or updates a branch. Branches are deactivated, never deleted, because
 * documents and settings keep pointing to them.
 */
class SaveBranch
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(?Branch $branch = null): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'alpha_dash', 'max:16', Rule::unique('branches', 'code')->ignore($branch)],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     */
    public function handle(User $actor, array $data, ?Branch $branch = null): Branch
    {
        Gate::forUser($actor)->authorize('core.branches.manage');

        $isActive = (bool) ($data['is_active'] ?? true);

        if ($branch !== null && $branch->is_active && ! $isActive && Branch::where('is_active', true)->count() === 1) {
            throw ValidationException::withMessages(['is_active' => __('core::branches.last_active')]);
        }

        return DB::transaction(function () use ($data, $branch, $isActive) {
            $branch ??= new Branch;
            $branch->fill([
                'name' => array_filter(['ar' => $data['name_ar'], 'en' => $data['name_en'] ?? null]),
                'code' => strtoupper($data['code']),
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_active' => $isActive,
            ])->save();

            return $branch;
        });
    }
}
