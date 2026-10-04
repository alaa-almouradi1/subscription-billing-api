<?php

namespace App\Console\Commands;

use App\Domain\Payments\WebhookSignature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Local development helper: plays the payment provider and delivers a
 * signed webhook, e.g. to settle a payment made with the tok_pending card.
 */
class SimulatePspWebhook extends Command
{
    protected $signature = 'psp:simulate-webhook
        {payment : ID of the payment to settle}
        {outcome=succeeded : succeeded or failed}
        {--url= : Base URL of the API (defaults to APP_URL)}';

    protected $description = 'Send a signed payment-provider webhook to this API';

    public function handle(): int
    {
        $outcome = $this->argument('outcome');

        if (! in_array($outcome, ['succeeded', 'failed'], true)) {
            $this->error('Outcome must be "succeeded" or "failed".');

            return self::INVALID;
        }

        $payload = json_encode([
            'id' => 'evt_'.Str::lower(Str::random(24)),
            'type' => "payment.{$outcome}",
            'data' => array_filter([
                'payment_id' => $this->argument('payment'),
                'failure_code' => $outcome === 'failed' ? 'card_declined' : null,
                'failure_message' => $outcome === 'failed' ? 'The card was declined.' : null,
            ]),
        ], JSON_THROW_ON_ERROR);

        $url = rtrim($this->option('url') ?: config('app.url'), '/').'/api/webhooks/psp';

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Psp-Signature' => WebhookSignature::header($payload, (string) config('billing.webhooks.secret'), now()->getTimestamp()),
        ])->withBody($payload, 'application/json')->post($url);

        $this->line("{$response->status()} {$response->body()}");

        return $response->successful() ? self::SUCCESS : self::FAILURE;
    }
}
