<?php

namespace Tests\Unit\Domain;

use App\Domain\Money\CurrencyMismatch;
use App\Domain\Money\Money;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_currency_codes_are_normalised_to_upper_case(): void
    {
        $this->assertSame('EUR', Money::of(100, 'eur')->currency);
    }

    public function test_invalid_currency_codes_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of(100, 'EURO');
    }

    public function test_addition_and_subtraction(): void
    {
        $a = Money::of(1_000, 'EUR');
        $b = Money::of(250, 'EUR');

        $this->assertTrue($a->add($b)->equals(Money::of(1_250, 'EUR')));
        $this->assertTrue($b->subtract($a)->equals(Money::of(-750, 'EUR')));
    }

    public function test_combining_different_currencies_fails(): void
    {
        $this->expectException(CurrencyMismatch::class);

        Money::of(100, 'EUR')->add(Money::of(100, 'USD'));
    }

    public function test_money_is_immutable(): void
    {
        $original = Money::of(100, 'EUR');
        $original->add(Money::of(50, 'EUR'));

        $this->assertSame(100, $original->amount);
    }

    #[DataProvider('ratioCases')]
    public function test_multiply_by_ratio_rounds_half_away_from_zero(int $amount, int $num, int $den, int $expected): void
    {
        $this->assertSame($expected, Money::of($amount, 'EUR')->multiplyByRatio($num, $den)->amount);
    }

    /**
     * @return array<string, array{int, int, int, int}>
     */
    public static function ratioCases(): array
    {
        return [
            'exact' => [1_000, 1, 2, 500],
            'rounds down below half' => [1_000, 1, 3, 333],
            'rounds up at half' => [5, 1, 2, 3],
            'negative rounds away from zero' => [-5, 1, 2, -3],
            'zero numerator' => [999, 0, 7, 0],
            'full ratio' => [1_999, 30, 30, 1_999],
        ];
    }

    public function test_multiply_by_ratio_rejects_a_zero_denominator(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of(100, 'EUR')->multiplyByRatio(1, 0);
    }

    public function test_multiply_by_ratio_detects_overflow(): void
    {
        $this->expectException(OverflowException::class);

        Money::of(PHP_INT_MAX, 'EUR')->multiplyByRatio(2, 3);
    }

    public function test_min_and_sign_helpers(): void
    {
        $small = Money::of(-1, 'EUR');
        $large = Money::of(10, 'EUR');

        $this->assertSame($small, $large->min($small));
        $this->assertTrue($small->isNegative());
        $this->assertTrue($large->isPositive());
        $this->assertTrue(Money::zero('EUR')->isZero());
    }

    public function test_json_representation(): void
    {
        $this->assertSame(
            '{"amount":1999,"currency":"EUR"}',
            json_encode(Money::of(1_999, 'EUR')),
        );
    }
}
