<?php

namespace App\Domain\Payments;

use App\Domain\Money\Money;

final class ChargeRequest
{
    public function __construct(
        /**
         * Our payment ID. Sent to the provider as merchant reference AND as
         * its idempotency key, so retrying the same attempt can never charge
         * the customer twice.
         */
        public readonly string $paymentId,
        public readonly Money $amount,
        public readonly string $paymentMethod,
        public readonly string $description,
    ) {}
}
