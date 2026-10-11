<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StoreSupplierPaymentRequest;
use App\Models\SupplierPayment;
use App\Services\Payment\SupplierPaymentService;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class SupplierPaymentController extends Controller
{
    public function __construct(private readonly SupplierPaymentService $paymentService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = SupplierPayment::query()
            ->with(['supplier', 'location', 'user', 'purchase'])
            ->latest('paid_at')
            ->latest('id');

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->integer('supplier_id'));
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->integer('location_id'));
        }

        return ApiResponse::success($query->paginate(Pagination::perPage($request)));
    }

    public function store(StoreSupplierPaymentRequest $request): JsonResponse
    {
        try {
            $payment = $this->paymentService->create([
                ...$request->validated(),
                'user' => $request->user(),
            ]);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Throwable $e) {
            report($e);

            return ApiResponse::error('Supplier payment could not be created.', null, 500);
        }

        return ApiResponse::success($payment, 'Supplier payment recorded', 201);
    }

    public function show(SupplierPayment $supplierPayment): JsonResponse
    {
        return ApiResponse::success(
            $supplierPayment->load(['supplier', 'location', 'user', 'purchase'])
        );
    }
}
