<?php

namespace Tests\Feature;

use App\Domain\Billing\InvoiceStatus;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanChangeTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Plan $basic;

    private Plan $pro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::factory()->create();
        $this->basic = Plan::factory()->price(3_000)->create(['name' => 'Basic']);
        $this->pro = Plan::factory()->price(6_000)->create(['name' => 'Pro']);
    }

    /**
     * Subscribe on 1 April: the period is 30 days long, which keeps the
     * prorated amounts round.
     */
    private function subscribeOnFirstOfApril(Plan $plan): Subscription
    {
        $this->travelTo('2026-04-01 00:00:00');

        return app(SubscriptionService::class)->subscribe($this->customer, $plan);
    }

    public function test_upgrading_charges_the_prorated_difference_immediately(): void
    {
        $subscription = $this->subscribeOnFirstOfApril($this->basic);
        $this->travelTo('2026-04-11 00:00:00');

        $this->postJson("/api/v1/subscriptions/{$subscription->id}/change-plan", ['plan_id' => $this->pro->id])
            ->assertOk()
            ->assertJsonPath('data.plan.id', $this->pro->id)
            ->assertJsonPath('data.latest_invoice.amount_due.amount', 2_000);

        $invoice = $subscription->fresh()->latestInvoice;
        $this->assertNull($invoice->period_start);
        $this->assertSame([-2_000, 4_000], $invoice->lines()->orderBy('amount')->pluck('amount')->all());
    }

    public function test_downgrading_grants_credit_that_reduces_the_next_invoice(): void
    {
        $subscription = $this->subscribeOnFirstOfApril($this->pro);
        $this->travelTo('2026-04-11 00:00:00');

        $this->postJson("/api/v1/subscriptions/{$subscription->id}/change-plan", ['plan_id' => $this->basic->id])
            ->assertOk();

        $this->assertSame(2_000, $this->customer->fresh()->creditBalance('EUR')->amount);

        $this->travelTo('2026-05-01 00:00:00');
        $this->artisan('billing:renew')->assertSuccessful();

        $renewal = $subscription->fresh()->latestInvoice;
        $this->assertSame(3_000, $renewal->subtotal);
        $this->assertSame(2_000, $renewal->credit_applied);
        $this->assertSame(1_000, $renewal->amount_due);
        $this->assertSame(0, $this->customer->fresh()->creditBalance('EUR')->amount);
    }

    public function test_credit_larger_than_the_invoice_settles_it_and_carries_over(): void
    {
        $this->customer->addCredit($this->basic->price()->add($this->basic->price()));
        $this->customer->save();

        $subscription = $this->subscribeOnFirstOfApril($this->basic);

        $invoice = $subscription->latestInvoice;
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(0, $invoice->amount_due);
        $this->assertSame(3_000, $this->customer->fresh()->creditBalance('EUR')->amount);
    }

    public function test_changing_plans_during_a_trial_is_free(): void
    {
        $trialPlan = Plan::factory()->price(3_000)->withTrial(14)->create();
        $subscription = $this->subscribeOnFirstOfApril($trialPlan);

        $this->postJson("/api/v1/subscriptions/{$subscription->id}/change-plan", ['plan_id' => $this->pro->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'trialing')
            ->assertJsonPath('data.latest_invoice', null);
    }

    public function test_switching_billing_interval_is_rejected(): void
    {
        $subscription = $this->subscribeOnFirstOfApril($this->basic);
        $yearly = Plan::factory()->yearly()->create();

        $this->postJson("/api/v1/subscriptions/{$subscription->id}/change-plan", ['plan_id' => $yearly->id])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'incompatible_plan');
    }

    public function test_changing_to_the_current_plan_is_rejected(): void
    {
        $subscription = $this->subscribeOnFirstOfApril($this->basic);

        $this->postJson("/api/v1/subscriptions/{$subscription->id}/change-plan", ['plan_id' => $this->basic->id])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'same_plan');
    }
}
