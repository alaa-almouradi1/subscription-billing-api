<?php

namespace Tests\Feature;

use App\Domain\Billing\InvoiceStatus;
use App\Domain\Billing\SubscriptionStatus;
use App\Domain\Payments\ChargeResult;
use App\Domain\Payments\PaymentGateway;
use App\Domain\Payments\PaymentStatus;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function subscribeWith(string $token): Subscription
    {
        $this->travelTo('2026-10-04 12:00:00');

        return app(SubscriptionService::class)->subscribe(
            Customer::factory()->withPaymentMethod($token)->create(),
            Plan::factory()->create(),
        );
    }

    private function payment(Subscription $subscription): Payment
    {
        return $subscription->latestInvoice->payments()->sole();
    }

    public function test_a_charge_the_provider_settled_later_is_picked_up(): void
    {
        $subscription = $this->subscribeWith('tok_pending');
        $payment = $this->payment($subscription);
        app(PaymentGateway::class)->settle($payment->id, ChargeResult::succeeded('psp_late'));

        $this->travel(20)->minutes();
        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertSame(InvoiceStatus::Paid, $subscription->latestInvoice->fresh()->status);
    }

    public function test_a_charge_that_never_reached_the_provider_is_failed_after_the_grace_period(): void
    {
        $subscription = $this->subscribeWith('tok_timeout');
        $payment = $this->payment($subscription);
        $this->assertSame(PaymentStatus::Processing, $payment->status);

        $this->travel(20)->minutes();
        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->assertSame(PaymentStatus::Processing, $payment->fresh()->status, 'still within the grace period');

        $this->travel(45)->minutes();
        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame('provider_no_record', $payment->fresh()->failure_code);
        $this->assertSame(SubscriptionStatus::PastDue, $subscription->fresh()->status);
    }

    public function test_recent_and_still_pending_payments_are_left_alone(): void
    {
        $payment = $this->payment($this->subscribeWith('tok_pending'));

        $this->travel(5)->minutes();
        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->travel(2)->hours();
        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame(PaymentStatus::Processing, $payment->fresh()->status);
    }
}
