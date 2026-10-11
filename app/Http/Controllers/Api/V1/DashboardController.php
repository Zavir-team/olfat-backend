<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Report\ReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly ReportService $reportService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $locationId = $request->filled('location_id') ? $request->integer('location_id') : null;

        return ApiResponse::success(
            $this->reportService->dashboard($locationId)
        );
    }
}
