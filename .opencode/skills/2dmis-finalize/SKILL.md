---
name: 2dmis-finalize
description: Run whenever the user asks to "finalize", "finish", "wrap up", "ship", or otherwise completes an implementation and wants it verified and recorded. Load this skill before running the close-out ritual: code style, the test suite, database safety checks, and documentation updates. Use ONLY for close-out work, not for mid-task coding.
---

# 2DMIS v2 — Finalize Implementation

## Purpose

Use this skill whenever an implementation is complete and needs to be verified
and recorded — the user says "finalize", "finish", "wrap up", "ship", or asks
to commit a completed change. Run through every step below before declaring the
work done. Do not skip the documentation phase: the repository rules require
that no code commit ships without its documentation update. This skill is
reusable — it stays valid for every stage of the project lifecycle and must not
be rewritten per phase or milestone.

## 1. Verify Environment

- Check that the required services are running (MySQL, PHP, queue/worker, etc.).
- Ensure any binaries the tooling needs are reachable (e.g. the database client
  on PATH for the test suite or schema dump).
- Ignore harmless warnings only when they are known to be always applicable and
  are environment noise rather than failures.

## 2. Code Quality

- Run the project's code-style tool (Pint) from the project root.
- Fix anything it flags before moving on.
- Keep changes minimal: the diff should contain only what the change requires.
- Do not add comments unless the user asked for them.

## 3. Validation

- Run `php artisan test` — the full suite.
- Report the pass/fail counts.
- If a test fails, fix the code or the test and re-run until the suite is green.
- Do not assert a specific expected test count — the suite size changes over
  time as the project grows.

## 4. Database Safety

Only execute when the change touched the schema.

- Never run `migrate:fresh`, `db:wipe`, or drop/alter existing tables. Schema
  changes are additive only.
- Take a database backup before any schema work.
- If the baseline schema changed, regenerate it with `php artisan schema:dump`
  and remove any deploy-only marker rows before committing.
- Never drop or alter production tables.

## 5. Documentation Update

Update every affected documentation file according to AGENTS.md. AGENTS.md is the
source of truth for which files must be updated and how — do not hardcode a
fixed file list here.

Examples of files that may be affected (update only those the change actually
touches):

- implementation log
- migration planning
- engineering blueprint
- architecture decisions
- roadmap

If AGENTS.md itself changed, keep its status sections in sync.

## 6. Completion Report

Summarize:

- the implementation completed
- the verification performed
- the test results
- the documentation updated

Only create a git commit if the user explicitly requests it. Never commit
automatically, and never stage `.env` or secrets.
