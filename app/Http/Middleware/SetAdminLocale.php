<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin panel language: the user's choice (session, via the language switch) or ADMIN_LOCALE
 * (default "fa"). The API always answers in English for client integrations.
 */
class SetAdminLocale
{
    public const SUPPORTED = ['fa', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->hasSession() ? $request->session()->get('admin_locale') : null;
        $locale = in_array($locale, self::SUPPORTED, true) ? $locale : (string) config('payments.admin_locale', 'fa');

        if (in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
