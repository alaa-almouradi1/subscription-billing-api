<?php

namespace App\Outbox\Publishers;

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

    /** @var list<int> */
    public array $batchSizes = [];

    public function publishBatch(array $messages): void
    {
        if ($this->failuresRemaining > 0) {
            $this->failuresRemaining--;

            throw new RuntimeException('Broker unavailable');
        }

        $this->batchSizes[] = count($messages);

        foreach ($messages as $message) {
            $this->published[] = [
                'key' => $message->partition_key,
                'value' => json_decode($message->toWireFormat(), true),
            ];
        }
    }
}
