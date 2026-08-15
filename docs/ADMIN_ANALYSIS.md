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
