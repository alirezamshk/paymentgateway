<?php

namespace Tests\Feature;

use App\Models\User;
use App\Webhooks\WebhookHeaders;
use App\Webhooks\WebhookSigner;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhiteLabelTest extends TestCase
{
    public function test_brand_name_comes_from_app_name(): void
    {
        config(['app.name' => 'Acme Pay']);
        $auth = $this->makeClient();
        $this->makeMerchant($auth['client']);
        Http::fake(['*' => Http::response('', 200)]);
        $id = $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody())->json('payment_id');

        $this->get("/pay/{$id}")->assertSee('Acme Pay')->assertDontSee('Tech-Kala');
        $this->get('/admin/login')->assertSee('Acme Pay');
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin')->assertSee('Acme Pay')->assertDontSee('Tech-Kala');
    }

    public function test_default_webhook_headers_are_generic(): void
    {
        $this->assertSame('X-Webhook-Event', WebhookHeaders::event());
        $this->assertSame('X-Webhook-Delivery-Id', WebhookHeaders::deliveryId());
        $this->assertSame('X-Webhook-Timestamp', WebhookHeaders::timestamp());
        $this->assertSame('X-Webhook-Signature', WebhookHeaders::signature());
    }

    public function test_custom_header_prefix_and_user_agent(): void
    {
        config(['payments.webhooks.header_prefix' => 'X-Acme', 'payments.webhooks.user_agent' => 'AcmePay-Webhooks/2.0']);
        Http::fake(['*' => Http::response('', 200)]);
        $auth = $this->makeClient();
        $this->makeMerchant($auth['client']);

        $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody())->assertCreated();

        Http::assertSent(fn (Request $r) => $r->hasHeader('X-Acme-Event')
            && $r->hasHeader('X-Acme-Delivery-Id')
            && $r->header('User-Agent')[0] === 'AcmePay-Webhooks/2.0'
            && ! $r->hasHeader('X-Webhook-Signature')
            && WebhookSigner::verify($auth['webhook_secret'], (int) $r->header('X-Acme-Timestamp')[0], $r->body(), $r->header('X-Acme-Signature')[0]));
    }

    public function test_invalid_prefix_falls_back_to_default(): void
    {
        config(['payments.webhooks.header_prefix' => "X-Bad\r\nInjected: 1"]);

        $this->assertSame('X-Webhook-Signature', WebhookHeaders::signature());
    }
}
