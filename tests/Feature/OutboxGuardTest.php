<?php

namespace Tests\Feature;

use App\Outbox\Outbox;
use LogicException;
use Tests\TestCase;

/**
 * Deliberately without RefreshDatabase, which would wrap the test in a
 * transaction and hide the bug this guards against.
 */
class OutboxGuardTest extends TestCase
{
    public function test_events_cannot_be_recorded_outside_a_transaction(): void
    {
        $this->expectException(LogicException::class);

        app(Outbox::class)->record('test.event', 'key', []);
    }
}
