<?php

namespace Tests\Feature;

use App\Enums\RecordStatus;
use App\Gateways\Data\GatewayCreateResult;
use App\Gateways\GatewayManager;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Reports\SalesReport;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class MerchantTestPaymentTest extends TestCase
{
    private FakeGateway $gateway;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response('', 200)]);
        $this->gateway = new FakeGateway;
        app(GatewayManager::class)->extend('sandbox', $this->gateway);
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    public function test_admin_runs_a_real_test_payment_without_side_effects(): void
    {
        $auth = $this->makeClient();
        $merchant = $this->makeMerchant($auth['client']);
        // Testing before enabling is the point: a disabled merchant can be tested.
        $merchant->update(['status' => RecordStatus::Disabled]);

        $this->actingAs($this->admin)->get("/admin/clients/{$auth['client']->public_id}")->assertOk()
            ->assertSee(route('admin.merchants.test-payment', $merchant));

        $response = $this->actingAs($this->admin)->post("/admin/merchants/{$merchant->public_id}/test-payment", ['amount' => 50000]);

        $payment = Payment::firstOrFail();
        $response->assertRedirect($payment->payment_url);
        $this->assertTrue($payment->is_test);
        $this->assertSame(50000, $payment->amount);
        $this->assertSame('IRT', $payment->currency->value);
        $this->assertSame($merchant->id, $payment->merchant_id);
        $this->assertStringStartsWith('TEST-', $payment->order_id);
        $this->assertSame(route('pay.test-result', $payment->public_id), $payment->return_url);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.test_created', 'target_id' => $payment->public_id]);

        // Real round trip: callback, verify, back to the result page.
        $this->post("/api/v1/gateways/sandbox/callback/{$payment->public_id}", ['authority' => $payment->latestAttempt->authority])
            ->assertRedirect();
        $payment->refresh();
        $this->assertSame('paid', $payment->status->value);

        $this->withSession(['admin_locale' => 'fa'])->get("/pay/{$payment->public_id}/test-result")->assertOk()
            ->assertSee('نتیجهٔ پرداخت تست')->assertSee($payment->reference_number)->assertSee(route('admin.payments.show', $payment));

        // No webhook to the site, no settlement credit, not in reports.
        $this->assertSame(0, WebhookDelivery::count());
        $this->assertSame(0, LedgerEntry::count());
        $this->assertSame(0, app(SalesReport::class)->build('day', 'provider', null, 'en')['total']);

        $this->actingAs($this->admin)->get('/admin/payments')->assertSee('تست ادمین');
    }

    public function test_rejected_test_payment_shows_the_reason_in_the_panel(): void
    {
        $this->gateway->onCreate = fn () => GatewayCreateResult::failure('ZARINPAL_-9', 'The amount must be at least 15000');
        $merchant = $this->makeMerchant($this->makeClient()['client']);

        $response = $this->actingAs($this->admin)->post("/admin/merchants/{$merchant->public_id}/test-payment", ['amount' => 1000]);

        $payment = Payment::firstOrFail();
        $response->assertRedirect(route('admin.payments.show', $payment))->assertSessionHas('error');
        $this->actingAs($this->admin)->get(route('admin.payments.show', $payment))->assertSee('The amount must be at least 15000');
    }

    public function test_validation_and_access(): void
    {
        $merchant = $this->makeMerchant($this->makeClient()['client']);

        $this->actingAs($this->admin)->post("/admin/merchants/{$merchant->public_id}/test-payment", ['amount' => 10])->assertSessionHasErrors('amount');
        $this->assertSame(0, Payment::count());

        auth()->logout();
        $this->post("/admin/merchants/{$merchant->public_id}/test-payment", ['amount' => 50000])->assertRedirect();
        $this->assertSame(0, Payment::count());

        // The result page exists only for test payments.
        $auth = $this->makeClient('site-b');
        $this->makeMerchant($auth['client']);
        $id = $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody())->json('payment_id');
        $this->get("/pay/{$id}/test-result")->assertNotFound();
    }
}
