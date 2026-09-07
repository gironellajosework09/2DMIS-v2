# 2DMIS v2 — Session Handoff

**Last Updated:** 2026-09-07 (Phase 28 — **Final Bootstrap-Free Forensic Audit**, AUDIT ONLY).
Phase 28 independently re-audited the post-Phase 27 state and confirmed the project is genuinely
Bootstrap-free: fresh repo-wide search with per-occurrence classification (**0 active dependencies**;
the rest are historical docs, comments, the E2E absence locator, 2 inert `[data-bs-popper]`/
`[data-bs-theme=dark]` parity selectors, and third-party framework/DataTables code); Bootstrap CSS
CDN **0**, Bootstrap JS CDN **0**, DataTables Bootstrap **CSS** CDN **0** (views, compiled views,
built assets, served HTML); live Chromium on 6 public surfaces — `window.bootstrap` `undefined`,
**0** Bootstrap network requests, **0** console/page errors, HTTP 200, no horizontal overflow at
375/768/1280; `--bs-*` active consumption **0** (`var(--bs-` absent from `resources`/`public`);
DataTables class emitter verified NOT to reference the Bootstrap library; **301 tests / 1419
assertions / 0 failures**, Pint + build + view:cache clean; **no application, CSS, JS, schema, or
database change** by the audit. **Verdict: PASS — BOOTSTRAP-FREE COMPLETE; no further
Bootstrap-removal phase is required.** Earlier Phase 27 (2026-09-07, **Final Bootstrap CSS removal**)
removed **all 8** Bootstrap 5.3.2 CSS CDN `<link>`s and owned the last families in project
`public/css/ui.css` §4.8–4.10, byte-faithful to 5.3.2: §4.8 (`.form-label` / `.accordion*` /
`.list-group*`), §4.9 (Bootstrap utility parity with `!important`, incl. the JS-contract `.d-none`,
the spacing ladder, and the `.row`/`.col-*` grid), §4.10 (Reboot + `_type.scss`: universal
`box-sizing:border-box`, `body{margin:0;color:#212529}`, element + heading defaults). **Bootstrap CSS
dependency: 8 → 0; removal-gate READY.** The proven-dangling `admin/users/show` `#passwordModal`
trigger was removed; `e2e/alpine-phase0.spec.ts` now asserts CDN absence + `ui.css` presence. Full
suite **301 tests / 1419 assertions / 0 failures**, Pint + build + view:cache clean, live Chromium
harness **5/5 structural gates (0 Bootstrap CSS requests, `window.bootstrap` undefined, 0
console/page errors, 200)** + **0 computed-style diffs** across 5 standalone pages × 8 selectors ×
44 props vs the pre-removal baseline, mobile spot-checks no horizontal overflow. E2E not run
(auth-gated screens — `.accordion`/`.list-group`/`.form-label` live DOM is auth-gated — no account,
DB untouched at 1604 audit rows — documented limitation). This completes the 2026-09-06–07 Phase 23
removal-gate audit → Phase 24 JS bundle removal → Phase 25 DataTables skin ownership → Phase 26
component ownership → **Phase 27 final CSS removal → Phase 28 final forensic audit (PASS)** chain;
the Status table column remains **blocked** (`tbl_clients` has no `status` column); P8 cutover NOT
executed — remains production-owner-gated.

---

## Current Status

### Project State

- Planning: Complete
- Architecture: Complete
- Migration Planning: Complete
- Engineering Blueprint: Complete
- Implementation: **P0 → P7 + P12 (action/municipality authz) + P8 hardening
  (A.1–A.4) complete; P8 cutover execution next (owner-approved pass required)**.
  **Pre-P8 IA consolidation complete 2026-08-30** (see Current Milestone).
- UI/UX Modernization: **Batch A — shared foundations complete 2026-08-23**
  (`public/css/ui.css` + one-line layout integration; retained, retires at
  final pass). **Architecture correction 2026-08-23: production styling is now
  Tailwind-first** (`docs/UI_UX_ANALYSIS.md` §9). **T1 — Tailwind foundation
  complete 2026-08-23:** tailwindcss/@tailwindcss/vite 4.3.3 installed
  (lockfile committed), `app.css` rebuilt CSS-first (Preflight skipped,
  `@theme static` tokens transplanted from `--ui-*`, utilities unlayered),
  layout `@vite` wired after the Bootstrap CDN link, cascade spike verified.
  **Critical T1 decision: source scanning is an EXPLICIT ALLOWLIST**
  (`source(none)`; no blanket view globs) — auto-scan of unmigrated Blade
  views was proven to generate Bootstrap-lookalike utilities with different
  values (p-4/gap-3/w-100…) that would override Bootstrap site-wide. Each
  migration batch adds its own `@source` line in the same change that
  migrates the markup. **Batch B — shell/navigation complete 2026-08-24:**
  layout/sidebar/navbar rebuilt Tailwind-first (`offcanvas-lg` drawer <992px,
  fixed 260px navy column ≥992px, grouped `$shellSections` catalog feeding
  sidebar + topbar breadcrumb, feather icons, gold active bar, skip-link,
  `<main>` landmark; ACL loops/flash/watchdog byte-preserved; sidebar href set
  35/35 parity-verified). New coexistence rule documented in `app.css`:
  stock spacing utilities at steps 3–5 are forbidden in migrated markup,
  @apply args and comments (Bootstrap same-name/different-value collisions;
  Tailwind also scans this CSS file itself). Inert stubs
  `partials/page-header` + `partials/breadcrumbs` created (unscanned until
  first consumer batch adds their `@source` lines).
  **Batch C — shared components complete 2026-08-24:** Tailwind-side
  component vocabulary in `app.css` mirroring the frozen ui.css API 1:1
  where Bootstrap-safe (status-badge `.is-*` family, data-card family,
  metric-card, ui-notice, ui-empty, ui-micro-label) plus self-contained
  buttons (.btn-gold/.btn-navy/.btn-red/.btn-subtle/.btn-outline-red — no
  `--bs-*` variables, survive Bootstrap retirement), new form field
  helpers (.field-*) and filter chips; `login_status` flash converted to a
  persistent manual-dismiss toast stack (`aria-live` container,
  `role="status"`, native BS Toast init in layout); `$errors` inline alert
  byte-preserved; inert stub `partials/active-filters` created. Deferred
  with reasons: confirm-modal/toast helpers (no consumer until E/G —
  no-dead-JS rule), DT/pagination/modal chrome (global overrides would hit
  unmigrated screens), uppercase consolidation (H/I). No screens migrated,
  no `@source` growth, zero backend changes.
  **Batch D — dashboard composition complete 2026-08-24:** first migrated
  screen; adopts page-header + breadcrumbs (both partials now scanned via
  three narrow @source additions). Implemented REAL-data widgets only:
  quick actions (Add Client / Register Household double-gated by page +
  action ACL at render time; New Transaction → transactions.index because
  the create form needs a beneficiary; scanner chips reuse the sidebar's
  per-scanner gate loop) and recent transactions fetched from the EXISTING
  `transactions.data` feed (endpoint enforces municipality + program scope;
  widget markup AND script ship only behind the all_transactions.php page
  gate; honest loading/empty/error states — local DB has 0 transactions so
  the empty state is the locally exercised path). KPIs, Program
  Distribution, Announcements, Calendar, Recent Activity all OMITTED per
  §8.8 classification (decision-gated/no endpoint — nothing fabricated).
  Layout head gained `<meta name="csrf-token">` for the fetch. Two
  collision violations caught and fixed mid-batch (`py-4`/`mb-4` → ladder
  longhands); final CSS gates clean, Preflight absent, zero !important.
  Verified: 213/1056 tests, Pint, view:cache, both-account render matrix
  (super admin full / restricted fallback-only), unmigrated screens intact,
  backend untouched.
  **Batch E part 1 — clients registry complete 2026-08-24:** index fully
  migrated (header partials, data-card shell, token filter row with
  field-labels, scoped `#clients-screen` DataTables skin replacing striped/
  bordered/dark BS table classes — no !important thanks to
  `autoWidth:false`, P1-5 keyboard rows with Enter/Space + focus outline,
  success flash → layout-matching toast); create/edit wrappers adopt header
  partials around the untouched `_form` include. Feed/filters/panel-fetch
  contract byte-preserved; actions cell incl. native confirm() is
  controller-owned and untouched (confirm-modal helper stays deferred —
  no view-side consumer possible without backend edits; revisit in F).
   Caught real Tailwind-v4 hazard: dynamic spacing generated `.w-100`
   against Bootstrap's — removed from markup. All gates green: 213/1056,
   Pint, collision sweep clean at source level for migrated files,
   restricted-ACL probe intact, unmigrated DT screen unaffected.
   **Batch E part 2 — clients CRUD partials complete 2026-08-24:** `_form`,
   `_details`, `_gip`, `show` all migrated presentation-only. `_form`:
   grid scaffolding + field-label with new for/id pairs (a11y), errors →
   field-error, footer buttons → btn-subtle/btn-navy; every name/id/
   conditional/old() binding preserved, `addOrgField()` className string
   synced in the same edit as the static stack (`mb-2` → wrapper gap).
   `_details`: dual-mode panel/full-page kept (h2 vs h1, inline script stays
   inline for executeScripts() re-execution, photo-modal IDs + d-none
   toggles untouched, native confirm kept); button vocabulary mapped
   (subtle/navy/gold/red); status cells → `.status-badge is-*`; legacy
   `w-100` pre-emptively swapped to `w-full`. `_gip`: functional BS
   accordion/modal chrome preserved; inner content tokenized;
   FontAwesome icons (never loaded) replaced with the established inline-
   SVG style; multiline GIP values via `whitespace-pre-line` (no raw
   `{!! !!}` output). `show`: breadcrumbs wrapper only — no page-header
   duplication. Route-parity gate proved no route names dropped; all three
   GIP branches verified by direct render against unsaved models (no DB
   writes); create-page probes 11/11 markers; suite 213/1056, Pint,
   view:cache, source+bundle gates clean.
   **Next: Batch F — transactions module** per §8.10 (confirm-modal helper
   becomes viable there: its confirm points are view-side). Households/
   duplicates/family-members were NOT in approved Batch E scope and stay
   deferred pending an owner call.
   **Batch F — transactions module complete 2026-08-24:** all four screens
   migrated presentation-only. Index: scoped `#transactions-screen` DT skin
   (autoWidth:false, no `!important`, `style="width:100%"`), applied-filter
   chips (aria-live, single-param removal URLs, model-name labels with raw-
   value fallback for unresolvable deep links), toast flash, export dropdown
   moved into page-header actions, status column → `.status-badge is-*`,
   money columns → `num-cell`. Inline-edit protocol, feed params, export
   URLSearchParams key set (9 keys) and filter/cascade JS all proven
   unchanged by line-diff + live probes; delete flows swapped to the new
   `partials/confirm-modal.blade.php` (`window.uiConfirm()` promise API +
   declarative `form[data-confirm]`, submits via
   `HTMLFormElement.prototype.submit.call` so no synthetic submit fires).
   create/edit: radio trio, clients-search, TUPAD toggle, `$isSelf/
   $isCustom` prechecks all byte-identical; show: definition grid +
   data-confirm delete. **Defect fixed:** page-header partial echoed
   `$actions` escaped (`{{ }}`) — header buttons rendered as literal text
   since Batch B adoption; now `{!! $actions !!}` with contract comment;
   regression probe confirms real markup on clients index too. Suite
   213/1056, Pint, view:cache, source+bundle gates clean; restricted ACL
   probe intact. **Next: Batch G — scholars dependency fix first, then
   scholars module** per §8.9/§8.10 (NOT started).
    **Batch G — all remaining views complete 2026-08-24:** every remaining
   unmigrated screen now uses the shared vocabulary. Group order: scholars
   (4 views — **P1-4 defect fixed**: index was missing its jQuery/DataTables
   bundle pushes and carried a stray `});`; dead screen restored), scanner
   (navy viewport + gold frame, script byte-preserved), payout attendance +
   unpaid index (config contract kept; row deletes → uiConfirm + silent
   reload), admin users + five permission matrices (reset-password modal
   contract preserved; toggles → uiConfirm with revert-on-decline;
   `.matrix-table` skin), sessions/audit/reports/update-logs (audit kept
   client-side after a serverSide mistake was caught and reverted; force-
   logout → declarative `data-confirm`), seven public standalone heads
   (own `<head>` kept, built CSS pulled via @vite, Bootstrap CDN retained so
   JS-injected BS-class markup still renders; camera/QR/self-service scripts
   byte-preserved incl. Decision C QR payload and pre-existing native
   confirms), and the Group-3 remainder (households index/create/show,
   family-member create, duplicates bulk-delete → uiConfirm; controller-
   rendered actions column untouched). `@source` block grew to cover every
   Batch G view in the same changes. Gates: build clean, view:cache OK,
   suite **213 passed / 1056 assertions**, Pint passed, hazard greps clean
   (no w-100/w-50, no stray confirm(), confirm-modal included where used),
   frozen-script line-diffs verified. **UI migration track is now markup-
   complete** — remaining track decisions are coexistence/cutover (Bootstrap
   retirement), NOT new batches.

### Current Milestone

**Phase 2 — UX/UI Modernization** — **Phase 2A (Dashboard), Phase 2B
(Global Search), Phase 2B Remediation v3 (App Shell Spacing & Sidebar
Polish), Global Typography (Inter/Outfit prototype baseline), Phase 2C
(FilterChips) + its test suite, Phase 2D (Responsive Polish), and Phase 2E
(Tests & Final Verification) complete 2026-08-28.** 264 tests / 1227
assertions / 0 failures / 0 risky, Pint clean, build clean. Calendar and
Notifications explicitly deferred. No database schema changes planned.

**Phase 2E (Tests & Final Verification) — done 2026-08-28:** resolved the
last pre-existing risky test (`HouseholdTest::test_households_pages_load_for_permitted_user`).
Root cause: `resources/views/households/show.blade.php` opened
`@section('content')` inside `@if (! $isPanel)` but never closed it with
`@endsection` → `startSection` left an output buffer open after render (PHPUnit
"did not close its own output buffers"); the full-page body was also emitting
the content before the layout `<html>` skeleton (`sections['content']` never
populated). Fix was the one-line `@endsection` before `@endif` — panel
(`?panel=1`) / DetailsPanel path untouched, no test modified. Full suite
**264 passed / 1227 assertions / 0 risky**, `pint --test` passed, build clean,
production `main_system` untouched. Detail:
`docs/IMPLEMENTATION_LOG.md` 2026-08-28 Phase 2E entry.

