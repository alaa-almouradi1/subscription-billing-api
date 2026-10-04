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
}
