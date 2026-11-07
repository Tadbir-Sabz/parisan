# Parisan Beauty — Premium WordPress Theme & Salon Platform

> Product vision, architecture, delivery roadmap, and mandatory contributor instructions.

**Project status:** Initial implementation scaffold — runtime validation pending  
**Canonical branch:** main (implementation work is currently on `feature/phase-0-foundation`)  
**Product:** A premium, Persian-first WordPress experience for women's beauty salons, backed by a first-party salon operations engine.

## Start here — mandatory for every agent

Before inspecting, changing, testing, or proposing work, read these documents in order:

1. [AGENTS.md](AGENTS.md) — mandatory agent workflow, safety rules, scope boundaries, and definition of done.
2. [Product Plan](docs/PRODUCT-PLAN.md) — product requirements, user journeys, architecture, design direction, and quality targets.
3. [Delivery Roadmap](docs/ROADMAP.md) — phases, dependencies, milestones, acceptance gates, and current status.

If a task request conflicts with these documents, do not silently choose one: explain the conflict and propose a documented resolution. Update the roadmap when scope or status materially changes.

## Product promise

Parisan is intended to feel like a polished premium beauty brand while working as a dependable salon business platform. It combines:

- A high-conversion, responsive, accessible, Persian-first RTL website.
- A first-party salon management and online appointment-booking engine.
- Customer accounts, staff/service scheduling, notifications, and operational reporting.
- First-party SEO, performance/caching controls, forms, and security-conscious settings.
- Deep, optional integrations with WooCommerce and Elementor.
- An adapter-based SMS integration for Melipayamak (ملی پیامک).
- Light and dark themes, with remembered visitor preference.

The product must not require unrelated third-party plugins for its advertised core capabilities. WordPress itself remains the platform; WooCommerce and Elementor are supported integrations, not silently bundled dependencies.

## Architecture

The product is delivered as a coordinated **theme + first-party companion plugin** in this same repository:

- **Parisan Beauty theme:** presentation, templates, design system, responsive behavior, accessibility, and theme-specific settings.
- **Parisan Core plugin:** durable business logic and data: bookings, staff, services, customers, notifications, forms, SEO features, and operational settings.
- Core functionality must remain available when the visual theme is changed. Never store bookings or other business-critical data in theme options or theme-only custom post types.
- WooCommerce and Elementor integrations are feature-detected and degrade gracefully when those plugins are absent. Do not reimplement their entire platforms.

## Repository structure

- `AGENTS.md`, `README.md`
- `docs/PRODUCT-PLAN.md`, `docs/ROADMAP.md`
- `theme/parisan/` — initial WordPress theme scaffold
- `plugin/parisan-core/` — initial companion plugin scaffold
- Planned: architecture decision records, automated tests, CI, packaging and manuals.

## Current verified progress

- [x] Product plan, roadmap, and mandatory agent rules committed.
- [x] Initial theme files added: metadata, bootstrap, header, footer, main template, and responsive RTL-aware stylesheet.
- [x] Initial Parisan Core plugin added with service and specialist catalogue content types.
- [ ] PHP lint, WordPress activation, browser rendering, accessibility, and responsive visual checks.
- [ ] Booking engine, customer portal, SMS, forms, SEO, caching, and integrations.

**Important:** The current branch is an implementation scaffold, not a release. Runtime compatibility and all feature behavior remain unverified until tested in a real WordPress environment.
