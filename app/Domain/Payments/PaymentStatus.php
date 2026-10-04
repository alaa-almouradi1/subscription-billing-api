<?php

namespace App\Domain\Payments;

enum PaymentStatus: string
{
    /** Sent to (or about to be sent to) the provider; outcome unknown. */
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function isFinal(): bool
    {
        return $this !== self::Processing;
    }
}
