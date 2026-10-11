<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ExternalParty;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalPartyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ExternalParty::query()->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return ApiResponse::success($query->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $party = ExternalParty::query()->create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::success($party, 'External party created', 201);
    }

    public function show(ExternalParty $externalParty): JsonResponse
    {
        return ApiResponse::success($externalParty->load('debts'));
    }

    public function update(Request $request, ExternalParty $externalParty): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $externalParty->fill($data)->save();

        return ApiResponse::success($externalParty->fresh(), 'External party updated');
    }

    public function destroy(ExternalParty $externalParty): JsonResponse
    {
        $externalParty->delete();

        return ApiResponse::success(null, 'External party deleted');
    }
}
