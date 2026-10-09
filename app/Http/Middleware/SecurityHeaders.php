<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        // Pages may relax this (the payment page sends its origin, which some PSPs require).
        if (! $headers->has('Referrer-Policy')) {
            $headers->set('Referrer-Policy', 'no-referrer');
        }
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if (! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', "default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
        }

        if ($request->isSecure()) {
            // No includeSubDomains: in a sub-folder install this header would bind every subdomain.
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        // API responses, and admin pages (which show secrets once), are never cached.
        if ($request->is('api/*') || $request->is('admin', 'admin/*')) {
            $headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
