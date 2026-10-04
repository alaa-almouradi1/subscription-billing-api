<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    protected const API_KEY = 'test-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['billing.api_keys' => [self::API_KEY]]);
        $this->withHeader('X-Api-Key', self::API_KEY);
    }

    /**
     * POST /invoices/{id}/pay with a fresh (or given) Idempotency-Key.
     *
     * @param  array<string, mixed>  $body
     */
    protected function payInvoice(string $invoiceId, array $body = [], ?string $idempotencyKey = null): TestResponse
    {
        return $this->postJson(
            "/api/v1/invoices/{$invoiceId}/pay",
            $body,
            ['Idempotency-Key' => $idempotencyKey ?? (string) Str::uuid()],
        );
    }
}
