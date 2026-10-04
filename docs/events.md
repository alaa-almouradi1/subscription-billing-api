# Event contract

The API publishes domain events to the Kafka topic **`billing.events`**.
They are written to the `outbox_messages` table in the same transaction as
the change they describe, then published by `php artisan outbox:relay`
([ADR 0004](adr/0004-transactional-outbox.md)).

## Delivery guarantees

| Guarantee | Detail |
|---|---|
| At least once | A message can be delivered more than once. **Consumers must deduplicate by `id`.** |
| Ordered per subscription | The Kafka message key is the subscription ID (the customer ID for invoices without a subscription), so one subscription's events land on one partition in commit order. |
| No phantom events | An event exists only if its transaction committed. |

## Envelope

```json
{
  "id": "01927d6e-2f7a-7c3e-9b1a-6f0e5c2d8a41",
  "type": "payment.failed",
  "version": 1,
  "source": "subscription-billing-api",
  "occurred_at": "2026-10-03T14:21:07.000000+00:00",
  "data": { }
}
```

Kafka headers `event-id` and `event-type` repeat the envelope values, so
consumers can route or skip messages without parsing the body.

## Event types

Money is always `{"amount": <int minor units>, "currency": "EUR"}`.

| Type | When | `data` fields |
|---|---|---|
| `subscription.created` | Subscription started | `subscription_id`, `customer_id`, `plan_id`, `status`, `current_period_end` |
| `subscription.plan_changed` | Plan switched mid-period | as above + `from_plan_id`, `to_plan_id` |
| `subscription.past_due` | A payment failed on an active or trialing subscription | as `subscription.created` |
| `subscription.reactivated` | A past-due subscription paid | as `subscription.created` |
| `subscription.canceled` | Canceled immediately or at period end | as `subscription.created` + `reason` (`requested` \| `period_end`) |
| `invoice.issued` | New invoice | `invoice_id`, `invoice_number`, `subscription_id`, `customer_id`, `status`, `amount_due` |
| `invoice.paid` | Invoice settled (payment or credit) | as `invoice.issued` |
| `invoice.voided` | Invoice voided | as `invoice.issued` |
| `invoice.marked_uncollectible` | Written off after dunning | as `invoice.issued` |
| `payment.succeeded` | Charge confirmed (synchronously or by webhook) | `payment_id`, `invoice_id`, `subscription_id`, `customer_id`, `amount` |
| `payment.failed` | Charge declined | as `payment.succeeded` + `failure_code`, `failure_message`, `failed_attempts` |

`failed_attempts` counts failed payments on the invoice, including this one.

## Evolving the contract

- Adding a field is backwards compatible; consumers must ignore unknown fields.
- Renaming or removing a field, or changing its meaning, requires a new
  `version` and a period in which both versions are published.
- New event types may appear at any time; consumers must skip types they do
  not know.

## Example: payment.failed

```json
{
  "id": "01927d6e-2f7a-7c3e-9b1a-6f0e5c2d8a41",
  "type": "payment.failed",
  "version": 1,
  "source": "subscription-billing-api",
  "occurred_at": "2026-10-03T14:21:07.000000+00:00",
  "data": {
    "payment_id": "01927d6e-2c11-7a0b-8f3d-1b2c3d4e5f60",
    "invoice_id": "01927d6e-2a00-7e2f-9c1d-0a1b2c3d4e5f",
    "subscription_id": "01927d6e-28f0-7d1e-8b0c-f9a8b7c6d5e4",
    "customer_id": "01927d6e-27e0-7c0d-9a9b-e8f7d6c5b4a3",
    "amount": { "amount": 4900, "currency": "EUR" },
    "failure_code": "insufficient_funds",
    "failure_message": "The card has insufficient funds.",
    "failed_attempts": 1
  }
}
```
