# 4. Publish domain events through a transactional outbox

Status: accepted

## Context

Other services (for example dunning) react to billing events. Writing to
the database and then publishing to Kafka is a **dual write**: if the
process crashes between the two, either an event is lost (DB committed,
Kafka not) or a phantom event is published (Kafka first, DB rolled back).

## Decision

- Services record events with `Outbox::record()` **inside** the transaction
  that changes state. The call throws if no transaction is open.
- `php artisan outbox:relay` reads unpublished rows in `id` order, publishes
  each to Kafka (idempotent producer, `acks=all`, flush per message), and
  marks it published.
- On a broker error the relay stops the batch rather than skipping ahead,
  preserving per-key order, and retries on the next loop.
- `SELECT ... FOR UPDATE SKIP LOCKED` keeps an accidentally scaled-out relay
  from publishing the same rows concurrently.

## Alternatives considered

- **Change data capture** (Debezium on the MariaDB binlog plus Kafka Connect's
  outbox event router): no polling and lower latency, but it needs Kafka
  Connect infrastructure. The table layout here is compatible with
  Debezium's outbox router, so switching later would not change the contract.
- **Laravel queued listeners**: still a dual write between the DB and the
  queue.

## Consequences

- Delivery is at least once. Consumers deduplicate by event ID.
- Run **one** relay replica for strict ordering. A second replica would not
  double-publish (SKIP LOCKED), but could reorder events across batches.
- The relay is a moving part to monitor: `/api/health/ready` degrades and
  `billing_outbox_lag_seconds` rises if events stop flowing.
