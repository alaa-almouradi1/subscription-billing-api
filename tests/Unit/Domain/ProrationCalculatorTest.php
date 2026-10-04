<?php

namespace Tests\Unit\Domain;

use App\Domain\Billing\ProrationCalculator;
use App\Domain\Money\CurrencyMismatch;
use App\Domain\Money\Money;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ProrationCalculatorTest extends TestCase
{
    private DateTimeImmutable $start;

    private DateTimeImmutable $end;

    protected function setUp(): void
    {
        parent::setUp();

        // April has 30 days, which keeps the expected numbers readable.
        $this->start = new DateTimeImmutable('2026-04-01 00:00:00');
        $this->end = new DateTimeImmutable('2026-05-01 00:00:00');
    }

    public function test_upgrade_after_a_third_of_the_period(): void
    {
        $proration = ProrationCalculator::calculate(
            $this->start,
            $this->end,
            new DateTimeImmutable('2026-04-11 00:00:00'),
            Money::of(3_000, 'EUR'),
            Money::of(6_000, 'EUR'),
        );

        $this->assertSame(-2_000, $proration->credit->amount);
        $this->assertSame(4_000, $proration->charge->amount);
        $this->assertSame(2_000, $proration->net()->amount);
    }

    public function test_downgrade_produces_a_negative_net_amount(): void
    {
        $proration = ProrationCalculator::calculate(
            $this->start,
            $this->end,
            new DateTimeImmutable('2026-04-11 00:00:00'),
            Money::of(6_000, 'EUR'),
            Money::of(3_000, 'EUR'),
        );

        $this->assertSame(-2_000, $proration->net()->amount);
    }

    public function test_changing_at_the_very_start_swaps_the_full_price(): void
    {
        $proration = ProrationCalculator::calculate(
            $this->start, $this->end, $this->start,
            Money::of(1_000, 'EUR'), Money::of(2_500, 'EUR'),
        );

        $this->assertSame(-1_000, $proration->credit->amount);
        $this->assertSame(2_500, $proration->charge->amount);
    }

    public function test_amounts_are_rounded_to_whole_minor_units(): void
    {
        // 1 second left out of 30 days: both amounts round to zero.
        $proration = ProrationCalculator::calculate(
            $this->start, $this->end, new DateTimeImmutable('2026-04-30 23:59:59'),
            Money::of(999, 'EUR'), Money::of(1_999, 'EUR'),
        );

        $this->assertSame(0, $proration->credit->amount);
        $this->assertSame(0, $proration->charge->amount);
    }

    public function test_changes_outside_the_period_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ProrationCalculator::calculate(
            $this->start, $this->end, $this->end,
            Money::of(1_000, 'EUR'), Money::of(2_000, 'EUR'),
        );
    }

    public function test_currencies_must_match(): void
    {
        $this->expectException(CurrencyMismatch::class);

        ProrationCalculator::calculate(
            $this->start, $this->end, new DateTimeImmutable('2026-04-11'),
            Money::of(1_000, 'EUR'), Money::of(1_000, 'USD'),
        );
    }
}
