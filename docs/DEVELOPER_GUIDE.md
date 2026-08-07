# 2D MIS v2 — Developer Guide

> The primary maintainer reference for working in this repository. Read this
> before touching code. Phase-level detail lives in
> `docs/implementation/P1…P7_*.md`; this guide explains the *how* of the whole
> codebase and the *why* behind its rules.

---

## 1. Overview

### 1.1 What this project is

2D MIS v2 is a **Laravel 12 rewrite** of a legacy plain-PHP municipal
assistance system ("2D MIS", Ilocos Sur). The v1 codebase lives **read-only** at
`C:\xampp\htdocs\system` (~115 PHP files, no framework). v2 adds application
code **around** the existing production MySQL database — it does not replace the
database.

### 1.2 Why Laravel

Laravel gives the project a real auth stack, migrations, middleware, routing,
testing, and a service container without requiring v1-style page-by-page
boilerplate. The framework choice was confirmed in the planning phase
(ADR-001; CI4 was the fallback). All decisions are recorded in
`docs/ARCHITECTURE_DECISION.md` (ADRs 001–010).

### 1.3 The frozen database (the most important rule)

The local MySQL `main_system` is a **copy of the production database** and must
remain **byte-identical** to v1 for the same data. Consequences:

- **Never** run `migrate:fresh`, `db:wipe`, or drop/alter existing tables.
- **All schema changes are additive** and must be reviewed.
- `php artisan migrate` auto-loads `database/schema/mysql-schema.sql`, which
  starts with `DROP TABLE IF EXISTS` for **every** table. That dump is only safe
  against a **fresh/empty** database and only when the `migrations` table has no
  recorded rows. On an existing DB, mark a baseline first (see §3.4) so the
  dump is skipped.
- **Back up before any schema work:**
  `& "C:\xampp\mysql\bin\mysqldump.exe" -u root main_system > backup.sql`.
- Regenerate the baseline with `php artisan schema:dump` whenever the schema
  changes, and strip the `__legacy_v1_baseline_schema__` sentinel row before
  committing.

### 1.4 Migration philosophy

- v1 schema abnormalities are fixed **additively** by the six
  `2026_08_05_*` migrations (see the README table in `docs/README.md` /
  `docs/IMPLEMENTATION_LOG.md`). The conditional ones **skip + warn**, never
  corrupt, on inconsistent data.
- Every committed schema change must come with a regenerated baseline.

### 1.5 Parity-first

Every feature is ported **file-for-file** from v1 with behavior preserved —
including quirks (TUPAD's remarks divergence, the CEAP/CEDSSG `payout_date`
column drift, name-matching collation semantics). Where v2 deliberately deviates
(super-admin as data, centralized derived fields, transaction-guarded deletes),
the deviation is **documented, audited, and tested**, never silent. Each phase
doc in `docs/implementation/` lists "Deviations v2 deliberately makes".

### 1.6 v1 vs v2 (orientation)

| | v1 | v2 |
|---|---|---|
| Stack | Plain PHP pages (~115 files) | Laravel 12, Eloquent, Blade, PHPUnit |
| DB | `main_system` | same `main_system` (frozen) |
| Auth | session + `user_id == 1` / username checks | `tbl_users` + `session_token` + ACL service |
| ACL | `restriction.php` + scattered checks | `AccessControlService` + `page:` middleware + Gates |
| Super-admin | implicit | `tbl_permissions.page_name = '*'` row |
| Audit | `logs.php::log_action()` | `AuditService` (same `tbl_audit_logs` contract) |
| Scanners | 14 pages + 15 handlers | 1 engine driven by `config/scanner.php` |

---

## 2. Directory structure

