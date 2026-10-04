<?php

namespace App\Services\Billing;

use App\Domain\Billing\BillingCalendar;
use App\Domain\Billing\SubscriptionRejected;
use App\Domain\Billing\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function subscribe(Customer $customer, Plan $plan): Subscription
    {
        if (! $plan->active) {
            throw SubscriptionRejected::planInactive($plan->code);
        }

        if ($plan->trial_days === 0 && $customer->default_payment_method === null) {
            throw SubscriptionRejected::missingPaymentMethod();
        }

        $now = now()->startOfSecond();

        return DB::transaction(function () use ($customer, $plan, $now) {
            $subscription = new Subscription([
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
            ]);

            if ($plan->trial_days > 0) {
                // The first paid period starts when the trial ends.
                $trialEnd = $now->addDays($plan->trial_days);

                $subscription->fill([
                    'status' => SubscriptionStatus::Trialing,
                    'billing_cycle_anchor' => $trialEnd,
                    'periods_billed' => 0,
                    'current_period_start' => $now,
                    'current_period_end' => $trialEnd,
                    'trial_ends_at' => $trialEnd,
                ]);
            } else {
                [$start, $end] = BillingCalendar::period($now, 0, $plan->interval);

                $subscription->fill([
                    'status' => SubscriptionStatus::Active,
                    'billing_cycle_anchor' => $now,
                    'periods_billed' => 1,
                    'current_period_start' => $start,
                    'current_period_end' => $end,
                ]);
            }

            $subscription->save();

            return $subscription;
        });
    }

    public function cancel(Subscription $subscription, bool $atPeriodEnd): Subscription
    {
        return DB::transaction(function () use ($subscription, $atPeriodEnd) {
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);

            if ($subscription->status === SubscriptionStatus::Canceled) {
                throw SubscriptionRejected::alreadyCanceled();
            }

            if ($atPeriodEnd) {
                $subscription->cancel_at_period_end = true;
            } else {
                $subscription->transitionTo(SubscriptionStatus::Canceled);
                $subscription->canceled_at = now();
            }

            $subscription->save();

            return $subscription;
        });
    }
}
