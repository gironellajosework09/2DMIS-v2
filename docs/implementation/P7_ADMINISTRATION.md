# P7 — Administration Subsystem (Build Contract)

> **STATUS: Implementation contract — NOT YET IMPLEMENTED.**
>
> This document is the authoritative, consolidated build contract for the P7
> (Administration) subsystem. It consolidates the authorization research in
> `docs/ADMIN_ANALYSIS.md` Passes 1–6 (the verified v1 ground truth and every
> authorization/audit decision) into one buildable contract. **Do not re-derive
> the research** — read `docs/ADMIN_ANALYSIS.md` Pass 1–6 for the detailed
> evidence; this document fixes the decisions and the exact strings the build
> must implement.
>
> **Contract date:** 2026-08-15 (Pass 7 consolidation). **Passes 1–6 dates:**
> 2026-08-13 (Pass 1) and 2026-08-15 (Passes 2–6).
>
> Nothing in this document has been implemented. The future implementation
> pass builds exactly what is specified here and nothing else.

---

## 1. Purpose

P7 lets admins manage who can do what (page permissions, program permissions,
multi-device exemptions), create users, and inspect the audit trail with an
activity leaderboard — all through the **one ACL service** built in P1, with
**no username checks and no `user_id == 1`** (ADR-003). The `'*'` permission
row is the only super-admin marker.

P7 is mostly **management UI over existing machinery**: `AccessControlService`
and the `Permission`/`ProgramPermission`/`MultiDeviceExemption`/`User` models
already exist (P1), and `AuditService` is the sole writer of `tbl_audit_logs`.
The P7 deliverables are the screens, the routes, the server-side validation,
and the audit integration defined in this contract.

---

## 2. Module Scope

### 2.1 In scope (v1 port targets)

| # | Capability | v1 source file(s) | P7 component |
|---|---|---|---|
| 1 | User creation | `register.php`, `add_user.php` | `UserController` (one screen under the `register.php` key) |
| 2 | Page permissions | `manage_permissions.php` | `AdminPermissionController` |
| 3 | Program permissions | `manage_program_permissions.php` | `AdminPermissionController` |
| 4 | Multi-device exemptions | `manage_multi_device_exemptions.php` | `AdminPermissionController` |
| 5 | Audit viewer | `audit_logs.php`, `fetch_logs.php` | `AuditController` |
| 6 | Leaderboard | `fetch_leaderboard.php` | `AuditController` (read-only) |

The **leaderboard is a read-only P7 capability**: it aggregates existing
`tbl_audit_logs` rows; it never writes.

### 2.2 Explicitly NOT in scope

- `manage_php.php` — not migrated (blueprint A10 / dead-file exclusion). Do not
  recreate a file-manager page in v2.
