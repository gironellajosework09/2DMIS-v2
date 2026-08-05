# v2 — Implementation Log

> **Phase:** Development (P0 → P8).
> This is the **running record of everything actually built** in the v2 Laravel
> project. The planning docs (`docs/README.md` index) describe *what* v2 must be;
> this log records *what has been done*, file by file, with verification and any
> deviations from the blueprint. **Append to this document on every update.**
>
> Guardrails still apply: `main_system` is byte-identical to production and is
> never wiped or altered non-additively; v1 code at `C:\xampp\htdocs\system` is
> read-only.

**Related:** `ENGINEERING_BLUEPRINT.md` (roadmap P0–P8, file matrix §8),
`ARCHITECTURE_DECISION.md` (ADR-001…010), `MIGRATION_PLANNING.md` (§6 gates).

---

## Phase status

| Phase | Deliverable | Status |
|---|---|---|
| P0 — Foundations | Bootstrap, baseline schema, assets, CI, storage | **Done** |
| Schema fixes | 6 additive migrations fixing v1 abnormalities | **Done** |
| P1 — Auth + RBAC | Username login, single-device, ACL, audit, shell | **Done** |
| P2 — Clients + households | Client CRUD, slide-over panel | In progress (next) |
| P3 — Transactions + reports | | Not started |
| P4 — Scanner engine | | Not started |
| P5 — Payout + unpaid | | Not started |
| P6 — Scholars / GIP | | Not started |
| P7 — Administration | | Not started |
| P8 — Hardening + cutover | | Not started |

---

## How to use this log

1. Every merged update adds one dated entry under **Changelog** describing the
   change, the files touched, and how it was verified.
2. Status-bearing docs stay in sync: `docs/README.md` (phase table),
   `ENGINEERING_BLUEPRINT.md` §8 (file matrix), `ARCHITECTURE_DECISION.md`
   (implementation notes), `MIGRATION_PLAN.md` §4 and `MIGRATION_PLANNING.md`
   §6 (gate status).
3. If implementation deviates from the blueprint, record it in the entry and in
   **Deviations from the blueprint** below — the blueprint itself is a plan and
   is not silently rewritten.

---

## Changelog

### 2026-08-05 — P0 Foundations + schema fixes + P1 Auth/RBAC (initial delivery)

Work delivered in this session (project created from a fresh Laravel 12
scaffold against the frozen `main_system` schema):

#### P0 — Foundations

- **Laravel 12 scaffold** on PHP 8.2.12 (XAMPP CLI; production targets PHP 8.3+),
  default infra migrations (users/password_reset_tokens/sessions/cache/jobs)
  committed as part of the baseline.
- **`.env`-based config** — DB credentials via `.env` (gitignored; `.env.example`
  committed). Session/cache/queue on `file`/`file`/`sync`.
- **Baseline schema** `database/schema/mysql-schema.sql` — generated with
  `php artisan schema:dump` (672 lines, 40 tables incl. Laravel infra).
  `php artisan migrate` auto-loads it on a DB with no recorded migrations.
- **Assets copied from v1** (read-only source): `seal_logo.png`, `sounds/*.mp3`
  → `public/`; `favicon.ico` at web root. Uploads wiring via `storage:link`
  (`storage/app/public/uploads/...`).
- **CI** `.github/workflows/ci.yml` — PHP 8.3 + MySQL 8.0 service; `migrate`
  loads the baseline into `main_system`; creates a dedicated `main_system_test`
  database; runs `pint --test` then `php artisan test`.
- **Fresh-DB proof** — built `main_system_fresh_test` from the dump via a plain
  `migrate` → 40 tables, all constraints present; dropped afterwards.

#### Schema fixes (6 additive migrations, applied to the local DB)

Data-preservation setup used locally (never against a real prod copy):
`migrate:install` → insert the deploy-only sentinel
`__legacy_v1_baseline_schema__` into `migrations` → `migrate` runs only the
additive migrations. The sentinel is a deploy-time marker only; it was removed
from the committed baseline dump.

