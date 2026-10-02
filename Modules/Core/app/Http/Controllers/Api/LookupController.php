<?php

namespace Modules\Core\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Currencies\Currencies;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Currency;

/**
 * Reference data the mobile app needs to build its forms.
 */
class LookupController
{
    /**
     * Branches the signed-in user may work in.
     */
    public function branches(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = $user->can('core.branches.all_access') ? Branch::query() : $user->branches();

        return response()->json(['data' => $query->where('branches.is_active', true)->orderBy('code')->get()
            ->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->name,
                'is_default' => (bool) ($branch->pivot?->is_default ?? false),
            ])]);
    }

    public function currencies(Currencies $currencies): JsonResponse
    {
        $base = $currencies->base()->code;

        return response()->json(['data' => Currency::where('is_active', true)->orderBy('code')->get()
            ->map(fn (Currency $currency) => [
                'id' => $currency->id,
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
                'decimal_places' => $currency->decimal_places,
                'is_base' => $currency->code === $base,
            ])]);
    }
}
