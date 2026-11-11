# Parisan Delivery Roadmap

**Canonical branch:** main  
**Active implementation branch:** `feat/parisan-product-completion`  
**Roadmap status:** The theme/catalogue and booking foundation are merged into main. Product-completion work is in progress on feat/parisan-product-completion: mobile navigation, editable homepage messaging, reliable blog routing, and owner-facing booking readiness guidance. Public booking remains disabled by default; browser/accessibility and deployment-specific security validation remain incomplete.  
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
- [~] Add architecture decision records for theme/core separation, booking data model, timezone/calendar, transactional booking creation, and integration strategy; ADR-0001 through ADR-0005 are present on the implementation branch.
- [ ] Define supported WordPress/PHP matrix based on current stable releases and available test environments.
- [~] Establish CI and focused automated validation; PHP lint and standalone weekly schedule tests PASS on PHP 8.1, 8.2, and 8.3. Disposable WordPress/MariaDB activation, plugin/theme activation, post type/taxonomy registration, and template asset smoke test PASS. Broader static analysis and end-to-end tests remain pending.
- [ ] Define local development environment and synthetic seed data.
- [x] Define the first booking PII lifecycle, active-record retention boundary, opt-in anonymization, and future public-route abuse-control requirements in ADR-0011; broader product threat modeling remains pending.

**Gate P0:** clean reproducible setup, CI baseline, approved architecture, no production credentials/data.

## Phase 1 — UX research, brand system, and product shell

**Status:** [ ] NOT STARTED

- [ ] Define brand concept, naming/wordmark direction, color tokens, Persian/Latin type system, iconography, spacing, motion, and responsive breakpoints.
- [ ] Produce desktop/tablet/mobile design references and high-fidelity homepage/service/booking/account/admin prototypes.
- [~] Define complete RTL/LTR interaction rules and dark/light tokens; accessible light/dark preference control, front-end Persian `dir="rtl"` direction fallback, SVG toggle icon, and WCAG-oriented Day/Night card contrast adjustments are implemented and checked in Chromium, while full-screen-reader/multi-browser WCAG 2.2 AA review remains UNVERIFIED.
- [x] Add initial theme scaffold, semantic header/footer, navigation, dedicated front page, standard page/post, archive, search, and 404 templates, and responsive RTL-aware base styles.
- [x] Add service catalogue archive and detail templates; runtime rendering remains UNVERIFIED.
- [~] Build public templates: dynamic editorial homepage and service/staff catalogue archive/detail foundations are implemented; gallery, blog, contact, FAQ, policies, and remaining content templates are pending. Browser rendering and responsive visual review remain UNVERIFIED.
- [~] Add accessible responsive gallery, testimonial, CTA, and sticky mobile booking action; the mobile booking shortcut is implemented and hides while the booking section is visible, while gallery/testimonial components and browser/device validation remain pending.
- [~] Validate content editing and Elementor coexistence/fallback; homepage brand messaging and CTA settings are now owner-editable through the Customizer, with browser validation pending.
- [ ] Create visual regression baselines for key viewports.

**Gate P1:** polished responsive public-site shell; RTL/LTR and dark/light reviewed; no console errors; accessibility baseline.

## Phase 2 — Parisan Core foundation and salon catalogue

**Status:** [ ] NOT STARTED

- [~] Add the first Persian-first admin settings workspace, booking-rule controls, and scoped admin styling; a complete setup wizard, full capability map, logging, and diagnostics remain pending.
- [ ] Add versioned database migrations and upgrade safety.
- [~] Add service content type and editable duration/price/price-display metadata with server-side nonce/capability/sanitization checks. Categories, add-ons, packages, booking rules, and runtime validation remain pending.
- [~] Implement specialist profiles, service eligibility, weekly recurring schedule editor with validated recurring break windows (breaks are excluded from availability), and date-specific full-day/partial-day unavailability administration. Resource schedules and expanded calendar UX remain pending.
- [~] Implement single-salon shared resource inventory and service resource pools; multi-branch/tenant ownership and branch-specific resource policies remain pending.
- [~] Add a unified settings entry point linking brand, homepage copy and four benefit cards, separate day/night hero images, booking rules, SEO/performance, WordPress timezone/language, and menus; a guided first-run setup wizard and role-specific dashboard remain pending.
- [ ] Add seed/demo data that is clearly non-production.
- [ ] Test deactivation/theme-switch data persistence.
- [x] Add pure weekly schedule normalization tests to the PHP CI matrix; previous run PASS on PHP 8.1/8.2/8.3; 22 assertions.