| Migration | Change | Guardrail |
|---|---|---|
| `2026_08_05_000001_drop_redundant_indexes.php` | Drops duplicate indexes: `tbl_household.household_id_2`, `tbl_clients.idx_full_name_clients`, `tbl_transactions.{t_prg,t_cid,t_da,t_pd,t_dp}`, `tbl_payout_scans`/`_2` `{idx_transaction_id,ps_tid,ps_sb,ps_sa}`, `tbl_users.u_un` | `down()` restores |
| `2026_08_05_000002_add_primary_keys_to_legacy_tables.php` | Auto-increment PKs on `gender`, `tbl_absent`, `tbl_kababaihan`, `tbl_details`, `temp_details` (reuse existing `id`) | Warns + skips if existing ids dirty |
| `2026_08_05_000003_make_clients_email_nullable.php` | `tbl_clients.email` → VARCHAR(255) NULL | `down()` warns if NULLs exist |
| `2026_08_05_000004_add_unique_permission_constraints.php` | UNIQUE `(user_id,page_name)` on `tbl_permissions`, `(user_id,program_name)` on `tbl_program_permissions` | Skips + warns on duplicate groups |
| `2026_08_05_000005_unify_table_collations.php` | 5 tables from `utf8mb4_general_ci` → `utf8mb4_unicode_ci` (fixes v1 join breaks) | — |
| `2026_08_05_000006_add_payout_scan_foreign_keys.php` | FKs `fk_tbl_payout_scans2_transaction/user`, `fk_tbl_payout_scans_unpaid_transaction/user` | Skips if orphans exist |

Verification on the local `main_system` copy: counts intact before/after
(munis 23, brgys 471, clients 1, users 1), all 6 fixes confirmed, second
`migrate` → "Nothing to migrate.", `.env` restored to `DB_DATABASE=main_system`.

#### P1 — Auth + RBAC

v1 contract mapped and ported (see `ARCHITECTURE_DECISION.md` ADR-002/003).

| v1 file | v2 target | Notes |
|---|---|---|
| `login.php` / `logout.php` | `AuthController` + `LoginRequest` + routes `login`/`login.attempt`/`logout` | Username + bcrypt via Laravel auth; audits `LOGIN`/`LOGOUT` |
| `session.php` (token contract) | `EnsureSingleDevice` middleware (`single-device` alias) | Session `session_token` vs `tbl_users.session_token` via `hash_equals`; mismatch → logout + redirect `login?login_status=expired`; refreshes `last_activity`; skips multi-device-exempt users |
| `restriction.php` + username checks | `AccessControlService` + `AuthorizePage` (`page:<name>`) + `page`/`program` Gates | Single ACL; super-admin is a data row (`page_name='*'`), never a username or `user_id=1` |
| `logs.php::log_action()` | `AuditService::log()` → `tbl_audit_logs` | v1 field contract (`user_id/action/target_table/target_id/old_value/new_value/created_at`) |
| `check_session.php` | `session/status` JSON route (`session.status`) | `logged_out`/`another_device`/`ok`; polled every 2 s from the layout |
| `force_logout.php` | `session/force-logout` POST (page-gated) | Nulls `session_token` + audit `FORCE_LOGOUT` |
| `currently_logged_users.php` | `session/online` page (page-gated) | Server-rendered table (see deviations) |
| `fetch_online_users.php` | — | Deferred (see deviations) |
| `navbar.php` / `sidebar.php` | Blade partials | Role-driven menu via ACL; hidden for no permission |
| `index.php` | `DashboardController` → `dashboard` route | Auth + single-device required |

**Models** (all `$table='tbl_*'`, `$timestamps=false`): `User` (username
identifier via `getAuthIdentifierName()`, `session_token` hidden, relations),
`Permission`, `ProgramPermission`, `MultiDeviceExemption`.

**Seeding:** `AccessControlSeeder` (idempotent, via `DatabaseSeeder`) grants the
local `jordi` account full access with a `tbl_permissions` row
(`page_name='*'`, `can_access=1`). Production carries its existing v1
permission rows unchanged at cutover.

**Middleware wiring:** aliases `single-device` and `page` registered in
`bootstrap/app.php`; `AccessControlService` registered as a singleton;
Gates `page`/`program` defined in `AppServiceProvider`.

**Views:** `layouts/app.blade.php` (navbar/sidebar/content + 2 s session poll),
`partials/navbar.blade.php`, `partials/sidebar.blade.php`,
`auth/login.blade.php` (seal logo, `login_status` flash + validation errors),
`dashboard.blade.php`, `sessions/online.blade.php` (self-hidden force-logout).

