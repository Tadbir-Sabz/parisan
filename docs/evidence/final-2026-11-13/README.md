# Parisan final QA evidence — 2026-11-13

This folder is a **curated, compressed subset** of the local final-QA evidence captured on a disposable WordPress 7.1.4 + MariaDB 11.4.8 installation (`http://127.0.0.1:8081`). All content is synthetic demo data; no customer, production, SMS, or payment data is present.

## Contents

| File | Screen |
|---|---|
| `desktop-home-viewport.jpg` | Homepage, desktop, day |
| `night-home-viewport.jpg` | Homepage, desktop, night |
| `mobile-home-viewport.jpg` | Homepage, 390 px, day |
| `mobile-night-home-viewport.jpg` | Homepage, 390 px, night |
| `desktop-services.jpg` | Services archive |
| `mobile-services.jpg` | Services archive, mobile |
| `desktop-service-detail.jpg` | Service detail, two-column desktop |
| `mobile-service-detail.jpg` | Service detail, mobile |
| `night-service-detail.jpg` | Service detail, night theme |
| `desktop-specialists.jpg` | Specialists archive |
| `mobile-specialist-detail.jpg` | Specialist detail, mobile |
| `desktop-portfolio.jpg` | Portfolio archive |
| `desktop-journal.jpg` | Journal archive |
| `desktop-contact-full.jpg` | Contact page, full desktop |
| `mobile-contact-viewport.jpg` | Contact page, mobile |
| `desktop-search.jpg` | Search results |
| `desktop-404.jpg` | Not-found page |
| `admin-dashboard.jpg` | WordPress dashboard with Parisan widgets |
| `admin-settings.jpg` | Parisan settings hub |
| `admin-bookings.jpg` | Booking operations |
| `admin-customize.jpg` | Customizer with Parisan panels |
| `diagnostics.json` | Browser console/page error capture (2 expected 404 test requests) |
| `responsive-overflow.json` | 72 route/viewport measurements across 1920, 1440, 1280, 1024, 768, 430, 390, 375, and 360 px |

The full 50-screenshot set and the original PNG files are kept outside Git at `artifacts/final/` because of repository size. The screenshots here were resized to a maximum width of 1200 px and encoded as JPEG quality 82; they are evidence of layout and theme behavior, not pixel-perfect visual-regression baselines.

## Pass conditions represented

- Every measured route has `scrollWidth === innerWidth` (no horizontal overflow).
- Every measured interior page has its breadcrumb top below the header bottom.
- Day and night themes both render readable breadcrumbs and contrast-controlled surfaces.
- Mobile header, navigation, hero, and booking shortcut are visible and do not overlap content.
- Admin pages render in Persian RTL on desktop and mobile.

## Not represented here

- Firefox/WebKit visual review
- Real screen-reader acceptance
- Physical-device testing
- Production HTTPS/DNS/reverse-proxy behavior
- Jalali calendar UI (not implemented)
