<?php

namespace App\Providers;

use App\Gateways\GatewayManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GatewayManager::class);
    }

    public function boot(): void
    {
        $proxies = trim((string) config('payments.trusted_proxies'));
        if ($proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $proxies)))));
        }

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        RateLimiter::for('api-ip', fn (Request $r) => Limit::perMinute((int) config('payments.auth.ip_rate_limit_per_minute'))->by('ip:'.$r->ip()));

        RateLimiter::for('client-api', function (Request $r) {
            $client = $r->attributes->get('client');

            return Limit::perMinute((int) config('payments.auth.rate_limit_per_minute'))->by('client:'.($client?->id ?? $r->ip()));
        });

        RateLimiter::for('callbacks', fn (Request $r) => Limit::perMinute(60)->by('cb:'.$r->ip()));
        RateLimiter::for('payment-page', fn (Request $r) => Limit::perMinute(60)->by('pay:'.$r->ip()));
        RateLimiter::for('admin-login', fn (Request $r) => [
            Limit::perMinute(5)->by('admin-login:'.$r->ip().'|'.mb_strtolower((string) $r->input('email'))),
            Limit::perMinute(20)->by('admin-login-ip:'.$r->ip()),
        ]);
    }
}