**Tests** — 14 tests, 36 assertions, green, on a dedicated `main_system_test`
DB (never the local copy); `phpunit.xml` forces `DB_DATABASE=main_system_test`:
- `tests/Feature/AuthTest.php` (6): login page accessible; login by username;
  wrong password fails; dashboard requires login; logout clears session+token;
  second-device login invalidates first device.
- `tests/Feature/AccessControlTest.php` (7): super-admin gated access; page
  permission access; no-permission blocked (`denied`); super-admin
  single-device exempt; program gate allow/deny; super-admin bypasses program
  gate.
- `tests/Feature/ExampleTest.php` (1): `/` redirects to login (guest).

**Verification ledger (2026-08-05):**
- `php artisan test` → 14 passed (36 assertions). NOTE: RefreshDatabase loads
  the schema dump through the `mysql` client, so `C:\xampp\mysql\bin` must be on
  PATH (`$env:PATH = "C:\xampp\mysql\bin;" + $env:PATH`) or every
  RefreshDatabase test fails with `ProcessFailedException` — documented in
  `README.md` and `AGENTS.md` Gotchas.
- `vendor\bin\pint` (app, tests, database, routes, bootstrap, resources) → passed;
  `pint --test` → passed.
- `php artisan migrate` → "Nothing to migrate." (safe no-op on the local copy).
- Live smoke test: `/login` 200; guest `/` → 302 to `/login`.
- Data intact: 23 municipalities / 471 barangays / 1 client / 1 user.

---

## Deviations from the blueprint

| Blueprint (§8 or ADR) | Planned | Actually built | Reason |
|---|---|---|---|
| File #16 (`sidebar.css/js`) + ADR-006 superseded direction | Tailwind + Vite asset build | **Bootstrap 5 (CDN) + inline CSS** in the layout; no Vite/Tailwind build, no `npm install` | Zero Node toolchain on the machine; matches staff-familiar Bootstrap (the original ADR-006 decision). Revisit Tailwind if a build step is wanted later |
| File #12 (`fetch_online_users.php`) | DataTables JSON route | **Deferred** — `session/online` renders a server-rendered table | Not needed for P1 parity; add the JSON feed when DataTables is adopted (P3+) |
| File #10 (`force_logout.php`) | `AdminController@forceLogout` | `SessionController@forceLogout` | No admin controller exists yet; route/page gate `page:force_logout.php` enforces the v1 permission key |
| ADR-008 | Audit via framework events/observers | `AuditService` called explicitly from controllers | No model mutations in P1 to observe; observers planned once domain writes exist (P2) |

---

## File inventory (P0 + P1)

**Created:** `AGENTS.md`, `docs/*`, `public/seal_logo.png`, `public/favicon.ico`,
`public/sounds/*.mp3`, `database/schema/mysql-schema.sql`,
6× `database/migrations/2026_08_05_*.php`, `database/seeders/AccessControlSeeder.php`,
`app/Http/Controllers/{AuthController,DashboardController,SessionController}.php`,
`app/Http/Middleware/{EnsureSingleDevice,AuthorizePage}.php`,
`app/Http/Requests/LoginRequest.php`,
`app/Models/{Permission,ProgramPermission,MultiDeviceExemption}.php`,
`app/Services/{AccessControlService,AuditService}.php`,
`resources/views/{auth,layouts,partials,sessions}/*.blade.php`,
`resources/views/dashboard.blade.php`, `tests/Feature/{AuthTest,AccessControlTest}.php`,
`.github/workflows/ci.yml`.

**Modified:** `.env.example`, `README.md`, `app/Models/User.php`,
`app/Providers/AppServiceProvider.php`, `bootstrap/app.php`,
`bootstrap/providers.php`, `config/auth.php`, `database/factories/UserFactory.php`,
`database/seeders/DatabaseSeeder.php`, `phpunit.xml`, `routes/web.php`,
`tests/Feature/ExampleTest.php`.

**Explicitly not done (later phases):** DataTables JSON feeds; Tailwind/Vite
build; `verify_mobile.php` (P2); all client/household/transaction/scanner/
payout/scholar/admin modules; login throttling (ADR-007, P8 hardening);
`password_reset_tokens` framework flow (disabled — v1 has no email reset).

---

*End of current implementation log. Append new dated entries above this line.*
