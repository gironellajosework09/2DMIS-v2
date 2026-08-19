# 2DMIS v2 — Session Handoff

**Last Updated:** 2026-08-16 (P12 action authorization + municipality scope implemented)

---

## Current Status

### Project State

- Planning: Complete
- Architecture: Complete
- Migration Planning: Complete
- Engineering Blueprint: Complete
- Implementation: **P0 → P7 + P12 (action/municipality authz) complete; P8 (Hardening + cutover) next**

### Current Milestone

**P8 — Hardening + cutover** (blueprint §1.12) — not yet started. Includes the
production admin-bootstrap runbook (grant a nominated existing user a
`tbl_permissions` row with `page_name = '*'` via reviewed cutover SQL), the
deferred P7 audit enhancements (server-side date-range filter, leaderboard
date-window, IP metadata) if the owner opts in, and the **P12 S2 cutover** —
flipping `enforcement` in `config/authorization.php` for the 5 pilot pages once
the owner approves (S2 §13).

---

## Completed Milestones

| Phase | Scope | Status |
|---|---|---|
| P0 | Laravel 12 foundation, env, baseline schema (additive 6-migration fixes), CI | Complete |
| P1 | Username auth on `tbl_users`, single-device `session_token`, ACL service + gates + `page:` middleware, audit logging | Complete |
| P2 | Clients registry, households, family members, duplicates, photos, student self-service, slide-over details panel | Complete |
| P3 | Transactions: 17-program `TransactionService`, CRUD, program-gated list/feed/filters/inline-edit, 4 CSV export modes | Complete |
| P4 | Config-driven scanner engine (14 keys / 8 modes), shared scan view, per-key routes + gates | Complete |
| P5 | Payout attendance (3 variants), unpaid verification admin + public self-service + search/verify/delete, BOM CSV | Complete |
| P6 | Scholars module: registry CRUD + feed + relink + client picker, GIP (with audit), grantee self-update + update-log viewer, scholarship reports + BOM CSV, QR viewer (decision C) | Complete |
| P7 | Administration: user creation (`register.php`/`add_user.php`), page/program permission management, multi-device exemptions, audit viewer + leaderboard, five `page:` route groups, sidebar links | Complete |
| P12 (approved contract) | Action authorization (`tbl_action_permissions`) + municipality scope (`tbl_user_municipalities`) on 5 pilot pages, admin screens under `manage_permissions.php`, all S2 `enforcement` off | Complete |

**Final P7 verification (2026-08-15):** full suite **158 tests / 769
assertions** green on `main_system_test` (incl. new `AdministrationTest` - 26
tests / 101 assertions); `vendor\bin\pint` clean on all changed files;
production `main_system` untouched (tests force `DB_DATABASE=main_system_test`).

**Final P12 verification (2026-08-16):** full suite **195 tests / 887
assertions** green on `main_system_test` (37 new tests in `ActionPermissionTest`,
`ScopeTest`, `AuthorizationAdminTest`, `AccessControlServiceTest`);
`vendor\bin\pint` clean; `tbl_action_permissions` + `tbl_user_municipalities`
created additively on local `main_system` (backup
`...\Temp\opencode\main_system_before_p12.sql`); committed baseline regenerated
sentinel-free; v1 untouched.

---

## Last Session Summary (P7 Administration)

The P7 Administration subsystem was built end-to-end per the owner-approved
contract (`docs/implementation/P7_ADMINISTRATION.md` + `ADMIN_ANALYSIS.md`).

**Owner decisions settled before building (via question prompt):** no
automatic/seeder bootstrap — production first-admin access is a reviewed
one-time cutover SQL grant of a `tbl_permissions` row with `page_name = '*'`
for a nominated existing user; the seven `MANAGE_*` audit strings approved
exactly; **no** `active` column (v1 create-only); audit enhancements C/D/E
(audit-on-permission-writes, subject-name resolution for the P7 tables,
exemption/`'*'` no-op silence) shipped, A/B/F (server date-range filter,
leaderboard date-window, IP metadata) deferred; no municipality/data-scope
authz, no action-level CRUD.

**Completed:**

