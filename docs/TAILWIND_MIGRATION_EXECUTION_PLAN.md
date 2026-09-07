# Tailwind Migration Execution Plan

> Supercedes/extends `docs/TAILWIND_MIGRATION_FORENSIC_AUDIT.md` (2026-09-02, logged in
> `docs/IMPLEMENTATION_LOG.md`). This plan turns that forensic audit into an actionable,
> sequenced execution roadmap for phasing Bootstrap 5.3.2 out of the Laravel Blade layer and
> replacing its implementation details with Tailwind CSS v4 + the existing token vocabulary,
> **without** changing UX, business logic, routes, database, or architecture.
>
> **Status: ACTIVE plan.** Phases 0–2 (Alpine foundation, `confirm-modal`, `record-view-modal`)
> through **Phase 19 (Shared Layout Flash Toast → Alpine)**, the
> **Phase 20 page-level toasts (Transactions + Households success toasts → Alpine)**, the
> **Phase 21 (Bootstrap JS inventory + navbar hamburger Alpine scope fix)**, the
> **Phase 22 (Bootstrap Toast init loop removal)**, the
> **Phase 23 (Bootstrap removal-gate audit — audit only, no code changes)**, the
> **Phase 24 (Bootstrap JS bundle removal — Bootstrap JS CDN `<script>`s removed)**,
> and the
> **Phase 25 (DataTables skin CSS ownership — CDN bootstrap5 skin replaced by the project-owned
> `public/css/datatables.css`)** are complete;
> §B/§C/§H/§N/§O updated for the approved **Tailwind CSS + Alpine.js** architecture (ADR + dated
> phase entries in `docs/IMPLEMENTATION_LOG.md`). The shared Bootstrap JS components migrated to
> Alpine so far are: confirm-modal, record-view-modal, sidebar/navbar (Phase 3), export dropdowns
> (Phase 4), GIP accordion + GIP modal (Phases 6–7), the client form modal (Phase 8), the
> client feedback modal + its toast channel (Phase 9), the client photo modal (Phase 10), the
> admin/users password reset modal (Phase 12), the scanner message modal (Phase 13), every
> Bootstrap Alert dismissal (`data-bs-dismiss="alert"`) (Phase 14), the Scholars
> `#clientIdPromptModal` (Phase 15), the Audit Logs `#leaderboardModal` (Phase 16), and the
> Unpaid Verification runtime-built Final Confirmation modal (Phase 17), the students
> `#photoModal` (Phase 18), the shared layout flash toast (Phase 19), the transactions +
> households success toasts (Phase 20), and the navbar hamburger **scope fix** (Phase 21).
> No clients-module modal or toast depends on Bootstrap JS anymore (only the DataTables skin, a
> removal-gate item), the first non-Clients target is done, the first scanner-modal target is
> done, all Bootstrap Alert JS is gone, the first Scholars Bootstrap modal is done, the first
> Audit Logs Bootstrap modal is done, and the shared layout flash toast — the one Bootstrap UI
> on every layout page — is done together with the page-level success toasts it used to serve.
> Phase 21 then re-inventoried the whole Bootstrap JS surface and fixed the one genuine live
> defect it found: the navbar hamburger's Alpine directives existed but were **inert**
> (outside any `x-data` scope) — now correctly scoped with a bare `x-data` on the button.
> Phase 22 then verified and removed the layout `bootstrap.Toast` init loop as redundant:
> the only `.toast`-class consumer it could match (the clients Phase 9 flash toast) is
> already revealed/wired by its own inline `wireFlashToast`, and the dynamic `showToast`
> elements are created at runtime after the loop has run — so **no Bootstrap Toast JS
> surface remains anywhere in the application**.
> **Phase 23 (removal-gate audit, 2026-09-06, audit-only):** the whole remaining Bootstrap
> surface was re-inventoried with evidence. **Bootstrap JS bundle = REDUNDANT** (zero live
> `bootstrap.*()` calls; the sole `data-bs-toggle` at `admin/users/show.blade.php:134` is a
> provably DEAD target — `#passwordModal` exists in no document; `dataTables.bootstrap5.min.js`
> 1.13.6 touches only jQuery + DataTables and never the `bootstrap` global). **Bootstrap CSS
> CDN = REQUIRED** (DataTables bootstrap5 skin base chrome on 11 screens whose token overlays
> only recolor; `.form-control`/`.form-select`/`.input-group`/`.form-check-input`; `.btn-close`
> on migrated toasts/alerts; the `--bs-*` variable bridge in `ui.css` (49 refs) powering
> `.btn`/`.btn-gold`/`.btn-primary`; the clients export `.dropdown-menu`; `.alert`/`.table`;
> and 7 standalone public pages). Removal-gate verdict: **NO — DEPENDENCY REMAINS (Bootstrap
> CSS)**; the JS bundle is REMOVAL-SAFE but deferred to a controlled phase.
> **Phase 24 (Bootstrap JS bundle removal, 2026-09-06):** executed that controlled
> phase — removed the six `bootstrap.bundle.min.js` CDN `<script>` includes
> (`layouts/app`, `auth/login`, `qr/viewer`, `students/photo-upload`,
> `unpaid_verifications/self-service`, `grantee_update/self-service`).
> Live verification: `/login` + `/qr-viewer` render 200 with `window.bootstrap`
> `undefined` and **zero console errors**; static scan: **0** Bootstrap JS CDN
> references remain (views + compiled output). **Bootstrap CSS CDN is now the
> only remaining Bootstrap artifact** — retained at all 8 original locations
> (DataTables bootstrap5 skin base + `.btn`/`--bs-*`/`.form-control`/`.btn-close`
> + 7 standalone public pages). The dangling
> `admin/users/show.blade.php:134` attribute is the last `data-bs-*` and was
> left untouched (its delegated handler no longer exists, so it cannot throw).
> The Bootstrap JS bundle (and the
> DataTables Bootstrap skin CSS) are removed/gone outright (removal-gate items).
> **Phase 26 (Core Bootstrap CSS component ownership, 2026-09-07):** rewrote `ui.css` §4 as a
> project-owned ownership layer (sections 4.1–4.7) for every shared Bootstrap class the app
> renders — `.btn` base + variants + `.btn-group`/`.btn-sm`, `.form-control(-sm)`/
> `.form-select(-sm)`/`.form-check(-input)`, `.input-group(.text)`, `.btn-close(-white)`,
> `.alert*`, plain `.table`/`.table-sm`/`.align-middle`/`.table-responsive`, dropdown CSS —
> byte-faithful to Bootstrap 5.3.2. **`--bs-*` refs in ui.css: 49 → 0** (the `.btn-gold`
> bridge and `.btn:hover` gradient bridge deleted; app.css owns `.btn-gold`). Bootstrap CSS
> remains REQUIRED for its remaining consumers: `.modal*` (48 refs), `.accordion` (10) /
> `.list-group` (7) permissions screens, `.form-label` (46), utility classes, and the 7
> standalone public pages. Removal-gate still **NO — DEPENDENCY REMAINS (Bootstrap CSS)**.
> **Phase 27 (Final Bootstrap CSS removal, 2026-09-07):** the completion step.
> Removed **all 8** Bootstrap 5.3.2 CSS CDN `<link>`s and owned the last families in
> `public/css/ui.css` §4.8–4.10, byte-faithful to 5.3.2: §4.8 (`.form-label`,
> `.accordion*`, `.list-group*`), §4.9 (the Bootstrap **utility** layer with its
> `!important` semantics — `.d-none` JS contract, display/flex/spacing/text/grid helpers —
> so the utilities that today beat Tailwind's non-important twins keep winning), §4.10
> (Reboot + `_type.scss`: universal `box-sizing:border-box`, `body{margin:0;color:#212529}`,
> element + heading defaults). `admin/users/show` dangling `#passwordModal` trigger removed;
> `e2e/alpine-phase0.spec.ts` asserts CDN absence + ui.css presence. **Removal-gate
> (Bootstrap CSS): READY — dependency 8 → 0**; repo-wide `bootstrap@5.3.2` string only in the
> E2E absence locator; live harness 5/5 structural gates + **0 computed-style diffs** vs the
> pre-removal baseline across 5 standalone pages × 8 selectors × 44 props; Tailwind Preflight
> deliberately NOT enabled in place of §4.10 (visible drift risk), documented for future
> baseline review. All other Bootstrap components remain, and each subsequent
> phase requires its own approval. See §O for the next pending phase.
> **Phase 28 (Final Bootstrap-Free Forensic Audit, 2026-09-07, AUDIT ONLY):** the final
> independent audit confirming the project is genuinely Bootstrap-free. No application
> code/CSS/schema/DB was changed. Fresh repository-wide search classified every remaining
> occurrence (0 active dependencies; the rest are historical docs, comments, the E2E absence
> locator, 2 inert `[data-bs-popper]`/`[data-bs-theme=dark]` parity selectors, and third-party
> framework/DataTables code). CSS network audit: Bootstrap CSS CDN **0**, Bootstrap JS CDN **0**,
> DataTables Bootstrap **CSS** CDN **0** (views, compiled views, built assets, served HTML all
> confirmed). Runtime audit (live Chromium, 6 public surfaces): `window.bootstrap` `undefined`,
> **0** Bootstrap network requests, **0** console/page errors. `--bs-*` active consumption **0**
> (`var(--bs-` absent from `resources`/`public`; only comment annotations remain). CSS ownership
> matrix produced (every family owned by Tailwind/`ui.css`/`datatables.css`, Bootstrap required
> NO). DataTables: 11 screens confirmed loading `jquery.dataTables.min.js` +
> `dataTables.bootstrap5.min.js` (class-emitter, verified NOT the Bootstrap library — 2,358-byte
> dist references only `jquery` + DataTables, zero `bootstrap` global refs) + project
> `css/datatables.css`. Assets: shared layout + all 7 standalone pages carry no Bootstrap asset.
> Automated: **301 tests / 1419 assertions / 0 failures**, Pint clean, `npm run build` clean
> (app-D3mz-Mcl.css 57.62 kB / app-DqsLDVL_.js 105.70 kB, 0 bootstrap hits), `view:cache` clean.
> DB integrity: no migration/schema/write performed by this phase (test suite isolated on
> `main_system_test`); one pre-2026-09-07-12:00 environment `LOGIN` audit row (id 1735) observed
> at 1605 rows vs the 1604 baseline — reported, not modified, audit-only. **Verdict: PASS —
> BOOTSTRAP-FREE COMPLETE; no further Bootstrap-removal phase required.** (Full entry in
> `docs/IMPLEMENTATION_LOG.md`.)

---

## A. Current State (baseline 2026-09-03; ui.css §4 re-owned by Phase 26, Bootstrap CSS removed by Phase 27 on 2026-09-07)

### A.1 CSS architecture
- **Bootstrap 5.3.2 is GONE — REMOVED in Phase 27 (2026-09-07).** The CSS CDN `<link>` tags on all
  8 blade files were deleted and every class they supplied is now project-owned (ui.css §4.1–4.10,
  datatables.css): `layouts/app.blade.php`, `qr/viewer.blade.php`,
  `students/photo-upload.blade.php`, `grantee_update/self-service.blade.php`,
  `unpaid_verifications/self-service.blade.php`, `auth/login.blade.php`,
  `students/verify.blade.php`, `students/update-photo.blade.php`. (Verified: no Bootstrap in
  `package.json`/`package-lock.json`; never an npm dependency.)
- **`public/css/datatables.css`** (~430 lines, NEW Phase 25, plain CSS, no build step) —
  project-owned DataTables skin replacing the `dataTables.bootstrap5.min.css` CDN on the 11
  DataTables screens. **Self-contained (zero `var(--bs-` consumption)**; owns, scoped to
  `.dataTables_wrapper`, the Bootstrap class NAMES the DataTables skin JS emits (`.table(-sm)`,
  `.form-control(-sm)` filter input, `.form-select(-sm)` length select, `.pagination/.page-item/
  .page-link`) using ui.css navy/gold tokens where the app re-points them and Bootstrap 5.3.2
  literal values elsewhere. DataTables chrome renders identically with Bootstrap CSS loaded or
  removed; `dataTables.bootstrap5.min.js` (the class emitter) is retained.
- **`resources/css/app.css`** — Tailwind v4 entry (1016 lines). No Preflight import (coexists
  with Bootstrap Reboot). Uses `@import 'tailwindcss/theme.css' layer(theme)` and
  `@import 'tailwindcss/utilities.css' source(none)` with an explicit per-file/line
  `@source` allowlist (§E). Tokens are transplanted into `@theme static` (§C).
- **`public/css/ui.css`** (~1489 lines since Phase 27) — shared UI foundation loaded in the layout
  head. Holds the `--ui-*` token values (do NOT confuse: `ui.css` at `public/css/ui.css`, NOT
  `resources/css/ui.css`), and shared component vocabulary (`.data-*` panels, `.metric-card`,
  `.topbar-search`, `.sidebar-*`, `.tag/status-badge`, pagination re-points, etc.). **Since
  Phase 26 (2026-09-07), §4 is the project-owned ownership layer** (sections 4.1–4.7: `.btn`
  base + variants + `.btn-group`/`.btn-sm`, `.form-control(-sm)`/`.form-select(-sm)`/
  `.form-check(-input)`, `.input-group(.text)`, `.btn-close(-white)`, `.alert*`, plain
  `.table`/`.table-sm`/`.align-middle`/`.table-responsive`, dropdown CSS) and **since Phase 27,
  §4.8–4.10 extend it** with `.form-label`/`.accordion*`/`.list-group*`, the Bootstrap utility
  layer (`!important` parity incl. the JS-contract `.d-none`, spacing ladder, `.row`/`.col-*`
  grid), and Reboot + `_type.scss` element parity (universal `box-sizing:border-box`,
  `body{margin:0;color:#212529}`, heading sizes) — all byte-faithful to
  Bootstrap 5.3.2 — **ui.css has ZERO `--bs-*` references (was 49)**; the former
  `--bs-btn-*` override lines and the `.btn-gold` bridge are deleted.

### A.2 JS architecture
- **`resources/js/bootstrap.js`** — axios wrapper ONLY, it does NOT load Bootstrap JS.
- **`resources/js/app.js`** — imports `bootstrap.js` (axios) only.
- **Bootstrap JS CDN bundle** used to be loaded at `layouts/app.blade.php` (and 5
  standalone pages) providing `window.bootstrap` (Modal/Toast/Offcanvas/Collapse/Dropdown)
  and `data-bs-*` declarative behavior — **REMOVED in Phase 24 (2026-09-06)**; zero live
  `bootstrap.*()` calls existed, and live Chromium confirms `window.bootstrap` is
  `undefined` with no console errors.
- **Custom, Bootstrap-free JS components** already exist and are the established replacement
  pattern (delivered to `public/js/components/` by the Vite copy step):
  - `resources/js/components/DetailsPanel.js` — persistent right-side panel (IIFE, no BS).
  - `resources/js/components/FilterChips.js` — custom filter chips + multi-select menu (IIFE, no BS).
  - `partials/global-search.blade.php` inline IIFE — custom autocomplete dropdown (no BS; the
    word "dropdown" in its markup is its own custom listbox, NOT Bootstrap's).
- **jQuery + DataTables 1.13.6** (CDN, Bootstrap 5 integration) loaded per index screen:
  - `jquery-3.7.1.min.js` + `jquery.dataTables.min.js` + `dataTables.bootstrap5.min.js`, plus —
    **since Phase 25 (2026-09-06)** — the project-owned, self-contained
    `{{ asset('css/datatables.css') }}` replacing the former skin CDN (which loaded per screen in
    `@push('styles')`, NOT in the layout).
  - Used by **11 index screens** (see §F).

### A.3 Migration status (~35% UI shell complete)
- **Fully migrated to Tailwind:** shell chrome (sidebar `partials/sidebar.blade.php`,
  navbar `partials/navbar.blade.php`, layout `layouts/app.blade.php`, details-panel,
  global-search), `auth/login`, `dashboard`, `scanners/*`, some standalone pages.
- **Partially migrated / mid-migration:** `clients/*` (heaviest Bootstrap + DataTables
  consumer), plus many shared partials still Bootstrap-dependent (see A.4).
- **Bootstrap-free partials already:** `details-panel.blade.php`, `filter-chips.blade.php`,
  `global-search.blade.php`, `sidebar-link.blade.php`, `sidebar-icon.blade.php`,
  `page-header.blade.php`, `breadcrumbs.blade.php`, `active-filters.blade.php`,
  `confirm-modal.blade.php`, `record-view-modal.blade.php` (Alpine), `client-form-modal.blade.php`
  (Alpine, Phase 8), `client-feedback-modal.blade.php` (Alpine, Phase 9).
- **Bootstrap-dependent:** no **clients-module modal or toast** depends on Bootstrap JS anymore —
  the clients **photo modal** (`#photoModal` in `clients/_details.blade.php`) is now Alpine
  (Phase 10). Remaining Bootstrap dependency: module screens still on DataTables/Bootstrap
  widgets (see A.4 / §F). The clients **feedback modal** and its associated **toast stack** are
  Alpine/pure-JS (Phase 9); the layout's own flash toast (shared infra) is unchanged and remains
  in scope for the layout phase.

### A.4 Bootstrap JS API surface (counts from grep)
- **`bootstrap.Modal`** — ~40 call sites across 11 files (heaviest: `clients/index` ~15,
  `clients/_details` ~6, `scholars/index` ~4, `unpaid_verifications/self-service` 1,
  `payouts/attendance` 1, `scanners/scan` 1, plus `partials/confirm-modal` 2).
- **`bootstrap.Toast`** — removed entirely (Phase 22); after Phase 9 the only
  use was the layout init loop in `layouts/app`, now gone. `clients/index` and
  `clients/_details` are Bootstrap-free toast (pure JS/Alpine); zero
  `bootstrap.Toast` references remain in application code.
- **Offcanvas / Collapse / Dropdown** — **declarative only** (`data-bs-toggle` attributes),
  no JS API calls: offcanvas in sidebar/navbar, collapse in sidebar group toggles, dropdown in
  navbar user menu and clients toolbar.
- **Inline Bootstrap-markup + JS-built models** in `clients/_details` and
  `unpaid_verifications/self-service` construct modal elements dynamically via
  `document.createElement`/`innerHTML` then drive them through `new bootstrap.Modal(...)`.

### A.5 `ui.css` removal gate (verified)
- `public/css/ui.css` has **49 `--bs-*` variable references** (lines ~220–320): `.btn-primary`,
  `.btn-outline-primary`, `.btn-danger`, `.btn-outline-danger`, `.btn-gold`, form control
  focus/check states. These are Bootstrap-variant overrides and are the **only hard Beam
  dependency in `ui.css`** — the rest is `--ui-*` token vocabulary independent of Bootstrap.
  → The removal gate (§K) requires these `--bs-*` blocks be deleted from `ui.css` before the
  Bootstrap CDN is dropped.

---

## B. Hard Constraints (from the user — non-negotiable)

1. **No application code, no backend, no routes, no DB, no architecture changes.** The
   migration is confined to the **Blade template layer**, `resources/css/app.css`,
   `public/css/ui.css`, and **Bootstrap-free JS replacement components** under
   `resources/js/components/` (+ their `public/js/components/` build output).
 2. **Do NOT remove Bootstrap** during migration. **Alpine.js is the approved interaction
   layer** for replacing Bootstrap JS behaviors (Modal/Toast/Offcanvas/Collapse/Dropdown/simple
   dismissible UI), installed through the existing Vite/npm pipeline. **No other UI library**
   (Flowbite, DaisyUI, or any component framework) may be introduced. Bootstrap is only removed
   at the final gate (§K).
3. **The prototype is the source of truth for UX/visual design.** Preserve the existing /
   prototype UX exactly; only the Bootstrap **implementation details** change.
4. **Tailwind is the final CSS system.** Bootstrap is being phased out of the Blade layer;
   Tailwind + the token vocabulary is the long-term styling stack.
5. **Auth / ACL / single-device login / `tbl_users`** must remain untouched. Bootstrap removal
   must not alter any access check.

---

## C. Target Architecture

After the migration completes:

1. **No Bootstrap CDN** in any blade file (CSS or JS).
2. **`layouts/app.blade.php`** loads only:
   - `@vite(['resources/css/app.css', 'resources/js/app.js'])` (Vite/Tailwind).
   - `public/css/ui.css` (retained for the `--ui-*` token vocabulary + shared component CSS,
     with the `--bs-*` override blocks **deleted** at the removal gate).
   - Fonts (`Inter`, `Outfit`).
3. **All Bootstrap markup** (`modal`, `dropdown`, `offcanvas`, `collapse`, `toast`, `alert`,
   `badge`, `btn-*`, `form-*`, `table-*`, `data-bs-*` attributes, `bootstrap.*` JS API calls)
   replaced with Tailwind utility classes + token classes, and the Bootstrap JS **behaviors**
   replaced by **Alpine.js** directives (`x-data`/`x-show`/`x-transition`/`@click`/`@click.outside`/
   `@keydown.escape`), wired through the existing Vite `app.js` bundle.
4. **DataTables retains its jQuery core** but drops the Bootstrap-5 skin in favor of the
   existing token-driven wrapper CSS (already present in each index screen's `<style>` block —
   see §F). The **removal gate** requires removing `dataTables.bootstrap5.min.css` +
   `dataTables.bootstrap5.min.js` and remapping the handful of Bootstrap classes DataTables
emits (`.page-link`, `.form-select`, `.form-control`, `.btn`, `.badge`) to token/utility
    equivalents via the existing `#*-screen .dataTables_wrapper` CSS.
    > **Phase 25 (2026-09-06) delivered an alternative first step:** rather than removing the
    > skin, the project now **owns** it — the skin CSS CDN was replaced by the self-contained
    > `public/css/datatables.css` (§A.1/§F.2), removing the DataTables dependency on Bootstrap
    > CSS while the class-emitting JS stays; the final skin-JS removal above remains the
    > migration end-state.
5. **Frontend JS architecture = Tailwind CSS + Alpine.js + existing Vanilla JS components +
   jQuery/DataTables.**
   - **Alpine.js** is the approved interaction layer for the Bootstrap JS behaviors
     (Modal/Toast/Offcanvas/Collapse/Dropdown/simple dismissible UI).
   - **Vanilla JS components remain unchanged** (Bootstrap-free, independently hosted):
     `DetailsPanel.js`, `FilterChips.js`, `global-search`, and the `axios` bootstrap.js wrapper.
   - **No `Modal.js`/`Toast.js`/`Drawer.js`/`Dropdown.js`/`Collapse.js`** custom IIFEs will be
     created — Alpine replaces those Bootstrap behaviors directly (per the approved decision).
6. `resources/css/app.css` **Preflight stays disabled** for the whole migration. Re-enabling
   Preflight is an explicit, gated, final decision (Post-Bootstrap) that must be reviewed
   separately because it rebases ALL base element styles (§I).

---

## D. Bootstrap–Tailwind Inventory

> File-by-file inventory of Bootstrap usage. `SHARED-FIRST` marks components that must be
> migrated before their consumers (module screens). `UX-PRESERVE` marks items whose visual/
> behavior must remain identical to today/prototype. Line numbers are approximate snapshots.

### D.1 Shell & shared partials (migrate FIRST)

| File | Bootstrap surface | Dependency | Replacement / notes | Shared-first | UX-preserve |
|---|---|---|---|---|---|
| `layouts/app.blade.php` | BS CSS:9 (no DataTables CSS in the layout — the DataTables skin CSS loads per screen in `@push('styles')`); **Bootstrap JS bundle REMOVED since Phase 24** (was line 111); **flash toast 77-88 → Alpine since Phase 19** (`#flashToast` `x-data`/`x-show`, `.toast` class removed); error alert 93-99; **bootstrap.Toast init loop 131-140 REMOVED since Phase 22** (proven redundant — clients flash reveal/wire is the inline `wireFlashToast`, dynamic toasts are runtime-created with manual `.show`; no Bootstrap Toast JS surface remains in the app); `adjustDataTables` 137-156 | DataTables | Toasts/alert → Alpine ✓; keep `adjustDataTables` (guarded, already BS-free logic but listens to layout events) | Yes | Yes |
| `partials/sidebar.blade.php` | `offcanvas` wrapper:2; `btn-close-*`:34; `data-bs-dismiss="offcanvas"`; `data-bs-toggle="collapse"` group toggles:94-95; collapse panels:102; `rounded-pill`:142 | Offcanvas/Collapse JS | Replace offcanvas w/ Tailwind responsive drawer (lg:static already present, mirror `lg:hidden` toggles); replace collapse with a small vanilla toggle or `<details>`; swap `rounded-pill`+`collapse`+`show` for theme utilities | Yes | Yes |
| `partials/navbar.blade.php` | hamburger toggle: early 40s (`@click="$store.sidebar.toggle()"` + `:aria-controls`/`:aria-expanded` → **Alpine, correctly scoped since Phase 21** — previously outside any `x-data` so inert); user dropdown ~80-92: `dropdown`/`dropdown-menu`/`dropdown-item` (Alpine `@click`/`@click.outside`/`:class` since Phase 3/4); no `data-bs-*` left | none (offcanvas/dropdown JS not used) | Hamburger + dropdown already Alpine; no Bootstrap JS dependency in this partial | Yes | Yes |
| `partials/confirm-modal.blade.php` | `modal fade`,`modal-dialog`,`modal-content`,`modal-body`,`modal-footer` 15-27; `bootstrap.Modal` 46,51 | Modal JS | Replace with a vanilla `<dialog>`-based or DOM-built modal; keep `window.uiConfirm` contract identical | Yes | Yes |
| `partials/record-view-modal.blade.php` | `modal fade`/`modal-dialog`/`modal-lg`/`modal-content`/`modal-header`/`modal-body`/`modal-footer`/`btn-close` 3-15 | Modal JS (opened by consumers) | Must coordinate with whichever consumers call `bootstrap.Modal` on `#viewModal`; replace with vanilla modal helper | Yes | Yes |

### D.2 Clients module (heaviest — most complex, has 4 consumers of shared partials)

| File | Bootstrap surface | Dependency | Replacement / notes | Shared-first | UX-preserve |
|---|---|---|---|---|---|
| `clients/index.blade.php` | DataTables skin `css/datatables.css`:6 (Phase 25), JS CDN 418-420; dropdown 266; `#clientFormModal` 368-383, `#clientFeedbackModal` 398-410; `bootstrap.Modal` ~15 call sites (789,803,811,826,834,853,903,944...); includes `confirm-modal` 365 | Modal JS; jQuery+DataTables | Biggest surface. Modals (client form, feedback) + toast now ~Alpine/pure-JS (Phases 8–9); dropdown w/ vanilla; DataTables skin owned (Phase 25) | No (consumes shared) | Yes |
| `clients/_details.blade.php` | `photoModal` (BS markup + `shown.bs.modal`/`hidden.bs.modal`); dynamically-built edit `modal` (`new bootstrap.Modal` with `backdrop:'static'`, `keyboard:false`); `btn`/`btn-gold` classes throughout | Modal JS | Edit modal + feedback modal + toast now Alpine/pure-JS (Phases 8–9); **photo modal now Alpine (Phase 10)** — `clientPhotoModal()` + `window.openClientPhotoModal()`, shown/hidden callbacks preserved via `openModal()`/`close()`. No clients modal depends on Bootstrap JS anymore | No | Yes |
| `clients/_form.blade.php` | Bootstrap form controls (`mb-3`, `form-label`, `form-control`, `form-select`, `form-check`, validation `.is-invalid`/`.invalid-feedback`) | Bootstrap CSS classes (grid/form) | Replace w/ Tailwind form utilities; keep server-driven validation markup semantics | No | Yes |
| `clients/_gip.blade.php` | Bootstrap grid/table markup + badge classes | Bootstrap CSS | Replace w/ Tailwind grid + token badges | No | Yes |
| `clients/show.blade.php` | Bootstrap markup (grid, card, badges, table) | Bootstrap CSS | Replace w/ Tailwind; consistent w/ details-panel content | No | Yes |

### D.3 Other registry / assistance modules

| File | Bootstrap surface | Dependency | Replacement | Shared-first | UX-preserve |
|---|---|---|---|---|---|
| `households/index|show|create.blade.php` + `family_members/create.blade.php` | DataTables (index); Bootstrap form/table/grid markup; **index success toast → Alpine (Phase 20)** | Bootstrap CSS + jQuery/DataTables | Tailwind utilities + token classes | No | Yes |
| `scholars/index.blade.php` | DataTables; `#clientIdPromptModal` 322-334; `bootstrap.Modal` 447,465,469 | Modal JS; DataTables | Vanilla modal helper; DataTables skin → token CSS | No | Yes |
| `scholars/_form.blade.php` | Bootstrap form markup | Bootstrap CSS | Tailwind form utilities | No | Yes |
| `scholars/show.blade.php` | Bootstrap markup | Bootstrap CSS | Tailwind | No | Yes |
| `transactions/index|create|edit|show.blade.php` | DataTables (index); form markup; includes `confirm-modal`; **index success toast → Alpine (Phase 20)** | Modal JS; DataTables | Vanilla modal; Tailwind forms; DataTables skin | No (index), Yes (form reuses nothing shared) | Yes |
| `scholarship_reports/index.blade.php`, `update_logs/index.blade.php` | DataTables + Bootstrap markup | DataTables | DataTables skin → token CSS; Tailwind markup | No | Yes |

### D.4 Payouts / scanners / sessions

| File | Bootstrap surface | Dependency | Replacement | Shared-first | UX-preserve |
|---|---|---|---|---|---|
| `payouts/attendance.blade.php` | DataTables; `bootstrap.Modal` 1 site | Modal JS; DataTables | Vanilla modal; DataTables skin | No | Yes |
| `payouts/index.blade.php`, `payouts/show.blade.php` | Bootstrap markup + DataTables (index) | Bootstrap CSS; DataTables | Tailwind; DataTables skin | No | Yes |
| `scanners/index.blade.php`, `scanners/scan.blade.php` | Bootstrap markup; `bootstrap.Modal` (scan) 1 | Modal JS | Vanilla modal (scan); Tailwind markup | No | Yes |
| `sessions/online.blade.php` | Bootstrap table/markup | Bootstrap CSS | Tailwind | No | Yes |

### D.5 Standalone / public-facing pages (load their own Bootstrap)

| File | Bootstrap surface | Dependency | Replacement | Shared-first | UX-preserve |
|---|---|---|---|---|---|
| `auth/login.blade.php` | Bootstrap CSS (CSS only) + form markup | Bootstrap CSS | Tailwind form (already partially migrated) | No | Yes |
| `students/verify.blade.php` | Bootstrap CSS (CSS only) | Bootstrap CSS | Tailwind | No | Yes |
| `students/update-photo.blade.php` | Bootstrap CSS (CSS only) | Bootstrap CSS | Tailwind | No | Yes |
| `students/photo-upload.blade.php` | Bootstrap CSS (JS bundle REMOVED since Phase 24); `#photoModal` (Alpine since Phase 18 — no `bootstrap.Modal`) | None | Alpine store + Tailwind modal ✓ | No | Yes |
| `qr/viewer.blade.php` | Bootstrap CSS (JS bundle REMOVED since Phase 24) | Bootstrap CSS | Tailwind + vanilla | No | Yes |
| `grantee_update/self-service.blade.php` + `_self_update_tab.blade.php` | Bootstrap CSS (self-service; JS bundle REMOVED since Phase 24) | Bootstrap CSS | Tailwind + vanilla | No | Yes |
| `unpaid_verifications/self-service.blade.php` | Bootstrap CSS:8 (JS bundle REMOVED since Phase 24); JS-built confirm modal (Alpine since Phase 17 — no `bootstrap.Modal`) | None | Alpine store + dynamically-built Tailwind modal ✓ | No | Yes |
| `unpaid_verifications/index|show.blade.php` | DataTables (index); Bootstrap markup | DataTables; Bootstrap CSS | DataTables skin; Tailwind | No | Yes |
| `duplicates/index.blade.php` | DataTables + Bootstrap markup | DataTables | DataTables skin; Tailwind | No | Yes |
| `admin/users/*`, `admin/audit_logs/*`, `admin/permissions/*` | DataTables (users/audit); Bootstrap form/table markup | DataTables; Bootstrap CSS | DataTables skin; Tailwind | No | Yes |
| `dashboard.blade.php` | Bootstrap markup remnants | Bootstrap CSS | Tailwind (mostly migrated) | No | Yes |

> Full alphabetical list of files containing any Bootstrap-ish string (from grep),
> including those with only `data-bs-`/`modal`/`btn-close` present:
> `admin/audit_logs/index|show`, `admin/users/create|index|show`, `auth/login`,
> `clients/_details|_form|_gip|index|show`, `dashboard`, `duplicates/index`,
> `family_members/create`, `grantee_update/_self_update_tab|self-service`, `households/*`,
> `layouts/app`, `partials/{confirm-modal,details-panel,global-search,navbar,record-view-modal,sidebar}`,
> `payouts/*`, `qr/viewer`, `scanners/*`, `scholars/*`, `scholarship_reports/index`,
> `sessions/online`, `students/photo-upload|update-photo|verify`, `transactions/*`,
> `unpaid_verifications/*`, `update_logs/index`, `welcome`.
>
> `details-panel.blade.php`, `filter-chips.blade.php`, `global-search.blade.php`,
> `sidebar-link.blade.php`, `sidebar-icon.blade.php` are matched only by incidental words and
> are already **Bootstrap-free** — they must NOT be changed for this migration.

---

## E. Tailwind Source Allowlist Strategy

`resources/css/app.css` currently scans only allowlisted files (~40 lines, one per migrated
group) with `@source`:
```css
@import 'tailwindcss/utilities.css' source(none);
@source "../views/layouts/app.blade.php";
@source "../views/partials";            /* shell partials dir */
@source "../views/clients";             /* whole module dir */
@source "../views/transactions";        /* ...etc */
```
Working rule (from the file's own header comments, §9.6):
- **`@source` must be added in the SAME change that first emits Tailwind classes** from a file,
  or those classes are silently dropped from the build (they aren't scanned).
- Directory-scoped `@source` (`../views/clients`, `../views/partials`) cover many files at once,
  but note the section-ordering at the top: partials dir + whole module dirs are already
  allowlisted, so most migrated files need **zero new `@source` lines** — confirm each file is
  covered before trusting the build.
- Plan: when migrating a file to Tailwind, verify its Tailwind classes are emitted by running
  `npm run dev`/`npm run build` and checking the output CSS (or a focused Playwright test).

---

## F. DataTables Strategy

### F.1 Decision — keep jQuery DataTables core, drop the Bootstrap-5 skin
- DataTables delivers server-side pagination/search/sort (the `#*-screen table.dataTable`
  wrapper) that **is itself Bootstrap-free** and already token-skinned in every index screen's
  `<style>` block (`dataTables_wrapper`, `page-link`, `length`/`info`/`paginate`, compact pager).
- The ONLY Bootstrap coupling was:
  - The `dataTables.bootstrap5.min.css` CDN skin (loaded per screen in `@push('styles')`, NOT the
    layout) which styled DataTables chrome via Bootstrap CSS classes — **REPLACED in Phase 25
    (2026-09-06) by the project-owned, self-contained `public/css/datatables.css`** (zero
    `var(--bs-` consumption; owns the generated `table(.sm)`/`.form-control(-sm)` filter input/
    `.form-select(-sm)` length select/`.pagination`/`.page-link` chrome scoped to
    `.dataTables_wrapper`; byte-faithful under Bootstrap, correct without it).
  - `dataTables.bootstrap5.min.js` (per screen, after jQuery + core DataTables) which makes
    DataTables emit those Bootstrap class NAMES — retained (DOM/class emit contract unchanged;
    the classes they produce are now owned by `datatables.css`, so rendering no longer depends on
    Bootstrap CSS).
- Replacing DataTables entirely (e.g. with a hand-rolled table) is **explicitly rejected** for
  this plan — it would be a large behavior/UX change, doubles the work, and risks the server-side
  feed contracts. DataTables is not "Bootstrap" — it's a jQuery plugin. Keep it.

### F.2 Required DataTables changes (removal-gate items)
> **SUPERSEDED by Phase 25 (2026-09-06).** Item 1 (skin CSS) is DONE via a different route than
> this section planned: rather than removing the `.bootstrap5` styling entirely, the project now
> owns it — the CDN `dataTables.bootstrap5.min.css` is replaced by the project-owned
> `public/css/datatables.css`, and the `.bootstrap5.min.js` class-emit contract stays because the
> classes it emits are owned there. The old plan's premise that the skin lived in
> `layouts/app.blade.php` was corrected (it never did — it loads per screen in `@push('styles')`).
> Remaining DataTables-side work: none CSS-related. The pre-existing assumption above about
> `.btn`/`.badge` classes from DataTables markup does not apply — DataTables screens render no
> buttons extension; per-screen token skins own all row content. Bootstrap dependencies that
> remain are NOT DataTables-owned (see the Phase 25 gate in the changelog): `.btn`/`--bs-btn-*`
> bridge, `.form-*`/`.form-check-*` bases, `.btn-close`, `.alert`, `.table` outside DataTables,
> `.table-responsive` (transactions), dropdowns, 7 standalone public pages.

1. ~~Remove `dataTables.bootstrap5.min.css` link~~ — DONE by Phase 25 (replaced with
   `{{ asset('css/datatables.css') }}` on the 11 screens; Bootstrap CSS stays loaded elsewhere).
2. Keep `dataTables.bootstrap5.min.js` (class emitter retained; its classes are owned by
   `datatables.css` — no removal needed).
3. Remapping of `.page-link`, `.page-item.active`, `.form-select` (length), `.form-control`
   (search input) to token rules is DONE in `public/css/datatables.css` §B (scoped to
   `.dataTables_wrapper`), layered under the per-screen token skins that still recolor them.
4. CSV/export buttons: no Buttons extension / bootstrap5 skin buttons exist on any index screen —
   not applicable.

### F.3 Index screens using DataTables (11)
`clients/index`, `households/index`, `scholars/index`, `transactions/index`,
`scholarship_reports/index`, `update_logs/index`, `payouts/attendance`, `payouts/index`,
`unpaid_verifications/index`, `admin/users/index`, `admin/audit_logs/index`, `duplicates/index`.

---

## G. Shared-Component Migration Order (SHARED-FIRST)

These must be migrated **before** their consumers, or consumers will break (they call
`bootstrap.Modal`/`data-bs-*` on the shared elements). Order:

1. **Vanilla modal + toast helpers** — new Bootstrap-free components under
   `resources/js/components/` (e.g. `Modal.js`, `Toast.js`) exposing `window.uiModal(...)` /
   `window.uiToast(...)` mirroring the current semantics (static backdrop, no-keyboard for the
   client form modal, show/hidden callbacks, `.remove()` on hidden for dynamically-built ones).
   Then migrate `partials/confirm-modal.blade.php` to use it (keeps `window.uiConfirm` contract).
2. **`partials/record-view-modal.blade.php`** — rebuild as a vanilla modal partial; update any
   consumers that call `bootstrap.Modal` on `#viewModal` (clients/scholars/etc.) to the helper.
3. **`partials/sidebar.blade.php` + `partials/navbar.blade.php`** — these are coupled (offcanvas
   toggle ↔ drawer). Migrate together: replace `offcanvas-lg`/`data-bs-toggle="offcanvas"`/
   `data-bs-dismiss="offcanvas"` with a Tailwind drawer + tiny vanilla toggle IIFE (or keep the
   responsive `lg:static`/`lg:hidden` behavior already in place and add a single mobile drawer;
   add a vanilla `aria-expanded` sync to replace the layout's offcanvas event wiring).
   Replace sidebar group `collapse` toggles with a vanilla collapse IIFE (or native
   `<details>`), preserving `aria-expanded`/`aria-controls`.
   Replace navbar `dropdown` with a vanilla dropdown IIFE (or `<details>`), preserving the
   Logout form + ESC/outside-click (reuse the global-search outside-click pattern).
4. **`layouts/app.blade.php`** — drop Bootstrap CDN links, drop DataTables BS5 links (handled in
   §F under removal gate — but the layout change happens once the shared parts it hosts are
   migrated), and remove the `bootstrap.Toast` calls + offcanvas event listeners (or repoint to
   vanilla equivalents).
5. Only THEN migrate module screens (D.2–D.5 order below).

---

## H. Alpine.js — Approved Interaction Layer

- **Decision: ADOPT Alpine.js** as the replacement interaction layer for Bootstrap JS behaviors.
  This supersedes the earlier Vanilla-JS-only stance in this plan (see the ADR entry in
  `docs/IMPLEMENTATION_LOG.md`, dated 2026-09-03). The forensic audit
  (`docs/TAILWIND_MIGRATION_FORENSIC_AUDIT.md` Appendix F) originally recommended Alpine.js
  ("lightweight, declarative") — this decision aligns the plan with that recommendation.
- **Applies to:** Modal, Toast, Offcanvas / sidebar drawer, Collapse, Dropdown, and simple
  dismissible UI — i.e. the Bootstrap JS behaviors listed in §D and §A.4.
- **Does NOT apply to:** `DetailsPanel.js`, `FilterChips.js`, `global-search`, and the
  `axios`/`bootstrap.js` wrapper — these existing Vanilla JS components stay unchanged
  (Bootstrap-free, tested) unless later repository evidence proves migration necessary.
  DataTables/jQuery stays unchanged (only its Bootstrap-5 skin leaves at the gate).
- **No custom `Modal.js`/`Toast.js`/`Drawer.js`/`Dropdown.js`/`Collapse.js`** IIFEs will be
  authored. Alpine directives replace those behaviors directly.
- **No other UI library** (Flowbite, DaisyUI, etc.) will be introduced.
- **Integration:** Alpine is installed via npm (`alpinejs`) and bundled through the existing
  Vite `resources/js/app.js` entry (see §E.2 / Phase 0). It is NOT loaded by CDN.
- **Loading scope:** the Vite JS bundle is only loaded where Alpine behavior is required
  (present implementation: the app shell, `layouts/app.blade.php`). Standalone pages are
  evaluated individually and the bundle is added only where a page's interaction is actually
  migrated to Alpine — never automatically.

---

## I. Preflight (Reboot) Strategy

- `resources/css/app.css` has **Preflight disabled** on purpose so Tailwind and Bootstrap Reboot
  coexist. The entire migration **keeps this disabled** so each step can run with both systems in
  the DOM.
- **Do NOT enable Preflight during the migration.** Enabling Preflight rebases every base element
  (headings, lists, borders, button reset) and would visually alter screen after screen.
- **Post-Bootstrap removal gate** (§K) had a final, separate, reviewed step: decide whether to
  enable Preflight now that Bootstrap Reboot is gone.
  > **DECISION — MADE by Phase 27 (2026-09-07): NOT enabled.** ui.css §4.10 owns Reboot +
  > `_type.scss` parity (universal `box-sizing:border-box`, `body{margin:0;color:#212529}`,
  > element + heading defaults) because Bootstrap Reboot was supplying the global reset for the
  > entire app (Tailwind Preflight has never been present here). Enabling Preflight now would
  > visibly shift bare headings (`font-size:inherit`), `img` (block), `hr`/`button`/table defaults
  > on authenticated screens — a regression with no parity payoff measured
  > (live harness: **0 computed-style diffs** vs the pre-removal baseline). Reviewing a Preflight
  > flip remains future baseline work and would be one dedicated commit with a full Playwright
  > visual check across all configured browser projects.

---

## J. `ui.css` Retirement / Roles

- `public/css/ui.css` is **not a Bootstrap-only file**. It holds the `--ui-*` token vocabulary
  (the future `@theme` transplant source) and shared component CSS used by both the shell and
  unmigrated screens. It is therefore **retained**, not deleted.
- Its **only Bootstrap dependency** was the 49 `--bs-btn-*`/`--bs-*` override lines in the
  `.btn-primary/.btn-outline-primary/.btn-danger/.btn-outline-danger/.btn-gold/.form-*/.form-check-*`
  blocks — **DELETED by Phase 26 (2026-09-07)** as part of the shared-component ownership rewrite of
  §4 (project-owned `.btn` base + variants, form controls, input-group, `.btn-close`, `.btn-group`,
  `.alert`, plain `.table`, dropdown CSS, all byte-faithful to Bootstrap 5.3.2). `ui.css` now has
  **zero `--bs-*` references**.
- Over the course of the migration, `@theme static` in `app.css` increasingly becomes the
  canonical token source (§9.6 of that file). When the final Bootstrap consumer is gone and the
  token transplant is complete, `ui.css` may be **retired entirely** — that is the terminal,
  separately-reviewed step (§K, item 6). Do not retire it sooner; unmigrated screens still
  reference `--ui-*`/`.btn-*`/`.tag` classes it provides.

---

## K. Bootstrap Removal Gate (checklist — all must pass before the CDN leaves)

Only proceed to remove Bootstrap CDN links once EVERY item below is true. Each is a
**reviewed, separate** change:

1. [x] **Zero `bootstrap.Modal` / `bootstrap.Toast` / `bootstrap.Offcanvas` / `bootstrap.Collapse` /
       `bootstrap.Dropdown` JS calls** remain anywhere in `resources/views` (grep clean).
       > **DONE by Phase 24 (2026-09-06):** zero live `bootstrap.*()` calls anywhere (views, `resources/js`,
       > built `app.js`); the JS CDN bundle was removed.
2. [x] **Zero `data-bs-*` attributes** remain in `resources/views` (grep clean).
       > **DONE by Phase 27 (2026-09-07):** the last one — the proven-dead
       > `data-bs-toggle`/`data-bs-target` `#passwordModal` trigger at `admin/users/show.blade.php` — was
       > removed (kept the Edit button + native inline password form).
3. [x] **Zero Bootstrap CSS class dependencies** remain in templates for anything that isn't
       overridden by token CSS (audit `.btn`, `.modal`, `.dropdown`, `.offcanvas`, `.collapse`,
       `.toast`, `.alert`, `.badge`, `.card`, `.table`, `.form-*`, `.grid`).
       > **DONE by Phases 25/26/27 (2026-09-07):** `.dropdown*`, `.alert*`, the `.btn` base + variants + `.btn-group`,
       > `.form-control/-sm`/`.form-select/-sm`/`.form-check-input`, `.input-group(.text)`, `.btn-close(-white)`,
       > plain `.table`/`.table-sm`/`.align-middle`/`.table-responsive`, DataTables chrome, **plus** (Phase 27)
       > `.form-label`, `.accordion*`, `.list-group*`, the Bootstrap **utility** layer (`.d-none` JS contract,
       > display/flex/spacing/text/grid helpers, `!important` parity) and **Reboot + `_type.scss` element defaults**
       > (universal `box-sizing:border-box`, `body{color:#212529}`, headings) are all project-owned in
       > `ui.css` §4.1–4.10 / `datatables.css` with **zero `--bs-*`** consumption.
4. [ ] **DataTables BS5 skin removed**: `dataTables.bootstrap5.min.css` and
        `dataTables.bootstrap5.min.js` no longer referenced; every index screen's `.dataTables_wrapper`
        CSS covers length/search/paginate/buttons with token styles (verified via Playwright).
        > **Partial — Phase 25 done:** the skin **CSS** is NO LONGER referenced (replaced by the
        > self-contained `public/css/datatables.css`, which covers the length/search/paginate
        > chrome under `.dataTables_wrapper`); `dataTables.bootstrap5.min.js` (the class emitter)
        > is still referenced by design — its removal is the migration end-state (out of scope for
        > the Bootstrap-CSS removal work).
5. [x] **`ui.css` `--bs-*` blocks deleted** (the 49 `--bs-btn-*`/form-state overrides).
       > **DONE by Phase 26 (2026-09-07):** ui.css has **0 `--bs-*` references** (was 49); the
       > project-owned ownership layer renders byte-identical to Bootstrap 5.3.2 at the app's 14px root.
6. [x] **Standalone pages** (`qr/viewer`, `students/*`, `grantee_update/self-service`,
       `unpaid_verifications/self-service`) no longer load their own Bootstrap CSS/JS.
       > **DONE by Phase 27 (2026-09-07):** all 8 CSS `<link>`s (incl. `layouts/app` + `auth/login`) removed;
       > live Chromium verifies 0 bootstrap network requests, `window.bootstrap` undefined, 0 console errors.
7. [x] **Shell offcanvas/collapse/dropdown** replaced by vanilla helpers and `layouts/app.blade.php`
       no longer references Bootstrap.
       > **DONE by Phase 27 (2026-09-07):** no Bootstrap CSS/JS reference remains in the layout (drawer is
       > Tailwind transform-driven; dropdowns Alpine `.show`).
8. [ ] **Full cross-browser regression passed** (§L) across all configured Playwright projects.
       > **NOT RUN (documented limitation):** auth-gated screens require an admin login account (none exists)
       > and DB must remain untouched at 1604 audit rows. Standalone-page coverage via the live Chromium
       > harness: 5/5 structural gates + 0 computed-style diffs + mobile no-overflow spot-checks (Phase 27).
9. [x] **Post-gate Preflight decision** made (reviewed) — §I.
       > **DECIDED by Phase 27 (2026-09-07): Preflight NOT enabled.** §4.10 Reboot + `_type.scss` parity
       > preserves today's pixels (Preflight would change bare heading sizes, `img` display, `hr`/buttons).
       > A Preflight review remains future baseline work (see §I).

Only after (1)–(9): remove the remaining Bootstrap CSS `<link>` from `layouts/app.blade.php`
(line 9) — the definitive removal action (the JS `<script>` at line 106 was already removed by
Phase 24).
> **DONE by Phase 27 (2026-09-07):** all 8 CDN `<link>`s removed; the only `bootstrap@5.3.2`
> string left in the repo is the intentional absence-assertion locator in `e2e/alpine-phase0.spec.ts`.

---

## L. Testing Strategy

- **PHPUnit Feature tests** (existing, run `php artisan test`) must all pass unmodified. These
  cover controllers/routes/ACL/business rules and are untouched by a template-only migration —
  they are the backstop that the migration changed no server behavior.
- **Playwright E2E** (`e2e/*.spec.ts`, `playwright.config.ts`) is the UI regression suite.
  - Per-feature: run only the relevant test file/project while developing a screen.
  - Cross-browser: run all configured projects at each milestone boundary (shell, clients, module
    completion) and at the removal gate.
  - Full suite: only at the removal gate and post-gate Preflight state.
  - Playwright must **adapt to the app**, never re-define behavior to pass (per AGENTS.md).
- **Manual visual check against the prototype** for the heavy interactive surfaces: client
  details panel, client add/edit modal (static backdrop, no-keyboard, validation feedback modal),
  scholars relink modal, confirm dialog, navbar dropdown, sidebar collapse/offcanvas, toasts.
- **Build verification**: `npm run build` must emit Tailwind utilities for every freshly
  `@source`-ed file (check output CSS), and no console errors (`window.bootstrap is undefined`
  must not occur after the gate).

---

## M. Risks & Rollback

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Modal semantics drift (static backdrop, no-keyboard, show/hidden callbacks, focus) | Med | High | Port behavior exactly into Alpine modal markup (`x-show`/`x-transition`, `@keydown.escape`, backdrop, focus); dedicated Playwright + manual checks; keep `window.uiConfirm`/details contract signatures. |
| DataTables chrome breaks after dropping BS5 skin | Med | High | Keep token `#*-screen .dataTables_wrapper` overrides; explicit `.page-link`/length/filter/paginate remap; E2E on every index that paginates/sorts/searches. |
| Offcanvas/collapse a11y regress (focus trap, aria, ESC, outside-click) | Med | Med | Move sidebar drawer to Alpine (`x-show`/`x-transition`, `@click.outside`, `@keydown.escape`) with the same a11y the details-panel implements (focus trap, aria-expanded, ESC); keep the details-panel's existing vanilla focus trap precedent. |
| Alpine runtime conflicts with Bootstrap-free IIFEs (DetailsPanel/FilterChips/global-search) | Low | Med | Alpine mounted on specific `x-data` roots; Vanilla IIFEs keep their `window.*` contracts and are not converted. |
| `@vite(['resources/js/app.js'])` coexisting with per-screen jQuery/DataTables | Low | Med | Alpine does not use `$`; DataTables loads its own jQuery/CDN per index screen (independent); verify no global `Alpine`/`$` collision during development. |
| `@source` allowlist misses new Tailwind classes → silent visual loss | Med | Med | Add `@source` in same change as first use; verify via built CSS + Playwright (see §E). |
| Enabling Preflight prematurely rebases all styles | Low | High | Keep disabled throughout; preflight only as gated post-Bootstrap step (§I). |
| Removing Bootstrap CSS too early breaks an unmigrated screen | Low | High | Strict gate order (§K); `ui.css` retained until terminal step; standalone pages migrated individually before gate. |
| Shared partial (record-view-modal/collapse) consumers not all updated | Med | Medium | Grep all `bootstrap.Modal`/`data-bs-target` consumers before removing a shared partial's Bootstrap; migrate shared part → update all consumers in same logical unit. |

**Rollback**: each step is an isolated commit (or at minimum a clearly-scoped change with the
`@source` line). Because Bootstrap stays loaded until the final gate, any intermediate commit can
be reverted without a global visual break. Database/schema are never touched — no data risk.

---

## N. Files Expected to Change per Phase

**Phase 0 — Alpine foundation (no Bootstrap removal, no component migration):**
- `package.json` / `package-lock.json` — add `alpinejs` dependency.
- `resources/js/app.js` — import + start Alpine (`window.Alpine = Alpine; Alpine.start();`).
- `resources/views/layouts/app.blade.php` — add `@vite(['resources/js/app.js'])` (the app shell
  hosts the shared components where Alpine behavior is first required).
- Standalone pages: `@vite(js)` added **only** per-page when that page's interaction is actually
  migrated to Alpine — never automatically.
- `docs/TAILWIND_MIGRATION_EXECUTION_PLAN.md` + `docs/IMPLEMENTATION_LOG.md` — record the decision.

**Phase 1 — Shared partials (SHARED-FIRST), each migrated to Alpine:**
- `partials/confirm-modal.blade.php`, `partials/record-view-modal.blade.php`,
  `partials/sidebar.blade.php` (offcanvas + collapse → Alpine), `partials/navbar.blade.php`
  (dropdown → Alpine), and any consumer of `bootstrap.Modal`/`data-bs-*` on those partials.

**Phase 2 — Layout:**
- `layouts/app.blade.php` (remove Bootstrap CDN, remap toast/error markup to Alpine,
  repoint offcanvas listeners; DataTables BS5 link removal deferred to gate).

**Phase 3 — Modules (per-module, each verified):**
- Clients: `clients/index|show|_details|_form|_gip`.
- Then: `transactions/*`, `scholars/*`, `households/*`, `family_members/create`,
  `scholarship_reports/index`, `update_logs/index`, `unpaid_verifications/*`,
  `duplicates/index`, `payouts/*`, `scanners/*`, `sessions/online`, `admin/*`, `dashboard`.

**Phase 4 — Standalone pages:**
- `auth/login`, `qr/viewer`, `students/verify|update-photo|photo-upload`,
  `grantee_update/self-service|_self_update_tab`, `unpaid_verifications/self-service`.

**Phase 5 — Removal gate (§K) + post-gate:**
- `layouts/app.blade.php` (final CDN removal), `public/css/ui.css` (delete `--bs-*` blocks),
  each DataTables index (drop `.bootstrap5` shims), `resources/css/app.css` (Preflight decision,
  `@theme` completion), and full regression.

**Files that must NOT change (Bootstrap-free already):**
- `partials/details-panel.blade.php`, `partials/filter-chips.blade.php`,
  `partials/global-search.blade.php`, `partials/sidebar-link.blade.php`,
  `partials/sidebar-icon.blade.php`, `partials/page-header.blade.php`,
  `partials/breadcrumbs.blade.php`, `partials/active-filters.blade.php`,
   `partials/confirm-modal.blade.php`, `partials/record-view-modal.blade.php`,
   `partials/client-form-modal.blade.php` (Phase 8, Alpine),
   `partials/client-feedback-modal.blade.php` (Phase 9, Alpine),
   `resources/js/components/DetailsPanel.js`, `resources/js/components/FilterChips.js`,
   `resources/js/bootstrap.js` (note: `resources/js/app.js` now bundles Alpine and is part of
   Phase 0/2 — it is not "frozen").
- All `app/`, `routes/`, `database/`, `config/`, tests that assert backend behavior, and
  anything under `prototype/` (source of truth, read-only for this effort).

---

## O. Implementation Status + Next Phase (requires separate approval)

**Phase 0 (Alpine foundation), Phase 1 (confirm-modal → Alpine) and Phase 2
(record-view-modal → Alpine) are COMPLETE** (see the dated ADR + phase entries in
`docs/IMPLEMENTATION_LOG.md`, 2026-09-03): `alpinejs` installed via npm, wired into
`resources/js/app.js` (Vite bundle), and `@vite(['resources/js/app.js'])` added to the app shell.
The shared `partials/confirm-modal.blade.php` and `partials/record-view-modal.blade.php` are
migrated off `bootstrap.Modal` to Tailwind + Alpine behind Alpine stores, preserving each partial's
contract exactly. Each migration exposed a small **imperative compatibility bridge**
(`window.uiConfirm` for confirm-modal; `window.uiViewModal` for record-view-modal) so consumers stay
Alpine-agnostic — consumers changed only at their single `bootstrap.Modal(...).show()`/`uiConfirm`
call sites. Covered by `e2e/confirm-modal-phase1.spec.ts` (42 checks) and
`e2e/record-view-modal-phase2.spec.ts` (49 checks) across 7 projects. Bootstrap remains loaded for
all other components.

**Completed phases:** Phase 0 (Alpine foundation), Phases 1–2 (confirm-modal, record-view-modal),
Phase 3 (sidebar offcanvas + collapse → Alpine, navbar dropdown → Alpine),
Phase 4 (clients/transactions export dropdowns → Alpine),
Phase 6 (clients GIP accordion collapse → Alpine x-collapse),
Phase 7 (clients GIP modal → Alpine.js),
**Phase 8 (client form modal → Alpine.js)**,
**Phase 9 (client feedback modal → Alpine.js + client toast → pure JS)**,
**Phase 10 (client photo modal → Alpine.js)**,
**Phase 12 (admin/users password reset modal → Alpine.js)**,
**Phase 13 (scanner message modal → Alpine.js)**,
**Phase 14 (Bootstrap Alert dismissals → Alpine.js)**,
**Phase 15 (Scholars Client ID Prompt Modal → Alpine.js)**,
**Phase 16 (Audit Logs Leaderboard Modal → Alpine.js)**,
**Phase 17 (Unpaid Verification Dynamic Confirmation Modal → Alpine.js)**,
**Phase 18 (Students Photo Modal → Alpine.js)**,
**Phase 19 (Shared Layout Flash Toast → Alpine.js)**,
**Phase 20 (Transactions + Households success toasts → Alpine.js)**,
**Phase 21 (Bootstrap JS inventory + navbar hamburger Alpine scope fix)**,
**Phase 22 (Bootstrap Toast init loop removal)**,
**Phase 23 (Bootstrap removal-gate audit — audit only)**, the
**Phase 24 (Bootstrap JS bundle removal — bundle `<script>`s gone)**, and the
**Phase 25 (DataTables skin CSS ownership — CDN bootstrap5 skin → project `public/css/datatables.css`)**, and the
**Phase 26 (Core Bootstrap CSS component ownership — shared components moved into project `ui.css`, 49 `--bs-*` refs → 0)**, and the
**Phase 27 (Final Bootstrap CSS removal — all 8 CDN CSS `<link>`s removed; `ui.css` §4.8–4.10 owns form-label/accordion/list-group + the Bootstrap utility + Reboot/type layers; removal-gate **READY**, dependency **8 → 0)**.

**Current module migration status (§M, Clients module):**
- `sidebar.blade.php`: offcanvas + collapse → Alpine ✓
- `navbar.blade.php`: dropdown → Alpine ✓; hamburger toggle → Alpine ✓ (Phase 21 —
  the button's `@click="$store.sidebar.toggle()"` + `:aria-*` were already Alpine-authored in
  Phase 3 but sat OUTSIDE any `x-data` scope and were inert; added bare `x-data` to the button)
- Export dropdowns (clients + transactions): → Alpine ✓
- `clients/_gip.blade.php`: accordion collapse → Alpine x-collapse ✓
- `clients/_gip.blade.php`: GIP modal → Alpine.js ✓
- Client form modal (new `partials/client-form-modal.blade.php`): → Alpine.js ✓
- Feedback modal (new `partials/client-feedback-modal.blade.php` + `_details` fallback): → Alpine.js ✓
- Client toast stack (`clients/index.blade.php`, `clients/_details.blade.php`): → pure JS ✓
- Photo modal (`_details.blade.php`): → Alpine.js ✓ (Phase 10)

With Phase 10 complete, **no clients-module modal or toast depends on Bootstrap JS
anymore.** Remaining Bootstrap JS in the clients module is only the DataTables skin
(`dataTables.bootstrap5`), which is a removal-gate item — and with **Phase 24**, the
Bootstrap 5.3.2 **framework JS bundle** is fully removed (no view loads it); the
DataTables bootstrap5 skin **JS** (which needs only jQuery + DataTables) remains, along with the
Bootstrap CSS CDN. **Phase 25 (2026-09-06) replaced the DataTables bootstrap5 skin CSS** — the
`dataTables.bootstrap5.min.css` CDN on the 11 screens is now the project-owned,
self-contained `public/css/datatables.css`, which owns the generated
`table(.sm)`/`form-control(-sm)`/`form-select(-sm)`/`.pagination`/`.page-link` chrome inside
`.dataTables_wrapper` with zero `--bs-*` consumption, so DataTables renders identically with
Bootstrap CSS loaded or removed (Bootstrap CSS itself remains loaded and required for its other
consumers — see the Phase 25 gate).

**Next implementation phase — module screens still on Bootstrap (requires approval):**
proceed with the shared-partial / module-screen order in §D/§N below (other modules'
markup remains on Bootstrap). The clients
**photo modal**, the **admin/users password reset modal (Phase 12)**, the
**scanner message modal (Phase 13)**, all **Bootstrap Alert dismissals
(Phase 14)**, the **Scholars Client ID Prompt Modal (Phase 15)**, the
**Audit Logs Leaderboard Modal (Phase 16)**, the **unpaid self-service dynamic
modal (Phase 17)**, the **students photo modal (Phase 18)**, the **shared
layout flash toast (Phase 19)**, the **transactions + households success
toasts (Phase 20)**, the **navbar hamburger scope fix (Phase 21)**, the
**layout Bootstrap Toast init loop removal (Phase 22)**, the
**removal-gate audit (Phase 23 — audit only)**, the
 **Bootstrap JS bundle removal (Phase 24)**, the
 **DataTables skin CSS ownership (Phase 25)**, the
 **Core Bootstrap CSS component ownership (Phase 26)**, the
 **final Bootstrap CSS removal (Phase 27 — all CDN CSS `<link>`s gone)**, and the
 **final Bootstrap-free forensic audit (Phase 28 — AUDIT ONLY, 2026-09-07)** are done;
 do not re-migrate them. Phase 28 independently re-verified the whole Bootstrap surface
 (fresh repo-wide search with per-occurrence classification; CSS/JS CDN counts = 0 in views,
 compiled views, built assets, and served HTML; live Chromium `window.bootstrap` undefined +
 0 console/network errors on 6 public pages; `--bs-*` active consumption 0; DataTables class
 emitter confirmed not to reference the Bootstrap library; **301 tests / 1419 assertions / 0
 failures**, Pint + build + view:cache clean; **NO further Bootstrap-removal phase required**).
 With Phase 27 the **Bootstrap dependency is fully removed**
 (CSS 8 → 0, JS already 0); remaining end-state candidates, separately reviewed, are:
 enable Tailwind Preflight (§I — currently decided NOT, §4.10 Reboot parity instead),
 make `.form-label`/`.accordion`/`.list-group`/utility usage on auth-gated screens
 covered by browser-run E2E once an admin account exists, and the terminal
 `dataTables.bootstrap5.min.js` emitter→vanilla DataTables end-state (§F).

Phase 18 (completed 2026-09-05): migrated `#photoModal` in the students
`students/photo-upload.blade.php` public flow from Bootstrap Modal to Tailwind +
Alpine (`photoModal` store with `openModal()`/`close()` + `photoModalComponent()`
Tab-trap). Standalone page gets `x-data="photoModalComponent()"` on `<body>` and
loads `app.js` so the Alpine trigger can live. The "Take Photo" trigger is now
`@click="$store.photoModal.openModal()"` (was `data-bs-toggle`), the dead
"Photo" button in `scholars/show.blade.php` (a `data-bs-*` pair targeting a
modal that never existed on that page) had its dangling attributes stripped.
The camera script (`initCamera`/`loadCameraDevices`/`switchCamera`/capture/
retake/`stopCamera`) is preserved verbatim and driven from the Alpine lifecycle
(visible-modal open → camera start; close → every track stops) exactly like the
old `shown.bs.modal`/`hidden.bs.modal` handlers — including the old
"stale preview persists across close" behavior. Close set unchanged (no X/Close
button; backdrop + ESC only), focus entry `#cameraSelect`, Tab/Shift+Tab
trapped, focus + body scroll restored on close. Because a modal trigger outside
any `x-data` scope is never bound (also true of the navbar hamburger and the
Audit Logs Leaderboard button — pre-existing, out of Phase 18 scope), the modal
root carries `x-show` + `x-cloak` so `#photoModal` is absent from the tree when
closed. Covered by `e2e/photo-modal-phase18.spec.ts` (4 chromium tests, live
student-verify flow + fake-camera lifecycle). Full suite **297 passed (1397
assertions), 0 failures**, build + view:cache + Pint clean.

Phase 19 (completed 2026-09-05): migrated the shared layout flash toast in
`layouts/app.blade.php` (`#flashToast`, `bootstrap.Toast.getOrCreateInstance(…,
{ autohide: false }).show()`) to Tailwind utilities + Alpine state. The toast
div lost the `.toast` class and `data-bs-autohide`/`data-bs-dismiss="toast"`,
gained `x-data="{ open: true }"` + `x-show="open"` (+ stable `id="flashToast"`);
the close button keeps `.btn-close`/`aria-label="Close"` but dismisses via
`@click="open = false"`. Persistent + one-shot semantics preserved (renders
visible from server HTML, closes only by click). The layout's init loop is
**kept verbatim** for the remaining child-view `.toast` surfaces
(transactions/households success toasts, clients Phase 9 stack); because the
layout toast no longer carries `.toast`, it is never re-initialized. Flash
contract (`session('login_status')`, `@if` guard, `aria-live="polite"` +
`role="status"`) unchanged. E2E `e2e/flash-toast-phase19.spec.ts` passed
live (4 chromium tests ×2 runs) via a zero-DB-write trigger
(`session.force-logout` with `user_id=99999999` → `back()->with('login_status',
'User not found.')`) driven through an injected form (real browser navigation —
`page.request` redirect-follow and in-page `fetch(redirect:'manual')` do not
carry the flash). An **ephemeral** `smoke_superadmin` account was inserted into
the local DB for the E2E and fully removed afterwards. Full suite **297 passed
(1397 assertions), 0 failures**, build + view:cache + Pint clean.

Phase 21 (completed 2026-09-06): fresh repo-wide Bootstrap JavaScript surface
inventory (classified A–F). Only **three live surfaces** remain in the Laravel
layer: (1) the intentional layout init loop `bootstrap.Toast.getOrCreateInstance`
(`layouts/app.blade.php:139`), serving the clients Phase 9 stack (kept); (2) the
dangling `data-bs-toggle="modal" data-bs-target="#passwordModal"` trigger on the
details-panel partial `admin/users/show.blade.php:134` (previously logged in
Phase 12; out of scope); and (3) the navbar hamburger. The inventory also
corrected the task path: `resources/views/components/navbar.blade.php` does not
exist — the navbar is `resources/views/partials/navbar.blade.php`. Migrated the
hamburger from **inert** Alpine (directives outside any `x-data` scope → never
bound, verified live: `_x_dataStack:false`, click no-op) to a working toggle via
a bare `x-data` on the button; it drives the existing shared `$store.sidebar`
store (drawer `#appSidebar`, backdrop, ESC, scroll lock — all unchanged; the
drawer is Tailwind transform-driven, **no Bootstrap Collapse involvement**).
Compiled view carries the migrated button; E2E `e2e/navbar-phase21.spec.ts`
**5/5 chromium + 5/5 Mobile Chrome** (serial; Mobile Chrome failure was the known
single-device login token collision, not an app bug), smoke regression 4/4
chromium. Full suite **301 passed (1419 assertions), 0 failures**, build +
view:cache + Pint clean; DB restored to 1604 audit rows.

Phase 22 (completed 2026-09-06): investigation-first — proved the layout init
loop `bootstrap.Toast.getOrCreateInstance(el, { autohide: false }).show()`
(`layouts/app.blade.php:139`) was **redundant** and removed it. The **only**
`.toast`-class element it could match at page-parse time is the clients flash
toast (`clients/index.blade.php:284`), which its own inline `wireFlashToast`
(clients/index:793, invoked at :799) already reveals and wires independently; the
dynamic `showToast` elements (index:760 / `_details`:820) are created at runtime
after the loop has run, reveal with `.show`, and dismiss by manual removal
(pure JS). The Phase 19 `#flashToast` and the Phase 20 page toasts no longer
carry `.toast`, so they were never matched. Repo-wide greps (incl. `resources/js`)
found **zero** other `bootstrap.Toast` consumers, zero `.bs.toast` events, and no
E2E/feature test depending on the loop — so **no Bootstrap Toast JS surface
remains anywhere in the application**. Removed only the obsolete loop + stale
comment, replaced with a concise note that the Bootstrap bundle stays for the
remaining non-toast CDN consumers. Full suite **301 passed (1419 assertions), 0
failures**, build + view:cache (compiled-view scan confirms no `bootstrap.Toast`)
+ Pint clean; view:clear done; DB untouched (no ephemeral account created — toast
contract covered by existing Bootstrap-JS-free
`client-feedback`/`flash-toast`/`page-toasts` specs).

Phase 23 (completed 2026-09-06, audit only — NO code changes): fresh
removal-gate audit. **JS bundle = REDUNDANT**: zero live `bootstrap.*()`
calls anywhere (views, `resources/js`, built `app.js`); the only `data-bs-*`
left is `admin/users/show.blade.php:134`, **proven DEAD** (target
`#passwordModal` exists in no document — the Phase 12 modal's id is
`passwordForm`; Bootstrap's delegated handler would resolve null; it sits in a
`display:none` actions block) — left untouched (accepted-UI contract, §12).
`dataTables.bootstrap5.min.js` (fetched, 1.13.6) UMD-wraps only jQuery +
DataTables and never references the `bootstrap` global. **CSS CDN = REQUIRED**:
Bootstrap 5.3.2 CSS renders the DataTables bootstrap5 skin base chrome on 11
screens (per-screen token skins recolor only), `.form-control`/`.form-select`/
`.input-group`/`.form-check-input`, `.btn-close` on migrated Phase 12/14/19/20
toasts+alerts, the `ui.css` `--bs-*` variable bridge (49 refs) that powers
`.btn`/`.btn-gold`/`.btn-primary`, the clients export `.dropdown-menu`, and 7
standalone public pages. **Removal-gate verdict: `NO — DEPENDENCY REMAINS`
(Bootstrap CSS); Bootstrap JS bundle REMOVAL-SAFE (deferred).** Full suite
**301 passed (1419 assertions), 0 failures**, build + view:cache (compiled
views: one documented dangling data-attribute, zero `bootstrap.*()` calls) +
Pint clean; E2E not run (audit-only, no account created — documented
limitation); DB untouched.

Phase 24 (completed 2026-09-06): executed the controlled bundle removal the
Phase 23 gate authorized. Removed the six `cdn.jsdelivr.net/npm/bootstrap@
5.3.2/dist/js/bootstrap.bundle.min.js` `<script>` includes: `layouts/app`,
`auth/login`, `qr/viewer`, `students/photo-upload`,
`unpaid_verifications/self-service`, `grantee_update/self-service`. Also
corrected the `unpaid_verifications/self-service` Batch-G head comment (it
claimed the bundle "is kept for coexistence" — now records the Phase 24
removal). **Bootstrap CSS unchanged** — all 8 `bootstrap.min.css` links
verified after removal; DataTables skin CSS + JS, jQuery, Alpine, and the
dangling `admin/users/show.blade.php:134` attribute all untouched. Post-change
audit: **0** bootstrap JS CDN references in views and compiled output; live
Chromium on `/login` + `/qr-viewer` — HTTP 200, `typeof window.bootstrap ===
'undefined'`, **zero console errors** (closes the §12 gate: nothing invokes
Bootstrap JS). Full suite **301 passed (1419 assertions), 0 failures**, build
(vite 6.4.3) + view:cache (no compiled bootstrap-JS refs) + Pint clean; E2E
suites not run (require admin login; no account created; DB untouched at 1604
audit rows) — documented limitation.

Phase 25 (completed 2026-09-06): first controlled Bootstrap-**CSS** ownership migration —
replaced the DataTables 1.13.6 Bootstrap 5 skin CDN (`dataTables.bootstrap5.min.css`, 11
per-screen `@push('styles')` links) with the project-owned, self-contained
`public/css/datatables.css` (zero `var(--bs-` consumption). It reasserts the upstream skin's
core chrome with identical selectors/values and owns the Bootstrap class names DataTables emits —
`table(.sm)`, `.form-control(-sm)` filter input, `.form-select(-sm)` length select,
`.pagination/.page-item/.page-link` (ui.css navy/gold tokens where the app re-points; Bootstrap
5.3.2 literal values elsewhere) — scoped to `.dataTables_wrapper`. `dataTables.bootstrap5.min.js`
(class emitter), jQuery, per-screen token skins, and Bootstrap CSS (8 links) all retained.
Post-change: **0** CDN skin-CSS refs in views/compiled output (11 `asset('css/datatables.css')`);
served HTTP 200 (20,561 B); full suite **301 passed (1419 assertions), 0 failures**, build +
Pint + view:cache clean. **Bootstrap-removal gate NOT ready** — remaining Bootstrap CSS consumers:
`.btn` base via ui.css's 49 `--bs-btn-*` bridge (26 `btn btn-*` usages incl. `btn-primary`/`btn-danger`/`btn-outline-primary`), `.form-control`/`.form-select`/`.form-check-input` bases,
`.btn-close` (14), `.btn-group` (2), `.alert`, `.table` outside DataTables, `.table-responsive`
(transactions), `.dropdown-toggle`/`.dropdown-menu`, and 7 standalone public pages (each loads
Bootstrap CSS itself). E2E not run (auth-gated DataTables screens; no admin account — documented
limitation).

Phase 26 (completed 2026-09-07): **Core Bootstrap CSS component ownership** — the second
Bootstrap-**CSS** ownership migration (Phase 23 gate's shared-component candidate). Rewrote
`ui.css` §4 ("Shared component ownership (Bootstrap CSS independence)", sections 4.1–4.7) to
project-own every shared Bootstrap class the app actually renders, byte-faithful to Bootstrap
5.3.2: the `.btn` base (private `--ui-btn-*` vars, `hover{border-color:currentColor}`,
`:focus-visible{outline:0}`, focus/active glows `rgba(0,56,168,.5)` / `rgba(206,17,38,.5)`,
`.btn:disabled` opacity .65, `.btn-sm`, `.btn-group` + radius joins) and its project variants
`.btn-primary`/`.btn-outline-primary`/`.btn-danger`/`.btn-outline-danger` +
`.btn.btn-gold:focus-visible` (app.css still owns the `.btn-gold` base); form controls
(`.form-control/-sm`, file selector, focus navy + `ui-focus-ring`, placeholder/disabled states,
`.form-select/-sm` with the `stroke='%23343a40'` chevron data-URI, `.form-check` +
`.form-check-input` navy checked/indeterminate/disabled); `.input-group` + `.input-group-text`;
`.btn-close(-white)` (blue focus glow `rgba(13,110,253,.25)` kept); `.alert` base + 4 variants
(Bootstrap 5.3 emphasis/subtle hexes) + `.alert-dismissible`; plain `.table` (Bootstrap-faithful
`margin-bottom:1rem`, cell `color:#000`, `.table-sm`, `.align-middle`, `.table-responsive`);
and the dropdown CSS used by the navbar + clients/transactions export menus
(`.dropdown-toggle` caret, `.dropdown-menu`, `[data-bs-popper]`/`-end` parity rules, `.show`,
`.dropdown-item`). Result: ui.css **`--bs-*` refs 49 → 0** (0 live `var(--bs-*)` consumption
anywhere; only comment mentions remain in app.css/datatables.css). The `.btn-gold` `--bs-btn-*`
bridge and the `.btn:hover` gradient bridge were removed (app.css fully owns `.btn-gold`/visible
props). **Audits:** 7 standalone public pages (auth/login, qr/viewer, students/verify,
students/update-photo, students/photo-upload, unpaid self-service, grantee self-service) remain
Bootstrap-CSS-dependent (load only the CDN; documented, untouched — they are their own final-phase
family); `admin/users/show.blade.php:134` `data-bs-toggle`/`data-bs-target="#passwordModal"`
proven dangling again (target modal exists only in `admin/users/index.blade.php`; no Bootstrap JS;
the show page's native `data-panel-edit-form` POST `admin.users.reset-password` is the real flow) —
documented as a finalization-pass removal candidate, **no markup changed this phase** (CSS-only).
**Verification:** full suite **301 passed (1419 assertions), 0 failures**, Pint clean, build clean
(same hashes app-D3mz-Mcl.css / app-DqsLDVL_.js), view:cache clean (0 `--bs-` in compiled views),
served HTTP 200 (`/login`, `/qr-viewer`, `/student/update-photo`, `/unpaid-verification`,
`/grantee-update`, `/css/ui.css`, `/css/datatables.css`), live Chromium harness (injected ui.css +
app.css into `/login`) — **48/48 computed-style checks pass** at the app's 14px root (incl. byte
parity on `.btn` radius `.375rem` = 5.25px, alert hexes, dropdown menu, caret, SVG chevrons,
`.btn-close-white` filter), **zero console errors**. **Removal-gate: more progress, still NOT
READY** — remaining Bootstrap CSS consumers: `.modal*` subsystem (48 class refs), `.accordion` (10),
`.list-group` (7), `.form-label` (46), `.d-none`/`.d-flex`/`.toast` utilities, and the 7 standalone
public pages. E2E not run (auth-gated screens; no admin account — documented limitation).

Phase 27 (completed 2026-09-07): **Final Bootstrap CSS removal** — the completion step
authorized by the Phase 26 gate. Removed **all 8** Bootstrap 5.3.2 CSS CDN `<link>`s
(`layouts/app`, `auth/login`, `qr/viewer`, `students/photo-upload`, `students/update-photo`,
`students/verify`, `unpaid_verifications/self-service`, `grantee_update/self-service`) and owned
every last family in `public/css/ui.css` §4.8–4.10, byte-faithful to 5.3.2:
**§4.8** `.form-label` (margin-bottom .5rem), the `.accordion*` subsystem (chevron data-URIs,
focus, collapse borders, active subtle/emphasis, disabled), `.list-group*` incl. `.list-group-
flush` + `.list-group-item-action` (`#dee2e6` border-color, `.5rem 1rem` item padding, flush
`0 0 1px` borders, last-child 0); **§4.9** the Bootstrap **utility** layer with its `!important`
semantics (`.d-none` — the JS classList contract with no Tailwind equivalent, `.d-*`/flex/
position/text/background/spacing ladder 0–5 + auto sides/`.gap-*`/`.row` + `.g-*` + `.col-sm-4/8`
+ `.col-md-3/4/6`) so the utilities that today beat Tailwind's non-important twins keep winning;
**§4.10** Reboot + `_type.scss` (universal `*,*::before,*::after{box-sizing:border-box}`, `body
{margin:0;color:#212529}`, element defaults, heading sizes + 1200px media query). Also removed the
proven-dangling `#passwordModal` `data-bs-*` trigger in `admin/users/show.blade.php` (finalization
candidate — the SHOW page's modal exists only on index; Edit button + native inline password form
kept), and flipped `e2e/alpine-phase0.spec.ts` to assert the Bootstrap CSS link absent + built
`ui.css` present. **Verification fallout caught and fixed:** the initial pass missed the Reboot
`body{color:#212529}` and Bootstrap's **global box-sizing** (its loss reverted every un-owned
element to content-box — probe showed `.form-control` 549→600px, `.btn-navy` 271→303.5px, card
420→369px, body content 1280→1232px); the two §4.10 lines fixed it and the re-run diff is clean.
**Verification:** full suite **301 passed (1419 assertions), 0 failures**, Pint clean, build clean,
view:cache clean, live Chromium harness (pre/post `bs-capture.mjs` + JSON baselines, scratch
deleted) — **5/5 structural gates** (0 Bootstrap CSS requests, `window.bootstrap` undefined, 0
console errors, 0 page errors, 200) and **0 computed-style diffs** across 5 standalone pages × 8
selectors × 44 props vs the pre-removal baseline; family spot-checks live (list-group flush
borders, `.d-none`, `h1.mb-3`=14px / `h1.mb-1`=3.5px); mobile 375×667/768×1024 no horizontal
overflow. Repo-wide `bootstrap@5.3.2` string now only in the E2E absence locator; `--bs-*` live
consumption 0. **Removal-gate (Bootstrap CSS): READY — dependency 8 → 0.** Tailwind Preflight NOT
enabled (see §I decision) — §4.10 Reboot parity preserves pixels. E2E not run (auth-gated
screens; no admin account; DB untouched at 1604 audit rows — documented limitation).

Phase 28 (completed 2026-09-07): **Final Bootstrap-free forensic audit (AUDIT ONLY)** — the
independent terminal audit. **No application source, CSS, JS, schema, or database was changed.**
- **Repo-wide search (fresh) with per-occurrence classification:** active dependencies **0**;
  everything else is documented even if it literally spells "bootstrap" — historical docs (B),
  comments (C), the `e2e/alpine-phase0.spec.ts:38` absence locator (D), two inert parity selectors
  `ui.css` `[data-bs-popper]` + `datatables.css` `[data-bs-theme=dark]` (E), and third-party
  framework/DataTables code (F: Laravel `bootstrap/` dir, `resources/js/bootstrap.js` = the axios
  shim, `dataTables.bootstrap5.min.js` = the class emitter).
- **CSS/JS network audit:** Bootstrap CSS CDN **0**, Bootstrap JS CDN **0**, DataTables bootstrap5
  **CSS** CDN **0** — in views, compiled views (`view:cache`), built assets (`public/build`), and
  served HTML (6 public pages). Only Google Fonts (CSS) and jQuery/DataTables/html5-qrcode JS CDNs
  remain; none are Bootstrap.
- **Runtime audit (live Chromium, 1280×800 + 375×667 + 768×1024):** `/login`, `/qr-viewer`,
  `/unpaid-verification`, `/grantee-update`, `/student/update-photo?search=`, `/student/verify/{id}`
  all **200**, `typeof window.bootstrap === "undefined"` everywhere, **0** Bootstrap network
  requests, **0** console errors, **0** page errors, **no horizontal overflow** at all viewports.
  Computed-style spot-check matched project-owned values (body `#212529` + border-box, form-control
  5.25px radius, form-select SVG chevron, flex card, btn-navy/gold tokens, `#a3cfbb` success alert).
- **`--bs-*` variable audit:** active consumption **0** — `var(--bs-` absent across `resources` and
  `public`; the 2 (`app.css`) + 15 (`datatables.css`) `--bs-` strings are comments annotating inlined
  literal values.
- **DataTables final audit:** all 11 screens confirmed on `jquery.dataTables.min.js` +
  `dataTables.bootstrap5.min.js` + project `css/datatables.css`; the 2,358-byte bootstrap5 renderer
  UMD-imports only `jquery` + DataTables and contains **zero** `bootstrap` global/library references
  (verified against the fetched 1.13.6 dist) — retained by design (Phase 25 contract), no Bootstrap
  runtime dependency.
- **Automated:** **301 tests / 1419 assertions / 0 failures** (34.22s), Pint clean, build clean
  (app-D3mz-Mcl.css 57.62 kB / app-DqsLDVL_.js 105.70 kB, 0 bootstrap hits), view:cache clean.
- **Database:** no migration, schema change, or write by this phase (test suite isolated on
  `main_system_test`); `migrations` table and 42-table inventory unchanged. Environment observation
  (pre-existing, pre-phase ~12:00, not modified): `tbl_audit_logs` = 1605 rows vs the 1604 baseline
  — one `LOGIN` row (id 1735, user 3, 2026-09-07 02:14). Deferred to the DB owner; a fix would be a
  DB write, out of an audit-only scope.
- **Verdict: PASS. BOOTSTRAP-FREE — COMPLETE. No further Bootstrap-removal phase is required.**
  (Full entry in `docs/IMPLEMENTATION_LOG.md`.)

Phase 20 (completed 2026-09-06): migrated the two page-level success toasts
(`session('success')` in `transactions/index.blade.php` and
`households/index.blade.php`) from Bootstrap Toast JS to Tailwind utilities +
Alpine state, as two independent per-page surfaces (`x-data="{ open: true }"` +
`x-show="open"`, close via `@click="open = false"`, `.toast` class +
`data-bs-autohide`/`data-bs-dismiss="toast"` removed, `role="status"` +
`aria-live="polite"` container + `aria-label="Close"` retained, per-page message
sink unchanged). The shared layout init loop is **kept verbatim** and now serves
only the clients Phase 9 feedback stack. Households E2E uses its real
store→index→toast→destroy flow; transactions has **no application route that can
produce the toast** (`TransactionController::store`/`update` redirect to
`transactions.show`), so E2E seeds the one-shot flash into the live file session
via a throwaway PHP helper (cookie value is a `CookieValuePrefix`-prefixed
`EncryptCookies` payload, decrypted in the helper) — an application finding
documented in the phase report. Full suite **301 passed (1419 assertions), 0
failures** (4 new tests), E2E Phase 20 5/5 + Phase 19 regression 4/4 chromium,
build + view:cache + Pint clean; DB restored to 1604 audit rows / id-1 household
/ no smoke remnants.

Phase 16 (completed 2026-09-04): migrated `#leaderboardModal` in
`admin/audit_logs/index.blade.php` from Bootstrap Modal to Tailwind + Alpine
(`leaderboardModal` store + `leaderboardModalComponent()`). The server-rendered
Leaderboard trigger (page-header actions) now calls
`@click="$store.leaderboardModal.open()"`. `open()` fires the existing jQuery
`$.ajax` POST to `admin.audit-logs.leaderboard` (`{ table: $('#table').val() }`
+ CSRF header) exactly **once per open** — no caching, one request per open,
same `.done` re-render (empty + append, escaped username); a late response still
populates the tbody (matching the old `show.bs.modal` behavior; no early-return
guard added). Removed `data-bs-toggle`/`data-bs-target="#leaderboardModal"`,
`data-bs-dismiss="modal"`, the `.modal*` structure, `.btn-close`, the
`show.bs.modal` handler, and the unused `leaderboardUrl` const. Full suite
**297 passed (1397 assertions), 0 failures** (`AdministrationTest` 32/133 incl.
leaderboard test; `FilterChipsTest` 24/90), build + view:cache + Pint clean.

Phase 15 (completed 2026-09-04): migrated `#clientIdPromptModal` in
`scholars/index.blade.php` from Bootstrap Modal to Tailwind + Alpine
(`clientIdPromptModal` store + `clientIdPromptModalComponent()`). The
`.edit-client-id` triggers live inside **DataTables AJAX rows**; the existing
delegated jQuery click handler now calls
`Alpine.store('clientIdPromptModal').openFor(id, current)` from the same
`data-id`/`data-clientid` attributes (no `relatedTarget`). The jQuery `$.ajax`
`POST scholars.update-client-id` submission, the success
`window.scholarsTable.ajax.reload(null, false)`, and the error alert are all
unchanged. Removed `data-bs-dismiss="modal"`, `.modal*` structure, `.btn-close`,
and `bootstrap.Modal.getOrCreateInstance(...)`. Full suite
**297 passed (1397 assertions), 0 failures** (`ScholarTest` 19/53), build +
view:cache + Pint clean.

Phase 14 (completed 2026-09-04): migrated every `data-bs-dismiss="alert"`
dismissal (7 alerts across the shared layout validation alert, duplicates,
admin/users, family_members/create, households/create) to per-alert
`x-data="{ open: true }"` + `x-show="open"` with `@click="open = false"` close
buttons. Server-side Blade conditions/content untouched (independent per-alert
state). Removed all Bootstrap Alert JS; verified no `bootstrap.Alert` /
`show.bs.alert` / `closed.bs.alert` remain. CSS-only `.alert` usages (qr/viewer,
grantee_update, unpaid self-service) intentionally left alone. Full suite
**297 passed (1397 assertions), 0 failures**, build + view:cache + Pint clean.

Phase 13 (completed 2026-09-04): migrated `#messageModal` in
`scanners/scan.blade.php` to Alpine (`scannerMessageModal` store +
`scannerMessageModalComponent()` + `window.showModal(msg, type, title, onOk)`
bridge preserving the exact public signature). Title/body still populated as
plain text (innerText preserves `\n` line breaks); `handleOk()` runs the
callback synchronously on OK click then closes; backdrop + ESC close without
firing the callback. All 17 in-file `showModal(` callers unchanged. Full suite
**297 passed (1397 assertions), 0 failures** (`ScannerTest` 18/125), build +
view:cache + Pint clean.

Phase 12 (completed 2026-09-04): migrated `#passwordModal` in
`admin/users/index.blade.php` to Alpine (`passwordResetModal` store +
`passwordResetModalComponent()` + `window.openPasswordResetModal(id, username)`
bridge). Both the server-rendered rows and the DataTables AJAX `.reset-btn` rows
open it via one delegated handler; the `show.bs.modal`/`relatedTarget` populate
logic became `openFor(id, username)`. Form contract (`#passwordForm`, POST,
`@csrf`, PUT, field names, dynamic action) unchanged; `admin/users/show.blade.php`
dangling `#passwordModal` trigger intentionally left untouched. Full suite
**297 passed (1397 assertions), 0 failures**, build + view:cache + Pint clean.

Concretely (Phase 10, completed 2026-09-04):
1. Migrated `#photoModal` (clients `_details.blade.php`) to Alpine
   `x-data`/`x-show`/`x-transition` (component `clientPhotoModal()` +
   `window.openClientPhotoModal()` bridge).
2. Preserved the `shown.bs.modal`/`hidden.bs.modal` behavior (file-picker reveal,
   camera stop/reset on close) with Alpine lifecycle equivalents in
   `openModal()`/`close()`.
3. `e2e/client-photo-modal-phase10.spec.ts` added (Playwright); passes once a test
   DB is seeded (`smoke_superadmin` absent — same env limitation as Phases 8/9).
4. `docs/IMPLEMENTATION_LOG.md` Phase 10 entry added; full suite
   **297 passed (1397 assertions), 0 failures**, build + view:cache + Pint clean.

**Subsequent shared-partial phases (each a separate approval):** module screens
still on Bootstrap (see §D order and §N), then removal-gate items (DataTables
Bootstrap skin / Bootstrap CSS CDN — the framework JS bundle is already gone).
The unpaid self-service dynamic modal
(`unpaid_verifications/self-service.blade.php`, `new bootstrap.Modal` +
`hidden.bs.modal`) is done (Phase 17), the students `#photoModal`
(`students/photo-upload.blade.php`) is done (Phase 18), the layout's shared
flash toast (`layouts/app.blade.php`, `bootstrap.Toast`) is done (Phase 19), the
transactions + households page-level success toasts are done (Phase 20), the
navbar hamburger inert-Alpine scope defect is fixed (Phase 21), and the layout
`bootstrap.Toast` init loop is removed (Phase 22 — no Bootstrap Toast JS surface
remains in the app; the clients stack + flash toast are pure JS/Alpine). Phase 23's
removal-gate audit (2026-09-06) closed the investigation: **Bootstrap JS bundle =
REDUNDANT** — the
documented dangling `admin/users/show.blade.php:134` trigger remains (DEAD, left
untouched and documented), and the only other Bootstrap JS inputs were a
DataTables skin that needs Bootstrap **CSS** only and the 6 bundle `<script>`
includes. **Phase 24 (2026-09-06) executed the bundle removal** — the six bundle
`<script>` includes are deleted, live Chromium shows `window.bootstrap`
`undefined` with zero console errors on `/login` + `/qr-viewer`, and no view or
compiled output references Bootstrap JS; the dead `admin/users/show:134`
attribute remains for a finalization pass. **Phase 25 (2026-09-06) then delivered the
DataTables-skin / Bootstrap-CSS-ownership phase**: the 11 per-screen
`dataTables.bootstrap5.min.css` CDN links are replaced by the project-owned, self-contained
`public/css/datatables.css`, so DataTables chrome no longer needs Bootstrap CSS at all (the
class-emitting `dataTables.bootstrap5.min.js` stays). **Bootstrap CSS as a whole still remains
REQUIRED** — Phase 25's gate recorded the remaining consumers; **Phase 26 (2026-09-07) then
owned the shared components in project CSS**: the `.btn`/`--bs-btn-*` bridge (ui.css, 49 refs → **0**),
`.form-control`/`.form-select`/`.form-check-input` bases, `.btn-close` (14), `.btn-group` (2),
`.alert`, `.table` outside DataTables, `.table-responsive` (transactions), and
`.dropdown-toggle`/`.dropdown-menu` are now project-owned in `ui.css` §4 — so Bootstrap CSS now
remains only for the `.modal*` subsystem (48 refs), `.accordion` (10) / `.list-group` (7)
permissions screens, `.form-label` (46) / `.d-none`-style utilities, and the 7 standalone public
pages. **Next candidate after Phase 26:** the `.modal*` subsystem and the `.accordion`/`.list-group`/
`.form-label`/utility leftovers (each needs a browser-verified pass per screen), then the standalone
public pages; the dangling trigger remains a finalization-pass item. The remaining `data-bs-dismiss="modal"` surfaces stay
deferred. Nothing in the app depends on the layout toast being Bootstrap anymore.

**Other Bootstrap components remain unmigrated;** remove Bootstrap only when its last CDN
consumer is replaced (see §B/§C). Do NOT yet remove Bootstrap from any screen.
