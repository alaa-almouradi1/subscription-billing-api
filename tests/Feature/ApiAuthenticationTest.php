<?php

namespace Tests\Feature;

use App\Auth\ApiClientRegistry;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_without_an_api_key_are_rejected(): void
    {
        $this->withoutHeader('X-Api-Key')
            ->getJson('/api/v1/plans')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_requests_with_a_wrong_api_key_are_rejected(): void
    {
        $this->withHeader('X-Api-Key', 'nope')
            ->getJson('/api/v1/plans')
            ->assertUnauthorized();
    }

    public function test_the_key_can_also_be_sent_as_a_bearer_token(): void
    {
        $this->withoutHeader('X-Api-Key')
            ->withToken(self::API_KEY)
            ->getJson('/api/v1/plans')
            ->assertOk();
    }

    public function test_two_keys_for_the_same_client_allow_rotation(): void
    {
        $this->useApiClients([
            ['name' => 'dunning', 'key' => 'old-key', 'scopes' => ['billing.read']],
            ['name' => 'dunning', 'key' => 'new-key', 'scopes' => ['billing.read']],
        ]);

        $this->withHeader('X-Api-Key', 'old-key')->getJson('/api/v1/plans')->assertOk();
        $this->withHeader('X-Api-Key', 'new-key')->getJson('/api/v1/plans')->assertOk();
    }

    public function test_clients_can_only_use_their_scopes(): void
    {
        $this->useApiClients([['name' => 'reader', 'key' => 'reader-key', 'scopes' => ['billing.read']]]);
        $invoice = Invoice::factory()->create();

        $this->withHeader('X-Api-Key', 'reader-key')
            ->getJson("/api/v1/invoices/{$invoice->id}")
            ->assertOk();

        $this->withHeader('X-Api-Key', 'reader-key')
            ->payInvoice($invoice->id)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'insufficient_scope');

        $this->assertSame(0, $invoice->payments()->count());
    }

    public function test_metrics_require_the_ops_scope(): void
    {
        $this->withoutHeader('X-Api-Key')->get('/api/metrics')->assertUnauthorized();

        $this->useApiClients([['name' => 'reader', 'key' => 'reader-key', 'scopes' => ['billing.read']]]);
        $this->withHeader('X-Api-Key', 'reader-key')->get('/api/metrics')->assertForbidden();
    }

    public function test_only_key_hashes_are_configured_and_malformed_entries_are_ignored(): void
    {
        $registry = new ApiClientRegistry([
            ['name' => 'broken'],
            ['name' => 'short-hash', 'key_sha256' => 'abc', 'scopes' => ['*']],
            ['name' => 'ok', 'key_sha256' => hash('sha256', 'secret'), 'scopes' => ['billing.read']],
        ]);

        $this->assertNull($registry->find('abc'));
        $this->assertNull($registry->find(''));
        $this->assertSame('ok', $registry->find('secret')?->name);
        $this->assertFalse($registry->find('secret')->can('payments.write'));
    }

    public function test_generated_keys_are_long_and_random(): void
    {
        $this->assertMatchesRegularExpression('/^bk_[0-9a-f]{64}$/', ApiClientRegistry::generateKey());
        $this->assertNotSame(ApiClientRegistry::generateKey(), ApiClientRegistry::generateKey());
    }

    public function test_the_root_endpoint_describes_the_service(): void
    {
        $this->get('/')->assertOk()->assertJsonStructure(['service', 'api', 'health', 'readiness']);
    }
}
