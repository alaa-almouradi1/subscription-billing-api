<?php

namespace App\Domain\Billing;

/**
 * Invoice lifecycle.
 *
 *   open ──► paid
 *    │  └──► void
 *    └─────► uncollectible ──► paid   (late recovery)
 *                         └──► void
 */
enum InvoiceStatus: string
{
    case Open = 'open';
    case Paid = 'paid';
    case Void = 'void';
    case Uncollectible = 'uncollectible';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Open => [self::Paid, self::Void, self::Uncollectible],
            self::Uncollectible => [self::Paid, self::Void],
            self::Paid, self::Void => [],
        }, true);
    }

    public function isPayable(): bool
    {
        return $this->canTransitionTo(self::Paid);
    }
}
