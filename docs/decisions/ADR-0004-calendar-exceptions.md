# ADR-0004: Date-specific staff schedule exceptions

- **Status:** Accepted
- **Date:** 2026-11-07
- **Decision owners:** Parisan product maintainers

## Context

Recurring weekly hours cannot represent a specialist's one-off leave, a public holiday, a temporary closure, or a partial-day absence. These exceptions must affect availability without rewriting the recurring schedule or deleting booking history.

## Decision

1. Store date-specific unavailable intervals in the additive `parisan_schedule_exceptions` table, scoped to a staff post.
2. Treat submitted date/time values as wall-clock values in the WordPress site's configured IANA timezone.
3. Convert validated local intervals to UTC before persistence. Reject impossible dates, invalid clock values, reversed intervals, and nonexistent local times during DST spring-forward.
4. Represent a full-day exception as local midnight on the selected date through local midnight on the following date. Its UTC duration may therefore be 23, 24, or 25 hours depending on timezone transitions.
5. The initial admin UI supports full-day and same-day partial-day unavailability, a staff member, and a short internal note. Cross-midnight partial exceptions and exception types that add availability are out of scope.
6. Require an authenticated user with the staff-editing capability, verify a nonce, and verify edit permission for the selected staff record on both create and delete.
7. Availability calculation treats exception intervals as half-open UTC ranges and excludes overlapping candidate slots. Existing bookings are not modified or cancelled when an exception is added.
8. Availability is advisory. Booking creation must independently re-check conflicts under a database transaction/lock.

## Consequences

- Weekly recurring schedules remain stable when leave or holidays are added.
- The UI can display local time while persistence and conflict checks remain UTC-based.
- Administrators must understand that adding leave does not automatically cancel existing appointments; operational conflict resolution is a separate workflow.
- The current UI lists at most 200 exceptions and 200 published staff records; pagination/filtering is a future admin UX improvement.
