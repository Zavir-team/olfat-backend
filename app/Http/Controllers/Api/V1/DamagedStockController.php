<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StoreDamagedStockRequest;
use App\Models\DamagedStock;
use App\Services\Stock\DamagedStockService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class DamagedStockController extends Controller
{
    public function __construct(private readonly DamagedStockService $damagedStockService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = DamagedStock::query()
            ->with(['variant.product', 'location', 'user'])
            ->latest('damaged_at')
            ->latest('id');

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->integer('location_id'));
        }

        if ($request->filled('product_variant_id')) {
            $query->where('product_variant_id', $request->integer('product_variant_id'));
        }

        return ApiResponse::success($query->paginate($request->integer('per_page', 20)));
    }

    public function store(StoreDamagedStockRequest $request): JsonResponse
    {
        try {
            $record = $this->damagedStockService->create([
                ...$request->validated(),
                'user' => $request->user(),
            ]);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Throwable $e) {
            report($e);

            return ApiResponse::error('Damaged stock could not be recorded.', null, 500);
        }

        return ApiResponse::success($record, 'Damaged stock recorded', 201);
    }

    public function show(DamagedStock $damagedStock): JsonResponse
    {
        return ApiResponse::success(
            $damagedStock->load(['variant.product', 'location', 'user'])
        );
    }
}
