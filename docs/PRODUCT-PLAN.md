# Parisan Beauty — Product Plan

**Status:** Baseline product definition  
**Audience:** Product owner, designers, developers, QA, and AI agents  
**Primary market experience:** Persian language, RTL, Iran-oriented salon operations  
**Architecture:** WordPress theme + first-party companion plugin in one product/repository

## 1. Product vision

Create a premium digital platform for women's beauty salons that combines brand-quality presentation with practical day-to-day salon operations. It must be beautiful enough to represent a high-end salon, fast enough for mobile visitors, discoverable by search engines, and reliable enough to manage real appointments.

The product should be configurable for different salon brands rather than hard-coded to one salon. Persian is the default language and RTL is a first-class layout direction. English and LTR must also be supported without layout breakage.

## 2. Product principles

1. **Premium visual identity:** refined, editorial, image-led, calm, confident, and conversion-focused—not a generic marketplace template.
2. **Mobile-first:** most discovery and booking journeys must be excellent on mobile.
3. **Booking reliability first:** availability, staff schedules, booking state, and customer notifications must be consistent server-side.
4. **Self-contained core:** first-party SEO, forms, booking, customer portal, SMS integration, and performance controls ship with Parisan Core.
5. **Integration, not lock-in:** WooCommerce and Elementor are optional integrations with graceful fallback.
6. **Persian-native:** correct RTL, Persian typography, localized numbers/date presentation, and Jalali calendar UX where appropriate.
7. **Privacy and security:** collect only necessary data, restrict access, retain data intentionally, and make consent explicit.
8. **Evidence-led quality:** performance, accessibility, SEO, and reliability claims require repeatable measurements.

## 3. Audiences and roles

- **Visitor:** explores salon, services, portfolio, team, prices, articles, and location.
- **Customer:** registers/signs in, books, pays if required, reschedules/cancels under policy, views history, and manages profile.
- **Receptionist:** creates and manages bookings, checks in customers, handles walk-ins, and views daily schedule.
- **Staff member / specialist:** sees assigned schedule and permitted booking/customer details; manages availability only within granted permissions.
- **Salon manager:** configures services, staff, schedules, pricing, booking rules, notifications, reports, and website content.
- **Administrator:** manages WordPress and platform settings with least-privilege controls.

## 4. Public website and visual direction

### 4.1 Brand and art direction

Create a distinct visual system suitable for premium beauty businesses:
- Warm ivory / porcelain surfaces, ink or deep plum typography, restrained champagne/rose accents; colors must remain configurable and contrast-safe.
- Editorial typography with a Persian font stack selected after licensing and rendering tests; pair Latin typography intentionally.
- High-quality real salon photography, generous whitespace, strong hierarchy, subtle borders, restrained shadows, and purposeful motion.
- Avoid excessive gradients, glassmorphism, tiny low-contrast text, oversized decorative effects, and generic stock-template patterns.
- Light and dark modes must both be deliberately designed, not simple color inversion.

### 4.2 Main page and templates

- Home: hero, clear booking CTA, signature services, trust signals, gallery, specialists, testimonials, offers, location/contact, FAQs.
- Services archive and service detail: duration, price/from-price, description, specialist options, preparation/care, related services, booking CTA.
- Specialists/team archive and profile: bio, specialties, working hours (public-safe), portfolio, book with this specialist.
- Gallery/portfolio with accessible filtering and optimized responsive images.
- Offers/packages and gift cards where enabled.
- Blog/archive/article with author, breadcrumbs, related content, and structured metadata.
- About, contact, FAQ, policies, privacy, and booking terms.
- Search, 404, archives, and accessible empty/error states.
- Header/footer variants, sticky booking action on mobile, and configurable global announcement bar.

### 4.3 Design system and responsive UX

Define design tokens for color, type, spacing, radii, shadows, z-index, motion, breakpoints, and component states. Support keyboard focus, hover, active, disabled, loading, success, and error states. Ensure Persian text wrapping, mixed-script labels, numerals, icons, menus, and form validation behave correctly in RTL.

