# P7 Administration Subsystem Analysis — v1 vs v2

> **Status:** Canonical analysis for the P7 (Administration) module, produced
> during the P6 finalization close-out (2026-08-13) ahead of the P7 build.
> It follows the naming convention of the other module analysis documents
> (`SCHOLAR_ANALYSIS.md`, `SCANNER_ANALYSIS.md`). The companion build contract
> is `docs/implementation/P7_ADMINISTRATION.md`; this document is the
> **verified v1 ground truth** that contract rests on, plus the parity
> requirements and the open decisions that must be settled before/while
> building P7.
>
> Analysis method: every v1 file below was read in full against the read-only
> legacy system at `C:\xampp\htdocs\system`, and cross-checked against
> `docs/implementation/P7_ADMINISTRATION.md`, `docs/ENGINEERING_BLUEPRINT.md`,
> the ADRs, and `AGENTS.md`. This analysis produced **no code**.
>
> Scope: user creation (`register.php`, `add_user.php`), page/program/multi-
> device permission management, and the audit viewer + leaderboard.
> `manage_php.php` is **excluded** from v2 (blueprint A10) and is analyzed only
> to confirm the exclusion and its dead links.

---

## 1. V1 inventory and behavior

### 1.1 File inventory

| Category | Files |
|---|---|
| **User creation** | `register.php`, `add_user.php` |
| **Page permissions** | `manage_permissions.php` |
| **Program permissions** | `manage_program_permissions.php` |
| **Multi-device exemptions** | `manage_multi_device_exemptions.php` |
| **Audit viewer** | `audit_logs.php`, `fetch_logs.php` |
| **Leaderboard** | `fetch_leaderboard.php` |
| **Excluded** | `manage_php.php` (file-manager-like admin page; blueprint A10) |

### 1.2 `register.php` — "Create User" (session-gated)

- **Not public**: requires a logged-in user (`redirect login.php`). Any logged-in
  user can create another account — the only gate is the session.
- POST fields `username`, `password`, `confirm_password`; rejects empty fields
  and a `password !== confirm_password` mismatch ("Passwords do not match.").
- Duplicate check `SELECT id FROM tbl_users WHERE username = ?` →
  "Username already taken.".
- `password_hash($password, PASSWORD_DEFAULT)` then
  `INSERT INTO tbl_users (username, password) VALUES (?, ?)`.
- **No `active`/status column, no disable semantics, no redirect after save**
  (message-based UI). Nothing else is written (no permissions, no exemptions).

### 1.3 `add_user.php` — "Create User" (super-admin gated)

- Gate is a hard-coded username check:
  `$_SESSION['username'] !== 'super_admin'` → "Access Denied. Super Admin only."
  (the A2/A3 anti-pattern v2 must not replicate).
- Same insert logic as `register.php` (unique username, confirm, bcrypt).
- Client-side password strength meter (≥8 chars, upper/lower/digit/special) and
  a password generator — **UI-only, not enforced server-side**.
- "← Back" links to the excluded `manage_php.php` — the only in-app path to this
  page, so v2 must surface user creation from its own admin screens.

### 1.4 `manage_permissions.php` — page-level grants

- Gated by session + `restriction.php` only (the `$allowed_ids = [1, 2]` block is
  **commented out** — every logged-in user could manage page permissions in v1).
- Page catalog is a **hard-coded PHP array**; it is **incomplete** (missing
  household/duplicate/student pages) and has **duplicate keys**
  (`scanner_ceap.php` and `scanner_otces.php` appear twice). The real keys are
  the `tbl_permissions.page_name` values.
- Save = **full-replace**: `DELETE FROM tbl_permissions WHERE user_id = ?`, then
  `INSERT ... (user_id, page_name, 1)` per checked page. `can_access` is always
  `1` on insert; there is no per-page `0` row (absence = no access).
- **No audit** on any write.
- v1 has **no `'*'` super-admin row concept** in this UI; super-admin is the
  implicit `user_id` 1/2 (see 1.5) and the hard-coded usernames elsewhere.

### 1.5 `manage_program_permissions.php` — program-level grants

