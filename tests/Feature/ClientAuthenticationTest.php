<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Security\RequestSigner;
use App\Services\ClientService;
use Tests\TestCase;

class ClientAuthenticationTest extends TestCase
{
    public function test_valid_signature_is_accepted(): void
    {
        $auth = $this->makeClient();
        $this->makeMerchant($auth['client']);

        $this->signed($auth, 'GET', '/api/v1/merchants')
            ->assertOk()
            ->assertHeader('X-Request-Id');
    }

    public function test_missing_headers_are_rejected(): void
    {
        $this->getJson('/api/v1/merchants')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_REQUIRED')
            ->assertJsonStructure(['error' => ['code', 'message', 'request_id']]);
    }

    public function test_invalid_signature_is_rejected_and_audited(): void
    {
        $auth = $this->makeClient();

        $this->signed($auth, 'GET', '/api/v1/merchants', headers: ['X-Signature' => str_repeat('a', 64)])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_INVALID_SIGNATURE');

        $log = AuditLog::where('action', 'client.auth_failed')->latest('id')->first();
        $this->assertSame('invalid_signature', $log->metadata['reason']);
        $this->assertStringNotContainsString($auth['secret'], json_encode($log->toArray()));
    }

    public function test_body_tampering_is_rejected(): void
    {
        $auth = $this->makeClient();
        $this->makeMerchant($auth['client']);
        $timestamp = (string) time();
        $nonce = 'nonce-tamper-0001';
        $signature = RequestSigner::sign($auth['secret'], 'POST', '/api/v1/payments', $timestamp, $nonce, json_encode($this->paymentBody(['amount' => 10000])));

        $this->call('POST', '/api/v1/payments', [], [], [], $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json', 'Accept' => 'application/json',
            'X-Client-Id' => $auth['credential']->key_id, 'X-Timestamp' => $timestamp, 'X-Nonce' => $nonce, 'X-Signature' => $signature,
        ]), json_encode($this->paymentBody(['amount' => 99999999])))
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_INVALID_SIGNATURE');
    }

    public function test_expired_timestamp_is_rejected(): void
    {
        $auth = $this->makeClient();

        $this->signed($auth, 'GET', '/api/v1/merchants', timestamp: time() - 301)
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_TIMESTAMP_EXPIRED');

        $this->signed($auth, 'GET', '/api/v1/merchants', timestamp: time() + 301)
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_TIMESTAMP_EXPIRED');
    }

    public function test_replayed_nonce_is_rejected(): void
    {
        $auth = $this->makeClient();
        $nonce = 'nonce-replay-000001';

        $this->signed($auth, 'GET', '/api/v1/merchants', nonce: $nonce)->assertOk();
        $this->signed($auth, 'GET', '/api/v1/merchants', nonce: $nonce)
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_NONCE_REPLAYED');
    }

    public function test_unknown_key_is_rejected(): void
    {
        $auth = $this->makeClient();
        $auth['credential']->key_id = 'tkc_unknown';

        $this->signed($auth, 'GET', '/api/v1/merchants')->assertStatus(401)->assertJsonPath('error.code', 'AUTH_INVALID_SIGNATURE');
    }

    public function test_revoked_credential_and_disabled_client_are_rejected(): void
    {
        $auth = $this->makeClient();
        app(ClientService::class)->revokeCredential($auth['credential']);

        $this->signed($auth, 'GET', '/api/v1/merchants')->assertStatus(403)->assertJsonPath('error.code', 'AUTH_CLIENT_DISABLED');

        $other = $this->makeClient('site-b');
        $other['client']->update(['status' => 'disabled']);
        $this->signed($other, 'GET', '/api/v1/merchants')->assertStatus(403);
    }

    public function test_credential_rotation_keeps_old_key_until_revoked(): void
    {
        $auth = $this->makeClient();
        [$newCredential, $newSecret] = app(ClientService::class)->issueCredential($auth['client']);
        $new = ['credential' => $newCredential, 'secret' => $newSecret];

        $this->signed($auth, 'GET', '/api/v1/merchants')->assertOk();
        $this->signed($new, 'GET', '/api/v1/merchants')->assertOk();

        app(ClientService::class)->revokeCredential($auth['credential']);
        $this->signed($auth, 'GET', '/api/v1/merchants')->assertStatus(403);
        $this->signed($new, 'GET', '/api/v1/merchants')->assertOk();
    }

    public function test_referer_and_origin_do_not_authenticate(): void
    {
        $this->makeClient();

        $this->withHeaders(['Referer' => 'https://site-a.example.com/', 'Origin' => 'https://site-a.example.com'])
            ->getJson('/api/v1/merchants')
            ->assertStatus(401);
    }

    public function test_client_rate_limit(): void
    {
        config(['payments.auth.rate_limit_per_minute' => 3]);
        $auth = $this->makeClient();

        for ($i = 0; $i < 3; $i++) {
            $this->signed($auth, 'GET', '/api/v1/merchants')->assertOk();
        }

        $this->signed($auth, 'GET', '/api/v1/merchants')->assertStatus(429)->assertJsonPath('error.code', 'RATE_LIMITED');
    }

    public function test_secrets_are_encrypted_at_rest(): void
    {
        $auth = $this->makeClient();
        $row = \DB::table('client_credentials')->where('id', $auth['credential']->id)->first();
        $clientRow = \DB::table('clients')->where('id', $auth['client']->id)->first();

        $this->assertStringNotContainsString($auth['secret'], $row->encrypted_secret);
        $this->assertStringNotContainsString($auth['webhook_secret'], $clientRow->webhook_secret);
        $this->assertSame($auth['secret'], $auth['credential']->fresh()->secret());
    }
}
