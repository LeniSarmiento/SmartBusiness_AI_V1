<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public static function amounts(): array
    {
        return [['0', 0], ['1.05', 105], ['35.7', 3570], ['9999999999.99', 999999999999]];
    }

    #[DataProvider('amounts')]
    public function test_money_conversion_does_not_use_floats(string $input, int $expected): void
    {
        $this->assertSame($expected, Money::cents($input));
        $this->assertSame($expected, Money::cents(Money::decimal($expected)));
    }

    public function test_tax_rounds_half_up_and_handles_large_values(): void
    {
        $this->assertSame(2, Money::tax(10, 1900));
        $this->assertSame(1900000000000000, Money::tax(10000000000000000, 1900));
    }

    public function test_invalid_precision_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::cents('1.001');
    }

    public static function invalidAmounts(): array
    {
        return [
            'negative input' => ['-1.00'],
            'comma decimal separator' => ['1,50'],
            'scientific notation' => ['1e3'],
            'too many whole digits' => ['10000000000.00'],
            'empty input' => [''],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_money_formats_are_rejected(string $input): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::cents($input);
    }

    public function test_decimal_formats_negative_and_zero_minor_units(): void
    {
        $this->assertSame('-12.34', Money::decimal(-1234));
        $this->assertSame('0.00', Money::decimal(0));
    }

    public function test_zero_tax_never_changes_the_subtotal(): void
    {
        $this->assertSame(0, Money::tax(999999999999, 0));
    }
}
