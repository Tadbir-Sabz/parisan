# Parisan Jalali Calendar and Scheduling — Implementation Report

**Date:** 2026-11-14

**Branch:** `feat/jalali-calendar-scheduling`

**Baseline `main`:** `43ee629ae8c2ebfb579b644062d4e33730948c26`

**Pull request:** pending — URL is recorded in a follow-up documentation commit after the PR is opened

**Scope:** Canonical Jalali conversion, Persian RTL admin calendar, salon/specialist date overrides, schema v12, and Jalali labels in the gated public booking API. Public booking remains disabled by default.

---

## 1. Verdict

| Area | Status | Scope and evidence |
|---|---|---|
| Jalali conversion library | **PASS (local)** | `php tests/run-jalali-tests.php` — 77 passed, 0 failed on PHP 8.4.26. Known date pairs, Esfand 29/30, leap years, invalid dates, reverse conversion, digits, parsing, formatting, month grids, and navigation. |
| Schedule/override engine | **PASS (local)** | `php tests/run-schedule-tests.php` — 45 passed, 0 failed. Weekly rules, breaks, salon/staff overrides, precedence, custom hours, DST spring-forward, Tehran midnight/UTC-date boundary. |
| PHP/JS syntax | **PASS (local)** | `php -l` on 15 edited/created PHP files; `node --check` on `calendar-admin.js`, `public-booking.js`, and `browser-smoke.js`; `git diff --check` clean. |
| WordPress/MariaDB calendar integration | **UNVERIFIED locally; CI-gated** | `tests/run-calendar-integration.php` covers schema v12, unique index, holiday, staff closure, special day, invalid dates, transactional booking rejection, day summary, and independent-connection salon lock serialization. No local WordPress/MariaDB runtime is available. |
| Public booking gating and labels | **UNVERIFIED locally; CI-gated** | Default-off route assertions, enabled-route assertions, Jalali date/time labels, day-chip privacy, holiday rejection, and specialist-closure isolation are in the disposable WordPress/MariaDB suites. |
| Admin calendar browser UX | **UNVERIFIED locally; CI-gated** | Authenticated Chromium/Firefox/WebKit checks assert RTL direction, 42-cell grid, open-day states, arrow-key focus movement, mobile no-overflow, scroll-container behavior, and console cleanliness. |
| Theme smoke integration | **UNVERIFIED locally; CI-gated** | `tests/run-theme-smoke.php` asserts schema v12, the new table, loaded calendar services, Jalali conversion/parse behavior, and calendar/public assets. |
| Production deployment | **NOT PERFORMED** | No production site, DNS, credentials, customer data, live SMS, or payment provider was touched. |

**Overall:** implementation and local pure-PHP evidence are **PASS**. Runtime/integration/browser acceptance is **UNVERIFIED** until this exact head passes CI. No merge or public-booking activation is implied.

---

## 2. What changed

### 2.1 Canonical Jalali service

New file `plugin/parisan-core/includes/jalali.php`:

- Gregorian leap-year and month-length validation.
- Arithmetic 33-year-cycle Gregorian→Jalali and Jalali→Gregorian conversion.
- Reverse-conversion cross-check on every public conversion; impossible dates return `false`.
- Supported range: Jalali 1–3177 / Gregorian 622–3799.
- Jalali leap-year and month-length helpers, including Esfand 29/30.
- Persian/English month and weekday names.
- Persian and Arabic digit normalization to Latin; Latin to Persian digits.
- Strict form parsing for Persian/Arabic digits and `/`, `-`, or `.` separators.
- `DateTimeImmutable`-based formatting in the requested timezone.
- 42-cell month grid and month navigation helpers.

`parisan_core_format_jalali_date()` formats the requested date at noon in its own timezone. A supplied display timezone cannot shift the label to the previous/next day.

### 2.2 Date-specific overrides

New file `plugin/parisan-core/includes/date-overrides.php`:

- Row mapping, local-date validation, single/range fetch, and day booking/exception summaries.
- Effective schedule resolution for one specialist/date.
- Save/update/delete with salon→staff advisory-lock order.
- Conflict count returned to the admin after save.
- Authorization uses the stored row's staff scope on deletion; callers still verify capability/nonce.

Schema v12 adds `parisan_schedule_date_overrides` with:

- `staff_id = 0` for salon-wide rules, positive IDs for specialists.
- `UNIQUE KEY staff_date (staff_id, override_date)`.
- Local date, type, closed flag, optional start/end and break times, note, and UTC created/updated timestamps.
- Additive `dbDelta` installation; no destructive migration.

