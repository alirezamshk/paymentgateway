<?php

namespace Tests\Feature;

use App\Gateways\GatewayManager;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class MerchantDeletionTest extends TestCase
{
    private array $auth;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response('', 200)]);
        app(GatewayManager::class)->extend('sandbox', new FakeGateway);
        $this->auth = $this->makeClient();
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    private function remove(Merchant $merchant, bool $confirm = true)
    {
        return $this->actingAs($this->admin)->delete("/admin/merchants/{$merchant->public_id}", $confirm ? ['confirm' => '1'] : []);
    }

    public function test_merchant_without_payments_is_deleted_after_confirmation(): void
    {
        $this->makeMerchant($this->auth['client']);
        $spare = $this->makeMerchant($this->auth['client'], 'zarinpal', ['merchant_identifier' => '1344b5d4-0048-11e8-94db-005056a205be'], false);

        $this->actingAs($this->admin)->get("/admin/merchants/{$spare->public_id}/edit")->assertOk()->assertSee(route('admin.merchants.destroy', $spare));

        $this->remove($spare, confirm: false)->assertSessionHasErrors('confirm');
        $this->assertNotNull(Merchant::find($spare->id));

        $this->remove($spare)->assertRedirect("/admin/clients/{$this->auth['client']->public_id}")->assertSessionHas('status');
        $this->assertNull(Merchant::withTrashed()->find($spare->id));
        $this->assertDatabaseHas('audit_logs', ['action' => 'merchant.deleted', 'target_id' => $spare->public_id]);
        $this->assertCount(1, $this->signed($this->auth, 'GET', '/api/v1/merchants')->json('data'));
    }

    public function test_default_merchant_needs_a_new_default_first(): void
    {
        $default = $this->makeMerchant($this->auth['client']);
        $other = $this->makeMerchant($this->auth['client'], 'zarinpal', ['merchant_identifier' => '1344b5d4-0048-11e8-94db-005056a205be'], false);

        $this->remove($default)->assertSessionHasErrors('merchant');
        $this->assertNotNull(Merchant::find($default->id));

        $this->actingAs($this->admin)->post("/admin/merchants/{$other->public_id}/default");
        $this->remove($default)->assertSessionHasNoErrors();
        $this->assertNull(Merchant::withTrashed()->find($default->id));
    }

    public function test_merchant_with_history_is_archived_and_history_survives(): void
    {
        $old = $this->makeMerchant($this->auth['client'], 'sandbox', ['merchant_identifier' => 'old-terminal']);
        $new = $this->makeMerchant($this->auth['client'], 'zarinpal', ['merchant_identifier' => '1344b5d4-0048-11e8-94db-005056a205be'], false);

        $paymentId = $this->signed($this->auth, 'POST', '/api/v1/payments', $this->paymentBody())->assertCreated()->json('payment_id');
        $this->actingAs($this->admin)->post("/admin/merchants/{$new->public_id}/default");

        // An open payment still needs this merchant's credentials for its callback / verify.
        $this->remove($old)->assertSessionHasErrors('merchant');

        $payment = Payment::where('public_id', $paymentId)->first();
        $this->post("/api/v1/gateways/sandbox/callback/{$paymentId}", ['authority' => $payment->latestAttempt->authority]);
        $this->assertSame('paid', $payment->fresh()->status->value);

        $this->remove($old)->assertSessionHasNoErrors()->assertSessionHas('status');

        $archived = Merchant::withTrashed()->find($old->id);
        $this->assertTrue($archived->trashed());
        $this->assertSame('disabled', $archived->status->value);
        $this->assertFalse($archived->is_default);
        $this->assertSame([], $archived->credentials());
        $this->assertDatabaseHas('audit_logs', ['action' => 'merchant.archived', 'target_id' => $old->public_id]);

        // Gone from lists and unusable...
        $this->assertSame([$new->public_id], array_column($this->signed($this->auth, 'GET', '/api/v1/merchants')->json('data'), 'merchant_id'));
        $this->signed($this->auth, 'GET', "/api/v1/merchants/{$old->public_id}")->assertNotFound();
        $this->signed($this->auth, 'POST', '/api/v1/payments', $this->paymentBody(['merchant_id' => $old->public_id]))
            ->assertStatus(422)->assertJsonPath('error.code', 'MERCHANT_NOT_FOUND');
        $this->actingAs($this->admin)->get("/admin/merchants/{$old->public_id}/edit")->assertNotFound();
        $this->actingAs($this->admin)->get("/admin/clients/{$this->auth['client']->public_id}")->assertOk()->assertDontSee($old->public_id);

        // ...but the old payment still shows it.
        $this->signed($this->auth, 'GET', "/api/v1/payments/{$paymentId}")->assertOk()->assertJsonPath('merchant_id', $old->public_id);
        $this->actingAs($this->admin)->get("/admin/payments/{$paymentId}")->assertOk()->assertSee($old->name);
    }
}
