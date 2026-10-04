<?php

namespace Tests\Feature;

use App\Domain\Billing\InvoiceStatus;
use App\Domain\Billing\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenewalTest extends TestCase
{
    use RefreshDatabase;

    private function subscribe(?Plan $plan = null): Subscription
    {
        return app(SubscriptionService::class)->subscribe(
            Customer::factory()->create(),
            $plan ?? Plan::factory()->price(2_500)->create(),
        );
    }

    public function test_nothing_happens_before_the_period_ends(): void
    {
        $this->travelTo('2026-01-31 10:00:00');
        $subscription = $this->subscribe();

        $this->travelTo('2026-02-28 09:59:59');
        $this->artisan('billing:renew')->assertSuccessful();

        $this->assertSame(1, $subscription->invoices()->count());
    }

    public function test_an_invoice_is_issued_for_the_next_period(): void
    {
        $this->travelTo('2026-01-31 10:00:00');
        $subscription = $this->subscribe();

        $this->travelTo('2026-02-28 10:00:00');
        $this->artisan('billing:renew')->assertSuccessful();

        $subscription->refresh();
        $invoice = $subscription->latestInvoice;

        $this->assertSame(2, $subscription->invoices()->count());
        $this->assertSame(InvoiceStatus::Open, $invoice->status);
        $this->assertSame(2_500, $invoice->amount_due);
        $this->assertSame('2026-02-28', $invoice->period_start->format('Y-m-d'));
        $this->assertSame('2026-03-31', $invoice->period_end->format('Y-m-d'));
        $this->assertSame('2026-03-31', $subscription->current_period_end->format('Y-m-d'));
        $this->assertCount(1, $invoice->lines);
    }

    public function test_running_twice_never_double_bills(): void
    {
        $this->travelTo('2026-01-31 10:00:00');
        $subscription = $this->subscribe();

        $this->travelTo('2026-03-01 00:00:00');
        $this->artisan('billing:renew')->assertSuccessful();
        $this->artisan('billing:renew')->assertSuccessful();

        $this->assertSame(2, $subscription->invoices()->count());
    }

    public function test_missed_periods_are_caught_up_one_invoice_at_a_time(): void
    {
        $this->travelTo('2026-01-15 00:00:00');
        $subscription = $this->subscribe();

        $this->travelTo('2026-04-20 00:00:00');
        $this->artisan('billing:renew')->assertSuccessful();

        $periods = $subscription->invoices()->orderBy('period_start')->pluck('period_start')
            ->map(fn ($date) => $date->format('Y-m-d'))
            ->all();

        $this->assertSame(['2026-01-15', '2026-02-15', '2026-03-15', '2026-04-15'], $periods);
    }

    public function test_a_trial_converts_to_active_with_its_first_invoice(): void
    {
        $this->travelTo('2026-03-01 00:00:00');
        $subscription = $this->subscribe(Plan::factory()->withTrial(14)->create());
        $this->assertSame(0, $subscription->invoices()->count());

        $this->travelTo('2026-03-15 00:00:00');
        $this->artisan('billing:renew')->assertSuccessful();

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame('2026-03-15', $subscription->latestInvoice->period_start->format('Y-m-d'));
        $this->assertSame('2026-04-15', $subscription->current_period_end->format('Y-m-d'));
    }

    public function test_cancel_at_period_end_stops_billing(): void
    {
        $this->travelTo('2026-01-10 00:00:00');
        $subscription = $this->subscribe();
        app(SubscriptionService::class)->cancel($subscription, atPeriodEnd: true);

        $this->travelTo('2026-02-10 00:00:00');
        $this->artisan('billing:renew')->assertSuccessful();

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Canceled, $subscription->status);
        $this->assertSame('2026-02-10', $subscription->canceled_at->format('Y-m-d'));
        $this->assertSame(1, $subscription->invoices()->count());
    }

    public function test_invoice_numbers_are_sequential_per_year(): void
    {
        $this->travelTo('2026-12-01 00:00:00');
        $this->subscribe();
        $this->subscribe();
        $this->travelTo('2027-01-01 00:00:00');
        $this->subscribe();

        $this->assertSame(
            ['INV-2026-000001', 'INV-2026-000002', 'INV-2027-000001'],
            Invoice::query()->orderBy('number')->pluck('number')->all(),
        );
    }

    public function test_free_plans_produce_invoices_that_are_already_paid(): void
    {
        $subscription = $this->subscribe(Plan::factory()->price(0)->create());

        $this->assertSame(InvoiceStatus::Paid, $subscription->latestInvoice->status);
    }
}
