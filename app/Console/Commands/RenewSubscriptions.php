<?php

namespace App\Console\Commands;

use App\Services\Billing\RenewalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RenewSubscriptions extends Command
{
    protected $signature = 'billing:renew {--limit=500 : Maximum number of subscriptions to process}';

    protected $description = 'Issue invoices for subscriptions whose billing period has ended';

    /**
     * Upper bound on periods caught up per subscription in one run, so a
     * misconfigured anchor can never produce an unbounded invoice loop.
     */
    private const MAX_PERIODS_PER_RUN = 24;

    public function handle(RenewalService $renewals): int
    {
        $now = now();
        $issued = 0;
        $failed = 0;

        foreach ($renewals->dueSubscriptionIds($now, (int) $this->option('limit')) as $id) {
            try {
                // A subscription that was down for several periods is caught up
                // one period (and one invoice) at a time.
                for ($i = 0; $i < self::MAX_PERIODS_PER_RUN; $i++) {
                    if ($renewals->renew($id, $now) === null) {
                        break;
                    }

                    $issued++;
                }
            } catch (Throwable $e) {
                // One broken subscription must not block everyone else's billing.
                $failed++;
                Log::error('Subscription renewal failed', ['subscription_id' => $id, 'exception' => $e]);
            }
        }

        $this->info("Issued {$issued} invoice(s), {$failed} failure(s).");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
