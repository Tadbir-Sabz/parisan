# ADR-0012: Gated Public Booking API and Customer Access Tokens

- Status: Implemented on the feature branch; public activation remains explicitly disabled by default
- Date: 2026-11-07
- Scope: Anonymous availability lookup, pending booking creation, scoped customer booking access, customer cancellation and rescheduling, and the Persian RTL shortcode UI. No SMS, payment, or production activation.

## Context

The internal booking engine is concurrency-safe but deliberately has no public endpoint. A public booking flow must not treat the UUID/public reference as authorization, expose contact details, or make a guessed identifier sufficient to read or mutate a booking.

## Decisions

1. Public routes are registered only when the site owner explicitly defines `PARISAN_PUBLIC_BOOKING_ENABLED` as boolean `true` in `wp-config.php`. The constant is not defined by the plugin and the default is disabled.
2. The API surface is limited to:
   - `GET /wp-json/parisan/v1/availability` — read-only, date-bounded availability.
   - `POST /wp-json/parisan/v1/bookings` — create a pending booking.
   - `GET /wp-json/parisan/v1/customer-booking` — minimal customer booking view.
   - `GET /wp-json/parisan/v1/customer-booking/availability` — return reschedule candidates using the booking's stored duration while excluding that booking from conflicts.\n   - `POST /wp-json/parisan/v1/customer-booking/reschedule` — move the token-owned pending/confirmed booking to an available time.\n   - `POST /wp-json/parisan/v1/customer-booking/cancel` — cancel a pending/confirmed booking.
3. Customer access uses a cryptographically random 256-bit token, returned only once in the successful create response and accepted only through `X-Parisan-Booking-Token`. It is never accepted in a URL or query parameter.
4. Only an HMAC-SHA-256 digest using the WordPress authentication salt is stored. Tokens expire 30 days after the scheduled booking end; an internal reschedule updates active token expiry to 30 days after the new end time in the same transaction. Re-issuing through a valid idempotent retry revokes previous tokens for that booking and stores the replacement token in the same InnoDB transaction as the booking operation.
5. The booking creation service accepts the optional token only for callers that explicitly provide it; internal staff/manual booking callers do not automatically issue customer tokens.
6. Creation requires an `Idempotency-Key` as 64 lowercase hexadecimal characters generated from 32 cryptographically random bytes, same-origin `Origin`, endpoint rate limiting, the existing transactional booking service, and a pending state. No payment or automatic confirmation occurs.
7. Customer read, availability, reschedule, and cancel routes resolve the token to one booking before operating. The customer view omits name, phone, email, event history, and internal database identifiers. Customer cancellation only permits pending/confirmed to cancelled, under the same per-staff advisory lock and a transaction that records an audit event. Customer rescheduling preserves the booked duration and price snapshot, excludes the booking itself from availability conflicts, updates active token expiry to 30 days after the new end, and records an audit event atomically with the schedule update.
8. Anonymous state-changing requests require an `Origin` matching the configured WordPress home origin. This is intentionally strict; reverse-proxy and headless deployment topologies require separate verification before enabling the API.
9. Public responses set `Cache-Control: no-store`, `Pragma: no-cache`, and `Vary` headers. The reusable HMAC/IP rate limiter is applied to public availability, creation, customer reads, customer availability, rescheduling, and cancellation. It uses only `REMOTE_ADDR`; proxy trust and multi-node behavior remain unverified.
10. The `[parisan_booking]` shortcode renders a Persian-first responsive form, availability selector, customer details form, one-time token copy panel, and token-based booking lookup, rescheduling, and cancellation. It renders only a disabled notice while the feature flag is off.
11. Terminal-booking anonymization and opt-in retention revoke associated customer tokens in the same transaction as contact/event anonymization.
12. All public routes remain unavailable unless the owner deliberately enables the constant after deployment-specific review. This implementation does not authorize production activation.

## Consequences and remaining limits

- A public UUID/reference is not an authorization credential.
- Token theft still grants scoped access until expiry/revocation; HTTPS, secure client storage, log/header redaction, and incident revocation procedures remain operational requirements.
- There is no token self-service rotation/revocation endpoint yet; a successful idempotent retry rotates the token, and privacy erasure/retention revokes tokens for anonymized terminal bookings.
- Public customer self-service rescheduling is implemented through the scoped token, but there is no account registration, email verification, SMS, payment, or automated confirmation. The initial shortcode UI has not yet passed browser/device/accessibility review.
- Rate limiting is per database-backed WordPress installation and the observed source IP. Reverse proxies, shared caches, multi-node topologies, and distributed abuse monitoring need deployment-specific validation.
- The owner must explicitly configure the constant only after reviewing same-origin behavior, HTTPS, proxy handling, backups, logging, and privacy operations.

## Verification

- **PASS — WordPress + MariaDB integration on runtime commit `64a5898`:** [run 42450640196](https://github.com/Tadbir-Sabz/parisan/actions/runs/42450640196). Verifies schema v3 and v2-to-v3 migration, default-off route registration, token digest storage/rotation/expiry, token-bound cancellation, privacy-erasure revocation/rollback, explicit route enablement, shortcode rendering/assets/privacy notice, public availability with occupied-slot exclusion, booking creation, idempotency conflict protection, 256-bit idempotency key enforcement, 90-day horizon, customer-token availability, rescheduling ownership and same-origin protection, current-booking exclusion from conflict checks, and token-expiry adjustment. It also verifies the booking stylesheet is enqueued before `wp_head` and emitted in the document head.
- **PASS — PHP/JS and WordPress activation smoke matrix on runtime commit `64a5898`:** [run 42450643123](https://github.com/Tadbir-Sabz/parisan/actions/runs/42450643123). PHP 8.1/8.2/8.3 lint, both booking JavaScript syntax checks, schedule/availability tests, WordPress schema-v3 activation smoke test, and disposable WordPress theme activation/dynamic homepage/navigation smoke test. The transactional booking test verifies customer token expiry follows a successful reschedule and rolls back when audit insertion fails.
- **PASS — privacy and abuse-control matrix on runtime commit `64a5898`:** [run 42450643261](https://github.com/Tadbir-Sabz/parisan/actions/runs/42450643261).
- Browser UX, real reverse-proxy behavior, multi-connection race stress, and production deployment remain UNVERIFIED.
