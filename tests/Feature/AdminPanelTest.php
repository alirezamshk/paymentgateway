<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\GatewayProvider;
use App\Models\User;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_admin_pages_require_admin(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get('/admin')->assertForbidden();
    }

    public function test_login_and_logout(): void
    {
        $user = User::factory()->create(['is_admin' => true, 'password' => 'correct-horse-battery']);

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'correct-horse-battery'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.login_failed']);
    }

    public function test_admin_creates_client_and_sees_secrets_once(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/admin/clients', [
            'name' => 'Panel A', 'slug' => 'panel-a',
            'webhook_url' => 'https://panel-a.example.com/hook', 'return_url' => 'https://panel-a.example.com/return',
        ]);

        $client = Client::where('slug', 'panel-a')->first();
        $response->assertRedirect("/admin/clients/{$client->public_id}");
        $this->assertArrayHasKey('Client secret', session('secrets'));

        $this->actingAs($admin)->get("/admin/clients/{$client->public_id}")->assertOk()->assertSee('tkc_');
        // Secrets are not shown on subsequent views.
        $this->actingAs($admin)->get("/admin/clients/{$client->public_id}")->assertDontSee('tksk_');
        $this->assertDatabaseHas('audit_logs', ['action' => 'client.created', 'actor_id' => $admin->id]);
    }

    public function test_admin_merchant_crud_and_test(): void
    {
        $admin = $this->admin();
        $auth = $this->makeClient();

        $this->actingAs($admin)->post('/admin/merchants', [
            'client' => $auth['client']->public_id, 'name' => 'ZP', 'provider' => 'zarinpal',
            'credentials' => ['merchant_identifier' => '1344b5d4-0048-11e8-94db-005056a205be'],
        ])->assertRedirect();

        $merchant = $auth['client']->merchants()->first();
        $this->actingAs($admin)->get("/admin/merchants/{$merchant->public_id}/edit")->assertOk()->assertDontSee('1344b5d4-0048-11e8-94db-005056a205be');
        $this->actingAs($admin)->post("/admin/merchants/{$merchant->public_id}/test")->assertSessionHas('status');
        $this->actingAs($admin)->post("/admin/merchants/{$merchant->public_id}/toggle");
        $this->assertSame('disabled', $merchant->fresh()->status->value);
    }

    public function test_admin_payment_search_detail_and_webhook_retry(): void
    {
        $up = false;
        Http::fake(function () use (&$up) {
            return Http::response('', $up ? 200 : 500);
        });
        $admin = $this->admin();
        $auth = $this->makeClient();
        $this->makeMerchant($auth['client']);
        $id = $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody(['order_id' => 'ORD-ADMIN']))->json('payment_id');

        $this->actingAs($admin)->get('/admin/payments?q=ORD-ADMIN')->assertOk()->assertSee($id);
        $this->actingAs($admin)->get('/admin/payments?status=paid')->assertOk()->assertDontSee($id);
        $this->actingAs($admin)->get("/admin/payments/{$id}")->assertOk()->assertSee('payment.created')->assertSee('gateway.requested');

        $delivery = WebhookDelivery::first();
        $this->actingAs($admin)->get('/admin/webhooks')->assertOk()->assertSee($delivery->public_id);
        $this->actingAs($admin)->get("/admin/webhooks/{$delivery->public_id}")->assertOk();

        $up = true;
        $this->actingAs($admin)->post("/admin/webhooks/{$delivery->public_id}/retry")->assertSessionHas('status');
        $this->assertSame('delivered', $delivery->fresh()->status->value);
    }

    public function test_admin_provider_toggle_and_config(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/providers')->assertOk()->assertSee('zarinpal');
        $this->actingAs($admin)->put('/admin/providers/zarinpal', ['name' => 'ZarinPal', 'config' => '{"sandbox": false}'])->assertRedirect();
        $this->actingAs($admin)->post('/admin/providers/zarinpal/toggle');

        $provider = GatewayProvider::where('code', 'zarinpal')->first();
        $this->assertFalse($provider->config['sandbox']);
        $this->assertSame('disabled', $provider->status->value);
        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/admin/audit-logs')->assertOk()->assertSee('provider.disabled');
    }
}
