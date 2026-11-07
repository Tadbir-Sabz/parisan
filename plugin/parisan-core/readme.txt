=== Parisan Core ===
Contributors: tadbir-sabz
Tags: salon, appointments, beauty, booking
Stable tag: 0.10.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

First-party salon operations foundation for the Parisan Beauty WordPress theme.

== Description ==

Parisan Core stores salon business data independently from the active theme. It registers service and specialist content types, service categories, service duration/pricing metadata, specialist-to-service eligibility, recurring weekly schedules, and additive booking-related database tables.

The availability foundation calculates candidate appointment starts for a salon-local date using weekly staff hours, service duration, configurable slot interval, before/after buffers, existing pending/confirmed bookings, and stored unavailable intervals.

== Features ==

* Service and specialist custom post types.
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

== Important availability boundary ==

Availability results are advisory only and do not reserve a slot. Availability remains advisory until a booking is committed. The internal booking service rechecks capacity under a per-staff advisory lock and transaction; calendar exception writes use the same lock. Services with a starting-from price must have a final price confirmed before booking. The service is not exposed through a public REST/AJAX endpoint. Break management, resource schedules, customer portal, and notifications remain future work. Public booking endpoints are not exposed.

== Installation ==

1. Upload the `parisan-core` directory to `/wp-content/plugins/`.
2. Activate Parisan Core from the WordPress Plugins screen.
3. Activate the Parisan theme separately.
4. Add specialists and configure their weekly hours.
5. Open Specialists -> Calendar and Holidays to record date-specific unavailability.

The plugin creates or upgrades its additive schema on activation/plugin load. Deactivation and theme switching do not delete business data. Date and time entries use the WordPress site's configured timezone.

== Changelog ==

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
