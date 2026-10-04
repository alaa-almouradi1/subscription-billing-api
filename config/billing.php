<?php

$list = fn (?string $value): array => array_values(array_filter(array_map('trim', explode(',', (string) $value))));

return [

    /*
    |--------------------------------------------------------------------------
    | API authentication
    |--------------------------------------------------------------------------
    |
    | Service-to-service clients authenticate with an X-Api-Key header.
    | Several keys may be configured at once so they can be rotated without
    | downtime.
    |
    */

    'api_keys' => $list(env('BILLING_API_KEYS')),

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

];
