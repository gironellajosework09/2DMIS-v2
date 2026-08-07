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

**P6 — Scholars / GIP** (not yet started; P5 complete)

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

### P5 — Payout Attendance & Unpaid Verification

- Config-driven payout module (`config/payout.php`, 3 attendance variants) with
  one shared list view + DataTables feeds; unpaid verification admin screen,
  **public** self-service form (`disabled_unpaid.php` equivalent), verify/search,
  delete, and BOM CSV export. No audit on any P5 write path (v1 parity).

Status: Complete — Tests: Passing

---

## Last Session Summary

**Completed:**

- P5 Payout Attendance & Unpaid Verification
- Config-driven `payout.php` (3 variants) + shared attendance view/feeds
- Unpaid verification admin + public self-service + search/verify + delete + export
- 89 tests passing (15 new in `PayoutTest.php`)
- Corrected `docs/implementation/P5_PAYOUT.md` §2.2 ground-truth error
- Documentation completed

**Next:**

- Begin P6 Scholars / GIP

---

## Current Work — P6 Scholars / GIP

**Goal:** Port the v1 scholars/GIP module
(`docs/implementation/P6_SCHOLARS.md`).

**Focus:**

- Scholar management / exam results
- GIP info screens
- Preserve v1 behavior
- Preserve database parity
- Build automated tests

Reference contract: `docs/implementation/P6_SCHOLARS.md`. P5 delivered the
payout attendance lists, unpaid verification (admin + public self-service),
grantee search/verify, and CSV exports — all reads, with **no audit** on any
P5 write path (v1 parity confirmed in `docs/implementation/P5_PAYOUT.md`).

---

## Development Priorities (P6)

1. Read `docs/implementation/P6_SCHOLARS.md` (§2 v1 ground truth, §4 extension
   points) and the matching v1 files under `C:\xampp\htdocs\system` (read-only).
2. Confirm the v1 audit `action` strings before inventing new ones.
3. Port the scholars/GIP screens following the P3/P4/P5 conventions
   (config/controller-driven where several pages share one shape).
4. Write feature tests and keep the full suite green on `main_system_test`.

---

## Open Decisions

- Soft-deletes / client-merge: in scope or not.
- Additive indexes on existing tables (recommended: yes).
- ADR-001..010 flip from Proposed to Accepted.
- Git: repo has no remote yet (docs live in a separate repo).

---

## Current Risks

### Highest Risk

Payout parity (now delivered — remaining watch items).

- The one-scan-per-transaction unique constraint contract is preserved (no
  app-level dedup added).
- The proxy snapshot semantics are preserved; removal is a plain delete
  (no `disabled` flag), matching v1.
- No P5 write path audits (v1 does zero audit calls in these files) — verify
  this remains true if any P5 file is touched again.

### Secondary Risks

- `tbl_payout_scans` (legacy variant 1) rows may no longer be meaningful in
  production — the first screen is wired but real data was never eyeballed.
- The dead `export_scanned_payouts_unpaid.php` link: v1 has no working payout
  export; P5 deliberately ships only the unpaid-verification export.

---

## Before Next Session

Continue **P6 — Scholars / GIP** using `docs/implementation/P6_SCHOLARS.md` as
the build contract.

Priority:

1. Read `docs/implementation/P6_SCHOLARS.md` (§2 v1 ground truth, §4 extension
   points).
2. Read the matching v1 files under `C:\xampp\htdocs\system` (read-only).
3. Confirm the exact v1 audit `action` strings.
4. Build the screens + feeds + tests; run `vendor\bin\pint` before finishing.
5. Append the P6 entry to `docs/IMPLEMENTATION_LOG.md` when delivered.

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
- P6 Scholars contract (`docs/implementation/P6_SCHOLARS.md`)

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
