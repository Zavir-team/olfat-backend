<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'is_active', 'role', 'created_at', 'updated_at']);

        return ApiResponse::success($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::query()->create($request->validated());

        return ApiResponse::success($user, 'User created', 201);
    }

    public function show(User $user): JsonResponse
    {
        return ApiResponse::success($user->only([
            'id', 'name', 'email', 'phone', 'is_active', 'role', 'created_at', 'updated_at',
        ]));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $wasActive = (bool) $user->is_active;
        $user->fill($request->validated());
        $user->save();

        if ($wasActive && $user->is_active === false) {
            $user->tokens()->delete();
        }

        return ApiResponse::success($user->fresh(), 'User updated');
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === auth()->id()) {
            return ApiResponse::error('You cannot delete your own account.', null, 422);
        }

        $adminCount = User::query()
            ->where('role', UserRole::Admin)
            ->where('is_active', true)
            ->count();

        if ($user->role === UserRole::Admin && $adminCount <= 1) {
            return ApiResponse::error('Cannot delete the last active admin.', null, 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return ApiResponse::success(null, 'User deleted');
    }
}
