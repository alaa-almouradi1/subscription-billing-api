<?php

namespace App\Services\Billing;

use App\Domain\Billing\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Subscription;
use DateTimeInterface;

class InvoiceIssuer
{
    public function __construct(private readonly InvoiceNumberGenerator $numbers) {}

    /**
     * Issue the invoice for one billing period of a subscription.
     * Must be called inside a transaction (see InvoiceNumberGenerator).
     */
    public function issueForPeriod(Subscription $subscription, DateTimeInterface $start, DateTimeInterface $end): Invoice
    {
        $plan = $subscription->plan;
        $price = $plan->price();

        $invoice = Invoice::create([
            'number' => $this->numbers->next(),
            'customer_id' => $subscription->customer_id,
            'subscription_id' => $subscription->id,
            'status' => $price->isZero() ? InvoiceStatus::Paid : InvoiceStatus::Open,
            'currency' => $price->currency,
            'subtotal' => $price->amount,
            'amount_due' => $price->amount,
            'period_start' => $start,
            'period_end' => $end,
            'issued_at' => now(),
            'paid_at' => $price->isZero() ? now() : null,
        ]);

        $invoice->lines()->create([
            'type' => InvoiceLine::TYPE_SUBSCRIPTION,
            'description' => sprintf('%s (%s – %s)', $plan->name, $start->format('Y-m-d'), $end->format('Y-m-d')),
            'amount' => $price->amount,
            'period_start' => $start,
            'period_end' => $end,
        ]);

        return $invoice;
    }
}
