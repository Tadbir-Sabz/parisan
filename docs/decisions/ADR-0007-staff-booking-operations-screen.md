# ADR-0007: Staff Booking Operations Screen

- Status: Accepted for the initial internal admin workflow
- Date: 2026-11-07
- Scope: WordPress admin operations; no public booking endpoint or customer portal is authorized

## Context

The booking write service and state machine need an operational interface for salon staff. The first interface should let authorized staff review bookings and perform only lifecycle transitions already enforced by the Core service.

## Decision

1. Add a Persian WordPress admin submenu under Specialists: “مدیریت نوبت‌ها”.
2. Restrict the page and its POST handler to users with `edit_others_posts`; each state transition is additionally authorized against the assigned specialist by `parisan_core_transition_booking()`.
3. Use nonce-protected POST forms for state changes. The handler delegates to the lifecycle service and never writes booking status directly.
4. Display a paginated list of bookings with status filtering, customer name/phone, service, specialist, salon-local start/end time, and price in toman.
5. Do not display customer email or public identifiers in the list. Do not export customer data or place PII in logs.
6. Available actions are derived from the current status and remain subject to the lifecycle state machine and confirmation conflict recheck.
7. Public REST/AJAX endpoints, customer self-service, booking creation UI, rescheduling, and notifications remain outside this decision.

## Verification and boundaries

- CI run 42376411637 passed all four jobs and rendered the admin page with a seeded synthetic booking.
- Browser-based visual/RTL/mobile review, accessibility review, pagination/filter manual checks, and end-to-end staff browser actions remain UNVERIFIED.
- This is an initial operations list, not a complete salon front-desk workspace.
