# Phase 2D — Responsive Polish Pre-Implementation Inspection Report

- **Date:** 2026-08-28
- **Scope:** Phase 2D — **Responsive polish** per `docs/SESSION_HANDOFF.md`.
- **Mode:** Pre-implementation inspection **only**. No application code was modified.
- **Baseline (verified this session):** full suite **263 passed / 1227 assertions** (1 pre-existing risky `HouseholdTest`); `vendor/bin/pint --test` **passed**; production `main_system` **untouched** (`php artisan test` runs on `main_system_test`).

---

## 1. Scope clarification (label discrepancy — resolved)

The repo carries two Phase-2 numbering schemes. The **original** `docs/PHASE_2_PLAN.md` §6
recommended order and `docs/implementation/PHASE_2_PRE_IMPLEMENTATION_CONFIRMATION.md` §6
label "Phase 2D — Per-Module Filter Integration" and "Phase 2E — Responsive Polish".
During execution the numbering was **recompressed**: FilterChips was implemented once and
integrated across all 7 modules under a single **Phase 2C**, its tests under "Phase 2E
(2C test suite)", and the operating `docs/SESSION_HANDOFF.md` now defines:

> **Phase 2D — Responsive polish:** Panel breakpoints, touch targets, overflow.
> Next: Phase 2D (see `docs/SESSION_HANDOFF.md` §Current Milestone / §Before Next Session).

This inspection follows the **authoritative SESSION_HANDOFF** definition of Phase 2D
(Responsive polish). The per-module FilterChips integration is already complete and is
treated as prior work; any residual integration gaps are catalogued under Risks below.
**Recommendation:** the original plan's "Phase 2E/2F" labels should be reconciled with
the working "Phase 2D/2E" scheme to avoid future confusion (see Open Decisions).

---

## 2. Approved Phase 2D requirements (source of truth)

From `docs/PHASE_2_PLAN.md` §13 (Responsive Requirements) and §6.D:

| # | Requirement | Plan reference |
|---|---|---|
| R1 | **Desktop panel behavior** — DetailsPanel ≈ **480px**, main table stays visible beside the panel | §13 "Desktop" |
| R2 | **Tablet panel behavior** — DetailsPanel ≈ **50vw** | §13 "Tablet" |
| R3 | **Mobile panel behavior** — DetailsPanel **100% width** (drawer/bottom-sheet) | §13 "Mobile" |
| R4 | Preserve on the panel: **ESC close, backdrop close, focus trap, focus restore, keyboard access, scroll lock** | §13 "Mobile" list |
| R5 | **Touch targets** ≈ **≥44px** where practical | §13 |
| R6 | **Table overflow** — tables must remain usable on narrow screens via horizontal scroll or responsive treatment | §13 |
| R7 | **Filter popovers** usable on tablet and mobile | §13 |

Phase 2D additionally requires (per `docs/SESSION_HANDOFF.md`): panel breakpoints, touch
targets, overflow — and the plan's global constraint is a **UX modernization phase only**:
no business-logic rewrite, no backend/schema changes (`PHASE_2_PLAN.md` §5; Phase 2
confirmation §8: "No database schema changes", confirmed per prior phases). Anything that
would touch controllers/routes/schema is out of scope and must be re-scoped.

---

## 3. Current v2 state vs. requirements (gap analysis)

### 3.1 DetailsPanel breakpoints — R1/R2/R3/R4 (largely satisfied)

Definition of the panel CSS lives **inline in** `resources/views/partials/details-panel.blade.php:37-248` (scoped `<style>`); behavior in `resources/js/components/DetailsPanel.js`.

| Aspect | Requirement | Current implementation | Status |
|---|---|---|---|
| Desktop width | 480px | `.details-panel { width: var(--panel-w) }` = 480px; `max-width:100%` | ✅ Matches |
| Tablet width | ≈50vw | `@media (min-width:768px) and (max-width:1023px)` → `width:50vw` (details-panel.blade.php:87-89) | ✅ Matches |
| Mobile width | 100% | `<768px` → `left:0; right:0; width:auto; max-width:100vw` (blade:91-102) | ✅ Matches (full-width drawer) |
| Desktop backdrop hidden | table stays visible | `@media (min-width:1024px)` → `.details-backdrop { display:none }` (blade:82-84) | ✅ Matches |
| ESC close | required | `DetailsPanel.js:197-201` | ✅ |
| Backdrop close | required | `DetailsPanel.js:193-195`; backdrop shown on tablet/mobile, hidden on desktop | ✅ |
| Focus trap | required | `DetailsPanel.js:203-221` (Tab wrap) | ✅ |
| Focus restore | required | `DetailsPanel.js:96-98` (returns to last focus) | ✅ |
| Scroll lock | required | `DetailsPanel.js:47-61` (`.no-scroll`, body overflow hidden) | ✅ |
| A11y | required | `role="dialog"`, `aria-modal`, `aria-hidden`, `aria-labelledby` (blade:5) | ✅ |

