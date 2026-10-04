<?php

namespace App\Domain\Payments;

/**
 * Port to the payment service provider (PSP).
 *
 * The application only depends on this interface; a real adapter (Adyen,
 * Stripe, Mollie, ...) can be added without touching billing logic.
 */
interface PaymentGateway
{
    /**
     * @throws GatewayUnavailable when the outcome is unknown (timeout,
     *                            connection reset, 5xx). Callers must NOT
     *                            assume the charge failed in that case.
     */
    public function charge(ChargeRequest $request): ChargeResult;

    /**
     * Ask the provider what happened to a charge (by our payment ID, which
     * was sent as its idempotency key / merchant reference).
     *
     * Returns null when the provider has no record of it, i.e. the charge
     * request never arrived and no money moved.
     *
     * @throws GatewayUnavailable
     */
    public function retrieve(string $paymentId): ?ChargeResult;
}