- Gate: `in_array($_SESSION['user_id'], [1, 2])` — the implicit
  **id-based super-admin** (A3 anti-pattern; same class of bug as username
  checks: changing an id's meaning changes privileges).
- Program catalog is hard-coded: `AICS, AKAP, MAIP, TUPAD, CEDSSG, CEAP,
  CEAP_NEW, OTCES, OTEA, CEDSSG_NEW, COFFEE GROWERS, PUSO TI KABABAIHAN,
  PUSO TI AGTUTUBO, PUSO TI MANNALON, TESDA, GIP, TODA`.
- Save = **full-replace** `DELETE` + `INSERT (user_id, program_name)` per checked
  program. **No audit.**

### 1.6 `manage_multi_device_exemptions.php`

- Gate: username in `['super_admin', 'jordi']` (hard-coded).
- Save = **idempotent toggle**: `DELETE FROM tbl_multi_device_exemptions WHERE
  user_id = ?`, then `INSERT (user_id)` only when the checkbox is checked.
- The user picker **excludes** `super_admin` and `jordi`.
- **No audit.**

### 1.7 `audit_logs.php` — viewer page

- Viewer + leaderboard page, gated by hard-coded usernames
  `super_admin`/`god_admin`/`jordi` (v1 docs; the A2 anti-pattern).
- Renders `fetch_logs.php` (table feed) and `fetch_leaderboard.php`.

### 1.8 `fetch_logs.php` — audit feed

- Session-gated JSON; `?table=` parameter **whitelisted to
  `['tbl_clients', 'tbl_transactions', 'tbl_cedssg']`** (default
  `tbl_clients`).
- `INNER JOIN tbl_users` for `username`.
- **Display-name resolution exists for two tables only**:
  - `tbl_clients` → `CONCAT(lastname, ', ', firstname, ' ', COALESCE(middlename,''))`
    (LEFT JOIN `tbl_clients` on `target_id`);
  - `tbl_transactions` → `CONCAT(COALESCE(patient_name,''), ' - ', COALESCE(program,'No Program'))`;
  - any other whitelisted table → raw `target_id`.
- Timestamps: **UTC → Asia/Manila**, display `m/d/Y - h:i A` plus a sortable raw
  `Y-m-d H:i:s`.
- `LIMIT 10000`; response includes `data` + distinct `users` + distinct `actions`
  arrays (for dropdown filters). **No date-range filter in v1.**
- The commented-out per-row loop shows a v1 iteration toward the optimized query;
  the live code is the optimized version above.

### 1.9 `fetch_leaderboard.php`

- `?table=` same 3-table whitelist.
- `SELECT u.username, COUNT(*) AS total_actions FROM tbl_audit_logs l JOIN
  tbl_users u ON l.user_id = u.id WHERE l.target_table = :table GROUP BY
  u.username ORDER BY total_actions DESC`.
- **No date window in v1.**

---

## 2. What v2 already provides (P1 machinery P7 will manage)

- `AccessControlService` — `isSuperAdmin` (`page_name = '*'`), `canAccessPage`,
  `canAccessProgram`, `isSingleDeviceExempt`, `isMultiDeviceExempt`,
  `permittedPages`, `permittedPrograms`; per-request caches.
- `Permission`, `ProgramPermission`, `MultiDeviceExemption` models (fillable =
  exactly the legacy columns).
- `AuthorizePage` middleware + `page:`/`program` Gates — the consumers of the
  rows P7 screens will edit.
- `AuditService` — the **single writer** to `tbl_audit_logs`.
- `User` model + relations; `password`/`session_token` are `$hidden`.
- `AccessControlSeeder` — local-only `jordi` `'*'` row (never run in production).

No user CRUD and no admin UI exist yet — those are the P7 deliverables.

---

## 3. Parity requirements for P7

1. **User creation** = v1's minimal contract: unique `username`, password +
   confirm match, bcrypt (`hashed` cast), `INSERT (username, password)` only.
2. **Permission saves are full-replace**: `DELETE` the user's rows, `INSERT` the
   checked set. Absence of a row = no access (`can_access` always `1`).
3. **Exemption toggle is idempotent**: `DELETE` then `INSERT` only if checked.
4. **Audit feed**: table whitelist, username join, clients/transactions display
   names, UTC→Asia/Manila `m/d/Y - h:i A`, `LIMIT 10000`.
5. **Leaderboard**: per-user action count per table, `ORDER BY total_actions
   DESC`.
6. **No `'*'` row management in v1** — granting super-admin is a **v2 contract**
   (`AccessControlService::isSuperAdmin`), not a v1 parity item.

## 4. Verified corrections / notes to `P7_ADMINISTRATION.md`

- "disable/enable semantics … confirm what v1 used" → **v1 has none.** There is
  no `active`/status column and no user update path anywhere in v1's user code.
  If P7 wants disable, it needs a **new additive column** (schema change:
  review + additive migration + `schema:dump` baseline regen) — an open
  decision, not a port.
- "filters on action / table / user / **date range**" → v1 has **no date range**
  in `fetch_logs.php`; the two dropdowns are fed from distinct
  users/actions **per table**. A date-range filter would be a v2 addition.
- "leaderboard … optional **date window**" → v1 has none; the leaderboard is
  **per-table** (`?table=`) — the `target_table` dimension is the filter, not
  time.
- Audit **display-name resolution covers only `tbl_clients` and
  `tbl_transactions`**; other tables show the raw `target_id` (do not promise a
  universal name resolver as "v1 parity").
- v1's `manage_permissions` **page catalog is incomplete + duplicated**; P7
  should source the page list from the real `tbl_permissions.page_name` values,
  not the v1 array.
- v1 gates every admin screen by **username or user_id**, and `register.php` is
  open to any logged-in user. v2 replaces all of that with `page:` gates and the
  `'*'` row — this is a deliberate security improvement, not parity.
- `MANAGE_*` audit rows on permission writes, the confirmed `'*'` grant, and
  server-side validation are **v2 additions** (v1 audits none of these); choose
  the action strings deliberately so the viewer's distinct-action filter stays
  clean.

## 5. Open decisions (must be settled before/while building P7)

1. **User disable/enable**: v1 has none. Add an additive `active` column
   (schema change) or ship v1-exact (create-only) for now?
2. **Admin bootstrapping**: production `tbl_permissions` may not carry rows for
   the admin page keys (`manage_permissions.php`, `manage_program_permissions.php`,
   `manage_multi_device_exemptions.php`, `audit_logs.php`) — v1 gated these by
   username, so no permission rows were needed. Without a granted `'*'` row (or
   page rows) no one can reach the P7 screens. Plan how the first admin gets
   access in production (data seeding/update, reviewed — the local
   `AccessControlSeeder` must not run in production).
3. **`MANAGE_*` action strings**: names and stability contract for the audit
   viewer filters.
4. **Additive features to include**: date-range audit filter, leaderboard date
   window, audit-on-permission-writes (all v2 additions — decide scope).
5. **Program catalog source**: P7 must match `TransactionService::PROGRAMS` /
   the DB enum exactly (v1's hard-coded 17-program array is the only v1 list).
6. **Fine-grained authorization architecture** (municipality/data-scope and/or
   action-level CRUD) — `DEFERRED — REQUIRES AUTHORIZATION ARCHITECTURE
   RESEARCH`. Pass 2 (Municipality / Data Scope Research, below) gathers the
   schema + enforcement evidence this decision rests on. Not resolved here.

## 6. Never-change list

- Never reintroduce username/id-based admin checks on these screens (the `'*'`
  row is the only admin marker).
- Never write `tbl_audit_logs` outside `AuditService`.
- Never relax the `page_name = '*'` super-admin contract.
- Never expose `password`/`session_token` in views or API responses.
- Never re-import `manage_php.php`.
- Any schema change (e.g. an `active` column) is additive, reviewed, and
  followed by `php artisan schema:dump` baseline regeneration.

---

## 7. Blueprint / ADR references

- `docs/ENGINEERING_BLUEPRINT.md` §1.11 (Administration), §2 rows
  (`audit_logs.php`/`fetch_logs.php`/`fetch_leaderboard.php` → `AuditController`),
  §3 rows for `tbl_users`, `tbl_permissions`, `tbl_program_permissions`,
  `tbl_audit_logs`.
- `docs/ARCHITECTURE_DECISION.md` — ADR-003 (one ACL service), ADR-009 (audit
  contract).
- `docs/implementation/P7_ADMINISTRATION.md` — the build contract this analysis
  grounds.
- `docs/IMPLEMENTATION_LOG.md` — append the P7 entry when delivered.

---

## V2 Authorization Research

### Pass 1 — Existing Authorization Model (2026-08-13)

> **Scope**: Read-only analysis of both the v1 legacy codebase
> (`C:\xampp\htdocs\system`) and the existing v2 authorization machinery.
> No code was written or modified.

---

#### Files inspected

**V1 (read-only)**

| File | Purpose |
|---|---|
| `login.php` | Authentication |
| `session.php` | Session validation + single-device enforcement |
| `check_session.php` | AJAX session-status probe |
| `restriction.php` | Per-page ACL check |
| `register.php` | User creation (session-only gate) |
| `add_user.php` | User creation (super-admin gate) |
| `manage_permissions.php` | Page-level permission editor |
| `manage_program_permissions.php` | Program-level permission editor |
| `manage_multi_device_exemptions.php` | Multi-device exemption editor |

**V2 (read-only)**

| File | Purpose |
|---|---|
| `app/Services/AccessControlService.php` | Single ACL service |
| `app/Services/AuditService.php` | Audit writer |
| `app/Models/Permission.php` | ORM for `tbl_permissions` |
| `app/Models/ProgramPermission.php` | ORM for `tbl_program_permissions` |
| `app/Models/MultiDeviceExemption.php` | ORM for `tbl_multi_device_exemptions` |
| `app/Models/User.php` | ORM for `tbl_users` |
| `app/Http/Middleware/AuthorizePage.php` | `page:` middleware |
| `app/Http/Middleware/EnsureSingleDevice.php` | Single-device middleware |
| `app/Providers/AppServiceProvider.php` | Gate definitions + service binding |
| `bootstrap/app.php` | Middleware alias registration |
| `routes/web.php` | Route-level guard attachment |
| `tests/Feature/AccessControlTest.php` | P1 ACL tests |
| `tests/Feature/AuthTest.php` | P1 auth tests |
| `app/Policies/ClientPolicy.php` | Only non-ACL-service policy |
| `app/Http/Controllers/TransactionController.php` | Program-gate consumer |
| `database/schema/mysql-schema.sql` | Canonical table definitions |
| `database/migrations/2026_08_05_000004_add_unique_permission_constraints.php` | Unique index on auth tables |

---

#### 1. V1 Authorization Findings

##### 1.1 Authentication

- **Mechanism**: `login.php` does `SELECT id, password FROM tbl_users WHERE username = ?`,
  verifies with `password_verify()`.
- **Session state**: `$_SESSION['user_id']` and `$_SESSION['username']` are written on
  success. A `session_token` (`bin2hex(random_bytes(32))`) is generated and written
  both to `$_SESSION['session_token']` and `tbl_users.session_token`.
- **No email**, no role column, no status/active column in `tbl_users`.
  Columns: `id`, `username`, `password`, `created_at`, `last_activity`, `session_token`.
- **Confirmed fact**: `last_activity` is updated on every authenticated page load via
  `session.php` (`UPDATE tbl_users SET last_activity = NOW() WHERE id = ?`).

##### 1.2 Single-device enforcement

- **Mechanism** (`session.php`): On each page load, if the user is not exempted,
  `tbl_users.session_token` is compared to `$_SESSION['session_token']`. Token mismatch
  → `session_destroy()` → redirect to `login.php?session=expired`.
- A new login overwrites `tbl_users.session_token`, instantly invalidating all older
  device sessions.
- **AJAX probe**: `check_session.php` performs the same token check and returns
  `{"status":"another_device"}` on mismatch; used by front-end polling.
- **Confirmed fact**: Single-device enforcement is **server-side**, not just UI-level.

##### 1.3 Super Admin determination

V1 uses **three parallel, inconsistent mechanisms** to determine admin identity:

| Mechanism | Where used | Value |
|---|---|---|
| `user_id == 1` hard-code | `restriction.php` line 22 | Any user whose DB `id` happens to be `1` |
| Username in `['super_admin', 'god_admin', 'jordi']` | `restriction.php` lines 27-31, `session.php` lines 12-18 | Hard-coded strings |
| Username in `['super_admin', 'jordi']` | `session.php` (10-year lifetime), `manage_multi_device_exemptions.php` gate, `check_session.php` | Hard-coded strings (subset — `god_admin` omitted) |
| Username in `['super_admin', 'jordi']` | `manage_multi_device_exemptions.php` gate | Hard-coded strings |
| `user_id in [1, 2]` | `manage_program_permissions.php` gate | Hard-coded ids |

**Confirmed fact**: There is **no single canonical super-admin marker** in v1.
The three mechanisms are inconsistently applied and can produce different results
(e.g., `god_admin` bypasses `restriction.php` but does NOT get a 10-year session and
cannot access multi-device exemptions). This is the A2/A3 anti-pattern documented in
`ADMIN_ANALYSIS.md` §1.3–§1.6.

##### 1.4 Page-level permissions

- **Table**: `tbl_permissions(id, user_id, page_name, can_access)`.
  `can_access` is `TINYINT(1) DEFAULT 1`. On insert, `can_access` is **always `1`**
  (absence of a row = no access; there is no `can_access = 0` row in practice).
- **Check** (`restriction.php`): `SELECT can_access FROM tbl_permissions WHERE user_id = ? AND page_name = ?`.
  If `!$hasAccess` → JS alert + redirect to `index.php`.
- **Bypass**: Admin-username/id users skip the DB check entirely (return early at lines
  22-30 in `restriction.php`).
- **Gate on `manage_permissions.php`**: The `$allowed_ids = [1, 2]` block is
  **commented out**. Any logged-in user who can reach the URL can edit page permissions.
- **Page catalog**: Hard-coded 32-entry PHP array in `manage_permissions.php`.
  It contains duplicate keys (`scanner_ceap.php` appears twice, `scanner_otces.php`
  appears twice) and is missing pages (`household.php`, `update_logs.php`, etc.).
  The authoritative permission keys are whatever `page_name` strings exist in
  `tbl_permissions` rows at runtime.
- **Save semantics**: `DELETE FROM tbl_permissions WHERE user_id = ?` then
  `INSERT (user_id, page_name, 1)` for each checked page. Full-replace on every save.
- **No audit** on any write.

##### 1.5 Program-level permissions

- **Table**: `tbl_program_permissions(id, user_id, program_name)`.
  No `can_access` column — presence of a row = access granted.
- **Check**: Only used via the hard-coded program lists in screen business logic
  (v1 does not have a central `canAccessProgram` check analogous to `restriction.php`).
  The program gate is effectively only enforced at the transaction-creation UI level.
- **Gate on `manage_program_permissions.php`**: `in_array($current_user_id, [1, 2])`
  — id-based super-admin (A3 anti-pattern).
- **Program catalog**: Hard-coded 17-program array identical to the `tbl_transactions.program`
  enum (`AICS`, `AKAP`, `MAIP`, `TUPAD`, `CEDSSG`, `CEAP`, `CEAP_NEW`, `OTCES`,
  `OTEA`, `CEDSSG_NEW`, `COFFEE GROWERS`, `PUSO TI KABABAIHAN`, `PUSO TI AGTUTUBO`,
  `PUSO TI MANNALON`, `TESDA`, `GIP`, `TODA`).
- **Save semantics**: `DELETE + INSERT` full-replace. No audit.

##### 1.6 Multi-device exemptions

- **Table**: `tbl_multi_device_exemptions(id, user_id, created_at)`.
  Presence of a row = exempted. No status column.
- **Gate**: Username in `['super_admin', 'jordi']` (hard-coded). Not data-driven.
- **Save**: Idempotent `DELETE` then conditional `INSERT`.
- **Exclusion**: `super_admin` and `jordi` are excluded from the user picker (they are
  permanently exempt via the username check in `session.php` and never need a DB row).
- **No audit.**

##### 1.7 Screens that perform authorization checks

| Screen | Method | Enforced server-side? |
|---|---|---|
| `login.php` | `password_verify` | Yes |
| `session.php` (every page) | Token comparison | Yes |
| `restriction.php` (most pages) | `tbl_permissions` DB query | Yes |
| `add_user.php` | Username `=== 'super_admin'` | Yes |
| `manage_program_permissions.php` | `user_id in [1, 2]` | Yes |
| `manage_multi_device_exemptions.php` | Username in `['super_admin', 'jordi']` | Yes |
| `audit_logs.php` | Username in `['super_admin', 'god_admin', 'jordi']` | Yes |
| `manage_permissions.php` | **None** (gate commented out) | ❌ No — session-only |
| `register.php` | Session presence only | Partial — any logged-in user |

**Pages that include `restriction.php`** (confirmed by grep):
`all_transactions.php`, `clients.php`, `currently_logged_users.php`,
`export_unpaid_verifications.php`, `fetch_unpaid_verifications.php`,
`force_logout.php`, `manage_multi_device_exemptions.php`,
`manage_permissions.php`, `manage_program_permissions.php`,
`register.php`, `scanned_payouts.php`, `scanned_payouts2.php`,
`scanned_payouts_unpaid.php`, `scanner_ceap.php`, `scanner_ceap_new.php`,
`scanner_cedssg.php`, `scanner_cedssg_new.php`, `scanner_cedssg_update.php`,
`scanner_new_scholars.php`, `scanner_ongoing_scholars.php`,
`scanner_otces.php`, `scanner_otea.php`, `scanner_toda.php`,
`scanner_tupad.php`, `scholars.php`, `scholarship_reports.php`,
`unpaid_verifications.php`.

Action-endpoint files (`fetch_*.php`, `save_*.php`, `add_*.php`, `delete_*.php`)
do **not** include `restriction.php`. They typically check only `$_SESSION['user_id']`
presence (session guard) without verifying page permission. This is an
**authorization gap**: a user with the session cookie but no page permission can POST
to action endpoints directly.

##### 1.8 Privilege-escalation weaknesses

1. **`manage_permissions.php` gate is commented out** — any logged-in user can grant
   themselves or others any page permission.
2. **Action endpoints lack page checks** — holding a session is sufficient to call
   `delete_client.php`, `delete_transaction.php`, `save_scholarship.php`, etc.
3. **Username/id conflation** — becoming `super_admin` or obtaining `user_id = 1` grants
   blanket privilege. The mechanisms are inconsistent (`god_admin` works in some places,
   not others).
4. **No CSRF tokens** on any v1 form (not a v2 concern, but confirms v1 had no
   action-level security).

##### 1.9 Action-level permissions — confirmed absence

**Confirmed fact**: V1 has **no action-level (CRUD) permission columns or rows**.
`tbl_permissions` has a single `can_access` bit. The system cannot distinguish:
- View vs. Create vs. Edit vs. Delete vs. Export vs. Approve

Page access grants all actions on that page. There is no concept of a user who can
view transactions but not delete them within v1's permission model.

##### 1.10 Municipality-related authorization

**Confirmed fact**: None of the four authorization tables
(`tbl_users`, `tbl_permissions`, `tbl_program_permissions`,
`tbl_multi_device_exemptions`) contain any municipality-related column.
`tbl_municipalities` exists in the schema but has no FK or reference in any
authorization table. Authorization is not scoped by municipality in either v1 or v2.

---

#### 2. V2 Authorization Findings

##### 2.1 AccessControlService (singleton, ADR-003)

`app/Services/AccessControlService.php` — the **single canonical ACL**. No username
or id checks anywhere in v2. Exposed methods:

| Method | What it checks |
|---|---|
| `isSuperAdmin(User $user)` | `tbl_permissions` row where `page_name = '*'` AND `can_access = true` |
| `canAccessPage(User $user, string $pageName)` | Super-admin OR matching `page_name` row with `can_access = true` |
| `canAccessProgram(User $user, string $programName)` | Super-admin OR matching `program_name` row in `tbl_program_permissions` |
| `isSingleDeviceExempt(User $user)` | Super-admin OR has a row in `tbl_multi_device_exemptions` |
| `isMultiDeviceExempt(User $user)` | Has a row in `tbl_multi_device_exemptions` |
| `permittedPages(User $user)` | All `page_name` strings with `can_access = true` |
| `permittedPrograms(User $user)` | All `program_name` strings |

**Per-request caching**: Results are memoized in private arrays keyed by `user->id`.
Cache is **request-scoped** (singleton per HTTP request). The test suite calls
`app->forgetInstance(AccessControlService::class)` between requests to simulate a fresh
request.

##### 2.2 Permission model (tbl_permissions)

```
tbl_permissions: id INT, user_id INT, page_name VARCHAR(100), can_access TINYINT(1) DEFAULT 1
  PRIMARY KEY (id)
  UNIQUE KEY uniq_permission_user_page (user_id, page_name)   ← added by migration 4
```

- `page_name = '*'` is the super-admin sentinel (v2 contract, not v1).
- `can_access` is cast to `boolean` in the Eloquent model.
- UNIQUE constraint on `(user_id, page_name)` ensures at most one row per user+page.
  The constraint was **not present in the original v1 schema** — it was added by
  migration `2026_08_05_000004` (conditional: skips with warning if duplicates exist).
- **No action columns** — only presence/absence of a row with `can_access = true`
  determines access.

##### 2.3 ProgramPermission model (tbl_program_permissions)

```
tbl_program_permissions: id INT, user_id INT, program_name VARCHAR(100)
  PRIMARY KEY (id)
  UNIQUE KEY uniq_program_permission_user_program (user_id, program_name)   ← added by migration 4
```

- Presence of a row = permission granted. No `can_access` column.
- UNIQUE constraint added by the same migration.

##### 2.4 MultiDeviceExemption model (tbl_multi_device_exemptions)

```
tbl_multi_device_exemptions: id INT, user_id INT, created_at TIMESTAMP
  PRIMARY KEY (id)
  UNIQUE KEY user_id (user_id)
```

- Presence of a row = exempt from single-device check.

##### 2.5 User model (tbl_users)

```
tbl_users: id INT, username VARCHAR(100) UNIQUE, password VARCHAR(255),
           created_at TIMESTAMP, last_activity DATETIME, session_token VARCHAR(255)
```

- No `active`/status column (confirmed: not added, no migration for it).
- No `role` column.
- `getAuthIdentifierName()` returns `'username'` — login is by username, not email.
- `password` and `session_token` are `$hidden`.

##### 2.6 Middleware and Gate wiring

| Alias | Class | Registration |
|---|---|---|
| `single-device` | `EnsureSingleDevice` | `bootstrap/app.php` |
| `page` | `AuthorizePage` | `bootstrap/app.php` |
| `Gate('page', ...)` | `AccessControlService::canAccessPage` | `AppServiceProvider::boot()` |
| `Gate('program', ...)` | `AccessControlService::canAccessProgram` | `AppServiceProvider::boot()` |

`AuthorizePage::handle()`: resolves user from request, calls `canAccessPage()`. On
denial: JSON requests get 403; HTML requests are redirected to `dashboard` with
`login_status = 'denied'` flash.

`EnsureSingleDevice::handle()`: calls `isSingleDeviceExempt()`. Non-exempt users have
`session('session_token')` compared to `tbl_users.session_token` via `hash_equals`.
Token mismatch → `Auth::logout()` + redirect to `login` with `login_status = 'expired'`.
`last_activity` is updated on every authenticated request.

##### 2.7 How P2–P6 consume authorization

All authenticated routes are under the `auth, single-device` middleware group
(`routes/web.php` line 69). Individual routes/groups then add `page:{page_name}`.

| Route group | Page gate key |
|---|---|
| Clients, duplicates, photos, GIP, family members | `clients.php` |
| Households | `household.php` |
| Transactions (index/create/edit/delete/export/inline-update) | `all_transactions.php` |
| Scholars | `scholars.php` |
| Scholarship reports | `scholarship_reports.php` |
| Update logs | `update_logs.php` |
| Payout attendance (3 variants) | `scanned_payouts.php`, `scanned_payouts2.php`, `scanned_payouts_unpaid.php` |
| Scanners (config-driven) | `scanner_{key}.php` per config entry |
| Currently logged users | `currently_logged_users.php` |
| Force logout | `force_logout.php` |
| Unpaid verifications (staff view) | `unpaid_verifications.php` |

**Program-level enforcement in TransactionController**: `programsForUser()` calls
`acl->permittedPrograms()` and intersects against `TransactionService::PROGRAMS`. An
empty permitted list → all programs allowed (super-admin / no restriction rows).
`authorizeProgram()` aborts 403 if the submitted program is not in the allowed set.
This mirrors v1's implicit behavior (v1 never enforced program scope at the
transaction-submission level server-side either; v2 closes that gap).

**ClientPolicy**: `delete(User, Client)` delegates to `canAccessPage($user, 'clients.php')`.
No separate delete permission; delete access is coextensive with page access.

##### 2.8 Audit

`AuditService::log()` writes to `tbl_audit_logs` with `user_id`, `action`,
`target_table`, `target_id`, `old_value`, `new_value`, `created_at`. It is the sole
writer; nothing in P2–P6 writes `tbl_audit_logs` directly. P7 will write
`MANAGE_*` action strings through this service.

---

#### 3. Capability Matrix

| Capability | V1 | V2 |
|---|---|---|
| **User authentication** | Username + bcrypt via `password_verify` | Username + bcrypt via `password_hash` cast; `Auth::attempt()` |
| **Session management** | PHP sessions; token in `$_SESSION` + `tbl_users.session_token` | Laravel session; token stored in session store + `tbl_users.session_token` |
| **Single-device login** | Server-side token check in `session.php` | Server-side token check in `EnsureSingleDevice` (port of `session.php`) |
| **Page/module access** | `restriction.php` → `tbl_permissions` DB query per page | `page:` middleware → `AccessControlService::canAccessPage()` (cached) |
| **Program access** | Implicit (no central check); per-screen UI visibility only | `program` Gate → `AccessControlService::canAccessProgram()`; enforced at transaction store/update |
| **Super Admin** | Three inconsistent mechanisms (username, user_id, god_admin subset) | Single data row: `tbl_permissions.page_name = '*'` with `can_access = true` |
| **Multi-device exemption** | `tbl_multi_device_exemptions` + username hardcode for super_admin/jordi | `tbl_multi_device_exemptions` (data-driven); super-admin exempt via `isSuperAdmin()` |
| **Action-level permissions** | ❌ Not present | ❌ Not present |
| **Municipality scope** | ❌ Not present in auth tables | ❌ Not present in auth tables |
| **User disable/enable** | ❌ Not present (no `active` column) | ❌ Not present (no `active` column) |
| **Audit on auth writes** | ❌ None | ❌ None (P7 will add) |
| **CSRF protection** | ❌ None | ✅ Laravel CSRF middleware |
| **Action-endpoint enforcement** | ❌ Session-only (gap) | ✅ Route-level middleware (gap closed) |

---

#### 4. Confirmed Limitations

1. **No action-level CRUD permissions.** Neither v1 nor v2's schema can distinguish
   View/Create/Edit/Delete/Export/Approve for a given page. `tbl_permissions.can_access`
   is a single bit. Adding action granularity would require **schema changes** (new
   columns or a new table) and is out of scope for a parity build.

2. **No municipality scope in authorization.** `tbl_users`, `tbl_permissions`,
   `tbl_program_permissions`, and `tbl_multi_device_exemptions` have no municipality
   column. Data is scoped to the single installation; there is no per-municipality
   user or permission row.

3. **No user disable/enable.** `tbl_users` has no `active` or `status` column.
   A user can only be removed from the system (record deletion) — there is no
   soft-disable concept in either v1 or v2. Adding it requires an additive migration.

4. **`page_name = '*'` is a v2-only contract.** V1 has no such row concept.
   The production `tbl_permissions` table may have no `'*'` row at all. Bootstrapping
   the first admin in production is an open decision (see Open Decisions §5 in the
   existing analysis).

5. **Program-gate enforcement is transaction-centric.** Only `TransactionController`
   enforces program scope server-side. Scanner routes are gated by page key
   (`page:scanner_*.php`) but not by `program`. A user with `scanner_ceap.php`
   permission but no `CEAP` program permission can still operate the CEAP scanner
   because page access and program access are independent dimensions.

6. **The `page:` middleware does not distinguish GET vs. POST.** A route group gated
   with `page:clients.php` protects all verbs equally. There is no "view-only" vs.
   "write" distinction within a page gate.

---

#### 5. Unresolved Questions

1. **Are there any existing `'*'` rows in production `tbl_permissions`?**
   Unknown without a live DB query. The local seeder creates one for `jordi`, but
   that row must never be run in production.

2. **What `page_name` strings actually exist in the production `tbl_permissions` table?**
   The v1 hard-coded catalog is incomplete and duplicated. The real key list is whatever
   the production rows contain. This list is needed to build the P7 admin UI's page
   catalog correctly.

3. **Do the production `tbl_permissions` rows include the admin page keys?**
   (`manage_permissions.php`, `manage_program_permissions.php`,
   `manage_multi_device_exemptions.php`, `audit_logs.php`). V1 gated these by username,
   so no permission rows were written. If they don't exist, no one can reach P7 screens
   after deployment until rows are seeded/inserted manually.

4. **Scanner vs. program gate interaction**: Is it intentional that scanner access
   bypasses `canAccessProgram()`? Or should scanner routes also enforce program-level
   permission? This decision affects P7's UI for configuring scanner access.

5. **`can_access = 0` rows**: The schema allows `can_access = 0` (tinyint with
   `DEFAULT 1`). V1 never inserts `0` rows (only `1` or absent). V2 casts the column
   to boolean and checks `can_access = true`. Should `0` rows be treated as explicit
   denials (overriding a `'*'` super-admin row)? Currently `isSuperAdmin()` does not
   short-circuit for `0` rows on non-wildcard pages. This edge case is unexercised.

---

#### 6. Recommended Next Research Pass

**Pass 2 — Live production data snapshot** (read-only DB query only, no writes):

1. Run `SELECT DISTINCT page_name FROM tbl_permissions ORDER BY page_name` against
   the local `main_system` copy to get the real page-key catalog.
2. Run `SELECT * FROM tbl_permissions WHERE page_name = '*'` to confirm whether any
   super-admin row exists in production data.
3. Run `SELECT COUNT(*) FROM tbl_permissions WHERE can_access = 0` to determine whether
   explicit-denial rows exist in production.
4. Enumerate all `tbl_program_permissions.program_name` values to confirm they match the
   v1 17-program catalog or diverge.
5. Confirm the route-to-page-key mapping is complete (i.e., every v1 page that uses
   `restriction.php` has a v2 counterpart gated with the same key).

This pass requires only `mysql` CLI reads against the local DB copy and can be completed
without any code changes.

---

### Pass 2 — Municipality / Data Scope Research (2026-08-15)

> **Scope**: Read-only investigation of how municipality is represented in the
> schema, used by the application, and (not) enforced as a data-scope
> boundary — in both v1 (`C:\xampp\htdocs\system`, read-only) and v2. No code,
> schema, route, or test changes; no authorization decision made. Feeds the
> deferred Open Decision #6 (fine-grained authorization architecture).
>
> **Method**: every v1 screen/feed/action file was read or grepped for
> `municipal`/`town`/`barangay`; every v2 controller/service was grepped for the
> same; table definitions were read from `database/schema/mysql-schema.sql` and
> confirmed against `phpmyadmin`/`mysql` on the local `main_system` copy.
> Note: the local `main_system` copy is **schema-only reference-data-wise**
> (0 municipalities, 0 barangays, 0 clients, 1 user), so value-level semantics
> (`intval()`, join coercion) were proven from v1 code, not from sample rows.

#### 1. Executive findings

- **Municipality is a data attribute and an optional report/list filter —
  never an authorization boundary** — in v1 or v2. No table, screen, or action
  ever restricts a user to a municipality; the default filter is "All".
- **`tbl_clients.city_municipality` and `tbl_clients.barangay` store integer
  IDs as `varchar(100)`** (unconstrained foreign keys). v1 writes them with
  `intval($_POST[...])` and joins with `ON m.id = c.city_municipality`.
- **`tbl_unpaid_verifications.municipality_id` is the only true integer FK to
  `tbl_municipalities`** in the whole schema.
- **Auth tables carry no municipality**: `tbl_users`, `tbl_permissions`,
  `tbl_program_permissions`, `tbl_multi_device_exemptions`, `tbl_audit_logs`
  have zero municipality columns/relations (confirmed by grep in v1 and v2).
- **Seat/exam "town" columns are display-only name strings**, not IDs and not
  FK-linked: `tbl_seats.town`, `tbl_seats2.town`, `tbl_exam.town`/`barangay`.
  They are never joined to `tbl_municipalities` and never used as a scope.
- **v2 `AccessControlService` has no municipality concept**: only
  `isSuperAdmin` (`page_name = '*'`), `canAccessPage`, `canAccessProgram`, and
  the exemption checks. All v2 scope is page-level via the `page:` middleware.
- **The only server-side "municipality match" logic in either codebase is the
  public grantee-self-service integrity check** (`search_grantee.php` /
  `GranteeSearchController::verify`), which compares the submitted
  `municipality_id` to the client's stored `city_municipality`. It is an
  anti-guessing/data-integrity check for a **public, sessionless** page — not
  an authorization mechanism.

#### 2. Schema / relationship map (ground truth: `database/schema/mysql-schema.sql`)

| Table | Municipality representation | Relationship |
|---|---|---|
| `tbl_municipalities` (`id`, `name`, `code`) | The dimension root | Only FK: `tbl_barangays.municipality_id` (CASCADE). No FK to/from any auth table. |
| `tbl_barangays` (`id`, `municipality_id`, `name`) | Real integer FK | `municipality_id` → `tbl_municipalities.id` |
| `tbl_clients` | `city_municipality` varchar(100), `barangay` varchar(100) — **int IDs stored as strings** | Unconstrained; `KEY idx_clients_muni`; joined `m.id = c.city_municipality` / `b.id = c.barangay` |
| `tbl_unpaid_verifications` | `municipality_id` int | **Real FK** → `tbl_municipalities.id` |
| `tbl_household` | None | Derived via `head_household` → `tbl_clients.city_municipality` |
| `tbl_transactions` | None | Derived via `tbl_clients.id` |
| `tbl_scholar_info`, `tbl_gip_info` | None | Derived via `client_id` |
| `tbl_payout_scans`, `tbl_payout_scans2` | None | Derived via `transaction_id` → `tbl_clients` |
| `tbl_seats`, `tbl_seats2`, `tbl_exam` | `town` (and `barangay`) as **municipality NAME strings** | Not FK-linked; display-only |
| `tbl_users`, `tbl_permissions`, `tbl_program_permissions`, `tbl_multi_device_exemptions`, `tbl_audit_logs` | **None** | — |

#### 3. Module-by-module representation

| Module | v1 | v2 |
|---|---|---|
| **Clients** | `city_municipality`/`barangay` stored as int ids (`add_client.php` L28-29 `intval()`); filter `c.city_municipality = :municipality`, `c.barangay = :brgy` (`fetch_clients.php`, `clients.php`) | `ClientRequest`: `city_municipality` `required|integer|exists:tbl_municipalities,id`; `ClientController::data()` optional municipality/barangay filter; join to names |
| **Households** | No household municipality; **household_id prefix = head client's municipality `code`/fallback name-derived code** (`add_household.php` `generate_household_id()`); list filter on head client `c.city_municipality` (`fetch_households.php`) | Same via `HouseholdService::generateHouseholdId()` (municipality code or fallback); `HouseholdController::data()` filter on head client |
| **Transactions** | No column; filter + CSV sections via client join `c.city_municipality` (`all_transactions.php`, `fetch_transactions.php`); `store` inserts for POSTed client with **no municipality check** | No column; `TransactionController::data()/export()` optional filter via client join; `store()` validates only `client_id exists` |
| **Scholars** | `fetch_scholars.php` shows **`ex.town`/`ex.barangay` from `tbl_exam`** (exam-record names, NOT the client's municipality); **no municipality filter** | `ScholarController::data()` identical (tbl_exam join by normalized name); no municipality filter |
| **GIP** | `save_gip.php` — **no municipality references**; `tbl_gip_info` has no column | `GipController::store()` (via client) — no municipality |
| **Scanner engine** | `scanner_payout_action.php`/`scanner_toda_action.php`: seats `s.town` display-only; lookup/save accept POSTed transaction/client ids with **no municipality restriction** | `ScanService::lookup()/save()` — same; client municipality resolved to a name for display only (L111-112, L665-667) |
| **Payout attendance** | `scanned_payouts*.php` + `fetch_scanned_payouts*.php`: filter `c.city_municipality`; `municipality_name` via join | `PayoutAttendanceController`: filter `c.city_municipality`; same display join |
| **Unpaid verifications** | `unpaid_verifications.php` + `fetch_unpaid_verifications.php` + `export_unpaid_verifications.php`: filter **`uv.municipality_id`** (the row's own FK) | `UnpaidVerificationController::data()/export()` — same filter on `uv.municipality_id` |
| **Reports** | `scholarship_reports.php` + `fetch/export_scholarship_reports.php`: filter `c.city_municipality` | `ReportController::scholarshipData()/scholarshipExport()` — same |
| **Duplicates** | v1 groups duplicates by (lastname, firstname, middlename, municipality) | `DuplicateService` — same grouping includes `city_municipality`; filter available |
| **Audit** | `tbl_audit_logs` no municipality; feed joins `tbl_users` only | `AuditService` — no municipality dimension |
| **Grantee self-service (public)** | `search_grantee.php` / `search_unpaid_grantee.php`: verify compares `intval(client.city_municipality) !== submitted municipality_id` → "Municipality does not match our records."; `unpaid_save.php` requires `municipality_id` but **never cross-checks the client** | `GranteeSearchController::verify` — same integrity comparison; `UnpaidVerificationController::store` ports `unpaid_save.php` |
| **Grantee self-update (public)** | municipality/barangay preserved, not editable | `GranteeUpdateService::PRESERVED` includes `city_municipality`/`barangay` |

#### 4. V1 enforcement

- Municipality is **always an optional, user-supplied filter** (POST/GET), never
  a scope: omitted → full dataset (`fetch_clients.php`, `fetch_transactions.php`,
  `all_transactions.php`, `fetch_scanned_payouts*.php`, `fetch_scholarship_reports.php`,
  `fetch_unpaid_verifications.php`).
- **Action endpoints do not include `restriction.php`** and never validate
  municipality: `unpaid_save.php`, `scanner_payout_action.php`,
  `scanner_toda_action.php`, `save_gip.php`, `save_scholarship.php`,
  `scanner_new_scholars_action.php`, `scanner_ongoing_scholars_action.php`,
  `scanner_payout_unpaid_action.php` (the last four have **no municipality
  references at all**).
- The **only** municipality check is the public self-service verify
  (`search_grantee.php`/`search_unpaid_grantee.php` L78-81) — integrity, not
  authz (no session, no user context).
- **Data-integrity gap**: `unpaid_save.php` stores the submitted
  `municipality_id` on the new row without verifying it equals the client's
  `city_municipality`.

#### 5. V2 enforcement

- Identical semantics: municipality is an optional filter parameter on
  `ClientController::data`, `TransactionController::data/export`,
  `DuplicateController::data`, `HouseholdController::data`,
  `PayoutAttendanceController::data`, `ReportController::scholarshipData/
  scholarshipExport`, `UnpaidVerificationController::data/export`.
- `AccessControlService` + `AuthorizePage` (`page:`) gates are the **only**
  server-side authorization and carry no municipality dimension.
- `ClientRequest` enforces `exists:tbl_municipalities,id` on writes, but no
  scope on reads/writes; `TransactionController::store` checks only client
  existence.
- Public self-service (`GranteeSearchController::verify`) ports the v1
  integrity comparison; `UnpaidVerificationController::store` ports
  `unpaid_save.php` **including its missing client cross-check** (parity).
- No per-user, per-role, or per-session municipality concept exists anywhere.

#### 6. Authorization capability matrix (municipality / data scope)

| Capability | V1 | V2 |
|---|---|---|
| Municipality stored on clients | SUPPORTED (`varchar` int ids) | SUPPORTED (same; validated `exists:`) |
| Municipality as list/report filter | SUPPORTED (optional) | SUPPORTED (optional) |
| Municipality as export filter | SUPPORTED (optional GET param) | SUPPORTED (optional) |
| Municipality display resolution (names) | SUPPORTED (joins) | SUPPORTED (joins/relations) |
| Municipality FK integrity on clients | PARTIALLY SUPPORTED (unconstrained varchar; numeric coercion on join) | PARTIALLY SUPPORTED (same schema) |
| Municipality FK integrity elsewhere | PARTIALLY SUPPORTED (`tbl_unpaid_verifications` only) | PARTIALLY SUPPORTED (same) |
| Public grantee municipality-match check | SUPPORTED (integrity only) | SUPPORTED (integrity only) |
| Writes cross-checked against client municipality | **NOT SUPPORTED** (`unpaid_save.php`, scanner saves) | **NOT SUPPORTED** (parity port) |
| Municipality-scoped authorization | **NOT SUPPORTED** | **NOT SUPPORTED** |
| Per-user municipality restriction | **NOT SUPPORTED** | **NOT SUPPORTED** |
| Municipality in auth tables / ACL | **NOT SUPPORTED** | **NOT SUPPORTED** |

#### 7. Direct-request / export / report bypass findings

- **Direct feed POSTs**: `fetch_*.php` and the v2 `/data` routes accept
  municipality/barangay as optional inputs; a crafted request with the filter
  omitted (or set to another municipality) returns that dataset. There is no
  server-side gate that ties the filter to the requester.
- **Exports**: `all_transactions.php` CSV sections, `export_unpaid_verifications.php`,
  `export_scholarship_reports.php` (v1) and `transactions/export`,
  `scholarship-reports/export`, `unpaid-verifications/export` (v2) build the
  WHERE clause from GET/POST params only; omitted municipality ⇒ **full export**
  for any user who passes the page gate.
- **Scanner writes**: `scanner_payout_action.php`/`scanner_toda_action.php` and
  `ScanService::save()` accept transaction/client ids from the POST body with no
  municipality scoping — a page-gated user can write scans for any municipality.
- **Public self-service**: `unpaid-verification/submit` and `grantee-update/save`
  are sessionless by design (venue workflow); their municipality handling is the
  integrity check described above, not a bypass.
- **Conclusion**: page-gate access = full-data access. Municipality scoping, if
  ever introduced, must be enforced at the query level in every feed/export and
  at every write, not just hidden in the UI.

#### 8. Schema gaps (A–D)

- **A — Client FK integrity**: `tbl_clients.city_municipality`/`barangay` are
  `varchar(100)` holding int ids with no FK. Joins rely on numeric coercion;
  an empty/invalid value silently LEFT-JOINs to a null name. Additive fix
  candidates (not decided): real FK columns or generated lookup — both schema
  changes requiring review.
- **B — No municipality on derived tables**: `tbl_household`, `tbl_transactions`,
  `tbl_scholar_info`, `tbl_gip_info`, `tbl_payout_scans(2)` have no municipality
  column; scope must be derived through `client_id`/`transaction_id` joins
  (de-normalized queries, index-driven).
- **C — Name-string town columns**: `tbl_seats.town`, `tbl_seats2.town`,
  `tbl_exam.town`/`barangay` store municipality **names**, not ids — cannot be
  used for scoping without name→id resolution (fragile; names change).
- **D — No municipality in auth tables**: any per-municipality user scope
  (e.g., a `user_municipalities` pivot or a scope column) is a net-new additive
  schema change plus an `AccessControlService` extension plus query-level
  enforcement across every feed/export listed in §5.

#### 9. Implications for fine-grained authorization (Open Decision #6)

- Municipality is the natural first "data scope" dimension because it exists on
  `tbl_clients` directly and is derivable from it for every other data table —
  but **v1 never scoped by it**, so any scoping is a **v2 addition**, not parity.
- Enforcement point is the **query layer** (feeds, exports, writes), not the
  page gate; a `page:`-gated user currently sees/writes every municipality.
- Seat/exam town strings (§8-C) cannot participate in scoping without id
  resolution; scope must key off `tbl_clients.city_municipality` and
  `tbl_unpaid_verifications.municipality_id`.
- Schema changes would be **additive and reviewed** (per AGENTS.md) and would
  touch the baseline dump; this is why the decision is deferred pending the
  authorization-architecture research pass, not settled here.

#### 10. Next research passes

- **Pass 3 (recommended next) — Live production-data snapshot** (the query plan
  formerly labeled "Pass 2" in §6 above, renumbered to avoid collision):
  `SELECT DISTINCT page_name FROM tbl_permissions`, `'*'` row check,
  `can_access = 0` count, `tbl_program_permissions.program_name` catalog vs the
  v1 17-program list, and route→page-key completeness.
- **Pass 4 — Per-municipality scope design exploration**: concrete additive
  schema options (pivot table vs scope column), impact inventory over every
  query in §5, backward compatibility, and UI implications for P7 admin screens.
- **Pass 5 — Write-integrity audit**: cross-check enforcement for
  `unpaid_save`/scanner saves/transaction store (whether to keep v1-parity
  gaps or close them as v2 improvements), plus export-bypass mitigations if
  scoping is adopted.

---

### Pass 3 — Live Production Data Snapshot (2026-08-15)

> **Scope**: Read-only snapshot of the local `main_system` database
> (`DB_DATABASE=main_system`, `DB_HOST=127.0.0.1`, user `root`) plus
> code/config cross-checks. Only `SELECT` queries were run; nothing was
> inserted, updated, deleted, migrated, or altered. No authorization decision
> made; Open Decision #6 remains `DEFERRED — REQUIRES AUTHORIZATION
> ARCHITECTURE RESEARCH`.
>
> **Method**: `mysql -u root main_system` read-only queries against the
> permission/program-permission/exemption/user/audit/reference tables; catalog
> comparison against `manage_program_permissions.php` (v1), the
> `tbl_transactions.program` enum, `TransactionService::PROGRAMS`,
> `config/scanner.php`, `config/payout.php`, and `routes/web.php`; a
> full `grep` of `include 'restriction.php'` across all v1 files.
>
> **Critical premise to report up front**: the local `main_system` is **not a
> populated production reference copy** — it is the local development
> environment (0 reference rows; auth tables carry only dev seeding). Findings
> below therefore describe the *local* state; production-data assumptions are
> marked INFERENCE and must be validated against the real production DB before
> P7 ships.

#### 1. Database snapshot summary

Observed row counts (2026-08-15, read-only):

| Table | Rows |
|---|---|
| `tbl_users` | 1 |
| `tbl_permissions` | 1 |
| `tbl_program_permissions` | 0 |
| `tbl_multi_device_exemptions` | 0 |
| `tbl_audit_logs` | 3 |
| `tbl_municipalities` / `tbl_barangays` | 0 / 0 |
| `tbl_clients` / `tbl_transactions` | 0 / 0 |
| `tbl_household` | 0 |
| `tbl_seats` / `tbl_seats2` / `tbl_exam` | 0 / 0 / 0 |

- **FACT**: The only user is `jordi` (id 3, created 2026-08-12, last activity
  2026-08-13).
- **FACT**: The only permission row is `(id=1, user_id=3, page_name='*',
  can_access=1)` — the `AccessControlSeeder` row, whose docblock states
  production cutover does **not** run it and v1 permission rows are carried
  over with the data.
- **FACT**: `tbl_audit_logs` holds 3 rows, all `LOGIN` / `tbl_users`, by user 3.
- **FACT**: The `migrations` table records the 9 real migrations (batch 1) but
  **no `__legacy_v1_baseline_schema__` sentinel** — this deviates from the
  AGENTS.md-described state. Because 9 real migrations are recorded, `php
  artisan migrate` will not reload the drop-and-reload schema dump (still
  safe), but this DB is not in the documented state.
- **INFERENCE**: every "production snapshot" conclusion below that relies on
  permission data is really describing **dev seeding**, not production.

#### 2. Actual page_name catalog

Query: `SELECT DISTINCT page_name FROM tbl_permissions ORDER BY page_name;`

- **FACT**: exactly **one** distinct key exists: `*` (1 row).
- **FACT**: **no** v1 page keys (`clients.php`, `all_transactions.php`,
  `scanner_*.php`, …) exist in the data; no duplicates; the v1 32-entry
  hard-coded catalog is **unrepresented**.
- **FACT**: none of the admin keys exist locally:
  `manage_permissions.php`, `manage_program_permissions.php`,
  `manage_multi_device_exemptions.php`, `audit_logs.php`.
- **INFERENCE**: nothing can be validated about the production page catalog
  from this copy; the real `page_name` catalog is only knowable from the
  production DB (or from `tbl_permissions` rows carried over at cutover).

#### 3. `'*'` super-admin findings

Query: `SELECT * FROM tbl_permissions WHERE page_name = '*';`

- **FACT**: exactly one `'*'` row: `id=1, user_id=3 (jordi), can_access=1`.
- **FACT**: provenance is the local `AccessControlSeeder` (dev-only).
- **INFERENCE**: v1 never created `'*'` rows (its admins were username/id
  checks), so a genuine production copy is expected to have **no** `'*'` row.
  The local presence is a dev artifact, not production evidence.
- **UNRESOLVED**: whether production actually carries a `'*'` row cannot be
  answered from this copy — this is exactly the bootstrapping question
  (Open Decision #2).

#### 4. can_access = 0 findings

Query: `SELECT COUNT(*) FROM tbl_permissions WHERE can_access = 0;`

- **FACT**: `0` rows locally.
- **FACT** (code): v1 only ever inserts `can_access=1` or no row; `restriction.php`
  checks row presence, so a missing row already means denial.
- **FACT** (v2 `AccessControlService`, read in full): `isSuperAdmin()` requires
  `page_name='*' AND can_access` truthy; `canAccessPage()` requires the page row
  to have truthy `can_access` (after the super-admin short-circuit);
  `permittedPages()` filters `can_access = true`. A `can_access=0` row is
  therefore an **explicit denial** — no code path turns it into a grant.
- **INFERENCE**: if production ever carried `can_access=0` rows, v2 would deny
  those page grants, which is consistent with v1's absence-as-denial semantics;
  no unexpected behavior observed. The Pass 1 edge case (0-row interplay with
  `'*'`) is moot: a `'*'` row with `can_access=0` simply does not make that user
  a super-admin.

#### 5. Program catalog comparison

Query: `SELECT DISTINCT program_name FROM tbl_program_permissions;` → **0 rows** locally.

- **FACT**: no data-level comparison is possible; the catalog comparison is
  therefore code/schema-only.
- **FACT**: three catalogs are **set-identical, 17 programs**:
  - v1 `manage_program_permissions.php` hard-coded array;
  - `tbl_transactions.program` ENUM
    (`AICS,AKAP,MAIP,TUPAD,CEDSSG,CEAP,CEAP_NEW,OTCES,OTEA,CEDSSG_NEW,
    COFFEE GROWERS,PUSO TI KABABAIHAN,PUSO TI AGTUTUBO,PUSO TI MANNALON,
    TESDA,GIP,TODA`);
  - `TransactionService::PROGRAMS`.
- **FACT**: ordering differs (`TransactionService` lists `CEDSSG_NEW` before
  `OTEA`/`OTCES`; enum/v1 list `OTCES, OTEA, CEDSSG_NEW`) — irrelevant for
  authorization (set equality, not order).
- **FACT**: **no** spelling/casing/whitespace differences and **no** extra
  programs between the three catalogs.
- **FACT**: `GranteeUpdateService::ALLOWED_PROGRAMS` (6: `CEAP, CEAP_NEW,
  CEDSSG, CEDSSG_NEW, OTEA, OTCES`) and the per-scanner program lists in
  `config/scanner.php` are behavior subsets, not the permission catalog.
- **INFERENCE**: a P7 program-permission screen can be driven from
  `TransactionService::PROGRAMS` (or the enum) with zero mismatch risk.

#### 6. User/permission distribution

| Dimension | Value |
|---|---|
| Total users | 1 (`jordi`, id 3) |
| Users with page permissions | 1 |
| Users with **zero** page permissions | 0 |
| Users with `'*'` permission | 1 |
| Users with program permissions | 0 |
| Users with multi-device exemptions | 0 |

- **FACT**: the distribution is 100% dev seeding — no production
  user-administration pattern is observable.
- **INFERENCE**: no role-like pattern can be inferred; none should be asserted
  (no roles are explicitly represented in the schema or data).

#### 7. P7 admin bootstrapping status

- **FACT**: a `'*'` row exists locally for `jordi`, so under the current v2
  model (`canAccessPage` → `isSuperAdmin`) that user can reach **any** page
  gate, including future P7 admin gates.
- **FACT**: no admin page keys exist in the data (only `'*'`).
- **INFERENCE**: "reach all P7 admin screens" under the current v2 model
  requires **either** one `'*'` row (`can_access=1`) **or** the four admin page
  keys (`manage_permissions.php`, `manage_program_permissions.php`,
  `manage_multi_device_exemptions.php`, `audit_logs.php`) granted to at least
  one user. The local `'*'` row must not be replicated blindly — it is a dev
  seeder artifact.
- **UNRESOLVED** (Open Decision #2, not decided here): exactly what production
  data must exist before P7 is usable — a `'*'` row, or the four admin keys —
  and by what reviewed mechanism it is introduced (data carryover vs reviewed
  seed). **Nothing was created.**

#### 8. v1 → v2 route/page-key completeness

- **FACT**: exactly **27** v1 files `include 'restriction.php'` (full grep
  confirmed; matches ADMIN_ANALYSIS §1.7).
- **FACT**: v2 route gates use matching keys for **22** of them:

| v1 page (restriction-gated) | v2 gate | Key match |
|---|---|---|
| `clients.php` | `page:clients.php` (clients, duplicates, photos, GIP, family members) | ✅ |
| `household.php` | `page:household.php` (households) | ✅ (v1 was session-only — hardening) |
| `all_transactions.php` | `page:all_transactions.php` (transactions incl. export/inline-update) | ✅ |
| `scholars.php` | `page:scholars.php` | ✅ |
| `scholarship_reports.php` | `page:scholarship_reports.php` (incl. export) | ✅ |
| `scanned_payouts.php` | `page:scanned_payouts.php` | ✅ |
| `scanned_payouts2.php` | `page:scanned_payouts2.php` | ✅ |
| `scanned_payouts_unpaid.php` | `page:scanned_payouts_unpaid.php` | ✅ |
| `scanner_ceap.php` | `page:scanner_ceap.php` | ✅ |
| `scanner_ceap_new.php` | `page:scanner_ceap_new.php` | ✅ |
| `scanner_cedssg.php` | `page:scanner_cedssg.php` | ✅ |
| `scanner_cedssg_new.php` | `page:scanner_cedssg_new.php` | ✅ |
| `scanner_cedssg_update.php` | `page:scanner_cedssg_update.php` | ✅ |
| `scanner_otces.php` | `page:scanner_otces.php` | ✅ |
| `scanner_otea.php` | `page:scanner_otea.php` | ✅ |
| `scanner_toda.php` | `page:scanner_toda.php` | ✅ |
| `scanner_tupad.php` | `page:scanner_tupad.php` | ✅ |
| `scanner_new_scholars.php` | `page:scanner_new_scholars.php` | ✅ |
| `scanner_ongoing_scholars.php` | `page:scanner_ongoing_scholars.php` | ✅ |
| `currently_logged_users.php` | `page:currently_logged_users.php` | ✅ |
| `force_logout.php` | `page:force_logout.php` | ✅ |
| `unpaid_verifications.php` + `fetch_unpaid_verifications.php` + `export_unpaid_verifications.php` | `page:unpaid_verifications.php` (index/data/export) | ✅ |

- **FACT**: the 4 P7 deliverables have **no v2 counterpart yet** (correct —
  they are the P7 build): `register.php`, `manage_permissions.php`,
  `manage_program_permissions.php`, `manage_multi_device_exemptions.php`.
- **FACT — hardening (v1 had no gate)**: `scanner_generic.php`,
  `scanner_payout.php`, `scanner_payout_unpaid.php`, and `update_logs.php` were
  **session-only** in v1 (not in the 27) but v2 gates them
  (`page:scanner_generic.php` etc., `page:update_logs.php`). This is the
  intentional R6 hardening documented in `SCANNER_ANALYSIS.md`.
- **FACT — action endpoints**: v1 action files (`scanner_*_action.php`,
  `delete_*.php`, `save_*.php`, `unpaid_save.php`, `add_*.php`) are session-only
  in v1; v2 nests their equivalents **inside** the same `page:` route groups —
  the Pass 1 action-endpoint gap is closed.
- **FACT**: `audit_logs.php`/`fetch_logs.php`/`fetch_leaderboard.php` are
  **username-gated** in v1 (not restriction-gated); P7 will gate them with page
  keys (contract decision; keys TBD).
- **FACT**: `manage_php.php` is deliberately excluded (blueprint A10).
- **FACT**: public self-service pages (login/logout, `student/verify`,
  `student/photo-upload`, `qr-viewer`, `grantee-search`, `unpaid-verification`,
  `grantee-update`) are intentionally top-level in v2, mirroring v1's
  sessionless venue pages.

#### 9. Scanner/program relationship findings

- **FACT**: 14 scanner keys in `config/scanner.php`, each gated
  `page:scanner_{key}.php` in `routes/web.php`.
- **FACT**: per-scanner program lists (behavioral): `ceap`→CEAP;
  `ceap_new`→CEAP_NEW; `cedssg`→CEDSSG; `cedssg_new`→CEDSSG_NEW;
  `cedssg_update`→CEDSSG, CEDSSG_NEW; `otces`→OTCES; `otea`→OTEA;
  `toda`→TODA; `tupad`→TUPAD; `generic`→AICS, AKAP, MAIP, TUPAD, CEDSSG, CEAP;
  `new_scholars`→CEAP_NEW, OTEA, OTCES, CEDSSG_NEW (exam-derived);
  `ongoing_scholars`→CEAP, CEAP_NEW, CEDSSG, CEDSSG_NEW;
  `payout`→CEAP, CEDSSG, CEAP_NEW, CEDSSG_NEW, OTEA, OTCES;
  `payout_unpaid`→CEAP, CEAP_NEW, OTEA, OTCES.
- **FACT** (code): scanner show/lookup/save routes are gated **only** by the
  page gate — `AccessControlService::canAccessProgram()` is **not** consulted
  on any scanner route, and `ScanService` performs no program-permission check.
- **FACT**: scanner access therefore exists **independently** of program
  permission: a user with the `scanner_ceap.php` page row but no `CEAP` program
  row can operate that scanner, and a user with `CEAP` program permission but no
  scanner page row cannot. The two dimensions are orthogonal in both v1 and v2
  (v1 never enforced program scope on scanners).
- **INFERENCE**: this matches Pass 1 limitation #5 and Pass 1 Unresolved
  Question #4.
- **UNRESOLVED** (architectural question, **not decided**): whether scanner
  routes should additionally enforce `canAccessProgram()`.

#### 10. Newly discovered authorization risks / inconsistencies

1. **Production data is unverifiable from this copy** — the local DB holds no
   production permission data, so the real page catalog, `'*'` presence, and
   admin-key presence are **unknown**. This is the single largest open
   dependency for P7 deployability. (INFERENCE: the Pass 1 §5 bootstrapping
   risk stands unchanged and unverified.)
2. **Local `'*'` row is a dev artifact** — it exists only because
   `AccessControlSeeder` ran locally; it must not be treated as evidence that
   production admins exist. (FACT + INFERENCE.)
3. **`migrations` table state deviates from AGENTS.md** — no
   `__legacy_v1_baseline_schema__` sentinel row (9 real migrations present,
   so `migrate` is still safe). (FACT; flagged, not acted on.)
4. **`can_access=0` is safe but unexercised** — v2 semantics (denial) are
   consistent with v1; 0 rows exist nowhere. (FACT + INFERENCE.)
5. **Scanner program-gate orthogonality** — a user can operate a scanner for a
   program they have no program permission for (and vice-versa); unchanged from
   v1. (FACT; decision deferred.)
6. **P7 admin gating keys are TBD** — the four admin screens have no v2 page
   key yet; the keys chosen must match whatever production rows exist/carry
   over. (UNRESOLVED.)

#### 11. Questions that remain for Pass 4

1. What is the **real production page_name catalog**? (Requires the actual
   production DB or the carried-over data, not the local dev copy.)
2. Does production contain a `'*'` row? Admin-key rows? (Bootstrapping.)
3. Do production `tbl_program_permissions` rows exactly match the 17-program
   catalog, or diverge?
4. What page keys should the four P7 admin screens use so they align with
   production data once reviewed?
5. Should scanner routes additionally enforce `canAccessProgram()`?
6. If municipality/data-scope is adopted (Open Decision #6), what production
   data would model the scope on — and which enforcement points
   (feeds/exports/writes) must change? (Pass 2 schema gaps A–D apply.)
7. Which reviewed mechanism grants the first production admin at cutover
   (data carryover vs reviewed seed), and does the `'*'`-row contract need any
   production guard?

### Pass 4 — Production Data-Carryover Validation (2026-08-15)

> **Scope**: Determine whether a real production (or production-carryover)
> database exists locally, and if so validate the permission/program data
> read-only. Only `SELECT`/`SHOW` queries and file reads were run; nothing was
> inserted, updated, deleted, migrated, altered, or seeded. No authorization
> decision made; Open Decision #6 remains `DEFERRED — REQUIRES AUTHORIZATION
> ARCHITECTURE RESEARCH`.
>
> **Method**: `SHOW DATABASES` over all 14 local schemas; read-only row counts
> and `SHOW TABLES` on every candidate MIS schema (`main_system`,
> `main_system_test`, `clients_system`, `system101`); read-only inspection of
> every `.sql` dump under `C:\xampp\htdocs\system\`; `SELECT VERSION()` of the
> local server; v1 `db_connect.php` read for the live DB name.
>
> **Up-front verdict**: **the real production database is NOT available
> locally, and no production data-carryover copy exists.** The only genuine
> production artifact is a **schema-only** phpMyAdmin dump of the production
> host's database (`u749085076_main_system.sql`, 0 data rows). Per the Pass 4
> contract, the data-validation portion stops here: catalog, `'*'`, denial
> rows, user distribution, and program distribution remain **UNVERIFIED**
> against production. What the dump *does* verify (production schema for every
> authorization table) is recorded as FACT below.

#### 1. Database identity / provenance

- **FACT**: `SHOW DATABASES` lists 14 schemas: `candon_arena`, `ccis-portal`,
  `ceap_appointments`, `clients_system`, `information_schema`, `main_system`,
  `main_system_test`, `mysql`, `performance_schema`, `phpmyadmin`,
  `scratch_test`, `system101`, `test`, `wordpress`.
- **FACT**: v1 live DB name (`C:\xampp\htdocs\system\db_connect.php`) is
  `main_system` — the same name v2's `.env` targets. Production hosting DB is
  `u749085076_main_system` (cPanel-style owner prefix), per the dump header.
- **FACT**: local MySQL is `10.4.32-MariaDB` (XAMPP). The production dump
  header reports `11.8.8-MariaDB-log` — a **different host** (production
  cPanel), not the local server.
- **FACT**: two SQL dumps exist under `C:\xampp\htdocs\system\`:
  - `main_system.sql` — local export, 2025-08-28, server `10.4.32-MariaDB`,
    **6** `CREATE TABLE`, 5 `INSERT` rows, **old schema** (includes `tbl_aics`;
    `tbl_users` without `session_token`). Historical dev artifact — **not**
    current production schema, not production data.
  - `u749085076_main_system.sql` — **production host export**, 2026-08-04,
    server `11.8.8-MariaDB-log`, database `u749085076_main_system`, **31**
    `CREATE TABLE` and **0** `INSERT` — a **schema-only dump of the real
    production database**, containing no data.
- **FACT**: `main_system_test` is the PHPUnit target — full schema, **0 rows**
  in every table including `tbl_users`/`tbl_permissions`/`tbl_transactions`.
- **FACT**: `clients_system` is a separate, populated DB (1 user, 34
  municipalities, 139 barangays, 5000 clients, 0 household) that v1 **does
  not connect to** — not the 2D MIS database, not authorization evidence.
- **FACT**: `system101` is an unrelated Laravel app (registrations/users).
- **INFERENCE**: no production data snapshot exists anywhere on this machine;
  production *data* carryover cannot be validated before cutover.
- **INFERENCE**: the 34 municipalities / 139 barangays observed in
  `clients_system` are plausible reference-data magnitudes, but they belong to
  a different system and are **not** evidence about `main_system` content.

#### 2. Production page_name catalog

- **FACT**: not observable — the production dump carries **no data rows**, so
  no `tbl_permissions` row contents exist to read.
- **INFERENCE**: the real production page catalog remains unknown; only the v1
  code catalog (`restriction.php` consumers + username-gated admin screens) and
  the local dev catalog (just `'*'`) are known.

#### 3. `'*'` super-admin row in production

- **FACT**: not observable (no production data).
- **INFERENCE** (carried from Pass 3): v1 never created `'*'` rows, so
  production is expected to have none; the local `'*'` row is a dev seeder
  artifact. The bootstrapping question (Open Decision #2) is **still
  unresolved** and unverified.

#### 4. P7 admin keys in production

- **FACT**: not observable (no production data); none of
  `manage_permissions.php`, `manage_program_permissions.php`,
  `manage_multi_device_exemptions.php`, `audit_logs.php` exist as v2 gates yet
  (P7 build). The keys chosen for the P7 gates must align with whatever rows
  production carries over. (UNRESOLVED.)

#### 5. can_access = 0 denial rows

- **FACT**: not observable (no production data). Local count remains 0.
- **FACT** (schema, from the production dump): `tbl_permissions.can_access` is
  `tinyint(1) DEFAULT 1` — the schema permits `0`; v2 treats a `0` row as
  explicit denial (Pass 3 §4). No data to confirm production usage.

#### 6. User/permission distribution

- **FACT**: not observable (no production data). Local distribution (1 user /
  1 `'*'` row / 0 program rows / 0 exemptions) is 100% dev seeding.

#### 7. Program catalog — production schema verification

- **FACT** (production dump): `tbl_transactions.program` ENUM on the production
  host is exactly:
  `AICS, AKAP, MAIP, TUPAD, CEDSSG, CEAP, CEAP_NEW, OTCES, OTEA, CEDSSG_NEW,
  COFFEE GROWERS, PUSO TI KABABAIHAN, PUSO TI AGTUTUBO, PUSO TI MANNALON,
  TESDA, GIP, TODA` — **17 programs, set-identical** to the local enum, to v1
  `manage_program_permissions.php`, and to `TransactionService::PROGRAMS`.
- **FACT**: this closes the Pass 3 §5 verification gap **for the schema** — the
  production catalog is now verified at the schema level (still not at the data
  level, since `tbl_program_permissions` rows are absent from the dump).
- **INFERENCE**: a P7 program-permission screen driven from
  `TransactionService::PROGRAMS` matches the production enum exactly.

#### 8. Production program distribution

- **FACT**: not observable (no production data rows). Which users hold which
  `tbl_program_permissions` rows, and whether any diverge from the catalog,
  remains UNVERIFIED.

#### 9. P7 admin bootstrapping assessment (reconciled)

- **FACT**: production data needed to bootstrap P7 (a `'*'` row **or** the four
  admin page keys for at least one user) **does not exist in any local copy**;
  the schema-only dump confirms only that the storage is in place.
- **INFERENCE**: P7 cannot be made usable at cutover from local evidence alone;
  the first production admin must be granted by a **reviewed mechanism** (data
  carryover with the v1→v2 permission mapping, or an explicit reviewed seed) —
  unchanged from Pass 1 §5 / Open Decision #2.

#### 10. Reconciliation with Pass 3

- **FACT**: Pass 3's "local `main_system` is dev-only, not a production copy"
  premise is **confirmed** — no populated production database or dump exists.
- **FACT**: Pass 3's table-level catalog, user distribution, and `'*'` findings
  remain valid descriptions of the **local** DB and are unchanged.
- **FACT**: the Pass 3 §5 program-catalog identity is now **upgraded to
  schema-verified** against production (Pass 4 §7), not merely
  code/local-enum-verified.
- **FACT**: the 31-table production dump set matches the local `main_system`
  legacy set exactly (local adds only the Laravel infra tables:
  `migrations`, `users`, `password_reset_tokens`, `sessions`, `cache`,
  `cache_locks`, `jobs`, `job_batches`, `failed_jobs`).
- **FACT**: Pass 3 §1's deviation stands — local `migrations` has the 9 real
  migrations and no `__legacy_v1_baseline_schema__` sentinel. (Flagged; not
  acted on.)
- **INFERENCE**: v2's permission/program/exemption/audit model is
  column-identical to the production schema for every table it reads/writes
  (Pass 4 §11), so no schema-driven rework of the ACL model is needed.

#### 11. Verified production-schema facts (authorization tables)

From `u749085076_main_system.sql` (read-only), production schema matches the
v2 model exactly:

| Production table | Production columns (dump) | v2 model match |
|---|---|---|
| `tbl_permissions` | `id, user_id, page_name varchar(100), can_access tinyint(1) DEFAULT 1` | ✅ `Permission` |
| `tbl_program_permissions` | `id, user_id, program_name varchar(100)` | ✅ `ProgramPermission` |
| `tbl_multi_device_exemptions` | `id, user_id, created_at` | ✅ `MultiDeviceExemption` |
| `tbl_users` | `id, username varchar(100), password, created_at, last_activity, session_token` | ✅ `User` (no email; no `active` column — v1 has no disable) |
| `tbl_audit_logs` | `id, user_id, action varchar(50), target_table, target_id, old_value, new_value, created_at` | ✅ `AuditService` contract |
| `tbl_transactions.program` | 17-program enum (Pass 4 §7) | ✅ `TransactionService::PROGRAMS` |

- **FACT**: production `tbl_users` has **no** `email` and **no** `active`
  column — confirms username-only auth and the open `active`-column question
  for P7 user disable/enable (additive migration path, not decided).

#### 12. Facts (summary)

1. No production **data** is available locally — the sole production artifact
   is a schema-only dump (`u749085076_main_system.sql`, 0 rows).
2. The production **schema** is verified: all authorization tables and the
   program enum match the v2 model byte-for-byte.
3. The 31-table production set equals the local `main_system` legacy set plus
   only Laravel infra tables.
4. `main_system_test` is empty; `clients_system` is a different system's
   populated DB; `system101` is unrelated.
5. Local `migrations` sentinel deviation from AGENTS.md is unchanged.

#### 13. Inferences

1. Production page catalog, `'*'` presence, admin keys, denial rows, and
   permission distributions are **unknown** and **cannot be verified before
   cutover** from local evidence.
2. v1 never wrote `'*'` rows, so production is expected to have none; the
   local `'*'` row must not be replicated blindly.
3. P7 admin bootstrapping requires a reviewed mechanism at cutover
   (Open Decision #2, still open).

#### 14. Unresolved (for the next pass)

1. Real production `page_name` catalog and whether any row carries `'*'` or the
   four P7 admin keys (requires the production DB or a data dump).
2. Production `tbl_program_permissions` contents vs the 17-program catalog.
3. Whether production carries any `can_access=0` rows.
4. Which P7 admin page keys to fix in the build so they match carried-over
   production rows.
5. Should scanner routes additionally enforce `canAccessProgram()`?
6. Municipality/data-scope adoption (Open Decision #6) — schema gaps A–D from
   Pass 2 still apply and are unaffected by this pass.
7. P7 user disable/enable needs an additive `active` column decision (no such
   column exists in production schema).

#### Recommended next pass

- **Pass 5 — P7 Admin Gating & Key Contract**: given production data is
  unavailable, design the four P7 page keys and the gating contract on the
  verified production schema (Pass 4 §11) plus v1 ground truth (P7 contract
  §2/§4), and specify the reviewed bootstrapping mechanism for the first
  production admin — still read-only research, no build. (Do not proceed until
  the user confirms.)

---

### Pass 5 — P7 Admin Gating & Key Contract (2026-08-15)

> **Scope**: Research/contract definition only — the authorization contract for
> the P7 administration pages (user creation, page permissions, program
> permissions, multi-device exemptions, audit viewer) is defined **before any
> P7 code is written**. No controllers, services, models, middleware, routes,
> migrations, schema files, views, JS/CSS, tests, or docs other than this one
> were modified. No DB reads/writes of any kind were run in this pass (all
> evidence is from the source files and the verified production **schema**
> from Pass 4). No authorization decision resolves Open Decision #6 — it stays
> `DEFERRED — REQUIRES AUTHORIZATION ARCHITECTURE RESEARCH`.
>
> **Method**: full re-read of every v1 P7 source (`restriction.php`,
> `register.php`, `add_user.php`, `manage_permissions.php`,
> `manage_program_permissions.php`, `manage_multi_device_exemptions.php`,
> `audit_logs.php`, `fetch_logs.php`, `fetch_leaderboard.php`, `sidebar.php`);
> full re-read of the v2 machinery (`AccessControlService`, `AuthorizePage`,
> `AppServiceProvider`, `AuditService`, `Permission`/`ProgramPermission`/
> `MultiDeviceExemption`/`User` models, `routes/web.php`, `config/scanner.php`,
> the sidebar partial); grep of the v2 codebase for any existing
> `manage_`/`audit_logs`/`admin.` keys (none exist). Primary ground truth:
> Pass 1–4 of this document.

#### 1. Objective

Define, on the verified v1 ground truth (Pass 1 §1) and the verified production
**schema** (Pass 4 §11):

1. The canonical v2 **page keys** for the four P7 areas plus the audit viewer.
2. The **gating strategy** per page — what v1 did vs. what v2 must enforce.
3. Confirmation of the **`'*'` super-admin contract**.
4. The **first-admin bootstrapping** design for production.
5. The **authorization implications** of each P7 capability.
6. The **audit-viewer** key + gate, keeping `AuditService` the sole writer.
7. What existing machinery is **reusable as-is** vs. what needs a new
   architecture decision.

No production permission **data** was verified (Pass 4 established only the
schema); every conclusion that depends on production *rows* is labelled
INFERENCE or UNRESOLVED.

#### 2. Four P7 Page Keys

All five keys are **v1 parity keys** (the v1 page filenames), matching ADR-003's
"`page_name` values identical to v1; seeded unchanged" guarantee. **No existing
v2 route or gate uses any of them** (verified against `routes/web.php` and a
codebase grep) — zero collision risk.

| # | Capability | Proposed exact key | v1 source | Why appropriate | Parity vs v2 naming | Existing same-name key? |
|---|---|---|---|---|---|---|
| 1 | User creation | `register.php` | `register.php` (restriction-gated; listed as a grantable page in v1 `manage_permissions.php` catalog) | `register.php` is the **grantable v1 page key**; `add_user.php` is dead (its only in-app link is the excluded `manage_php.php`; hardcoded `super_admin` gate; **not** in the v1 catalog). Folding both into one screen under the `register.php` key is the parity key choice. | **V1 parity** (the v1 page key) | No |
| 2 | Page permissions | `manage_permissions.php` | `manage_permissions.php` (restriction-gated; in v1 catalog; `$allowed_ids=[1,2]` gate commented out) | The v1 page filename is the established key; v1 catalog already grants it. | **V1 parity** | No |
| 3 | Program permissions | `manage_program_permissions.php` | `manage_program_permissions.php` (restriction-gated + `user_id in [1,2]`; in v1 catalog) | Same — v1 page filename, already a grantable catalog key. | **V1 parity** | No |
| 4 | Multi-device exemptions | `manage_multi_device_exemptions.php` | `manage_multi_device_exemptions.php` (restriction-gated + username `['super_admin','jordi']`; **not** in the v1 catalog) | The v1 page filename is the only stable name. Its absence from the v1 catalog is a known v1 catalog gap (Pass 1 §1.4: the catalog is incomplete and duplicated) — the real key set is whatever `tbl_permissions.page_name` holds, so the key stands on the filename. | **V1 parity** (key = v1 page filename; catalog gap is a v1 defect, not a key-design problem) | No |
| 5 | Audit viewer | `audit_logs.php` | `audit_logs.php` (username-gated `['super_admin','god_admin','jordi']`; **no** `restriction.php`; in v1 catalog) | The v1 page filename is the grantable catalog key. v1's username gate meant the `audit_logs.php` row was **inert** (page never ran `restriction.php`); v2 makes the row meaningful. | **V1 parity** (key) + **v2 security** (now enforced via the row, not usernames) | No |

Feed endpoints do **not** get keys: `fetch_logs.php` and `fetch_leaderboard.php`
nest inside the `audit_logs.php` group (the established v2 pattern — every
`/data`/`/export` feed is nested in its page's `page:` group, see `routes/web.php`).

**Collision risk assessment**: none. `register.php`/`manage_permissions.php`/
`manage_program_permissions.php`/`manage_multi_device_exemptions.php`/
`audit_logs.php` appear nowhere in v2 routes, gates, config, or views. Route
*names* are separate from page *keys* (e.g. `admin.users.create` behind
`page:register.php`), so the Laravel default `register` route name space is not
a collision — no framework `register` route exists in `web.php`.

#### 3. P7 Page-Gating Contract

Per page: required `page:` permission, `'*'` interplay, program permission,
normal-user availability, v1 behavior, v2 contract. `'*'` satisfies every P7
gate by construction (`canAccessPage` short-circuits on `isSuperAdmin`).

| Page key | Required permission | `'*'` satisfies? | Program permission required? | Available to normal users? | V1 behavior (parity) | V2 contract (security/architecture) |
|---|---|---|---|---|---|---|
| `register.php` | `page:register.php` row **or** `'*'` | ✅ | No | Only if explicitly granted the row (v1 parity); in practice admin-issued because only admins can grant the row | **V1 PARITY**: `register.php` was reachable by any logged-in user holding a `register.php` row (restriction DB check); `add_user.php` was `super_admin`-username-only. Users created with **no permissions** (username+password only) | **V2**: one gate, `page:register.php`. Admin-only emerges from the grant model — only a permission-admin can issue `register.php` rows — not from a hardcoded check. Do **not** add an `isSuperAdmin` check on top of the page key (that would make the key meaningless and reintroduce a second admin rule). The `'*'` row satisfies the key like any other page |
| `manage_permissions.php` | `page:manage_permissions.php` row **or** `'*'` | ✅ | No | Only if granted the row; the most privileged P7 screen (can grant `'*'` itself) | **V1 PARITY**: restriction-gated, but the `$allowed_ids=[1,2]` block is **commented out** → any logged-in user with the page row could edit permissions. Save = full-replace `DELETE`+`INSERT`, `can_access` always `1`; no audit | **V2**: page key enforced. A holder of this key IS a permission admin by definition (ADR-003). Must show the `'*'` row as an explicit, confirmed toggle (it is absent from `permittedPages()` — Pass 1 §2.1 — so the normal page list cannot render it). Every save audited via `AuditService` (`MANAGE_*`) |
| `manage_program_permissions.php` | `page:manage_program_permissions.php` row **or** `'*'` | ✅ | No — a program holder for one program must **not** implicitly edit others' program grants; only the page row / `'*'` authorizes the screen | Only if granted the row | **V1 PARITY**: restriction-gated + `user_id in [1,2]` (id-based admin). Save = full-replace; 17-program hardcoded catalog; no audit | **V2**: page key only. Program list sourced from `TransactionService::PROGRAMS` (set-identical to the verified production enum, Pass 4 §7) so admins cannot create orphan keys. Save audited |
| `manage_multi_device_exemptions.php` | `page:manage_multi_device_exemptions.php` row **or** `'*'` | ✅ | No | Only if granted the row; privileged (weakens single-device security) | **V1 PARITY**: restriction-gated + username `['super_admin','jordi']`. Idempotent toggle (`DELETE` then conditional `INSERT`). Picker **excludes** `super_admin`/`jordi` | **V2**: page key only. Idempotent toggle kept. Picker exclusion becomes **data-driven**: exclude `'*'` holders (they are already exempt — `isSingleDeviceExempt → isSuperAdmin`), replacing the hardcoded name list. Save audited |
| `audit_logs.php` | `page:audit_logs.php` row **or** `'*'` | ✅ | No | Only if granted the row; read-only | **V1 PARITY**: username `['super_admin','god_admin','jordi']`, **no** `restriction.php` → the `audit_logs.php` row was inert. Feeds were weaker: `fetch_logs.php` session-only, `fetch_leaderboard.php` **no session check at all** (public!) | **V2**: page key enforced on the viewer **and** both feeds (nested in the group). This closes the v1 gap where the data feeds were reachable without the viewer's gate. Read-only; `AuditService` remains the sole writer |

**Key design decision (recommended)**: all five keys are ordinary page keys
satisfied by `'*'` **or** a row. No P7 screen adds an extra `isSuperAdmin`
requirement — the admin-ness of the screens is established by the fact that
only permission-admins can grant the rows. This preserves ADR-003 (permission
rows are the role) and the P1–P6 page-key convention (SESSION_HANDOFF "All
routes behind `page:` gates with the v1 page keys; `'*'` row is the only admin
marker").

#### 4. `'*'` Super-Admin Contract

Confirmed against the v2 architecture (Pass 1 §2, `AccessControlService` read
in full this pass):

- **FACT**: `page_name = '*'` (`AccessControlService::SUPER_ADMIN_PAGE`) is the
  **only** v2 super-admin marker.
- **FACT**: no username-based or user-ID-based admin checks exist anywhere in
  v2 (grep across `app/` and `routes/`; `AccessControlService` implements every
  decision).
- **FACT**: `isSuperAdmin()` remains the canonical mechanism; `canAccessPage`,
  `canAccessProgram`, and `isSingleDeviceExempt` all derive from it.
- **FACT**: P7 authorization flows through `AccessControlService` /
  `AuthorizePage` (`page:` middleware) — no custom checks.
- **FACT** (relevant side-effect): `isSingleDeviceExempt → isSuperAdmin` means
  granting `'*'` also exempts the user from the single-device token check. v1
  did the same for its hardcoded admins (`session.php`), so this is parity —
  and it is why the exemption picker must exclude `'*'` holders.

Each P7 page **is** reachable by a `'*'` holder (documented in §3). Why: it is
the entire purpose of `'*'` (grant all page gates, including the permission
screens), it is uniform with every other page key, and without it P7 would need
exactly the username/id checks ADR-003 forbids.

#### 5. Production First-Admin Bootstrapping

Pass 4 established: the only production artifact is a **schema-only** dump. It
proves the storage exists but **not** whether `'*'` or any P7 permission rows
exist, and **not** which production user is the first administrator. The v1
evidence makes the default state predictable:

- **INFERENCE** (high confidence, from v1 source): production `tbl_permissions`
  contains **no** `'*'` row (v1 never created one) and likely **no** rows for
  `manage_permissions.php` / `manage_program_permissions.php` /
  `manage_multi_device_exemptions.php` / `audit_logs.php` (v1 gated those by
  username/id, so rows were unnecessary). Therefore, **immediately after v2
  cutover, no one can reach the P7 screens unless a reviewed grant is applied**.

Scenario analysis:

| Scenario | State | Consequence | Handling |
|---|---|---|---|
| A. Production data carries an admin row (`'*'` or the four admin keys) | Best case | P7 usable immediately | Verify by a read-only reconciliation query at cutover (before the app points at production); no bootstrap needed |
| B. Production has users but **no** admin rows | Expected | Admin locked out of P7 screens | **Controlled one-time bootstrap required** (below) |
| C. No suitable admin can be identified | Worst case | P7 unusable, and no one can grant access | Requires project-owner decision on who is nominated **before** cutover; cannot be automated |
| D. Controlled one-time bootstrap process | Recommended design | One nominated user granted admin rows by operator, verified, then process ends | The design below |

**What is technically possible**: during the P8 cutover runbook, an **operator
with DB access** (never application code) runs a single reviewed SQL statement
against production that inserts a `'*'` row (or the four admin page keys) for a
**nominated, verified, existing `tbl_users` row**, followed by a read-only
verification query and an audit-recorded confirmation. The SQL is versioned in
the runbook, reviewed before the window, and executed with the same
`mysqldump`-before-schema-work discipline as any P0 baseline step (AGENTS.md).

**What is unsafe**:
- Running `AccessControlSeeder` in production (creates the `jordi` dev user).
- Any seed or code path that **creates users** as part of bootstrap.
- Automating bootstrap **in application code** by promoting "the first user" /
  `user_id = 1` / any username — that reintroduces the exact A2/A3 anti-pattern
  ADR-003 eliminates.
- Auto-promoting based on row counts or "only user" heuristics.

**What requires deployment/operator action**: the actual grant statement,
executed by an operator in the cutover window after verifying the target user
exists and the target rows are absent. It is a one-time, human-in-the-loop
step; it is not part of `php artisan migrate` or any seeding.

**What must NOT be done automatically**: nothing in v2 code may ever self-promote
a user to `'*'`; no seeder runs in production; the bootstrap is not a scheduled
task.

**What requires explicit project-owner approval** (not decided here):
1. **Who** is the first production admin (nominated username).
2. **Which grant** — a single `'*'` row vs. the four admin page keys vs. a
   minimum (e.g. `manage_permissions.php` + `audit_logs.php`).
3. **Which mechanism** — reviewed SQL in the cutover runbook vs. a guarded,
   `APP_ENV=production`-disabled artisan command (design choice for P8).
4. Whether the bootstrap grant persists as the permanent admin record or is
   replaced by granular grants after first login.
5. The `MANAGE_*` audit action strings used to record the bootstrap itself (the
   SQL insert should be reflected in the audit trail via the first P7 audit
   write or a recorded runbook entry).

**Goal satisfied**: no deployment in which P7 exists but nobody can administer
permissions — the runbook *forces* the operator to verify the admin grant
(reconciliation query) before cutover completes, and the owner pre-nominates
the admin.

#### 6. User Administration Authorization

- **V1**: `register.php` = any logged-in user with the `register.php` row;
  `add_user.php` = `super_admin` only (and only reachable from the excluded
  `manage_php.php`). Neither writes permissions — a new user is inert until
  granted rows. No audit, no disable semantics, no `active` column.
- **V2**: page-level capability behind `page:register.php` (or `'*'`).
  Restricting creation is achieved by the grant model, not a hardcoded rule.
  New users still start with zero permissions (parity), so a non-admin holding
  `register.php` can only create additional inert accounts — same blast radius
  as v1, now audit-trailed.
- **Page vs action-level**: this is a **page-level capability** in P7. A future
  `user:create` **action** level (who may create users beyond the page holder)
  belongs to deferred fine-grained authorization (Open Decision #6) — **not
  designed in this pass**. The page key is the initial authorization unit; no
  action schema is invented.

#### 7. Page Permission Administration Authorization

- **V1**: restriction-gated on `manage_permissions.php` with the `[1,2]` id gate
  **commented out** — effectively open to any logged-in user with the row.
  Full-replace saves; `can_access` always `1`; absence = deny; **no audit**.
- **V2**: `page:manage_permissions.php` (or `'*'`). This is the highest-
  privilege P7 screen (it can grant `'*'`). Page permission alone is the v2
  authorization unit — a holder is a permission admin by definition.
- **Contract details** (build-relevant, no code written): page catalog sourced
  from real `tbl_permissions.page_name` values plus the P7 keys themselves
  (v1's hardcoded array is incomplete/duplicated — Pass 1 §1.4); `'*'` rendered
  as a special confirmed toggle (never in the normal checkbox list, since
  `permittedPages()` filters it out); full-replace save semantics kept;
  every save audited via `AuditService` with a stable `MANAGE_*` action string
  (ADMIN_ANALYSIS Open Decision #3).
- **Action-level**: future `permission:grant` granularity is deferred (Open
  Decision #6); the page key is sufficient for the initial P7 implementation.

#### 8. Program Permission Administration Authorization

- **V1**: restriction-gated + `user_id in [1,2]`; full-replace saves; hardcoded
  17-program catalog; no audit.
- **V2**: `page:manage_program_permissions.php` (or `'*'`). The program catalog
  is the **verified** 17-program set (`TransactionService::PROGRAMS` == the
  production `tbl_transactions.program` enum, Pass 4 §7) — admins cannot create
  orphan `tbl_program_permissions` rows that `permittedPrograms()` would ignore.
- **FACT**: page permission **alone** is sufficient for the initial P7
  implementation. No program permission is required to manage *other* users'
  program grants (that would be a confusing coupling); `canAccessProgram` gates
  business modules (transactions, P3), not the admin screen.

#### 9. Multi-Device Exemption Authorization

- **V1**: restriction-gated + username `['super_admin','jordi']`; idempotent
  toggle; picker excludes `super_admin`/`jordi`.
- **V2**: `page:manage_multi_device_exemptions.php` (or `'*'`). This is a
  **privileged administrative capability** (it weakens single-device login),
  so it stays behind its own page key — a permission admin decides who is
  exempt. Idempotent toggle parity kept.
- **Page-level sufficiency**: yes, before any action-level authorization is
  designed. The picker exclusion becomes data-driven (exclude `'*'` holders,
  who are exempt via `isSuperAdmin`). The `tbl_multi_device_exemptions` row is
  still the only marker `isMultiDeviceExempt` reads — `'*'` is exempt at the
  service level without a row, matching v1's implicit admin exemption.
- **Action-level**: deferred with Open Decision #6.

#### 10. Audit Viewer Authorization

- **Recommended page key**: `audit_logs.php` (§2). Required gate: `page:
  audit_logs.php` (or `'*'`).
- **FACT**: it **belongs under P7 administration** (blueprint §1.11/§2, ADR-008;
  viewer over the `tbl_audit_logs` rows P1–P6 already write).
- **FACT**: audit viewing is **separate** from permission-management access —
  distinct page keys mean a user can be granted `audit_logs.php` without any
  `manage_*.php` key (and vice-versa). v1's username gate lumped them; v2's
  key model separates them. This is the intended v2 improvement and a concrete
  answer to the P7 contract's open question.
- **FACT**: `AuditService` is the **sole writer** of `tbl_audit_logs`; the
  viewer + feeds are read-only by design. No second audit-writing mechanism is
  introduced; the feeds (`fetch_logs.php`/`fetch_leaderboard.php` ports) nest
  inside the `audit_logs.php` gate, closing v1's session-only/public feed gaps.
- **Parity constraints carried**: table whitelist (clients/transactions display
  names; others show raw `target_id`), username join, UTC→Asia/Manila
  `m/d/Y - h:i A` + sortable `date_raw`, `LIMIT 10000`, per-table leaderboard
  (Pass 1 §1.8–1.9). Date-range filter / leaderboard date window remain v2
  additions (Open Decision #4), not v1 parity.

#### 11. Existing v2 Machinery Reuse

Identified what can be built with **existing machinery unchanged**:

| Machinery | Reuse for P7 |
|---|---|
| `AccessControlService` (`isSuperAdmin`, `canAccessPage`, `canAccessProgram`, `permittedPages`, `permittedPrograms`, `isMultiDeviceExempt`, `isSingleDeviceExempt`) | All five gates + sidebar visibility; no changes needed |
| `AuthorizePage` middleware (`page:` alias) + `page`/`program` Gates | Route-level enforcement on the five keys |
| `Permission` / `ProgramPermission` / `MultiDeviceExemption` models | Read/update the rows the screens manage (fillable = legacy columns) |
| `User` model (username auth, `$hidden` password/session_token, `hashed` cast, relations) | User list + creation writes; never expose password/session_token |
| `AuditService` (sole writer) | `MANAGE_*` audit rows on every P7 write |
| Route-group pattern (`auth, single-device` → nested `page:<key>` groups) + sidebar `canAccessPage` gating | P7 routes + nav, following the exact P1–P6 convention |
| Per-request permission caches | v1-equivalent "edits apply on next request" behavior (P7 writes the same rows the service reads) |

**What requires a new architecture decision** (NOT made here):
- `MANAGE_*` audit action strings and their stability contract (Open Decision #3).
- Additive `active` column for user disable/enable (Open Decision #1).
- Scope of v2-only additions: date-range audit filter, leaderboard date window,
  audit-on-permission-writes (Open Decision #4).
- Bootstrap mechanism + nominated first admin (this pass §5 — owner approval).
- Any fine-grained authorization (action-level / municipality) — deferred.

#### 12. New Architecture Decisions Required

1. **First-admin bootstrap mechanism** (this pass §5) — owner approval of the
   nominated admin, the grant shape (`'*'` vs admin keys), and the mechanism.
2. **`MANAGE_*` action strings** for permission/exemption/user writes — must be
   chosen deliberately and kept stable for the audit-viewer's distinct-action
   filter (ADMIN_ANALYSIS Open Decision #3).
3. **User disable/enable** — additive `active` column vs create-only (Open
   Decision #1); schema change path per AGENTS.md if adopted.
4. **v2-only audit additions** scope (Open Decision #4).
5. **Deferred (Open Decision #6 — `DEFERRED — REQUIRES AUTHORIZATION
   ARCHITECTURE RESEARCH`, NOT resolved here)**: action-level CRUD
   authorization (where it would eventually apply: user-creation, permission
   grants, exemption toggles — each currently a page-level capability);
   municipality/data-scope authorization; the combined municipality + action
   model; and the fine-grained authorization schema. Pass 5 deliberately
   identifies where these would apply (§6–§9) but designs **no** action schema,
   **no** municipality schema, and implements nothing.

#### 13. V1 Parity vs V2 Security Additions

| Area | V1 parity kept | V2 security/architecture addition |
|---|---|---|
| Page keys | All five keys are the v1 page filenames | Enforcement via `page:` middleware (v1 had inconsistent gates) |
| `'*'` super-admin | — (v1 had none) | The data-row admin contract; `'*'` satisfies every P7 gate |
| User creation | create-only (username+password), new users start with zero permissions | One gate (`register.php`) replaces the `super_admin` username gate; every create audited |
| Permission saves | full-replace `DELETE`+`INSERT`, `can_access` always `1`, absence = deny | Admin-only via the page key (v1's `[1,2]` gate was commented out); audited; `'*'` as an explicit confirmed toggle |
| Program saves | full-replace; 17-program catalog | Catalog from `TransactionService::PROGRAMS`/enum (no orphan keys); page-key gate replaces `user_id in [1,2]`; audited |
| Exemption toggle | idempotent `DELETE`/conditional `INSERT` | Page-key gate replaces the username list; picker exclusion data-driven (exclude `'*'` holders) |
| Audit viewer | table whitelist, username join, display names, timezone/format, `LIMIT 10000`, per-table leaderboard | `audit_logs.php` key now enforced (v1 row was inert); feeds nested under the gate (v1 `fetch_leaderboard.php` was fully public) |
| Audit writes | — (v1 wrote none on these screens) | `MANAGE_*` rows via `AuditService` (sole writer) |
| CSRF / validation | — (v1 none) | Laravel CSRF + `FormRequest` server-side validation |

#### 14. Facts

1. All five recommended keys are v1 page filenames; none collides with any v2
   key (verified against `routes/web.php` + codebase grep).
2. `'*'` is the only v2 super-admin marker; no username/id admin checks exist in
   v2 (grep-verified); `isSuperAdmin`/`canAccessPage` are canonical.
3. `'*'` holders reach every P7 gate by construction and are single-device
   exempt (`isSingleDeviceExempt`).
4. v1 gates: `register.php` session+row; `add_user.php` `super_admin` username;
   `manage_permissions.php` restriction-only (id gate commented out);
   `manage_program_permissions.php` id `[1,2]`; `manage_multi_device_exemptions.php`
   username list; `audit_logs.php` username list, no `restriction.php`;
   `fetch_logs.php` session-only; `fetch_leaderboard.php` **no session check**.
5. v1 `manage_permissions.php` catalog lists `register.php`, `audit_logs.php`,
   `manage_permissions.php`, `manage_program_permissions.php` as grantable keys
   — but **not** `add_user.php` or `manage_multi_device_exemptions.php`.
6. v2 `AccessControlService`, `AuthorizePage`, the `page`/`program` Gates, the
   four permission models, and `AuditService` are directly reusable; no P7
   gate requires new machinery.
7. Production **schema** (Pass 4 §11) matches the v2 model for all authorization
   tables; the production `tbl_transactions.program` enum ==
   `TransactionService::PROGRAMS` (17, set-identical).
8. No production permission **data** was verified — Pass 4's schema-only dump
   cannot prove the presence/absence of `'*'` or any P7 key in production.

#### 15. Inferences

1. Production almost certainly has **no** `'*'` row and **no** P7 admin-key rows
   (v1 never wrote them), so the first-admin bootstrap (§5) is required at
   cutover — this is the single highest-impact P7 deployment dependency.
2. Admin-only-ness of P7 screens emerges from the grant model (only admins can
   grant the keys) — no extra `isSuperAdmin` checks should be layered on the
   page keys.
3. A non-admin holding `register.php` can create only inert accounts (zero
   permissions) — acceptable parity blast radius, now audited.
4. Separating `audit_logs.php` from the `manage_*.php` keys gives admins
   read-only audit access without permission-management powers — the intended
   v2 improvement over v1's username lumping.

#### 16. Unresolved Questions

1. Does production actually carry any `'*'` / admin-key rows? (Requires the real
   DB or a data dump — not the schema-only artifact.)
2. Which user is nominated as first admin, and which grant shape does the owner
   approve (§5)?
3. Exact `MANAGE_*` action strings (Open Decision #3) — to be fixed in the build
   contract.
4. User disable/enable — additive `active` column vs create-only (Open Decision
   #1).
5. Which v2-only audit additions ship (Open Decision #4).
6. Deferred with Open Decision #6: action-level CRUD, municipality/data-scope,
   combined model, fine-grained schema — all explicitly not designed here.
7. Whether the audit viewer should remain under P7 administration or be
   granted separately by policy later (architecture answer today: separate key,
   same module).

#### 17. Recommended Next Research Pass

- **Pass 6 — P7 Audit & `MANAGE_*` Action Contract**: enumerate every P7 write
  (user create, page-permission save, program-permission save, exemption
  toggle, `'*'` grant/revoke) and fix the exact `AuditService` action strings,
  payload conventions (`old_value`/`new_value` JSON), and the distinct-action
  filter contract — plus the bootstrap audit-trail record. Read-only; no build.
  Do not proceed until the user confirms.

---

**HARD STOP — Pass 5 complete.** No P7 code was written; no other file was
modified. Open Decision #6 remains `DEFERRED — REQUIRES AUTHORIZATION
ARCHITECTURE RESEARCH`.

---

### Pass 6 — P7 Audit & `MANAGE_*` Action Contract (2026-08-15)

#### 1. Objective

Define the audit contract for every P7 write: exact `AuditService` action
strings, `old_value`/`new_value` payload conventions, which operations are
audited vs. silently skipped, the distinct-action filter contract, and how the
first-admin bootstrap enters the audit trail. The goal is a single canonical
spelling per operation so the P7 build contract and the audit viewer can both
be written against it without further negotiation. Read-only; no code was
written and no other file was modified.

#### 2. Existing AuditService Contract

1. `AuditService::log(?int $userId, string $action, string $targetTable,
   ?int $targetId = null, ?string $oldValue = null, ?string $newValue = null): void`
   (`app/Services/AuditService.php`) — a faithful port of v1 `log_action()` in
   `system/logs.php`. It performs one `DB::table('tbl_audit_logs')->insert([...])`
   with `created_at => now()`.
2. **Sole writer (FACT, grep-verified):** every audit row in v2 is written
   through `AuditService` — `app(AuditService::class)->log(...)` or a
   constructor-injected `$this->auditService->log(...)`. No other insert into
   `tbl_audit_logs` exists. P7 must follow the same rule.
3. **Schema (FACT, Pass 4 §11):** `tbl_audit_logs` columns are `id, user_id,
   action, target_table, target_id, old_value, new_value, created_at`. There is
   **no `ip` column** — any request/IP metadata would require an additive schema
   change (review + baseline regeneration), which is explicitly deferred (§10.F).
4. **Existing payload conventions (FACT, verified call sites):**
   - Create (`ADD_CLIENT` `ClientService:159`, `ADD_TRANSACTION`
     `TransactionService:33`, `ADD_HOUSEHOLD` `HouseholdService:66`,
     `ADD_GIP` `GipService:105`): `old_value = null`,
     `new_value = json_encode(full created row)`.
   - Update (`EDIT_CLIENT` `ClientService:195`, `EDIT_TRANSACTION`
     `TransactionService:74`, `UPDATE_GIP` `GipService:88`):
     `old_value = json_encode(before)`, `new_value = json_encode(after)`. The
     client path stores **only changed columns**; the transaction/GIP paths
     store the full column subset before/after. All three agree: one row per
     logical save, no per-column rows.
   - Delete (`DELETE_CLIENT` `ClientService:236`, `DELETE_TRANSACTION`
     `TransactionService:104`, `DELETE_HOUSEHOLD` `HouseholdService:93`):
     `old_value = json_encode(full row)`, `new_value = null`.
   - Auth (`LOGIN`/`LOGOUT` `AuthController:32,50`, `FORCE_LOGOUT`
     `SessionController:74`): no payload at all — `action`, `target_table =
     'tbl_users'`, `target_id = <user id>`. P7 user actions therefore inherit
     `tbl_users` as their target table.
   - Existing strings split two styles: `VERB_ENTITY` underscores in every
     service/controller (`LOGIN`, `LOGOUT`, `FORCE_LOGOUT`, `ADD_CLIENT`,
     `EDIT_CLIENT`, `DELETE_CLIENT`, `ADD_HOUSEHOLD`, `DELETE_HOUSEHOLD`,
     `ADD_FAMILY_MEMBER`, `DELETE_FAMILY_MEMBER`, `ADD_TRANSACTION`,
     `EDIT_TRANSACTION`, `DELETE_TRANSACTION`, `ADD_GIP`, `UPDATE_GIP`) vs. the
     dash style confined to scanner config (`SCAN-CEAP`, `SCAN-CEDSSG`,
     `UPDATE-CEDSSG-PAYMENT`, etc.). **The underscore `VERB_ENTITY` convention
     is the codebase norm and is adopted for P7**; the dash style is a scanner
     config artifact, not a general convention.

#### 3. V1 P7 Audit Behavior

1. **FACT:** none of the v1 P7 writers audited anything. `register.php`,
   `add_user.php`, `manage_permissions.php`, `manage_program_permissions.php`,
   and `manage_multi_device_exemptions.php` include `db_connect.php`,
   `session.php`, `restriction.php` (or similar) but never include `logs.php`
   and never call `log_action()`. User creation, permission changes, and
   exemption toggles were **completely silent** in v1's audit trail.
2. **FACT:** v1 had no read-audits either — no `VIEW_*` action exists anywhere
   in v1. `audit_logs.php`/`fetch_logs.php` are pure readers.
3. **FACT (correction to earlier analysis):** v1 `audit_logs.php` **does** have
   a date-range filter — two DateTime pickers (`#minDate`/`#maxDate`) driving a
   DataTables `$.fn.dataTable.ext.search` predicate over each row's `date_raw`
   (`audit_logs.php:201-221`). The earlier "no date-range in v1" claim was true
   only of the server feed: `fetch_logs.php` has no date parameter and returns
   `LIMIT 10000` rows (three branches at lines 99/119/134). So v1's date
   filtering is a purely client-side render-time filter.
4. **FACT:** `fetch_leaderboard.php` has no session check (fully public);
   `fetch_logs.php` is session-only. Both are now nested under the
   `page:audit_logs.php` gate (Pass 5 §3).
5. **INFERENCE:** the v1 audit trail records only the domain write side
   (clients/transactions/households/family members/GIP + scanner ops). Admin
   operations were invisible to it. Any v2 admin auditing is a deliberate
   improvement, not a parity requirement — the contract below is therefore free
   to choose ergonomics without a v1 constraint.

#### 4. Canonical `MANAGE_*` Actions

One canonical spelling per operation. All strings use the underscore
`VERB_ENTITY` norm; the `MANAGE_` prefix groups every P7 admin write under one
prefix in the viewer's distinct-action filter (a clean, filterable family that
cannot collide with existing domain actions).

| Operation | Canonical Action | V1 Audited? | Audit Required in V2? | Notes |
|---|---|---|---|---|
| User creation | `MANAGE_USER_CREATE` | No | **Yes** | `tbl_users`; `target_id` = new user id; payload = username only (§5) |
| Page-permission save (full replace) | `MANAGE_PAGE_PERMISSIONS` | No | **Yes** | `tbl_permissions`; one op-level row; old/new page sets incl. `'*'`; `target_id` = subject user (§6) |
| Super-admin `'*'` grant (row inserted) | `MANAGE_SUPER_ADMIN_GRANT` | No | **Yes** (recommended distinct) | only when the `'*'` row flips absent→present (§6) |
| Super-admin `'*'` revoke (row deleted) | `MANAGE_SUPER_ADMIN_REVOKE` | No | **Yes** (recommended distinct) | only when the `'*'` row flips present→absent (§6) |
| Program-permission save (full replace) | `MANAGE_PROGRAM_PERMISSIONS` | No | **Yes** | `tbl_program_permissions`; one op-level row; old/new program sets (verified 17); `target_id` = subject user (§7) |
| Exemption grant (state change) | `MANAGE_EXEMPTION_GRANT` | No | **Yes** | `tbl_multi_device_exemptions`; only on real change; `target_id` = subject user (§8) |
| Exemption revoke (state change) | `MANAGE_EXEMPTION_REVOKE` | No | **Yes** | same, inverse (§8) |
| No-op exemption toggle (unchanged state) | — (no event) | No | **No** | silence; nothing to reconstruct (§8) |
| Viewing the audit viewer / leaderboard | — (no event) | No | **No** | no `VIEW_AUDIT`/`VIEW_LEADERBOARD` (§9) |

Exactly two strings beyond the five core operations are recommended, and they
are deliberately high-signal: the `'*'` flip is the single highest-privilege
mutation in the system, the P7 contract demands an explicit confirmed action
for `'*'` grants (Pass 5 §5), and a distinct, filterable action makes that
accountable in the viewer. The lean alternative (fold `'*'` into the
`MANAGE_PAGE_PERMISSIONS` payload) remains acceptable if the owner prefers zero
extra strings, but the distinct pair is the recommendation.

#### 5. User Creation Audit Contract

1. **When:** every successful user creation (`register.php`-port) writes one
   row: action `MANAGE_USER_CREATE`, `target_table = 'tbl_users'`,
   `target_id = <new user id>`, `old_value = null`,
   `new_value = json_encode({'username': <username>})`.
2. **Payload rule (RECOMMENDATION):** username only. The username is the login
   identifier, already displayed in the UI, and never a secret. The payload must
   be built from an explicit allow-list — **never** `User::getAttributes()`,
   which would leak `password` and `session_token` into the audit trail (the
   full-row pattern used by `GipService:111` must NOT be copied for `tbl_users`).
   A build-time guard should assert the payload contains no `password`,
   `password_hash`, or `session_token` key.
3. **Rationale:** account provisioning is the entry point for every privilege
   decision; recording who created which account is the minimum accountability
   an admin module requires. A v2-only improvement (v1 was silent).
4. **Failed creation:** no audit row (no event occurred); validation/CSRF
   errors leave no trace — consistent with every other v2 writer.

#### 6. Page Permission Audit Contract

1. **One op-level row per save**, not per DELETE/INSERT of the replace-all
   pattern. Action `MANAGE_PAGE_PERMISSIONS`, `target_table = 'tbl_permissions'`.
2. **`target_id` semantic:** the **subject user's id** (the user whose
   permission set was replaced), not a `tbl_permissions` row id — the set spans
   many rows and has no single PK. This is a documented deviation from the
   "PK of the row" reading of `target_id`; the viewer resolves it to the
   subject username (§9). Same semantic applies to program permissions and
   exemptions.
3. **Payload:** `old_value = json_encode({'username': <subject>, 'pages': [<previous page set>]})`,
   `new_value = json_encode({'username': <subject>, 'pages': [<new page set>]})`.
   The `pages` arrays are the actual `page_name` values written, so a `'*'`
   present in the new set is captured naturally by this event.
4. **Super-admin `'*'` flip:** when the set changes such that the `'*'` row
   transitions absent→present or present→absent, one **additional** row is
   written — `MANAGE_SUPER_ADMIN_GRANT` or `MANAGE_SUPER_ADMIN_REVOKE`,
   `target_table = 'tbl_permissions'`, `target_id` = subject user id,
   `old_value = json_encode({'username': <subject>, 'super_admin': false})`,
   `new_value = json_encode({'username': <subject>, 'super_admin': true})`
   (and inverse for revoke). The page-permission row is still written; the two
   events complement, not duplicate. Flips are detected by diffing the `'*'`
   membership between old and new sets.
5. **`'*'` and exemptions (§8.4):** a `'*'` grant/revoke produces NO exemption
   event. The exemption for a super-admin is derived from `'*'`, not a row, so
   the trail records the true mutation (the `'*'` flip) and the derived state
   is reconstructable by any reader.

#### 7. Program Permission Audit Contract

1. **One op-level row per save:** action `MANAGE_PROGRAM_PERMISSIONS`,
   `target_table = 'tbl_program_permissions'`, `target_id` = subject user id,
   `old_value = json_encode({'username': <subject>, 'programs': [<previous set>]})`,
   `new_value = json_encode({'username': <subject>, 'programs': [<new set>]})`.
2. Program names come from the verified 17-program catalog
   (`TransactionService::PROGRAMS`, Pass 4 §11 — set-identical to production);
   only catalog members can appear, so payloads are constrained by construction.
3. **Retention rationale:** old + new sets are kept (rather than only the new
   set) so a viewer can reconstruct exactly what changed without cross-referencing
   the current table state — same accountability rationale as the edit paths in
   §2.4. The program set is non-secret catalog data.

#### 8. Multi-Device Exemption Audit Contract

1. **Audit only real state changes** of `tbl_multi_device_exemptions`:
   - row inserted → `MANAGE_EXEMPTION_GRANT`, `target_table =
     'tbl_multi_device_exemptions'`, `target_id` = subject user id,
     `old_value = null`, `new_value = json_encode({'username': <subject>})`;
   - row deleted → `MANAGE_EXEMPTION_REVOKE`, same table/id,
     `old_value = json_encode({'username': <subject>})`, `new_value = null`.
2. **No-op toggles produce nothing (RECOMMENDATION):** submitting the toggle for
   a user who is already exempt (or already not exempt) changes no state and
   writes no audit row. Rationale: state is unchanged, so a row would only be
   noise, and v1 (the baseline) wrote nothing at all — silence is strictly a
   parity improvement.
3. **Distinct GRANT/REVOKE over one "TOGGLE" action:** the two directions are
   semantically different (a grant may be audited differently than a revoke
   later) and the distinct-action filter benefits from separate values. This
   mirrors the domain norm where mutating events are directional
   (`ADD_*` vs `DELETE_*`).
4. **Derived vs. explicit:** `isSingleDeviceExempt()` derives `'*'` holders'
   exemption from `isSuperAdmin()` (Pass 5 §3); `isMultiDeviceExempt()` reads
   only the exemption table. The audit trail therefore records **explicit**
   grants/revokes only; a super-admin's exemption is visible via the `'*'` event
   (§6.5), and the picker excludes `'*'` holders (Pass 5 §3) so the screen never
   proposes an exempt-forever toggle.

#### 9. Audit Viewer and Leaderboard Contract

1. **Access:** both feeds and their views live under `page:audit_logs.php`
   (Pass 5 §3); no change.
2. **No read-audits (RECOMMENDATION):** viewing the audit trail or the leaderboard
   is not itself audited — no `VIEW_AUDIT`, no `VIEW_LEADERBOARD`. v1 had no
   read-audits, v2 has none, and a read generates no state change to record.
3. **Distinct-action filter:** the viewer's filter lists the canonical strings —
   the domain actions + the seven P7 strings — from the `action` column; the
   `MANAGE_*` family is one prefix filter. No per-operation metadata table is
   needed; the strings are the contract (§4).
4. **Subject-name resolution (REQUIRED viewer addition):** v1's feed resolves
   display names only for a small whitelist (clients/transactions). `MANAGE_*`
   rows carry `target_id` = a user id, so the v2 viewer must resolve the subject
   username by joining `tbl_users` on `target_id` when `target_table IN
   ('tbl_users','tbl_permissions','tbl_program_permissions',
   'tbl_multi_device_exemptions')`. This is a v2-only viewer rule; without it
   P7 audit rows would display raw ids.
5. **Date-range parity (FACT-corrected):** v1's client-side DateTime filter
   (§3.3) is the parity baseline — the v2 viewer must preserve equivalent
   client-side filtering to stay at parity; server-side filtering is a
   deferred v2 enhancement (§10.A).
6. **Limit contract:** v1 returned `LIMIT 10000` with no server-side
   pagination; the v2 viewer keeps an equivalent cap to preserve behavior
   (enhancement beyond it is deferred, §10.A).

#### 10. V2-Only Audit Enhancements (A–F)

Each enhancement is classified in-scope (part of this contract), optional/
deferred (no decision needed), or schema-blocked (needs owner approval + review).

- **A. Server-side audit date-range filtering.** v1 filters the already-loaded
   `LIMIT 10000` rows client-side (§3.3). A server-side date param on the feed
   would scale the viewer for large trails. **Class: optional/deferred.** No
   schema impact, no architecture decision — only a feed parameter plus tests.
   Not needed for P7 core; parity is already met client-side.
- **B. Leaderboard date-window.** v1 has none (FACT — `fetch_leaderboard.php`
   has no date param). **Class: optional/deferred.** Same reasoning as A.
- **C. Audit for permission changes.** In scope — `MANAGE_PAGE_PERMISSIONS`,
   `MANAGE_PROGRAM_PERMISSIONS`, and the `'*'` events (§6–§7). **Required by
   this contract.**
- **D. Audit for user creation.** In scope — `MANAGE_USER_CREATE` (§5).
   **Required by this contract.**
- **E. Audit for exemption changes.** In scope — `MANAGE_EXEMPTION_GRANT` /
   `MANAGE_EXEMPTION_REVOKE` (§8). **Required by this contract.**
- **F. Additional audit metadata (IP / request info).** `tbl_audit_logs` has no
   `ip` column (Pass 4 §11); capturing IP would be an additive schema change
   (migration + review + baseline regeneration per AGENTS.md). **Class:
   schema-blocked / deferred.** Does not block P7 core; if adopted, it is a
   separate reviewed change.

Open Decision #4 ("which v2-only audit additions ship") is therefore **resolved
in part**: C/D/E are in scope; A/B are optional/deferred; F is deferred pending
a schema decision.

#### 11. Audit Payload Safety

1. **Never** include `password`, password hash, `session_token`, or any secret
   in any payload — build all P7 payloads from explicit allow-lists (username,
   page arrays, program arrays), never from `User::getAttributes()` (§5.2). This
   is the only payload class in the codebase that could leak credentials, because
   `tbl_users` is the only audited table that stores a credential column.
2. Usernames are safe by v1 precedent (they already appear in v1 UI messages and
   the login identifier). Page/program names are non-secret catalog values.
3. `AuditService` is the sole writer; P7 controllers call it exactly like every
   other service — no raw `DB::table('tbl_audit_logs')` inserts, no seeder writes
   into the trail except the explicit bootstrap record (§11, §5 of Pass 5).
4. All writes occur inside the same transaction/flow as the mutation they
   describe (matching `ClientService`/`TransactionService` patterns), so the
   trail cannot describe events that did not persist.

#### 12. Future Fine-Grained Authorization Relationship

1. Each canonical action is a 1:1 stand-in for a future action-level grant:
   `MANAGE_USER_CREATE` → future `create` on the user resource;
   `MANAGE_PAGE_PERMISSIONS`/`MANAGE_PROGRAM_PERMISSIONS` → future `manage` on
   permissions; `MANAGE_EXEMPTION_GRANT`/`MANAGE_EXEMPTION_REVOKE` → future
   `grant`/`revoke` on exemptions; `MANAGE_SUPER_ADMIN_GRANT`/`REVOKE` → future
   `manage` on the super-admin marker. Because every P7 event already maps to
   exactly one operation, migrating to per-action checks later requires no
   audit-trail redesign.
2. **Scope boundary (unchanged):** this is a naming/observability mapping only.
   No action-level CRUD, municipality/data-scope, combined permission model, or
   fine-grained schema is designed here. Open Decision #6 remains exactly
   `DEFERRED — REQUIRES AUTHORIZATION ARCHITECTURE RESEARCH`.
3. The viewer's distinct-action filter doubles as the future grant catalog
   checklist: every `MANAGE_*` string in this contract corresponds to a
   future-checkable operation.

#### 13. Facts

1. `AuditService` is the sole writer of `tbl_audit_logs` (grep: every insert
   call site goes through it); P7 must preserve this.
2. `tbl_audit_logs` has no `ip` column; any metadata addition is a schema change.
3. v1 audited NO P7 operation: `register.php`, `add_user.php`,
   `manage_permissions.php`, `manage_program_permissions.php`,
   `manage_multi_device_exemptions.php` never call `log_action()`.
4. v1 has no read-audits anywhere (no `VIEW_*` action exists).
5. v1 `audit_logs.php` has a client-side DateTime date-range filter
   (`audit_logs.php:201-221`); `fetch_logs.php` has no server date param and
   returns `LIMIT 10000`.
6. v1 `fetch_leaderboard.php` has no session check; `fetch_logs.php` is
   session-only; both are now nested under `page:audit_logs.php` (Pass 5).
7. Existing v2 actions use underscore `VERB_ENTITY` naming in all
   services/controllers; the dash style (`SCAN-*`) exists only in scanner config.
8. The `'*'` row is stored in `tbl_permissions` (`page_name = '*'`); the P7
   permission save already carries it in the page set, so `'*'` flips are
   diff-able without new machinery.
9. `isSingleDeviceExempt()` derives super-admin exemption from `'*'`
   (`AccessControlService`); explicit exemption rows are read only by
   `isMultiDeviceExempt()`.
10. `AuditService` accepts `?int $userId = null`, so the operator-run bootstrap
    can record a `user_id = NULL` (system) row.

#### 14. Inferences

1. The seven canonical `MANAGE_*` strings (five core + two `'*'`) are the
   complete P7 audit vocabulary; no operation needs more than one string, and
   the no-op/read cases intentionally produce none.
2. `'*'` flips need their own event because they are the highest-privilege
   mutation and the contract requires an explicit confirmed `'*'` action; a
   viewer filter for "super-admin changes" is operationally the most important
   query an admin will run.
3. No-op exemption toggles and reads should stay silent — v1's zero-audit
   baseline makes silence the parity-conservative choice, and stateful events
   are what an audit trail exists to record.
4. The bootstrap (`operator-run SQL`, Pass 5 §5) should write an explicit
   `MANAGE_SUPER_ADMIN_GRANT` row with `user_id = NULL` (no operator account
   exists yet, so the subject user id goes in `target_id`) — otherwise the
   highest-privilege event in the system would be the one event the trail
   cannot show.
5. Because v1's admin ops were never audited, this contract is a pure
   improvement and carries no parity risk; the only parity-sensitive piece is
   the viewer (client-side date filter + `LIMIT` cap), which §9 preserves.

#### 15. Unresolved Questions

1. Owner sign-off on the final action contract (§4) — including acceptance of
   the two distinct `MANAGE_SUPER_ADMIN_GRANT`/`REVOKE` events vs. the lean
   fold-into-`MANAGE_PAGE_PERMISSIONS` alternative.
2. First-admin nomination and grant shape (Pass 5 §5 — carries forward; the
   bootstrap audit row from §14.4 depends on it).
3. Enhancement A (server-side date filtering) and B (leaderboard window):
   adopted or not — optional, deferred, no decision required for P7 core.
4. Enhancement F (IP metadata): schema change, deferred pending owner decision.
5. Whether the viewer's subject-name resolution (§9.4) is confirmed as in-scope
   for the P7 build (recommended: yes, it is required for usable audit rows).
6. User disable/enable — additive `active` column vs. create-only (Open Decision
   #1, carries forward, out of scope here).
7. Production permission-data truth (no data dump; schema-only evidence, Pass 4)
   — the first-admin bootstrap requirement stands regardless.

#### 16. Recommended Next Research Pass

- **Pass 7 — P7 Build Contract:** consolidate every P7 decision from Passes 4–6
  (keys, gating, `MANAGE_*` actions, payloads, bootstrap, viewer rules,
  `'*'`-picker exclusion) into one buildable contract for
  `docs/implementation/P7_ADMINISTRATION.md`: concrete routes, controllers,
  `FormRequest`s, views/partials, `AccessControlService`/`AuthorizePage`/gate
  usage, the audit call sites, and the Open Decision #1 `active`-column
  resolution. Read-only; no build. Do not proceed until the user confirms.

---

**HARD STOP — Pass 6 complete.** No P7 code was written; no other file was
modified. Open Decision #3 (`MANAGE_*` strings) is **resolved by this contract**
(pending owner sign-off); Open Decision #4 is **resolved in part** (C/D/E in
scope, A/B optional/deferred, F schema-deferred); Open Decision #6 remains
`DEFERRED — REQUIRES AUTHORIZATION ARCHITECTURE RESEARCH`.

---

### P8 — Action-Level Authorization Architecture Research (2026-08-15)

> **Scope**: research/architecture pass only. Determines whether and how v2
> should add **action-level** authorization — per-operation grants
> (view/create/edit/delete/export/approve) layered on the current page-level
> model. **Read-only**: no code, schema, migration, route, model, controller,
> service, view, test, seeder, or DB operation was run, and no file other than
> this one was modified (verified with `git status`, §18). Open Decision #6
> remains `DEFERRED — REQUIRES AUTHORIZATION ARCHITECTURE RESEARCH`, with the
> four-way breakdown in §17: **A (action-level CRUD) may be recommended here but
> is not finalized; B (municipality/data-scope) and C (combined model) are NOT
> finalized; D (schema) is left UNRESOLVED** where it depends on B/C.
>
> **Method**: full re-read of the v2 authorization machinery
> (`AccessControlService`, `AuthorizePage`, the `page`/`program` Gates in
> `AppServiceProvider`, `AuditService`, the `Permission`/`ProgramPermission`/
> `MultiDeviceExemption`/`User` models, `ClientPolicy`), every P1–P7 controller,
> `routes/web.php`, and the audit-action inventory (grep across `app/`); the P7
> build contract and its canonical evidence (Passes 1–7 of this document); the
> v1 ground truth already recorded in Pass 1. Every conclusion is labelled FACT
> (read from code/schema) / INFERENCE (derived) / RECOMMENDATION (proposed —
> owner decision required, not claimed as approved) / UNRESOLVED.

#### 1. Objective

1. Establish the **current authorization baseline** (§2) as the ground truth any
   future layer must preserve.
2. Produce the **module/action inventory** (§3–§4) — the complete set of
   operations today's page gates admit.
3. Evaluate **granularity** (§5), **page-vs-action modeling** (§6), **storage**
   (§9), **allow/deny semantics** (§10), **defaults and adoption** (§11),
   **enforcement** (§12), **special operations** (§13), and the **audit
   relationship** (§14).
4. **Test compatibility** with the future municipality/data-scope dimension
   (§15) and enumerate the **security failure modes** a new layer must avoid
   (§16).
5. Split Open Decision #6 into its four components, **recommend** (not
   finalize) the action-level architecture (§17–§20), and leave the **next
   pass** clearly scoped (§22).