**Gaps / notes for R1–R4:**
- **Mobile treatment is a full-width right-side drawer, not a bottom sheet.** The prototype
  (`prototype/css/style.css:1446`) also uses a full-width drawer for the details panel —
  so v2 matches the prototype here. `PROTOTYPE_SPEC.md` allows drawer *or* bottom sheet;
  confirm which is desired on mobile (Open Decision). The current drawer slides from the
  right at 100% width, which is acceptable and matches the prototype.
- **No backdrop visible at ≥1024px**: correct per prototype (`style.css:1202`); table stays
  fully interactive.
- The DetailsPanel contract itself must **not** be redesigned (`PHASE_2_PLAN.md` §14; the
  `data-panel-*` + `.details-panel.open` contract is the Phase 1 stable contract). Phase 2D
  may only *light-polish*.

### 3.2 Filter popovers — R7 (satisfied; desktop/mobile verified in Phase 2C remediation)

`resources/css/app.css`:
- Desktop: `.filter-multi-menu { position:absolute; width:300px; max-width:90vw; z-index:60 }` (app.css:608-624).
- Mobile `<767.98px`: fixed bottom sheet `left:0; right:0; bottom:0; width:100%; max-width:100vw; max-height:80vh` (app.css:761-772); chip padding grows to `0.5rem 0.875rem` (app.css:773-775).
- Real-browser verification (Phase 2C remediation, 2026-08-27) confirmed the popover opens
  `insideViewport:true` at desktop 1280×900 and mobile 390×844; mobile renders `position:fixed`.

**Gaps / notes for R7:**
- Prototype touch bump for mobile popover is **more aggressive** than v2: prototype makes
  the popover a floating sheet with `left:12px; right:12px; bottom:12px; max-height:70vh`,
  gives `.filter-check { min-height:40px }`, checkbox 18px, `.filter-chip-x` 22px.
  v2's bottom sheet is edge-to-edge (0/0) with 80vh and no per-option min-height bump
  (`app.css:761-775`). Decide whether v2 should adopt the prototype's floating-sheet +
  min-height-40px option rows (Open Decision — touch target concern feeds R5 below).
- v2 `filter-multi-search`/`filter-date-input` grow tap targets but remain small (see §3.4).

### 3.3 Table overflow — R6 (partially satisfied; coverage is uneven)

Reference prototype: `.table-scroll { overflow-x:auto; -webkit-overflow-scrolling:touch }`
(`prototype/css/style.css:784`) + `<768px` `min-width:620px` floor (`style.css:1443`); sticky
`thead` (`style.css:796`). v1 baseline: `.table-responsive` wrapper on 16 screens + DataTables
`scrollX:true` on 4 (`scanned_payouts*`, `unpaid_verifications`); the browser-level width
constraint is that **no page should introduce unnecessary horizontal page scroll**.

**Per-screen current state (verified):**

| Screen | Wrapper | `scrollX` | `responsive:true` | Table `min-width` floor | Readiness |
|---|---|---|---|---|---|
| clients/index.blade.php:131 | `overflow-x-auto` | no | no | **none** | Wrapper only |
| households/index.blade.php:109 | `overflow-x-auto` | no | no | none | Wrapper only |
| transactions/index.blade.php:125 | `.table-responsive` | no | no | none | Wrapper only (>20 cols, worst squeeze) |
| scholarship_reports/index.blade.php:79 | `overflow-x-auto` | **yes** (:166) | no | none | Double layer |
| payouts/attendance.blade.php:96 | `overflow-x-auto` | **yes** (:192) | no | none | Double layer |
| unpaid_verifications/index.blade.php:91 | `overflow-x-auto` | **yes** (:191) | no | none | Double layer |
| admin/audit_logs/index.blade.php:97 | `overflow-x-auto` | no | no | none | **broken page JS** (see §5) |
| scholars/index.blade.php:135,159,231,281 | 4×`overflow-x-auto` | **reports only** (:500) | no | none | scholars/GIP/logs wrapper-only |
| duplicates/index.blade.php:130 | `overflow-x-auto` | no (`autoWidth` at default true) | no | none | Wrapper only, 8 cols |
| admin/users/index.blade.php:86 | `overflow-x-auto` | no | no | none | Wrapper only |

