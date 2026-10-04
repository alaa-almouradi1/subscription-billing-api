<?php

namespace Tests\Feature;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitAndHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_client_has_its_own_request_budget(): void
    {
        config(['billing.rate_limits.per_client' => 2]);
        $this->useApiClients([
            ['name' => 'busy', 'key' => 'busy-key', 'scopes' => ['*']],
            ['name' => 'calm', 'key' => 'calm-key', 'scopes' => ['*']],
        ]);

        $this->withHeader('X-Api-Key', 'busy-key')->getJson('/api/v1/plans')->assertOk();
        $this->withHeader('X-Api-Key', 'busy-key')->getJson('/api/v1/plans')->assertOk();
        $this->withHeader('X-Api-Key', 'busy-key')->getJson('/api/v1/plans')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'rate_limited')
            ->assertHeader('Retry-After');

        $this->withHeader('X-Api-Key', 'calm-key')->getJson('/api/v1/plans')->assertOk();
    }

    public function test_payment_attempts_are_limited_per_invoice(): void
    {
        config(['billing.rate_limits.payments_per_invoice' => 2]);
        $invoice = Invoice::factory()->create();
        $other = Invoice::factory()->create();

        $this->payInvoice($invoice->id, ['payment_method' => 'tok_chargeDeclined'])->assertCreated();
        $this->payInvoice($invoice->id, ['payment_method' => 'tok_chargeDeclined'])->assertCreated();
        $this->payInvoice($invoice->id, ['payment_method' => 'tok_visa'])->assertStatus(429);

        $this->payInvoice($other->id)->assertCreated();
    }

    public function test_webhooks_are_limited_per_ip(): void
    {
        config(['billing.rate_limits.webhooks_per_ip' => 1]);

        $this->postJson('/api/webhooks/psp', [])->assertStatus(400);
        $this->postJson('/api/webhooks/psp', [])->assertStatus(429);
    }

    public function test_api_responses_carry_security_headers_and_are_never_cached(): void
    {
        $this->getJson('/api/v1/plans')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');

        $this->assertStringContainsString('no-store', $this->getJson('/api/v1/plans')->headers->get('Cache-Control'));
    }
}
