<?php

namespace Tests\Feature;

use App\Gateways\GatewayManager;
use App\Models\Merchant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class MerchantManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Client-side merchant management is opt-in (operator-managed by default).
        config(['payments.client_merchant_write' => true]);
    }

    public function test_client_creates_merchant_and_credentials_are_never_returned(): void
    {
        $auth = $this->makeClient();

        $response = $this->signed($auth, 'POST', '/api/v1/merchants', [
            'name' => 'Main AsanPardakht',
            'provider' => 'asanpardakht',
            'credentials' => ['merchant_identifier' => '270', 'username' => 'apuser', 'password' => 'secret-pass-123'],
        ])->assertCreated()
            ->assertJsonPath('provider', 'asanpardakht')
            ->assertJsonPath('is_default', true)
            ->assertJsonPath('configured_credentials', ['merchant_identifier', 'username', 'password']);

        $this->assertStringNotContainsString('secret-pass-123', $response->getContent());
        $this->assertStringNotContainsString('apuser', $response->getContent());

        $row = DB::table('merchants')->first();
        $this->assertStringNotContainsString('secret-pass-123', (string) $row->encrypted_password);
        $this->assertSame('secret-pass-123', Merchant::first()->credential('password'));
    }

    public function test_missing_provider_credentials_are_rejected(): void
    {
        $auth = $this->makeClient();

        $this->signed($auth, 'POST', '/api/v1/merchants', [
            'name' => 'Broken', 'provider' => 'asanpardakht', 'credentials' => ['merchant_identifier' => '270'],
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_MERCHANT_CREDENTIALS')
            ->assertJsonPath('error.details.missing', ['username', 'password']);

        $this->signed($auth, 'POST', '/api/v1/merchants', ['name' => 'X', 'provider' => 'unknown-psp'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PROVIDER_NOT_SUPPORTED');
    }

    public function test_update_keeps_existing_secrets_and_switches_default(): void
    {
        $auth = $this->makeClient();
        $first = $this->makeMerchant($auth['client'], 'zarinpal', ['merchant_identifier' => '1344b5d4-0048-11e8-94db-005056a205be']);
        $second = $this->makeMerchant($auth['client'], 'sepehr', ['terminal_identifier' => '123'], default: false);

        $this->signed($auth, 'PATCH', "/api/v1/merchants/{$second->public_id}", ['name' => 'Renamed', 'is_default' => true])
            ->assertOk()
            ->assertJsonPath('name', 'Renamed')
            ->assertJsonPath('is_default', true);

        $this->assertSame('123', $second->fresh()->credential('terminal_identifier'));
        $this->assertFalse($first->fresh()->is_default);
    }

    public function test_one_client_can_have_merchants_on_multiple_providers(): void
    {
        $auth = $this->makeClient();
        $this->makeMerchant($auth['client'], 'zarinpal', ['merchant_identifier' => '1344b5d4-0048-11e8-94db-005056a205be']);
        $this->makeMerchant($auth['client'], 'sepehr', ['terminal_identifier' => '123'], default: false);
        $this->makeMerchant($auth['client'], 'sepordeh', ['merchant_identifier' => 'k'], default: false);

        $this->assertCount(3, $this->signed($auth, 'GET', '/api/v1/merchants')->json('data'));
    }

    public function test_merchant_writes_are_operator_only_by_default(): void
    {
        config(['payments.client_merchant_write' => false]);
        $auth = $this->makeClient();
        $merchant = $this->makeMerchant($auth['client'], 'sepehr', ['terminal_identifier' => '11111111']);

        $this->signed($auth, 'POST', '/api/v1/merchants', ['name' => 'Mine', 'provider' => 'sepehr', 'credentials' => ['terminal_identifier' => '99999999']])
            ->assertStatus(403)->assertJsonPath('error.code', 'MERCHANT_MANAGEMENT_DISABLED');
        $this->signed($auth, 'PATCH', "/api/v1/merchants/{$merchant->public_id}", ['credentials' => ['terminal_identifier' => '99999999']])
            ->assertStatus(403);
        $this->assertSame('11111111', $merchant->fresh()->credential('terminal_identifier'));
        $this->signed($auth, 'GET', '/api/v1/merchants')->assertOk();
    }

    public function test_credentials_cannot_change_while_payments_are_open(): void
    {
        app(GatewayManager::class)->extend('sandbox', new FakeGateway);
        Http::fake(['*' => Http::response('', 200)]);
        $auth = $this->makeClient();
        $merchant = $this->makeMerchant($auth['client'], 'sandbox', ['merchant_identifier' => 'old']);
        $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody())->assertCreated();

        $this->signed($auth, 'PATCH', "/api/v1/merchants/{$merchant->public_id}", ['credentials' => ['merchant_identifier' => 'new']])
            ->assertStatus(409)->assertJsonPath('error.code', 'MERCHANT_HAS_OPEN_PAYMENTS');
        // Renaming is still fine.
        $this->signed($auth, 'PATCH', "/api/v1/merchants/{$merchant->public_id}", ['name' => 'Renamed'])->assertOk();
        $this->assertSame('old', $merchant->fresh()->credential('merchant_identifier'));
    }
}
