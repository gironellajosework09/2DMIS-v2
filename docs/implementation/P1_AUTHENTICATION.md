# P1 — Authentication, Single-Device Login, and Access Control (ACL)

> **Status:** Delivered, tested, and documented.
> **Scope of this document:** the `AuthController`, username-based auth provider,
> single-device session contract, session polling, force logout, online users,
> the `AccessControlService`, page/program Gates, `AuthorizePage` middleware,
> permission data model, and audit writes. This is the primary maintainer
> reference for **why** each piece exists and how to extend it without breaking
> the v1 parity contract.

---

## 1. Purpose

P1 gives v2 a working, v1-compatible login and a single authorization story so
that every later phase (clients, transactions, scanners, admin) can gate pages
and programs with one consistent mechanism instead of the scattered, fragile
checks v1 used.

Three distinct concerns are addressed:

1. **Authentication** — verify `username`/`password` against `tbl_users` and
   establish a session (v1 `login.php`/`logout.php`).
2. **Single-device enforcement** — one login at a time per user, enforced by a
   `session_token` stored in both the PHP session and `tbl_users` (v1
   `session.php` / `check_session.php` / `force_logout.php`).
3. **Authorization (ACL)** — page-level and program-level permissions read from
   `tbl_permissions` / `tbl_program_permissions`, plus multi-device exemptions
   (v1 `restriction.php` plus the hard-coded username checks and the implicit
   `user_id = 1` super-user).

---

## 2. Legacy v1 behavior (what we ported)

v1's security model had several independent, mutually inconsistent pieces:

- `db_connect.php` created a raw `$pdo`; every page called
  `restriction.php` to check `tbl_permissions` for the current `$_SESSION['user_id']`.
- **Implicit super-user:** checks like `if ($_SESSION['user_id'] == 1)` and
  hard-coded username comparisons (`in_array($user, ['jordi', ...])`) were
  scattered across sidebar, audit, and client pages. This meant admin powers
  were defined in code, not data.
- `session.php` implemented single-device login by comparing the session
  `session_token` against `tbl_users.session_token`, logging the user out when
  they did not match. Hard-coded admin usernames were exempted.
- `check_session.php` was polled by the front-end to detect a forced logout
  without a page reload.
- `force_logout.php` nulled the target user's `session_token`.
- `currently_logged_users.php` + `fetch_online_users.php` listed users with a
  recent `last_activity`.
- `logs.php::log_action($pdo, $user_id, $action, $target_table, $target_id,
  $old_value, $new_value)` wrote every audit row into `tbl_audit_logs`.
- Login used a plain form post against `login.php` with the `users`/`tbl_users`
  password hash; there was no CSRF protection, no throttling, and no framework.

### Deviations v2 deliberately makes (all behavioral)

| v1 | v2 |
|---|---|
| Super-admin = hard-coded `user_id == 1` / username checks | Super-admin = a `tbl_permissions` row with `page_name = '*'` (`AccessControlService::SUPER_ADMIN_PAGE`), `can_access = 1` |
| Restriction checks inline per page | One `AccessControlService` + `AuthorizePage` middleware + `page`/`program` Gates |
| Hard-coded usernames exempt from single-device | Exemption expressed as data: super-admin rows + `tbl_multi_device_exemptions` rows |
| Sessions keyed on integer user id | Framework session keyed on username (see §6) |
| Plain form POST, no CSRF | Laravel session CSRF token on all POSTs |

No `user_id = 1`, no `if ($user->username === 'jordi')`, and no inline
permission checks exist anywhere in the v2 codebase. **This is a hard
requirement** — see the never-change list (§13).

---

## 3. Laravel architecture

Flow for a typical authenticated page request:

```
Request
  → web middleware group (session, CSRF, …)
  → route middleware 'auth'            (framework Authenticate)
  → route middleware 'single-device'   (EnsureSingleDevice — ADR-002)
  → route middleware 'page:<v1_page>'  (AuthorizePage — ADR-003)
  → controller
```

Key wiring:

- `config/auth.php` uses the default Eloquent provider but **points the
  framework at `App\Models\User`**, whose `getAuthIdentifierName()` returns
  `username` (see §6). No email is involved anywhere.
- `bootstrap/app.php` registers two middleware aliases:
  `'single-device' => EnsureSingleDevice::class` and
  `'page' => AuthorizePage::class`.
- `AppServiceProvider::register()` binds `AccessControlService` as a
  **singleton** so its per-request caches live for the whole request.
- `AppServiceProvider::boot()` registers `ClientPolicy`, the `page` Gate, and
  the `program` Gate.
- All authentication-relevant routes live in `routes/web.php` (§9).

---

## 4. Services