#### 2. Current authorization baseline (FACT)

1. **Page level** — `tbl_permissions` (`id, user_id, page_name varchar(100),
   can_access tinyint(1) DEFAULT 1`), `UNIQUE(user_id, page_name)` (migration
   2026_08_05_000004). Verified byte-identical to the production schema (Pass 4
   §11).
2. **Super-admin is a data row** — `page_name = '*'`
   (`AccessControlService::SUPER_ADMIN_PAGE`) with `can_access = 1` is the only
   admin marker. `isSuperAdmin()` tests exactly that row. No username or
   `user_id` checks exist anywhere (ADR-003, grep-verified this pass).
3. **`canAccessPage`** — `'*'` → `isSuperAdmin`; otherwise `isSuperAdmin` **or**
   a `(page_name, can_access=1)` row. Absence = deny. A `can_access=0` row is
   treated as explicit denial (Pass 3 §4).
4. **Program level** — `tbl_program_permissions` (`user_id, program_name`),
   `UNIQUE(user_id, program_name)`. `canAccessProgram` = `isSuperAdmin` **or** a
   row. **Empty permitted list = unrestricted** (v1 parity — no rows = allow
   all). Enforced only by `TransactionController::authorizeProgram` (store,
   update) and the `program` Gate; **not** enforced on scanner routes (Pass 3 §5
   fact: a user can scan a program they hold no program row for — unchanged from
   v1, decision deferred).
