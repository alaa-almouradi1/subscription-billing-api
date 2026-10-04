<?php

namespace App\Domain\Billing;

use App\Domain\BillingException;

final class PlanChangeRejected extends BillingException
{
    private function __construct(string $message, private readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function samePlan(): self
    {
        return new self('The subscription is already on this plan.', 'same_plan');
    }

    public static function incompatible(): self
    {
        return new self(
            'Plan changes must keep the same billing interval and currency; cancel and resubscribe instead.',
            'incompatible_plan',
        );
    }

    public static function subscriptionCanceled(): self
    {
        return new self('A canceled subscription cannot change plans.', 'subscription_canceled');
    }

    public static function planInactive(): self
    {
        return new self('The target plan is no longer available.', 'plan_inactive');
    }

    public static function renewalPending(): self
    {
        return new self('The current period has ended and is about to renew; try again shortly.', 'renewal_pending');
    }

    public function errorCode(): string
    {
        return $this->reason;
    }

    public function status(): int
    {
        return in_array($this->reason, ['subscription_canceled', 'renewal_pending'], true) ? 409 : 422;
    }
}
