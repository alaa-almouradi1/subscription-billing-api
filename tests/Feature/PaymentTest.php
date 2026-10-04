<?php

namespace Tests\Feature;

use App\Domain\Billing\InvoiceStatus;
use App\Domain\Billing\SubscriptionStatus;
use App\Domain\Payments\PaymentGateway;
use App\Domain\Payments\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function subscribeWithCard(string $token): Subscription
    {
        return app(SubscriptionService::class)->subscribe(
            Customer::factory()->withPaymentMethod($token)->create(),
            Plan::factory()->price(2_000)->create(),
        );
    }

    public function test_a_successful_first_charge_pays_the_invoice(): void
    {
        $subscription = $this->subscribeWithCard('tok_visa');

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame(InvoiceStatus::Paid, $subscription->latestInvoice->status);
        $this->assertNotNull($subscription->latestInvoice->paid_at);
    }

    public function test_a_declined_first_charge_makes_the_subscription_past_due(): void
    {
        $subscription = $this->subscribeWithCard('tok_chargeDeclined');
        $invoice = $subscription->latestInvoice;

        $this->assertSame(SubscriptionStatus::PastDue, $subscription->status);
        $this->assertSame(InvoiceStatus::Open, $invoice->status);
        $this->assertSame('card_declined', $invoice->payments()->first()->failure_code);
    }

    public function test_paying_with_a_new_card_recovers_a_past_due_subscription(): void
    {
        $subscription = $this->subscribeWithCard('tok_insufficientFunds');
        $invoice = $subscription->latestInvoice;

        $this->postJson("/api/v1/invoices/{$invoice->id}/pay", ['payment_method' => 'tok_visa'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('data.amount.amount', 2_000);

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $subscription->fresh()->status);
    }

    public function test_a_declined_retry_is_reported_as_a_failed_attempt_not_an_error(): void
    {
        $invoice = $this->subscribeWithCard('tok_chargeDeclined')->latestInvoice;

        $this->postJson("/api/v1/invoices/{$invoice->id}/pay")
            ->assertCreated()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.failure_code', 'card_declined');
    }

    public function test_a_paid_invoice_cannot_be_charged_again(): void
    {
        $invoice = $this->subscribeWithCard('tok_visa')->latestInvoice;

        $this->postJson("/api/v1/invoices/{$invoice->id}/pay")
            ->assertConflict()
            ->assertJsonPath('error.code', 'invoice_not_payable');

        $this->assertCount(1, app(PaymentGateway::class)->charges());
    }

    public function test_a_pending_payment_blocks_a_second_attempt(): void
    {
        $invoice = $this->subscribeWithCard('tok_pending')->latestInvoice;

        $this->assertSame(PaymentStatus::Processing, $invoice->payments()->first()->status);
        $this->assertSame(InvoiceStatus::Open, $invoice->fresh()->status);

        $this->postJson("/api/v1/invoices/{$invoice->id}/pay", ['payment_method' => 'tok_visa'])
            ->assertConflict()
            ->assertJsonPath('error.code', 'payment_in_progress');
    }

    public function test_a_provider_timeout_leaves_the_outcome_open_instead_of_failing(): void
    {
        $invoice = Invoice::factory()->create();

        $this->postJson("/api/v1/invoices/{$invoice->id}/pay", ['payment_method' => 'tok_timeout'])
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'gateway_unavailable');

        $this->assertSame(PaymentStatus::Processing, $invoice->payments()->first()->status);
        $this->assertSame(InvoiceStatus::Open, $invoice->fresh()->status);
    }

    public function test_a_trial_ending_without_a_card_goes_past_due(): void
    {
        $this->travelTo('2026-03-01 00:00:00');
        $subscription = app(SubscriptionService::class)->subscribe(
            Customer::factory()->create(['default_payment_method' => null]),
            Plan::factory()->withTrial(7)->create(),
        );

        $this->travelTo('2026-03-08 00:00:00');
        $this->artisan('billing:renew')->assertSuccessful();

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::PastDue, $subscription->status);
        $this->assertSame('payment_method_required', $subscription->latestInvoice->payments()->first()->failure_code);
    }
}
