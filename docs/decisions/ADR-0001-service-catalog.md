# ADR-0001: Service catalogue ownership and presentation

- **Status:** Accepted
- **Date:** 2026-11-07
- **Scope:** Initial service catalogue implementation

## Context

Parisan is delivered as a WordPress theme plus a first-party companion plugin. Service descriptions and salon-specific pricing/duration are business catalogue data. They must remain available if the customer changes the visual theme.

## Decision

- Parisan Core owns the `parisan_service` content type and its structured metadata.
- The theme owns archive/detail presentation only.
- Service duration is stored as an integer number of minutes.
- Price is stored as an integer amount in the salon's configured base currency. The initial UI labels the amount as toman for the Persian-first Iran market; currency configuration and normalization are a later requirement.
- Price display mode is a constrained value: `fixed` or `from`.
- Metadata writes require a valid WordPress nonce and the current user's `edit_post` capability. Values are bounded and sanitized server-side.
- Theme templates escape output and remain functional without the Core plugin by falling back to WordPress's normal templates.

## Consequences

- Service data survives theme changes.
- The initial service editor uses a classic WordPress meta box; a richer React/Gutenberg editor is not required for this foundation slice.
- Booking availability and staff eligibility are not implied by the existence of a service record.
- Before production, currency must become an explicit salon setting and monetary storage/formatting must be reviewed for multi-currency and payment integration.

## Validation still required

PHP lint, plugin activation, save/reload behavior, REST exposure, role/capability checks, archive/detail rendering, RTL/mobile visual review, and WordPress version compatibility.
