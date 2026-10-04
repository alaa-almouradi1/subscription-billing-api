<?php

namespace App\Outbox;

use App\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * Transactional outbox: domain events are written to the database in the
 * SAME transaction as the state change they describe, and published to
 * Kafka afterwards by the relay (see OutboxRelay).
 *
 * This avoids the dual-write problem: either both the change and its event
 * are committed, or neither is. See docs/adr/0004-transactional-outbox.md.
 */
class Outbox
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function record(string $type, string $partitionKey, array $data): OutboxMessage
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException("Event [{$type}] must be recorded inside the transaction that caused it.");
        }

        return OutboxMessage::create([
            'event_id' => (string) Str::uuid7(),
            'event_type' => $type,
            'partition_key' => $partitionKey,
            'payload' => $data,
            'occurred_at' => now(),
        ]);
    }
}
