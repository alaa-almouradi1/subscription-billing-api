<?php

namespace App\Outbox;

use App\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Publishes unpublished outbox messages in commit order.
 *
 * Delivery is at-least-once: if the process dies after the broker
 * acknowledged a message but before it is marked as published, it is sent
 * again on the next run. Consumers deduplicate by event ID.
 */
class OutboxRelay
{
    public function __construct(private readonly EventPublisher $publisher) {}

    /**
     * @return int number of messages published
     */
    public function relayBatch(int $limit = 100): int
    {
        return DB::transaction(function () use ($limit) {
            // SKIP LOCKED (MariaDB 10.6+, MySQL 8, PostgreSQL) lets an
            // accidental second relay skip rows instead of publishing them twice.
            // SQLite ignores the clause.
            $messages = OutboxMessage::query()
                ->whereNull('published_at')
                ->orderBy('id')
                ->limit($limit)
                ->lock('for update skip locked')
                ->get();

            if ($messages->isEmpty()) {
                return 0;
            }

            $ids = $messages->modelKeys();

            try {
                // One produce-and-flush round trip for the whole batch.
                $this->publisher->publishBatch($messages->all());
            } catch (Throwable $e) {
                // Nothing is marked as published: the same batch is retried in
                // the same order, so per-subscription ordering is preserved.
                // Messages the broker did accept are sent again (consumers
                // deduplicate by event ID).
                OutboxMessage::query()->whereKey($ids)->update([
                    'attempts' => DB::raw('attempts + 1'),
                    'last_error' => mb_substr($e->getMessage(), 0, 1000),
                ]);

                Log::error('Outbox publish failed; will retry', [
                    'first_event_id' => $messages->first()->event_id,
                    'batch_size' => count($ids),
                    'error' => $e->getMessage(),
                ]);

                return 0;
            }

            // A single UPDATE instead of one per message.
            OutboxMessage::query()->whereKey($ids)->update([
                'published_at' => now(),
                'attempts' => DB::raw('attempts + 1'),
                'last_error' => null,
            ]);

            return count($ids);
        });
    }

    public function backlog(): int
    {
        return OutboxMessage::query()->whereNull('published_at')->count();
    }
}
