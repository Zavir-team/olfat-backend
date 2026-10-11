<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\AdjustStockRequest;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Services\Stock\StockService;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class StockController extends Controller
{
    public function __construct(private readonly StockService $stockService)
    {
    }

    public function balances(Request $request): JsonResponse
    {
        $query = StockBalance::query()
            ->with(['variant.product', 'location'])
            ->orderBy('id');

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->integer('location_id'));
        }

        if ($request->filled('product_variant_id')) {
            $query->where('product_variant_id', $request->integer('product_variant_id'));
        }

        if ($request->filled('module')) {
            $query->whereHas('variant.product', fn ($q) => $q->where('module', $request->string('module')));
        }

        return ApiResponse::success($query->paginate(Pagination::perPage($request, 50)));
    }

    public function movements(Request $request): JsonResponse
    {
        $query = StockMovement::query()
            ->with(['variant.product', 'location', 'user'])
            ->latest('occurred_at')
            ->latest('id');

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->integer('location_id'));
        }

        if ($request->filled('product_variant_id')) {
            $query->where('product_variant_id', $request->integer('product_variant_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('from')) {
            $query->where('occurred_at', '>=', $request->date('from')->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('occurred_at', '<=', $request->date('to')->endOfDay());
        }

        return ApiResponse::success($query->paginate(Pagination::perPage($request, 50)));
    }

    public function adjust(AdjustStockRequest $request): JsonResponse
    {
        try {
            $movement = $this->stockService->apply([
                'product_variant_id' => $request->integer('product_variant_id'),
                'location_id' => $request->integer('location_id'),
                'type' => StockMovementType::Adjustment,
                'quantity_change' => $request->input('quantity_change'),
                'unit' => $request->input('unit'),
                'quantity_in_unit' => $request->input('quantity_in_unit'),
                'user' => $request->user(),
                'notes' => $request->input('notes'),
                'occurred_at' => $request->input('occurred_at'),
                // Negative stock is never allowed from the public API.
                'allow_negative' => false,
            ]);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Throwable $e) {
            report($e);

            return ApiResponse::error('Stock adjustment failed.', null, 500);
        }

        return ApiResponse::success(
            $movement->load(['variant.product', 'location', 'user']),
            'Stock adjusted',
            201
        );
    }
}
