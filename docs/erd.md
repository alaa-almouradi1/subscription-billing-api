# Entity relationship diagram

```mermaid
erDiagram
    CUSTOMERS ||--o{ SUBSCRIPTIONS : has
    CUSTOMERS ||--o{ INVOICES : receives
    PLANS ||--o{ SUBSCRIPTIONS : "priced by"
    SUBSCRIPTIONS ||--o{ INVOICES : "billed through"
    INVOICES ||--|{ INVOICE_LINES : contains
    INVOICES ||--o{ PAYMENTS : "settled by"

    CUSTOMERS {
        uuid id PK
        string name
        string email UK
        string default_payment_method "PSP token, never card data"
        bigint credit_balance "minor units, from downgrades"
        char3 credit_currency
    }
    PLANS {
        uuid id PK
        string code UK
        string name
        bigint amount "minor units"
        char3 currency
        string interval "month | year"
        smallint trial_days
        bool active
    }
    SUBSCRIPTIONS {
        uuid id PK
        uuid customer_id FK
        uuid plan_id FK
        string status "trialing | active | past_due | canceled"
        datetime billing_cycle_anchor "all periods derive from this"
        int periods_billed
        datetime current_period_start
        datetime current_period_end
        datetime trial_ends_at
        bool cancel_at_period_end
        datetime canceled_at
    }
    INVOICES {
        uuid id PK
        string number UK "INV-YYYY-NNNNNN, gapless"
        uuid customer_id FK
        uuid subscription_id FK
        string status "open | paid | void | uncollectible"
        char3 currency
        bigint subtotal
        bigint credit_applied
        bigint amount_due
        datetime period_start "UK with subscription_id"
        datetime period_end
        datetime issued_at
        datetime paid_at
    }
    INVOICE_LINES {
        uuid id PK
        uuid invoice_id FK
        string type "subscription | proration_credit | proration_charge"
        string description
        bigint amount "negative for credits"
    }
    PAYMENTS {
        uuid id PK
        uuid invoice_id FK
        bigint amount
        char3 currency
        string status "processing | succeeded | failed"
        string payment_method
        string psp_reference UK
        string failure_code
        datetime settled_at
    }
```

## Supporting tables

These have no foreign keys into the domain; they exist for reliability.

| Table | Purpose | Key constraint | Retention |
|---|---|---|---|
| `invoice_sequences` | Gapless invoice numbers per year, incremented under a row lock | `prefix` primary key | forever (one row per year) |
| `idempotency_keys` | Stored responses for `Idempotency-Key` replays | unique `(scope, idempotency_key)` | 24 hours |
| `webhook_events` | Provider events already applied | provider event ID as primary key | 30 days |
| `outbox_messages` | Domain events waiting to be published to Kafka | auto-increment `id` gives publish order | 7 days after publishing |

Old rows are deleted in chunks by the daily `model:prune` command.

## Constraints that protect money

- `invoices (subscription_id, period_start)` is **unique**: even if two renewal
  workers race, a period can only be invoiced once.
- `payments.psp_reference` is **unique**: one provider charge maps to one payment.
- All amounts are **integers in minor units** (see [ADR 0001](adr/0001-money-as-integer-minor-units.md)).
- Business timestamps use `DATETIME`, not `TIMESTAMP`, which overflows in 2038.
