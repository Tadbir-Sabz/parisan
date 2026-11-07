# ADR-0009: Booking Detail and Audit Timeline

- Status: Accepted for the internal staff operations workflow
- Date: 2026-11-07
- Scope: Authorized WordPress admin detail view; no public customer data endpoint is authorized

## Context

The booking list is useful for daily operations, but staff need a protected detail view to inspect customer contact information and the complete sequence of status changes.

## Decision

1. Each booking row links to a dedicated detail view within the existing staff booking admin page.
2. The detail view checks both the general staff operations capability and edit permission for the booking's assigned specialist.
3. It displays the customer name, phone, optional email, service, specialist, salon-local time range, price snapshot, current status, and audit event timeline.
4. Audit events are ordered by UTC creation time and include event type, old/new status, actor display name, and internal note.
5. Lifecycle actions on the detail page still use nonce-protected POST forms and the existing transition service. The handler returns the operator to the same booking detail after the action.
6. Customer email and internal audit notes are not shown in the general booking list.

## Verification and boundaries

- CI run 42377096704 passed all four jobs and rendered the booking detail page with a seeded customer and audit event timeline.
- Browser/RTL/mobile visual review, accessibility, role-specific visibility, and manual browser action flow remain UNVERIFIED.
- No export, customer portal, public API, or direct database mutation is introduced.
