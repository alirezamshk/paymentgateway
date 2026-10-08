<?php

namespace Tests\Feature;

use App\Gateways\GatewayManager;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class PaymentSearchTest extends TestCase
{
    private array $auth;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response('', 200)]);
        app(GatewayManager::class)->extend('sandbox', new FakeGateway);
        $this->auth = $this->makeClient();
        $this->makeMerchant($this->auth['client']);
    }

    public function test_api_accepts_and_normalizes_customer_details(): void
    {
        $this->signed($this->auth, 'POST', '/api/v1/payments', $this->paymentBody([
            'customer' => ['mobile' => '+98 912 123 4567', 'username' => 'ali_m', 'name' => 'علی رضا'],
        ]))->assertCreated()
            ->assertJsonPath('customer.mobile', '09121234567')
            ->assertJsonPath('customer.username', 'ali_m')
            ->assertJsonPath('customer.name', 'علی رضا');

        $this->signed($this->auth, 'POST', '/api/v1/payments', $this->paymentBody(['customer' => ['mobile' => '021123']]))
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');

        // Without customer details the response simply has customer: null.
        $this->signed($this->auth, 'POST', '/api/v1/payments', $this->paymentBody())->assertCreated()->assertJsonPath('customer', null);
    }

    public function test_admin_search_by_mobile_card_and_username(): void
    {
        $make = function (array $customer, string $description = 'Order') {
            $id = $this->signed($this->auth, 'POST', '/api/v1/payments', $this->paymentBody(['customer' => $customer, 'description' => $description]))->json('payment_id');
            $p = Payment::where('public_id', $id)->first();
            $this->post("/api/v1/gateways/sandbox/callback/{$id}", ['authority' => $p->latestAttempt->authority]);

            return $p->fresh();
        };

        $a = $make(['mobile' => '09121111111', 'username' => 'alice']);
        $b = $make(['mobile' => '09352222222', 'username' => 'bob', 'name' => 'Bob Smith']);
        $b->forceFill(['card_mask' => '621986******4557'])->save();
        // Older payment without customer fields: mobile only inside the description.
        $c = $make([], 'شارژ کیف پول علی 09133122944');

        $admin = User::factory()->create(['is_admin' => true]);
        $search = fn (array $q) => $this->actingAs($admin)->get('/admin/payments?'.http_build_query($q))->assertOk();

        $search(['mobile' => '0912 111 1111'])->assertSee($a->order_id)->assertDontSee($b->order_id);
        $search(['mobile' => '۰۹۳۵'])->assertSee($b->order_id)->assertDontSee($a->order_id);
        $search(['mobile' => '09133122944'])->assertSee($c->order_id)->assertDontSee($a->order_id);
        $search(['card' => '4557'])->assertSee($b->order_id)->assertDontSee($a->order_id);
        $search(['username' => 'ali'])->assertSee($a->order_id)->assertDontSee($b->order_id);
        $search(['username' => 'Smith'])->assertSee($b->order_id);
        $search(['q' => '4557'])->assertSee($b->order_id)->assertDontSee($a->order_id);
        $search(['q' => 'bob'])->assertSee($b->order_id)->assertDontSee($a->order_id);

        $this->assertSame('4557', $b->card_last4);
    }

    public function test_language_switch(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin')->assertSee('dir="rtl"', false)->assertSee('داشبورد');
        $this->actingAs($admin)->post('/admin/locale/en')->assertRedirect();
        $this->actingAs($admin)->get('/admin')->assertSee('dir="ltr"', false)->assertSee('Dashboard');
        $this->actingAs($admin)->post('/admin/locale/de')->assertNotFound();
    }
}
