<?php

namespace App\Http\Middleware;

use App\Support\ProvinceScope;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class EnforceProvinceScope
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! ProvinceScope::isScopedWrite($request)) {
            return $next($request);
        }

        $userLocationId = ProvinceScope::userLocationId($request->user());

        // Users without a claimed province (e.g. legacy test fixtures) are not locked yet.
        // After login, every real user has a province and is restricted to it.
        if ($userLocationId === null) {
            return $next($request);
        }

        if ($request->filled('location_id') && (int) $request->input('location_id') !== $userLocationId) {
            throw ValidationException::withMessages([
                'location_id' => ['You can only add or change records in your own province.'],
            ]);
        }

        // Force the working province so the client cannot target another province.
        $request->merge(['location_id' => $userLocationId]);

        return $next($request);
    }
}
