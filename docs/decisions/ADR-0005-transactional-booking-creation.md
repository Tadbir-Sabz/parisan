# ADR-0005: Transactional Booking Creation

- Status: Accepted for the internal service boundary
- Date: 2026-11-07
- Scope: Parisan Core booking persistence; no public endpoint is authorized by this decision

## Context

Availability calculation is advisory. Two requests can observe the same free slot, so the write path must serialize competing requests and re-check the slot immediately before inserting a booking. A booking and its audit event must either both persist or neither persist.

## Decision

1. Booking creation is implemented as the internal `parisan_core_create_booking()` service. It is not registered as a REST, AJAX, or public form endpoint.
2. The service validates published service/staff records, staff eligibility, configured duration, customer fields, salon-local wall-clock time, and a client-supplied idempotency token.
3. Local time is converted strictly to UTC using the WordPress site timezone. Nonexistent DST wall-clock times are rejected. The booking stores the timezone identifier and UTC start/end instants.
4. The client token is never stored. Its SHA-256 digest is stored under the schema's unique idempotency key. Repeating the same token and same booking payload returns the original booking identity; reusing it with a different payload returns a conflict.
5. The write path requires both bookings and booking-events tables to report InnoDB. It fails closed if transactional storage is unavailable; it does not silently continue on a non-transactional engine.
6. A shared MySQL/MariaDB advisory lock is acquired per staff member with a bounded wait. Booking creation and calendar exception create/delete handlers use the same lock, so the schedule cannot change between the availability recheck and booking insert. Lock failure is a hard failure. Under the lock, the service starts a transaction, checks idempotency, re-runs availability against current bookings and unavailable exceptions, then inserts a pending booking and its `created` audit event before commit.
7. Failure to insert the audit event rolls back the booking. The lock is released in a `finally` path.
8. Service prices in the current WordPress admin UI are entered in toman. The booking snapshot stores integer IRR, converting toman to rial at the explicit 10:1 ratio. Currency is stored as `IRR`. Services configured as “price from” are rejected until the final price is confirmed; a starting price is not silently treated as a final booking price.
9. The service returns booking identity and a reuse indicator only. It does not return customer PII or log customer data.

## Boundaries and follow-up

- This is not a complete public booking workflow. Authorization, public abuse controls/rate limiting, privacy/retention controls, customer-facing confirmation, cancellation/rescheduling, and notification delivery remain separate gates.
- No live SMS, payment, or production deployment is enabled.
- Advisory locks serialize callers using this service and the same database connection/lock namespace. Every future booking writer must use this service or the same lock protocol; direct table writes would bypass the concurrency guarantee.
- The service uses the WordPress site timezone as the salon timezone until a dedicated salon timezone setting is introduced.
- Automated CI coverage exercises successful creation, audit-event persistence, idempotent replay/conflict, and overlap rejection. Lock-timeout behavior, forced audit-insert rollback, parallel multi-connection stress, and browser workflows remain unverified.
