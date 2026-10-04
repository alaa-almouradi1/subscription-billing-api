<?php

namespace Tests\Feature;

use App\Domain\Payments\PaymentGateway;
use App\Models\IdempotencyKey;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_paying_requires_an_idempotency_key(): void
    {
        $invoice = Invoice::factory()->create();

        $this->postJson("/api/v1/invoices/{$invoice->id}/pay")
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'idempotency_key_required');
    }

    public function test_a_retried_payment_is_replayed_instead_of_charging_twice(): void
    {
        $invoice = Invoice::factory()->create();

        $first = $this->payInvoice($invoice->id, idempotencyKey: 'retry-me')->assertCreated();
        $second = $this->payInvoice($invoice->id, idempotencyKey: 'retry-me')->assertCreated();

        $second->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertCount(1, app(PaymentGateway::class)->charges());
        $this->assertSame(1, $invoice->payments()->count());
    }

    public function test_reusing_a_key_for_a_different_request_is_rejected(): void
    {
        $invoice = Invoice::factory()->create();

        $this->payInvoice($invoice->id, ['payment_method' => 'tok_visa'], 'same-key')->assertCreated();

        $this->payInvoice($invoice->id, ['payment_method' => 'tok_chargeDeclined'], 'same-key')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'idempotency_key_reused');
    }

    public function test_a_request_still_in_flight_is_reported_as_a_conflict(): void
    {
        $invoice = Invoice::factory()->create();
        IdempotencyKey::create([
            'scope' => hash('sha256', 'test'),
            'idempotency_key' => 'in-flight',
            'fingerprint' => hash('sha256', "POST api/v1/invoices/{$invoice->id}/pay ".json_encode([])),
            'status' => IdempotencyKey::STATUS_PROCESSING,
            'expires_at' => now()->addDay(),
        ]);

        $this->payInvoice($invoice->id, idempotencyKey: 'in-flight')
            ->assertConflict()
            ->assertHeader('Retry-After', '1');
    }

    public function test_keys_are_scoped_per_api_client(): void
    {
        $this->useApiClients([
            ['name' => 'test', 'key' => self::API_KEY, 'scopes' => ['*']],
            ['name' => 'other', 'key' => 'other-client', 'scopes' => ['*']],
        ]);
        $invoice = Invoice::factory()->create();

        $this->payInvoice($invoice->id, idempotencyKey: 'shared')->assertCreated();

        // Same key from another client is a brand-new request (and the
        // invoice is already paid, so it is rejected on its merits).
        $this->withHeader('X-Api-Key', 'other-client')
            ->payInvoice($invoice->id, idempotencyKey: 'shared')
            ->assertConflict()
            ->assertJsonPath('error.code', 'invoice_not_payable');
    }

    public function test_server_errors_are_not_stored_so_the_client_can_retry(): void
    {
        $invoice = Invoice::factory()->create();

        $this->payInvoice($invoice->id, ['payment_method' => 'tok_timeout'], 'after-timeout')->assertStatus(503);

        $this->assertDatabaseMissing('idempotency_keys', ['idempotency_key' => 'after-timeout']);
    }

    public function test_expired_keys_are_pruned(): void
    {
        $invoice = Invoice::factory()->create();
        $this->payInvoice($invoice->id, idempotencyKey: 'old')->assertCreated();

        $this->travel(25)->hours();
        $this->artisan('model:prune', ['--model' => [IdempotencyKey::class]])->assertSuccessful();

        $this->assertDatabaseMissing('idempotency_keys', ['idempotency_key' => 'old']);
    }
}
