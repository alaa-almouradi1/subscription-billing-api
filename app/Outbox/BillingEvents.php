<?php

namespace App\Outbox;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;

/**
 * The public event contract of the billing domain (documented in
 * docs/events.md). Keeping every payload in one place makes it easy to see
 * what consumers depend on before changing anything.
 *
 * Partition key: the subscription ID (falling back to the customer ID), so
 * all events of one subscription are consumed in order.
 */
class BillingEvents
{
    public function __construct(private readonly Outbox $outbox) {}

    public function subscriptionCreated(Subscription $subscription): void
    {
        $this->outbox->record('subscription.created', $subscription->id, $this->subscription($subscription));
    }

    public function subscriptionPlanChanged(Subscription $subscription, Plan $from, Plan $to): void
    {
        $this->outbox->record('subscription.plan_changed', $subscription->id, $this->subscription($subscription) + [
            'from_plan_id' => $from->id,
            'to_plan_id' => $to->id,
        ]);
    }

    public function subscriptionPastDue(Subscription $subscription): void
    {
        $this->outbox->record('subscription.past_due', $subscription->id, $this->subscription($subscription));
    }

    public function subscriptionReactivated(Subscription $subscription): void
    {
        $this->outbox->record('subscription.reactivated', $subscription->id, $this->subscription($subscription));
    }

    public function subscriptionCanceled(Subscription $subscription, string $reason): void
    {
        $this->outbox->record('subscription.canceled', $subscription->id, $this->subscription($subscription) + [
            'reason' => $reason,
        ]);
    }

    public function invoiceIssued(Invoice $invoice): void
    {
        $this->outbox->record('invoice.issued', $this->key($invoice), $this->invoice($invoice));
    }

    public function invoicePaid(Invoice $invoice): void
    {
        $this->outbox->record('invoice.paid', $this->key($invoice), $this->invoice($invoice));
    }

    public function invoiceVoided(Invoice $invoice): void
    {
        $this->outbox->record('invoice.voided', $this->key($invoice), $this->invoice($invoice));
    }

    public function invoiceMarkedUncollectible(Invoice $invoice): void
    {
        $this->outbox->record('invoice.marked_uncollectible', $this->key($invoice), $this->invoice($invoice));
    }

    public function paymentSucceeded(Payment $payment, Invoice $invoice): void
    {
        $this->outbox->record('payment.succeeded', $this->key($invoice), $this->payment($payment, $invoice));
    }

    /**
     * @param  int  $failedAttempts  failed payments on this invoice so far, including this one
     */
    public function paymentFailed(Payment $payment, Invoice $invoice, int $failedAttempts): void
    {
        $this->outbox->record('payment.failed', $this->key($invoice), $this->payment($payment, $invoice) + [
            'failure_code' => $payment->failure_code,
            'failure_message' => $payment->failure_message,
            'failed_attempts' => $failedAttempts,
        ]);
    }

    private function key(Invoice $invoice): string
    {
        return $invoice->subscription_id ?? $invoice->customer_id;
    }

    /**
     * @return array<string, mixed>
     */
    private function subscription(Subscription $subscription): array
    {
        return [
            'subscription_id' => $subscription->id,
            'customer_id' => $subscription->customer_id,
            'plan_id' => $subscription->plan_id,
            'status' => $subscription->status->value,
            'current_period_end' => $subscription->current_period_end->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invoice(Invoice $invoice): array
    {
        return [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->number,
            'subscription_id' => $invoice->subscription_id,
            'customer_id' => $invoice->customer_id,
            'status' => $invoice->status->value,
            'amount_due' => $invoice->amountDue()->jsonSerialize(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payment(Payment $payment, Invoice $invoice): array
    {
        return [
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'subscription_id' => $invoice->subscription_id,
            'customer_id' => $invoice->customer_id,
            'amount' => $payment->money()->jsonSerialize(),
        ];
    }
}
