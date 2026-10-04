<?php

namespace App\Services\Billing;

use App\Domain\Billing\BillingCalendar;
use App\Domain\Billing\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Outbox\BillingEvents;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class RenewalService
{
    /** Retries on deadlocks and lock-wait timeouts (the closure only touches the database). */
    private const TRANSACTION_ATTEMPTS = 3;

    public function __construct(
        private readonly InvoiceIssuer $issuer,
        private readonly BillingEvents $events,
    ) {}

    /**
     * Call $callback with the ID of every subscription whose period has ended.
     *
     * Reads in keyset-paginated chunks (WHERE id > last ORDER BY id), so memory
     * stays flat and no OFFSET scan slows down with millions of rows. The
     * filter matches the (status, current_period_end) index.
     *
     * @param  callable(string): void  $callback
     */
    public function eachDueSubscriptionId(DateTimeInterface $now, callable $callback, int $chunk = 1000): void
    {
        $renewing = array_values(array_filter(
            SubscriptionStatus::cases(),
            fn (SubscriptionStatus $status) => $status->renews(),
        ));

        Subscription::query()
            ->select('id')
            ->whereIn('status', $renewing)
            ->where('current_period_end', '<=', $now)
            ->chunkById($chunk, function ($subscriptions) use ($callback) {
                foreach ($subscriptions as $subscription) {
                    $callback((string) $subscription->id);
                }
            });
    }

    /**
     * Advance one subscription by exactly one period.
     *
     * Returns the issued invoice, or null when there was nothing to do
     * (not due any more, canceled, or another worker got there first).
     * Safe to call repeatedly and concurrently for the same subscription.
     */
    public function renew(string $subscriptionId, DateTimeInterface $now): ?Invoice
    {
        return DB::transaction(function () use ($subscriptionId, $now) {
            $subscription = Subscription::query()->with('plan')->lockForUpdate()->find($subscriptionId);

            // Re-check under the row lock: the list of due IDs may be stale.
            if ($subscription === null
                || ! $subscription->status->renews()
                || $subscription->current_period_end > $now) {
                return null;
            }

            if ($subscription->cancel_at_period_end) {
                $subscription->transitionTo(SubscriptionStatus::Canceled);
                $subscription->canceled_at = $subscription->current_period_end;
                $subscription->save();
                $this->events->subscriptionCanceled($subscription, 'period_end');

                return null;
            }

            [$start, $end] = BillingCalendar::period(
                $subscription->billing_cycle_anchor,
                $subscription->periods_billed,
                $subscription->plan->interval,
            );

            $invoice = $this->issuer->issueForPeriod($subscription, $start, $end);

            if ($subscription->status === SubscriptionStatus::Trialing) {
                $subscription->transitionTo(SubscriptionStatus::Active);
            }

            $subscription->periods_billed++;
            $subscription->current_period_start = $start;
            $subscription->current_period_end = $end;
            $subscription->save();

            return $invoice;
        }, self::TRANSACTION_ATTEMPTS);
    }
}
