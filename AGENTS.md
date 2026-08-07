# 2DMIS v2 — Laravel rewrite of the municipal assistance MIS

## What this is
V2 rewrite of a legacy plain-PHP municipal assistance system ("2D MIS", Ilocos Sur).
The v1 codebase lives read-only at `C:\xampp\htdocs\system` (~115 PHP files, no framework).
This project is the Laravel replacement. The production MySQL database (`main_system`)
must remain byte-identical to v1 — this project only adds application code around it.

## Non-negotiable rules
- The local MySQL `main_system` is a **copy of the production database**. Never run
  `migrate:fresh`, `db:wipe`, or drop/alter existing tables. All schema changes are
  **additive** and must be reviewed.
- `php artisan migrate` auto-loads `database/schema/mysql-schema.sql`, which starts
  with `DROP TABLE IF EXISTS` for every table — it **wipes all rows** — whenever
  **no migrations are recorded yet** in the `migrations` table (the table existing
  alone is NOT enough; `migrate:install` leaves it empty). Only run it against a
  **fresh/empty database**. To preserve rows, mark a baseline first:
  `php artisan migrate:install`, then insert
  `INSERT INTO migrations (migration, batch) VALUES ('__legacy_v1_baseline_schema__', 1);`
  so the dump is skipped and only the additive migrations run. `mysqldump` before
  any schema work: `& "C:\xampp\mysql\bin\mysqldump.exe" -u root main_system > backup.sql`.
- Baseline schema lives in `database/schema/mysql-schema.sql` (generated with
  `php artisan schema:dump`). Regenerate it whenever the schema changes, and remove
  the `__legacy_v1_baseline_schema__` row from the dump before committing it (it is
  a deploy-time-only marker, not a migration).
- The six `2026_08_05_*` migrations fix v1 schema abnormalities additively
  (see README table). The conditional ones skip + warn — never corrupt — on
  inconsistent data.
- Never put secrets in code or docs. `.env` is gitignored — keep it that way.
- Auth uses the **`tbl_users`** table with a **username** identifier (no email).
  Enforce **single-device login** via the `session_token` column.
  Never hardcode `user_id = 1` or check usernames inline — route all access checks
  through the single ACL service.
- Never modify files under `C:\xampp\htdocs\system` except for read-only analysis.

- **Document every completed change.** Every completed change must be
  recorded in `docs/IMPLEMENTATION_LOG.md` (dated changelog entry, affected
  files, verification, and test results).

- Update other documentation only when affected by the change:

  - `docs/README.md` — project overview, feature list, or milestone status.
  - `docs/ENGINEERING_BLUEPRINT.md` — implementation progress or engineering status.
  - `docs/ARCHITECTURE_DECISION.md` — only when architectural decisions or ADRs change.
  - `docs/MIGRATION_PLAN.md` — when migration milestones or roadmap progress change.
  - `docs/MIGRATION_PLANNING.md` — when migration strategy, risks, or planning status change.

- Do not modify documentation that is unaffected simply to keep timestamps synchronized.

- No completed change is considered finished until all affected documentation has been updated.

## Stack & environment
- Laravel 12.x on PHP 8.2.12 (XAMPP CLI). Production targets PHP 8.3+.
- MySQL `127.0.0.1:3306`, database `main_system`, user `root`, empty password (local only).
- Session/cache/queue are on `file`/`file`/`sync` for now; the Laravel infra tables
  (users/password_reset_tokens/sessions/cache/jobs) are created by the framework
  migrations and are part of the committed baseline.
- XAMPP MySQL must be running. If it isn't:
  `Start-Process "C:\xampp\mysql\bin\mysqld.exe" -ArgumentList "--defaults-file=C:\xampp\mysql\bin\my.ini"`

