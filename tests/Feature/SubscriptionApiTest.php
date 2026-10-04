<?php

namespace Tests\Feature;

use App\Domain\Billing\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscribing_without_a_trial_starts_an_active_period_immediately(): void
    {
        $this->travelTo('2026-01-31 10:00:00');
        $customer = Customer::factory()->create();
        $plan = Plan::factory()->create();

        $this->postJson('/api/v1/subscriptions', ['customer_id' => $customer->id, 'plan_id' => $plan->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.plan.id', $plan->id)
            ->assertJsonPath('data.current_period_start', '2026-01-31T10:00:00+00:00')
            ->assertJsonPath('data.current_period_end', '2026-02-28T10:00:00+00:00');
    }

    public function test_subscribing_to_a_plan_with_a_trial(): void
    {
        $this->travelTo('2026-03-01 00:00:00');
        $customer = Customer::factory()->create(['default_payment_method' => null]);
        $plan = Plan::factory()->withTrial(14)->create();

        $this->postJson('/api/v1/subscriptions', ['customer_id' => $customer->id, 'plan_id' => $plan->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'trialing')
            ->assertJsonPath('data.trial_ends_at', '2026-03-15T00:00:00+00:00')
            ->assertJsonPath('data.current_period_end', '2026-03-15T00:00:00+00:00');
    }

    public function test_a_payment_method_is_required_without_a_trial(): void
    {
        $customer = Customer::factory()->create(['default_payment_method' => null]);
        $plan = Plan::factory()->create();

        $this->postJson('/api/v1/subscriptions', ['customer_id' => $customer->id, 'plan_id' => $plan->id])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'payment_method_required');
    }

    public function test_inactive_plans_cannot_be_subscribed_to(): void
    {
        $customer = Customer::factory()->create();
        $plan = Plan::factory()->create(['active' => false]);

        $this->postJson('/api/v1/subscriptions', ['customer_id' => $customer->id, 'plan_id' => $plan->id])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'plan_inactive');
    }

    public function test_cancel_at_period_end_keeps_the_subscription_active(): void
    {
        $subscription = Subscription::factory()->create();

        $this->postJson("/api/v1/subscriptions/{$subscription->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.cancel_at_period_end', true);
    }

    public function test_immediate_cancellation(): void
    {
        $subscription = Subscription::factory()->create();

        $this->postJson("/api/v1/subscriptions/{$subscription->id}/cancel", ['at_period_end' => false])
            ->assertOk()
            ->assertJsonPath('data.status', 'canceled');

        $this->assertNotNull($subscription->fresh()->canceled_at);
    }

    public function test_canceling_twice_is_a_conflict(): void
    {
        $subscription = Subscription::factory()->status(SubscriptionStatus::Canceled)->create();

        $this->postJson("/api/v1/subscriptions/{$subscription->id}/cancel", ['at_period_end' => false])
            ->assertConflict()
            ->assertJsonPath('error.code', 'subscription_canceled');
    }

    public function test_malformed_ids_are_validation_errors_not_server_errors(): void
    {
        $this->postJson('/api/v1/subscriptions', ['customer_id' => 'not-a-uuid', 'plan_id' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id', 'plan_id']);
    }
}
