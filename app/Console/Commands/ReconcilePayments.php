<?php

namespace App\Console\Commands;

use App\Domain\Payments\GatewayUnavailable;
use App\Domain\Payments\PaymentGateway;
use App\Domain\Payments\PaymentStatus;
use App\Models\Payment;
use App\Services\Payments\PaymentOutcomeRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Settles payments whose outcome we never learned: the provider timed out
 * and its webhook was lost. Without this, such a payment would stay
 * "processing" forever and block every new attempt on its invoice.
 */
class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile
        {--older-than=15 : Only look at payments processing for at least this many minutes}
        {--give-up-after=60 : Fail payments the provider has no record of after this many minutes}
        {--limit=500 : Maximum payments per run}';

    protected $description = 'Ask the payment provider about payments stuck in processing';

    public function handle(PaymentGateway $gateway, PaymentOutcomeRecorder $outcomes): int
    {
        $giveUpBefore = now()->subMinutes((int) $this->option('give-up-after'));
        $settled = 0;

        $stuck = Payment::query()
            ->where('status', PaymentStatus::Processing)
            ->where('created_at', '<=', now()->subMinutes((int) $this->option('older-than')))
            ->orderBy('created_at')
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($stuck as $payment) {
            try {
                $result = $gateway->retrieve($payment->id);
            } catch (GatewayUnavailable $e) {
                Log::warning('Reconciliation skipped: provider unavailable', ['payment_id' => $payment->id]);

                continue;
            }

            if ($result === null) {
                // The provider never received the request (our payment ID is
                // its idempotency key), so no money moved: safe to fail it
                // and let dunning retry.
                if ($payment->created_at <= $giveUpBefore) {
                    $outcomes->failed($payment, null, 'provider_no_record', 'The payment provider has no record of this charge.');
                    $settled++;
                }

                continue;
            }

            if ($result->status->isFinal()) {
                $outcomes->record($payment, $result);
                $settled++;
            }
        }

        $this->info("Checked {$stuck->count()} payment(s), settled {$settled}.");

        return self::SUCCESS;
    }
}