```
app/
├─ Http/
│  ├─ Controllers/      # thin HTTP layer; logic lives in services
│  ├─ Middleware/       # EnsureSingleDevice (single-device), AuthorizePage (page:)
│  └─ Requests/         # FormRequest validation (ClientRequest, LoginRequest, HouseholdStoreRequest)
├─ Models/              # Eloquent models, all $table = 'tbl_*', $timestamps = false
├─ Policies/            # ClientPolicy (delete → clients.php)
├─ Providers/           # AppServiceProvider: ACL singleton + page/program Gates + policy binding
└─ Services/            # THE business logic: AccessControl, Audit, Client, Household,
                        #   FamilyMember, Duplicate, Photo, Transaction, Scan
bootstrap/app.php       # app config: middleware aliases, routes, exceptions
config/
├─ auth.php             # provider → App\Models\User (username identifier)
├─ database.php, session.php, cache.php, queue.php  # file/file/sync for now
└─ scanner.php          # P4 scanner engine config (14 keys, 8 modes)
database/
├─ schema/mysql-schema.sql  # baseline dump (frozen DB ground truth)
├─ migrations/          # framework migrations + six additive 2026_08_05_* fixes
└─ seeders/AccessControlSeeder.php  # LOCAL-only jordi '*' row (never in production)
docs/
├─ implementation/      # THIS phase documentation set (P1…P7 + this guide)
├─ README.md, IMPLEMENTATION_LOG.md, ENGINEERING_BLUEPRINT.md, ARCHITECTURE_DECISION.md,
│  MIGRATION_PLAN*.md, SCANNER_ANALYSIS.md, SCANNER_CONFIGURATION_MATRIX.md, …
public/
├─ sounds/*.mp3         # scanner success/error audio
└─ uploads/client_photos/  # symlinked from storage
resources/views/
├─ layouts/app.blade.php    # Bootstrap 5.3.2 CDN, 220px sidebar shell
├─ partials/{sidebar,navbar}.blade.php
├─ auth/, clients/, households/, family_members/, duplicates/, transactions/,
│  sessions/, students/, scanners/scan.blade.php
routes/web.php          # all HTTP routes (auth group + page-gated groups + scanner loop)
tests/                  # PHPUnit; run against main_system_test (phpunit.xml)
```

**Convention:** controllers stay thin and return redirects/views/JSON; domain
rules live in services; models are data maps; permissions go through
`AccessControlService`. New code must follow this shape.

---

## 3. Workflow (day-to-day)

### 3.1 Boot the environment

- XAMPP MySQL must run:
  `Start-Process "C:\xampp\mysql\bin\mysqld.exe" -ArgumentList "--defaults-file=C:\xampp\mysql\bin\my.ini"`
- Dev server: `php artisan serve` → http://127.0.0.1:8000
- **PATH gotcha:** `php artisan test` and `php artisan schema:dump` need
  `C:\xampp\mysql\bin` on `PATH`. Prepend it in the shell, e.g.
  `$env:Path = 'C:\xampp\mysql\bin;' + $env:Path`.
- The harmless `Module openssl is already loaded` PHP warning on every run is a
  duplicate ini line — ignore it.

### 3.2 Develop a change

1. Read the relevant phase doc in `docs/implementation/` (they document WHY and
   the never-change lists).
2. Check `docs/IMPLEMENTATION_LOG.md` for prior related entries.
3. Follow parity-first: read the v1 file at `C:\xampp\htdocs\system` (read-only)
   before porting; preserve behavior unless a deviation is documented.
4. Write the service → controller → view/route → tests, in that dependency order.
5. Route new pages through the existing middleware groups:
   - `['auth', 'single-device']` for the app shell,
   - `page:<v1_page_key>` for ACL-gated groups (the v1 page name from
     `tbl_permissions.page_name`).
6. Add tests to the matching feature test; never touch `main_system` — tests
   run against `main_system_test`.

### 3.3 Verify (mandatory before finishing)

```
vendor\bin\pint                # code style
php artisan test               # full suite (needs mysql bin on PATH)
```

Also run `php artisan test --filter=<YourTest>` during development.

### 3.4 Schema work (only when truly needed, and always additive)

1. `mysqldump` the local copy first.
2. Add an **additive** migration (no `migrate:fresh`, no `db:wipe`).
3. On an existing DB with no recorded migrations, first mark a baseline:
   `php artisan migrate:install` then
   `INSERT INTO migrations (migration, batch) VALUES ('__legacy_v1_baseline_schema__', 1);`
4. `php artisan migrate`.
5. Regenerate the baseline: `php artisan schema:dump`; **remove** the
   `__legacy_v1_baseline_schema__` row from the dump before committing.
