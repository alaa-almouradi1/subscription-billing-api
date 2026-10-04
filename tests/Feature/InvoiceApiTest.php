<?php

namespace Tests\Feature;

use App\Domain\Billing\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscribing_returns_the_first_invoice(): void
    {
        $customer = Customer::factory()->create();
        $plan = Plan::factory()->price(4_900)->create(['name' => 'Pro']);

        $response = $this->postJson('/api/v1/subscriptions', ['customer_id' => $customer->id, 'plan_id' => $plan->id])
            ->assertCreated()
            ->assertJsonPath('data.latest_invoice.status', 'open')
            ->assertJsonPath('data.latest_invoice.amount_due.amount', 4_900);

        $this->getJson('/api/v1/invoices/'.$response->json('data.latest_invoice.id'))
            ->assertOk()
            ->assertJsonPath('data.number', 'INV-'.now()->format('Y').'-000001')
            ->assertJsonPath('data.lines.0.type', 'subscription')
            ->assertJsonPath('data.lines.0.amount.amount', 4_900);
    }

    public function test_a_customers_invoices_are_listed_newest_first(): void
    {
        $customer = Customer::factory()->create();
        Invoice::factory()->for($customer)->create(['number' => 'INV-2026-000001', 'issued_at' => '2026-01-01']);
        Invoice::factory()->for($customer)->create(['number' => 'INV-2026-000002', 'issued_at' => '2026-02-01']);
        Invoice::factory()->create();

        $this->getJson("/api/v1/customers/{$customer->id}/invoices")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.number', 'INV-2026-000002');
    }

    public function test_an_open_invoice_can_be_voided(): void
    {
        $invoice = Invoice::factory()->create();

        $this->postJson("/api/v1/invoices/{$invoice->id}/void")
            ->assertOk()
            ->assertJsonPath('data.status', 'void');

        $this->assertNotNull($invoice->fresh()->voided_at);
    }

    public function test_a_paid_invoice_cannot_be_voided(): void
    {
        $invoice = Invoice::factory()->status(InvoiceStatus::Paid)->create();

        $this->postJson("/api/v1/invoices/{$invoice->id}/void")
            ->assertConflict()
            ->assertJsonPath('error.code', 'invalid_state_transition');
    }

    public function test_an_invoice_can_be_marked_uncollectible(): void
    {
        $invoice = Invoice::factory()->create();

        $this->postJson("/api/v1/invoices/{$invoice->id}/mark-uncollectible")
            ->assertOk()
            ->assertJsonPath('data.status', 'uncollectible');
    }
}
