# P7 — Administration Subsystem (Planned)

> **Status:** **Not yet implemented.** P7 is the administration subsystem —
> user + permission management, multi-device exemptions, and the audit
> log / leaderboard viewers (Blueprint §1.11). Unlike P1–P4, P7's *data* and
> *enforcement* already exist: P1 built the ACL service and the permission
> model that P7 screens will manage, and `AuditService` writes every row the
> audit viewer will display. P7 is therefore mostly **management UI over
> existing machinery** — and the blueprint explicitly excludes one v1 file
> (`manage_php.php`) from the rewrite.
>
> This is a **hybrid** document: §2 is v1 ground truth, §3 is what v2 already
> provides, §4 onward is the build contract and extension points.

---

## 1. Purpose

P7 lets admins manage who can do what (page permissions, program permissions,
multi-device exemptions), create/disable users, and inspect the audit trail
with an activity leaderboard — all through the **one ACL service** built in P1,
with **no username checks and no `user_id == 1`**.

---

## 2. Legacy v1 behavior (ground truth)

Blueprint §1.11 lists the major files:

| File | Role |
|---|---|
| `manage_permissions.php` | page-level permission rows (`tbl_permissions`) |
| `manage_program_permissions.php` | program-level permission rows (`tbl_program_permissions`) |
| `manage_multi_device_exemptions.php` | single-device exemption rows (`tbl_multi_device_exemptions`) |
| `audit_logs.php` + `fetch_logs.php` | audit log viewer (DataTables feed) |
| `fetch_leaderboard.php` | "who did the most actions" leaderboard |
| `register.php`, `add_user.php` | user creation |
| `manage_php.php` | **excluded from v2** (see §5) |

Facts relevant to P7:

- v1 permission screens edited the same tables P1 now reads: rows in
  `tbl_permissions` (`user_id`, `page_name`, `can_access`) and
  `tbl_program_permissions` (`user_id`, `program_name`), plus
  `tbl_multi_device_exemptions` (`user_id`).
- Super-admin in v1 was implicit (`user_id == 1` / hard-coded names). **P7 must
  not replicate that**: super-admin is the `page_name = '*'` row.
- The audit viewer read `tbl_audit_logs` (the same rows `AuditService` writes).
- The leaderboard aggregated audit rows per user (an action-count query).
- `manage_php.php` was a v1 file-manager-like admin page; the blueprint lists it
  as dead/undesirable and **does not migrate it** (A10 cleanup).

---

## 3. What already exists in v2

P1 (see `docs/implementation/P1_AUTHENTICATION.md`) delivered the entire
enforcement side these screens manage:

- `AccessControlService` — `isSuperAdmin`, `canAccessPage`, `canAccessProgram`,
  `isSingleDeviceExempt`, `isMultiDeviceExempt`, `permittedPages`,
  `permittedPrograms`, singleton with per-request caches.
- Models `Permission`, `ProgramPermission`, `MultiDeviceExemption` (fillable =
  exactly the legacy columns).
- `AuthorizePage` middleware + `page:`/`program` Gates — the consumers of the
  rows P7 will edit.
