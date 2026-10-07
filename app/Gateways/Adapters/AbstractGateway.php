<?php

namespace App\Gateways\Adapters;

use App\Enums\Currency;
use App\Exceptions\GatewayException;
use App\Gateways\Contracts\GatewayInterface;
use App\Models\GatewayProvider;
use App\Models\Merchant;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Shared plumbing for HTTP based PSP adapters. Nothing PSP-specific lives here.
 */
abstract class AbstractGateway implements GatewayInterface
{
    protected function http(): PendingRequest
    {
        return Http::timeout(config('gateways.http.timeout'))
            ->connectTimeout(config('gateways.http.connect_timeout'))
            ->withOptions(['allow_redirects' => false])
            ->acceptJson();
    }

    /**
     * Send a request and turn transport failures into GatewayException (outcome unknown).
     *
     * @param  callable(PendingRequest): Response  $send
     */
    protected function send(callable $send): Response
    {
        try {
            $response = $send($this->http());
        } catch (ConnectionException $e) {
            throw new GatewayException("{$this->code()}: connection to PSP failed", null, $e);
        }

        if ($response->serverError()) {
            throw new GatewayException("{$this->code()}: PSP returned HTTP {$response->status()}", (string) $response->status());
        }

        return $response;
    }

    /** Provider-wide (non-secret) setting from gateway_providers.config. */
    protected function setting(string $key, mixed $default = null): mixed
    {
        // Not memoized: long-running workers must see admin changes.
        $provider = GatewayProvider::where('code', $this->code())->first();

        return $provider?->setting($key, $default) ?? $default;
    }

    protected function callbackUrl(Payment $payment): string
    {
        return route('gateways.callback', ['provider' => $this->code(), 'payment' => $payment->public_id]);
    }

    /** Payment amount expressed in the unit the PSP expects. Exact or throws. */
    protected function amountIn(Payment $payment, Currency $pspCurrency): int
    {
        return Money::convert($payment->amount, $payment->currency, $pspCurrency);
    }

    protected function requireCredential(Merchant $merchant, string $key): string
    {
        $value = $merchant->credential($key);

        if ($value === null || $value === '') {
            throw new GatewayException("{$this->code()}: merchant credential [{$key}] is missing");
        }

        return (string) $value;
    }

    /** @return array<string, mixed> */
    protected function json(Response $response): array
    {
        $data = $response->json();

        return is_array($data) ? $data : ['_body' => mb_substr($response->body(), 0, 1000)];
    }
}