- **Action-level CRUD authorization** — deferred (Open Decision #6).
- **Municipality / data-scope authorization** — deferred (Open Decision #6).
- **Combined fine-grained authorization** (action + municipality model,
  fine-grained schema) — deferred (Open Decision #6).
- Unrelated administrative modules (framework `users`/`password_reset_tokens`
  flows; password reset; self-service account management). v2 uses `tbl_users`
  with username auth and has no self-service reset.
- Changing how authorization is decided — P7 only manages the rows; it never
  re-implements access checks.

---

## 3. Final Page Key Contract

Canonical v2 page keys (established by `ADMIN_ANALYSIS.md` Pass 5 §2). All are
the **v1 page filenames** (ADR-003 "`page_name` values identical to v1"),
verified collision-free against `routes/web.php` and the codebase.

| Capability | Canonical page key | v1 source of the name |
|---|---|---|
| User creation | `register.php` | `register.php` (the grantable v1 catalog key; `add_user.php` folds into this screen) |
| Page permissions | `manage_permissions.php` | `manage_permissions.php` |
| Program permissions | `manage_program_permissions.php` | `manage_program_permissions.php` |
| Multi-device exemptions | `manage_multi_device_exemptions.php` | `manage_multi_device_exemptions.php` |
| Audit viewer | `audit_logs.php` | `audit_logs.php` |

**Gating contract (Pass 5 §3–§4):**

1. Every P7 page is gated with `page:<key>` (`AuthorizePage` middleware) inside
   the authenticated group (`auth`, `single-device`) — exactly the P1–P6 route
   convention (`routes/web.php:69`).
2. `'*'` satisfies every gate by construction (`canAccessPage` short-circuits on
   `isSuperAdmin`). No P7 page adds an extra `isSuperAdmin` requirement on top
   of its page key — the admin-ness of the screens emerges from the grant model
   (only a permission-admin can issue the rows).
3. The individual permission row satisfies the gate (`can_access = true`).
4. **No program permission is required** to reach any P7 screen — program
   permissions gate business modules (transactions, P3), not the admin screens.
5. **No extra username-based or user-ID-based admin gate exists.** No
   `isSuperAdmin`-gated username/id checks, no `in_array($user->id, [1,2])`, no
   hard-coded names. `AccessControlService` remains the canonical ACL and
   `AuthorizePage` the route/page gate mechanism (Pass 5 §4).
6. **Feeds under `audit_logs.php` do not create separate page keys**:
   `fetch_logs.php` and `fetch_leaderboard.php` nest inside the
   `page:audit_logs.php` route group (Pass 5 §2, §3), following the established
   pattern where every `/data` feed lives in its page's `page:` group.

**Why `'*'` is deliberately reachable for P7:** it is the entire purpose of the
`'*'` row (grant all page gates) and is uniform with every other page key;
without it P7 would need exactly the username/id checks ADR-003 forbids
(Pass 5 §4).

---

## 4. Route Contract

Route **page keys** and **controller/action targets** are fixed by Pass 5 and
the blueprint §2/§8; the exact **URIs** below are the proposed P7 structure
following the P1–P6 conventions (`kebab-case`, `page:` groups, POST `/data`
feeds). URI strings are marked **[implementation decision]** where Pass 1–6 did
not fix them — confirm them in the implementation pass rather than treating them
as binding.

### 4.1 Page/view routes

| Method | URI (proposed) | Controller@action | Page gate | Purpose |
|---|---|---|---|---|
| GET | `admin/users/create` **[impl]** | `UserController@create` | `page:register.php` | User creation form (register.php + add_user.php port) |
| GET | `admin/permissions` **[impl]** | `AdminPermissionController@pages` | `page:manage_permissions.php` | Page-permission editor (list users + rows) |
| GET | `admin/program-permissions` **[impl]** | `AdminPermissionController@programs` | `page:manage_program_permissions.php` | Program-permission editor |
| GET | `admin/exemptions` **[impl]** | `AdminPermissionController@exemptions` | `page:manage_multi_device_exemptions.php` | Multi-device exemption editor |
| GET | `admin/audit-logs` **[impl]** | `AuditController@index` | `page:audit_logs.php` | Audit viewer + leaderboard page |

### 4.2 Form submission routes

| Method | URI (proposed) | Controller@action | Page gate | Purpose |
|---|---|---|---|---|
| POST | `admin/users` **[impl]** | `UserController@store` | `page:register.php` | Create user; emits `MANAGE_USER_CREATE` |
| POST | `admin/permissions/{user}` **[impl]** | `AdminPermissionController@updatePages` | `page:manage_permissions.php` | Full-replace page permissions; emits `MANAGE_PAGE_PERMISSIONS` (+ `'*'` events) |
| POST | `admin/program-permissions/{user}` **[impl]** | `AdminPermissionController@updatePrograms` | `page:manage_program_permissions.php` | Full-replace program permissions; emits `MANAGE_PROGRAM_PERMISSIONS` |
| POST | `admin/exemptions/{user}` **[impl]** | `AdminPermissionController@toggleExemption` | `page:manage_multi_device_exemptions.php` | Idempotent exemption toggle; emits `MANAGE_EXEMPTION_GRANT`/`REVOKE` only on real change |

### 4.3 JSON/feed routes

| Method | URI (proposed) | Controller@action | Page gate | Purpose |
|---|---|---|---|---|
| POST | `admin/audit-logs/data` **[impl]** | `AuditController@data` | `page:audit_logs.php` | `fetch_logs.php` port (DataTables feed) |
| POST | `admin/audit-logs/leaderboard` **[impl]** | `AuditController@leaderboard` | `page:audit_logs.php` | `fetch_leaderboard.php` port (per-table aggregation) |

Notes:

- All routes sit in the `auth, single-device` group with a nested `page:<key>`
  group, mirroring `routes/web.php` (Pass 1 §2.6/§2.7).
- The v1 `GET ?table=` feed contract maps to the v2 POST `/data` convention
  (ADR-006 heritage; every P2–P6 feed is POST). The `table` whitelist parameter
  is preserved as the feed's input.
- Route **names** are separate from page **keys** (Pass 5 §2): e.g. the
  `register.php` key does not collide with Laravel's default `register` route
  name space (no framework `register` route exists in `web.php`).

---

## 5. Controller Contract

Follow the P2–P6 conventions: thin controllers, `FormRequest` validation,
`page:` gate enforcement at the route level, `AuditService` for writes. **No new
service is required** — the P7 writes are simple row replacements on the
existing models, and the audit orchestration (full-replace + `'*'` diff +
`AuditService`) is small enough for the controller or a thin inline helper; do
not introduce a new service class unless the implementer has a concrete
reuse/extensibility reason (justify in the implementation log if added).

### 5.1 `UserController` (gate `page:register.php`)

| Aspect | Contract |
|---|---|
| Input | `username`, `password`, `password_confirmation` (§6.1) |
| Validation | `UserCreateRequest` — server-side (§6.1) |
| Authorization | Route gate only; no extra check (Pass 5 §6) |
| Operation | `User::create(['username' => ..., 'password' => ...])` — `hashed` cast bcrypts; only these two fields inserted; new user starts with **zero** permissions (v1 parity) |
| Audit | `AuditService::log($actorId, 'MANAGE_USER_CREATE', 'tbl_users', $newUser->id, null, json_encode(['username' => $username]))` — inside the same flow as the insert (§12) |
| Response | Redirect back to the user-creation/admin screen with a success message (v1 was message-based; no redirect — v2 uses the established flash+redirect convention) |
| Error handling | Validation errors → back with errors (Laravel standard); duplicate username surfaces as a validation error |

### 5.2 `AdminPermissionController` (gates `page:manage_permissions.php`, `page:manage_program_permissions.php`, `page:manage_multi_device_exemptions.php`)

One controller for the three permission screens (blueprint §2/§8; ADR-003).

**`pages` / `updatePages`** (manage_permissions.php):
- Input: `user_id`, `pages` (array of page keys), `super_admin` (boolean,
  separate confirmed toggle).
- Validation: `PagePermissionRequest` (§6.2); target user exists; page values
  from the real catalog.
- Operation: full-replace `DELETE` then `INSERT` of `Permission` rows with
  `can_access = 1`; absence = deny (v1 parity). `'*'` handled separately
  (§9). Wrap the replace + audit in one `DB::transaction`.
- Audit: `MANAGE_PAGE_PERMISSIONS` always on a save; `MANAGE_SUPER_ADMIN_GRANT`
  or `MANAGE_SUPER_ADMIN_REVOKE` additionally when the `'*'` row flips (§12).
- Response: redirect back with success.
- Error: validation errors; nothing partial (transaction).

**`programs` / `updatePrograms`** (manage_program_permissions.php):
- Input: `user_id`, `programs` (array).
- Validation: `ProgramPermissionRequest` (§6.3); target user exists; every value
  in `TransactionService::PROGRAMS` (the verified 17 — Pass 4 §7).
- Operation: full-replace `DELETE` + `INSERT` of `ProgramPermission` rows.
- Audit: `MANAGE_PROGRAM_PERMISSIONS` with old/new program sets (§12).
- Response/error: same conventions as above.

**`exemptions` / `toggleExemption`** (manage_multi_device_exemptions.php):
- Input: `user_id`, `grant` (boolean).
- Validation: `ExemptionToggleRequest` (§6.4); target user exists and is **not**
  a `'*'` holder (data-driven exclusion, Pass 5 §3 / Pass 6 §8.4).
- Operation: idempotent — `grant=true` and no row → insert; `grant=false` and a
  row → delete; otherwise no-op. A `'*'` holder toggle is a **no-op** (they are
  already exempt via `isSingleDeviceExempt → isSuperAdmin`).
- Audit: `MANAGE_EXEMPTION_GRANT` (insert) / `MANAGE_EXEMPTION_REVOKE`
  (delete) **only** on a real state change; no-op → **no** audit row (§12).
- Response/error: same conventions.

### 5.3 `AuditController` (gate `page:audit_logs.php`)

| Aspect | Contract |
|---|---|
| `index` | Renders the viewer + leaderboard sections (v1 `audit_logs.php`) |
| `data` | DataTables feed over `tbl_audit_logs` (v1 `fetch_logs.php`): table whitelist, username join, display-name resolution, timezone/format, `LIMIT 10000` (§13) |
| `leaderboard` | Per-table per-user action-count aggregation (v1 `fetch_leaderboard.php`, §14) |
| Authorization | Route gate only; feeds nested in the group — closes v1's session-only/public feed gaps |
| Reads | Never audited (no `VIEW_*` action, §12) |
| Render safety | `old_value`/`new_value` are JSON strings — render JSON-escaped / pretty-printed as text, never raw HTML (Pass 1 §2.8; current contract §7) |

---

## 6. Form Request / Validation Contract

Distinction (Pass 5 §13 / current §7): **v1 validation** was minimal (empty
checks, confirm match, unique username, catalog from hard-coded arrays). **v2
server-side security validation** is `FormRequest`-enforced + CSRF. UI-only
validation must **not** be treated as sufficient server-side protection. No
validation rule beyond the v1 contract or the established v2 architecture is
invented here.

New FormRequests (follow existing patterns in `app/Http/Requests/`):
`UserCreateRequest`, `PagePermissionRequest`, `ProgramPermissionRequest`,
`ExemptionToggleRequest`.

### 6.1 User creation (`UserCreateRequest`)

| Field | v1 behavior | v2 server-side rule |
|---|---|---|
| `username` | required non-empty; unique (`SELECT id FROM tbl_users WHERE username = ?` → "Username already taken.") | `required, string, max:100, unique:tbl_users,username` (unique enforced server-side — v1 checked it; keep) |
| `password` | required non-empty; bcrypt via `password_hash` | `required, string` (+ confirmed). **v1 parity:** no server-side strength rule (the ≥8-char meter in v1 `add_user.php` was UI-only). A minimum-length/complexity rule is a **v2 hardening option**, not parity — do not add unless approved. |
| `password_confirmation` | `password === confirm_password` → "Passwords do not match." | `required, same:password` |

On failure: no user row and **no audit row** (no event occurred; Pass 6 §5.4).

### 6.2 Page permissions (`PagePermissionRequest`)

| Field | v1 behavior | v2 server-side rule |
|---|---|---|
| `user_id` | (list-based UI) | `required, integer, exists:tbl_users,id` |
| `pages` | full-replace of checked `page_name` values (hard-coded catalog) | `required, array`; each value in the **real page catalog** = distinct `tbl_permissions.page_name` values + the five P7 keys (`'*'` excluded from this list — handled by the separate `super_admin` toggle) (Pass 5 §7) |
| `super_admin` | (v1 had no `'*'`) | `required, boolean` — the explicit confirmed `'*'` toggle; grants/removes the `page_name = '*'` row (§9) |

### 6.3 Program permissions (`ProgramPermissionRequest`)

| Field | v1 behavior | v2 server-side rule |
|---|---|---|
| `user_id` | (id-gated UI) | `required, integer, exists:tbl_users,id` |
| `programs` | full-replace of checked programs from the hard-coded 17 | `required, array`; each value `in:TransactionService::PROGRAMS` (the verified 17; no second catalog — Pass 4 §7, §10) |

### 6.4 Exemption toggle (`ExemptionToggleRequest`)

| Field | v1 behavior | v2 server-side rule |
|---|---|---|
| `user_id` | picker excluded `super_admin`/`jordi` | `required, integer, exists:tbl_users,id`; must **not** be a `'*'` holder (`isSuperAdmin` false) — data-driven exclusion replaces the hard-coded name list (Pass 5 §3) |
| `grant` | checkbox; DELETE then conditional INSERT | `required, boolean`; idempotent semantics in the controller (§5.2) |

### 6.5 Audit viewer

- `table` feed parameter **whitelisted** to the v1 set
  `['tbl_clients', 'tbl_transactions', 'tbl_cedssg']` (default `tbl_clients`)
  — Pass 1 §1.8; P7 keeps the whitelist. (Whether `tbl_audit_logs` is a valid
  viewer table is NOT established by v1 — do not silently add it; mark as an
  implementation decision if needed.)
- `user`/`action` filter inputs fed from the distinct users/actions **per
  table** (v1 contract), validated against those distinct sets.
- No new server-side filters are introduced (date range is optional/deferred —
  §17.A); the v1 **client-side** date filter is preserved in the viewer (§13).
- Search/sort parameters follow the existing DataTables contract used by every
  P2–P6 feed.

---

## 7. User Creation Contract

Consolidated (Pass 1 §1.2–1.3, Pass 5 §2/§6, Pass 6 §5):

1. **v1 creation behavior**: `register.php` (session/row-gated) and `add_user.php`
   (`super_admin` username-gated) both insert exactly `(username, password)`.
   No permissions, no exemptions, no status, no redirect.
2. **Duplicate username**: rejected server-side ("Username already taken.") —
   keep as a `unique:tbl_users,username` rule.
3. **Password hashing**: bcrypt via the `hashed` cast on `User` (P1); never
   store plaintext.
4. **Password confirmation**: `password_confirmation` must equal `password`.
5. **Fields inserted**: `username`, `password` only. Never `session_token`
   (login/force-logout concern — `FORCE_LOGOUT` clears it), never any status
   column (none exists; §8).
6. **Fields that must never be exposed**: `password`, `session_token` are
   `$hidden` on `User` — keep it that way in every user list/response/view, and
   never include them in audit payloads (§12).
7. **Authorization gate**: `page:register.php` (or `'*'`). One gate replaces the
   v1 split (any-logged-in-with-row for `register.php`, `super_admin` for
   `add_user.php`). Admin-only-ness emerges from the grant model — do **not**
   add an `isSuperAdmin` check on top (Pass 5 §3).
8. **Audit**: `MANAGE_USER_CREATE` on every successful create (§12).

**Open Decision #1 (user disable/enable) is NOT automatically added.** See §8.

---

## 8. Open Decision #1 — `active` Column Recommendation

**Decision status: this pass makes a strong architecture-based recommendation;
the final call requires owner approval. Do not implement the column.**

### 8.1 Evidence

- v1 has **no** `active`/status column and **no** user update/disable path
  anywhere (`ADMIN_ANALYSIS.md` Pass 1 §1.1, §4; Pass 5 §6). `tbl_users`
  columns are `id, username, password, created_at, last_activity,
  session_token`.
- Production schema (Pass 4 §11) matches — **no** `active` column. Adding one
  is a production-schema divergence (additive migration + review +
  `schema:dump` baseline regeneration per AGENTS.md).
- The only existing live-session revocation mechanism is `FORCE_LOGOUT`
  (clears `session_token`), already in v2.
- Fine-grained authorization (Open Decision #6) is deferred and orthogonal to a
  user status flag.

### 8.2 Options

- **A. V1-exact create-only** (recommended): users are created and never
  disabled; no schema change; exact v1 parity.
- **B. Additive `active` status**: new `active` column (default `1`), a
  disable/enable UI, and login/session enforcement. A deliberate v2 feature.

### 8.3 Recommendation — ship Option A (create-only) in P7

Rationale:

1. **v1 parity is a project non-negotiable** (AGENTS.md; SESSION_HANDOFF "Do not
   redesign behavior. Parity comes before optimization."). v1 has no disable;
   adding it is a v2-only feature and belongs to a separate reviewed decision,
   not the P7 port.
2. **Security without disable is adequate for the v1 contract**: `FORCE_LOGOUT`
   already revokes a live session; combined with no-self-service and admin-only
   creation, the v1 model carries the same blast radius in v2 (Pass 5 §6).
3. **Adding `active` now expands P7 scope** into a new subsystem (account
   lifecycle: column + migration + login enforcement + session enforcement +
   disable UI + tests) that no v1 workflow needs.
4. **It is cleanly additive later**: `active TINYINT(1) NOT NULL DEFAULT 1`
   + enforcement in the login provider and `EnsureSingleDevice`/session guard.
   This does **not** conflict with the P7 contract — user creation remains
   `INSERT(username, password)` and the column default covers existing rows.

### 8.4 Risks

- **Of Option A:** a departed staff account cannot be blocked except by password
  change (admin-driven) or `FORCE_LOGOUT`. Acceptable at v1 parity; owner may
  revisit.
- **Of Option B now:** production-schema divergence, migration review +
  baseline regen, new enforcement paths, new UI, test surface — a materially
  larger P7 for zero v1-parity gain.

### 8.5 Implementation impact

- If A (recommended): P7 implements §7 exactly as written; no migration; no
  `active` handling anywhere.
- If B later: additive migration + `schema:dump` regen (AGENTS.md workflow),
  `UserCreateRequest` unchanged, new `UserController@update`/disable routes
  behind `page:register.php`, login + session guard enforcement.

### 8.6 Owner approval required

**Yes.** Adopting the `active` column (Option B) is an owner decision (schema
change + scope expansion). This contract proceeds with **Option A** until the
owner rules otherwise. P7 must not silently decide this (AGENTS.md open
decisions).

---

## 9. Page Permission Contract

1. **Target user selection**: the editor lists `tbl_users` (the v1 page listed
   users with their rows); a row-selected save targets that user.
2. **Available page catalog**: the **real** catalog = distinct
   `tbl_permissions.page_name` values + the five P7 keys (Pass 5 §7). v1's
   hard-coded 32-entry array is **incomplete and duplicated** (Pass 1 §1.4) —
   do not copy it. `'*'` is **not** in this list (it is the separate
   super-admin toggle).
3. **Replacement behavior**: full-replace — `DELETE` the user's `Permission`
   rows, then `INSERT` one row per checked page with `can_access = 1` (v1
   parity, Pass 1 §1.4 / §3). Absence of a row = no access.
4. **`can_access` semantics**: always `1` on insert; no `0` rows are written
   (v1 never wrote them). A `0` row anywhere is treated as explicit denial by
   v2 (`canAccessPage`), consistent with absence-as-denial — do not create one.
5. **`'*'` handling**: a **separate, explicit, confirmed toggle** (confirm UI +
   audit), because `'*'` bypasses every page gate and is absent from
   `permittedPages()` (Pass 1 §2.1, Pass 5 §3/§7). Grant = insert the
   `page_name = '*'`, `can_access = 1` row; remove = delete that row.
6. **Removal behavior**: unchecking pages removes their rows on save
   (full-replace); removing `'*'` deletes the super-admin row and, as a
   side-effect, that user's derived single-device exemption (Pass 5 §4).
7. **Validation**: `PagePermissionRequest` (§6.2).
8. **Authorization**: `page:manage_permissions.php` (or `'*'`). A holder of
   this key **is** a permission admin by definition (ADR-003).
9. **Audit**: one op-level `MANAGE_PAGE_PERMISSIONS` row per save (old/new page
   sets) **plus** `MANAGE_SUPER_ADMIN_GRANT`/`MANAGE_SUPER_ADMIN_REVOKE` when
   the `'*'` row flips (§12). **One audit row per save, not per
   DELETE/INSERT** (Pass 6 §6).

---

## 10. Program Permission Contract

1. **Target user**: same list-selection pattern as §9.
2. **Verified 17-program catalog**: `TransactionService::PROGRAMS` — proven
   set-identical to the v1 hard-coded array and the production
   `tbl_transactions.program` enum (Pass 4 §7; Pass 3 §5). **No second catalog
   is introduced.** The catalog must remain aligned with `TransactionService::PROGRAMS`
   and the DB enum — do not fork it.
3. **Full-replace behavior**: `DELETE` the user's `ProgramPermission` rows,
   `INSERT` one per checked program (v1 parity, Pass 1 §1.5). Presence of a row
   = access; there is no `can_access` column.
4. **Validation**: every selected program `in:` the verified 17 — admins cannot
   create orphan rows that `permittedPrograms()` would ignore (Pass 5 §8).
5. **Authorization**: `page:manage_program_permissions.php` (or `'*'`). Page
   permission alone is sufficient; a program holder for one program must **not**
   implicitly edit others' grants (Pass 5 §3/§8).
6. **Audit**: one op-level `MANAGE_PROGRAM_PERMISSIONS` row per save with
   old/new program sets (§12).
7. **Resulting semantics**: `canAccessProgram` (used by `TransactionController`)
   reads exactly these rows; empty permitted list = unrestricted (super-admin /
   no rows), matching v1 (Pass 1 §2.7).

---

## 11. Multi-Device Exemption Contract

1. **Grant**: insert a `tbl_multi_device_exemptions` row for the target user
   (presence of a row = exempt, per `isMultiDeviceExempt`).
2. **Revoke**: delete that row.
3. **Idempotent behavior**: grant on an already-exempt user / revoke on an
   already-not-exempt user changes nothing (v1 parity — the v1 save was
   DELETE-then-conditional-INSERT; Pass 1 §1.6).
4. **No-op behavior**: a no-op produces **no DB write and no audit row**
   (Pass 6 §8.2).
5. **Authorization**: `page:manage_multi_device_exemptions.php` (or `'*'`).
   This is a privileged capability (weakens single-device login) and stays
   behind its own page key (Pass 5 §9).
6. **Audit behavior**: `MANAGE_EXEMPTION_GRANT` on real insert,
   `MANAGE_EXEMPTION_REVOKE` on real delete; silent otherwise (§12).

**Pass 5 / Pass 6 constraints preserved:**

- **`'*'` users are automatically exempt** (`isSingleDeviceExempt →
  isSuperAdmin`); no exemption row is needed or written for them.
- **Exemption picker excludes `'*'` holders** — data-driven exclusion
  (`isSuperAdmin` false), replacing v1's hard-coded
  `['super_admin', 'jordi']` list. Enforced server-side too (§6.4): toggling a
  `'*'` holder is a no-op.
- **Explicit exemption is represented separately from `'*'` semantics**:
  `isMultiDeviceExempt` reads only the exemption table; the audit trail records
  explicit grants/revokes only, and a `'*'` flip does **not** produce an
  exemption event (Pass 6 §8.4).

---

## 12. Audit Contract

Authoritative source: `ADMIN_ANALYSIS.md` Pass 6. **P7 v1 had ZERO audit
events for these writer operations** — auditing them is a deliberate v2
security/accountability improvement, not parity.

### 12.1 Canonical actions (the seven strings)

| Operation | Canonical action | Target table | `target_id` | `old_value` / `new_value` |
|---|---|---|---|---|
| User creation | `MANAGE_USER_CREATE` | `tbl_users` | new user id | `null` / `json_encode({'username': ...})` |
| Page-permission save (full replace) | `MANAGE_PAGE_PERMISSIONS` | `tbl_permissions` | **subject user id** | `{'username', 'pages': [old]}` / `{'username', 'pages': [new]}` |
| `'*'` grant (row inserted) | `MANAGE_SUPER_ADMIN_GRANT` | `tbl_permissions` | subject user id | `{'username', 'super_admin': false}` / `{'username', 'super_admin': true}` |
| `'*'` revoke (row deleted) | `MANAGE_SUPER_ADMIN_REVOKE` | `tbl_permissions` | subject user id | inverse of grant |
| Program-permission save (full replace) | `MANAGE_PROGRAM_PERMISSIONS` | `tbl_program_permissions` | subject user id | `{'username', 'programs': [old]}` / `{'username', 'programs': [new]}` |
| Exemption grant (real change) | `MANAGE_EXEMPTION_GRANT` | `tbl_multi_device_exemptions` | subject user id | `null` / `{'username': ...}` |
| Exemption revoke (real change) | `MANAGE_EXEMPTION_REVOKE` | `tbl_multi_device_exemptions` | subject user id | `{'username': ...}` / `null` |

### 12.2 Emission rules

- **When each is emitted:** exactly once per successful mutation, in the same
  transaction/flow as the mutation (Pass 6 §11.4). Failed validation/CSRF → no
  audit row (no event occurred).
- **`'*'` grant/revoke distinguished from ordinary page changes:** the page set
  includes `'*'` when present, so the `MANAGE_PAGE_PERMISSIONS` payload captures
  it; the **additional** `MANAGE_SUPER_ADMIN_GRANT`/`REVOKE` row is emitted only
  when `'*'` membership flips between the old and new sets (diff of `'*'` in the
  two page arrays). Both rows are written; they complement, not duplicate
  (Pass 6 §6.4).
- **No-op exemption toggle:** no audit (state unchanged; v1 baseline was
  silence) (Pass 6 §8.2).
- **`'*'` flip never emits an exemption event** (exemption is derived, not a
  row) (Pass 6 §8.4).
- **Reads are not audited:** viewing the audit viewer/leaderboard writes
  nothing; **no `VIEW_*` action is introduced** (Pass 6 §9.2).

### 12.3 Writer and payload safety

- `AuditService` is the **sole writer** of `tbl_audit_logs` (grep-verified,
  Pass 6 §13.1). P7 calls it exactly like every other service — no raw
  `DB::table('tbl_audit_logs')` inserts, no seeder writes into the trail except
  the explicit bootstrap record (§15).
- **No passwords, password hashes, or `session_token` in any payload.** Build
  payloads from explicit allow-lists (username, page arrays, program arrays);
  **never** `User::getAttributes()` (which would leak credentials — the
  full-row pattern used by `GipService` for `tbl_clients` must NOT be copied for
  `tbl_users`) (Pass 6 §5.2, §11). A build-time guard/assert should reject any
  payload containing `password`, `password_hash`, or `session_token` keys.

---

## 13. Audit Viewer Contract

1. **Page key**: `audit_logs.php`.
2. **Access mechanism**: `page:audit_logs.php` (or `'*'`) on the viewer AND on
   both feeds (nested in the group). v1's `fetch_logs.php` was session-only and
   `fetch_leaderboard.php` was **fully public** — v2 closes both gaps (Pass 5
   §3/§10). Distinct from the `manage_*.php` keys: a user can be granted
   read-only audit access without permission-management powers (Pass 5 §10).
3. **Feed nesting**: `fetch_logs.php` and `fetch_leaderboard.php` are routes in
   the `audit_logs.php` group, no separate keys (§3.6).
4. **Display behavior**: table whitelist `['tbl_clients', 'tbl_transactions',
   'tbl_cedssg']`; `INNER JOIN tbl_users` for actor `username`; distinct
   `users` + `actions` arrays per table for the dropdown filters (Pass 1 §1.8).
5. **Display-name resolution**: v1 resolves names for **two tables only**
   (`tbl_clients` → `lastname, firstname middlename`; `tbl_transactions` →
   `patient_name - program`); all other whitelisted tables show the raw
   `target_id` (Pass 1 §1.8, Pass 4 §4). **Required v2 viewer addition
   (Pass 6 §9.4):** resolve the subject username by joining `tbl_users` on
   `target_id` when `target_table IN ('tbl_users','tbl_permissions',
   'tbl_program_permissions','tbl_multi_device_exemptions')` so P7 `MANAGE_*`
   rows are readable. This is a v2-only rule — do not present it as v1 parity.
6. **Date filter behavior**: v1 **has** a **client-side** date-range filter
   (DateTime pickers + DataTables `ext.search` on `date_raw`,
   `audit_logs.php:201-221`) — preserve equivalent client-side filtering for
   parity. `fetch_logs.php` has **no** server date parameter. A server-side
   date-range filter is a v2-only enhancement (optional/deferred, §17.A) — do
   not silently add it.
7. **Server-side `LIMIT 10000`**: keep the v1 cap (Pass 1 §1.8).
8. **Timezone behavior**: UTC → Asia/Manila, display `m/d/Y - h:i A`, plus a
   sortable raw `Y-m-d H:i:s` (Pass 1 §1.8).
9. **Sorting behavior**: DataTables server-side sort on the feed columns,
   matching the v1 feed contract.
10. **Render safety**: `old_value`/`new_value` are JSON — render JSON-escaped or
    pretty-printed as text; never render raw HTML (Pass 1 §2.8).

---

## 14. Leaderboard Contract

1. **Page gate**: `page:audit_logs.php` (or `'*'`) — the leaderboard lives on
   the audit viewer page and its feed nests in the same group.
2. **Feed**: `AuditController@leaderboard` — port of v1 `fetch_leaderboard.php`:
   `SELECT u.username, COUNT(*) AS total_actions FROM tbl_audit_logs l JOIN
   tbl_users u ON l.user_id = u.id WHERE l.target_table = :table GROUP BY
   u.username ORDER BY total_actions DESC` (Pass 1 §1.9), per-table
   (`?table=`/POST `table` whitelisted to the v1 set).
3. **v1 behavior**: public (no session check) in v1 — a data leak.
4. **v2 security improvement**: placed behind the `audit_logs.php` page
   authorization boundary (Pass 5 §3/§10, Pass 6 §9.1) — no public bypass.
5. **No `VIEW_LEADERBOARD` action** is introduced (§12).
6. **No date window** (v1 had none); a date-window is an optional/deferred v2
   enhancement (§17.B).

---

## 15. Bootstrapping Contract

**Do NOT implement bootstrap in code.** The first-production-admin problem is
unresolved and requires owner approval (Pass 5 §5, Pass 4 §1/§9).

- **Established facts:** the only production artifact is a schema-only dump
  (Pass 4); production permission **rows** cannot be verified; production
  `'*'` presence is unknown; production P7 admin-key rows are unknown. v1 never
  wrote `'*'` or admin-key rows (username/id gates), so production is expected
  to have none — meaning **after cutover, no one can reach P7 unless a reviewed
  grant is applied** (Pass 5 §5, INFERENCE).
- **`'*'` is the sole v2 super-admin marker** (Pass 5 §4) — the bootstrap grant
  is a `'*'` row **or** the four admin page keys for a nominated user.
- **Allowed mechanism:** during the P8 cutover runbook, an **operator with DB
  access** runs a single **reviewed SQL** statement against production that
  inserts the grant for a **nominated, verified, existing `tbl_users` row**,
  followed by a read-only verification query. Versioned in the runbook,
  reviewed before the window, `mysqldump`-before-any-schema-work discipline.
- **Prohibited:** running `AccessControlSeeder` in production (creates the
  `jordi` dev user); any seeder/code that creates users as part of bootstrap;
  automating promotion in application code (by "first user", `user_id = 1`,
  username, or row-count heuristics) — all reintroduce the A2/A3 anti-pattern.
- **Audit-trail record (Pass 6 §14.4):** the operator-run bootstrap should also
  insert an explicit `MANAGE_SUPER_ADMIN_GRANT` audit row with `user_id = NULL`
  (no operator account exists yet) and `target_id` = the nominated user id —
  otherwise the highest-privilege event is the one event the trail cannot show.
  (Exact SQL shape is a P8 runbook item.)
- **Waits on owner approval of:** the nominated first administrator; `'*'` vs
  the individual admin grants (or a minimum set); reviewed cutover SQL vs a
  guarded, `APP_ENV=production`-disabled artisan command; whether the grant is
  permanent or replaced by granular grants after first login.
- **No automated first-admin creation. No bootstrap code in this pass.**

---

## 16. Deferred Authorization Architecture (Open Decision #6)

Open Decision #6 remains exactly **`DEFERRED — REQUIRES AUTHORIZATION
ARCHITECTURE RESEARCH`**. P7 must NOT attempt:

- action-level CRUD permissions (view/create/edit/delete/export/approve);
- municipality / data-scope authorization;
- a combined municipality + action model;
- any fine-grained authorization schema.

The schema (no action or municipality columns in the auth tables — Pass 1
§1.9/§1.10, Pass 2 §1) and the P7 contract deliberately leave these untouched.
The P7 contract nonetheless **does not prevent** them from being added later:
- Page keys + the `page:` gate are the initial authorization unit; nothing in
  P7 assumes action granularity (Pass 5 §6–§9).
- Each `MANAGE_*` audit action is a 1:1 stand-in for a future action-level
  grant (`MANAGE_USER_CREATE` → future `create`, `MANAGE_PAGE_PERMISSIONS` /
  `MANAGE_PROGRAM_PERMISSIONS` → future `manage`, `MANAGE_EXEMPTION_GRANT` /
  `REVOKE` → future `grant`/`revoke`, `MANAGE_SUPER_ADMIN_GRANT`/`REVOKE` →
  future `manage` on the marker) — migrating later needs no audit-trail
  redesign (Pass 6 §12). The viewer's distinct-action filter doubles as the
  future grant catalog checklist.
- No municipality columns are added; no query-level scoping is introduced in
  the P7 feeds beyond the established filters.

---

## 17. Optional v2 Audit Enhancements

Status per Pass 6 §10 (do not implement optional/deferred items in P7 core):

| Enhancement | Status |
|---|---|
| C. Permission-change auditing (`MANAGE_PAGE_PERMISSIONS`, `MANAGE_PROGRAM_PERMISSIONS`, `'*'` events) | **IN SCOPE** — required by this contract |
| D. User-creation auditing (`MANAGE_USER_CREATE`) | **IN SCOPE** — required |
| E. Exemption-change auditing (`MANAGE_EXEMPTION_GRANT`/`REVOKE`) | **IN SCOPE** — required |
| A. Server-side audit date-range filter | **OPTIONAL / DEFERRED** — no schema impact, no decision needed; parity is already met client-side (§13.6) |
| B. Leaderboard date-window | **OPTIONAL / DEFERRED** — v1 has none (§14.6) |
| F. IP audit metadata | **DEFERRED / schema-dependent** — `tbl_audit_logs` has no `ip` column; additive schema change requiring review + baseline regen (AGENTS.md) |

Open Decision #4 is therefore **resolved in part**: C/D/E in scope; A/B
optional/deferred; F deferred pending a schema decision (Pass 6 §10).

---

## 18. v1 Parity vs v2 Security Additions

| Area | V1 parity kept | V2 security/architecture addition |
|---|---|---|
| Page keys | All five keys are the v1 page filenames | Enforcement via `page:` middleware (v1 gates were inconsistent/username/id-based) |
| `'*'` super-admin | — (v1 had none) | The data-row admin contract; `'*'` satisfies every P7 gate |
| User creation | create-only (username+password); new users start with zero permissions | One `register.php` gate replaces the `super_admin` username gate; every create audited |
| Permission saves | full-replace `DELETE`+`INSERT`; `can_access` always `1`; absence = deny | Admin-only via page key (v1's `[1,2]` gate was commented out); audited; `'*'` as explicit confirmed toggle |
| Program saves | full-replace; 17-program catalog | Catalog from `TransactionService::PROGRAMS`/production enum (no orphan keys); page-key gate replaces `user_id in [1,2]`; audited |
| Exemption toggle | idempotent `DELETE`/conditional `INSERT` | Page-key gate replaces the username list; picker exclusion data-driven (exclude `'*'` holders) |
| Audit viewer | table whitelist, username join, display names (clients/transactions), timezone/format, `LIMIT 10000`, client-side date filter, per-table leaderboard | `audit_logs.php` key now enforced (v1 row was inert); feeds nested under the gate (v1 `fetch_leaderboard.php` was fully public); subject-name resolution for `MANAGE_*` rows |
| Audit writes | — (v1 wrote none on these screens) | `MANAGE_*` rows via `AuditService` (sole writer) |
| CSRF / validation | — (v1 none) | Laravel CSRF + `FormRequest` server-side validation |

---

## 19. Implementation Order (future build)

Recommended build sequence for the implementation pass (no code in this pass):

1. **Routes** — add the §4 routes (page groups + gates; no keys created beyond
   the five).
2. **Authorization gates** — verify `page:<key>` + `'*'` behavior with the
   existing `AccessControlService`/`AuthorizePage`; no new machinery (§3).
3. **Controllers/actions** — `UserController`, `AdminPermissionController`,
   `AuditController` skeletons per §5.
4. **Validation** — the four `FormRequest`s (§6).
5. **Service/model operations** — full-replace / idempotent-toggle writes on the
   existing models, inside `DB::transaction` (§5.2, §9–§11).
6. **Audit integration** — the seven `MANAGE_*` calls with §12 payloads.
7. **Views** — the five screens + sidebar entries gated by `canAccessPage`
   (Bootstrap/DataTables stack per ADR-006 deviation).
8. **Feeds** — `AuditController@data` + `@leaderboard` (§13–§14).
9. **Tests** — the §20 contract on `main_system_test`; full suite green.
10. **Parity/security verification** — round-trips, gate denials, payload-safety
    assert, `vendor\bin\pint`, production `main_system` untouched.

Bootstrap (§15) is a separate cutover-time runbook step, not part of the app
build.

---

## 20. Test Contract

The future P7 implementation must test at minimum (feature tests on
`main_system_test`, following the P1–P6 suite conventions):

**Authorization**
- Individual P7 permission grants reach the screen; `'*'` reaches every P7
  gate.
- Users without the page row (and without `'*'`) are denied (403/redirect).
- No username-based bypass; no user-ID-based bypass (grant a non-admin, attempt
  the screens).

**User creation**
- Valid creation succeeds; new user can log in; new user starts with zero
  permissions.
- Duplicate username rejected.
- Invalid input rejected (missing fields, mismatched confirmation).
- Password handling: bcrypt stored; `password`/`session_token` never in any
  response/payload.

**Page permissions**
- Full replacement replaces the whole set (old rows gone, new rows present).
- Removal works (unchecking removes rows).
- `'*'` grant inserts the row and makes the user a super-admin.
- `'*'` removal deletes the row and revokes super-admin.
- Ordinary page grants/revokes round-trip through `canAccessPage`.

**Program permissions**
- Full replacement and removal of `tbl_program_permissions` rows.
- Valid program values accepted; invalid/unknown program values rejected
  (catalog alignment with `TransactionService::PROGRAMS`).

**Exemptions**
- Grant inserts a row (`isMultiDeviceExempt` true).
- Revoke deletes the row.
- Idempotent grant (already exempt) and idempotent revoke (not exempt) change
  nothing.
- `'*'` holders are excluded/no-op (never a real toggle).

**Auditing**
- All **seven canonical actions** emitted with correct `target_table`,
  `target_id`, and payload shapes (§12.1).
- No-op exemption operations produce **no** audit row.
- Reads (viewer/leaderboard/data feeds) produce **no** audit row.
- No secrets in payloads: assert payloads never contain `password`,
  `password_hash`, or `session_token` keys (build-time guard + test).

**Audit viewer**
- Page gate enforced on the viewer and on both feeds.
- Existing table-whitelist, filters (distinct users/actions), and sorting
  behavior preserved; `LIMIT 10000` cap.
- `MANAGE_*` rows resolve the subject username.

**Leaderboard**
- Page gate enforced; no public bypass (feed without session → denied).
- Per-table aggregation matches `tbl_audit_logs` contents.

---

## 21. DB tables involved

| Table | P7 role |
|---|---|
| `tbl_users` | user list/creation (username + password only; `session_token`/`last_activity` are runtime state; no `active` column) |
| `tbl_permissions` | page grants incl. the `'*'` super-admin row |
| `tbl_program_permissions` | program grants |
| `tbl_multi_device_exemptions` | single-device exemptions |
| `tbl_audit_logs` | audit viewer + leaderboard (write side unchanged — `AuditService` only) |

No schema changes in P7 core. Any change (e.g. an `active` column, §8, or IP
metadata, §17.F) is additive, reviewed, and followed by `schema:dump` baseline
regeneration (AGENTS.md).

---

## 22. Never-change list

- Never reintroduce username/id-based admin checks on these screens — the `'*'`
  row is the only admin marker (ADR-003).
- Never write `tbl_audit_logs` outside `AuditService`.
- Never relax the `page_name = '*'` super-admin contract.
- Never expose `password`/`session_token` in views, API responses, or audit
  payloads.
- Never re-import `manage_php.php`.
- Never add a second program catalog or allow orphan program keys.
- Never make a P7 audit/viewer/leaderboard read write an audit row (no
  `VIEW_*`).
- Any schema change is additive, reviewed, and followed by baseline regen.

---

## 23. Verification / acceptance gates

- Permission management round-trips: grant/revoke a page and a program for a
  user; confirm the user's next page request is gated correctly.
- `'*'` grant/revoke flips `isSuperAdmin` and the derived single-device
  exemption behavior.
- Multi-device exemption add/remove changes `isMultiDeviceExempt`; `'*'` holders
  never appear in the picker.
- Audit viewer + filters + leaderboard match `tbl_audit_logs` contents; every
  P7 write appears with its `MANAGE_*` action.
- User creation succeeds and the new username can log in; duplicates rejected.
- Full suite green on `main_system_test`; `vendor\bin\pint` clean; production
  `main_system` untouched; no destructive schema operations.

---

## 24. Blueprint / ADR references

- `docs/ENGINEERING_BLUEPRINT.md` §1.11 (Administration), §2 rows
  (`manage_permissions.php`/`manage_program_permissions.php`/
  `manage_multi_device_exemptions.php` → `AdminPermissionController`;
  `audit_logs.php`/`fetch_logs.php`/`fetch_leaderboard.php` →
  `AuditController`; `register.php`/`add_user.php`/`manage_php.php` →
  `UserController`), §3 rows for the five tables, §8 rows 94–100.
- `docs/ARCHITECTURE_DECISION.md` — ADR-003 (one ACL service, which P7
  manages), ADR-008/ADR-009 (audit + export contracts the viewer renders).
- `docs/ADMIN_ANALYSIS.md` — Pass 1–6 (canonical evidence for everything in
  this contract). **Read it before building.**
- `docs/IMPLEMENTATION_LOG.md` — append the P7 entry when delivered (per
  AGENTS.md).
- `docs/REQUIREMENTS_ANALYSIS.md` — admin/audit FRs.

---

## 25. Decisions requiring owner approval

1. **First-admin bootstrap** (§15): nominated username; `'*'` vs individual
   admin grants; reviewed-SQL vs guarded-command mechanism; permanent vs
   replace-after-first-login.
2. **Open Decision #1 — `active` column** (§8): this pass recommends **Option A
   (create-only)**; adopting Option B (disable/enable) is an owner decision.
3. **Open Decision #3 — `MANAGE_*` strings** (§12.1): **resolved by this
   contract** (seven canonical strings, including the distinct
   `MANAGE_SUPER_ADMIN_GRANT`/`REVOKE` pair) — pending owner sign-off.
4. **Open Decision #4 — v2-only audit additions** (§17): **resolved in part**
   (C/D/E in scope; A/B optional/deferred; F deferred/schema-dependent) —
   pending owner sign-off on A/B/F if adopted.
5. **Open Decision #6 — fine-grained authorization** (§16): remains
   `DEFERRED — REQUIRES AUTHORIZATION ARCHITECTURE RESEARCH`. Not designed
   here.

---

**STATUS: Implementation contract — NOT YET IMPLEMENTED.** No P7 code was
written in this pass; only this document was modified. The next step is the P7
implementation build per §19, following the decisions fixed above and the
evidence in `docs/ADMIN_ANALYSIS.md` Pass 1–6.