6. Update the README migration table + IMPLEMENTATION_LOG.

### 3.5 Close-out (run the close-out skill)

When a change is complete, load the `2dmis-finalize` skill. It runs the
verification ritual (pint + suite) and the **mandatory six-document update**
(see §6.2).

---

## 4. Coding standards

- **Style:** run `vendor\bin\pint` before finishing. Match the surrounding
  file's conventions (typed readonly constructor properties, docblocks on
  non-obvious logic, no comments unless they explain a v1 quirk or a WHY).
- **Services own the logic.** Controllers validate + delegate. No business
  logic in Blade; Blade only renders `$config`/models.
- **Single writers:** derived fields (`ClientService::attributes()`), audits
  (`AuditService`), scanner saves (`ScanService`), transactions
  (`TransactionService`). New writes go through the existing writer.
- **Auth never hardcodes.** No `user_id == 1`, no username checks, no inline
  permission queries — use `app(AccessControlService::class)`,
  `Gate::allows('page', …)`, or the `page:` middleware.
- **Models are maps:** `$table = 'tbl_*'`, `$timestamps = false`, fillable =
  legacy columns, casts for ints/decimals/enums only.
- **Validation via FormRequest** where a form is involved; inline `validate()`
  only for small JSON endpoints (mirror existing controllers).
- **All SQL parameter-bound.** Order columns come from fixed maps. Feed output
  is escaped (`htmlspecialchars` / Blade `e()`).
- **Audit every write.** Use the established `action` naming and JSON
  `old_value`/`new_value`. Never invent new actions casually (P7's viewer will
  render them).

---

## 5. Documentation standards

### 5.1 The documentation set

| Document | Purpose |
|---|---|
| `docs/README.md` | Index + phase status; update its index when docs change |
| `docs/IMPLEMENTATION_LOG.md` | **Running record of what was built** — append on every completed change |
| `docs/ENGINEERING_BLUEPRINT.md` | Legacy inventory, matrix, §8 file-migration statuses |
| `docs/ARCHITECTURE_DECISION.md` | ADRs — update only when a decision changes |
| `docs/MIGRATION_PLAN.md` / `docs/MIGRATION_PLANNING.md` | Milestones/gates — update on roadmap changes |
| `docs/implementation/*.md` | **Primary developer reference** per phase (this set) |
| `AGENTS.md` | Operating rules for the agent + milestone paragraph |

### 5.2 Documentation standards (as a working rule)

- **No completed change is finished until all affected docs are updated.**
- Update docs **only when affected** — never to keep timestamps in sync.
- The `2dmis-finalize` skill enforces the **six-document update**:
  1. `IMPLEMENTATION_LOG.md` — dated changelog entry (what/why, affected files,
     verification, test results) + phase status table.
  2. `README.md` — phase status + index.
  3. `ENGINEERING_BLUEPRINT.md` — §8 file-migration matrix statuses.
  4. `MIGRATION_PLAN.md` — phase table.
  5. `MIGRATION_PLANNING.md` — P0–P8 gates.
  6. `ARCHITECTURE_DECISION.md` — per-ADR "Implementation" lines when relevant.
  Plus the `AGENTS.md` milestone paragraph.
- Phase docs (`docs/implementation/P1…P7`) carry a "Deviations v2 makes",
  "Never-change list", and "Common mistakes" section — keep those honest as the
  code evolves.

---

## 6. Adding a feature (walk-through)

Say you must add a page gated by a new v1 page key:

1. Confirm the v1 page file's behavior; note quirks.
2. **Service** — add the domain logic (or extend an existing service; there is
   usually one per domain).
3. **Controller** — thin methods; inject the service via constructor.
4. **Routes** — add to the appropriate `page:` group in `routes/web.php`
   (follow the P3 transaction-group or P4 scanner-loop pattern; for scanner
   keys, add a `config/scanner.php` entry and the route loop generates
   everything).
5. **Views** — reuse `layouts/app.blade.php`; add sidebar links through the
   ACL-guarded pattern in `partials/sidebar.blade.php`.
