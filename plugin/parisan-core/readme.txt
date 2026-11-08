=== Parisan Core ===
Contributors: tadbir-sabz
Tags: salon, appointments, beauty, booking
Stable tag: 0.21.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

First-party salon operations foundation for the Parisan Beauty WordPress theme.

== Description ==

Parisan Core stores salon business data independently from the active theme. It registers service, specialist, and portfolio content types, service and portfolio categories, service duration/pricing metadata, specialist-to-service eligibility, recurring weekly schedules, and additive booking-related database tables.

The availability foundation calculates candidate appointment starts for a salon-local date using weekly staff hours, service duration, configurable slot interval, before/after buffers, existing pending/confirmed bookings, and stored unavailable intervals.

== Features ==

* Service, specialist, and portfolio custom post types.
* Hierarchical portfolio categories, portfolio archive, and individual portfolio detail pages.
* Hierarchical service categories.
* Service duration and price metadata.
* Specialist service eligibility and weekly recurring schedule.
* Persian admin page for date-specific specialist leave, full-day closures, and partial-day unavailable intervals.
* Additive booking, booking-event, and schedule-exception schema.
* Read-only availability calculation with timezone conversion and overlap checks.
* Internal booking creation service with per-staff advisory locking, InnoDB transaction checks, idempotency, and atomic booking/audit-event persistence.
* Internal booking lifecycle transitions for confirmation, cancellation, expiry, completion, and no-show with atomic audit events.
* Persian WordPress admin booking operations list with status filters, pagination, and guarded state-transition actions.
* Authorized manual booking creation from the staff operations screen, with salon-timezone input and server-side availability recheck.
* Authenticated admin-only availability slot picker with nonce and capability checks.
* Protected booking detail page with customer information and an ordered audit timeline.
* Concurrency-safe rescheduling for pending and confirmed bookings, preserving duration/price snapshots and recording an audit event.
* WordPress personal-data exporter and eraser for booking records.
* Administrator-controlled, opt-in anonymization of old terminal bookings; disabled by default.
* Public API foundation for date-bounded availability, pending booking creation, and token-scoped customer view/cancellation; routes are registered only with an explicit owner feature flag.
* 256-bit customer access tokens stored as HMAC digests, with expiry, rotation, privacy-erasure revocation, and transactional cancellation/audit.
* First-party SEO defaults and per-content title/description/noindex controls, canonical/Open Graph output, Service JSON-LD, accessible context-aware breadcrumbs, BreadcrumbList JSON-LD, and exclusion of per-content noindex pages from the WordPress core sitemap when no supported SEO plugin owns metadata.
* Explicit no-store cache boundaries for logged-in, booking-shortcode, customer-token, and Parisan customer REST contexts; public-content invalidation hook, targeted LiteSpeed Cache/WP Rocket/W3 Total Cache post-purge adapters, and non-secret runtime cache diagnostics.
* Persian-first responsive `[parisan_booking]` shortcode for availability selection, pending booking requests, one-time token display/copy, and token-based booking lookup/cancellation.

== Important privacy and availability boundaries ==

Availability results are advisory only and do not reserve a slot. The internal booking service rechecks capacity under a per-staff advisory lock and transaction; calendar exception writes use the same lock. Services with a starting-from price must have a final price confirmed before booking.

The public REST API is disabled by default. The owner must explicitly define `PARISAN_PUBLIC_BOOKING_ENABLED` as boolean `true` in `wp-config.php` before routes are registered. The API includes read-only availability, pending booking creation, and token-scoped customer view/cancellation. Customer tokens are 256-bit random values; only HMAC digests are stored, and tokens expire 30 days after the scheduled appointment end. State-changing anonymous requests require a matching Origin and are rate-limited. The customer view does not expose name, phone, email, or event history. Do not enable the feature until HTTPS, reverse-proxy source-IP behavior, same-origin deployment, logging/header redaction, browser UX, and concurrency/security testing have been reviewed.

WordPress privacy export includes booking details and associated event notes. Erasure anonymizes terminal bookings and removes their free-form event notes while preserving operational history. Pending and confirmed bookings are retained while needed to fulfil the appointment. Automatic anonymization is disabled by default and requires an administrator to choose a retention period. The site owner remains responsible for selecting a period appropriate to applicable legal and accounting duties, and for backup/processor retention.

Break management, resource schedules, full account-based customer portal/rescheduling, email/SMS, payment, advanced SEO controls (sitemap extensions, redirects, SEO audits), CDN/reverse-proxy purge adapters, backend health checks, cache hit/miss metrics, and measured purge completion remain future work. The initial shortcode UI exists, but browser/device/accessibility review is still UNVERIFIED. Public API activation is not approved by this code delivery. No live SMS or payment operations are performed.

== Installation ==

1. Upload the `parisan-core` directory to `/wp-content/plugins/`.
2. Activate Parisan Core from the WordPress Plugins screen.
3. Activate the Parisan theme separately.
4. Add specialists and configure their weekly hours.
5. Open Specialists -> Calendar and Holidays to record date-specific unavailability.
6. Administrators can review privacy controls under Specialists -> Privacy and Data Retention.
7. Add `[parisan_booking]` to a WordPress page to render the booking wizard. It shows a disabled notice until the owner explicitly enables the public API constant; do not enable it before deployment security review.