## 5. Booking and salon operations (Parisan Core)

This is a core product capability, not a decorative contact form.

### 5.1 Service catalogue

- Categories, service descriptions, duration, base price, sale price, tax/fee configuration where applicable.
- Optional add-ons, packages, required preparation, aftercare, and booking lead-time.
- Service-specific eligible specialists, locations/rooms/resources, and capacity rules.
- Configurable visibility, active status, and booking eligibility.

### 5.2 Staff and resources

- Staff profiles, specialties, service eligibility, working schedules, breaks, leave, holidays, and exceptions.
- Multiple branches/locations as a planned extensibility point; initial release may target one salon with clean data modeling.
- Rooms/chairs/equipment/resources with capacity and conflict rules where a service requires them.
- Role-based staff access; staff must not automatically see all customer data.

### 5.3 Booking journey

1. Choose service (and optional add-ons).
2. Choose specialist or “any available”.
3. Choose branch/location if enabled.
4. Select date and real-time available slot.
5. Enter or confirm customer details.
6. Review policy, price, deposit, and cancellation terms.
7. Pay a deposit/full amount when configured, or reserve without online payment if allowed.
8. Receive confirmation and manage booking from customer account or secure link.

Availability must be recalculated and validated on the server at submission. Prevent concurrent double booking with transaction-safe locking/constraints. Support configurable slot intervals, buffers, cleanup time, lead-time, booking horizon, minimum notice, and capacity.

### 5.4 Booking lifecycle

Use explicit states, for example: pending, awaiting payment, confirmed, checked-in, in-service, completed, cancelled, rejected, no-show, and refunded. Define allowed transitions and audit every important change. Support rescheduling, cancellation windows, waitlist (later milestone), manual receptionist bookings, recurring blocks, and walk-ins.

### 5.5 Calendar and operations dashboard

- Day/week views, staff/resource filters, status indicators, conflict warnings, quick create/edit, and customer lookup.
- Persian/Jalali calendar presentation with correct timezone conversion.
- Today summary, upcoming appointments, pending requests, cancellations, revenue summaries, and staff utilization.
- CSV export with capability checks and privacy-safe field selection.

### 5.6 Customer portal

- Profile and contact preferences.
- Upcoming and past bookings with clear statuses.
- Reschedule/cancel according to salon policy.
- Payment/deposit/refund status and receipts where integrated.
- Notification preferences and consent.
- Secure password reset and account deletion/privacy request pathways.
- Mobile-friendly, accessible dashboard; never expose another customer's records by changing an ID or URL.

### 5.7 Payments and WooCommerce

- Base booking must work without WooCommerce when the salon permits unpaid reservations.
- When WooCommerce is installed, offer an integration adapter for products/orders/checkout/refunds and use WooCommerce payment gateways rather than building a competing payment processor.
- Link booking identifiers to order IDs with integrity checks; handle failed, pending, refunded, and partially refunded orders.
- Do not assume WooCommerce is installed or activate it automatically.
- Never mark a booking paid based only on a browser redirect; verify trusted server-side order/payment state.

## 6. Messaging and Melipayamak (ملی پیامک)

Build a provider adapter and admin SMS panel:
- API credentials entered in settings, masked after save, never displayed in logs or exported.
- Sender line, provider configuration, connection test with explicit administrator action, and delivery/error diagnostics with redaction.
- Templates for booking received, confirmed, reminder, rescheduled, cancelled, payment status, and staff alerts.
- Persian template editor, variable preview, character/segment estimate where supported, and per-event enable/disable.
- Queue and retry with bounded attempts, idempotency, delivery status where provider supports it, and failure visibility.
- Consent/preferences, quiet hours, rate limits, and prevention of duplicate reminders.
- Dry-run/test recipient mode; no real SMS during automated tests.
- Provider API details must be verified against current official Melipayamak documentation before implementation. Keep credentials and transport details behind an adapter so another provider can be added later.

