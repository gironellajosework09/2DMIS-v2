# v2 — Implementation Log

> **Phase:** Development (P0 → P8).
> This is the **running record of everything actually built** in the v2 Laravel
> project. The planning docs (`docs/README.md` index) describe *what* v2 must be;
> this log records *what has been done*, file by file, with verification and any
> deviations from the blueprint. **Append to this document on every update.**
>
> Guardrails still apply: `main_system` is byte-identical to production and is
> never wiped or altered non-additively; v1 code at `C:\xampp\htdocs\system` is
> read-only.

**Related:** `ENGINEERING_BLUEPRINT.md` (roadmap P0–P8, file matrix §8),
`ARCHITECTURE_DECISION.md` (ADR-001…010), `MIGRATION_PLANNING.md` (§6 gates).

---

## Phase status

| Phase | Deliverable | Status |
|---|---|---|
| P0 — Foundations | Bootstrap, baseline schema, assets, CI, storage | **Done** |
| Schema fixes | 6 additive migrations fixing v1 abnormalities | **Done** |
| P1 — Auth + RBAC | Username login, single-device, ACL, audit, shell | **Done** |
| P2 — Clients + households | Client CRUD, households + family members, profile, duplicates, photos, student self-service | **Done** 2026-08-05 (full P2 scope incl. delete_client, duplicates, photo/student — see entries below); 2026-08-06 slide-over details panel added |
| P3 — Transactions + reports | Transaction CRUD, filters, inline edit, CSV exports | **Done** 2026-08-05 (all 9 v1 transaction files ported; per-program gating) |
| P4 — Scanner engine | One `ScanService` (8 modes) + program config + shared view + routes | **Done** 2026-08-07 (all 14 v1 scanners as config; 14 tests green — see entry below) |
| P5 — Payout + unpaid | | **Done** 2026-08-07 (3 payout lists + unpaid verification admin/self-service + CSV export; 15 tests green — see entry below) |
| P6 — Scholars / GIP | | Scaffold + scholar CRUD v1-parity done (Phase 2 cleanup 2026-08-07); GIP, reports, QR, grantee updates pending |
| P7 — Administration | | Not started |
| P8 — Hardening + cutover | | Not started |

---

## How to use this log

1. Every merged update adds one dated entry under **Changelog** describing the
   change, the files touched, and how it was verified.
2. Status-bearing docs stay in sync: `docs/README.md` (phase table),
   `ENGINEERING_BLUEPRINT.md` §8 (file matrix), `ARCHITECTURE_DECISION.md`
   (implementation notes), `MIGRATION_PLAN.md` §4 and `MIGRATION_PLANNING.md`
   §6 (gate status).
3. If implementation deviates from the blueprint, record it in the entry and in
   **Deviations from the blueprint** below — the blueprint itself is a plan and
   is not silently rewritten.

---

## Changelog

### 2026-08-05 — Sample Data Seeder for Local Development Environment

