<?php

namespace Tests\Feature;

use App\Gateways\GatewayManager;
use App\Models\GatewayProvider;
use App\Models\Payment;
use App\Models\User;
use App\Security\RequestSigner;
use App\Support\SensitiveData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    public function test_form_encoded_bodies_are_refused_because_they_are_not_signed(): void
    {
        $auth = $this->makeClient();
        $this->makeMerchant($auth['client']);

        // Signed as an empty body, but carries form fields (PHP parses them; the hash does not cover them).
        $timestamp = (string) time();
        $nonce = Str::random(24);
        $server = $this->transformHeadersToServerVars([
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'X-Client-Id' => $auth['credential']->key_id,
            'X-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => RequestSigner::sign($auth['secret'], 'POST', '/api/v1/payments', $timestamp, $nonce, ''),
        ]);

        $this->call('POST', '/api/v1/payments', ['order_id' => 'ORD-FORM', 'amount' => 500000], [], [], $server)
            ->assertStatus(401)->assertJsonPath('error.code', 'AUTH_INVALID');
        $this->assertSame(0, Payment::count());
    }

    public function test_non_string_currency_is_a_validation_error_not_a_crash(): void
    {
        $auth = $this->makeClient();
        $this->makeMerchant($auth['client']);

        $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody(['currency' => ['x']]))
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_admin_test_payments_are_not_visible_through_the_client_api(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        app(GatewayManager::class)->extend('sandbox', new FakeGateway);
        $auth = $this->makeClient();
        $merchant = $this->makeMerchant($auth['client']);
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post("/admin/merchants/{$merchant->public_id}/test-payment", ['amount' => 50000]);

        $id = Payment::firstOrFail()->public_id;
        $this->signed($auth, 'GET', "/api/v1/payments/{$id}")->assertNotFound();
    }

    public function test_zarinpal_sandbox_is_refused_for_real_payments_in_production(): void
    {
        $this->app['env'] = 'production';
        GatewayProvider::where('code', 'zarinpal')->update(['config' => ['sandbox' => true]]);
        Http::fake(['*' => Http::response(['data' => ['code' => 100, 'authority' => 'A1']], 200)]);
        $auth = $this->makeClient();
        $this->makeMerchant($auth['client'], 'zarinpal', ['merchant_identifier' => '1344b5d4-0048-11e8-94db-005056a205be']);

        $payment = Payment::where('public_id', $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody())->json('payment_id'))->first();
        $this->assertSame('failed', $payment->status->value);
        $this->assertSame('SANDBOX_NOT_ALLOWED', $payment->latestAttempt->error_code);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'zarinpal.com'));
    }

    public function test_admin_pages_are_not_cached_and_hsts_is_scoped(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $response = $this->actingAs($admin)->get('/admin');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $secure = $this->get('https://localhost/docs');
        $this->assertSame('max-age=31536000', $secure->headers->get('Strict-Transport-Security'));
    }

    public function test_settlement_csv_neutralises_formulas(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = $this->makeClient()['client'];
        $this->actingAs($admin)->post("/admin/settlements/{$client->public_id}/adjustments", [
            'amount' => 1000, 'unit' => 'irr', 'direction' => 'credit', 'description' => '=HYPERLINK("http://evil")',
        ]);

        $csv = $this->actingAs($admin)->get("/admin/settlements/{$client->public_id}/export")->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_redaction_covers_compound_secret_keys(): void
    {
        $masked = SensitiveData::mask(['merchant_password' => 'x', 'sepehr_api_secret' => 'y', 'iban' => 'IR12', 'digitalreceipt' => 'keep', 'token' => 'keep']);

        $this->assertSame('[REDACTED]', $masked['merchant_password']);
        $this->assertSame('[REDACTED]', $masked['sepehr_api_secret']);
        $this->assertSame('[REDACTED]', $masked['iban']);
        // Needed again for re-verification from the stored callback.
        $this->assertSame('keep', $masked['digitalreceipt']);
        $this->assertSame('keep', $masked['token']);
    }
}
