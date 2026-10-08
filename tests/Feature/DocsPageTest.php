<?php

namespace Tests\Feature;

use App\Models\User;
use App\Security\RequestSigner;
use App\Webhooks\WebhookSigner;
use Tests\TestCase;

class DocsPageTest extends TestCase
{
    public function test_public_docs_render_in_both_languages_with_install_values(): void
    {
        config(['payments.webhooks.header_prefix' => 'X-TK-']);

        $this->withSession(['admin_locale' => 'en'])->get('/docs')->assertOk()
            ->assertSee('Signing requests')
            ->assertSee(url('/').'/api/v1/payments')
            ->assertSee('X-TK-Signature')
            ->assertSee('HTTP_X_TK_SIGNATURE')
            ->assertSee('GET /api/v1/merchants')
            ->assertDontSee('tikpgate');

        $this->withSession(['admin_locale' => 'fa'])->get('/docs')->assertOk()
            ->assertSee('امضای درخواست‌ها')
            ->assertSee('dir="rtl"', false);
    }

    public function test_test_vectors_match_the_signers(): void
    {
        $body = '{"order_id":"ORD-10001","amount":500000,"currency":"IRR","description":"Test"}';
        $secret = 'tksk_0000000000000000000000000000000000000000000000000000000000000000';

        // The published reference values (sub-directory install) stay reproducible.
        $this->assertSame('d57178c4a59ef546d36cc20fb2f290692f273c7194a74a4ee02c538f4ced7187',
            RequestSigner::sign($secret, 'POST', '/payment/api/v1/payments', '1791446400', '3f2a9c1e5b7d4a608c1e2f3a4b5c6d7e', $body));

        $page = $this->get('/docs')->assertOk();
        $page->assertSee(RequestSigner::sign($secret, 'POST', '/api/v1/payments', '1791446400', '3f2a9c1e5b7d4a608c1e2f3a4b5c6d7e', $body));
        $page->assertSee('50e419609abdcedb3de7f4f9bb5a0802916519fc5c4e43075da60b96686b0354');
        $this->assertSame('50e419609abdcedb3de7f4f9bb5a0802916519fc5c4e43075da60b96686b0354', WebhookSigner::sign(
            'whsec_1111111111111111111111111111111111111111111111111111111111111111',
            1791446606,
            '{"event":"payment.succeeded","payment_id":"pay_01jabcdefghjkmnpqrstvwxyz0","order_id":"ORD-10001","amount":500000,"currency":"IRR","status":"paid","reference_number":"130085019108","card_mask":"621986******4557","paid_at":"2026-10-08T07:03:25Z","occurred_at":"2026-10-08T07:03:26Z"}',
        ));
    }

    public function test_openapi_uses_this_installation_url(): void
    {
        $response = $this->get('/docs/openapi.yaml')->assertOk();
        $this->assertStringContainsString("servers:\n  - url: ".url('/'), $response->getContent());
        $this->assertStringStartsWith('application/yaml', $response->headers->get('Content-Type'));
    }

    public function test_docs_can_be_limited_to_admins(): void
    {
        config(['payments.public_docs' => false]);

        $this->get('/docs')->assertNotFound();
        $this->get('/docs/openapi.yaml')->assertNotFound();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/docs')->assertOk();
    }

    public function test_admin_sidebar_links_to_docs(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin')->assertOk()->assertSee(route('docs'));
    }
}