- **Created [`SampleDataSeeder.php`](file:///c:/xampp/htdocs/2DMIS-v2/database/seeders/SampleDataSeeder.php)** to safely populate the local `main_system` development database with realistic dummy records for local browser testing.
- **Seeded Records:**
  - 5 Households (`HH-2026-*` codes)
  - 25 Clients (Heads of Household, Spouses, Children across Candon City barangays, mapped with ages, categories, civil status, contact numbers, and unique voter IDs)
  - 56 Family Member bidirectional relationship pairs (`PARENT`, `CHILD`, `SPOUSE`)
  - 20 Assistance Transactions across multiple programs (`AICS`, `MAIP`, `TUPAD`, `GIP`, `TODA`, `CEAP`, `OTEA`, `OTCES`, `CEDSSG`) with various statuses (`PAID`, `APPROVED`) and amounts.
- **Verification:** Ran `php artisan db:seed --class=SampleDataSeeder` against `main_system` (25 clients, 6 households, 56 family members, 20 transactions created) and confirmed all 59 PHPUnit tests remain 100% green (`php artisan test`).

### 2026-08-05 — P0 Foundations + schema fixes + P1 Auth/RBAC (initial delivery)

Work delivered in this session (project created from a fresh Laravel 12
scaffold against the frozen `main_system` schema):

#### P0 — Foundations

- **Laravel 12 scaffold** on PHP 8.2.12 (XAMPP CLI; production targets PHP 8.3+),
  default infra migrations (users/password_reset_tokens/sessions/cache/jobs)
  committed as part of the baseline.
- **`.env`-based config** — DB credentials via `.env` (gitignored; `.env.example`
  committed). Session/cache/queue on `file`/`file`/`sync`.
- **Baseline schema** `database/schema/mysql-schema.sql` — generated with
  `php artisan schema:dump` (672 lines, 40 tables incl. Laravel infra).
  `php artisan migrate` auto-loads it on a DB with no recorded migrations.
- **Assets copied from v1** (read-only source): `seal_logo.png`, `sounds/*.mp3`
  → `public/`; `favicon.ico` at web root. Uploads wiring via `storage:link`
  (`storage/app/public/uploads/...`).
- **CI** `.github/workflows/ci.yml` — PHP 8.3 + MySQL 8.0 service; `migrate`
  loads the baseline into `main_system`; creates a dedicated `main_system_test`
  database; runs `pint --test` then `php artisan test`.
- **Fresh-DB proof** — built `main_system_fresh_test` from the dump via a plain
  `migrate` → 40 tables, all constraints present; dropped afterwards.

#### Schema fixes (6 additive migrations, applied to the local DB)

Data-preservation setup used locally (never against a real prod copy):
`migrate:install` → insert the deploy-only sentinel
`__legacy_v1_baseline_schema__` into `migrations` → `migrate` runs only the
additive migrations. The sentinel is a deploy-time marker only; it was removed
from the committed baseline dump.

| Migration | Change | Guardrail |
|---|---|---|
| `2026_08_05_000001_drop_redundant_indexes.php` | Drops duplicate indexes: `tbl_household.household_id_2`, `tbl_clients.idx_full_name_clients`, `tbl_transactions.{t_prg,t_cid,t_da,t_pd,t_dp}`, `tbl_payout_scans`/`_2` `{idx_transaction_id,ps_tid,ps_sb,ps_sa}`, `tbl_users.u_un` | `down()` restores |
| `2026_08_05_000002_add_primary_keys_to_legacy_tables.php` | Auto-increment PKs on `gender`, `tbl_absent`, `tbl_kababaihan`, `tbl_details`, `temp_details` (reuse existing `id`) | Warns + skips if existing ids dirty |
| `2026_08_05_000003_make_clients_email_nullable.php` | `tbl_clients.email` → VARCHAR(255) NULL | `down()` warns if NULLs exist |
| `2026_08_05_000004_add_unique_permission_constraints.php` | UNIQUE `(user_id,page_name)` on `tbl_permissions`, `(user_id,program_name)` on `tbl_program_permissions` | Skips + warns on duplicate groups |
| `2026_08_05_000005_unify_table_collations.php` | 5 tables from `utf8mb4_general_ci` → `utf8mb4_unicode_ci` (fixes v1 join breaks) | — |
| `2026_08_05_000006_add_payout_scan_foreign_keys.php` | FKs `fk_tbl_payout_scans2_transaction/user`, `fk_tbl_payout_scans_unpaid_transaction/user` | Skips if orphans exist |

Verification on the local `main_system` copy: counts intact before/after
(munis 23, brgys 471, clients 1, users 1), all 6 fixes confirmed, second
`migrate` → "Nothing to migrate.", `.env` restored to `DB_DATABASE=main_system`.

#### P1 — Auth + RBAC

v1 contract mapped and ported (see `ARCHITECTURE_DECISION.md` ADR-002/003).

| v1 file | v2 target | Notes |
|---|---|---|
| `login.php` / `logout.php` | `AuthController` + `LoginRequest` + routes `login`/`login.attempt`/`logout` | Username + bcrypt via Laravel auth; audits `LOGIN`/`LOGOUT` |
| `session.php` (token contract) | `EnsureSingleDevice` middleware (`single-device` alias) | Session `session_token` vs `tbl_users.session_token` via `hash_equals`; mismatch → logout + redirect `login?login_status=expired`; refreshes `last_activity`; skips multi-device-exempt users |
| `restriction.php` + username checks | `AccessControlService` + `AuthorizePage` (`page:<name>`) + `page`/`program` Gates | Single ACL; super-admin is a data row (`page_name='*'`), never a username or `user_id=1` |
| `logs.php::log_action()` | `AuditService::log()` → `tbl_audit_logs` | v1 field contract (`user_id/action/target_table/target_id/old_value/new_value/created_at`) |
| `check_session.php` | `session/status` JSON route (`session.status`) | `logged_out`/`another_device`/`ok`; polled every 2 s from the layout |
| `force_logout.php` | `session/force-logout` POST (page-gated) | Nulls `session_token` + audit `FORCE_LOGOUT` |
| `currently_logged_users.php` | `session/online` page (page-gated) | Server-rendered table (see deviations) |
| `fetch_online_users.php` | — | Deferred (see deviations) |
| `navbar.php` / `sidebar.php` | Blade partials | Role-driven menu via ACL; hidden for no permission |
| `index.php` | `DashboardController` → `dashboard` route | Auth + single-device required |

**Models** (all `$table='tbl_*'`, `$timestamps=false`): `User` (username
identifier via `getAuthIdentifierName()`, `session_token` hidden, relations),
`Permission`, `ProgramPermission`, `MultiDeviceExemption`.

**Seeding:** `AccessControlSeeder` (idempotent, via `DatabaseSeeder`) grants the
local `jordi` account full access with a `tbl_permissions` row
(`page_name='*'`, `can_access=1`). Production carries its existing v1
permission rows unchanged at cutover.

**Middleware wiring:** aliases `single-device` and `page` registered in
`bootstrap/app.php`; `AccessControlService` registered as a singleton;
Gates `page`/`program` defined in `AppServiceProvider`.

**Views:** `layouts/app.blade.php` (navbar/sidebar/content + 2 s session poll),
`partials/navbar.blade.php`, `partials/sidebar.blade.php`,
`auth/login.blade.php` (seal logo, `login_status` flash + validation errors),
`dashboard.blade.php`, `sessions/online.blade.php` (self-hidden force-logout).

**Tests** — 14 tests, 36 assertions, green, on a dedicated `main_system_test`
DB (never the local copy); `phpunit.xml` forces `DB_DATABASE=main_system_test`:
- `tests/Feature/AuthTest.php` (6): login page accessible; login by username;
  wrong password fails; dashboard requires login; logout clears session+token;
  second-device login invalidates first device.
- `tests/Feature/AccessControlTest.php` (7): super-admin gated access; page
  permission access; no-permission blocked (`denied`); super-admin
  single-device exempt; program gate allow/deny; super-admin bypasses program
  gate.
- `tests/Feature/ExampleTest.php` (1): `/` redirects to login (guest).

**Verification ledger (2026-08-05):**
- `php artisan test` → 14 passed (36 assertions). NOTE: RefreshDatabase loads
  the schema dump through the `mysql` client, so `C:\xampp\mysql\bin` must be on
  PATH (`$env:PATH = "C:\xampp\mysql\bin;" + $env:PATH`) or every
  RefreshDatabase test fails with `ProcessFailedException` — documented in
  `README.md` and `AGENTS.md` Gotchas.
- `vendor\bin\pint` (app, tests, database, routes, bootstrap, resources) → passed;
  `pint --test` → passed.
- `php artisan migrate` → "Nothing to migrate." (safe no-op on the local copy).
- Live smoke test: `/login` 200; guest `/` → 302 to `/login`.
- Data intact: 23 municipalities / 471 barangays / 1 client / 1 user.

---

## Deviations from the blueprint

| Blueprint (§8 or ADR) | Planned | Actually built | Reason |
|---|---|---|---|
| File #16 (`sidebar.css/js`) + ADR-006 superseded direction | Tailwind + Vite asset build | **Bootstrap 5 (CDN) + inline CSS** in the layout; no Vite/Tailwind build, no `npm install` | Zero Node toolchain on the machine; matches staff-familiar Bootstrap (the original ADR-006 decision). Revisit Tailwind if a build step is wanted later |
| File #12 (`fetch_online_users.php`) | DataTables JSON route | **Deferred** — `session/online` renders a server-rendered table | Not needed for P1 parity; add the JSON feed when DataTables is adopted (P3+) |
| File #10 (`force_logout.php`) | `AdminController@forceLogout` | `SessionController@forceLogout` | No admin controller exists yet; route/page gate `page:force_logout.php` enforces the v1 permission key |
| ADR-008 | Audit via framework events/observers | `AuditService` called explicitly from controllers | No model mutations in P1 to observe; observers planned once domain writes exist (P2) |
| ADR-010 (P2) / blueprint AD-10 | Right-side sliding details panel for client rows | Profile extracted into a shared partial (`clients/_details.blade.php`); the dedicated page stays as a deep link, and the client list now opens the details in a **right-side slide-over panel** (Bootstrap Offcanvas) on row click | Panel is primary as planned (AD-10); page kept for deep links/print. See the P2 UI entry below |
| P2 family members | View-level inverse mapping | **Service-level inverse mapping** in `FamilyMemberService` | Keeps view dumb; logic covered by tests |
| P3 CSV exports | v1 writes temp files then streams | **`streamDownload`** with UTF-8 BOM | Framework-native; byte-comparable contract kept (P3/P6 parity) |
| P3 `all_transaction_edit.php` / `all_transaction_delete.php` | Separate list-edit/list-delete pages | **Inline row edit/save/cancel + row delete** on the index | Single list surface, matches DataTables UX |
| P2 duplicates (`preview_duplicates.php` / `fetch_duplicates.php` / `delete_duplicates.php`) | v1 hard-coded `super_admin`/`jordi` username gate; single un-audited `DELETE … IN (…)` | **Page-gated on `page:clients.php` via the ACL; per-row audited `DELETE_CLIENT` deletes inside a transaction** | ADR-003 forbids username checks; per-row delete keeps the audit trail and lets one guarded row (with transactions) fail without aborting the batch |
| P2 `delete_client.php` | Bare `DELETE` (crashes on clients with transactions; leaves orphan family links) | **`ClientService::destroy`** — explicit transaction-guard error, two-direction family-link cleanup, `DELETE_CLIENT` audit | Mirrors the DB constraints while surfacing a clean error to staff |
| P2 photo + student (`save_client_photo.php`, `student_photo_upload.php`, `student_update_photo.php`, `student_verify.php`) | v1 trusts client base64 + file extension blindly | **`PhotoService` validates JPEG magic bytes + extensions; camera input validated server-side** | Security hardening without changing the stored-data contract (filename only) |

---

## File inventory (P0 + P1)

**Created:** `AGENTS.md`, `docs/*`, `public/seal_logo.png`, `public/favicon.ico`,
`public/sounds/*.mp3`, `database/schema/mysql-schema.sql`,
6× `database/migrations/2026_08_05_*.php`, `database/seeders/AccessControlSeeder.php`,
`app/Http/Controllers/{AuthController,DashboardController,SessionController}.php`,
`app/Http/Middleware/{EnsureSingleDevice,AuthorizePage}.php`,
`app/Http/Requests/LoginRequest.php`,
`app/Models/{Permission,ProgramPermission,MultiDeviceExemption}.php`,
`app/Services/{AccessControlService,AuditService}.php`,
`resources/views/{auth,layouts,partials,sessions}/*.blade.php`,
`resources/views/dashboard.blade.php`, `tests/Feature/{AuthTest,AccessControlTest}.php`,
`.github/workflows/ci.yml`.

**Modified:** `.env.example`, `README.md`, `app/Models/User.php`,
`app/Providers/AppServiceProvider.php`, `bootstrap/app.php`,
`bootstrap/providers.php`, `config/auth.php`, `database/factories/UserFactory.php`,
`database/seeders/DatabaseSeeder.php`, `phpunit.xml`, `routes/web.php`,
`tests/Feature/ExampleTest.php`.

**Explicitly not done (later phases):** DataTables JSON feeds; Tailwind/Vite
build; `verify_mobile.php` (P2); household CRUD + slide-over panel (rest of
P2); all transaction/scanner/payout/scholar/admin modules; login throttling
(ADR-007, P8 hardening); `password_reset_tokens` framework flow (disabled —
v1 has no email reset).

### File inventory (P2 clients — added 2026-08-05)

**Created:** `app/Models/{Client,ClientAffOrg,Municipality,Barangay,Household}.php`,
`app/Services/ClientService.php`, `app/Http/Requests/ClientRequest.php`,
`app/Http/Controllers/{ClientController,GeographyController}.php`,
`resources/views/clients/{index,_form,create,edit}.blade.php`,
`tests/Feature/ClientTest.php`.

### 2026-08-05 — P2 Clients (registry, add/edit, server-side list, page gate)

First delivery of the P2 milestone: the client registry (v1 `clients.php`,
`fetch_clients.php`, `add_client.php`, `edit_client.php`, `get_barangays.php`).
Household CRUD and the slide-over panel remain for the rest of P2.

#### What was built

- **Models** (`$table='tbl_*'`, `$timestamps=false`): `Client`
  (`tbl_clients`, relations `municipality`, `barangayInfo`, `household`,
  `affOrgs`), `ClientAffOrg`, `Municipality`, `Barangay`, `Household`.
- **`ClientService`** — the single write/derivation path (v1 duplicated this
  logic across add/edit; v2 unifies it):
  - `deriveFullName` → `"LASTNAME, FIRSTNAME MIDDLENAME EXTENSION"`, skipping
    the middlename when blank or `N/A`.
  - `deriveMatchName` → uppercase concatenation of last+first+middle with all
    whitespace removed (`preg_replace('/\s+/', '')`), matching v1's edit path
    and applied consistently on add too (A6 fix; v1's add path left spaces).
  - `deriveAge` (DateTimeImmutable diff) and `deriveCategory`
    (MINOR/YOUTH/ADULT/SENIOR at 17/29/59) — always derived server-side;
    client-supplied `age`/`category` are ignored.
  - `attributes()` normalizes the whole write payload: names uppercased,
    `region='Region I'`/`province='Ilocos Sur'`, empty `monthly_income` → null,
    `ip_group` persisted only when `ip='YES'`, age/category derived, empty
    `aff_org` preserved (column is NOT NULL, no default).
  - `create()`/`update()` run in a `DB::transaction`, audit
    `ADD_CLIENT`/`EDIT_CLIENT` with `old_value`/`new_value` JSON.
  - `syncAffiliations()` — delete-then-insert of `tbl_client_aff_orgs` rows
    (deduped, uppercased; max 5 enforced client-side).
- **`ClientRequest`** — required: lastname/firstname/city_municipality/barangay/
  birthdate/sex/civil_status/pwd/ip; `exists:tbl_municipalities,id` /
  `tbl_barangays,id` / `tbl_household,id`; `in:MALE,FEMALE` and
  `SINGLE,MARRIED,WIDOWED` and `YES,NO`; `ip_group` `required_if:ip,YES`;
  `aff_org` array max 5.
- **`ClientController`** — `index` (view + municipalities), `create`, `store`,
  `edit` (client + its aff-org names + municipalities + barangays scoped to the
  client's municipality), `update`, and `data()`: a server-side port of
  `fetch_clients.php` (POST draw/start/length; municipality + barangay filters;
  word-split AND search across lastname/firstname/middlename/extension/full_name/
  mobile_no/voter_id/precinct_no/occupation/m.name/b.name; smart rank ordering
  when searching — "prefix first, then contains"; 19-column sortable map;
  `htmlspecialchars`-escaped rows; actions link to `clients.edit`).
- **`GeographyController::barangays`** — port of `get_barangays.php`
  (`GET geography/barangays?municipality_id=` → `[{id,name}]`, validated).
- **Routes** (in the `auth` + `single-device` group): `geography/barangays`
  (no page gate — v1 same), plus a `page:clients.php` group → named `clients.*`
  (`index`, `create`, `store`, `edit`, `update`, `data`).
- **Views:** `clients/index.blade.php` (Bootstrap + DataTables 1.13.6 CDN,
  21-column server-side table, municipality/barangay filter selects +
  Filter/Reset, municipality change → `geography.barangays` fetch fills the
  barangay select); `clients/_form.blade.php` shared add/edit form (uppercase
  name inputs, municipality→barangay cascade, birthdate→age→category live
  calc, IP=YES reveals `ip_group`, aff-org selects + "Add another" capped at 5,
  readonly Region I/Ilocos Sur, Cancel/Save); `clients/create.blade.php` and
  `clients/edit.blade.php` wrappers. Sidebar link gated on
  `canAccessPage($user, 'clients.php')`, active on `routeIs('clients.*')`.

#### Verification

- `php artisan test` → **21 passed (91 assertions)**. New `ClientTest` (7):
  page gate (denied without permission), pages load (index/create/edit), create
  with derived fields (full_name/match_name/age/category/region/province/
  occupation + aff-org rows + ADD_CLIENT audit), validation errors, edit +
  EDIT_CLIENT audit + aff-org replacement + consistent match_name, data feed
  (rows + municipality filter + search), geography barangays JSON + invalid id.
- `vendor\bin\pint --test` → passed.
- `route:list` → 14 routes incl. `clients.*` and `geography.barangays`.
- Fixed during verification: `clients/index.blade.php` originally used
  `@section('scripts'/'styles')`, but the layout only renders `@stack(...)` —
  converted both blocks to `@push(...)/@endpush` so the DataTables CSS/JS
  actually load.
- Local `main_system` untouched: clients 1, municipalities 23, barangays 471,
  users 1, client_aff_orgs 0, audit_logs 0.

**Not done yet (rest of P2):** household CRUD, slide-over client panel,
`verify_mobile.php`, transaction-facing list refinements. **Not done (later):**
DataTables JSON adoption for online-users; `fetch_online_users.php`.

---

### 2026-08-05 — P2 households, profile/verify-mobile, family members (rest of P2)

Completes the P2 milestone with the remaining v1 household/profile/family
surfaces. See the P2 clients entry above for the registry; this entry covers
households, the client profile page, mobile verification, and family members.

#### What was built

- **`HouseholdService`** — `code()` generates `VIG-00001`-style codes
  (prefix + zero-padded sequence via `tbl_household.id`), `create()` (audit
  `ADD_HOUSEHOLD`), `destroy()` (audit `DELETE_HOUSEHOLD` + detaches
  `tbl_clients.household_id` to null so no FK breakage), `search()`.
- **`HouseholdController`** — `index` (server-side DataTables), `create`/
  `store`, `show` (members + client count), `destroy` (CSRF-guarded fetch —
  fixed a latent P2 bug where the per-row delete fetch sent no `X-CSRF-TOKEN`
  and would 419 in a real browser), `data` (feed), `search`,
  `clientOptions`, `searchClientsForHousehold` (JSON helpers).
- **`FamilyMemberService`** + **`FamilyMemberController`** — relationship
  labels, inverse mapping (a person listed as child sees the parent as their
  own parent), SIBLING fan-out across `family_id`, audits
  `ADD_FAMILY_MEMBER`/`DELETE_FAMILY_MEMBER`.
- **`ClientController@show`** — the client profile page (`clients.show`),
  replacing the blueprint's slide-over panel (see deviations). Shows derived
  fields, aff-orgs, household, family members, and action buttons (Edit,
  Delete, + Add Transaction when the user can access `all_transactions.php`).
- **`ClientController@verifyMobile`** — port of v1 `verify_mobile.php`
  (`POST clients/verify-mobile` → `{success:true}` on match, `success:false`
  on mismatch, `skipped:true` when the client has no mobile number).
- **Models** added: `FamilyMember` (`tbl_family_members`, unique
  `(client_id, relative_id)`), `ClientHousehold` lookup.
- **Views:** `households/{index,create,show}.blade.php`,
  `clients/show.blade.php` (profile), `family_members/create.blade.php`.
- **Routes:** `page:household.php` group → `households.*`
  (`index`, `create`, `store`, `show`, `destroy`, `data`, `search`,
  `client-options`, `search-clients`); `page:clients.php` → `clients.show`
  and `clients.verify-mobile`.
- **Sidebar:** Household link gated on `canAccessPage($user, 'household.php')`.

#### Verification

- `php artisan test` → **28 passed (129 assertions)** (P2 suite + P1).
- `vendor\bin\pint --test` → passed.
- Latent bug fixed: per-row household delete now sends `X-CSRF-TOKEN`.

**Deviations:** slide-over client panel → dedicated profile page
(`clients/show.blade.php`) — simpler to build and test with the current
Bootstrap stack; family-member inverse mapping handled in the service, not the
view.

---

### 2026-08-05 — P3 Transactions (CRUD, filters, inline edit, CSV exports)

Ports all 9 v1 transaction files (`all_transactions.php`, `fetch_transactions.php`,
`add_transaction.php`, `edit_transaction.php`, `view_transaction.php`,
`delete_transaction.php`, `update_transaction.php`, `all_transaction_edit.php`,
`all_transaction_delete.php`, plus `transaction_table.php` as the list partial).

#### What was built

- **`TransactionService`** — `PROGRAMS` (17: AICS, AKAP, MAIP, TUPAD, CEDSSG,
  CEAP, CEAP_NEW, CEDSSG_NEW, OTEA, OTCES, COFFEE GROWERS, PUSO TI KABABAIHAN,
  PUSO TI AGTUTUBO, PUSO TI MANNALON, TESDA, GIP, TODA), `TYPES`, `STATUSES`
  (`PENDING PAYOUT`, `PAID`); `create`/`update`/`destroy` (client_id,
  patient_name, date_applied, type, remarks, comments, suggested_amount,
  status, amount_paid, payout_date, date_paid, gwa, units); audits
  `ADD_TRANSACTION`/`EDIT_TRANSACTION`/`DELETE_TRANSACTION` with old/new JSON;
  `resolvePatientName()` (self/custom/existing with v1 name format
  `lastname, firstname middle`); TUPAD stores nulls for comments/payout_date/
  gwa/units.
- **`TransactionController`** — `index`, `create` (+ optional `{client}`
  prefill), `store`, `show`, `edit`, `update`, `destroy`,
  `inlineUpdate` (comma-stripping normalize + date parse mirroring v1
  `update_transaction.php`, JSON `{success}`), `data` (server-side DataTables
  feed, 21-col map default-ordered by client name, program restriction +
  forbidden-filter-empty, status/municipality/barangay/date filters, action
  cells `Edit/Save/Cancel/Delete` for inline row editing), `searchClients`
  (`transactions.clients-search`, 2-char min, page-gated), `export`
  (streamed CSV with UTF-8 BOM; `export_mode` standard/custom/custom2/gip).
- **Views:** `transactions/{index,create,edit,show}.blade.php` — index =
  DataTables + program/status/municipality/barangay/date_applied/date_paid
  filters + export dropdown (filters wired via `URLSearchParams`) + inline
  row edit; create = beneficiary radio (self/custom/existing) + hidden
  `existing_client_id` + TUPAD field-disable via JS; edit = same with prefill;
  show = read-only detail.
- **Routes** in the `page:all_transactions.php` group — static before
  parameter: `GET transactions.index`, `GET transactions/create/{client}`,
  `POST transactions.store`, `GET transactions.export`, `POST
  transactions.data`, `POST transactions.inline-update`, `GET
  transactions.clients-search`, `GET transactions/{transaction}/edit`,
  `PUT transactions/{transaction}`, `GET transactions/{transaction}`,
  `POST transactions/{transaction}` (destroy).
- **Gating:** sidebar "All Transactions" + profile "+ Add Transaction" buttons
  use `canAccessPage(auth()->user(), 'all_transactions.php')` — v1's
  transaction list is permission-gated by that page name (confirmed against the
  local `tbl_permissions`); v2 applies it to list, create, feed, search, export,
  inline-update, and detail routes. `authorizeProgram()` allows all programs
  when `tbl_program_permissions` is empty (v1 model: no rows = unrestricted).
- **Tests** — `tests/Feature/TransactionTest.php` (12): page gate, pages load,
  create-self w/ audit, TUPAD nulls, restricted-user forbidden, update/delete +
  audits, data feed + filters, program restriction on feed, dropdown
  restriction, inline-update (comma amounts + `m/d/Y` date), client search,
  BOM CSV export.

#### Verification

- `php artisan test` → **40 passed (197 assertions)** (P1+P2+P3 suites).
- `vendor\bin\pint` → passed.
- `route:list` → `transactions.*` group present, static-before-parameter order.
- Local `main_system` untouched (0 transaction rows — no live data to corrupt;
  patient-name "self" behaviour validated against v1 `update_transaction.php`
  which stores the full client name string, not the literal "Self").

**Deviations:** CSV export is streamed (`streamDownload`) instead of writing a
v1-style temp file; `all_transaction_edit.php`/`all_transaction_delete.php`
folded into inline `update`/`destroy` on the list; `transaction_table.php`
became the index view with a Blade partial — same contract.

---

### 2026-08-05 — P2 completion: delete_client, duplicate detection, client photos, student self-service

Closes the last P2 v1 files (`delete_client.php`, `preview_duplicates.php`,
`fetch_duplicates.php`, `delete_duplicates.php`, `save_client_photo.php`,
`client_photo.php`, `student_photo_upload.php`, `student_update_photo.php`,
`student_verify.php`), completing the P2 milestone.

#### What was built

- **Client delete + `ClientPolicy`** — `ClientService::destroy(Client, User)`:
  works inside a `DB::transaction`; throws `InvalidArgumentException` when the
  client has `tbl_transactions` rows (v1's bare `DELETE` hit the same wall —
  `tbl_transactions.client_id` has no ON DELETE CASCADE); manually removes
  two-direction `tbl_family_members` links (no FK exists); writes a
  `DELETE_CLIENT` audit with the old row as JSON. `ClientPolicy` gates the
  action on `page:clients.php` via `AccessControlService`; registered with
  `Gate::policy` in `AppServiceProvider`. `ClientController@destroy` calls
  `$this->authorize('delete', $client)`; the base `Controller` now uses
  `AuthorizesRequests`. `clients/index.blade.php` gains a per-row DELETE form
  (CSRF fetch) and a "Remove Duplicates" button (wider actions column).
- **Duplicate detection** — `DuplicateService::baseQuery()`: joins `tbl_clients`
  against a subquery of duplicate groups keyed on
  (lastname, firstname, middlename, city_municipality) with `HAVING COUNT(*) > 1`
  — exactly v1's DISTINCT-group semantics — plus municipality/barangay name
  joins. `countTotal()`/`countFiltered()` and `destroyMany()` (per-row
  `ClientService::destroy`, so one guarded client can't abort the batch;
  returns `{deleted, failed}`). `DuplicateController`: `index` (filter-persisting
  page), `data` (server-side DataTables, name/municipality/barangay/precinct
  search, sortable), `destroy` (POST, "No records selected" error, N deleted /
  M skipped summary). View `duplicates/index.blade.php` mirrors v1: checkboxes,
  select-all, count badge, approve-delete confirm, municipality→barangay cascade.
- **Client photo upload** — `PhotoService::store()`: writes to
  `storage/app/public/uploads/client_photos/`, stores only the filename in
  `tbl_client_photos` (v1 contract); accepts file upload **and** WebRTC camera
  capture; validates JPEG magic bytes for camera input (v1 trusted base64 +
  extension blindly), restricts extensions, handles `UPLOAD_ERR_NO_FILE`.
  `PhotoController@store` validates client existence + image (max 5 MB).
  `clients/show.blade.php` gains a photo modal (file + camera → capture → retake
  → save) and renders photos via `asset('storage/uploads/client_photos/…')`
  (the previous `asset($photo->photo_path)` was fixed).
- **Student self-service (public)** — v1 has no auth here, so these routes live
  OUTSIDE the `auth` + `single-device` group. `StudentController::updatePhoto`
  (search over `tbl_transactions` client joins, scholar programs only:
  CEAP, CEAP_NEW, CEDSSG, CEDSSG_NEW, OTEA, OTCES), `verify` (birthdate +
  mobile_no match → `session(['verified_student' => …])`), `photoUpload`
  (guarded), `storePhoto` (camera image only, saves, clears session). Views:
  `students/{update-photo,verify,photo-upload}.blade.php`.
- **Routes** — public group: `GET student/update-photo`,
  `GET|POST student/verify/{client}`, `GET|POST student/photo-upload`. Inside
  `page:clients.php` (static-before-parameter): `clients/duplicates`
  (`duplicates.index`), `POST clients/duplicates/data`, `POST
  clients/duplicates/delete`, `POST clients/photo` (`clients.photo.store`),
  `POST clients/{client}` (`clients.destroy`).
- **Tests** — `DuplicateTest` (6), `PhotoTest` (4), `StudentTest` (7), plus 2
  delete tests added to `ClientTest`.

#### Verification

- `php artisan test` → **59 passed (270 assertions)** (P1+P2+P3 suites).
- `vendor\bin\pint` → passed; `pint --test` → passed.
- `route:list` → duplicates/photo/student/destroy routes registered.
- Fixes during verification: base `Controller` needed `AuthorizesRequests`;
  duplicate destroy now builds redirect query params with `array_filter`
  (no empty `?municipality=&barangay=`); duplicates-feed test asserts the
  checkbox HTML generically instead of a specific row id (tie order).

**Deviations:** duplicates gating — v1 hard-coded `super_admin`/`jordi`
usernames; v2 gates the duplicate pages on `page:clients.php` via the ACL
(ADR-003 — no username checks). Delete is per-row audited
(`DELETE_CLIENT` each row) where v1's `delete_duplicates.php` ran a single
un-audited `DELETE … IN (…)`. Family-member links are cleaned up on delete
(v1 left orphans — `tbl_family_members` has no FK).

---

### 2026-08-06 — P2 UI: client details slide-over panel (blueprint AD-10)

Implements the blueprint's right-side sliding details panel for client rows,
per prototype feedback ("details should show on the right side of the screen,
not go to another page; clicking a row opens the panel; responsive").

#### What was built

- **Shared partial `clients/_details.blade.php`** — the profile content
  (photo, fields, household, family members, transactions, photo-upload modal)
  extracted from the old `show` view. Panel-aware: in panel mode the "Back"
  button becomes an "Open full page" deep link. The camera script now runs in
  an IIFE so it can be injected repeatedly into the list page without `const`
  redeclaration errors.
- **`clients/show.blade.php`** — now a thin wrapper that renders the partial
  (`panel=false`); the full profile page is preserved as a deep link and for
  direct navigation.
- **`ClientController@show(Request, Client)`** — returns the bare partial
  (no layout) when the request has `?panel=1`; full page otherwise.
- **`clients/index.blade.php`** — a Bootstrap **Offcanvas panel fixed to the
  right edge** (`width: min(680px, 94vw)` → responsive: 680 px desktop, ~94 %
  of the viewport on small screens) with a spinner placeholder. Row click
  (skipping the Actions cell) fetches `clients/{id}?panel=1`, injects the
  HTML, and re-executes the partial's inline scripts via a small
  `executeScripts()` helper (innerHTML does not run `<script>` tags). DataTables
  `createdRow` stamps `data-id` on each row; `window.openClientPanel(id)` is
  also used by the Actions-column "View" button (replacing the previous link to
  the profile page).
- **Tests** — `ClientTest::test_client_details_panel_returns_partial_without_layout`
  asserts `?panel=1` returns the partial (profile content, no `<html>` layout)
  and the full page still renders the layout.

#### Verification

- `php artisan test` → **60 passed (276 assertions)** (P1+P2+P3 suites).
- `vendor\bin\pint` → passed.
- Bootstrap's data API uses document-level delegation, so the injected
  photo-upload modal (data-bs-toggle) works without re-initialization; the
  offcanvas is driven via the JS API (`bootstrap.Offcanvas.getOrCreateInstance`).

**Deviations:** none beyond the earlier recorded ones — this brings the client
detail surface in line with blueprint AD-10 (slide-over is primary; the page
remains for deep links/print, matching the blueprint's compatibility table).

---

### File inventory (P2 households + profile + family members — added 2026-08-05)

**Created:** `app/Services/{HouseholdService,FamilyMemberService}.php`,
`app/Http/Controllers/{HouseholdController,FamilyMemberController}.php`,
`app/Models/{FamilyMember,ClientHousehold}.php`,
`resources/views/households/{index,create,show}.blade.php`,
`resources/views/clients/show.blade.php`,
`resources/views/family_members/create.blade.php`.

**Modified:** `routes/web.php`, `app/Http/Controllers/ClientController.php`,
`resources/views/partials/sidebar.blade.php`,
`resources/views/households/index.blade.php` (CSRF header on delete fetch).

### File inventory (P3 transactions — added 2026-08-05)

**Created:** `app/Services/TransactionService.php`,
`app/Http/Controllers/TransactionController.php`,
`resources/views/transactions/{index,create,edit,show}.blade.php`,
`tests/Feature/TransactionTest.php`.

**Modified:** `routes/web.php` (`page:all_transactions.php` group, static-
before-parameter order), `resources/views/partials/sidebar.blade.php` (gated
"All Transactions" link), `resources/views/clients/show.blade.php` (gated
"+ Add Transaction" button).

### File inventory (P2 completion — delete, duplicates, photos, student — added 2026-08-05)

**Created:** `app/Services/{DuplicateService,PhotoService}.php`,
`app/Http/Controllers/{DuplicateController,PhotoController,StudentController}.php`,
`app/Policies/ClientPolicy.php`,
`resources/views/duplicates/index.blade.php`,
`resources/views/students/{update-photo,verify,photo-upload}.blade.php`,
`tests/Feature/{DuplicateTest,PhotoTest,StudentTest}.php`.

**Modified:** `app/Http/Controllers/{Controller,ClientController}.php` (base
controller now uses `AuthorizesRequests`; destroy + authorize),
`app/Providers/AppServiceProvider.php` (`Gate::policy`),
`app/Services/ClientService.php` (`destroy`),
`resources/views/clients/{index,show}.blade.php` (delete form, Remove
Duplicates button, photo modal + storage-URL fix),
`routes/web.php` (public student group + duplicates/photo/destroy routes),
`tests/Feature/ClientTest.php` (+2 delete tests).

### File inventory (P2 slide-over details panel — added 2026-08-06)

**Created:** `resources/views/clients/_details.blade.php` (shared profile
partial), `tests/Feature/ClientTest.php` (+1 panel test).

**Modified:** `app/Http/Controllers/ClientController.php` (`show` supports
`?panel=1`; data-feed "View" button now calls `openClientPanel(id)`),
`resources/views/clients/index.blade.php` (right-side Offcanvas panel + row
click + `openClientPanel`/`executeScripts` JS),
`resources/views/clients/show.blade.php` (thin wrapper over the partial).

### 2026-08-07 — P6 Scholars / GIP (initialization)

- **Models Created:** `ScholarInfo`, `GipInfo`, `UpdateLog`, `Exam`, `ExamResult` mapped to legacy tables.
- **Next steps:** Implement `ScholarService` and `ScholarController`.

---

Completes the P4 milestone. All 14 v1 scanner pages + their action handlers
(`scanner_*.php` + `scanner_*_action.php`) collapse into **one config-driven
engine**: a `ScanService` with 8 behavioral modes, a thin `ScannerController`,
one shared Blade view, per-program routes/sidebar links, and 14 feature tests.
Behavior was transcribed byte-for-byte from the v1 action handlers into
`config/scanner.php` (source-of-truth: `docs/SCANNER_ANALYSIS.md` +
`docs/SCANNER_CONFIGURATION_MATRIX.md`); the service has **no branching on
scanner key**.

#### What was built

- **`config/scanner.php`** — 14 scanner keys (`ceap`, `ceap_new`, `cedssg`,
  `cedssg_new`, `cedssg_update`, `otces`, `otea`, `toda`, `tupad`,
  `new_scholars`, `ongoing_scholars`, `payout`, `payout_unpaid`, `generic`).
  Each entry: `key`, `mode`, `title`, `page` (ACL gate = the v1 file name),
  `lookup` (mode + `lookup_miss_message`), `programs`, `insert`/`update`,
  `duplicate` (rule, message, optional `show_existing`), `audit`
  (action/fields/values or `null`), `attendance`, and `ui` (fields, resume,
  success message, `amount_paid_readonly` for cedssg_update, types/statuses
  from `TransactionService` consts, `scan_success_sound`). Semester dates and
  amounts are config data, never hardcoded. Generic exposes only AICS, AKAP,
  MAIP, TUPAD, CEDSSG, CEAP.
- **`app/Services/ScanService.php`** — `lookup()`/`save()` dispatch by mode:
  lookups `client`, `client_geo`, `transaction`, `transaction_partial`,
  `exam_derived`, `existing_program`, `seat_attendance` (exact → partial
  fallback, `lookup_ignore_scan` variant); saves `scholarship_transaction`,
  `date_guarded_transaction`, `update_in_place`, `exam_derived`,
  `validate_existing`, `seat_attendance`/`unpaid_attendance`, `generic_form`.
  Helpers: `findClientByName`, `municipalityName`, `barangayName`,
  `remarkKeyDuplicateExists`, `alreadyScanned`, `missMessage`,
  `duplicateMessage`, `resolveGenericPatient`, `writeAudit` (via
  `AuditService::log`).
- **`app/Http/Controllers/ScannerController.php`** — `show($key)` (view),
  `lookup($key)` / `save($key)` (JSON), private `requireAccess` (config lookup,
  404, `AccessControlService::canAccessPage`, 403) so runtime JSON calls
  re-check the ACL even though the GET pages are middleware-gated.
- **`resources/views/scanners/scan.blade.php`** — the single shared scanner
  view: `html5-qrcode` camera + manual input, date/amount fields per
  `ui.fields`, `amount_paid_readonly`, mode-aware result rendering, modal with
  OK → reload/resume, success/error sounds, and the generic form
  (self/custom/existing + client search via `transactions.clients-search`).
  CSRF via `X-CSRF-TOKEN` header (existing AJAX convention). JS config is built
  in the controller and passed as `$scannerJs` (a multi-line `@json([…])` broke
  Blade's one-line directive — moved to the controller).
- **Routes** (`routes/web.php`) — one GET page + POST `lookup`/`save` per key,
  each registered with **literal URLs** and `->defaults('key', $key)` (the
  controller receives `{key}` with no route parameter), gated by
  `page:scanner_*.php` middleware per key.
- **Sidebar** (`partials/sidebar.blade.php`) — loops `config('scanner.scanners')`
  rendering each title, gated via `canAccessPage($user, $page)`.
- **Tests** — `tests/Feature/ScannerTest.php` (14): page gate; all pages load
  for super admin; CEAP lookup/save + audit; CEAP duplicate blocked; OTEA/OTCES
  semester templates; TODA geo + date-guarded save; TUPAD stored-vs-audit
  remarks; CEDSSG update marks pending 2nd sem paid (idempotent); new_scholars
  derives program from exam results; ongoing_scholars latest program + no audit;
  payout seat-attendance one-scan-per-transaction (exact → partial,
  `lookup_ignore_scan`); payout_unpaid partial match + one-scan pre-check;
  generic self + custom patient name, no audit.

#### Verification

- `php artisan test` → **74 passed (386 assertions)** (P1+P2+P3+P4 suites).
- `vendor\bin\pint` → passed on `ScanService`, `ScannerController`, `routes`,
  `ScannerTest`, `config/scanner.php`.
- Two defects found and fixed during verification: (1) scanner routes originally
  used a `{key}` placeholder + `->where('key', …)` which produced URLs like
  `scanners/{key}/lookup` that never matched the test's `route('scanners.X.lookup')`
  calls — switched to literal URLs with `->defaults('key', …)`; (2) the view's
  multi-line `@json([…])` array was truncated by Blade's end-of-line directive
  (compile-time `ParseError`) — the JS config now lives in the controller
  (`$scannerJs`).
- Local `main_system` untouched — no schema or data changes; the engine reads
  `tbl_transactions`, `tbl_exam`, `tbl_results`, `tbl_seats2`,
  `tbl_payout_scans2`, `tbl_payout_scans_unpaid` and writes only through the
  same service paths the tests exercise on `main_system_test`.

**Deviations:** the generic scanner **does** get ACL/program gates (v1 gap —
username-only check — not preserved; ADR-003). V1 `scanner_new_scholars.php`
reads hardcoded program→(amount, date) maps; v2 keeps those values in
`config/scanner.php` (semester data, so still config-driven). Formal P4.5
architecture review of the v1 paid/failed scan paths is recommended next
(informational). `scan_success_sound` is only enabled for the generic scanner
today (v1 toggled it per page); the flag exists in config for the rest.

---

### 2026-08-07 — P5 Payout attendance lists + unpaid verification (admin + public self-service)

Completes the P5 milestone. Ports the three v1 payout-attendance list screens
(`scanned_payouts.php` / `scanned_payouts2.php` / `scanned_payouts_unpaid.php`
+ `fetch_scanned_payouts*.php`) and the unpaid-verification workflow
(`unpaid_verifications.php` admin screen, `disabled_unpaid.php` **public**
self-service form, `unpaid_save.php`, `search_unpaid_grantee.php`,
`fetch_unpaid_verifications.php` delete + feed, `export_unpaid_verifications.php`).
The P4 lesson was reused: one config-driven controller/view for the three
payout lists, not three copies.

#### What was built

- **`config/payout.php`** — 3 attendance variants
  (`scanned_payouts`/`scanned_payouts2`/`scanned_payouts_unpaid`) with
  `table`, `seat_table`, `title`, `programs`, `client_name` SQL fragment
  (`CONCAT(c.lastname, ', ', c.firstname, …)` for paid, `t.patient_name` for
  unpaid), and `labels`. Source of truth for the route/view loop.
- **Models** — `PayoutScan`/`PayoutScan2`/`PayoutScanUnpaid`
  (`tbl_payout_scans*`), `UnpaidVerification` (`tbl_unpaid_verifications`,
  fillable incl. `created_at`, casts), `Seat`/`Seat2` (`tbl_seats*`); all
  `$timestamps = false`.
- **`app/Services/UnpaidService.php`** — `create()` (uppercase/trim every proxy
  field, empty → `NULL`, `created_at = now()`, requires `client_id` +
  `municipality_id`, duplicate guard "You have already submitted your
  confirmation. Multiple submissions are not allowed.") and `destroy()`. No
  audit — v1 parity.
- **`PayoutAttendanceController`** — config-driven `index`/`data` handling
  single-record `id` / delete `delete_id` / DataTables feed modes on the shared
  view; `scanned_at` converted UTC → Asia/Manila `m/d/Y - h:i A`; seats attached
  via batch lookup on the variant's seat table keyed by client name + program.
  Filters: municipality, program, scanned_start/scanned_end; global search over
  name/program/username. Deletes address the variant table, no audit.
- **`UnpaidVerificationController`** — public self-service `store` (mirrors
  `unpaid_save.php`, `proxy_name_display` computed for the success message only),
  admin `data` (single/delete/DataTables; 9-part search; municipality + date
  filters; `created_at` returned raw as v1 does), and streamed BOM CSV export
  (12 v1 columns, `unpaid_verifications_{Y-m-d_H-i-s}.csv`).
- **`GranteeSearchController`** — `search` (kind = `grantee`: 6 programs no
  status filter; `unpaid`: CEAP/CEAP_NEW/OTEA/OTCES with
  `t.status = 'PENDING PAYOUT'`) and `verify` (action=verify; municipality
  match check; latest qualifying program). Public, no auth.
- **Views** — `payouts/attendance.blade.php` (shared, DataTables, delete modal),
  `unpaid_verifications/index.blade.php` (admin table + filters + export +
  delete), `unpaid_verifications/self-service.blade.php` (public form).
- **Routes/sidebar** — public P5 routes (`grantee-search/{kind?}`,
  `unpaid-verification`, `unpaid-verification/submit`) outside the auth group
  (like `student/*`); protected P5 routes inside the auth group via a
  `config('payout.attendance')` loop with `->defaults('variant', …)` and
  `page:` gates (`scanned_payouts*.php`, `unpaid_verifications.php`); sidebar
  shows the three payout links + Unpaid Grantees, ACL-gated.

#### Verification

- `php artisan test` → **89 passed (491 assertions)** (P1–P5 suites), including
  the new `tests/Feature/PayoutTest.php` (15 tests: gates, shared screens, seat
  feed/filters, delete-no-audit, unpaid patient-name feed, self-service public,
  munis/search/verify, store-requires/duplicate/audit-free, feed/filters,
  export BOM+columns).
- `vendor\bin\pint` → passed on all new/changed files.
- Three defects found and fixed during verification: (1) creating a `Client`
  without `barangay` (NOT NULL) broke the unpaid-search test — the test helper
  now builds municipality + barangay per client; (2) empty proxy fields were
  stored as `''`, which MySQL rejects on the DATE `proxy_birthdate` column —
  the store method now maps blank → `NULL`; (3) `fputcsv` quotes fields with
  spaces (`"Client Name"`, …), so the export test parses the header with
  `str_getcsv` instead of asserting a bare string.
- Local `main_system` untouched — no schema or data changes; all P5 tests
  exercise `main_system_test`.
- Corrected a ground-truth error in `docs/implementation/P5_PAYOUT.md` §2.2:
  `disabled_unpaid.php` is the **public self-service form** (no `session.php`),
  not a "disable" screen; removal is a bare `DELETE` via
  `fetch_unpaid_verifications.php?delete_id=N`, and none of the P5 write paths
  audit.

**Deviations:** none of the P5 write paths write `tbl_audit_logs` (v1 parity —
v1 does zero audit calls in these files). Admin list pages are gated by the
`page:` middleware per v1 page key; the self-service form and its
search/verify/save endpoints are intentionally public, exactly as v1 ships them.

---

### 2026-08-07 — P6 Phase 2 cleanup: scholar registry reworked for v1 parity

P6 was initialized earlier today (models + scholar route shell + defective CRUD
scaffolding). After the approved `docs/SCHOLAR_ANALYSIS.md` (REFACTOR + BUILD), this
entry fixes every P6 audit deviation in the scholar registry and the doc
conflicts, then applies the three approved decisions. No Phase 3 features
(reports, QR viewer, GIP, grantee self-service, update logs) were started.

#### Approved decisions applied (SCHOLAR_ANALYSIS §8)

1. **Scholar `full_name`/`match_name`**: v1 `save_scholarship.php` writes
   neither — `ScholarService::save` no longer derives or writes them. New rows
   store `full_name = ''` (INSERT omits it in v1; explicit empty string keeps
   the NOT NULL column happy under strict mode), `match_name` stays `NULL`.
2. **GIP `normalized_name`**: documented as not written by v1 (`save_gip.php`)
   — no PHP syncing. Future `GipController` must leave it unset.
3. **QR payload**: verified against P4 `ScanService` + v1
   `scanner_payout_action.php` — both match the scanned text against
   `tbl_clients.full_name` (exact `TRIM`, collation-insensitive; seat names ≡
   `full_name`). The Phase 3 QR must encode the persisted comma-form
   `client.full_name`. Recorded in `docs/implementation/P6_SCHOLARS.md` §5.7.

#### What was changed

- **`database/factories/ClientFactory.php`** — added `aff_org` (NOT NULL, no
  default); the missing value made `main_system_test` inserts fail
  (`SQLSTATE 1364`).
- **`app/Http/Requests/ScholarRequest.php`** — rewritten: only `client_id` +
  `program` required (v1 modal marks exactly those); `school`, `school_type`,
  `campus`, `college_department`, `course`, `year_level`, `landbank_no`
  nullable (v1 stores `''` for empty); `is_regular` `nullable|boolean`;
  `year_start`/`year_end` nullable strings replacing the bogus integer
  `year_started min:2000/max:2100` rule.
- **`app/Services/ScholarService.php`** — rewritten as a faithful port of
  `save_scholarship.php`: upsert on the latest `(client_id, program)` row
  (`ORDER BY id DESC LIMIT 1`); trims all text fields; `is_regular` defaults to
  `0` when absent (`isset ? intval : 0`); `year_started` built as the
  `"YYYY - YYYY"` varchar with v1's exact one-sided/empty logic; no
  `UpdateLog` write; no `ClientService` dependency.
- **`app/Http/Controllers/ScholarController.php`** — `data()` rewritten to
  `fetch_scholars.php` parity: 15-column order map, default order
  `client_id` asc, search over `full_name`/`program`/`school`,
  subquery-paginate then `LEFT JOIN tbl_exam` on the generated
  `normalized_name` columns (≡ `TRIM(LOWER())`), rows expose
  `ex.barangay`/`ex.town`; `recordsTotal == recordsFiltered` v1 quirk
  preserved. `update()` no longer takes a scholar id (upsert key is
  `(client_id, program)` like v1).
- **`routes/web.php`** — added the missing
  `use App\Http\Controllers\ScholarController;` import (the P6 route shell
  referenced `ScholarController::class` unqualified → "Target class does not
  exist").
- **Views** — `resources/views/scholars/index.blade.php`: v1 columns (ID,
  Client ID, Full Name, Program, Barangay, Town), `client_id` default order,
  pageLength 25 (Add Scholar button kept as the v2 entry to the create page).
  `_form.blade.php`: `year_start`/`year_end` 4-digit inputs (split from
  `year_started` on edit, v1 modal layout), `required` dropped from every
  non-v1-required field, REGULAR/IRREGULAR select order matches the v1 modal.
- **`resources/views/partials/sidebar.blade.php`** — added the Scholars entry
  gated on `page:scholars.php` (sidebar previously had no link to the screen).
- **`tests/Feature/ScholarTest.php`** — rewritten (8 tests, 25 assertions):
  create (parity asserts: `full_name=''`, `is_regular=1`, `year_started`
  `'2025 - 2026'`, no `tbl_update_logs` row); `is_regular` defaults to 0 when
  absent; one-sided year → `'2025'`; both-empty → `''`; empty optional fields
  accepted; update upserts latest row and keeps `full_name`; data feed joins
  `tbl_exam` (case-insensitive) + `client_id` ordering; feed search reports the
  filtered total (v1 quirk). Uses the `logInAs`/`Permission(scholars.php)`
  pattern from `ClientTest`.
- **Docs** — `docs/implementation/P6_SCHOLARS.md`: status header refreshed,
  §2.3 `disabled_update_grantee.php` relabeled as the public self-update form,
  §4 ScholarService/GIP/grantee-update contracts corrected to v1 ground truth,
  §5 rules and §7 mistakes updated, §6 validation guidance made nullable.
  The early `SCHOLAR_ANALYSIS.md` draft was folded into the P6 audit (the
  audit was later re-canonicalized as `docs/SCHOLAR_ANALYSIS.md` — 2026-08-09,
  see the entry below). `ENGINEERING_BLUEPRINT.md` rows 81–83 + §2/§3
  `tbl_scholar_info`/`tbl_gip_info` notes updated.

#### Verification

- `php artisan test --filter=ScholarTest` → **8 passed (25 assertions)**.
- `vendor\bin\pint` → passed on all new/changed files.
- Local `main_system` untouched — no schema or data changes; tests exercise
  `main_system_test`.
- One defect found during verification: the P6 scholar routes referenced
  `ScholarController::class` without the class import (the earlier `aff_org`
  failure happened in test setup, masking the routing bug) — fixed by adding
  the import.

**Deviations:** scholar saves redirect to `scholars.index` with a flash message
instead of v1's `view_client.php#collapseScholarship` (v2 has a standalone
registry page, not the client-page modal); the DataTables feed collapses
multiple `tbl_exam` matches per name into one row instead of duplicating rows.

---

### 2026-08-09 — P6 analysis docs consolidated into `docs/SCHOLAR_ANALYSIS.md`

The P6 Phase 1 audit and the earlier scholars gap notes were merged and
restructured into a single canonical analysis document,
`docs/SCHOLAR_ANALYSIS.md` ("P6 Scholars Module Analysis — v1 vs v2"),
following the naming convention of the other module analyses
(`SCANNER_ANALYSIS.md`, `SCANNER_CONFIGURATION_MATRIX.md`).

- **Content preserved** — §1 v1 inventory and behavior (registry,
  `save_scholarship.php`, relink, GIP, grantee self-service, reports, QR),
  §2 v2 current implementation + test health, §3 v1-to-v2 gap analysis (~85%
  missing pre-cleanup), §4 confirmed parity requirements (10), §5 the 8
  implementation deviations (+ dead-route defect), §6 missing functionality
  (Phase 3 build list), §7 risks and parity concerns, §8 the three decisions
  A/B/C (approved + applied), §9 recommended implementation sequence, §10
  implementation readiness (REFACTOR + BUILD); Appendices A–C (code to reuse,
  doc conflicts resolved, implementation notes).
- **Files changed** — `docs/SCHOLAR_ANALYSIS.md` rewritten as the canonical
  P6 analysis; the standalone audit document removed (not retained as a
  separate file); the archived early-draft scholars analysis removed so the
  canonical document is the only P6 analysis document; references updated to
  point at `docs/SCHOLAR_ANALYSIS.md` (decisions section `§9` → `§8`) in
  `docs/ENGINEERING_BLUEPRINT.md`, this log, and
  `docs/implementation/P6_SCHOLARS.md`.
- **Verification** — repo-wide search confirms no remaining stale audit-file
  references; documentation-only change. No Laravel source, schema, or data
  touched (`main_system` untouched; no tests run because no code changed).

---

### 2026-08-12 — `docs/README.md` title drops the "Planning & Analysis" phase

- **Change** — title changed from `# 2D MIS — v2 Planning & Analysis` to
  `# 2D MIS`. Planning & Analysis is long complete (phase table), so the phase
  is no longer carried in the document title.
- **Verification** — documentation-only change; no Laravel source, schema, or
  data touched (`main_system` untouched; no tests run because no code changed).

---

*End of current implementation log. Append new dated entries above this line.*
