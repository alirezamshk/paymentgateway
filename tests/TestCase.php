<?php

namespace Tests;

use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\GatewayProvider;
use App\Models\Merchant;
use App\Security\RequestSigner;
use App\Services\ClientService;
use App\Services\MerchantService;
use Database\Seeders\GatewayProviderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GatewayProviderSeeder::class);
        GatewayProvider::query()->update(['status' => 'active']);
    }

    /**
     * @return array{client: Client, credential: ClientCredential, secret: string, webhook_secret: string}
     */
    protected function makeClient(string $slug = 'site-a', array $attributes = []): array
    {
        $result = app(ClientService::class)->create(array_merge([
            'name' => Str::title($slug),
            'slug' => $slug,
            'webhook_url' => "https://{$slug}.example.com/webhooks/tk",
            'return_url' => "https://{$slug}.example.com/payment/result",
        ], $attributes));

        return [
            'client' => $result['client'],
            'credential' => ClientCredential::where('key_id', $result['key_id'])->first(),
            'secret' => $result['secret'],
            'webhook_secret' => $result['webhook_secret'],
        ];
    }

    protected function makeMerchant(Client $client, string $provider = 'sandbox', array $credentials = [], bool $default = true): Merchant
    {
        return app(MerchantService::class)->create($client, [
            'name' => "{$provider} merchant",
            'provider' => $provider,
            'credentials' => $credentials,
            'is_default' => $default,
        ], 'system', null);
    }

    /** Send an HMAC-signed API request as the given client. */
    protected function signed(array $auth, string $method, string $uri, ?array $body = null, array $headers = [], ?int $timestamp = null, ?string $nonce = null): TestResponse
    {
        $content = $body === null ? '' : json_encode($body);
        $timestamp = (string) ($timestamp ?? time());
        $nonce ??= Str::random(24);
        $path = parse_url($uri, PHP_URL_PATH).(($q = parse_url($uri, PHP_URL_QUERY)) ? '?'.$q : '');

        $server = $this->transformHeadersToServerVars(array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Client-Id' => $auth['credential']->key_id,
            'X-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => RequestSigner::sign($auth['secret'], $method, $path, $timestamp, $nonce, $content),
        ], $headers));

        return $this->call($method, $uri, [], [], [], $server, $content);
    }

    protected function paymentBody(array $overrides = []): array
    {
        return array_merge([
            'order_id' => 'ORD-'.Str::random(8),
            'amount' => 500000,
            'currency' => 'IRR',
            'description' => 'Service purchase',
        ], $overrides);
    }
}
