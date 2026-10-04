<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\OutboxMessage;
use App\Models\Plan;
use App\Outbox\Outbox;
use App\Services\Billing\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class OutboxEventsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function recordedTypes(): array
    {
        return OutboxMessage::query()->orderBy('id')->pluck('event_type')->all();
    }

    public function test_a_successful_signup_records_its_events_in_order(): void
    {
        $subscription = app(SubscriptionService::class)->subscribe(
            Customer::factory()->create(),
            Plan::factory()->create(),
        );

        $this->assertSame(
            ['subscription.created', 'invoice.issued', 'payment.succeeded', 'invoice.paid'],
            $this->recordedTypes(),
        );
        $this->assertSame(
            [$subscription->id],
            OutboxMessage::query()->distinct()->pluck('partition_key')->all(),
            'All events of a subscription share its partition key.',
        );
    }

    public function test_a_declined_payment_records_the_failure_and_the_past_due_transition(): void
    {
        app(SubscriptionService::class)->subscribe(
            Customer::factory()->withPaymentMethod('tok_chargeDeclined')->create(),
            Plan::factory()->price(1_500)->create(),
        );

        $this->assertSame(
            ['subscription.created', 'invoice.issued', 'payment.failed', 'subscription.past_due'],
            $this->recordedTypes(),
        );

        $failed = OutboxMessage::query()->where('event_type', 'payment.failed')->sole();
        $this->assertSame('card_declined', $failed->payload['failure_code']);
        $this->assertSame(1, $failed->payload['failed_attempts']);
        $this->assertSame(['amount' => 1_500, 'currency' => 'EUR'], $failed->payload['amount']);
    }

    public function test_events_are_not_recorded_when_the_transaction_rolls_back(): void
    {
        $invoice = Invoice::factory()->create();

        try {
            DB::transaction(function () use ($invoice) {
                $this->postJson("/api/v1/invoices/{$invoice->id}/void")->assertOk();
                throw new RuntimeException('Simulated failure after the change');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame([], $this->recordedTypes());
    }

    public function test_the_wire_format_is_a_versioned_envelope(): void
    {
        DB::transaction(fn () => app(Outbox::class)->record('invoice.voided', 'sub_1', ['invoice_id' => 'inv_1']));

        $wire = json_decode(OutboxMessage::query()->sole()->toWireFormat(), true);

        $this->assertSame('invoice.voided', $wire['type']);
        $this->assertSame(1, $wire['version']);
        $this->assertSame('subscription-billing-api', $wire['source']);
        $this->assertSame(['invoice_id' => 'inv_1'], $wire['data']);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $wire['id']);
    }
}
