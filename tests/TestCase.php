<?php

namespace Tests;

use App\Auth\ApiClientRegistry;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    protected const API_KEY = 'test-key';

    protected function setUp(): void
    {
        parent::setUp();

        $this->useApiClients([['name' => 'test', 'key' => self::API_KEY, 'scopes' => ['*']]]);
        $this->withHeader('X-Api-Key', self::API_KEY);
    }

    /**
     * @param  list<array{name: string, key: string, scopes: list<string>}>  $clients
     */
    protected function useApiClients(array $clients): void
    {
        config(['billing.api_clients' => array_map(fn (array $client) => [
            'name' => $client['name'],
            'key_sha256' => hash('sha256', $client['key']),
            'scopes' => $client['scopes'],
        ], $clients)]);

        // The registry is a singleton built from config; rebuild it.
        $this->app->forgetInstance(ApiClientRegistry::class);
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
