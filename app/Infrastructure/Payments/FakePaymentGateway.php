<?php

namespace App\Infrastructure\Payments;

use App\Domain\Payments\ChargeRequest;
use App\Domain\Payments\ChargeResult;
use App\Domain\Payments\GatewayUnavailable;
use App\Domain\Payments\PaymentGateway;

/**
 * Deterministic stand-in for a real PSP, driven by test card tokens
 * (the same idea as Stripe's or Adyen's test cards):
 *
 *   tok_visa               succeeds
 *   tok_chargeDeclined     fails: card_declined
 *   tok_insufficientFunds  fails: insufficient_funds
 *   tok_pending            stays pending until a webhook settles it
 *   tok_timeout            throws GatewayUnavailable (outcome unknown)
 *
 * Like a real PSP it is idempotent per payment ID: repeating a request
 * returns the original result instead of charging again.
 */
final class FakePaymentGateway implements PaymentGateway
{
    /** @var array<string, ChargeResult> */
    private array $results = [];

    /** @var list<ChargeRequest> */
    private array $charges = [];

    public function charge(ChargeRequest $request): ChargeResult
    {
        if (isset($this->results[$request->paymentId])) {
            return $this->results[$request->paymentId];
        }

        if ($request->paymentMethod === 'tok_timeout') {
            throw new GatewayUnavailable('Timed out waiting for the payment provider.');
        }

        $reference = 'psp_'.substr(hash('sha256', $request->paymentId), 0, 20);

        $result = match ($request->paymentMethod) {
            'tok_visa' => ChargeResult::succeeded($reference),
            'tok_pending' => ChargeResult::pending($reference),
            'tok_chargeDeclined' => ChargeResult::failed($reference, 'card_declined', 'The card was declined.'),
            'tok_insufficientFunds' => ChargeResult::failed($reference, 'insufficient_funds', 'The card has insufficient funds.'),
            default => ChargeResult::failed(null, 'invalid_payment_method', 'Unknown payment method.'),
        };

        if ($result->pspReference !== null) {
            $this->charges[] = $request;
        }

        return $this->results[$request->paymentId] = $result;
    }

    /**
     * @return list<ChargeRequest>
     */
    public function charges(): array
    {
        return $this->charges;
    }
}
