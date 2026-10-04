# 5. Named API clients with hashed keys and scopes

Status: accepted

## Context

The first version accepted any key from a flat list. Every caller could do
everything, keys sat in configuration in plain text, and there was no way to
tell from the logs which service made a request. For a payments API that is
too much trust: a leaked analytics key could charge cards.

## Decision

- Every caller is a **named client** with a set of **scopes**, configured as
  JSON in `BILLING_API_CLIENTS`.
- Only the **SHA-256 hash** of each key is stored. Keys are 256-bit random
  values from `php artisan billing:client`, so a fast hash is sufficient; a
  slow password hash such as bcrypt would only add latency to every request.
- Lookup compares against every entry with `hash_equals` and never stops
  early, so timing reveals nothing about which entry matched.
- Routes declare the scope they need (`api.scope:payments.write`). The client
  name goes into the log context and scopes idempotency keys and rate limits.
- `AuthenticateApiKey` implements `AuthenticatesRequests`, which places it
  before `ThrottleRequests` in Laravel's middleware priority list, so rate
  limits count per client rather than per IP.

## Alternatives considered

- **OAuth2 client credentials** (e.g. Laravel Passport): short-lived tokens
  and a standard flow, but another moving part (token endpoint, key storage)
  for a small number of internal services. It is the natural next step if
  external partners ever call this API.
- **mTLS between services**: strong, but usually provided by a service mesh
  rather than by the application.

## Consequences

- A compromised client is limited to its scopes, and can be revoked by
  removing one entry.
- Rotation without downtime: two entries with the same name are valid at
  once.
