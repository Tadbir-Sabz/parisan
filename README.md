# Parisan Beauty — Premium WordPress Theme & Salon Platform

> Product vision, architecture, delivery roadmap, and mandatory contributor instructions.

**Project status:** Initial theme and service-catalogue implementation; runtime validation pending  
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
- [x] Specialist profiles, service eligibility, and weekly recurring availability editor (runtime behavior pending).
- [x] PHP syntax lint workflow for PHP 8.1, 8.2, and 8.3.
- [x] Standalone schedule-normalization test suite passes in CI on PHP 8.1, 8.2, and 8.3.
- [x] Disposable WordPress + MariaDB activation/registration smoke test passes.
- [ ] CI execution result and WordPress runtime activation.
- [ ] Browser rendering, responsive/RTL visual review, accessibility checks.
- [ ] Staff schedule model, booking engine, customer portal, SMS, forms, SEO, caching, integrations.

**Do not infer runtime readiness from source files.** This is an implementation foundation, not a production release. Each feature requires evidence from automated tests and relevant manual flows.