## 7. SEO toolkit

First-party SEO controls should cover:
- Editable title and meta description templates per content type and individual page.
- Canonical URLs, robots directives, index/noindex controls, XML sitemap index and child sitemaps.
- Open Graph and social metadata.
- Breadcrumbs and valid JSON-LD structured data (Organization/LocalBusiness as appropriate, WebSite, WebPage, Article, Service, Person, BreadcrumbList; use only properties supported by actual content).
- Image alt-text workflow, descriptive media handling, clean permalinks, pagination, archive controls, redirects (planned), and 404 monitoring (privacy-safe).
- Search Console / analytics verification fields without requiring a plugin; do not inject analytics without consent/configuration.
- Avoid duplicate schema, title tags, canonical tags, and sitemaps when another SEO plugin is active. Provide compatibility detection and clear ownership/disable controls.
- Never promise rankings. Validate with structured-data tools, crawl tests, and real search-engine requirements.

## 8. Performance and caching

Performance is an architectural requirement:
- Lightweight server-rendered templates; no unnecessary client-side application shell.
- Conditional asset loading by template/feature; minified production assets; avoid render-blocking scripts.
- Responsive WebP/AVIF where supported, WordPress image sizes/srcset, lazy loading below the fold, explicit dimensions, and no lazy-loading of the primary LCP image.
- Font subsets/preload only when measured and licensed; avoid loading multiple unused weights.
- Database indexes and bounded queries for booking lookups; pagination for admin tables.
- First-party cache controls for safe derived data/transients and cache invalidation on content/settings/booking changes.
- Optional object-cache integration when a persistent object cache exists.
- Full-page cache is host/CDN dependent; do not cache customer dashboards, booking flows, authenticated pages, or personalized responses as public pages.
- Do not claim that a theme can replace PHP OPcache, a host-level reverse proxy, CDN, or server configuration.
- Provide diagnostics and safe cache purge controls; never purge unrelated host-wide caches without authorization.

Targets (measured on a documented reference environment, production build, representative content):
- Core Web Vitals “good” thresholds: LCP <= 2.5s, INP <= 200ms, CLS <= 0.1 at the 75th percentile when field data exists.
- Lighthouse mobile performance target >= 90 on agreed representative pages, with disclosed environment and repeat runs; treat as a target, not a guarantee.
- No avoidable layout shifts; minimal JS; page-specific assets; no unbounded database queries.

## 9. Forms and lead management

- First-party contact, consultation request, callback, and custom form builder for common field types.
- Server-side validation, nonce/CSRF protection, spam/rate limiting, honeypot and optional CAPTCHA integration.
- Consent text, privacy notice, configurable retention, export/delete tools, and notification routing.
- Store submissions securely with restricted capabilities and redacted logs.
- Email delivery through WordPress mail hooks with clear diagnostics; SMTP delivery remains a hosting/service concern and should be integrated rather than reimplemented.
- Accessible validation, success, and error states; Persian localization and RTL.

## 10. Admin experience

Provide a coherent Parisan workspace in WordPress admin:
- Overview dashboard and setup wizard.
- Bookings calendar/list and booking detail.
- Services, categories, add-ons, packages.
- Staff, schedules, leave, resources, branches.
- Customers and privacy-aware history.
- SMS provider, templates, queue, logs.
- Forms and submissions.
- SEO settings and per-content controls.
- Performance/cache diagnostics and safe controls.
- Integrations, roles/capabilities, notifications, data retention, import/export, backup guidance.
- Clear empty states, inline help, validation, status, search, filters, pagination, bulk actions with confirmation, and Persian help/manual content.

Do not overload the WordPress dashboard with duplicate menus. Keep navigation predictable and permissions explicit.

## 11. Elementor and WooCommerce compatibility

