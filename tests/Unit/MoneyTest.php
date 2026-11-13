<?php

namespace Tests\Unit;

use App\Services\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_parses_money_and_rates_without_losing_precision(): void
    {
        $this->assertSame(12345, Money::parse('123.45'));
        $this->assertSame(-12345, Money::parse('-123,45'));
        $this->assertSame(546123456, Money::rate('546.123456'));
        $this->assertSame('-123.45', Money::format(-12345));
    }

    public function test_converts_and_rounds_half_up_with_large_intermediate_products(): void
    {
        $this->assertSame(1550, Money::bsToUsd(224719, 145000000));
        $this->assertSame(224750, Money::usdToBs(1550, 145000000));
        $this->assertSame(1, Money::ratio(1, 1, 2));
        $this->assertSame(-1, Money::ratio(-1, 1, 2));
        $this->assertSame(9000000000000, Money::ratio(9000000000000, 9000000000000, 9000000000000));
    }

    public function test_rejects_fractional_cents_instead_of_silently_truncating(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::parse('1.001');
    }

    public function test_rejects_a_zero_exchange_rate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::bsToUsd(1000, 0);
    }
}
