<?php

namespace Tests\Feature;

use App\Models\OutboxMessage;
use App\Models\WebhookEvent;
use App\Outbox\Outbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RetentionTest extends TestCase
{
    use RefreshDatabase;

    private function prune(): void
    {
        $this->artisan('model:prune', ['--model' => [OutboxMessage::class, WebhookEvent::class]])
            ->assertSuccessful();
    }

    public function test_published_outbox_messages_are_pruned_after_the_retention_period(): void
    {
        DB::transaction(fn () => app(Outbox::class)->record('old', 'k', []));
        DB::transaction(fn () => app(Outbox::class)->record('never-published', 'k', []));
        OutboxMessage::query()->where('event_type', 'old')->update(['published_at' => now()]);

        $this->travel(8)->days();
        $this->prune();

        $this->assertSame(['never-published'], OutboxMessage::query()->pluck('event_type')->all());
    }

    public function test_webhook_events_are_kept_for_the_redelivery_window_then_pruned(): void
    {
        DB::table('webhook_events')->insert([
            ['id' => 'evt_old', 'type' => 'payment.succeeded', 'payload' => '{}', 'received_at' => now()->subDays(31)],
            ['id' => 'evt_new', 'type' => 'payment.succeeded', 'payload' => '{}', 'received_at' => now()->subDays(29)],
        ]);

        $this->prune();

        $this->assertSame(['evt_new'], WebhookEvent::query()->pluck('id')->all());
    }
}
