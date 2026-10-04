<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API authentication
    |--------------------------------------------------------------------------
    |
    | Each calling service has a name, scopes, and the SHA-256 hash of its
    | key (never the key itself). Generate both with:
    |
    |   php artisan billing:client dunning-service --scope=billing.read ...
    |
    | Rotate a key by adding a second entry with the same name, deploying the
    | new key to the client, then removing the old entry.
    |
    | Scopes: billing.read, customers.write, plans.write, subscriptions.write,
    | invoices.write, payments.write, ops.read, or "*" for everything.
    |
    */

    'api_clients' => json_decode((string) env('BILLING_API_CLIENTS', '[]'), true) ?: [],

    /*
    |--------------------------------------------------------------------------
    | Rate limits (requests per minute)
    |--------------------------------------------------------------------------
    |
    | Counters live in the cache store, so every API instance shares them
    | (use Redis in production). Payment attempts are also limited per
    | invoice, which bounds retry storms and card-testing abuse.
    |
    */

    'rate_limits' => [
        'per_client' => (int) env('BILLING_RATE_LIMIT_PER_CLIENT', 600),
        'payments_per_invoice' => (int) env('BILLING_RATE_LIMIT_PAYMENTS_PER_INVOICE', 5),
        'webhooks_per_ip' => (int) env('BILLING_RATE_LIMIT_WEBHOOKS_PER_IP', 1200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    |
    | "fake" is a deterministic provider driven by test card tokens
    | (see App\Infrastructure\Payments\FakePaymentGateway).
    |
    */

    'payments' => [
        'gateway' => env('BILLING_PAYMENT_GATEWAY', 'fake'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment provider webhooks
    |--------------------------------------------------------------------------
    |
    | Signed with HMAC-SHA256 (see App\Domain\Payments\WebhookSignature).
    | Requests older than the tolerance are rejected as possible replays.
    |
    */

    'webhooks' => [
        'secret' => env('BILLING_WEBHOOK_SECRET', ''),
        'tolerance' => (int) env('BILLING_WEBHOOK_TOLERANCE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain events
    |--------------------------------------------------------------------------
    |
    | Driver used by `php artisan outbox:relay`: "kafka", "log" or "memory".
    |
    */

    'events' => [
        'driver' => env('BILLING_EVENTS_DRIVER', 'log'),
        'kafka' => [
            'brokers' => env('KAFKA_BROKERS', 'localhost:9092'),
            'topic' => env('BILLING_EVENTS_TOPIC', 'billing.events'),
        ],
    ],

];
