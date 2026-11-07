# ADR-0010: Concurrency-Safe Booking Rescheduling

- Status: Accepted for the internal staff workflow
- Date: 2026-11-07
- Scope: Authorized staff rescheduling; no public reschedule endpoint is authorized

## Context

Staff need to move a pending or confirmed booking without losing its original price or duration and without introducing a conflict with another booking or an unavailable calendar interval.

## Decision

1. Add a versioned `duration_minutes` snapshot to the booking table. New bookings persist the service duration at creation; older rows with zero snapshot duration derive it from their stored UTC start/end interval when first rescheduled.
2. Rescheduling is handled only by `parisan_core_reschedule_booking()`. The function requires a logged-in user who can edit the assigned specialist.
3. The service uses the same per-staff advisory lock as booking creation, status transitions, and calendar exception writes, then starts an InnoDB transaction and locks the booking row with `SELECT ... FOR UPDATE`.
4. Only pending and confirmed bookings may be rescheduled. Cancelled, expired, completed, and no-show bookings are terminal.
5. The original duration and price snapshot are preserved. The new start is interpreted in the current salon timezone, converted to UTC, and the end is recalculated from the stored duration.
6. Availability is rechecked while holding the lock. The booking being moved is excluded from its own busy interval query; all other pending/confirmed bookings and unavailable exceptions remain blocking intervals.
7. The booking row update and `rescheduled` audit event are committed atomically. Audit failure rolls back the time change.
8. Repeating a request for the current start time is idempotent and creates no duplicate event.

## Boundaries and verification

- The staff detail page offers a nonce-protected reschedule form. The selected time is checked again server-side; the form does not treat browser-side availability as a reservation.
- CI run 42377819791 passed all four jobs and verified the schema v2 duration snapshot, successful reschedule preserving 45 minutes, idempotent replay, rejection of a time inside an unavailable exception, terminal-state rejection, reschedule audit event, and forced audit-insert failure rolling back the time change.
- Multi-connection stress, lock-timeout behavior, lifecycle status-change audit rollback, and browser-based rescheduling remain UNVERIFIED.
- No customer self-service, notifications, SMS, or payment operation is triggered.


Verification: the disposable WordPress/MariaDB booking integration checks that active customer-token expiry follows the rescheduled end time and is rolled back when the reschedule audit event fails. **PASS** — [PHP/WordPress smoke run 42405661236](https://github.com/Tadbir-Sabz/parisan/actions/runs/42405607604), commit `2231f0e`.