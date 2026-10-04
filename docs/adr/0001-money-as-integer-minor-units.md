# 1. Money as integer minor units

Status: accepted

## Context

Billing multiplies and splits prices constantly: proration, credits,
discounts. Floating-point numbers cannot represent most decimal fractions
exactly (`0.1 + 0.2 !== 0.3`), and a cent lost per invoice adds up across
millions of invoices. Decimal strings (`DECIMAL(19,4)` with bcmath) are
exact but force every calculation through string functions.

## Decision

- Store and compute every amount as an integer in the currency's minor unit
  (cents), together with its ISO 4217 currency code.
- Wrap amounts in an immutable `Money` value object that refuses to combine
  different currencies.
- Round exactly once, at the end of a calculation, half away from zero
  (`Money::multiplyByRatio`). Detect integer overflow instead of silently
  falling back to floats.

## Consequences

- Arithmetic is exact and fast; database columns are plain `BIGINT`.
- The API exposes `{"amount": 1999, "currency": "EUR"}`, and clients format it.
- Currencies with three decimals (KWD) or none (JPY) still work as long as
  callers think in minor units; a formatting layer would need an exponent
  table, which is not needed yet.
