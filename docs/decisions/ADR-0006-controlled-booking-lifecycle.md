# ADR-0006: Controlled Booking Lifecycle

- Status: Accepted for the internal service boundary
- Date: 2026-11-07
- Scope: Internal booking status changes; no public or customer-facing endpoint is authorized

## Context

Persisting a pending booking is not a complete booking workflow. Confirmation, cancellation, expiry, completion, and no-show outcomes must be explicit, auditable, and protected from concurrent schedule changes.

## Decision

1. Booking status changes are performed by `parisan_core_transition_booking()`; the function is not registered as a REST, AJAX, or public form endpoint.
2. Allowed transitions are:
   - `pending -> confirmed | cancelled | expired`
   - `confirmed -> cancelled | completed | no_show`
   - Cancelled, expired, completed, and no-show are terminal in this initial state machine.
3. A logged-in WordPress user must have permission to edit the assigned specialist record. System/background transitions require a separate explicitly designed service identity and are not implicitly permitted.
4. The transition service obtains the same per-staff advisory lock used by booking creation and calendar exception writes, starts an InnoDB transaction, and locks the booking row with `SELECT ... FOR UPDATE`.
5. Confirmation independently checks for overlapping pending/confirmed bookings and unavailable calendar exceptions. A conflict prevents confirmation.
6. Status update and `status_changed` audit event are written in the same transaction. Audit failure rolls back the status update.
7. Repeating a request for the already-current status is idempotent and does not create duplicate audit events.
8. The service stores only a sanitized, bounded internal audit note and returns status metadata, not customer PII.

## Boundaries and follow-up

- No public customer cancellation, customer self-service, automatic expiry worker, notifications, payment settlement, or production operation is enabled.
- Browser/admin booking management UX and accessibility remain separate work.
- Multi-connection concurrency stress, lock-timeout tests, confirmation-conflict tests, and forced lifecycle audit rollback remain unverified until CI coverage is extended.
