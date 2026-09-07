# 2DMIS v2 — Comprehensive UI/UX & Interaction Architecture Audit

Date: 2026-08-31
Mode: **Strict read-only.** No application code, view, CSS, JS, route, controller,
service, permission, or database change was made. This report is the deliverable.
Implementation starts only after owner approval.

The full 16-section audit (§A–§O) follows the mandated template. Read-only
constraints (no login-driven live reproduction, no temp users, no DB writes)
are documented where they bound a finding.

---

## §A — Executive Assessment

**Verdict: hold the P8 cutover; run a presentation-only stabilization program
("UX-1 Foundation") first, then re-gate production on the owner-approved checks.**

The application layer under the UI is sound and v1-parity-correct:

- 286 PHPUnit tests / 1336 assertions, all green; Pint clean; Vite build clean.
- Scanner engine (P4), payout attendance (P5), ACL (P7), and the P3 transaction
  layer all preserve v1 business behavior and the proxy read-only DB rule.
- The Tailwind/Bootstrap coexistence foundation (Batch A/B/C) is coherent and
  well-scoped; screens are migrated batch-by-batch with a stable token
  vocabulary and per-screen CSS namespacing.

The interaction layer, however, is **not production-ready**:

1. **Three broken record-detail flows** (confirmed by file trace): households,
   payouts, and audit-log rows cannot open their record panel. Each is a wiring
   defect (feed shape vs. panel URL), not a business-rule issue. See §E/§M.
2. **Mix of V1 and prototype interaction models** across screens: native
   `confirm()` on client delete vs. shared `uiConfirm` on session force-logout;
   inline `prompt()` on scholar client-id vs. inline-edit cells on transactions;
   static rows on sessions vs. DataTables elsewhere; only some tables are
   row-click→panel driven. Consistent, learnable interaction is the biggest gap.
3. **Chrome/foundation defects** the user reported (e.g., the **Access Control
   group collapsing**): one statically confirmed cause found (detail routes not
   covered by the sidebar `is` sets), remaining candidates require live
   reproduction. See §G.
4. **Column density** is V1-verbatim (clients 21 cols, transactions 21,
   scholarship reports 18) and will spoil the panels-and-hubs intent unless the
   owner sanctions presentation flattens under the SQL-parity rule.
5. **Navigation is invented, not inherited**: hubs (Scanner Engine, Payouts),
   a grouped Access Control, tabs inside Scholars, and a global search bar are
   all new IA. Good direction, but several leaf interactions were designed
   before the interaction model was fixed, which is why panels are broken.

**Priority order (proposed):**
- **P0** — Repair the three broken panel flows + the Access Control/breadcrumb
  regression (files listed in §O, presentation-only).
- **P0** — Settle the interaction-model matrix (§C) so all screens speak the
  same language; sanction the small set of presentation consolidations in §N.
- **P1** — Column-density flattens (§F) where the owner approves them.
- **P2** — Polish (notif bell decision, mock-action removal, keyboard/focus
  pass, uiConfirm normalization, audit-poll performance).
- **P8** — Re-gate on the §M checklist (E2E for every "open record" flow).

---

## §B — Scope and Methodology

Read-only analysis. Evidence = complete file traces, quoted behavior, route
tables, feeds, and CSS tokens. No execution against the production-copy DB,
no login (a login writes `session_token`/`sessions`), no user creation, no
servers started beyond reading code.

In scope (deep):
- Layout shell, sidebar, topbar, and the shared partial ecosystem
  (`layouts/app.blade.php`, `partials/{sidebar,navbar,sidebar-link,sidebar-icon,
  details-panel,confirm-modal,filter-chips,page-header,breadcrumbs,
  record-view-modal}`).
- Clients, Households, Transactions, Scholars (index/show/panel/feed).
- Scanner engine (`config/scanner.php` + `ScannerController` + hub + shell),
  Payouts (`config/payout.php` + `PayoutAttendanceController` + hub + screens),
  Unpaid verifications (route/controller level).
- Admin: Users, Permissions, Program permissions, Scopes, Exemptions, Audit
  logs, Sessions.
- `routes/web.php` (full), `DetailsPanel.js`, `FilterChips.js`, `app.css`
  (token/shell/components), `public/css/ui.css`.

