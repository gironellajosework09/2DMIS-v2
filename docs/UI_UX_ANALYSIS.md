# 2DMIS v2 — UI/UX Analysis

> **UI/UX ANALYSIS IN PROGRESS — PHASE 5 PLAN COMPLETE**
>
> - Phase 1 — Repository Reconnaissance: factual inventory of the prototype,
>   the Laravel UI layer, routes, controllers, and authorization-aware
>   navigation. Completed 2026-08-23 (§1–§4).
> - Phase 2 — Prototype Design Analysis: the prototype analyzed as the
>   visual/design source of truth — design tokens, application shell,
>   components, responsive behavior, accessibility, and UX conventions.
>   Completed 2026-08-23 (§5).
> - Phase 3 — Current V2 UI Analysis: the implemented Laravel UI analyzed
>   with the same factual discipline — application shell, page structures,
>   components, dashboard, responsive behavior, accessibility, de-facto
>   visual conventions, repeated patterns and inconsistencies, and the
>   functional UI mechanisms current features depend on.
>   Completed 2026-08-23 (§6).
> - Phase 4 — Prototype ↔ V2 Comparison and Gap Analysis: side-by-side
>   comparison across six lenses (visual/UX, functional, structural,
>   accessibility, responsive, interaction patterns), shell/page/component/
>   token gap analysis, functional-vs-visual conflict analysis, a gap
>   matrix, prioritized recommendations, and Preserve/Adapt/Replace/Defer
>   categorization. Analysis and recommendations only — **no application
>   code was modified**. Completed 2026-08-23 (§7).
> - Phase 5 — UI/UX Implementation Plan: the Phase 4 recommendations
>   converted into an implementation-ready roadmap — principles,
>   architecture roles, design-system consolidation, shell/component/
>   accessibility/responsive plans, dashboard data classification, batched
>   screen roadmap, priority sequencing, file impact map, verification
>   strategy, risk register, and recommended order. Planning only —
>   **nothing implemented**. Completed 2026-08-23 (§8).
> - Architecture Decision Addendum (2026-08-23): production styling
>   direction changed to **Tailwind-first** by owner decision,
>   superseding the §8.3 single-shared-stylesheet choice for remaining
>   batches. Batch A stands as implemented; Phases 1–5 findings unchanged.
>   See §9.
>
> Sections §1–§6 contain **facts observed in the repository only**. Section
> §7 adds comparative analysis and recommendations derived exclusively from
> those recorded facts; Section §8 translates those recommendations into an
> implementation plan. Still nothing implemented.

---

## 1. Executive Summary

The repository contains two parallel UI artifacts:

1. **A standalone HTML/CSS/vanilla-JS prototype** (`prototype/`, ~244 KB across
   3 code files plus a 498-line design spec) that models the *intended future*
   experience of 2DMIS as a single-page application: login mock, 9 top-level
   pages (Dashboard, Clients, Households, Scholars with 5 sub-tabs,
   Transactions, Scanner Engine, Payouts, Access Control, Audit Logs),
   breadcrumb topbar with global search and notifications, and a persistent
   right-side slide-in resident-details panel. Per its own spec
   (`PROTOTYPE_SPEC.md`), it is presentation-only, backend-free, uses mock data,
   and prescribes a government design language (USWDS/GOV.UK-inspired palette,
   Inter/Outfit typography, responsive breakpoints, accessibility rules).

2. **The implemented Laravel 12 v2 application** (P0–P7 + P12 complete per
   `docs/SESSION_HANDOFF.md`; 213 tests / 1056 assertions green) whose UI is a
   functional, ACL-gated, multi-page Blade application: 46 Blade files composed
   of 1 layout, 2 shell partials, 4 form/detail partials, 7 standalone
   public/self-service pages, 1 dead framework stub (`welcome.blade.php`), and
   31 authenticated pages extending the layout. It renders ~53 addressable
   screens (46 authenticated incl. 14 config-driven scanner keys and 3 payout
   variants; 7 public pages) styled with Bootstrap 5.3.2 via CDN, per-view
   inline `<style>` blocks, and jQuery + DataTables 1.13.6 on list screens.

Navigation in v2 is fully authorization-aware: every sidebar link is wrapped in
an `AccessControlService::canAccessPage()` check keyed to the same v1 page keys
(`page:` middleware) that guard the routes, so menu visibility and route access
cannot drift apart. The v2 dashboard is currently a minimal placeholder card.

Phase 2 (§5) documents the prototype's actual design system and UX patterns as
observed in `prototype/css/style.css`, `prototype/index.html`,
`prototype/js/app.js`, and `prototype/PROTOTYPE_SPEC.md`. Phase 3 (§6) applies
the same factual discipline to the implemented V2 UI itself — application
shell, page structures, components, dashboard, responsive behavior,
accessibility, de-facto visual conventions, repeated patterns and
inconsistencies, and the functional UI mechanisms current features depend on.
Phase 4 (§7) compares the two artifacts across six lenses and records the gap
analysis. Its headline conclusion: **functionally, V2 meets or exceeds the
prototype nearly everywhere — including the three destinations the prototype
still shows as milestone placeholders (Payouts, Access Control, Audit Logs);
the decisive gaps are experiential, not functional** — V2 lacks the
prototype's mobile navigation strategy, shell wayfinding (breadcrumbs, global
search), unified non-blocking feedback model (toasts and modal confirms vs
native `alert()`/`confirm()`), government design-token identity (navy/gold vs
stock Bootstrap), and accessibility scaffolding (skip link, focus-visible,
label wiring, touch targets), and its dashboard is still a placeholder against
the prototype's KPI composition. All Phase 4 output is analysis and
recommendations only; nothing has been implemented.
Phase 5 (§8) has now prepared the implementation roadmap itself: fifteen
planning subsections covering principles, architecture roles, design-system
consolidation, shell/component/accessibility/responsive plans, a dashboard
data classification (real vs endpoint-needed vs placeholder), nine screen
groups, nine execution batches with dependencies and rollback boundaries, a
file impact map, verification strategy, and a risk register — so future
implementation can proceed incrementally without touching business logic.

---

## 2. Current UI/UX State (factual description)

### 2.1 Application shell (authenticated area)

Defined entirely in `resources/views/layouts/app.blade.php` (116 lines):

- Fixed top dark navbar (`navbar-dark bg-dark shadow-sm fixed-top`) with a
  hamburger sidebar-toggle button, municipal seal logo
  (`public/seal_logo.png`), "2D MIS" brand link to the dashboard, and a user
  dropdown ("Welcome, {username}") containing only a Logout form button.
- Fixed left sidebar (220 px wide, dark `#212529`, starts below the navbar at
  `top: 56px`, scrolls independently) rendered by
  `resources/views/partials/sidebar.blade.php`. Toggle collapses it via
  `margin-left: -220px` / `.shifted` content transition.
- Content area offset by the sidebar width (`margin-left: 220px`,
  `padding-top: 76px`), rounded cards (`border-radius: 1rem`).
- Typography: Roboto (400/500/700) loaded from Google Fonts CDN; body
  background `#f8f9fa`.
- Flash/error handling: two dismissible Bootstrap alerts at the top of the
  content area (`session('login_status')` warning, `$errors->any()` danger).
- Session watchdog: inline JS polls `GET /session/status`
  (`route('session.status')`) **every 2 seconds**; on `another_device` it shows
  a browser `alert()` then redirects to `/login`; on `logged_out` it redirects
  silently.
- Extension points: `@stack('styles')` in `<head>`, `@stack('scripts')` before
  `</body>`, `@yield('title')`, `@yield('content')`.

### 2.2 Styling approach

- Bootstrap 5.3.2 CSS **and** JS bundle loaded from jsDelivr CDN in the layout;
  standalone (non-layout) pages load the Bootstrap bundle individually.
- **No compiled/bundled CSS or JS is used by any live screen.** The Vite
  pipeline exists (`vite.config.js`, `resources/css/app.css` Tailwind v4 stub,
  `resources/js/app.js` importing `bootstrap.js`) but is referenced only by the
  unreferenced Laravel starter view `welcome.blade.php`.
