<?php

namespace Database\Factories;

use App\Domain\Billing\BillingCalendar;
use App\Domain\Billing\BillingInterval;
use App\Domain\Billing\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        $anchor = now()->startOfSecond();
        [$start, $end] = BillingCalendar::period($anchor, 0, BillingInterval::Month);

        return [
            'customer_id' => Customer::factory(),
            'plan_id' => Plan::factory(),
            'status' => SubscriptionStatus::Active,
            'billing_cycle_anchor' => $anchor,
            'periods_billed' => 1,
            'current_period_start' => $start,
            'current_period_end' => $end,
        ];
    }

    public function status(SubscriptionStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