In scope (route/navigation level only, bodies deferred to the notes in §O):
- Form screens (`clients._form`, `clients._gip`, `households.create`,
  `transactions.create/edit`, `scholars.create/edit/_form`), public
  self-service (`student/verify`, `grantee-update.*`, `unpaid-verification.*`,
  `qr-viewer`), dashboard internals, and non-core tables (users feed, unpaid
  verifications feed).

Limits on findings: live-behavior claims are marked *Confirmed (static)* or
*Needs live reproduction*; the Access Control collapse is the one place this
distinction matters (§G.4).

---

## §C — UX Principles & Standards

1. **Parity-first.** Every visual/interaction change preserves the SQL rows
   produced, the routes, the permission keys, and the ACL semantics of the v1
   page it replaces. Consolidations happen ONLY in presentation space (see the
   two approval gates in §N).
2. **WCAG 2.1 AA targets.** Focus-visible (global gold outline from `ui.css`),
   skip link, aria-landmarks, `role="dialog"` + `aria-modal` on the DetailsPanel,
   contrast-darkened text tokens (`--ui-text-primary/secondary/muted`), labels on
   icon buttons.
3. **One skill per surface.** DataTables for search/sort/paginate; server-side
   POST feeds; row-click → DetailsPanel overlay; Actions column for explicit
   commands; `uiConfirm` for anything destructive; native state preserved only
   where parity blocks replacement.
4. **Feed + Panel as the core record idiom.** Every index screen should open a
   record via `DetailsPanel.load(module, id, {url})`, and every such default
   URL must resolve to a real `?panel=1` capable view. This is currently the
   single most violated rule (§E, §M).
5. **Design tokens are the single vocabulary.** Components use the token/utility
   vocabulary shared between `app.css` `@theme` (canonical since T1) and the
   frozen `ui.css` API; screens add only namespaced skin under their
   `#*-screen` block.
6. **No silent accessibility regressions.** The V1-inherited alert()-style
   feedback and mock actions are cataloged (§I) so their eventual replacement is
   deliberate.

---

## §D — Design Grammar

Tokens (both sources agree; `app.css` `@theme` is canonical):

| Slot | Value | Usage |
|---|---|---|
| navy | #0038A8 | structure: sidebar, links tables, payouts |
| navy-hover | #002B7F | hover |
| gold | #FCD116 | primary action (`btn-gold`), active sidebar mark |
| gold-dim | rgba(252,209,22,.12) | active sidebar bg, avatar chip |
| teal | #2D8B7A | success: paid/active/online |
| red | #CE1126 | destructive only |
| amber | #D4A900 | pending |
| blue-accent | #3B82F6 | info/approved |
| bg | #F0F2F5 | app shell |
| text | #0F1B2D / #4E5A6E / #70798B | primary/secondary/muted |
| surface | #FFFFFF, ring #E2E5EA | cards, `data-card` |
| fonts | Inter (body), Outfit (headings, 4–8 weights via Google Fonts) | type |
| radius | `rounded-btn` (8px), `rounded-panel` (1rem), `rounded-pill` | components |
| spacing | `--ui-*` 4–28 px ladder; screens also use bracket utilities (`mb-[16px]`) | rhythm |

Layout system:
- Fixed **260 px** navy sidebar ≥ `lg` (offcanvas < `lg`, 280 px), `main` offset
  `lg:ml-[260px]`; sticky 64 px topbar with breadcrumb, global search,
  notifications bell, user dropdown.
- **5 navigation sections** (Overview, Registry, Assistance, Administration,
  plus the grouped Access Control block inside Administration).
- Dual-mode record views (`?panel=1` compact vs. full page) sharing one Blade
  partial; `data-panel-*` hooks drive the DetailsPanel header/actions.
- Per-screen CSS is namespaced (`#clients-screen`, `#transactions-screen`,
  `#payouts-screen`, `#scanners-screen`, `#audit-screen`, `#sessions-screen`)
  and delivered through `@stack('styles')` — nothing leaks across screens.
- Code-comment embargo (project rule): maintained; long prose comments inside
  CSS/Blade carry architecture rationale, which does not violate the rule.

