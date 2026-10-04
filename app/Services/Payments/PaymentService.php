<?php

namespace App\Services\Payments;

use App\Domain\Payments\ChargeRequest;
use App\Domain\Payments\GatewayUnavailable;
use App\Domain\Payments\PaymentGateway;
use App\Domain\Payments\PaymentRejected;
use App\Domain\Payments\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Charges an invoice in three steps (see docs/adr/0003-payment-flow.md):
 *
 *  1. Reserve: in a short transaction, lock the invoice, check it is payable
 *     and that no other attempt is in flight, and insert a `processing`
 *     payment. This is what prevents double charges.
 *  2. Charge: call the provider OUTSIDE any transaction, so a slow provider
 *     never holds database locks.
 *  3. Record: apply the outcome. If the provider timed out the payment stays
 *     `processing`; the webhook settles it later.
 */
class PaymentService
{
    /** Retries on deadlocks and lock-wait timeouts (the closure only touches the database). */
    private const TRANSACTION_ATTEMPTS = 3;

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly PaymentOutcomeRecorder $outcomes,
    ) {}

    public function pay(Invoice $invoice, ?string $paymentMethod = null): Payment
    {
        $payment = $this->reserve($invoice, $paymentMethod);

        try {
            $result = $this->gateway->charge(new ChargeRequest(
                paymentId: $payment->id,
                amount: $payment->money(),
                paymentMethod: $payment->payment_method,
                description: "Invoice {$invoice->number}",
            ));
        } catch (GatewayUnavailable $e) {
            Log::warning('Payment outcome unknown, waiting for provider webhook', [
                'payment_id' => $payment->id,
                'reason' => $e->getMessage(),
            ]);

            throw PaymentRejected::gatewayUnavailable();
        }

        return $this->outcomes->record($payment, $result);
    }

    /**
     * A trial ended (or a renewal came due) and there is nothing to charge.
     * Recorded as a failed attempt so the subscription goes past due and
     * dunning can ask the customer for a card.
     */
    public function recordMissingPaymentMethod(Invoice $invoice): Payment
    {
        $payment = $this->reserve($invoice, 'none');

        return $this->outcomes->failed($payment, null, 'payment_method_required', 'The customer has no payment method on file.');
    }

    private function reserve(Invoice $invoice, ?string $paymentMethod): Payment
    {
        return DB::transaction(function () use ($invoice, $paymentMethod) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (! $invoice->status->isPayable()) {
                throw PaymentRejected::invoiceNotPayable($invoice->status->value);
            }

            $inFlight = Payment::query()
                ->where('invoice_id', $invoice->id)
                ->where('status', PaymentStatus::Processing)
                ->exists();

            if ($inFlight) {
                throw PaymentRejected::alreadyInProgress();
            }

            $method = $paymentMethod ?? Customer::query()->whereKey($invoice->customer_id)->value('default_payment_method');

            if ($method === null) {
                throw PaymentRejected::missingPaymentMethod();
            }

            $payment = new Payment([
                'amount' => $invoice->amount_due,
                'currency' => $invoice->currency,
                'status' => PaymentStatus::Processing,
                'payment_method' => $method,
            ]);
            $payment->invoice()->associate($invoice);
            $payment->save();

            return $payment;
        }, self::TRANSACTION_ATTEMPTS);
    }
}
