# 6. Renew subscriptions with queued jobs

Status: accepted

## Context

The first `billing:renew` renewed and charged every due subscription in a
loop, inside the scheduler process. At scale that fails in two ways: one
process bounds throughput, and since charging calls the payment provider, a
slow provider delays every renewal behind it.

## Decision

- The scheduler only finds due subscriptions (keyset pagination, so memory
  stays flat over millions of rows) and queues one `RenewSubscription` job
  per subscription on the `billing` queue (Redis).
- Jobs are `ShouldBeUnique` per subscription, so a slow queue cannot pile up
  duplicates. Correctness does not depend on this: `RenewalService` re-checks
  under a row lock, and the unique index on `(subscription_id, period_start)`
  remains the last line of defence.
- Jobs retry with backoff (10 s, 60 s, 5 min) and log loudly after the last
  failure.
- The scheduler uses `onOneServer()`, so several scheduler instances can run
  for availability without queueing the same work twice.

## Consequences

- Renewal throughput scales with the number of workers
  (`docker compose up --scale worker=N`, or replicas in Kubernetes).
- Redis becomes a dependency. It also carries rate-limit counters and
  scheduler locks, which have to be shared across instances anyway.
- Tests run the queue synchronously, so the same renewal tests cover both
  modes.
