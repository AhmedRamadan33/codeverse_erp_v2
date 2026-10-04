<?php

namespace Modules\Sales\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Documents\DocumentStatus;
use Modules\Sales\Actions\InvoiceActions;
use Modules\Sales\Http\Resources\SalesDocumentResource;
use Modules\Sales\Models\SalesInvoice;

/**
 * Sales invoices for the mobile app: the same actions as the screens (core-design.md §13).
 */
class InvoiceController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('sales.invoices.view');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:draft,posted,cancelled'],
            'partner_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $user = $request->user();

        return SalesDocumentResource::collection(SalesInvoice::with('partner')
            ->when(! $user->can('core.branches.all_access'), fn ($q) => $q->whereIn('branch_id', $user->branches()->select('branches.id')))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('number', 'like', "%{$search}%")
                ->orWhereHas('partner', fn ($p) => $p->where('name', 'like', "%{$search}%"))))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['partner_id'] ?? null, fn ($q, $id) => $q->where('partner_id', $id))
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 25));
    }

    public function show(Request $request, int $invoice): SalesDocumentResource
    {
        Gate::authorize('sales.invoices.view');

        return new SalesDocumentResource($this->find($request, $invoice)->load(['lines.product', 'partner']));
    }

    public function store(Request $request, InvoiceActions $actions): JsonResponse
    {
        $invoice = $actions->save($request->user(), $request->validate(InvoiceActions::rules()));

        return (new SalesDocumentResource($invoice->load(['lines.product', 'partner'])))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(Request $request, int $invoice, InvoiceActions $actions): SalesDocumentResource
    {
        $model = $this->find($request, $invoice);
        $model = $actions->save($request->user(), $request->validate(InvoiceActions::rules()), $model);

        return new SalesDocumentResource($model->load(['lines.product', 'partner']));
    }

    public function destroy(Request $request, int $invoice, InvoiceActions $actions): Response
    {
        $actions->delete($request->user(), $this->find($request, $invoice));

        return response()->noContent();
    }

    /**
     * Posting an invoice that is no longer a draft answers 409, so a retried request is harmless.
     * Over the credit limit in "warn" mode it answers 422 credit_limit_confirm; resend with
     * confirm_over_limit: true.
     */
    public function post(Request $request, int $invoice, InvoiceActions $actions): SalesDocumentResource
    {
        $data = $request->validate(['confirm_over_limit' => ['boolean']]);
        $model = $this->find($request, $invoice);
        abort_if($model->status !== DocumentStatus::Draft, Response::HTTP_CONFLICT, __('core::documents.not_draft'));

        $posted = $actions->post($request->user(), $model, (bool) ($data['confirm_over_limit'] ?? false));

        return new SalesDocumentResource($posted->load(['lines.product', 'partner']));
    }

    public function cancel(Request $request, int $invoice, InvoiceActions $actions): SalesDocumentResource
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return new SalesDocumentResource($actions->cancel($request->user(), $this->find($request, $invoice), $data['reason'])->load(['lines.product', 'partner']));
    }

    private function find(Request $request, int $id): SalesInvoice
    {
        $invoice = SalesInvoice::findOrFail($id);
        abort_unless($request->user()->canAccessBranch($invoice->branch_id), 404);

        return $invoice;
    }
}
