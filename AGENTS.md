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

## Development Workflow

- For normal implementation tasks, follow `.opencode/skills/SKILL-EFFICIENT.md`.
- For finalization tasks, follow `.opencode/skills/SKILL-FINALIZE.md` (workflow
  prompt comes from the user; the skill file is currently empty).
- Current project state is tracked in `docs/SESSION_HANDOFF.md` (see below).

## Current Development Status

The current implementation status is maintained in:

`docs/SESSION_HANDOFF.md`

`SESSION_HANDOFF.md` is the authoritative source for:

- Current implementation phase
- Completed milestones
- Current milestone
- Development priorities
- Open decisions
- Current risks
- Next-session handoff

Detailed implementation contracts and phase documentation are located in:

`docs/implementation/`

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


# Playwright Usage Rules

Playwright is the project's E2E testing framework and must be allowed to test the application across all configured browsers and mobile devices.

## Execution Rules

* Do not disable or remove configured Playwright browser projects to reduce AI token usage.
* Do not run the entire Playwright suite unless explicitly requested or required for final regression verification.
* When developing or debugging a specific feature, run only the relevant test file.
* When possible, run the specific browser/project relevant to the current issue.
* Run all configured browser projects when performing cross-browser verification.
* Full-suite testing across all configured projects is reserved for explicit regression testing.

## Failure Analysis Rules

When a Playwright test fails:

1. Read the test failure output first.
2. Inspect the failing test.
3. Inspect only the minimum application files necessary to diagnose the failure.
4. Do not scan the entire project.
5. Do not inspect unrelated modules.
6. Do not automatically open screenshots, traces, videos, or HTML reports unless necessary.
7. Do not generate additional debugging artifacts unless necessary.
8. Fix only the actual cause of the failure.
9. Rerun only the affected test.
10. Perform broader regression testing only after the targeted test passes.

## Application Preservation

Playwright must adapt to the existing 2DMIS v2 application.

Never modify application functionality, backend behavior, database structure, routes, authentication, business rules, or existing behavior merely to make a Playwright test pass.

Tests must verify the application rather than redefine how the application should work.

## Token Efficiency

Token efficiency must be achieved by limiting unnecessary AI analysis, not by reducing Playwright's browser coverage.

Do not perform broad project analysis for a localized Playwright issue.

Do not repeatedly rerun successful tests without a reason.

Do not summarize large Playwright reports when a concise pass/fail result is sufficient.

