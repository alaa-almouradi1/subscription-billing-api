<?php

namespace App\Domain\Billing;

use App\Domain\Money\Money;

final class Proration
{
    public function __construct(
        /** Negative: refund for the unused part of the old plan. */
        public readonly Money $credit,
        /** Positive: price of the remaining part of the period on the new plan. */
        public readonly Money $charge,
    ) {}

    public function net(): Money
    {
        return $this->charge->add($this->credit);
    }
}
