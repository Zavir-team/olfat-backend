<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    /**
     * Force HTTPS in production when the app URL is https.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (
            app()->environment('production')
            && ! $request->secure()
            && str_starts_with((string) config('app.url'), 'https://')
        ) {
            return redirect()->secure($request->getRequestUri());
        }

        return $next($request);
    }
}