5. **Enforcement points** — `AuthorizePage` middleware (`page:<key>`, 403 JSON
   vs redirect-to-dashboard with `login_status=denied`); the `page`/`program`
   Gates (`AppServiceProvider`); `ClientPolicy` (the **only** policy,
   registered via `Gate::policy`; `ClientController@destroy` →
   `authorize('delete')` re-asserts `page:clients.php`, it does **not** tighten
   beyond the page); sidebar gating via `canAccessPage`. **There is no
   action-level enforcement anywhere.**
6. **The `page:` groups** (`routes/web.php`): every business page group is one
   flat group — a holder of the page key can exercise **every** route in the
   group (view + feed + create + edit + delete + export + program-gated writes).
7. **Data-scope** — none. No municipality/data-scope dimension exists (Pass 2:
   `tbl_clients.city_municipality`/`barangay` store int IDs as varchar;
   `tbl_unpaid_verifications.municipality_id` is the only true FK to
   `tbl_municipalities`). Municipality is a filter attribute, never an authz
   boundary.
8. **Correction to an earlier P7 claim**: `permittedPages()` returns the raw
   `can_access` page_names (it would include `'*'` if present); the P7 **catalog
   UI** excludes `'*'` explicitly via `AdminPermissionController::pageCatalog()`
   (reject of `SUPER_ADMIN_PAGE`), not via `permittedPages()`. The P7 screen
   behavior is as designed; only the documented mechanism was slightly off.

#### 3. Module / action inventory (FACT)

Derived from `routes/web.php` and the controller methods read this pass.
"Actions" = distinct operations a page-key holder can perform today.

| Module | Page key(s) | Actions today (all under one page gate) |
|---|---|---|
| Auth/session | `currently_logged_users.php`, `force_logout.php` | view online users; force-logout a session |
| Clients | `clients.php` | view list, feed, details (`show`/panel), create, edit, delete, duplicates (view/feed/batch-delete), photo upload, verify-mobile, add GIP (`gip.store` shares `clients.php`) |
| Households | `household.php` | view, feed, create, show, delete, search, client-picker |
| Family members | `clients.php` (shared key) | view picker, create, search |
| Transactions | `all_transactions.php` | view, feed, create, edit (page + inline), delete, client-search, **export CSV**, plus per-program gate |
| Scanners (14 keys) | `scanner_*.php` | view page, lookup (read), save (write/attendance) |
| Payout attendance (3 variants) | `scanned_payouts*.php` | view, feed, delete (via feed `delete_id`) |
| Unpaid verifications | `unpaid_verifications.php` | view, feed, **export CSV**, delete (via feed) |
| Scholars | `scholars.php` | view, feed, create, edit, update, relink client-id |
| Scholarship reports | `scholarship_reports.php` | view, feed, **export CSV** |
| Grantee update logs | `update_logs.php` | view |
| Administration | `register.php`; `manage_permissions.php`; `manage_program_permissions.php`; `manage_multi_device_exemptions.php`; `audit_logs.php` | create user; grant/revoke pages incl. `'*'`; grant/revoke programs; grant/revoke exemptions; view audit + leaderboard |

**Public (no auth, no page gate)** — out of scope for any authorization layer by
construction: `student/*`, `unpaid-verification*`, `grantee-search/*`,
`grantee-update/*`, `grantee/verify-mobile`, `grantee/barangays`, `qr-viewer`,
`session/status`. These are anonymous self-service flows (v1 parity) and must
**not** acquire action grants.

**Dashboard** — `auth, single-device` only, no page gate; reachable by every
logged-in user.

#### 4. Existing action vocabulary (FACT)

The audit strings are the de-facto operation vocabulary (Pass 6 §2):

- Domain writes: `ADD_CLIENT`/`EDIT_CLIENT`/`DELETE_CLIENT`,
  `ADD_HOUSEHOLD`/`DELETE_HOUSEHOLD`, `ADD_FAMILY_MEMBER`/`DELETE_FAMILY_MEMBER`,
  `ADD_TRANSACTION`/`EDIT_TRANSACTION`/`DELETE_TRANSACTION`,
  `ADD_GIP`/`UPDATE_GIP`, `LOGIN`/`LOGOUT`/`FORCE_LOGOUT`.
- P7 admin: `MANAGE_USER_CREATE`, `MANAGE_PAGE_PERMISSIONS`,
  `MANAGE_SUPER_ADMIN_GRANT`/`REVOKE`, `MANAGE_PROGRAM_PERMISSIONS`,
  `MANAGE_EXEMPTION_GRANT`/`REVOKE`.
- Scanner config: dash-style (`SCAN-CEAP`, `UPDATE-CEDSSG-PAYMENT`, …) — a
  scanner-config artifact, not a general convention (Pass 6 §2.4).
- **Gaps**: scholar writes (create/update/relink) emit **no** audit action;
  payout/unpaid deletes emit no audit (P5 parity); exports emit no action.

The existing strings map 1:1 to a future canonical action set —
`view/create/edit/delete/export/approve` (Pass 6 §12: every `MANAGE_*` string is
a 1:1 stand-in for a future action grant; the viewer's distinct-action filter
doubles as the future grant-catalog checklist). **This is the strongest existing
hook for action-level authorization**: the vocabulary already exists, is stable,
and is user-visible.

#### 5. Granularity options (A/B/C)

| Option | Model | Effect vs today | Verdict for v2 |
|---|---|---|---|
| **A. Page-level only (status quo)** | `tbl_permissions` page grant = all actions on the page | No change; coarse | Current baseline; fine for parity, insufficient once the owner wants "encoder can add, cannot delete" |
| **B. Action-level CRUD (this pass's subject)** | per (page, action) grants layered on the page grant | A page holder performs only granted actions | **RECOMMENDED to consider** (§17.A, §20) |
| **C. Action + data-scope (municipality)** | (page, action, municipality) | Adds a row-level dimension | **Deferred** (§17.B/C) — v2 has no data-scope today (Pass 2) and no consistent municipality representation to model on |

#### 6. Page-vs-action modeling options (A–D)

How actions relate to pages — four ways to express "the create button on the
clients page":

| Option | Shape | Strengths | Weaknesses |
|---|---|---|---|
| **A. Orthogonal verbs** | grants = (page, action); action ∈ {view, create, edit, delete, export, approve} global | one verb set everywhere; simplest catalog; matches audit vocabulary | some pages have no natural "approve"; verb set must be a superset |
| **B. Per-page capability names** | each page declares its own actions (`clients:add-client`, `clients:delete-client`) | precise; matches v1 page-per-file granularity | larger catalog; needs a per-page action manifest; less uniform |
| **C. Page = view; actions layer on top (hierarchical)** | page permission remains the "enter" gate; non-view actions additionally require an action grant | keeps `tbl_permissions` semantics intact; view stays v1-parity; writes can be tightened without touching viewers | write behavior changes for existing holders when a page adopts actions (see §11) |
| **D. Synthetic compound page keys** | each action becomes its own `page_name` value (`clients.php:delete`) in `tbl_permissions` | no new table | **violates ADR-003** (`page_name` identical to v1), breaks the real-catalog derivation, `permittedPages()`, the P7 screen, and the `'*'` contract — rejected |

**RECOMMENDATION (consolidated in §20): Option C** — page permission = view/
enter (v1 parity preserved, `tbl_permissions` untouched), with a layered action
grant for every non-view operation. Option A supplies the verb set (see §14's
mapping) rather than full verb orthogonality; B's per-page precision is
achievable inside C via the per-page action catalog.

#### 7. `'*'` super-admin semantics under action authz

1. **FACT**: `'*'` satisfies every page gate by construction (`canAccessPage`
   short-circuits on `isSuperAdmin`).
2. **RECOMMENDATION**: `'*'` must **also satisfy every action grant** — the
   action check is `isSuperAdmin` **or** (page grant **and** action grant).
   Otherwise a `'*'` holder could be blocked from an action, breaking the admin
   contract, the P7 screens (which rely on `'*'` reaching everything), and the
   bootstrap model (Pass 5 §5 grants a `'*'` row for the first production
   admin).
3. **INFERENCE**: because `'*'` bypasses actions, the only admin who can
   *restrict* another super-admin is a super-admin via the P7 `'*'` toggle —
   unchanged from today. Action grants can only tighten **non-**`'*'` users;
   that is the entire point (data encoders, approvers, regional staff).

#### 8. Program-permission interaction (FACT + RECOMMENDATION)

1. **FACT**: program permissions are **orthogonal** to page permissions. Today
   `TransactionController` checks `authorizeProgram` on create/update only; the
   scanner routes do **not** check `canAccessProgram` (Pass 3 §5, deferred);
   payout/unpaid routes do not check program permissions at all.
2. **RECOMMENDATION**: the action layer **does not change** `canAccessProgram`.
   Composition is AND: an action grant gates *which operation*, a program grant
   gates *which program*. A transaction write check =
   `canAccessAction(page=all_transactions.php, action)` **and**
   `canAccessProgram(program)`. The scanner program-gate question (Pass 3 §5 /
   Pass 4 §14.5) stays deferred; the action layer composes with either outcome.
3. **INFERENCE**: P7 admin screens (which manage program grants) need **no**
   program permission themselves (Pass 5 §8) — the action layer must not add one,
   or permission admins would need a program row to manage programs.

#### 9. Storage options (A–D)

All additive; none alter existing tables' columns.

| Option | Design | Pros | Cons / risk |
|---|---|---|---|
| **A. New table `tbl_action_permissions`** | `id, user_id, page_name, action, created_at`, `UNIQUE(user_id, page_name, action)` — the exact shape of `tbl_program_permissions` | zero risk to `tbl_permissions` (ADR-003 "identical to v1" stays true); mirrors the proven program pattern; **scope-ready** (a future additive scope column leaves room for §15); clean catalog queries | one new table (additive migration + baseline regen per AGENTS.md) |
| **B. `action` column on `tbl_permissions`** | additive `action varchar(50) NULL`; `NULL` = page-level (all actions); `UNIQUE(user_id, page_name, action)` | reuses the existing table/screen | pollutes the v1-identical table; `NULL`-means-all is ambiguous and error-prone (§10); the P7 catalog derivation (`page_name` distinct) must be reworked; a future scope column would sit in an already-polluted table |
| **C. JSON `actions` column on `tbl_permissions`** | `actions JSON` | flexible | not first-normal-form; hard to query/audit/join; same pollution objection as B |
| **D. No new storage — synthetic page keys** | see §6.D | — | **rejected** (violates ADR-003; breaks catalog/`'*'`/`permittedPages`) |

**RECOMMENDATION: Option A.** It is the only storage that keeps
`tbl_permissions` byte-identical (the non-negotiable), reuses a proven pattern
(`tbl_program_permissions`), and is the cleanest base for the deferred
municipality dimension (§15). Any adoption of A requires the additive-migration
+ `schema:dump` baseline workflow (AGENTS.md) — a build item, **not** this pass.

#### 10. Allow vs deny semantics (RECOMMENDATION)

1. **Allow-list only**: presence of a (page, action) grant = allow; absence =
   deny. No separate deny rows. This mirrors the page model (`can_access=1` row
   = allow, absence = deny) and keeps the P7 permission screens simple
   (checkboxes, full-replace).
2. **No flag column needed** if a row's presence is the grant;
   `tbl_program_permissions` sets the precedent (presence = allow, no flag).
3. **No per-action deny overrides `'*'`** (§7): `'*'` always wins.

#### 11. Defaults and adoption (backward compatibility)

The core tension: v2 must keep **every current page holder** working at cutover
(zero production data change), while still allowing pages to *adopt* action
enforcement later.

| Strategy | Rule | Risk |
|---|---|---|
| **S1. Implicit adoption** | `canAccessAction(U,page,action)` = `'*'` OR (`canAccessPage(U,page)` AND (page has **no** action rows → true; else U has the (page,action) row)). A page is "adopted" the moment its first action row exists | **one user's new action grant flips the whole page's behavior** — the widest lockout/loosening footgun; behavior depends on row presence, not configuration |
| **S2. Explicit per-page enforcement flag** | an explicit "actions enforced for this page" switch (config `config/actions.php` or a small data table); a page with the flag off behaves exactly like today; a page with the flag on requires action grants | deterministic; adoption is a deliberate, reviewable act; **recommended** |
| **S3. Full backfill at go-live** | adopt actions for every page at once and backfill `create/edit/delete` grants for every current page holder (reviewed SQL) | deterministic but a production **data** change; heavier; guarantees behavior changes everywhere at once |

**RECOMMENDATION: S2** (explicit per-page enforcement flag) as the primary
strategy, with **S3-style backfill** available *per page* at the moment each
page is adopted (an admin grants the existing writers their current actions — a
P7 screen operation, not a blind seed). This preserves: zero production data
change at cutover; per-page opt-in; and a reversible, audited adoption (the P7
permission screen records `MANAGE_*` rows for action grants too).

**Default for a non-adopted page** = today's exact behavior (page permission =
all actions). **Default for an adopted page** = page permission = view only;
every write/export/approve operation needs its own action grant.

#### 12. Enforcement layer (RECOMMENDATION)

1. **`AccessControlService::canAccessAction(User, page, action)`** — the single
   implementation (ADR-003); `'*'` and the S2 rule live here. Per-request cache
   mirrors the existing `pagePermissions`/`programPermissions` pattern.
2. **An `action` Gate** — `Gate::define('action', fn (User $u, string $page,
   string $action) => app(AccessControlService::class)->canAccessAction(...))`,
   alongside the existing `page`/`program` Gates.
3. **An `action:` middleware** — mirror of `AuthorizePage` (`403` JSON /
   redirect-flash), applied per write route, e.g.
   `action:all_transactions.php:create`. The `page:` middleware stays the
   enter/view gate on the group; `action:` is layered on the specific mutation
   routes (store/update/destroy/inline-update/export/toggle).
4. **Controller-level where middleware is awkward** — `Gate::authorize('action',
   [page, action])` or the same `canAccessAction` call in the method, matching
   the `ClientPolicy` precedent (the only existing non-route authz).
5. **Sidebar/UI** — keep page-level gating for menu visibility; **add
   action-level hiding** for buttons once a page is adopted (a user who can view
   but not delete must not see a delete button). This is UX, not security; the
   server check is authoritative.
6. **Feeds (`/data`) are reads** → page gate only, no action needed (v1 feeds
   and the P7 feeds are read surfaces). **Exports are NOT reads** — `export` is
   a distinct action and every export route (`transactions.export`,
   `scholarship-reports.export`, `unpaid-verifications.export`) must be
   action-gated when its page adopts actions (CSV exfiltration is the classic
   overlooked action).

#### 13. Special operations (RECOMMENDATION)

Operations that need care regardless of the general model:

| Operation | Today | Action model |
|---|---|---|
| `'*'` grant/revoke (P7 toggle) | page-level under `manage_permissions.php` | keep page-level **and** treat it as the highest-privilege action; a dedicated action (`manage_permissions.php:super-admin`) so a page-holder without it cannot flip admin status. `'*'` holders pass it by definition |
| Exemption grant/revoke | page-level under `manage_multi_device_exemptions.php` | dedicated actions (`grant`/`revoke`) on that page; `'*'` passes |
| User create | page-level under `register.php` | `register.php:create` |
| Audit viewer / leaderboard | page-level under `audit_logs.php` | view-only by nature → **no** action beyond page access; never add a read audit (§14) |
| Force logout / online users | page-level | `view`/`force` actions optional; low priority |
| Public self-service flows | no auth | **never** action-gated (§3) |
| First-admin bootstrap (Pass 5 §5) | operator SQL granting `'*'` | unchanged — the `'*'` row grants all actions (§7) |

#### 14. Audit relationship (FACT + RECOMMENDATION)

1. **FACT**: `AuditService` is the sole `tbl_audit_logs` writer; reads are never
   audited (no `VIEW_*`); the P7 `MANAGE_*` strings are the administration
   vocabulary (Pass 6).
2. **RECOMMENDATION**: the canonical action catalog **is derived from the audit
   vocabulary** (`view/create/edit/delete/export/approve`, mapping to
   `ADD_*`/`EDIT_*`/`DELETE_*`/`MANAGE_*` where they exist, §4). Every action
   grant corresponds to an existing or future audit string, so the audit
   viewer's distinct-action filter remains the grant-catalog checklist (Pass 6
   §12) — **no new vocabulary is invented**; new actions must pass the Open
   Decision #3 stability discipline.
3. **RECOMMENDATION**: granting/revoking an **action** row is itself audited via
   the existing `MANAGE_*` family (extend the P7 screen's payloads to include
   `actions`, or add a `MANAGE_ACTION_*` pair under the same stability rules) —
   the permission-grant trail stays complete.
4. **INFERENCE**: action checks gate *before* an operation and audit rows record
   *after* it; the layers are complementary — action grants answer "may they?",
   audit rows answer "did they?".

#### 15. Municipality (data-scope) compatibility test (UNRESOLVED but bounded)

Open Decision #6.B is **not finalized here**; this section only tests whether the
recommended action architecture is compatible with a future municipality/
data-scope dimension and whether it would need redesign.

1. **Composability**: the future check is naturally
   `canAccessAction(page, action)` **and** `canAccessScope(record-municipality)`.
   The action and scope dimensions are orthogonal — one is an operation gate, the
   other a row filter.
2. **Storage**: Option A (§9) is the only storage that leaves room for a scope
   column (additive scope column on `tbl_action_permissions`) **without**
   touching `tbl_permissions`. Options B/C would force the scope into the
   v1-identical table. → **storage A is the scope-ready choice**; this is a
   supporting argument, not a decision on B/C.
3. **Data-representation blocker (carried from Pass 2)**: there is **no
   consistent municipality representation** in the schema to model scope on —
   `tbl_clients.city_municipality`/`barangay` are int IDs stored as varchar(100)
   (no FK); `tbl_unpaid_verifications.municipality_id` is the only true int FK
   to `tbl_municipalities`; households/transactions have no municipality column
   of their own (derived via client join). **Until the owner decides the
   municipality-scope data model, B/C cannot be designed**, and D remains
   unresolved (§17.D).
4. **Enforcement point for scope later**: feeds (`/data`) and exports must be
   the scope-enforcement target (server-side row filtering), not just UI selects
   — the current feeds already filter on municipality (P2/P3), which is the
   natural seam.

**Verdict: the recommended architecture is scope-compatible (§15.2) and the
scope question is data-blocked, not architecture-blocked.**

#### 16. Security failure modes

The layer must be built so these cannot happen:

1. **Adoption lockout** — flipping a page to enforced without backfilling locks
   legitimate writers out (availability). → S2 explicit adoption + per-page
   backfill at adoption (§11).
2. **Behavior-dependent-on-data** — S1's "rows exist ⇒ enforced" makes every
   action grant a global switch (§11). → S2.
3. **Ambiguous `NULL`-means-all** — storage B/C's "no row = everything"
   confusion. → storage A with explicit row-per-grant (§9, §10).
4. **Forgotten export** — `export` left page-level while the page is enforced
   leaks CSV. → `export` is always a distinct action (§12.6).
5. **`'*'` drift** — someone makes `'*'` non-bypassing (or bypassing only page,
   not action). → `'*'` short-circuits both, in one service method (§7).
6. **Inconsistent enforcement** — middleware on some routes, nothing on others.
   → one `action:` middleware + `canAccessAction` as the only implementation
   (§12).
7. **Username/id relapse** — an implementer "helps" by hard-coding a name/id
   check for the action screen. → ADR-003 guard: no username/id checks, ever
   (P7 contract §22 never-change list).
8. **Program coupling** — requiring program grants on the P7 admin screens (§8.3)
   or on actions that don't consume programs.
9. **Scope-by-UI-only (later)** — municipality filters that only hide rows in
   the browser without server-side enforcement (§15.4).
10. **Catalog drift** — action names that diverge from the audit vocabulary
    (§14.2), making the viewer's filter and the grant catalog disagree.

#### 17. Open Decision #6 breakdown (A/B/C/D)

| Component | Status this pass | What happens next |
|---|---|---|
| **A. Action-level CRUD authorization** | **RECOMMENDED** (not finalized) — the layered model of §6.C + §9.A + §10–§12, with the §20 architecture | Owner approves/rejects; if approved, a contract pass (Pass 9) fixes the per-page action catalog, the S2 adoption mechanism, and the P7 action-management screen |
| **B. Municipality/data-scope** | **NOT finalized** — only bounded (§15); data-blocked by the absent municipality representation (Pass 2) | Owner must decide the municipality-scope *data model* first (which tables, which columns, FK or enum) |
| **C. Combined model (action + scope)** | **NOT finalized** — depends on A adoption + B data decision | only designable after B; §15.1 shows it composes as action AND scope |
| **D. Fine-grained schema** | **LEFT UNRESOLVED** — a candidate (storage A) is offered and justified (§9.A, §15.2) but not locked | locked only when A is adopted (and B shape known); any migration goes through the additive-migration + `schema:dump` workflow (AGENTS.md) |

Open Decision #6 therefore remains exactly
`DEFERRED — REQUIRES AUTHORIZATION ARCHITECTURE RESEARCH` **pending owner
approval of A**; this pass resolves none of it.

#### 18. Facts (summary)

1. v2 authorization is page-level only; a page-key holder can exercise every
   route in the group (view/feed/create/edit/delete/export) plus program-gated
   writes (FACT — `routes/web.php`, controllers).
2. `tbl_permissions` (incl. `'*'`) and `tbl_program_permissions` are verified
   byte-identical to the production schema (Pass 4 §11); `page_name` identical to
   v1 is a binding contract (ADR-003).
3. `'*'` is the sole admin marker; no username/id checks exist (grep).
4. `ClientPolicy` is the only policy and does not tighten beyond `page:clients.php`.
5. The audit vocabulary already enumerates the operations (§4); scholar writes,
   payout/unpaid deletes, and exports are un-audited (parity).
6. Scanner routes do not check `canAccessProgram`; transaction create/update do.
7. No municipality data-scope exists; `tbl_clients` municipality/barangay are
   int-in-varchar, only `tbl_unpaid_verifications.municipality_id` is a true FK
   (Pass 2).
8. Public self-service routes (student/unpaid/grantee/QR) are unauthenticated by
   design (v1 parity) and out of scope for action grants.
9. This pass modified **only** this file (`git status` clean elsewhere).

#### 19. Inferences

1. Action-level authorization is the **only** remaining coarse-granularity gap
   in the v2 authorization story: page-level covers "which screen", program-level
   covers "which program", and neither covers "which operation".
2. The audit strings are sufficient and stable enough to seed the canonical
   action catalog without inventing vocabulary.
3. The recommended storage (A) and modeling (C) keep `tbl_permissions` and the
   `'*'` contract untouched, so adoption is fully reversible and additive — no
   v1/production parity is at risk until an owner-approved build executes.
4. The municipality question is data-blocked (not architecture-blocked): the
   action layer's design does not foreclose it.

#### 20. One recommendation (consolidated)

**RECOMMENDATION — adopt action-level authorization as a layered, explicit,
allow-list refinement, pending owner approval:**

1. **Keep `tbl_permissions` (and `'*'`) byte-identical** — page permission stays
   the view/enter gate (v1 parity, ADR-003).
2. **Add storage Option A**: a new `tbl_action_permissions`
   (`id, user_id, page_name, action`, `UNIQUE(user_id, page_name, action)`),
   additive migration + `schema:dump` baseline regen (AGENTS.md).
3. **One service method** `AccessControlService::canAccessAction(User, page,
   action)`: `'*'` → true; else `canAccessPage(page)` **and** (page **not
   enforced** (S2 flag off) → true; else explicit action row).
4. **Explicit adoption (S2)**: `config/actions.php` (or a small flag) declares,
   per page, the action catalog and whether actions are enforced; enforcement is
   a deliberate, reviewable, audited act; adoption goes live per page with a
   backfill of current writers' existing actions.
5. **Enforcement**: an `action` Gate + `action:` middleware layered on write/
   export routes (`page:` middleware unchanged for view); `export` is always a
   distinct action.
6. **Vocabulary**: canonical actions = `view, create, edit, delete, export,
   approve`, mapped from the existing audit strings; no new strings without the
   Open Decision #3 discipline.
7. **P7 screen**: the permission editor gains an action-management mode
   (grant/revoke (page, action) rows per user), audited via the `MANAGE_*`
   family — extending, not replacing, the page screen.
8. **Scope-ready**: leave `tbl_action_permissions` room for a future additive
   scope column (§15) so municipality/data-scope (B/C) composes later without
   redesign.

This is **not claimed as approved** — it requires owner sign-off on §17.A (and
separately on the §22 next pass) before any build.

#### 21. Unresolved questions

1. Does the owner adopt action-level authorization (§17.A) at all, and under the
   layered model (§20) or a simpler variant?
2. Is S2 (explicit per-page enforcement flag) accepted, or does the owner prefer
   S1/S3 (§11)?
3. Canonical action catalog per page — is `view/create/edit/delete/export/
   approve` right, or does any page need a different set (e.g. `approve` for
   transaction payouts)?
4. Should `export` be a distinct action on every page that exports (§12.6)?
5. Which pages are adopted first (recommended: none until a contract pass defines
   the catalog; then `all_transactions.php` or `clients.php` as pilots)?
6. Scholar writes, payout/unpaid deletes: are their missing audit strings a
   blocker for those pages' action catalog (§4 gap)?
7. Municipality-scope data model (B): which tables/columns carry the scope
   (carried from Pass 2 — owner decision needed before B/C can be designed).
8. First-admin bootstrap unchanged (§13)? (A `'*'` row grants all actions.)

#### 22. Recommended next research pass

- **Pass 9 — Action-Level Authorization Contract** (if the owner approves
  §17.A/§20): fix the per-page action catalog from the §3/§4 inventory, the S2
  adoption mechanism, the `tbl_action_permissions` DDL + additive migration
  shape, the `canAccessAction` contract, the `action:` middleware semantics, the
  P7 action-management screen contract (routes/requests/audit strings), and the
  pilot adoption pages. Read-only research; no build. **Do not proceed until the
  user confirms.**

---

**HARD STOP — P8 complete.** No code, schema, migration, route, model,
controller, service, view, test, seeder, or DB operation was run; no file other
than this one was modified. Open Decision #6 remains
`DEFERRED — REQUIRES AUTHORIZATION ARCHITECTURE RESEARCH`, split into A
(recommended, not finalized), B/C (not finalized, data-blocked), and D
(unresolved). Next step: Pass 9 on owner approval.

---

### P9 — Action-Level Authorization Contract (2026-08-15)

> **Scope**: contract definition only — turns the Pass 8 recommendation into a
> precise, owner-reviewable authorization contract. **Nothing is implemented.**
> No code, schema, migration, route, model, controller, service, middleware,
> FormRequest, view, JS/CSS, test, seeder, or DB operation was run; no file
> other than this one was modified (verified with `git status`, §20). The
> proposed `tbl_action_permissions` is **conceptual only** — it is **NOT
> created**. P7 is **not** modified. Municipality implementation is **not**
> started.
>
> **Status**: Pass 9 produces a complete recommendation for Open Decision #6.A
> (§18–§19) but does **not** finalize it — every decision point is listed in
> §18 for owner approval. B and C are **not** finalized; D remains unresolved.
>
> **Method**: re-read of the v2 authorization machinery (Pass 8 §2–§4 facts
> carried forward), `routes/web.php` (route → page-key → action mapping),
> `config/scanner.php` (scan modes + audit strings), the P7 build contract and
> Passes 1–8 evidence, and v1 ground truth (Pass 1). Every conclusion is
> labelled FACT / INFERENCE / RECOMMENDATION / REQUIRES OWNER DECISION /
> UNRESOLVED.

#### 1. Objective

Produce the explicit action-level authorization contract that answers:

