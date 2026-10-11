<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StoreCustomerPaymentRequest;
use App\Models\CustomerPayment;
use App\Services\Payment\CustomerPaymentService;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class CustomerPaymentController extends Controller
{
    public function __construct(private readonly CustomerPaymentService $paymentService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = CustomerPayment::query()
            ->with(['customer', 'location', 'user', 'sale'])
            ->latest('paid_at')
            ->latest('id');

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->integer('location_id'));
        }

        return ApiResponse::success($query->paginate(Pagination::perPage($request)));
    }

    public function store(StoreCustomerPaymentRequest $request): JsonResponse
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

            return ApiResponse::error('Customer payment could not be created.', null, 500);
        }

        return ApiResponse::success($payment, 'Customer payment recorded', 201);
    }

    public function show(CustomerPayment $customerPayment): JsonResponse
    {
        return ApiResponse::success(
            $customerPayment->load(['customer', 'location', 'user', 'sale'])
        );
    }
}
