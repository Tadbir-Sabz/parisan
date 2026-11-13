# ADR-0013: Canonical Jalali Calendar and Date-Specific Schedule Overrides

- **Status:** Implemented on the feature branch; public booking remains disabled by default
- **Date:** 2026-11-14
- **Decision owners:** Parisan product maintainers
- **Scope:** Jalali conversion/presentation, salon and specialist date overrides, the Persian admin calendar, and Jalali labels in the gated public booking API. No holiday-provider integration, SMS, payment, or production activation.

## Context

Parisan stores booking instants in UTC and schedules in the WordPress site's configured IANA timezone. Persian-speaking owners and customers need Jalali dates, but the booking engine must keep canonical Gregorian/UTC values. Previous scheduling work supported recurring weekly hours and interval-based staff exceptions, but it could not express a salon-wide holiday, a specialist's full-day leave, or a one-off special working day without rewriting weekly rules.

A conversion implementation also has to fail closed at month/year boundaries: Esfand has 29 or 30 days depending on the Jalali leap year, and a naive conversion can silently roll an invalid date into the next year or shift a label when a display timezone is supplied.

## Decisions

1. **One canonical conversion module.** `plugin/parisan-core/includes/jalali.php` is the only Gregorian↔Jalali implementation. It is WordPress-independent so it can be tested as a pure library and reused by admin, REST, booking, and theme presentation code. Theme templates must not duplicate conversion tables.

2. **Arithmetic conversion, fail-closed validation.** Conversion uses the arithmetic 33-year Jalali cycle. The supported range is Jalali 1–3177 (Gregorian 622–3799). Every public conversion cross-checks a reverse conversion, so an impossible Esfand 30 or an out-of-range year returns `false` instead of rolling over. Month lengths, leap years, month names, weekday names, Persian digit conversion, strict Jalali form parsing, month grids, and month navigation live in the same module.

3. **Canonical storage and display.** Database boundaries remain UTC. Schedules, exceptions, overrides, and date overrides are interpreted in the WordPress `wp_timezone()` IANA timezone. A Jalali label is presentation only; it never replaces or mutates the stored instant. `parisan_core_format_jalali_date()` formats the requested calendar date at noon in its own timezone so a caller-supplied display timezone cannot roll the label to another day.

4. **Additive date-override storage.** Schema v12 adds `parisan_schedule_date_overrides` with one row per `(staff_id, override_date)`. `staff_id = 0` means a salon-wide rule; a positive ID means a specialist rule. Rows store the local date, type (`holiday`, `closure`, `special_day`, `custom_hours`), a closed flag, optional start/end and break times, a short note, and UTC created/updated timestamps. No uninstall path deletes the table implicitly; data retention follows the existing plugin policy.

5. **Weekly rules remain the base.** Date overrides are exceptions, not a replacement for recurring weekly hours. Availability resolves the effective day in this safety-first order:
   1. salon-wide closure;
   2. specialist closure;
   3. specialist override (special day or custom hours);
   4. salon-wide special day or custom hours;
   5. recurring weekly rule.

   A specialist closure deliberately wins over a salon-wide special day, so a leave request cannot be overridden by a salon-level event. A malformed override fails closed as an unavailable day.

6. **Consistent lock order.** Booking creation acquires the salon-calendar advisory lock before the per-staff lock. Date-override writes and deletions acquire locks in the same salon→staff order. This prevents a holiday or leave row from being committed in the middle of a booking availability recheck. The locks are advisory and MySQL/MariaDB-specific; the existing InnoDB fail-closed gate remains required.

7. **Persian RTL admin calendar.** The screen at `edit.php?post_type=parisan_staff&page=parisan-schedule-exceptions` requires `edit_others_posts` and object-level `edit_post` permission for every visible or submitted specialist. It provides month/week/day views, Jalali month/year jump, previous/next/today navigation, staff filtering, Jalali day links, legend/status states, and a day detail panel. Forms accept Persian or Arabic digits and `/`, `-`, or `.` separators, normalize them once to canonical Gregorian dates, and validate times on the server. Conflict notices count overlapping pending/confirmed bookings without cancelling or moving them. JavaScript adds keyboard grid navigation (arrows, Home/End, PageUp/PageDown), progressive disclosure, and client-side validation; the server remains authoritative and the screen works without JavaScript.

8. **Public API remains gated.** `GET /wp-json/parisan/v1/availability-days` is registered only when the owner defines `PARISAN_PUBLIC_BOOKING_ENABLED` as boolean `true`. It returns only the date, a Jalali label, and an availability boolean for a bounded window; it does not expose booking counts, customer data, or booking identifiers. Existing availability and customer-booking responses gain Jalali `date_label`/`time_label` fields without changing their UTC/ownership contracts. Rate limiting, same-origin mutation checks, token scoping, and server-side conflict rechecks remain in force.

9. **No holiday dataset is invented.** The product stores owner-entered holidays and closures. It does not bundle an incomplete official holiday calendar, call an external holiday API, or guess regional observances. Sites that need a preloaded dataset must provide and review it explicitly.

## Consequences

- Jalali behavior is consistent across admin, public booking, and future reporting because there is one conversion module.
- Owners can model salon holidays, specialist leave, special working days, and per-date hours without destroying weekly recurrence or booking history.
- Adding a closure does not cancel existing appointments; operators see a conflict count and must resolve bookings separately.
- The conversion range is intentionally bounded. Dates outside Jalali 1–3177 are rejected rather than approximated.
- The arithmetic cycle is cross-checked by reverse conversion, but it is not an official observatory/religious calendar authority. The UI accepts years 1200–1600 for form input, a narrower operational range than the library.
- Date overrides are local-date rules; multi-day ranges must be entered one date at a time. Bulk holiday ranges, imported holiday datasets, and automatic observance rules remain future work.
- The browser suite covers Chromium, Firefox, and WebKit on a disposable WordPress/MariaDB installation. Real screen-reader, real-device, and production-timezone verification remain UNVERIFIED until separately evidenced.