1. What is an **action**? (§3)
2. Which **actions exist**, with evidence? (§4)
3. Which **pages adopt** action authorization, and in which phase? (§5)
4. Which **page/action combinations** are valid? (§5, §18.2)
5. How is an action **evaluated**? (§3, §11)
6. What does **`'*'`** mean under the action model? (§10)
7. What happens when an **action row is absent**? (§11)
8. How does **page authorization interact** with action authorization? (§2, §6)
9. How are **exports** protected? (§8)
10. How are **direct endpoint requests** protected? (§13)

#### 2. Page-Level Baseline

Preserve the P7 architecture exactly (FACT — Pass 8 §2; P7 contract §3):

```
    Page Permission
          ↓
    Can enter module?
          ↓
       YES / NO
```

Action authorization is a **refinement layered after** page authorization:

```
    Page Permission         (existing tbl_permissions — unchanged)
          ↓
    Action Permission       (proposed tbl_action_permissions — conceptual)
          ↓
      Operation
```

Contract rules:

1. **Do NOT replace `tbl_permissions`** with action permissions.
2. **Do NOT remove or reinterpret existing page permissions.** `page_name`
   stays identical to v1 (ADR-003); `'*'` stays the sole super-admin marker.
3. The page gate decides **whether a user may enter the module at all**. The
   action layer decides **which operations inside the module are allowed**.
4. `view` (VIEW) is the bridge: on an adopted page, page permission **is** the
   VIEW grant (no duplicate view row needed — §6).

#### 3. Action Model

**What is an action?** A named, server-side-checked operation that a user may
perform on a page after entering it (e.g. "create a client on `clients.php`").
It is **not** a page and **not** a program: page = which module, action = which
operation, program = which program the operation targets (§14).

Evaluation chain (conceptual):

```
    user
      ↓
    page (tbl_permissions)      — entry gate, unchanged
      ↓
    action (tbl_action_permissions) — operation gate, new
      ↓
    allowed / denied
```

**Proposed conceptual table `tbl_action_permissions`** (NOT created):

| Column | Type | Rationale |
|---|---|---|
| `id` | INT PK AUTO_INCREMENT | project convention (every legacy table has `id`) |
| `user_id` | INT NOT NULL | grant subject; mirrors `tbl_permissions.user_id` |
| `page_name` | VARCHAR(100) NOT NULL | **must reference real v1 page keys** (`page_name` identical to v1 — ADR-003); never synthetic `clients.php:delete` keys (Pass 8 §6.D rejected) |
| `action` | VARCHAR(50) NOT NULL | canonical action name from the §4 catalog (uppercase, underscore-free verb) |
| `created_at` | DATETIME (optional) | present on `tbl_audit_logs`/`tbl_multi_device_exemptions`; absent on `tbl_program_permissions`. RECOMMENDATION: include (grant-time accountability); not required for correctness |
| `can_access` | TINYINT(1) DEFAULT 1 (optional) | mirrors `tbl_permissions`; presence-of-row = allow (RECOMMENDED, no deny rows — §11.C); the flag is optional future-proofing only |

**Conceptual unique constraint**: `UNIQUE(user_id, page_name, action)` —
exactly the `tbl_program_permissions` pattern. It makes the grant idempotent
(repeat inserts are no-ops on the same triple) and gives the P7 editor a clean
full-replace target. No migration code is written in this pass.

**FACT**: an action row must **never** be grantable for a page the user has no
page row for — page remains the entry gate (§11.D).

#### 4. Canonical Action Catalog

Actions are derived **only** from actual v1/v2 operations and the established
audit vocabulary (Pass 8 §4; Pass 6 §12). No action is added "because other
systems have it".

| Action | Meaning | Existing operation (evidence) | Modules/pages | Required now? |
|---|---|---|---|---|
| `VIEW` | Read: enter page, list/index, detail/show, feeds (`/data`) | every read route; v1 restriction-gated screens; `permittedPages` | all pages | **YES** — the bridge between page and action (§6). On adopted pages page permission == VIEW |
| `CREATE` | Insert a new record | `ADD_CLIENT`, `ADD_HOUSEHOLD`, `ADD_FAMILY_MEMBER`, `ADD_TRANSACTION`, `MANAGE_USER_CREATE`, `ADD_GIP`; scanner scholarship_transaction/date_guarded inserts | clients, households, family members, transactions, scholars, register.php, gip (under clients.php) | **YES** (adopted pages) |
| `EDIT` | Modify an existing record | `EDIT_CLIENT`, `EDIT_TRANSACTION`, `inline-update`, `UPDATE_GIP`, `UPDATE-CEDSSG-PAYMENT` (cedssg_update scanner), scholar update/relink | clients, transactions, scholars, gip | **YES** (adopted pages) |
| `DELETE` | Remove a record | `DELETE_CLIENT`, `DELETE_HOUSEHOLD`, `DELETE_FAMILY_MEMBER`, `DELETE_TRANSACTION`, duplicate batch-delete, payout/unpaid `delete_id` destroys | clients (+duplicates), households, family members, transactions, payout, unpaid | **YES** (adopted pages) |
| `EXPORT` | Stream a CSV/file | `transactions.export`, `scholarship-reports.export`, `unpaid-verifications.export` (all currently page-gated) | transactions, scholarship reports, unpaid verifications | **YES** — always distinct on adopted pages (§8) |
| `APPROVE` | Advance a record's workflow state (e.g. PENDING PAYOUT → PAID) | **NO v1/v2 evidence** — transactions are created directly with a status; the payout **scan** (not an approval action) marks paid | — | **NO** — future capability only; **REQUIRES OWNER DECISION** to add (§18.1) |
| `SCAN` | Run a scanner save (attendance/transaction write) | `SCAN-CEAP`, `SCAN-CEAP_NEW`, `SCAN-CEDSSG`, `SCAN-CEDSSG_NEW`, `SCAN-OTCES`, `SCAN-OTEA`, `SCAN-TODA`, `SCAN-TUPAD`, `SCAN-{program}` (generic) in `config/scanner.php`; modes `scholarship_transaction`, `date_guarded_transaction`, `seat_attendance`, `unpaid_attendance`, `exam_derived`, `update_in_place` | the 14 `scanner_*.php` pages | **Reserved** — no page adopts SCAN in Phase 1 (§5); high-stakes writes, adopt after pilots |
| `VERIFY` | Identity/eligibility check | `verify_mobile.php` port, student verify, grantee search/verify, unpaid verify — **all read-only checks, mostly public** | clients, student, grantee, unpaid (public) | **NO** — folded into VIEW (FACT: every verify is a read; no distinct privileged write). **REQUIRES OWNER DECISION** only if a tracked verify capability is wanted (§18.1) |
| `PAYOUT` | Record payout attendance | `scanner_payout` (`seat_attendance`), `scanner_payout_unpaid` (`unpaid_attendance`) | scanner_payout pages, payout lists | **NO** — folded into SCAN (attendance write) + VIEW/DELETE (lists). **REQUIRES OWNER DECISION** if a distinct "record payout" capability is wanted (§18.1) |
| `MANAGE` | Administer a managed resource (grant/revoke, user create, exemptions) | `MANAGE_USER_CREATE`, `MANAGE_PAGE_PERMISSIONS`, `MANAGE_SUPER_ADMIN_GRANT/REVOKE`, `MANAGE_PROGRAM_PERMISSIONS`, `MANAGE_EXEMPTION_GRANT/REVOKE` | the 4 P7 admin pages (register.php, manage_permissions.php, manage_program_permissions.php, manage_multi_device_exemptions.php) | **Reserved** — P7 stays page-only in Phase 1 (§5); MANAGE is the natural action when adopted, with `manage_permissions.php:super-admin` as the highest-privilege special op (Pass 8 §13) |

**FACT — action names are single uppercase verbs** (`VIEW`, `CREATE`, …),
matching the underscore-free portion of the audit vocabulary's `VERB` slot
(`ADD_CLIENT` → `CREATE` on `clients.php`; the `MANAGE_*` prefix groups admin
events, the `SCAN-*`/`UPDATE-*` dash strings are a scanner-config artifact, Pass
6 §2.4). The action catalog maps 1:1 onto existing audit strings (Pass 6 §12),
so **no new audit vocabulary is introduced for business operations** (only the
grant-change event in §15, which is a new, justified string).

#### 5. Page/Action Adoption Matrix