**Phase 2C (FilterChips) — done 2026-08-27:** one shared FilterChips component
(JS + Blade partial) on top of the existing server-side DataTables feeds,
integrated into all 7 modules (Clients, Households, Transactions, Scholarship
Reports, Payouts, Unpaid Verifications, Audit Logs). URL persistence via query
string; multi-value OR within a category, AND across categories; municipality→
barangay cascade; Clear All + per-chip removal; `copySharedComponents()` Vite
plugin fixes the DetailsPanel/FilterChips delivery gap. **ACL option parity:**
option lists are ACL-scoped ONLY where the feed enforces scope (Clients,
Households, Transactions); Scholarship/Payouts/Unpaid feeds are NOT scoped so
their option lists stay unscoped (matches pre-existing v1 behavior — this was a
corrective decision after a one-test failure). DetailsPanel.js 404 (Phase 2B)
resolved. Full detail:
`docs/IMPLEMENTATION_LOG.md` 2026-08-27 Phase 2C entry +
`docs/PHASE_2C_INSPECTION_REPORT.md`.

**Phase 2C test suite (FilterChipsTest) — done 2026-08-27:** 24 tests / 90
assertions in `tests/Feature/FilterChipsTest.php` covering OR/AND semantics,
URL persistence + deep-link restore, chip removal, Clear All, municipality→
barangay cascade config, ACL option parity (scoped module options respect the
`tbl_user_municipalities` grant; enforcement applies before a hostile
municipality param; Scholarship/Payouts/Unpaid stay unscoped), program
permission filtering, backward-compatible single-value `where`, zero-result
feeds, transactions 8-param compatibility, and the Audit Logs client-side
model. Full suite **263 passed / 1227 assertions** green; `pint --test`
passed; production `main_system` untouched. Detail: `docs/IMPLEMENTATION_LOG.md`
2026-08-27 test-suite entry.

**Phase 2C positioning remediation — done 2026-08-27:** real-browser
verification (Playwright) confirmed the FilterChips popover was landing
off-screen (~886 px below the button) because `.filter-chips` lacked a
positioned containing block. Fixed minimally by adding `position: relative`
to `.filter-chips` in `resources/css/app.css` and rebuilding (`vite build`).
Verified in a real chromium browser at desktop (1280×900) and mobile
(390×844): popover `insideViewport: true` both widths, desktop `deltaY = 6px`
below the button with `deltaX = 0`, mobile renders as a `fixed` bottom sheet;
chip/count/Clear All/Escape all functional, zero console errors. Full suite
still **263 passed / 1227 assertions** green (CSS-only change); `pint --test`
passed. Production `main_system` untouched. Detail: `docs/IMPLEMENTATION_LOG.md`
2026-08-27 remediation entry.

**Phase 2D — Responsive Polish — done 2026-08-28:** real-browser (Playwright
chromium) responsive pass across 9 widths (1440×900 … 320×568). Decision
D5 (single 768px tier: mobile < 768px, tablet/desktop ≥ 768px, Bootstrap 992px
sidebar kept) → DetailsPanel media query reconciled to `767.98px` to match
FilterChips. Decision D1 (DataTables `scrollX: true` for wide tables only,
preserving server-side DataTables) → Transactions gained `scrollX: true`, plus a
cross-cutting `columns().adjust()` hook on offcanvas show/hide + DetailsPanel
transitionend in the layout. Decision D2 (mobile-only touch-target tier
< 768px, per prototype) → mobile filter popover is now a floating bottom sheet
with ≥ 36–40px targets, 16px inputs (iOS zoom), 18px checkboxes. D3 → Audit
Logs duplicate `return json.data; }` SyntaxError removed (DataTables upgrade +
FilterChips init restored). **Defect fixed:** a Phase-2D Blade comment containing
a literal `@stack('scripts')` inside the layout inline `<script>` was doubling
every pushed script (DetailsPanel/FilterChips/DataTables/jQuery ×2) with
"already declared" console errors — comment rewritten, pushed scripts back to ×1.
Verified in-browser at 4–9 widths per screen: zero console errors,
`pageOverflow: 0`, DetailsPanel widths/scroll-lock/row-click correct at all
widths, Transactions `hasScrollXBody: true`. Suite **263 passed / 1227
assertions** green (baseline, no regressions); `pint --test` passed; build clean
(`app-CSrJtVoO.css`). Production `main_system` untouched. Detail:
`docs/IMPLEMENTATION_LOG.md` 2026-08-28 Phase 2D entry +
`docs/PHASE_2D_INSPECTION_REPORT.md`.

**Documents created (Phase 2):**
- `docs/PHASE_2_INSPECTION_REPORT.md` — 26-section inspection report
- `docs/implementation/PHASE_2_KPI_DEFINITIONS.md` — KPI business definitions from v1
- `docs/implementation/PHASE_2_PRE_IMPLEMENTATION_CONFIRMATION.md` — formal confirmation

**Previous milestone (P8 Hardening):** Hardening half complete 2026-08-24;
cutover execution pass pending owner-approved pass. Suite **213 tests /
1056 assertions** green (now 240 / 1137 with Phase 2A+2B tests); Pint clean.

**Pre-P8 IA consolidation — done 2026-08-30:** owner-approved pre-P8
information-architecture pass over the Tailwind shell, presentation-only:

- **Grouped Access Control sidebar** in `$shellSections` catalog: scanner
  engine + payouts become single hub items (aggregated `canAccessPage` gates);
  Access Control children collapse under a Bootstrap `collapse` toggle
  (route-active groups open; orphan grouplabels suppressed); AICS dead link
  removed.
- **Hubs:** `GET /scanners` (`ScannerController@index`) and `GET /payouts`
  (`PayoutAttendanceController@landing`) with per-destination pages; existing
  per-destination routes/controllers untouched.
- **Scholars Reports/Logs tabs** gated on `scholarship_reports.php` /
  `update_logs.php` via the ACL service.
- **Blade render bug fixed (root cause):** inline `@php(…)` appearing before
  the next `@endphp` in the same file is swallowed by
  `BladeCompiler::storePhpBlocks` and withheld from compilation → render-time
  parse error despite `view:cache` passing. All inline `@php(…)` eliminated
  from the sidebar (block-mode precompute + inlined `@include` args).
- **Verification:** suite **286 tests / 1336 assertions / 0 failures**;
  `pint --dirty` passed; `npm run build` clean with all hub/grid/group
  classes emitted; routes/permission-keys/DB audited (no schema, no
  `config/authorization.php`, no key renames); Playwright Chromium smoke
  **4/4 passed** (temp `smoke_superadmin` created on local `main_system` and
  deleted afterward — DB restored byte-identical aside from the consumed
  auto-increment counter).
- Detail: `docs/IMPLEMENTATION_LOG.md` 2026-08-30 entry.

**Clients module modal-first action workspace — done 2026-08-31** (clients-only
scope; presentation/interaction only, no schema/ACL/route-semantics/business-rule
change):

- **Add/Edit via modals** — `ClientController@create`/`edit` gained `?modal=1`
  branches returning the shared `clients._form` fragment; `store` gained an
  `expectsJson()` branch (mirroring `update`) plus a JSON duplicate-warning 422
  reusing `findPotentialDuplicates`. No navigation to `/clients/create` or
  `/clients/{id}/edit` in the normal flow (routes/views preserved for
  compatibility).
- **View = DetailsPanel (view-only)** — removed the embedded `#clientPanelEdit`
  inline form; the panel's Edit button now opens the edit modal. Delete uses
  `window.uiConfirm` → JSON (→ `details:deleted` while open / native redirect on
  full page).
- **Simplified 6-col table** (Client / Precinct / Municipality / Barangay /
  Category / Actions) with identity cell, icon-only View/Edit/Delete actions,
  existing multiselect FilterChips, right-aligned search, and the existing
  compact DataTables pager. Initial Status column removed — **BLOCKED** (`tbl_clients`
  has no `status` column; not inventing statuses per §14/§24).
- Reused `partials.confirm-modal` (`uiConfirm`), FilterChips/FilterConfig,
  DetailsPanel.js, Bootstrap modal — no new dependencies.
- **Verification:** suite **291 tests / 1362 assertions / 0 failures** (+5 new
  ClientTest modal/JSON tests); Pint clean; `view:cache` clean; `npm run build`
  clean. Playwright `e2e/clients.spec.ts` added but not executable locally (no
  `smoke_superadmin` seed in the production-copy DB — environmental, same as the
  existing smoke suite).
- Detail: `docs/IMPLEMENTATION_LOG.md` 2026-08-31 Clients modal-first entry.

**Clients module UX refinement pass — done 2026-08-31** (clients-only scope;
presentation/interaction only; on top of the modal-first workspace):

- **Action group** — header is now `[+ Add Client] [Export CSV ▾]`; the
  **"Remove Duplicates" action removed** from the UI (duplicate management route/
  backend untouched).
- **Filters** — added an accessible filter icon; Municipality / Barangay /
  Program remain multi-select + searchable via the shared FilterChips popover with
  **per-filter Clear**; **global Reset removed**. URL/query FilterChips contract
  preserved. Cascade (municipality → barangay) preserved.
- **Single search** — one `#clientsSearch` ("Search clients..."); DataTables
  built-in search box suppressed via `dom: 'lrtip'`; search still feeds the
  existing server-side `clients.data`.
- **Table** — unchanged 6 columns (Client / Precinct / Municipality / Barangay /
  Category / Actions); Client ID remains under Full Name; icon-only fixed-width
  actions; no unnecessary desktop horizontal scroll.
- **Edit-modal navigation bug fixed** — the `[data-edit-client]` handler now calls
  `e.preventDefault()`, so the `<a href="{clients.edit}">` no longer navigates to
  the full page after the modal opens. Add/Edit never navigate to a full page.
- **Modal design** — navy/blue header with white title + subtitle; grouped form
  (`_form`) with navy section headings (Personal / Address / Contact / Personal
  Details / Additional / Programs & Services / Registration); footer gray with
  **Cancel secondary + gold (`btn-gold`) primary** — "Add Client" / "Save Client".
- **Programs & Services** — Affiliated Organizations redesigned into a checkbox
  **multi-select** (`aff_org[]`, backend unchanged); options are only the existing
  aff_org values, grouped presentation-only; **no transaction-program values
  mixed in** (owner decision).
- **Duplicate/identity gate (modal-native)** — replaces the native `confirm()`
  with an in-modal "Possible existing client found" panel listing the server-computed
  matches and offering "Review existing client" (opens DetailsPanel) or
  "This is a different person — Continue" (`duplicate_confirm=1`). No
  `alert()`/`confirm()`/`prompt()` in the Add/Edit modal flow.
- **Verification:** full suite **291 tests / 1363 assertions / 0 failures** (create-
  modal label assertion updated per §25 to "Add Client"); `npm run build` clean
  (compiled CSS includes `.icon-btn`, `.client-form-group*`, `.client-aff-*`);
  Pint clean on changed PHP files. Playwright still not locally runnable (no smoke
  seed in the production-copy DB). Detail: `docs/IMPLEMENTATION_LOG.md` 2026-08-31
  Clients UX refinement entry.

**Clients module UI/UX refinement pass (Phase A→B) — done 2026-08-31** (clients-only
scope; presentation/interaction only; additive backend feed support):

- **4-filter pill toolbar** — Municipality / Barangay / Programs / **Category**,
  each a pill with embedded filter icon + active-count badge (`is-active` /
  `aria-pressed`); rounded toolbar shell wrapping the single rounded
  `#clientsSearch`. **Category** is new (authoritative `deriveCategory` values,
  server-rendered into the shared FilterChips menu; multi-value, ACL-scoped).
- **Filter popover fixed** — `#clients-screen .data-card { overflow: visible }` +
  raised `filter-multi-menu` z-index undo the `overflow:hidden` clipping that made
  the shared panel fail to open dependably. FilterChips reused as-is (searchable
  multi-select, **per-filter Clear, no global Reset**, municipality→barangay
  cascade; URL/query contract preserved).
- **Backend (additive, clients-only)** — `ClientService::CATEGORIES` const;
  `FilterConfig::staticCategory()`; `ClientController` `index()` appends Category,
  `data()`/`export()` accept a `category` multi-value param.
- **Table + pager** — font 0.875rem, comfortable padding; `dom:"<'top-chrome'lp>rtip"`
  = top row (Show-entries + compact windowed pager) + bottom row (info + pager); no
  forced desktop horizontal scroll.
- **Modal 3 regions** — fixed gray footer (Cancel secondary + **gold** primary
  `form="clientForm"`, "Add Client"/"Save Client"); navy header non-scrolling; only
  body scrolls; `_form` modal mode renders no inline footer; **`novalidate`** →
  server-driven **modal-native** validation error list + `.is-invalid` field
  highlight + focuses first invalid field; duplicate gate stays modal-native.
- **Programs & Services grouped** — Affiliated Organizations (7 `aff_org[]`
  values) grouped presentation-only under category headings (Livelihood &
  Employment; Community Programs); checkbox multi-select unchanged through
  `syncAffiliations`.
- **Verification:** full suite **295 tests / 1380 assertions / 0 failures** (+4 new
  tests: category feed filter, index renders category segment + options, fixed footer
  + single search & 6-column thead); `npm run build` clean (`.client-aff-group*`,
  `.seg-btn-icon`, `.filter-toolbar`, `.top-chrome`, `bg-neutral-100`);
  `view:cache` clean; Pint clean on `ClientController.php`/`FilterConfig.php`/
  `ClientTest.php` (only the pre-existing 3 flags in `ClientService.php` remain).
   Playwright still not locally runnable (no smoke seed in the production-copy DB).
   Detail: `docs/IMPLEMENTATION_LOG.md` 2026-08-31 Phase A→B entry.

**Clients details-panel UI/UX restructure — done 2026-09-02** (clients-only scope;
presentation-only restructure of `clients._details` + the shared panel shell — no
schema/route/controller/model/service/permission/business-rule change):

- **Fixed shell intact** — the DetailsPanel remains a fixed right drawer (480px
  desktop / 50vw tablet / full-width mobile), header + action bar fixed, body
  scrolls independently. Added `.details-panel-inner { display:flex; flex:1;
  min-width:0 }` so long content shrinks instead of widening the drawer.
- **Info hierarchy** — the body no longer duplicates the identity chrome in panel
  mode. Seven scannable sections, in order: Personal / Contact Information &
  Address / Additional / Household / Family Composition / Transactions / Audit.
  Labels subordinate to values (existing `.details-field` vocabulary).
- **Collapsibles** — Family Composition and Transactions are now accessible
  button-based accordions (`aria-expanded`/`aria-controls`, visible chevron,
  keyboard accessible), wired by an inline IIFE re-executed by
  `DetailsPanel.executeScripts()`. Data preserved.
- **Avatar fit** — `data-panel-avatar` moved outside `[data-panel-body]` (was an
  overflowing 180px image + name in the 64px header box); now a 64px photo
  (object-fit cover) or a gold initials placeholder.
- **Audit Information** — from the **existing** `tbl_audit_logs` (read-only):
  Created By = `ADD_CLIENT` log username; Created At = stored
  `tbl_clients.created_at`; Last Updated By/At = newest `EDIT_CLIENT` log.
  Genuinely unavailable values render `—` (no fabrication, no new tracking).
  Verified live: client id=1 → Created By `jordi`, Last Updated By/At `—`.