**Findings for R6:**
1. **Every screen has always-on horizontal-overflow handling** (wrapper and/or `scrollX`),
   so nothing overflows the page — but the behavior is **inconsistent**:
   - Only 4 of ~14 tables use DataTables `scrollX:true` (transactions, the widest, does **not**).
   - **No table enforces a `min-width` floor.** The `min-w-[42rem]`/`min-w-[36rem]` pattern
     exists only on non-list tables (`dashboard.blade.php:197`, `clients/_details.blade.php:103,126`).
     Without a `min-width`, narrow screens reflow/compress columns awkwardly rather than
     scrolling, and `white-space:nowrap` th (e.g. clients :16-22) can clip.
   - **Double scrolling** where `overflow-x-auto` wrapper + `scrollX:true` both apply
     (scholarship_reports, payouts, unpaid, scholars-tabs).
2. **No screen uses DataTables `responsive:true`** and the DataTables **Responsive extension is
   never loaded** — v1 also did not use it (the one v1 `responsive:true` in `preview_duplicates.php`
   was inert because the extension wasn't loaded). So column hide/reflow is absent everywhere.
3. **`html { overflow-x:hidden }`** (`app.css:282`) silently clips any future wrapperless wide
   table — regression trap, but currently safe because every screen wraps.
4. **Transactions (21 columns + inline-edit inputs) is the highest-risk screen** for mobile
   squeeze; it relies on `.table-responsive` alone with no `scrollX` and no `min-width`.

**Decision needed (R6):** adopt a consistent treatment —
(a) add a `min-width` floor to list tables + rely on the existing wrappers (prototype-style),
(b) enable `scrollX:true` on the wide tables (transactions, clients, etc.) + `.columns().adjust()`
on panel open/sidebar toggle (v1 `sidebar.js` did exactly this for `#transactionsTable`), or
(c) load the DataTables Responsive extension (column hide/reflow — largest behavior change,
needs per-column priority config, and is the furthest from v1). This is a concrete Open Decision.

### 3.4 Touch targets — R5 (largest gap)

Root font 14px ⇒ 1rem = 14px. Computed control heights (vs. **≥44px** guideline):

| Element | Height | Ref |
|---|---|---|
| `.filter-group-toggle` | **≈32px** | app.css:556-569 |
| `.filter-done-btn` | **≈32px** | app.css:736-745 |
| `.filter-clear-all` | **≈25px** | app.css:595-605 |
| `.filter-clear-cat` | **≈20px** | app.css:651-661 |
| `.filter-check` row | **≈30px** | app.css:683-694 |
| `.filter-chip` desktop / mobile | **≈28 / ≈32px** | app.css:506-508, 533-547, 773-775 |
| `.filter-chip-remove` | **17.5×17.5px** | app.css:510-512, 539-547 |
| `.filter-multi-search` | **≈28px** | app.css:663-672 |
| `.filter-date-input` | **≈29-30px** | app.css:718-726 |
| `.details-close` | **34×34px** | details-panel.blade.php:110-127 |
| Shared `.btn-*` | **≈32px** | app.css:367-373 |
| Sidebar `.sidebar-link` | **≈37px** | app.css:301-309 |
| Navbar hamburger | **≈36px** | navbar.blade.php:26,34 |
| Navbar notification / user / search | **≈34 / ≈35 / ≈36px** | navbar.blade.php:50-59; global-search.blade.php:10 |
| DataTables length/filter inputs | **≈25px** | scoped CSS each screen |
| In-row action buttons | **≈20.5px** | e.g. clients:48-51 |
| Bootstrap `.page-link` paging | **≈31.5px** | Bootstrap default |
| Clickable table row | **≈24px** | everywhere (row-click → DetailsPanel) |

Prototype reference targets (mobile `<768px`): `.btn` min-height **42px**, `.btn-sm`
min-height **38px**, `.filter-chip` pad `8px 14px`, `.pagination` 36px, `.details-close`
36px, `.filter-multi-btn` 38px, `.filter-check` min-height **40px**, checkbox 18px,
`.filter-chip-x` 22px, `.table-search input` font-size 16px (prevents iOS zoom).

**Findings for R5:**
1. **Every interactive element is below 44px.** The largest (scholar tabs ≈43px, sidebar
   links ≈37px) are just under; the smallest (chip-remove 17.5px, clear-cat 20px, in-row
   actions ≈20.5px, BS close ≈19px) are far short.
2. This matches the **prototype's own intent** (which does NOT target 44px universally —
   it targets 36–42px on mobile). The plan says "at least approximately 44px **where
   practical**". So exact 44px is not mandatory; the prototype-compliant mobile tier
   (min-height 38–42px for primary controls, 36px for icons, 40px filter rows) is the
   design-accurate target.
