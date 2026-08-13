# 2DMIS v2 — Session Handoff

**Last Updated:** 2026-08-13

---

## Current Status

### Project State

- Planning: Complete
- Architecture: Complete
- Migration Planning: Complete
- Engineering Blueprint: Complete
- Implementation: In Progress

### Current Milestone

**P6 — Scholars / GIP** (Phase 3 in progress — scholar registry + relink + scholarship reports done 2026-08-12; GIP details + QR viewer + grantee updates done 2026-08-13; client picker for the standalone create form done 2026-08-13 — **P6 scope complete**) → next: P7 — Administration

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

- P6 Phase 3 step 1: scholar relink (`update_client_id.php` port)
- P6 Phase 3 step 3: scholarship reports — `ReportController` (screen + feed + BOM CSV export), `scholarship_reports` view, routes behind `page:scholarship_reports.php`, sidebar link, 7 tests (60 assertions)
- P6 Phase 3 step 5: GIP details (`save_gip.php` port) — `GipService` (v1-parity upsert + `ADD_GIP`/`UPDATE_GIP` audit via `AuditService`), `GipController@store`, `POST clients/{client}/gip` gated by `clients.php`, GIP accordion + modal on the client profile (`clients/_gip.blade.php`), `Client::gipInfo()` relation, 6 tests (20 assertions)
- P6 Phase 3 step 4: QR viewer (`view_qrcode.php` port) — public top-level `GET qr-viewer` (`QrController@show`, `qr/viewer.blade.php`) reusing the shared `grantee-search` endpoints; verify now returns `client.full_name`; QR encodes the persisted comma-form name (decision C) via external `api.qrserver.com` (parity, no package); 3 tests (11 assertions)
- P6 grantee updates (`save_grantee_update.php` / `disabled_update_grantee.php` / `update_logs.php` port, SCHOLAR_ANALYSIS §6 steps 3+4) — public `GET grantee-update` + `POST grantee-update/save` (`GranteeUpdateController` + `GranteeUpdateService`: v1-exact transaction — client update preserving name/location, latest scholar_info upsert, `tbl_update_logs` append with IP + exact action string); public `grantee/verify-mobile` + `grantee/barangays` aliases; gated `GET update-logs` (`page:update_logs.php`) with v1 name formatting + PHT conversion; `fetch_update_logs.php` NOT ported (dead in v1); sidebar Update Logs link; 10 tests (37 assertions)
- P6 step 9 (SCHOLAR_ANALYSIS §6): client picker for the standalone scholar create form — `GET scholars/clients-search` in the `page:scholars.php` group reusing `TransactionController@searchClients`; `scholars/_form.blade.php` now uses a search picker (hidden `client_id` + live results) shared by create (prefill via `?client_id=`) and edit (prefill from the scholar's client, fixing the empty select); 6 tests (13 assertions)
- Full suite green on `main_system_test` (126 → **132 tests, 651 → 664 assertions**)

**Next:**

- **P7 — Administration**: permissions (`manage_permissions.php` / `manage_program_permissions.php`), audit viewer, hardening.

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

**P6 — Scholars / GIP is complete** (SCHOLAR_ANALYSIS §6 fully delivered).

Next: **P7 — Administration** using `docs/ENGINEERING_BLUEPRINT.md` and the v1
files under `C:\xampp\htdocs\system` (read-only) as the build contract.

Priority:

1. Read the P7 contract/analysis (permissions, audit viewer, hardening) and the
   matching v1 files (`manage_permissions.php` / `manage_program_permissions.php`).
2. Confirm the exact v1 audit `action` strings before inventing new ones.
3. Build the screens + feeds + tests; run `vendor\bin\pint` before finishing.
4. Append the P7 entry to `docs/IMPLEMENTATION_LOG.md` when delivered.

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