---

## §E — Common Interaction Patterns

**Pattern matrix (current reality):**

| Pattern | Where | Status |
|---|---|---|
| Row-click → DetailsPanel | Clients, Scholars(+GIP), Transactions | Works |
| Row-click → DetailsPanel | **Households** | **Broken (404)** — feed lacks numeric `id` (see below) |
| Row-click → DetailsPanel | **Payout attendance** | **Broken (detail JSON load)** |
| Row-click → DetailsPanel | **Audit logs** | **Dead (no `id` + POST feed)** |
| Row double-click as twin | Clients/Households/Scholars | Present |
| Inline cell edit (td click) | Transactions (8 fields) | Works |
| Prompt()-based edit | Scholars "Client ID" | Works, V1-styled |
| Page → Edit | All detail screens; Edit gated by `canAccessAction(page,'edit')` | Works |
| Declarative `data-confirm` | Sessions force-logout | Works (shared modal) |
| Native `confirm()` | Client delete (_details + panel), others per parity | Works, V1 legacy |
| DataTables + server POST feed | Clients/Households/Transactions/Scholars/Reports/Payouts/Audit/Update-logs | Works |
| Static server-rendered table | Sessions online | Works (by design) |
| Photo modal w/ camera+upload | Clients profile | Works |

**Confirmed wiring defects (all presentation-only):**

1. **Households panel 404.** `HouseholdController@data` returns rows keyed on
   `household_id` (the "HH-…" string); `households/index.blade.php` sets
   `data-id = data.household_id`. Row click / keyboard twin call
   `route('households.show','__ID__')` → `/households/HH-…?panel=1`, which
   binds `households/{household}` to a numeric id → 404. The Delete action is
   unaffected (uses numeric `id`). Fix: add numeric `id` to the feed.
2. **Payouts detail broken.** `payouts/attendance.blade.php` row click loads
   `DetailsPanel.load('payouts', id, { url: dataUrl, method: 'POST' })` — i.e.,
   it asks DetailsPanel to render the **JSON feed itself**; with an empty
   success callback the panel shows "No detail content available." The real
   detail routes (`payout-attendance/{variant}/{payout}?panel=1`, plus the
   weird `…/show` alias with a dead `->where('panel','1')`) exist but are never
   used. DetailsPanel's own default URL for `payouts` is also wrong (missing the
   `{variant}` segment), so even a bare panel open fails.
3. **Audit rows dead + heavy polling.** Feed rows (username, action, target,
   date, date_raw) have no `id`; `createdRow` reads `data.id`, so row click and
   keyboard do nothing; the load also points at the POST `dataUrl`. The screen
   also re-fetches the full **LIMIT 10000** feed every 5 s (§L).
