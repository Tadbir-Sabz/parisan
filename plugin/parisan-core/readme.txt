=== Parisan Core ===
Contributors: tadbir-sabz
Tags: salon, appointments, beauty, booking
Stable tag: 0.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

First-party salon operations foundation for the Parisan Beauty WordPress theme.

== Description ==

Parisan Core stores salon business data independently from the active theme. It currently registers service and specialist content types, service categories, service duration/pricing metadata, specialist-to-service eligibility, recurring weekly schedules, and additive booking-related database tables.

The availability foundation calculates candidate appointment starts for a salon-local date using weekly staff hours, service duration, configurable slot interval, before/after buffers, existing pending/confirmed bookings, and stored unavailable intervals. All persisted appointment/exception instants are interpreted as UTC while weekly working hours use the WordPress site timezone.

== Features ==

* Service and specialist custom post types.
* Hierarchical service categories.
* Service duration and price metadata.
* Specialist service eligibility and weekly recurring schedule.
* Additive booking, booking-event, and schedule-exception schema.
* Read-only availability calculation with timezone conversion and overlap checks.

== Important availability boundary ==

Availability results are advisory only and do not reserve a slot. Public booking endpoints and concurrency-safe booking creation are not implemented. Date-specific exception administration, breaks, leave/holiday management UI, booking state transitions, customer portal, and notifications remain future work.

== Installation ==

1. Upload the `parisan-core` directory to `/wp-content/plugins/`.
2. Activate Parisan Core from the WordPress Plugins screen.
3. Activate the Parisan theme separately.

The plugin creates or upgrades its additive schema on activation/plugin load. Deactivation and theme switching do not delete business data.

== Changelog ==

= 0.5.0 =
* Added timezone-aware availability calculation and WordPress data adapter.
* Added schedule/availability tests for UTC conversion, overlaps, buffers, invalid dates/timezones, and DST gaps.

= 0.4.0 =
* Added additive booking, booking-event, and schedule-exception tables.
* Added weekly specialist schedule and service eligibility foundation.