Established by reading `routes/web.php` and every P1–P7 controller (FACT).
Phase 1 adopts only pages whose adoption is low-risk and high-value; everything
else stays page-only (today's behavior) until the model is proven.

| Page | Existing page gate | Action authorization? | Actions | Reason |
|---|---|---|---|---|
| dashboard | none (auth + single-device) | **Page-only** | — | not a gated resource; every authenticated user |
| `currently_logged_users.php` | `page:` | Page-only Phase 1 | `VIEW`, `FORCE` (reserved) | P1 parity; low priority |
| `force_logout.php` | `page:` | Page-only Phase 1 | `FORCE` (reserved) | P1 parity |
| `clients.php` | `page:clients.php` | **ADOPT (pilot)** | `VIEW, CREATE, EDIT, DELETE` | core registry; highest-value tightening; shared sub-resources map onto these actions (§5.1) |
| `household.php` | `page:household.php` | **ADOPT (pilot)** | `VIEW, CREATE, DELETE` | FACT: no household EDIT exists (no `edit_household` route — blueprint §8 row 38 shows only add/view/delete); small surface |
| `all_transactions.php` | `page:all_transactions.php` | **ADOPT (pilot)** | `VIEW, CREATE, EDIT, DELETE, EXPORT` | highest daily usage; exercises program interaction (§14); has an export (§8) |
| `scholars.php` | `page:scholars.php` | **ADOPT (pilot)** | `VIEW, CREATE, EDIT` | self-contained; relink = EDIT; no export |
| `scholarship_reports.php` | `page:scholarship_reports.php` | Page-only Phase 1 | `VIEW, EXPORT` (reserved) | read-only reporting page; export already page-gated |
| `scanner_*.php` (14 keys) | `page:scanner_*.php` each | Page-only Phase 1 | `VIEW, SCAN` (reserved) | high-stakes attendance/transaction writes; adopt **after** pilots prove the model; scanner program-gate question stays deferred (Pass 8 §8) |
| `scanned_payouts*.php` (3) | `page:` each | Page-only Phase 1 | `VIEW, DELETE` (reserved) | low traffic; parity |
| `unpaid_verifications.php` | `page:unpaid_verifications.php` | Page-only Phase 1 | `VIEW, DELETE, EXPORT` (reserved) | parity; admin + public surfaces |
| `update_logs.php` | `page:update_logs.php` | Page-only Phase 1 | `VIEW` | view-only page |
| `register.php` | `page:register.php` | **ADOPT (pilot)** | `CREATE` | single-operation page (create user); page entry already implies the form (§6) |
| `manage_permissions.php` | `page:` | Page-only Phase 1 | `MANAGE` + `super-admin` special op (reserved) | **P7 must not be broken**; `'*'` toggle logic stays page-level until adoption (Pass 8 §13) |
| `manage_program_permissions.php` | `page:` | Page-only Phase 1 | `MANAGE` (reserved) | P7 parity |
| `manage_multi_device_exemptions.php` | `page:` | Page-only Phase 1 | `MANAGE` (reserved) | P7 parity |
| `audit_logs.php` | `page:` | Page-only Phase 1 | `VIEW` | read-only viewer; **never** action-beyond-page, never a read-audit (Pass 8 §13/§14) |
| public self-service (`student/*`, `unpaid-verification*`, `grantee-search/*`, `grantee-update/*`, `grantee/*`, `qr-viewer`) | none | **No action authorization (never)** | — | anonymous flows (v1 parity); no authz layer applies (Pass 8 §3) |

**§5.1 Shared-key sub-resource mapping (`clients.php`)** — FACT: the
`page:clients.php` group also gates family members, duplicates, photo upload,
and GIP. Under (page, action) these inherit the page's actions:

| Route (FACT) | Page key | Action (RECOMMENDED classification) |
|---|---|---|
| `clients.index` / `clients.data` / `clients.show` / `clients.verify-mobile` / `duplicates.index` / `duplicates.data` | `clients.php` | `VIEW` |
| `clients.create` / `clients.store` | `clients.php` | `CREATE` |
| `family-members.create` / `family-members.store` | `clients.php` | `CREATE` (sub-resource) |
| `gip.store` | `clients.php` | `CREATE` (sub-resource) |
| `clients.edit` / `clients.update` / `clients.photo` | `clients.php` | `EDIT` |
| `clients.destroy` / `duplicates.destroy` | `clients.php` | `DELETE` |

No per-resource page keys are invented (ADR-003); the mapping above is the
contract for how sub-operations classify. **REQUIRES OWNER DECISION** whether
GIP/family-member creation should be `CREATE` on `clients.php` or a future
dedicated action (§18.2).

**§5.2 First adoption phase = the five pilots**: `clients.php`, `household.php`,
`all_transactions.php`, `scholars.php`, `register.php`. Every other page stays
page-only (byte-for-byte today's behavior) until the owner approves the next
tranche. **RECOMMENDATION.**

#### 6. VIEW Semantics

1. **VIEW controls page entry, list/index, detail/show, AND feed (`/data`)
   responses** — all read surfaces on a page. On an adopted page, the page
   permission row **is** the VIEW grant: there is **no separate `view` row**
   (`tbl_permissions` already encodes "can enter this page").
2. **No duplicate gate**: the page gate is not re-checked for VIEW; the action
   check for VIEW is simply `canAccessPage`. This keeps the middleware stack
   unchanged for read routes.
3. **Feed protection (the direct-feed concern)**: a user who cannot enter the
   page cannot call its `/data` feed — `AuthorizePage` already answers 403 JSON
   on `expectsJson()` for every route in the page group (FACT). A user **with**
   page permission may call the feed (that **is** VIEW). There is no scenario in
   which page entry is granted but VIEW is not — they are the same predicate.
   (§13 documents that feeds must not be additionally action-gated, or read
   parity breaks.)
4. **UI is not the boundary**: the sidebar hides links via `canAccessPage`
   (FACT), but that is presentation; the middleware is the boundary.

#### 7. CREATE / EDIT / DELETE Semantics

| Action | Precisely | Domain-specific handling |
|---|---|---|
| `CREATE` | Insert a **new** record for the page's primary resource (+ documented sub-resources, §5.1) | GIP add and family-member add classify as `CREATE` on the shared `clients.php` key (§5.1). Scanner inserts classify as `SCAN`, **not** `CREATE` (a scanner save is a scripted, config-driven insert — `SCAN` preserves its operational identity, Pass 8 §7). Scholar create = `CREATE` on `scholars.php`. User create = `CREATE` on `register.php` |
| `EDIT` | Modify an **existing** record | Transaction inline-update and page edit both = `EDIT` on `all_transactions.php`. Scholar relink (`scholars.update-client-id`) = `EDIT`. GIP upsert-to-existing (`UPDATE_GIP`) = `EDIT` under `clients.php`. Scanner `cedssg_update` (`UPDATE-CEDSSG-PAYMENT`, update-in-place) = `SCAN`, not `EDIT` — the scanner mode is a scan workflow, not a general editor (§9) |
| `DELETE` | Remove/delete a record or row set | Duplicate batch-delete = `DELETE` (per-row `DELETE_CLIENT`), payout/unpaid `delete_id` destroys = `DELETE` on their pages, household destroy = `DELETE`, transaction destroy = `DELETE` |

**RECOMMENDATION**: do **not** force scanner/payout operations into `EDIT` or
`DELETE` — `SCAN` and the reserved domain actions preserve authorization
clarity for operational (attendance/payout) writes (§9).

#### 8. EXPORT Semantics

1. **Current exports (FACT — routes/web.php)**: `transactions.export`
   (`all_transactions.php`, P3, 4 modes), `scholarship-reports.export`
   (`scholarship_reports.php`, P6), `unpaid-verifications.export`
   (`unpaid_verifications.php`, P5). **All three are currently page-gated only.**
   No other exports exist (payout attendance and audit logs have no export —
   FACT).
2. **`EXPORT` is always a distinct action** on an adopted page that exports
   (RECOMMENDATION, Pass 8 §12.6). `VIEW` alone does **not** grant `EXPORT`.
   A user with `VIEW` but no `EXPORT` may view list/feed/detail but must get
   403/redirect on the export route.
3. **Direct export endpoint**: the export route carries both its page gate
   (unchanged) and the `EXPORT` action check (§13). Knowing the export URL
   grants nothing — the check is server-side on the route, the UI link is only
   presentation.
4. **Non-adopted pages**: an export on a page that has not adopted actions keeps
   today's behavior (page permission = all actions incl. export) until adopted
   (§12) — no lockout, no accidental open export.
5. **Future**: if a future page adds an export, `EXPORT` must be added to its
   action catalog at adoption time, never left implicit (§17 fail-closed).

#### 9. Domain-Specific Actions

Inspection of P4/P5/P6 operations (FACT — config/scanner.php, routes, services):

| Actual operation | Current endpoint | Current authorization | Recommended action | Adopt now or later |
|---|---|---|---|---|
| Scanner transaction insert (CEAP/CEAP_NEW/CEDSSG/CEDSSG_NEW/OTCES/OTEA/TODA/TUPAD/generic) | `POST scanners/{key}/save` | `page:scanner_{key}.php` only | `SCAN` | **Later** (Phase 1 keeps scanners page-only; §5) |
| Scanner update-in-place (cedssg_update) | `POST scanners/cedssg_update/save` | page gate only | `SCAN` | Later |
| Scanner lookup (identity/eligibility read) | `POST scanners/{key}/lookup` | page gate only | `VIEW` (read) | Later (view is covered by the page gate) |
| Payout seat attendance (`scanner_payout`) | `POST scanners/payout/save` | page gate only | `SCAN` (attendance write) | Later |
| Unpaid attendance (`scanner_payout_unpaid`) | `POST scanners/payout_unpaid/save` | page gate only | `SCAN` | Later |
| QR viewer | `GET qr-viewer` | **public** (no auth) | none | Never (anonymous) |
| Student photo upload / verify | `student/*` | **public** | none | Never (anonymous) |
| Grantee self-update | `grantee-update/*` | **public** | none | Never (anonymous) |
| Unpaid self-service submit | `unpaid-verification/submit` | **public** | none | Never (anonymous) |
| Unpaid search/verify | `grantee-search/*` | **public** | none | Never (anonymous) |
| GIP add/update | `POST clients/{client}/gip` | `page:clients.php` | `CREATE`/`EDIT` (sub-resource, §5.1) | **Pilot** (with clients.php) |
| Scholar create/update/relink | `POST scholars`, `PUT scholars/{s}`, `POST scholars/update-client-id` | `page:scholars.php` | `CREATE`/`EDIT` | **Pilot** (with scholars.php) |
| Transaction inline-update | `POST transactions/inline-update` | `page:all_transactions.php` + program | `EDIT` | **Pilot** (with all_transactions.php) |
| Payout list delete | `POST payout-attendance/{v}/data` (delete_id) | page gate | `DELETE` | Later |
| Unpaid delete | `POST unpaid-verifications/data` (delete_id) | page gate | `DELETE` | Later |

**No workflows are invented** — every row above is an existing operation. The
verification/QR/public flows are read-only or anonymous and get **no** action
grants.

#### 10. `'*'` Super-Admin Contract

**RECOMMENDATION — `'*'` bypasses both page AND action restrictions** (Pass 8
§7 confirmed):

1. `canAccessAction(U, page, action)` short-circuits **true** for any `'*'`
   holder, **regardless of whether any action row exists for that user**.
2. **`'*'` user + no action row** → allowed (the marker is the grant; no action
   rows are needed or implied for super-admins).
3. **`'*'` user + hypothetical explicit deny row** → allowed (if deny rows are
   ever introduced, `'*'` still wins — §11.C; Pass 8 §10.3). RECOMMENDATION:
   **no deny rows exist**, so this case cannot arise (§11.C).
4. **`'*'` user + future municipality restriction** → **UNRESOLVED** and
   **out of scope** (Pass 8 §17.B/C; §16): whether data-scope can constrain a
   super-admin is a B-decision, not an action-layer decision. RECOMMENDATION
   (for later): treat `'*'` as scope-exempt by default, same as today's
   program/page behavior.
5. **Preserved (non-negotiable)**: `'*'` is the sole super-admin marker; **no
   username-based admin checks**; **no user-ID-based admin checks** (ADR-003).
   The P7 `'*'` toggle stays page-level and becomes a dedicated
   `super-admin` special action only when `manage_permissions.php` adopts
   actions (§5, Pass 8 §13).

#### 11. Missing Action Row Semantics

This is the critical decision. Case table:

| Case | Page permission | Action row | Verdict |
|---|---|---|---|
| **A** | ✓ | present, allowed | **ALLOW** |
| **B** | ✓ | missing | **Page non-adopted → ALLOW** (today's behavior, §12). **Page adopted → DENY for write/export actions** (fail closed); `VIEW` still allowed (page = VIEW). **RECOMMENDED** |
| **C** | ✓ | present, denies | **DENY** — but RECOMMENDATION: **no deny rows** (allow-list only, Pass 8 §10); if a `can_access=0` flag is ever introduced, deny wins over allow, and `'*'` still wins over deny (§10.3) |
| **D** | ✗ | present (any) | **DENY** — action rows never grant page entry; page is the entry gate. **Fail closed** (§6, §13) |
| **E** | `'*'` | — | **ALLOW everything** (§10) |

**Safest default (RECOMMENDATION)**: **fail closed** —
`canAccessAction` returns **false** for:
- any unknown **page**,
- any unknown **action**,
- any adopted page where the (page, action) row is absent,
- any action row present without a page row (case D).

The **only** deliberate exceptions (documented compatibility mechanisms) are:
(a) non-adopted pages (today's behavior), and (b) `'*'` (super-admin). Newly
introduced privileged actions are **deny-by-default until explicitly granted**.
This is required by the security properties (§17).

#### 12. Backward Compatibility

Problem (FACT): every existing v2 user has page permissions and **zero** action
rows. Action authorization must be introduced without (a) locking out
legitimate users, (b) silently granting everyone every new action, (c) breaking
P7, or (d) touching production authorization data.

| Strategy | Mechanism | Risk |
|---|---|---|
| **S1. Implicit adoption** | page "adopted" the moment its first action row exists | one grant flips the whole page's behavior — rejected (Pass 8 §11) |
| **S2. Explicit per-page enforcement flag (RECOMMENDED)** | `config/actions.php` (or a small data flag) declares, per page, the action catalog + enforcement state; enforcement off ⇒ today's behavior; on ⇒ action rows required | deterministic, reviewable, reversible |
| **S3. Full backfill at go-live** | adopt all pages at once; backfill `CREATE/EDIT/DELETE` for every current holder via SQL | production data change; behavior changes everywhere at once |

**RECOMMENDED strategy — S2 with per-page S3-style backfill at adoption**:

1. **Cutover (P9 → build)**: new table empty, enforcement flags off, zero
   production data change. Behavior is byte-identical to today.
2. **Per-page adoption procedure (RECOMMENDED, audited)**:
   a. An admin grants the page's current holders the actions they currently
      exercise (`CREATE`/`EDIT`/`DELETE`/`EXPORT` as applicable) via the P7
      action-management screen (audited — §15). **Grant first.**
   b. The enforcement flag for that page is flipped on. **Flip second.**
      No lockout window (grants precede enforcement).
   c. The adoption is recorded in the audit trail (grant events) and in
      `IMPLEMENTATION_LOG` at build time.
3. **P7 is never broken**: the P7 admin pages stay page-only in Phase 1 (§5);
   their `'*'`/exemption logic is untouched.
4. **No silent grant-everyone**: the backfill grants are **explicit, per-user,
   per-page, audited actions taken by a permission admin** — never a blind
   seed, never an automatic rule.

#### 13. Enforcement Contract

**Canonical enforcement layer: route middleware backed by the single ACL
service**, mirroring the existing `AuthorizePage` pattern.

1. **The one implementation** — a future `AccessControlService` extension
   `canAccessAction(User, page, action)` containing all semantics of §11 (§10,
   fail-closed, adoption flags). This is the canonical decision point (ADR-003:
   one ACL service). Per-request caching mirrors the existing
   `pagePermissions`/`programPermissions` pattern (FACT).
2. **`action:` middleware** — a mirror of `AuthorizePage` (403 JSON when
   `expectsJson()`, else redirect-dashboard + `login_status=denied`), applied
   **on the specific mutation/export routes** of adopted pages, e.g.
   `action:all_transactions.php:create`. The `page:` middleware stays on the
   group (entry/view); `action:` layers on the writes.
3. **Gate** — `Gate::define('action', fn (User $u, string $page, string $action)
   => app(AccessControlService::class)->canAccessAction(...))`, for
   controller-level checks where middleware is awkward (the `ClientPolicy`
   precedent — the only existing policy, FACT).
4. **Layers evaluated** — middleware for route-level; policy only for the
   existing `ClientPolicy`-style need if any; **FormRequests do NOT authorize**
   (they validate; `authorize()` returning true is not a boundary — FACT: the
   P7 requests return `true`); **services do NOT authorize** (they assume
   authorization; a defensive service re-check is optional belt-and-braces but
   never the boundary).
5. **Bypass prevention (mandatory)**:
   - **UI bypass** — links/buttons are presentation only; the server check is
     authoritative (§6.4).
   - **Direct POST bypass** — every store/update/destroy/inline-update route
     carries `action:`.
   - **Direct JSON endpoint bypass** — every `/data` feed stays under the page
     group (403 JSON for the unpermitted); feeds are VIEW, not separately
     action-gated (§6.3).
   - **Direct export bypass** — the export route carries `page:` **and**
     `action:...:export` (§8.3).
6. **No enforcement is skipped for anonymous routes**: public self-service
   routes have no authz layer by design (v1 parity) and are outside this
   contract (§5).

#### 14. Program Permission Interaction

Do not redesign program permissions (RECOMMENDATION — Pass 8 §8). Coexistence
order for domain operations that consume programs (transactions, feeds with
program filter, GIP):

```
    PAGE   (canAccessPage — entry)
      ↓
    ACTION (canAccessAction — which operation)
      ↓
    PROGRAM (canAccessProgram — which program, 17-program model)
      ↓
   operation executes
```

1. **Program is evaluated AFTER action, only for operations that touch a
   program** (FACT: `TransactionController::authorizeProgram` on store/update
   today; feeds apply program restrictions; scanners do **not** check program —
   deferred, Pass 8 §8).
2. Example: `transactions EDIT on GIP` =
   `canAccessAction(all_transactions.php, EDIT)` **and**
   `canAccessProgram('GIP')`. Both must pass.
3. The action layer adds **no** program checks to admin screens (§8.3 of
   Pass 8) and no program checks where none exist today (scanners stay as-is —
   deferred question untouched).
4. Empty `tbl_program_permissions` remains "unrestricted" (v1 parity, FACT);
   the action layer does not change that.

#### 15. Audit Relationship

Preserve the P7 `AuditService` contract exactly (FACT — sole writer, 7
`MANAGE_*` strings, no `VIEW_*` reads; Pass 6/P7 §12).

1. **Two distinct concepts** (RECOMMENDATION):
   - **Changing someone's action permission** → audited as a **grant-change
     event** (see 2).
   - **Performing an authorized business action** → audited by the existing
     domain string (`ADD_CLIENT`, `EDIT_TRANSACTION`, `SCAN-CEAP`, …). No
     change.
2. **Grant-change audit (RECOMMENDATION — one new string)**: when a permission
   admin saves a user's action grants for a page, write
   **`MANAGE_ACTION_PERMISSIONS`** — `target_table = 'tbl_action_permissions'`,
   `target_id` = subject user id, `old_value`/`new_value` JSON
   `{'username', 'page', 'actions': [allowed set]}`. Justification for the new
   string: the P7 page-permission event records *pages*; action grants are a
   distinct object (page × action set) that must be filterable in the viewer.
   This is the **only** new audit string proposed; it follows the Open
   Decision #3 stability discipline. **REQUIRES OWNER DECISION** (§18.7).
   - The `'*'` toggle keeps `MANAGE_SUPER_ADMIN_GRANT`/`REVOKE` unchanged.
3. **No read-audits**: viewing feeds, the audit viewer, or the leaderboard
   writes nothing (`VIEW` is not audited). Never add `VIEW_*` (Pass 6 §9.2).
4. **Secrets**: action/permission payloads contain only `username`, `page`,
   `action` names — never `password`/`session_token` (P7 §12.3; §17).

#### 16. Municipality Compatibility

Do **not** design municipality authorization (REQUIRES OWNER DECISION for the
data model — Pass 2 authoritative: municipality is a **data attribute, not an
authorization boundary**). This section only verifies the proposed action model
can later support:

```
    User → Page → Action → Program → Municipality/Data Scope
```

1. **Composable**: the future check is `canAccessAction(page, action)` **and**
   `canAccessScope(record)`. Scope is a row-filter dimension, orthogonal to the
   operation gate (Pass 8 §15.1).
2. **Storage assumption (kept)**: `tbl_action_permissions` leaves room for a
   future additive scope column **without** touching `tbl_permissions` (Pass 8
   §9.A/§15.2). If B/C storage were chosen instead (action column on
   `tbl_permissions`), the future scope column would pollute the v1-identical
   table — **this is a reason to keep storage A**.
3. **Assumptions to avoid** (would make the future model difficult):
   - baking municipality into `page_name` (violates ADR-003; rejected, §3),
   - scope-by-UI-only (feeds/exports must be the server-side enforcement seam —
     the existing P2/P3 municipality filters are the natural target, Pass 8
     §15.4),
   - assuming `'*'` is scope-constrained (default: scope-exempt, §10.4 —
     **UNRESOLVED**, owner decision when B is researched).
4. **Data blocker (carried, UNRESOLVED)**: `tbl_clients.city_municipality`/
   `barangay` store int IDs as varchar (no FK); only
   `tbl_unpaid_verifications.municipality_id` is a true FK to
   `tbl_municipalities`; households/transactions derive municipality via client
   join (Pass 2). No B/C design is possible until the owner picks the scope
   data model.
5. **Verdict**: the action model imposes **no assumption** that blocks the
   future chain; the chain composes as ANDs (page, action, program, scope).

#### 17. Security Requirements

Mandatory properties of the future build (all server-side):

1. **Fail closed for unknown actions** (§11): unknown page/action → deny.
2. **No UI-only authorization** (§6.4, §13.5): UI hides buttons/links;
   middleware is the boundary.
3. **No username-based bypass** (ADR-003 — no `super_admin`/`jordi` name
   checks; grep-enforceable).
4. **No user-ID-based bypass** (ADR-003 — no `user_id = 1`; all checks through
   the ACL service).
5. **`'*'` remains centralized** (§10): the marker bypasses page and action
   restrictions in the one service method.
6. **Authorization enforced server-side** on every write, export, and feed
   route of adopted pages (§13).
7. **Exports protected** (§8): distinct `EXPORT` action on the export route.
8. **Feeds protected** (§6.3): page group gate (403 JSON) covers `/data`.
9. **Direct endpoints protected** (§13.5): every mutation route carries
   `action:`.
10. **Permission changes audited** (§15): `MANAGE_ACTION_PERMISSIONS` (+ the
    existing `MANAGE_*` family unchanged).
11. **Canonical decision point** (§13.1): `AccessControlService`
    (`canAccessAction`) or its future extension is the **only** authorization
    implementation; middleware/gates call it.
12. **No secrets in authorization/audit payloads** (§15.4): only
    username/page/action names; never password/session_token.

#### 18. Owner-Approval Decisions

Every decision below is **required before implementation** and is **not**
claimed as approved.

| # | Decision | Recommendation | Status |
|---|---|---|---|
| 1 | **Action vocabulary** (§4) | `VIEW, CREATE, EDIT, DELETE, EXPORT` adopted; `SCAN`, `MANAGE` reserved; `APPROVE`, `VERIFY`, `PAYOUT` **not** added (no evidence / folded in) | **REQUIRES OWNER DECISION** (each addition/omission) |
| 2 | **Page adoption matrix** (§5) | Phase 1 pilots = `clients.php`, `household.php`, `all_transactions.php`, `scholars.php`, `register.php`; all else page-only; sub-resource mapping §5.1 | **REQUIRES OWNER DECISION** (esp. GIP/family-member classification) |
| 3 | **`'*'` semantics** (§10) | `'*'` bypasses page **and** action; no deny rows; scope-exempt later | **REQUIRES OWNER DECISION** |
| 4 | **Missing action-row behavior** (§11) | fail closed (B/E exceptions only); no deny rows; case D always denies | **REQUIRES OWNER DECISION** |
| 5 | **Backward-compatibility strategy** (§12) | S2 explicit per-page flag + audited per-page backfill at adoption; zero cutover data change | **REQUIRES OWNER DECISION** |
| 6 | **Conceptual `tbl_action_permissions`** (§3) | `id, user_id, page_name, action, created_at`; `UNIQUE(user_id, page_name, action)`; presence = allow | **REQUIRES OWNER DECISION** (exact DDL fixed at build, after approval) |
| 7 | **Enforcement mechanism** (§13) | `canAccessAction` + `action:` middleware + `action` Gate; no FormRequest/Service authz | **REQUIRES OWNER DECISION** |
| 8 | **Program/action interaction** (§14) | PAGE → ACTION → PROGRAM order; program only for program-consuming ops; admin screens get no program checks | **REQUIRES OWNER DECISION** |
| 9 | **Audit grant-change string** (§15.2) | one new string `MANAGE_ACTION_PERMISSIONS` (Open Decision #3 discipline); 7 existing `MANAGE_*` unchanged | **REQUIRES OWNER DECISION** |

#### 19. Open Decision #6 Status

| Component | Status after Pass 9 |
|---|---|
| **A. Action-level authorization** | **Complete recommendation produced** (this pass). Still `DEFERRED — REQUIRES OWNER APPROVAL` (§18.1–§18.9). Not implemented. |
| **B. Municipality/data-scope** | **NOT finalized.** Data-blocked (Pass 2). Assigned to the future Pass 10 research (§23). |
| **C. Combined model (action + scope)** | **NOT finalized.** Only verified as composable (AND chain, §16). Depends on A approval + B data model. |
| **D. Final schema** | **UNRESOLVED.** Candidate `tbl_action_permissions` (storage A) is justified (§3, §16.2) but only locked after A approval and B/C shape is known. Any migration is additive + `schema:dump` regen (AGENTS.md). |

Open Decision #6 remains exactly
`DEFERRED — REQUIRES AUTHORIZATION ARCHITECTURE RESEARCH` pending owner
approval of A; Pass 9 finalizes nothing.

#### 20. Facts

1. Page-level authorization is the sole v2 mechanism; a page-key holder can
   exercise every route in the group (Pass 8 §2; this pass's route/controller
   re-read).
2. `tbl_permissions`, `tbl_program_permissions`, `tbl_multi_device_exemptions`
   are verified byte-identical to the production schema (Pass 4 §11).
3. `'*'` is the sole super-admin marker; no username/id checks exist (grep).
4. The audit vocabulary enumerates the operations; scholar writes,
   payout/unpaid deletes, and exports are un-audited (parity); scanner audit
   strings are `SCAN-*`/`UPDATE-*` dash-style config artifacts (config/scanner.php).
5. Exactly **three** exports exist: transactions, scholarship reports, unpaid
   verifications (routes/web.php).
6. `household.php` has no EDIT route (create/view/delete/feed/search only).
7. `clients.php` gates sub-resources (family members, duplicates, photos, GIP).
8. Scanner routes do not check `canAccessProgram` (deferred); transaction
   create/update do.
9. Public self-service routes (student/unpaid/grantee/QR) are anonymous (v1
   parity) and outside any authz layer.
10. No municipality data-scope exists; `tbl_clients` municipality/barangay are
    int-in-varchar; only `tbl_unpaid_verifications.municipality_id` is a true FK
    (Pass 2).
11. This pass modified **only** this file (`git status` clean elsewhere).

#### 21. Inferences

1. The five pilot pages exercise every canonical action and every enforcement
   shape (write, export, shared-key, program) with minimal blast radius.
2. `SCAN` and `MANAGE` as reserved actions give the model room to grow without
   inventing vocabulary now.
3. The S2 adoption strategy keeps production byte-identical at cutover and makes
   each adoption a deliberate, audited event.
4. The AND chain (page, action, program, scope) is additive and requires no
   redesign of any existing layer.

#### 22. Unresolved Questions

1. Does the owner approve the §18 decisions (all nine)?
2. Is the Phase 1 pilot set right, or should scanners/payout adopt first?
3. Should GIP/family-member creation be `CREATE` on `clients.php` or a future
   dedicated action (§5.1)?
4. Is `MANAGE_ACTION_PERMISSIONS` the right single grant-change string (§15.2)?
5. Should `can_access` flag be included in `tbl_action_permissions` (§3) or is
   pure presence-of-row sufficient?
6. Scanner program-gate question (Pass 3 §5 / Pass 4 §14.5) — still deferred;
   action adoption does not resolve it.
7. Municipality-scope data model (B) — which tables/columns carry the scope
   (Pass 2); pre-requisite for Pass 10.
8. Whether `'*'` is scope-exempt in the future (B) (§10.4, §16.3).

#### 23. Recommended Next Pass

- **Pass 10 — Municipality / Data-Scope Research**: read-only research on the
  municipality dimension (Open Decision #6.B) **only after the owner picks the
  scope data model** (Pass 2 evidence + §16.4) and approves the §18 decisions
  for A. Do not proceed until the user confirms.
- If the owner **rejects** action-level authorization, Pass 9's contract is
  archived and Open Decision #6.A is closed as "rejected — page-level stands".

---

**HARD STOP — Pass 9 complete.** No code, schema, migration, route, model,
controller, service, middleware, FormRequest, view, JS/CSS, test, seeder, or DB
operation was run; `tbl_action_permissions` was **not** created; P7 was **not**
modified; no file other than this one was modified. Open Decision #6 remains
`DEFERRED — REQUIRES AUTHORIZATION ARCHITECTURE RESEARCH`, with A recommended
(§18, owner approval pending), B/C not finalized (data-blocked), D unresolved.
Next step: owner approval of the §18 decisions, then Pass 10 (municipality/
data-scope research) or build.

---

### P10 — Municipality/Data-Scope Authorization Research (2026-08-15)

> **Scope**: research + architecture analysis only — determines the correct
> architecture for municipality/data-scope authorization (Open Decision #6.B).
> **Nothing is implemented.** No code, schema, migration, route, model,
> controller, service, middleware, policy, Blade view, seeder, or test was
> written; no database (production or local) was touched; v1 was not modified;
> no file other than this one was modified (verified with `git status`, §22.13).
> The proposed `tbl_user_municipalities` is **conceptual only** — it is **NOT
> created**.
>
> **Method**: re-read of the P2/P3/P8/P9 evidence already recorded in this
> document; verification against the committed schema (`database/schema/
> mysql-schema.sql`) and the P1–P7 v2 source (`routes/web.php`,
> `config/scanner.php`, controllers, services, `AccessControlService`,
> `ClientPolicy`); and the P2 v1 trace (§2 references). Every conclusion is
> labelled FACT / INFERENCE / RECOMMENDATION / REQUIRES OWNER DECISION /
> UNRESOLVED. No previous finding was contradicted by source evidence during
> this pass (§20); nothing in P8/P9 was rewritten.

#### 1. Existing Municipality Representation

The municipality dimension is verified from `database/schema/mysql-schema.sql`
(FACT).

**Dimension root.**

- **`tbl_municipalities`** — `id` INT PK AUTO_INCREMENT, `name`
  varchar(255) NOT NULL, `code` varchar(5) NULL; KEY `m_n` (`name`). No column
  on it marks "archived"/"active".

**Real integer FKs to `tbl_municipalities.id` (FACT — the ONLY two in the
schema):**

1. `tbl_barangays.municipality_id` INT NOT NULL, FK ON DELETE CASCADE.
2. `tbl_unpaid_verifications.municipality_id` INT NOT NULL, FK (no cascade).

**Per-table classification (FACT, verified in schema + v2 source):**

| Table | Municipality representation | Category |
|---|---|---|
| `tbl_municipalities` | dimension root (`id`, `name`, `code`) | — (reference) |
| `tbl_barangays` | `municipality_id` INT, real FK | **A — direct FK** |
| `tbl_unpaid_verifications` | `municipality_id` INT, real FK | **A — direct FK** |
| `tbl_clients` | `city_municipality` varchar(100) **NOT NULL** — int IDs stored as strings; `barangay` varchar(100) — same | **B — varchar/string** (no FK; `KEY idx_clients_muni`, `idx_clients_brgy` exist) |
| `tbl_household` | none; `head_household` FK → `tbl_clients.id`; municipality **derived via head client** | **C — derived through client** |
| `tbl_transactions` | none; `client_id` FK → `tbl_clients.id` | **C — derived through client** |
| `tbl_scholar_info` | none; `client_id` FK → `tbl_clients.id` | **C — derived through client** |
| `tbl_gip_info` | none; `client_id` FK → `tbl_clients.id` | **C — derived through client** |
| `tbl_family_members` | none; `client_id`/`relative_id` → `tbl_clients.id` | **C — derived through client** |
| `tbl_payout_scans` / `tbl_payout_scans2` / `tbl_payout_scans_unpaid` | none; `transaction_id` FK → `tbl_transactions.id` → `client_id` | **C — derived through transaction → client** |
| `tbl_exam` | `town` varchar(255), `barangay` varchar(255) — **municipality NAME strings**, not ids | **E — display text only** |
| `tbl_seats` / `tbl_seats2` | `town` varchar(100) — name string | **E — display text only** |
| `tbl_details`, `tbl_kababaihan`, `temp_details` | `TOWN`/`town` name strings | **E — display text only** (legacy import tables) |
| `tbl_users` | none | **F — no relationship** |
| `tbl_permissions` | none | **F — no relationship** |
| `tbl_program_permissions` | none | **F — no relationship** |
| `tbl_multi_device_exemptions` | none | **F — no relationship** |
| `tbl_audit_logs` | none | **F — no relationship** |

**v2 model facts:** `Client` casts `city_municipality`/`barangay` to integer and
`belongsTo(Municipality::class, 'city_municipality')` (FACT) — the app treats the
varchar as an int at the model layer while the column stays varchar (parity).
`UnpaidVerification` has a real integer FK relationship. `HouseholdService::
generateHouseholdId` derives the household ID prefix from the head client's
municipality `code` (fallback: name-derived), but the household row itself
carries no municipality.

**Key conclusion (INFERENCE, consistent with Pass 2):** the schema has **two
consistent scoping surfaces** — `tbl_clients.city_municipality` (authoritative
for every client-rooted table) and `tbl_unpaid_verifications.municipality_id`
(the only standalone row-level FK). Everything else derives through the client
chain.

#### 2. Current v1 Municipality Filtering

Traced in Pass 2 (§5 table); re-affirmed as FACT here.

- **Pages with municipality filters**: clients, households, all transactions,
  scanned payouts (3 variants), unpaid verifications, scholarship reports,
  duplicates.
- **Mechanism**: filter comes from a GET/POST parameter (`municipality`,
  `municipality_id`, `brgy`, `barangay`) and becomes a SQL `WHERE` on
  `c.city_municipality` (client-rooted) or `uv.municipality_id` (unpaid).
- **Default behavior**: parameter absent ⇒ **no municipality condition ⇒ "All
  municipalities"** (FACT).
- **Joins**: `INNER/LEFT JOIN tbl_municipalities m ON m.id = c.city_municipality`
  (int-vs-varchar comparison works because the varchar holds integer strings).
- **Exports**: `export_unpaid_verifications.php`, `fetch_scholarship_reports.php`
  CSV path, transaction export — same param-driven WHERE; **omitted param ⇒
  full export** (FACT).
- **Writes**: `add_client.php` `intval()` on municipality; `unpaid_save.php`
  stores the submitted `municipality_id` with **no client cross-check**;
  scanner saves accept any client/transaction id with no municipality
  restriction (FACT — Pass 2 §5, §6).
- **Public self-service verify**: `search_grantee.php`/`search_unpaid_grantee.php`
  compare `intval(client.city_municipality) !== submitted municipality_id` and
  reject ("Municipality does not match our records.") — **an integrity check,
  not authorization** (FACT).

**Verdict (INFERENCE):** v1 municipality filtering is **reporting/filtering, not
authorization**. No user is ever restricted; the default is "All"; filters are
omittable client-side and only shape result sets.

#### 3. Current v2 Municipality Handling

Verified against v2 source (FACT for every bullet).

- **Where values are read**: `ClientController::data()`, `DuplicateController::
  data()`, `HouseholdController::data()`, `TransactionController::data()` +
  `applyExportFilters()`, `PayoutAttendanceController::data()`,
  `ReportController::scholarshipData()`/`scholarshipExport()`, and
  `UnpaidVerificationController::data()`/`export()` all read an optional
  municipality parameter and apply `WHERE` only when present.
- **Centralized?** **NO.** Every controller builds its own join + WHERE. The
  single `AccessControlService` (the one authorization service) has **no
  municipality concept** — its surface is `isSuperAdmin`, `canAccessPage`,
  `canAccessProgram`, `isSingleDeviceExempt`, `isMultiDeviceExempt`,
  `permittedPages`, `permittedPrograms` (FACT).
- **Writes validate municipality ownership?** **NO.**
  - `ClientRequest` requires `city_municipality` `integer|exists:
    tbl_municipalities,id` — a well-formedness check (must be a real id), **not
    an ownership/scope check** (FACT).
  - `TransactionController::store()`/`update()` validate `client_id` exists and
    authorize the **program**, never the municipality (FACT).
  - `UnpaidVerificationController::store()` (public) checks only
    `client_id != 0 && municipality_id != 0`; the stored `municipality_id` is
    **not cross-checked against the client** (parity with v1 `unpaid_save.php`)
    (FACT).
  - Scanner `ScanService::save()` writes against the looked-up client/transaction
    with no municipality restriction (FACT — Pass 3/4 evidence; client
    municipality resolved to a name for display only).
- **Exports honor municipality filters?** Only when the param is supplied;
  `transactions.export`, `scholarship-reports.export`, and
  `unpaid-verifications.export` all stream **the full permitted set when the
  param is omitted** (subject only to program filters where present) (FACT).
- **Services receive municipality scope?** **No service takes a scope argument**
  (FACT — `TransactionService`, `ScanService`, `ClientService`, etc. operate on
  ids, not scopes).
- **Policies/middleware enforce scope?** **None.** The only policy
  (`ClientPolicy::delete`) delegates to `canAccessPage` (FACT). `AuthorizePage`
  is page-level only.
- **Existing authorization layer with scope semantics?** **None.** (FACT —
  grep confirms zero municipality references in `tbl_users`,
  `tbl_permissions`, `tbl_program_permissions`, `tbl_multi_device_exemptions`.)

#### 4. Data-Scope Security Gap

The gap is where a **future** restriction must be enforced; today there is no
restriction to bypass (INFERENCE — "do not overstate"). For each vector:

| Bypass vector | Example | Existing v1 behavior | Existing v2 behavior | Future authorization requirement |
|---|---|---|---|---|
| Change URL param | `?municipality=1` → `=2` | returns muni-2 rows | identical (parity) | feed queries must override the client param with the effective scope (or reject out-of-scope params) |
| Omit the municipality filter | call `transactions.data` with no `municipality` | full dataset | full dataset | scope predicate must be injected server-side regardless of params |
| Change a POST field | `municipality` on feeds (DataTables sends POST) | same | same | same as above |
| Change a hidden form field | `head_household` on households.create | no check | no check | head client must be within scope before household creation |
| Change an ID | `transactions/{id}` show/edit/update/destroy; `clients/{id}` | page-gated only | page-gated only | single-record check must compare the record's municipality to scope |
| Change `client_id` | `transactions.store` posts another client | no check | no check | store must load the client and scope-check it |
| Change `municipality_id` | public `unpaid-verification/submit` | stored as-is, no cross-check | stored as-is, no cross-check (parity) | public flows are **out of scope** by design (anonymous); admin unpaid handling must scope-check the row |
| Request an export directly | `transactions/export` with no params | full export | full export | export must apply scope predicate server-side (§18) |
| Call a feed directly | `clients/data`, `households/data`, `payout-attendance/*/data` | full dataset | full dataset | feed scope injection (§13) |
| Call a detail endpoint directly | `households/{household}` show | page-gated | page-gated | single-record scope check |
| Scanner lookup/save | POST `/scanners/{key}/save` with arbitrary id | no check | no check | SCAN action + scope check on the target client/transaction when adopted |

**Categorization (INFERENCE):** every row is **existing v1 + existing v2
behavior** (byte-identical parity) and becomes a **future authorization
requirement** if data-scope is adopted. No row is a v2-only regression.

#### 5. Scope Model Options

Evaluated against the five moderators in the objective (A–E) and the verified
schema.

| Option | Description | Fits 2DMIS? | Blockers |
|---|---|---|---|
| **A. Single municipality per user** | one `municipality_id` on the user | **No** (Moderator C needs several) | rejects the "multiple municipalities" requirement outright |
| **B. Multiple municipalities per user** | a user↔municipality pivot | **Yes** — supports Moderator A/B/C | none; standard additive table |
| **C. All-municipalities flag** | boolean "all" per user | Partial — needs to coexist with B | creates a three-state model (none / subset / all); representation question (§16) |
| **D. Municipality permission table** | rows `(user_id, municipality_id)` | **Yes** — this IS option B; a permission-style pivot | naming only; must be additive |
| **E. Scope attached to page/action permission** | municipality column on `tbl_permissions` / `tbl_action_permissions` | **No** | couples a data dimension to operation grants; duplicates assignments per (page,action); pollutes v1-identical tables (ADR-003) |
| **F. Scope attached to program permission** | municipality on `tbl_program_permissions` | **No** | municipality is orthogonal to programs (a user may own all programs in one town, or one program in all towns) |
| **G. Separate reusable municipality-scope table** | `tbl_user_municipalities` (user_id, municipality_id) + all-marker | **Yes** — the recommended shape of B/D | none; name is the only choice |
| **H. Role-based municipality scope** | roles carry municipalities | **No** | v2 has **no role concept** (permission rows are per-user); introducing roles is a much larger change than this decision |

**Recommendation (RECOMMENDATION):** option **B/G** — a dedicated, additive
user↔municipality pivot. Options A, E, F, H are rejected for the stated reasons;
C is folded in as the "all" representation inside the pivot (§16) rather than a
separate boolean.

#### 6. Recommended Scope Architecture

**RECOMMENDATION — the scope is USER-LEVEL, not page/action/program-level.**

- **Ownership**: a user-level set of municipalities (one, many, or all).
- **Single vs multiple**: multiple — the pivot supports 1..n; "all" is a
  marker inside it (§16).
- **"All municipalities"**: represented by an explicit all-scope marker, not by
  "absence of rows" (§16 — recommended but **REQUIRES OWNER DECISION**).
- **"No municipalities"**: represented by zero rows + no all-marker. **Fails
  closed** — the user has scope over **no** municipality data (§17).
- **Super Admin bypass**: `'*'` bypasses page, action, program **and** scope
  without needing scope rows (§9).
- **User has page/action access but no municipality scope**: on a
  scope-enforced module, **denies all municipality-sensitive reads/writes**
  (fail closed, §17). On non-scoped pages (P7 admin, audit viewer) scope does
  not apply.
- **User has municipality scope but no page/action permission**: **denied** —
  scope never grants entry; page remains the gate.

**Evaluation model (RECOMMENDATION, with justification):**

```
canEnterModule   = canAccessPage(USER, page)             — entry gate (unchanged)
canPerformAction = canAccessAction(USER, page, action)   — P9 operation gate
canUseProgram    = canAccessProgram(USER, program)       — P3 gate (unchanged)
canTouchRecord   = recordMunicipality ∊ userScope(USER)  — NEW row gate
effectiveAccess  = canEnterModule AND canPerformAction
                   AND canUseProgram AND canTouchRecord
```

**Justification for the AND relationship (INFERENCE):** each dimension answers a
different question (which module / which operation / which program / which data
rows). OR-ing any dimension would let one grant widen another; the moderators in
the objective are separable — e.g. Moderator B has VIEW on Transactions but not
CREATE (action differs) while Moderator A has both (page/action differ);
Moderator D has no Transactions access at all (page fails, everything below is
moot); Moderator C needs two municipalities (scope differs). Only AND satisfies
all five simultaneously without inventing roles or composite keys. **REQUIRES
OWNER DECISION** to accept the AND chain as final.

#### 7. Interaction With P9 Action Authorization

Scope composes with every P9 action (RECOMMENDATION — future enforcement rules,
not implementation):

| Action | Scope rule |
|---|---|
| **VIEW** | every list/search/detail/feed row returned must have `recordMunicipality ∊ userScope` — feeds inject the scope predicate; single-record endpoints scope-check before serving |
| **CREATE** | the **submitted municipality must be within scope**: for `clients.php`, the posted `city_municipality`; for derived writes (transaction via client, scholar via client, GIP via client, household via head client), the **client's** `city_municipality` must be in scope (the write itself posts no municipality); for unpaid, the submitted `municipality_id` must be in scope |
| **EDIT** | the **existing record's municipality must be in scope** (client edit: `city_municipality`; transaction/scholar/GIP edit: the bound client's municipality; also the **new** municipality on client edits must be in scope) |
| **DELETE** | the **existing record's municipality must be in scope** (client delete: `city_municipality`; duplicates batch-delete: each target client; payout/unpaid list deletes: via client / row FK) |
| **EXPORT** | **every exported row must be within scope** — the scope predicate is applied to the export query server-side; the UI filter parameter must not be trusted (§18) |
| `SCAN` (reserved, P9 §4) | when adopted: the looked-up/created client (or transaction) must be in scope — a scanner save is both a `SCAN`-action write and a scope-touching write |

**CREATE/EDIT/DELETE nuance (INFERENCE):** transactions, scholars, GIP, and
households have **no municipality column** — their scope check is a **join back
to `tbl_clients`** at enforcement time (the same join the feeds already use).
`tbl_unpaid_verifications` is the one table with an **own** FK, so its scope
check is direct.

#### 8. Interaction With Program Permissions

- **No redesign** of `tbl_program_permissions` (RECOMMENDATION — P9 §14; P3 is
  final).
- **Chain**: `Page AND Action AND Program AND Municipality` (§6) — the
  four-dimension AND model. Program and municipality are **independent
  dimensions** (FACT: a moderator could legitimately hold {GIP} programs across
  all towns, or all programs within one town — neither implies the other).
- **Transactions**: program permission already narrows
  `TransactionController::data()`/`store()`/`export()` (FACT). Municipality adds
  an **orthogonal row restriction**: program filters the `program` column; scope
  filters the client-join municipality. Both apply on the same query.
- **Admin screens / audit viewer**: no program checks today (P7/Pass 8 §8.3)
  and **no scope** — they manage metadata (users/permissions), not
  municipality-bearing data rows (§13 exceptions).

#### 9. Super Admin Semantics

**RECOMMENDATION — `'*'` means all pages, all actions, all programs, AND all
municipalities, without requiring any scope rows.**

```
isSuperAdmin(USER) == '*' in tbl_permissions
   ⇒ canAccessPage:      true
   ⇒ canAccessAction:    true          (P9 §10)
   ⇒ canAccessProgram:   true
   ⇒ effectiveScope:     ALL           (no tbl_user_municipalities rows needed)
```

- **Consistency check (INFERENCE):** this mirrors P8/P9 exactly — `'*'` is the
  single marker that already satisfies every page gate and every program gate;
  extending it to scope is the same rule applied to a fourth dimension, and it
  keeps "Super Admin" in the objective working (no scope rows, no UI lockout).
- **No scope rows required, and scope rows for a `'*'` user are** ignored
  (harmless) **RECOMMENDATION**.
- **REQUIRES OWNER DECISION** whether a `'*'` user may ever be *restricted* by
  scope in the future (P9 §10.4 — currently recommended **not**).

#### 10. Existing Database Constraints

What the schema permits without breaking production compatibility (FACT):

- **FK limitations**: only `tbl_barangays` and `tbl_unpaid_verifications` FK
  to `tbl_municipalities`. `tbl_clients.city_municipality` has **no FK** (a
  varchar holding integer strings). Adding a scope pivot is safe; adding a FK on
  `city_municipality` would require a data-repair migration — **not
  recommended**.
- **varchar municipality fields**: `tbl_clients.city_municipality`/
  `barangay` (int-in-varchar); `tbl_exam.town`; `tbl_seats*.town`;
  `tbl_details.TOWN`; `tbl_kababaihan.town` — only `tbl_clients` is
  scoping-relevant; the rest are display/legacy text.
- **Integer municipality IDs**: `tbl_barangays.municipality_id`,
  `tbl_unpaid_verifications.municipality_id`.
- **Derived relationships**: household/transactions/scholar/GIP/family/payout
  all derive through `client_id` → `tbl_clients`.
- **Missing constraints**: no FK, no CHECK on `city_municipality` (any string
  accepted by the schema; app validates `exists:` on writes).
- **Nullable fields**: `tbl_unpaid_verifications.municipality_id` is
  NOT NULL; `tbl_clients.city_municipality` is NOT NULL.
- **Existing indexes**: `idx_clients_muni` (`city_municipality`),
  `idx_clients_brgy` (`barangay`), `KEY m_n` on `tbl_municipalities.name`,
  `municipality_id` keys on barangays/unpaid. The scoping joins are already
  index-backed.
- **Existing uniqueness**: `uniq_permission_user_page` (permissions),
  `uniq_program_permission_user_program` (programs), `user_id` (exemptions),
  `household_id` (household), `client_id`/`relative_id` (family), `username`
  (users).
- **Tables requiring joins to determine municipality**:
  `tbl_household`, `tbl_transactions`, `tbl_scholar_info`, `tbl_gip_info`,
  `tbl_family_members`, `tbl_payout_scans*` (all via `tbl_clients`).

**Compatibility (INFERENCE):** a **user-scope pivot** (option B/G) is the only
model that is fully compatible with the existing production schema without
touching any existing table. Every other option either repurposes an existing
column (forbidden) or invents a per-row/per-action scope that the derived tables
cannot satisfy.

#### 11. Additive Schema Compatibility

**Conceptual additions (NOT created; exact DDL fixed at build, after owner
approval):**

**1. `tbl_user_municipalities` — the scope pivot.**

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AUTO_INCREMENT | project convention |
| `user_id` | INT NOT NULL | mirrors `tbl_permissions.user_id` |
| `municipality_id` | INT NOT NULL | → `tbl_municipalities.id` |
| `created_at` | DATETIME (optional) | grant-time accountability |
| unique | `UNIQUE(user_id, municipality_id)` | `tbl_program_permissions` pattern; idempotent full-replace target |

- **Purpose**: the user's effective municipality set.
- **Relationship**: N:M user↔municipality; additive; **no existing table is
  altered**.
- **Required?** Yes — if data-scope is adopted (there is no other compatible
  home, §10).
- **Ambiguity risk**: none (mirrors `tbl_program_permissions` exactly).

**2. All-municipality marker — representation to be finalized (§16).**
Candidates: a sentinel row (`municipality_id` = reserved value / `NULL` with a
flag) or a separate `tbl_user_all_municipalities`-style row. **UNRESOLVED** —
depends on §16 owner decision. No column on `tbl_users` is proposed (avoid
polluting the auth table).

**3. No changes to `tbl_clients`, `tbl_unpaid_verifications`, or any derived
table.** Scope is evaluated by **joining to `tbl_clients`** (or using the unpaid
FK) at query time — no per-row scope columns, no data backfill.

**Does it affect existing tables?** No. **Additive?** Yes (new tables only;
`schema:dump` regen per AGENTS.md). **Risks ambiguity?** Only if the all-marker
is added later without a decision (§16) — hence the explicit owner decision.

#### 12. Scope Enforcement Location

Distinguish two layers (RECOMMENDATION):

**A. Page/action authorization** — *operation* gate, already defined:
middleware (`page:`, future `action:`) + `AccessControlService`
(`canAccessPage`, P9 `canAccessAction`). This decides **which operations** a user
may attempt. It is **not** row-sensitive.

**B. Row/data authorization** — *record* gate, new: decides **which rows** may
be read/written. Because the v2 feeds, exports, and detail endpoints are
directly addressable and use the **query builder (`DB::table`) — not Eloquent
models** (FACT), a global Eloquent scope **cannot** be the enforcement seam
(queries never touch the models). The workable seams are:

1. **A single scope service** — `AccessControlService::effectiveMunicipalityIds
   (User)` (+ `canAccessRecord(User, table, recordMunicipality)`) as the one
   authority for "what is in scope" — canonical, cacheable, testable
   (mirrors `permittedPrograms`).
2. **Query-scope application at composition time** — every
   municipality-sensitive feed/export query gets an injected `whereIn(scope)`
   clause composed by the service, so filters, exports, and feeds cannot be
   widened by parameters (§4).
3. **Record-level check on single-record and write endpoints** — show/edit/
   update/destroy/export single row: fetch → compute record municipality
   (direct or client-join) → `canAccessRecord` → 403/deny.
4. **Controller/service/policy**: the check lives **in the service layer + a
   policy or controller call**, not in `FormRequest` (requests validate shape,
   not scope — consistent with P9 §13.4).

**Why UI filtering is insufficient (FACT):** every municipality filter today is
a client-supplied parameter; there is nothing server-side tying a page-gated
user's result set to a scope. A restricted user calling any feed/export directly
without the filter receives the full dataset (§3, §4). UI-only hiding would leave
every direct endpoint open.

#### 13. Read Operations

**Requirement (RECOMMENDATION):** every municipality-sensitive read must respect
the effective scope.

| Read surface | Must scope? | Mechanism (conceptual) |
|---|---|---|
| index/list (page) | yes | view may render the dropdown limited to scope; the feed is the boundary |
| search (client picker used by transactions/scholars/households) | yes | scope `whereIn` on the shared search queries (they join `tbl_clients`) |
| detail/show (`clients/{id}`, `households/{id}`, `transactions/{id}`) | yes | record municipality check |
| dashboard | yes (counts/summaries that show data rows) | scope the aggregate queries (future; P1 parity today shows all) |
| reports (`scholarship-reports/data` + export) | yes | scope on the client join |
| feeds (`*/data`) | yes | scope injection (§12.B) |
| AJAX (barangay cascade, mobile verify, grantee verify-mobile) | no (geo/lookup helpers, no data rows) | exception |
| exports | yes (§18) | scope predicate in the export query |
| audit viewer / leaderboard | **no exception is needed** — `tbl_audit_logs` has no municipality dimension (FACT) | not scopeable |
| P7 admin screens (users/permissions/exemptions) | **no exception** — metadata, no municipality data | not scopeable |
| public self-service (student/unpaid/grantee/QR) | **no exception** — anonymous by design, outside any authz layer (P8 §3) | N/A |

#### 14. Write Operations

**Requirement (RECOMMENDATION) — each write checks the municipality it creates
or touches:**

| Write | Submitted value to authorize | Existing record to authorize | Derived (client) municipality |
|---|---|---|---|
| client CREATE | posted `city_municipality` ∊ scope | — | — |
| client EDIT | new `city_municipality` ∊ scope | existing client's municipality ∊ scope | — |
| client DELETE / duplicate batch-delete | — | each target client ∊ scope | — |
| transaction CREATE | — | — | bound client ∊ scope (the write posts no municipality) |
| transaction EDIT / inline-update | — | transaction's client ∊ scope | — |
| transaction DELETE | — | transaction's client ∊ scope | — |
| household CREATE | head client ∊ scope | — | — |
| household DELETE | — | head client ∊ scope | — |
| scholar CREATE / EDIT / relink | — | scholar's client ∊ scope | — |
| GIP CREATE / EDIT | — | client ∊ scope | — |
| scanner save (when adopted) | — | target client/transaction ∊ scope | — |
| unpaid admin delete | — | row's own `municipality_id` ∊ scope | — |
| unpaid public submit | **out of scope** — anonymous flow (v1 parity); its `municipality_id` stays un-cross-checked until the owner decides otherwise | — | — |

**Verification note (FACT):** transactions/scholars/GIP/households have no own
municipality column — "record municipality" always means **the bound client's
`city_municipality`** (derived, §1), resolved at enforcement time. The unpaid
row is the only table whose own column is used.

#### 15. Multi-Municipality Users

- **Requirement is real (FACT — objective Moderator C):** one user may need
  Transactions and/or Scholars across several municipalities.
- **Architecture (RECOMMENDATION):** support **one, many, and all** without
  separate accounts:
  - one / many → the `tbl_user_municipalities` pivot rows;
  - all → the all-marker (§16);
  - no separate user account required in any case (pivot rows are per-user).
- **Edge cases handled (INFERENCE):** a user with a pivot row for {A, B} sees A+B
  only (feeds inject `IN (A,B)`); adding C is one new row (P7-style admin
  screen, future); removing all rows without the all-marker = no scope (§17).

#### 16. "All Municipalities" Semantics

| Option | Mechanism | Security | Maintainability | Verdict |
|---|---|---|---|---|
| A. special `'*'`-like marker | reuse the `'*'` concept in the scope pivot (e.g. reserved `municipality_id` or an `all=1` row) | distinct from `'*'` user marker; cannot be granted accidentally | one row instead of 34 | **Recommended** (inside the pivot) |
| B. `NULL` | `NULL` municipality_id = all | ambiguous: NULL rows are indistinguishable from "unset"; risky with UNIQUE constraints | poor — `whereIn` vs null logic | reject |
| C. dedicated boolean | `all_municipalities` on the pivot or user | two-state; cannot express "all" + nothing else | separate column to keep in sync | acceptable, but adds a column where a row suffices |
| D. dedicated scope row | e.g. `tbl_user_all_municipalities` | clean; another table | extra table | acceptable |
| E. another mechanism (role/env) | — | n/a (no roles; env is global) | n/a | reject |

**RECOMMENDATION — Option A as a marker inside the pivot** (either a reserved
sentinel `municipality_id` documented as "ALL" or an `is_all` flag on a single
pivot row), with the exact column chosen at build. The marker must be a **grant**
(only an admin with scope-management can set it), never a default. **REQUIRES
OWNER DECISION** on the precise representation.

#### 17. No-Scope Semantics

**RECOMMENDATION — fail closed.** A user with page/action/program access but
**no municipality scope and no all-marker** has scope over **zero** records:

- reads: feeds/exports/search return **empty**; single-record endpoints deny;
- writes: CREATE/EDIT/DELETE deny (no in-scope record can even be addressed);
- on non-scoped surfaces (audit viewer, P7 admin, public flows) scope does not
  apply.

**Rationale (INFERENCE):** the only safe default for a *data-scoping* feature is
that an un-configured user sees nothing, not everything. The alternative
(no-scope = all) would silently grant every existing page-gated user full data —
the exact accident the feature is meant to prevent (§21).

**Cutover caveat (RECOMMENDATION):** because today's users are all
implicitly "all", flipping on fail-closed scope for a module changes behavior
for everyone. Cutover must follow the P9 S2 pattern — grant scope rows for
existing users **before** enforcement per module (§21).

#### 18. Export Security

**Requirement (RECOMMENDATION — treat exports as a distinct security surface):**

1. **Scope predicate is applied server-side to the export query** — the same
   `whereIn` injection as feeds (§12.B). An export URL with no municipality
   param, or with an out-of-scope param, yields **only** in-scope rows (or an
   empty file), never a widening.
2. **The three existing exports** (`transactions.export`,
   `scholarship-reports.export`, `unpaid-verifications.export`) are the exact
   routes that must carry the predicate when their module adopts scope (FACT —
   they currently stream the full permitted set on param omission, §3).
3. **UI filter is presentation** — the CSV link's municipality/barangay values
   are client-chosen; the server recomputes scope regardless.
4. **No future export** (e.g. payout attendance) may be added without the same
   predicate at adoption time (P9 §8.5 applies to scope as well).
5. **Export is never a bypass** around row-level restrictions: a user scoped to
   A cannot produce a CSV containing B by any combination of parameters.

#### 19. Scope + Audit

- **Scope grant changes should be audited (RECOMMENDATION).** The existing
  `AuditService` convention (P7 §12; `target_table`, `target_id`,
  `old_value`/`new_value` JSON) supports it without new infrastructure (FACT).
- **Reusable convention (INFERENCE):** future events
  `MANAGE_SCOPE_GRANT` / `MANAGE_SCOPE_REVOKE` / `MANAGE_SCOPE_ALL`
  (final names **not** decided here) with `target_table =
  'tbl_user_municipalities'`, `target_id` = the subject user id, and
  `old_value`/`new_value` carrying `{'username', 'municipalities': [ids], 'all':
  bool}` — the same shape as the P9-proposed `MANAGE_ACTION_PERMISSIONS`
  (§15.2 of Pass 9). **REQUIRES OWNER DECISION** on the final strings; nothing
  is added now.
- **No read-audits** — scope evaluation itself writes nothing (P9 §15.3
  discipline).
- **No secrets** in scope/audit payloads (P9 §15.4).

#### 20. Scope + Action Permission Table

| Option | Mechanism | Verdict |
|---|---|---|
| **A. Independent of action permissions** | scope = user-level pivot; action rows stay `(user, page, action)` | **Recommended** — scope is a data dimension owned by the user, not by an operation grant; one scope set serves every page/action; no duplication |
| **B. Embedded in action permission records** | municipality on `tbl_action_permissions` rows | Rejected — would duplicate the same municipality list across every (page,action) row for the same user, make "add municipality C" a multi-row update, and couple operation grants to a data dimension that outlives them |

**Recommendation (RECOMMENDATION):** **A — independent**. Scope is evaluated at
row-access time by joining the user's pivot to the record's municipality,
regardless of which page/action the user reached the record through. This also
keeps the P9 `tbl_action_permissions` design untouched and additive.

#### 21. Migration/Cutover Considerations

Future bootstrap requirements only (nothing is migrated in this pass):

1. **Existing users** are implicitly "all" today (no restriction exists). Under
   fail-closed scope (§17), every non-`'*'` user with page access to a
   scoped module **must receive explicit scope rows (or the all-marker) before
   that module's enforcement flips on** — otherwise they silently see nothing
   (lockout) or, if no-scope defaulted to all, everything (over-grant).
2. **Super Admin (`'*'`)** is unaffected — no scope rows needed, scope bypass
   guaranteed (§9). No data change.
3. **Page-permission holders** keep their page rows; scope is additive and does
   not touch `tbl_permissions`.
4. **Program-permission holders** keep their program rows; scope is orthogonal
   (§8). No change.
5. **Users with no explicit permissions** remain unable to enter any module —
   scope rows do not grant page entry (§6). No change.
6. **Existing production records** remain byte-identical — scope is evaluated
   by join, no per-row backfill (§11.3).
7. **Adoption sequence (RECOMMENDATION):** grant scope → flip module
   enforcement → audit each step — the P9 S2 per-page procedure reused for
   scope. There is **no migration** in this pass and none is designed yet
   beyond the additive `tbl_user_municipalities` + all-marker.

#### 22. Final Recommendation

1. **Municipality scope ownership** — **VERIFIED**: schema has two consistent
   scoping surfaces (`tbl_clients.city_municipality`, `tbl_unpaid_verifications.
   municipality_id`); everything else derives via client. **RECOMMENDED**: scope
   is **user-level**.
2. **Single vs multiple** — **RECOMMENDED**: multiple (pivot), covering one,
   many, all.
3. **All-municipality semantics** — **RECOMMENDED**: explicit all-marker inside
   the pivot (Option A, §16); **OPEN DECISION** on the exact column/flag.
4. **No-scope semantics** — **RECOMMENDED**: **fail closed** (zero records;
   §17).
5. **Super Admin behavior** — **RECOMMENDED**: `'*'` = all pages/actions/
   programs/municipalities, no scope rows needed (§9).
6. **Interaction with page permissions** — **RECOMMENDED**: page remains the
   entry gate; scope is applied after it (AND, §6).
7. **Interaction with P9 action permissions** — **RECOMMENDED**: scope
   composes with VIEW/CREATE/EDIT/DELETE/EXPORT (and reserved SCAN) as defined
   in §7; scope is **independent** of action rows (§20).
8. **Interaction with program permissions** — **RECOMMENDED**: independent AND
   dimension; no change to `tbl_program_permissions` (§8).
9. **Read enforcement** — **RECOMMENDED**: scope injection into every
   municipality-sensitive feed/search/export/detail; UI-only filtering rejected
   (§12, §13).
10. **Write enforcement** — **RECOMMENDED**: CREATE/EDIT/DELETE scope-check the
    submitted/derived/existing record municipality (§14).
11. **Export enforcement** — **RECOMMENDED**: server-side scope predicate on all
    exports; exports are never a bypass (§18).
12. **Audit considerations** — **RECOMMENDED**: future scope grant/revoke/all
    events via the existing `AuditService` convention; names **OPEN DECISION**
    (§19).
13. **Additive schema compatibility** — **RECOMMENDED**: one additive pivot
    `tbl_user_municipalities` (+ all-marker); **no** alteration of any existing
    table or column (§11). **OPEN DECISION** on the all-marker representation.

**Verdict (INFERENCE):** the combined model is
`Page AND Action AND Program AND Municipality` — every dimension independent,
additive, and byte-compatible with production. Nothing is implemented.

#### 23. Update Open Decision #6

Open Decision #6 — **Fine-Grained Authorization Architecture** — remains
**DEFERRED — REQUIRES OWNER APPROVAL**, now with complete recommendations for
both dimensions:

| Component | Status after P9 + P10 |
|---|---|
| **A. Action-level authorization** | **RECOMMENDED (complete contract, P9 §18)** — not approved, not implemented |
| **B. Municipality/data-scope** | **RECOMMENDED (complete research, this pass)** — not approved, not implemented; requires the owner to pick the scope **data model** (pivot + all-marker representation) |
| **C. Combined model** | **Architecture verified** as the AND chain `Page ∧ Action ∧ Program ∧ Municipality` — **not finalized** (depends on A and B approval) |
| **D. Final schema** | **UNRESOLVED** — candidates `tbl_action_permissions` (P9) and `tbl_user_municipalities` (+ all-marker) proposed; any migration additive + `schema:dump` regen (AGENTS.md); nothing created |

The overall decision **stays DEFERRED** until the owner approves the P9 §18 and
P10 §24 checklists.

#### 24. Owner Approval Checklist

Future owner decisions (approve the **architecture**, not implementation):

1. **Municipality scope model** — user-level pivot (option B/G) vs alternatives
   (§5).
2. **Single vs multiple municipalities** — multiple supported via pivot (§15).
3. **All-municipality representation** — all-marker inside the pivot vs boolean
   vs separate table (§16).
4. **No-scope behavior** — fail closed (§17).
5. **Super Admin scope bypass** — `'*'` = all, no scope rows (§9).
6. **Action + municipality composition** — scope applies to
   VIEW/CREATE/EDIT/DELETE/EXPORT (+ reserved SCAN) per §7.
7. **Program + municipality composition** — independent AND dimension; program
   permissions untouched (§8).
8. **Enforcement architecture** — scope service + query injection + record-level
   checks; UI filtering rejected (§12).
9. **Migration/cutover strategy** — P9-S2-style per-module adoption; no data
   backfill; additive table only (§21).

---

**HARD STOP — Pass 10 complete.** No code, schema, migration, route, model,
controller, service, middleware, policy, view, test, seeder, or DB operation
was run; `tbl_user_municipalities` was **not** created; no existing table was
altered; v1 was not modified; the local or production database was not touched;
no file other than this one was modified (verified with `git status`). Open
Decision #6 remains `DEFERRED — REQUIRES AUTHORIZATION ARCHITECTURE RESEARCH`,
with A (action) and B (municipality/data-scope) both carrying complete
recommendations pending owner approval, C verified-composable but not
finalized, and D unresolved. Next step: owner approval of the P9 §18 and P10
§24 checklists, then build (or further research only on owner instruction).

---

### P11 — Combined Authorization Architecture Contract (2026-08-15)

> **Scope**: research + architecture contract only — reconciles the P8 action
> research, the P9 action contract, and the P10 municipality/data-scope research
> into **one** proposed authorization architecture. **Nothing is implemented.**
> No code, schema, migration, route, model, controller, service, middleware,
> policy, view, seeder, or test was written; no database (local or production)
> was touched; v1 was not modified; no file other than this one was modified
> (verified with `git status`). `tbl_action_permissions` and
> `tbl_user_municipalities` remain **conceptual — NOT created**.
>
> **Method**: synthesis and validation of P8 (research), P9 (action contract),
> and P10 (municipality research) against the verified v2 baseline
> (`routes/web.php`, `config/scanner.php`, `AccessControlService`,
> `AppServiceProvider` Gates, `AuthorizePage`, `ClientPolicy`,
> `database/schema/mysql-schema.sql`). Every statement is labelled FACT /
> INFERENCE / RECOMMENDATION / REQUIRES OWNER DECISION / UNRESOLVED. No previous
> finding was contradicted; P8/P9/P10 are preserved unchanged and referenced,
> not repeated.

#### 1. Current Authorization Baseline

Verified v2 baseline (FACT — P7 contract; `AccessControlService`,
`AppServiceProvider`, `AuthorizePage`, schema):

| Mechanism | Current shape | Status in the target architecture |
|---|---|---|
| **`tbl_permissions`** | `id, user_id, page_name varchar(100), can_access tinyint(1) default 1`; `UNIQUE(user_id, page_name)` | **UNCHANGED** — stays the page gate and the `'*'` home |
| **`'*'` Super Admin marker** | a `page_name='*'` row in `tbl_permissions`; sole super-admin marker; satisfies every page gate (FACT) | **UNCHANGED** — semantics extended to bypass action/program/scope (§11) |
| **`AccessControlService`** | the one ACL service: `isSuperAdmin`, `canAccessPage`, `canAccessProgram`, `isSingleDeviceExempt`, `isMultiDeviceExempt`, `permittedPages`, `permittedPrograms` | **UNCHANGED core** — gains `canAccessAction` (P9) + scope methods (P10) as the single authority (ADR-003) |
| **`page:` middleware** (`AuthorizePage`) | 403 JSON on `expectsJson()` else redirect-dashboard + `login_status=denied`; applied on every route group | **UNCHANGED** — remains the page gate; `action:` is a new sibling (P9 §13) |
| **Gates** | `Gate::define('page', ...)` and `Gate::define('program', ...)` in `AppServiceProvider`, both delegating to `AccessControlService` | **UNCHANGED** — a new `action` Gate is added (P9 §13) |
| **`tbl_program_permissions`** | `id, user_id, program_name varchar(100)`; `UNIQUE(user_id, program_name)`; empty set = unrestricted (v1 parity, FACT) | **UNCHANGED** — independent dimension (§12) |
| **Multi-device exemptions** | `tbl_multi_device_exemptions` (single-device bypass); single-device login via `session_token` | **UNCHANGED** — orthogonal to authorization (session/device layer) |
| **`AuditService`** | sole audit writer; 7 approved `MANAGE_*` strings; `target_table`/`target_id`/`old_value`/`new_value` convention | **UNCHANGED** — future scope/action-mutation events use the same convention (§20) |
| **`ClientPolicy`** | only policy; `delete` delegates to `canAccessPage` | **UNCHANGED** — precedent for controller-level checks |

**Unchanged contract (FACT):** page-level authorization is the only mechanism
active today; every page-key holder can exercise every route in its group
(P8 §2); P7 admin pages, the `'*'` toggle, exemptions, and the audit strings
must remain byte-compatible.

#### 2. Target Authorization Dimensions

**FOUR dimensions** (RECOMMENDATION — validated in P8/P9/P10):

| Dimension | Controls | Does NOT control | Level | Required for normal users | `'*'` bypass |
|---|---|---|---|---|---|
| **A. PAGE** (`tbl_permissions`) | module **entry**: may the user reach the page at all (UI + routes) | which operations inside; which programs; which rows | user-level (which modules) | **yes** — entry gate | yes |
| **B. ACTION** (`tbl_action_permissions`, P9) | which **operation** the user may perform on an adopted page (VIEW/CREATE/EDIT/DELETE/EXPORT, reserved SCAN/MANAGE) | module entry; which programs; which rows | user-level (which operations) | **only on adopted pages** — non-adopted pages are page-only (P9 §5/§12) | yes |
| **C. PROGRAM** (`tbl_program_permissions`) | which of the 17 programs the user may touch on program-consuming operations (Transactions) | module entry; operations; rows | user-level (which programs) | no (empty set = unrestricted, v1 parity) | yes |
| **D. MUNICIPALITY** (`tbl_user_municipalities`, P10) | which **data rows** (by municipality) the user may read/write on municipality-sensitive surfaces | module entry; operations; programs | user-level (which data scope) | yes **on scope-enforced modules** (fail closed, §10) | yes |

**Separation of concerns (INFERENCE):** A answers "which module", B "which
operation", C "which program", D "which data". Each is independent and
user-level; none is a resource-level column (no per-row/per-record permission
columns anywhere — scope is evaluated by join, P10 §11).

#### 3. Final Conceptual Authorization Chain

**RECOMMENDATION — the four-dimension AND chain** (validated against the P10
evidence; not assumed):

```
effectiveAccess(USER, page, action, program, record) =
    canAccessPage(USER, page)                    -- A. PAGE
    AND canAccessAction(USER, page, action)      -- B. ACTION (adopted pages only)
    AND canAccessProgram(USER, program)          -- C. PROGRAM (program-consuming ops only)
    AND recordMunicipality(record) ∈ userScope(USER)  -- D. MUNICIPALITY (sensitive rows only)
```

**When each dimension is evaluated (RECOMMENDATION):**

| Step | Where | Trigger |
|---|---|---|
| PAGE | `page:` middleware / first route of the group | every request to a gated page (today, FACT) |
| ACTION | `action:` middleware on the mutation/export routes of adopted pages | only adopted pages; non-adopted pages skip (P9 §12/S2) |
| PROGRAM | controller `authorizeProgram` (Transactions store/update) + feed WHERE | only operations that consume a program (P9 §14) |
| MUNICIPALITY | query injection on feeds/exports/search; record check on single-ID and write endpoints | only municipality-sensitive surfaces (P10 §13/§14) |

**Outcomes (RECOMMENDATION):**

- **Page denied** → request never reaches the module (403 JSON or dashboard
  redirect). No further dimension is evaluated.
- **Page allowed, action denied** → on an adopted page the operation is refused
  (403) while reads still work (VIEW).
- **Page+action allowed, program denied** → the program-consuming operation is
  refused / the program filter is forced to the permitted set (today's
  behavior, FACT).
- **First three allowed, municipality denied** → feed/search/export rows are
  filtered to scope (possibly empty); single-record/detail and writes deny.
- **All four allowed** → operation executes.

**Why AND (justification, INFERENCE):** each dimension is necessary — a
municipality grant must not grant module entry (scope-without-page denies, P10
§6), an action grant must not widen programs, and no single dimension may OR
the others open. This reproduces the five moderators of the P10 objective
exactly (e.g. Moderator B = Transactions page + VIEW + no CREATE; Moderator C =
same page + multiple scope rows). **REQUIRES OWNER DECISION** to accept.

#### 4. Conceptual Data Model

Two **conceptual** additions only (NOT created; exact DDL fixed at build after
owner approval).

**1. `tbl_action_permissions`** (P9 §3 — re-affirmed):

| Aspect | Definition |
|---|---|
| Purpose | the operation gate for adopted pages |
| Owner relationship | user-level; `user_id` mirrors `tbl_permissions.user_id` |
| Fields | `id` INT PK, `user_id` INT NOT NULL, `page_name` varchar(100) NOT NULL (real v1 page keys, ADR-003), `action` varchar(50) NOT NULL, `created_at` DATETIME (optional) |
| Uniqueness | `UNIQUE(user_id, page_name, action)` — `tbl_program_permissions` pattern; presence = allow (no deny rows, P9 §11.C) |
| Expected indexes | the unique key (covers lookups) |
| References existing tables | references `page_name` values and `user_id` values but adds **no FK constraint** to legacy tables (same discipline as `tbl_permissions`) |
| Additive-only | yes — new table, zero changes to existing tables |
| Changes existing tables | no |

**2. `tbl_user_municipalities`** (P10 §11 — re-affirmed):

| Aspect | Definition |
|---|---|
| Purpose | the user's municipality data scope |
| Owner relationship | user-level; N:M user↔municipality |
| Fields | `id` INT PK, `user_id` INT NOT NULL, `municipality_id` INT NOT NULL, `created_at` DATETIME (optional); plus the all-marker representation (§9 — **OPEN DECISION**) |
| Uniqueness | `UNIQUE(user_id, municipality_id)` |
| Expected indexes | the unique key; FK-index on `municipality_id` if a constraint is added (optional) |
| References existing tables | `municipality_id` → `tbl_municipalities.id` (the dimension root); `user_id` → `tbl_users.id`; no changes to either |
| Additive-only | yes — new table, zero changes to existing tables |
| Changes existing tables | no |

**Explicitly not added (RECOMMENDATION):** no municipality column on
`tbl_permissions`, `tbl_action_permissions`, `tbl_program_permissions`, or
`tbl_users`; no per-row scope column on any data table (P10 §11.3). No
unnecessary columns were invented.

#### 5. `tbl_permissions` Compatibility

**`tbl_permissions` is never replaced or altered (RECOMMENDATION — P9 §2).**

- **Page-level access**: unchanged — the `(user_id, page_name)` rows continue to
  answer "may the user enter this module".
- **`'*'`**: unchanged home of the Super Admin marker; semantics extended only
  in the ACL service (`'*'` ⇒ all pages/actions/programs/municipalities), never
  in the table shape.
- **Legacy page grants**: every existing row keeps its meaning and value;
  v1 page keys stay identical (ADR-003).
- **Relationship to action permissions**: on an **adopted** page, page
  permission = the VIEW grant (P9 §6); action rows refine the same user+page.
  A page row is **required** for any action to matter (action-without-page
  denies, P9 §11.D).
- **Transition (page-only → page+action)**: page-only behavior for a page is
  preserved exactly until that page's enforcement flag flips on (S2, §21);
  `tbl_permissions` requires zero rows changed for the transition.

#### 6. Action Permission Model

Final proposed semantics (P9 §4/§7 — re-affirmed after P10):

| Action | Page-scoped | Program-sensitive | Municipality-sensitive | Requires existing record | Applies to collection/list ops |
|---|---|---|---|---|---|
| **VIEW** | yes (page row **is** VIEW on adopted pages) | no (list shows permitted programs via the program filter; VIEW itself is program-neutral) | **yes** — every returned/detail row must be in scope | no | **yes** — list, search, feed, detail are VIEW |
| **CREATE** | yes | yes where the op consumes a program (Transactions store) | **yes** — submitted municipality / derived client municipality in scope | no | no |
| **EDIT** | yes | yes (Transactions update/inline) | **yes** — existing record's effective municipality in scope | **yes** | no |
| **DELETE** | yes | no (deletes are program-neutral today; no program check exists on destroy — parity) | **yes** — existing record's effective municipality in scope | **yes** | no |
| **EXPORT** | yes (distinct from VIEW, P9 §8) | yes — exports inherit the program filter (Transactions export, FACT) | **yes** — every exported row in scope | no | **yes** — the whole exported set is the "collection" |
| **SCAN** (reserved) | yes (scanner page) | yes — scanner programs from config (FACT) | **yes** when adopted — target client/transaction in scope | sometimes (update-in-place, attendance) | no |
| **MANAGE** (reserved) | yes (P7 admin pages when adopted) | no | **no** — admin metadata has no municipality dimension | no | yes (full-replace saves) |

**Unsupported (FACT — no new evidence):** `APPROVE`, `VERIFY`, `PAYOUT` are
**not** added (P9 §4): there is no distinct approve/verify/payout operation in
v1/v2 — payout is a SCAN variant; verifies are reads; approval is a future
capability. **REQUIRES OWNER DECISION** only if the owner wants them later.

#### 7. Action Adoption Strategy

**Phase-1 pilots remain correct after P10 (RECOMMENDATION — validated).**

| Pilot page | Page gate | Actions | Program interaction | Municipality interaction |
|---|---|---|---|---|
| **`clients.php`** | `page:clients.php` | VIEW, CREATE, EDIT, DELETE | none (client module is program-neutral, FACT) | **direct** — the submitted/edited `city_municipality` is on the row itself |
| **`household.php`** | `page:household.php` | VIEW, CREATE, DELETE (no EDIT route, FACT) | none | **derived** — via head client's municipality |
| **`all_transactions.php`** | `page:all_transactions.php` | VIEW, CREATE, EDIT, DELETE, EXPORT | **yes** — `permittedPrograms` on feeds/store/export (FACT) | **derived** — via the transaction's client join |
| **`scholars.php`** | `page:scholars.php` | VIEW, CREATE, EDIT | none (scholars are program-neutral in the module) | **derived** — via scholar's client |
| **`register.php`** | `page:register.php` | CREATE | none | **none** — a user record has no municipality (metadata) |

**Why still correct (INFERENCE):** the pilots exercise every enforcement shape —
direct municipality (clients), derived municipality (household/transactions/
scholars), program interaction + export (transactions), single-op page
(register) — with minimal blast radius. **Every other page stays page-only**
(scanners, payout, unpaid, scholarship reports, update logs, audit logs, the 4
P7 admin pages, dashboard) until explicitly migrated (P9 §5). Nothing is
modified.

#### 8. Municipality Scope Model

**`tbl_user_municipalities` re-affirmed (P10 §8/§15/§17):**

- **One municipality**: one pivot row.
- **Multiple municipalities**: several pivot rows.
- **All municipalities**: the explicit all-marker (§9).
- **No municipalities**: zero rows + no all-marker ⇒ **scope over zero records
  (fail closed, §10)**.

**Properties (RECOMMENDATION — P10 §6/§8):**

1. **Independent authorization dimension** — scope is orthogonal to pages,
   actions, and programs (each is its own AND term).
2. **Shared by all modules** — one user-scope set is evaluated against every
   municipality-sensitive surface; no per-module scope lists.
3. **Reusable across pages/actions/programs** — the same scope set serves VIEW/
   CREATE/EDIT/DELETE/EXPORT and every program; no duplication (P10 §20).

#### 9. All-Municipality Semantics

Comparison (P10 §16 — summarized):

| Option | Verdict |
|---|---|
| special `'*'` row inside the pivot | **Recommended** — an explicit grant-marker, distinct from `page_name='*'`, cannot be accidentally granted |
| `NULL` | reject — ambiguous, collides with uniqueness/semantics of "unset" |
| dedicated boolean on a pivot row | acceptable but adds a column where a marker row suffices |
| separate all-scope table | acceptable but extra table for no gain |

**Recommendation (RECOMMENDATION):** "all" is an **explicit marker inside
`tbl_user_municipalities`** (e.g. a reserved `municipality_id` documented as ALL
or an `is_all` flag on a single pivot row — exact form **OPEN DECISION**, P10
§16). **Conflict check with `page_name='*'` (FACT):** none — the all-marker
lives in a different table with a different subject (data scope vs module
entry); the two `'*'` concepts are independent and the ACL service keeps them in
separate methods (`isSuperAdmin` vs `hasAllMunicipalities`). **REQUIRES OWNER
DECISION** on the marker's exact representation.

#### 10. No-Scope Semantics

**Fail closed (RECOMMENDATION — P10 §17).** For each case:

| Case | Verdict |
|---|---|
| **A. Page permission, no scope** | page entry granted; on a scope-enforced module, **all** municipality-sensitive reads/writes deny (empty feeds, denied detail/writes). Non-sensitive surfaces (P7 admin, audit viewer) unaffected |
| **B. Action permission, no scope** | action is moot on sensitive rows — every target record is out of scope, so every operation on that module's data denies |
| **C. Program permission, no scope** | program set intact, but no scoped record can be reached — combined effect is denial on sensitive surfaces |
| **D. Multiple page/action/program rows, zero municipality rows** | the user is fully functional only on **non-scoped** surfaces; on scoped modules they see nothing and can write nothing |

**Evidence (FACT):** the alternative (no-scope = all) would silently grant every
existing page-gated user full data — precisely the accident data-scope is meant
to prevent. No evidence supports no-scope = all.

#### 11. Super Admin Semantics

**Final proposal (RECOMMENDATION — P9 §10 + P10 §9):**

```
tbl_permissions.page_name = '*'
  ⇒ canAccessPage    : true  (bypasses PAGE)
  ⇒ canAccessAction  : true  (bypasses ACTION — P9 §10)
  ⇒ canAccessProgram : true  (bypasses PROGRAM)
  ⇒ effectiveScope   : ALL   (bypasses MUNICIPALITY — P10 §9)
  ⇒ no tbl_action_permissions rows required
  ⇒ no tbl_user_municipalities rows required
```

- **No username-based or user-ID-based admin checks are introduced** (ADR-003;
  grep-enforceable, P9 §17). `'*'` is the **only** super-admin marker.
- **Super Admin scope rows**: none required; any rows present for a `'*'` user
  are ignored (harmless).
- **Consistency (INFERENCE):** this is the same rule P8/P9/P10 already applied —
  `'*'` satisfies every gate; extending it to the fourth dimension adds no new
  mechanism.

#### 12. Program Permission Interaction

**`tbl_program_permissions` is unchanged (RECOMMENDATION — P9 §14 + P10 §8).**

- **Independent dimension**: program access is orthogonal to page, action, and
  municipality. A user may hold all 17 programs in one town or one program in
  all towns — neither implies the other.
- **Evaluation**: program is checked only on program-consuming operations
  (Transactions store/update/feed/export — the `permittedPrograms` filter,
  FACT). Admin screens and scanner saves have **no program check today**
  (deferred scanner-program question, P3 §5/P4 §14.5 — untouched).
- **Transactions explicitly**: `Page(all_transactions.php) ∧ Action ∧ Program ∧
  Municipality` — program filters the `program` column; scope filters the
  client-join municipality; the two conditions coexist on the same query (§3).
- **Alternatives rejected (INFERENCE):** replacing the chain with a
  single composite grant, or nesting program inside municipality (or vice
  versa), breaks the separable moderators of the P10 objective. The AND chain
  is the minimal structure that keeps every dimension independently
  administerable.

#### 13. Read Authorization

**Final rules (RECOMMENDATION — P9 §6/§8 + P10 §13):**

| Read surface | Required | Notes |
|---|---|---|
| list / feed (`*/data`) | VIEW + scope | scope predicate injected server-side regardless of params (P10 §12) |
| search (client picker for transactions/scholars/households) | VIEW + scope | searches join `tbl_clients`; scope must apply |
| detail (`show` endpoints) | VIEW + scope | record municipality check |
| dashboard | auth + scope (future) | counts/summaries that surface data rows must scope (P1 parity today shows all — future requirement) |
| reports | VIEW + scope | `scholarship-reports/data` scopes via client join |
| AJAX helpers (barangay cascade, verify-mobile) | page gate only | no municipality-sensitive rows (exception) |
| exports | VIEW + **EXPORT** + program + scope | **EXPORT stays a separate action** (P9 §8) |

**VIEW sufficiency (RECOMMENDATION):** VIEW covers list/search/detail/feed
reads; it does **not** cover EXPORT — the export route additionally requires the
`EXPORT` action (P9 §8). No read is authorized by UI visibility alone.

#### 14. CREATE Authorization

**`CREATE` requires (RECOMMENDATION — P9 §7 + P10 §14):**

```
canAccessPage(page)  AND  canAccessAction(page, CREATE)
  AND canAccessProgram(program) [where the op consumes a program]
  AND submittedMunicipality ∈ userScope   [clients: posted city_municipality]
      -- or -- derivedClientMunicipality ∈ userScope  [transaction/scholar/GIP/
                                                       household via client]
```

- **Bypass prevention (mandatory):** the `city_municipality` field is
  **server-checked against scope** at store time — a user cannot change the
  posted municipality (or omit it) to escape the scope check; the server
  recomputes `submittedMunicipality` from the validated input and compares it to
  `userScope`. Changing a hidden field (`client_id`, `head_household`) is
  likewise caught because the **bound client's** municipality is the checked
  value (P10 §14).

#### 15. EDIT Authorization

**`EDIT` requires (RECOMMENDATION — P9 §7 + P10 §14):**

```
canAccessPage(page)  AND  canAccessAction(page, EDIT)
  AND canAccessProgram(program) [program-consuming ops]
  AND existingRecordMunicipality ∈ userScope
  AND (for clients) newSubmittedMunicipality ∈ userScope
```

- **Derived municipalities (FACT):** transactions, scholars, GIP, households,
  and family members have **no municipality column** — the checked value is the
  **bound client's `city_municipality`** (the verified client-join relationship,
  P10 §1/§14). No direct column is assumed where none exists.
- **Bypass prevention:** the check reads the **existing record** from the DB
  (never from the request), so editing an ID that maps to an out-of-scope record
  denies even if the request body is otherwise valid.

#### 16. DELETE Authorization

**`DELETE` requires (RECOMMENDATION — P9 §7 + P10 §14):**

```
canAccessPage(page)  AND  canAccessAction(page, DELETE)
  AND existingRecordMunicipality ∈ userScope
```

- **Parity note (FACT):** deletes carry **no program check today** (destroy has
  no `authorizeProgram`); program is not added to DELETE (no evidence, P9 §6).
- **Bypass prevention (mandatory):** the record's municipality is resolved
  **server-side from the row** (`tbl_clients.city_municipality` directly, or via
  the client join for derived tables, or the unpaid row's own FK) **after the
  id is read from the DB**. Changing `id`/`delete_id`/request parameters cannot
  alter the checked value (P10 §4).

#### 17. EXPORT Authorization

**`EXPORT` is a separate action (RECOMMENDATION — P9 §8 + P10 §18).**

```
canAccessPage(page)  AND  canAccessAction(page, EXPORT)
  AND canAccessProgram(program) [Transactions export inherits the program filter]
  AND everyExportedRow ∈ userScope
```

- **Filtered-result enforcement:** the export query gets the scope predicate
  **injected server-side**; the municipality/barangay parameters from the URL
  are presentation-only.
- **Parameter-omission bypass prevention (mandatory):** an export URL with no
  municipality parameter (today it streams the full set, FACT) or with an
  out-of-scope parameter must produce **only in-scope rows** (or an empty file) —
  never a widening (P10 §18).
- **Explicit statement (non-negotiable):** an export must **never** bypass
  row-level authorization.

#### 18. Security Enforcement Architecture

**One canonical stack (RECOMMENDATION — P9 §13 + P10 §12):**

| Question | Answering layer |
|---|---|
| **A. Can this user access this page?** | `AccessControlService::canAccessPage` + `page:` middleware (+ the `page` Gate) — existing, unchanged |
| **B. Can this user perform this action?** | `AccessControlService::canAccessAction` + `action:` middleware on mutation/export routes of adopted pages (+ a new `action` Gate) — P9 §13 |
| **C. Can this user access this program?** | `AccessControlService::canAccessProgram` + `program` Gate + controller `authorizeProgram` — existing, unchanged |
| **D. Can this user access this record/data?** | `AccessControlService::effectiveMunicipalityIds` / `canAccessRecord` — the **one** scope authority; consumed by (1) a query-scope composer and (2) record-level checks on single-ID/write endpoints (P10 §12) |

**Layer rules (RECOMMENDATION):**
- `AccessControlService` is the **only** place authorization semantics live
  (ADR-003; P9 §17.11).
- **Middleware** guards routes; **Gates** serve controller-level checks (the
  `ClientPolicy` precedent); **services/controllers** call the service but never
  re-implement decisions; **FormRequests** validate shape, never authorize (P9
  §13.4).
- **Query scopes / query composition** belong to a scope **composer** that adds
  the WHERE — an authorization **decision** (A–D) is distinct from **query
  filtering** (application of the decision to a query) (§19).

#### 19. Query / Data-Scope Enforcement

**Distinction (RECOMMENDATION — P10 §12):**

- **Authorization decision**: `canAccessRecord` — a boolean for a single record.
- **Query/data filtering**: applying the decided scope to a **query builder**
  (`whereIn(municipality set)`).

**Critical v2 fact (FACT — P10 §12):** the feeds, exports, and searches use
`DB::table` (query builder), **not** Eloquent models — Eloquent global scopes
would **never run** on them. Enforcement must therefore be:

1. A **scope composer** that appends the `whereIn(scope)` clause to every
   municipality-sensitive query (feeds, exports, search), invoked centrally
   from the same service that owns the scope set.
2. A **record check** for single-ID endpoints (show/edit/update/destroy/
   single-row export) — fetch row → resolve municipality (direct or client
   join) → `canAccessRecord`.

**Bypasses prevented (RECOMMENDATION — all mandatory):**

| Attack | Defense |
|---|---|
| unfiltered list access (omit param) | composer injects scope unconditionally |
| direct-ID access (`show`/`destroy` with an out-of-scope id) | record check on the fetched row |
| altered municipality parameter | composer ignores/overrides the client param with scope |
| export bypass | scope predicate in the export query (§17) |
| AJAX/feed bypass | composer applies to every `*/data` feed |
| detail endpoint bypass | record check on every `show` |

**Do not overstate (INFERENCE):** no current endpoint has a scope to bypass
today — these are the future enforcement seams, all server-side.

#### 20. Audit Architecture

**Future authorization mutations that require auditing (RECOMMENDATION — P9
§15 + P10 §19):**

| Mutation | Audit required | Candidate event (final names OPEN) |
|---|---|---|
| action permission grant/revoke (full-replace save) | yes | `MANAGE_ACTION_PERMISSIONS` (P9 §15.2) |
| municipality scope grant/revoke (row insert/delete) | yes | `MANAGE_SCOPE_GRANT` / `MANAGE_SCOPE_REVOKE` |
| municipality scope replacement (full-replace save) | yes | `MANAGE_SCOPE_...` (reuse the grant/revoke events on real change, P7 discipline) |
| all-municipality marker set/cleared | yes | same scope family |
| existing page/program/super-admin/exemption mutations | **already audited** (7 `MANAGE_*` strings) | unchanged |

- **Convention (FACT):** `AuditService::log(actorId, action, target_table,
  target_id, old_value, new_value)` with JSON payloads (`username` +
  ids/sets only, no secrets) supports all of the above without new
  infrastructure.
- **The seven approved `MANAGE_*` strings are kept intact** (P7 §12) —
  `MANAGE_USER_CREATE`, `MANAGE_PAGE_PERMISSIONS`, `MANAGE_PROGRAM_PERMISSIONS`,
  `MANAGE_SUPER_ADMIN_GRANT`, `MANAGE_SUPER_ADMIN_REVOKE`,
  `MANAGE_EXEMPTION_GRANT`, `MANAGE_EXEMPTION_REVOKE`.
- **New strings are NOT added in this pass** (P9 §15.2 named one candidate;
  final names **REQUIRE OWNER DECISION** and are written only at build).

#### 21. Backward Compatibility

**P9's S2 strategy remains appropriate after P10 (RECOMMENDATION — validated):**

1. **Explicit per-page enforcement flag** (config-driven) — a page is "page-only"
   (today's behavior) until its flag flips; action and scope enforcement apply
   only to flagged pages. Zero production behavior change at cutover.
2. **Audited per-user grants before activation** — grant action rows (P9 §12) and
   scope rows (P10 §21) for current holders **before** flipping each page's
   flag. No lockout window, no silent over-grant.
3. **No destructive cutover** — no backfill UPDATE, no table replacement, no
   `migrate:fresh`; only additive tables + config.
4. **P7 remains unchanged** — the four admin pages stay page-only; `'*'`
   toggle/exemption logic untouched (§1).

**After P10 (INFERENCE):** S2 extends naturally — scope is per-module too
(flip the flag → scope WHERE activates), and both dimensions share the same
grant-then-flip ordering. This remains the recommended transition.

#### 22. Migration / Cutover Architecture

**Future requirements only — nothing is migrated (RECOMMENDATION):**

| Aspect | Future requirement |
|---|---|
| existing users | non-`'*'` users keep page/program rows; receive explicit action + scope grants per pilot page **before** each flag flips (§21) |
| existing page permissions | untouched (`tbl_permissions` unchanged) |
| existing program permissions | untouched |
| existing Super Admin | `'*'` continues to satisfy every gate; no action/scope rows needed (§11) |
| new municipality assignments | new rows in `tbl_user_municipalities` (grant-then-flip) |
| new action permissions | new rows in `tbl_action_permissions` (grant-then-flip) |
| pilot pages | 5 pilots (§7) adopt first; remaining pages page-only |
| production cutover | per-page, additive, audited, reversible; no data backfill |
| rollback | flip the enforcement flag back (config) — behavior returns to page-only; additive tables remain but are inert |

**Must be reviewed before implementation (RECOMMENDATION):** the owner-approved
architecture (§28) + the exact all-marker representation (§9) + final audit
strings (§20) + the additive migrations (additive-only, AGENTS.md) + `schema:dump`
regen. **REQUIRES OWNER DECISION** on the cutover sequence (page-by-page vs
all-pilots-at-once).

#### 23. Administration Model

**Future Super Admin capabilities (RECOMMENDATION — conceptual, NOT built):**

- manage page permissions (exists — P7 `manage_permissions.php`)
- manage program permissions (exists — P7 `manage_program_permissions.php`)
- manage exemptions (exists — P7 `manage_multi_device_exemptions.php`)
- manage users (exists — P7 `register.php`)
- manage **action permissions** (new — future)
- manage **municipality assignments** (new — future)
- audit viewer (exists — P7 `audit_logs.php`)

**Independent vs combined screens (RECOMMENDATION):**

- **Independent screens** for page, action, program, and municipality — because
  each dimension is an independent admin resource with its own full-replace
  save and its own audit event (P7 pattern). Combined screens would couple
  four orthogonal grant sets into one form and one audit payload for no benefit.
- **Simple maintainable shape**: one admin page per dimension, each a
  per-user full-replace save (`{username}` + ids) emitting its own `MANAGE_*`
  event — mirroring the proven P7 `AdminPermissionController` pattern (FACT).
- **REQUIRES OWNER DECISION** on whether action+municipality administration
  ships together (recommended: same milestone, separate screens).

#### 24. Security Invariants

**Final non-negotiable invariants (RECOMMENDATION — mandatory at build):**

1. **No username/id admin checks** — all authorization via `AccessControlService`
   (ADR-003; grep-enforceable).
2. **`'*'` is the only Super Admin marker**.
3. **Page authorization is server-side** (`page:` middleware, unchanged).
4. **Action authorization is server-side** (`action:` middleware, adopted pages).
5. **Program authorization is server-side** (`permittedPrograms`/Gate, unchanged).
6. **Municipality authorization is server-side** (scope composer + record check).
7. **Missing required authorization fails closed** — unknown page/action denies;
   no-scope ⇒ zero records (§10); action-without-page denies.
8. **Exports cannot bypass authorization** (§17).
9. **Direct-ID requests cannot bypass scope** — record checks read the DB row,
   never the request (§15/§16/§19).
10. **Client-derived municipality relationships are verified server-side** (§15).
11. **UI filters are never treated as authorization** (§19).
12. **Super Admin bypass is explicit and centralized** in `AccessControlService`.
13. **Authorization logic is not duplicated across controllers** — one service.
14. **Existing production data is preserved** — additive-only schema, no
    destructive operations, `main_system` untouched.

#### 25. Final Target Architecture

**Conceptual diagram (RECOMMENDATION):**

```
                        User
                         |
        +----------------+----------------+----------------+-----------+
        |                |                |                |           |
        v                v                v                v           v
  Page Permissions  Action Permissions  Program Perm.  Municipality  Multi-device
  (tbl_permissions) (tbl_action_perm.) (tbl_program_)  Scope (tbl_    Exemption
      incl. '*'                                            user_muni.)  (session/
                                                                        device)
        |                |                |                |           |
        +----------------+----------------+----------------+           |
                         |                                             |
                         v                                             |
             AccessControlService (single authority)                   |
             |  canAccessPage / canAccessAction /                      |
             |  canAccessProgram / canAccessRecord                     |
             |  (isSuperAdmin, isMultiDeviceExempt)                    |
                         |                                             |
                         v                                             |
              +----------+----------+----------+                       |
              |          |          |          |                       |
              v          v          v          v                       |
          PAGE      ACTION      PROGRAM   MUNICIPALITY           (session gate
         decision   decision    decision    decision               short-circuits
                                                                  before ACL, at
                                                                  login/session)
              |          |          |          |
              +----------+----+-----+----------+
                               |
                               v
                    Application operation
            (route middleware -> controller -> service -> query)
```

**Explanation (INFERENCE):**

- **Four permission surfaces** feed one `AccessControlService`; each surface is
  a separate additive table (page = existing; action/scope = new).
- **The service** is the single decision authority (page, action, program,
  record/scope) — the diagram's "decision" row is four checks of the AND chain,
  evaluated per §3.
- **Multi-device exemption** is deliberately a **separate branch**: it is a
  session/device gate (single-device login), not a data authorization
  dimension; it is checked at the auth/session layer, not inside the ACL data
  decisions.
- **Every application operation** reaches data through the chain; reads are
  filtered by the scope composer, writes by the record checks, exports by the
  export predicate — all behind the same service.

#### 26. Final Decision Matrix

| Dimension | Current v2 | Proposed v2 | Schema change | Enforcement | Status |
|---|---|---|---|---|---|
| **Page** | `tbl_permissions` rows; `page:` middleware; page key = v1 key | unchanged | none | middleware + `AccessControlService` | **VERIFIED** (existing, final) |
| **Action** | none (page grant ⇒ all operations) | VIEW/CREATE/EDIT/DELETE/EXPORT on adopted pages; SCAN/MANAGE reserved | additive `tbl_action_permissions` (conceptual) | `action:` middleware + `canAccessAction` + `action` Gate | **RECOMMENDED** (P9) — pending owner approval |
| **Program** | `tbl_program_permissions`; `permittedPrograms` + `authorizeProgram`; empty = unrestricted | unchanged, independent AND dimension | none | Gate + controller (existing) | **VERIFIED** (existing, final) |
| **Municipality** | none (filters are reporting-only) | user-level scope; one/many/all; fail-closed no-scope | additive `tbl_user_municipalities` + all-marker (conceptual) | scope composer + record check in `AccessControlService` | **RECOMMENDED** (P10) — pending owner approval |
| **Super Admin** | `'*'` satisfies page gates | `'*'` bypasses page/action/program/municipality; no rows needed | none | centralized in `AccessControlService::isSuperAdmin` | **VERIFIED** (semantics extension RECOMMENDED) |
| **Multi-device exemption** | `tbl_multi_device_exemptions`; session gate | unchanged | none | session/auth layer | **VERIFIED** (final) |
| **Audit** | 7 `MANAGE_*` strings via `AuditService` | + future action/scope mutation events (names OPEN) | none | `AuditService` (sole writer) | **RECOMMENDED** (extensions) — names pending owner decision |
| **Combined model** | page-only AND | `Page ∧ Action ∧ Program ∧ Municipality` | additive only | as above | **OPEN** — architecture recommended, awaiting owner approval |

#### 27. Open Decision #6

**Open Decision #6 — Fine-Grained Authorization Architecture** — updated after
P11 (still **DEFERRED — REQUIRES OWNER APPROVAL**; nothing is claimed final):

| Component | Status |
|---|---|
| **A. Action-level authorization** | **Recommended** (P9 contract + P11 §6/§7) — pending owner approval, not implemented |
| **B. Municipality/data scope** | **Recommended** (P10 research + P11 §8–§10) — pending owner approval of the scope model and the all-marker representation, not implemented |
| **C. Combined authorization model** | **Recommended and validated** — `Page ∧ Action ∧ Program ∧ Municipality` (P11 §3/§26); pending owner approval, not finalized as binding |
| **D. Final schema** | **Unresolved** — candidates `tbl_action_permissions` and `tbl_user_municipalities` (+ all-marker) proposed; no table created; any migration additive + `schema:dump` regen (AGENTS.md) |

**Verified parts (FACT):** baseline page/program/super-admin/exemption
mechanics (P11 §1); the AND chain's consistency with every moderator scenario.
**Recommended but not final:** everything in P9 §18 and P10 §24. **Pending
owner approval:** the consolidated contract (§28). **Unresolved:** exact
all-marker representation, final audit strings, exact DDL, cutover sequence.

#### 28. Owner Approval Contract

**One consolidated architecture decision (RECOMMENDATION — replaces separate
P9/P10 approvals).** The owner approves the architecture **as a whole**, then
implementation separately.

**ARCHITECTURE APPROVAL — required items:**

1. **Action vocabulary**: `VIEW, CREATE, EDIT, DELETE, EXPORT` (+ reserved
   `SCAN`, `MANAGE`; no `APPROVE`/`VERIFY`/`PAYOUT`) — P9 §4, P11 §6.
2. **Phase-1 action adoption**: `clients.php`, `household.php`,
   `all_transactions.php`, `scholars.php`, `register.php` — P9 §5, P11 §7.
3. **`'*'` semantics**: bypasses page/action/program/municipality; no rows
   needed — P9 §10, P11 §11.
4. **Fail-closed missing-action behavior**: unknown page/action denies;
   non-adopted pages unchanged — P9 §11, P11 §10.
5. **`tbl_action_permissions` conceptual model** (user, page, action;
   UNIQUE triple; presence = allow) — P9 §3, P11 §4.
6. **Municipality scope model**: user-level pivot, independent dimension —
   P10 §6/§8, P11 §8.
7. **`tbl_user_municipalities` conceptual model** (user, municipality;
   UNIQUE pair) — P10 §11, P11 §4.
8. **Single/multiple/all municipality support** — one, many, and all without
   separate accounts — P10 §15, P11 §8.
9. **No-scope behavior**: fail closed — P10 §17, P11 §10.
10. **Action + municipality AND relationship** — P10 §7, P11 §14–§16.
11. **Program + municipality AND relationship** — P10 §8, P11 §12.
12. **Enforcement architecture**: `AccessControlService` + `action:` middleware
    + scope composer + record checks; UI filtering rejected — P9 §13, P10 §12,
    P11 §18/§19.
13. **Backward compatibility / S2 rollout**: explicit per-page flag, grant-
    then-flip, zero unexpected access loss, P7 untouched — P9 §12, P11 §21.
14. **Future administration model**: independent per-dimension screens —
    P11 §23.
15. **Cutover strategy**: per-page additive, audited, reversible — P11 §22.
16. **Future authorization audit requirements**: grant/revoke/replace events via
    `AuditService`; 7 `MANAGE_*` intact; final names approved at build —
    P9 §15, P10 §19, P11 §20.
17. **Final schema approval**: the two additive tables (exact DDL) — P11 §4.

**IMPLEMENTATION APPROVAL — separate, later:** approving this contract is
**not** approval to build. Implementation begins only on an explicit
second approval (after this architecture is signed off), following the
additive-only rules (AGENTS.md) and P7 conventions.

---

**HARD STOP — Pass 11 complete.** No code, schema, migration, route, model,
controller, service, middleware, policy, view, test, seeder, or DB operation
was run; `tbl_action_permissions` and `tbl_user_municipalities` were **not**
created; no existing table was altered; v1 was not modified; the local or
production database was not touched; no file other than this one was modified
(verified with `git status`). Open Decision #6 remains `DEFERRED — REQUIRES
OWNER APPROVAL`, with A (action) and B (municipality/data-scope) recommended,
C (combined model) recommended and validated as
`Page ∧ Action ∧ Program ∧ Municipality`, and D (schema) unresolved. Next
step: the owner reviews and approves the §28 consolidated architecture
contract (ARCHITECTURE APPROVAL), after which implementation may be proposed
separately (IMPLEMENTATION APPROVAL).

---

### P11 Architecture Approval (2026-08-15)

> **Status: ARCHITECTURE APPROVAL — GRANTED (implementation NOT approved).**
> The owner reviewed the Pass 11 Combined Authorization Architecture Contract
> and approved it as the target authorization architecture for 2DMIS v2.
> **Implementation approval remains pending** — nothing has been or may be
> implemented until a separate implementation approval is given.

#### Approved scope

1. **Priority population**: authenticated **staff** users (Administrators,
   Moderators).
2. **Client Portal is OUT OF SCOPE** for this authorization implementation.
   v1 has client records but **no client accounts**; this architecture must
   **not** introduce client accounts or client authentication (FACT — v1
   self-service flows are anonymous; P8 §3, P10 §13).

#### Approved architecture items (owner-confirmed, all 16)

1. Action authorization uses **VIEW, CREATE, EDIT, DELETE, EXPORT**; **SCAN**
   and **MANAGE** remain reserved; **APPROVE, VERIFY, PAYOUT** are not
   introduced at this time.
2. Phase-1 action authorization applies to **clients.php, household.php,
   all_transactions.php, scholars.php, register.php**.
3. Existing **`tbl_permissions`** remains the page-level authorization layer.
4. Existing **`tbl_program_permissions`** remains unchanged and independent.
5. **Municipality/data scope** is a separate authorization dimension.
6. Authorization is composed as **PAGE ∧ ACTION ∧ PROGRAM ∧ MUNICIPALITY**,
   with the applicable dimensions enforced according to the operation.
7. **Municipality authorization is enforced server-side**; UI/reporting filters
   are never treated as authorization.
8. Super Admin remains identified solely by **`tbl_permissions.page_name =
   '*'`** and bypasses page, action, program, and municipality restrictions.
9. **Missing authorization fails closed.**
10. **EXPORT** is a distinct authorization action and must not bypass any
    applicable authorization or municipality restriction.
11. **`AccessControlService`** remains the single authorization authority.
12. Existing **P7 authorization** must remain intact.
13. The **S2 grant-then-flip strategy** is used to introduce action/scope
    enforcement without unexpectedly removing existing access.
14. Action permissions and municipality assignments will eventually be managed
    through dedicated administration functionality.
15. Future authorization mutations continue to use **`AuditService`** as the
    sole audit writer.
16. The conceptual direction of **`tbl_action_permissions`** and
    **`tbl_user_municipalities`** is approved.

#### Effect on Open Decision #6

| Component | Status after approval |
|---|---|
| **A. Action-level authorization** | **APPROVED (architecture)** — implementation pending |
| **B. Municipality/data scope** | **APPROVED (architecture)** — implementation pending |
| **C. Combined model** | **APPROVED (architecture)** — `Page ∧ Action ∧ Program ∧ Municipality`, per-operation enforcement |
| **D. Final schema** | **UNRESOLVED (pending implementation approval)** — conceptual `tbl_action_permissions` + `tbl_user_municipalities` (+ all-marker representation) approved in direction; exact DDL reviewed at implementation |

Open Decision #6 is now: `APPROVED (ARCHITECTURE) — REQUIRES IMPLEMENTATION
APPROVAL`.

#### Next step

Propose the **IMPLEMENTATION APPROVAL** package (additive migrations with exact
DDL, `schema:dump` regen, S2 flag config, `AccessControlService` extensions,
`action:` middleware, scope composer + record checks, P7-compatible admin
screens, audit events, tests) for the owner to review **before** any code is
written.

---

**HARD STOP — architecture approval recorded, no implementation.** No code,
schema, migration, route, model, controller, service, middleware, policy,
view, test, seeder, or DB operation was run; `tbl_action_permissions` and
`tbl_user_municipalities` were **not** created; no existing table was altered;
v1 was not modified; the local or production database was not touched; no file
other than this one was modified (verified with `git status`). Open Decision #6
is `APPROVED (ARCHITECTURE) — REQUIRES IMPLEMENTATION APPROVAL`; implementation
will not begin until a separate IMPLEMENTATION APPROVAL is granted.

---

### P12 — Authorization Implementation Contract (2026-08-15)

> **Status: CONTRACT ONLY — nothing implemented.** This pass converts the
> owner-approved P11 architecture (P11 §28) into an exact, implementation-ready
> contract: exact additive DDL, exact service/API/middleware/Gate seams, exact
> route→action mapping, the scope resolver/composer, record-level checks, the
> S2 rollout mechanism, the admin screens, the audit events, the
> migration/cutover/rollback plan, and the test plan. **No code, schema,
> migration, route, model, service, middleware, policy, view, or test was
> written.** Every `REQUIRES OWNER DECISION` item below is a bind-point for the
> separate IMPLEMENTATION APPROVAL; nothing is implemented until that approval.

#### 0. How to read this contract

- Sections 1–21 map 1:1 onto the Pass-12 scope list requested by the owner.
- Labels: **FACT** = verified against the current v2 code/DB; **INFERENCE** =
  reasoned from facts; **RECOMMENDATION** = the bind-if-approved default;
  **REQUIRES OWNER DECISION** = a bind-point confirmed at IMPLEMENTATION
  APPROVAL.
- Everything is additive (AGENTS.md): two new tables, no ALTER on any existing
  table, no data backfill, no destructive operation. The local `main_system`
  copy and production stay byte-identical until build time, and even then only
  the two new tables are added.

#### 1. Current Authorization Baseline (FACT — re-verified for this contract)

| Layer | Existing implementation | Reference |
|---|---|---|
| Page gate | `Gate::define('page', …)`; `AuthorizePage` middleware (`page:clients.php`), redirect-dashboard + `login_status=denied` / 403 JSON | `app/Providers/AppServiceProvider.php:29`, `app/Http/Middleware/AuthorizePage.php` |
| Program gate | `Gate::define('program', …)`; `TransactionController::authorizeProgram`; `permittedPrograms` on feeds/store/export; empty `tbl_program_permissions` = unrestricted | `AppServiceProvider:30`, `TransactionService::PROGRAMS` |
| Super Admin | `tbl_permissions.page_name = '*'`, `AccessControlService::isSuperAdmin`; satisfies every gate | `app/Services/AccessControlService.php:21,32` |
| Single-device | `session_token` check; `isSingleDeviceExempt` = `'*'` OR `tbl_multi_device_exemptions` row | `AccessControlService.php:61` |
| Audit | `AuditService::log(userId, action, targetTable, targetId, oldValue, newValue)`; sole writer; 7 `MANAGE_*` strings | `app/Services/AuditService.php`, `AdminPermissionController` |
| ACL registry | `tbl_permissions`, `tbl_program_permissions`, `tbl_multi_device_exemptions` — byte-identical to production (P4 §11) | `database/schema/mysql-schema.sql` |

**Feeds/exports/search use `DB::table` (query builder), not Eloquent** (FACT —
P10 §12) → Eloquent global scopes would never run; the scope composer + record
checks below are the enforcement seams. Feeds are VIEW (never separately
action-gated); only the three exports exist and only `transactions.export`
gains an EXPORT action in Phase 1 (P9 §20.5).

#### 2. Exact DDL — `tbl_action_permissions` (RECOMMENDATION — bind at approval)

Resolves P9 §18.6 / P11 §28.5. Mirror of `tbl_program_permissions`
(`database/schema/mysql-schema.sql:463`) + a `created_at` column and the
triple unique.

**Proposed additive migration `2026_08_15_000001_create_tbl_action_permissions_table.php`:**

```php
Schema::create('tbl_action_permissions', function (Blueprint $table) {
    $table->integer('id')->autoIncrement();
    $table->integer('user_id');            // mirrors tbl_permissions.user_id (int(11))
    $table->string('page_name', 100);      // real v1 page keys (ADR-003)
    $table->string('action', 50);          // canonical verb: CREATE/EDIT/DELETE/EXPORT (VIEW is page-implied)
    $table->dateTime('created_at')->useCurrent();  // grant-time accountability
    $table->unique(['user_id', 'page_name', 'action'], 'uniq_action_permission_user_page_action');
});
```

**Resulting MySQL DDL (post-`schema:dump`, MariaDB/XAMPP dialect):**

```sql
CREATE TABLE `tbl_action_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `page_name` varchar(100) NOT NULL,
  `action` varchar(50) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_action_permission_user_page_action` (`user_id`,`page_name`,`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Design binds (each resolves a prior open question):

| Aspect | Resolution | Prior ref |
|---|---|---|
| No `can_access` flag | **presence-of-row = allow**; no deny rows (P9 §11.C). Resolves P9 §22.5. | P9 §3 |
| No FK to `tbl_users` | mirrors `tbl_permissions`/`tbl_program_permissions`, which have **no FK** — additive safety, no foreign-key enforcement on the legacy auth table | P11 §4 |
| VIEW not stored | page row = VIEW grant on adopted pages (P9 §6); `tbl_action_permissions` stores only `CREATE/EDIT/DELETE/EXPORT` | P9 §6 |
| Unique triple | idempotent full-replace target for the admin screen (P7 pattern) | P9 §3 |

**Required?** Yes (action authorization needs a home; P9 §3, storage A).
**Existing tables altered?** None. **Ambiguity?** None — identical shape to the
two proven permission tables.

#### 3. Exact DDL — `tbl_user_municipalities` + all-marker (RECOMMENDATION — bind at approval)

Resolves P10 §16/§24.3 + P11 §8/§9. Mirror of `tbl_program_permissions` +
`created_at` + the **all-marker as a reserved sentinel row**.

**All-marker resolution (RESOLVED — sentinel `municipality_id = 0`):**

Of the P11 §9 candidates, the contract binds **Option A inside the pivot** as a
**reserved `municipality_id` value `0`** (constant
`AccessControlService::ALL_MUNICIPALITY_MARKER = 0`), documented as "ALL".

- **Why `0` (INFERENCE):** `tbl_municipalities.id` is auto-increment starting
  at 1 (production ids 1–34; schema:371) — id `0` can never be a real
  municipality, so the sentinel is unambiguous; a single row replaces 34 rows;
  it rides the existing `UNIQUE(user_id, municipality_id)` with no extra column,
  no `NULL` semantics (P10 §16.B rejected), and no separate table (P10 §16.D).
- **Distinct from `page_name='*'` (FACT):** different table, different
  subject (data scope vs module entry), different service method
  (`hasAllMunicipalities` vs `isSuperAdmin`) — the two cannot be confused.
- **Grant-only (FACT/P10 §16):** only the scope-admin screen can write the
  marker row; municipality pickers never offer id `0`; it is never a default.
- **If the owner prefers an `is_all` column instead (REQUIRES OWNER DECISION):**
  the difference is confined to this migration + `hasAllMunicipalities`; the
  sentinel is the recommended default and everything below assumes it.

**Proposed additive migration `2026_08_15_000002_create_tbl_user_municipalities_table.php`:**

```php
Schema::create('tbl_user_municipalities', function (Blueprint $table) {
    $table->integer('id')->autoIncrement();
    $table->integer('user_id');            // mirrors tbl_permissions.user_id (int(11))
    $table->integer('municipality_id');    // tbl_municipalities.id, or 0 = ALL marker
    $table->dateTime('created_at')->useCurrent();
    $table->unique(['user_id', 'municipality_id'], 'uniq_user_municipality');
});
```

**Resulting MySQL DDL:**

```sql
CREATE TABLE `tbl_user_municipalities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `municipality_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_municipality` (`user_id`,`municipality_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Scope state by rows (FACT/P11 §8):

| State | Rows |
|---|---|
| one municipality | one row `(user_id, m)` |
| several | several rows |
| all | **only** the marker row `(user_id, 0)` — explicit rows are ignored and, on save, removed |
| none (fail closed) | zero rows |

**No FK** on either column (mirrors the permission tables; additive-safe). No
existing table altered; no data backfill (scope is evaluated by join, P10 §11.3).

#### 4. Exact Table Relationships

| Relation | Kind | Implementation | Rule |
|---|---|---|---|
| `tbl_users` → `tbl_permissions` | 1:N (page) | `User::permissions()` HasMany; `Permission::user()` BelongsTo — existing | unchanged |
| `tbl_users` → `tbl_program_permissions` | 1:N (program) | `User::programPermissions()` — existing | unchanged |
| `tbl_users` → `tbl_multi_device_exemptions` | 1:N | `User::multiDeviceExemptions()` — existing | unchanged |
| `tbl_users` → `tbl_action_permissions` | 1:N (operation grant) | new `User::actionPermissions()` HasMany + `ActionPermission::user()` BelongsTo | **logical ref only** (no FK), mirroring the other grant tables |
| `tbl_users` → `tbl_user_municipalities` | 1:N (scope pivot) | new `User::municipalityScope()` HasMany + `UserMunicipality::user()` BelongsTo | **logical ref only** (no FK) |
| `tbl_user_municipalities.municipality_id` → `tbl_municipalities.id` | logical ref | no FK; id `0` = ALL marker (§3) | ids `1..N` = real municipalities |
| `tbl_clients.city_municipality` → `tbl_municipalities.id` | **derived data scope** | int-in-varchar(100), no FK (FACT P10 §1) | scope resolution at query/record time |
| `tbl_unpaid_verifications.municipality_id` → `tbl_municipalities.id` | true FK (only one) | exists | unpaid admin delete scope-checks this column |
| derived tables (transactions/scholars/gip/households/family) | via `tbl_clients` join | no municipality column of their own (FACT P10 §1/§14) | record municipality = bound client's `city_municipality` |

`tbl_action_permissions` is **independent** of `tbl_permissions` and
`tbl_program_permissions` (a page row never implies an action row; an action
row never grants page entry — P9 §11.D). `tbl_user_municipalities` is
**independent** of all three (P10 §20.A).

**Models added at build:** `App\Models\ActionPermission`
(`$table='tbl_action_permissions'`, fillable `user_id,page_name,action`,
`$timestamps=false`, `belongsTo(User)`), `App\Models\UserMunicipality`
(`$table='tbl_user_municipalities'`, fillable `user_id,municipality_id`,
`$timestamps=false`, `belongsTo(User)`); two `HasMany` relations on `User`.
This is the **only** model-surface change; no existing model is altered.

#### 5. `AccessControlService` Changes (single authority — ADR-003)

New constants and methods (semantics bind exactly; placement mirrors the
existing per-request cache pattern `AccessControlService.php:99-112`):

```php
public const ALL_MUNICIPALITY_MARKER = 0;

public function canAccessAction(User $user, string $pageName, string $action): bool;
public function permittedActions(User $user, string $pageName): array;
public function hasAllMunicipalities(User $user): bool;
public function effectiveMunicipalityIds(User $user): array; // list<int>
public function canAccessRecord(User $user, int $municipalityId): bool;
public function applyMunicipalityScope(Builder $query, User $user, string $column): Builder;
```

**`canAccessAction` truth table (binds P9 §11 + §12 + P11 §21):**

| Case | Verdict |
|---|---|
| `'*'` user | **true** (bypasses ACTION; no rows needed — P9 §10) |
| page not in `config('authorization.pages')` | **true** (non-adopted = page-only = today's behavior) |
| page adopted, `enforcement => false` (S2 off) | **true** (today's behavior until the flag flips) |
| page adopted, enforcement on, `action = VIEW` | `canAccessPage(user, page)` — page row IS the VIEW grant (P9 §6) |
| page adopted, enforcement on, action ≠ VIEW | presence of `(user, page_name, action)` row in `tbl_action_permissions` |
| anything unknown (page/action) | **false** (fail closed — P9 §11) |
| action row present, no page row | **false** (case D — action never grants entry) |

**`effectiveMunicipalityIds`:** `'*'` or `hasAllMunicipalities` → all
`tbl_municipalities.id`s; else the explicit pivot set (empty ⇒ zero records,
fail closed — P10 §17). `canAccessRecord`: `'*'` → true; `hasAllMunicipalities`
→ true; else `in_array($municipalityId, effectiveMunicipalityIds)`.
`applyMunicipalityScope`: `'*'`/all → return query unchanged; else
`$query->whereIn($column, effectiveMunicipalityIds)` — **the only** composer
entry point (P10 §12.B.1-2). Per-request caches: `$actionPermissions`,
`$municipalityScope` arrays keyed by user id, exactly mirroring
`pagePermissions`/`programPermissions`.

**No username/id checks anywhere** (ADR-003); `'*'` bypass lives solely here
(P9 §17.11). FormRequests do **not** authorize (P7 requests return `true` —
FACT); services do **not** re-implement decisions (P9 §13.4).

#### 6. Action API

`Gate::define('action', fn (User $u, string $page, string $action) =>
app(AccessControlService::class)->canAccessAction($u, $page, $action));` in
`AppServiceProvider::boot()` next to the `page`/`program` Gates (line 30
pattern). `AccessControlService::permittedActions` feeds the admin screen and
UI affordance rendering (presentation only — the middleware/Gate is the
boundary, P9 §13.5). Route-level usage: `action:clients.php:create` middleware
(§7). The reserved `SCAN`/`MANAGE` names are catalog-only strings — no rows are
written for them in Phase 1 (P9 §5).

#### 7. `action:` Middleware and `action` Gate

**New middleware `App\Http\Middleware\AuthorizeAction` — exact mirror of
`AuthorizePage`** (FACT pattern at `app/Http/Middleware/AuthorizePage.php`):

```php
public function handle(Request $request, Closure $next, string $pageName, string $action): Response
{
    $user = $request->user();
    if ($user !== null && app(AccessControlService::class)->canAccessAction($user, $pageName, $action)) {
        return $next($request);
    }
    if ($request->expectsJson()) {
        abort(403, 'Access denied.');
    }
    return redirect()->route('dashboard')->with('login_status', 'denied');
}
```

Registered with alias `'action'` in `bootstrap/app.php` `withMiddleware` (next
to `'page'`). Applied **on the specific mutation/export routes** of adopted
pages (full mapping in §11); the `page:` middleware stays on each route group
(entry/VIEW). 403-JSON for feeds-adjacent JSON posts; dashboard redirect +
`login_status=denied` for HTML — identical UX to `AuthorizePage` (P9 §13.2).

#### 8. Scope Resolver and Query Composer

Two seams (P10 §12.B / P11 §18.D):

**1. Composer — `AccessControlService::applyMunicipalityScope`** (§5) injects
`whereIn(scope)` into every municipality-sensitive query. Applied at build to:

| Query | Column to scope (FACT P10 §1) |
|---|---|
| `clients.data` feed, clients search (transactions/scholars/household pickers) | `tbl_clients.city_municipality` |
| `households.data`, household client search | `tbl_clients.city_municipality` (head client join) |
| `transactions.data`, `transactions.export` | `tbl_clients.city_municipality` (transaction→client join) |
| `scholars.data`, scholar client search | `tbl_clients.city_municipality` (scholar→client join) |
| `duplicates.data` | `tbl_clients.city_municipality` |
| `unpaid-verifications.data`/`export` **when the module adopts scope** (not Phase 1) | `tbl_unpaid_verifications.municipality_id` (own FK) |

The composer runs **regardless of client params** — an omitted or altered
municipality parameter never widens the set (P10 §4/§12).

**2. Record checks — municipality resolver + `canAccessRecord`.** New
`App\Support\RecordMunicipality` static resolvers (data access, not an
authorization decision — P9 §13.4): `ofClient(int)`, `ofTransaction(int)`,
`ofHousehold(int)`, `ofScholar(int)`, `ofGip(int)`, `ofUnpaidVerification(int)`
→ returns the municipality id (direct `city_municipality` or client join). The
**controller** calls `$acl->canAccessRecord($user, RecordMunicipality::ofX($id))`
and aborts (403 JSON / dashboard-redirect) on false. **The row is always read
from the DB, never from the request** (P11 §15/§16 — changing an id or hidden
field cannot change the checked value).

#### 9. Record-Level Checks (single-ID and write endpoints — Phase 1)

| Endpoint | Record municipality source | Check |
|---|---|---|
| `clients.show` | client row `city_municipality` | VIEW + record |
| `clients.edit`/`update` | existing client `city_municipality` **and** new posted `city_municipality` | EDIT + both ∊ scope (P11 §15) |
| `clients.store` | posted (validated) `city_municipality` | CREATE + ∊ scope (P11 §14) |
| `clients.destroy` / `duplicates.destroy` | each target client | DELETE + record (P11 §16) |
| `households.show`/`destroy` | head client | VIEW/DELETE + record |
| `households.store` | posted head client | CREATE + record |
| `transactions.show`/`edit`/`update`/`inline-update` | transaction→client | VIEW/EDIT + record (+ program, existing) |
| `transactions.store` | bound client (hidden-field tamper caught) | CREATE + record (+ program) |
| `transactions.destroy` | transaction→client | DELETE + record |
| `scholars.show`/`edit`/`update` | scholar→client | VIEW/EDIT + record |
| `scholars.update-client-id` (relink) | **old and new** client | EDIT + both ∊ scope |
| `scholars.store` | bound client | CREATE + record |
| `admin.users.store` (register.php) | — (metadata, no municipality — P11 §7) | CREATE action only |
| `transactions.export` | composer (§8) — never a widening | EXPORT + program + scope (P11 §17) |

GIP (`gip.store`) inherits `clients.php` CREATE + client record scope
(P10 §14). AJAX helpers (barangay cascade, verify-mobile) are page-gated only
— no data rows (P10 §13 exception). Public self-service (student/unpaid/
grantee/QR) is anonymous — **no authz layer applies** (P9 §20.9).

#### 10. Per-Action Behavior (Phase 1 pilots)

| Action | Allows | Denies | Reference |
|---|---|---|---|
| **VIEW** | page entry, index, detail/show, feeds (`/data`), searches (scoped) | nothing beyond the page gate (no separate row needed) | P9 §6 |
| **CREATE** | store routes of adopted pages, subject to program + scope | missing row ⇒ 403/redirect; out-of-scope submitted municipality | P11 §14 |
| **EDIT** | update/inline-update/relink/photo routes, subject to program + scope | missing row; out-of-scope existing or new municipality | P11 §15 |
| **DELETE** | destroy/dedupe-destroy routes, subject to scope (no program check today — parity, P9 §20.6) | missing row; out-of-scope record | P11 §16 |
| **EXPORT** | `transactions.export` only (Phase 1); scoped + program-filtered | missing EXPORT row; any out-of-scope row in output | P11 §17 |

SCAN/MANAGE reserved (no rows written); APPROVE/VERIFY/PAYOUT not introduced
(P11 approved scope §2).

#### 11. Exact Route → Middleware Mapping (Phase 1)

Every mutation/export route of the five pilots carries `action:<page>:<action>`
**on top of** the existing `page:<page>` group. `view` routes get no extra
middleware (page = VIEW). Full mapping (FACT from `routes/web.php`):

| Route | page group | additional middleware |
|---|---|---|
| `clients.store` | `page:clients.php` | `action:clients.php:create` |
| `clients.update` | same | `action:clients.php:edit` |
| `clients.destroy` | same | `action:clients.php:delete` |
| `family-members.store` | same | `action:clients.php:create` (sub-resource, P9 §5.1) |
| `gip.store` | same | `action:clients.php:create` |
| `clients.photo` | same | `action:clients.php:edit` |
| `duplicates.destroy` | same | `action:clients.php:delete` |
| `households.store` | `page:household.php` | `action:household.php:create` |
| `households.destroy` | same | `action:household.php:delete` |
| `transactions.store` | `page:all_transactions.php` | `action:all_transactions.php:create` |
| `transactions.update`, `transactions.inline-update` | same | `action:all_transactions.php:edit` |
| `transactions.destroy` | same | `action:all_transactions.php:delete` |
| `transactions.export` | same | `action:all_transactions.php:export` |
| `scholars.store` | `page:scholars.php` | `action:scholars.php:create` |
| `scholars.update`, `scholars.update-client-id` | same | `action:scholars.php:edit` |
| `admin.users.store` | `page:register.php` | `action:register.php:create` |

No route is **removed**; no page gate is loosened; all VIEW/feed routes stay
page-only (P9 §13.5). `household.php` has no EDIT route (FACT — P9 §20.6).

#### 12. Program + Action + Scope Interaction (P11 §12/§14)

For program-consuming operations the checks AND together, evaluated on the
route: `canAccessPage` (middleware) → `canAccessAction` (middleware) →
`canAccessProgram` (existing `authorizeProgram`/`permittedPrograms`, unchanged)
→ scope (composer for lists, `canAccessRecord` for single-ID). Example:
`transactions.export` requires `page ∧ export ∧ program ∧ scope` — one query
carries both the program filter and the scope `whereIn` (P11 §3/§12).
`tbl_program_permissions` is **unchanged** (no rows for the new dimensions are
stored there); empty program set stays unrestricted (v1 parity, P9 §14.4). No
program check is added where none exists today (scanners deferred — untouched).

#### 13. S2 Rollout Mechanism (binds P9 §12 / P11 §21)

**New config file `config/authorization.php`** (deterministic, reviewable,
reversible — P9 §12):

```php
return [
    'catalog' => ['VIEW', 'CREATE', 'EDIT', 'DELETE', 'EXPORT'], // canonical verbs
    'pages' => [
        'clients.php'         => ['enforcement' => false, 'actions' => ['VIEW','CREATE','EDIT','DELETE']],
        'household.php'       => ['enforcement' => false, 'actions' => ['VIEW','CREATE','DELETE']],
        'all_transactions.php'=> ['enforcement' => false, 'actions' => ['VIEW','CREATE','EDIT','DELETE','EXPORT']],
        'scholars.php'        => ['enforcement' => false, 'actions' => ['VIEW','CREATE','EDIT']],
        'register.php'        => ['enforcement' => false, 'actions' => ['CREATE']],
    ],
];
```

- **`enforcement => false` is the shipped default** → `canAccessAction` returns
  true for every pilot page and the composer is inert: behavior is byte-identical
  to today (P9 §12.1). `SCAN`/`MANAGE` never appear in `pages[].actions` in
  Phase 1.
- **Adoption = grant-then-flip** (P9 §12.2): an admin (via the §15 screens,
  audited) grants the current holders of each pilot page their existing
  operations (`CREATE/EDIT/DELETE/EXPORT` rows) and their scope rows/all-marker
  **first**; then `enforcement => true` for that page. No lockout window, no
  silent over-grant (grants are explicit, per-user, audited — never a seed).
- One `enforcement` flag per page covers action **and** scope together (both
  dimensions adopt per page; P11 §21.3). Pages flip independently.
- Cutover sequence **REQUIRES OWNER DECISION**: recommended **page-by-page** in
  this order — `clients.php` → `household.php` → `all_transactions.php` →
  `scholars.php` → `register.php` (smallest blast radius, each flip audited and
  logged in `IMPLEMENTATION_LOG`). All-at-once is acceptable but not recommended.

#### 14. Phase-1 Page Behavior (post-flip summary)

| Page | Entry | Writes require | Data scope |
|---|---|---|---|
| `clients.php` | page row = VIEW | CREATE/EDIT/DELETE rows | direct `city_municipality` |
| `household.php` | page row = VIEW | CREATE/DELETE rows | head client |
| `all_transactions.php` | page row = VIEW | CREATE/EDIT/DELETE/EXPORT rows | transaction→client + program |
| `scholars.php` | page row = VIEW | CREATE/EDIT rows | scholar→client |
| `register.php` | page row = VIEW | CREATE row | none (metadata) |

After all five flags flip, a non-`'*'` user with a page row but **no** action
rows can VIEW (lists/detail/feeds, scoped) but **cannot write/export**; a user
with action rows but **no** scope sees empty feeds and is denied every
write/detail (P11 §10) — both fail closed, by design.

#### 15. Page-Only Behavior (every other page — unchanged)

All non-pilot pages keep today's page-gate-only behavior **byte-for-byte**
(P9 §5 / P11 §7): the 14 `scanner_*.php` keys, 3 `scanned_payouts*.php`,
`scanned_payouts2.php`, `scanned_payouts_unpaid.php`,
`scholarship_reports.php` (+ its export), `update_logs.php`,
`unpaid_verifications.php` (+ export — module not adopted in Phase 1),
`currently_logged_users.php`, `force_logout.php`, the 4 P7 admin pages
(`manage_permissions.php`, `manage_program_permissions.php`,
`manage_multi_device_exemptions.php`, `audit_logs.php`), and `dashboard`.
No `action:` middleware, no scope composer, no record checks, no config entry —
`canAccessAction` short-circuits true for them (not in `config.pages`). The
three exports: only `transactions.export` gains EXPORT+scope; the other two
stay page-gated (parity) until their modules adopt.

#### 16. Admin Functionality (new screens — build-time, not built now)

Mirrors the P7 `AdminPermissionController` pattern exactly (full-replace save,
per-user, audited; P11 §23). Both new screens live under the **existing**
`page:manage_permissions.php` route group (page-only Phase 1; `MANAGE` is the
reserved action when this page ever adopts — P9 §5) so **no new page key is
invented** (ADR-003). Two new routes + views + FormRequests + controller
methods:

**A. Action permissions — `GET/POST admin/action-permissions`**
(`AdminPermissionController::actions` / `updateActions`, view
`admin.permissions.actions`). Per (user, page): checkbox list of that page's
catalog from `config('authorization.pages')` (VIEW disabled — implied by page).
Save = full-replace `DELETE` + `INSERT` inside `DB::transaction`, audited as
`MANAGE_ACTION_PERMISSIONS` (§17). Validation: `ActionPermissionRequest`
validates `page_name` against the config pages, `actions.*` against the page's
catalog (mirrors `PagePermissionRequest`).

**B. Municipality scope — `GET/POST admin/municipality-scope`**
(`AdminPermissionController::scopes` / `updateScopes`, view
`admin.permissions.scopes`). Per user: 34 municipality checkboxes + an **"All
municipalities"** toggle. Save = full-replace: all → write only the marker row
`(user, 0)`; else write the selected `(user, m)` rows; delete the rest.
Audited as `MANAGE_SCOPE_ASSIGNMENTS` (§17). `MunicipalityScopeRequest`
validates `municipalities.*` ∈ real `tbl_municipalities.id`s; `all` is boolean.
`'*'` users may be shown with the "no rows needed" note but no rows are written
(P11 §11). Sidebar links added to `partials/sidebar.blade.php` (P7 pattern).

**REQUIRES OWNER DECISION:** action + scope admin ship together in the same
implementation milestone (recommended: **same milestone, separate screens** —
P11 §23).

#### 17. Audit Actions

`AuditService` stays the **sole audit writer**; the 7 `MANAGE_*` strings are
kept intact (P11 §20). Two new events (final names bind here; P9 §18.9 + P10
§19.3 resolved):

| Event | When | `target_table` | `target_id` | payload (`old_value`/`new_value` JSON) |
|---|---|---|---|---|
| `MANAGE_ACTION_PERMISSIONS` | action full-replace save with real change | `tbl_action_permissions` | subject user id | `{'username', 'page_name', 'actions': [sorted list]}` |
| `MANAGE_SCOPE_ASSIGNMENTS` | scope full-replace save with real change | `tbl_user_municipalities` | subject user id | `{'username', 'municipality_ids': [sorted], 'all': bool}` |

- Full-replace discipline (P7): **one event per save**, only when the state
  actually changed; no event on no-op saves.
- Payloads carry **username + ids/actions only — never `password`/
  `session_token`** (P9 §15.4).
- Existing `MANAGE_SUPER_ADMIN_GRANT/REVOKE`, `MANAGE_PAGE_PERMISSIONS`,
  `MANAGE_PROGRAM_PERMISSIONS`, `MANAGE_EXEMPTION_GRANT/REVOKE`,
  `MANAGE_USER_CREATE` unchanged.
- No read-audits; the scope composer and action checks write nothing (P9 §15.3).

#### 18. Migration / Cutover Plan (additive only — AGENTS.md)

| Step | Action | Data impact |
|---|---|---|
| 0 | `mysqldump` before any schema work (AGENTS.md) | none |
| 1 | Add the two migrations (§2/§3) via `php artisan make:migration` | none |
| 2 | `php artisan migrate` on the local copy (sentinel row present → dump is skipped, only the two tables are created) | +2 empty tables |
| 3 | `php artisan schema:dump`; **remove the `__legacy_v1_baseline_schema__` sentinel from the dump before committing** (AGENTS.md) | none (baseline regenerated) |
| 4 | Ship with `config/authorization.php` `enforcement => false` for all five pilots | behavior byte-identical |
| 5 | Per page (owner-approved sequence, §13): admin grants action rows + scope rows/all-marker to current holders → audit → flip `enforcement => true` → record in `IMPLEMENTATION_LOG` | explicit, audited grants only; no backfill UPDATE |
| 6 | `'*'` users: no rows needed, behavior unchanged | none |
| 7 | Non-pilot pages: untouched | none |

No `migrate:fresh`/`db:wipe`/ALTER/DROP of any existing table; `tbl_permissions`,
`tbl_program_permissions`, `tbl_clients`, `tbl_users`, etc. stay byte-identical
(P11 §22). The local `main_system` copy and production diverge only by the two
new (initially empty) tables.

#### 19. Rollback

- **Behavior rollback (instant):** set `config/authorization.php` `enforcement
  => false` for any/all pages → `canAccessAction` and the composer return to
  today's behavior immediately; the two tables remain but are **inert** (P11
  §22 rollback row).
- **Schema rollback (if ever needed):** the two additive migrations have `down()`
  that `dropIfExists` only their own tables — no existing table is touched.
  Because they are additive and empty-or-rebuildable, `down` is safe (unlike any
  legacy-table operation, which is never reversible — AGENTS.md).
- **Grants rollback:** an admin re-saves the per-user action/scope screens
  (full-replace) to remove rows; each save is audited.

#### 20. Test Plan (PHPUnit, `RefreshDatabase` → `main_system_test`; needs
`C:\xampp\mysql\bin` on PATH — AGENTS.md gotcha)

New test classes (P7 `AdministrationTest` conventions: `logInAs`,
`grantPage`, factory users, real Municipality/Barangay rows):

1. **`tests/Feature/ActionPermissionTest.php`**
   - adopted page + action row → store/edit/delete/export allowed;
   - adopted page, enforcement on, missing action row → denied (403 JSON for
     JSON posts, dashboard-redirect + `login_status=denied` for HTML);
   - non-adopted page → today's behavior (no action row needed);
   - `'*'` user bypasses every action gate without rows;
   - action row without page row → denied (case D);
   - `register.php` store requires CREATE; `scholars.update-client-id` requires
     EDIT.
2. **`tests/Feature/ScopeTest.php`**
   - feed with no municipality param returns only in-scope rows; out-of-scope
     param never widens;
   - `clients.show` out-of-scope id → denied; in-scope → ok;
   - client store with out-of-scope posted `city_municipality` → denied;
   - client update moving a record to an out-of-scope municipality → denied;
   - transaction store bound to an out-of-scope client → denied (hidden-field
     tamper);
   - household/scholar/gip scope via bound client;
   - multi-municipality user (rows A+B) sees A+B only;
   - all-marker user sees everything; marker row is not a municipality id;
   - no-scope user: empty feeds, denied detail/writes (fail closed);
   - `transactions.export` respects EXPORT + program + scope (no widening);
   - `'*'` user is scope-exempt without rows.
3. **`tests/Feature/AuthorizationAdminTest.php`**
   - action screen full-replace save writes correct rows + audits
     `MANAGE_ACTION_PERMISSIONS`;
   - scope screen full-replace save (ids / all-marker) writes correct rows +
     audits `MANAGE_SCOPE_ASSIGNMENTS`;
   - no-op saves emit no audit event; payloads contain no secrets;
   - `'*'` user managed with no rows required; screens reachable only under
     `page:manage_permissions.php`.
4. **`tests/Unit/AccessControlServiceTest.php`**
   - the §5 truth table (every case), `effectiveMunicipalityIds` (one/many/
     all/none), `canAccessRecord`, `applyMunicipalityScope` inert for
     `'*'`/all, per-request caching.
5. **Regression:** existing `AdministrationTest` (586 lines) and P1–P7 feature
   tests pass unchanged; P7 screens stay page-only; `pint` clean.

**Bind:** exact test names/locations are fixed at build; the plan above is the
minimum suite required for IMPLEMENTATION APPROVAL sign-off.

#### 21. Client Portal — Out-of-Scope Verification

Confirmed for the contract (FACT — P11 approved scope §2, P10 §13): this
implementation adds **no client accounts and no client authentication**. The
only new tables are the two authorization pivots (§2/§3); no `tbl_clients`
change; the public self-service flows (`student/*`, `unpaid-verification*`,
`grantee-search/*`, `grantee-update/*`, `grantee/*`, `qr-viewer`) stay
anonymous (v1 parity). At build, verification is a grep that no client-facing
auth, login, or account table/route is introduced and that the anonymous routes
carry no authz layer. The authorization chain (§3) applies to **authenticated
staff only**.

#### 22. Remaining Owner Decisions (bind-points for IMPLEMENTATION APPROVAL)

| # | Decision | Recommended | Status |
|---|---|---|---|
| 1 | Exact DDL for both tables (§2/§3) | as written | REQUIRES OWNER DECISION |
| 2 | All-marker representation | sentinel `municipality_id = 0` (§3) | REQUIRES OWNER DECISION (accept or pick `is_all` column) |
| 3 | Final audit strings | `MANAGE_ACTION_PERMISSIONS`, `MANAGE_SCOPE_ASSIGNMENTS` (§17) | REQUIRES OWNER DECISION |
| 4 | Cutover sequence | page-by-page: clients → household → transactions → scholars → register (§13) | REQUIRES OWNER DECISION |
| 5 | Action+scope admin ship together | same milestone, separate screens (§16) | REQUIRES OWNER DECISION |
| 6 | GIP/family-member classification | `CREATE` on `clients.php` (§11) | REQUIRES OWNER DECISION (P9 §18.2 carried) |
| 7 | Admin screens under `manage_permissions.php` page key | yes (§16) | REQUIRES OWNER DECISION |
| 8 | `can_access` deny rows | never in Phase 1 (presence = allow) (§2) | REQUIRES OWNER DECISION |

The owner may approve the contract **as a whole** or with explicit amendments;
each bind-point is individually confirmable. Architecture (§28) is **already
approved**; this contract resolves the implementation shape it authorized.

#### 23. IMPLEMENTATION APPROVAL Checklist

When the owner grants IMPLEMENTATION APPROVAL, the build will: (1) add the two
migrations (§2/§3) after `mysqldump`; (2) run `php artisan migrate` +
`schema:dump` (removing the baseline sentinel); (3) add the two models +
`User` relations; (4) extend `AccessControlService` (§5) + add the `action`
Gate (§6) + `AuthorizeAction` middleware (§7); (5) add
`App\Support\RecordMunicipality` (§8); (6) apply the §11 route middleware map;
(7) add `config/authorization.php` (enforcement off) (§13); (8) add the two
admin screens + requests + audit events (§16/§17); (9) implement the §20 test
suite; (10) run `vendor\bin\pint` + `php artisan test`; (11) update
`IMPLEMENTATION_LOG.md` and this file's build record. No step runs before
approval.

---

**HARD STOP — Pass 12 complete, contract only, no implementation.** No code,
schema, migration, route, model, controller, service, middleware, policy,
view, or test was written; `tbl_action_permissions` and
`tbl_user_municipalities` were **not** created; no existing table was altered;
v1 was not modified; the local or production database was not touched; no file
other than this one was modified (verified with `git status`). Open Decision #6
remains `APPROVED (ARCHITECTURE) — REQUIRES IMPLEMENTATION APPROVAL`. The next
step is the owner's review of the §23 checklist and the §22 bind-points;
implementation will not begin until a separate IMPLEMENTATION APPROVAL is
granted.

---

## BUILD RECORD — Pass 12 implemented 2026-08-16 (after owner approval)

All 8 approved bind-points shipped exactly per this contract. The build record
for the full implementation, including verification results (195 tests / 887
assertions green), lives in `docs/IMPLEMENTATION_LOG.md` entry **2026-08-16 —
P12 action authorization + municipality scope (implemented)**. Deviations:

- **No code deviations.** One route-syntax implementation detail: Laravel
  middleware parameters split on `,`, so the middleware is attached as
  `action:<page>,<action>` (e.g. `action:clients.php,CREATE`) — the same
  page/action semantics as the §7 `action:page:action` examples. The service
  normalizes the incoming action to the canonical uppercase catalog name, and
  page settings are read as a literal `config('authorization.pages')` array
  index (dot-notation would split the `clients.php` key on its dot).
- `tbl_action_permissions` / `tbl_user_municipalities` created additively on
  local `main_system`; committed `database/schema/mysql-schema.sql` regenerated
  (sentinel-free, migration ids 10/11).
- Both admin screens ship under the existing `page:manage_permissions.php`
  group (§16) with the §17 audit strings. `enforcement` stays `false` for all
  five pilot pages (S2 §13) — flipping it in `config/authorization.php` is the
  only cutover step.
