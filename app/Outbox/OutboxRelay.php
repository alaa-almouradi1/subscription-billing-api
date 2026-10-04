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

            $published = 0;

            foreach ($messages as $message) {
                $message->attempts++;

                try {
                    $this->publisher->publish($message);
                } catch (Throwable $e) {
                    $message->last_error = mb_substr($e->getMessage(), 0, 1000);
                    $message->save();

                    Log::error('Outbox publish failed; will retry', [
                        'event_id' => $message->event_id,
                        'attempts' => $message->attempts,
                        'error' => $e->getMessage(),
                    ]);

                    // Stop here: publishing later messages first would break
                    // the per-subscription ordering consumers rely on.
                    break;
                }

                $message->published_at = now();
                $message->last_error = null;
                $message->save();
                $published++;
            }

            return $published;
        });
    }

    public function backlog(): int
    {
        return OutboxMessage::query()->whereNull('published_at')->count();
    }
}
