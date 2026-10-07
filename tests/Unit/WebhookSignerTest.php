<?php

namespace Tests\Unit;

use App\Webhooks\WebhookSigner;
use PHPUnit\Framework\TestCase;

class WebhookSignerTest extends TestCase
{
    public function test_sign_and_verify(): void
    {
        $body = '{"event":"payment.succeeded"}';
        $signature = WebhookSigner::sign('whsec_x', 1700000000, $body);

        $this->assertSame(hash_hmac('sha256', '1700000000.'.$body, 'whsec_x'), $signature);
        $this->assertTrue(WebhookSigner::verify('whsec_x', 1700000000, $body, $signature, 300, 1700000100));
    }

    public function test_rejects_tampering_wrong_secret_and_old_timestamps(): void
    {
        $body = '{"event":"payment.succeeded"}';
        $signature = WebhookSigner::sign('whsec_x', 1700000000, $body);

        $this->assertFalse(WebhookSigner::verify('whsec_x', 1700000000, '{"event":"payment.failed"}', $signature, 300, 1700000000));
        $this->assertFalse(WebhookSigner::verify('whsec_y', 1700000000, $body, $signature, 300, 1700000000));
        $this->assertFalse(WebhookSigner::verify('whsec_x', 1700000000, $body, $signature, 300, 1700000301));
    }
}