## Commands
- `php artisan serve` — dev server at http://127.0.0.1:8000
- `php artisan test` — PHPUnit suite (needs `C:\xampp\mysql\bin` on PATH — see Gotchas)
- `vendor\bin\pint` — code style (run before finishing changes)
- `php artisan schema:dump` — regenerate baseline (needs `C:\xampp\mysql\bin` on PATH for mysqldump)

## Phase & next steps
Planning + P0 bootstrap + P1 auth + P2 clients/households + P3 transactions
are done. P1 delivered: username provider on `tbl_users`, `EnsureSingleDevice`
middleware (`session_token` contract, ADR-002), single `AccessControlService` +
`AuthorizePage` middleware + `page`/`program` Gates (ADR-003), permission
seeding, login/logout/force-logout, session-status and online-users routes,
audit writes to `tbl_audit_logs`. There is no `user_id = 1` or inline username
check anywhere — super-admin is a `tbl_permissions` row with `page_name = '*'`.
Tests run against a dedicated `main_system_test` database (phpunit.xml), never
the local copy.
P2 delivered: client registry (index + add/edit + server-side list + geography
cascade, page-gated `clients.php`, 7 ClientTest), households (codegen, CRUD,
feed, search, page-gated `household.php`, CSRF-safe delete), family members
(unique pair, inverse mapping, SIBLING fan-out), client profile page
(`clients.show` — replaces the slide-over panel), `verify_mobile.php`,
client delete (`ClientService::destroy` + page-gated `ClientPolicy`,
transaction-guard + family cleanup + audit), duplicate detection
(`DuplicateService` + page-gated `duplicates.*` feed/delete), client photo
upload (file + camera, `PhotoService`), the public student self-service
flow (update-photo → verify → photo-upload for scholar programs), and the
client-details **right-side slide-over panel** (row click / View button →
responsive Bootstrap Offcanvas loading the shared `clients/_details` partial;
full page kept as deep link) — 60 tests green. P3 delivered: `TransactionService` (17 programs, CRUD + audits,
patient-name resolution, TUPAD nulls), page-gated `all_transactions.php` list
+ server-side feed + filters + inline edit + client search + CSV exports
(UTF-8 BOM, standard/custom/custom2/gip), 12 TransactionTest — 40 tests green.
Current milestone: **P5 payout + unpaid screens** (payout attendance, seats,
unpaid verification — blueprint §1.9/§1.10; v1 `scanned_payouts*.php`,
`unpaid_verifications.php`). P4 delivered the scanner engine: `config/scanner.php`
(14 keys) drives `ScanService` (8 modes, no key branching), a thin
`ScannerController`, one shared `scanners/scan.blade.php` view, literal per-key
routes with `page:scanner_*.php` gates (`->defaults('key', …)`), ACL-gated
sidebar links, and 14 scanner tests — 74 tests green. Full roadmap:
`docs/ENGINEERING_BLUEPRINT.md` (also in `C:\xampp\htdocs\system\doc\v2`).

## Open decisions (do not silently decide)
- Framework confirmation: Laravel vs CI4 fallback (Laravel is the default).
- Soft-deletes / client-merge: in scope or not.
- Additive indexes on existing tables (recommended: yes).
- ADR-001..010 flip from Proposed to Accepted.
- Git: this repo has no remote yet (docs live in a separate repo).

## Gotchas
- `php artisan test` and `php artisan schema:dump` need `C:\xampp\mysql\bin` on
  PATH. Without it, tests fail with `ProcessFailedException` (RefreshDatabase
  reloads `database/schema/mysql-schema.sql` through the `mysql` client) and
  schema:dump fails with "mysqldump is not recognized". mysqldump also prints a
  harmless `unknown variable 'column-statistics=0'` warning under MariaDB.
- The local `main_system` `migrations` table holds the deploy-only
  `__legacy_v1_baseline_schema__` sentinel (plus the 9 real migrations), so
  subsequent `php artisan migrate` runs there are safe and do NOT reload the dump.
- PHP prints a harmless "Module openssl is already loaded" warning on every run
  (duplicate line in `C:\xampp\php\php.ini`).