### 4.1 `AccessControlService` (`app/Services/AccessControlService.php`)

The single authorization service (ADR-003). Replaces v1's dual ACL and the
magic super-user.

- `SUPER_ADMIN_PAGE = '*'` — the sentinel `page_name` that marks a
  super-admin permission row.
- `isSuperAdmin(User)` — true if the user has a `tbl_permissions` row with
  `page_name = '*'` and `can_access = 1`.
- `canAccessPage(User, string $pageName)` — super-admin OR an explicit
  `page_name` row with `can_access = 1`. Passing `'*'` delegates to
  `isSuperAdmin`.
- `canAccessProgram(User, string $programName)` — super-admin OR a
  `tbl_program_permissions` row.
- `isSingleDeviceExempt(User)` — super-admin OR `isMultiDeviceExempt`.
  Mirrors v1's exemption of admin usernames, but as data.
- `isMultiDeviceExempt(User)` — cached existence check against
  `tbl_multi_device_exemptions`.
- `permittedPages(User)` / `permittedPrograms(User)` — convenience lists used
  by menus and program-gated feeds.

**Caching:** the service is a singleton and lazily memoizes per-user
`Collection`s of page/program permissions and a boolean map of exemptions in
private arrays. A user whose permissions change mid-request will see the stale
view for that request only — acceptable, and avoids N+1 queries on menus.

### 4.2 `AuditService` (`app/Services/AuditService.php`)

Port of v1 `logs.php::log_action()`. One method:

```php
log(?int $userId, string $action, string $targetTable, ?int $targetId = null,
    ?string $oldValue = null, ?string $newValue = null): void
```

Writes exactly one row to `tbl_audit_logs` with the same column contract as v1
(`action`, `target_table`, `target_id`, `old_value`, `new_value`, `created_at`).
`old_value`/`new_value` are conventionally JSON strings. All phases audit
through this one service; nothing else writes to `tbl_audit_logs` directly
(this is also the seam where an audit queue / admin viewer will attach in P7).

---

## 5. Controllers

### 5.1 `AuthController` (`app/Http/Controllers/AuthController.php`)

- `showLogin()` — renders `auth/login.blade.php`.
- `login(LoginRequest)`:
  1. `Auth::attempt(['username' => ..., 'password' => ...])`.
  2. On success: generate a fresh token `bin2hex(random_bytes(32))`, persist it
     to `tbl_users.session_token`, `session()->regenerate()`, store the token in
     the session under the `session_token` key, write a `LOGIN` audit row, and
     redirect to `route('dashboard')` (or `intended()`).
  3. On failure: redirect back with a `username` error; the password is never
     echoed.
- `logout()` — nulls `tbl_users.session_token`, writes a `LOGOUT` audit row,
  `Auth::logout()`, invalidates + regenerates the session token, and redirects
  to the login page.

**Why a fresh token per login:** every login must invalidate the previous
device. Because the token lives in both session and DB, the older device's
session token will no longer match the DB and will be forced out on its next
authenticated request.

### 5.2 `SessionController` (`app/Http/Controllers/SessionController.php`)

- `status()` — port of v1 `check_session.php`. JSON polled by the front end:
  - no authenticated user → `{status: 'logged_out'}`;
  - single-device exempt → `{status: 'ok'}`;
  - session token missing or `! hash_equals(session, db)` →
    `{status: 'another_device'}`;
  - otherwise `{status: 'ok'}`.
  Uses `hash_equals` for a constant-time comparison.
- `online()` — page-gated render of `sessions/online` (v1
  `currently_logged_users.php`). The actual user list is a DataTables feed in
  the view.
- `forceLogout(Request)` — page-gated `page:force_logout.php`. Validates a
  `user_id`, nulls the target's `session_token`, writes a `FORCE_LOGOUT` audit
  row (actor = current user), and flashes `login_status`. The victim's next
  request fails the single-device check.

---

## 6. Models

| Model | Table | Notes |
|---|---|---|
| `App\Models\User` | `tbl_users` | `username` unique; `password` cast to `hashed`; `session_token`/`last_activity` plain columns; **no `updated_at`**; `getAuthIdentifierName()` returns `username`. Hidden from serialization: `password`, `session_token`. Relations: `permissions()`, `programPermissions()`, `multiDeviceExemptions()`. |
| `App\Models\Permission` | `tbl_permissions` | `user_id`, `page_name`, `can_access` (boolean cast). Unique `(user_id, page_name)` — one row per page per user. |
| `App\Models\ProgramPermission` | `tbl_program_permissions` | `user_id`, `program_name`. Unique `(user_id, program_name)`. No `can_access` column — presence *is* the grant. |
| `App\Models\MultiDeviceExemption` | `tbl_multi_device_exemptions` | `user_id` (unique). Presence *is* the exemption. |

