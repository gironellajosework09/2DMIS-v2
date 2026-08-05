---
name: 2dmis-finalize
description: Run whenever the user asks to "finalize", "finish", "wrap up", "ship", "commit", or otherwise completes a change in the 2DMIS-v2 Laravel project. Load this skill before running the verification/close-out ritual: pint, the PHPUnit suite (with C:\xampp\mysql\bin on PATH), and the mandatory six-document update. Use ONLY for close-out work in this repo, not for mid-task coding.
---

# 2DMIS v2 — Finalize / Close-out Ritual

Trigger whenever a change is complete and the user wants it verified and
recorded. Run through every step below; do not skip the documentation phase —
AGENTS.md requires that no code commit ships without its doc update.

## 1. Verify the environment first

- XAMPP MySQL must be running. If not:
  `Start-Process "C:\xampp\mysql\bin\mysqld.exe" -ArgumentList "--defaults-file=C:\xampp\mysql\bin\my.ini"`
- `php artisan test` and `php artisan schema:dump` need `C:\xampp\mysql\bin`
  on PATH. In PowerShell prepend it for the command:
  `$env:PATH = "C:\xampp\mysql\bin;$env:PATH"` — without it tests fail with
  `ProcessFailedException` and schema:dump says "mysqldump is not recognized".
- Ignore the harmless "Module openssl is already loaded" PHP warning.

## 2. Code style

Run `vendor\bin\pint` from the repo root and fix anything it flags. No
comments unless the user asked for them. Keep diffs to the change only.

## 3. Run the test suite

- `php artisan test` — full PHPUnit suite (tests run against `main_system_test`,
  never the local `main_system` copy, so real data is safe).
- Confirm the expected suite size for the current phase (P1: 6+6; P2: ~59;
  P3: ~40). Report pass/fail counts.
- If a test fails, fix the code or the test; re-run until green.

## 4. Schema safety (only when the change touched the schema)

- Never `migrate:fresh`, `db:wipe`, or drop/alter existing tables. Only
  additive migrations.
- Before any schema work: `& "C:\xampp\mysql\bin\mysqldump.exe" -u root main_system > backup.sql`
- If the baseline changed: `php artisan schema:dump`, then remove the
  `__legacy_v1_baseline_schema__` row from `database/schema/mysql-schema.sql`
  before committing.

## 5. Documentation (mandatory, six files)

1. `docs/IMPLEMENTATION_LOG.md` — new dated changelog entry + file inventory +
   verification results.
2. `docs/README.md` — update the status tables.
3. `docs/ENGINEERING_BLUEPRINT.md` — §8 status table.
4. `docs/ARCHITECTURE_DECISION.md` — Implementation lines (and flip ADRs from
   Proposed to Accepted only when the user approves).
5. `docs/MIGRATION_PLAN.md` — §4 status table.
6. `docs/MIGRATION_PLANNING.md` — §6 status table.

If a change also touched `AGENTS.md`, keep its phase/status section in sync.

## 6. Report and commit

- Summarize: what changed, test results, docs updated.
- Only commit if the user explicitly asked. Commit style is short and matches
  the repo history (e.g. "implementation log", "P3 transactions"). Stage only
  intended files; never commit `.env` or secrets.
