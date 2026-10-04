<?php

namespace App\Outbox\Publishers;

use App\Models\OutboxMessage;
use App\Outbox\EventPublisher;
use RuntimeException;

/**
 * Test double: keeps published messages in memory and can simulate an
 * unavailable broker.
 */
final class InMemoryEventPublisher implements EventPublisher
{
    /** @var list<array{key: string, value: array<string, mixed>}> */
    public array $published = [];

    private int $failuresRemaining = 0;

    public function failNext(int $times = 1): void
    {
        $this->failuresRemaining = $times;
    }

    public function publish(OutboxMessage $message): void
    {
        if ($this->failuresRemaining > 0) {
            $this->failuresRemaining--;

            throw new RuntimeException('Broker unavailable');
        }

        $this->published[] = [
            'key' => $message->partition_key,
            'value' => json_decode($message->toWireFormat(), true),
        ];
    }
}
