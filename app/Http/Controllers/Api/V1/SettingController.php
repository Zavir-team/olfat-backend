<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Settings\SettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class SettingController extends Controller
{
    public function __construct(private readonly SettingsService $settingsService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $group = $request->input('group');

        return ApiResponse::success(
            $this->settingsService->all($group)
        );
    }

    public function show(string $key): JsonResponse
    {
        $settings = $this->settingsService->all();
        $setting = $settings->firstWhere('key', $key);

        if (! $setting) {
            return ApiResponse::error('Setting not found.', null, 404);
        }

        return ApiResponse::success($setting);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $data = $request->validate([
            'value' => ['present'],
        ]);

        try {
            $setting = $this->settingsService->update($key, $data['value']);
        } catch (Throwable $e) {
            return ApiResponse::error('Setting not found or could not be updated.', null, 404);
        }

        return ApiResponse::success($setting, 'Setting updated');
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array', 'min:1'],
        ]);

        try {
            $updated = $this->settingsService->updateMany($data['settings']);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Throwable $e) {
            report($e);

            return ApiResponse::error('Settings could not be updated.', null, 500);
        }

        return ApiResponse::success($updated->values(), 'Settings updated');
    }
}
