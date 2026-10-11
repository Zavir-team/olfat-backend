<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorkerPayment;
use App\Services\Worker\WorkerPaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class WorkerPaymentController extends Controller
{
    public function __construct(private readonly WorkerPaymentService $paymentService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = WorkerPayment::query()
            ->with(['worker', 'location', 'user'])
            ->latest('paid_at')
            ->latest('id');

        if ($request->filled('worker_id')) {
            $query->where('worker_id', $request->integer('worker_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('paid_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('paid_at', '<=', $request->date('to'));
        }

        return ApiResponse::success($query->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'worker_id' => ['required', 'exists:workers,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_at' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $payment = $this->paymentService->create([
                ...$data,
                'user' => $request->user(),
            ]);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Throwable $e) {
            report($e);

            return ApiResponse::error('Worker payment could not be recorded.', null, 500);
        }

        return ApiResponse::success($payment, 'Worker payment recorded', 201);
    }

    public function show(WorkerPayment $workerPayment): JsonResponse
    {
        return ApiResponse::success($workerPayment->load(['worker', 'location', 'user']));
    }
}