### 2.3 Effective-day precedence

`parisan_core_resolve_effective_day_schedule()` applies:

1. salon closure;
2. specialist closure;
3. specialist override;
4. salon special day/custom hours;
5. weekly rule.

Malformed overrides fail closed. Slot calculation accepts both staff and salon overrides, preserves breaks, and converts wall-clock intervals to UTC with DST-gap rejection.

### 2.4 Lock ordering

`booking-service.php` now exposes salon-calendar lock helpers. Booking creation acquires the salon lock before the per-staff lock; date-override writes/deletes use the same order. This closes a race where a salon holiday could be committed between a booking's availability recheck and its insert. The existing staff lock, InnoDB transaction gate, and conflict recheck remain in place.

### 2.5 Persian RTL admin calendar

`schedule-exceptions-admin.php` now provides:

- Month/week/day views with Jalali labels.
- Previous/next/today navigation, Jalali month/year jump, and staff filtering.
- Day links to a day detail view.
- Legend and status states for open/closed/special/custom/partial/booking days.
- Salon holiday, specialist closure, special working day, custom-hours, and partial-day exception forms.
- Persian digit/date parsing and server-side time validation.
- Conflict warnings for overlapping pending/confirmed bookings; no automatic cancellation.
- Capability checks (`edit_others_posts` plus `edit_post` per specialist) and nonces.
- Override range queries limited to visible staff and capped at 10,000 rows.
- Read-only handling when no editable specialists exist.

New assets:

- `assets/css/calendar-admin.css` — RTL, responsive, dark-mode, sticky day cells, horizontal scroll containment.
- `assets/js/calendar-admin.js` — keyboard grid navigation (arrows, Home/End, PageUp/PageDown), progressive disclosure, client-side validation. Server remains authoritative; the page works without JavaScript.

### 2.6 Public booking integration

- New gated route `GET /wp-json/parisan/v1/availability-days`, limited to 31 days (default 14), within the booking horizon.
- Response exposes only `date`, Jalali `date_label`, and `available`; no customer, booking, count, weekday, or slot metadata.
- Public availability and customer-booking availability add `date_label` and `time_label`.
- Public JS renders day chips and Jalali labels; CSS adds the chip layout.
- Routes remain registered only when `PARISAN_PUBLIC_BOOKING_ENABLED` is explicitly `true`.
- Existing rate limiting, no-store responses, same-origin mutation checks, token scoping, and transactional conflict rechecks are unchanged.

### 2.7 Version and CI

- Parisan Core version bumped to `0.33.0`; readme stable tag/changelog updated.
- `.github/workflows/php-lint.yml`: calendar JS syntax, Jalali suite, schema v12/table/function assertions.
- `.github/workflows/privacy-integration.yml`: runs `tests/run-calendar-integration.php`.
- `.github/workflows/browser-smoke.yml`: seeds a full weekly schedule for `STAFF_1` and runs authenticated admin-calendar checks.
- `tests/browser-smoke.js`: authenticated admin calendar assertions added.
- `tests/run-theme-smoke.php`, `tests/run-public-booking-integration.php`, and `tests/run-public-booking-enabled-integration.php`: updated for schema v12 and Jalali/calendar behavior.

### 2.8 Documentation

- `docs/JALALI-CALENDAR.fa.md` — Persian operator guide.
- `docs/decisions/ADR-0013-jalali-calendar-and-date-overrides.md` — durable architecture decision.
- `docs/ROADMAP.md` and `README.md` — milestone status and evidence.

---

## 3. Tests and exact local results

Environment: Windows, PHP 8.4.26 (NTS), Node 24.20.0. No local WordPress/MariaDB runtime.

```text
php -l  (15 changed/created PHP files)                         -> PASS, 0 failures
php tests/run-jalali-tests.php                                 -> 77 passed, 0 failed
php tests/run-schedule-tests.php                               -> 45 passed, 0 failed
node --check plugin/parisan-core/assets/js/calendar-admin.js   -> PASS
node --check plugin/parisan-core/assets/js/public-booking.js   -> PASS
node --check tests/browser-smoke.js                            -> PASS
git diff --check                                               -> clean
```

CI-only suites updated and pending exact-head execution:

