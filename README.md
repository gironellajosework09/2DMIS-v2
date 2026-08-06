# 2DMIS v2 — Laravel rewrite of the municipal assistance MIS

Laravel 12 rewrite of the legacy plain-PHP municipal assistance system
("2D MIS", Ilocos Sur). The v1 codebase lives read-only at
`C:\xampp\htdocs\system` (~115 PHP files, no framework).

The production MySQL database (`main_system`) must remain byte-identical to
v1 — this project only adds application code around it. **Never** run
`migrate:fresh` or `db:wipe`; all schema changes are additive and reviewed.

Full roadmap: `docs/ENGINEERING_BLUEPRINT.md` (mirror in
`C:\xampp\htdocs\system\doc\v2`).

**Every change made to this project is documented** in
`docs/IMPLEMENTATION_LOG.md` — a running record of what was built, file
inventory, verification results, and deviations from the blueprint. Append to
it on every update, and keep the status tables in `docs/README.md`,
`ENGINEERING_BLUEPRINT.md` §8, `ARCHITECTURE_DECISION.md`, `MIGRATION_PLAN.md`
§4, and `MIGRATION_PLANNING.md` §6 in sync.

## Stack

- Laravel 12.x on PHP 8.2.12 (XAMPP CLI); production targets PHP 8.3+
- MySQL `127.0.0.1:3306`, database `main_system`, user `root`, empty password (local only)
- Session/cache/queue on `file`/`file`/`sync`
- XAMPP MySQL must be running (see "MySQL not running" below)

## Local setup

1. XAMPP Apache + MySQL running (`http://localhost` works).
2. Copy `.env.example` to `.env` and confirm the DB block
   (`main_system` / `root` / empty password).
3. Build the schema — pick the path that matches the DB you are on:

   **Fresh / empty database** (CI, new staging): `migrate` auto-loads
   `database/schema/mysql-schema.sql` (fully fixed schema, no legacy rows) and
   records all migrations itself:
   ```pwsh
   $env:PATH = "C:\xampp\mysql\bin;" + $env:PATH
   php artisan migrate
   php artisan db:seed
   ```

   **Database that must keep its rows** (local `main_system` copy, production
   restore): Laravel loads the schema dump — which starts with
   `DROP TABLE IF EXISTS` for every table — whenever *no migrations have been
   recorded yet*, so a plain `migrate` wipes all rows. Mark a baseline record
   first so the dump is skipped and the additive migrations run on top:
   ```pwsh
   php artisan migrate:install
   & "C:\xampp\mysql\bin\mysql.exe" -u root main_system -e "INSERT INTO migrations (migration, batch) VALUES ('__legacy_v1_baseline_schema__', 1);"
   php artisan migrate
   ```
   Back up before any schema work:
   `& "C:\xampp\mysql\bin\mysqldump.exe" -u root main_system > backup.sql`.
4. Link uploads storage:
   ```pwsh
   php artisan storage:link
   ```

## Schema fixes (additive, applied by migrations)

The six `2026_08_05_*` migrations fix v1 schema abnormalities without touching
legacy rows; on a data-bearing DB they run only after the baseline record above
exists. Summary:

| Migration | Change |
|---|---|
| `drop_redundant_indexes` | Removes duplicate indexes (e.g. `tbl_household.household_id_2`, `tbl_transactions.t_*`, `tbl_payout_scans.ps_*`, `tbl_users.u_un`) |
| `add_primary_keys_to_legacy_tables` | Gives `gender`, `tbl_absent`, `tbl_kababaihan`, `tbl_details`, `temp_details` auto-increment PKs |
| `make_clients_email_nullable` | Relaxes `tbl_clients.email` to allow NULL |
| `add_unique_permission_constraints` | UNIQUE on `tbl_permissions(user_id,page_name)` and `tbl_program_permissions(user_id,program_name)` |
| `unify_table_collations` | Converts the 5 `utf8mb4_general_ci` tables to `utf8mb4_unicode_ci` |
| `add_payout_scan_foreign_keys` | FKs from `tbl_payout_scans2`/`tbl_payout_scans_unpaid` to `tbl_transactions`/`tbl_users` |

