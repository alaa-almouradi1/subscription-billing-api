<?php

namespace App\Domain\Billing;

use App\Domain\Money\CurrencyMismatch;
use App\Domain\Money\Money;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Splits a mid-period plan change into a credit for unused time on the old
 * plan and a charge for the remaining time on the new plan.
 *
 * Time is measured in seconds, so "how much of the period is left" is exact
 * regardless of month length; rounding happens once per amount.
 */
final class ProrationCalculator
{
    public static function calculate(
        DateTimeInterface $periodStart,
        DateTimeInterface $periodEnd,
        DateTimeInterface $changeAt,
        Money $oldPrice,
        Money $newPrice,
    ): Proration {
        if ($oldPrice->currency !== $newPrice->currency) {
            throw CurrencyMismatch::between($oldPrice->currency, $newPrice->currency);
        }

        $total = $periodEnd->getTimestamp() - $periodStart->getTimestamp();
        $remaining = $periodEnd->getTimestamp() - $changeAt->getTimestamp();

        if ($total <= 0) {
            throw new InvalidArgumentException('The period must end after it starts.');
        }

        if ($remaining <= 0 || $remaining > $total) {
            throw new InvalidArgumentException('The change must happen within the current period.');
        }

        return new Proration(
            credit: $oldPrice->multiplyByRatio($remaining, $total)->negate(),
            charge: $newPrice->multiplyByRatio($remaining, $total),
        );
    }
}