- **Age** uses the stored `$client->age` (authoritative `deriveAge()` write).
- **Verification:** suite **297 tests / 1397 assertions / 0 failures** (panel
  partial-render test still passes); `npm run build` clean; `view:cache` clean;
  static render check confirms all 7 sections in order, no `undefined`/`null`
  literals, avatar + audit resolve correctly. No PHP files changed → Pint N/A.
  Detail: `docs/IMPLEMENTATION_LOG.md` 2026-09-02 entry.

**Clients details-panel follow-up: action bar + fixed-drawer scrolling - done
2026-09-02** (clients-only; presentation/code-behind follow-up to the restructure
above - no schema/route/controller/model/service/permission/business-rule change):

- **Four-action bar** (in `.details-actions-line`) - existing real routes/ACL:
  - **Add Transaction** → `route('transactions.create', $client)` (`btn-navy`),
    rendered only under `$acl->canAccessPage($user, 'all_transactions.php')` (the
    same condition full-page mode uses). Uses the existing `transactions.create`
    route with the client id; backend still enforces the `page:all_transactions.php`
    gate + `TransactionController::create()` per-record `canAccessRecord`. No
    bypass.
  - **Open Full Page** → `route('clients.show', $client)` (`btn-subtle`) - the
    existing `clients.show` route in the same `page:clients.php` group that gates
    the panel. No duplicate page/route.
  - **Edit** (`btn-gold` modal) + **Delete** (`btn-red` form) unchanged. The added
    wrapper div does not break Edit/Delete (delegated document-level handlers).
- **Fixed-drawer scrolling** - `.details-panel-inner`/`.details-body` gained
  `min-height: 0`; `.details-header`/`.details-actions` gained `flex-shrink: 0`.
  Only `.details-body` scrolls; header/action bar stay pinned; panel stays fixed to
  the viewport. No `position:fixed` on header/actions.
- **Responsive action bar** - `.details-actions-line` (100% flex-wrap) children are
  `flex: 1 1 calc(50% - 8px); min-width: 120px` → even 2x2 on the 480px drawer and
  full-width mobile drawer; delete submit fills its tile (`width:100%`); scoped so
  other panels keep their existing `.details-actions .btn` layout.
- **Design tokens** - reused existing `btn-navy`/`btn-subtle`/`btn-gold`/`btn-red`
  vocabulary + `--color-*` tokens; no new tokens, no hardcoded colors; allowlist &
  Bootstrap coexistence untouched.
- **Verification** - static render shows the correct resolved URLs
  (`/transactions/create/1`, `/clients/1`); suite **297 / 1397 / 0**, `npm run
  build` clean, `view:cache` clean. No PHP changed → Pint N/A.
  Detail: `docs/IMPLEMENTATION_LOG.md` 2026-09-02 (follow-up) entry.

---

## Completed Milestones

| Phase | Scope | Status |
|---|---|---|
| P0 | Laravel 12 foundation, env, baseline schema (additive 6-migration fixes), CI | Complete |
| P1 | Username auth on `tbl_users`, single-device `session_token`, ACL service + gates + `page:` middleware, audit logging | Complete |
| P2 | Clients registry, households, family members, duplicates, photos, student self-service, slide-over details panel | Complete |
| P3 | Transactions: 17-program `TransactionService`, CRUD, program-gated list/feed/filters/inline-edit, 4 CSV export modes | Complete |
| P4 | Config-driven scanner engine (14 keys / 8 modes), shared scan view, per-key routes + gates | Complete |
| P5 | Payout attendance (3 variants), unpaid verification admin + public self-service + search/verify/delete, BOM CSV | Complete |
| P6 | Scholars module: registry CRUD + feed + relink + client picker, GIP (with audit), grantee self-update + update-log viewer, scholarship reports + BOM CSV, QR viewer (decision C) | Complete |
| P7 | Administration: user creation (`register.php`/`add_user.php`), page/program permission management, multi-device exemptions, audit viewer + leaderboard, five `page:` route groups, sidebar links | Complete |
| P12 (approved contract) | Action authorization (`tbl_action_permissions`) + municipality scope (`tbl_user_municipalities`) on 5 pilot pages, admin screens under `manage_permissions.php`, all S2 `enforcement` off | Complete |

**Final P7 verification (2026-08-15):** full suite **158 tests / 769
assertions** green on `main_system_test` (incl. new `AdministrationTest` - 26
tests / 101 assertions); `vendor\bin\pint` clean on all changed files;
production `main_system` untouched (tests force `DB_DATABASE=main_system_test`).

**Final P12 verification (2026-08-16):** full suite **195 tests / 887
assertions** green on `main_system_test` (37 new tests in `ActionPermissionTest`,
`ScopeTest`, `AuthorizationAdminTest`, `AccessControlServiceTest`);
`vendor\bin\pint` clean; `tbl_action_permissions` + `tbl_user_municipalities`
created additively on local `main_system` (backup
`...\Temp\opencode\main_system_before_p12.sql`); committed baseline regenerated
sentinel-free; v1 untouched.

**Phase 2A verification (2026-08-27):** full suite **225 tests / 1097
assertions** green on `main_system_test` (13 new tests in `DashboardTest`);
`vendor\bin\pint` clean; production `main_system` untouched.

**Phase 2B verification (2026-08-27):** full suite **240 tests / 1137
assertions** green on `main_system_test` (14 new tests in `GlobalSearchTest`);
`vendor\bin\pint` clean; production `main_system` untouched.

**Phase 2B Remediation v3 verification (2026-08-27):** full suite **240 tests /
1137 assertions** green on `main_system_test` (no new tests — CSS/markup
only); `vendor\bin\pint` clean; production `main_system` untouched. Spacing
polish: sidebar `lg:overflow-visible` + `lg:w-[260px]!` (Bootstrap offcanvas
conflict), body bg `#F0F2F5`, sidebar brand/nav/footer padding + section
margin matched to prototype, responsive page padding (20px mobile, 28px
desktop).

---

## Last Session Summary (2026-08-22 — functional-completeness fixes)

Seven approved audit fixes, minimal-change policy:

1. **Admin password reset restored** (v1 `manage_php.php` is a super-admin
   user-management/password-reset screen, not a PHP editor): `page:*` route
   group + sidebar link, `UserController@index/resetPassword`, min-8/confirmed
   rule, `'*'`-holder targets protected (data-driven), `password_resets` log
   row + `PASSWORD_RESET` audit via `AuditService`. Reset-log viewer/CSV UI
   deliberately not reproduced.
2. **Transaction full-page edit** now persists `comments`/`gwa`/`units`
   (v1 `edit_transaction.php` parity) + edit-view fields.
3. **Online users**: v1 filtering semantics restored (token + 20-min window +
   `'*'`-holder exclusion), Online badge; no longer lists every user.
4. **UserCreateRequest** password gained the missing `min:8` (log corrected).
5. **RBAC consolidation documented**: the six granular v1 keys were
   grantable-but-inert in v1 itself → v2 parity confirmed, recorded in
   ADR-003; P8 reconciliation query 7 inventories ALL production page keys.
6. **Scholars client-search** governed by `scholars.php` scope via route
   defaults (`scopePage`); transactions picker unchanged (S-1 closed).
7. **Login throttle** uses the trimmed username for both credentials and
   throttle key (v1 parity).

Suite **213 tests / 1056 assertions** green on `main_system_test`; pint clean;
`main_system` untouched. Details: `docs/IMPLEMENTATION_LOG.md` 2026-08-22.

---

## Last Session Summary (P7 Administration)

The P7 Administration subsystem was built end-to-end per the owner-approved
contract (`docs/implementation/P7_ADMINISTRATION.md` + `ADMIN_ANALYSIS.md`).

**Owner decisions settled before building (via question prompt):** no
automatic/seeder bootstrap — production first-admin access is a reviewed
one-time cutover SQL grant of a `tbl_permissions` row with `page_name = '*'`
for a nominated existing user; the seven `MANAGE_*` audit strings approved
exactly; **no** `active` column (v1 create-only); audit enhancements C/D/E
(audit-on-permission-writes, subject-name resolution for the P7 tables,
exemption/`'*'` no-op silence) shipped, A/B/F (server date-range filter,
leaderboard date-window, IP metadata) deferred; no municipality/data-scope
authz, no action-level CRUD.

**Completed:**

- `UserController` (create-only, `MANAGE_USER_CREATE`), `AdminPermissionController`
  (page full-replace + `'*'` toggle, program full-replace, idempotent exemption
  toggle), `AuditController` (viewer + `{data,users,actions}` feed + leaderboard),
  four `FormRequest`s, five Blade views, five `page:` route groups, sidebar links.
- `tests/Feature/AdministrationTest.php` — 26 tests / 101 assertions (authz,
  all 7 audit actions, no-op silence, feeds, no-secret payloads).
- Full suite **158 tests / 769 assertions** green; pint clean.

