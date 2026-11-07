# Parisan Delivery Roadmap

**Canonical branch:** main  
**Active implementation branch:** `feature/service-catalog-foundation`  
**Roadmap status:** Initial theme/catalogue and timezone-aware availability calculation source implemented on a feature branch; availability CI validation pending.  
**Rule:** Update this file in the same delivery slice whenever phase status, scope, dependencies, or acceptance evidence changes.

## Status legend

- [ ] NOT STARTED
- [~] IN PROGRESS
- [x] PASS — acceptance criteria met with evidence
- [!] BLOCKED — dependency/authorization/environment prevents progress
- [?] UNVERIFIED — insufficient evidence; never treat as complete

A checkbox is not a quality verdict by itself. Each completed milestone must link to a commit, test report, or artifact.

## Delivery strategy

Build the product in usable vertical slices. Establish architecture and quality automation first, then deliver the public experience and booking engine early. Integrate messaging, SEO, performance, customer portal, and commerce in coherent slices. Avoid polishing every page before the first real booking flow works.

## Phase 0 — Product and repository foundation

**Status:** [~] IN PROGRESS

- [x] Initialize product README and documentation-first agent entrypoint.
- [x] Define product vision, target users, scope, and architecture boundary.
- [x] Define phased roadmap and status rules.
- [ ] Inspect repository branch protections, CI, permissions, and initial commit state; document verified baseline.
- [ ] Add architecture decision records for theme/core separation, booking data model, timezone/calendar, and integration strategy.
- [ ] Define supported WordPress/PHP matrix based on current stable releases and available test environments.
- [~] Establish CI and focused automated validation; PHP lint and standalone weekly schedule tests PASS on PHP 8.1, 8.2, and 8.3. Disposable WordPress/MariaDB activation, plugin/theme activation, post type/taxonomy registration, and template asset smoke test PASS. Broader static analysis and end-to-end tests remain pending.
- [ ] Define local development environment and synthetic seed data.
- [ ] Add security/privacy threat model and data classification.

**Gate P0:** clean reproducible setup, CI baseline, approved architecture, no production credentials/data.

## Phase 1 — UX research, brand system, and product shell

**Status:** [ ] NOT STARTED

- [ ] Define brand concept, naming/wordmark direction, color tokens, Persian/Latin type system, iconography, spacing, motion, and responsive breakpoints.
- [ ] Produce desktop/tablet/mobile design references and high-fidelity homepage/service/booking/account/admin prototypes.
- [ ] Define complete RTL/LTR interaction rules and dark/light tokens.
- [x] Add initial theme scaffold, semantic header/footer, navigation, dedicated front page, standard page/post, archive, search, and 404 templates, and responsive RTL-aware base styles.
- [x] Add service catalogue archive and detail templates; runtime rendering remains UNVERIFIED.
- [ ] Build public templates: home, service archive/detail, team, gallery, blog, contact, FAQ, policies, search, 404.
- [ ] Add accessible responsive gallery, testimonial, CTA, and sticky mobile booking action.
- [ ] Validate content editing and Elementor coexistence/fallback.
- [ ] Create visual regression baselines for key viewports.

**Gate P1:** polished responsive public-site shell; RTL/LTR and dark/light reviewed; no console errors; accessibility baseline.

## Phase 2 — Parisan Core foundation and salon catalogue

**Status:** [ ] NOT STARTED

- [ ] Implement plugin bootstrap, namespacing/autoloading, module boundaries, capability map, settings, logging, and diagnostics.
- [ ] Add versioned database migrations and upgrade safety.
- [~] Add service content type and editable duration/price/price-display metadata with server-side nonce/capability/sanitization checks. Categories, add-ons, packages, booking rules, and runtime validation remain pending.
- [~] Implement specialist profiles, service eligibility, and a weekly recurring schedule editor. Breaks, date-specific exceptions, leave, holidays, and resource schedules remain pending.
- [ ] Implement branches/resources data model with single-salon-first UX.
- [ ] Build admin navigation and setup wizard.
- [ ] Add seed/demo data that is clearly non-production.
- [ ] Test deactivation/theme-switch data persistence.
- [x] Add pure weekly schedule normalization tests to the PHP CI matrix; latest run pending.

