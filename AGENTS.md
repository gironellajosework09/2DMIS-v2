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
- **Document every update.** Every change must be recorded in
  `docs/IMPLEMENTATION_LOG.md` (new dated changelog entry + file inventory +
  verification) and reflected in the status tables of `docs/README.md`,
  `docs/ENGINEERING_BLUEPRINT.md` §8, `docs/ARCHITECTURE_DECISION.md`
  (Implementation lines), `docs/MIGRATION_PLAN.md` §4, and
  `docs/MIGRATION_PLANNING.md` §6. No code commit ships without its doc update.

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
upload (file + camera, `PhotoService`), and the public student self-service
flow (update-photo → verify → photo-upload for scholar programs) — 59 tests
green. P3 delivered: `TransactionService` (17 programs, CRUD + audits,
patient-name resolution, TUPAD nulls), page-gated `all_transactions.php` list
+ server-side feed + filters + inline edit + client search + CSV exports
(UTF-8 BOM, standard/custom/custom2/gip), 12 TransactionTest — 40 tests green.
Current milestone: **P4 scanner engine** (study v1 paid/failed scan + all 17
programs incl. GIP/OTEA/OTCES/CEDSSG/TODA/MAIP/CEAP/TUPAD; build `ScanService`
+ program config + views + tests). Full roadmap:
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
