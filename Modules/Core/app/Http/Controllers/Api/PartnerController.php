<?php

namespace Modules\Core\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Http\Resources\PartnerResource;
use Modules\Core\Models\Partner;
use Modules\Core\Partners\Actions\DeletePartner;
use Modules\Core\Partners\Actions\SavePartner;
use Modules\Core\Partners\PartnerData;

class PartnerController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('core.partners.view');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'in:customers,suppliers'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $partners = Partner::query()
            ->visibleTo($request->user())
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('tax_number', 'like', "%{$search}%")))
            ->when(($filters['role'] ?? null) === 'customers', fn ($q) => $q->customers())
            ->when(($filters['role'] ?? null) === 'suppliers', fn ($q) => $q->suppliers())
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', (bool) $filters['is_active']))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 25);

        return PartnerResource::collection($partners);
    }

    public function show(Request $request, int $partner): PartnerResource
    {
        Gate::authorize('core.partners.view');

        return new PartnerResource(Partner::visibleTo($request->user())->findOrFail($partner));
    }

    public function store(Request $request, SavePartner $action): JsonResponse
    {
        $data = PartnerData::fromArray($request->validate(PartnerData::rules()));

        return (new PartnerResource($action->handle($request->user(), $data)))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(Request $request, int $partner, SavePartner $action): PartnerResource
    {
        $model = Partner::visibleTo($request->user())->findOrFail($partner);
        $data = PartnerData::fromArray($request->validate(PartnerData::rules()));

        return new PartnerResource($action->handle($request->user(), $data, $model));
    }

    public function destroy(Request $request, int $partner, DeletePartner $action): Response
    {
        $action->handle($request->user(), Partner::visibleTo($request->user())->findOrFail($partner));

        return response()->noContent();
    }
}
