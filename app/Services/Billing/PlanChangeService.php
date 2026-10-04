<?php

namespace App\Services\Billing;

use App\Domain\Billing\PlanChangeRejected;
use App\Domain\Billing\ProrationCalculator;
use App\Domain\Billing\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class PlanChangeService
{
    public function __construct(private readonly InvoiceIssuer $issuer) {}

    /**
     * Switch plans immediately, prorating the current period.
     *
     *  - upgrade:   the customer pays the difference now (proration invoice)
     *  - downgrade: the difference becomes credit for the next invoice
     *  - trial:     nothing to prorate, the plan is simply swapped
     *
     * Returns the proration invoice, if one was issued.
     */
    public function change(Subscription $subscription, Plan $newPlan): ?Invoice
    {
        return DB::transaction(function () use ($subscription, $newPlan) {
            $subscription = Subscription::query()->with('plan')->lockForUpdate()->findOrFail($subscription->id);
            $oldPlan = $subscription->plan;

            $this->guard($subscription, $oldPlan, $newPlan);

            $invoice = null;

            if ($subscription->status !== SubscriptionStatus::Trialing) {
                $proration = ProrationCalculator::calculate(
                    $subscription->current_period_start,
                    $subscription->current_period_end,
                    now(),
                    $oldPlan->price(),
                    $newPlan->price(),
                );

                $net = $proration->net();

                if ($net->isPositive()) {
                    $invoice = $this->issuer->issueForProration($subscription, $proration, $oldPlan, $newPlan);
                } elseif ($net->isNegative()) {
                    $customer = Customer::query()->lockForUpdate()->findOrFail($subscription->customer_id);
                    $customer->addCredit($net->negate());
                    $customer->save();
                }
            }

            $subscription->plan()->associate($newPlan);
            $subscription->save();

            return $invoice;
        });
    }

    private function guard(Subscription $subscription, Plan $oldPlan, Plan $newPlan): void
    {
        if ($subscription->status === SubscriptionStatus::Canceled) {
            throw PlanChangeRejected::subscriptionCanceled();
        }

        if ($oldPlan->is($newPlan)) {
            throw PlanChangeRejected::samePlan();
        }

        if (! $newPlan->active) {
            throw PlanChangeRejected::planInactive();
        }

        // Periods are derived from the anchor and the plan's interval, so the
        // interval must not change mid-subscription (see BillingCalendar).
        if ($oldPlan->interval !== $newPlan->interval || $oldPlan->currency !== $newPlan->currency) {
            throw PlanChangeRejected::incompatible();
        }

        if ($subscription->current_period_end <= now()) {
            throw PlanChangeRejected::renewalPending();
        }
    }
}
