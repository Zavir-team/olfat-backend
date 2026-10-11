<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * HTTP trigger for the Laravel scheduler on platforms without a system cron
 * (e.g. Vercel Cron Jobs). Protected by a shared secret sent as a Bearer token,
 * which is exactly what Vercel sends when CRON_SECRET is configured.
 */
class SystemCronController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('app.cron_secret');

        if ($secret === '' || ! hash_equals($secret, (string) $request->bearerToken())) {
            abort(401, 'Invalid cron secret.');
        }

        $exitCode = Artisan::call('schedule:run', ['--no-interaction' => true]);

        return response()->json([
            'status' => $exitCode === 0 ? 'ok' : 'error',
            'exit_code' => $exitCode,
            'output' => trim(Artisan::output()),
            'ran_at' => now()->toIso8601String(),
        ], $exitCode === 0 ? 200 : 500);
    }
}