The framework's own `users` table (email-based, created by
`0001_01_01_000000_create_users_table`) exists in the baseline schema but is
**not used by the application auth flow** — it is present only because Laravel's
migrations create it and the committed baseline must stay complete. Do not
confuse it with `tbl_users`. The `tbl_users` model overrides the auth identifier
so the framework never touches the framework `users` table for login.

---

## 7. Policies, middleware, and form requests

- **`ClientPolicy`** (`app/Policies/ClientPolicy.php`) — `delete()` requires
  `canAccessPage($user, 'clients.php')`. Registered via `Gate::policy` in
  `AppServiceProvider::boot()`. Used by `ClientController::destroy()` through
  `$this->authorize('delete', $client)`. This is the v2 way of making the v1
  delete guard an explicit, single-decision policy.
- **`EnsureSingleDevice`** middleware (`app/Http/Middleware/EnsureSingleDevice.php`)
  — port of v1 `session.php`. If the user is not exempt, compares the session
  `session_token` with `tbl_users.session_token` via `hash_equals`; on mismatch
  it logs out, invalidates + regenerates the session, and redirects to login
  with `login_status = 'expired'`. Always refreshes `last_activity` on the
  authenticated user (even for exempt users) and passes through for guests.
- **`AuthorizePage`** middleware (`app/Http/Middleware/AuthorizePage.php`) —
  usage `->middleware('page:clients.php')`. Resolves the v1 page key against
  `tbl_permissions.page_name`. Denied + `expectsJson()` → 403; denied HTML →
  redirect to dashboard with `login_status = 'denied'`.
- **`LoginRequest`** (`app/Http/Requests/LoginRequest.php`) — `username`
  required string, `password` required string. No email.

---

## 8. Routes

All in `routes/web.php` (P1 portion):

```
GET  /login              AuthController@showLogin       name=login         (guest)
POST /login              AuthController@login           name=login.attempt (guest)
POST /logout             AuthController@logout          name=logout        (auth)
GET  /session/status     SessionController@status       name=session.status
GET  /session/online     SessionController@online       name=session.online (auth+single-device, page:currently_logged_users.php)
POST /session/force-logout SessionController@forceLogout name=session.force-logout (auth+single-device, page:force_logout.php)
```

The dashboard `/` is the authenticated entry point inside the
`['auth', 'single-device']` group. `/session/status` is intentionally **outside**
the `single-device` middleware so a user who lost the token race can still get
a truthful `another_device` answer.

---

## 9. DB tables involved

| Table | Role |
|---|---|
| `tbl_users` | `username` (unique), bcrypt `password`, `session_token`, `last_activity`, `created_at`. No email. |
| `tbl_permissions` | Page grants. `page_name` matches v1 page file names exactly (e.g. `clients.php`, `scanner_ceap.php`) so the ACL contract carries over untouched. `'*'` = super-admin. |
| `tbl_program_permissions` | Program grants; values match the transaction `program` strings. |
| `tbl_multi_device_exemptions` | Users allowed multiple simultaneous sessions. |
| `tbl_audit_logs` | Append-only audit. `action` values in use: `LOGIN`, `LOGOUT`, `FORCE_LOGOUT` (plus per-module actions from later phases). Indexed on `(target_table, created_at)` and `user_id`. |

All are legacy tables with **no Laravel-managed `updated_at`/`created_at`
conventions** — models set `$timestamps = false` (except `tbl_audit_logs`,
which has `created_at` written explicitly by `AuditService`).

---

## 10. Business rules

1. A user is identified by `username` — never email.
2. `password` is always stored as a bcrypt hash (`hashed` cast handles both
   seeding and login verification).
3. On every login a fresh 256-bit random `session_token` is generated and
   stored in **both** the session and `tbl_users`. Any older device is forced
   out at its next request.
4. A user who is exempt (super-admin or exemption row) can hold multiple
   concurrent sessions and skips the token check.
5. Forced logout is a data operation: nulling `session_token`. It applies to
   all of the target's devices immediately.
6. Super-admin is a **data row**, not a code path: `page_name = '*'`.
7. Permission checks are always routed through `AccessControlService`; pages
   never inspect `session_token`/usernames/ids directly.
8. Audit rows are written for `LOGIN`, `LOGOUT`, and `FORCE_LOGOUT` by the
   actor who performed the action.

---

## 11. Validation

- `LoginRequest`: `username` and `password` required strings. No email rule, no
  uniqueness — it is a login, not a registration.
- `SessionController::forceLogout`: `user_id` required integer (target exists
  check happens in the controller and flashes `User not found.`).

