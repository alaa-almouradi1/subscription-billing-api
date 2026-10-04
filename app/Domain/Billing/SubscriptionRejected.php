<?php

namespace App\Domain\Billing;

use App\Domain\BillingException;

final class SubscriptionRejected extends BillingException
{
    private function __construct(
        string $message,
        private readonly string $reason,
        private readonly int $httpStatus = 422,
    ) {
        parent::__construct($message);
    }

    public static function planInactive(string $planCode): self
    {
        return new self("Plan [{$planCode}] is no longer available.", 'plan_inactive');
    }

    public static function missingPaymentMethod(): self
    {
        return new self('The customer needs a payment method for a plan without a trial.', 'payment_method_required');
    }

    public static function alreadyCanceled(): self
    {
        return new self('The subscription is already canceled.', 'subscription_canceled', 409);
    }

    public function errorCode(): string
    {
        return $this->reason;
    }

    public function status(): int
    {
        return $this->httpStatus;
    }
}