**Gate P2:** salon catalogue and staff/schedule administration usable; migrations tested; permissions enforced.

## Phase 3 — Booking engine and customer portal (first business-ready release)

**Status:** [~] IN PROGRESS — schema foundation only

- [x] Define and add versioned booking, booking-event, and schedule-exception tables with additive activation/upgrade migration; disposable WordPress/MariaDB smoke test confirms all three tables and schema version.
- [~] Implement availability calculation: core supports staff weekly hours, service duration, configurable slot interval, before/after buffers, existing pending/confirmed bookings, and stored unavailable intervals. Date-exception admin UI, breaks, leave/holiday management, resources, capacity, lead time, and booking horizon remain pending.
- [ ] Implement concurrency-safe booking creation and double-book prevention.
- [ ] Implement booking state machine, audit trail, manual receptionist booking, reschedule/cancel policies, and walk-ins.
- [ ] Implement Persian/Jalali calendar UI backed by canonical UTC timestamps and salon IANA timezone.
- [ ] Implement public booking wizard with accessible mobile UX and server-side validation.
- [ ] Implement customer registration/login integration with WordPress accounts and secure booking ownership.
- [ ] Implement customer portal: upcoming/past bookings, details, cancellation/reschedule, profile, preferences.
- [ ] Implement admin calendar/list, filters, search, status actions, and daily dashboard.
- [ ] Add email notifications and testable notification queue.
- [ ] Test timezone boundaries, concurrent requests, authorization/IDOR, and lifecycle transitions.

**Gate P3:** a complete customer can book and manage an appointment; staff can operate the schedule; no double booking under tested concurrency.

## Phase 4 — SMS, forms, and operational communication

**Status:** [ ] NOT STARTED

- [ ] Verify current official Melipayamak API docs and supported authentication/send/status methods.
- [ ] Implement provider adapter, secure credential storage, masked settings, and explicit connection test.
- [ ] Implement Persian SMS templates, variable preview, event mapping, and staff/customer routing.
- [ ] Implement queue, bounded retry, idempotency, quiet hours, consent, rate limits, and redacted logs.
- [ ] Implement reminders with duplicate prevention and real-cron guidance.
- [ ] Implement contact/consultation/custom forms, validation, spam controls, privacy consent, retention, export/delete.
- [ ] Add mock-provider and test-recipient modes; prove automated tests never send live SMS.

**Gate P4:** messaging is observable, safe, consent-aware, and failure-tolerant; forms are secure and manageable.

## Phase 5 — SEO and performance toolkit

**Status:** [ ] NOT STARTED

- [ ] Implement SEO metadata templates, canonical/robots controls, sitemap generation, Open Graph, breadcrumbs, and validated JSON-LD.
- [ ] Implement duplicate-output detection and coexistence controls for other SEO plugins.
- [ ] Add redirect management and privacy-safe 404 diagnostics if justified.
- [ ] Implement page-specific asset loading, image optimization, font strategy, and Core Web Vitals instrumentation guidance.
- [ ] Implement safe first-party cache controls, invalidation, persistent object-cache detection, and diagnostics.
- [ ] Explicitly bypass public cache for authenticated, booking, customer, and personalized routes.
- [ ] Benchmark representative pages and queries; tune database indexes and query plans.
- [ ] Validate sitemap, metadata, structured data, crawlability, and real mobile performance.

**Gate P5:** no duplicate SEO output; benchmark report meets agreed targets or documents justified gaps; no private data cached publicly.

## Phase 6 — Commerce and page-builder integrations

**Status:** [ ] NOT STARTED

