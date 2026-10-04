<?php

namespace Modules\Sales\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Documents\DocumentStatus;
use Modules\Sales\Actions\ReturnActions;
use Modules\Sales\Http\Resources\SalesDocumentResource;
use Modules\Sales\Models\SalesInvoice;
use Modules\Sales\Models\SalesReturn;

class ReturnController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('sales.returns.view');

        $filters = $request->validate([
            'status' => ['nullable', 'in:draft,posted,cancelled'],
            'sales_invoice_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $user = $request->user();

        return SalesDocumentResource::collection(SalesReturn::with('partner')
            ->when(! $user->can('core.branches.all_access'), fn ($q) => $q->whereIn('branch_id', $user->branches()->select('branches.id')))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['sales_invoice_id'] ?? null, fn ($q, $id) => $q->where('sales_invoice_id', $id))
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 25));
    }

    public function show(Request $request, int $return): SalesDocumentResource
    {
        Gate::authorize('sales.returns.view');

        return new SalesDocumentResource($this->find($request, $return)->load(['lines.product', 'partner']));
    }

    /**
     * A draft return against a posted invoice.
     */
    public function store(Request $request, int $invoice, ReturnActions $actions): JsonResponse
    {
        $model = SalesInvoice::findOrFail($invoice);
        abort_unless($request->user()->canAccessBranch($model->branch_id), 404);

        $return = $actions->save($request->user(), $model, $request->validate(ReturnActions::rules()));

        return (new SalesDocumentResource($return->load(['lines.product', 'partner'])))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(Request $request, int $return, ReturnActions $actions): SalesDocumentResource
    {
        $model = $this->find($request, $return);
        $model = $actions->save($request->user(), $model->invoice, $request->validate(ReturnActions::rules()), $model);

        return new SalesDocumentResource($model->load(['lines.product', 'partner']));
    }

    public function destroy(Request $request, int $return, ReturnActions $actions): Response
    {
        $actions->delete($request->user(), $this->find($request, $return));

        return response()->noContent();
    }

    public function post(Request $request, int $return, ReturnActions $actions): SalesDocumentResource
    {
        $model = $this->find($request, $return);
        abort_if($model->status !== DocumentStatus::Draft, Response::HTTP_CONFLICT, __('core::documents.not_draft'));

        return new SalesDocumentResource($actions->post($request->user(), $model)->load(['lines.product', 'partner']));
    }

    public function cancel(Request $request, int $return, ReturnActions $actions): SalesDocumentResource
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return new SalesDocumentResource($actions->cancel($request->user(), $this->find($request, $return), $data['reason'])->load(['lines.product', 'partner']));
    }

    private function find(Request $request, int $id): SalesReturn
    {
        $return = SalesReturn::findOrFail($id);
        abort_unless($request->user()->canAccessBranch($return->branch_id), 404);

        return $return;
    }
}
