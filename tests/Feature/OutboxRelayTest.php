<?php

namespace Tests\Feature;

use App\Models\OutboxMessage;
use App\Outbox\EventPublisher;
use App\Outbox\Outbox;
use App\Outbox\Publishers\InMemoryEventPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OutboxRelayTest extends TestCase
{
    use RefreshDatabase;

    private InMemoryEventPublisher $broker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->broker = new InMemoryEventPublisher;
        $this->app->instance(EventPublisher::class, $this->broker);
    }

    private function record(string $type, string $key = 'sub_1'): void
    {
        DB::transaction(fn () => app(Outbox::class)->record($type, $key, ['n' => $type]));
    }

    public function test_messages_are_published_in_commit_order_and_marked(): void
    {
        $this->record('first');
        $this->record('second');
        $this->record('third', 'sub_2');

        $this->artisan('outbox:relay', ['--once' => true])->assertSuccessful();

        $this->assertSame(['first', 'second', 'third'], array_map(fn ($m) => $m['value']['type'], $this->broker->published));
        $this->assertSame(['sub_1', 'sub_1', 'sub_2'], array_column($this->broker->published, 'key'));
        $this->assertSame(0, OutboxMessage::query()->whereNull('published_at')->count());
    }

    public function test_published_messages_are_not_sent_again(): void
    {
        $this->record('only-once');

        $this->artisan('outbox:relay', ['--once' => true]);
        $this->artisan('outbox:relay', ['--once' => true]);

        $this->assertCount(1, $this->broker->published);
    }

    public function test_a_broker_failure_stops_the_batch_to_preserve_order_and_retries_later(): void
    {
        $this->record('a');
        $this->record('b');
        $this->broker->failNext();

        $this->artisan('outbox:relay', ['--once' => true])->assertSuccessful();

        $this->assertSame([], $this->broker->published);
        $failed = OutboxMessage::query()->orderBy('id')->first();
        $this->assertSame(1, $failed->attempts);
        $this->assertSame('Broker unavailable', $failed->last_error);

        $this->artisan('outbox:relay', ['--once' => true])->assertSuccessful();

        $this->assertSame(['a', 'b'], array_map(fn ($m) => $m['value']['type'], $this->broker->published));
        $this->assertNull($failed->fresh()->last_error);
    }
}