The plugin creates or upgrades its additive schema on activation/plugin load. Deactivation and theme switching do not delete business data. Date and time entries use the WordPress site's configured timezone.

== Changelog ==

= 0.20.0 =
* Added a Persian admin diagnostics table for the persistent object-cache runtime flag, object-cache drop-in presence, and supported page-cache purge APIs.
* Diagnostics do not expose cache keys/credentials or claim backend health/CDN purge completion.
* Added isolated tests for runtime diagnostic signals and provider detection.

= 0.19.0 =
* Excluded content marked noindex from WordPress core post-type XML sitemaps while preserving other query constraints.
* Added WordPress integration coverage for sitemap inclusion/exclusion and SEO-plugin ownership.

= 0.18.0 =
* Added accessible, responsive, context-aware breadcrumbs for service/specialist details, pages, posts, archives, taxonomies, search, and 404 views.
* Added BreadcrumbList JSON-LD with duplicate-schema suppression when a supported SEO plugin owns output.
* Added WordPress integration smoke coverage for breadcrumb markup, schema, and SEO-plugin coexistence.

= 0.17.0 =
* Added capability-detected, post-scoped cache purge adapters for LiteSpeed Cache, WP Rocket, and W3 Total Cache.
* Restricted public cache invalidation to Parisan service and specialist content, including deletion events.
* Added isolated adapter tests to the PHP CI matrix; no site-wide cache purge is issued.

= 0.16.0 =
* Added per-content SEO title/description/noindex fields and site-level SEO defaults.
* Added canonical and Open Graph metadata plus Service JSON-LD for service pages.
* Avoided duplicate first-party metadata when supported SEO plugins are active.
* Added explicit no-store cache guards for logged-in, booking, tokenized customer, and Parisan REST contexts.
* Added a public-content cache invalidation integration hook.

= 0.15.0 =
* Added a gated public booking API foundation with availability, pending booking creation, scoped customer view, and transactional cancellation.
* Added expiring HMAC-digested customer access tokens with rotation and revocation; routes remain disabled by default.
* Added schema v3 for customer access token storage and disposable WordPress/MariaDB integration checks.
* Revoke customer tokens atomically when terminal bookings are anonymized.
* Added Persian RTL `[parisan_booking]` shortcode with availability selection, booking submission, one-time token copy, and customer lookup/cancellation.
* Added WordPress personal-data export and erasure integration for booking records.
* Added terminal-booking anonymization while preserving booking and event audit structure.
* Added an administrator-controlled retention policy, disabled by default, with bounded daily cleanup.
* Applied the MySQL-lock-serialized fixed-window request limiter to the gated public API.

= 0.13.0 =
* Added a versioned booking duration snapshot and migration.
* Added internal rescheduling with the shared staff lock, InnoDB transaction, availability recheck, and atomic audit event.
* Preserved original duration and price when moving an existing booking.

= 0.12.0 =
* Added a protected booking detail view with status actions and audit event history.
* Added authorized display of customer contact details on the individual booking page.

= 0.11.0 =
* Added an authenticated admin-only availability slot lookup and time selector for manual bookings.
* Added capability and nonce checks; no unauthenticated AJAX handler is registered.
* Added Node syntax validation for the booking admin script.

= 0.10.0 =
* Added authorized manual booking creation for salon staff.
* Added salon timezone indication, future-time validation, and Persian/Arabic phone digit normalization.
* Added CI coverage for the manual booking form, phone normalization, and past-time rejection.

= 0.9.0 =
* Added a Persian WordPress admin screen for viewing and filtering bookings.
* Added paginated booking operations with confirmation, cancellation, expiry, completion, and no-show actions.
* Added capability, nonce, and lifecycle-service checks to all status-change actions.

= 0.8.0 =
* Added controlled internal booking status transitions and audit history.
* Confirmation rechecks overlapping bookings and unavailable calendar exceptions.
* Added CI coverage for confirmation, cancellation, idempotent status replay, invalid transitions, and booking/audit atomicity.

= 0.7.1 =
* Added internal transactional booking creation with staff-level advisory locking and idempotency.
* Serialized calendar exception writes with booking creation using the same staff lock.
* Required InnoDB for booking, booking-event, and schedule-exception tables.
* Rejected “price from” services until the final price is confirmed.

= 0.6.0 =
* Added Persian admin calendar for full-day and partial-day staff unavailability.
* Added strict local-to-UTC interval validation and DST gap rejection.
* Added WordPress database-backed availability integration coverage.

= 0.5.0 =
* Added timezone-aware availability calculation and WordPress data adapter.
* Added schedule/availability tests for UTC conversion, overlaps, buffers, invalid dates/timezones, and DST gaps.

= 0.4.0 =
* Added additive booking, booking-event, and schedule-exception tables.
* Added weekly specialist schedule and service eligibility foundation.