- [ ] Implement optional WooCommerce adapter and booking/order linkage.
- [ ] Support deposits/full payment, failed/pending payment, refunds, and order status reconciliation.
- [ ] Integrate customer account navigation and checkout styling without replacing WooCommerce core.
- [ ] Implement Elementor compatibility layer, global styles, selected widgets/dynamic data, and editor-safe controls.
- [ ] Verify base theme and booking remain functional when WooCommerce/Elementor are absent.
- [ ] Run supported-version integration matrix and regression tests.

**Gate P6:** tested end-to-end commerce lifecycle; Elementor editor/front end remain stable; graceful absence and update compatibility proven.

## Phase 7 — Admin polish, reports, hardening, and localization

**Status:** [ ] NOT STARTED

- [ ] Complete unified Parisan dashboard, filters, bulk operations, exports, diagnostics, and role-specific views.
- [ ] Add operational reports: bookings, cancellations, no-shows, revenue (where payment data exists), utilization, and service performance.
- [ ] Complete accessibility review against WCAG 2.2 AA target.
- [ ] Complete Persian localization, RTL visual review, English/LTR review, and Persian user/admin manuals.
- [ ] Perform security review, dependency audit, privacy/retention review, and upgrade/uninstall tests.
- [ ] Perform cross-browser/device and supported WordPress/PHP compatibility testing.
- [ ] Complete performance and load tests for realistic booking volumes.

**Gate P7:** release candidate passes security, accessibility, localization, compatibility, upgrade, and performance gates.

## Phase 8 — Packaging, release, and maintenance

**Status:** [ ] NOT STARTED

- [ ] Build distributable theme and Parisan Core packages from the same tagged release.
- [ ] Include setup wizard, dependency/compatibility checks, demo content importer with safe rollback, and uninstall/data-retention choices.
- [ ] Produce Persian installation, configuration, booking operations, SMS, SEO, troubleshooting, and update guides.
- [ ] Produce release notes, changelog, licensing notices, SBOM/dependency inventory, and support policy.
- [ ] Validate clean install, upgrade from previous supported release, rollback plan, and theme-switch persistence.
- [ ] Tag release only after all gates have evidence and no unresolved release-blocking findings.

**Gate P8:** reproducible release artifacts and documented support/upgrade process.

## Cross-cutting workstreams

These apply throughout all phases:
- Security and privacy threat modeling.
- Accessibility and responsive RTL/LTR testing.
- Performance budgets and regression monitoring.
- Automated test coverage and CI reliability.
- Documentation and agent onboarding.
- Dependency/license/supply-chain review.
- Evidence-based status reporting.

## Priority order and first implementation slice

1. Finish Phase 0 baseline and architecture decisions.
2. Design the key public pages and booking journey before broad page implementation.
3. Build theme shell and Core foundation together.
4. Deliver a complete booking vertical slice as the first major product outcome.
5. Add SMS/forms, customer operations, SEO/performance, then WooCommerce/Elementor integration.
6. Harden, package, and release only after evidence-backed gates.

## Current verified progress

As of the current implementation branch:
- Product documentation and mandatory agent rules are present on main.
- Initial theme bootstrap, homepage, service archive, and service detail source files exist on `feature/service-catalog-foundation`.
- Parisan Core registers service and specialist content types; service duration/price metadata, hierarchical service categories, specialist-to-service eligibility, and weekly recurring availability fields have source implementations with nonce/capability checks and input validation.
- A GitHub Actions PHP syntax workflow targets PHP 8.1, 8.2, and 8.3. Its run result is pending until GitHub executes it.
- WordPress activation, editor save/reload, browser rendering, accessibility, RTL/mobile visual review, and compatibility are UNVERIFIED.
- Versioned booking/event/schedule-exception schema is implemented and verified by the disposable WordPress/MariaDB CI smoke test.
- Timezone-aware availability calculation source is implemented; new CI validation is pending. Date-specific exception management UI, breaks, leave/holiday rules, booking transactions, customer portal, SMS, forms, SEO toolkit, caching, WooCommerce and Elementor adapters remain NOT STARTED.
- No production site, customer database, live SMS, or payment system has been accessed.

Do not infer runtime readiness from source files. Replace this section only with reproducible evidence.
