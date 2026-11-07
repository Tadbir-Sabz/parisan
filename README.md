# Parisan Beauty — Premium WordPress Theme & Salon Platform

> Product vision, architecture, delivery roadmap, and mandatory contributor instructions.

**Project status:** Initial theme/catalogue, timezone-aware availability, date-specific calendar exceptions, and internal transactional booking-write service; public booking lifecycle pending  
**Canonical branch:** main  
**Active implementation branch:** `feature/service-catalog-foundation`

## Mandatory reading for every agent

1. [AGENTS.md](AGENTS.md) — operating rules, safety, architecture boundaries, and definition of done.
2. [Product Plan](docs/PRODUCT-PLAN.md) — requirements, user journeys, design direction, and quality targets.
3. [Delivery Roadmap](docs/ROADMAP.md) — sequencing, dependencies, milestones, and evidence-backed status.
4. [Architecture decisions](docs/decisions/) — durable implementation decisions.

## Product promise

Parisan is a premium, Persian-first WordPress experience for women's beauty salons, combined with a dependable salon operations platform:

- Responsive, accessible RTL website and brand-quality public pages.
- First-party salon catalogue, booking engine, staff scheduling, customer portal, and operations dashboard.
- First-party SEO, forms, performance/caching controls, and privacy-conscious settings.
- Adapter-based Melipayamak SMS integration.
- Optional WooCommerce and Elementor integrations with graceful fallback.
- Deliberately designed light/dark modes.

## Architecture

The product ships as a coordinated **theme + first-party companion plugin**:

- **Parisan Beauty theme:** presentation, templates, design system, responsive behavior, accessibility.
- **Parisan Core plugin:** durable business data and logic, including services, staff, bookings, customers, notifications, forms, SEO, and operational settings.
- Business data must survive theme changes. Do not put bookings or customer records in theme options or theme-only post types.
- WooCommerce and Elementor are optional integrations, not mandatory dependencies and not silently bundled.

## Repository structure

- `theme/parisan/` — WordPress theme.
- `plugin/parisan-core/` — first-party salon platform plugin.
- `docs/` — product plan, roadmap, architecture decisions.
- `.github/workflows/` — automated checks.

## Current implementation status

- [x] Product plan, roadmap, and mandatory agent rules.
- [x] Initial theme bootstrap, semantic header/footer, homepage starter, RTL-aware responsive styles.
- [x] Service catalogue archive and detail templates.
- [x] Parisan Core service and specialist content types.
- [x] Service duration and pricing fields with nonce, capability, sanitization, and bounded values.
- [x] Hierarchical service categories.
- [x] Specialist profiles, service eligibility, and weekly recurring availability editor.
- [x] Timezone-aware availability calculation for a salon-local date, including existing bookings, stored unavailability intervals, service duration, slot interval, and before/after buffers.
- [x] Persian admin calendar for full-day and partial-day specialist leave/unavailability, with local-time validation and UTC persistence.
- [x] Internal booking creation service with per-staff advisory lock, InnoDB transaction gate, locked availability recheck, idempotency, and atomic booking/audit-event persistence.
- [x] Controlled internal booking lifecycle: pending → confirmed/cancelled/expired and confirmed → cancelled/completed/no-show, with permission checks, confirmation conflict recheck, idempotent status replay, and audit events.
- [x] Persian WordPress admin booking operations screen with status filters, pagination, local time range, customer contact, price, and guarded lifecycle actions. Public REST/AJAX booking endpoints remain disabled.
- [x] PHP syntax lint workflow for PHP 8.1, 8.2, and 8.3.
- [x] Standalone schedule-normalization test suite passes in CI on PHP 8.1, 8.2, and 8.3.
- [x] Disposable WordPress + MariaDB activation/registration smoke test passes.
- [x] Latest baseline CI run passed PHP 8.1/8.2/8.3 and disposable WordPress/MariaDB activation smoke test.
- [x] Booking service + lifecycle CI run 42376217290 passed all 4 jobs: PHP 8.1/8.2/8.3 lint and schedule tests; disposable WordPress/MariaDB verifies InnoDB engines, staff lock acquisition/release, pending booking + audit event, idempotency replay/conflict, overlap rejection, fixed-price gate, atomic rollback on audit failure, confirmation/cancellation, idempotent status replay, invalid transition rejection.
- [x] Booking admin screen CI run 42376411637 passed all 4 jobs and rendered the admin booking list with a seeded customer booking. Browser-based visual and responsive review remains UNVERIFIED.
- [x] Manual booking workflow CI run 42376730497 passed all 4 jobs; the WordPress/MariaDB test rendered the manual booking form, normalized Persian phone digits, and rejected past appointment times.
- [x] Admin availability slot picker CI run 42376924602 passed all 4 jobs; the authenticated nonce-protected AJAX handler is registered only for logged-in admins, the script is enqueued on the booking screen, and Node syntax validation passes. Booking creation still rechecks availability under the staff lock.
- [x] Booking detail and audit timeline CI run 42377096704 passed all 4 jobs; the protected detail page rendered the seeded booking and its audit events.
- [x] CI validation of the availability engine: 22 schedule/availability assertions passed on PHP 8.1, 8.2, and 8.3; disposable WordPress/MariaDB smoke test passed (run 42373364749).
- [x] CI validation of calendar exception loading and seeded database-backed availability integration; 26 pure assertions pass on PHP 8.1/8.2/8.3 and the WordPress/MariaDB smoke test confirms booking + exception rows remove conflicting slots (run 42374197402).
- [ ] Browser rendering, responsive/RTL visual review, accessibility checks.
- [~] Availability, date-exception administration, internal booking creation, and controlled status lifecycle implemented and CI-verified. Booking accepts only fixed service prices; booking and calendar exception writes share the staff lock. Multi-connection concurrency stress, lock-timeout behavior, confirmation-conflict branch, lifecycle audit-failure rollback, browser booking/admin visual and accessibility review, break management, and resource schedules remain pending.
- [ ] Customer portal, SMS, forms, SEO, caching, and integrations.
- [x] See [ADR-0005](docs/decisions/ADR-0005-transactional-booking-creation.md), [ADR-0006](docs/decisions/ADR-0006-controlled-booking-lifecycle.md), [ADR-0007](docs/decisions/ADR-0007-staff-booking-operations-screen.md), [ADR-0008](docs/decisions/ADR-0008-staff-created-manual-bookings.md), and [ADR-0009](docs/decisions/ADR-0009-booking-detail-and-audit-timeline.md) for booking persistence, lifecycle, staff operations, manual booking, and audit detail boundaries.

**Do not infer runtime readiness from source files.** This is an implementation foundation, not a production release. Each feature requires evidence from automated tests and relevant manual flows.
