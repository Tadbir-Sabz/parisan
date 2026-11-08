# ADR-0002: Staff profiles and weekly availability

- **Status:** Accepted for the initial catalogue milestone
- **Date:** 2026-11-07

## Context

The booking engine needs a clear relationship between specialists, the services they can perform, and their recurring weekly working windows. The first milestone needs an editable source of availability without prematurely introducing a full scheduling engine.

## Decision

- Parisan Core owns specialist profile records and metadata; the theme only renders public specialist pages.
- A specialist's eligible services are stored as validated service post IDs and may reference only Parisan service records.
- Weekly availability is stored as salon-local wall-clock times, keyed by WordPress weekday (0 Sunday through 6 Saturday).
- Each day is either marked off or has a valid HH:MM start and end, with end later than start. Overnight shifts are not supported in this first model.
- Metadata writes require a valid nonce, edit_post capability, and server-side sanitization.
- Date-specific leave, holidays, breaks, exceptions, branch/resource allocation, and timezone-aware slot calculation are explicitly deferred to the scheduling/booking milestone.

## Consequences

- The initial editor can configure a specialist's weekly pattern and service eligibility.
- The weekly pattern is not yet proof of a bookable slot; booking must not be enabled until exceptions, timezone conversion, buffers, and conflict handling are implemented.
- A later migration may move recurring schedules into normalized tables if query and operational requirements justify it.

## Validation still required

PHP lint, WordPress activation, save/reload behavior, capability testing, public profile rendering, timezone interpretation, and full RTL/mobile visual review.