4. **Access Control collapse / breadcrumb regression.** `admin.users.show`
   (`GET /admin/users/{user}`, reached from the Users panel "Open full page")
   is **not** in any `is` set of the Access Control group in `$shellSections`
   (users' `is` lists only cover index/reset/store). On that URL the group
   renders collapsed and the topbar breadcrumb falls back to "Dashboard".

**Shared contracts worth preserving after repair:**
- `uiConfirm({title,message,confirmLabel}) → Promise<boolean>`; `<form
  data-confirm>` intercept is modal-native and keeps `@csrf` via
  `HTMLFormElement.submit()`.
- DetailsPanel: `executeScripts()` re-runs each module partial's inline script
  on every panel load — a correctness feature; keep module partials dual-mode.
- FilterChips: multiple values in one category OR; different categories AND;
  global search composes AND; municipality→barangay cascades; state in the URL.
- Toast channel: `login_status` flash renders as a manual-dismiss toast
  (no auto-hide).

---

## §F — DataTables and Column Density

Verified header inventories (column counts include Actions):

| Screen | Columns | scrollX | pageLength | inline-edit | Note |
|---|---|---|---|---|---|
| Clients | **21** (ID, Full Name, Lastname, Firstname, Middlename, Extension, Precinct, Region, Province, Municipality, Barangay, House No, Mobile, Birthdate, Age, Sex, Civil Status, Occupation, Income, Voter ID, Actions) | no | 10 | no | Full Name + split name cols duplicate |
| Transactions | **21** | yes | 10 | 8 cells (remarks, comments, suggested_amount, status, amount_paid, date_paid, gwa, units) | program/date_applied/type/payout_date excluded; default `order [[4,'asc']]` |
| Scholars | **14** (ScholarController::COLUMNS) | — | — | Client ID via `prompt()` | LEFT-JOIN full-name/exam parity |
| Scholarship Reports | **18** | — | — | no | Reports tab |
| Payout attendance | **12** (seat variants) / **8** (unpaid, no seat cols) | — | — | no | actions-col 100 px |
| Households | 6 | no | — | no | actions-col 120 px |
| Update logs | 7 | — | — | no | static above the fold, client-side DataTables |
| Audit logs | 5 | — | — | no | 5 s auto-refresh (see §L) |
| Sessions online | 4 | — | — | no | static server-rendered by design |
| Users / Unpaid verifications | feeds, not counted here | — | — | — | bodies deferred |

V1's 21+-column grids were tolerable in v1's console-style screens; inside the
new panel/hub model they force horizontal scan (`scrollX` on transactions takes
over the viewport) and hide the record narrative. **Presentation-space flattens
require owner approval** (SQL parity maintained); candidates: Clients (drop
Full Name + split-name duplication short-term, keep V1 sorting via hidden
columns), Transactions (defer payout_date and/or split type/program), Books.

---

## §G — Navigation and Wayfinding (IA)

### G.1 Current IA (from `$shellSections`)

- **Overview** — Dashboard
- **Registry** — Clients, Households
- **Assistance** — Scholars, Scholarship Reports, Update Logs, All Transactions,
  Scanner Engine (hub), Payouts (hub)
- **Administration** — Access Control (group: Create User, User Management,
  Manage Permissions, Action Permissions, Municipality Scope, Manage Program
  Permissions, Multi-Device Exemptions, Currently Logged Users), Audit Logs

### G.2 Classification (V1 → V2 mapping)

| Class | Meaning | Items |
|---|---|---|
| A · Direct V1 map | 1:1 page replacement, identical semantics | Dashboard, Clients, Households, All Transactions, Scholars, Scholarship Reports, Update Logs, scanner pages, payout screens, sessions, audit logs |
| B · Hub-workspaces | Many v1 pages behind one nav item, destination grid filters by page permission | Scanner Engine hub, Payouts hub, Unpaid Grantees |
| C · New primary flows | Flows raised out of old file-level home | Create User / User Management / Manage Permissions / Action Permissions / Municipality Scope / Manage Program Permissions / Multi-Device Exemptions (Access Control group) |
| D · Fallback redundancy | Same target shows as top-level link for fallback users AND as a tab for scholars-holders | Scholarship Reports, Update Logs |
| E · Public self-service | No nav entry, top-level routes (v1 had no session check) | student/verify, grantee-update, unpaid-verification, qr-viewer |

### G.3 Breadcrumb

Topbar shows `2DMIS › {label}` (label resolved from `$shellSections` + config,
with explicit handling for scanner/payout/unpaid hubs). Bug boundary: any GET
route not covered by an `is` set falls back to "Dashboard" — this is what make
`admin.users.show` mislabel and collapse the group (see §E.4).

### G.4 Access Control group — analysis of the reported collapse

Mechanism (verified in code): the group is a Bootstrap Collapse; the server
renders `show`/`aria-expanded` from `$groupActive`, which is TRUE only when the
current route matches one of the children's `is` routeIs lists
(`sidebar.blade.php` lines 66–101). Bootstrap then owns open/close client-side.

Factors:
1. **Confirmed**: navigating to `admin.users.show` (users panel detail) re-renders
   with the group collapsed and breadcrumb "Dashboard", because that route is
   absent from all `is` sets. Same class of miss would affect any other detail
   route under the group.
2. **Needs live reproduction**: reports of the group "opening then immediately
   closing" without navigation cannot be confirmed statically. The Bootstrap
   Collapse markup is correct; the offcanvas-lg parent + `lg:overflow-visible`
   + inner `overflow-y-auto` combination is a plausible suspect on some
   viewports but not provable without a browser session. The sidebar offcanvas
   `hidden.bs.offcanvas` recompute in `layouts/app.blade.php` also re-measures
   DataTables, not state.

Recommendation: reproduce in a real authenticated session after approval; if
(1) is the whole story, closing the `is` gap is the fix; otherwise capture the
viewport/token conditions for the JS/CSS follow-up.

---

## §H — Layout and Responsive Behavior

- ≥ `lg` (992 px): fixed navy 260 px sidebar, main offset, topbar full.
- < `lg`: sidebar is an offcanvas drawer (280 px) behind the hamburger; the
  drawer has its own close button; body scroll is locked by Bootstrap when open.
- Tables: transactions uses `scrollX`; the 21-col grids overflow horizontally on
  tablets by necessity until §F flattens are approved.
- DetailsPanel overlays from the right; `transitionend` re-`columns().adjust()`s
  any open DataTables (correct hoisting of the v1 technique).
- Layout scripts are guarded (`window.jQuery && …dataTable`) so they no-op until
  a screen's own script stack loads jQuery/DataTables — safe on every screen.
- Mobile nav ordering mirrors desktop sections; icons carry `aria-hidden`.

---

## §I — Alerts, Feedback, and Modal Behavior

| Channel | Mechanism | Screens | Verdict |
|---|---|---|---|
| Confirm destructive | Native `confirm()` (V1 parity) | Client delete (page + panel) | Kept by parity; candidate for uiConfirm after owner sanction |
| Confirm destructive | Shared `data-confirm` modal (Promise) | Sessions force-logout | Modern; inconsistent with above |
| Validation errors | Bootstrap inline `alert` block in layout | All forms | Consistent |
| Flash success | `login_status` toast stack, manual dismiss | Post-login / actions | Consistent |
| Prompt edit | `prompt()` | Scholars Client ID | Keep only if owner accepts; else convert to inline cell or modal |
| Mock actions | `data-sim` toast | "Generate Certificate" (clients panel) | **Remove or implement before P8** — a toast masquerades as a real action |
| Notifications bell | `#notifBtn` + `.notif-dot` (hidden, count 0) | Global | **Stub; decide implement-or-remove before P8** |
| DataTables feedback | Built-in processing overlay + pageInfo | Feed screens | Consistent |
| Camera capture | getUserMedia → canvas → submit | Client photo modal | Works; responds to `shown/hidden.bs.modal` |

---

## §J — Accessibility and Keyboard

Implemented (verified): skip link; `aria-label` on the offcanvas/sidebar/nav
buttons; `role="dialog"`+`aria-modal`+`aria-labelledby` on DetailsPanel with
focus trap + `lockScroll`; labels on icon-only commands; `textarea`/`input`
`title` fallbacks; contrast-darkened tokens; gold global `:focus-visible`.

Gaps found:
- **Audit rows**: not keyboard-reachable (dead click path) — a By-permission
  screen is the one that must stay keyboard-clean for operators.
- Clients/Households/Scholars implement a keyboard twin (Enter on the focused
  row) — extend that pattern to any table that gets row click.
- `prompt()`/`alert()` are not WCAG-friendly; sanctioned replacements only.
- The global 2 s `checkSession` poll can `alert()` the user mid-scope on
  "another_device" — V1-required behavior, but the modal could be non-blocking
  (presentation only) if the owner approves.
- Modal focus return: DetailsPanel restores focus on close; confirm-modal/
  photo-modal rely on Bootstrap defaults (adequate).

---

## §K — Consistency and Scalability

- **Vocabulary**: one token set; names mirror the frozen `ui.css` API except
  where a ui.css name implies Bootstrap coupling (structural button variants
  were given new names — `.btn-navy`, `.btn-red`, `.btn-subtle`,
  `.btn-outline-red` — instead of overriding Bootstrap-named classes). This
  keeps `ui.css` safely retirable at the final pass.
- **Screen scoping**: each screen declares its skin inside `#*-screen`; no
  cross-screen leakage observed in the audited set.
- **Mixed raw bracket utilities vs tokens** inside views (`mb-[16px]`,
  `gap-[12px]`) — functionally identical to `--ui-space-*`; a candidate for a
  light cleanup pass to make hand-offs consistent, not a blocker.
- **The groups of screens that should behave the same** (all "entry → list →
  row → detail" screens) currently differ only where marked in §E; that matrix
  is the definition of "consistent."
- Stylesheet seams: Bootstrap CDN first, then `app.css`, then `ui.css`; layout
  shell skips Preflight on purpose (coexistence). Documented seam, correct is
  not fast — seamed cleanly at the final pass.

---

## §L — Performance

- **Audit logs auto-refresh every 5 s over a LIMIT 10000 feed** — the screen
  re-downloads up to 10k rows per 5 s while open. Replace with manual refresh
  (or paged navigation) in the Foundation pass.
- Global `checkSession` every 2 s (a single JSON `session/status`) — cheap but
  constant; acceptable; could be pushed to 10 s.
- All DataTables feeds are server-side POST with explicit LIMITs (clients,
  households, transactions, scholars, reports, payouts, audit, update logs) —
  correct scale behavior.
- Fonts: two families (8 weights) + CDN Bootstrap+icons; no local vendoring.
- `DetailsPanel` fetches exactly one small partial per open; `executeScripts`
  re-binds only fresh nodes. Открытие/закрытие cheap.
- Scan shell loads `html5-qrcode` only inside the scanner screen.

---

## §M — Testability and E2E Regression Gate

The project's Playwright E2E suite (all configured browsers/devices) is the
right gate. The three broken flows above prove the current suite does not yet
cover "open record" interactions.

**Proposed P0 E2E additions (spec-level, will be implemented on approval):**
1. Clients index → row click opens panel; panel shows ID; "Open full page"
   round-trips; Delete tab's Edit path.
2. Households index → row click opens panel (this catches the missing-`id` fix);
   household detail page from client profile link.
3. Transactions index → inline edit an allowed cell, save, row redraws; panel
   opens when clicking a non-editing area.
4. Payouts (both seat variants + unpaid) → row click opens the payout detail
   panel; Open Scanner wiring.
5. Audit logs → row click opens the audit-entry panel; table/pagination works;
   leaderboard modal opens.
6. Users → panel "Open full page" → Access Control group **stays open** and
   breadcrumb shows "User Management".
7. Scholars → tabs: Scholarship Reports renders; Update Log renders; GIP row
   click opens GIP panel.
8. Sessions → force logout via `data-confirm` modal confirm/cancel.

Checks on gate: `php artisan test` full suite, Pint, Vite build, then the
Playwright `*` projects. P8 does not proceed until 1–8 are green.

---

## §N — Production Parity, Risks, and Parity Matrix

**Change discipline:** all defects and consolidation candidates in this report
are presentation-layer. No route, permission key, ACL rule, controller/service
signature, business rule, or database value changes. The following two
consolidations are the ONLY ones carried in this report and each needs explicit
owner sign-off (both are presentation-level; neither alters rows or rules):
- **C1 · Confirm normalization**: replace residual native `confirm()`/`prompt()`
  with the shared `uiConfirm`/inline-edit idioms (screen consistency).
- **C2 · Column flattens**: present fewer visible columns (or relocate them to
  the record panel) while keeping the SQLs byte-identical (V1 sort order for any
  field can be preserved via hidden columns).

**V1 → V2 parity matrix (behavior; presentation differences allowed):**

| V1 page | V2 route / surface | Parity | Risk |
|---|---|---|---|
| scanner_*.php (14) | `scanners.{key}` + config-driven modes | Behavior identical; scan shell is presentation-only | Low |
| scanned_payouts*.php (3) | `payout-attendance.{variant}.*` | Behavior identical; only detail-panel wiring broken | Low (fix in P0) |
| disabled_unpaid.php etc. | `unpaid-verification.*` (public) | Preserved (no session check, as V1) | Low |
| disabled_update_grantee.php | `grantee-update.*` (public) | Preserved | Low |
| view_qrcode.php | `qr-viewer` (public) | Preserved | Low |
| clients.php etc. | `clients.*` | Behavior identical (feeds/actions/ACL) | Low |
| household.php | `households.*` | Behavior identical; panel 404 bug | Fix in P0 |
| all_transactions.php | `transactions.*` (inline update = V1 quirk preserved) | Behavior identical | Low |
| scholars.php / exam.php | `scholars.*` (tabs + exam LEFT JOIN parity) | Behavior identical | Low |
| reports / update_logs | `scholarship-reports.*`, `update-logs.index` | Behavior identical | Low |
| register.php / manage_* | Access Control group (`page:*` super-admin gate on users index/show/password reset) | Rule preserved via ACL service | Low |
| audit_logs.php | `admin.audit-logs.*` (LIMIT 10000 kept) | Behavior identical; polling & dead-click are UI bugs | Fix in P0 |
| currently_logged_users.php / force_logout.php | `session.online` / `session.force-logout` | Behavior identical | Low |

**Compliance checklist (unchanged by this audit):** local `main_system` remains
a copy; no `migrate:fresh`/`db:wipe`; additive migrations only; mysqldump
backup before any schema work; `__legacy_v1_baseline_schema__` sentinel intact;
no `.*env*` exposure; no hardcoded user_id/username checks (ACL service only).

**Open decisions to be answered by the owner (none silently decided here):**
- Sanction C1 and/or C2 for this phase, with scope (all screens vs. listed).
- Access Control group: flatten to a tab-based "Access Control" workspace
  (presentation) or keep the collapsible group + close the `is` gaps?
- Notifications bell and "Generate Certificate" mock: implement, remove, or
  defer — all before P8.
- Audit logs: manual refresh + pagination (recommended) vs. keep 5 s interval.
- Live reproduction budget for the Access Control collapse (needs a real login).

---

## §O — Exact File Impact (presentation-only; per planned phase)

**UX-1 Foundation (P0; interaction-model stabilization).** Owner-approved fixes
for the confirmed defects only:
- `resources/views/households/index.blade.php` + `app/Http/Controllers/
  HouseholdController.php` — add numeric `id` to the feed; keep `household_id`.
- `resources/views/payouts/attendance.blade.php` + `resources/js/components/
  DetailsPanel.js` — row click → `payout-attendance/{variant}/{payout}?panel=1`;
  correct the `payouts` default URL (variant-aware).
- `resources/js/components/DetailsPanel.js` — default-URL/`dataUrl` guard so a
  POST feed can never be handed to the panel as HTML.
- `resources/views/admin/audit_logs/index.blade.php` + `app/Http/Controllers/
  AuditController.php` — include `id` in feed; panel URL → show-panel; replace
  5 s polling with manual refresh (or pagination) — owner decision.
- `resources/views/layouts/app.blade.php` (`$shellSections`) +
  `resources/views/partials/navbar.blade.php` — add `admin.users.show` (and any
  other reachable detail routes) to the `is` sets so the group stays open and
  the breadcrumb resolves; unmatched-route fallback stays "Dashboard".
- Keyboard twin for audit rows (per §J).
- Add the §M E2E specs 1–6 to the Playwright suite.

**UX-2 (P1; approved consolidations C1/C2):**
- `clients/index.blade.php`, `transactions/index.blade.php`,
  `scholars/...` (reports tab), `payouts/attendance.blade.php` — column flattens
  + hidden preservation columns; `scholars/index.blade.php` prompt() → inline
  cell (C1) if approved; native `confirm()` → `uiConfirm` wherever approved.
- `config/...` unchanged.

**UX-3 (P2; polish):**
- Notifications bell (`navbar.blade.php` + `app.css`/`ui.css`), certificate mock
  removal or implementation (`clients/_details.blade.php`), focus-visible token
  handoff note, `checkSession` interval tuning, format/date helpers for
  update-logs PHT, empty-state copy pass.

**I (Standing final pass):** retire `public/css/ui.css`; Preflight/`@theme`
finalize; keyboard/focus full sweep; re-run all checks.

**Files NOT touched by any phase:** `routes/web.php`, `config/*`, controllers'
business logic, service layers, migrations/database, `C:\xampp\htdocs\system`.

---

## Compliance summary

This audit made no application changes. Findings are wired to exact files and
status (confirmed vs needs live reproduction). No commit is implied. Change
ownership: the specific fixes in §O should be merged, tested (Pint, PHPUnit,
Vite, and the §M E2E specs), and only then re-gating the P8 decision.