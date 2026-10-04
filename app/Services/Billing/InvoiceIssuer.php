<?php

namespace App\Services\Billing;

use App\Domain\Billing\InvoiceStatus;
use App\Domain\Billing\Proration;
use App\Domain\Money\Money;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Plan;
use App\Models\Subscription;
use App\Outbox\BillingEvents;
use DateTimeInterface;

/**
 * Creates invoices. All methods must run inside a database transaction
 * (numbering and credit application both rely on row locks).
 */
class InvoiceIssuer
{
    public function __construct(
        private readonly InvoiceNumberGenerator $numbers,
        private readonly BillingEvents $events,
    ) {}

    /**
     * Issue the invoice for one billing period of a subscription.
     */
    public function issueForPeriod(Subscription $subscription, DateTimeInterface $start, DateTimeInterface $end): Invoice
    {
        $plan = $subscription->plan;

        return $this->issue($subscription, $plan->currency, [[
            'type' => InvoiceLine::TYPE_SUBSCRIPTION,
            'description' => sprintf('%s (%s – %s)', $plan->name, $start->format('Y-m-d'), $end->format('Y-m-d')),
            'amount' => $plan->amount,
            'period_start' => $start,
            'period_end' => $end,
        ]], $start, $end);
    }

    /**
     * Issue an invoice for the net positive amount of a mid-period upgrade.
     */
    public function issueForProration(Subscription $subscription, Proration $proration, Plan $from, Plan $to): Invoice
    {
        return $this->issue($subscription, $proration->charge->currency, [
            [
                'type' => InvoiceLine::TYPE_PRORATION_CREDIT,
                'description' => "Unused time on {$from->name}",
                'amount' => $proration->credit->amount,
            ],
            [
                'type' => InvoiceLine::TYPE_PRORATION_CHARGE,
                'description' => "Remaining time on {$to->name}",
                'amount' => $proration->charge->amount,
            ],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function issue(
        Subscription $subscription,
        string $currency,
        array $lines,
        ?DateTimeInterface $periodStart = null,
        ?DateTimeInterface $periodEnd = null,
    ): Invoice {
        $subtotal = Money::of(array_sum(array_column($lines, 'amount')), $currency);
        $credit = $this->consumeCustomerCredit($subscription->customer_id, $subtotal);
        $amountDue = $subtotal->subtract($credit);
        $settled = ! $amountDue->isPositive();

        $invoice = Invoice::create([
            'number' => $this->numbers->next(),
            'customer_id' => $subscription->customer_id,
            'subscription_id' => $subscription->id,
            'status' => $settled ? InvoiceStatus::Paid : InvoiceStatus::Open,
            'currency' => $currency,
            'subtotal' => $subtotal->amount,
            'credit_applied' => $credit->amount,
            'amount_due' => max(0, $amountDue->amount),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'issued_at' => now(),
            'paid_at' => $settled ? now() : null,
        ]);

        $invoice->lines()->createMany($lines);

        $this->events->invoiceIssued($invoice);

        if ($settled) {
            $this->events->invoicePaid($invoice);
        }

        return $invoice;
    }

    /**
     * Use as much of the customer's credit balance as this invoice needs.
     */
    private function consumeCustomerCredit(string $customerId, Money $subtotal): Money
    {
        if (! $subtotal->isPositive()) {
            return Money::zero($subtotal->currency);
        }

        $customer = Customer::query()->lockForUpdate()->findOrFail($customerId);
        $credit = $customer->creditBalance($subtotal->currency)->min($subtotal);

        if ($credit->isPositive()) {
            $customer->deductCredit($credit);
            $customer->save();
        }

        return $credit;
    }
}