---

## 12. Security notes

- **Constant-time comparison:** `hash_equals` is used in `EnsureSingleDevice`
  and `SessionController::status`.
- **Session fixation:** `session()->regenerate()` on login; `invalidate()` +
  `regenerateToken()` on logout and on single-device ejection.
- **CSRF:** all POST routes are behind the framework's CSRF middleware;
  `auth/login.blade.php` uses the `@csrf` directive.
- **Credential secrecy:** the login failure message never distinguishes
  username vs password; password never echoed back.
- **Token entropy:** `random_bytes(32)` → 64 hex chars; stored in DB and
  session only, never serialized to the client.
- **Hidden fields:** `password` and `session_token` are in `$hidden` on `User`,
  so any JSON/serialization leak cannot expose them.
- **No secrets in code:** the seeder is local-only; production permission rows
  come from the carried-over data (see §14).
- **Known v1-limitation kept:** there is no login throttling / rate limiting.
  This is a documented future improvement (§15) — do not silently add it in a
  way that changes login behavior without an ADR.

---

## 13. Never-change list

- Never reintroduce `user_id == 1` or username string checks anywhere.
- Never route an access decision around `AccessControlService` (no ad-hoc
  `DB::table('tbl_permissions')` checks in controllers/views).
- Never use the framework `users` table for login, and never add an email
  column to `tbl_users` without an ADR.
- Never drop/alter the `session_token`, `last_activity`, `username`, or
  `password` columns of `tbl_users`.
- Never relax the `page_name` semantics of `tbl_permissions` (must equal v1
  page file names), and never change `SUPER_ADMIN_PAGE` without an ADR.
- Never write to `tbl_audit_logs` from anywhere except `AuditService`.
- Never remove `session()->regenerate()`/`invalidate()` flows on
  login/logout/ejection.
- Never move `/session/status` under the `single-device` middleware (it must be
  able to report the truth to a doomed session).

---

## 14. Seeding and deployment

- `database/seeders/AccessControlSeeder.php` grants the **local-only** account
  `jordi` a `SUPER_ADMIN_PAGE` row using `updateOrCreate`. It is intentionally
  documented as **never run in production**: production carries v1 permission
  rows over with the data, and super-admin is simply a `'*'` row among them.
- For a fresh local test database, the seed gives a working super-admin so all
  pages are reachable. Everything still flows through the ACL service.

---

## 15. Common mistakes (observed or likely)

1. **Checking `username` or `user_id` in a blade/controller** — always use
   `app(AccessControlService::class)->canAccessPage(...)`, `Gate('page', ...)`,
   or `Gate('program', ...)`.
2. **Forgetting the session token on logout/force-logout** — logout and forced
   logout must null `session_token`; otherwise the "logged out" user can still
   be re-detected as active.
3. **Regenerating the session without re-storing the token** — order matters:
   regenerate first, then `put('session_token', ...)`.
4. **Trying to compare tokens with `===`** — use `hash_equals`.
5. **Adding a second auth provider/table for users** — there is one user
   table and one provider; adding another fragments the ACL.
6. **Putting `/session/status` behind `single-device`** — breaks the "another
   device" UX because the middleware would bounce the request before the poll
   can answer.

---

## 16. Future improvements

- Login throttling (rate limiting) — v1 has none; needs an ADR to change
  behavior.
- Password change / reset flow — v1 has no self-service reset; the legacy
  `password_resets` table has non-standard columns and must not be used as a
  Laravel reset table without a migration + ADR.
- P7 admin screens will build user CRUD and permission management on top of
  `tbl_permissions`/`tbl_program_permissions`/`tbl_multi_device_exemptions`
  through the same service (never inline).
- The audit writer could move to a queue in a later hardening pass; the
  `AuditService` method signature already matches v1's contract so callers do
  not change.

---

## 17. Blueprint / ADR references

- `docs/ENGINEERING_BLUEPRINT.md` §2 rows for `session.php`, `restriction.php`,
  `logs.php`, `check_session.php`, `login.php`/`logout.php`,
  `force_logout.php`, `currently_logged_users.php`; §3 table rows for
  `tbl_users`, `tbl_permissions`, `tbl_program_permissions`,
  `tbl_multi_device_exemptions`, `tbl_audit_logs`.
- `docs/ARCHITECTURE_DECISION.md` — **ADR-002** (single-device session token
  contract), **ADR-003** (single ACL service), ADR-009 (audit log contract),
  ADR-006 (username-based provider).
- `docs/IMPLEMENTATION_LOG.md` — the dated P1 entry with test results.
- `docs/REQUIREMENTS_ANALYSIS.md` FRs on authentication and authorization.
