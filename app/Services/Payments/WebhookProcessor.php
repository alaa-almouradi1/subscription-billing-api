<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebhookProcessor
{
    /** Retries on deadlocks and lock-wait timeouts (the closure only touches the database). */
    private const TRANSACTION_ATTEMPTS = 3;

    public const RESULT_PROCESSED = 'processed';

    public const RESULT_DUPLICATE = 'duplicate';

    public const RESULT_IGNORED = 'ignored';

    public function __construct(private readonly PaymentOutcomeRecorder $outcomes) {}

    /**
     * Record and apply one provider event exactly once.
     *
     * Storing the event and applying it share a transaction: if applying
     * fails, the event is not stored either, we answer 500, and the
     * provider's retry gets a clean second chance.
     *
     * @param  array{id: string, type: string, data: array<string, mixed>}  $event
     */
    public function process(array $event): string
    {
        return DB::transaction(function () use ($event) {
            $inserted = DB::table('webhook_events')->insertOrIgnore([
                'id' => $event['id'],
                'type' => $event['type'],
                'payload' => json_encode($event),
                'received_at' => now(),
            ]);

            if ($inserted === 0) {
                return self::RESULT_DUPLICATE;
            }

            $paymentId = (string) ($event['data']['payment_id'] ?? '');
            // Guard before querying: MariaDB's native UUID type rejects
            // malformed values, so never send them to the database.
            $payment = Str::isUuid($paymentId) ? Payment::find($paymentId) : null;

            if ($payment === null) {
                Log::warning('Webhook for an unknown payment', ['event_id' => $event['id']]);

                return self::RESULT_IGNORED;
            }

            match ($event['type']) {
                'payment.succeeded' => $this->outcomes->succeeded(
                    $payment,
                    (string) ($event['data']['psp_reference'] ?? $payment->psp_reference),
                ),
                'payment.failed' => $this->outcomes->failed(
                    $payment,
                    $event['data']['psp_reference'] ?? null,
                    (string) ($event['data']['failure_code'] ?? 'unknown'),
                    (string) ($event['data']['failure_message'] ?? 'The payment failed.'),
                ),
                default => null,
            };

            return in_array($event['type'], ['payment.succeeded', 'payment.failed'], true)
                ? self::RESULT_PROCESSED
                : self::RESULT_IGNORED;
        }, self::TRANSACTION_ATTEMPTS);
    }
}