- All custom styling lives in **inline `<style>` blocks inside 24 Blade files**
  (the layout's shell styles plus one block per complex screen). There is no
  shared application stylesheet, no design-token file, and no versioned local
  asset build for the live UI.
- List screens use **jQuery 3.7.1 + DataTables 1.13.6 (+ DataTables Bootstrap 5
  integration)** from CDNs, fed by server-side JSON `POST */data` endpoints.
- The scanner screen additionally loads **html5-qrcode** from unpkg.
- Audible scan feedback ships as static assets: `public/sounds/success.mp3`,
  `public/sounds/not_found.mp3`.

### 2.3 Page-level interaction patterns observed (facts only)

- Lists: DataTables tables backed by `POST` data-feed routes; row actions as
  buttons/links; modals for details (e.g., payout attendance "Scanned Payout
  Details", duplicates compare/delete).
- Clients module includes a slide-over details panel (partial
  `clients/_details.blade.php`) introduced in P2 per the implementation log.
- Forms: standard Blade forms with Bootstrap classes; several forms carry
  inline JS for dependent selects (barangay lookup via
  `GET geography/barangays`, mobile-number verification via
  `GET clients/verify-mobile` / `grantee/verify-mobile`, household client
  pickers via `households.clients.*` endpoints, transaction/scholar client
  pickers via `*-clients-search` endpoints).
- Scanner engine: one shared view (`scanners/scan.blade.php`) driven by
  `config/scanner.php` — camera QR scanning (html5-qrcode), lookup/save POSTs,
  resume-capable modes, success/failure sounds.
- Public self-service pages (login-style standalone layouts, no sidebar):
  unpaid verification form, grantee self-update form, QR viewer, student
  verify/photo flows.

### 2.4 Authorization-aware navigation (facts)

`resources/views/partials/sidebar.blade.php` resolves the ACL service once
(`app(\App\Services\AccessControlService::class)`) and wraps **every** link in
an explicit permission check against the v1 page key:

| Sidebar entry | Gate (`canAccessPage`) | Route |
|---|---|---|
| Dashboard | *(always, auth)* | `dashboard` |
| Currently Logged Users | `currently_logged_users.php` | `session.online` |
| Clients | `clients.php` | `clients.index` |
| Households | `household.php` | `households.index` |
| Scholars | `scholars.php` | `scholars.index` |
| Scholarship Reports | `scholarship_reports.php` | `scholarship-reports.index` |
| Update Logs | `update_logs.php` | `update-logs.index` |
| All Transactions | `all_transactions.php` | `transactions.index` |
| 14 scanner entries | `$scannerConfig['page']` from `config('scanner.scanners')` | `scanners.{key}` |
| Payout Attendance / 2 / Unpaid | `scanned_payouts*.php` (hardcoded map in the view) | `payout-attendance.{variant}.index` |
| Unpaid Grantees | `unpaid_verifications.php` | `unpaid-verifications.index` |
| Create User | `register.php` | `admin.users.create` |
| User Management | `*` (super-admin rule) | `admin.users.index` |
| Manage Permissions / Action Permissions / Municipality Scope | `manage_permissions.php` | `admin.permissions.*` |
| Manage Program Permissions | `manage_program_permissions.php` | `admin.program-permissions.pages` |
| Multi-Device Exemptions | `manage_multi_device_exemptions.php` | `admin.exemptions.pages` |
| Audit Logs | `audit_logs.php` | `admin.audit-logs.index` |
| AICS (placeholder `href="#"`) | `canAccessProgram($user, 'AICS')` | — |

Active-state highlighting uses `request()->routeIs()` patterns. The same page
keys are enforced at the route layer via the `page:` middleware alias
(`AuthorizePage`), and mutation endpoints add `action:` middleware
(`AuthorizeAction`, e.g. `action:clients.php,create`). Middleware aliases are
registered in `bootstrap/app.php` together with `single-device`
(`EnsureSingleDevice`). Nav visibility and route access therefore share one
source of truth (the ACL service reading the v1 permission tables).

### 2.5 Known dead/stub UI elements (facts)

- `welcome.blade.php`: Laravel framework starter (Tailwind/Vite); **no route
  references it** — `/` is the authenticated dashboard.
- `resources/js/bootstrap.js`, `resources/js/app.js`,
  `resources/css/app.css`: framework scaffolding, unused by live screens.
- Dashboard body is a single card: heading "Dashboard" and
  "Welcome, {username}." — no widgets/statistics.
- Sidebar "AICS" entry links to `#` when the user holds the AICS program.

---

## 3. Current V2 Screen Inventory (user-facing)

### 3.1 Public / unauthenticated screens (standalone views, no app shell)

| # | Screen | Route | View | Controller@method |
|---|---|---|---|---|
| 1 | Login | `GET /login` | `auth/login.blade.php` | `AuthController@showLogin` |
| 2 | Student update-photo notice | `GET /student/update-photo` | `students/update-photo.blade.php` | `StudentController@updatePhoto` |
| 3 | Student verification | `GET/POST /student/verify/{client}` | `students/verify.blade.php` | `StudentController@verify` |
| 4 | Student photo upload | `GET/POST /student/photo-upload` | `students/photo-upload.blade.php` | `StudentController@photoUpload` / `@storePhoto` |
| 5 | Unpaid verification (self-service) | `GET /unpaid-verification`, `POST /unpaid-verification/submit` | `unpaid_verifications/self-service.blade.php` | `UnpaidVerificationController@selfService` / `@store` |
| 6 | Grantee self-update (self-service) | `GET /grantee-update`, `POST /grantee-update/save` | `grantee_update/self-service.blade.php` | `GranteeUpdateController@selfService` / `@store` |
| 7 | Grantee QR viewer | `GET /qr-viewer` | `qr/viewer.blade.php` | `QrController@show` |

Public helper endpoints consumed by these pages (JSON/no dedicated view):
`GET /session/status`, `GET/POST /grantee-search/{kind}`
(`GranteeSearchController@search/@verify`), `GET /grantee/verify-mobile`
(`ClientController@verifyMobile`), `GET /grantee/barangays`
(`GeographyController@barangays`).

### 3.2 Authenticated screens (extend `layouts.app`, ACL-gated)

| # | Screen | Route | View | Gate |
|---|---|---|---|---|
| 1 | Dashboard (placeholder) | `GET /` | `dashboard.blade.php` | auth |
| 2 | Currently Logged Users | `GET /session/online` (+ force-logout POST) | `sessions/online.blade.php` | `currently_logged_users.php` / `force_logout.php` |
| 3–6 | Clients list / create / edit / show | `GET|POST|PUT|DELETE /clients…` | `clients/index,create,edit,show` + `_form`,`_details`,`_gip` partials | `clients.php` (+ `action:` on store/update/delete/photo/GIP) |
| 7 | Duplicate records review | `GET /clients/duplicates` (+ data/delete POSTs) | `duplicates/index.blade.php` | `clients.php` (delete: `action:clients.php,delete`) |
| 8–10 | Households list / create / show (+ delete) | `/households…` | `households/index,create,show` | `household.php` (+ `action:` on store/delete) |
| 11 | Add family member | `GET/POST /family-members/{client}` (+ search) | `family_members/create.blade.php` | `clients.php` |
| 12–15 | Transactions list / create / edit / show (+ inline edit, export, feed) | `/transactions…` | `transactions/index,create,edit,show` | `all_transactions.php` (+ `action:` create/edit/delete/export) |
| 16–18 | Scholars list / create / edit (+ relink, feed) | `/scholars…` | `scholars/index,create,edit` + `_form` | `scholars.php` (+ `action:` create/edit) |
| 19 | Scholarship reports | `GET /scholarship-reports` (+ data/export) | `scholarship_reports/index.blade.php` | `scholarship_reports.php` |
| 20 | Update logs | `GET /update-logs` | `update_logs/index.blade.php` | `update_logs.php` |
| 21–34 | Scanner engine — 14 keys: `ceap`, `ceap_new`, `cedssg`, `cedssg_new`, `cedssg_update`, `otces`, `otea`, `toda`, `tupad`, `generic`, `new_scholars`, `ongoing_scholars`, `payout`, `payout_unpaid` | `GET /scanners/{key}` (+ `POST …/lookup`, `POST …/save`) — generated by loop over `config('scanner.scanners')` | shared `scanners/scan.blade.php` | each key's own `scanner_*.php` page |
| 35–37 | Payout attendance — 3 variants: `scanned_payouts`, `scanned_payouts2`, `scanned_payouts_unpaid` | `GET /payout-attendance/{variant}` (+ `POST …/data`) — loop over `config('payout.attendance')` | shared `payouts/attendance.blade.php` | `scanned_payouts*.php` |
| 38 | Unpaid grantees (admin) | `GET /unpaid-verifications` (+ data/export) | `unpaid_verifications/index.blade.php` | `unpaid_verifications.php` |
| 39 | Create user | `GET/POST /admin/users/create` → `/admin/users` | `admin/users/create.blade.php` | `register.php` (+ `action:register.php,create`) |
| 40 | User management + password reset | `GET /admin/users`, `PUT /admin/users/{user}/password` | `admin/users/index.blade.php` | `*` (super-admin rule) |
| 41 | Manage permissions — pages | `GET /admin/permissions` (+ POST update) | `admin/permissions/pages.blade.php` | `manage_permissions.php` |
| 42 | Manage permissions — actions | `GET /admin/action-permissions` (+ POST) | `admin/permissions/actions.blade.php` | `manage_permissions.php` |
| 43 | Manage permissions — municipality scope | `GET /admin/municipality-scope` (+ POST) | `admin/permissions/scopes.blade.php` | `manage_permissions.php` |
| 44 | Program permissions | `GET /admin/program-permissions` (+ POST) | `admin/permissions/programs.blade.php` | `manage_program_permissions.php` |
| 45 | Multi-device exemptions | `GET /admin/exemptions` (+ POST toggle) | `admin/permissions/exemptions.blade.php` | `manage_multi_device_exemptions.php` |
| 46 | Audit logs (+ leaderboard feed) | `GET /admin/audit-logs` (+ data/leaderboard POSTs) | `admin/audit_logs/index.blade.php` | `audit_logs.php` |

Totals: **7 public screens**, **46 authenticated GET screens** (37 unique view
templates — the scanner and payout families share one template each, driven by
config), plus non-screen JSON/feed endpoints listed in §4.7.

---

## 4. Phase 1 Repository Inventory

### 4.1 Prototype files and assets

| Path | Size | Contents (observed) |
|---|---|---|
| `prototype/index.html` | 45,599 B (~834 lines) | Login mock (`#loginPage`) + SPA shell (`#appShell`); sidebar with 3 labeled sections (Overview / Registry / Assistance / Administration) and 9 `data-page` links; topbar with hamburger, breadcrumb (`#breadcrumbPage`), global search input, notifications bell + panel, "Online · Single Device" indicator, logout; 9 page containers (`#page-dashboard`, `-clients`, `-households`, `-transactions`, `-scholars` [5 sub-tabs: Scholars, GIP Profiles, Scholarship Reports, Update Log, Grantee Self-Update], `-scanner`, `-payouts`, `-users`, `-audit`); skip-link for a11y. References `../public/seal_logo.png`. |
| `prototype/css/style.css` | 65,499 B (~1.7k lines) | Full prototype stylesheet (design system, components, responsive rules). |
| `prototype/js/app.js` | 132,544 B (~2.7k lines) | Vanilla-JS SPA: mock data (`PROGRAMS` — 17 programs; `SCHOLAR_PROGRAMS`; `PROGRAM_GROUPS`; `CATEGORIES`; avatar colors), DOM helpers, pager rendering, sidebar routing, resident-details slide-in panel renderer (sections: personal, household, contact, programs, gov IDs, notes, timeline, documents, audit; scholar/GIP/exam sections). Header comment: "Interactive SPA presentation layer for stakeholder demos only. No backend — all data is mock." |
| `prototype/PROTOTYPE_SPEC.md` | 10,166 B (498 lines) | Design spec: presentation-only purpose; incremental-improvement constraint (work only inside `prototype/`); design philosophy (professional/trust/clarity; avoid gradients/glassmorphism); preserve branding/navigation/workflow; inspiration USWDS 3.0, GOV.UK, SGDS, IBM Carbon, Fluent 2; government color palette (#0038A8 primary blue, #CE1126 danger red, #F4C430 accent gold, #1E293B sidebar, #F8FAFC workspace, #FFFFFF surface, #E5E7EB border, #111827/#6B7280 text); dashboard/records/resident-details-panel requirements (persistent right-side slide-in, 420–500 px desktop, tablet half-screen, mobile drawer/bottom sheet, never navigate away); responsive breakpoints (test widths 1440→320 px); accessibility checklist; interaction standards (150–250 ms animations). |
| `concept_1_executive_lgu_1785925032219.png` (repo root) | 509,842 B | Concept image (executive/LGU concept board). Not referenced by application code. |

No other image/font assets exist under `prototype/`.

### 4.2 Laravel UI files (views)

46 Blade files under `resources/views/`:

- **Layout (1):** `layouts/app.blade.php`
- **Shell partials (2):** `partials/navbar.blade.php`, `partials/sidebar.blade.php`
- **View partials (4):** `clients/_form`, `clients/_details`, `clients/_gip`,
  `scholars/_form` (included by the clients/scholars pages)
- **Standalone public/self-service views (7):** see inventory §3.1
  (`auth/login`, `qr/viewer`, `students/verify`, `students/update-photo`,
  `students/photo-upload`, `unpaid_verifications/self-service`,
  `grantee_update/self-service`)
- **Framework stub, unrouted (1):** `welcome.blade.php`
- **Authenticated pages extending `layouts.app` (31):** see inventory §3.2

**There are no Blade components** — no `resources/views/components/` directory
and no `app/View/Components/` classes exist. Reuse is done exclusively through
`@include` partials and `@extends`/`@section`.

### 4.3 Layouts

Exactly one layout: `layouts/app.blade.php` (structure described in §2.1).
Public/self-service pages intentionally do **not** extend it; they render their
own minimal HTML shells (each loads the Bootstrap bundle itself).

### 4.4 Blade components/partials

| Partial | Consumed by | Role |
|---|---|---|
| `partials/navbar` | `layouts.app` | Topbar: sidebar toggle, seal + brand, user dropdown + logout |
| `partials/sidebar` | `layouts.app` | ACL-filtered navigation (§2.4) |
| `clients/_form` | `clients/create`, `clients/edit` | Shared client form (+ inline JS: barangays, mobile verify) |
| `clients/_details` | `clients/show` (and index slide-over usage) | Client detail panel markup (+ inline JS) |
| `clients/_gip` | `clients/show` | GIP profile section |
| `scholars/_form` | `scholars/create`, `scholars/edit` | Shared scholar form (+ inline JS: client picker, program groups) |

### 4.5 CSS

| Source | Status |
|---|---|
| Bootstrap 5.3.2 CSS (jsDelivr CDN) | Loaded by `layouts.app` and each standalone page |
| Google Fonts: Roboto 400/500/700 (CDN) | Loaded by `layouts.app` |
| Inline `<style>` blocks in 24 Blade files | The only custom styling mechanism (list in evidence: layout, login, all list screens, forms, self-service pages) |
| `resources/css/app.css` (Tailwind v4 import + theme) | Starter stub; used **only** by unrouted `welcome.blade.php` |
| `vite.config.js` / built assets | Present but unused by live screens |

### 4.6 JavaScript

| Source | Status |
|---|---|
| Bootstrap 5.3.2 bundle (CDN) | Dropdowns/modals/dismissibles |
| Inline `<script>` blocks (61 occurrences across ~20 views) | All page behavior: DataTable init + AJAX feeds, form helpers, scanner logic, password-reset confirm, etc. |
| jQuery 3.7.1 + DataTables 1.13.6 (+ BS5 integration) (CDN) | 9 list screens: `clients/index`, `households/index`, `duplicates/index`, `transactions/index`, `payouts/attendance`, `scholarship_reports/index`, `update_logs/index`, `unpaid_verifications/index`, `admin/audit_logs/index` |
| html5-qrcode (unpkg CDN) | `scanners/scan.blade.php` camera scanning |
| Layout inline JS | Sidebar toggle; session-status polling every 2 s with redirect/`alert()` |
| `public/sounds/success.mp3`, `public/sounds/not_found.mp3` | Scan feedback audio |
| `resources/js/app.js` → `import './bootstrap'` | Starter stub; unused by live screens |

### 4.7 Routes (`routes/web.php`, 247 lines — authoritative map)

- **Guest:** `GET/POST /login` (`login`, `login.attempt`)
- **Authed:** `POST /logout`
- **Public helpers/pages:** `GET /session/status`; student self-service trio
  (`student/update-photo`, `student/verify/{client}`, `student/photo-upload`);
  unpaid self-service (`/unpaid-verification`, `/unpaid-verification/submit`);
  grantee search (`GET/POST /grantee-search/{kind}`); grantee self-update
  (`/grantee-update`, `/grantee-update/save`); `GET /grantee/verify-mobile`;
  `GET /grantee/barangays`; `GET /qr-viewer`
- **Authenticated group** (`auth` + `single-device`):
  - `GET /` dashboard; `GET /geography/barangays`
  - Sessions: `GET /session/online` (`page:currently_logged_users.php`),
    `POST /session/force-logout` (`page:force_logout.php`)
  - Clients group (`page:clients.php`): CRUD + `POST clients/data` feed +
    `clients.photo.store` + `gip.store` + `clients.verify-mobile` +
    duplicates (`index`, `data`, `destroy`) + family-members
    (`search`, `{client}` create/store)
  - Households group (`page:household.php`): CRUD + `data` feed +
    `search` + client-picker endpoints (`clients.search`, `clients.options`)
  - Transactions group (`page:all_transactions.php`): CRUD + `data` +
    `inline-update` + `export` + scoped `clients-search`
  - Scholars group (`page:scholars.php`): create/edit/relink + `data` +
    scoped `clients-search`
  - Scholarship reports group (`page:scholarship_reports.php`):
    index/data/export
  - Update logs group (`page:update_logs.php`): index
  - Scanner loop: 14 keys × (`GET page`, `POST lookup`, `POST save`),
    each wrapped in its `page:scanner_*.php` gate
  - Payout loop: 3 variants × (`GET index`, `POST data`), own page gates
  - Unpaid verifications admin (`page:unpaid_verifications.php`):
    index/data/export
  - Admin: `register.php` (create user + `action:` gate), `page:*`
    (users index + password reset), `manage_permissions.php`
    (pages/actions/scopes + updates), `manage_program_permissions.php`,
    `manage_multi_device_exemptions.php`, `audit_logs.php`
    (index/data/leaderboard)

Middleware aliases (`bootstrap/app.php`): `single-device` →
`EnsureSingleDevice`, `page` → `AuthorizePage`, `action` → `AuthorizeAction`.

### 4.8 Controllers relevant to UI actions (24 files, `app/Http/Controllers/`)

| Controller | Screens/actions served |
|---|---|
| `AuthController` | login form, attempt, logout |
| `DashboardController` | dashboard placeholder |
| `SessionController` | status JSON poll, online users list, force-logout |
| `ClientController` | clients CRUD, mobile verification (both authed + public alias), data feed |
| `DuplicateController` | duplicates review screen, data feed, delete |
| `PhotoController` | client photo upload |
| `GipController` | GIP profile save |
| `HouseholdController` | households CRUD, feeds, search/pickers |
| `FamilyMemberController` | family-member add flow |
| `TransactionController` | transactions CRUD, inline edit, export, client picker (shared with scholars via `scopePage`) |
| `ScholarController` | scholars CRUD, feed, client relink |
| `ReportController` | scholarship reports screen, data, BOM/CSV export |
| `GranteeUpdateController` | public self-update form/save, admin update-log viewer |
| `GranteeSearchController` | public grantee search/verify endpoints |
| `UnpaidVerificationController` | public self-service form/save, admin list/export |
| `QrController` | public QR viewer |
| `StudentController` | student verify / photo upload flows (public) |
| `ScannerController` | shared scanner screen + lookup/save for all 14 config-driven keys |
| `PayoutAttendanceController` | shared attendance screen + data feed for 3 variants |
| `UserController` | create-user screen, users index, password reset |
| `AdminPermissionController` | page/action/scope/program/exemption admin screens + updates |
| `AuditController` | audit log viewer, data feed, leaderboard |
| `GeographyController` | barangay cascade endpoint (authed + public alias) |
| `Controller` (base) | shared base class |

Supporting services feeding these screens (13 files in `app/Services/`,
incl. `AccessControlService` used by the sidebar): `AuditService`,
`ClientService`, `DuplicateService`, `FamilyMemberService`, `GipService`,
`GranteeUpdateService`, `HouseholdService`, `PhotoService`, `ScanService`,
`ScholarService`, `TransactionService`, `UnpaidService`.

Config driving UI variants: `config/scanner.php` (14 scanner definitions:
mode, title, ACL page, programs, insert/duplicate/audit rules, UI field list)
and `config/payout.php` (3 attendance variants: title, backing table, seat
table, program filters, linked scanner route/label, modal title).

### 4.9 Authorization-aware navigation

Covered factually in §2.4. Summary: sidebar gating, route gating (`page:`),
and mutation gating (`action:`) all resolve the same v1 permission keys through
`AccessControlService`; `'*'` denotes super admin; active-link highlighting via
`request()->routeIs()`; one program-level check (`canAccessProgram('AICS')`)
renders a placeholder link.

---

## 5. Phase 2 — Prototype Design Analysis

Sources analyzed (all findings below are read directly from these files):
`prototype/css/style.css` (1,894 lines), `prototype/index.html` (834 lines),
`prototype/js/app.js` (2,669 lines), `prototype/PROTOTYPE_SPEC.md` (498 lines).
The prototype is a self-contained SPA: one HTML file containing the login mock,
the app shell, all page containers, and mount points; `app.js` renders all
dynamic content (tables, panels, modals, toasts) from mock data; `style.css`
defines the entire design system in CSS custom properties.

### 5.1 Visual identity

**Color system** (actual `:root` tokens in `style.css:8-78`; an older palette —
navy `#0F1B2D`, gold `#C9953E` — is preserved as a commented-out block above
the current values):

| Token | Value | Observed usage |
|---|---|---|
| `--navy` | `#0038A8` | Philippine flag blue. Sidebar/panel/modal/login-header gradients, primary buttons, active pagination/tabs/calendar-today, filter chips |
| `--navy-light` / `--navy-hover` | `#164A9C` / `#002B7F` | Gradient stops, hover states |
| `--gold` | `#FCD116` | Philippine flag yellow. Primary action buttons (`btn-gold`), active sidebar link text + left bar + badges, focus rings (`:focus-visible`, `--shadow-glow`), timeline dots, brand tagline |
| `--gold-light` / `--gold-dim` | `#FFF8D6` / `rgba(252,209,22,.12)` | Gradients, tinted icon tiles/notices/unread rows |
| `--teal` / `--teal-light` | `#2D8B7A` / `#34A08C` | Success/paid/active status, "Online · Single Device" indicator, avatar gradients, program chips |
| `--red` / `--red-light` | `#CE1126` / `#E8463A` | Destructive only: delete confirms, danger row-actions, notification dot, rejected badges |
| `--amber` / `--amber-light` | `#D4A900` / `#FFF8D6` | Pending badges, notes left-border |
| `--blue-accent` | `#3B82F6` | "approved" badges, links ("Mark all read", notification footer) |
| Surfaces | `--bg #F0F2F5`, `--bg-alt #E8EBF0`, `--card #FFFFFF`, `--card-hover #FAFBFC` | Workspace vs. surface layering |
| Borders | `--border #E2E5EA`, `--border-light #EEF0F4` | Inputs/dividers/card borders |
| Text | `--text-primary #0F1B2D`, `--text-secondary #4E5A6E`, `--text-muted #70798B`, `--text-inverse #FFFFFF` | A CSS comment records that muted shades were "slightly darkened for contrast" |

Semantic color rules observed in practice match the spec's intent: blue =
structure/primary navigation, gold = primary actions & active states, teal =
success/paid/online, amber = pending, red strictly destructive/critical.

Note (factual deviation from spec): `PROTOTYPE_SPEC.md` §7 prescribes sidebar
`#1E293B`, workspace `#F8FAFC`, gold `#F4C430`; the implemented prototype uses
a navy-gradient sidebar (`linear-gradient(180deg, #0038A8 → #0C1622)`),
workspace `#F0F2F5`, and gold `#FCD116`.

**Typography**

- Fonts loaded from Google Fonts in `index.html`: **Inter** 300–800 (body/UI)
  and **Outfit** 400–800 (headings, metric values, money/mono accents).
- Base: `html { font-size: 14px }`; body Inter, `line-height: 1.6`,
  antialiased.
- Headings: Outfit, weight 600, `line-height: 1.3`, `letter-spacing: -0.01em`.
- Observed scale: page title h1 `1.5rem/700`; metric value `1.85rem/800`
  (Outfit); card header `1rem/700`; modal title `1.02–1.05rem`; body/table
  cells `0.85rem`; secondary text `0.76–0.82rem`; micro-labels `0.65–0.72rem`
  uppercase with `0.04–0.1em` letter-spacing (sidebar section labels, form
  labels, table headers, details-section titles).
- Money/mono accents use `'Outfit', monospace` (`.text-money`, `.text-mono`,
  `.ip-chip`).

**Branding & government/LGU visual language**

- Municipal seal (`../public/seal_logo.png`) on white rounded containers:
  72 px circle on login, 36 px rounded-square in the sidebar brand block.
- Wordmark "2DMIS" (white, bold) + "Municipal Assistance" tagline in gold,
  uppercase, letter-spaced; login subtitle "2nd District Management Information
  System"; dashboard subtitle "Municipal Assistance Overview — Ilocos Sur,
  2nd District".
- Government aesthetic per spec §4/§6 (USWDS/GOV.UK/SGDS/Carbon/Fluent
  inspiration): restrained flat surfaces, no glassmorphism; subtle gradients
  used only on navy headers/buttons and accent bars; enterprise-style motion.

### 5.2 Application shell

- **Sidebar** (`style.css:280-411`): fixed, full-height, 260 px
  (`--sidebar-w`; a `--sidebar-collapsed-w: 72px` token exists but no
  collapse-to-icons mode is implemented). Navy gradient background. Brand
  block (seal + wordmark, min-height = header). Nav organized into labeled
  sections: *Overview*, *Registry*, *Assistance*, *Administration* — labels
  are 0.65 rem uppercase, 30%-white. Links are full-width `<button>`s with
  inline Feather-style SVG icons (18 px, stroke 1.8): default 55% white;
  hover white 90% on a 6% white overlay; **active = gold text on 10% gold
  background plus a 3 px gold bar flush to the left edge**. Gold pill badges
  show record counts (clients/scholars/transactions). Footer: user card
  (34 px initials avatar on teal gradient, name, role) inside a subtle panel.
- **Topbar** (`style.css:422-492`): sticky, 64 px, white, bottom border +
  xs shadow. Left: hamburger (hidden ≥1024 px) + breadcrumb
  "2DMIS › {Page}" (current page bold). Center-right: global search — pill
  input, 320 px, magnifier icon inside, searches/filters clients live and
  jumps to the Clients page on Enter. Right: notification bell with red count
  badge opening a 340 px dropdown panel (unread items get a gold tint; "Mark
  all read"; footer "View all notifications"), the pulsing teal
  "Online · Single Device" session indicator, and an outline Logout button.
- **Navigation behavior** (`app.js:2370-2374`, `780-792`, `2629-2633`):
  delegated click handlers switch pages via `showPage()` — toggles
  `.page.active`, syncs the sidebar active link from `data-page`, updates the
  breadcrumb from `PAGE_NAMES`, and closes the mobile drawer. Escape closes,
  in priority order: open modal → open details panel → notifications menu
  (`app.js:2423-2429`).
- **Responsive navigation**: ≥1024 px the sidebar is permanently visible and
  the hamburger hidden; <1024 px the sidebar becomes an off-canvas drawer
  (`translateX(-100%)`, 280 px wide on phones) opened by the hamburger over a
  dimmed backdrop (`rgba(12,22,34,.5)`); main content margin resets to 0; the
  topbar indicator hides.

### 5.3 Page structure

- `.page-container` padding 28 px (20×16 px on mobile).
- Every page opens with `.page-header`: flex space-between, wrap, 16 px gap,
  24 px bottom margin — left side is h1 (1.5 rem) + muted subtitle paragraph;
  right side `.page-actions` holds outline secondary actions and a gold
  primary action (e.g., "Export CSV" outline + "Add Client"/"New Transaction"
  gold).
- Content sits in white surfaces (`.data-card`, `.card`) on the gray
  workspace; grid utilities `.grid-2`/`.grid-3` (24/18 px gaps) compose
  dashboard sections; `.metrics-grid` is a 4-column KPI band.
- Section rhythm inside cards: 18–24 px padding blocks separated by
  `--border-light` hairlines (card header → toolbar → scrollable table →
  footer).

### 5.4 Component inventory

- **Cards** — `.metric-card` (white, radius-lg, sm shadow, hover lift −2 px,
  3 px gradient top-accent bar in navy/gold/teal/red, 42 px tinted icon tile,
  trend pill ▲up-teal/▼down-red); `.data-card` (+ `-header` with toolbar slot,
  `-footer` with count + pager); generic `.card/.card-body`;
  `.profile-card` with navy-gradient header (used by profile views);
  placeholder cards for unimplemented milestones (Payouts/P5, Access Control/
  P7, Audit Logs/P7 — centered icon + heading + description).
- **Tables** — `.data-table`: uppercase 0.72 rem muted sticky header row on
  `--bg`; 12×16 px cells; hairline row separators; hover tint. Sortable
  headers carry a `↕` indicator that turns gold when active
  (clients: name/municipality/barangay; transactions: name/date/amount).
  Clickable rows (`.row-clickable`) get a gold hover tint, reveal a chevron
  in a dedicated last cell, and are keyboard-focusable (`tabindex="0"`). Name
  cells pair a 32 px initials avatar with the formal name. Money cells use
  the Outfit mono style. Row actions are 30 px bordered icon buttons
  (`.row-action`, danger variant hovers red). Tables scroll horizontally
  inside `.table-scroll` instead of widening the page (min-width floor 620 px
  on mobile).
- **Buttons** — `.btn` base (11×24 px, radius 8, 0.88 rem/600, active scale
  .97); variants: `btn-primary` (navy→light gradient, used on login submit),
  `btn-gold` (gold gradient, navy text — the primary action on content
  pages), `btn-outline`, `btn-danger` (ghost red border → solid red fill on
  hover, used for destructive confirms); sizes `btn-sm`, `btn-xs`,
  `btn-icon` (36 px square).
- **Forms/inputs** — uppercase 0.78 rem labels; `.form-input` with 1.5 px
  border, radius 8, focus = gold border + `--shadow-glow` ring; selects get a
  custom SVG chevron (appearance:none); `.form-grid` two-column grid with
  `.span-2` full-width fields (single column <768 px); `.form-hint` helper
  text; `.form-actions` row; textarea vertical resize. Multi-value picker
  pattern: `.prog-select` container holding selected teal chips with × remove
  buttons, a search field, and grouped pill option buttons
  (`.prog-groups/.prog-btn`) — used for program selection and the grantee
  self-update form.
- **Filters** — single-select `.filter-chip` pills (scanner program selector);
  multi-select popover system: `.filter-multi-btn` (funnel icon,
  `aria-haspopup`/`aria-expanded`, `.has-filters` border state, count badge),
  popover menu (280 px, radius-lg, xl shadow) containing a search field,
  checkbox list (max-height 220 px scroll, navy `accent-color`), footer hint,
  and outside-click/Escape dismissal; applied selections render as removable
  navy chips (`.filter-chip-select`) in an `aria-live="polite"` region with a
  red "clear" action. On mobile the popover becomes a fixed bottom sheet.
- **Search** — topbar global search (live-filters clients; Enter navigates)
  plus per-table pill search inputs positioned right of filter groups; both
  reset pagination to page 1 on input.
- **Badges** — `.status-badge` pills with leading dot:
  paid/active=teal, approved=blue, pending=amber, rejected/red=danger,
  archived=gray; light-on-dark overrides exist for badges shown on the navy
  panel header. `.program-tag` neutral chips; gold count badges in the
  sidebar; notification unread dot; `.ip-chip` mono chip for update-log IPs.
- **Tabs** — `.page-tabs` segmented control (container on `--bg-alt`,
  radius-lg, 4 px inner padding; active tab = white pill with sm shadow and
  navy text) hosting the Scholars page's five sub-tabs (Scholars / GIP
  Profiles / Scholarship Reports / Update Log / Grantee Self-Update) with
  proper `role="tablist"/tab/tabpanel` + `aria-selected` wiring; scrolls
  horizontally with hidden scrollbar, goes edge-to-edge on mobile.
- **Pagination** — client-side; 32 px square bordered buttons («, numbered,
  »), active = solid navy, disabled = 40% opacity; paired footer text
  "Showing X–Y of N {entity}". Per-page sizes: 8 (clients, transactions,
  scholars), 6 (households).
- **Modals** — JS-built overlay (`rgba(8,16,30,.55)`, top-aligned at 7 vh):
  `.modal` 560 px (`.modal-lg` 780 px), max-height 86 vh, radius-xl; navy
  gradient header with title/subtitle/close button; scrollable body; footer
  on `--bg-alt` with right-aligned buttons. `openModal()` stores and restores
  focus, traps Tab, locks body scroll (ref-counted), focuses the first field;
  `confirmDialog()` returns a Promise with Cancel + danger/gold confirm
  (confirm focused on open). Used for CRUD forms (client/transaction/
  household/scholar), QR display (200 px QR box), and deletions.
- **Slide-in details panel** — `<aside role="dialog" aria-modal="true">`
  fixed right, `--panel-w: 480px`; 50 vw on tablet; full-width sheet on
  mobile. Structure: navy-gradient header (close button; 64 px avatar tile;
  name + subtitle; status badges with contrast overrides; 58 px white QR box)
  → actions row (Edit / Print / Generate Certificate / Archive / Delete as
  flexible thirds) → scrollable body of sections (uppercase micro-title with
  trailing hairline): Personal, Household, Contact, Programs & Services
  (chips), Government IDs, Notes (amber-left-border callout), Timeline
  (vertical line + gold dot nodes), Documents, Audit info — each a 2-column
  label/value grid. Opened by clicking/Enter/Space on any registry row; opens
  with scroll lock, `aria-hidden="false"`, focus moved to the close button;
  closed via close button/backdrop/Escape with focus restored to the
  originating row; Tab is trapped in both directions. **No backdrop ≥1024 px**
  (table stays visible/interactive per spec §10; CSS comment cites the spec).
  A second renderer reuses the same shell for scholar/GIP profiles (scholar,
  exam results, scholar log/contact sections).
- **Alerts/toasts** — no traditional alert banners anywhere; feedback is via
  toasts: bottom-right stack, navy pill-card with gold check icon,
  `role="status"` inside an `aria-live="polite"` container, auto-dismiss
  (~2.6 s + 300 ms fade-out). Inline informational banner pattern:
  `.p6-notice` (gold-tinted, icon + strong lead) — used for the Scholars
  "In progress (P6)" notice. Login-flow note under the submit button states
  "Single-device session enforcement active".
- **Empty/loading/error states** — empty tables render a full-colspan muted
  centered row ("No clients match your filters." etc.); milestone-placeholder
  pages show icon + "Coming Soon" copy; filter/program pickers have explicit
  "no options match" strings. **No skeleton/spinner loading components exist
  anywhere in the prototype** (mock data renders instantly) — a factual gap
  relative to the component checklist. Error handling appears only as confirm
  dialogs; there is no dedicated error-state component.

### 5.5 Dashboard patterns

Dashboard composition (in order): page header with Export + New Transaction →
4 KPI metric cards (Total Registered Clients, Assistance Transactions, Total
Amount Disbursed ₱, Pending Approvals — each with icon tile, trend pill,
accent color) → quick-action chips row (Add Client, New Transaction,
Register Household, Scan QR [display:none]) → two-column band: Recent
Transactions mini-table (with "View All" jumping to the Transactions page)
beside Program Distribution (pure-CSS horizontal gradient bars, percentage
widths, transaction counts) → three-column band: Announcements (icon list
with dates), Activity Calendar (month nav ‹ ›, event dots in gold, today
highlighted solid navy, legend), Recent Activity feed (initial-avatar entries
with timestamps).

### 5.6 Responsive behavior

| Breakpoint | Changes observed |
|---|---|
| ≤1200 px | metrics-grid 4→2 columns; grid-3 →2 columns |
| ≤1023 px | off-canvas sidebar drawer + hamburger + backdrop; content margin 0; topbar indicator hidden |
| 768–1023 px | details panel width 50 vw |
| ≤767 px | everything single-column (grids, scanner layout, profile grid, forms); sidebar 280 px drawer; details panel full-width sheet; `.data-table` min-width 620 px in scroll wrapper; topbar search hidden; touch targets enlarged (buttons ≥42 px, inputs font-size 16 px to prevent iOS zoom, row actions/pagination 36 px, filter checks 40 px); modal footers stack; tabs edge-to-edge; filter popovers become bottom sheets |

Spec §12 requires testing at 1440/1280/1024/768/576/430/390/375/320 px; the
CSS implements three structural tiers (desktop / tablet / mobile) rather than
scaling the desktop layout down.

### 5.7 Accessibility-relevant design patterns

- Skip-link ("Skip to main content", gold bg/navy text) as first focusable
  element; smooth-scroll html.
- Global `:focus-visible` = 3 px gold outline, offset 2; additional explicit
  outlines for modal controls and row actions; focused rows get an inset gold
  outline.
- Landmarks/roles: `<nav aria-label>`, `<aside aria-label="Primary">`,
  dialog roles with `aria-modal`, `role="status"` toasts, `role="listbox"`
  filter menus, tablist/tab/tabpanel semantics.
- Full keyboard support: rows focusable and activatable with Enter/Space;
  Tab trapped (both directions) inside open panel and modals; Escape closes
  modal → panel → menus in priority order; focus moves into dialogs on open
  and restores to the trigger on close.
- Labeled controls: `aria-label` on every icon-only control; `aria-expanded`
  on hamburger/filter/notification toggles; `aria-current` on pagination;
  `aria-hidden` on decorative icons and empty calendar cells; `aria-live`
  regions for toasts and applied-filter chips.
- Contrast-conscious tokens (darkened muted text, light-on-dark badge
  variants on navy headers) — documented in CSS comments.

### 5.8 Design tokens summary

- **Colors:** see table §5.1 (11 core/accent tokens + 6 surface/border + 4
  text tokens).
- **Typography:** Inter body / Outfit headings+numerals; 14 px root; scale
  from 0.65 rem micro-labels to 1.85 rem metric values (page titles 1.5 rem).
- **Spacing:** page gutter 28 px (20/16 mobile); card padding 18–28 px; grid
  gaps 18/24 px; control paddings 5–14 px; section rhythm ~24 px.
- **Radius ladder:** `--radius-sm` 6 px, `--radius` 8 px, `--radius-lg` 12 px,
  `--radius-xl` 16 px, `--radius-pill` 999 px (pills for chips/badges/search).
- **Shadows:** five-step neutral ramp `xs→xl` based on `rgba(15,27,45,…)`
  plus `--shadow-glow` gold focus ring; hover elevation via shadow-md/lift.
- **Sizing/layout:** sidebar 260 px (collapsed token 72 px unused), header
  64 px, details panel 480 px, modals 560/780 px, icon tiles 32–42 px,
  avatars 32–72 px, pagination squares 32 px (36 px mobile), touch minimum
  42 px buttons on mobile.
- **Motion:** `--ease cubic-bezier(0.4,0,0.2,1)`, `--duration 200ms`; named
  keyframes fadeIn (pages, 300 ms), slideUp (login card, 500 ms), pulse
  (online dot, 2 s loop), scanRotate (scanner frame, 2 s loop), toastIn
  (300 ms); panel slide 340 ms; modal fade/scale 250–300 ms. (Spec §15 asks
  for 150–250 ms; the implementation uses up to 340 ms for the panel.)
- **Icons:** no icon font/library — inline Feather-style SVGs, stroke
  currentColor, stroke-width 1.8–2, sizes 14–20 px, consistently paired with
  text or given aria-labels.

### 5.9 Information architecture & navigation patterns

- Flat SPA IA: 9 top-level destinations grouped in four labeled sidebar
  sections (Overview: Dashboard; Registry: Clients, Households; Assistance:
  Scholars, Transactions, Scanner Engine, Payouts; Administration: Access
  Control, Audit Logs). Payouts, Access Control, and Audit Logs are
  milestone placeholders in the prototype while the record modules are fully
  interactive mocks.
- The Scholars destination consolidates five related screens as sub-tabs
  within one page rather than separate sidebar entries.
- Wayfinding: breadcrumb in the topbar mirrors the active page; active nav
  item gets gold highlight + left bar; page identity repeated in the h1.
- Cross-navigation: dashboard widgets deep-link to modules ("View All",
  quick actions, Scan QR); global search routes Enter to the filtered Clients
  page.
- Detail-level IA: registry row → persistent slide-in profile panel
  (never a separate route/view); create/edit flows open as modals over the
  list; destructive actions require promise-based confirmation.
- Session identity surfaced twice: sidebar footer user card and login note /
  topbar "Online · Single Device" indicator.

### 5.10 Notable UX conventions recorded in the prototype (facts)

1. Viewing a record never navigates away — clicking any registry row opens
   the persistent right-side details panel (spec §10 requirement, fully
   implemented with keyboard parity and focus management).
2. Gold is the universal "primary action" color on content pages; navy is
   structural (headers, gradients, selected states); red appears only for
   destructive/critical elements; status colors are semantically fixed
   (paid/active teal, approved blue, pending amber, rejected red).
3. Uppercase letter-spaced micro-labels are the consistent device for section
   titles, form labels, and table headers.
4. List screens follow one template: toolbar (filters left, pill search
   right) → dense table (sticky header, sortable columns, avatar+name cells,
   status pills, icon row-actions, chevron affordance) → footer with
   result-count text and numeric pager; every new query resets to page 1.
5. Feedback model: non-blocking navy toasts (bottom-right) for success/simulated
   actions; promise-based modal confirms for deletes; inline tinted banners
   for contextual notices; audible-free visual scan simulation with a result
   placeholder card on the scanner page.
6. Accessibility is built in rather than retrofitted: skip link, gold focus
   rings, dialog focus traps with restoration, complete aria wiring, and
   mobile zoom prevention (16 px inputs).
7. Identity presentation is uniform: initials-avatar tiles (gradient by
   category) beside formal names everywhere — tables, panels, activity feeds
   — and a QR box embedded in the profile panel header.
8. Self-service flows pair the form with an explanatory "What happens next"
   numbered step-list panel (grantee self-update tab).
9. Update-log entries expose IP addresses as mono chips; reports advertise
   CSV export "UTF-8 BOM".
10. The prototype preserves v1 vocabulary throughout (Clients, Households,
    Scholars, GIP, Scanner Engine, program names, "Single Device") per spec
    §5 — terminology continuity is itself a design constraint.

---

## PHASE 3 — CURRENT V2 UI ANALYSIS

Sources inspected for this section (direct reads): `layouts/app.blade.php`,
`dashboard.blade.php`, `clients/index`, `clients/_form`, `clients/_details`
(consumed by both the show page and the list slide-over), `households/index`,
`transactions/index`, `scholars/index`, `payouts/attendance`,
`scanners/scan`, `sessions/online`, `admin/permissions/pages`,
`auth/login`, and `unpaid_verifications/self-service` (the shell partials
`partials/navbar` / `partials/sidebar` were inventoried in §2). Repo-wide
greps over `resources/views` quantified cross-cutting conventions: `@media`
usage, ARIA attributes, `role=` attributes, native `alert()`/`confirm()`,
Bootstrap badge usage, uppercase/text-transform mechanisms, and
jQuery/DataTables loading. All findings below are facts observed in the
working tree on 2026-08-23; no judgments, comparisons, or recommendations.

### 6.1 Application shell (current)

Restating §2.1 with structural detail relevant to later phases:

- One layout (`layouts/app.blade.php`, 116 lines) serves all 31 authenticated
  pages; the 7 public pages render their own standalone shells.
- Fixed dark navbar (`navbar-dark bg-dark`, z-index 1030): hamburger toggle,
  municipal seal image, "2D MIS" brand link, and a user dropdown
  ("Welcome, {username}") whose only item is a Logout form button.
- Fixed left sidebar, 220 px wide, `#212529`, starting at `top: 56px`,
  independently scrollable, rendered by the ACL-gated partial (§2.4). Link
  states: default `#adb5bd`; hover/active white on `#343a40`; active
  detection via `request()->routeIs()` patterns.
- Collapse interaction: toggling adds `.collapsed` (`margin-left: -220px`) to
  the sidebar and `.shifted` (`margin-left: 0`) to `.content`, CSS transition
  `0.3s ease`. There is **no breakpoint-based behavior** — the same margin
  toggle runs at every viewport width; no off-canvas drawer, backdrop, or
  overlay exists.
- Content area: `margin-left: 220px; padding: 76px 24px 24px`; body
  background `#f8f9fa`; all cards globally rounded (`border-radius: 1rem`).
- Flash region directly above `@yield('content')`: dismissible warning alert
  (`session('login_status')`) and a dismissible danger alert iterating
  `$errors->all()` (one `<div>` per message).
- Inline JS after the Bootstrap bundle: sidebar toggle listener plus a
  session watchdog polling `GET /session/status` every 2 seconds —
  `another_device` triggers `alert('Your account was logged out by the
  system.')` followed by redirect to `/login`; `logged_out` redirects
  silently; fetch failures are logged to console.
- The shell contains no breadcrumb, no global search, no notification
  affordance, no session/device indicator, and no footer.
- Extension points: `@stack('styles')` in `<head>`, `@stack('scripts')`
  before `</body>`, `@yield('title')` defaulting to `'2D MIS'`.

### 6.2 Page structures (current)

**Canonical authenticated list-screen template** — observed nearly
identically on Clients, Households, Transactions, and Payout Attendance
(structurally on the other DataTables screens):

1. Everything inside one white card: `card shadow-lg border-0 p-4`.
2. Header row: `h3.mb-0` title left; right-aligned small buttons (create =
   `btn-success btn-sm "+ Add …"`; module-specific extras such as
   `btn-danger btn-sm` Remove Duplicates or `btn-primary btn-sm` linked
   scanner shortcut / Export dropdown).
3. Optional dismissible success alert from `session('success')`.
4. Filter toolbar above the table: labeled `form-select form-select-sm`
   selects and `form-control form-control-sm` date inputs laid out with flex
   utilities (`min-width:200px` inline hints); right-aligned Filter
   (`btn-primary btn-sm`) + Reset (`btn-secondary btn-sm`). Municipality
   select cascades barangays through `GET geography/barangays`.
5. `table-responsive` wrapper containing a DataTables-enhanced
   `table table-striped table-bordered table-sm w-100` with `thead
   table-dark`; tbody populated by server-side processing over a POST
   `*/data` JSON feed.
6. DataTables default chrome supplies the search box, length menu,
   pagination controls, "Showing X to Y of Z" info, and processing overlay.
7. Trailing Actions column fixed per screen via an inline-style
   `.actions-col` rule (170 px clients, 120 px transactions/households,
   100 px payouts) holding tiny buttons (`padding: 2px 6px;
   font-size: 11px`) and/or inline POST forms.

Variations from the canonical template (facts):

- Transactions filters submit via a GET `<form>` whose submit handler is
  intercepted to redraw the table; Reset is a link back to the unfiltered
  route; four date-range inputs exist; Export is a `btn-group` dropdown that
  serializes current filter values into an export query string.
- Scholars renders outside the card convention entirely: `container-fluid`,
  bare `h1`, Add Scholar button, plain striped table — no `table-dark`
  header, no filters, no actions column.
- Sessions/Online and the admin permission screens are static
  server-rendered Bootstrap tables without DataTables.

**Form pages** (Clients create/edit share `clients/_form`; scholar and
household forms follow the same shape):

- Plain `<form>` with `row`/`col-md-*` grids; each field is label + input +
  optional red asterisk `<span class="text-danger">*</span>` for required +
  `@error` rendered as `<small class="text-danger">`.
- Labels generally lack `for`/`id` bindings (exceptions include login's
  username/password and some scanner fields).
- Uppercase-enforced text fields carry `class="form-control uppercase"`
  (resolution differs per view — see §6.8).
- Inline JS in the partial wires: birthdate change computing readonly Age and
  auto-filling readonly Category (MINOR/YOUTH/ADULT/SENIOR bands);
  Municipality→Barangay cascade; IP=YES revealing the IP-group select;
  "+ Add another" appending up to 5 affiliated-organization selects
  (`alert()` beyond the fifth).
- Footer: right-aligned Cancel/Return (`btn-secondary`) + Save
  (`btn-primary`).

**Detail/profile pages** (`clients/show` rendering `_details`; the same
markup doubles as slide-over content):

- Card header: "Client Profile" h3 plus a wrapped action cluster — Back vs
  "Open full page" depending on the `$panel ?? false` context, ACL-guarded
  "+ Add Transaction", Photo modal trigger, Edit, Delete (inline form with
  `onsubmit="return confirm(...)"`).
- Body: photo column (180×200 image or bordered "No photo" placeholder) and a
  three-across grid of muted-label/value pairs (name parts, birthdate/age,
  category, sex, civil status, PWD/IP(+group), address, contact,
  occupation/income, precinct/voter ID, affiliated orgs).
- Hairline-separated sub-sections: Household (two-row mini-table linking to
  the household), Family Members (striped table with `@empty` colspan muted
  row), Transactions (striped table with `@empty` row), then the GIP
  partial.
- Slide-over mode: `clients/index` fetches `clients.show {id}?panel=1` HTML
  into a Bootstrap Offcanvas body (`width: min(680px, 94vw)`), shows a
  `spinner-border role="status"` placeholder while fetching, and re-executes
  embedded scripts via an `executeScripts()` clone-and-replace helper.

**Admin configuration screens** (`admin/permissions/pages` representative):
user picker is a GET form whose select auto-submits `onchange`; super-admin
grant is a bordered `bg-warning-subtle` checkbox card whose toggler prompts
native `confirm()` and reverts itself on cancel; the permission matrix is a
single-column checklist table with a header "Check All" master checkbox;
save via `btn-primary`.

**Scanner screen** (one shared template driven by `config/scanner.php`):
centered `h3` title; optional constant-field inputs (Date Applied / Date
Paid / Amount Paid) per config `ui.fields`; `#reader` div (max-width 500 px)
hosting html5-qrcode's own UI; result area hidden until scan — an
`alert alert-info` block filled with bold key/value lines (seat modes render
SECTION/BOX/ROW/SEAT as 1.25 rem red `#dc3545` bold lines); Confirm
(success) and Cancel/Scan Again (secondary) buttons; `generic_form` mode
reveals a full transaction form (program select; beneficiary radio trio
self/custom/existing with disabled-state juggling; live client-search
`list-group` dropdown; transaction fields; Cancel + Save Transaction); a
shared notification modal displays every outcome and its OK button runs a
config-dependent continuation (`resumeAfterModal()` re-renders the scanner
vs `location.reload()`); success/error `<audio preload="auto">` elements play
on modal show.

**Public standalone pages** (`unpaid_verifications/self-service`
representative): own minimal HTML document loading the Bootstrap bundle
itself; system-ui font stack (login uses Roboto); single centered card
(`max-width:600px`); progressive disclosure by JS toggling `d-none` sections
(debounced name autocomplete over a custom absolute-positioned suggestions
list → municipality + Verify → attendance choice → optional proxy sub-form →
dynamically constructed final-confirmation modal containing a "You can only
submit once" warning → success box); errors surface as swapped-in Bootstrap
alerts or native `alert()`. Login: centered card (`col-12 col-sm-8 col-md-6
col-lg-4`), 80 px seal, contextual warning alerts (expired/forced sessions),
danger alert for the first username validation error, autofocus username,
full-width primary submit.

### 6.3 Components currently in use (inventory)

| Component | Implementation observed |
|---|---|
| Cards | Single Bootstrap `card shadow-lg border-0 p-4` wrapping whole screens; global 1 rem radius |
| Tables | DataTables (`table-striped table-bordered table-sm`, `table-dark` heads) on 9 screens; plain bordered/striped tables elsewhere; `table-responsive` wrappers throughout |
| Forms | Native `form-control`/`form-select` (+ `-sm` sizes), grid rows, `@error` red small text, `old()` repopulation, readonly computed fields |
| Buttons | Bootstrap color classes only: primary (save/filter/view/edit), success (create actions, scan confirm, save photo), danger (delete/duplicates/force logout), secondary (cancel/reset/close), outline-secondary (photo/open page), warning (Proxy), grouped dropdown (export) |
| Alerts | Dismissible session/validation banners (layout + ~10 views); inline swapped alerts on public pages; `alert-info` reused as scanner result container |
| Modals | Bootstrap modals: payout record viewer, scanner notification modal, client photo capture modal, duplicates compare/delete, dynamically built confirmation modal on unpaid self-service |
| Slide-over | Exactly one Offcanvas (`offcanvas-end`, clients index) hosting the profile partial |
| Badges | Exactly 4 `badge bg-*` occurrences repo-wide: `bg-info` selected-count (duplicates), `bg-primary rounded-pill` member count (household show), `bg-success` Head-of-Household flag, `bg-success` Online status |
| Dropdowns | Navbar user menu; transactions export menu |
| Tabs | None — no tab component exists anywhere in v2 |
| Pagination | DataTables built-in pager only |
| Loading states | DataTables `processing` overlay; one `spinner-border role="status"` (client panel fetch); literal "Loading..." strings (payout modal body, barangay cascade option) |
| Empty states | Blade `@forelse/@empty` colspan muted rows (family members, client transactions, online users); DataTables default zero-records message; "No matches" suggestion div |
| Error states | `catch` handlers showing `alert()` or inline red text ("Failed to load client details."); validation errors re-rendered server-side |
| Audio | Two `<audio preload="auto">` elements on the scanner (success/not-found MP3 assets) |

### 6.4 Dashboard (current)

`dashboard.blade.php` is 10 lines: one `card shadow-lg border-0 p-4`
containing an `h3 "Dashboard"` heading and a muted "Welcome, {username}."
paragraph — no widgets, statistics, quick actions, or navigation tiles.
Route `/` and the sidebar "Dashboard" entry land here.

### 6.5 Screen coverage recap

The complete screen-by-screen inventory (7 public + 46 authenticated screens
with routes, views, and gates) is maintained factually in §3 and remains
accurate as of this phase — no screen was added or removed. This phase's
representative deep-reads cover the templates listed at the top of §6.

### 6.6 Responsive behavior (current)

- **Zero `@media` queries exist in any routed view.** The only `@media`
  match in the views tree is inside the unrouted Tailwind starter stub
  `welcome.blade.php`.
- All width adaptation therefore comes from stock Bootstrap utilities and
  components: `col-md-*`/`col-sm-*` grids collapsing to one column,
  `table-responsive` horizontal scrollers, `flex-wrap` toolbars,
  `modal-dialog-centered`, login column widths, and the Offcanvas component.
- The shell itself has no mobile strategy: navbar, 220 px sidebar, and
  content margins are identical at every width; the hamburger performs the
  same permanent collapse at desktop and mobile; `table-responsive` produces
  very wide horizontal scrolls for the 21-column clients and transactions
  tables (payouts additionally sets DataTables `scrollX: true`).
- Public pages constrain themselves with max-width cards and stack via grid
  utilities; the unpaid suggestions list is absolutely positioned at
  z-index 2000.

### 6.7 Accessibility characteristics (current, quantified)

Grep totals over `resources/views/**/*.blade.php`:

| Signal | Count | Observed usage |
|---|---|---|
| `aria-label` | 17 | Mostly `aria-label="Close"` on dismiss buttons; other icon-only controls largely unlabeled |
| `aria-hidden` | 7 | Modal/offcanvas scaffolding |
| `aria-labelledby` | 4 | Client panel, payout/scanner modals |
| `aria-expanded` | 3 | Navbar user menu and export dropdown toggles |
| `aria-controls` | 1 | Export dropdown |

Further observations:

- `role=` attributes appear only on Bootstrap scaffolding: dismissible alert
  containers (`role="alert"`, roughly a dozen locations across the flash
  banners) and a single `role="status"` spinner in the client panel loader.
- Form labels are visually present on nearly all fields but most lack
  `for`/`id` associations; selects generated by JS (affiliated-org clones,
  cascaded barangay options) carry no programmatic labels.
- Interactive rows (clients list) respond to mouse click only — no
  `tabindex`, keyboard handler, or row-level affordance exists.
- Feedback relies on native `alert()` (12 files) and native `confirm()`
  (11 files) — blocking dialogs with no aria-live announcement.
- No skip-link, no custom visible-focus styling beyond browser defaults, no
  focus management beyond Bootstrap's built-in modal/offcanvas behavior, no
  `aria-live` regions for dynamically swapped content (suggestion lists and
  inline error boxes are silent to assistive tech).

### 6.8 Current visual/design conventions (de-facto tokens)

There is **no token layer**: colors and sizes come straight from Bootstrap
defaults plus per-view inline values.

- Palette in service: Bootstrap primary blue (filter/save/view buttons),
  success green (creates/confirms), danger red (destructive), secondary gray
  (cancel/reset/close), warning yellow (Proxy button), `bg-dark #212529`
  navbar/sidebar/table heads, body `#f8f9fa`, white cards; ad-hoc accents:
  scanner seat lines `#dc3545`, `alert-info` cyan scan results,
  `bg-warning-subtle` admin callout, `#ccc` borders on public suggestions.
- Typography: Roboto 400/500/700 loaded by the layout and login; the unpaid
  and grantee self-service pages instead use a `system-ui, Segoe UI, Roboto…`
  fallback stack; base sizes untouched (16 px) with local overrides —
  DataTables cells forced to `font-size:12px` inline (clients/households) or
  `0.8rem/0.85rem` rules elsewhere, action buttons 11 px, screen titles use
  default `h3` sizing.
- Elevation: uniform `shadow-lg` on every authenticated screen card;
  `shadow-sm` on public self-service cards.
- Radius: global `1rem` card radius from the layout CSS; everything else
  Bootstrap defaults.
- Uppercase convention: an `uppercase` class appears in 7 views but resolves
  three different ways — (a) JS `value.toUpperCase()` binding in
  `clients/_form` (transforms value and display), (b) local
  `text-transform: uppercase` CSS rules in transactions create/edit,
  `qr/viewer`, and the two self-service pages (display only), (c) inert in
  `scanners/scan`'s generic form (class present, neither rule nor binding
  defined there).
- Spacing: `p-4` card padding, Bootstrap spacing scale, ad-hoc inline
  `min-width` hints on filters, fixed `.actions-col` widths per screen.

### 6.9 Repeated UI patterns and inconsistencies (facts)

Repeated patterns:

1. Per-view CDN duplication: each of the 9 DataTables screens re-emits the
   same jQuery/DataTables/BS-integration script tags and DataTables CSS link
   inside its own `@push` blocks; the layout loads neither library.
2. CSRF handling varies by screen: `$.ajaxSetup` header injection (clients,
   households indexes), per-request explicit headers (transactions, payouts),
   URL-encoded bodies with token headers (scanner, unpaid self-service), and
   `<meta name="csrf-token">` reads on public pages.
3. Destructive-action guards are uniformly native `confirm()` — delete
   client/household/payout/duplicate, force logout, super-admin grant,
   transaction delete.
4. Success feedback after redirects is uniformly a dismissible green flash
   alert; AJAX paths instead use `alert()` or modals — there is no unified
   toast mechanism.
5. The Municipality→Barangay cascade against `geography.barangays` is
   copy-pasted in at least five call sites (clients index, households index,
   transactions index, clients `_form`, scanner generic form).
6. Row-click → slide-over detail exists on exactly one registry (Clients);
   other modules open dedicated show pages, plain links, or modals.

Inconsistencies (each a direct observation):

1. **Scholars list runtime dependency issue:** `scholars/index.blade.php`
   calls `$`/`DataTable()` but pushes neither jQuery nor DataTables (and the
   layout ships neither), so the initialization cannot run; its inline
   script additionally contains a stray closing brace (`});`) after the
   ready-handler. As written the screen renders a headed but empty static
   table with console errors.
2. Title hierarchy mixes a bare `h1` (Scholars) with card-wrapped `h3`
   headings everywhere else; several views define no `@section('title')` and
   fall back to the layout default `'2D MIS'`.
3. DataTables page-length defaults differ (25 for clients/households/payouts/
   scholars-intent vs 10 for transactions) and length menus vary
   (`[25,50,100]` vs `[10,25,50,100]`).
4. Filter interaction differs: Apply-button + JS redraw (clients,
   households, payouts) vs GET-form submit interception (transactions); Reset
   is either JS clearing or a route link.
5. Button color semantics drift: creation is `btn-success` on most lists and
   forms but saving is `btn-primary` on the scanner and photo modal; Edit is
   `btn-primary` in the client panel header yet tiny primary buttons appear
   inside data rows elsewhere.
6. The `uppercase` convention resolves through three different mechanisms
   (§6.8), one of which is inert.
7. Detail-viewing models differ per module: off-canvas partial fetch
   (clients), full pages (households show, transactions show), modal
   description list (payouts), `prompt()`-based Client-ID edit (scholars),
   row-inline editing (transactions).
8. Status presentation differs: plain text cells (transactions/details
   tables) vs the four badge uses listed in §6.3 vs bold red seat lines
   (scanner results).
9. Escaping practice varies: barangay filter options are inserted via a
   jQuery text-escaping helper, while the payout modal and scanner result
   renderers interpolate server values directly into HTML template literals.
10. Checkbox multi-select with a live counter badge exists only on the
    duplicates screen; no shared selection pattern is used elsewhere.

### 6.10 Preserve-worthy functional/UI implementation patterns

Facts about behavior current features rely on; any future UI work interacts
with these mechanisms:

1. **ACL-gated navigation:** every sidebar link wrapped in
   `AccessControlService::canAccessPage()`/`canAccessProgram()` checks using
   v1 page keys, mirroring `page:`/`action:` route middleware (§2.4).
2. **Server-side DataTables contract:** 9 screens depend on POST `*/data`
   endpoints returning `{data:[...]}` rows with pre-rendered `actions` HTML;
   clients additionally relies on hidden-but-searchable column defs and
   `data-id` row attributes for its click-to-panel handler.
3. **Panel partial-fetch mechanism:** `clients.show?panel=1` returns partial
   markup consumed by the Offcanvas; embedded scripts re-executed via
   `executeScripts()`; the `$panel ?? false` flag switches header buttons.
4. **Config-driven shared templates:** `scanners/scan.blade.php` and
   `payouts/attendance.blade.php` branch on `$config`/`$variant` (modes,
   field lists, seat columns, titles, linked routes); the scanner receives
   its runtime contract as a `@json($scannerJs)` blob.
5. **Session watchdog contract:** every layout page polls
   `GET /session/status` every 2 s and interprets
   `another_device`/`logged_out` statuses — the UX surface of single-device
   enforcement.
6. **Geography cascade endpoint:** `geography.barangays` consumed by 5+
   call sites expecting `[{id,name}]` JSON.
7. **Inline-edit protocol:** transactions row editing converts between
   display and wire formats (m/d/Y ↔ ISO dates, comma numbers ↔ floats) and
   posts a JSON payload to `transactions.inline-update`, updating only the
   affected row on success.
8. **Export query builder:** transactions export links serialize current
   filter state into `export_mode` + filter query parameters.
9. **Photo pipeline:** camera capture writes a base64 JPEG into a hidden
   `camera_image` field alongside a file-input fallback; media tracks are
   stopped on modal close/retake.
10. **Scanner loop mechanics:** html5-qrcode instance cleared on first
    successful decode; lookup/save POSTs keyed by an `action` field; an
    already-scanned recovery path uses `lookup_ignore_scan`; the notification
    modal's OK button drives config-dependent resume-vs-reload continuation.
11. **Public self-service contracts:** debounced grantee search, verify
    step, client-guarded one-shot submission behind a confirmation modal,
    proxy-vs-self branching feeding `is_proxy` payloads.
12. **Admin matrix mechanics:** GET-select auto-submit, check-all master
    checkbox wiring, super-admin revert-on-cancel confirm guard.

---

## PHASE 4 — PROTOTYPE ↔ V2 COMPARISON AND GAP ANALYSIS

Sources for this phase are the persisted analyses themselves: every prototype
fact from §5 was paired against the corresponding V2 fact from §2, §3, and §6.
Two targeted spot-checks were made in the prototype files for details Phase 2
recorded only implicitly — the scanner page composition
(`prototype/index.html:747-778`, `prototype/css/style.css:1034-1112`) and the
milestone-placeholder copy (`prototype/index.html:785-809`). No further
repository inspection was performed and no application code was modified.

Ground rules applied throughout (source-of-truth rules):

1. Laravel v2 is the functional source of truth; `prototype/` is the
   visual/UX source of truth.
2. A capability is only reported missing if no V2 route/view provides it
   under another name (checked against the §3 inventory).
3. Functional differences are not automatically gaps: wherever prototype
   behavior and V2 behavior disagree functionally, V2 wins and the prototype
   contributes at most presentation.
4. No recommendation below proposes changing backend/business logic merely to
   match the prototype. Nothing in this phase has been implemented.

### 7.1 Comparison methodology

The comparison was performed as a documentation-level cross-reference of the
two persisted analyses under six explicit lenses:

| Lens | What it compares | Primary evidence |
|---|---|---|
| Visual/UX | design tokens, typography, spacing, component styling | §5.1, §5.8 ↔ §6.8 |
| Functional | which actions exist and how they behave | §2.3, §6.10 ↔ §5.4, §5.10 |
| Structural | DOM/routing architecture — SPA page containers vs MPA Blade routes/views | §5.9 ↔ §3, §4.7 |
| Accessibility | semantics, ARIA wiring, keyboard support, focus management | §5.7 ↔ §6.7 (quantified) |
| Responsive | breakpoint behavior and mobile strategy | §5.6 ↔ §6.6 |
| Interaction patterns | click models, dialogs, feedback, scanner flows | §5.10 ↔ §6.9 |

Component/pattern findings use a fixed four-value status vocabulary:
**exists** (prototype pattern present in V2), **partial** (present but
narrower or inconsistent), **differs** (V2 has its own established pattern),
**absent** (no V2 counterpart anywhere).

### 7.2 Global shell comparison

| Shell element | Prototype (§5.2) | Current V2 (§6.1) | Finding |
|---|---|---|---|
| Sidebar | Fixed 260 px navy-gradient; four labeled sections (Overview / Registry / Assistance / Administration); inline SVG icon on every link; active = gold text + 10% gold background + 3 px gold left bar; gold count badges; footer user card with initials avatar | Fixed 220 px flat `#212529`; flat ACL-gated link list; no section labels, icons, badges, or user card; hover/active white on `#343a40` | **differs** — visual identity and wayfinding aids absent; V2's ACL gating is the stronger property (§2.4) |
| Navigation hierarchy | 9 top-level destinations grouped into labeled sections; Scholars consolidates five sub-tabs | ~24 flat entries, including 14 individually named scanner links and 3 payout variants | **structural difference** — V2 sidebar is longer and flatter; grouping is an IA improvement opportunity |
| Topbar | White sticky 64 px bar: hamburger (<1024 px), breadcrumb, global search pill, notification bell + panel, teal "Online · Single Device" indicator, Logout button | Dark navbar: hamburger, seal + brand, user dropdown whose only item is Logout | **partial** — account/logout equivalent exists; breadcrumb/search/notifications/session indicator have no shell counterpart |
| Breadcrumbs | "2DMIS › {Page}" on every page, synced to active nav | None anywhere | **absent** |
| Global search | Pill input live-filtering clients; Enter navigates to filtered Clients page | None at shell level; nearest analogs are the per-table DataTables search boxes | **absent** at shell level; table-level analog exists |
| User/account area | Sidebar footer user card (avatar, name, role) + topbar Logout | Topbar dropdown "Welcome, {username}" + Logout form button | **exists, differs** in placement and styling |
| Responsive navigation | <1024 px off-canvas drawer (280 px on phones) over a dimmed backdrop; hamburger hidden ≥1024 px | Identical margin-collapse toggle at every viewport width; no drawer, backdrop, or off-canvas mode | **gap** — no mobile navigation strategy (see §7.6) |
| Active states | Gold highlight + left edge bar; high visibility | Subtle background change via `request()->routeIs()` | **exists**, lower visibility |
| Information density | Systematic density: 0.85 rem cells, uppercase micro headers, compact controls | Comparably dense (`table-sm`, forced 12 px cells) but through inconsistent ad-hoc rules (12 px vs 0.8 vs 0.85 rem, §6.8) | Density parity; systematization gap |

Notes: both shells surface session identity — passively in the prototype
(indicator + login note), actively in V2 (the 2 s watchdog that alerts and
redirects). The V2 flash-alert region has no prototype counterpart; the
prototype deliberately uses no traditional alert banners (§5.4).

### 7.3 Page and information-architecture comparison

Screen-by-screen pairing (prototype destinations ↔ V2 screens per §3):

| Prototype destination | Prototype state | V2 counterpart(s) | Match quality |
|---|---|---|---|
| Login mock | Interactive mock (`#loginPage`, prefilled credentials) | `GET /login` (`auth/login`) | matched (V2 real) |
| Dashboard | Full composition: KPI band, quick actions, recent transactions, program distribution, calendar, activity feed (§5.5) | Placeholder card (§6.4) | partially matched — same destination, depth gap |
| Clients | Registry + slide-in details panel | Clients CRUD + duplicates review + show page + Offcanvas panel (`_details`) | matched |
| Households | Registry | Households CRUD + family-member add flow | matched |
| Scholars (tab 1) | Scholars list | `scholars.index` (+ create/edit/relink) | matched (V2 template diverges visually — bare h1/plain table, §6.2) |
| Scholars (tab GIP Profiles) | GIP profile view | `clients/_gip` inside the client profile | matched under different IA location |
| Scholars (tab Scholarship Reports) | Reports view | `scholarship-reports.index` | matched as separate route/page |
| Scholars (tab Update Log) | Update log | `update-logs.index` | matched as separate route/page |
| Scholars (tab Grantee Self-Update) | Self-update form + "What happens next" steps panel | Public `/grantee-update` standalone page | matched as public standalone route |
| Transactions | Registry with sort/filter/export | Transactions CRUD + inline edit + export + feed | matched |
| Scanner Engine | Simulated scan UI (frame animation, program chips, result placeholder) | 14 config-driven scanner screens (camera scanning, resume modes, audio) | matched; V2 exceeds prototype functionally |
| Payouts | Placeholder ("Coming Soon — P5 Milestone") | Payout attendance ×3 variants + linked scanners + unpaid-grantees admin list | V2 exceeds prototype |
| Access Control | Placeholder ("Administration — P7 Milestone") | Seven admin screens: create user, user management + password reset, permissions pages/actions/scopes, program permissions, multi-device exemptions | V2 exceeds prototype |
| Audit Logs | Placeholder ("Audit Trail — P7 Milestone") | Admin audit logs + leaderboard feed | V2 exceeds prototype |

Missing prototype UX patterns in V2 (none has a V2 equivalent under another
route — verified against §3): breadcrumb wayfinding; global search;
notification panel; dashboard widgets (KPI cards, quick actions, program
distribution bars, activity calendar, recent-activity feed); the
details-panel richness (timeline, documents, gov-ID, notes sections);
sortable-header affordances; filter-chip and multi-select-popover system;
toast feedback; styled empty states; "What happens next" companion step-list
panels on self-service forms; scan-frame visual framing. Caveat: most of
these are presentation-layer over existing data (dashboard counts, empty
states, framing); two require new data sources before they can exist at all —
the notification center (the prototype's own panel runs on mock data) and
global search (needs a backend endpoint).

V2-only screens/patterns (no prototype counterpart): Currently Logged Users +
force logout; duplicates review screen; family-member add flow; student
self-service trio (verify / update-photo notice / photo upload); unpaid
verification self-service + admin list/export; QR viewer; transactions inline
row editing; client photo-capture modal; password-reset UI; super-admin and
municipality-scope matrices; seat-mode scanner results
(SECTION/BOX/ROW/SEAT); audible scan feedback; the session watchdog itself.

Terminology differences:

| Concept | Prototype | Current V2 |
|---|---|---|
| Brand wordmark | "2DMIS" + "Municipal Assistance" tagline | "2D MIS" plain brand link |
| Administration destination | Single "Access Control" entry | Seven separate admin sidebar entries |
| Scanning | One "Scanner Engine" destination | 14 individually named scanner links |
| Payout tracking | "Payouts" | "Payout Attendance" + variant titles |
| Page identity | h1 + muted subtitle on every page | Card-wrapped `h3` (bare `h1` on Scholars); several views fall back to the layout default title `'2D MIS'` |

Both artifacts preserve the v1 module vocabulary (Clients, Households,
Scholars, GIP, program names, "Single Device") — spec §5's continuity
constraint is satisfied by both sides.

Navigation differences: the prototype's sub-tab consolidation maps to four
separate V2 routes plus one partial (Scholars cluster); deep-linking
dashboard widgets have no V2 analog (nothing to link from); the V2 sidebar's
AICS placeholder (`href="#"`) has no prototype analog.

### 7.4 Component comparison

Status vocabulary per §7.1: **exists / partial / differs / absent**.

| Component | Prototype pattern (§5.4) | Current V2 (§6.2–§6.4) | Status |
|---|---|---|---|
| Cards | Taxonomy: metric-card (accent bar, icon tile, trend pill), data-card (header/footer slots), profile-card | One whole-screen Bootstrap `card shadow-lg border-0 p-4` wrapper; no component taxonomy | **differs** |
| Tables | Custom `.data-table`: sticky uppercase header, hairline rows, avatar+name cells, sort ↕ indicator, chevron affordance, keyboard-focusable rows | DataTables striped/bordered `table-dark` chrome on 9 screens; static headers; mouse-only rows; comparable density | **differs** (density parity, language differs) |
| Forms | Uppercase micro-labels, gold focus ring, helper hints, span-2 grid, chip-based multi-picker | Bootstrap controls, labels mostly without `for`/`id`, `@error` small text, JS-dependent selects | **partial** — all functional primitives present, affordances missing |
| Buttons | Variant system: navy structural, gold primary, red destructive-only; sm/xs/icon sizes | Bootstrap color classes with semantic drift (success = create, primary mixed) | **differs** |
| Badges/status indicators | Systematic dot-pill system with fixed semantic colors + light-on-dark overrides | Exactly 4 `badge bg-*` uses repo-wide; status mostly plain text | **partial → mostly absent as a system** |
| Tabs | Segmented control with full tablist/tab/tabpanel wiring (Scholars) | Zero tabs anywhere in v2 | **absent** |
| Filters | Single-select chips + multi-select popovers + removable applied chips + bottom sheet on mobile | Select toolbar + Filter/Reset buttons (two interaction models, §6.9 #4) | **partial** — filtering works; richer UX absent |
| Modals | JS modal with focus trap, first-field focus, ref-counted scroll lock, promise confirms | Bootstrap modals (photo capture, payout viewer, scanner notification, duplicates compare, unpaid confirmation) with built-in focus/ESC handling | **exists, differs** in mechanics |
| Details panels | Persistent 480 px slide-in, sectioned body, no desktop backdrop | Single Offcanvas (clients, min(680px, 94vw)) + dedicated show pages elsewhere | **partial** — clients covered; coverage and treatment differ |
| Pagination | Square pager + "Showing X–Y of N" text | DataTables pager + info line | **exists, differs** styling only |
| Notifications/toasts | Bottom-right aria-live toast stack | Flash alerts after redirect + `alert()` on AJAX paths + scanner modal | **absent as a pattern** |
| Empty states | Styled muted colspan rows; picker "no match" strings | `@empty` rows, DataTables default zero-records message, suggestion div | **partial** |
| Loading states | None in the prototype (factual prototype gap, §5.4) | Processing overlay + `spinner-border role="status"` + literal "Loading..." strings | **V2 ahead** (prototype absent) |
| Confirmation dialogs | Promise-based modal (Cancel + danger/gold confirm, confirm focused) | Native `confirm()` in 11 files | **differs** |
| Scanner UI | Animated frame + labeled result card + program filter chips + simulate button | html5-qrcode native UI + alert-info result block + audio + notification-modal continuation | **differs**; V2 functionally richer |
| Search interfaces | Global pill search + per-table pill searches | Per-table DataTables search boxes only | **partial** |

### 7.5 Interaction-pattern comparison

1. **Row-click details** — prototype: every registry row opens the persistent
   panel, keyboard-activatable with Enter/Space. V2: clients index only,
   mouse click → Offcanvas fetch; other modules navigate to full pages, open
   modals, or use `prompt()`. Gap = coverage + keyboard parity.
2. **Create/edit workflows** — prototype opens CRUD as modals over lists.
   V2 uses dedicated pages with server-side validation re-render, `old()`
   repopulation, file uploads, and dependent-select JS. Copying modals
   literally would break these flows (see §7.9-B).
3. **Destructive confirmations** — promise dialog vs native `confirm()` in 11
   files. Replacement is presentation-layer: the cancel-aborts-submit
   semantics can be preserved exactly.
4. **Filters** — V2's two models (Apply-button + JS redraw vs GET-form
   interception) both work and both reset paging on query change; the
   prototype's chip/popover model adds a visible applied-state and quicker
   manipulation.
5. **Search** — per-table only in V2; Enter-to-navigate global search absent.
6. **Navigation** — SPA instant switching vs MPA full reloads: inherent
   structural difference, not a defect; perceived-latency mitigation
   (processing indicators) already partially exists via DataTables.
7. **Modal behavior** — Bootstrap 5 provides focus containment, ESC close,
   and restoration; the prototype additionally focuses the first field,
   ref-counts scroll locks, and restores focus explicitly. Roughly
   comparable basics; the prototype is more deliberate.
8. **Slide-in details** — V2's Offcanvas always renders a backdrop and closes
   on outside click; the prototype's desktop panel is deliberately
   backdrop-less so the table stays interactive (spec §10). Mobile sheet
   equivalence exists on both sides.
9. **Keyboard/Escape** — prototype implements a priority chain (modal →
   panel → menus). V2 relies on component defaults (ESC closes BS
   modal/offcanvas/dropdown); no custom chain, no row keyboard activation;
   the session watchdog surfaces state through blocking `alert()`.
10. **Feedback after actions** — V2 splits feedback by transport: PRG flash
    alerts after redirects, `alert()` on AJAX paths (§6.9 #4). The prototype
    uses one non-blocking mechanism everywhere. V2's model functions but is
    internally inconsistent.
11. **Scanner interactions** — V2 is authoritative and richer: camera
    scanning, success/not-found audio, resume-capable modes, ignore-scan
    recovery, config-driven continuation. The prototype contributes only
    visual framing (animated frame, labeled result card, program chips); its
    silent simulation must not override V2 behavior.

### 7.6 Responsive comparison

Factual anchor (§6.6): routed V2 views contain **zero custom `@media`
rules**; all width adaptation comes from stock Bootstrap utilities and
components. The prototype implements three structural tiers (§5.6).

| Viewport | Prototype | Current V2 | Assessment |
|---|---|---|---|
| Desktop ≥1024 px | Permanent sidebar, hamburger hidden, full layout | Permanent 220 px sidebar, full layout | broadly equivalent |
| Tablet 768–1023 px | Sidebar becomes drawer + backdrop; details panel 50 vw; metrics/grids reflow | Same fixed sidebar (or margin-collapse); grids reflow via `col-md-*`; Offcanvas fixed width | navigation-model gap; panel widths comparable |
| Mobile ≤767 px | Drawer 280 px + backdrop; single column everywhere; touch targets ≥42 px; 16 px inputs prevent iOS zoom; popovers become bottom sheets; tables scroll at min-width 620 px | Single column via grid utilities; same collapse toggle (no drawer/backdrop); `-sm` inputs/buttons keep default sizes (iOS zoom risk); wide horizontal scrolls incl. 21-column clients/transactions tables | largest responsive gap |

Bootstrap utilities cover grid collapse, flex wrapping, table scrolling, and
modal/offcanvas behavior — they do not provide nav-mode switching,
touch-target enlargement, zoom prevention, adaptive patterns (bottom sheets),
or hiding/reordering chrome. Bootstrap responsiveness therefore does **not**
automatically mean prototype-equivalent responsiveness. The exception is the
public/self-service pages: max-width cards plus utility stacking make them
adequately responsive without custom media queries.

### 7.7 Accessibility comparison

Quantified cross-reference (prototype §5.7 vs V2 §6.7):

| Dimension | Prototype | Current V2 |
|---|---|---|
| Semantic structure | Landmarks and roles throughout (`nav`, `aside`, `main`, dialogs, tablist) | `role=` only on Bootstrap scaffolding (alert containers, one status spinner) |
| Labels | Every icon-only control `aria-label`ed; form labels systematic | 17 `aria-label` total, mostly "Close"; most form labels lack `for`/`id` (exceptions: login username/password, some scanner fields) |
| ARIA state | `aria-expanded`, `aria-current`, `aria-hidden`, `aria-live` used systematically | `aria-expanded` ×3, `aria-labelledby` ×4, `aria-controls` ×1 |
| Keyboard navigation | Rows focusable + Enter/Space; Tab trapped both directions in modals/panel | Component defaults only (BS modal/offcanvas/dropdown); client rows mouse-only |
| Focus management | Focus moved into dialogs, restored to trigger; skip-link first element | Browser/component defaults; no skip-link |
| Focus-visible behavior | Global 3 px gold ring, offset 2 | No custom styling (browser default outline only) |
| Modal accessibility | `role="dialog"`, `aria-modal`, labelled, trap + restore | BS scaffolding; 4 `aria-labelledby` instances |
| Drawer/details-panel accessibility | Dialog semantics on the aside; focus moved/restored | Offcanvas component semantics (BS5 handles focus/ESC) |
| Touch target sizing | ≥42 px mobile buttons; 36 px row actions/pager | 11 px-font action buttons (small hit areas); default sizes elsewhere |
| Mobile input sizing | 16 px font prevents iOS zoom | Default/`-sm` sizes (iOS zoom risk) |
| Status communication | `role="status"` toasts in aria-live container; live region for applied filters | Dynamically swapped content silent (suggestions, inline errors); no aria-live regions |
| Native alert()/confirm() usage | None — component dialogs throughout | Primary mechanisms: `alert()` in 12 files, `confirm()` in 11 files — blocking, unstylable, inconsistently announced |

Summary: the prototype treats accessibility as built-in; V2 relies on
Bootstrap's baseline. The quantified counts show an order-of-magnitude
difference in explicit accessibility wiring, concentrated in label
associations, live-region feedback, keyboard row activation, and touch/input
sizing.

### 7.8 Visual/design-token gap analysis

V2 has no token layer (§6.8); the prototype defines its entire system in CSS
custom properties (§5.8).

Where V2 already aligns with the prototype:

- Red reserved for destructive/critical actions on both sides (Bootstrap
  danger ≈ the prototype's strict `--red` role).
- Near-white workspace behind white surfaces (`#f8f9fa` body vs
  `--bg #F0F2F5` / `--card #FFFFFF` — close in effect).
- Dark chrome concept for navbar/sidebar/table heads (hue differs:
  `#212529` flat vs navy gradient).
- Rounded-card language (global 1 rem sits within the prototype's 12–16 px
  ladder).
- Dense data-table presentation as a shared value.
- Contextual dismissible banners exist in both (flash alerts vs tinted
  notices), expressed differently.

Where V2 diverges:

- Primary action color: success-green creates/saves vs the gold `btn-gold`
  system; structural blue `#0d6efd` vs navy `#0038A8`.
- Typography: Roboto/system-ui split vs Inter body + Outfit headings; 16 px
  base vs 14 px root; ad-hoc forced sizes (12 px cells, 11 px action buttons)
  vs a documented 0.65–1.85 rem scale.
- Radius: single 1 rem card rule + Bootstrap defaults vs the 6/8/12/16/999 px
  ladder including pills.
- Shadows: uniform `shadow-lg` on every screen card vs the five-step ramp
  with hover elevation.
- Status colors: scattered ad-hoc uses (`bg-success`, `alert-info`,
  `bg-warning-subtle`) vs fixed semantic mapping (teal paid/active, blue
  approved, amber pending, red rejected).
- Sidebar/topbar: flat dark chrome vs navy-gradient sidebar + white bordered
  sticky header.
- Interaction states: browser-default focus/hover vs gold focus rings,
  tinted hover rows, hover lift, press scale .97.

Adopting prototype tokens is presentation-only; none of §6.10's functional
contracts depends on current visual values (inline values such as the
scanner's red seat lines encode appearance, not logic).

### 7.9 Functional-vs-visual conflict analysis

**A. Prototype UX adoptable without changing behavior (presentation-only).**
Design tokens, button/badge/status semantics, typography treatment, sidebar
grouping + icons + stronger active states, toasts rendering the *existing*
flash messages, modal confirms replacing native `confirm()` (same
cancel-aborts semantics), styled empty states, scanner result framing, table
visual treatment, skip-link/focus-visible/label wiring/touch targets. All of
these sit above the §6.10 contracts.

**B. Prototype UX that cannot be copied literally because V2 behaves
differently:**

- Create/edit-in-modals: V2 flows depend on server-side validation
  round-trips, `old()` repopulation, photo file upload, and dependent-select
  JS. Full-page flows must stay (progressive-enhancement modaling would be
  additive future work, not a copy).
- Persistent no-backdrop details panel: V2's panel is fetched HTML via
  `clients.show?panel=1` re-executed through `executeScripts()`. The visual
  treatment can be adopted; the fetch mechanism must remain.
- SPA transitions: V2 is server-rendered MPA — not adoptable structurally.
- Notification center and global search run on mock data in the prototype;
  real versions need new endpoints/event sources (functional additions,
  outside UX-copy scope).

**C. Cases where V2 behavior remains authoritative:**

- Single-device enforcement: V2 actively enforces (2 s polling, alert +
  redirect contract, §6.10 #5); the prototype merely displays an indicator.
  Any future indicator UI must be a passive display of the same
  `/session/status` data — never a replacement for enforcement.
- Scanner loop: audio feedback, resume modes, seat mapping, ignore-scan
  recovery, config-driven continuation stay exactly as built; the prototype's
  silent simulation contributes framing only.
- ACL-gated navigation truth: regrouping/redesigning the sidebar must not
  alter the `canAccessPage` wrapping or `page:`/`action:` middleware.
- Inline-edit protocol, export query builder, photo pipeline, public
  self-service contracts, admin matrix mechanics: unchanged by any visual
  adoption.

**D. Patterns to adapt rather than copy:**

- Scholars consolidation: keep the separate routes (each has its own ACL
  key); adapt by adding cross-navigation aids between scholars / scholarship
  reports / update log / GIP / grantee self-update instead of merging.
- Details coverage: extend the existing Offcanvas + `?panel=1` mechanism to
  other registries additively, rather than rebuilding the prototype's panel
  renderer.
- Uppercase convention: standardize on display-only `text-transform` while
  preserving `clients/_form`'s JS value binding — that binding transforms
  submitted values and therefore stored data (behavioral, keep).
- Dashboard: adopt the prototype's composition/layout; populate from existing
  services/feeds where possible; any new aggregation query is a read-only
  additive endpoint requiring an explicit decision (not silently assumed).

### 7.10 Gap matrix

| Area | Prototype | Current V2 | Gap | Impact | Recommended Direction |
|---|---|---|---|---|---|
| Mobile navigation | Drawer + backdrop <1024 px | Identical collapse toggle at all widths | No mobile nav mode | High | P0 — off-canvas drawer + backdrop (presentation-only) |
| Wayfinding (breadcrumbs/titles) | Breadcrumb + h1/subtitle everywhere | None; mixed heading hierarchy | Absent | Medium | P1 — breadcrumbs + consistent page-header pattern |
| Feedback model | Toasts (aria-live) + modal confirms | Flash alerts + `alert()`/`confirm()` | Blocking, inconsistent channels | High | P1 — toast layer over existing flashes + modal confirms (presentation-only) |
| Design tokens / brand identity | Navy/gold government token system | Stock Bootstrap + ad-hoc accents | No token layer | High | P1 — shared token stylesheet + semantic alignment (presentation-only) |
| Button color semantics | Gold primary, red destructive-only | Success-green creates, mixed primary | Semantic mismatch vs prototype | Medium | Fold into token work (presentation-only) |
| Status badge system | Systematic dot pills | 4 ad-hoc badge uses; plain-text status | Mostly absent | Medium | Badge component aligned to token colors (presentation-only) |
| Dashboard depth | KPI band, quick actions, widgets | Placeholder card | Large content gap on first screen | High | P1 — enrichment from existing data (aggregation decision required) |
| Row-click details | Universal, keyboard-accessible | Clients only, mouse-only | Coverage + a11y | Medium-high | P1 — extend `?panel=1` pattern + keyboard activation (additive views) |
| Accessibility scaffolding | Built-in (skip link, rings, traps, labels) | Bootstrap baseline only | Order-of-magnitude wiring gap (§7.7) | Critical | P0 — accessibility bundle (presentation-only) |
| Touch/input sizing | ≥42 px targets, 16 px mobile inputs | 11 px-font buttons, `-sm` inputs | Touch usability | High (mobile) | Included in P0 bundle (presentation-only) |
| Table visual language | Sticky uppercase headers, chevrons, avatars | DataTables dark-head striped chrome | Visual divergence | Low-medium | P2 — restyle within DataTables (presentation-only) |
| Tabs / Scholars IA | Five consolidated sub-tabs | Four routes + one partial | IA difference (not a missing feature) | Medium | P2 — cross-links between screens; no route merge |
| Empty/loading states | Styled empties; no loaders (prototype gap) | Defaults + spinner + strings | Minor polish | Low | P2 — standardize styled empties |
| Scanner framing | Animated frame, labeled result card, chips | Raw html5-qrcode UI + alert-info block | Visual only | Low-medium | P2 — reskin; keep audio/modal/resume flow |
| Shell search | Global pill search | Per-table search only | Absent at shell level | Low-medium | P3 — requires new search endpoint (functional addition) |
| Notification center | Bell + panel (mock data) | None | Absent + no data source | Low until product decision | P3 — defer; needs event source (functional addition) |

### 7.11 Prioritized recommendations

Format per item: problem → evidence → affected area/screens → recommended
direction → behavior impact. Nothing here is implemented.

**P0-1 — Mobile navigation strategy.**
Problem: the shell has no mobile mode; the desktop collapse toggle is the
only navigation control at every width, with no overlay/backdrop.
Evidence: §6.6 (zero `@media`, identical toggle), §5.6 (drawer + backdrop).
Affected: all authenticated screens at ≤1023 px.
Direction: off-canvas drawer + dimmed backdrop below a breakpoint, mirroring
§5.2 behavior; content margin resets when open.
Behavior impact: presentation-only.

**P0-2 — Accessibility baseline bundle.**
Problem: no skip link, no custom focus-visible styling, most form labels not
programmatically associated, icon-only controls unlabeled, tiny touch targets
(11 px-font action buttons), iOS-zoom-prone inputs, silent dynamic updates.
Evidence: §6.7 quantified counts; §5.7 prototype counterparts.
Affected: layout shell + every list/form screen (worst: clients/transactions
action columns, all forms, public self-service pages).
Direction: skip-link; global `:focus-visible` style; `for`/`id` wiring and
icon-button `aria-label`s; minimum touch-target sizing; 16 px mobile input
font; aria-live region for dynamically swapped messages. Native-dialog
replacement is handled separately in P1-1.
Behavior impact: presentation-only.

**P1-1 — Unified non-blocking feedback layer.**
Problem: three feedback channels (redirect flash, `alert()`, modals) with
blocking native dialogs in 12/11 files.
Evidence: §6.7, §6.9 #4; prototype toasts/confirms (§5.4, §5.10).
Affected: every delete/force-logout/super-admin guard, all AJAX error paths,
post-redirect successes.
Direction: render existing flash messages as toasts (`role="status"` in an
aria-live container); replace `confirm()` guards with a styled modal confirm
preserving cancel-aborts semantics; replace `alert()` error paths with
toasts.
Behavior impact: presentation-only (guards still abort submission; PRG flow
unchanged).

**P1-2 — Design-token layer + semantic alignment.**
Problem: no token layer; stock-Bootstrap palette and button semantics drift
from the intended government identity.
Evidence: §6.8 vs §5.1/§5.8.
Affected: all authenticated + public screens.
Direction: introduce a shared stylesheet defining the §5.1 palette
(navy `#0038A8`, gold `#FCD116`, teal/red/amber roles, radius/shadow ramps)
and map buttons/badges/sidebar/topbar onto it (gold primary, red
destructive-only).
Behavior impact: presentation-only.

**P1-3 — Sidebar IA grouping, icons, active-state emphasis.**
Problem: ~24 flat entries with no grouping/icons; weak active visibility.
Evidence: §7.2; §5.2 sections/icons/gold-bar pattern.
Affected: `partials/sidebar.blade.php` rendering only.
Direction: labeled sections (Overview / Registry / Assistance /
Administration mapping onto the existing entries), inline SVG icons, gold-bar
active state — preserving the per-link `canAccessPage` checks untouched.
Behavior impact: presentation-only.

**P1-4 — Scholars list runtime dependency fix.**
Problem: the scholars index calls `$`/`DataTable()` without loading
jQuery/DataTables (and has a stray brace), so the screen cannot initialize.
Evidence: §6.9 #1.
Affected: `scholars/index`.
Direction: push the same jQuery/DataTables blocks the other eight list
screens use; remove the stray brace.
Behavior impact: bug fix restoring intended function (no behavioral change —
the code already expresses this intent).

**P1-5 — Extend click-to-details beyond Clients.**
Problem: detail-opening models differ per module (pages/modals/prompt);
row-click detail exists only on Clients and is mouse-only.
Evidence: §6.9 #7; §7.5 #1.
Affected: households, transactions (and scholars once P1-4 lands).
Direction: reuse the proven `?panel=1` + Offcanvas mechanism against existing
show routes where feasible; add keyboard activation (tabindex +
Enter/Space) to clickable rows.
Behavior impact: additive view work reusing existing endpoints; new panel
partials are additive, not behavioral changes to controllers.

**P1-6 — Dashboard enrichment.**
Problem: the landing screen is a placeholder while the prototype defines a
KPI/widget composition users meet first.
Evidence: §6.4; §5.5.
Affected: `dashboard.blade.php`.
Direction: adopt the composition (metric cards, quick actions gated by the
same ACL checks, recent-transactions mini-table linking to existing screens);
populate from existing services/feeds first.
Behavior impact: UI-additive; if any new aggregation is needed it must be a
read-only endpoint decided explicitly (per §7.9-D) — no mutation logic.

**P2-1 — Table visual language + keyboard rows.**
Problem: DataTables chrome diverges from the prototype table language;
clickable rows lack affordances.
Evidence: §7.4 tables row; §5.4.
Affected: the nine DataTables screens.
Direction: restyle within DataTables (sticky uppercase header styling, hover
tint, chevron column on row-click screens); pair with P1-5 keyboard support.
Behavior impact: presentation-only.

**P2-2 — Scholars-cluster cross-navigation.**
Problem: five related screens scatter across sidebar/routes with no
interconnection (the prototype consolidated them as tabs).
Evidence: §7.3 navigation differences.
Affected: scholars, scholarship reports, update logs, client GIP section,
grantee self-update.
Direction: add mutually linking tab-like navigation aids on each screen;
routes and ACL keys unchanged.
Behavior impact: navigation-aid-only.

**P2-3 — Filter interaction consistency + applied-filter display.**
Problem: two interaction models (Apply+redraw vs GET-interception) and no
visible applied-filter state.
Evidence: §6.9 #4; prototype chips (§5.4).
Affected: clients, households, transactions, payouts filters.
Direction: unify Apply/Reset behavior and render removable applied-filter
chips above tables.
Behavior impact: interaction-consistency JS; query outcomes unchanged.

**P2-4 — Empty/loading state standardization.**
Problem: default DataTables zero-records strings and mixed loading idioms.
Evidence: §6.3; prototype empties (§5.4).
Affected: all list screens.
Direction: styled empty-row markup; unify page-length defaults/menus as
config-level polish.
Behavior impact: presentation-only (page-length defaults are UI state).

**P2-5 — Scanner result framing reskin.**
Problem: results render as a generic alert-info block; html5-qrcode chrome is
unbranded.
Evidence: §6.2 scanner; prototype scanner composition (spot-checked).
Affected: shared `scanners/scan.blade.php`.
Direction: styled result card + framed reader area; sounds, modal
continuation, and resume logic untouched.
Behavior impact: presentation-only.

**P3-1 — Global search.** Problem: no shell-level search. Evidence: §7.2.
Affected: topbar. Direction: pill search backed by a new read-only search
endpoint (clients-first; Enter → filtered list). Behavior impact:
**functional addition** (new endpoint) — requires explicit decision; not pure
UI.

**P3-2 — Notification center.** Problem: no aggregate event surface.
Evidence: §7.3 caveat — the prototype's own panel runs on mock data.
Affected: topbar. Direction: defer until a product decision names the event
source; do not fake it with static data. Behavior impact: functional addition
— deferred.

**P3-3 — Typography modernization.** Problem: Roboto/system-ui split vs the
Inter/Outfit pairing and micro-label convention. Evidence: §6.8, §5.1.
Affected: all screens. Direction: load Inter/Outfit via the token layer;
uppercase micro-labels display-only. Behavior impact: presentation-only.

**P3-4 — Motion/elevation polish.** Problem: uniform shadow-lg monotony; no
tokenized motion. Evidence: §6.8, §5.8. Affected: all cards/panels. Direction:
apply the shadow ramp + 150–250 ms transitions from tokens. Behavior impact:
presentation-only.

### 7.12 Preserve / Adapt / Replace / Defer

**PRESERVE** (already strong; must remain):

- Authorization-aware navigation truth: sidebar gating ↔ `page:`/`action:`
  middleware resolving one ACL service (stronger than anything in the
  prototype).
- Server-side DataTables contract (POST `*/data` feeds with pre-rendered
  actions HTML) — the backbone of nine screens.
- Config-driven shared templates (14 scanners, 3 payout variants) — scales
  better than the prototype's hand-built pages.
- Session-watchdog contract (single-device enforcement UX).
- Panel partial-fetch mechanism (`?panel=1` + script re-execution).
- PRG + flash-alert pattern, export query builder, photo pipeline, geography
  cascade, inline-edit protocol, self-service contracts, admin matrix
  mechanics (§6.10 in full).
- v1 vocabulary continuity (both artifacts already comply).
- Working functional depth beyond the prototype: payouts, access control,
  audit logs, duplicates, sessions, the self-service trio.

**ADAPT** (bring from the prototype, adjusted for V2 reality):

- Visual identity/token system onto existing structures (not a rebuild).
- Modal-confirm styling over existing guard semantics.
- Toast rendering of existing flash messages.
- Sidebar grouping/icons/active emphasis without touching gating.
- Prototype details-panel treatment applied to the existing Offcanvas
  mechanism, extended additively to other registries.
- Dashboard composition fed by real aggregates (decision-gated).
- Micro-label/uppercase conventions standardized display-only (keep the
  `_form` value binding).
- Scanner visual framing around the existing loop mechanics.

**REPLACE** (current V2 patterns that create a clear UX/accessibility
problem):

- Native `confirm()`/`alert()` as primary dialog/feedback mechanisms.
- Success-green-as-primary-action drift (semantic misalignment).
- Mouse-only row activation on clickable rows.
- The three divergent `uppercase` mechanisms (one inert) → one display-only
  rule.
- Uniform heavy `shadow-lg` flattening hierarchy (tokenized ramp instead).

**DEFER** (lower-priority enhancements; must not block implementation):

- Global search (needs new endpoint — product decision).
- Notification center (needs event source — product decision).
- Activity calendar / announcements widgets (content source undefined).
- Skeleton loaders (the prototype itself lacks them; low value today).
- Collapse-to-icons sidebar mode (unused even in the prototype).
- Print/certificate/archive actions from the details panel (no V2 backing
  functionality exists).
- Progressive-enhancement modaling of create/edit flows (large, risky;
  current pages work).

### 7.13 Phase 4 conclusion

**Strongest current V2 UX foundations.** Authorization-truth UI: menu
visibility and route access cannot drift because both resolve one ACL service.
Robust server-backed data workflows (DataTables POST feeds, inline-edit
protocol, export builders) that already handle production-shaped data.
Config-driven template families that scale (14 scanners, 3 payout variants
from two views). Functional depth beyond the prototype's own scope — payouts,
access control, audit logs, duplicates, sessions, self-service flows are all
live where the prototype still shows placeholders or mock panels.

**Largest prototype↔V2 gaps.** All experiential rather than functional:
(1) no mobile navigation strategy while the prototype has a full drawer tier;
(2) no shell wayfinding/feedback layer — breadcrumbs, global search,
notifications, toasts are absent and native dialogs/blocking alerts stand in;
(3) no design-token layer — stock Bootstrap plus ad-hoc accents instead of
the navy/gold government identity; (4) an accessibility baseline an order of
magnitude thinner than the prototype's built-in wiring; (5) a placeholder
dashboard against the prototype's KPI composition; (6) one registry with
click-to-details versus the prototype's universal panel pattern.

**Highest-priority improvements.** The two P0 bundles — mobile navigation
(P0-1) and the accessibility baseline (P0-2) — followed by the P1 set that
converts V2's solid functional skeleton into the intended experience: unified
non-blocking feedback (P1-1), token layer + semantic alignment (P1-2), sidebar
grouping (P1-3), the scholars dependency fix (P1-4), wider click-to-details
(P1-5), and dashboard enrichment (P1-6).

**Constraints that must remain during future implementation.** The v1-parity
database rules (additive-only schema work); ACL keys and middleware untouched
by any restyling; the §6.10 functional contracts preserved verbatim (watchdog,
scanner loop, panel fetch, feeds, pipelines); Laravel v2 remains functionally
authoritative — the prototype contributes visual language only; every change
from this point must state whether it is presentation-only or behavioral, per
the classification established in §7.9/§7.11.

---

## PHASE 5 — UI/UX IMPLEMENTATION PLAN

This phase converts the completed analysis (§1–§6), comparison (§7), and its
recommendations (§7.11) into an implementation-ready plan. It is **planning
only**: no Blade, PHP, JS, or CSS file has been touched, no package installed,
no route/controller/service/model/schema change proposed as done. Every file
reference below describes *future* work.

The plan deliberately stays inside the existing architecture — Laravel 12 +
Blade partials + Bootstrap 5.3.2 (+ jQuery/DataTables already in service) —
and introduces no frontend framework, build step, or npm dependency. The only
new artifacts it anticipates are plain files the current stack can serve
as-is: one shared stylesheet, one small shared script, and additional Blade
partials/components.

### 8.1 Implementation principles

These principles govern every future batch and are traceable to §7's findings:

1. **Preserve functionality.** Nothing that works today may stop working.
   The twelve functional contracts of §6.10 (DataTables feeds, `?panel=1`
   fetch, watchdog, scanner loop, inline-edit protocol, export builder, photo
   pipeline, geography cascade, self-service contracts, admin matrix
   mechanics, ACL gating, config-driven templates) are treated as frozen
   interfaces.
2. **Preserve business rules.** No controller/service/model change merely to
   make V2 resemble the prototype (rule 4 of §7). Where a recommendation
   touches data exposure (only P1-6 dashboard aggregates, P3-1 search), it is
   explicitly marked decision-gated and read-only.
3. **Preserve ACL/RBAC.** Sidebar links keep their per-link
   `canAccessPage()`/`canAccessProgram()` wrapping; routes keep `page:`/
   `action:` middleware. Restyling must never alter gate logic or ordering of
   checks. Any new quick-action/link repeats the same checks.
4. **Prototype as visual/UX reference** — tokens, spacing, component
   treatment, interaction polish — never as behavioral specification.
5. **V2 as functional source of truth.** Where prototype UX conflicts with V2
   behavior (modals-vs-pages CRUD, silent scan simulation, mock notifications),
   §7.9-B/C verdicts apply unchanged.
6. **Incremental implementation.** Small batches, each independently
   shippable and revertible; no big-bang restyle (risk register 8.13-11).
7. **Shared components over duplicated markup.** Repeated patterns (flash
   feedback, confirm dialogs, page headers, empty rows) move into shared
   partials/classes instead of growing new copies; this directly attacks the
   duplication documented in §6.9 (five cascade copies, nine re-emitted CDN
   blocks, three uppercase mechanisms).
8. **Minimal dependencies.** Bootstrap's existing components (Offcanvas,
   Modal, Toast, Dropdown) are used before any custom JS; inline SVG icons
   follow the prototype's own zero-dependency approach; no icon fonts, no
   bundler for live UI.
9. **Accessibility-first.** The P0-2 baseline (skip link, focus-visible,
   label wiring, live regions, touch/input sizing) lands with the foundations,
   not as an afterthought (§7.7 gap).
10. **Responsive-first.** Mobile/tablet behavior is designed with each change
    (off-canvas shell, stacking rules), not patched later (P0-1).
11. **Reversible changes.** Additive files first (stylesheet/script/partials);
    edits to shared files kept minimal and isolated per batch so a single
    revert restores prior state (rollback boundaries in §8.10).
12. **Verify after every implementation batch.** Each batch defines its
    verification method (§8.12) before work starts; a batch is not complete
    until verification passes and the log records results.

### 8.2 Implementation architecture

Roles assigned on top of the existing structure (no new layers):

| Artifact | Current role (facts, §2/§6) | Role in modernization |
|---|---|---|
| `resources/views/layouts/app.blade.php` | One layout for all 31 authed pages: navbar+sidebar includes, flash alerts, content yield, stacks, watchdog JS | **Primary shared touchpoint.** Enqueues the shared stylesheet/script, adds skip-link, hosts toast container for flash messages, gains breadcrumb/page-header hooks via existing `@yield`/`@stack`. Watchdog JS untouched. |
| `resources/views/partials/navbar.blade.php` | Hamburger toggle, seal+brand, user dropdown | Becomes the off-canvas toggler wiring point (Bootstrap native); visually tokenized. Global-search/notification slots are *reserved but empty* until their deferred decisions exist (P3-1/P3-2) — no fake UI. |
| `resources/views/partials/sidebar.blade.php` | Flat ACL-gated link list with `routeIs()` active logic | Keeps its ACL loop byte-for-byte in structure; gains section grouping wrappers, inline SVG icons, gold active-bar styling (class-level), optional footer user card using existing session data. This is where P0-1 (mobile drawer) and P1-3 land. |
| Existing view partials (`clients/_form`, `_details`, `_gip`, `scholars/_form`) | Domain-specific form/detail markup incl. inline JS | Stay domain-specific. `_details` gets the visual panel treatment and becomes the template for future sibling detail partials (households/transactions) under P1-5. |
| Individual page Blade files | Canonical list template + variations; per-view `@push` for jQuery/DataTables; inline styles/scripts | Receive class/markup-level changes only; their `@push` dependency blocks remain until a later consolidation decision (§8.13 risk 4); genuinely unique rules stay page-local. |
| Existing inline styles (24 views) | Only styling mechanism today; ad-hoc conventions (§6.8) | Progressively superseded by the shared stylesheet (§8.3); deleted per-screen once covered. Duplicated rules (uppercase transforms, actions-col widths audit, card idioms) move to shared CSS first. |
| Existing inline scripts (~61 occurrences / ~20 views) | DataTable init + AJAX feeds, cascades, pickers, scanner logic | Page scripts stay page-owned initially. Only cross-screen generic helpers (confirm dialog, toast emit, barangay cascade reuse) graduate into the shared script, behind data-attributes, late and carefully (§8.13 risks 8/10). |

**New shared artifacts (planned, not created):**

- `public/css/ui.css` — design tokens (:root custom properties) + component
  classes + responsive rules; loaded after the Bootstrap CDN link in the
  layout. Plain CSS, no build.
- `public/js/ui.js` — tiny vanilla helpers (toast emitter reading flash
  payloads rendered server-side, promise-based confirm modal wiring via
  `data-confirm`, keyboard row activation helper). Loaded by the layout after
  the Bootstrap bundle.
- Shared Blade partials: `partials/flash-toasts`, `partials/breadcrumbs`,
  `partials/page-header`, `partials/confirm-modal`; anonymous Blade
  components only if repetition justifies them (Laravel-native, no tooling).

**What should NOT be centralized:** scanner/payout config-driven branching,
page-specific DataTable column defs, public pages' self-contained heads (they
intentionally do not extend the layout), validation error rendering (server
round-trip contract).

### 8.3 Design-system consolidation plan

**Decision:** consolidate into **one plain shared stylesheet**
(`public/css/ui.css`) defining CSS custom properties (the prototype's §5.1/§5.8
token values) plus a small set of component classes, loaded by
`layouts/app.blade.php` immediately after the Bootstrap CDN `<link>`, and by
each standalone public page when that screen is modernized. Rationale: the
project already serves static assets from `public/` (`seal_logo.png`,
sounds), needs no build step, keeps diffs reviewable, and is fully reversible
(delete file + one layout line). Alternatives considered and rejected: a
Blade style partial (no caching/versioning), activating the unused Vite
pipeline (build tooling the constraints exclude), Tailwind/other frameworks
(excluded). Bootstrap remains the component foundation — there is no factual
reason to leave it.

Token adoption strategy: define `--ui-*` custom properties mirroring the
§5.1 values verbatim (no invented values): navy `#0038A8` (+`#164A9C`/
`#002B7F` gradient stops), gold `#FCD116` (+`#FFF8D6`),
teal `#2D8B7A`/`#34A08C`, red `#CE1126`/`#E8463A`, amber `#D4A900`,
blue-accent `#3B82F6`, surface/border/text tokens; radius ladder
6/8/12/16/999 px; shadow ramp xs→xl + gold focus ring; motion
`cubic-bezier(0.4,0,0.2,1)` / 200 ms; font variables initially resolving to
**Roboto / system-ui** (current reality) so the Inter/Outfit swap (P3-3)
remains a one-line decision in Batch I rather than a migration.

Bootstrap integration approach: override at the class level (e.g.
`.btn-primary { --bs-btn-bg: var(--ui-navy); … }`, `.page-link.active { … }`)
instead of editing compiled Bootstrap values; map semantic slots:

| Slot | Token | Notes |
|---|---|---|
| Structure/navigation | navy `#0038A8` | sidebar gradient, active nav bar, breadcrumb accents, pagination active |
| Primary content action | gold `#FCD116` (+navy text) | `.btn-gold`; replaces success-green-as-create drift (§6.9 #5) per screen group |
| Success/paid/online | teal | status badges only; buttons keep semantic green for true confirmations where already used, converging later |
| Destructive/critical | red `#CE1126` | danger buttons/confirms keep their role; hue aligned via token |
| Pending/warning | amber `#D4A900` | badges/callouts (`bg-warning-subtle` admin callout maps here) |
| Approved/informational | blue-accent `#3B82F6` | approved badges, info accents (replacing generic `alert-info` scan-result styling per P2-5) |

Per-area plan (all presentation-only unless noted):

- **Colors:** tokens above; workspace stays `#f8f9fa` initially (already
  near-equivalent to `#F0F2F5`, §7.8) — exact alignment deferred to Batch I to
  minimize early churn.
- **Typography:** font variables as above; scale documented from prototype
  (micro-label 0.65–0.72 rem uppercase letter-spaced → table headers, form
  labels, section titles; page title 1.5 rem); the ad-hoc forced sizes
  (12 px cells / 0.8 rem / 11 px action buttons, §6.8) are replaced by two
  shared rules (table cell sizing, action-button sizing) during screen-group
  migrations.
- **Spacing:** keep Bootstrap spacing scale + `p-4` card idiom; standardize
  page gutter and toolbar gaps via utility conventions already in use; no new
  grid system.
- **Radii/shadows:** ladder variables applied to cards/buttons/inputs/pills;
  uniform `shadow-lg` softened toward the mid ramp progressively (visual
  only).
- **Buttons:** shared variants (`.btn-gold`, tokenized primary/danger);
  migration is a per-screen class swap; sizes standardized on existing `btn-sm`
  usage.
- **Badges/status indicators:** one `.status-badge` dot-pill family with the
  fixed semantic mapping (paid/active teal, approved blue, pending amber,
  rejected red, archived gray) + light-on-dark overrides for navy headers;
  replaces the four scattered uses and upgrades plain-text status cells per
  screen group.
- **Cards:** canonical screen wrapper unchanged; add metric/data-card header/
  footer classes for dashboard and future compositions.
- **Tables:** restyle DataTables chrome via its Bootstrap-integration classes
  (uppercase muted headers, hairline separators, hover tint, navy active
  pager); sticky headers only where no `scrollX` conflict; no DOM
  restructuring of DataTables wrappers (risk 8.13-3).
- **Forms:** label/`for`/`id` wiring (markup sweep, Batch C/H), gold
  focus ring on `.form-control/.form-select`, hint text styling;
  uppercase convention collapses to one display-only rule (JS value binding in
  `clients/_form` preserved — it affects stored data, §7.9-D).
- **Alerts:** validation `$errors` block stays an inline alert (server-side
  round-trip contract); session flash migrates to toasts (§8.5 #12);
  contextual tinted notice styling (prototype `.p6-notice` analog) available
  for scanner/self-service informational blocks.
- **Status indicators:** covered by badge family; scanner seat-line color
  becomes a token reference without changing values.
- **Focus states:** global `:focus-visible` = gold outline, offset 2,
  matching §5.7; verified for contrast on white surfaces during Batch A.
- **Responsive conventions:** single source of truth in `ui.css` using
  Bootstrap's breakpoints (see §8.7 boundary note).

### 8.4 Shell modernization plan

All shell changes live in `layouts/app.blade.php` + `partials/navbar.blade.php`
+ `partials/sidebar.blade.php` (+ new shared partials), so every authenticated
screen inherits them without per-page edits.

**Sidebar (P1-3 + P0-1):**

- Keep the ACL loop exactly as built — each entry's
  `canAccessPage()`/`canAccessProgram()` check, the scanner/payout loops, and
  `routeIs()` active detection remain; only wrappers/classes are added around
  them.
- Wrap the sidebar content in Bootstrap's **responsive Offcanvas**
  (`offcanvas-lg`, native to the loaded 5.3.2 bundle): ≥992 px it renders as
  the existing fixed left column; <992 px it becomes a drawer opened by the
  existing hamburger via `data-bs-toggle`. This replaces the margin-collapse
  toggle's role on mobile while keeping one DOM source (no dual rendering).
  Backdrop, Escape, focus handling, and scroll lock come from Bootstrap.
- Add labeled section groups (Overview / Registry / Assistance /
  Administration) mapping onto the existing entries; inline Feather-style SVG
  icons (stroke currentColor, ~18 px) per entry, matching the prototype's
  zero-dependency icon approach; active state upgraded by CSS to gold text +
  left bar keyed to the classes the existing logic already emits.
- Optional footer user card (initials avatar + username from the already-
  available session user) — presentation-only.

**Explicitly preserved in the sidebar:** ACL-aware navigation (unchanged
checks), existing page authorization (`page:`/`action:` middleware untouched),
the AICS placeholder behavior (restyled at most, never given a route),
current functional navigation (same routes, same order within groups).

**Mobile navigation acceptance criteria:** drawer opens/closes via hamburger/
backdrop/Escape at <992 px; focus enters drawer on open and returns to the
toggler on close; body scroll locked while open; identical link set and
ordering at every width (same partial); content margin resets when drawer is
open; no horizontal overflow introduced.

**Topbar/navbar:** keep the dark navbar structure; wire the hamburger to the
offcanvas target; token-level visual alignment only (Batch C). Global search
and notification bell: slots reserved, deliberately **not rendered** until
their product decisions exist (P3-1/P3-2) — no mock UI.

**Breadcrumbs:** new `partials/breadcrumbs.blade.php` rendered inside a shared
`partials/page-header.blade.php` (crumb trail "Section › Page" + h1 +
subtitle) included at the top of `@yield('content')` usage per screen group;
derived from existing screen titles/sections — no routing change.

**Active navigation state:** unchanged detection (`routeIs()`), stronger
visual (CSS only); `aria-current="page"` added to the active link (markup
only).

**Account/user area:** dropdown preserved as-is; optional sidebar card noted
above; logout form untouched.

**Page title/wayfinding:** every view gains an explicit meaningful
`@section('title')` (removing silent `'2D MIS'` fallbacks, §6.9 #2) and the
shared page-header pattern replaces bare/card-wrapped heading variance —
markup-only, applied per screen group.

**Single-device behavior:** the watchdog JS, polling interval, statuses, and
alert/redirect contract remain byte-intact through all batches; any cosmetic
replacement of its `alert()` is isolated in Batch H behind an explicit
decision and two-device test (§8.6 interaction-affecting list).

**Responsive shell behavior summary:** ≥992 px permanent sidebar (hamburger
hidden or inert); <992 px off-canvas drawer + backdrop; topbar condenses
(brand + toggler + account); breadcrumb hides on the smallest tier (page
header retains wayfinding).

### 8.5 Component modernization plan

Planning-only inventory. "Location" = where the future change lives; nothing
exists yet.

| Component | Current V2 implementation | Target behavior/appearance | Likely location | Shareable? | Behavior change? | Risk |
|---|---|---|---|---|---|---|
| Buttons | Bootstrap color classes; semantic drift (success=create) | Token variants (`.btn-gold` primary action, navy structural, red destructive-only); unified sizing | `ui.css`; per-view class swaps | Yes | No | Low |
| Cards | Whole-screen `card shadow-lg border-0 p-4` | Canonical wrapper kept; add metric/data-card header-footer classes | `ui.css` (+ dashboard markup later) | Yes | No | Low |
| Tables | DataTables striped/bordered/dark-head on 9 screens; plain tables elsewhere | Restyled chrome: uppercase muted headers, hairlines, hover tint, navy pager; keyboard rows only where row-click exists | `ui.css` + init snippets | Yes (CSS), per-screen for rows | No (CSS); keyboard rows = interaction-affecting | Low–Med |
| Badges/statuses | Exactly 4 ad-hoc badges; plain-text status cells | `.status-badge` dot-pill family with fixed semantic colors (+on-dark overrides) | `ui.css`; class swaps per view | Yes | No | Low |
| Forms | BS controls; labels mostly unbound; 3 uppercase mechanisms | Bound labels, gold focus ring, hint styling; one display-only uppercase rule (JS binding preserved) | `ui.css` + attribute sweep | Partly (bindings per view) | No | Low |
| Filters | Two models: Apply+redraw / GET-intercept | Models kept; applied-filter chip row with remove/clear; consistent Reset affordance | New `partials/active-filters` + per-view hooks | Partly | Interaction-consistency only (query outcomes unchanged) | Medium |
| Tabs | None anywhere | Link-style `.page-tabs` for the Scholars cluster cross-navigation (anchors, `aria-current="page"` — separate routes preserved per §7.9-D) | `ui.css` + links on the five screens | Yes | Navigation aids only | Low |
| Modals | BS modals (photo, payout viewer, scanner notice, duplicates, unpaid confirm) | Shared skin via CSS (header/body/footer treatment); existing markup/attributes untouched | `ui.css` | Yes | No | Low |
| Confirmation dialogs | Native `confirm()` ×11 files | Promise-based modal confirm wired by `data-confirm` attributes; cancel still aborts submission | `partials/confirm-modal` + `ui.js` | Yes | Interaction-affecting (guarded semantics identical) | Medium |
| Slide-in details panels | One Offcanvas (clients) fed by `?panel=1` + script re-exec | Visual panel treatment (gradient header, sectioned body); mechanism reused; extended additively to households/transactions | Restyle in `clients/_details`; NEW sibling partials + index wiring | Per-domain partials, shared styling | Additive views; no logic change | Medium |
| Pagination | DataTables built-in pager | Color/shape restyle only (`page-link` active → navy) | `ui.css` | Yes | No | Low |
| Toasts/feedback | Flash alerts after redirect; `alert()` on AJAX paths | Server-rendered flash emitted into a BS toast stack (`role="status"`, aria-live polite); `toast()` helper replaces AJAX `alert()`s | `partials/flash-toasts` + layout container + `ui.js` | Yes | Presentation-channel change (non-blocking now) | Medium |
| Empty states | DT defaults; `@empty` rows; suggestion div | Styled empty-row markup + standardized DT language strings | `ui.css` + init snippet conventions | Yes (DT language) | No | Low |
| Loading states | Processing overlay; spinner; literal strings | Mechanisms kept; spinner markup standardized where literals exist | View-local minor edits | Partly | No | Low |
| Scanner UI | html5-qrcode raw UI in `#reader`; alert-info result block; audio; notification-modal continuation | Framed reader wrapper + labeled result card classes; program chips visual; **ids, config JS, sounds, resume flow byte-intact** | `scanners/scan.blade.php` markup wrappers + `ui.css` | Template already shared | No | High caution — smallest possible diffs, isolated commits |

### 8.6 Accessibility implementation plan

Grounded in the quantified §6.7 counts and §7.7 comparison. Each item is
classified as **markup/presentation-only** or **interaction-affecting**.

| Improvement | Plan | Class |
|---|---|---|
| Semantic HTML | Skip-link first in layout; `<main>` landmark around content yield; `aria-label` on nav/aside landmarks; single `h1` per screen via page-header partial | Markup-only |
| Labels | Sweep binding visible labels to inputs (`for`/`id`) across all forms incl. JS-generated selects (affiliated-org clones, cascaded barangays); associate `@error` smalls via `aria-describedby` where trivially possible | Markup-only |
| ARIA | Add `aria-label`s to remaining icon-only controls (beyond the 17 existing); `aria-expanded` on the sidebar toggler (BS provides); `aria-current="page"` on active nav + pagination | Markup-only |
| Status announcements | Toast container with `role="status"` inside `aria-live="polite"`; live region also receives panel-fetch "Failed to load client details." class messages that are currently silent | Markup-only container; message routing is presentation-level |
| Error messaging | Validation alerts remain inline (server contract) but gain heading + association; public pages' swapped-in alerts get role/announcement wiring | Mostly markup-only |
| Button/link semantics | AICS placeholder gets `aria-disabled` treatment (still no route); icon-only row actions gain labels during table restyle | Markup-only |
| Color contrast | Verify muted text/borders flagged in §6.8 (`#ccc` suggestion borders, placeholder grays, 11 px action text enlarged to shared sizing); fix by token values — no behavior impact | Presentation-only |
| Touch target sizing / mobile input sizing | Shared CSS at the mobile tier: controls ≥42 px hit areas, row-action buttons ≥36 px, inputs ≥16 px font (prevents iOS zoom) | Presentation-only (motor interaction improves; no logic) |
| Focus-visible | Global gold ring per §5.7; explicit outlines for modal controls and row actions | Presentation-only |
| Modal focus management | Continue relying on Bootstrap's trap/restore for all BS modals/offcanvas; verify each dialog type returns focus to trigger after Batch C restyle | Interaction-affecting (verification-led, no new code unless a gap is proven) |
| Drawer/details-panel focus management | Offcanvas-lg drawer + clients Offcanvas rely on BS handling; extended detail panels inherit it | Interaction-affecting (config-level only) |
| Keyboard navigation | Clickable rows gain `tabindex="0"` + Enter/Space activation via shared helper on screens that adopt row-click (clients first, then P1-5 extensions) | **Interaction-affecting** |
| Escape handling | Rely on Bootstrap defaults (modals/offcanvas/dropdowns already close on Escape); no custom priority chain planned — documented non-goal to avoid duplicating BS behavior | None (policy) |
| Native alert()/confirm() replacement | `confirm()` ×11 → modal confirm helper; AJAX-path `alert()` → toast helper; watchdog's `alert()` → optional styled modal + same redirect, isolated late-batch change behind decision + two-device test | **Interaction-affecting** (blocking→non-blocking changes timing of feedback) |

Sequencing note: all markup-only items ship with Batches A/C and the per-group
sweeps; every interaction-affecting item ships individually with its own
verification line in §8.12.

### 8.7 Responsive implementation plan

Boundary decision: use Bootstrap's breakpoints (the framework already loaded)
rather than the prototype's 1024 px tier — `lg` = 992 px becomes the
drawer threshold. This is an explicit adaptation (§7.6), accepted because
fighting Bootstrap's breakpoint system would add custom media machinery
without functional benefit. Three tiers:

**Desktop (≥992 px):**
- Sidebar permanently visible at 220 px; hamburger hidden/inert; content
  margin unchanged.
- Tables render full-width; hover affordances active; details panels open as
  offcanvas over a backdrop (current V2 behavior retained — §7.5 #8 notes the
  prototype's backdrop-less desktop variant is not adopted because V2's
  offcanvas mechanism is the established pattern).
- Acceptance: no layout shift vs today other than intended styling; all list
  screens usable without horizontal page scroll.

**Tablet (768–991 px):**
- Sidebar becomes off-canvas drawer with backdrop (P0-1); toggler visible.
- Filter toolbars wrap to two columns; modals remain centered and scrollable;
  detail panels keep `min(680px, 94vw)` width.
- Scanner layout stacks below its controls.
- Acceptance: drawer criteria from §8.4 pass; no element requires
  horizontal scrolling except intentional table scrollers.

**Mobile (<768 px):**
- Forms/filters stack single-column (grid utilities already provide;
  verified per screen).
- Touch targets ≥42 px; inputs ≥16 px font; row-action buttons ≥36 px within
  horizontally scrolling tables; pagination tappable (≥36 px squares).
- Wide tables (21-column clients/transactions) stay inside their scrollers
  with a visible edge affordance; **no columns removed or re-ordered**
  (functional parity).
- Modal footers stack; scanner controls thumb-reachable; result card stacks
  under the reader.
- Page-header breadcrumbs hide; h1 wayfinding remains.
- Acceptance: zero accidental page-level horizontal overflow (only
  `.table-responsive`/scrollX wrappers scroll); login/self-service flows
  completable one-handed; no iOS input zoom triggered anywhere.

Per-component responsive rules (tables, forms, filters, cards, panels,
modals, scanner, pagination, navigation) follow these tier criteria and are
implemented once in `ui.css`, not per view.

### 8.8 Dashboard modernization plan

Target composition mirrors the prototype's information architecture (§5.5)
populated only with data V2 actually has (§3/§4 inventory). No dashboard
query or code is created in this phase.

| Proposed element | Data classification | Notes / condition |
|---|---|---|
| KPI — Total Registered Clients | **DATA/ENDPOINT NEEDED** (read-only count) | Requires a decision-gated read-only aggregate; no such endpoint exists today |
| KPI — Assistance Transactions | **DATA/ENDPOINT NEEDED** (read-only count) | Same as above |
| KPI — Total Amount Disbursed ₱ | **DATA/ENDPOINT NEEDED** (read-only sum) | Same; display-only currency formatting |
| KPI — Pending Approvals | **VISUAL PLACEHOLDER ONLY** | No approval-workflow concept exists anywhere in the analyzed V2 routes/services; recommend omitting or substituting a real metric (e.g., transactions/scans today) — explicit product decision required |
| Quick actions: Add Client / New Transaction / Register Household | **REAL DATA AVAILABLE** | Routes exist (`clients.create`, `transactions.create`, `households.create`); each button must repeat the same `canAccessPage()` (+ action where applicable) checks the sidebar uses |
| Quick action: Scan QR → scanner deep-link | **REAL DATA AVAILABLE** (conditional) | 14 configured scanner routes exist; render links only for the user's permitted scanner pages; prototype hid its chip (`display:none`) — V2 can legitimately show real ones |
| Recent Transactions mini-table ("View All" → transactions index) | **REAL DATA AVAILABLE** (reuse) | Served by existing `transactions/data` feed/service query; embedding recent rows server-side is read-only reuse, no new business logic; "View All" is an existing route link |
| Program Distribution bars | **DATA/ENDPOINT NEEDED** (read-only GROUP BY) | No distribution aggregate exposed today; decision-gated like P1-6; render nothing if not approved |
| Announcements panel | **VISUAL PLACEHOLDER ONLY** | No announcements feature/source exists in V2; recommend omitting entirely until a content source exists (matches Phase 4 DEFER) — never fake content |
| Activity calendar | **VISUAL PLACEHOLDER ONLY** | No event-source concept; defer (Phase 4 DEFER list); do not render an empty decorative calendar on production |
| Recent Activity feed | **DATA/ENDPOINT NEEDED** (read-only, ACL-scoped) | Audit data exists but is gated behind `audit_logs.php`; showing it on a shared dashboard requires either restricting the widget to permitted users or a user-scoped read — explicit ACL-preserving decision required |

Implementation shape (future): static widget markup + metric-card classes from
`ui.css`; widgets render/unrender per classification and per ACL checks;
approved aggregates arrive via the DashboardController as view data only.
Behavior impact: UI-additive; any new aggregation is read-only and explicitly
decision-gated before implementation (principle §8.1-2).

### 8.9 Screen-by-screen implementation roadmap

Ordered groups using the §3 inventory (screen numbers referenced). No screen
is modified yet.

**Group 1 — Shared shell** (`layouts/app`, `partials/navbar`,
`partials/sidebar`, new shared partials/css/js)
Changes: asset enqueue, skip-link/main landmark, offcanvas-lg sidebar +
grouping/icons/active styling, flash→toast partial, page-header/breadcrumb
integration points.
Shared components: tokens, toasts, drawer, page-header.
Behavior risk: Medium (blast radius = every authed screen; mitigated by
additive-first + single-batch rollback).
Accessibility risk: High positive (P0-2 core lands here).
Responsive risk: High positive (P0-1 lands here).
Testing: full authenticated smoke of all 46 screens at 3 widths; ACL matrix
probe (limited vs super-admin link sets identical to today); artisan test
suite; console clean.

**Group 2 — Dashboard** (`dashboard.blade.php`; DashboardController only if
aggregates approved)
Changes: composition per §8.8 with REAL items first.
Shared components: metric-card, quick-action chips (ACL-checked), mini-table,
page-header.
Behavior risk: Low–Med (only if decision-gated reads approved).
A11y risk: Low (new markup done right from start). Responsive risk: Low
(grid utilities).
Testing: zero-permission user sees shell only; each widget's gate; empty-data
states render sanely.

**Group 3 — Registry/client screens** (§3 screens 3–11: clients
index/create/edit/show + `_form/_details/_gip`, duplicates, households
index/create/show, family members; scholars trio deferred to Group 4)
Changes: table restyle, badge/status cells, filter chip row, clients row-click
keyboard support, `_details` panel reskin, form label sweep, confirm-modal on
deletes.
Shared components: tables, badges, filters chips, confirm modal, forms, empty
states.
Behavior risk: Medium (duplicates delete flow; panel fetch contract must stay).
A11y risk: Medium (keyboard rows are interaction-affecting — tested
explicitly). Responsive risk: Low–Med (21-column table stays in scroller).
Testing: CRUD round-trips incl. validation re-render; duplicates compare/
delete; `?panel=1` fetch + script execution; geography cascade; verify-mobile;
photo capture modal incl. track stop.

**Group 4 — Scholars cluster + Transactions** (§3 screens 12–18)
Changes: **scholars runtime dependency fix first (P1-4)** — push the same
jQuery/DataTables blocks the other eight screens use, remove stray brace;
then scholars visual alignment + cluster cross-navigation tabs (P2-2);
transactions restyle, applied-filter chips, inline-edit untouched, export
untouched, delete confirm swap.
Shared components: tables, tabs-as-links, filter chips, badges, confirm modal.
Behavior risk: Medium-High (inline-edit protocol + export query builder are
the densest contracts in the app — changes strictly visual around them).
A11y risk: Medium. Responsive risk: Med (wide transactional table).
Testing: inline edit round-trip (m/d/Y ↔ ISO, comma ↔ float, row-only update);
export URL parity captured before/after; filter redraw; scholars DataTable
initializes with console clean; relink flow.

**Group 5 — Payouts + unpaid admin** (§3 screens 35–38)
Changes: attendance table/modal reskin (scrollX retained), status badges,
linked-scanner shortcut buttons tokenized; unpaid-verifications list/export
restyle.
Shared components: tables, modals skin, badges.
Behavior risk: Medium (seat-mode rendering variants ×3 configs).
A11y risk: Low-Med. Responsive risk: Med (scrollX interplay).
Testing: all 3 variants' data feeds; modal open/close focus return; seat
columns render; export parity.

**Group 6 — Scanners** (§3 screens 21–34, one shared template)
Changes: framing/result-card/chips visuals only; **zero JS-logic edits**
(loop mechanics, `@json($scannerJs)` contract, audio, resume/ignore-scan
paths frozen).
Shared components: scanner UI treatment (§8.5 #15), result card, notice
styling.
Behavior risk: High sensitivity → smallest diffs, isolated commits, no batch
co-mingling.
A11y risk: Medium (result announcements could gain live region — interaction-
affecting, optional, tested).
Responsive risk: Med (reader sizing on small screens).
Testing: config-driven matrix across all 14 keys × applicable modes: lookup,
save, duplicate path, resume-after-modal, ignore-scan recovery, sounds play,
generic_form beneficiary branching.

**Group 7 — Administration/configuration** (§3 screens 2, 19–20, 39–46:
sessions online, scholarship reports, update logs, users create/index,
permissions pages/actions/scopes, program permissions, exemptions, audit logs)
Changes: matrix screens get consistent card/table/form treatment; check-all
and auto-submit mechanics untouched; confirm swap on force-logout/super-admin
toggle; audit/update-log tables restyled; reports/export buttons tokenized.
Shared components: tables, forms, badges, confirm modal, empty states.
Behavior risk: Medium (permission matrices are sensitive but markup-stable).
A11y risk: Med (checkbox labels/master-checkbox semantics improved).
Responsive risk: Low.
Testing: permission update round-trips; check-all behavior; super-admin
revert-on-cancel; force-logout gate; audit leaderboard feed; reports export
parity.

**Group 8 — Public/self-service screens** (§3.1 screens 1–7)
Changes: token application to standalone heads (they add the shared CSS link),
focus-visible, labels, 16 px inputs/touch sizing, suggestion-list contrast;
progressive-disclosure JS contracts untouched (debounced search, one-shot
guard, proxy branching, photo flows).
Shared components: tokens, forms, buttons, alerts/toasts (public pages use
their own heads — partial reuse where trivial).
Behavior risk: Medium (public-facing, one-shot submission flows).
A11y risk: Med-High positive (these pages had the least wiring).
Responsive risk: Low (already self-constraining, §7.6).
Testing: full happy-path of each public flow; confirmation modal parity on
unpaid submission; student photo pipeline; QR viewer render; login error
states; mobile zoom absence.

**Group 9 — Remaining specialized screens**: covered transitively (sessions
online/audit/reports/logs assigned in Group 7; qr viewer/student pages in
Group 8; duplicates/family-members in Group 3).

### 8.10 Priority and sequencing

Priorities carry over from §7.11 (P0-1/P0-2 critical; P1-1..P1-6 high;
P2-1..P2-5 medium; P3-1..P3-4 polish). Practical execution order — nine
batches, each a rollback boundary (one coherent commit set; revert restores
the previous state):

| Batch | Contents (priority refs) | Dependencies | Affected files | Risk | Verification | Rollback boundary |
|---|---|---|---|---|---|---|
| **A — Shared foundations** | `ui.css` tokens+base, `ui.js` helpers, flash→toast partial, skip-link/main landmark, focus-visible, P0-2 markup-only items in layout | None (first) | NEW css/js; `layouts/app` (+ minimal navbar include point) | Low–Med | Suite + smoke of all screens at 3 widths; toast renders for success/error flashes; console clean | Revert layout line + delete new files |
| **B — Shell/navigation** | Offcanvas-lg sidebar drawer (P0-1), grouping/icons/active bar (P1-3), page-header/breadcrumb partials, title sweep start, account card (optional) | A | `partials/sidebar`, `partials/navbar`, `layouts/app`, NEW breadcrumbs/page-header partials | Med | §8.4 drawer criteria; ACL link-set parity probe; all-screens smoke; suite | Revert the three shell files + delete new partials |
| **C — Core components** | Button/badge/table/form/modal skins; confirm-modal helper replacing `confirm()` (P1-1 part); empty/loading standardization (P2-4); uppercase single-rule | A | `ui.css`, `ui.js`, NEW confirm partial; class swaps begin on Group-3 screens only | Medium | Delete-abort semantics test per converted screen; forms tab-order; DT screens visual QA | Per-screen commits; revert reverts swaps independently |
| **D — Dashboard** | Composition per §8.8 (REAL items; decision-gated reads only if approved) | A, B | `dashboard.blade.php`; DashboardController **only** if aggregates approved | Low–Med | Widget gate matrix; empty-data rendering; suite | Revert view (+controller diff if any) |
| **E — Registry/scholars/transactions** | Scholars dependency fix (P1-4) first; registry group changes incl. keyboard rows (P1-5 clients), panel extension households/transactions; transactions restyle with contract freezes (P2-1/P2-3) | A, B, C | Group 3 + Group 4 views/partials (+ NEW detail siblings) | Med–High | Contract tests: inline-edit round-trip, export URL parity, `?panel=1` script exec, duplicates flow; scholars console-clean check | Screen-level commits; scanner-free zone |
| **F — Scanners/public** | Scanner framing reskin (P2-5); public/self-service token+a11y pass | A, C | `scanners/scan`, 8 standalone views | High caution / Med | 14-key × mode matrix; public happy-paths; zoom/touch checks | Isolated per-file commits |
| **G — Administration/specialized** | Admin matrices, sessions, audit/reports/logs restyles; confirm swaps | A, C | Group 7 views | Medium | Permission round-trips; force-logout gate; export parity | Per-screen commits |
| **H — Accessibility/responsive cleanup** | Remaining interaction-affecting a11y items (keyboard rows on extended panels, watchdog alert decision), touch/input sizing sweep, contrast fixes, label completion (P0-2 completion) | A–G | Cross-cutting small diffs | Medium | Keyboard/SR spot-check list; two-device watchdog test if changed; mobile device pass | Item-level reverts |
| **I — Final consistency pass** | PageLength defaults unify, CDN block consolidation decision, remaining inline-style removal, optional font swap via token flip (P3-3), motion/elevation polish (P3-4), visual diff vs prototype | All | Residual views + `ui.css` | Low | Full visual QA checklist (§8.12); suite | Batch revert |

Deferred regardless of batch: global search (P3-1) and notification center
(P3-2) until product decisions exist.

### 8.11 File impact map

Classification for future implementation planning (no file is edited now).

**SHARED FOUNDATION** (touch once, benefit everywhere; highest review care):
`resources/views/layouts/app.blade.php`; `resources/views/partials/
navbar.blade.php`; `resources/views/partials/sidebar.blade.php`;
NEW `public/css/ui.css`; NEW `public/js/ui.js`; NEW shared partials
(flash-toasts, breadcrumbs, page-header, confirm-modal, active-filters).

**HIGH IMPACT** (dense functional contracts or public exposure; smallest
diffs, isolated commits):
`resources/views/scanners/scan.blade.php` (frozen loop mechanics);
`resources/views/transactions/index.blade.php` (inline edit/export/filters);
`resources/views/clients/index.blade.php` (feed + offcanvas + data-id);
`resources/views/payouts/attendance.blade.php` (3 config variants);
`resources/views/auth/login.blade.php`;
`resources/views/unpaid_verifications/self-service.blade.php`;
`resources/views/grantee_update/self-service.blade.php`.

**MEDIUM IMPACT**: `clients/create|edit|show` + `_form/_details/_gip`;
`households/*`; `family_members/create`; `duplicates/index`;
`scholars/index|create|edit` + `_form` (index also carries the P1-4 fix);
`transactions/create|edit|show`; `scholarship_reports/index`;
`update_logs/index`; `unpaid_verifications/index`;
`admin/users/create|index`; `admin/permissions/pages|actions|scopes|
programs|exemptions`; `admin/audit_logs/index`; `sessions/online`;
`students/verify|update-photo|photo-upload`; `qr/viewer`; `dashboard`.

**LOW IMPACT**: minor attribute/class-only edits expected (titles, labels,
badges): most files above during sweeps; no structural change anticipated.

**NO CHANGE EXPECTED**: `welcome.blade.php` (unrouted stub);
`resources/css/app.css`, `resources/js/app.js`, `resources/js/bootstrap.js`,
`vite.config.js` (starter scaffolding, unused by live UI);
`routes/web.php`; all controllers/services/models; `config/scanner.php`,
`config/payout.php` and all other config; everything under `database/`;
`composer.json`/`package.json` (no new dependencies);
`prototype/*` (read-only reference); `public/seal_logo.png`,
`public/sounds/*` (consumed as-is).

Special cautions honored: scanner view = frozen-logic zone; DataTables
screens = CSS-first only; public/self-service heads = self-contained (shared
CSS linked individually, never by converting them to the layout).

### 8.12 Testing and verification strategy

Baseline to protect: 213 tests / 1056 assertions green (`php artisan test`,
needs `C:\xampp\mysql\bin` on PATH) plus the twelve §6.10 contracts.

**FUNCTIONAL** (per affected batch, scripted checklist):
- Login/logout incl. expired + forced-session alerts on the login screen.
- ACL/RBAC: limited vs super-admin sidebar link sets identical to pre-change;
  direct-URL probe of gated routes still enforced; action-gated mutations
  (create/edit/delete/export) behave identically.
- Navigation: every sidebar entry reaches its screen; active state correct on
  each; breadcrumbs/page titles correct.
- CRUD workflows: clients/households/transactions/scholars create → validate
  error re-render → save → edit → show; family member add.
- Forms: dependent selects (municipality→barangay cascade ×5 call sites),
  mobile verify endpoints, birthdate→age/category computation, affiliated-org
  add/remove, uppercase field behavior unchanged (stored values included).
- Filters: Apply/Reset per model (JS redraw and GET-intercept); applied-chip
  removal equals manual clearing.
- Tables: each of the 9 DataTables screens — server POST `*/data` returns
  `{data:[...]}` with actions column HTML intact; search/length/pager work;
  zero-records path styled.
- Scanner: matrix over all 14 keys × applicable modes (lookup, save,
  duplicate, resume-after-modal, `lookup_ignore_scan`, seat modes, generic
  form branching); sounds fire; notification-modal OK continuation matches
  config (reload vs re-render).
- Payouts: all 3 variants' feeds; modal viewer; linked scanner shortcuts.
- Public/self-service: grantee search debounce → verify → one-shot submit
  behind confirmation (double-submit blocked); proxy-vs-self payload flags;
  student verify/photo flows; QR viewer; grantee self-update save.

**RESPONSIVE**: device/viewport pass at 1440 / 1280 / 1024 / 992 / 768 /
576 / 430 / 375 / 320: drawer criteria (§8.4), stacking criteria, touch/input
sizing, table scroller containment, no accidental page overflow, no iOS zoom.

**ACCESSIBILITY**: keyboard-only pass per modernized screen (tab order,
Enter/Space on rows, Escape closes dialog/drawer, focus returns to trigger);
labels announced (spot-check with a screen reader on login, clients form,
scanner result); live region announces flash/toast messages; focus-visible
visible on every interactive element; contrast spot-checks on new token
colors (gold ring on white, badges, muted text); touch target measurements.

**REGRESSION**: routes list unchanged (`artisan route:list` diff empty);
authorization tests green; business-rule tests green; browser console zero
errors on the 20 key screens (explicitly covers the scholars fix);
DataTables init on all 9 screens; watchdog two-device scenario (another
device logs in → current device alerted/redirected) after any Batch H change;
single-device exemption behavior untouched.

**VISUAL**: side-by-side comparison against prototype for shell/dashboard/
list/form patterns; token spot checks (colors/radii/shadows/typography via
computed styles); component-state coverage (default/hover/focus/disabled/
active) for buttons, inputs, pagination, badges; consistency sweep confirming
the retired inconsistencies from §6.9 stay retired.

### 8.13 Risk register

| # | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| 1 | Shared layout change breaks many screens | Medium | High | Additive-first foundations (Batch A), shell isolated in Batch B, full 46-screen smoke after each shell commit, single-commit rollback |
| 2 | Inline CSS conflicts / specificity wars during consolidation | High | Medium | Namespace shared classes; load `ui.css` after Bootstrap; migrate+delete page rules per screen in the same commit; grep audits for orphaned selectors |
| 3 | Bootstrap/DataTables styling interplay breaks tables or scrollX | Medium | Medium | CSS-first only; restyle via existing BS-integration classes; no DOM restructuring of DT wrappers; visual QA on all 9 DT screens |
| 4 | jQuery/DataTables dependency inconsistency (scholars bug class) | High (already manifest) | Medium | P1-4 fixed first in Batch E; checklist that any `$`/`DataTable()` usage has matching `@push`; console-clean gate |
| 5 | Scanner regressions | Medium | High | Frozen-JS zone; markup/CSS-only diffs; isolated commits outside other batches; 14-key × mode test matrix before merge |
| 6 | Modal/drawer regressions (focus, backdrop, dynamic content) | Medium | Medium | Rely on BS-tested behaviors; verify focus return per dialog type; special-case dynamically built modals (duplicates compare/delete, unpaid confirm) |
| 7 | Responsive table problems (wide tables unusable on mobile) | High | Medium | Keep `table-responsive`/scrollX; min-width floors preserved; no column removal/reorder; device pass at 375/430 |
| 8 | Accessibility changes affecting interaction (keyboard rows, confirm swap) | Medium | Medium | Ship interaction-affecting items individually; data-attribute wiring; explicit Enter/Escape/abort tests per converted control |
| 9 | ACL/navigation regressions | Low | High | Gate logic untouched by design; diff review proves wrappers-only; automated suite + limited-user link-set probe |
| 10 | Accidental business-logic changes | Low | High | Batches name allowed files (§8.11 NO CHANGE list enforced); controllers/services excluded unless decision-gated item approved; review diffs against the map |
| 11 | Large batches become unreviewable | Medium | Medium | Batch size caps (~≤10 files), one concern per commit, rollback boundary per batch, contract-heavy screens get dedicated commits |
| 12 | Flash→toast hides persistent messages (auto-dismiss losing context) | Medium | Low | Validation errors remain inline alerts (only session flash becomes toast); long/error-class messages get extended/no auto-dismiss |

### 8.14 Recommended implementation order

**A Shared foundations → B Shell/navigation → C Core components →
D Dashboard → E Core transactional screens (scholars fix first) →
F Scanners/public → G Administration/specialized → H Accessibility/
responsive cleanup → I Final consistency pass.**

Why this order minimizes risk:

1. Foundations (A) are purely additive files + a few layout lines — the
   smallest possible blast radius with the largest shared payoff, and every
   later batch depends on them existing.
2. Shell (B) has the widest reach but is concentrated in exactly three
   already-shared files; doing it early — before per-screen styling — means
   no screen is restyled twice, and its P0 items deliver immediately.
3. Components (C) establish the vocabulary (buttons/badges/tables/confirm/
   toasts) that every screen-group batch then consumes, preventing one-off
   implementations from re-creating the inconsistency §6.9 documented.
4. Dashboard (D) is small and isolated; doing it after components means its
   widgets use final classes, not placeholders.
5. Core screens (E) carry the densest functional contracts; they are touched
   only after patterns are proven on simpler surfaces, with the scholars bug
   fixed first so subsequent visual work targets a working screen. Contract
   freezes (inline-edit/export/panel-fetch) are verified by captured parity,
   not hope.
6. Scanners/public (F) are the most fragile/public-facing surfaces and are
   deliberately last among feature-bearing groups, isolated in their own
   commits with their own matrices.
7. Cleanup (H) sweeps what only became visible after real usage of A–G;
   interaction-affecting accessibility items land here individually.
8. Final pass (I) verifies visual consistency against the prototype once the
   structure is stable, so polish is applied to settled ground.

This ordering also matches the rollback strategy: early batches revert as
wholes; later batches are split into screen-level commits precisely where
contracts concentrate.

### 8.15 Phase 5 conclusion

**Implement first:** Batch A foundations (`ui.css` tokens/base, `ui.js`
helpers, flash→toast container, skip-link/landmarks/focus-visible) and
Batch B shell (offcanvas-lg drawer, sidebar grouping/icons/active bar,
page-header/breadcrumbs) — together they resolve both P0 gaps and unblock
everything else.

**Must remain untouched:** controllers, services, models, routes, config
(incl. `config/scanner.php`, `config/payout.php`), database schema/migrations;
the twelve §6.10 functional contracts byte-for-byte (watchdog polling/status
handling, scanner loop incl. audio/resume/ignore-scan, `?panel=1` fetch +
script re-execution, DataTables POST feeds, inline-edit protocol, export
builder, photo pipeline, geography cascade, self-service one-shot/proxy
logic, admin matrix mechanics); ACL checks in the sidebar and all `page:`/
`action:` middleware; the v1 module vocabulary.

**Requires careful testing:** every shell commit (46-screen smoke at three
widths + ACL link-set parity); transactions inline-edit/export parity;
scanner 14-key × mode matrix; public one-shot submission flows; any
interaction-affecting accessibility change (keyboard rows, confirm/alert
replacements, optional watchdog cosmetic change).

**Safe to standardize:** design tokens and their class-level Bootstrap
overrides; buttons/badges/cards/tables/forms skins; empty/loading strings;
page titles and headers; label/id and aria-label wiring; touch/input sizing;
uppercase display rule — all presentation-only by construction.

**Deferred:** global search and notification center (need product decisions
and real data sources); announcements/activity calendar (no content source);
font-family swap and exact workspace-color alignment (Batch I token flips);
watchdog alert cosmetic replacement unless explicitly approved; anything that
would require backend changes beyond the two decision-gated read-only dashboard/search cases.

Nothing in this phase has been implemented. Implementation begins — if and
only if approved — with Batch A under the verification regime of §8.12.

---

## Phase status

| Phase | Scope | Status |
|---|---|---|
| **Phase 1** | Repository reconnaissance (§1–§4) | **COMPLETE** 2026-08-23 |
| **Phase 2** | Prototype design analysis (§5) | **COMPLETE** 2026-08-23 |
| **Phase 3** | Current V2 UI analysis (§6) | **COMPLETE** 2026-08-23 |
| **Phase 4** | Prototype ↔ V2 comparison & gap analysis (§7) | **COMPLETE** 2026-08-23 |
| **Phase 5** | UI/UX implementation plan (§8) | **COMPLETE** 2026-08-23 |

---

## 9. ARCHITECTURE DECISION ADDENDUM — TAILWIND CSS FIRST (2026-08-23)

**Status: DECIDED, NOT IMPLEMENTED.** No dependency was installed, no build
output produced, no view, layout style, or `ui.css` line changed for this
decision. This addendum records the owner's architecture correction and the
inspection it is based on; the next session implements an approved Tailwind
foundation as a separate, controlled batch. Phases 1–5 above remain the
factual record and are not rewritten by this section.

### 9.1 Decision

The production UI/UX will be styled **Tailwind-first**: Laravel 12 + Blade +
Tailwind CSS + minimal JavaScript, with the existing backend untouched. The
source-of-truth rules are unchanged — `prototype/` stays the visual/UX source
of truth, Laravel v2 stays the functional source of truth, and §7.9 verdicts
(adopt presentation, keep V2 behavior) carry over unchanged.

This supersedes two specific planning choices in §8.3 for all remaining
batches: (a) "consolidate into one plain shared stylesheet" as the end state,
and (b) the explicit rejection of Tailwind ("excluded" as an alternative).
It does NOT supersede §8.1 principles, batch sequencing (§8.10), verification
strategy (§8.12), or any Phase 1–5 finding.

### 9.2 Why Tailwind was selected (owner rationale)

The prototype's design system (palette, spacing/radius/shadow systems,
component treatments, responsive tiers, accessibility states) is strongly
specified. Expressing it as Tailwind utility patterns plus a theme layer
keeps the visual rules close to the Blade templates they style, avoids a
growing parallel custom-CSS vocabulary, and makes per-screen migration
incremental and reviewable — while the token values themselves stay
prototype-derived rather than reinvented.

### 9.3 Inspection findings this decision rests on (observed 2026-08-23)

| Question | Finding |
|---|---|
| Tailwind already installed? | **Declared but never installed**: `tailwindcss ^4.0.0` + `@tailwindcss/vite ^4.0.0` sit in `package.json` devDependencies (Laravel 12 scaffolding); no `node_modules`, no `package-lock.json`, no `public/build` exist; the only consumer is the unrouted starter stub `welcome.blade.php`. Zero effect on any live screen today. |
| Appropriate version/setup | Tailwind **v4 CSS-first via `@tailwindcss/vite`** — exactly what is already declared. No `tailwind.config.js` needed; `resources/css/app.css` already carries correct v4 `@source` globs scanning all Blade views/JS. |
| Vite configured? | Yes — `vite.config.js` with `laravel-vite-plugin` (inputs `resources/css/app.css`, `resources/js/app.js`, refresh) and the Tailwind plugin; unused by live screens (`@vite` appears only in `welcome.blade.php`). |
| Bootstrap usage today | Bootstrap **5.3.2 CSS+JS from jsDelivr CDN** in `layouts/app.blade.php` and each of the 7 standalone public pages; DataTables 1.13.6 BS5-integration CSS/JS on 9 list screens; Bootstrap JS components functionally load-bearing: modals (photo capture, scanner notification, payout viewer, duplicates compare/delete, unpaid confirm), clients Offcanvas panel, navbar/export dropdowns. |
| Composer packages | None styling-related (`laravel/framework`, tinker, dev tooling only). |

### 9.4 What remains functionally unchanged

Everything in §8.15 "must remain untouched": controllers, services, models,
routes, middleware, ACL/RBAC, authentication/single-device watchdog, config,
database/migrations, audit logging, validation, and the twelve §6.10
functional contracts. The Tailwind decision changes the styling layer only.
jQuery/DataTables feeds, html5-qrcode, scan audio, `?panel=1` fetch, and all
inline page scripts are out of scope for this decision.

### 9.5 Token mapping: `--ui-*` → Tailwind v4 `@theme`

Batch A's verified token VALUES (§5.1-derived) transplant into
`resources/css/app.css` under v4 `@theme` namespaces, which auto-generate
utilities (`bg-navy`, `text-gold`, `border-line`, …). Illustrative mapping
(final names fixed at implementation):

| Batch A token | @theme declaration | Utilities generated |
|---|---|---|
| `--ui-navy #0038A8` (+light/hover) | `--color-navy:` (+ `-light`, `-hover`) | `bg-navy`, `text-navy`, `border-navy`, … |
| `--ui-gold #FCD116` (+light/dim) | `--color-gold:` (+ `-light`) | gold accents / `.btn-gold` analog |
| `--ui-teal/red/amber/blue-accent` | `--color-*` same names | status/badge utilities |
| surfaces `--ui-bg/bg-alt/card/card-hover` | `--color-bg`, `--color-surface`, … | workspace/surface classes |
| borders `--ui-border/-light` | `--color-line`, `--color-line-light` | `border-line` |
| text `--ui-text-primary/secondary/muted/inverse` | `--color-ink*` family | `text-ink`, `text-ink-muted` |
| fonts (Roboto now; Inter/Outfit later) | `--font-body`, `--font-heading` | `font-body`, `font-heading` |
| radius ladder 6/8/12/16/999 | additive keys (e.g. `--radius-card`) — defaults not silently clobbered | `rounded-card` |
| shadow ramp xs→xl + glow | named additions (`--shadow-card`, `--shadow-panel`, …) | `shadow-card` |
| motion tokens | `--ease-standard`, duration utilities | `ease-standard` |

Naming rule: color names are additive (no collision with Tailwind defaults);
generic default scale keys (`--radius-lg`, `--shadow-md`) are overridden only
deliberately and documented if at all. The current `app.css` Instrument Sans
override is replaced by prototype-derived font vars at foundation time.

### 9.6 Disposition of Batch A (`public/css/ui.css`)

Batch A was verified (213 tests / 1056 assertions) and is presentation-only;
it is **retained as-is for now, retired at the final cleanup pass — not
deleted immediately**. Rationale: its scoped Bootstrap-alignment rules are
what currently align button/pagination/focus visuals on live screens;
removing them before Bootstrap CSS itself retires would visibly revert those
screens. Sequence: (1) token values transplant into `@theme`; (2) ui.css
keeps serving existing screens during migration; (3) when no screen depends
on it (Bootstrap CSS removed, screens migrated), delete file + layout link.
Its `.ui-*` component classes may end up unused once batches go
Tailwind-first — acceptable; they are inert and documented.

### 9.7 Coexistence mechanics and prerequisites before Batch B

A controlled **T1 "Tailwind foundation" batch must land before Batch B**:

1. Owner-approved `npm install` (creates `package-lock.json`; commit it).
   Node becomes a required tool for UI work — deployment/cutover policy for
   built assets must be decided explicitly (`public/build` is gitignored by
   default: either production runs the build, or the ignore rule changes).
2. `app.css`: replace full `@import 'tailwindcss'` with partial imports that
   **skip Preflight** while Bootstrap CSS is loaded (Preflight resets would
   fight Bootstrap Reboot on every unmigrated screen); install the §9.5
   `@theme` tokens; drop the Instrument Sans override.
3. Layout gains `@vite(['resources/css/app.css'])` after the Bootstrap CDN
   link (transition order). Expected visual delta: none until utilities are
   used.
4. Cascade-layer spike on ONE mixed screen: unlayered Bootstrap CSS beats
   layered Tailwind utilities regardless of order — verify the chosen
   import layering actually overrides Bootstrap classes on partially
   migrated markup BEFORE any screen migrates.
5. Full suite green + console clean + zero visual delta gate, then Batch B
   proceeds Tailwind-first.

Bootstrap JS bundle is retained through B–G (modals/offcanvas/dropdowns are
functional dependencies; replacing them is interaction-affecting work needing
its own approved items). Removing the Bootstrap **CSS** CDN happens per the
existing final consistency pass once zero live references remain; DataTables
BS5-integration chrome is restyled within its own table batch.

### 9.8 Risks / dependencies introduced by this correction

| # | Risk | Mitigation |
|---|---|---|
| 1 | Preflight vs Bootstrap Reboot resets clash on mixed screens | Skip Preflight during coexistence; enable only after Bootstrap CSS retires (final-pass decision) |
| 2 | Unlayered Bootstrap CSS outranks layered utilities — migrated classes appear inert | T1 layering spike (§9.7 #4) gates all migration work |
| 3 | Two token sources during transition (`ui.css` ↔ `@theme`) drift | `@theme` becomes canonical at T1; ui.css frozen (no new rules) until deletion |
| 4 | Build tooling now required (node/npm) — new operational dependency | Lockfile committed; asset-deployment policy decided explicitly at T1 |
| 5 | Partially migrated screens mix Bootstrap/Tailwind idioms | Migrate whole screens per batch (§8.10 boundaries unchanged); grep audits for orphaned classes |
| 6 | DataTables/jQuery BS5-integration styling assumptions | Tables batch restyles DT chrome deliberately; no DOM restructuring (risk §8.13-3 stands) |
| 7 | Beginner-maintainability regression vs one stylesheet | Token names mirror §9.5 table; component conventions documented as batches land |

### 9.9 Revised sequence

§8.10 order stands, with one insertion and amended contents:
**T1 Tailwind foundation (new, prerequisite) → A′ token retirement deferred to I →
B shell/navigation (Tailwind-first markup) → C components (utility patterns +
minimal shared JS where justified) → D dashboard → E registry/transactions →
F scanners/public → G administration → H a11y/responsive cleanup →
I final pass (now also: remove Bootstrap CDN CSS, delete `ui.css`,
Preflight decision, DT chrome under Tailwind).** Batch A is not reworked
before B; §9.6 governs its retirement.
