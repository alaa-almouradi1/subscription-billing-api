<?php

namespace App\Services\Billing;

use App\Domain\Billing\BillingCalendar;
use App\Domain\Billing\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Outbox\BillingEvents;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RenewalService
{
    public function __construct(
        private readonly InvoiceIssuer $issuer,
        private readonly BillingEvents $events,
    ) {}

    /**
     * IDs of subscriptions whose current period has ended.
     *
     * @return Collection<int, string>
     */
    public function dueSubscriptionIds(DateTimeInterface $now, int $limit): Collection
    {
        return Subscription::query()
            ->where('status', '!=', SubscriptionStatus::Canceled)
            ->where('current_period_end', '<=', $now)
            ->orderBy('current_period_end')
            ->limit($limit)
            ->pluck('id');
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
        });
    }
}
