<?php

namespace App\Services\Payments;

use App\Domain\Billing\InvoiceStatus;
use App\Domain\Billing\SubscriptionStatus;
use App\Domain\Payments\ChargeResult;
use App\Domain\Payments\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies the final outcome of a payment to payment, invoice and subscription.
 *
 * Outcomes arrive from two places that can race each other: the synchronous
 * gateway response and the provider's webhook. Every method therefore locks
 * the payment row and is idempotent: recording the same outcome twice is a
 * no-op.
 */
class PaymentOutcomeRecorder
{
    public function record(Payment $payment, ChargeResult $result): Payment
    {
        return match ($result->status) {
            PaymentStatus::Succeeded => $this->succeeded($payment, (string) $result->pspReference),
            PaymentStatus::Failed => $this->failed($payment, $result->pspReference, (string) $result->failureCode, (string) $result->failureMessage),
            PaymentStatus::Processing => $this->pending($payment, (string) $result->pspReference),
        };
    }

    public function succeeded(Payment $payment, string $pspReference): Payment
    {
        return DB::transaction(function () use ($payment, $pspReference) {
            $payment = $this->lock($payment);

            if (! $this->canSettle($payment, PaymentStatus::Succeeded)) {
                return $payment;
            }

            $payment->fill([
                'status' => PaymentStatus::Succeeded,
                'psp_reference' => $pspReference,
                'settled_at' => now(),
            ])->save();

            $invoice = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);

            if (! $invoice->status->isPayable()) {
                // E.g. the invoice was voided while the payment was pending.
                // Money was taken, so this needs a human (and a refund).
                Log::critical('Payment succeeded for an invoice that is not payable', [
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'invoice_status' => $invoice->status->value,
                ]);

                return $payment;
            }

            $invoice->transitionTo(InvoiceStatus::Paid);
            $invoice->paid_at = now();
            $invoice->save();

            $subscription = $this->lockSubscription($invoice);

            if ($subscription?->status === SubscriptionStatus::PastDue) {
                $subscription->transitionTo(SubscriptionStatus::Active);
                $subscription->save();
            }

            return $payment;
        });
    }

    public function failed(Payment $payment, ?string $pspReference, string $code, string $message): Payment
    {
        return DB::transaction(function () use ($payment, $pspReference, $code, $message) {
            $payment = $this->lock($payment);

            if (! $this->canSettle($payment, PaymentStatus::Failed)) {
                return $payment;
            }

            $payment->fill([
                'status' => PaymentStatus::Failed,
                'psp_reference' => $pspReference ?? $payment->psp_reference,
                'failure_code' => $code,
                'failure_message' => $message,
                'settled_at' => now(),
            ])->save();

            $invoice = Invoice::query()->findOrFail($payment->invoice_id);
            $subscription = $this->lockSubscription($invoice);

            if (in_array($subscription?->status, [SubscriptionStatus::Active, SubscriptionStatus::Trialing], true)) {
                $subscription->transitionTo(SubscriptionStatus::PastDue);
                $subscription->save();
            }

            return $payment;
        });
    }

    private function pending(Payment $payment, string $pspReference): Payment
    {
        $payment->psp_reference = $pspReference;
        $payment->save();

        return $payment;
    }

    private function lock(Payment $payment): Payment
    {
        return Payment::query()->lockForUpdate()->findOrFail($payment->id);
    }

    private function lockSubscription(Invoice $invoice): ?Subscription
    {
        return $invoice->subscription_id === null
            ? null
            : Subscription::query()->lockForUpdate()->find($invoice->subscription_id);
    }

    private function canSettle(Payment $payment, PaymentStatus $outcome): bool
    {
        if ($payment->status === PaymentStatus::Processing) {
            return true;
        }

        if ($payment->status !== $outcome) {
            Log::warning('Ignoring conflicting outcome for an already settled payment', [
                'payment_id' => $payment->id,
                'current' => $payment->status->value,
                'reported' => $outcome->value,
            ]);
        }

        return false;
    }
}
