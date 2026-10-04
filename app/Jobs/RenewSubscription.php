<?php

namespace App\Jobs;

use App\Services\Billing\RenewalService;
use App\Services\Payments\AutoCollector;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Renews one subscription (catching up missed periods) and charges the new
 * invoices. Runs on queue workers, so renewals scale horizontally and a slow
 * payment provider never holds up the scheduler or other subscriptions.
 *
 * Safe to run twice: RenewalService re-checks under a row lock. ShouldBeUnique
 * only avoids queueing duplicate work while a job is still pending.
 */
class RenewSubscription implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Upper bound on periods caught up in one job (guards against a bad anchor). */
    public const MAX_PERIODS = 24;

    public int $tries = 5;

    public int $uniqueFor = 900;

    public function __construct(public readonly string $subscriptionId)
    {
        $this->onQueue('billing');
    }

    public function uniqueId(): string
    {
        return $this->subscriptionId;
    }

    /**
     * @return list<int> seconds to wait before each retry
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(RenewalService $renewals, AutoCollector $collector): void
    {
        $now = now();

        for ($i = 0; $i < self::MAX_PERIODS; $i++) {
            $invoice = $renewals->renew($this->subscriptionId, $now);

            if ($invoice === null) {
                return;
            }

            $collector->collect($invoice);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('Subscription renewal failed after all retries', [
            'subscription_id' => $this->subscriptionId,
            'exception' => $e,
        ]);
    }
}
