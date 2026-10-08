<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin panel has its own language (ADMIN_LOCALE, default "fa"), independent of the
 * API, whose messages stay in English for client integrations.
 */
class SetAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) config('payments.admin_locale', 'fa');

        if (in_array($locale, ['fa', 'en'], true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
