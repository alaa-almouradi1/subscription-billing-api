<?php

namespace App\Outbox\Publishers;

use App\Models\OutboxMessage;
use App\Outbox\EventPublisher;
use Illuminate\Support\Facades\Log;

/**
 * Local development without a broker: events are written to the log.
 */
final class LogEventPublisher implements EventPublisher
{
    public function publish(OutboxMessage $message): void
    {
        Log::info('Event published', [
            'key' => $message->partition_key,
            'event' => json_decode($message->toWireFormat(), true),
        ]);
    }
}
