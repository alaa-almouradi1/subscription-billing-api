<?php

namespace Database\Factories;

use App\Domain\Billing\BillingInterval;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'amount' => 1_999,
            'currency' => 'EUR',
            'interval' => BillingInterval::Month,
            'trial_days' => 0,
            'active' => true,
        ];
    }

    public function price(int $amount, string $currency = 'EUR'): static
    {
        return $this->state(fn () => ['amount' => $amount, 'currency' => $currency]);
    }

    public function withTrial(int $days): static
    {
        return $this->state(fn () => ['trial_days' => $days]);
    }

    public function yearly(): static
    {
        return $this->state(fn () => ['interval' => BillingInterval::Year]);
    }
}
