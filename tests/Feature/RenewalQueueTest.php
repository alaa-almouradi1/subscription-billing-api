<?php

namespace Tests\Feature;

use App\Domain\Billing\SubscriptionStatus;
use App\Jobs\RenewSubscription;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RenewalQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_scheduler_queues_one_job_per_due_subscription(): void
    {
        Queue::fake();
        $this->travelTo('2026-05-01 00:00:00');

        $due = Subscription::factory()->count(3)->create(['current_period_end' => '2026-04-30 00:00:00']);
        Subscription::factory()->create(['current_period_end' => '2026-05-15 00:00:00']);
        Subscription::factory()->status(SubscriptionStatus::Canceled)->create(['current_period_end' => '2026-04-01 00:00:00']);

        $this->artisan('billing:renew')->assertSuccessful();

        Queue::assertPushedOn('billing', RenewSubscription::class);
        Queue::assertPushed(RenewSubscription::class, 3);
        foreach ($due as $subscription) {
            Queue::assertPushed(RenewSubscription::class, fn (RenewSubscription $job) => $job->subscriptionId === $subscription->id);
        }
    }

    public function test_jobs_are_unique_per_subscription_and_retry_with_backoff(): void
    {
        $job = new RenewSubscription('sub-1');

        $this->assertSame('sub-1', $job->uniqueId());
        $this->assertSame(5, $job->tries);
        $this->assertSame([10, 60, 300], $job->backoff());
    }
}
