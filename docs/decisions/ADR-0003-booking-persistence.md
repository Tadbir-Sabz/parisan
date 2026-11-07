# ADR-0003: Booking persistence and scheduling data boundary

- **Status:** Accepted as the target for the first booking-engine increment
- **Date:** 2026-11-07

## Context

Salon bookings are transactional records with interval-overlap constraints, state transitions, and an audit history. They must survive theme changes and must not be modeled as theme-owned content. Recurring staff hours alone are insufficient to establish a bookable slot.

## Decision

- Parisan Core owns booking, booking-event, and schedule-exception persistence in versioned custom database tables.
- Service and staff catalogue records continue to reference WordPress post IDs; database foreign keys to WordPress content tables are not used.
- Booking timestamps are stored as UTC instants. The salon's IANA timezone is stored with the booking for correct historical rendering and interpretation.
- Weekly recurring schedules remain salon-local wall-clock values. Date-specific leave, holidays, breaks, and overrides are separate schedule exceptions with explicit UTC intervals after timezone conversion.
- Booking state changes are explicit and auditable in a separate event table.
- Booking creation must validate availability server-side and use a database-level concurrency strategy to prevent overlapping active appointments for the same staff/resource. A browser availability result is never authoritative.
- Guest contact data is treated as personal data: access must be capability- and ownership-checked, excluded from public REST responses, redacted from logs, and covered by retention/export/delete controls.
- Schema changes are additive, versioned, and run through WordPress dbDelta migrations. Deactivation and theme switching never delete business records. Uninstall data deletion requires an explicit, documented owner choice.

## Consequences

- The first schema increment can establish durable tables before the booking UI and REST endpoint are enabled.
- The booking API must not be exposed until availability, timezone, idempotency, rate limiting, object-level authorization, and concurrency behavior are implemented and tested.
- The first release should use a single salon timezone and one salon/branch UX while retaining branch/resource extension points.
- Currency must be an explicit salon setting; the initial catalogue's toman label is a market-facing placeholder, not a final accounting model.

## Validation required

Migration creation and repeatability against disposable MySQL/MariaDB, upgrade behavior, table indexes, booking conflict concurrency tests, timezone boundary tests, authorization tests, and privacy/retention tests.
