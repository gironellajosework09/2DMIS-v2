# 2DMIS v2 — Session Handoff

**Last Updated:** 2026-08-07

---

## Current Status

### Project State

- Planning: Complete
- Architecture: Complete
- Migration Planning: Complete
- Engineering Blueprint: Complete
- Implementation: In Progress

### Current Milestone

**P5 — Payout Attendance and Unpaid Verification** (not yet started)

---

## Completed Milestones

### P0 — Foundations

- Laravel 12 foundation, environment setup, baseline schema, CI setup.

Status: Complete

---

### P1 — Authentication & RBAC

- Username authentication, single-device login, Access Control Service (ACL),
  gates, authorization middleware, audit logging.

Status: Complete — Tests: Passing

---

### P2 — Clients & Households

- Clients, households, family members, duplicate detection, client photos,
  student self-service, right-side slide-over details panel.

Status: Complete — Tests: Passing

---

### P3 — Transactions

- Transaction module, CRUD, reports, CSV export, filters, program permissions.

Status: Complete — Tests: Passing

---

### P4 — Scanner Engine

- Config-driven `ScanService` (8 behavioral modes) replacing all 14 v1
  scanners as config; single shared scanner view; 14 scanner routes gated by
  `page:scanner_*.php` middleware.

Status: Complete — Tests: Passing

---

## Last Session Summary

**Completed:**

- P4 Scanner Engine
- Config-driven `ScanService`
- 14 scanner routes
- 74 tests passing
- Documentation completed

**Next:**

- Begin P5 Payout module

---

## Current Work — P5 Payout Attendance & Unpaid Verification

**Goal:** Port the v1 payout module onto the attendance data the P4 engine
already writes, preserving v1 behavior exactly.

**Focus:**

- Attendance management
- Payout attendance
- Unpaid verification
- Preserve v1 payout behavior
- Preserve database parity
- Build automated tests

Reference contract: `docs/implementation/P5_PAYOUT.md` (v1 ground truth,
extension points, acceptance gates). The P4 engine already provides the
**write side** (`payout` / `payout_unpaid` scanner keys → `tbl_payout_scans2` /
`tbl_payout_scans_unpaid`); P5 is the read/verification side, so no schema
change and no duplicated write path.

---

## Development Priorities (P5)

1. Port the three payout attendance list screens
   (`scanned_payouts.php`, `scanned_payouts2.php`, `scanned_payouts_unpaid.php`)
   into **one shared view** driven by a variant/backing-table argument, plus
   their DataTables feeds (`fetch_scanned_payouts*.php`).
2. Port the unpaid verification workflow (`unpaid_verifications.php`,
   `unpaid_save.php`, `disabled_unpaid.php`, `fetch_unpaid_verifications.php`,
   `export_unpaid_verifications.php`, `search_grantee.php`,
   `search_unpaid_grantee.php`) into a controller + service.
3. Honor the one-scan-per-transaction contract via the DB `UNIQUE` on the
   payout-scan tables — never add an app-level dedup workaround.
4. Keep the proxy identity block in `tbl_unpaid_verifications` a denormalized
   snapshot (v1 design intent).
5. Confirm v1 `disabled_unpaid.php` semantics (delete vs disable) before coding.
6. Confirm the exact v1 audit `action` strings for payout/unpaid writes before
   inventing new ones.
7. Port CSV exports with the UTF-8 BOM (reuse the P3 streamed-download pattern);
   the v1 `export_scanned_payouts_unpaid.php` link is dead — implement the
   export deliberately.
8. Write feature tests (list feeds, unpaid create/disable/search/export,
   duplicate-rejection) and keep the full suite green on `main_system_test`.

---

## Open Decisions

- Soft-deletes / client-merge: in scope or not.
- Additive indexes on existing tables (recommended: yes).
- ADR-001..010 flip from Proposed to Accepted.
- Git: repo has no remote yet (docs live in a separate repo).

---

## Current Risks

### Highest Risk

Payout parity.

Every payout attendance, unpaid verification, and export behavior must exactly
match the v1 implementation, including:

- The one-scan-per-transaction unique constraint contract.
- The proxy snapshot semantics.
- The exact audit action strings and v1 column sets.

### Secondary Risks

- `tbl_payout_scans` (legacy variant 1) rows may no longer be meaningful in
  production — verify before wiring the first screen.
- The dead `export_scanned_payouts_unpaid.php` link must be resolved
  deliberately rather than silently dropped.

---

## Before Next Session

Continue **P5 — Payout Attendance & Unpaid Verification** using
`docs/implementation/P5_PAYOUT.md` as the build contract.

Priority:

1. Read `docs/implementation/P5_PAYOUT.md` (§2 v1 ground truth, §4 extension
   points) and `docs/SCANNER_ANALYSIS.md` §4.13/§4.14 for the scan-time
   behavior that produces the data these screens display.
2. Read the v1 payout files in `C:\xampp\htdocs\system` (read-only):
   `scanned_payouts*.php`, `fetch_scanned_payouts*.php`,
   `unpaid_verifications.php`, `unpaid_save.php`, `disabled_unpaid.php`,
   `export_unpaid_verifications.php`, `search_grantee.php`,
   `search_unpaid_grantee.php`.
3. Confirm the exact `disabled_unpaid.php` and audit `action` string semantics.
4. Build the shared payout-attendance view + feeds.
5. Build the unpaid verification service + screens.
6. Write automated tests; run `vendor\bin\pint` before finishing.
7. Append the P5 entry to `docs/IMPLEMENTATION_LOG.md` when delivered.

Do not redesign behavior. Parity comes before optimization.

---

## Documentation Status

Current documents:

- README
- Migration Plan
- Migration Planning
- Engineering Blueprint
- Architecture Decisions
- Implementation Log
- P5 Payout contract (`docs/implementation/P5_PAYOUT.md`)

Status: Up to date.

---

## Reminder

Do not modify:

- Production database schema
- Legacy v1 source code
- Authentication contract
- Permission keys
- Audit log format

Database parity is mandatory.