- `UserController` (create-only, `MANAGE_USER_CREATE`), `AdminPermissionController`
  (page full-replace + `'*'` toggle, program full-replace, idempotent exemption
  toggle), `AuditController` (viewer + `{data,users,actions}` feed + leaderboard),
  four `FormRequest`s, five Blade views, five `page:` route groups, sidebar links.
- `tests/Feature/AdministrationTest.php` — 26 tests / 101 assertions (authz,
  all 7 audit actions, no-op silence, feeds, no-secret payloads).
- Full suite **158 tests / 769 assertions** green; pint clean.

**Deviations documented in the log:** `pages`/`programs` are `nullable|array`
(not the contract's `required`) so the v1 remove-all full-replace works; no-op
exemption toggle returns a message instead of an audit row (contract §11.5).

**Next:** P8 — Hardening + cutover (see below).

---

## Last Session Summary (P12 action authorization + municipality scope)

The owner-approved Pass 12 contract (`docs/ADMIN_ANALYSIS.md` §§22-23) was
implemented end-to-end. It layers the **action** and **municipality**
dimensions on P1's ACL behind a per-page `enforcement` flag that is **off** for
all five pilot pages (`config/authorization.php`) — pre-P12 behavior is
byte-identical until the flag flips (S2 §13 rollback = flip it back).

**Completed:**

- Two additive migrations + regenerated sentinel-free baseline
  (`schema:dump`); local `main_system` backup before migration.
- `AccessControlService`: `canAccessAction` (uppercase-normalized, VIEW = page
  row, `'*'` bypass, fail-closed unknowns), `permittedActions`,
  `hasAllMunicipalities` (reserved `0` marker), `effectiveMunicipalityIds`,
  `canAccessRecord`, `applyMunicipalityScope` (Builder `whereIn`); page config
  read as literal array index (dot-notation would split `clients.php`).
- `action` middleware alias + `Gate::define('action', ...)`; §11 route map (18
  `action:<page>,<action>` instances); `RecordMunicipality` data resolvers.
- Scope seams on feeds/searches + record-level checks on single-ID/write
  endpoints across the 5 pilot controllers.
- Two admin screens under `page:manage_permissions.php` (actions grid with
  composite `page:ACTION` checkboxes, VIEW excluded; scope screen with the ALL
  toggle + check-all) — full-replace saves, `MANAGE_ACTION_PERMISSIONS` /
  `MANAGE_SCOPE_ASSIGNMENTS` audits, no-op silence.
- 37 new tests (`ActionPermissionTest`, `ScopeTest`, `AuthorizationAdminTest`,
  `AccessControlServiceTest`); full suite **195 tests / 887 assertions** green;
  pint clean.

**Open items:** S2 cutover (flip `enforcement` per page, owner decision);
enforcement-aware UI hiding (uses `permittedActions`) if the owner wants it.

---

## Current Work — P8 Hardening + cutover

**Goal:** harden the v2 application and execute the cutover plan
(`docs/MIGRATION_PLAN.md`), keeping `main_system` byte-identical to production.

**Focus:**

- **Production admin bootstrap runbook** (P7 carry-over): a one-time cutover SQL
  grant of a `tbl_permissions` row with `page_name = '*'` (and the four P7 page
  keys if preferred) for a nominated existing user — no seeder, no username
  checks. Optionally also the NULL-user bootstrap audit-row item from
  `ADMIN_ANALYSIS.md` (note `tbl_audit_logs.user_id`/`target_id` are NOT NULL).
- **Deferred P7 audit enhancements** (if owner opts in): server-side date-range
  filter, leaderboard date-window, IP metadata.
- **Hardening pass:** coverage gaps, error handling, perf, cutover rehearsal.

Reference: `docs/MIGRATION_PLAN.md` / `docs/MIGRATION_PLANNING.md` →
`docs/ENGINEERING_BLUEPRINT.md` §1.12.

---

## Development Priorities (P8)

1. Confirm the P8 scope with the owner (hardening items, deferred P7 audit
   enhancements, cutover rehearsal) — do not decide silently.
2. Review P7 residuals: none open besides the deployment-time `'*'` grant.
3. Keep the full suite green on `main_system_test`; run `vendor\bin\pint`
   before finishing; append the P8 entry to `docs/IMPLEMENTATION_LOG.md`.

---

## Open Decisions

- Soft-deletes / client-merge: in scope or not.
- Additive indexes on existing tables (recommended: yes).
- ADR-001..010 flip from Proposed to Accepted.
- Git: this repo has no remote yet (docs live in a separate repo).
- **P8:** whether to ship the deferred P7 audit enhancements (server-side
  date-range filter, leaderboard date-window, IP metadata) and the exact
  hardening scope.
- **P12 S2 cutover:** when (and for which of the 5 pilot pages) to flip
  `enforcement` in `config/authorization.php`; who gets which action/scope rows
  first. Do not decide silently.

---

## Current Risks

- **P12 S2 rollout** — enforcement is off for all 5 pilot pages, so the action
  and municipality rows have **no effect yet** by design. Enabling a page
  without first granting its users action/scope rows would immediately deny
  those users (fail closed). Roll out per page: grant rows via the admin
  screens, then flip `enforcement`. Rollback = flip back.
- **P7 admin bootstrapping (carry-over, now P8-runbook)** — production
  `tbl_permissions` may lack rows for the four admin page keys, so no one can
  reach the P7 screens until a `'*'` (or those keys) is granted to a nominated
  user via reviewed cutover SQL. `tbl_audit_logs.user_id`/`target_id` are NOT
  NULL — the bootstrap audit row (if desired) must use a real user id.
- **Audit viewer scope** — v1 resolves display names only for
  `tbl_clients`/`tbl_transactions`/P7 subject tables; other tables show raw
  `target_id`; the feed has `LIMIT 10000` and only a client-side date filter.
  Don't over-promise parity.
- **Schema creep** — any hardening change touching the schema (e.g. deferred
  IP metadata, additive indexes) must go through the additive-migration +
  `schema:dump` baseline regen workflow (AGENTS.md), never destructive.
- **Payout (P5) watch items still stand** — no P5 write-path audits; unique
  scan constraint preserved; the `export_scanned_payouts_unpaid.php` dead link
  is deliberately not shipped.

---

## Before Next Session

1. Read `docs/ADMIN_ANALYSIS.md` (P12 contract, now with a **BUILD RECORD**)
   and `docs/IMPLEMENTATION_LOG.md` 2026-08-16 entry for the P12 build.
2. Confirm P8 scope with the user (hardening items, deferred P7 audit
   enhancements, cutover rehearsal) — do not decide silently.
3. Confirm the **P12 S2 cutover** decision (which pilot pages to flip, and the
   action/scope grants to set up first) — do not decide silently.
4. Execute the P8 runbook items and the production `'*'` admin bootstrap grant
   only with explicit owner sign-off.
5. Verify the full suite on `main_system_test`; confirm production `main_system`
   untouched; confirm no destructive schema operations.

Do not redesign behavior. Parity comes before optimization.

---

## Documentation Status

| Document | Status |
|---|---|
| `README.md` | Up to date (P7 + P12 complete; P8 next) |
| `ENGINEERING_BLUEPRINT.md` | P0–P7 rows done; P8 §1.12 pending build |
| `IMPLEMENTATION_LOG.md` | P0–P7 + P12 entry recorded 2026-08-16 |
| `implementation/P7_ADMINISTRATION.md` | Header **COMPLETE** (contract verified vs `ADMIN_ANALYSIS.md`; implemented) |
| `ADMIN_ANALYSIS.md` | Canonical P12 analysis; **BUILD RECORD** added 2026-08-16 |
| `MIGRATION_PLAN.md` / `MIGRATION_PLANNING.md` / `ARCHITECTURE_DECISION.md` | Unaffected by P12 so far |

---

## Reminder

Do not modify:

- Production database schema (`main_system`)
- Legacy v1 source code (`C:\xampp\htdocs\system`)
- Authentication contract (username, `session_token` single-device)
- Permission keys (`page_name` values identical to v1)
- Audit log format (`AuditService` is the single writer)

Database parity is mandatory.