### Elementor
- Provide documented styling compatibility and selected native Elementor widgets/dynamic controls only where they add real value.
- Provide template/theme-builder compatibility and global design tokens.
- Feature-detect Elementor; no fatal errors or broken front end when absent, disabled, or updated.
- Core booking shortcode/block must work independently of Elementor.
- Do not require Elementor Pro unless a feature is explicitly labeled as such.

### WooCommerce
- Declare compatibility only after integration tests with supported versions.
- Support account endpoints and checkout/cart styling without overriding WooCommerce core templates unnecessarily.
- Booking-to-order/payment lifecycle must be tested end-to-end.
- Do not duplicate cart, checkout, payment gateways, tax, or refund engines.

## 12. Dark and light modes

- User-selectable light/dark/system preference.
- Accessible contrast in both themes, including booking calendar, admin-facing front-end portal, forms, gallery overlays, and menus.
- Prevent flash of incorrect theme where feasible without blocking rendering.
- Remember preference locally; offer a clear toggle and keyboard-accessible label.
- Respect system preference on first visit and reduced-motion preferences.

## 13. Security, privacy, and reliability

- Capability checks and object-level authorization for every read/write.
- Nonces for state-changing browser requests; REST API permission callbacks; rate limiting for public endpoints.
- Prepared SQL, strict validation, contextual escaping, safe uploads, MIME checks, and secure file handling.
- Protect booking enumeration and customer data; secure links must be random, expiring, and scoped.
- Audit logs for privileged booking changes, payment state, settings, and exports, with retention controls.
- Data export/erasure tools, privacy policy guidance, consent records, and documented retention.
- No payment-card data storage; delegate payment handling to WooCommerce/gateway providers.
- Threat model and dependency review before release.

## 14. Technical architecture baseline

- WordPress-native PHP architecture with namespaced, autoloaded modules and explicit service boundaries.
- Theme and plugin developed in this repository and released as one coordinated product.
- Use WordPress APIs for hooks, settings, users, media, REST, cron, and capabilities.
- Use custom relational tables for bookings, staff schedules, resources, notification queue/logs, and other high-volume transactional data when benchmarks and query needs justify them. Version every schema migration and test upgrade/rollback strategy.
- Keep service catalog and editorial content compatible with WordPress content APIs where that is the simpler maintainable choice.
- All timestamps stored canonically in UTC; salon timezone stored as an IANA timezone identifier; display calendar can be Jalali.
- Use WP-Cron as a fallback for reminders and queues, but document that reliable timing may require a real server cron trigger.
- External integrations use adapters and typed/validated boundaries.
- Establish minimum PHP and WordPress versions after checking the supported hosting market and CI matrix. Do not invent compatibility claims.
- Avoid mandatory runtime build tooling; commit reproducible production assets and lockfiles/manifests for development tooling.

## 15. Quality and acceptance

Release readiness requires:
- Installation, activation, upgrade, deactivation, and uninstall/data-retention tests.
- Theme switch test proving booking/customer records remain intact.
- Booking concurrency and timezone tests, including daylight-saving boundaries where relevant.
- Capability/IDOR, nonce, REST, input validation, XSS, SQL injection, and upload security tests.
- SMS adapter failure/retry/idempotency tests using mocks.
- WooCommerce and Elementor integration tests across declared versions and graceful-absence tests.
- SEO crawl/metadata/schema/sitemap tests and duplicate-plugin coexistence tests.
- Accessibility checks for keyboard, focus, contrast, forms, menus, calendar, and screen-reader semantics.
- RTL and LTR visual regression at mobile, tablet, and desktop widths.
- Performance measurements on representative pages and booking flows.
- Persian user guide, administrator manual, setup/troubleshooting, and release notes.

## 16. Out of scope unless approved

- Building a payment gateway or storing card data.
- Replacing hosting, CDN, DNS, mail transport, or server-level cache.
- Automatically provisioning external services or sending real customer messages.
- Guaranteeing search ranking, Lighthouse score, or provider delivery.
- Building a multi-salon SaaS tenancy platform in the first release; preserve extension points without prematurely implementing it.
