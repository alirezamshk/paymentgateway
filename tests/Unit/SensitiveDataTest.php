<?php

namespace Tests\Unit;

use App\Support\SensitiveData;
use PHPUnit\Framework\TestCase;

class SensitiveDataTest extends TestCase
{
    public function test_secrets_are_redacted_recursively(): void
    {
        $masked = SensitiveData::mask([
            'password' => 'p@ss',
            'merchant_id' => 'zp-merchant-uuid',
            'nested' => ['api_key' => 'k', 'X-Signature' => 'sig', 'amount' => 1000],
            'cvv2' => '123',
        ]);

        $this->assertSame('[REDACTED]', $masked['password']);
        $this->assertSame('[REDACTED]', $masked['merchant_id']);
        $this->assertSame('[REDACTED]', $masked['nested']['api_key']);
        $this->assertSame('[REDACTED]', $masked['nested']['X-Signature']);
        $this->assertSame('[REDACTED]', $masked['cvv2']);
        $this->assertSame(1000, $masked['nested']['amount']);
    }

    public function test_card_numbers_are_masked(): void
    {
        $this->assertSame('603799******1234', SensitiveData::maskPan('6037-9900-0000-1234'));
        $this->assertSame(['cardNumber' => '603799******1234'], SensitiveData::mask(['cardNumber' => '6037990000001234']));
        // Luhn-valid PAN inside free text is masked; other long numbers are left alone.
        $this->assertSame('card 411111******1111 ref 123456789012345', SensitiveData::maskPansInText('card 4111111111111111 ref 123456789012345'));
    }
}
