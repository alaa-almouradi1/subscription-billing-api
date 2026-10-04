<?php

namespace Tests\Feature;

use App\Domain\Billing\InvoiceStatus;
use App\Domain\Billing\SubscriptionStatus;
use App\Domain\Payments\PaymentStatus;
use App\Domain\Payments\WebhookSignature;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PspWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test';

    protected function setUp(): void
    {
        parent::setUp();

        config(['billing.webhooks.secret' => self::SECRET]);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function deliver(array $event, ?string $signature = null): TestResponse
    {
        $payload = json_encode($event);
        $signature ??= WebhookSignature::header($payload, self::SECRET, now()->getTimestamp());

        return $this->call('POST', '/api/webhooks/psp', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_PSP_SIGNATURE' => $signature,
        ], content: $payload);
    }

    private function pendingSubscription(): Subscription
    {
        return app(SubscriptionService::class)->subscribe(
            Customer::factory()->withPaymentMethod('tok_pending')->create(),
            Plan::factory()->create(),
        );
    }

    private function pendingPayment(Subscription $subscription): Payment
    {
        return $subscription->latestInvoice->payments()->sole();
    }

    public function test_a_success_webhook_settles_a_pending_payment(): void
    {
        $subscription = $this->pendingSubscription();
        $payment = $this->pendingPayment($subscription);

        $this->deliver(['id' => 'evt_1', 'type' => 'payment.succeeded', 'data' => ['payment_id' => $payment->id]])
            ->assertOk()
            ->assertJsonPath('status', 'processed');

        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertSame(InvoiceStatus::Paid, $subscription->latestInvoice->fresh()->status);
    }

    public function test_a_failure_webhook_makes_the_subscription_past_due(): void
    {
        $subscription = $this->pendingSubscription();
        $payment = $this->pendingPayment($subscription);

        $this->deliver(['id' => 'evt_2', 'type' => 'payment.failed', 'data' => [
            'payment_id' => $payment->id,
            'failure_code' => 'expired_card',
        ]])->assertOk();

        $this->assertSame('expired_card', $payment->fresh()->failure_code);
        $this->assertSame(SubscriptionStatus::PastDue, $subscription->fresh()->status);
    }

    public function test_redelivered_events_are_applied_only_once(): void
    {
        $payment = $this->pendingPayment($this->pendingSubscription());
        $event = ['id' => 'evt_3', 'type' => 'payment.succeeded', 'data' => ['payment_id' => $payment->id]];

        $this->deliver($event)->assertJsonPath('status', 'processed');
        $this->deliver($event)->assertOk()->assertJsonPath('status', 'duplicate');

        $this->assertDatabaseCount('webhook_events', 1);
    }

    public function test_a_late_conflicting_outcome_does_not_overwrite_a_settled_payment(): void
    {
        $subscription = $this->pendingSubscription();
        $payment = $this->pendingPayment($subscription);

        $this->deliver(['id' => 'evt_4', 'type' => 'payment.succeeded', 'data' => ['payment_id' => $payment->id]]);
        $this->deliver(['id' => 'evt_5', 'type' => 'payment.failed', 'data' => ['payment_id' => $payment->id]]);

        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $subscription->fresh()->status);
    }

    public function test_invalid_signatures_are_rejected(): void
    {
        $this->deliver(['id' => 'evt_6', 'type' => 'payment.succeeded', 'data' => ['payment_id' => 'x']], 't=1,v1=forged')
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'invalid_signature');

        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_events_for_unknown_payments_are_acknowledged_and_ignored(): void
    {
        // Answering 2xx stops the provider from retrying an event we can never apply.
        $this->deliver(['id' => 'evt_7', 'type' => 'payment.succeeded', 'data' => ['payment_id' => 'does-not-exist']])
            ->assertOk()
            ->assertJsonPath('status', 'ignored');
    }

    public function test_malformed_events_are_rejected(): void
    {
        $this->deliver(['type' => 'payment.succeeded'])->assertUnprocessable();
    }
}