3. Phase 2D should apply the prototype's **mobile touch-target tier**, scoped to `<768px`,
   rather than a blanket global 44px bump that would inflate the entire desktop UI.
4. `details-actions .btn { flex:1 1 calc(33.33% - 8px); min-width:96px }` (details-panel.blade.php:142-145)
   already gives action buttons a wide horizontal target; the 44px height remains the question.

### 3.5 Shell responsive / breakpoints — supporting R6/R7 context

- Shell breakpoint is **Bootstrap `lg` = 992px** (offcanvas sidebar `<992px`, fixed column `≥992px`); sidebar drawer width **280px** vs fixed width **260px** (inconsistent widths between states).
- DetailsPanel mobile cutoff is **768px**; FilterChips bottom-sheet cutoff is **767.98px**;
  prototype details cutoff is also **768px**. So the panel/filter 768px tier sits **below** the
  shell's 992px offcanvas tier — meaning on an 800–991px (tablet-landscape) viewport the sidebar
  is an off-canvas drawer while the panel is still in its "tablet 50vw" tier. This layering is
  intentional per prototype, but the **767 vs 767.98px** mismatch between DetailsPanel and
  FilterChips is an inconsistency to reconcile (Open Decision).
- Topbar: global search `hidden sm:block` (hidden <576px); breadcrumb hidden `<sm` — both
  hidden on the smallest phones (acceptable, but worth confirming).
- `viewport` meta present (`layouts/app.blade.php:5`).

---

## 4. Confirmed defects discovered during inspection (do NOT fix yet — inspection mode)

1. **Audit Logs page JS SyntaxError — DataTables never initializes.**
   `resources/views/admin/audit_logs/index.blade.php:160-170` contains a **duplicated**
   `return json.data; }` block after the `dataSrc` function closes. The committed baseline
   (`git show HEAD:...`) has only **one** `return json.data;`; the duplicate was introduced in
   the **working-tree Batch G migration** of this screen (uncommitted diff: 178+/92-). Net
   effect: the `$(document).ready` handler throws, so `#logsTable` is not upgraded to
   DataTables and the client-side FilterChips wiring on that screen cannot run. **This is a
   functional regression, not a responsive issue — but it directly blocks Phase 2D browser
   verification of the Audit Logs module and should be fixed (or at least acknowledged) when
   Phase 2D implementation begins.**
2. **`duplicates/index.blade.php`** DataTables init omits `autoWidth:false` (defaults to
   `true`) — inconsistent with every other list screen; can cause container mis-measure.
3. Sidebar drawer (280px) vs fixed column (260px) width mismatch across breakpoints.

---

## 5. Dependencies

- **DetailsPanel.js / `data-panel-*` contract** — must remain stable (`PHASE_2_PLAN.md` §14).
  Any Phase 2D change touching panel CSS lives in `partials/details-panel.blade.php` scoped
  `<style>`, not in the `.open`/FC contract.
- **FilterChips asset delivery** — `public/js/components/FilterChips.js` is copied 1:1 from
  source by the `copySharedComponents()` Vite plugin (verified 23,431 bytes exact match).
  Frontend-only CSS changes to `app.css` do not require the JS copy to change; a JS-behavior
  change would require a rebuild + copy.
- **`@source` allowlist + coexistence rule** — any **new Tailwind utility classes** added in a
  migrated view must not use Bootstrap-colliding spacing steps (3–5) and must respect the
  explicit-allowlist scanning contract (`app.css:18-51`). Longhand CSS in `app.css` is safest.
- **No schema / backend changes** — Phase 2D is CSS/JS/view-only by scope.
- **DataTables** v1.13.6 + Bootstrap5 theme (no Responsive extension) — sets the feasible
  table-overflow options.

---

## 6. Risks

- **Audit Logs regression (High)** — the §4.1 SyntaxError means that module's table + FilterChips
  are already broken in the working tree; must be fixed and browser-verified in Phase 2D.
- **Table-overflow inconsistency (Medium)** — uneven `scrollX`/`min-width`/wrapper coverage;
  transactions (21 cols) and clients are the most exposed on mobile. Without a decision
  (§3.3) polish could be piecemeal.
