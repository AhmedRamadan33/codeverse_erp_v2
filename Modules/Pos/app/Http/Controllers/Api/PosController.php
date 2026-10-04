<?php

namespace Modules\Pos\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Pos\Actions\ReceiptActions;
use Modules\Pos\Actions\ShiftActions;
use Modules\Pos\Enums\ShiftStatus;
use Modules\Pos\Http\Resources\ReceiptResource;
use Modules\Pos\Http\Resources\ShiftResource;
use Modules\Pos\Models\Receipt;
use Modules\Pos\Models\Register;
use Modules\Pos\Models\Shift;
use Modules\Products\Models\Product;
use Modules\Products\Support\ProductLookup;
use Modules\Sales\Pricing\PriceResolver;

/**
 * The mobile POS: the same actions as the selling screen (core-design.md §13).
 */
class PosController
{
    /**
     * Registers the user may open a shift on, and the payment methods to offer.
     */
    public function setup(Request $request): JsonResponse
    {
        Gate::authorize('pos.terminal.sell');
        $user = $request->user();

        $registers = Register::where('is_active', true)
            ->when(! $user->can('core.branches.all_access'), fn ($q) => $q->whereIn('branch_id', $user->branches()->select('branches.id')))
            ->withExists(['shifts as busy' => fn ($q) => $q->where('status', ShiftStatus::Open)])
            ->orderBy('code')
            ->get();

        return response()->json(['data' => [
            'registers' => $registers->map(fn (Register $r) => [
                'id' => $r->id, 'code' => $r->code, 'name' => $r->name, 'branch_id' => $r->branch_id,
                'cash_payment_method_id' => $r->cash_payment_method_id, 'busy' => (bool) $r->busy,
            ]),
            'payment_methods' => PaymentMethod::where('is_active', true)->orderBy('sort')->get()
                ->map(fn (PaymentMethod $m) => ['id' => $m->id, 'name' => $m->name, 'type' => $m->type->value]),
            'permissions' => [
                'prices_override' => $user->can('pos.prices.override'),
                'discounts_give' => $user->can('pos.discounts.give'),
                'returns_create' => $user->can('pos.returns.create'),
            ],
        ]]);
    }

    public function currentShift(Request $request, ShiftActions $shifts): JsonResponse
    {
        Gate::authorize('pos.terminal.sell');
        $shift = $shifts->current($request->user());

        return response()->json(['data' => $shift ? new ShiftResource($shift->load('register')) : null]);
    }

    public function openShift(Request $request, ShiftActions $shifts): JsonResponse
    {
        $data = $request->validate([
            'register_id' => ['required', 'integer'],
            'opening_float' => ['required', 'decimal:0,4', 'min:0'],
        ]);

        $shift = $shifts->open($request->user(), Register::findOrFail($data['register_id']), (string) $data['opening_float']);

        return (new ShiftResource($shift->load('register')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function showShift(Request $request, int $shift): ShiftResource
    {
        $model = Shift::with('register')->findOrFail($shift);
        if ($model->user_id !== $request->user()->id) {
            Gate::authorize('pos.shifts.view');
        }
        abort_unless($request->user()->canAccessBranch($model->branch_id), 404);

        return new ShiftResource($model);
    }

    public function closeShift(Request $request, int $shift, ShiftActions $shifts): ShiftResource
    {
        $data = $request->validate([
            'counted_cash' => ['required', 'decimal:0,4', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        $model = Shift::findOrFail($shift);
        abort_unless($request->user()->canAccessBranch($model->branch_id), 404);

        return new ShiftResource($shifts->close($request->user(), $model, (string) $data['counted_cash'], $data['notes'] ?? null)->load('register'));
    }

    /**
     * ?barcode= exact scan, or ?search= name/SKU; prices for the customer (or the cashier's register).
     */
    public function products(Request $request, ProductLookup $lookup, PriceResolver $prices, ShiftActions $shifts): JsonResponse
    {
        Gate::authorize('pos.terminal.sell');
        $filters = $request->validate([
            'barcode' => ['nullable', 'string', 'max:64'],
            'search' => ['nullable', 'string', 'max:100'],
            'partner_id' => ['nullable', 'integer'],
        ]);

        $listId = $prices->priceListFor($filters['partner_id'] ?? null) ?? $shifts->current($request->user())?->register->price_list_id;
        $row = fn (Product $product, ?int $unitId = null) => [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'tracking' => $product->tracking->value,
            'unit_id' => $unitId ?? $product->units->firstWhere('is_default_sale', true)?->unit_id ?? $product->base_unit_id,
            'units' => $product->units->map(fn ($u) => [
                'unit_id' => $u->unit_id, 'name' => $u->unit->name, 'factor' => (string) $u->factor,
                'price' => (string) $prices->price($product, $u->unit_id, $listId),
            ])->values(),
            'tax_rate' => $product->saleTax ? (string) $product->saleTax->rate : null,
        ];

        if (! empty($filters['barcode'])) {
            $hit = $lookup->byBarcode($filters['barcode']);
            if ($hit === null) {
                return response()->json(['data' => []]);
            }

            return response()->json(['data' => [$row($hit['product']->load(['units.unit', 'saleTax']), $hit['unit']->unit_id)]]);
        }

        $products = $lookup->search((string) ($filters['search'] ?? ''))
            ->with(['units.unit', 'saleTax'])->orderBy('sku')->limit(20)->get();

        return response()->json(['data' => $products->map(fn (Product $p) => $row($p))]);
    }

    public function sell(Request $request, ReceiptActions $actions): JsonResponse
    {
        $receipt = $actions->sell($request->user(), $request->validate(ReceiptActions::saleRules()));

        return $this->receiptResponse($receipt, Response::HTTP_CREATED);
    }

    public function showReceipt(Request $request, int $receipt): JsonResponse
    {
        $model = Receipt::findOrFail($receipt);
        if ($model->created_by !== $request->user()->id) {
            Gate::authorize('pos.receipts.view');
        }
        abort_unless($model->number !== null && $request->user()->canAccessBranch($model->branch_id), 404);

        return $this->receiptResponse($model);
    }

    public function returnReceipt(Request $request, int $receipt, ReceiptActions $actions): JsonResponse
    {
        $return = $actions->return($request->user(), Receipt::findOrFail($receipt), $request->validate(ReceiptActions::returnRules()));

        return $this->receiptResponse($return, Response::HTTP_CREATED);
    }

    private function receiptResponse(Receipt $receipt, int $status = Response::HTTP_OK): JsonResponse
    {
        return (new ReceiptResource($receipt->load(['lines.product', 'lines.unit', 'payments.method', 'partner'])))
            ->response()->setStatusCode($status);
    }
}
