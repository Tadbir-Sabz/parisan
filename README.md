# Parisan Beauty — Premium WordPress Theme & Salon Platform

> Product vision, architecture, delivery roadmap, and mandatory contributor instructions.

**Project status:** Planning / repository bootstrap  
**Canonical branch:** main  
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

## Important architecture decision

The product is delivered as a coordinated **theme + first-party companion plugin** in this same repository:

- The **theme** owns presentation, templates, design system, responsive behavior, accessibility, and theme-specific settings.
- The **Parisan Core plugin** owns durable business logic and data: bookings, staff, services, customers, notifications, forms, SEO features, and operational settings.
- Core functionality must remain available when the visual theme is changed. Never store bookings or other business-critical data in theme options or theme-only custom post types.
- The companion plugin is part of the Parisan product distribution. It is not an external dependency the customer must find independently.
- WooCommerce and Elementor integrations are feature-detected and degrade gracefully when those plugins are absent. Do not reimplement their entire platforms.

This separation is intentional: WordPress deactivates theme code on a theme switch, so putting booking and customer records in the theme would be fragile and unsafe.

## Repository structure (target)

- AGENTS.md
- README.md
- docs/PRODUCT-PLAN.md
- docs/ROADMAP.md
- docs/ARCHITECTURE.md
- docs/DESIGN-SYSTEM.md
- docs/decisions/
- theme/ — Parisan Beauty WordPress theme
- plugin/parisan-core/ — first-party business and platform features
- tests/ — automated and integration tests
- .github/ — CI, issue and contribution templates

The repository is currently being bootstrapped. This target structure is a direction, not a claim that these directories or features already exist.

## Current delivery status

- [x] Repository product entrypoint and mandatory documentation-first rule
- [ ] Product requirements and architecture baseline
- [ ] Delivery roadmap and acceptance criteria
- [ ] UX flows and design system
- [ ] Theme foundation and visual implementation
- [ ] Parisan Core booking and salon operations engine
- [ ] SEO, performance, forms, SMS, customer portal
- [ ] WooCommerce and Elementor integrations
- [ ] Security, accessibility, compatibility, and release validation

**Do not describe a feature as implemented or PASS until code, tests, and evidence demonstrate it.**