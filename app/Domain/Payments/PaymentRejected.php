<?php

namespace App\Domain\Payments;

use App\Domain\BillingException;

final class PaymentRejected extends BillingException
{
    private function __construct(string $message, private readonly string $reason, private readonly int $httpStatus)
    {
        parent::__construct($message);
    }

    public static function invoiceNotPayable(string $status): self
    {
        return new self("An invoice with status [{$status}] cannot be paid.", 'invoice_not_payable', 409);
    }

    public static function alreadyInProgress(): self
    {
        return new self('A payment for this invoice is already being processed.', 'payment_in_progress', 409);
    }

    public static function missingPaymentMethod(): self
    {
        return new self('No payment method given and the customer has no default.', 'payment_method_required', 422);
    }

    public static function gatewayUnavailable(): self
    {
        return new self(
            'The payment provider did not respond. The payment stays in processing until the provider confirms the outcome.',
            'gateway_unavailable',
            503,
        );
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