```text
wp eval-file tests/run-calendar-integration.php               -> UNVERIFIED (CI)
wp eval-file tests/run-public-booking-integration.php         -> UNVERIFIED (CI)
wp eval-file tests/run-public-booking-enabled-integration.php -> UNVERIFIED (CI)
wp eval-file tests/run-theme-smoke.php                        -> UNVERIFIED (CI)
node tests/browser-smoke.js                                   -> UNVERIFIED (CI)
```

Local test coverage highlights:

- Known conversions: 2024-03-20 ↔ 1403/01/01, 2025-03-20 ↔ 1403/12/30, 2025-03-21 ↔ 1404/01/01, 2026-11-13 ↔ 1405/08/22, 1979-02-11 ↔ 1357/11/22, 2021-03-20 ↔ 1399/12/30, 2030-03-20 ↔ 1408/12/30.
- Leap-year and Esfand boundary behavior; invalid Esfand 30 rejected in non-leap years.
- Persian/Arabic digit normalization and strict Jalali form parsing; Gregorian values are not silently reinterpreted.
- Persian/English labels, Persian digit output, timezone-aware formatting, noon-anchored date labels.
- 42-cell month grid, in-month cell count, grid tail crossing, year-boundary navigation.
- Weekly normalization, invalid/overlapping windows, breaks, off days.
- Salon closure, specialist closure, specialist override, salon special day, custom hours, fallback to weekly rule.
- DST spring-forward rejection of nonexistent local times.
- Tehran midnight slot converts to the previous UTC date without changing the salon date.

CI integration coverage (when run):

- Schema v12 and unique `(staff_id, override_date)` index.
- Holiday/special-day/closure persistence and availability effects.
- Transactional booking rejection on a salon holiday.
- Day booking summary scoping.
- Independent-connection salon-lock serialization.
- Default-off `/availability-days` route; enabled-route Jalali labels and privacy fields.
- Specialist closure isolation across specialists.
- Authenticated admin calendar RTL/grid/keyboard/mobile/console checks in Chromium, Firefox, and WebKit.

---

## 4. Security and data impact

- **Permissions:** calendar page requires `edit_others_posts`; per-specialist actions require `edit_post`. Delete authorization resolves the stored row's staff scope rather than trusting a posted scope.
- **CSRF:** all mutating forms use WordPress nonces (`check_admin_referer`).
- **Input:** dates/times validated on the server; invalid dates and DST gaps fail closed; notes sanitized and length-bounded.
- **SQL:** table names are internal; values use `$wpdb->prepare`/formats; visible-staff scoping and bounded ranges reduce data exposure.
- **Concurrency:** salon→staff advisory locks plus the existing transactional booking recheck prevent holiday/booking races.
- **Public API:** remains gated and default-off. The new route exposes only date, Jalali label, and availability boolean. No customer data, booking IDs, or counts are returned.
- **Privacy:** no new PII is collected. Date overrides store operational notes only.
- **Data retention:** additive table; no implicit deletion on deactivation/uninstall. Existing booking and schedule data are preserved.
- **External calls:** none. No holiday API, SMS, payment, or production system is contacted.

---

## 5. Known gaps and limitations

- CI results for the exact head are pending; integration and browser status stays **UNVERIFIED** until they pass.
- No local WordPress/MariaDB environment; only pure-PHP and syntax evidence is local.
- No bundled official holiday dataset and no external holiday API; holidays are owner-entered.
- Multi-day holidays must be entered one date at a time.
- Jalali library supports 1–3177, but admin form input is intentionally limited to 1200–1600.
- The 33-year arithmetic cycle is cross-checked by reverse conversion but is not an official observatory/religious calendar authority.
- Adding a closure does not cancel or move existing bookings; operators must resolve conflicts separately.
- Changing the WordPress timezone after data entry changes the local meaning of stored wall-clock schedule rows; no automatic conversion is performed.
- Real screen-reader, real-device, production-timezone, reverse-proxy, and multi-node behavior remain UNVERIFIED.
- SMS, payments/refunds, WooCommerce/Elementor adapters, setup wizard, packaging, and production deployment are out of scope.

---

## 6. Next action

1. Push `feat/jalali-calendar-scheduling` and open a PR against `main` without merging.
2. Run exact-head CI (PHP 8.1/8.2/8.3, WordPress/MariaDB calendar integration, privacy integration, Chromium/Firefox/WebKit).
3. Record the PR URL and exact CI run URLs in this report and `docs/ROADMAP.md`.
4. Fix any CI findings before requesting review; do not infer PASS from local pure-PHP results.

Public booking remains disabled by default. No production deployment was performed.
