<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Availability probe for uptime monitors and the hosting platform.
 *
 * Returns 200 when the database and cache are reachable, 503 otherwise.
 * Intentionally exposes no configuration or data.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(function (): void {
                DB::connection()->getPdo();
                DB::select('select 1');
            }),
            'cache' => $this->check(function (): void {
                $key = 'health:'.Str::random(8);
                Cache::put($key, 1, 10);

                if (Cache::get($key) !== 1) {
                    throw new \RuntimeException('Cache round-trip failed.');
                }

                Cache::forget($key);
            }),
        ];

        $healthy = ! in_array(false, array_column($checks, 'ok'), true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    /**
     * @param  callable(): void  $probe
     * @return array{ok: bool, error?: string}
     */
    private function check(callable $probe): array
    {
        try {
            $probe();

            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => class_basename($e)];
        }
    }
}
