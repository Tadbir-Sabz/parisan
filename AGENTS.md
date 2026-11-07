# Agent Operating Rules — Parisan

This file is mandatory. Every human or automated agent must read it, README.md, docs/PRODUCT-PLAN.md, and docs/ROADMAP.md before starting work. The repository owner may explicitly authorize a narrower emergency task, but the agent must still inspect the relevant architecture and safety constraints.

## 1. Mission and source of truth

Build Parisan as a premium, reliable, Persian-first WordPress theme and salon platform. The product plan defines intended behavior; the roadmap defines sequencing and current evidence-backed status. Code, tests, and reproducible evidence determine what is actually implemented.

At task start:
1. Read the four mandatory documents.
2. Inspect the current branch, working tree, recent commits, repository structure, and existing implementation.
3. Identify the exact task scope, dependencies, risks, and acceptance criteria.
4. Preserve existing work. Never overwrite, reset, force-push, or discard another agent's changes.
5. Report baseline facts before making broad changes.

## 2. Execution principles

- Prefer substantial, outcome-based implementation over repetitive audit-only work.
- Work in coherent vertical slices: user journey, backend behavior, UI, tests, documentation, and evidence together.
- Do not create speculative abstractions or build every feature in one giant unreviewable change.
- Use focused tests during development and a proportionate regression suite at meaningful gates.
- Run independent implementation and read-only review in parallel where practical. Independent review must not block safe, unrelated implementation.
- Do not claim PASS based on code inspection alone when runtime behavior is required.
- Use statuses exactly: PASS, FAIL, BLOCKED, or UNVERIFIED, with scope and evidence.
- QA is required at meaningful security, data-integrity, integration, and release gates; avoid repetitive full audits when no material risk or evidence change exists.

## 3. Safety and authorization

- Never connect to, modify, migrate, seed, or delete production/customer databases without explicit owner authorization and a verified backup/restore plan.
- Never deploy, change DNS, rotate live credentials, send real customer SMS, charge payments, or alter a live WordPress site without explicit authorization.
- Use local disposable environments and synthetic data by default.
- Never expose credentials, API keys, personal data, phone numbers, or booking details in logs, screenshots, commits, or reports. Use secret stores/environment variables and redaction.
- Do not disable security controls, TLS verification, backups, or access checks to make tests pass.
- Before installing tools/dependencies, inspect licensing, maintenance, supply-chain risk, and side effects. Keep dependencies minimal.
- No destructive Git operation, history rewrite, force push, or branch protection change without explicit authorization.
- If environment isolation, permissions, backups, or target identity cannot be proven, mark the action BLOCKED or UNVERIFIED and explain why. Do not guess.

## 4. Architecture guardrails

- Theme = presentation. Parisan Core = durable salon/business behavior and data.
- Booking, customers, staff, service definitions, message logs, and business settings must survive a theme switch.
- Keep the product self-contained in this repository and distribution. Do not make advertised first-party features depend on unrelated plugins.
- WooCommerce and Elementor are supported external integrations, not mandatory runtime dependencies for the base product. Detect availability and degrade gracefully.
- Do not fork or recreate all of WooCommerce or Elementor functionality.
- Use WordPress APIs, capability checks, nonces, sanitization, escaping, prepared SQL, and least privilege.
- Prefer custom database tables for high-volume relational booking/scheduling data when justified; use versioned, reversible migrations and explicit uninstall/data-retention behavior.
- Store all instants in UTC and retain the salon timezone (IANA identifier); render in the salon's configured timezone. Persian calendar presentation must not corrupt canonical timestamps.
- Avoid double booking through server-side transactional/locking logic, not front-end availability checks alone.
- Make external services adapter-based. A provider failure must not corrupt bookings.
- Never silently send SMS or email to real people in development/test runs.

## 5. Engineering quality

- Follow WordPress coding standards and modern PHP practices compatible with the declared minimum PHP/WordPress versions.
- Declare minimum and tested versions explicitly; do not claim compatibility without CI/runtime evidence.
- Use progressive enhancement, semantic HTML, keyboard support, reduced-motion preferences, and WCAG 2.2 AA as the accessibility target.
- Keep JavaScript modular and load only what each page needs. Avoid unnecessary front-end frameworks and heavy dependencies.
- Escape output by context; sanitize input; validate business rules on the server.
- Use capability-based authorization for every privileged action and object-level checks for customer-owned bookings.
- Add automated tests for booking conflicts, permissions, timezones, payments, SMS failures, migrations, and critical integrations.
- Never place secrets in source control or expose sensitive provider response payloads.

## 6. Required task report

Every substantial task ends with:
1. **Verdict:** PASS / FAIL / BLOCKED / UNVERIFIED, limited to actual scope.
2. **Changes:** files/modules and user-visible outcomes.
3. **Tests:** exact commands, environment, pass/fail/skip counts.
4. **Evidence:** commit SHA, screenshots or test artifacts where relevant.
5. **Security/data impact:** permissions, migrations, external calls, data retention.
6. **Known gaps:** explicit list; no implied completion.
7. **Next action:** one highest-value step that advances the roadmap.

Update docs/ROADMAP.md when a milestone changes. Update architecture decision records when a durable architectural choice changes. Keep README status accurate.

## 7. Definition of done

A feature is done only when its acceptance criteria are met, implementation is integrated, relevant automated tests pass, critical manual flows are evidenced, security and accessibility implications are addressed, Persian RTL and responsive behavior are checked where relevant, documentation is updated, and no known blocker is hidden.

A green build is not proof of product completeness. A mockup is not a working feature. A test skipped due to missing infrastructure is not a pass.
