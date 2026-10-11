<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()->with('variants')->orderBy('name');

        if ($request->filled('module')) {
            $query->where('module', $request->string('module'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        return ApiResponse::success($query->paginate($request->integer('per_page', 20)));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = DB::transaction(function () use ($request) {
            $data = $request->safe()->except('variants');
            $product = Product::query()->create($data);

            foreach ($request->input('variants', []) as $variant) {
                $product->variants()->create($variant);
            }

            return $product->load('variants');
        });

        return ApiResponse::success($product, 'Product created', 201);
    }

    public function show(Product $product): JsonResponse
    {
        return ApiResponse::success($product->load('variants'));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->fill($request->validated());
        $product->save();

        return ApiResponse::success($product->fresh('variants'), 'Product updated');
    }

    public function destroy(Product $product): JsonResponse
    {
        $hasStock = $product->variants()
            ->whereHas('stockBalances', fn ($q) => $q->where('quantity', '!=', 0))
            ->exists();

        if ($hasStock) {
            return ApiResponse::error(
                'Cannot delete a product that still has stock. Deactivate it instead.',
                null,
                422
            );
        }

        $hasMovements = $product->variants()
            ->whereHas('stockMovements')
            ->exists();

        if ($hasMovements) {
            return ApiResponse::error(
                'Cannot delete a product with stock history. Deactivate it instead.',
                null,
                422
            );
        }

        $product->delete();

        return ApiResponse::success(null, 'Product deleted');
    }
}
