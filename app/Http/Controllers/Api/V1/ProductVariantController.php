<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductVariant\StoreProductVariantRequest;
use App\Http\Requests\ProductVariant\UpdateProductVariantRequest;
use App\Models\ProductVariant;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductVariantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ProductVariant::query()->with('product')->orderBy('name');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->filled('module')) {
            $query->whereHas('product', fn ($q) => $q->where('module', $request->string('module')));
        }

        return ApiResponse::success($query->paginate($request->integer('per_page', 50)));
    }

    public function store(StoreProductVariantRequest $request): JsonResponse
    {
        $variant = ProductVariant::query()->create($request->validated());

        return ApiResponse::success($variant->load('product'), 'Variant created', 201);
    }

    public function show(ProductVariant $productVariant): JsonResponse
    {
        return ApiResponse::success($productVariant->load('product'));
    }

    public function update(UpdateProductVariantRequest $request, ProductVariant $productVariant): JsonResponse
    {
        $productVariant->fill($request->validated());
        $productVariant->save();

        return ApiResponse::success($productVariant->fresh('product'), 'Variant updated');
    }

    public function destroy(ProductVariant $productVariant): JsonResponse
    {
        $hasStock = $productVariant->stockBalances()
            ->where('quantity', '!=', 0)
            ->exists();

        if ($hasStock) {
            return ApiResponse::error(
                'Cannot delete a variant that still has stock. Deactivate it instead.',
                null,
                422
            );
        }

        if ($productVariant->stockMovements()->exists()) {
            return ApiResponse::error(
                'Cannot delete a variant with stock history. Deactivate it instead.',
                null,
                422
            );
        }

        $productVariant->delete();

        return ApiResponse::success(null, 'Variant deleted');
    }
}
