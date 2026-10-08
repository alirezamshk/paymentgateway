<?php

namespace Tests\Unit;

use App\Support\Display;
use App\Support\Iban;
use App\Support\Mobile;
use PHPUnit\Framework\TestCase;

class DisplayAndValidationTest extends TestCase
{
    public function test_jalali_conversion(): void
    {
        $this->assertSame([1405, 7, 16], Display::toJalali(2026, 10, 8));
        $this->assertSame([1405, 1, 1], Display::toJalali(2026, 3, 21));
        $this->assertSame([1403, 12, 30], Display::toJalali(2025, 3, 20));
    }

    public function test_mobile_normalization(): void
    {
        foreach (['09121234567', '+989121234567', '00989121234567', '989121234567', '9121234567', '۰۹۱۲۱۲۳۴۵۶۷', '0912 123 4567'] as $input) {
            $this->assertSame('09121234567', Mobile::normalize($input), $input);
        }

        $this->assertNull(Mobile::normalize('02112345678'));
        $this->assertNull(Mobile::normalize('0912123'));
        $this->assertSame('0912', Mobile::forSearch('۰۹۱۲'));
    }

    public function test_iban_checksum(): void
    {
        $this->assertTrue(Iban::isValid('IR062960000000100324200001'));
        $this->assertTrue(Iban::isValid('ir06 2960 0000 0010 0324 2000 01'));
        $this->assertFalse(Iban::isValid('IR062960000000100324200002'));
        $this->assertFalse(Iban::isValid('IR0629600000001003242'));
        $this->assertSame('IR062960000000100324200001', Iban::normalize('06-2960-0000-0010-0324-2000-01'));
    }
}
