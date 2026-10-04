<?php

namespace App\Domain\Billing;

/**
 * Subscription lifecycle.
 *
 *   trialing ──► active ◄──► past_due
 *       │          │            │
 *       └──────────┴────────────┴──► canceled (terminal)
 */
enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /**
     * Whether the subscription keeps renewing and producing invoices.
     */
    public function renews(): bool
    {
        return $this !== self::Canceled;
    }

    /**
     * @return list<self>
     */
    private function allowedTransitions(): array
    {
        return match ($this) {
            self::Trialing => [self::Active, self::PastDue, self::Canceled],
            self::Active => [self::PastDue, self::Canceled],
            self::PastDue => [self::Active, self::Canceled],
            self::Canceled => [],
        };
    }
}
