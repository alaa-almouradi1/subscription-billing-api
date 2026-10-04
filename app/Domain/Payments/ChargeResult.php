<?php

namespace App\Domain\Payments;

final class ChargeResult
{
    private function __construct(
        public readonly PaymentStatus $status,
        public readonly ?string $pspReference,
        public readonly ?string $failureCode = null,
        public readonly ?string $failureMessage = null,
    ) {}

    public static function succeeded(string $pspReference): self
    {
        return new self(PaymentStatus::Succeeded, $pspReference);
    }

    public static function failed(?string $pspReference, string $code, string $message): self
    {
        return new self(PaymentStatus::Failed, $pspReference, $code, $message);
    }

    /**
     * Accepted by the provider but not settled yet (e.g. 3-D Secure or bank
     * transfer). The final outcome arrives later via webhook.
     */
    public static function pending(string $pspReference): self
    {
        return new self(PaymentStatus::Processing, $pspReference);
    }
}
