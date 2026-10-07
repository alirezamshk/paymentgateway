<?php

namespace Tests\Unit;

use App\Enums\Currency;
use App\Exceptions\ApiException;
use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_exact_conversions(): void
    {
        $this->assertSame(500000, Money::convert(500000, Currency::IRR, Currency::IRR));
        $this->assertSame(500000, Money::convert(50000, Currency::IRT, Currency::IRR));
        $this->assertSame(50000, Money::convert(500000, Currency::IRR, Currency::IRT));
    }

    public function test_inexact_conversion_is_rejected_not_rounded(): void
    {
        $this->expectException(ApiException::class);
        Money::convert(500005, Currency::IRR, Currency::IRT);
    }
}