6. **Permission** — the `tbl_permissions.page_name` row already exists in the
   carried-over data if the v1 page key is reused; do NOT hardcode who can see
   it.
7. **Audit** — write through `AuditService` with a deliberate action name.
8. **Tests** — feature test asserting behavior + the page gate (mirror
   `AuthTest`/`ScannerTest` helpers: `RefreshDatabase` + `logInAs(User)` which
   sets a `session_token`, plus `Permission` rows).
9. Run pint + the filtered test + the full suite.
10. **Close out** — load the `2dmis-finalize` skill and do the six-doc update.

---

## 7. Debugging

- **Logs:** `storage/logs/laravel.log`. The framework exception handler is
  minimal (`bootstrap/app.php` `withExceptions` is empty) — Laravel's defaults
  apply.
- **401/redirects on page loads:** check the `page:` middleware + the user's
  `tbl_permissions` rows. Denied → redirect to dashboard with
  `login_status=denied`.
- **"Expired" / logged out after login:** single-device token mismatch —
  confirm `session_token` was written to both the session and `tbl_users` (see
  the P1 doc §5.1 and §15 mistakes).
- **Scanner lookup/save failing:** confirm routes were generated from config
  (literal URLs + `defaults('key', …)` — the P4 §13 fixes); check the
  `config/scanner.php` entry (mode, programs, page key) and the `@json($scannerJs)`
  one-liner in the view.
- **Test DB weirdness:** tests use `main_system_test` (`phpunit.xml`). If a
  test fails on schema reload, ensure `C:\xampp\mysql\bin` is on `PATH`.
- **Frozen-DB errors** (e.g. "table already exists", constraint violations):
  you are likely hitting the baseline dump path — do not "fix" the schema; use
  the additive-migration flow (§3.4).

---

## 8. Testing

- **Framework:** PHPUnit (`php artisan test`). DB is `main_system_test`,
  refreshed via `RefreshDatabase` (which reloads `database/schema/mysql-schema.sql`
  through the `mysql` client — hence the PATH requirement).
- **Helpers/conventions** (see `tests/Feature/*Test.php`):
  - `logInAs(User)` — creates a user, sets a `session_token`, logs in.
  - `Permission`/`ProgramPermission` rows grant page/program access for tests.
  - `RefreshDatabase` on the suite base.
- **Coverage today:** Auth (login/logout/single-device/session status/force
  logout/ACL), Clients (7 tests), Households, Duplicates, Photos, Transactions
  (12 tests), Students, Scanners (14 tests), Payout/unpaid (15 tests),
  AccessControl — the full suite was
  **89 tests / 491 assertions green** at P5 close-out.
- **Never** let a test touch the local `main_system` copy.

---

## 9. Future development (roadmap)

- **P5** — ✅ payout attendance list screens + unpaid verification
  (`docs/implementation/P5_PAYOUT.md`) — delivered 2026-08-07.
- **P6** — scholars module (enrollment, GIP, grantee updates, reports, QR
  viewer) (`docs/implementation/P6_SCHOLARS.md`).
- **P7** — administration (permission screens, user CRUD, audit viewer +
  leaderboard) (`docs/implementation/P7_ADMINISTRATION.md`).
- **Open decisions** (do not decide silently; AGENTS.md lists them): Laravel vs
  CI4 (default Laravel), soft-deletes/client-merge scope, additive indexes,
  ADR-001…010 Proposed→Accepted flips, git remote.
- **Hardening candidates** recorded in the phase docs: login throttling,
  password reset (needs ADR), photo thumbnails, offline scanner buffering.

---

## 10. The non-negotiables (cheat sheet)

1. `main_system` is frozen — additive schema only, backup first, baseline
   regenerated and sentinel-stripped.
2. No secrets in code; `.env` stays gitignored.
3. Auth = `tbl_users` username provider; single-device via `session_token`.
4. No `user_id == 1`, no username checks — route everything through the ACL
   service.
5. Never modify files under `C:\xampp\htdocs\system` (read-only analysis only).
6. Document every completed change (IMPLEMENTATION_LOG + affected docs).
7. Run pint + the suite before finishing; tests only against `main_system_test`.
