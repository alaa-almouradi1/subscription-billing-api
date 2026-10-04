# 3. Payment flow: reserve, charge outside the transaction, record

Status: accepted

## Context

Charging a card is a call to an external system that can be slow, fail, or
time out with an **unknown outcome**: the money may or may not have moved.
At the same time, a customer must never be charged twice for one invoice,
whether because of a double click, a client retry, or two workers.

## Decision

`PaymentService::pay()` works in three steps:

1. **Reserve** (short DB transaction): lock the invoice row, check it is
   payable and that no payment is `processing`, then insert a `processing`
   payment. Concurrent attempts serialize on the invoice lock and the second
   one gets `409 payment_in_progress`.
2. **Charge** (no transaction, no locks held): call the provider. The
   payment ID is the provider-side idempotency key, so a retry of the same
   attempt is deduplicated by the provider too.
3. **Record**: `PaymentOutcomeRecorder` applies the outcome to the payment,
   invoice, and subscription in one transaction, locking the payment row.
   It is idempotent, because the synchronous response and the provider's
   webhook can race.

On a timeout the payment **stays `processing`** and the API answers
`503 gateway_unavailable`. It is not marked failed, because it may have
succeeded. The provider's signed webhook settles it later.

At the HTTP level, `POST /invoices/{id}/pay` requires an `Idempotency-Key`
header. The first response is stored for 24 hours and replayed for retries
with the same key and body. Server errors (5xx) are not stored, so the client
can safely retry them.

## Alternatives considered

- **Calling the provider inside the DB transaction**: simpler, but a slow
  provider would hold row locks and exhaust connections under load.
- **Marking timeouts as failed**: leads to dunning a customer who actually
  paid, or to a second charge on retry.

## Consequences

- A stuck `processing` payment blocks new attempts on that invoice until the
  webhook arrives. That is the safe failure mode. A reconciliation job that
  polls the provider for old `processing` payments would be the next step.
- Webhook events are deduplicated by provider event ID, which is the primary
  key of `webhook_events`.