The four conditional ones (`add_primary_keys_to_legacy_tables`,
`add_unique_permission_constraints`, `add_payout_scan_foreign_keys`,
`make_clients_email_nullable`'s revert) skip with a warning — never corrupt —
if the data isn't consistent (duplicates/orphans), so they can be applied to a
copy of production safely.

## Preview / run

- Dev server: `php artisan serve` → http://127.0.0.1:8000
- XAMPP Apache: http://localhost/2dmis-v2/public

## Assets

Static (tracked in git):

| Source (v1, read-only) | Target (v2) |
|---|---|
| `system\seal_logo.png` | `public\seal_logo.png` |
| `system\sounds\*.mp3` | `public\sounds\` |

Uploads (untracked, user data; copied into local storage):

| Source (v1, read-only) | Target (v2) |
|---|---|
| `system\uploads\client_photos\` | `storage\app\public\uploads\client_photos\` |
| `system\uploads\profile_photos\` | `storage\app\public\uploads\profile_photos\` |

Served at `/storage/uploads/...` via `public\storage` (symlink). Re-run
`php artisan storage:link` after a fresh clone; the photos themselves are not
in git.

## Commands

- `php artisan serve` — dev server at http://127.0.0.1:8000
- `php artisan test` — PHPUnit suite
- `vendor\bin\pint` — code style (run before finishing changes)
- `php artisan schema:dump` — regenerate baseline (needs `C:\xampp\mysql\bin` on PATH for mysqldump)

## Gotchas

- `php artisan schema:dump` fails with "mysqldump is not recognized" unless
  `C:\xampp\mysql\bin` is on PATH for the process. It also prints a harmless
  `mysqldump: unknown variable 'column-statistics=0'` warning under MariaDB.
- `php artisan test` needs `C:\xampp\mysql\bin` on PATH too: RefreshDatabase
  reloads `database/schema/mysql-schema.sql` through the `mysql` client and
  otherwise fails with `ProcessFailedException`. Prefix commands with
  `$env:PATH = "C:\xampp\mysql\bin;" + $env:PATH` (also handles migrate/db:seed
  when mysqldump/mysql aren't on PATH).
- MySQL not running? Start it:
  ```pwsh
  Start-Process "C:\xampp\mysql\bin\mysqld.exe" -ArgumentList "--defaults-file=C:\xampp\mysql\bin\my.ini"
  ```
- PHP prints a harmless "Module openssl is already loaded" warning on every
  run (duplicate line in `C:\xampp\php\php.ini`).

## Current milestone

**P2 — Clients + households** and **P3 — Transactions** are done (see
`docs/ENGINEERING_BLUEPRINT.md` §5 for the phase deliverables and
`docs/IMPLEMENTATION_LOG.md` for the record). Next up is **P4 — Scanner
engine**.

Done so far:
- Planning + P0 bootstrap (assets, storage, CI, baseline diff verification).
- Six additive schema-fix migrations (see above) applied to the local DB with
  sample data intact.
- **P1 — Auth + RBAC**: username login on `tbl_users`, single-device
  `session_token` middleware (ADR-002), one ACL service + page/program Gates
  (ADR-003), permission seeding, login/logout/force-logout, session-status and
  online-users routes, Blade layout/sidebar, audit writes to `tbl_audit_logs`.
  All checks go through the ACL service — no hard-coded usernames or
  `user_id = 1`. `db:seed` grants the local `jordi` account full access via a
  `tbl_permissions` row (`page_name = '*'`), not a magic id.
- **P2 — Client registry**: `ClientService` (single `full_name`/`match_name`/
  age/category derivation — v1 A6 fix), page-gated `clients.php` list with
  server-side DataTables feed + municipality/barangay filters, add/edit forms
  with geography cascade, aff-org assignment, audit on create/update.
  21 tests green (7 new ClientTest).
- **P2 — Households + family members + profile**: `HouseholdService`
  (`VIG-00001` codegen, audit, detach-on-delete), households index/create/
  show with server-side feed + search, `FamilyMemberService` (relationships,
  inverse mapping, SIBLING fan-out), client profile page (`clients.show`)
  replacing the slide-over panel, `verify_mobile.php` port. 28 tests green.
- **P3 — Transactions**: `TransactionService` (17 programs, TYPES, STATUSES,
  CRUD + audits, patient-name resolution, TUPAD nulls), page-gated
  `all_transactions.php` list with server-side DataTables + program/status/
  geography/date filters, beneficiary picker (self/custom/existing), inline
  row edit (v1-compatible normalize + date parse), CSV exports with UTF-8 BOM
  (standard/custom/custom2/gip). 40 tests green (12 new TransactionTest).
- **P2 completion — delete + duplicates + photos + student**: `ClientService::destroy`
  (transaction-guard, family-link cleanup, `DELETE_CLIENT` audit) + page-gated
  `ClientPolicy`; duplicate detection (`DuplicateService` — v1's name+
  municipality group key) with server-side feed, filters, and batch delete
  (`duplicates/index`); client photo upload via file or camera capture
  (`PhotoService`, JPEG magic check); public student self-service flow
  (search → birthdate/mobile verify → photo upload) for scholar programs;
  client details open in a **right-side slide-over panel** on row click
  (responsive Bootstrap Offcanvas; full page kept as a deep link).
  60 tests green (20 new).
