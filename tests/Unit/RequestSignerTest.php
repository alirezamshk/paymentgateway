<?php

namespace Tests\Unit;

use App\Security\RequestSigner;
use PHPUnit\Framework\TestCase;

class RequestSignerTest extends TestCase
{
    public function test_canonical_string_contains_all_parts_in_order(): void
    {
        $canonical = RequestSigner::canonical('post', '/api/v1/payments', '1700000000', 'nonce-123', '{"a":1}');

        $this->assertSame(
            "POST\n/api/v1/payments\n1700000000\nnonce-123\n".hash('sha256', '{"a":1}'),
            $canonical,
        );
    }

    public function test_signature_is_hmac_sha256_of_canonical_string(): void
    {
        $signature = RequestSigner::sign('secret', 'GET', '/api/v1/payments/pay_x', '1700000000', 'abcdefghijklmnop', '');

        $expected = hash_hmac('sha256', "GET\n/api/v1/payments/pay_x\n1700000000\nabcdefghijklmnop\n".hash('sha256', ''), 'secret');
        $this->assertSame($expected, $signature);
    }

    public function test_any_change_invalidates_signature(): void
    {
        $base = RequestSigner::sign('secret', 'POST', '/api/v1/payments', '1', 'nonce-nonce-nonce', '{"amount":1000}');

        $this->assertFalse(RequestSigner::matches($base, RequestSigner::sign('secret', 'POST', '/api/v1/payments', '1', 'nonce-nonce-nonce', '{"amount":9000}')));
        $this->assertFalse(RequestSigner::matches($base, RequestSigner::sign('secret', 'PUT', '/api/v1/payments', '1', 'nonce-nonce-nonce', '{"amount":1000}')));
        $this->assertFalse(RequestSigner::matches($base, RequestSigner::sign('secret', 'POST', '/api/v1/other', '1', 'nonce-nonce-nonce', '{"amount":1000}')));
        $this->assertFalse(RequestSigner::matches($base, RequestSigner::sign('secret', 'POST', '/api/v1/payments', '2', 'nonce-nonce-nonce', '{"amount":1000}')));
        $this->assertFalse(RequestSigner::matches($base, RequestSigner::sign('other', 'POST', '/api/v1/payments', '1', 'nonce-nonce-nonce', '{"amount":1000}')));
        $this->assertTrue(RequestSigner::matches($base, strtoupper($base)));
    }
}
