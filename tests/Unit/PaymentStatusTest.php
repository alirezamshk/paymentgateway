<?php

namespace Tests\Unit;

use App\Enums\PaymentStatus as S;
use PHPUnit\Framework\TestCase;

class PaymentStatusTest extends TestCase
{
    public function test_happy_path_transitions_are_allowed(): void
    {
        $path = [S::Created, S::Pending, S::Redirected, S::CallbackReceived, S::Verifying, S::Paid];

        for ($i = 0; $i < count($path) - 1; $i++) {
            $this->assertTrue($path[$i]->canTransitionTo($path[$i + 1]), "{$path[$i]->value} -> {$path[$i + 1]->value}");
        }

        $this->assertTrue(S::Verifying->canTransitionTo(S::Failed));
    }

    public function test_paid_is_terminal(): void
    {
        foreach (S::cases() as $to) {
            $this->assertFalse(S::Paid->canTransitionTo($to), "paid -> {$to->value} must be rejected");
        }
    }

    public function test_arbitrary_jumps_are_rejected(): void
    {
        $this->assertFalse(S::Created->canTransitionTo(S::Paid));
        $this->assertFalse(S::Pending->canTransitionTo(S::Paid));
        $this->assertFalse(S::Redirected->canTransitionTo(S::Paid));
        $this->assertFalse(S::CallbackReceived->canTransitionTo(S::Paid));
        $this->assertFalse(S::Expired->canTransitionTo(S::Pending));
        $this->assertFalse(S::Cancelled->canTransitionTo(S::Pending));
        $this->assertFalse(S::Failed->canTransitionTo(S::Paid));
    }

    public function test_webhook_events(): void
    {
        $this->assertSame('payment.succeeded', S::Paid->webhookEvent());
        $this->assertSame('payment.failed', S::Failed->webhookEvent());
        $this->assertSame('payment.expired', S::Expired->webhookEvent());
        $this->assertSame('payment.cancelled', S::Cancelled->webhookEvent());
        $this->assertSame('payment.pending', S::Pending->webhookEvent());
        $this->assertNull(S::Verifying->webhookEvent());
    }
}