- **Touch-target inflation (Medium)** — a blanket 44px bump would bloat the desktop UI and
  fight the prototype's design-accurate 36–42px mobile tier. Scope to mobile.
- **Breakpoint divergence (Low/Medium)** — 767 vs 767.98px panel/filter mismatch and the
  shell's separate 992px tier can cause edge-case overlap; low likelihood of user-visible
  breakage but worth one reconciliation.
- **`html { overflow-x:hidden }` (Low)** — future wrapperless wide content would be silently
  clipped.
- **v1 parity (guardrail)** — do not regress v1 feed/wiring semantics; DataTables server-side
  contract, per-row actions, `.columns().adjust()` pattern (v1 `sidebar.js`) must stay intact.

---

## 7. Decisions required before implementation

| # | Decision | Options | Recommendation |
|---|---|---|---|
| D1 | **Table-overflow strategy** (§3.3) | (a) `min-width` floors + wrappers; (b) `scrollX:true` + `.columns().adjust()` on wide tables; (c) load DataTables Responsive extension (column reflow) | (b) — closest to v1's proven `#transactionsTable` pattern + prototype scroll-in-card intent; keeps column priority untouched |
| D2 | **Touch-target tier** (§3.4) | (a) prototype mobile tier only (<768px: primary ≥38–42px, icons 36px, filter rows 40px); (b) strict 44px everywhere; (c) no bump | (a) — design-accurate, avoids desktop inflation, satisfies "≈44px where practical" on touch |
| D3 | **Audit Logs defect** (§4.1) | (a) fix the SyntaxError as the first Phase 2D task; (b) fix separately/tracked | (a) — blocks verification of a Phase 2D module |
| D4 | **Mobile DetailsPanel form** (§3.1) | right-side full-width drawer (current, matches prototype) vs bottom sheet | Keep current drawer (matches prototype `style.css:1446`) unless owner prefers a sheet |
| D5 | **Breakpoint reconciliation** (§3.5) | align panel/filter to a single 768px constant; decide interaction with shell's 992px tier | Align to 767.98/768 consistently; leave shell tier as-is |
| D6 | **Phase numbering** (§1) | reconcile SESSION_HANDOFF "2D=responsive / 2E=tests" labels with original plan "2D=per-module / 2E=responsive / 2F=tests" | Update SESSION_HANDOFF/plan to a single consistent scheme |

---

## 8. Recommended Phase 2D implementation order (for approval)

1. **Fix the Audit Logs `dataSrc` SyntaxError** (D3) — restores that module before polish.
2. **Reconcile breakpoints** (D5) — single 768px panel/filter constant, documented.
3. **Table-overflow pass** (D1) — apply chosen strategy to wide tables (esp. transactions,
   clients); ensure `.columns().adjust()` on panel open/sidebar toggle where scrollX is used.
4. **Touch-target mobile tier** (D2) — scope to `<768px`, prototype-compliant sizes for
   FilterChips controls, DetailsPanel close, in-row actions, paging.
5. **Filter-popover mobile polish** (R7) — adopt prototype floating-sheet + option-row
   min-height 40px if approved.
6. **Browser verification** at prototype widths (1440/1280/1024/768/576/430/390/375/320)
   incl. the Audit Logs screen; run full suite + Pint.

---

## 9. Verification performed this session

- Read `docs/PHASE_2_PLAN.md` §6/§13, `docs/implementation/PHASE_2_PRE_IMPLEMENTATION_CONFIRMATION.md` §6,
  `docs/PHASE_2C_INSPECTION_REPORT.md`, `docs/SESSION_HANDOFF.md`.
- Read `resources/css/app.css` (full), `resources/js/components/DetailsPanel.js`,
  `resources/views/partials/details-panel.blade.php` (full), and audit_logs/index.blade.php source.
- Dispatched two read-only explorers: (A) per-screen table-overflow + touch-target audit across
  all 10 list screens; (B) prototype responsive CSS + v1 behavior inventory. Key line references
  above are drawn from those, spot-checked directly.
- Verified the Audit Logs `dataSrc` duplicate is a **working-tree regression** (`git show HEAD` has
  1 occurrence; working tree has a duplicated block).
- Verified FilterChips.js source == deployed byte-for-byte (23,431 B).
- **Test baseline:** 263 passed / 1227 assertions, 1 pre-existing risky `HouseholdTest`.
- **Pint:** `vendor/bin/pint --test` → passed.
- **Database:** production `main_system` untouched (tests run on `main_system_test`).

No application code, docs other than this report, or database were modified.
