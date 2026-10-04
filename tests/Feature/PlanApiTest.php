<?php

namespace Tests\Feature;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_plan_can_be_created(): void
    {
        $this->postJson('/api/v1/plans', [
            'code' => 'pro-monthly',
            'name' => 'Pro',
            'amount' => 4_900,
            'currency' => 'eur',
            'interval' => 'month',
            'trial_days' => 14,
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'pro-monthly')
            ->assertJsonPath('data.price.amount', 4_900)
            ->assertJsonPath('data.price.currency', 'EUR')
            ->assertJsonPath('data.trial_days', 14);

        $this->assertDatabaseHas('plans', ['code' => 'pro-monthly', 'currency' => 'EUR']);
    }

    public function test_plan_validation(): void
    {
        Plan::factory()->create(['code' => 'taken']);

        $this->postJson('/api/v1/plans', [
            'code' => 'taken',
            'name' => 'Duplicate',
            'amount' => -1,
            'currency' => 'EURO',
            'interval' => 'week',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'amount', 'currency', 'interval']);
    }

    public function test_only_active_plans_are_listed_cheapest_first(): void
    {
        Plan::factory()->price(9_900)->create(['code' => 'premium']);
        Plan::factory()->price(1_900)->create(['code' => 'basic']);
        Plan::factory()->create(['code' => 'legacy', 'active' => false]);

        $this->getJson('/api/v1/plans')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'basic')
            ->assertJsonPath('data.1.code', 'premium');
    }

    public function test_unknown_plans_return_404(): void
    {
        $this->getJson('/api/v1/plans/0198b6a0-0000-7000-8000-000000000000')->assertNotFound();
    }
}
