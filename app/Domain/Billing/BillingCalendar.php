<?php

namespace App\Domain\Billing;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Calculates billing period boundaries.
 *
 * Every period is derived from the original billing-cycle anchor rather than
 * from the previous period's end. A subscription that starts on 31 January
 * therefore renews on 28/29 February, then on 31 March again, instead of
 * drifting to the 28th forever.
 */
final class BillingCalendar
{
    /**
     * Boundaries of the period with the given zero-based index.
     *
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable} [start, end)
     */
    public static function period(DateTimeInterface $anchor, int $index, BillingInterval $interval): array
    {
        if ($index < 0) {
            throw new InvalidArgumentException('Period index must not be negative.');
        }

        return [
            self::addMonthsClamped($anchor, $index * $interval->months()),
            self::addMonthsClamped($anchor, ($index + 1) * $interval->months()),
        ];
    }

    /**
     * Add whole months, clamping the day to the target month's last day.
     */
    public static function addMonthsClamped(DateTimeInterface $date, int $months): DateTimeImmutable
    {
        $immutable = $date instanceof DateTimeImmutable ? $date : DateTimeImmutable::createFromInterface($date);

        $monthIndex = (int) $immutable->format('Y') * 12 + (int) $immutable->format('n') - 1 + $months;
        $year = intdiv($monthIndex, 12);
        $month = $monthIndex % 12 + 1;

        $daysInTargetMonth = (int) $immutable->setDate($year, $month, 1)->format('t');
        $day = min((int) $immutable->format('j'), $daysInTargetMonth);

        return $immutable->setDate($year, $month, $day);
    }
}