**Gate P2:** salon catalogue and staff/schedule administration usable; migrations tested; permissions enforced.

## Phase 3 — Booking engine and customer portal (first business-ready release)

**Status:** [~] IN PROGRESS — schema, availability, internal booking-write service, and gated API/token foundation; customer UX and deployment validation incomplete

- [x] Define and add versioned booking, booking-event, and schedule-exception tables with additive activation/upgrade migration; disposable WordPress/MariaDB smoke test confirms all three tables and schema version.
- [x] Implement timezone-aware availability calculation using staff weekly hours, service duration, configurable slot interval, before/after buffers, existing pending/confirmed bookings, and stored unavailable intervals. Pure tests and a seeded WordPress database integration test are required for this milestone.
- [x] Add Persian admin management for date-specific full-day leave/closures and partial-day unavailability; all submitted local intervals are validated and stored in UTC.
- [~] Add owner-configurable minimum booking lead time and maximum booking horizon with bounded settings, Persian admin controls, date-picker alignment, shared slot filtering, and server-side booking/reschedule enforcement. Focused integration coverage includes exact boundaries, non-default horizon values, and transactional rejection inside the lead time; latest CI validation is pending.
- [~] Recurring weekly specialist breaks are implemented in the schedule editor and availability engine. Configurable minimum lead time (0–10,080 minutes) and booking horizon (1–365 days, default 90) are implemented in the booking-rules admin screen and enforced by shared availability filtering, transactional booking rechecks, public booking creation, and customer rescheduling. Shared resource inventory, service-to-resource pools, resource-aware availability, transactional resource locks, and booking resource snapshots are implemented. Per-resource weekly working hours are now implemented in the resource editor and enforced against the complete buffered interval during shared availability calculation; exact-SHA automated validation is pending. Multi-resource bundles per service and concurrent multi-connection stress validation remain pending.
- [x] Implement and CI-verify internal concurrency-safe booking creation: per-staff advisory lock, InnoDB fail-closed gate, locked availability recheck, idempotency, and atomic pending booking + audit event transaction (run 42376217290). Multi-connection stress and lock-timeout behavior remain UNVERIFIED. The public route wrapper is separately feature-gated and reuses this service.
- [x] Implement internal booking state machine, audit trail, authorized manual receptionist booking, and concurrency-safe rescheduling; browser workflows and walk-in UX remain unverified/not started.
- [ ] Implement Persian/Jalali calendar UI backed by canonical UTC timestamps and salon IANA timezone.
- [~] Implement a gated public API foundation for availability, pending booking creation, token-scoped customer view, and cancellation (ADR-0012). Routes and the wizard remain disabled unless the owner explicitly defines `PARISAN_PUBLIC_BOOKING_ENABLED=true`; initial Persian RTL shortcode UI is implemented, while browser/device/accessibility review remains UNVERIFIED.
- [~] Implement non-account customer ownership using 256-bit random, HMAC-digested, expiring, revocable access tokens (ADR-0012). Customer registration/login and account-based ownership remain future options.
- [x] Add token-scoped booking view, availability, cancellation, and self-service rescheduling APIs plus the Persian shortcode UI for lookup/rescheduling/cancellation. Disposable WordPress/MariaDB integration verifies token ownership, same-origin enforcement, the 90-day horizon, current-booking exclusion from conflict checks, duration/price preservation, and token-expiry adjustment; browser/device/accessibility workflows remain UNVERIFIED.
- [~] Implement admin booking list, status filters/actions, detail/audit timeline, manual booking, and reschedule form; calendar-style operations view, search, and daily dashboard remain pending.
- [ ] Add email notifications and testable notification queue.
- [~] Test timezone boundaries, concurrent requests, token scoping/rotation/expiry, lifecycle transitions, privacy export/erasure, retention, and abuse controls; updated WordPress/MariaDB CI covers token lifecycle and default-off/explicitly-enabled route/shortcode behavior. Browser, multi-connection stress, proxy, and multi-node evidence remains pending.

