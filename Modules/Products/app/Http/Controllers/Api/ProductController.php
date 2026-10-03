<?php

namespace Modules\Products\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Modules\Products\Http\Resources\ProductResource;
use Modules\Products\Models\Product;
use Modules\Products\Support\ProductLookup;

/**
 * Read-only catalogue for the mobile app; products are edited from the dashboard.
 */
class ProductController
{
    public function index(Request $request, ProductLookup $lookup): AnonymousResourceCollection
    {
        Gate::authorize('products.products.view');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $products = $lookup->search($filters['search'] ?? '')
            ->with(['units.unit', 'units.barcodes', 'baseUnit'])
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->orderBy('sku')
            ->paginate($filters['per_page'] ?? 25);

        return ProductResource::collection($products);
    }

    public function show(int $product): ProductResource
    {
        Gate::authorize('products.products.view');

        return new ProductResource(Product::with(['units.unit', 'units.barcodes', 'baseUnit'])->findOrFail($product));
    }

    /**
     * Scanner lookup: the product and the unit the barcode stands for.
     */
    public function barcode(string $barcode, ProductLookup $lookup): JsonResponse
    {
        Gate::authorize('products.products.view');

        $match = $lookup->byBarcode($barcode) ?? abort(404);

        return response()->json([
            'data' => [
                'product' => new ProductResource($match['product']->load(['units.unit', 'units.barcodes', 'baseUnit'])),
                'unit_id' => $match['unit']->unit_id,
            ],
        ]);
    }
}
