<?php

namespace App\Outbox;

use App\Models\OutboxMessage;

interface EventPublisher
{
    /**
     * Publish one message. Must throw if the broker did not acknowledge it,
     * so the relay keeps it for a later retry.
     */
    public function publish(OutboxMessage $message): void;
}