**Gate P3:** a complete customer can book and manage an appointment; staff can operate the schedule; no double booking under tested concurrency.

## Phase 4 — SMS, forms, and operational communication

**Status:** [ ] NOT STARTED

- [ ] Verify current official Melipayamak API docs and supported authentication/send/status methods.
- [ ] Implement provider adapter, secure credential storage, masked settings, and explicit connection test.
- [ ] Implement Persian SMS templates, variable preview, event mapping, and staff/customer routing.
- [ ] Implement queue, bounded retry, idempotency, quiet hours, consent, endpoint-specific rate limits, and redacted logs. The current reusable rate-limit primitive does not enable or secure a public route by itself.
- [ ] Implement reminders with duplicate prevention and real-cron guidance.
- [ ] Implement contact/consultation/custom forms, validation, spam controls, privacy consent, retention, export/delete.
- [ ] Add mock-provider and test-recipient modes; prove automated tests never send live SMS.

**Gate P4:** messaging is observable, safe, consent-aware, and failure-tolerant; forms are secure and manageable.

## Phase 5 — SEO and performance toolkit

**Status:** [~] IN PROGRESS — initial metadata, structured-data, and privacy-aware cache boundaries implemented; sitemap extensions, crawler controls, vendor cache adapters, and performance diagnostics pending

- [~] Implement SEO metadata templates, canonical, Open Graph, Service JSON-LD, and context-aware breadcrumbs with BreadcrumbList schema. Per-content title/description/noindex, site defaults, BreadcrumbList, and WordPress core sitemap exclusion for noindex content are implemented with smoke coverage; custom sitemap providers/extensions, redirect management, and real search-engine validation remain pending.
- [x] Implement duplicate-output detection and coexistence controls for Yoast SEO, Rank Math, SEOPress, All in One SEO, and The SEO Framework using explicit ownership detection; full version/plugin-matrix compatibility remains UNVERIFIED.
- [ ] Add redirect management and privacy-safe 404 diagnostics if justified.
- [ ] Implement page-specific asset loading, image optimization, font strategy, and Core Web Vitals instrumentation guidance.
- [~] Implement safe first-party cache boundaries and targeted post-purge adapters for LiteSpeed Cache, WP Rocket, and W3 Total Cache. The adapters are capability-detected and scoped to Parisan service/specialist content; isolated PHP tests cover provider detection and post-scoped requests. A Persian admin diagnostics table reports the external object-cache runtime flag, drop-in presence, and provider APIs. Provider-installed runtime verification, backend health metrics, CDN/Reverse Proxy purge, and measured purge completion remain pending.
- [x] Explicitly bypass public cache for authenticated, booking-shortcode, tokenized customer, and Parisan customer REST contexts using WordPress cache flags and no-store headers; reverse-proxy behavior remains UNVERIFIED.
- [ ] Benchmark representative pages and queries; tune database indexes and query plans.
- [ ] Validate sitemap, metadata, structured data, crawlability, and real mobile performance.