- `AuditService` — the single writer to `tbl_audit_logs` (LOGIN/LOGOUT/
  FORCE_LOGOUT today; every phase's actions).
- `tbl_users` model with username auth; `User::permissions()`,
  `programPermissions()`, `multiDeviceExemptions()` relations.
- `AccessControlSeeder` — the **local-only** `jordi` `'*'` row (never run in
  production).

There is **no user CRUD yet** (registration/add-user were not part of P1), and
there is **no admin UI** — those are the P7 deliverables.

---

## 4. Extension points (the P7 build contract)

Port the v1 files into (Blueprint §2 rows):

- **`AdminPermissionController`** (one controller for all three permission
  screens):
  - `manage_permissions` → list users + their `tbl_permissions` rows; grant/
    revoke `can_access` per `page_name`; grant/remove the `'*'` super-admin row.
    **Every write goes through `AccessControlService`-compatible mutations** —
    the screens only change the same rows the service reads, never the service's
    semantics.
  - `manage_program_permissions` → grant/remove `tbl_program_permissions` rows;
    the program list must match `TransactionService::PROGRAMS` (or read the
    `tbl_transactions.program` enum) so admins cannot create orphan program keys.
  - `manage_multi_device_exemptions` → add/remove `tbl_multi_device_exemptions`
    rows.
  - Each mutation audited via `AuditService` with `MANAGE_*`-style actions
    (choose names deliberately; keep them stable for the audit viewer).
- **`UserController`** — `register.php`/`add_user.php` ports: create users in
  `tbl_users` (`username` unique, bcrypt password via the `hashed` cast),
  disable/enable semantics (v1 has no `active` column — confirm what v1 used;
  if nothing, propose an additive column through the additive-migration path).
  Never allow setting `session_token` here (that is the login/force-logout
  concern).
- **`AuditController`** — `audit_logs.php` + `fetch_logs.php`:
  - Feed over `tbl_audit_logs` joined to `tbl_users` for actor names, with
    filters on action / table / user / date range (the schema already indexes
    `(target_table, created_at)` and `user_id`).
  - Read-only by design. The `new_value`/`old_value` JSON convention set by
    `AuditService` must be the render contract.
  - Leaderboard (`fetch_leaderboard.php`) — aggregate action counts per user,
    optional date window, ordered desc.
- Gate all P7 routes with `page:` middleware using the v1 page keys
  (`manage_permissions.php`, `manage_program_permissions.php`,
  `manage_multi_device_exemptions.php`, `audit_logs.php`) — the carried-over
  permission rows already gate them. **Do not** hardcode "admin" checks; the
  `'*'` row is what makes a user an admin.

---

## 5. Deliberately NOT in scope

- `manage_php.php` — not migrated (blueprint A10 / dead-file exclusion). Do not
  recreate a file-manager page in v2.
- Framework `users`/`password_reset_tokens` flows — v2 uses `tbl_users`
  (username) and has no self-service reset yet (§ future improvements in the
  P1 doc).
- Changing how authorization is decided — P7 only manages the rows, it never
  re-implements access checks.

---

## 6. DB tables involved

| Table | P7 role |
|---|---|
| `tbl_users` | user list/creation (username + password only; `session_token`/`last_activity` are runtime state) |
| `tbl_permissions` | page grants incl. the `'*'` super-admin row |
| `tbl_program_permissions` | program grants |
| `tbl_multi_device_exemptions` | single-device exemptions |
| `tbl_audit_logs` | audit viewer + leaderboard |

---

## 7. Security & validation expectations

- Every P7 screen behind its `page:` gate; every mutation server-side validated
  (`user_id` exists; `page_name` non-blank; `program_name` in the catalog;
  `can_access` boolean) via `FormRequest`.
- Never expose `password` or `session_token` in any user list response (they are
  `$hidden` on `User` — keep it that way).
- Granting `'*'` is the super-admin switch; it should be an explicit,
  confirmed action (a confirm UI + audit row), because it bypasses all page
  gates.
- Audit viewer must not allow filtering by `user_id` value (actor names only) —
  no id guessing; and it must not render raw HTML from `new_value`/`old_value`
  (JSON-escape or pretty-print as text).
- User creation enforces unique `username`; the `hashed` cast hashes on save
  automatically.

---

## 8. Common mistakes to avoid

1. Hardcoding `user_id == 1` or a username to identify "the admin" on these
   screens — the `'*'` permission row is the only admin marker.
2. Editing `tbl_permissions` in SQL directly instead of through the screens
   (rows are cached per request — direct edits appear only on next request, and
   bypass audit).
3. Letting an admin grant a program name that is not in the transaction enum —
   orphan rows are invisible to `programsForUser`.
4. Rendering audit `old_value`/`new_value` as HTML — they are JSON strings.
5. Re-importing `manage_php.php` — explicitly excluded.
6. Adding an `active` column via a destructive migration — any schema change is
   additive and reviewed (AGENTS.md rule), with the baseline regenerated.

---

## 9. Never-change list

- Never reintroduce username/id-based admin checks.
- Never write `tbl_audit_logs` outside `AuditService`.
- Never relax the `page_name = '*'` super-admin contract.
- Never expose `password`/`session_token` in views or API responses.
- Never modify the ACL decision logic from P7 screens (they manage data only).

---

## 10. Verification / acceptance gates

- Permission management round-trips: grant/revoke a page and a program for a
  user; confirm the user's next page request is gated correctly (including the
  feed/export gating in P3).
- Multi-device exemption add/remove changes `isSingleDeviceExempt` behavior.
- Audit viewer + filters + leaderboard match `tbl_audit_logs` contents.
- User creation succeeds and the new username can log in; duplicates rejected.
- Full suite green on `main_system_test`.

---

## 11. Blueprint / ADR references

- `docs/ENGINEERING_BLUEPRINT.md` §1.11 (Administration subsystem), §2 rows for
  `manage_permissions.php`/`manage_program_permissions.php`/
  `manage_multi_device_exemptions.php`, `audit_logs.php`/`fetch_logs.php`/
  `fetch_leaderboard.php`, `register.php`/`add_user.php`, and the
  `manage_php.php` **exclusion**.
- `docs/ARCHITECTURE_DECISION.md` — ADR-003 (single ACL service, which P7
  manages), ADR-009 (audit contract the viewer renders).
- `docs/IMPLEMENTATION_LOG.md` — append the P7 entry when delivered.
- `docs/REQUIREMENTS_ANALYSIS.md` — admin/audit FRs.
