<?php

namespace Tests\Unit\Domain;

use App\Domain\Billing\BillingCalendar;
use App\Domain\Billing\BillingInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BillingCalendarTest extends TestCase
{
    #[DataProvider('monthClampCases')]
    public function test_adding_months_clamps_to_the_end_of_short_months(string $from, int $months, string $expected): void
    {
        $result = BillingCalendar::addMonthsClamped(new DateTimeImmutable($from), $months);

        $this->assertSame($expected, $result->format('Y-m-d H:i:s'));
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function monthClampCases(): array
    {
        return [
            'regular month' => ['2026-01-15 10:00:00', 1, '2026-02-15 10:00:00'],
            'jan 31 to feb' => ['2026-01-31 10:00:00', 1, '2026-02-28 10:00:00'],
            'leap year feb' => ['2028-01-31 00:00:00', 1, '2028-02-29 00:00:00'],
            'across year end' => ['2026-11-30 00:00:00', 3, '2027-02-28 00:00:00'],
            'twelve months' => ['2028-02-29 00:00:00', 12, '2029-02-28 00:00:00'],
        ];
    }

    public function test_periods_are_anchored_so_the_day_does_not_drift(): void
    {
        $anchor = new DateTimeImmutable('2026-01-31 09:00:00');

        [$febStart, $febEnd] = BillingCalendar::period($anchor, 1, BillingInterval::Month);
        [$marStart] = BillingCalendar::period($anchor, 2, BillingInterval::Month);

        $this->assertSame('2026-02-28', $febStart->format('Y-m-d'));
        $this->assertSame('2026-03-31', $febEnd->format('Y-m-d'));
        $this->assertSame('2026-03-31', $marStart->format('Y-m-d'));
    }

    public function test_consecutive_periods_are_contiguous(): void
    {
        $anchor = new DateTimeImmutable('2026-01-31 09:00:00');

        for ($i = 0; $i < 24; $i++) {
            [, $end] = BillingCalendar::period($anchor, $i, BillingInterval::Month);
            [$nextStart] = BillingCalendar::period($anchor, $i + 1, BillingInterval::Month);

            $this->assertEquals($end, $nextStart);
        }
    }

    public function test_yearly_periods(): void
    {
        [$start, $end] = BillingCalendar::period(new DateTimeImmutable('2026-03-01'), 0, BillingInterval::Year);

        $this->assertSame('2026-03-01', $start->format('Y-m-d'));
        $this->assertSame('2027-03-01', $end->format('Y-m-d'));
    }

    public function test_negative_period_index_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BillingCalendar::period(new DateTimeImmutable, -1, BillingInterval::Month);
    }
}
