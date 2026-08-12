# 2D MIS — v2 Planning & Analysis

> Version 2.0 upgrade of the municipal assistance MIS.
> Planning & Analysis documentation for the rewrite, which is implemented in the
> Laravel project at this repository root (`C:\xampp\htdocs\2dmis-v2`).
> No changes have been made to the v1 system, its database, or its data.
> **Implemented work is recorded in [IMPLEMENTATION_LOG.md](IMPLEMENTATION_LOG.md)** —
> the planning docs below describe intent; the log records what has been built.

## Documentation index

| Document | Contents |
|---|---|
| [VISION_AND_SCOPE.md](VISION_AND_SCOPE.md) | Why v2 exists, objectives, what is in / out of scope |
| [REQUIREMENTS_ANALYSIS.md](REQUIREMENTS_ANALYSIS.md) | Functional & non-functional requirements derived from v1 analysis |
| [GAP_ANALYSIS.md](GAP_ANALYSIS.md) | What v1 lacks vs what v2 must provide (mapped to v1 anti-patterns & recommendations) |
| [MIGRATION_PLAN.md](MIGRATION_PLAN.md) | Data-preservation strategy, target stack, phased delivery, and rollout |
| [ARCHITECTURE_DECISION.md](ARCHITECTURE_DECISION.md) | ADR collection: framework, auth, ACL, scanner engine, data layer, security, logging, deploy |
| [MODERNIZATION_PROPOSAL.md](MODERNIZATION_PROPOSAL.md) | Architecture review & modernization proposal (Laravel + Blade + Tailwind; evaluation, decisions, security, UI/UX panel, migration strategy/roadmap, risks) |
| [MIGRATION_PLANNING.md](MIGRATION_PLANNING.md) | Migration Planning phase: baseline workflow, backup/restore drill, reconciliation framework, P0–P8 gates, cutover runbook, rollback |
| [ENGINEERING_BLUEPRINT.md](ENGINEERING_BLUEPRINT.md) | Final engineering blueprint: legacy inventory, transformation matrix, DB→model mapping, technical strategy, module deliverables, dependency matrix, compatibility guarantees, file-migration checklist, readiness assessment |
| [IMPLEMENTATION_LOG.md](IMPLEMENTATION_LOG.md) | **Running record of what has been built** (P0 → P8), file inventory, v1→v2 mapping, verification results, deviations from the blueprint. Append on every update |

### Developer documentation

| Document | Contents |
|---|---|
| [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md) | **Primary maintainer reference**: overview, directory map, workflow, coding/doc standards, adding features, debugging, testing, roadmap, non-negotiables |
| [implementation/P1_AUTHENTICATION.md](implementation/P1_AUTHENTICATION.md) | P1 delivered — username auth on `tbl_users`, single-device `session_token`, ACL service + Gates, audit logging, session status/routes |
| [implementation/P2_CLIENTS.md](implementation/P2_CLIENTS.md) | P2 delivered — client registry, households, family members, photos, duplicates, student self-service, client details slide-over |
| [implementation/P3_TRANSACTIONS.md](implementation/P3_TRANSACTIONS.md) | P3 delivered — 17-program `TransactionService`, program-gated list/feed/filters/inline-edit/search, 4 CSV export modes |
| [implementation/P4_SCANNER_ENGINE.md](implementation/P4_SCANNER_ENGINE.md) | P4 delivered — config-driven 14-key/8-mode scanner engine, one shared scan view, per-key routes + gates, 14 tests |
| [implementation/P5_PAYOUT.md](implementation/P5_PAYOUT.md) | P5 delivered — payout attendance lists (3 variants, one shared view/feeds), unpaid verification admin + public self-service + search/verify/delete, BOM CSV export; 15 tests |
| [implementation/P6_SCHOLARS.md](implementation/P6_SCHOLARS.md) | P6 in progress — scholars module: enrollment, GIP, grantee updates, reports, QR viewer (blueprint §1.12). Audit approved + scholar registry v1-parity cleanup done 2026-08-07 |
| [implementation/P7_ADMINISTRATION.md](implementation/P7_ADMINISTRATION.md) | P7 planned — permission management, user CRUD, audit viewer + leaderboard (blueprint §1.11); `manage_php.php` excluded |

## Phase status

| Phase | Status |
|---|---|
| Planning & Analysis | **Complete** |
| Design | **Complete** |
| Development | **In progress (P6 — scholars / GIP)** |
| Testing | In progress (per-module gates; full suite in P8) |
| Rollout | Not started |

P0 foundations, the six additive schema-fix migrations, P1 Auth + RBAC, P2
clients/households, P3 transactions, P4 the scanner engine, and P5 payouts +
unpaid verification are complete — see `IMPLEMENTATION_LOG.md` for the full
record.

## Guardrails

- **Data is untouchable.** All records in `main_system` must survive the
  upgrade. Any plan that drops, truncates, or rebuilds tables is rejected.
- **No changes to v1.** v1 keeps running and keeps accepting data until v2 is
  ready to take over.
- **Documentation only.** The planning documents in this folder imply no
  changes to source code, files, SQL, or the database. Implemented changes are
  recorded (not planned) in `IMPLEMENTATION_LOG.md`.