- [x] CI on runtime/test commit `c01e55e`: PHP 8.1/8.2/8.3 lint, WordPress theme/SEO/cache smoke, privacy/customer integration, and PHP 8.1/8.2/8.3 abuse-control matrix all PASS — [PHP/WordPress run 42452870953](https://github.com/Tadbir-Sabz/parisan/actions/runs/42452870953), [WordPress/MariaDB run 42452870912](https://github.com/Tadbir-Sabz/parisan/actions/runs/42452870912), [privacy matrix run 42452870925](https://github.com/Tadbir-Sabz/parisan/actions/runs/42452870925). Later commits are documentation-only.

**Gate P5:** no duplicate SEO output; sitemap, metadata, and structured data validated; cache-plugin/reverse-proxy purge and bypass behavior verified; performance report meets agreed targets or documents justified gaps; no private data cached publicly.

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

## Booking privacy and public endpoint readiness

**Status:** [~] IN PROGRESS — see [ADR-0011](docs/decisions/ADR-0011-booking-personal-data-lifecycle.md).

- [x] Register booking records with WordPress personal-data export/erasure hooks; PHP matrix PASS [run 42398990545](https://github.com/Tadbir-Sabz/parisan/actions/runs/42398990545).
- [x] Define and integration-test terminal-booking anonymization while preserving operational/event structure; active pending/confirmed bookings are reported as retained [run 42398990422](https://github.com/Tadbir-Sabz/parisan/actions/runs/42398990422).
- [x] Add and integration-test administrator-controlled automatic retention, disabled by default, with bounded daily anonymization batches [run 42398990422](https://github.com/Tadbir-Sabz/parisan/actions/runs/42398990422).
- [x] Add and test a reusable HMAC-keyed, MySQL-lock-serialized fixed-window request limiter; raw IPs are not stored and forwarded-IP headers are ignored [runs 42398990545 and 42398990422](https://github.com/Tadbir-Sabz/parisan/actions).
- [x] Dedicated PHP 8.1/8.2/8.3 matrix assertions PASS [run 42398990545](https://github.com/Tadbir-Sabz/parisan/actions/runs/42398990545).
- [x] Disposable WordPress/MariaDB erasure and forced transaction rollback PASS (run 42398990422). Browser privacy workflow, reverse-proxy IP topology, multi-node rate-limit behavior, and owner-approved retention period remain UNVERIFIED.
- [~] Implemented gated public availability/create/customer-view/cancel routes, HMAC-digested 256-bit expiring customer tokens, rotation/revocation, same-origin mutation checks, endpoint rate limiting, no-store responses, and transactional customer cancellation (ADR-0012). Updated disposable WordPress/MariaDB integration CI is pending.
- [ ] Before enabling the feature constant: complete end-to-end IDOR/replay/brute-force/concurrency tests, browser booking UX and accessibility, reverse-proxy/multi-node verification, security review, and explicit site-owner approval. The routes remain disabled by default.

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

As of the active implementation branch:

- Product architecture, agent instructions, product plan, roadmap, and ADRs 0001–0011 are present in the repository.
- Parisan theme and Core catalogue/scheduling foundations are implemented; browser rendering, responsive RTL, accessibility, and theme-switch persistence remain UNVERIFIED.
- Availability and calendar exception integration passed [CI run 42374197402](https://github.com/Tadbir-Sabz/parisan/actions/runs/42374197402).
- Internal booking creation and lifecycle passed [CI run 42376217290](https://github.com/Tadbir-Sabz/parisan/actions/runs/42376217290).
- Manual booking, admin slot lookup, and booking detail/audit screens have CI evidence in runs 42376730497, 42376924602, and 42377058236.
- Duration snapshots and concurrency-safe rescheduling passed [CI run 42377819791](https://github.com/Tadbir-Sabz/parisan/actions/runs/42377819791).
- Privacy exporter/eraser, terminal-only anonymization, active-booking retention, rollback safety, opt-in retention, and request throttling passed the PHP matrix [run 42398990545](https://github.com/Tadbir-Sabz/parisan/actions/runs/42398990545) and disposable WordPress/MariaDB integration [run 42398990422](https://github.com/Tadbir-Sabz/parisan/actions/runs/42398990422).
- Latest PHP syntax, schedule/availability, booking integration, and WordPress activation smoke tests passed [run 42398990464](https://github.com/Tadbir-Sabz/parisan/actions/runs/42398990464).
- Public booking/customer portal, secure customer access tokens, resource-specific schedules and multi-resource bundles, browser visual/accessibility review, multi-connection stress, proxy/multi-node rate-limit verification, SMS, forms, SEO, caching, WooCommerce/Elementor adapters, packaging, and production deployment remain NOT STARTED or UNVERIFIED as marked above.
- No production site, customer database, live SMS, or payment system has been accessed.

Do not infer runtime readiness from source files. Replace this section only with reproducible evidence.


## Product completion follow-up — 2026-11-09

- [~] Resolve review findings H1/M1/M2/M3: safe booking remains off by default with a Persian admin readiness checklist; homepage CTAs reflect disabled booking; blog links use the configured posts page or a non-404 home fallback; mobile navigation has a keyboard-operable toggle with Escape handling and a no-JavaScript fallback; key homepage copy and CTA URLs are owner-editable.
- [ ] Add and execute browser-based visual/accessibility checks at desktop/tablet/mobile viewports.
- [ ] Verify contrast with default and owner-selected palettes; no custom palette is claimed WCAG-compliant without measurement.
- [ ] Run exact-SHA CI and independent review on the implementation branch before merge.


- [~] Hero photography now has separate day/night images, independent focal points and alternative text, plus responsive media-library output; four trust-benefit cards are owner-editable; 17 self-contained local AI-generated demo visuals (`theme/parisan/assets/images/*.jpg`) eliminate broken/duplicated Unsplash URLs across homepage and archives; duplicate front-page bottom loop and theme-toggle overlap are resolved with fresh Chromium desktop/mobile day/night verification on the session branch. Existing saved day-image URLs remain supported. Real salon photography, multi-browser/screen-reader audits, and production deployment remain pending.


## Product completion follow-up — 2026-11-10

- [x] Add a Persian-first, read-only first-run readiness center under «متخصصان ← راهنمای شروع», linked from the unified settings workspace. It reports live checks for theme activation, front-page configuration, timezone, bookable service data, specialist/service/schedule coverage, booking rules, HTTPS detection, and the public-booking gate; each item links to its corrective admin screen.
- [x] Add responsive status presentation and a Persian setup guide. The readiness screen never mutates content, options, schedules, database state, or the public-booking constant.
- [x] Add WordPress smoke assertions for unique checks and complete actionable status records.
- [ ] Execute the full CI suite against the new exact head SHA. The GitHub connector currently reports no workflow runs or status checks for the new commit; automated validation is therefore UNVERIFIED until a run is triggered and completed.


- [x] Add a live operational overview to the booking-management screen: active bookings for the current local day, all bookings awaiting confirmation, and confirmed bookings from the next local day onward. The cards link directly to the pending queue, use the WordPress timezone, and are read-only aggregates.
- [x] Add responsive styling and smoke assertions for the new operational metrics.
- [ ] Confirm the new exact commit through PHP, WordPress activation, privacy/integration, and Chromium/Firefox/WebKit browser CI before marking the operational overview PASS.


## Booking operations — daily schedule

- Added a timezone-safe daily schedule filter to the booking operations screen. Staff can filter by salon-local date, specialist, and booking status; the selected day is shown in chronological order while the unfiltered history remains newest-first.
- Date boundaries are converted from the WordPress salon timezone to UTC before querying, including daylight-saving transitions. Invalid dates and unauthorized specialist filters are discarded safely.
- Booking overview metrics are calculated in the booking-list scope; booking detail queries use the explicit bookings table. Public booking remains explicitly gated and disabled by default.


## Resource scheduling follow-up — 2026-11-11

- [x] Add optional per-resource weekly working hours in the resource editor (rooms, beds, chairs, devices).
- [x] Enforce resource-local working windows in the shared availability allocator using the WordPress timezone; the complete booking interval including service buffers must fit within the resource window.
- [x] Preserve backward compatibility: resources without an enabled custom schedule remain unrestricted by resource-specific hours.
- [ ] Add focused automated coverage for enabled/disabled days, boundary fits, buffer spillover, and timezone/DST boundaries; exact-head CI validation remains pending.
- [ ] Implement multi-resource bundles (e.g. room + device in one appointment) across allocation, booking snapshots, rescheduling, and admin display.
