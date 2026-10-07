<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * In production every request must be HTTPS. API calls over plain HTTP are rejected
 * (not redirected) so that signed requests are never sent in clear text twice.
 */
class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->isProduction() || $request->isSecure()) {
            return $next($request);
        }

        if ($request->is('api/*')) {
            return response()->json(['error' => [
                'code' => 'HTTPS_REQUIRED',
                'message' => 'HTTPS is required.',
                'request_id' => $request->attributes->get('request_id'),
            ]], 403);
        }

        return redirect()->secure($request->getRequestUri(), 301);
    }
}
