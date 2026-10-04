# 2. Billing periods are derived from a fixed anchor

Status: accepted

## Context

The naive renewal rule, `next_end = current_end + 1 month`, drifts.
A subscription started on 31 January renews on 28 February, then on
28 March, 28 April... forever, because PHP and most libraries clamp
or overflow when the target month is shorter.

## Decision

Each subscription stores a `billing_cycle_anchor` and a `periods_billed`
counter. Period *n* is computed from the anchor:

```
start(n) = anchor + n months      (day clamped to the month's length)
end(n)   = anchor + (n + 1) months
```

So a 31 January anchor gives 28 Feb, 31 Mar, 30 Apr, 31 May, and so on.
`BillingCalendar` implements this without framework dependencies and is
covered by unit tests, including leap years and year boundaries.

## Consequences

- Renewal dates are stable and predictable for customers.
- Plan changes must keep the same interval (monthly to yearly would change
  what "period n" means), so `PlanChangeService` rejects them; customers
  cancel and resubscribe instead. This is a deliberate scope limit.
- Renewal is idempotent: running `billing:renew` twice, or on two workers,
  re-checks the period under a row lock, and a unique index on
  `(subscription_id, period_start)` is the final safety net.