**Deviations documented in the log:** `pages`/`programs` are `nullable|array`
(not the contract's `required`) so the v1 remove-all full-replace works; no-op
exemption toggle returns a message instead of an audit row (contract §11.5).

**Next:** P8 — Hardening + cutover (see below).

---

## Last Session Summary (P12 action authorization + municipality scope)

The owner-approved Pass 12 contract (`docs/ADMIN_ANALYSIS.md` §§22-23) was
implemented end-to-end. It layers the **action** and **municipality**
dimensions on P1's ACL behind a per-page `enforcement` flag that is **off** for
all five pilot pages (`config/authorization.php`) — pre-P12 behavior is
byte-identical until the flag flips (S2 §13 rollback = flip it back).

**Completed:**

- Two additive migrations + regenerated sentinel-free baseline
  (`schema:dump`); local `main_system` backup before migration.
- `AccessControlService`: `canAccessAction` (uppercase-normalized, VIEW = page
  row, `'*'` bypass, fail-closed unknowns), `permittedActions`,
  `hasAllMunicipalities` (reserved `0` marker), `effectiveMunicipalityIds`,
  `canAccessRecord`, `applyMunicipalityScope` (Builder `whereIn`); page config
  read as literal array index (dot-notation would split `clients.php`).
- `action` middleware alias + `Gate::define('action', ...)`; §11 route map (18
  `action:<page>,<action>` instances); `RecordMunicipality` data resolvers.
- Scope seams on feeds/searches + record-level checks on single-ID/write
  endpoints across the 5 pilot controllers.
- Two admin screens under `page:manage_permissions.php` (actions grid with
  composite `page:ACTION` checkboxes, VIEW excluded; scope screen with the ALL
  toggle + check-all) — full-replace saves, `MANAGE_ACTION_PERMISSIONS` /
  `MANAGE_SCOPE_ASSIGNMENTS` audits, no-op silence.
- 37 new tests (`ActionPermissionTest`, `ScopeTest`, `AuthorizationAdminTest`,
  `AccessControlServiceTest`); full suite **195 tests / 887 assertions** green;
  pint clean.

**Open items:** S2 cutover (flip `enforcement` per page, owner decision);
enforcement-aware UI hiding (uses `permittedActions`) if the owner wants it.

---

## Last Session Summary (2026-08-31 — UX-1..UX-7 presentation-only modernization)

**Scope:** presentation/interaction only — no schema, migration, permission key,
ACL, business rule, route-semantics or `C:\xampp\htdocs\system` change. Owner
approved UX-1..UX-7 + C1/C2/edit-scope/audit-polling gates. Full detail:
`docs/IMPLEMENTATION_LOG.md` (2026-08-31 entry) + `docs/UI_UX_AUDIT.md`.

**Completed:**
- **UX-1** broken-panel repairs: households feed `id`, payouts GET `?panel=1`
  variant-aware detail, audit feed `id` + manual Refresh (poll removed), Access
  Control `admin.users.show` is-set.
- **UX-3/C2** column flattens: transactions (`columnDefs` hidden non-editable),
  scholars orphan `Actions` th removed, payouts municipality/scanned_by hidden.
- **UX-4** Payouts tab workspace (`payouts/index.blade.php` cards → tab bar).
- **UX-5** Scanner unified selector (`scanners/index.blade.php` cards → select).
- **UX-2** lightweight edit-in-panel: additive `expectsJson()` JSON branches on
  `clients.update` / `scholars.update` / `admin.users.reset-password`;
  `DetailsPanel.submitPanelForm()`; panel-edit forms for Scholars/Users (compact)
  and Clients — the clients **compact** form was **superseded the same day** by
  the full shared `clients._form` reused inside the panel (sectioned profile +
  panel action bar, `data-delete-client-form` uiConfirm, photo modal preserved;
  see the clients details-panel rework entry below); **fixed the pre-existing
  users-panel crash** (non-existent `admin.users.edit` route).
- **UX-6** dead `openClientPanel` wired in `clients/index.blade.php`.
- **UX-7/C1** clients delete confirm → `uiConfirm`; scholars Client-ID `prompt()`
  → Bootstrap modal; CSS dedupe audit-only (ui.css retained).

**Verified:** PHPUnit **286 / 1336 / 0**; Pint clean; build clean; Blade cache
clean; `DetailsPanel.js` resources/public byte-identical; Chromium smoke 4/4
(temp `smoke_superadmin` created + deleted, 0 rows). Clients details-panel
rework (full-form panel editing + sectioned profile): suite still **286 / 1336 /
0** (targeted `ClientTest`/`PhotoTest`/`GipTest` 10/4/6 green), Pint + `view:cache`
clean — detail: the 2026-08-31 rework entry below.

**Open / follow-up (NOT done, per approved scope):**
- Households edit-in-panel NOT built (no `houses.update` route today — would
  require a new business workflow, out of scope); view-only.
- Grantee self-update native `confirm()` calls left as-is (outside approved C1
  list); full CSS dedupe (UI_UX_AUDIT) not executed.
- Cross-browser Playwright regression NOT run (reserved per rules for explicit
  regression); only chromium smoke run.

---

## Last Session Summary (2026-09-02 — Clients UX refinement, clients-only)

**Scope:** clients-module UX refinements on the details panel + filters + edit
modal. **One authorized backend change** (photo 1MB + GD optimization); all other
changes are presentation/code-behind only. No schema, ACL, route, business-rule,
or production-data change; `C:\xampp\htdocs\system` untouched. Full detail:
`docs/IMPLEMENTATION_LOG.md` (2026-09-02 refinement entry).

**Completed:**
- **Header (#2):** `data-panel-sub` now shows only `ID: <id>`; category moved to a
  distinct `.details-category` line below the ID in `data-panel-meta`
  (`.details-category` light-on-dark pill for the fixed navy header). Header stays
  pinned (flex shell unchanged).
- **Filters (#5):** fixed the barangay option-visibility bug — `FilterChips.js`
  `applyCascadeVisibility()` now shows all barangay options when **no municipality
  is selected** (matching `isValidBarangay`), narrowing to the selected
  municipality's barangays only once one is chosen. Previously the Barangay filter
  appeared empty until the user typed in the search box.
- **Photo in Edit modal (#6):** `_form.blade.php` adds a Profile Photo file input
  (edit+modal only); the modal update success handler posts a chosen photo to the
  existing `clients.photo.store` route (gated `action:clients.php,edit`).
  `PhotoController::store` `max:5120` → `max:1024` (1MB) and got additive JSON
  success/error branches; `PhotoService` adds GD `optimizeImage()` (1600px cap,
  re-encode in original format) for uploaded files; camera path unchanged.
- **Verified already-satisfied (no change):** per-category Clear + per-value chip
  × + Clear All filters (#4); Family Composition + Transactions display (#7/#8);
  four-action token button bar + responsive behavior (#1/#9).
- **Documented conflict, NOT implemented:** Task 3's selectable Category dropdown —
  `category` is authoritative age-derived (`ClientService::deriveCategory`/
  `attributes`), the `_form` field is readonly and JS auto-populated; per the task
  directive the change was stopped and documented rather than creating a
  conflicting selectable rule.

**Verified:** PHPUnit **297 / 1397 / 0**; Pint clean (PhotoController + PhotoService);
`npm run build` clean (vite v6.4.3, 56 modules); Blade `view:cache` clean. PhotoTest
(file/camera/invalid/required) still green with the 1MB limit + GD optimization.

**Open / follow-up (NOT done):**
- Photo upload from the Edit modal fails silently if `clients.photo.store` errors
  (client save still succeeds); consider surfacing a non-blocking toast later.
- Cross-browser Playwright regression NOT run (reserved per rules for explicit
  regression); no browser run performed in this session (no test seed on the
  production-copy `main_system`).

---

## Last Session Summary (2026-09-06 — Phase 25: DataTables skin CSS ownership)

**Phase 25 deliverable:** first controlled Bootstrap-**CSS** ownership migration — replaced the
DataTables 1.13.6 Bootstrap 5 skin CSS CDN (`dataTables.bootstrap5.min.css`, 11 per-screen
`@push('styles')` links) with the project-owned, self-contained
**`public/css/datatables.css`** (new, ~430 lines, plain CSS, no build step, HTTP 200 / 20,561 B,
**zero `var(--bs-` consumption**). It (a) reasserts the upstream skin's core chrome with identical
selectors/values (sorting arrows, processing loader, wrapper/length/filter/info/paginate layout,
`table.dataTable` base, alignment utilities, scroll/scrollFoot, responsive 767px centering,
`--dt-*` tokens, dark-mode overrides) and (b) owns the Bootstrap class names the skin JS emits —
`table(.sm)` + `.align-middle`, `.form-control(-sm)` filter input, `.form-select(-sm)` length
select, `.pagination/.page-item/.page-link` — **scoped to `.dataTables_wrapper`**, using ui.css
navy/gold tokens exactly where the app re-points Bootstrap and Bootstrap 5.3.2 literal values
elsewhere. Retained: `dataTables.bootstrap5.min.js` (class emitter), jQuery 3.7.1, all per-screen
token skins, Bootstrap 5.3.2 CSS (8 links), the DEAD `admin/users/show:134` trigger.

**Verification evidence:** 0 `dataTables.bootstrap5.min.css` refs in views and compiled output
(11 `asset('css/datatables.css')`; `rg --no-ignore` over `storage/framework/views` = 11 files /
0 CDN); full suite **301 passed / 1419 assertions / 0 failures** (40.04s); Pint clean;
`npm run build` clean (vite v6.4.3); `view:cache` + compiled scan + `view:clear` clean; live HTTP
checks OK. **E2E suites not run** — auth-gated DataTables screens, no admin login account
(ephemeral accounts forbidden), DB byte-untouched at 1604 audit rows (consistent with Phases
20–24). No schema/ACL/route/controller/model/DB/business change.

**Bootstrap-removal gate (`NOT READY`)** — remaining Bootstrap CSS consumers (evidence-based):
- `.btn` base consumed by the ui.css 49-`--bs-btn-*` variable bridge (`btn-primary`×1,
  `btn-danger`×1, `btn-outline-primary`×2 among 26 `btn btn-*` usages), `btn-close`×14,
  `btn-group`×2.
- `.form-control`/`.form-select`/`.form-check-input` bases (ui.css themes focus/checked only).
- `.alert`, `.table`/`.table-sm` outside DataTables, `.table-responsive` wrapper
  (`transactions/index:130`), `.dropdown-toggle`/`.dropdown-menu`.
- 7 standalone public pages (auth/login, qr/viewer, students/verify, students/update-photo,
  students/photo-upload, unpaid_verifications/self-service, grantee_update/self-service) each
  loading Bootstrap CSS in their own head.

**Next candidate (Phase 26, identify only):** own the `.btn` base (making the `--bs-btn-*` bridge
project-pure) + the `.form-control`/`.form-select`/`.form-check-input` bases in project CSS —
app-wide change that needs a browser-verified, per-screen pass (an admin account or a
per-applicable approval for ephemeral verification) — then the standalone public pages. The
dangling `admin/users/show:134` trigger remains a finalization-pass item. **No Phase 26 work was
executed in this session.**

Books updated: `docs/IMPLEMENTATION_LOG.md` (phase row + dated changelog),
`docs/TAILWIND_MIGRATION_EXECUTION_PLAN.md` (status, §A.1, §D.1, §F.1/F.2, §O), this file.

---

## Last Session Summary (2026-09-07 — Phase 27: Final Bootstrap CSS removal)

**Phase 27 deliverable:** the third and final controlled Bootstrap-**CSS** ownership migration —
the Bootstrap-CSS **removal** itself. Removed **all 8** Bootstrap 5.3.2 CSS CDN `<link>`s and owned
every last family the remaining screens render, byte-faithful to 5.3.2, in `public/css/ui.css`
§4.8–4.10. Post-change: **zero Bootstrap CSS anywhere; removal-gate READY** (Phase 23 gate).

**What changed:**
- `ui.css` **§4.8** (new): `.form-label`; the full `.accordion*` subsystem (permissions) incl.
  closed/collapsed chevron data-URIs, focus `#86b7fe`/`rgba(13,110,253,.25)`, active subtle/
  emphasis, collapse border-width rules; `.list-group*` incl. `.list-group-flush` + `.list-group-
  item-action` (border-color resolves `#dee2e6`, item padding `.5rem 1rem`, flush borders
  `0 0 1px`, last-child bottom 0).
- `ui.css` **§4.9** (new): Bootstrap **utility parity with `!important`** so the utilities that
  today beat Tailwind's non-important twins keep winning: `.d-none` (JS classList contract — no
  Tailwind equivalent; JS-driven across households/create, family_members/create, grantee
  self-service + `_self_update_tab`, unpaid self-service, qr/viewer, photo-upload, clients/_form),
  `.d-block/-inline-block/-flex/-inline-flex`, flex-row/column + justify/align, position +
  `.top-0`/`.bottom-0`, `.text-start/-end/-center` + the text-color palette, `.fw-bold`,
  `.fst-italic`, `.fs-4`, `.small`, `.w-auto`, `.min-vh-100`, `.img-fluid`, `.img-thumbnail`,
  `.bg-white`, `.bg-transparent`, `.border` 1–3, `.rounded(-1/-2/-3/-circle/-pill)`, `.overflow-*`,
  the margin/padding ladder 0–5 (Bootstrap scale — `mb-3`/`mb-4`/`p-3`/`gap-3` differ from
  Tailwind's at steps 3–5; Bootstrap wins today, so the ui.css `!important` values reproduce
  today's pixels) + auto sides, `.gap-0–5`; grid `.row`/`.g-0…g-5` + `@media(min-width:576px)`
  `.col-sm-4/8` + `@media(min-width:768px)` `.col-md-3/4/6`.
- `ui.css` **§4.10** (new): Reboot + `_type.scss` element parity — **universal
  `*,*::before,*::after{box-sizing:border-box}`** (Bootstrap's global box model; see the fallout
  caught below), `body{margin:0;color:#212529}` (`--bs-body-color`), element defaults
  (h1–h6/p/ul/ol/dl/dt/dd/blockquote/hr/abbr/address/a/caption/th/label/button/form-element
  inheritance/textarea), and `_type.scss` heading sizes incl. the `1200px` media query.
- Removed the 8 CDN `<link>`s + refreshed the stale "Bootstrap kept for…" comments:
  `layouts/app`, `auth/login`, `qr/viewer`, `grantee_update/self-service`,
  `unpaid_verifications/self-service`, `students/photo-upload`, `students/update-photo`,
  `students/verify`.
- `admin/users/show.blade.php`: **removed the proven-dangling `#passwordModal` trigger** (Phase
  23/26 finalization candidate — target exists in no document, no Bootstrap JS loads; Edit button
  + native inline password form kept).
- `e2e/alpine-phase0.spec.ts`: login smoke now asserts the Bootstrap CSS link **absent**
  (`link[href*="bootstrap@5.3.2"]` count 0) + built `ui.css` link present (count 1).

**Fallout caught by live verification (and fixed):** the initial §4.8–4.10 pass left two real
gaps, both caught by the computed-style harness before any cleanup: (a) body/`.data-card` color
drifted `rgb(33,37,41)`→`rgb(0,0,0)` (missing Reboot `body{color:#212529}`), and (b) **every
padded element's width shifted** — `.form-control` 549→600px, `.btn-navy` 271→303.5px, login card
420→369px, body content 1280→1232px — all symptoms of the **lost universal box-sizing:border-box**
(Bootstrap supplied it for the whole app; Tailwind preflight is absent here). The two §4.10 lines
above fixed it; the re-run diff is clean.

**Verification evidence:** full suite **301 passed / 1419 assertions / 0 failures** (Phase 26
state — this phase is CSS + one Blade trigger removal + one E2E assertion, no PHP logic); Pint
clean; `npm run build` clean (unchanged hash app-D3mz-Mcl.css 57.62 kB — ui.css is static);
`view:cache` clean. **Live Chromium harness** (`bs-capture.mjs` + before/after JSON, scratch
scripts now deleted) across `/login`, `/qr-viewer`, `/student/update-photo?search=`,
`/unpaid-verification`, `/grantee-update`: post-removal structural gates **5/5 (0 Bootstrap CSS
requests, `window.bootstrap` undefined, 0 console errors, 0 page errors, status 200)** and the
after↔before computed-style diff over 5 pages × 8 selectors (body, input.form-control,
button.btn-navy, label.field-label, .data-card, .alert, select.form-select, h1) × 44 props each =
**0 diffs**. Family spot-checks live: `.list-group` flex column, flush item borders 1px bottom /
`#dee2e6` / last-child 0, `bg-transparent`; `.d-none` display none; `h1.mb-3`=14px, `h1.mb-1`=3.5px
(Bootstrap utility values winning as today). Mobile spot-checks 375×667/768×1024 on `/login` +
`/student/update-photo`: no horizontal overflow, cards centered. **E2E suites not run** —
auth-gated screens (`.list-group`/`.accordion`/`.form-label` live DOM is auth-gated) require an
admin login account (none exists) and DB must remain untouched at 1604 audit rows (documented
limitation — those families byte-verified textually against the 5.3.2 dist + the two reachable
list-group branches verified live).

**Result:** PASS. Bootstrap CSS dependency: **8 CDN links → 0**; `--bs-*` live consumption 0;
removal-gate (Bootstrap CSS) **READY**; repo-wide `bootstrap@5.3.2` string only in the E2E absence
locator; DataTables skin (`datatables.css` Phase 25) + jQuery/DataTables/Alpine untouched.
Decisions: **Tailwind Preflight NOT enabled** in place of §4.10 (Preflight would shift bare
headings/img/hr/buttons on authenticated screens — §4.10 preserves pixels; a Preflight review is
future baseline work); the standalone Tailwind `@source` allowlist unchanged (`_self_update_tab` +
`admin/users/show` rely on the ui.css layer).

Books updated: `docs/IMPLEMENTATION_LOG.md` (phase row + dated changelog),
`docs/TAILWIND_MIGRATION_EXECUTION_PLAN.md` (completed list, §O paragraph + next-pending, §K
gate), this file.

---

## Current Work — P8 Hardening + cutover

**Goal:** harden the v2 application and execute the cutover plan
(`docs/MIGRATION_PLAN.md`), keeping `main_system` byte-identical to production.

**Focus:**

- **Production admin bootstrap runbook** (P7 carry-over): a one-time cutover SQL
  grant of a `tbl_permissions` row with `page_name = '*'` (and the four P7 page
  keys if preferred) for a nominated existing user — no seeder, no username
  checks. Optionally also the NULL-user bootstrap audit-row item from
  `ADMIN_ANALYSIS.md` (note `tbl_audit_logs.user_id`/`target_id` are NOT NULL).
- **Deferred P7 audit enhancements** (if owner opts in): server-side date-range
  filter, leaderboard date-window, IP metadata.
- **Hardening pass:** coverage gaps, error handling, perf, cutover rehearsal.

Reference: `docs/MIGRATION_PLAN.md` / `docs/MIGRATION_PLANNING.md` →
`docs/ENGINEERING_BLUEPRINT.md` §1.12.

**2026-08-29 pre-flight pass (READ-ONLY) + approvals:** P8 pre-flight report
delivered; all §H owner decisions approved (scope A.1–A.4 / A.5 deferred;
§D rollout order; §E default grant strategy; §F `'*'` bootstrap model; P7
audit enhancements + denial auditing deferred; ADR statuses aligned to
`ARCHITECTURE_DECISION.md`; staging rehearsal required, local fallback used).
Local flip/rollback rehearsal **passed** on `main_system_test` (real
config-file flip for `clients.php` → deny/grant/admin verified; revert →
prior behavior restored; temp test deleted; suite re-verified green). Detail:
`P8_DECISION_PACKAGE.md` → "OWNER DECISIONS & PRE-FLIGHT — 2026-08-29" and
`MIGRATION_PLANNING.md` §7.1. No production actions taken.

**2026-08-29 authority re-scoping (developer ≠ production owner):** the
developer holds NO Hostinger SSH or production DB access and performs NO
production operation. Super-admin = the **existing production account** (no
create/bootstrap; §F only if Q3 shows 0 `'*'` holders, DBA-executed). A
read-only production DBA handoff was prepared (`docs/DBA_RECONCILIATION_HANDOFF.md`)
— SELECT-only reconciliation Q1–Q7 with expected results and owner-decision
triggers; the developer does not run it. Responsibility matrix recorded in
`P8_DECISION_PACKAGE.md` ("RESPONSIBILITY MATRIX & DBA HANDOFF — 2026-08-29")
and `MIGRATION_PLANNING.md` §7.2. No production query has been executed by the
developer.

---

## Development Priorities (Phase 2)

1. **Phase 2A — Dashboard:** KPI cards, program distribution, activity feed.
   **DONE 2026-08-27.** 225 tests / 1097 assertions green; 13 new DashboardTest tests.
2. **Phase 2B — Global Search:** Server-side autocomplete in topbar with dropdown.
   **DONE 2026-08-27.** 239 tests / 1137 assertions green; 14 new GlobalSearchTest tests.
3. **Phase 2B Remediation — App Shell:** Topbar prototype alignment (sidebar
   top-to-bottom, white sticky topbar inside content column only).
   **DONE 2026-08-27 (v3).** 240 tests / 1137 assertions green; Pint clean.
   v2: moved navbar inside `<main>`, sidebar `lg:fixed`, z-index/padding.
   v3: sidebar overflow fix (`lg:overflow-visible`, `lg:w-[260px]!`),
   body bg → `#F0F2F5`, sidebar nav/footer/brand padding matched to
   prototype, section margin 24px, responsive page padding (20px→28px).
4. **Global Typography (pre-2C):** Prototype baseline applied to the shell —
   **DONE 2026-08-27.** Inter body (14px root, line-height 1.6, font
   smoothing, overflow-x hidden) + Outfit headings (600/1.3/-0.01em);
   `--font-*` and `--ui-font-*` tokens flipped. 240 tests / 1137 assertions
   green; Pint clean. Standalone public layouts remain on Roboto (out of
   scope — they don't use the shell layout).
5. **Phase 2C — FilterChips:** Shared component creation + the 7-module
   integration **DONE 2026-08-27.** 239 tests / 1137 assertions green; Pint
   clean; build emits `public/js/components/{DetailsPanel,FilterChips}.js`.
6. **Phase 2D — Responsive polish:** Panel breakpoints, touch targets, overflow.
   **DONE 2026-08-28.** D3 Audit Logs SyntaxError fixed; D5 768px reconciled;
   D1 `scrollX` + `columns().adjust()`; D2 mobile touch targets + bottom-sheet
   filter popover; layout `@stack`-comment corruption fixed; real-browser
   verified at 9 widths. Suite 263 passed / 1227 assertions green; Pint clean.
7. **Phase 2E — Tests and verification:** FilterChipsTest **DONE 2026-08-27**
    (24 tests / 90 assertions; OR/AND, URL persistence + deep-link restore,
    chip removal, Clear All, cascade, ACL option parity + feed enforcement,
    program permissions, backward-compatible single value, zero-result feeds,
     audit client-side model). **Risky HouseholdTest resolved 2026-08-28**
    (root cause: missing `@endsection` in `households/show.blade.php` leaving
    an open output buffer; one-line fix; panel path untouched). Full suite
    **264 passed / 1227 assertions / 0 risky** green; `pint --test` passed;
    build clean.
8. Keep the full suite green; run `vendor\bin\pint` before finishing; append
    entries to `docs/IMPLEMENTATION_LOG.md`.
9. **Pre-P8 IA consolidation — DONE 2026-08-30.** Suite **286 tests / 1336
    assertions** green; Pint clean; build clean; Chromium smoke 4/4. P8
    cutover execution remained NOT started (production-owner-gated).

---

## Open Decisions

- Soft-deletes / client-merge: in scope or not.
- Additive indexes on existing tables (recommended: yes).
- Git: this repo has no remote yet (docs live in a separate repo).
- **P8 (decided 2026-08-29, authority re-scoped):** hardening scope A.1–A.4
  approved, A.5 deferred; rollout order (§D), grant strategy (§E default), P7
  audit enhancements + denial auditing deferred — approved. ADR-001..009
  already carry the 2026-08-24 owner sign-off (007/010 stay Proposed). The
  developer holds NO production authority: super-admin = the **existing
  production account** (no create/bootstrap; §F only if Q3 shows 0 `'*'`
  holders, DBA-executed); production reconciliation runs via the DBA read-only
  handoff (`docs/DBA_RECONCILIATION_HANDOFF.md`); Hostinger SSH / PHP 8.3+ /
  staging are **OWNER/ADMIN ACTION REQUIRED**. Remaining inputs: super-admin
  **username** confirmation, DBA results (then the **C.9-6 policy** decision
  using the CAST-corrected Q6), staging availability.
- **P12 S2 cutover:** per-page flip order, grant strategy, and the flip
  procedure are **approved 2026-08-29**; execution still requires production
  access + a named super-admin before any flag is flipped or row granted. Do
  not decide the remaining inputs silently.

---

## Current Risks

- **P12 S2 rollout** — enforcement is off for all 5 pilot pages, so the action
  and municipality rows have **no effect yet** by design. Enabling a page
  without first granting its users action/scope rows would immediately deny
  those users (fail closed). Roll out per page: grant rows via the admin
  screens, then flip `enforcement`. Rollback = flip back.
- **P7 admin bootstrapping (carry-over, now P8-runbook)** — production
  `tbl_permissions` may lack rows for the four admin page keys, so no one can
  reach the P7 screens until a `'*'` (or those keys) is granted to a nominated
  user via reviewed cutover SQL. `tbl_audit_logs.user_id`/`target_id` are NOT
  NULL — the bootstrap audit row (if desired) must use a real user id.
- **Audit viewer scope** — v1 resolves display names only for
  `tbl_clients`/`tbl_transactions`/P7 subject tables; other tables show raw
  `target_id`; the feed has `LIMIT 10000` and only a client-side date filter.
  Don't over-promise parity.
- **Schema creep** — any hardening change touching the schema (e.g. deferred
  IP metadata, additive indexes) must go through the additive-migration +
  `schema:dump` baseline regen workflow (AGENTS.md), never destructive.
- **Payout (P5) watch items still stand** — no P5 write-path audits; unique
  scan constraint preserved; the `export_scanned_payouts_unpaid.php` dead link
  is deliberately not shipped.
- **Household full-page `show` ordering (pre-existing, surfaced by Phase 2E)** —
  the `#household-show-screen` panel block is emitted *before* the layout
  `<html>` skeleton on the standalone full-page route (outside the `content`
  section); pre-existing view-structure defect, not a regression. DetailsPanel
  (`?panel=1`) path is unaffected. Follow-up restructure recommended if the
  standalone page is used.

---

## Before Next Session

1. **Phase 2 full track is complete** (2A Dashboard, 2B Global Search + shell
   remediation + typography, 2C FilterChips + test suite + positioning
   remediation, 2D Responsive Polish, 2E final verification — including the
   formerly-risky `HouseholdTest`, now resolved; full suite **264 passed /
   1227 assertions / 0 risky**). Remaining: final owner-review, then the
   separate owner-approved P8 cutover execution pass.
2. Calendar and Notifications are DEFERRED — do not implement.
3. No database schema changes — all data from existing tables.
4. Keep the full suite green on `main_system_test`; confirm production
   `main_system` untouched.
5. **P8 carry-over (prep done 2026-08-29, authority re-scoped):** pre-flight +
   §H approvals complete; local flip/rollback rehearsal passed; DBA read-only
   reconciliation handoff prepared (`docs/DBA_RECONCILIATION_HANDOFF.md`);
   responsibility matrix agreed (developer = code/build/tests/docs/handoff/
   analysis only; production administrator/owner = backup, reconciliation,
   migration, grants, bootstrap-if-needed, config cache, flips, rollback,
   server ops). Cutover proceeds only via the production administrator/owner —
   not part of Phase 2 scope. Outstanding: super-admin username confirmation,
   DBA results (→ C.9-6 decision), staging availability.
6. **Household full-page `show` defect (pre-existing, reported not fixed):**
   the unconditional panel markup sits outside the `content` section, so the
   full-page route renders the content block before the layout `<html>`
   skeleton. DetailsPanel (`?panel=1`) is unaffected. A follow-up owner-approved
   restructure mirroring the `clients.show` + `clients._details` pattern is
   recommended if the standalone full-page route matters.
7. **Pre-P8 IA consolidation complete 2026-08-30** (grouped sidebar + hubs +
   gating; suite 286/1336; smoke 4/4). No DB/schema/`config/authorization.php`
   changes; the temporary local smoke user was removed (DB restored).
   P8 cutover remains owner-gated — do not execute.
 8. **Clients UI/UX refinement pass (Phase A→B) + final verification complete
    2026-08-31** (clients-only; on top of the modal-first workspace). Adds a
    **Category** filter (4th pill, authoritative `deriveCategory` values wired into
    `clients.data`/`export`), **fixes the filter popover** (clipped by
    `.data-card{overflow:hidden}` → now `overflow:visible` + raised z-index), adds
    a fixed gray modal footer with gold primary, grouped Programs & Services
    presentation, and modal-native validation. Final verification pass: full suite
    **295 / 1380 / 0 failures**, `pint --dirty` clean (the **3 pre-existing
    `ClientService.php` style flags are now resolved** — cosmetic `new Collection` /
    whitespace, no behavior change), `npm run build` + `view:cache` clean. No
    schema/ACL/route/business-rule change (additive backend feed support only). Do
    **not** expand these Clients patterns to other modules until the owner directs
    it, and do **not** touch the schema. **Status table column is BLOCKED** —
    `tbl_clients` has no `status` column (re-confirmed `SHOW COLUMNS`); per §14/§24
    do not invent statuses or repurpose `tbl_details.status`. Edit-modal navigation
    is now intercepted (`e.preventDefault()`); "Remove Duplicates" is removed from
    the UI (route kept). `e2e/clients.spec.ts` (and the smoke suite) still cannot
    authenticate against the production-copy `main_system` without a seeded test DB.
 9. **Clients details-panel UI/UX restructure complete 2026-09-02** (clients-only;
    presentation-only `clients._details` + shared panel shell). Fixed-shell
    identity/actions; seven scannable sections; accessible collapsible Family
    Composition & Transactions; avatar photo/initials fit; Audit Information read
    only from the existing `tbl_audit_logs` (`ADD_CLIENT`/`EDIT_CLIENT` →
    username via `tbl_users`), `—` for genuinely-absent values; Age stays the
    stored authoritative `$client->age`. Full suite **297 / 1397 / 0**, build +
    view:cache clean, static render check green. No schema/route/controller/
    model/service/permission/business-rule change. Do not expand these patterns
    to other panels without owner direction, and do **not** add audit tracking.
10. **Clients details-panel follow-up complete 2026-09-02** (clients-only):
    four-action bar (Add Transaction → `transactions.create` with the
    `all_transactions.php` page gate, Open Full Page → `clients.show`, plus the
    existing Edit modal / Delete form, all using the real existing routes/ACL),
    and fixed-drawer scrolling completed via `min-height: 0` on
    `.details-panel-inner`/`.details-body` + `flex-shrink: 0` on
    `.details-header`/`.details-actions` (only the body scrolls; header/action
    bar pinned). Responsive even 2x2 action bar on desktop & mobile. Presentation
    only — no schema/route/controller/model/service/permission/business-rule
    change. Static render confirms resolved URLs; suite **297 / 1397 / 0**, build
    + view:cache clean.
11. **Tailwind migration Phase 9 (client feedback modal + toast) complete
    2026-09-04** (clients-only, presentation-only; on top of Phases 0–8 of the
    Tailwind/Alpine migration — see `docs/TAILWIND_MIGRATION_EXECUTION_PLAN.md`
    §O). The clients **feedback modal** (    `#clientFeedbackModal`) and its
    associated **toast channel** (`#clientsToastStack` / `showToast()`) are now
    Alpine.js / pure-JS in `clients/index.blade.php` and `clients/_details.blade.php`
    (new `partials/client-feedback-modal.blade.php`), preserving the exact
    Bootstrap contract (default close semantics: backdrop + ESC; focus-in on
    open / focus-restore on close; body-scroll lock aware of the form modal;
    Tab trap; persistent toast with manual dismiss). Full suite
    **297 / 1397 / 0 failures**, Pint clean, `npm run build` + `view:cache`
    clean. `e2e/client-feedback-phase9.spec.ts` added; E2E still cannot
    authenticate without a seeded test DB (`smoke_superadmin` absent). No
    schema/ACL/route/business-rule change.
12. **Tailwind migration Phase 10 (client photo modal → Alpine) complete
    2026-09-04** (clients-only, presentation-only; on top of Phases 0–9 — see
    `docs/TAILWIND_MIGRATION_EXECUTION_PLAN.md` §O). The clients **photo modal**
    (`#photoModal`) in `clients/_details.blade.php` — an upload/camera surface
    posting to the existing `clients.photo.store` endpoint — moved off Bootstrap
    JS to Alpine: `x-data="clientPhotoModal()"` with `x-cloak`, dialog semantics
    (`role="dialog"`, `aria-modal`, `aria-labelledby="photoModalTitle"`), default
    close (backdrop + ESC + X + Cancel), Tab trap, body scroll lock, focus-in /
    focus-restore, and `openModal()`/`close()` reproducing the old
    `shown.bs.modal`/`hidden.bs.modal` camera reset. Compatibility bridge
    `window.openClientPhotoModal()`. Removed `data-bs-dismiss="modal"` and the
    Bootstrap modal structure for `#photoModal`; no clients-modal Bootstrap JS
    dependency remains (only the DataTables skin, a removal-gate item). Full suite
    **297 / 1397 / 0 failures**, Pint clean, `npm run build` + `view:cache` clean.
    `e2e/client-photo-modal-phase10.spec.ts` added; E2E still cannot authenticate
    without a seeded test DB (`smoke_superadmin` absent). No schema/ACL/route/
    business-rule change. **Next candidates requiring approval:** module screens
    still on Bootstrap, then the layout's shared flash toast, then removal-gate
    items (DataTables skin / Bootstrap CDN).
13. **Tailwind migration Phase 12 (admin/users password reset modal → Alpine)
    complete 2026-09-04** (frontend-only; on top of Phases 0–11 — see
    `docs/TAILWIND_MIGRATION_EXECUTION_PLAN.md` §O). The **`#passwordModal`** in
    `admin/users/index.blade.php` moved off Bootstrap JS to Alpine:
    `passwordResetModal` store + `passwordResetModalComponent()` + bridge
    `window.openPasswordResetModal(id, username)`. The old `show.bs.modal`/
    `relatedTarget` populate logic became the store's `openFor(id, username)`
    (sets `#passwordForm.action`, `#user_id`, `#modal_username` from the
    trigger's `data-id`/`data-username`). One delegated `.reset-btn` handler
    drives both the server-rendered rows and the DataTables AJAX rows. Dialog
    semantics, default close (backdrop + ESC + X + Cancel), Tab trap, body
    scroll lock, focus-in/focus-restore preserved. Form contract (`#passwordForm`,
    POST, `@csrf`, PUT, field names, dynamic action) unchanged; route/controller/
    request-rule untouched. `admin/users/show.blade.php` dangling
    `#passwordModal` trigger **intentionally left unchanged**. Full suite
    **297 / 1397 / 0 failures**, Pint clean, `npm run build` + `view:cache`
    clean. `e2e/admin-users-password-modal-phase12.spec.ts` added; E2E still
    cannot authenticate without a seeded test DB (`smoke_superadmin` absent).
    No schema/ACL/route/business-rule change. **Next candidates requiring
    approval:** `#messageModal` (scanners), `data-bs-dismiss="alert"` banners,
    `#leaderboardModal` (audit_logs), then the layout's flash toast, then
    removal-gate items (DataTables skin / Bootstrap CDN).
14. **Tailwind migration Phase 13 (scanner message modal → Alpine)
    complete 2026-09-04** (frontend-only; on top of Phases 0–13 — see
    `docs/TAILWIND_MIGRATION_EXECUTION_PLAN.md` §O). The **`#messageModal`** in
    `scanners/scan.blade.php` moved off Bootstrap JS to Alpine:
    `scannerMessageModal` store + `scannerMessageModalComponent()` + the
    `window.showModal(msg, type, title, onOk)` **public contract preserved
    exactly** (all 17 in-file callers unchanged). Title/body still populated as
    plain text via `innerText` (preserves `\n` line breaks in the multi-line
    "Already Saved" message — deliberately NOT `x-text`); `handleOk()` runs the
    callback **synchronously on OK click** then closes (no transitionend/async
    dependency); backdrop + ESC close **without** firing the callback (only OK
    does). Dialog semantics (role/aria-modal/labelled/described), Tab trap
    (project `handleTab`), body scroll lock, focus-in (OK) / focus-restore on
    close, `aria-label="Close"` on the X. Removed `new bootstrap.Modal(...)`,
    `data-bs-dismiss="modal"`, the `.modal-*` structure, the module `afterModal`
    var, and the direct OK listener. No schema/ACL/route/controller/scanner
    business-rule change. Full suite **297 / 1397 / 0 failures**
    (`ScannerTest` 18/125), Pint clean, `npm run build` + `view:cache` clean.
    `e2e/scanner-message-modal-phase13.spec.ts` added; E2E still cannot
    authenticate without a seeded test DB (`smoke_superadmin` absent).
    **Next candidates requiring approval:** `data-bs-dismiss="alert"` banners,
    `#clientIdPromptModal` (scholars) or `#leaderboardModal` (audit_logs), then
    the layout's flash toast, then removal-gate items (DataTables skin /
    Bootstrap CDN).
15. **Tailwind migration Phase 14 (Bootstrap Alert dismissals → Alpine)
    complete 2026-09-04** (frontend-only; on top of Phases 0–14 — see
    `docs/TAILWIND_MIGRATION_EXECUTION_PLAN.md` §O). Migrated **every**
    `data-bs-dismiss="alert"` dismissal in the repo (7 alerts) to per-alert
    Alpine `x-data="{ open: true }"` + `x-show="open"` with
    `@click="open = false"` close buttons: the shared layout validation alert
    (`$errors->any()`, iterates `$errors->all()`), duplicates/index
    (`session('success')` + errors), admin/users/index (`login_status` +
    errors; Phase 12 modal untouched), family_members/create (errors), and
    households/create (errors). Server-side Blade conditions/content untouched;
    independent per-alert state means dismissing one never hides a sibling.
    `.btn-close` visual (Bootstrap CSS, still globally loaded) kept for exact
    appearance; `aria-label="Close"` + keyboard focusability preserved.
    Removed all Bootstrap Alert JS; verified no `bootstrap.Alert` /
    `show.bs.alert` / `closed.bs.alert` remain. CSS-only `.alert` usages in
    qr/viewer, grantee_update, and unpaid self-service left untouched. No
    schema/ACL/route/controller/validation/business-rule change. Full suite
    **297 / 1397 / 0 failures**, Pint clean, `npm run build` + `view:cache`
    clean (7 compiled views carry the Alpine dismiss state, none carry
    `data-bs-dismiss="alert"`). `e2e/alert-dismiss-phase14.spec.ts` added; E2E
    still cannot authenticate without a seeded test DB (`smoke_superadmin`
    absent). **Env note:** the first full-suite run hung and put MySQL into
    crash recovery; confirmed tests use `main_system_test` (never the
    production `main_system` copy), which was verified intact (1002 clients)
    after a clean MySQL restart before the suite passed.
    **Next candidates requiring approval:** `#clientIdPromptModal` (scholars)
    or `#leaderboardModal` (audit_logs), then the layout's flash toast
    (Bootstrap Toast), then removal-gate items (DataTables skin / Bootstrap
    CDN).
16. **Tailwind migration Phase 15 (Scholars Client ID Prompt Modal → Alpine)
    complete 2026-09-04** (frontend-only; on top of Phases 0–15 — see
    `docs/TAILWIND_MIGRATION_EXECUTION_PLAN.md` §O). Migrated **only**
    `#clientIdPromptModal` in `scholars/index.blade.php` from Bootstrap Modal
    to Tailwind + Alpine: `clientIdPromptModal` store +
    `clientIdPromptModalComponent()` (Phase 12 conventions). The `.edit-client-id`
    trigger buttons live **inside DataTables AJAX rows**; the existing
    delegated jQuery click handler now calls
    `Alpine.store('clientIdPromptModal').openFor(id, current)` from the same
    `data-id`/`data-clientid` attributes (no `relatedTarget`; the store keeps
    the relink identity that previously lived on `modalEl._relinkId`). The
    jQuery `$.ajax` `POST scholars.update-client-id` submission, the success
    `window.scholarsTable.ajax.reload(null, false)`, and the error
    `alert('Error updating Client ID')` are **all unchanged**; no loading state
    added (none existed). Dialog semantics (role/aria-modal/labelled/described),
    Tab trap (project `handleTab`), body scroll lock, ESC + backdrop close
    (neither fires the submit), focus-in (input) / focus-restore on close,
    `aria-label="Close"`. Removed `data-bs-dismiss="modal"` (X + Cancel), the
    `.modal*` + `.btn-close` structure, and the
    `bootstrap.Modal.getOrCreateInstance(...).show()/.hide()` calls. No
    schema/ACL/route/controller/validation/business-rule/DataTables/jQuery
    change. Full suite **297 / 1397 / 0 failures** (`ScholarTest` 19/53), Pint
    clean, `npm run build` + `view:cache` clean (compiled scholars view carries
    no Bootstrap-modal dep). `e2e/scholars-client-id-modal-phase15.spec.ts`
    added; E2E still cannot authenticate without a seeded test DB
    (`smoke_superadmin` absent — `smoke_superadmin`), and `.edit-client-id`
    rows only render after authenticated DataTables AJAX (documented).
    **Next candidate requiring approval:** `#leaderboardModal` (audit_logs,
    `show.bs.modal` AJAX content), then the layout's flash toast (Bootstrap
    Toast), then removal-gate items (DataTables skin / Bootstrap CDN).
17. **Tailwind migration Phase 16 (Audit Logs Leaderboard Modal → Alpine)
    complete 2026-09-04** (frontend-only; on top of Phases 0–16 — see
    `docs/TAILWIND_MIGRATION_EXECUTION_PLAN.md` §O). Migrated **only**
    `#leaderboardModal` in `admin/audit_logs/index.blade.php` from Bootstrap
    Modal to Tailwind + Alpine: `leaderboardModal` store +
    `leaderboardModalComponent()` (Phase 12/15 conventions). The
    server-rendered Leaderboard trigger (page-header actions) now calls
    `@click="$store.leaderboardModal.open()"` (text/class/placement preserved).
    `open()` locks body scroll, opens, focuses the close button, and calls
    `load()` which runs the **existing jQuery `$.ajax` POST** to
    `admin.audit-logs.leaderboard` (`{ table: $('#table').val() }` + CSRF
    header) exactly **once per open** — no caching, one request per open, same
    `.done` re-render (tbody empty + append, escaped username, rank = index+1);
    a late response still populates the hidden tbody even after close (old
    `show.bs.modal` behavior preserved; no early-return guard). Dialog
    semantics (role/aria-modal/labelled), Tab trap (project `handleTab`), body
    scroll lock, ESC + backdrop + X + Close close (Bootstrap's default close
    set preserved), focus-restore on close, `aria-label="Close"`. Removed
    `data-bs-toggle`/`data-bs-target="#leaderboardModal"`,
    `data-bs-dismiss="modal"`, the `.modal*` + `.btn-close` structure, the
    `show.bs.modal` jQuery handler, and the unused `leaderboardUrl` const. No
    schema/ACL/route/controller/validation/business-rule/DataTables/jQuery
    change. Full suite **297 / 1397 / 0 failures** (`AdministrationTest`
    32/133 incl. `test_leaderboard_returns_descending_totals`;
    `FilterChipsTest` 24/90), Pint clean, `npm run build` + `view:cache` clean
    (compiled view carries no Bootstrap-modal dep).
    `e2e/leaderboard-modal-phase16.spec.ts` added; E2E still cannot
    authenticate without a seeded test DB (`smoke_superadmin` absent) and the
    audit route + leaderboard AJAX need an authenticated `audit_logs.php`
    session (documented).
    **Next candidate requiring approval:** the students `#photoModal`
    (`scholars/show.blade.php` + `students/photo-upload.blade.php`), then the
    layout's flash toast (Bootstrap Toast), then removal-gate items (DataTables
    skin / Bootstrap CDN).

18. **Tailwind migration Phase 17 (Unpaid Verification Dynamic Confirmation
    Modal → Alpine) complete 2026-09-05** (frontend-only; on top of Phases
    0–16). Migrated **only** the runtime-built Final Confirmation modal in the
    standalone public page `unpaid_verifications/self-service.blade.php` (the
    page's own `<!DOCTYPE html>`, last `new bootstrap.Modal` surface) from
    Bootstrap Modal JS to Tailwind + Alpine. The page's Vite tag now loads
    `resources/js/app.js` (Alpine) alongside app.css. The new `alpine:init`
    store `unpaidConfirmationModal` (`open`, `_prevFocus`, `_onConfirm`,
    `_rootEl`) + `window.unpaidConfirmationModalComponent()` (Tab trap via
    `x-ref="dialog"`). `showConfirmation(isProxy, lname, fname, mname, rel)`
    still **builds the modal DOM dynamically per open**
    (`document.createElement('div')` + the same proxy/self template),
    appends, `Alpine.initTree(modal)`, then `openFor(modal, () =>
    saveUnpaid(...))` — exact create → append → init → show → … → remove →
    recreate lifecycle, no persistent hidden DOM, no confirm-without-Click
    close. Close set preserved: X / Cancel / backdrop / ESC all close without
    confirming; only `#confirmYesBtn` (id kept) confirms, which
    closes/removes then runs the **untouched fetch-based `saveUnpaid()`** POST
    (`window.proxyExtra` payload). Focus enters on the Close button, returns to
    the trigger on close; body scroll locked/open-restored. Removed
    `new bootstrap.Modal`/`bsModal.show()/hide()`, `hidden.bs.modal`, the
    `.modal*` + `.btn-close` structure, and both `data-bs-dismiss="modal"`
    buttons; the bootstrap bundle `<script>` stays loaded (coexistence). No
    schema/ACL/route/controller/validation/business-rule/fetch change. Full
    suite **297 / 1397 / 0 failures** (targeted PayoutTest +
    GranteeUpdateTest 29/159 incl. `self service is public`), Pint clean,
    `npm run build` + `view:cache` clean (compiled view has no real
    Bootstrap-modal dep — comments only). **E2E
    `e2e/unpaid-verification-modal-phase17.spec.ts` (5 tests) actually runs
    live — this page is public — and passes 5/5 on chromium** (dynamic
    creation + dialog/aria + focus entry, no Bootstrap-Modal-js dependency,
    full close set + focus restore, single-instance reopen, scroll lock,
    confirm → stubbed `saveUnpaid` POST via `page.route`, no DB write;
    `#confirmSection` revealed in E2E as the app's verify-success branch
    does).
    **Next candidate requiring approval:** the students `#photoModal`
    (`scholars/show.blade.php` + `students/photo-upload.blade.php`, camera
    shown/hidden lifecycle), then the layout's flash toast (Bootstrap Toast),
    then removal-gate items (DataTables skin / Bootstrap CDN).

19. **Tailwind migration Phase 18 (Students Photo Modal → Alpine) complete
    2026-09-05** (frontend-only; on top of Phases 0–17). Migrated **only** the
    `#photoModal` camera modal in the standalone public student flow page
    `students/photo-upload.blade.php` (the last camera modal still on Bootstrap
    JS) to Tailwind + Alpine. Analysis established that the **only real
    `#photoModal`** lives on that page; the `scholars/show.blade.php` "Photo"
    button was a dead `data-bs-*` trigger targeting a modal that never existed
    on that page (its dangling attributes were stripped — zero observable
    behavior change; the legacy files' other `#photoModal` is the Phase 10
    clients one, untouched). The page's Vite tag now also loads
    `resources/js/app.js` (Alpine) alongside app.css; the standalone page gets
    `x-data="photoModalComponent()"` on `<body>` (Alpine never binds
    `@click`/`x-show` on elements outside an `x-data` scope — same latent
    reason the navbar hamburger and Audit Logs Leaderboard buttons are inert;
    pre-existing, out of Phase 18 scope, noted for a future phase). New
    `alpine:init` store `photoModal` (`open`, `_prevFocus`,
    `openModal()`/`close()`, method named `openModal` to avoid the Phase 16
    duplicate-key store quirk) + `window.photoModalComponent()` Tab trap via
    `x-ref="dialog"`. The "Take Photo" trigger is
    `@click="$store.photoModal.openModal()"` (was `data-bs-toggle="modal"
    data-bs-target="#photoModal"`; same class/text/placement). All capture
    controls/ids preserved (`#cameraSelect`, `#video`, `#capturedPreview`,
    `#cameraButtons`/`#captureBtn`, `#previewButtons`/`#retakeBtn`/`#saveBtn`,
    `#photoForm` POST → `student.photo-upload.store`, hidden `#cameraImage`);
    the camera script (`initCamera`/`loadCameraDevices`/`switchCamera`/capture
    canvas→dataURL/retake) is **verbatim**, and the modal root carries
    `x-show` + `x-cloak` so `#photoModal` leaves the tree when closed. Camera
    lifecycle parity: visible-modal open → `initCamera()` (was `shown.bs.modal`),
    close → `stopCamera()` stopping every track (was `hidden.bs.modal`),
    including the old "stale preview persists across close" behavior. Close set
    unchanged — **no X/Close button** (the old modal had none): backdrop + ESC
    only. Focus entry `#cameraSelect`, Tab/Shift+Tab trapped (hidden `cameraImage`
    excluded), focus + body scroll restored on close. No schema/ACL/route/
    controller/validation/business-rule/camera-logic change. Full suite
    **297 / 1397 / 0 failures** (PhotoTest + StudentTest 11/39), Pint clean,
    `npm run build` + `view:cache` clean (compiled view keeps only comment
    mentions of `shown.bs.modal`/`hidden.bs.modal`). **E2E
    `e2e/photo-modal-phase18.spec.ts` (4 tests) runs live** — the student
    verify→photo-upload flow is public and the suite drives it with a real
    production-copy client row (id=1, env-overridable) plus Chromium
    fake-camera launch flags. It verifies: dialog semantics (`role=dialog`,
    `aria-modal`, `aria-labelledby=photoModalTitle`) hidden until open, no
    Bootstrap-Modal dependency in the page's scripts, focus entry + scroll
    lock, capture/retake dataURL flow, and the full camera lifecycle
    (open→live stream → ESC→tracks ended → reopen→fresh live stream →
    backdrop→ended, single instance, no stale stream) — **4/4 passed**.
    `scholars/show.blade.php` now has zero `data-bs`/`photoModal` matches.
    **Next candidate requiring approval:** the layout's shared flash toast
    (`layouts/app.blade.php`, `bootstrap.Toast`), then removal-gate items
    (DataTables skin / Bootstrap CDN).

20. **Tailwind migration Phase 19 (Shared Layout Flash Toast → Alpine) complete
    2026-09-05** (frontend-only; on top of Phases 0–18). Migrated **only** the
    shared flash toast `#flashToast` in `layouts/app.blade.php` — the Bootstrap
    JS surface present on *every* layout page (`bootstrap.Toast.
    getOrCreateInstance(toastEl, { autohide: false }).show()`) — to Tailwind +
    Alpine state. The toast div lost the `.toast` class (Bootstrap's
    `.toast:not(.show)` hide gate; its own Tailwind utilities fully own the
    visuals) and `data-bs-autohide`/`data-bs-dismiss="toast"`, and gained
    `x-data="{ open: true }"` + `x-show="open"` + a stable `id="flashToast"`;
    the close button keeps `.btn-close`/`aria-label="Close"` but now dismisses
    via `@click="open = false"`. Persistence + one-shot semantics preserved
    (visible from server HTML with zero `.show()` call; closes only by click;
    flash `session('login_status')` consumed on first render exactly as
    before). **Coexistence-critical decision:** the layout's init loop is kept
    **verbatim** because it also launches the remaining child-view `.toast`
    surfaces (transactions/households success toasts — `data-bs-autohide` +
    `data-bs-dismiss="toast"` still live there, out of Phase 19 scope — and the
    clients Phase 9 feedback stack); removing `.toast` from the shared toast
    makes the loop skip it automatically. `aria-live="polite"` container +
    `role="status"` toasts + `@if (session('login_status'))` guard unchanged;
    the out-of-layout `login_status` surfaces (admin/users/index, login page)
    untouched. Full suite **297 / 1397 / 0 failures**, Pint clean, `npm run
    build` + `view:cache` clean (compiled layout: toast region has zero
    `data-bs-dismiss`/`data-bs-autohide`; `bootstrap.Toast` remaining only in
    the kept loop's comment). **E2E `e2e/flash-toast-phase19.spec.ts` (4 tests)
    runs live ×2** — the first auth-gated phase: an **ephemeral**
    `smoke_superadmin` account (`'*'` page permission) was inserted into the
    local production-copy DB solely for the run and **fully removed afterwards**
    (user + permission + its LOGIN audit rows; audit trail back to 1604 rows).
    The suite triggers a **zero-DB-write** real flash
    (`session.force-logout` with `user_id=99999999` → `back()->with(
    'login_status', 'User not found.')`) via an injected plain form so the
    browser itself follows the redirect (two alternatives disproved by debug:
    `page.request` auto-follow consumes the one-shot flash; in-page
    `fetch('redirect:manual')` never commits Set-Cookie). Verifies: toast
    renders with the exact flashed value, top-20 aria-live stack + teal check +
    `rounded-panel` visuals, persistence past 3.2 s (no auto-hide), one-shot
    across navigation, zero Bootstrap-toast attributes + zero legacy `.toast`
    matches on a layout-only page, Alpine binding (`_x_dataStack`) and
    close-by-Alpine-state (retried for boot timing). **Next candidate requiring
    approval:** the remaining page-level `.toast` surfaces
    (transactions/households success toasts, clients Phase 9 stack), then
    removal-gate items (DataTables skin / Bootstrap CDN).

21. **Tailwind migration Phase 20 (Transactions + Households success toasts →
    Alpine) complete 2026-09-06** (frontend-only; on top of Phases 0–19).
    Migrated **only** the two page-level success toasts rendered when a
    controller flashes `session('success')` — in `transactions/index.blade.php`
    and `households/index.blade.php` — from Bootstrap Toast JS to Tailwind +
    Alpine state, as **two independent per-page surfaces** (each carries its own
    `x-data="{ open: true }"`; never shared with the layout `#flashToast`). Each
    toast: lost `.toast` + `data-bs-autohide`/`data-bs-dismiss="toast"`, gained
    `x-show="open"`; close button keeps `.btn-close`/`aria-label="Close"` but
    dismisses via `@click="open = false"`; `role="status"` + the shared
    `aria-live="polite"` stack container + per-page message sink (`<span>`
    transactions / `<div>` households) preserved. Persistence is deterministic
    (visible from server HTML, closes only by click — old `autohide:false`
    parity); flash is one-shot exactly as before. The layout init loop is
    **kept verbatim** and now matches only the clients Phase 9 stack.
    **Application finding:** `transactions.index`'s success toast is **not
    reachable by any UI flow** — `TransactionController::store` (134)/`update`
    (215) redirect to `transactions.show` (no toast); only households has a real
    trigger (`HouseholdController::store` 56 → `households.index` with
    'Household added successfully!'; destroy is a JSON POST). No
    schema/ACL/route/controller/business-rule change. Full suite
    **301 passed / 1419 assertions / 0 failures** (4 new tests), Pint clean,
    `npm run build` + `view:cache` clean (compiled views have zero
    `data-bs-autohide`/`data-bs-dismiss="toast"`). **E2E
    `e2e/page-toasts-phase20.spec.ts` (5 tests) runs live 5/5 chromium, and the
    Phase 19 suite re-ran 4/4** (regression). Households is verified end-to-end
    via its REAL trigger (create household → toast renders → destroy via real
    `POST /households/{id}`). Transactions — no trigger exists — is verified by
    seeding the one-shot flash into the browser's live file session via a
    throwaway PHP helper (outside the repo) that decrypts the `2dmis_session`
    cookie (an `EncryptCookies` payload — prefixed + URL-encoded → 
    `rawurldecode` + `decryptString` + `CookieValuePrefix::validate`) and calls
    `flash('success', …)` + `save()`; three failed runs preceded the decrypt
    fix. Ephemeral `smoke_superadmin` (id 8) + `'*'` permission row used and
    fully removed; DB restored (audit 1604, `tbl_household` id 1 only, no user-8
    remnants). **Env note:** local MariaDB crashed mid-session on a corrupt
    `mysql.proxies_priv` system-table index (recovered; `main_system` verified
    intact). **Next candidate requiring approval:** the only remaining Bootstrap
    `.toast` surface (clients Phase 9 stack), then removal-gate items (DataTables
    skin / Bootstrap CDN).

22. **Tailwind migration Phase 21 (Bootstrap JS inventory + navbar hamburger
    Alpine scope fix) complete 2026-09-06** (frontend-only; on top of Phases
    0–20). A fresh, repo-wide Bootstrap JavaScript surface inventory classified
    every match in the Laravel layer. Only **three live surfaces** remained:
    (1) the intentional layout init loop
    `bootstrap.Toast.getOrCreateInstance(el, { autohide: false }).show()`
    (`layouts/app.blade.php:139`) serving the clients Phase 9 stack — kept
    verbatim; (2) the dangling
    `data-bs-toggle="modal" data-bs-target="#passwordModal"` trigger on the
    details-panel partial `admin/users/show.blade.php:134` — pre-existing
    no-op, already logged in Phase 12, untouched; (3) the navbar hamburger.
    **Path correction:** the task's documented path
    `resources/views/components/navbar.blade.php` does **not** exist; the real
    navbar is `resources/views/partials/navbar.blade.php`. The hamburger's Alpine
    directives (`@click="$store.sidebar.toggle()"`, `:aria-controls`,
    `:aria-expanded`) were **inert** — outside any `x-data` scope, so Alpine
    never compiled them (verified live pre-fix: `_x_dataStack:false`,
    `aria-expanded` null, click no-op). This is a **pre-existing Alpine scope
    defect** from the Phase 3 Tailwind-first shell rebuild, not a Bootstrap
    migration issue. Fix: bare `x-data` on the hamburger button (smallest
    correct scope); it drives the existing shared `$store.sidebar` store
    (drawer `#appSidebar`, backdrop, ESC, scroll lock — all unchanged;
    Tailwind transform-driven, no Bootstrap Collapse involved). Post-fix:
    `_x_dataStack:true`, toggle works, `aria-expanded` flips, all three close
    paths (X/backdrop/ESC) + scroll-lock release verified. Bootstrap CDN/CSS/JS
    intentionally **not** removed (removal-gate phase). Only app code change =
    that one button. Full suite **301 passed / 1419 assertions / 0 failures**
    (unchanged baseline), Pint clean, `npm run build` + `view:cache` clean
    (compiled navbar view `0e26c4fce3a73253cccd48d6b4147291.php` carries the
    migrated button). **E2E `e2e/navbar-phase21.spec.ts` (5 tests) 5/5 chromium
    AND 5/5 Mobile Chrome** (serial; the initial Mobile Chrome 2/5 failure was
    the known single-device login `session_token` collision from parallel
    workers — fixed with `test.describe.configure({ mode: 'serial' })`, not an
    app bug), smoke regression `e2e/smoke.spec.ts` 4/4 chromium. Ephemeral
    `smoke_superadmin` (id 9) + `'*'` permission row used and fully removed
    (user + permission + 35 LOGIN audit rows; audit trail restored to exactly
    **1604**; a mid-session user-3 LOGIN row the AUTO_INCREMENT sequence proved
    was not part of the 1604 baseline was also removed; `tbl_household` id 1
    only, jordi/jiro only, jordi's legitimate `'*'` permission intact). **Next
    candidate requiring approval:** the clients Phase 9 feedback stack (the
    last Bootstrap `.toast` surface), then removal-gate items (DataTables
    Bootstrap skin / Bootstrap CDN).

23. **Tailwind migration Phase 22 (Bootstrap Toast init loop removal) complete
    2026-09-06** (frontend-only; on top of Phases 0–21). Investigation-first:
    proved the layout init loop `bootstrap.Toast.getOrCreateInstance(el,
    { autohide: false }).show()` (`layouts/app.blade.php:139`) is **redundant**
    and remove it. Evidence: the loop is the **only** `bootstrap.Toast`
    invocation in the app; the **only** `.toast`-class element it could match
    at page-parse time is the clients flash toast (`clients/index.blade.php:284`),
    which its own inline `wireFlashToast` (clients/index:793, invoked at :799
    via `document.querySelectorAll('.toast').forEach(wireFlashToast)`) already
    reveals with `.show` and wires its close button to remove — Phase 9's
    documented owner ("no Bootstrap Toast JS", reveal via `.show`, dismiss
    manual). The dynamic `showToast` elements (clients/index:760 and
    `clients/_details.blade.php:820`) are created into `#clientsToastStack` at
    **runtime** — after the parse-time loop has already run — and reveal/close
    themselves (pure JS). The Phase 19 `#flashToast` and the Phase 20 page
    toasts lost the `.toast` class, so the loop never matched them. Greps
    (incl. `resources/js`) found zero other consumers, zero `.bs.toast` events,
    and no E2E/feature test depending on the loop (the Phase 9 E2E only asserts
    the static toast-channel container contract). **So no Bootstrap Toast JS
    surface remains anywhere in the app.** Change = smallest possible: removed
    only the obsolete loop + stale comment in `layouts/app.blade.php`, replaced
    with a concise note that the Bootstrap bundle stays for remaining non-toast
    CDN consumers. Full suite **301 passed / 1419 assertions / 0 failures**, Pint
    clean, `npm run build` + `view:cache` clean (compiled-view scan confirms no
    `bootstrap.Toast` / `getOrCreateInstance(toastEl` remains), then `view:clear`.
    **E2E note:** per Phase 22 rules (no ephemeral auth account unless
    absolutely necessary) no live-toast browser run was performed; the toast
    stack contract is covered by the Bootstrap-JS-free
    `client-feedback-phase9`, `flash-toast-phase19` and `page-toasts-phase20`
    specs. No DB writes; DB untouched (1604 audit rows, jordi/jiro only).
    **Next candidate requiring approval:** removal-gate items (DataTables
    Bootstrap skin / Bootstrap CDN) and the dangling
    `data-bs-toggle="modal"` trigger at `admin/users/show.blade.php:134`.

24. **Tailwind migration Phase 23 (Bootstrap removal-gate audit) complete
    2026-09-06** (audit only — **NO application code changed**; on top of
    Phases 0–22). Fresh repo-wide inventory + trace + classification of
    every remaining Bootstrap dependency. **JS bundle = REDUNDANT:** zero live
    `bootstrap.*()` API calls anywhere (views, `resources/js`, built `app.js`);
    the only `data-bs-*` attribute in the app —
    `admin/users/show.blade.php:134` (`data-bs-toggle="modal"`
    `data-bs-target="#passwordModal"`) — is **provably DEAD**: no element with
    `id="passwordModal"` exists in any document (the Phase 12 modal's id is
    `passwordForm`), so Bootstrap 5's delegated click handler would resolve
    null and throw; the button also sits in a `display:none` `data-panel-actions`
    block on the full page; left untouched (accepted Phase 12 UI, §12 rule) and
    documented. No legacy `data-toggle`/`data-target#/`data-dismiss` remain.
    `dataTables.bootstrap5.min.js` (fetched, 1.13.6) UMD-wraps only jQuery +
    DataTables and never references the `bootstrap` global — DataTables needs
    Bootstrap **CSS** (class names) but not Bootstrap **JS**. **CSS CDN =
    REQUIRED:** Bootstrap 5.3.2 CSS renders the DataTables bootstrap5 skin base
    chrome on 11 screens (per-screen token skins recolor, not replace),
    `.form-control`/`.form-select`/`.input-group`/`.form-check-input`,
    `.btn-close` on the Phase 19/20/14 toasts+alerts, the `.btn`/`--bs-btn-*`
    variable bridge in `ui.css` (49 `--bs-*` refs powering `.btn-gold`/
    `.btn-primary`/`.btn-outline-primary` etc.), the clients export
    `.dropdown-menu`, `.alert`/`.table`, and 7 standalone public pages
    (`auth/login`, `qr/viewer`, `students/verify`, `students/update-photo`,
    `students/photo-upload`, `unpaid_verifications/self-service`,
    `grantee_update/self-service`). CDN refs: CSS in 8 files, JS bundle in 6
    files, all 5.3.2, no SRI; no Bootstrap npm package. **Removal-gate verdict:
    `NO — DEPENDENCY REMAINS` (Bootstrap CSS)**; Bootstrap JS bundle classified
    **REMOVAL-SAFE** but deferred (a pure `<script>` deletion is low-risk yet a
    global change — out of audit-only scope). Full suite **301 passed / 1419
    assertions / 0 failures**, Pint clean, `npm run build` clean (built
    `app.js` 105.70 kB, 0 bootstrap/data-bs hits), `view:cache` + compiled-view
    scan (exactly one documented dangling data-attribute, zero `bootstrap.*()`
    calls) + `view:clear`. **E2E/browser note:** not run — audit-only (no
    behavior change) and no ephemeral auth account was created per phase rules;
    documented as a limitation. No DB writes; DB untouched (1604 audit rows,
    jordi/jiro only). **Suggested next phases (identify only):** controlled
    "Bootstrap JS bundle removal" (delete the 6 bundle `<script>` includes,
    verify zero console errors + E2E), then a separate DataTables-skin /
    Bootstrap-CSS-ownership phase; the dangling trigger may be cleaned in the
    JS-removal phase.

25. **Tailwind migration Phase 24 (Bootstrap JS bundle removal) complete
    2026-09-06** (frontend-only; the "controlled Bootstrap JS bundle removal"
    phase recommended by Phase 23). Removed the six
    `cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js`
    `<script>` includes from `layouts/app`, `auth/login`, `qr/viewer`,
    `students/photo-upload`, `unpaid_verifications/self-service` and
    `grantee_update/self-service`. Also fixed the now-false
    `unpaid_verifications/self-service` Batch-G head comment ("kept for
    coexistence" → records the Phase 24 removal). **Bootstrap CSS untouched**
    (all 8 `bootstrap.min.css` links verified post-change); DataTables
    bootstrap5 skin CSS+JS, jQuery, Alpine, and the dangling
    `admin/users/show.blade.php:134` attribute all untouched
    (its Bootstrap delegated handler no longer exists, so it cannot throw;
    deferred to a finalization pass). Post-change static audit: **0** Bootstrap
    JS CDN references in views + compiled output; **0** live `bootstrap.*()`
    API calls; only historical comments remain. **Live Chromium console check:
    `/login` and `/qr-viewer` (two of the six edited, public pages) return HTTP
    200, contain zero bootstrap bundle/jsdelivr-Bootstrap-JS scripts,
    `typeof window.bootstrap === 'undefined'`, and emit **zero console/page
    errors** — conclusively no `bootstrap is not defined` (closes Phase 24's
    §12 gate).** Full suite **301 passed / 1419 assertions / 0 failures**
    (58.02s), Pint clean, `npm run build` (vite v6.4.3) clean (built `app.js`
    still 0 bootstrap hits), `view:cache` + compiled-view scan (no bootstrap
    JS refs) + `view:clear`. **E2E suites not run:** navbar-phase21,
    flash-toast-phase19, page-toasts-phase20, client-feedback-phase9, smoke and
    DataTables-backed suites all require an admin login; no ephemeral account
    created and DB untouched (1604 audit rows, jordi/jiro only) — documented
    limitation (consistent with Phases 20–23). No DB writes.
    **Next candidate (identify only):** the DataTables-skin / Bootstrap-**CSS**
    ownership phase (the remaining removal-gate item — Swiftly, **Phase 25
    DELIVERED it 2026-09-06**, see item 13 below), and/or the dangling-trigger
    cleanup in a finalization pass.

13. **Phase 25 (DataTables skin CSS ownership) complete 2026-09-06.** Replaced
    the `dataTables.bootstrap5.min.css` CDN on the 11 DataTables screens with the
    project-owned, self-contained **`public/css/datatables.css`** (new, ~430
    lines, plain CSS, zero `var(--bs-`) consumption, HTTP 200 / 20,561 B). It
    reasserts the upstream 1.13.6 skin core chrome (identical selectors/values:
    sort arrows, processing loader, wrapper/length/filter/info/paginate layout,
    `table.dataTable` base, `dt-*` alignment utilities, scroll/scrollFoot,
    767px responsive centering, `--dt-*` tokens, dark-mode overrides) and owns,
    scoped to `.dataTables_wrapper`, the Bootstrap class names the skin JS emits
    — `table(.sm)`/`.align-middle`, `.form-control(-sm)` filter input,
    `.form-select(-sm)` length select, `.pagination/.page-item/.page-link`
    (ui.css navy/gold tokens where the app re-points; Bootstrap 5.3.2 literals
    elsewhere). `dataTables.bootstrap5.min.js` (class emitter), jQuery 3.7.1,
    all per-screen token skins, and Bootstrap 5.3.2 CSS (8 links) retained.
    Post-change: **0** CDN skin-CSS refs in views + compiled output (11
    `asset('css/datatables.css')`, `rg --no-ignore` over `storage/framework/
    views`), full suite **301 passed / 1419 assertions / 0 failures**, Pint +
    `npm run build` + `view:cache` clean, live `/login` + `/css/datatables.css`
    HTTP 200. No schema/ACL/route/controller/model/DB change; DB untouched
    (1604 audit rows). **E2E not run** (auth-gated DataTables screens; no
    account — limitation consistent with Phases 20–24; DataTables visuals
    verified statically via the retained-by-faithfulness contract).
    **Removal-gate: `NOT READY`** — remaining Bootstrap CSS consumers: `.btn`
    base via the ui.css 49-`--bs-btn-*` bridge (26 `btn btn-*` usages incl.
    `btn-primary`×1/`btn-danger`×1/`btn-outline-primary`×2), `.btn-close`×14,
    `.btn-group`×2, `.form-control`/`.form-select`/`.form-check-input` bases,
    `.alert`, `.table` outside DataTables, `.table-responsive`
    (`transactions/index:130`), dropdowns, and the 7 standalone public pages.
    **Phase 26 (execute next):** own the `.btn` base + the
    `.form-control`/`.form-select`/`.form-check-input` bases in project CSS
    (app-wide — requires a browser-verified, per-screen pass), then the
    standalone public pages; dangling `admin/users/show:134` stays for the
    finalization pass. **→ DELIVERED 2026-09-07 (item 26 below).**

26. **Phase 26 (Core Bootstrap CSS component ownership) complete 2026-09-07.**
27. **Phase 27 (Final Bootstrap CSS removal) complete 2026-09-07** — all 8 Bootstrap CSS CDN
    `<link>`s removed; last families owned in `ui.css` §4.8 (`form-label`/accordion/list-group),
    §4.9 (Bootstrap utility `!important` parity + grid), §4.10 (Reboot + type parity incl.
    universal box-sizing + body color); `admin/users/show` dangling trigger removed; E2E spec
    asserts CDN absence; full suite 301/1419 green, live Chromium harness 5/5 structural gates +
    **0 computed-style diffs** vs the pre-removal baseline (5 pages × 8 selectors × 44 props),
    mobile no-overflow spot-checks pass; **removal-gate (Bootstrap CSS): READY — dependency 8 → 0**. DataTables skin + jQuery/DataTables/Alpine untouched (out of scope).
    Rewrote `public/css/ui.css` §4 (sections 4.1–4.7, "Shared component ownership (Bootstrap CSS
    independence)") so the project owns every shared Bootstrap class the app renders, byte-faithful
    to Bootstrap 5.3.2: `.btn` base (private `--ui-btn-*`, `.375rem` radius = 5.25px at the 14px
    root, `.btn:hover{border-color:currentColor}`, focus/active glows, `.btn-sm`) + variants
    `.btn-primary`/`.btn-outline-primary`/`.btn-danger`/`.btn-outline-danger` +
    `.btn.btn-gold:focus-visible`; `.form-control/-sm`, `.form-select/-sm`, `.form-check(-input)`;
    `.input-group(.text)`; `.btn-close(-white)`; `.alert` + 4 variants + dismissible; plain
    `.table`/`.table-sm`/`.align-middle`/`.table-responsive`; dropdown CSS
    (`.dropdown-toggle` caret, `.dropdown-menu`, `[data-bs-popper]`/`-end` parity, `.show`,
    `.dropdown-item`). **`--bs-*` refs: ui.css 49 → 0** (0 live `var(--bs-*)` consumption
    anywhere; only comment mentions in datatables.css/app.css). `.btn-gold` `--bs-btn-*` bridge +
    `.btn:hover` gradient bridge removed (app.css owns `.btn-gold` base). Zero blade edits
    (ui.css-only). **Audits:** 7 standalone public pages remain Bootstrap-CSS-dependent (load only
    the CDN — no removal); `admin/users/show:134` `#passwordModal` trigger re-proven DEAD (modal
    target exists only in `admin/users/index.blade.php`; the show page's real flow is the native
    `data-panel-edit-form` POST to `admin.users.reset-password`) — documented for the finalization
    pass, no markup change. **Verification:** suite **301 / 1419 / 0**, Pint clean, build clean
    (same hashes), view:cache clean + 0 `--bs-` in compiled views, served 200 on
    `/login`/`/qr-viewer`/`/student/update-photo`/`/unpaid-verification`/`/grantee-update` +
    css files, live Chromium harness (injected ui.css + app.css into `/login`) **48/48
    computed-style checks** (one real deviation found + fixed during verification: `.btn` radius
    is `.375rem`/5.25px, NOT the 6px `--ui-radius-sm` token) **+ zero console errors**. DB
    untouched (1604 audit rows). **Removal-gate: still NOT ready** — remaining Bootstrap CSS
    consumers: `.modal*` subsystem (48), `.accordion` (10) / `.list-group` (7) permissions,
    `.form-label` (46, margin-bottom only), `.d-none`/`.d-flex`/`.toast` utilities, and the 7
    standalone public pages. **Phase 27 (identify only, NOT executed):** own the `.modal*`
    subsystem + `.accordion`/`.list-group`/`.form-label`/utility leftovers app-wide
    (browser-verified per screen), then the standalone public pages, then remove the final
    Bootstrap CSS `<link>`; the dangling `admin/users/show:134` trigger remains a
    finalization-pass removal.

Do not redesign behavior. Parity comes before optimization.

---

## Documentation Status

| Document | Status |
|---|---|
| `README.md` | Up to date (P7 + P12 complete; Phase 2 implementation authorized) |
| `ENGINEERING_BLUEPRINT.md` | P0–P7 rows done; P8 §1.12 pending build; Phase 2 inspection recorded |
| `IMPLEMENTATION_LOG.md` | P0–P7 + P12 + UI/UX Batches A–G + Phase 2 inspection + confirmation + Phase 2C FilterChips (+ test suite + positioning remediation) + **Phase 2D Responsive Polish** + **Phase 2E final verification** + **2026-08-29 P8 pre-flight + rehearsal** + **2026-08-30 Pre-P8 IA consolidation** + **2026-08-31 Clients modal-first** + **2026-08-31 Clients UX refinement** + **2026-08-31 Clients UI/UX Phase A→B** + **2026-08-31 Clients final verification + Pint cleanup** + **2026-09-02 CSS token cleanup** + **2026-09-02 Clients details-panel UI/UX restructure** + **2026-09-02 Clients details-panel follow-up (action bar + scrolling)** + **2026-09-02 Clients UX refinement (header category line, filter clears, barangay fix, photo-in-Edit-modal)** + **2026-09-02 Clients UX follow-up correction + verification (edit-modal duplicate root-cause, header photo-above-name, validation-feedback modal, button rules, filter clear syncing)** + **2026-09-02 Clients UX correction pass (photo root-cause `currentPhoto()`, sticky profile header, action-bar single row)** + **2026-09-03 Phase 8 client form modal → Alpine** + **2026-09-04 Phase 9 client feedback modal + toast → Alpine / pure JS** + **2026-09-04 Phase 10 client photo modal → Alpine** + **2026-09-04 Phase 11 (discovery only — Bootstrap interactive dependency inventory)** + **2026-09-04 Phase 12 admin/users password reset modal → Alpine** + **2026-09-04 Phase 13 scanner message modal → Alpine** + **2026-09-04 Phase 14 Bootstrap Alert dismissals → Alpine** + **2026-09-04 Phase 15 Scholars Client ID Prompt Modal → Alpine** + **2026-09-04 Phase 16 Audit Logs Leaderboard Modal → Alpine** + **2026-09-05 Phase 17 Unpaid Verification Dynamic Confirmation Modal → Alpine** + **2026-09-05 Phase 18 Students Photo Modal → Alpine** + **2026-09-05 Phase 19 Shared Layout Flash Toast → Alpine** + **2026-09-06 Phase 20 Transactions + Households success toasts → Alpine** + **2026-09-06 Phase 21 Bootstrap JS inventory + navbar hamburger Alpine scope fix** + **2026-09-06 Phase 22 Bootstrap Toast init loop removal** + **2026-09-06 Phase 23 Bootstrap removal-gate audit (audit only)** + **2026-09-06 Phase 24 Bootstrap JS bundle removal** + **2026-09-06 Phase 25 DataTables skin CSS ownership (CDN bootstrap5 skin → project `css/datatables.css`, 11 screens)** + **2026-09-07 Phase 26 Core Bootstrap CSS component ownership (ui.css §4, `--bs-*` 49 → 0, 48/48 Chromium checks + zero console errors)** entries recorded |
| `TAILWIND_MIGRATION_EXECUTION_PLAN.md` | §O updated for Phase 21 + Phase 22 + **Phase 23** + **Phase 24** + **Phase 25** + **Phase 26** + **Phase 27** (status line, completed list, Phase 21 + Phase 22 + **Phase 23 + Phase 24 + Phase 25 + Phase 26 + Phase 27 paragraphs**; Phase 27 = Final Bootstrap CSS removal — all 8 CDN links removed, ui.css §4.8–4.10 ownership incl. Reboot/box-sizing parity, gate **READY / dependency 8 → 0**; §O next-pending rewritten to finalization); Phase 20 paragraphs/§D.3 tags already present |
| `implementation/P7_ADMINISTRATION.md` | Header **COMPLETE** (contract verified vs `ADMIN_ANALYSIS.md`; implemented) |
| `ADMIN_ANALYSIS.md` | Canonical P12 analysis; **BUILD RECORD** added 2026-08-16 |
| `PHASE_2_PLAN.md` | Authoritative Phase 2 plan (fully read) |
| `PHASE_2_INSPECTION_REPORT.md` | Complete pre-implementation inspection (26 sections) |
| `PHASE_2C_INSPECTION_REPORT.md` | **NEW** — Phase 2C FilterChips inspection + final decisions |
| `PHASE_2D_INSPECTION_REPORT.md` | **NEW** — Phase 2D Responsive Polish inspection + decisions (D1–D6) |
| `implementation/PHASE_2_KPI_DEFINITIONS.md` | **NEW** — KPI business definitions from v1 codebase |
| `implementation/PHASE_2_PRE_IMPLEMENTATION_CONFIRMATION.md` | **NEW** — formal pre-implementation confirmation |
| `implementation/P8_DECISION_PACKAGE.md` | P8 decision package; §G realigned to `ARCHITECTURE_DECISION.md` (2026-08-29); §H approvals + pre-flight + rehearsal recorded; **responsibility matrix added 2026-08-29** |
| `DBA_RECONCILIATION_HANDOFF.md` | **NEW 2026-08-29** — read-only production reconciliation handoff (Q1–Q7 SELECT-only; expected results; owner-decision triggers) |
| `reconciliation_queries.sql` | Machine-copyable companion to the handoff (CAST-corrected Q6; expected/decision comments) |
| `MIGRATION_PLANNING.md` | §7.1 S2 per-page flip + production specifics finalized 2026-08-29 |
| `MIGRATION_PLAN.md` / `ARCHITECTURE_DECISION.md` | ADR-001..009 sign-offs already recorded (2026-08-24); 007/010 Proposed |

---

## Reminder

Do not modify:

- Production database schema (`main_system`)
- Legacy v1 source code (`C:\xampp\htdocs\system`)
- Authentication contract (username, `session_token` single-device)
- Permission keys (`page_name` values identical to v1)
- Audit log format (`AuditService` is the single writer)

Database parity is mandatory.
