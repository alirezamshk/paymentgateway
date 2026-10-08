<?php

namespace Tests\Feature;

use App\Gateways\Data\GatewayVerifyResult;
use App\Gateways\GatewayManager;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\User;
use App\Settlement\LedgerService;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class SettlementTest extends TestCase
{
    private array $auth;

    private FakeGateway $bank;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response('', 200)]);
        $this->bank = new FakeGateway;
        app(GatewayManager::class)->extend('sandbox', $this->bank);
        $this->auth = $this->makeClient();
        $this->makeMerchant($this->auth['client']);
    }

    private function pay(int $amount, string $currency = 'IRR'): Payment
    {
        $id = $this->signed($this->auth, 'POST', '/api/v1/payments', $this->paymentBody(['amount' => $amount, 'currency' => $currency]))->json('payment_id');
        $payment = Payment::where('public_id', $id)->first();
        $this->post("/api/v1/gateways/sandbox/callback/{$id}", ['authority' => $payment->latestAttempt->authority]);

        return $payment->fresh();
    }

    private function ledger(): LedgerService
    {
        return app(LedgerService::class);
    }

    public function test_paid_payment_is_credited_once_without_commission_by_default(): void
    {
        $payment = $this->pay(500000);

        $this->assertSame('paid', $payment->status->value);
        $this->assertSame(1, LedgerEntry::count());
        $this->assertSame(500000, $this->ledger()->summary($this->auth['client'])['balance']);

        // Duplicate callbacks / backfill do not double-credit.
        $this->post("/api/v1/gateways/sandbox/callback/{$payment->public_id}", ['authority' => $payment->latestAttempt->authority]);
        $this->assertSame(0, $this->ledger()->backfill());
        $this->assertSame(1, LedgerEntry::count());
    }

    public function test_commission_percent_and_fixed_in_rials(): void
    {
        $this->auth['client']->update(['commission_bps' => 150, 'commission_fixed_irr' => 2000]);

        $this->pay(390000);           // 1.5% = 5850 + 2000
        $this->pay(15000, 'IRT');     // 150,000 IRR: 2250 + 2000

        $s = $this->ledger()->summary($this->auth['client']->fresh());
        $this->assertSame(540000, $s['paid_in']);
        $this->assertSame(7850 + 4250, $s['commission']);
        $this->assertSame(540000 - 12100, $s['balance']);
    }

    public function test_commission_never_exceeds_amount(): void
    {
        $this->auth['client']->update(['commission_fixed_irr' => 999999]);
        $this->pay(10000);

        $this->assertSame(0, $this->ledger()->summary($this->auth['client']->fresh())['balance']);
    }

    public function test_failed_payments_are_not_credited(): void
    {
        $this->bank->onVerify = fn () => GatewayVerifyResult::rejected('X', 'declined');
        $this->pay(500000);

        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_hold_period_and_payout_limits(): void
    {
        $client = $this->auth['client'];
        $client->update(['settlement_delay_hours' => 24]);
        $this->pay(500000);

        $s = $this->ledger()->summary($client->fresh());
        $this->assertSame(500000, $s['balance']);
        $this->assertSame(0, $s['available']);
        $this->assertSame(500000, $s['pending']);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post("/admin/settlements/{$client->public_id}/payouts", [
            'amount' => 1000, 'unit' => 'irr', 'bank_reference' => 'X1', 'paid_on' => now()->toDateString(),
        ])->assertSessionHasErrors('amount');

        $this->travel(25)->hours();
        $this->actingAs($admin)->post("/admin/settlements/{$client->public_id}/payouts", [
            'amount' => 30000, 'unit' => 'toman', 'bank_reference' => 'BANK-123', 'paid_on' => now()->toDateString(), 'note' => 'weekly',
        ])->assertSessionHas('status');

        $s = $this->ledger()->summary($client->fresh());
        $this->assertSame(300000, $s['paid_out']);
        $this->assertSame(200000, $s['balance']);
        $this->assertDatabaseHas('payouts', ['client_id' => $client->id, 'amount_irr' => 300000, 'bank_reference' => 'BANK-123']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settlement.payout_recorded']);

        // Cannot pay out more than what is left.
        $this->actingAs($admin)->post("/admin/settlements/{$client->public_id}/payouts", [
            'amount' => 200001, 'unit' => 'irr', 'bank_reference' => 'X2', 'paid_on' => now()->toDateString(),
        ])->assertSessionHasErrors('amount');
    }

    public function test_adjustments_and_ledger_is_immutable(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = $this->auth['client'];

        $this->actingAs($admin)->post("/admin/settlements/{$client->public_id}/adjustments", [
            'direction' => 'debit', 'amount' => 5000, 'unit' => 'irr', 'description' => 'Refund outside system',
        ])->assertSessionHas('status');

        $this->assertSame(-5000, $this->ledger()->summary($client)['balance']);
        $this->expectException(\LogicException::class);
        LedgerEntry::first()->update(['amount_irr' => 1]);
    }

    public function test_backfill_credits_old_paid_payments(): void
    {
        $payment = $this->pay(200000);
        LedgerEntry::query()->toBase()->delete(); // simulate a payment paid before settlement existed

        $this->artisan('settlement:backfill')->assertSuccessful();

        $this->assertSame(200000, $this->ledger()->summary($this->auth['client'])['balance']);
        $this->assertDatabaseHas('ledger_entries', ['payment_id' => $payment->id, 'type' => 'payment']);
    }

    public function test_admin_pages_and_csv_export(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->auth['client']->update(['commission_bps' => 100]);
        $this->pay(500000);
        $client = $this->auth['client'];

        $this->actingAs($admin)->get('/admin/settlements')->assertOk()->assertSee($client->name)->assertSee('495,000');
        $this->actingAs($admin)->get("/admin/settlements/{$client->public_id}")->assertOk()->assertSee('495,000');

        $csv = $this->actingAs($admin)->get("/admin/settlements/{$client->public_id}/export")->assertOk()->streamedContent();
        $this->assertStringContainsString('payment,500000', $csv);
        $this->assertStringContainsString('commission,-5000', $csv);
    }

    public function test_client_settlement_settings_validation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = $this->auth['client'];
        $base = ['name' => $client->name, 'slug' => $client->slug];

        $this->actingAs($admin)->put("/admin/clients/{$client->public_id}", $base + ['iban' => 'IR062960000000100324200002'])->assertSessionHasErrors('iban');

        $this->actingAs($admin)->put("/admin/clients/{$client->public_id}", $base + [
            'commission_percent' => '1.75', 'commission_fixed_irr' => 1000, 'settlement_delay_hours' => 12,
            'iban' => 'IR06 2960 0000 0010 0324 2000 01', 'account_holder' => 'Ali',
        ])->assertRedirect();

        $client->refresh();
        $this->assertSame(175, $client->commission_bps);
        $this->assertSame(1000, $client->commission_fixed_irr);
        $this->assertSame(12, $client->settlement_delay_hours);
        $this->assertSame('IR062960000000100324200001', $client->iban);
    }
}
