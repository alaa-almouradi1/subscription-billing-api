<?php

namespace Tests\Feature;

use App\Models\OutboxMessage;
use App\Models\Subscription;
use App\Outbox\Outbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_is_ok_when_dependencies_are_healthy(): void
    {
        $this->getJson('/api/health/ready')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', 'ok');
    }

    public function test_readiness_degrades_when_events_stop_flowing(): void
    {
        DB::transaction(fn () => app(Outbox::class)->record('stuck.event', 'key', []));
        $this->travel(10)->minutes();

        $this->getJson('/api/health/ready')
            ->assertStatus(503)
            ->assertJsonPath('status', 'degraded');
    }

    public function test_metrics_are_exposed_in_prometheus_format(): void
    {
        Subscription::factory()->count(2)->create();
        DB::transaction(fn () => app(Outbox::class)->record('pending.event', 'key', []));

        $response = $this->get('/api/metrics')->assertOk();

        $this->assertStringContainsString('billing_outbox_backlog '.OutboxMessage::query()->whereNull('published_at')->count(), $response->getContent());
        $this->assertStringContainsString('billing_subscriptions{status="active"} 2', $response->getContent());
    }

    public function test_every_response_carries_a_request_id(): void
    {
        $this->getJson('/api/v1/plans')->assertHeader('X-Request-Id');
    }

    public function test_a_valid_incoming_request_id_is_propagated(): void
    {
        $this->getJson('/api/v1/plans', ['X-Request-Id' => 'upstream-req-12345'])
            ->assertHeader('X-Request-Id', 'upstream-req-12345');
    }

    public function test_a_malformed_incoming_request_id_is_replaced(): void
    {
        $response = $this->getJson('/api/v1/plans', ['X-Request-Id' => "bad\nvalue"]);

        $this->assertNotSame("bad\nvalue", $response->headers->get('X-Request-Id'));
    }
}
