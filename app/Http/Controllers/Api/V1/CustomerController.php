<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Models\Customer;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query()->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        return ApiResponse::success($query->paginate($request->integer('per_page', 20)));
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = new Customer($request->validated());
        $customer->created_by = $request->user()->id;
        $customer->balance = 0;
        $customer->save();

        return ApiResponse::success($customer->fresh(), 'Customer created', 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        return ApiResponse::success($customer);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        // Balance is never updated from this endpoint — sales/payments own it.
        $customer->fill($request->validated());
        $customer->save();

        return ApiResponse::success($customer->fresh(), 'Customer updated');
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return ApiResponse::success(null, 'Customer deleted');
    }
}
