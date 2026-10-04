<?php

namespace App\Outbox;

use App\Models\OutboxMessage;

interface EventPublisher
{
    /**
     * Publish messages in the given order. All-or-nothing: must throw unless
     * the broker acknowledged every message, so the relay retries the batch.
     *
     * @param  list<OutboxMessage>  $messages
     */
    public function publishBatch(array $messages): void;
}
