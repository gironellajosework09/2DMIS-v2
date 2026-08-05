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

## Phase status

| Phase | Status |
|---|---|
| Planning & Analysis | **Complete** |
| Design | **Complete** |
| Development | **In progress (P4 — scanner engine)** |
| Testing | In progress (per-module gates; full suite in P8) |
| Rollout | Not started |

P0 foundations, the six additive schema-fix migrations, P1 Auth + RBAC, P2
clients/households, and P3 transactions are complete — see
`IMPLEMENTATION_LOG.md` for the full record.

## Guardrails

- **Data is untouchable.** All records in `main_system` must survive the
  upgrade. Any plan that drops, truncates, or rebuilds tables is rejected.
- **No changes to v1.** v1 keeps running and keeps accepting data until v2 is
  ready to take over.
- **Documentation only.** The planning documents in this folder imply no
  changes to source code, files, SQL, or the database. Implemented changes are
  recorded (not planned) in `IMPLEMENTATION_LOG.md`.
