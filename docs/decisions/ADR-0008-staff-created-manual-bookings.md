# ADR-0008: Staff-Created Manual Bookings

- Status: Accepted for the internal staff workflow
- Date: 2026-11-07
- Scope: Manual booking by authorized salon staff; no public booking endpoint is authorized

## Context

The booking operations list can manage existing bookings, but front-desk staff also need to record appointments made by phone, in person, or through another offline channel.

## Decision

1. Add a manual booking form to the Persian staff booking operations screen.
2. Only authenticated users with `edit_others_posts` may open the workflow. The handler additionally checks edit permission for the selected specialist and service and verifies a nonce.
3. The form accepts specialist, service, salon-local start time, customer name, phone, and optional email. It displays the WordPress salon timezone next to the datetime control.
4. The handler delegates to `parisan_core_create_booking()`; it does not write booking rows directly. The shared staff lock, InnoDB transaction, idempotency, future-time check, fixed-price gate, and availability recheck therefore remain mandatory.
5. Every manual booking starts in `pending` and must be confirmed through the controlled lifecycle action.
6. Persian and Arabic numerals in phone input are normalized to ASCII before validation/storage. The UI does not expose customer email in the booking list.
7. The idempotency token is generated for the rendered form and submitted with the booking to make a repeated submission safe.

## Boundaries and follow-up

- This is an internal front-desk form, not a customer-facing booking flow.
- It does not yet offer live slot suggestions; the server validates the manually selected time at submit.
- Browser/RTL/mobile visual review, form validation UX, real staff role review, and end-to-end manual browser submission remain UNVERIFIED.
- No notification, SMS, payment, or external customer communication is triggered.
