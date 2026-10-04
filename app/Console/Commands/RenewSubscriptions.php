<?php

namespace App\Console\Commands;

use App\Jobs\RenewSubscription;
use App\Services\Billing\RenewalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RenewSubscriptions extends Command
{
    protected $signature = 'billing:renew';

    protected $description = 'Queue a renewal job for every subscription whose billing period has ended';

    public function handle(RenewalService $renewals): int
    {
        $queued = 0;
        $failed = 0;

        $renewals->eachDueSubscriptionId(now(), function (string $id) use (&$queued, &$failed) {
            try {
                RenewSubscription::dispatch($id);
                $queued++;
            } catch (Throwable $e) {
                // Only reachable with the sync queue (tests, local dev): one
                // broken subscription must not stop everyone else's billing.
                $failed++;
                Log::error('Subscription renewal failed', ['subscription_id' => $id, 'exception' => $e]);
            }
        });

        $this->info("Queued {$queued} renewal(s), {$failed} failure(s).");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
