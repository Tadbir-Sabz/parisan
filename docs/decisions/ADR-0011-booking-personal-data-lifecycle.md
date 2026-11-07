# ADR-0011: Booking Personal Data Lifecycle and Request Abuse Controls

- Status: Accepted for the current internal booking foundation
- Date: 2026-11-07
- Scope: WordPress privacy export/erasure, terminal-booking anonymization, opt-in retention, and a reusable rate-limit primitive. No public booking endpoint is enabled.

## Context

Booking records contain customer contact details and may contain free-form notes in the event timeline. The product must support data-subject requests without destroying the operational booking history needed for salon administration. Public booking endpoints will eventually need abuse controls, but a rate limiter must never be confused with authentication, ownership checks, or booking authorization.

## Decisions

1. Register Parisan booking data with WordPress personal-data exporters and erasers.
2. Export matching booking identity/contact data, appointment/service/staff references, price/duration snapshots, and associated event history.
3. For erasure requests, anonymize terminal bookings only: replace the customer name with a generic marker, clear phone/email and customer user ID, and clear free-form event notes. Preserve the booking row, status, schedule, price snapshot, event types, and event timestamps for operational/audit continuity.
4. Pending and confirmed bookings are retained while they are needed to fulfil or administer the requested service. The eraser must report retained active bookings rather than silently claiming complete removal.
5. Automatic retention is disabled by default. An administrator may opt in to a supported period (90 days to 5 years); daily cleanup processes at most 250 old terminal bookings per run. It anonymizes contact data and free-form notes; it does not delete booking/event history.
6. Anonymization is an InnoDB transaction across booking and event tables and fails closed if transactional storage or transaction startup is unavailable.
7. A reusable fixed-window limiter uses an HMAC of the action scope and the server-provided `REMOTE_ADDR`, stores no raw IP address, serializes updates with a short MySQL advisory lock, and fails closed if the lock cannot be acquired. Forwarded-IP headers are deliberately ignored.
8. The limiter is only one layer. Any future public route must separately implement input validation, secure booking-reference/token design, object-level authorization, origin/CSRF protections where applicable, and operational monitoring. Public routes remain disabled until those controls and integration tests exist.
9. The site owner must choose retention periods based on applicable law, accounting obligations, business needs, backups, and external processors. The implementation does not make a legal-compliance claim.

## Consequences

- Theme switching and plugin deactivation do not remove booking records.
- Erasure preserves operational history while removing direct customer identifiers from eligible terminal records.
- Active booking contact data is intentionally retained until the booking is terminal; the WordPress eraser communicates this outcome.
- A bounded daily cleanup avoids an unbounded cron query, but sites with large backlogs may need multiple daily runs.
- The request limiter is reusable but is not yet attached to any public booking endpoint because none exists.

## Verification

- Dedicated PHP 8.1/8.2/8.3 workflow and focused unit assertions are added for identity matching, export shape, terminal/active status policy, anonymization SQL/transaction sequence, rate-limit allow/deny/fail-closed behavior, and retention default/scheduling.
- Actual WordPress privacy screen interaction, real MariaDB erasure/rollback, proxy topology, distributed multi-node rate-limit behavior, and legal retention configuration remain UNVERIFIED pending an integration environment and owner policy.
