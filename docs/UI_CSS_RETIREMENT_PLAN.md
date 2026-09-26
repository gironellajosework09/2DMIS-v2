# ui.css Retirement Plan

> **Status:** M1 + M2 + M3 **LANDED** (2026-09-07) — tokens ported into `app.css`
> `@theme static`; Reboot/type ownership ported into `app.css` `@layer base`
> (Option B, Preflight OFF); global `:focus-visible` + `prefers-reduced-motion` ported
> into `app.css` `@layer base`. **M4.1 LANDED (2026-09-07)** — utility drop for
> `d-flex` (9/10), `fw-bold` (5/5) migrated to Tailwind `flex`/`font-bold`; `mx-auto`
> already string-identical so no change required; `align-middle` (12), `text-muted` (2),
> `bg-white` (genuine usages) **retained with justification** (value/`!important`/
> excluded-family coupling), documented in §M4. **M4.2 LANDED (2026-09-07) — forms
> family:** `.form-label` (46 consumers) migrated to Tailwind `mb-2` (exact `.5rem`
> parity); `.form-control` (189), `.form-select` (60), `.form-check-input` (37),
> `.input-group` (5), `-sm` variants (19) **retained with justification** (no Tailwind
> twin reproduces the Bootstrap control/focus/chevron/native-checkbox metrics under
> Preflight-OFF + still-linked ui.css; `.input-group` tied to excluded `.btn` seam),
> documented in §M4. **M4.3 LANDED (2026-09-08) — layout/grid family:** `.row` (20),
> `.col-sm-*` (48), `.col-md-*` (54), `.g-2` (4) — **all RETAINED after full
> inspection** (inventory + live computed geometry + JS contract audit). The grid is a
> genuine Bootstrap **gutter-compensation** model (`.row` negative `-3.5px`/`-10.5px`
> margins, `.row>*` child padding `3.5px`/`10.5px`, `margin-top` compensation, `flex-shrink:0`
> width-basis, `gap:normal` — measured live on `/grantee-update`), which Tailwind `gap-*`/
> `w-*` cannot reproduce (outer-edge compensation, shrink/wrap semantics) without the bespoke
> `-mx-*`+`*:px-*` construction §7 forbids; `.col-sm-*` also sit in dynamic JS `<dl class="row">`
> DataTables modals (excluded family). M4.3 → **RETAIN ALL** (valid per §19); no view/CSS/JS
> changed; build/PHPUnit 301/1419/0/Pint green; 0 console/overflow. **M4.4 LANDED
> (2026-09-08) — buttons family:** `.btn` base (25), `.btn-sm` (4), `.btn-primary` (1),
> `.btn-danger` (1), `.btn-outline-primary` (2), project variants `.btn-gold` (34)/`.btn-navy`
> (47)/`.btn-red` (10)/`.btn-subtle` (81)/`.btn-outline` (3)/`.btn-outline-red` (3) — **all
> RETAINED** (valid per §19). Live-cascade probe proved `ui.css` `.btn` (unlayered, linked
> after app.css, same 0,1,0 specificity) **wins the metric conflicts** on every
> `btn <variant>` composite today (ghost Bootstrap metrics: 5.25/10.5px padding, 5.25px
> radius, 14px/400, transparent bg) — removing `.btn` flips display/padding/font/color/bg/
> border/radius/transition in all states (12+ props), a §1-forbidden redesign; the pure
> Bootstrap variants have no canonical Tailwind twin (navy-light hover, focus ring,
> `.btn-sm` metrics) and live in excluded JS DataTables/grantee-QR templates. Zero
> view/CSS/JS changes; build/PHPUnit 301/1419/0/Pint green; 0 console/overflow.
> **M4.5 LANDED (2026-09-08) — alerts/feedback family:** `.alert` (17 usages: 2
> server-rendered — the `layouts/app` `.alert-danger.alert-dismissible` session-error list
> (Alpine `x-show` + `.btn-close`, excluded) and the `.alert-success d-none` `#successBox`
> state hook — plus 15 in Blade-embedded **JS template literals** injecting `alert-danger` ×11,
> `alert-success` ×3, `alert-warning` ×1 into container divs), `.alert-dismissible`, and the
> ui.css-owned variants `-success/-danger/-warning/-info` (stock Bootstrap 5.3 emphasis
> palette) — **all RETAINED** (valid per §19). `.alert-primary/secondary/light/dark/heading/
> link` have **0 consumers + 0 ui.css rules**. No canonical Tailwind alert exists (`.ui-notice`
> is a redesigned gold bar, not a twin); the Bootstrap hex-byte palettes + exact alert metrics
> would need arbitrary-value stacks §10 forbids; `.alert` is a **live JS selector contract**
> (`msg.querySelector('.alert')` on the QR-error path); `app.css` has no `.alert` rules at all;
> the 20 `alert(...)` strings are `window.alert()` dialogs, not the class. Live in-flow probe
> on `/grantee-update` confirmed ui.css parity @375/768/1280 (14px/14px/5.25px, dismissible
> 42px right-pad, `.btn-close` z-2 at top-right, 0 console/overflow). Zero view/CSS/JS changes;
> build/PHPUnit 301/1419/0/Pint green. **M4.6 LANDED (2026-09-08) — remaining component
> families (final M4 sub-step):** tables (`.table` ×21 [15 DataTables + 6 plain], `.table-sm`
> ×12, `.table-responsive` ×1, `.align-middle` ×12), dropdowns/btn-groups (3 Alpine
> `.dropdown-menu`+`.dropdown-item`+`.dropdown-toggle` menus on a `.show` state contract),
> `.btn-close` (15 — SVG glyph, content-box 1em+.25em, opacity/.75/focus-ring, white variant,
> inside excluded parents), accordion (1 Alpine `.accordion*` w/ `.collapsed` class-binding +
> chevron swap), list-group (5 containers, items injected via JS `classList.add` contract),
> `.d-none` (74 + JS hooks — behavioral), remaining utilities — **all RETAINED**. No canonical
> app.css twin exists for any family; a faithful Tailwind map = §10-forbidden arbitrary-byte
> stacks; `.show`/`.collapsed`/`.d-none`/`list-group-item` are live JS/Alpine contracts; DataTables
> bootstrap5 integration consumes `.table`; utilities diverge in value from Tailwind
> (`.mt-4`=24px vs 16px, `.text-danger`≠`--color-red`, `!important`). Live-tab probe
> @375/768/1280 matched every declaration byte-for-byte (incl. `.show` toggle, 21×21 close box),
> 0 console/overflow. Zero view/CSS/JS changes; build/PHPUnit 301/1419/0/Pint green; DataTables
> skin/CDN intact. **M4 (Bootstrap-parity family pass) is COMPLETE. M5.1 LANDED (2026-09-08,
> AUDIT ONLY — behavioral-contract & dependency audit; roadmap refined; gate: ui.css still
> NOT SAFE to delete). M5.2 LANDED (2026-09-08 — DataTables B2 focus seam is now OWNED by
> `datatables.css`; phantom `.w-100` eliminated; `app.css` + `ui.css` untouched/byte-identical;
> 0 computed-style drift; build/Pint/PHPUnit 301/1419/0 green). **M5.3 LANDED (2026-09-08 —
> `.d-none` behavioral migration: 72 live references → native Tailwind `hidden`, ui.css
> `.d-none` rule REMOVED (no owner); cascade-equivalent (0 dedicated consumer selectors, no
> competing `display`); build byte-identical, 0 drift harness, suite 301/1419/0).
> **M5.4 LANDED (2026-09-08 — dropdown `.show` behavioral migration: all 3 Alpine dropdowns
> bind the canonical app-owned `dropdown-open` state class (`app.css`
> `.dropdown-menu.dropdown-open { display:block }`), ui.css `.dropdown-menu.show` rule REMOVED —
> dropdown state doesn't load in ui.css anymore; toast `.show` untouched; cascade-safe by
> specificity (0,2,0 vs 0,1,0 base); real-Alpine lifecycle harness 0 drift @375/768/1280; suite
> 301/1419/0; build `app-kffPtn-M.css`). **M5.5 LANDED (2026-09-08 — accordion `.collapsed`
> behavioral migration: the single live accordion (clients/_gip) binds the canonical app-owned
> `accordion-open` state class (`app.css` `.accordion .accordion-button.accordion-open` family + the
> `:not(.accordion-open)` closed-corner rule, `.accordion`-scoped to beat the later-loading
> `.accordion-button:focus` tie), ui.css `.collapsed` / `:not(.collapsed)` state rules REMOVED —
> no collapsed-state CSS loads in ui.css anymore; `x-show` + `x-collapse` panel visibility untouched;
> real-Alpine + `@alpinejs/collapse` lifecycle harness 0 drift @375/768/1280; suite 301/1419/0;
> build `app-DjDvJDSm.css`). **M5.6 LANDED (2026-09-08 — `.btn-close` ownership & behavioral
> migration: the `.btn-close`/`.btn-close-white` SELF contract (box model, glyph, opacity,
> hover/focus/disabled states) moved from ui.css §4.3 to `app.css` as rules byte-identical to the
> retired ones; the class literal `.btn-close` is RETAINED (no JS selector depends on it — both
> JS-created toasts wire via `button[aria-label="Close"]`, DetailsPanel via `#detailsClose`, so all
> 16 consumers incl. the 2 dynamically-generated buttons keep byte-identical strings); `.alert-dismissible .btn-close`
> child-positioning stays in ui.css §4.5 with its `.alert` parent (alerts remain ui.css-owned);
> plain top-level rules chosen over the earlier-planned `@utility` bridge (unlayered = same conflict
> resolution vs unlayered ui.css siblings as before; `@utility` would demote to the utilities layer);
> real-Alpine-style Playwright parity harness incl. dynamic toast creation/repeated-creation,
> hover, focus, click, Enter/Space @375/768/1280 — 0 drift, 0 console/page errors, no overflow;
> suite 301/1419/0; build `app-BZH684Eh.css`). **M5.7 LANDED (2026-09-08 — final utility /
> grid / text dependency re-scan: exhaustive value-level audit of the remaining §4.x utility,
> grid and text families against the compiled Tailwind bundle; MIGRATED 11 rules whose Tailwind
> twin is value-exact and cascade-isolated — `.gap-1/.gap-2`, `.flex-wrap`, `.top-0/.bottom-0`,
> `.overflow-hidden/-visible/-x-auto/-y-auto`, `.ms-auto`, `.mx-auto` (zero Blade/JS edits; the
> identical class strings already compile the twins); RETAINED everything else with forensic
> value/specificity/`!important`/layer reasons — spacing ladder 3–5, `.rounded` (.375 vs .25rem),
> `.border` (Tailwind twin carries no #dee2e6 color), Bootstrap-only text colors, `.align-middle`
> (would flip `.table{vertical-align:top}` cell-centering via the layered twin), `.bg-transparent`
> (flips `.list-group-item` bg to #fff), `bg-white`, `d-*`, `position-*`, `fw-*`/`fs-4`/`.small`,
> grid `.row/.col-*/.g-*` (M4.3 + §16), tables/`.align-middle` (M4.6 + datatables.css), DEAD
> `.mt-auto/.mb-auto/.me-auto/.flex-nowrap/.overflow-auto/…` (classified for M6); Playwright
> parity over `/login`, `/qr-viewer`, `/grantee-update`, `/unpaid-verification` + rendered
> auth-surface contexts @375/768/1280 — 0 drift, 0 errors, no overflow (interceptor proven live
> via a poisoned-BEFORE probe); suite 301/1419/0; build byte-identical `app-BZH684Eh.css`;
> ui.css prod selectors −11; M6 deletion gate still NOT READY). M6 PENDING.**
> **Verdict of the audit:** `public/css/ui.css` is **NOT SAFE to delete** while ~46 of 68
> pages still depend on it. This document is the migration roadmap that makes deletion
> safe.
>
> **Relation to other docs:** this is the detailed execution map behind
> `docs/TAILWIND_MIGRATION_EXECUTION_PLAN.md` §J (ui.css Retirement / Roles) and §I
> (Preflight strategy). It supersedes no implemented decision; Section 8 below maps
> every audit finding to the §J/§I/§E clauses it refines.

---

## 1. Purpose

`public/css/ui.css` (~1492 lines) is the shared UI foundation. After Phases 26–28 the
Bootstrap **CDN** is gone, but `ui.css` itself is now the *only* provider of:

1. the `--ui-*` design-token vocabulary consumed by the **built** `app.css` output,
   `public/css/datatables.css`, and several Blade views;
2. the Reboot + `_type.scss` element-parity layer (global `box-sizing`, body/heading/
   link defaults) — Tailwind Preflight is intentionally NOT enabled (§I);
3. the global `:focus-visible` gold outline and `prefers-reduced-motion` guard;
4. the project-owned Bootstrap-parity component/utility vocabulary still used by
   ~46 screens that have not been migrated to the Tailwind `.field-*`/`.btn-navy*`
   vocabulary.

**This plan deletes `ui.css` — but only after each of the six migrations below is
landed and its gate verified.** The terminal deletion is a single reviewed removal
commit (Section 7).

---

## 2. Current dependency surface (audit evidence, 2026-09-07)

| # | Consumer | Kind | Lives in | Notes |
|---|---|---|---|---|
| D1 | `layouts/app.blade.php` + **39** views extending it | direct `<link>` | L13 | layout host; inherited by all authenticated screens |
| D2 | `auth/login`, `qr/viewer`, `students/photo-upload`, `students/update-photo`, `students/verify`, `grantee_update/self-service`, `unpaid_verifications/self-service` | direct `<link>` | 7 standalone pages | each also loads `@vite(['resources/css/app.css'])` first |
| D3 | `public/css/datatables.css` | stylesheet token refs | L532 `--ui-navy`, L552 `--ui-navy-hover`, L562/572 `--ui-focus-ring`, L567/568/571 `--ui-navy` | colors pagination on **11** DataTables screens |
| D4 | `dashboard.blade.php` | `var(--ui-*)` in class attr | 29 refs / 17 unique tokens | arbitrary-value utilities emitted into the build (T1 installed them via the `@source` allowlist) |
| D5 | `partials/global-search.blade.php` | inline `<style>` + JS strings | `var(--ui-card,#fff)` etc. | mostly **has fallbacks**; a few no-fallback refs remain |
| D6 | `scanners/scan.blade.php` | inline `<style>` | L38 `font-size: var(--ui-text-sm)` | **no fallback** |
| D7 | `clients/index.blade.php` | inline `<style>` | L47/L135 `outline: 2px solid var(--ui-focus-ring)` | **no fallback** |
| D8 | Bootstrap-parity classes | Blade markup | §3 table | forms/buttons/alerts/tables/dropdowns/grid/spacing utilities |

Independent (safe) check: `details-panel.blade.php` defines its own `:root { --panel-w: 480px }`
— self-contained, NOT a `ui.css` dependency.

**Token presence in the build** (`public/build/assets/app-D3mz-Mcl.css`, 57.6 kB):
- `var(--ui-*)` **references**: 39 (arbitrary-value utilities from `dashboard`, plus
  `--ui-accent` metric-card accent hooks).
- `--ui-navy` / `--ui-focus-ring` / `--ui-navy-hover`: **0** definitions in the build.
- `box-sizing`: **0** | `prefers-reduced-motion`: **0** | global `:focus-visible`: **0**
  (only component-scoped rules: `.icon-btn`, `.filter-*`, `.btn-navy:focus-visible`).
- Bootstrap-parity names **absent** from the build: `.d-none`, `.d-flex`, `.row`,
  `.col-*`, `.img-thumbnail`, `.text-muted`, `.fw-bold`, `.btn-close`, `.form-control`,
  `.form-select`, `.form-check-input`, `.input-group`, `.list-group`, `.accordion`,
  `.alert`, `.btn-primary`, `.dropdown-menu`, `.table-responsive`, `.form-label`,
  `.ui-page-header`.
- Bootstrap-named **twins with Tailwind values already in build** (value drift hazard,
  §6.4): `.mt-5` (Tailwind 1.25rem vs Bootstrap 3rem), `.py-3` (0.75rem vs 1rem),
  `.mx-auto`, `.ms-auto`, `.align-middle`, `.rounded-pill`, `.px-1`, `.bg-white`.

---

## 3. Bootstrap-parity usage that remains (deletion blockers — §4.9 in ui.css)

Counts are files under `resources/views` (grep evidence, 2026-09-07). `app.css`/Tailwind
provides **no** replacement for any of these names today.

| Class family | Files | Representative screens |
|---|---|---|
| `.btn` base + `.btn-sm` (with `.btn-gold` etc.) | 12 | users/show, clients/_details, households/show, payouts/show+attendance, scholars/_form, transactions/show, audit_logs/show, grantee self-service |
| `.btn-primary` / `.btn-outline-primary` / `.btn-danger` | 3 | payouts/attendance (primary+danger), grantee self-service (outline-primary), grantee _self_update_tab |
| `.form-control(-sm)` / `.form-select(-sm)` / `.form-check(-input/-label)` | 31 | transaction forms, scholars, permissions matrices, scanner, self-service flows |
| `.form-label` | 4 | forms on schema-bound screens |
| `.input-group` / `.input-group-text` | 1+ | scanner / grouped controls |
| `.btn-close` | 10 | toasts, alerts, modals, mobile sidebar close |
| `.btn-group` | 2 | clients + transactions export dropdowns |
| `.alert*` + `.alert-dismissible` | 5+ | error/`$errors` blocks, flash messages, grantee/self-service |
| `.table` / `.table-sm` / `.align-middle` / `.table-responsive` | 9 / 12 / 1 | sessions/online, households/show, permission matrices (plain tables, NOT DataTables) |
| `.dropdown-menu(-end)` / `.dropdown-item` / `.dropdown-toggle` | 3 | navbar user menu, clients/transactions export menus |
| `.page-link` / `.page-item.active` | 10 | DataTables screens (chrome scoped inside `.dataTables_wrapper` via datatables.css) |
| `.accordion*` | 5 | clients/_gip |
| `.list-group*` | 7 | qr/viewer suggestions, scanners/scan, scholars/_form, transactions create+edit, students/update-photo |
| `.d-none` | 10 | **JS/UX show-hide contract** (sections toggled by Alpine/vanilla listeners) |
| `.d-flex` | 6 | inline flex rows on unmigrated screens |
| `.row` / `.col-sm-*` / `.col-md-*` / `.col-lg-*` / `.g-*` | 23 (`.row`), 4 / 2 (col-md/col-sm) | grid — grantee, unpaid, qr, attendance, forms |
| `.mx-auto` | 11 | centered blocks |
| `.img-thumbnail` | 1 | grantee photo frames |
| `.text-muted` / `.fw-bold` / `.bg-white` | 4 / n / 16 | utility text + card surfaces |
| `.mt-*` / `.mb-*` / `.py-*` / `.px-*` / `.gap-*` spacing ladder | throughout | **coexistence-rule hazard** (steps 3–5 collide with Tailwind) |

**Not a blocker (already mirrored in app.css):** `.status-badge*`, `.data-card*`,
`.metric-card*`, `.ui-notice`, `.ui-empty`, `.ui-micro-label`, `.ui-skip-link`,
plus the new-name Tailwind vocabulary (`.btn-navy/.btn-gold/.btn-red/.btn-subtle/
.btn-outline-red/.btn-outline`, `.field-*`, `.filter-*`, `.icon-btn`).

---

## 4. The six migrations (each independently reviewed, landed, and verified)

### M1 — Port every `--ui-*` token into `app.css` `@theme static` (or remap consumers) — **LANDED 2026-09-07**

**Why first:** tokens are the single most infectious dependency — one definition can
feed `datatables.css`, the built `app.css`, and three views.

**Evidence:** `ui.css:root` defines ~40 tokens (`.--ui-*` block, lines 23–~140).
The build emits 39 `var(--ui-*)` references but defines **no** `--ui-navy`,
`--ui-focus-ring`, or `--ui-navy-hover`. Consumers:
- `public/css/datatables.css` L532/552/562/567/568/571/572 (pagination + focus gold);
- `dashboard.blade.php` — 29 refs across 17 unique tokens (`--ui-bg-alt`, `--ui-border`,
  `--ui-border-light`, `--ui-card`, `--ui-ease`, `--ui-gold`, `--ui-gold-dim`,
  `--ui-radius`(+lg/sm), `--ui-red`, `--ui-shadow-*`, `--ui-teal`, `--ui-text-*`);
- `clients/index.blade.php` L47/L135 — `--ui-focus-ring`;
- `scanners/scan.blade.php` L38 — `--ui-text-sm`;
- `partials/global-search.blade.php` — `--ui-card`, `--ui-border-light`, `--ui-navy`,
  `--ui-text-muted` (fallbacks present).

**Recommended approach (single coherent change):**
1. Add every `--ui-*` token as an `@theme static` `--color-*`/`--radius-*`/`--shadow-*`/
   `--text-*`/`--ease-*`/`--tracking-*` entry in `app.css` (most already exist per the
   header map at `app.css:165–244`; the missing ones are the *references* below).
2. Remap `datatables.css` to the `@theme` names (e.g. `color: var(--color-navy)`,
   `box-shadow: var(--shadow-focus)` after adding a focus-ring token).
3. Remap the three views' no-fallback refs (`--ui-focus-ring`, `--ui-text-sm`, and
   dashboard's arbitrary values) to `var(--color-*)` / `text-dense` equivalents.
4. **Gate (M1):** `rg -- "--ui-" resources public build` → **0** live refs outside
   historical docs; grep `var(--color-navy)` and `var(--shadow-focus)` present in the
   new build; redeploy `public/css/datatables.css`; `npm run build`.

**◆ LANDED 2026-09-07.** Gate result: **PASS** — `rg -- "--ui-" resources public build`
shows only provenance comments inside app.css's `@theme static` header (not references);
**0 live `var(--ui-` refs** in `resources`, `public/css/datatables.css`, and the build
(`public/css/ui.css` is retained and keeps its own `--ui-` self-reference set by design
until M6). `npm run build` emits `--color-navy` and the new `--shadow-focus`
(`0 0 0 0.2rem rgba(252,209,22,0.3)`, value-exact from `--ui-focus-ring`) in the theme
roots. Implemented: app.css token add + `--ui-accent`→`--accent` rename; datatables.css
7 refs; dashboard 29 refs; clients/index 2 refs; scan 1 ref; global-search inline+class
refs. See `docs/IMPLEMENTATION_LOG.md` 2026-09-07 "M1" entry. No PHP changed, suite
unchanged (301 tests / 1419 assertions).

### M2 — Port Reboot + `_type.scss` element parity into `app.css`, OR take the §I Preflight decision — **LANDED 2026-09-07 (Option B)**

**Why:** the build has 0 `box-sizing`, 0 `body{margin:0}`, and heading/keyboard resets
come only from `ui.css` §4.10. Deleting `ui.css` without this step reverts the whole
app to content-box, UA margins, and UA heading sizes.

**Two options (decision to be made, not in this doc):**
- **A (Preflight ON):** enable `@import 'tailwindcss/preflight.css'` as a layer above the
  theme and delete §4.10 in the same removal commit. Bigger visual delta (bare `h1-h6`
  become `font-size:inherit`, `img` becomes block) — §I currently says **NOT enabled**.
- **B (Preflight OFF — matches current §I):** port §4.10's element rules verbatim into
  `app.css` under a `@layer base` (universal `box-sizing`, body font/color/margin,
  heading sizes, link color, form/label/table/button defaults). Zero visual diff today;
  the Preflight flip stays future baseline work.

**Gate (M2):** computed-style parity check (the Phase-27 harness) shows **0 diffs** on
the 8 pubic + 2 representative auth screens; `npm run build` emits `*,*::before,*::after`
box-sizing rule.

**◆ LANDED 2026-09-07 (Option B — Preflight stays OFF).** Gate result: **PASS** —
§4.10 Reboot/type rules ported **verbatim** into `resources/css/app.css` `@layer base`
(universal `box-sizing`, body margin/color, heading/keyboard/link/table/form/label/button
defaults, `_type.scss` heading sizes + 1200px media query). `npm run build` now emits
`*,:before,:after{box-sizing:border-box}` and the full `@layer base` Reboot block (braces
balanced); served `/login` loads `app-dN0ghNSi.css` (Vite) before `ui.css` (unchanged,
still active). Parity: layered copy is **outranked by unlayered ui.css §4.10 at equal
specificity (identical values) → rendered styles are byte-identical to the pre-M2 baseline**;
no new unlayered element rules were added. Live harness (5 public pages × 375/768/1280):
universal `box-sizing:border-box`, `body{margin:0;color:#212529}` all pass; the only
computed "overrides" observed (`label`→block via `.form-label`, `button`→8px via
`.btn`/`rounded-btn`) are **pre-existing** higher-specificity component rules that also
beat ui.css today (not drift); 0 console errors, 0 page errors, 0 horizontal overflow.
See `docs/IMPLEMENTATION_LOG.md` 2026-09-07 "M2" entry. No PHP changed; suite 301 tests /
1419 assertions, Pint clean.

### M3 — Port global `:focus-visible` gold outline + `prefers-reduced-motion` into `app.css` — **LANDED 2026-09-07**

**Why:** the build has only component-scoped focus rules (`.icon-btn`, `.filter-group-
toggle`, `.filter-multi-search`, `.btn-navy:focus-visible`) and **no**
`prefers-reduced-motion`. ui.css §3 owns the app-wide 3px gold ring and the motion guard.

**Do:** move both blocks (§3's `:focus-visible` + `@media (prefers-reduced-motion…)`)
into `app.css` verbatim (or as the counterpart token-driven rules once §I is decided).
Keep `ui-skip-link` in app.css (already mirrored there).

**Gate (M3):** `rg -- "prefers-reduced-motion" resources build` → present in new build;
`:focus-visible` count in build rises from 6 to ≥7 with a **global** (unscoped) rule;
Playwright keyboard-Tab spot check shows the gold ring on a bare `<button>`.

**◆ LANDED 2026-09-07.** Gate result: **PASS** — ui.css §3's global `:focus-visible`
(3px gold outline, offset 2) and `@media (prefers-reduced-motion: reduce)` motion guard
ported **verbatim** into `resources/css/app.css` `@layer base` (using canonical
`--color-gold` = ui.css `--ui-gold`). `npm run build` emits both `@media(prefers-reduced-
motion:reduce)` and a **global unscoped** `:focus-visible{outline:3px solid var(--color-
gold);outline-offset:2px}`; `:focus-visible` selector count is now **7** (global + 6
scoped). Live Playwright (5-injected bare controls on `/login` × 375/768/1280): bare
`<button>` and `<a>` keyboard-focus show `3px solid rgb(252,209,22)` with `2px` offset —
**identical to the ui.css baseline**; 0 console/page errors, 0 horizontal overflow.
`ui-skip-link` left owned in app.css (not re-ported). ui.css unchanged + linked; Preflight
OFF; M4 not started. See `docs/IMPLEMENTATION_LOG.md` 2026-09-07 "M3" entry.

### M4 — Complete the Bootstrap-parity layer in `app.css`, OR migrate each remaining consumer

This is the large one. Every section-3 family must land a migrated.
**Single-chunk candidate order (parallelize after M1 builds on tokens):**

1. **Utility drop that is trivial and safe to port:** `.d-flex`, `.align-middle`,
   `.text-muted` (→ `.text-gray-500`), `.bg-white`, `.mx-auto` (→ `mx-auto`), `.fw-bold`
   (→ `font-bold`). Port-as-utilities into app.css with `@utility` for the JS contract
   names, or replace markup — screen by screen.
2. **Forms (heaviest):** `.form-control(-sm)`/`.form-select(-sm)` → `app.css .field*
   (field / field-sm / select-field)` vocabulary already used on migrated screens;
   `.form-check-input` → `app.css` checkbox styling; `.form-label` → `app.css .field-label`.
3. **Buttons:** `.btn` base + `.btn-sm` → extend `app.css .btn-gold/.btn-navy/…` with a
   `.btn-field` shared base or translate each `btn btn-*` call site to `app.css .btn-navy
   btn-field`. `.btn-primary/.btn-danger/.btn-outline-primary` → their `app.css`
   equivalents (`.btn-navy`, `.btn-red`, `.btn-outline`).
4. **Chrome:** `.btn-close` (→ Alpine-driven X / `@apply` close utility), `.btn-group`,
   `.alert*` (→ `app.css .ui-notice`/`.ui-alert` or Tailwind), `.table`/`.table-sm`/
   `.align-middle`/`.table-responsive` (→ Tailwind table utilities + `overflow-x-auto`),
   `.dropdown-menu*`/`.dropdown-item` (→ Alpine `.show` dropdown classes in `app.css`),
   `.accordion*` (clients/_gip → Alpine `x-collapse` already in place; drop classes),
   `.list-group*` (→ `app.css` list-stack or Tailwind), `.page-link` (datatables.css owns
   the scoped chrome — only the unscoped ui.css base needs removal).
5. **Grid & spacing ladder:** `.row`/`.col-*`/`.g-*` → Tailwind `flex/grid` + `grid-cols`
   per screen; `.mt-*/mb-*/py-*/px-*/gap-*` **steps 3–5 must be re-verified after
   removal** because Tailwind twins load with different values (§6.4).
6. **JS contract `.d-none`:** keep the class working. Either (a) port as `@utility
   d-none { display: none !important; }` in app.css, or (b) replace every `d-none`
   toggle with `x-show`/`hidden` on the 10 screens that flip it. (b) is preferred so the
   JS contract disappears with the layer; (a) is the low-risk bridge if a screen cannot
   be touched yet.

**Gate (M4):** every §3 family has **0** live refs in `resources/views` (grep clean),
the 11 DataTables screens render identical to pre-migration (Playwright per screen when
an admin account exists; computed-style fallback otherwise), `npm run build` emits the
new utilities, and the standalone public pages are visually reviewed.

**◆ M4.1 LANDED 2026-09-07 — utility drop (`.d-flex`, `.align-middle`, `.text-muted`,
`.bg-white`, `.mx-auto`, `.fw-bold`).** Worked strictly to the per-family gate below.
- **Migrated to Tailwind (value-identical, build already emitted them; markup-only swap):**
  `.d-flex` → `flex` on **9/10** elements (auth/login, grantee_update/self-service +
  _self_update_tab, unpaid_verifications/self-service, qr/viewer `col-md-6 flex
  align-items-end`/`mt-3 flex …`/`flex flex-wrap …`); `.fw-bold` → `font-bold` on **5/5**
  (clients/_gip accordion-button, scanners/scan ×2 JS template, grantee_update ×2 JS
  template). `.mx-auto` needed **no change** — the class string is already identical in
  Bootstrap and Tailwind and the Tailwind `mx-auto` utility is emitted from scanned
  screens, so it renders `margin-inline:auto` with no competing rule.
- **Retained with documented justification (not drift — deliberate):**
  - `.d-flex` **1** (`students/update-photo:35` `list-group-item d-flex …`): ui.css
    `.list-group-item { display: block }` (0-1-0, unlayered) would beat a non-`!important`
    Tailwind `flex` at equal specificity while ui.css is linked → would change `display`
    to block. Also enchains the excluded `list-group` family.
  - `.align-middle` **12** (all on `.table`/`.table-sm` DataTables & matrix rows): pure
    `!important` table `vertical-align` whose non-`!important` Tailwind twin would **lose
    the cascade** to unlayered `.table { vertical-align: top }` → computed drift. Table
    family is outside M4.1 scope.
  - `.text-muted` **2** (JS template strings in family_members/create:112,
    households/create:186): value mismatch — ui.css `.text-muted` = `rgba(33,37,41,.75)`,
    Tailwind `text-gray-500` = `#6b7280` (different hue). No exact Tailwind twin.
  - `.bg-white` (24 matches, only ~5 are genuine Bootstrap: family_members/create:62,
    transactions/create:85, transactions/edit:89, scanners/scan:190, scholars/_form:17;
    the rest are already-Tailwind `dark:bg-white`/`hover:bg-white/10`/`focus:bg-white`):
    every genuine Bootstrap `.bg-white` coexists with an excluded family (`list-group`,
    `d-none`) or is a JS-driven autocomplete dropdown container — retained with the
    family it is paired with.
- **Verification:** `npm run build` clean (CSS hash unchanged — markup-only migration;
  `flex`/`font-bold`/`mx-auto` payload unchanged). Computed-style: login `.flex`
  container displays `flex` at 375/768/1280 (matches baseline), injected `font-bold`
  weighs 700 (**= baseline**); 0 console/page errors, 0 horizontal overflow. Zero JS refs
  to any of the 6 families in `resources/js`/`public/js`. PHPUnit 301/1419/0, Pint clean.
  `ui.css` unchanged + linked; Preflight OFF; excluded families untouched. M4.2+ **not**
  started. Full entry: `docs/IMPLEMENTATION_LOG.md` 2026-09-07 "M4.1".

**◆ M4.2 LANDED 2026-09-07 — forms family (`.form-control`, `.form-select`,
`.form-check-input`, `.form-label`, `.input-group`, + secondary form-state classes).**
Inventory (before): `.form-control` 189 · `.form-select` 60 · `form-check*` 37 ·
`.form-label` 48 (46 real + 2 comments) · `.input-group` 5 · `-sm` 19 ·
secondary (`.is-invalid` 4 custom non-ui.css, others 0). Zero JS-module/JS references to
any target class as selector/toggler.
- **Migrated (markup-only):** `.form-label` → Tailwind `mb-2` on **46** labels
  (`grantee_update/self-service` ×23, `grantee_update/_self_update_tab` ×23). Exact
  `.5rem` parity — root `html{font-size:14px}` ⇒ both compute 7px, verified live equal on
  `/grantee-update` @ 375/768/1280. `for=`/text/validation/accessibility unchanged.
- **Retained with documented justification (NOT drift — parity/cascade):**
  - `.form-control` (189): `app.css .field-control` is a *redesigned* dense control
    (0.85rem/8px/ink/gold-ring), not the Bootstrap metrics (1rem/6px·12px/#212529 +
    `--ui-focus-ring` navy focus + file/password/number/date/textarea variants). No
    Tailwind twin reproduces it exactly.
  - `.form-select` (60): native `<select>` + custom SVG chevron + navy focus ring; a
    generic Tailwind `<select>` changes appearance/native behavior.
  - `.form-check-input` (37): `appearance:none` SVG checked/radio/indeterminate + navy
    checked states — high-risk native control redesign.
  - `.input-group` (5): flex layout + radius-collapse/margin `-1px` seam tied to `.btn`
    /`.input-group-text`; migration pulls the excluded button family into scope.
  - `-sm` variants (19): all sit in DataTables toolbar/inline-edit (excluded table family).
  - `.is-invalid` (4): custom project contract in `clients/index.blade.php` (scoped
    `<style>` + `classList.toggle`), **not** from `ui.css` — untouched.
- **Verification:** build clean, CSS hash unchanged (markup-only; `mb-2` already emitted);
  Playwright `/grantee-update` + `/login` @ 375/768/1280 → label before==after (21/21),
  `.form-control` metrics untouched, 0 console/errors/overflow/shift; PHPUnit 301/1419/0,
  Pint clean; `ui.css` unchanged + linked; Preflight OFF; excluded families + M4.3+
  untouched. Full entry: `docs/IMPLEMENTATION_LOG.md` 2026-09-07 "M4.2".

**◆ M4.3 LANDED 2026-09-08 — layout/grid family (`.row`, `.col-sm-*`, `.col-md-*`, `.g-*`).**
**Verdict: RETAIN ALL** (126 classes) after full inspection — a valid gate-PASS result per §19
(exact parity cannot be safely achieved). **Zero view/CSS/JS changes.**
- **Inventory (before == after):** `.row` **20** (4 `row g-2 mb-3` header + 14 bare `row`
  field groups + 2 `<dl class="row">` JS DataTables detail grids) · `.col-md-*` **54**
  (12×`col-md-6`, 24×`col-md-3`, 18×`col-md-4`) · `.col-sm-*` **48** (24×`col-sm-4`,
  24×`col-sm-8`, all in the `<dl>` templates) · `.g-2` **4** · `.col`/`.col-auto`/
  `.col-lg-*`/`.col-xl-*`/`.col-xxl-*`/`.gx-*`/`.gy-*` **0**. Consumers:
  `grantee_update/self-service` (8 rows/25 cols), `grantee_update/_self_update_tab` (8/25),
  `qr/viewer` (1/2), `unpaid_verifications/self-service` (1/2), `payouts/attendance` (dl+24 sm),
  `unpaid_verifications/index` (dl+24 sm).
- **Why retained (parity = impossible without a §7-forbidden construction):** the grid is a
  genuine **gutter-compensation** system (ui.css L1227-1258), verified live at
  `/grantee-update`: `.row` = `display:flex; flex-wrap:wrap; margin-top:-7px (g-2)/0 (bare);
  margin-left/right -3.5px (g-2)/-10.5px (bare); gap:normal`; `.row>*` = `flex-shrink:0;
  width:100%→md%; padding 0 3.5px/10.5px`; `.col-md-*` = `flex:0 0 auto; width:P%` @≥768px,
  `.col-sm-*` @≥576px. `gap-2`≠`.g-2` (Bootstrap pads each child 3.5px + compensates −3.5px at
  the outer edge; Tailwind `gap` leaves 7px free space, zero edge compensation); `w-*` alone
  cannot reproduce `flex:0 0 auto` + `max-width:100%` wrap semantics. A faithful replacement
  would be the bespoke stack `flex flex-wrap -mx-* *:px-*`(+`-mt-* *:mt-*`)+`md:w-*/md:flex-none`
  — a re-implementation of Bootstrap gutters with raw utilities, whose parity holds only at the
  14px root; §7/§10/§19 → retain.
- **Responsive (measured):** 375px = 100% stacked (rows wrap, `width:100%; max-width:100%`);
  768px = col-md-6 50% / col-md-3 25% / col-md-4 33.333%; 1280px = same md values (no lg rules);
  `<dl>` col-sm-4/8 active ≥576px, stacked below.
- **Nested-grid finding:** no `.row`-inside-`.col-*` nesting exists (update-form rows are direct
  children of `#updateForm`; the `<dl>` grids are flat) → no partial/hybrid migration risk.
- **Excluded-family interaction:** header rows pair with a `.btn-navy` (excluded family);
  `<dl>` grids live in **JS template literals** inside DataTables **modals** (excluded,
  auth-gated, structures rendered dynamically) — retained with the grid.
- **JS/Alpine contract findings:** 0 references in `resources/js`/`public/js` to any grid class
  as selector/hook (only literal "Click a row…" text hits). No contract depends on
  `.row`/`.col-*` renaming.
- **Spacing:** no spacing-family migration performed; `mb-2`/`mb-3` read for grid context only,
  left untouched.
- **Verification:** `npm run build` clean (CSS/JS hashes unchanged); Playwright
  `/grantee-update` + `/login` @375/768/1280 — geometry above proven live, **0 console/page
  errors, 0 horizontal overflow**; PHPUnit 301/1419/0, Pint clean; `ui.css` unchanged + linked;
  Preflight OFF; M4.4+ untouched. Full entry: `docs/IMPLEMENTATION_LOG.md` 2026-09-08 "M4.3".

**◆ M4.4 LANDED 2026-09-08 — buttons family (`.btn`, `.btn-sm`, `.btn-primary`,
`.btn-danger`, `.btn-outline-primary` + project variants).**
**Verdict: RETAIN ALL** — a valid gate-PASS result per §19 (exact parity cannot be safely
achieved). **Zero view/CSS/JS changes.**
- **Inventory (before == after):** Bootstrap-parity `.btn` base **25** (all in
  `btn <variant>` composites) · `.btn-sm` **4** · `.btn-lg` **0** · `.btn-primary` **1** ·
  `.btn-danger` **1** · `.btn-outline-primary` **2** · `.btn-secondary/success/warning/info/
  light/dark/link` + other `.btn-outline-*` **0**. Project variants (canonical `app.css` vocab,
  shown for the composite impact): `.btn-gold` **34** (7 with `.btn`), `.btn-navy` **47** (1),
  `.btn-red` **10** (4), `.btn-subtle` **81** (6), `.btn-outline` **3** (3), `.btn-outline-red`
  **3** (0). Composites: `btn btn-gold` ×7 (admin/users/show:133, households/show:150,
  scholars/gip-show:88, scholars/show:209, payouts/show:100, transactions/show:145,
  clients/_details:327) · `btn btn-navy` ×1 (clients/_details:323) · `btn btn-red` ×4
  (clients/_details:333, payouts/show:107, unpaid/show:91, transactions/show:151) ·
  `btn btn-subtle` ×6 (admin/audit_logs/show:86, households/show:152, payouts/show:110,
  unpaid/show:94, transactions/show:154, clients/_details:325) · `btn btn-outline` ×3
  (scholars/gip-show:88, scholars/show:210-211) · `btn btn-sm btn-primary`/`btn btn-sm
  btn-danger` ×1 each (**JS template literals**, payouts/attendance:167-168) ·
  `btn btn-sm btn-outline-primary` ×2 (**JS template literals**, grantee_update/self-service:433,
  _self_update_tab:413 — QR download). Excluded untouched: `.btn-group` ×2 (Alpine dropdown
  contracts), `.btn-close` ×15.
- **Cascade reality (probed live):** both `ui.css` `.btn` (L233-251: `inline-block;
  padding .375rem .75rem =5.25/10.5px @14px root; 1rem/400/1.5; #212529; transparent;
  1px transparent border; radius .375rem =5.25px; .15s transition`) and the app.css
  self-contained variant block (L523-529: `inline-flex; 7.875/14px; 8px; .85rem/600;` variant
  colors) are **unlayered, same 0,1,0 specificity**; ui.css links **after** `@vite` at
  `layouts/app.blade.php` → **`.btn` wins the metric conflicts**. Playwright probe (elements
  injected on `/login`, default/hover/active/focus @375/768/1280, identical across widths)
  measured `btn btn-navy` = transparent `#212529`, 5.25/10.5px, 5.25px radius, 14px/400,
  1px solid border — i.e. the **legacy Bootstrap ghost metrics**, not the designed navy
  (white on `#0038A8`, 7.875/14px, 8px, 11.9px/600). Δ on `.btn` removal = 12+ computed
  properties per state (display, padding ×4, font-size/weight, line-height, color, bg-color,
  border ×3, radius, text-align, transition-duration, hover/active/focus box-shadows) →
  a visible redesign prohibited by §1/§7/§8/§19 → **retain all composites**.
- **Why the pure Bootstrap variants are retained:** `.btn-primary` (ui.css L277-305) couples
  `--ui-navy-light #164A9C` hover + `rgba(0,56,168,.5)` 4px focus ring; `.btn-danger`
  (L340-365) `#A80F20/#900C1B` stops + red ring; `.btn-sm` 3.5/7px + 0.875rem + 3.5px radius —
  none has a canonical Tailwind twin under Preflight-OFF + still-linked ui.css, and all 4 usages
  live in **dynamic JS template literals** (DataTables action column, auth-gated/excluded;
  grantee verify-gated QR links). `.btn-outline` (app.css L540-545) is **not self-contained**
  (border+color only) — hard-depends on `.btn` for metrics.
- **JS/Alpine contract findings:** 0 references in `resources/js`/`public/js`/Blade to
  `.btn`/`.btn-variant`/`.btn-*` as selector/hook; behavior binds `.dropdown-toggle`,
  `view-btn`, `delete-btn`, `reset-btn`, `data-*` ids — unaffected. No `form`/`name`/`value`
  cross-form verbs; `type="submit"`/`type="button"` and checked states untouched (no markup
  changes).
- **Known limitation:** the 4 pure Bootstrap buttons render only inside auth-gated modals /
  DB-driven verify flows — retained Bootstrap classes guarantee structural parity (ui.css owns
  them); verified statically + via the injected-probe metrics, not on their real pages.
  Hover/active/focus captures some mid-`.15s`-transition values; end-stops cited from ui.css.
- **Verification:** `npm run build` clean (CSS/JS hashes unchanged); Playwright `/login`
  @375/768/1280 → 0 console/page errors, 0 horizontal overflow; PHPUnit 301/1419/0, Pint clean;
  `ui.css` unchanged + linked; Preflight OFF; M4.5+ untouched; scratch harnesses removed. Full
  entry: `docs/IMPLEMENTATION_LOG.md` 2026-09-08 "M4.4".

**◆ M4.5 LANDED 2026-09-08 — alerts/feedback family (`.alert`, `.alert-dismissible`,
`-success/-danger/-warning/-info`).**
**Verdict: RETAIN ALL** — a valid gate-PASS result per §19 (exact parity cannot be safely
achieved; some consumers are JS-contract-coupled). **Zero view/CSS/JS changes.**
- **Inventory (before == after):** `.alert` classes in **17 class usages + 2 selector refs**.
  Server-rendered (2): `layouts/app:101` `.alert alert-danger alert-dismissible` (validation
  error list; dismissed via Alpine `x-show` + excluded `.btn-close`); `unpaid/self-service:109`
  `#successBox` `.alert alert-success d-none mt-4 text-center` (`.d-none` state hook, filled
  by JS `textContent` + `classList.remove('d-none')`). JS-generated in Blade template literals
  (15): grantee_update/self-service 327/331/348/428/447; _self_update_tab 307/311/328/408/427;
  qr/viewer 175/195/221; unpaid_verifications/self-service 257/266/269/345 — `alert-danger` ×11,
  `alert-success` ×3 (QR-success block), `alert-warning small mb-0` ×1 (Alpine confirm-modal
  template), injected into empty `#alertBox`/`#saveMsg`/`#selfSaveMsg` containers.
  `.alert-primary/secondary/light/dark/heading/link` **0 consumers + 0 ui.css rules**;
  `.alert-info` rule present, 0 consumers. Out-of-family, untouched: `role="alert"` custom
  Tailwind error bars, `ui-notice` info bars (already canonical), `panel-form-errors`,
  `window.alert()` calls (~20).
- **Why retained (parity + JS contract):** ui.css owns the exact contract (L693-731): `.alert`
  = `relative; 1rem/1rem pad; 1rem mb; color inherit; transparent; 1px transparent border;
  .375rem radius`; `.alert-dismissible` = `padding-right:3rem`; `.alert-dismissible .btn-close` =
  `absolute top0 right0 z2; 1.25rem/1rem`; variants = stock Bootstrap 5.3 emphasis bytes
  (success `#0a3622/#d1e7dd/#a3cfbb`, danger `#58151c/#f8d7da/#f1aeb5`, warning
  `#664d03/#fff3cd/#ffe69c`, info `#055160/#cff4fc/#9eeaf9`). `app.css` has **no `.alert` rules**
  and no equivalent (`.ui-notice` is a redesigned gold bar — no palette/radius/dismissal twin).
  A faithful Tailwind equivalent = arbitrary-value stacks hardcoding Bootstrap hex bytes (the
  bespoke reimplementation §10 forbids), and it could not reach the JS-generated alerts without
  changing JS behavior. Decisive: `.alert` is a **live JS selector hook** — grantee_update
  `self-service:444` + `_self_update_tab:424` `msg.querySelector('.alert').insertAdjacentHTML(...)`
  (QR-error append) → removing the class throws `TypeError`. 0 references in
  `resources/js`/`public/js`.
- **Parity probe (measured, not assumed):** elements injected in-flow on public
  `/grantee-update` @375/768/1280: base/success/danger = 14px pad, 14px mb, 5.25px radius,
  14px/22.4px/400; `alert-warning small mb-0` = 12.25px font, 0 mb; `alert-dismissible` =
  42px right-pad with `.btn-close` 1px/1px from top-right, z-2, 42×49 — all match ui.css
  byte-for-byte at every width. 0 console errors, 0 horizontal overflow.
- **Dismissal findings:** no dismissal on JS alerts (wholesale `innerHTML` replacement);
  `layouts/app` = Alpine `x-show` (`.btn-close` untouched); `successBox` = `.d-none` removal
  (untouched). `role="alert"` on the server alert only.
- **Verification:** build clean, hashes unchanged; probe above; PHPUnit 301/1419/0; Pint clean;
  `ui.css` unchanged + linked; Preflight OFF; M4.6+ untouched; no `.btn`/`.btn-close`/toast/
  flash/`d-none` migration; scratch harnesses removed. Full
  entry: `docs/IMPLEMENTATION_LOG.md` 2026-09-08 "M4.5".

**◆ M4.6 LANDED 2026-09-08 — remaining component families (tables / dropdowns+btn-group /
`.btn-close` / accordion / list-group / `.d-none` / utilities) — FINAL M4 SUB-STEP.**
**Verdict: RETAIN ALL** — a valid gate-PASS result per §19 (exact visual/behavioral parity
cannot be established safely on any family; most remain live JS/Alpine/app contracts).
**Zero view/CSS/JS changes.**
- **Inventory (before == after):** Tables `.table` ×21 (15 DataTables + 6 plain:
  permission matrices ×4, sessions/online, households/`members-table`), `.table-sm` ×12,
  `.table-responsive` ×1 (DataTables wrapper, transactions/index:130), `.align-middle` ×12
  (M4.1-justified, `!important`). Dropdowns: clients/index:265, transactions/index:94,
  navbar:80 — `.btn-group`×2 / `.dropdown`×1, `.btn-subtle/.dropdown-toggle`,
  `.dropdown-menu.dropdown-menu-end :class={'show':open}`, `.dropdown-item` ×7.
  `.btn-close` ×15 (alert layouts/app:105; toasts clients/index:290+:769, households/index:97,
  transactions/index:122, clients/_details:840; error bars duplicates:77+:88,
  admin/users:74+:81, family_members/create:52, households/create:68; flash layouts/app:92;
  sidebar:50 `btn-close-white ms-auto lg:hidden`). Accordion ×1 (clients/_gip:10-25, Alpine
  x-show/x-collapse + `.collapsed` `:class` binding). List-group ×5 (autocomplete containers
  scanners/scan, transactions create+edit, scholars/_form + items via JS `classList.add`
  ×4 files; static `.list-group-flush` students/update-photo:33-35). `.d-none` 74 + JS
  classList hooks (incl. public suggestList/$successBox). Utilities: stock-Bootstrap set in
  ui.css §4.9 (`!important`, divergent values).
- **Contracts discovered (decisive):** `.show` = Alpine state class (renaming breaks 3 menus);
  `.collapsed` = Alpine class-binding (chevron transform + corner-radius contract);
  `list-group-item`/`list-group-item-action` = runtime `classList.add` strings in 4 files;
  `.d-none` = behavioral visibility hook (Step 8's 5 conditions all fail); `.btn-close` =
  `@click` + `aria-label` inside excluded parents; DataTables bootstrap5 integration (CDN
  `dataTables.bootstrap5.min.js` 1.13.6 + project `datatables.css` chrome) consumes base
  `.table`/`.table-sm` on 15 screens. 0 other JS selectors on any target class.
- **Why retained (per family):** Tables — DataTables coupling + no canonical plain-table
  vocabulary + §10 byte-stack prohibition; Dropdowns — caret `::after`, menu
  box/shadow/radius/min-width/offsets + `.show` state hook; `.btn-close` — inline SVG glyph +
  content-box 1em+.25em (21×21 @14px root) + opacity/hover/focus-ring + `.btn-close-white`
  filter, no Tailwind twin, parents excluded; Accordion — full Bootstrap 5.3.2 styling
  (border/radius/active `#084298`/`#cfe2ff`/chevron SVG swap) under Alpine state;
  List-group — JS-injected class contract + border/padding/radius/flush presentational set;
  `.d-none` — behavioral (JS state hooks); Utilities — broad rewrite forbidden, values diverge
  (`.mt-4`/`.mb-4`=1.5rem vs Tailwind 1rem; `.text-danger` #dc3545 vs `--color-red`;
  `!important`), many coupled to retained families.
- **Verification (Steps 11-13):** Playwright probe on `/grantee-update` @375/768/1280 injected
  in-flow representatives of every family; measured values byte-match ui.css (`.table` cell 7px
  pad, `.table-sm` 3.5px, `.align-middle` middle, `.table-responsive` overflow-x:auto,
  dropdown closed none/abspos/z-1000 + `.show` live-toggle → block, item active `#fff/#0d6efd`,
  `.btn-close` 21×21 opacity .5 SVG, accordion open color/bg + chevrons, list borders/padding,
  `.d-none` none); **0 console/page errors, 0 horizontal overflow** (sw==cw) at every width.
  `npm run build` clean (CSS/JS hashes unchanged); PHPUnit **301/1419/0**; Pint clean.
  `ui.css` unchanged + linked; Preflight OFF; `datatables.css` diff pre-existing (untouched);
  DataTables CDN intact; no M5/M6; no backend/DB change; scratch harnesses removed. Full entry:
  `docs/IMPLEMENTATION_LOG.md` 2026-09-08 "M4.6".

### M5 — Remove `d-none`/utility reliance (or bridge it)

**◆ M5.1 LANDED 2026-09-08 — behavioral-contract & `ui.css` dependency audit (AUDIT ONLY,
zero application changes).** Forensic classifier pass over `public/css/ui.css` (1,492 lines),
`datatables.css` (586), `app.css` (1,125) and the built bundle (`app-ByXmc8gJ.css`). Delivery:
an exhaustive contract inventory (Step 4 of the log entry), an ownership/blocking matrix
(Category 1–5) per ui.css §section, and the refined sub-roadmap below. **Verdict confirmed:
ui.css is NOT SAFE to delete; ~46 pages still depend on it. No M5.2+ work; no M6; Preflight
OFF; DataTables CDN + skin intact; suite 301/1419/0.** Full entry:
`docs/IMPLEMENTATION_LOG.md` 2026-09-08 "M5.1".
- **Decisive live JS/Alpine contracts re-verified:** `.show` = Alpine state class on 3
  dropdowns (`:class` binding; renaming breaks menu visibility) — while the toast `.show`
  markers (`classList.add` ×3) are **app-owned with zero CSS rule** (not ui.css dependencies);
  `.collapsed` = Alpine class-binding on the single accordion (`clients/_gip`, chevron swap +
  corner radius from ui.css §4.8 — app.css has no accordion rules); `.d-none` = behavioral JS
  hook (21 static usages, ~74 total refs, `classList`/`jQuery.toggleClass` toggles across 10+
  files; Tailwind emits 0); `.btn-close` = 13 static hidden inside toasts/alerts/modals/JS-created
  twice (`innerHTML` templates — a ported utility is picked up automatically); `list-group-item`/
  `-action` = runtime `classList.add` in 4 files; `.alert` = DNSD/live `querySelector('.alert')`
  hook ×2 (M4.5, unchanged).
- **Cross-file (Category 2) dependency found:** `datatables.css` B2 explicitly hands the
  DataTables filter/length **focus** states to ui.css `.form-control:focus` /
  `.form-select:focus` (datatables.css:477-480) — a deletion seam on all 10 DT screens.
- **Bundle truth:** the Vite build already emits `.align-middle`, `ms-1`, `mt-4`,
  `text-center` (value-identical) and a **phantom `.w-100`** (width:400px; 0 real consumers).
  *Attribution CORRECTED by M5.2: the emitter was the literal `w-100` token inside the
  `scholars/_form.blade.php` Blade comment — that file is on the app.css `@source` allowlist,
  so Tailwind's candidate scanner picked the spelling out of the comment text. The app.css
  coexistence comment is NOT scanned (proven: editing it produced a byte-identical bundle).* It does
  **not** emit `.d-none`, `.show`, `.collapsed`, `.dropdown-menu`, `.accordion*`,
  `.list-group*`, `.row`/`.col-*`, `.text-muted/-danger/…`, `.form-*`, `.input-group*`,
  `.btn-close`, `.alert-*`, `.btn-outline-primary`, `.page-link`, `.table-sm`,
  `.table-responsive` — every one of these with a Blade/JS consumer is a genuine blocker; plain
  tables (4 permission matrices, sessions/online, households/members-table) still need ui.css
  §4.6 (DT chrome is covered by datatables.css B1/B3).
- **◆ M5.2 LANDED 2026-09-08 — DataTables B2 focus seam + phantom `w-100` cleanup.**
  `datatables.css` now **owns** the DataTables filter/length **focus** state, scoped to
  `.dataTables_wrapper .dataTables_filter input:focus` / `.dataTables_wrapper
  .dataTables_length select:focus`: `outline: 0` + `box-shadow: var(--shadow-focus)` (the
  canonical `@theme` ring token, resolves to `rgba(252,209,22,.3) 0 0 0 2.8px` in 0.15s ease,
  mirroring ui.css `.form-control:focus`/`.form-select:focus` exactly). color/background/
  border-color are deliberately NOT re-declared on `:focus` — the B2 base rules (0,2,1) already
  own them (#212529/#fff/#dee2e6) and out-specify ui.css's (0,2,0), so border-color stays
  `#dee2e6` exactly as before (re-declaring would tee up `#164A9C` drift). Plain (non-DT)
  `.form-control`/`.form-select` focus still resolves entirely in ui.css — unchanged.
  **Phantom `.w-100`:** the emitter was the literal `w-100` token in the `scholars/_form.blade.php`
  Blade comment (file is on the `@source` allowlist; app.css comment proven inert); the comment
  now reads "full-width Bootstrap utility", and the built bundle contains **zero `.w-100`**
  (`.p-4`/`.gap-3`/`.w-full`/`mt-4`/`ms-1`/`align-middle`/`text-center` all remain = real
  consumers). **Untouched:** `ui.css` and `app.css` (both hash-identical to M5.1), DataTables
  JS/CDN, all Blade markup (only the comment text changed), Preflight, JS/Alpine.
  Verification: strict byte reconstruction of `datatables.css` = intended patch only; Playwright
  computed-style parity harness (OLD vs NEW datatables.css; 22 props × filter/length/plain
  controls × unfocused/focused × 375/768/1280) = **0 drift, 0 console errors, no overflow**;
  `view:cache` OK; Pint pass; suite 301/1419/0; build `app-DjX_TTSN.css`. Full entry:
  `docs/IMPLEMENTATION_LOG.md` 2026-09-08 "M5.2".
- **◆ M5.3 LANDED 2026-09-08 — `.d-none` behavioral migration.** All **72 live `.d-none`
  references** (17 static class hooks + 55 runtime operations across 9 views; the 3 inert
  comment mentions in non-consumer files are explicitly not consumers) migrated to the native
  Tailwind `hidden` utility, and the `.d-none { display: none !important; }` rule (ui.css §4.9,
  specificity 0,1,0, unlayered, single rule, no state mechanism) was **REMOVED** from ui.css —
  final ownership: **ui.css ❌ none / app.css ❌ none (no bridge) / Tailwind `hidden` ✅ canonical**.
  **Cascade-equivalence proof:** the app bundle already emitted `.hidden{display:none}` (Tailwind
  v4, `@layer utilities`, non-important); a repo-wide audit found ZERO dedicated selectors for
  any consumer (by id or class; `suggestions-list` has no CSS at all) and no unlayered/
  `!important` `display` rule capable of outranking a layered utility, so dropping the
  `!important` is behavior-neutral for every current consumer. **Runtime contracts renamed
  symmetrically:** `classList.add/remove/toggle('d-none')` → `'hidden'`; jQuery
  `toggleClass('d-none', editing)` → `('hidden', editing)` (transactions inline-edit row
  buttons). **Dynamic template literals:** none — the only `d-none` JS strings were the
  classList/jQuery args themselves. Blade compile clean (`view:cache` OK). Build output is
  **byte-identical** to M5.2 (`app-DjX_TTSN.css`; `.hidden` present; `.d-none`/`.w-100` absent)
  because `.d-none` was never Tailwind-emitted and `.hidden` already existed → **zero CSS growth,
  zero new selectors**. Playwright parity harness (ui-OLD.css with `.d-none` vs ui-NEW.css + app
  bundle; 13 elements incl. `.alert`/`img`/row-btn composites × 23 props × lifecycle:
  initial hidden → reveal → re-hide → ×4 repeated toggle → jQuery two-arg force toggle →
  dynamic insert, at 375/768/1280): **0 drift, 0 console errors, 0 page errors, no overflow**.
  Pint pass; suite **301/1419/0** (one transient mysqld-restart run excluded — re-run green).
  Full entry: `docs/IMPLEMENTATION_LOG.md` 2026-09-08 "M5.3".
- **◆ M5.4 LANDED 2026-09-08 — dropdown `.show` behavioral migration.** The dropdown open-state
  dependency is REMOVED from ui.css. **State mechanism (before):** each of the three Alpine
  dropdowns toggled the Bootstrap `.show` class (`:class="{ 'show': open }"` → ui.css
  `.dropdown-menu.show { display: block; }`, unlayered, 0,2,0). **After:** the same Alpine `open`
  state now binds the canonical app-owned **`.dropdown-open`** class, backed by a single
  narrowly-scoped rule in `resources/css/app.css`:
  `.dropdown-menu.dropdown-open { display: block; }` (top-level/unlayered, 0,2,0). No generic
  `.show` replacement was created (the goal is state vocabulary ≠ Bootstrap), and no Tailwind
  arbitrary stacks were used. **Why not `x-show` / plain `block`/`hidden` utilities:** plain
  utilities are unlayered here AND ui.css loads after the bundle, so `.block` (0,1,0) would lose
  the source-order tie to the `.dropdown-menu { display: none }` base (0,1,0) → menu could never
  open; `x-show` alone likewise resolves to the base `display:none` when the inline display is
  removed on open (and would reintroduce an init-flash risk). A 0,2,0 app-owned rule out-ranks the
  0,1,0 base on specificity regardless of load order, exactly mirroring the old `.show` cascade
  mechanics minus the Bootstrap vocabulary. **Consumers (3):** `partials/navbar.blade.php` (user
  menu), `clients/index.blade.php` + `transactions/index.blade.php` Export menus (Blade string
  templates `\'dropdown-open\'`). Base formation (`position:absolute`, z-index 1000,
  min-width 10rem, padding .5rem 0, border 1px rgba(0,0,0,.175), radius .375rem, Bootstrap
  shadow, `[data-bs-popper]` offsets, `.dropdown-item` states, caret) all stay in ui.css §4.7 —
  only the STATE rule left. **Referee checks:** `rg .dropdown-menu.show` → 0 in ui.css + bundle.
  **Toast `.show` untouched** (3 runtime `classList.add('show')` in clients views — not a
  dropdown/toast CSS dep; no `.toast/.show` rule exists). Harvested `.show` repo-wide = 3 Alpine
  bindings (migrated) + 3 toast ops + 0 CSS rules beyond the removed one + inert comments.
  **Parity:** real-Alpine Playwright harness (ui-OLD.css w/ `.show` vs ui-NEW + new bundle; two
  menu composites [navbar + export], computer-closed/open lifecycle ×6 states
  closed→open→outside-click→open→Escape→open-other, 26 props incl. bbox) @375/768/1280:
  **0 drift, 0 console errors, 0 page errors, no overflow**; class token differs exactly
  `show`→`dropdown-open`. Focused AFTER probe: closed `display:none`/0×0, open `display:block`,
  absolute, zIndex 1000, min-width 140px, pad 7px, radius 5.25px, shadow `rgba(0,0,0,.15)
  0 7px 14px 0`, item `.dropdown-item` 3.5px/14px (#212529); aria-expanded false⇄true tracked;
  outside-click + Escape close identically. `view:cache` OK; Pint pass; suite **301/1419/0**;
  build `app-kffPtn-M.css` (59.64 kB — +42 B = the migration rule; `.hidden` present, no
  `.show{`, no `.w-100`). Full entry: `docs/IMPLEMENTATION_LOG.md` 2026-09-08 "M5.4".
- **◆ M5.5 LANDED 2026-09-08 — accordion `.collapsed` behavioral migration.** The accordion
  collapsed-state dependency is REMOVED from ui.css. **State mechanism (before):** the single live
  accordion (clients/_gip, only when `$hasGipTransaction`) bound the Bootstrap `.collapsed` class
  inverted from Alpine state (`:class="{ 'collapsed': !accordionOpen }"` → ui.css §4.8 selectors
  `.accordion-button:not(.collapsed)` [open color/bg/inset shadow], `.accordion-button:not(.collapsed)::after`
  [blue chevron + `rotate(-180deg)`], `.accordion-item:last-of-type .accordion-button.collapsed`
  [closed last-item corner `calc(.375rem - 1px)`]). Panel visibility was ALREADY Alpine-owned
  (`x-show="accordionOpen" x-collapse` — the bundled `@alpinejs/collapse` plugin), so `.collapsed`
  was purely a visual-state class: **separating state from visibility required no visibility change.**
  **After:** Alpine now binds the canonical app-owned **`.accordion-open`** class directly from the
  boolean open state (`:class="{ 'accordion-open': accordionOpen }"` — no inversion), backed by three
  scoped rules in `resources/css/app.css` (top-level/unlayered):
  `.accordion .accordion-button.accordion-open` (open color/background/inset), `.accordion …accordion-open::after`
  (active icon + `rotate(-180deg)`), `.accordion .accordion-item:last-of-type .accordion-button:not(.accordion-open)`
  (closed corner; open state re-routes the corner to the existing ui.css `.accordion-collapse` radius).
  All values reference the `--ui-accordion-*` tokens still owned by ui.css §4.8, so they stay
  byte-identical to the retired rules. **Critical cascade detail:** the `.accordion` scope prefix
  raises specificity to 0,3,0. Without it, `.accordion-button.accordion-open` (0,2,0) would lose the
  `box-shadow` tie to the LATER-loading ui.css `.accordion-button:focus` (0,2,0) (the pre-migration
  file ordered `:focus` before `:not(.collapsed)`, so the open inset won); the 0,3,0 prefix reproduces
  exactly the old open+focused rendering. Retired ui.css selectors: the three above — **0 `.collapsed`
  rules remain**; all base `.accordion(.item/.button/.header/.collapse/.body)` rules AND the
  `--ui-accordion-*` token block stay (legitimate base formation). **Referee checks:** `rg .collapsed`
  in resources+public → 3 inert comment mentions (app.css ownership comment ×2, ui.css §4.8 comment);
  `rg 'accordion-open'` → 1 view binding + 3 app.css rules. **Parity:** real-Alpine + `@alpinejs/collapse`
  Playwright harness (`%TEMP%\opencode\m55`; before = current ui.css + re-appended retired rules + the
  built bundle MINUS the three `accordion-open` rules — the old bundle must be stripped or the new
  `:not(.accordion-open)` corner rule masks the old contract; after = current ui.css + current bundle;
  16-row dl grid, button `accordion-button font-bold`, lifecycle closed→open→closed→Enter-open→Space-closed→
  open→open+focused→closed ×5 cycles, ~30 props + bbox + aria/class + panel + overflow) @375/768/1280:
  **0 drift, 0 console errors, 0 page errors, no overflow**; class token differs exactly
  `collapsed`→`accordion-open`. Focused probe: open+focused box-shadow `rgb(222,226,230) 0px -1px 0px 0px
  inset` BEFORE==AFTER (inset beats the focus ring — the specificity scope is behavior-neutral); open
  chevron `matrix(-1,0,0,-1,0,0)` + active icon MATCH; closed chevron `none`; button/panel/item corners
  4.25/5.25 px identical. `view:cache` OK; Pint pass; suite **301/1419/0**; build `app-DjDvJDSm.css`
  (60.23 kB — +595 B = the three rules; `.hidden` present, no `.show{`, no `.w-100`); datatables.css
  `B82937…` unchanged. Full entry: `docs/IMPLEMENTATION_LOG.md` 2026-09-08 "M5.5".
- **◆ M5.6 LANDED 2026-09-08 — `.btn-close` ownership & behavioral migration.** The `.btn-close`
  SELF dependency is REMOVED from ui.css. **Contract:** ui.css §4.3 owned the button's own visual
  contract — box (`content-box`, `1em×1em`, `.25em` padding), color `#000`, glyph (Bootstrap 5.3.2
  `data:image/svg+xml` × 16×16 × black path, `center/1em auto no-repeat`), `border:0`,
  `border-radius:.375rem`, `opacity:.5`; `:hover` → `opacity:.75`; `:focus` → `outline:0` +
  `0 0 0 .25rem rgba(13,110,253,.25)` + `opacity:1`; `.disabled/:disabled` → `pointer-events:none`,
  `user-select:none`, `opacity:.25`; `.btn-close-white` → `filter:invert(1) grayscale(100%) brightness(200%)`.
  **Consumers:** 12 static blade buttons (modals on family_members/create, duplicates ×2,
  households/create, admin/users ×2, households/index, transactions/index, the flash toast +
  `.alert-dismissible` validation alert in layouts/app, clients/index modal header pull-in) + 1
  sidebar mobile close (`btn-close-white ms-auto lg:hidden`) + **2 JS-created** toast buttons
  (clients/index `showToast` and clients/_details feedback toast; both build
  `<button type="button" class="btn-close shrink-0" aria-label="Close"></button>` via `innerHTML`,
  wired through `el.querySelector('button[aria-label="Close"]')` → `el.remove()`, destroyed
  deterministically). **JS selector audit:** NO selector targets `.btn-close` — DetailsPanel uses
  `#detailsClose`; both toast paths use `button[aria-label="Close"]` — so the literal class is
  contract-free and was RETAINED (no markup/JS change: 16 consumers byte-identical, incl. the 2
  dynamically created). **Ownership:** the six rules moved to `resources/css/app.css` as plain
  top-level/unlayered rules with byte-identical values, replacing ui.css §4.3 ownership; ui.css §4.3
  now holds only a note. **Step taken vs the earlier plan** (which proposed `@utility btn-close`
  + 13 static swaps): plain rules + retained class were safer — `@utility` registers in the
  utilities LAYER, which unlayered ui.css siblings outrank regardless of specificity ties, whereas
  plain app.css rules keep the exact old unlayered conflict resolution; and swap churn (markup +
  `btn-close-white` companion) adds risk with zero behavioral gain because no JS contract binds the
  name. **Retained in ui.css:** `.alert-dismissible .btn-close` (position/top/right/z-index/padding)
  is an `.alert` PARENT positioning contract (it pairs with `.alert-dismissible{padding-right:3rem}`);
  alerts remain ui.css-owned per M5.6 scope (§16) — not `.btn-close` self-ownership. Production
  cadence shown by probe: alert button idle 14×14, content-box, pad 17.5px/14px (dismissible
  override), absolute top 0 right 0 — identical before/after. **Parity:** Playwright harness
  (`%TEMP%\opencode\m56`; before = current ui.css + re-appended retired §4.3 block + current bundle
  MINUS the six contiguous minified `.btn-close`/white rules [61008→60229 B]; after = current
  ui.css + current bundle; six static contexts — plain, modal-hdr flex, `.alert-dismissible`,
  `.sidebar` white, flash-toast flex, disabled clone — + JS-created toasts with click-remove and
  repeated creation; lifecycle idle→hover→focus(alert)→focus(modal)→create→remove→recreate→
  alert-click-close→Enter→Space, ~60 computed props × ::before/::after + bbox) @375/768/1280:
  **0 drift, 0 console errors, 0 page errors, no overflow**; glyph = SAME `data:image/svg+xml` URI
  (byte-identical string before/after); sidebar filter identical. `view:cache` OK; Pint pass; suite
  **301/1419/0**; build `app-BZH684Eh.css` (61.01 kB — +779 B = the six rules; `.hidden` present,
  no `.show{`, no `.w-100`); datatables.css `B82937…` unchanged. Full entry:
  `docs/IMPLEMENTATION_LOG.md` 2026-09-08 "M5.6".
- **◆ M5.7 LANDED 2026-09-08 — final utility / grid / text dependency re-scan.** Forensic,
  value-level audit of every remaining §4.x utility / grid / text family (display, flex,
  justify/align, margin-auto, position, text-align/color, typography, sizing, images, bg,
  border, radius, overflow, spacing ladder, gap, grid `.row/.col-*/.g-*`, tables,
  `.align-middle`) against the **compiled Tailwind bundle** (`app-BZH684Eh.css`) — values
  compared by compiled declaration, not by class name. **MIGRATED (11 ui.css rules removed,
  ZERO Blade/JS file edits — the consumer class literal IS the Tailwind class string, so the
  twins were already compiled):** `.gap-1` / `.gap-2` (`var(--spacing)` × 1/2 = .25/.5rem,
  exactly the Bootstrap ladder; no ui.css component sets `gap` on the consumers — the
  `.status-badge`/`.data-card-*`/`.ui-*` gap rules target other classes), `.flex-wrap`
  (`wrap` == `wrap`; ui.css `flex-wrap` declarations live only on `.row`/`.data-card-*`/`.ui-*`
  component classes the consumer divs never carry), `.top-0`/`.bottom-0` (only top/bottom
  declarations on the fixed-shell consumers; ui.css `top`/`bottom` live on `.dropdown-menu`
  /`.alert-dismissible .btn-close`), `.overflow-hidden`/`-visible`/`-x-auto`/`-y-auto`
  (identical values; the surviving ui.css `overflow` declarations are component-scoped:
  `.form-control`, `.table-responsive`, `.metric-card`, `.data-card-*`), `.ms-auto`
  (margin-inline-start:auto == margin-left:auto in this LTR, `dir` unset project),
  `.mx-auto` (margin-inline:auto; `.data-card` sets no margin). Each removal is
  cascade-neutral because the migrated family property has **no competing unlayered
  declaration** on any consumer element (verified by per-family static audit of the whole
  ui.css + app.css rule set). **RETAINED with forensic reasons:** spacing ladder 0–2 values
  are equal but every consumer is exposed to unlayered element rules (§4.10 `h1`/`p`/`dd`
  margins) or component padding (`.list-group-item` padding beats the layered `px-0` twin →
  would flip), and 3–5 differ outright (`mt-3` Tailwind .75rem vs 1rem, `mt-4` 1rem vs
  1.5rem, `mt-5` 1.25rem vs 3rem …) — **entire `m*`/`p*` family retained, NOT trimmed
  by name coincidence**; `.rounded` (Bootstrap .375rem vs Tailwind `.rounded` = .25rem —
  the ~124 consumers currently keep .375rem via `!important`); `.border` (Tailwind twin =
  width + `var(--tw-border-style)` only, NO `#dee2e6` color — a live flip); `rounded-1/2/3/
  circle/pill`, `border-1/2/3`, `text-primary/secondary/success/danger/warning/info/muted/
  white/body` (no exact Tailwind token; `text-danger #dc3545` etc.); `.text-start/-end/
  -center` (`.text-center` has a concrete live flip: unlayered §4.10 `th { text-align:
  inherit }` beats the layered twin → `<th class="text-center">` headers would snap to
  inherited/left — 8+ live instances); `.fw-*`/`font-weight-*`, `.fst-italic`, `.fs-4`,
  `.small`, `.img-fluid`/`.img-thumbnail`, `.bg-white`/`.bg-transparent` (`.bg-transparent`
  flip proven: unlayered `.list-group-item{background-color:#fff}` would beat the layered
  twin — the search-results `li` list uses it; `.bg-white` deferred on auth-scope parity);
  `.d-inline`/`.d-block`/`.d-flex`/`.d-inline-flex` (names differ from Tailwind `inline/
  block/flex/inline-flex` → rename + `position-*` cascade same risk, negligible value);
  `.position-*` (renames), `.min-vh-100`, `.w-auto`, `.me-auto`, `.mt-auto`, `.mb-auto`,
  `.my-*`/`.mx-*` (0/1/2 element-rule exposure), `.g-0/3/4/5` (value mismatch, see gap
  note), grid `.row/.col-sm-*/.col-md-*/.g-*` (M4.3 + §16 gutter-compensation model, fully
  live at 128 `.row` consumers), `.table/.table-sm/.table-responsive/.align-middle` (M4.6 +
  datatables.css; `.align-middle` on `<table class="table align-middle">` is the cell-center
  MECHANISM — unlayered `.table{vertical-align:top}` beats a layered twin → must stay).
  **DEAD (0 consumers, classified for M6):** `.mt-auto`, `.mb-auto`, `.flex-nowrap`,
  `.me-auto`, `.overflow-auto`, `.d-block`, `.d-inline-block`, `.d-inline-flex`,
  `.position-static/-fixed`, `.text-end/-body/-secondary`, `.fw-light/-normal`,
  `.fst-italic`, `.rounded-1/2/3/-circle`, `.border-1/-3`, `.ps-0`, `.pe-0`,
  `.py-0`, `.pt-0`, `.pb-0`, `.my-0`, `.mx-0`, `.g-0/-4/-5`, `.gap-5` + near-dead
  `text-info` 2, `text-muted` 2, `rounded-pill` 9, `fw-semibold` 0/`fw-bold` 0 in
  class-markup (occurrence counts incl. comments/JS strings). **Parity (M5.7):** Playwright
  harness (`%TEMP%\opencode\m57`; before = ui-BEFORE.css + current bundle; after = edited
  ui.css + current bundle; full-page snapshot every body element's computed style + bbox
  over `/login`, `/qr-viewer`, `/grantee-update`, `/unpaid-verification` @375/768/1280 +
  REP-CONTEXT replicating the auth-gated shells — sidebar `fixed top-0 bottom-0
  overflow-visible` + `ms-auto` close/chevron, sticky-nav `top-0`, `.data-card mx-auto`,
  `flex flex-wrap gap-1/2/3` strips, `h-2 overflow-hidden` metric bar, `overflow-x-auto`
  table shells incl. `<th class="text-center">` + `table align-middle`): **0 drift, 0
  console/page errors, no overflow**. Interceptor proven LIVE — a poisoned ui-BEFORE
  (`.gap-2{gap:99px!important}`) computed 99px on the real /unpaid-verification page vs
  7px (0.5rem @14px root) in AFTER → both variants genuinely applied. `view:cache` OK;
  Pint pass; suite **301/1419/0**; build byte-identical `app-BZH684Eh.css` (61.01 kB,
  `.hidden` present, no `.show{`, no `.collapsed`, no `.w-100`); ui.css hash
  `B593DDF8…`; datatables.css intact at 595 lines LF (sole HEAD-delta = the M5.2 seam;
  `--color-navy`/`--shadow-focus` + `.align-middle` seam rules present); M5.2–M5.6 guards
  re-verified. Full entry: `docs/IMPLEMENTATION_LOG.md` 2026-09-08 "M5.7".
- **Refined sub-roadmap for M5.8+ (in order):**
  1. **M6** — full-matrix regression + terminal deletion commit (below); ui.css utility,
     grid and text families plus component families (alerts, list-group, dropdown,
     accordion, form-*, .btn*, modal base, toast/navbar) plus §4.10 Reboot/type parity —
     each still a verified dependency block. M5.7's 11 retired rules are APPROVED
     deletions; the remaining rules carry their Reasons column from the ◆ M5.7 matrix.
  Each sub-step keeps the per-family gate (inventory → parity probe → no sibling-family
  migration) used across M4/M5.
  (M5.6 itself executed as a plain retained-class ownership transfer — see the ◆ M5.6 bullet —
  instead of the `@utility btn-close` + 13 static-swaps variant sketched in the pre-M5.6 plan.)

### M6 — Full-matrix regression and the terminal deletion commit

1. **Static gates:** grep clean for `--ui-`, `.btn-primary`, `.form-control`,
   `.dropdown-menu`, `.list-group`, `.d-none`, `.accordion` in `resources/views` +
   `resources/js` + `public/js`; `build/assets` contains the new vocab; `ui.css` has no
   `<link>` and no `@import` anywhere.
2. **Live gates:** PHPUnit suite (`php artisan test`), `npm run build`, `view:cache`,
   Pint; live Chromium on the 6–7 public surfaces (0 console errors, 200, no overflow);
   DataTables pagination colors verified (token remap in M1).
3. **Deletion commit:** delete `public/css/datatables.css`? **No** — datatables.css
   **stays** (it owns DataTables chrome; only its token refs change in M1). Delete only
   `public/css/ui.css` + the 8 `<link>` lines. Then a final full-suite run.

> **Auth-gated limitation (persistent):** internal screens cannot be browser-verified
> without an admin login; DB stays untouched. Apply the documented Phase-27/28
> computed-style parity harness at 375/768/1280 on whatever surfaces are reachable, and
> schedule browser E2E for auth-gated screens once an admin account exists.

---

## 5. Precedence & phases (execution order)

| Order | Work | Blocks |
|---|---|---|
| 1 | **M1** tokens → `@theme` + datatables/view remap | everything downstream |
| 2 | **M2** Reboot/type port (or §I Preflight decision) | delete |
| 3 | **M3** global focus/reduced-motion port | delete (a11y) |
| 4 | **M4.1–M4.6** parity completion per family | delete |
| 5 | **M6** gates + deletion commit | — |

M1–M3 are cohesive, low-risk, and removable independently. M4 is the bulk; split by the
§3 families. Each step is a separate reviewed change (matching the phase-style
discipline used for Phases 24–28).

---

## 6. Risks

### 6.1 Token-origin ambiguity
The `@theme static` header already transplants verified Batch-A token *values*;
`ui.css:root` is the same value set. Remap by **value** (not by name) — copy from
`ui.css` and confirm each with the built-CSS parity harness.

### 6.2 `@source` allowlist silent drops
If a migrating Blade adds Tailwind classes, the file must already be on the §E
allowlist; otherwise the class silently vanishes from the build. Directory `@source`
entries already cover `partials`, `clients`, etc. — verify each newly migrated file.

### 6.3 Bootstrap-parity JS contract
`.d-none`, `.btn-close`'s `aria-label`/X, and the export dropdowns' `.show`-class
toggling are behavioral contracts, not just cosmetics. Grep `classList` / `addClass` /
`removeClass` in `resources/js` and `public/js/components/*` when removing each class —
`DetailsPanel.js` (`.open`, `.no-scroll`), `FilterChips.js` (`.is-disabled`) already use
app.css-owned names and are unaffected.

### 6.4 Shared-name value drift (the coexistence hazard)
Tailwind twins already in the build differ from Bootstrap in value for the **spacing
steps 3–5** and some `py`/`px` steps (e.g. `.mt-5` 3rem → 1.25rem, `.py-3` 1rem →
0.75rem). Today `ui.css`, loaded **after** the Vite CSS and `!important`, wins. After
deletion the Tailwind values silently apply. M4.5 must re-verify every 3–5 spacing
on unmigrated screens and re-pad explicitly where the visual intent was the Bootstrap
value.

### 6.5 Incomplete computed-style coverage
Phase-27/28 parity is measured on public pages only. Auth-gated screens (forms, GIP
accordion, list-groups, DataTables pagination) need the future admin-account E2E + a
manual visual pass — this is a residual risk to record at the deletion gate.

---

## 7. Rollback

Each migration is independent and revertable (one-commit rollback). The deletion
commit is the only irreversible step; before it, keep `public/css/ui.css` on disk but
unlinked in a WIP branch, then:
- **Static re-run:** paragraph 1 of M6.
- **Live re-run:** parity harness + Playwright (public pages now, auth screens when an
  account exists).
- **Fallback:** restore the 8 `<link>` lines + filename from git — the file's content
  is unchanged (deletion is just unreferencing).

---

## 8. Cross-reference map to existing plans

| This doc | `TAILWIND_MIGRATION_EXECUTION_PLAN.md` | What it clarifies |
|---|---|---|
| §4 M1 | §C (tokens → `@theme`), §J (retirement), `app.css` §9.6 | Names the **live `var(--ui-*)` consumers** (datatables.css, dashboard 29 refs, 3 views) that must be remapped before `@theme` takes over |
| §4 M2 | §I (Preflight decision = NOT enabled today) | Gives the concrete **option B** port so §I's "Post-Bootstrap" Reboot source has a Tailwind home |
| §4 M3 | §J (ui.css global a11y) | Makes explicit that global focus-visible/reduced-motion are ui.css-only and must move to app.css |
| §4 M4 | §D (module inventory), §N (files per phase) | The per-family parity completion list is the pending workload behind §D.1–D.5 clusters |
| §4 M6 | §K (removal gate), §L (testing) | The **ui.css** delete gate is a follower of the Bootstrap CSS gate; reuses the same parity harness |
| §6.4 | §E (allowlist) + T1 coexistence rule | Documents the tailwind-twin value drift for spacing 3–5 that the allowlist was first built to avoid |

---

## 9. Definition of done (the deletion gate)

All six migrations landed **and** every checkbox:
- [x] M1: `--ui-` live refs = 0 (grep); tokens exist as `@theme`/`--color-*`/`--shadow-focus` (LANDED 2026-09-07)
- [x] M2: Reboot/type parity live in app.css or Preflight decision recorded (LANDED 2026-09-07, Option B)
- [x] M3: global `:focus-visible` + `prefers-reduced-motion` in the build (LANDED 2026-09-07)
- [ ] M4: §3 families have 0 live refs; JS contracts (`.d-none`, `.btn-close`, dropdown `.show`) traced to replacements
- [ ] M5: behavioral contracts resolved (M5.1 **AUDIT LANDED 2026-09-08**; **M5.2 LANDED 2026-09-08 — B2 focus-seam port + phantom `.w-100` removed**; **M5.3 LANDED 2026-09-08 — `.d-none`→`hidden` migration, 72 refs, `.d-none` removed from ui.css**; **M5.4 LANDED 2026-09-08 — dropdown `.show` → canonical `.dropdown-open` in app.css, ui.css state rule removed; toast `.show` untouched**; **M5.5 LANDED 2026-09-08 — accordion `.collapsed` → canonical `.accordion-open` in app.css (scoped 0,3,0), ui.css state rules removed**; **M5.6 LANDED 2026-09-08 — `.btn-close` self-ownership → app.css (plain unlayered rules, class retained, glyph/values byte-identical, `.btn-close-white` companion included; `.alert-dismissible .btn-close` parent-positioning stays with alerts); 12 static + 2 JS-created consumers byte-identical, no JS selector on the class**; **M5.7 LANDED 2026-09-08 — final utility/grid/text re-scan: 11 value-exact cascade-isolated rules migrated to existing Tailwind twins (`.gap-1/2`, `.flex-wrap`, `.top-0/.bottom-0`, `.overflow-hidden/-visible/-x-auto/-y-auto`, `.ms-auto`, `.mx-auto`), everything else retained with forensic value/layer reasons (spacing 3–5, `.rounded`, `.border`, text colors, `.align-middle`, `.bg-transparent`, d-*/position-*/fw-*, grid, tables, DEAD set classified for M6); parity 0 drift @375/768/1280**; M6 deletion gate — **AUDITED 2026-09-09 (M6.1): FAILED — deletion remains BLOCKED, see §M6.1**; **M6.2 LANDED 2026-09-09 — accordion token ownership resolved (the 7 `--ui-accordion-*` tokens consumed by the M5.5 `.accordion-open` state relocated verbatim into an app.css `.accordion` scoped block; ui.css declares none of them; real-Alpine parity 0 drift @375/768/1280; open/closed STATE survives simulated ui.css deletion; accordion *base* still ui.css-owned — see §M6.2**); **M6.3.1 LANDED 2026-09-09 — accordion base+chevron family migrated to app.css (full 22-`--ui-accordion-*` token family + base formation verbatim; ui.css now returns 0 accordion selectors/tokens; real-Alpine parity 0 drift @375/768/1280; new base props asserted equal to ui.css-derived values; deletion-sim 0 drift for the accordion family — accordion blocker row CLEARED — see §M6.3.1**); **M6.3.2 LANDED 2026-09-09 — forms family + `:root` design-token block relocated (the entire `--ui-*` Batch A `:root` block moved VERBATIM to app.css as the canonical owner — ui.css now declares 0 `--ui-*` tokens — and the whole forms family [`.form-label`/`.form-control`+states/`.form-select`+states/`.form-check-input`+checked/indeterminate/`.input-group`+children] ported VERBATIM; forms parity fixture @375/768/1280 equals every ui.css-derived expected value incl. the navy border + gold `2.8px` focus ring (0.2rem @14px root); deletion-sim 0 drift across the whole form matrix — forms blocker row CLEARED — see §M6.3.2**); **M6.3.3 LANDED 2026-09-09 — buttons base family migrated (ui.css §4.1 `.btn` base + `.btn:hover`/`:focus-visible`/`.disabled`/`:disabled` + `.btn-sm` + `.btn-primary`/`.btn-outline-primary` [navy] + `.btn-danger`/`.btn-outline-danger` [red] + `.btn.btn-gold:focus-visible` glow ported VERBATIM, APPENDED at end of app.css so every same-specificity tie with the earlier app.css brand group resolves exactly as late-loaded ui.css did — `inline-block`, 5.25/10.5px, 5.25px radius, 14px/400/1.5, `#212529`, `.15s ease-in-out`; buttons parity fixture [9 buttons × base/hover/focus/disabled @375/768/1280] 25/25 assertions equal ui.css-derived literals; deletion-sim 0 drift across the whole button matrix incl. token resolution from the app.css `:root` — buttons base blocker row CLEARED — see §M6.3.3**); **M6.3.4 LANDED 2026-09-09 — alerts + dismissible placement family migrated (ui.css §4.5 `.alert` base + `.alert-dismissible` + the `.alert-dismissible .btn-close` PLACEMENT rule [absolute 0/0, z2, 1.25rem/1rem — the parent contract M5.6 kept with alerts] + `.alert-success/-danger/-warning/-info` Bootstrap 5.3.2 emphasis variants ported VERBATIM to end-of-file app.css — `.btn-close` is now FULLY app.css-owned; alerts parity fixture [6 alerts incl. dismissible child @375/768/1280] 29/29 assertions equal ui.css-derived literals; deletion-sim 0 drift across the alert matrix — alerts blocker row CLEARED — see §M6.3.4**); **M6.3.5 LANDED 2026-09-09 — dropdown base + `.btn-group` family migrated to app.css (ui.css §4.7 `.dropdown`/`.dropdown-toggle`+`::after` caret/`.dropdown-menu` base + `[data-bs-popper]` offsets/`.dropdown-item` states ported VERBATIM to end-of-file app.css together with §4.4 `.btn-group` — INSEPARABLE: the clients/export + transactions/export dropdowns use `.btn-group` as their positioned wrapper; navbar uses `.dropdown`; all three Alpine menus still bind canonical `.dropdown-open` [M5.4], no `.show`, no `data-bs-popper` in live consumers, global-search self-contained; dropdown parity fixture [5 structures replicating both live compositions + popper variants @375/768/1280] 53/53 assertions equal the ui.css-derived literals [`.btn-group` relative/inline-flex/middle, open menu block/absolute/z1000/140px/7px/5.25px/`rgba(0,0,0,.176)`/shadow, closed display none, popper top 100%/left 0/1.75px and end right 0/left auto, item hover `#f8f9fa`/active `#0d6efd`/disabled `#adb5bd`]; deletion-sim 0 drift across the dropdown matrix — dropdown + `.btn-group` blocker rows CLEARED — see §M6.3.5**)** **M6.3.6 LANDED 2026-09-09 — list-group family migrated to app.css (ui.css §4.8 `.list-group`/`.list-group-item`+corners+adjacency collapse/`.disabled,`:disabled``/`.list-group-item-action`+`:hover`/`:focus`/`:active`/`.list-group-flush` — all 12 selectors ported VERBATIM to end-of-file app.css; consumers: ONE static flush list [students/update-photo] + FOUR runtime client-autocomplete ULs [transactions create+edit, scanners/scan, scholars/_form] adding `list-group-item list-group-item-action` via JS classList — class contract unchanged, static + runtime-created items resolve identically; no `.btn` interplay, no `--ui-list-*` tokens exist; list-group parity fixture [3 structures replicating BOTH live compositions + a disabled item @375/768/1280] 295/295 assertions equal the ui.css-derived literals [flush radius 0 + `0 0 1px` borders + last border-bottom 0, autocomplete 5.25px/`#fff`/1px `#dee2e6`, items relative/block/7px 14px/`#212529`, adjacency border-top 0, action `rgba(33,37,41,.75)`, `:hover`/`:focus` `#000`/`#f8f9fa`/z1, `:active` `#212529`/`#e9ecef`, disabled `#6c757d`/none, menu width 336px]; deletion-sim 0 drift across the list-group matrix — list-group blocker row CLEARED — see §M6.3.6**); **M6.3.7 LANDED 2026-09-09 — `.table` layout family migrated (ui.css §4.6 `.table`/`.table > :not(caption) > * > *`/`.table > tbody`/`.table > thead`/`.table-sm`/`.align-middle`/`.table-responsive` — all 7 selectors ported VERBATIM to end-of-file app.css; consumers: `.table-responsive` only at transactions/index:130; `.table` on the plain screens [sessions/online, 4 admin matrix tables, households/show] AND all 11 DataTables id-tables (`class="table table-sm"`/`table … align-middle`); `.align-middle` in 12 files all on cells — datatables.css l.457-458 scoped rule re-covers DataTables cells; element Reboot table rules [ui.css l.513-516] NOT migrated — separate family, app.css twins already exist; datatables.css UNTOUCHED [hash B82937375133CABD unchanged, B2 seam intact]; table parity fixture [plain + `table-sm` + `.table-responsive` wide + DataTables-style wrapper with real datatables.css injected in correct stack order + `.align-middle` cells @375/768/1280] 246/246 assertions equal the 14px-root literals [table 14px margin-bottom/1px `#dee2e6` border-color/collapse, cells 7px/`#000`/`#fff`/1px, `table-sm` 3.5px, `.align-middle` middle, `.table-responsive` overflow-x auto, datatables skin overrides [separate/middle/6px/.25rem/`#212529`] intact]; deletion-sim 0 drift across the table matrix — `.table` blocker row CLEARED — see §M6.3.7**); **M6.3.8 LANDED 2026-09-09 — grid family migrated (ui.css grid block [`.row`/`.row > *`/`.g-0…g-5`/`@media 576` `.col-sm-4/8`/`@media 768` `.col-md-3/4/6`, 37 lines 1288 chars incl. §4 comment header] ported VERBATIM to end-of-file app.css — the FINAL grid block, app.css had zero grid selectors before; `--ui-gutter-x/y` tokens self-contained in the block so canonical ownership moved with it; adjacent `.gap-3/4/5` spacing rules NOT moved [spacing family, M6.3.9]; consumers: static `.row` on grantee_update [self-update tab + self-service], unpaid_verifications [self-service + index], payouts/attendance, qr/viewer — exactly 4 `row g-2` consumers, `g-1/g-2` on non-row elements inert; columns `col-md-3`×24/`col-md-4`×18/`col-md-6`×8/`col-sm-4`×12/`col-sm-8`×12; 2 JS templates emit `dl.row`+`dt.col-sm-4`/`dd.col-sm-8`; no nested grids, no header grid; grid parity fixture [row g-2+md-6, plain row+md-3/md-4/md-6, nested, dl.row pairs, g-0..5 probes @375/576/768/1280] pre→post drift 0 AND deletion-sim drift 0 at every viewport, 25/25 assertions equal the 14px-root literals [row margins -10.5px default/-3.5px g-2, child padding 10.5px/3.5px, flex 0 0 auto, width ratios 0.25/0.333/0.5, dl dt 33.3%/dd 66.7%, nested 0.25, gutter tokens, full-width stack @375], sim keeps only the known aborted-stylesheet console artifact, no overflow; `rg` → 0 real grid selectors in ui.css; datatables.css untouched [full MD5 2EE627B74B0FD170727506AFD46C6C46, B2 seam intact]; prior-phase parity re-verified [buttons 25/25, alerts 29/29, dropdown 53/53, list-group 295/295, table 246/246, accordion/forms 0 drift]; git diff --check clean, view:cache OK, Pint passed, PHPUnit 301/1419/0; deletion-sim 0 drift across the grid matrix — grid blocker row CLEARED — see §M6.3.8**); **M6.3.9 LANDED 2026-09-09 — ALL remaining utility families migrated (ui.css §4.9; this absorbed the previously-planned M6.3.10 text/display/position utilities so the next remaining family is the modal base = M6.3.10): 63 rules ported VERBATIM to end-of-file app.css with Bootstrap `!important` retained (d-inline/d-flex, justify-content-center/-between, align-items-center/-end, position-relative/-absolute, text-start/text-center, text-primary/secondary/success/danger/warning/info/muted/white, fs-4, small, min-vh-100, w-auto, img-fluid/img-thumbnail, bg-white/bg-transparent, border/border-2, rounded/rounded-pill, m-0/mt-0…5/mb-0…4/ms-1/2, p-1…4/pt-3/pb-1/3/px-0/1/3/4/5/py-1…5, gap-3/4) — even the value-exact Tailwind twins (text-center/text-white/bg-white/bg-transparent/m-0/mb-0…4/mt-1/2/ms-1/2/p-1…4/px-0/1/3/4/5/py-2…5/w-auto/rounded-pill) because they carry `!important` and retiring would require dropping the flag + proving no competing unlayered declaration (byte-verbatim port guarantees parity by construction); 60 rules REMOVED as proven DEAD (0 Blade + 0 JS + 0 compiled-twin: d-block/d-inline-flex/flex-nowrap/justify-content-start/-end/-around/align-items-start/me-auto/position-static/-fixed/-sticky/text-end/text-body/fw-light/-normal/-semibold/-bold/fst-italic/overflow-auto/border-1/-3/rounded-1/-2/-3/-circle/gap-0/-5/me-0/-1/-2/ms-0/-3/mx-0…3/my-0…3/mt-auto/mb-auto/mb-5/p-0/-5/pt-0/-1/-2/-4/-5/pb-0/-2/-4/-5/ps-0…3/px-2/py-0); no RETIRE-to-Tailwind; ported rules are unlayered `!important` beating the `@layer utilities` twins exactly as ui.css-last did; parity harness @375/576/768/1280 CURRENT vs deletion-sim drift 0 (utils + the M6.3.8 grid slot re-probed), grid slot vs M6.3.8 baseline drift 0, sim chain post638→now 0, docOverflow 0, only the known aborted-stylesheet console artifact in sim; `rg` → 0 real utility selectors in ui.css; datatables.css untouched (full MD5 2EE627B74B0FD170727506AFD46C6C46, B2 seam intact); ui.css 662→483 lines, app.css 2488→2595; build app-PXXoPz1A.css 80.08 kB, JS unchanged; prior-phase parity re-verified; git diff --check clean, view:cache OK, Pint passed, PHPUnit 301/1419/0 — utilities blocker row CLEARED — see §M6.3.9**)
- [ ] M6 static: `resources/views|js`, `public/js`, `build/assets` grep clean; 8 `<link>`s + `ui.css` the only deletions
- [ ] M6 live: PHPUnit green, build/view:cache/Pint clean, served pages 200 + 0 console errors + no overflow, DataTables pagination colors intact
- [ ] Docs: `IMPLEMENTATION_LOG.md` entries per step; plan sections §J/§I updated; this doc's status flipped to DONE (or archived)

---

## M6.1 — Deletion-readiness gate audit (2026-09-09, AUDIT ONLY)

Opened the §9 M6 deletion gate and executed it exactly as written — full inventory, per-family
ownership against the compiled bundle, and a **real browser simulated deletion** (Chromium
`page.route('**/css/ui.css', r => r.abort())` vs normal load, nothing else changed) across
375/768/1280 on `/login`, `/qr-viewer`, `/grantee-update`, `/unpaid-verification`, plus an
injected parity harness for the auth-gated shell (sidebar/navbar dropdown/accordion/list-group/
alert-dismissible/table/grid) and DataTables filter+length+paginate chrome.

**Verdict: NOT SAFE. The M6 gate FAILS — `ui.css` deletion remains BLOCKED.**
No deletion, no unlink, no application change (all 8 `<link>`s and `ui.css` intact).

### Verdict input evidence (measured, not assumed)

- **Inventory:** 333 rules; **94 of 285 simple classes have ≥1 live consumer** (21 with exactly
  1); 191 dead. Heaviest live families: `mb-2` (116), `rounded` (101), `text-center`/`text-danger`
  (63 each), `form-select` (52), `form-check`/`data-card-body` (37), `mb-3` (32), `text-white`
  (28), `py-2` (27), `col-*` grid (54 `col-md-*`), `alert*` (16–19), `btn-close` (14),
  `accordion*` (10), `list-group*` (11); complex selectors reach 31 screens.
- **Ownership:** app.css/build owns only the M5.x ported set (btn-variant group, btn-close self,
  dropdown-open/accordion-open states, metric/data-card/status-badge, h1–h6, focus/reduced-motion,
  matching value twins). Everything else (btn base, forms, alerts, dropdown base, accordion base
  + chevron, list-group, `.table` layout, grid, utilities with live consumers, spacing 3–5) is
  **ui.css-only** — none of it is emitted by the build.
- **Simulated deletion drift (real browser):** 0 console/page errors, 0 overflow on all 12
  combos, and the global `:focus-visible` gold outline survives — but every retained family
  drifts: `.btn` → UA-default button (radius 0, `cursor` fallback, grey bg); `.form-control/-
  select` → intrinsic width, radius 0, `appearance` auto; `.input-group` flex→block;
  `.alert*`/`.alert-dismissible .btn-close` → static flow, palette lost, close button unplaced;
  `.dropdown-menu/-item` → static, transparent, z-index auto; `.accordion-*` → radius/padding/
  colors lost, button font-size 14px⇒22–28px; `.list-group*` flex→block; `table.table`
  width 100%⇒auto; `.row/.col-*/.g-*` → grid collapses to stacked blocks; utilities `text-danger`,
  `d-flex`, `position-relative` vanish; spacing-3–5 twins value-drift (`.mt-3` 14px⇒10.5px,
  `.rounded` 5.25px⇒3.5px). `.dataTables_wrapper .page-link` navy drift is harness-only — real DT
  screens are covered by datatables.css (`var(--color-navy)`) and ui.css `.page-link` has 0
  non-DT consumers (DEAD family, SAFE).
- **`--ui-*` token audit:** 67 `:root` tokens; 58 self-used; **7 consumed BY app.css** (the M5.5
  `.accordion-open` state uses `--ui-accordion-active-color/-bg/-border-width/-border-color/
  -btn-active-icon/-btn-icon-transform/-inner-border-radius`, declared only in ui.css `:root`) —
  deletion would invalidate the migrated active-state rule on the single live accordion
  (`clients/_gip`). 29 tokens unused anywhere (M6.2 cleanup).
- **Guards M5.2–M5.7:** PASS (ui.css: 0 `.dataTables_*`, 0 `.d-none`, 0 `.show{`, 0 `.collapsed{`,
  0 `.btn-close{` self; build: 0 `.w-100`, 0 `.show{`, 0 `.collapsed{`; datatables.css
  self-contained, 595 L LF, B2 seam + `--color-navy`/`--shadow-focus`).
- **e2e:** `alpine-phase0` PASS (login boots, Bootstrap CSS removed); `smoke.spec` 4× FAIL at
  `signIn` only — `smoke_superadmin` seed absent from the local `main_system` copy (stays on
  `/login`); environment gap, not CSS.
- **PHPUnit 301 / 1419 / 0** (unchanged). Intercept validity proven: drift confined to
  ui.css-only families while app.css twins were byte-identical.

### M6 blocking matrix (per family / verdict)

| Family | Owner after deletion | Sim drift (measured) | Verdict |
|---|---|---|---|
| `.btn` base + `.btn-primary/-outline-primary/-danger/-outline-danger/-sm`, `.btn-group` | none | UA button, radius 0, no pointer cursor | **BLOCKED** |
| `.btn-gold/-navy/-red/-subtle/-outline(-red)` | app.css group | identical | SAFE (B) |
| `.btn-close` self + white | app.css (M5.6) | identical | SAFE (B) |
| `.form-control/-select/-check-input/-sm`, `.input-group*` | none | width/radius/appearance/borders collapse (31 screens) | **BLOCKED** |
| `.alert*`, `.alert-dismissible .btn-close` placement | none | palette/placement lost | **BLOCKED** |
| `.dropdown-menu/-item/-toggle` base | app.css only `display:block` token | static/transparent/z-auto | **BLOCKED** |
| `.accordion*` base + `::after` chevron + 7 `--ui-accordion-*` tokens | (open-state rule in app.css BROKEN by token loss) | radius/padding/chevron gone; 22–28px font; active-state rule invalid | **BLOCKED** |
| `.list-group*` | none | flex→block, item chrome gone (5 inject sites) | **BLOCKED** |
| `.table`, `.table-responsive` | `table{display:table}` only | width 100%⇒auto, margin 0 | **BLOCKED** |
| `.row/.col-sm-*/.col-md-*/.g-*` | none | grid collapse (every CRUD form + dashboard) | **BLOCKED** |
| `.page-link`/`.page-item.active` (navy) | datatables.css on DT; 0 non-DT consumers | harness-only drift | SAFE (D) |
| matching-value twins (`.text-center`, `.text-white`, `.align-middle`, `.mb-2`, `.py-2`, `.border-2`, `.w-auto`, `.ms-1/-2`, spacing 0–2, `px-0/1/4`, `rounded-pill`) | Tailwind twin | identical | SAFE (B) |
| spacing 3–5 / `.rounded` / `.px-3` / `.py-3` / `.gap-3/-4` etc. | Tailwind twin | value drift 14px⇒10.5px, 5.25⇒3.5px | **BLOCKED** |
| `text-danger/-warning/-muted/-primary…`, `d-*`, `position-*`, `fw-*` (live consumers), `.img-thumbnail`, `.small`, `fs-4` | none | class vanishes | **BLOCKED** |
| DEAD set (191 classes, 0 consumers) | — | — | SAFE (D) |
| DataTables chrome | datatables.css (B1/B2/B3 self-contained) | identical | SAFE (F) |

### M6.2 recommendation (NOT STARTED)

> **FULFILLED BELOW — the focused accordion token-ownership phase (M6.2, 2026-09-09) ran first
> as a standalone controlled change and LANDED; the remaining items 2–5 (component-family
> migration, spacing drift, DEAD-set deletion, re-run with auth shell) stay future phases.**

1. Port the 7 `--ui-accordion-*` tokens into `@theme static` (remap by value, §6.1) and re-verify
   the `.accordion-open` rule; decide the fate of the remaining 60-token `:root` block
   (58 self-used within families that must move with them).
2. Port or Blade-rewrite (with the §6.2 `@source` allowlist rule) in dependency order:
   accordion base+chevron → forms → buttons base → alerts+dismissible placement → dropdown base →
   list-group → `.table` layout → grid (M4.3 gutter model revisited) → live utilities.
3. Resolve spacing-3–5 / `.rounded` drift per §6.4 with explicit paddings on unmigrated screens.
4. Delete the DEAD set (191 classes) on the same change as M6.2.1–3.
5. Re-run THIS gate with an authenticated-shell pass once a test account exists (§6.5 residual).

---

## M6.2 — Accordion token ownership resolution (2026-09-09, LANDED)

Focused follow-through on the M6.1 blocker: the M5.5 `.accordion-open` state rules in app.css
consumed seven `--ui-accordion-*` tokens that only `ui.css` declared. M6.2 establishes canonical
ownership in app.css for exactly those seven, then removes exactly those seven declarations from
ui.css. This is NOT a deletion phase and NOT a component migration.

**Correction to the M6.1 token record:** the seven tokens are declared on **`ui.css` §4.8
`.accordion`** (scoped, not `:root`) — the M6.1 wording said `:root`. Values, consumers, and the
M6.2 relocation are unaffected; the exact scope is preserved in the move.

### Token inventory

| Token | Value (verbatim) | Old owner | New owner | Consumers |
|---|---|---|---|---|
| `--ui-accordion-active-color` | `#084298` | ui.css `.accordion` §4.8 | app.css `.accordion` (M6.2) | app.css `.accordion-open` color |
| `--ui-accordion-active-bg` | `#cfe2ff` | ui.css `.accordion` §4.8 | app.css `.accordion` (M6.2) | app.css `.accordion-open` fill |
| `--ui-accordion-border-width` | `1px` | ui.css `.accordion` §4.8 | app.css `.accordion` (M6.2) | app.css `.accordion-open` inset calc; ui.css §4.8 `.accordion-item` border |
| `--ui-accordion-border-color` | `#dee2e6` | ui.css `.accordion` §4.8 | app.css `.accordion` (M6.2) | app.css `.accordion-open` inset; ui.css §4.8 `.accordion-item` border |
| `--ui-accordion-btn-active-icon` | `url("… fill='%23084298' …")` | ui.css `.accordion` §4.8 | app.css `.accordion` (M6.2) | app.css `.accordion-open::after` bg |
| `--ui-accordion-btn-icon-transform` | `rotate(-180deg)` | ui.css `.accordion` §4.8 | app.css `.accordion` (M6.2) | app.css `.accordion-open::after` transform |
| `--ui-accordion-inner-border-radius` | `calc(.375rem - 1px)` | ui.css `.accordion` §4.8 | app.css `.accordion` (M6.2) | app.css `:not(.accordion-open)` corner; ui.css §4.8 first-of-type inner radius |

ui.css now declares **none** of the seven (15 other `--ui-accordion-*` tokens remain there).
ui.css base-formation references to the moved tokens (`border-width`/`-color` on `.accordion-item`,
`inner-border-radius` on first-of-type) resolve via inheritance from the app.css `.accordion`
scope. `--ui-accordion-inner-border-radius` keeps depending on the ui.css-held
`--ui-accordion-border-radius` until the base migration.

### Source changes (exactly two application files)

- `resources/css/app.css` — canonical `.accordion { … }` token block above the M5.5 rules; same
  names, same byte-identical values, same `.accordion` scope; no `!important`, no layer change,
  no source-order change (`@vite` → `ui.css` → screen styles unchanged).
- `public/css/ui.css` — the seven declarations removed from §4.8 `.accordion` (+ pointer
  comment). Nothing else touched.

### Verification

- **Parity:** real Alpine + `@alpinejs/collapse` (actual built JS) on `/login`, exact `_gip`
  markup/component; closed→open→closed + Enter→Space→Enter at 375/768/1280. Baseline (pre-change
  build) vs post-change: **0 computed-style drift**, 0 console errors, 0 overflow. Final values:
  `rgb(8,66,152)` / `rgb(207,226,255)` / inset `rgb(222,226,230) 0px -1px 0px 0px` /
  `rotate(-180deg)` matrix / closed corner `4.25px` (= calc at 14px root) / focus ring 3.5px.
- **Deletion simulation:** `ui.css` aborted post-change — the accordion **open and closed STATE**
  survive identically (token-driven color/bg/inset/active chevron/rotation/corner + panel
  behavior); the **base formation** (`.accordion-button` box/typography, `.accordion-item` border,
  `::after` content/size/transition, focus ring) still comes from ui.css §4.8 — that base family
  is the remaining M6 blocker and was **NOT** migrated (scope). Pseudo nuance: computed `transform`
  reads `none` on the non-generated `::after` (base `content` absent), but force-generating the
  pseudo returns the identical `rotate(-180deg)` — the token-owned declaration resolves.
- **Build:** `app-D8NYbDco.css` (61.57 kB), 7 canonical tokens + all M5.5 rules + `.hidden`/
  `.dropdown-open`/`.btn-close` present; JS unchanged.
- **Regression:** `git diff --check` clean; `view:cache` OK; Pint passed; PHPUnit **301/1419/0**.
- **M5 guards:** M5.2 datatables.css B2 seam, M5.3 `.hidden`, M5.4 `.dropdown-open`, M5.5
  `.accordion-open`, M5.6 `.btn-close` self-ownership, M5.7 eleven migrated utilities — all intact.

### Status after M6.2

**M6.2 COMPLETE. M6.3 (family migrations) NOT STARTED. `ui.css` not deleted; all 8 `<link>`s
intact. No dead-token cleanup, no further token moves, no Component-family migration.** The
deletion gate remains BLOCKED until the remaining families (§M6.1 matrix: buttons base, forms,
alerts, dropdown base, accordion **base**+chevron, list-group, tables, grid, spacing-3–5
utilities, text/display/position utilities, modal base) are owned by app.css or rewritten.

---

## M6.3.1 — Accordion base+chevron family migration to app.css (2026-09-09, LANDED)

First retained-family migration per the §M6.1 dependency order (accordion base+chevron first).
Completes the accordion family begun by M5.5 (open-state) and M6.2 (state tokens): the whole
base formation is now app.css-owned and **self-contained against ui.css deletion**.

### Source changes (exactly two application files)

- `resources/css/app.css` — the `.accordion` token block grew to the full **22-token** family (the
  remaining 15 `--ui-accordion-*` tokens joined the M6.2 seven **verbatim**), and a new
  **M6.3.1 base-formation section** was appended after the M5.5 open-state rules, porting the whole
  ui.css §4.8 formation **verbatim**: `.accordion-button`, `.accordion-button:focus`,
  `.accordion-button::after` (chevron `content`/SVG/size/repeat/transition), `.accordion-header`,
  `.accordion-item`, `:first-of-type` (+ inner button corners), `:not(:first-of-type)`,
  `:last-of-type` (+ `.accordion-collapse` radius), `.accordion-body`. Selectors/order/values
  byte-identical to the ui.css source; M5.5 rules untouched.
- `public/css/ui.css` — the 15-token `.accordion` block + all base rules removed from §4.8; §4.8
  header comment trimmed to `.form-label` + list-groups. `--ui-accordion-*` custom props and
  `.accordion*` selectors now return **0** in ui.css.

### Verification

- **Build** `app-DspRAT0c.css` (64.27 kB; JS unchanged): 22/22 accordion tokens + every base
  selector present; `.hidden`/`.dropdown-open`/`.btn-close`/`.accordion-open` retained.
- **Parity (Gate 1):** post-change `current` vs pre-change M6.2 real-browser capture — **0 computed
  drift** @375/768/1280 over every shared prop (button/chevron/panel/item, all 6 states).
- **New base props** (added to the harness, not in the M6.2 capture) asserted equal to their
  ui.css-derived expected values AND identical in full-load vs deletion-sim: button `transition`,
  `overflow-anchor:none`, `align-items:center`, `::after` `flex-shrink:0`/`background-repeat:
  no-repeat`/`content:""`, `.accordion-body` padding `14px 17.5px 14px 17.5px` (1rem/1.25rem @14px
  root — ported base still beats the later `p-[1rem]` twin exactly as late-loaded ui.css did),
  `.accordion-header` margin-bottom `0`.
- **Deletion sim (Gate 2):** with `ui.css` aborted, the accordion now renders **0 drift** vs
  full-load (border, chevron, body padding, corners, typography all intact). Only sim delta = the
  pre-existing aborted-stylesheet console artifact (1 error/viewport, identical to pre-change
  baseline). **Accordion blocker row CLEARED.**

### Status after M6.3.1

**M6.3.1 COMPLETE.** Remaining blocker families: buttons base, forms, alerts + dismissible
placement, dropdown base, list-group, `.table` layout, grid, spacing-3–5/.rounded drift,
text/display/position utilities, modal base. `ui.css` retained and linked; deletion gate still
BLOCKED. **Next in dependency order: M6.3.2 forms family.**

---

## M6.3.2 — Forms family + ui.css `:root` design-token block relocation (2026-09-09, LANDED)

Second retained-family migration and the resolution of the plan's flagged "fate" of the remaining
design-token `:root` block. Two stages:

- **Stage A — `:root` design-token block relocated VERBATIM.** The entire `--ui-*` Batch A block
  (ui.css §1, historical lines 24–131; `--ui-navy` … `--ui-focus-ring`) moved byte-for-byte into
  `resources/css/app.css` as a plain unlayered `:root { … }` rule right after `@theme static`.
  app.css is now the canonical owner; ui.css declares **zero** `--ui-*` tokens. Values were NOT
  remapped onto the `@theme` `--color-*`/`--shadow-*`/`--text-*` equivalents — verbatim parallel
  names keep every still-ui.css component family (buttons gold focus `--ui-focus-ring`,
  `.btn-outline-danger` `--ui-red`, pagination `--ui-navy-light`/`--ui-focus-ring`, forms) resolving
  identically; the `--shadow-focus` === `--ui-focus-ring` overlap is preserved as a byte-faithful
  duplicate.
- **Stage B — forms family ported VERBATIM.** ui.css §4.2 (`.form-control` family incl.
  `[type=file]`/`:focus`/`::placeholder`/`:disabled`/`::file-selector-button`/`.form-control-sm`;
  `.form-select` incl. `:focus`/`[multiple]`/`:disabled`/`.form-select-sm`; `.form-check`/
  `.form-check-input` incl. `:active`/`:focus`/`:checked` glyphs/`:indeterminate`/`:disabled`;
  `.input-group` + children + `.input-group-text`) and the `.form-label` rule (ui.css §4.8) moved
  verbatim into a new forms-family section in app.css before the M5.6 `.btn-close` section. Class
  names (`.form-control`/`.form-select`/`.form-check-input`) unchanged — zero blade/JS edits. The
  app focus identity (navy border + gold ring) traveled with the rules via the relocated tokens.

### Source changes (exactly two application files)

- `resources/css/app.css` — new `:root` design-token block + new forms-family section.
- `public/css/ui.css` — `:root` block **removed** (pointer comment), §4.2 + `.form-label`
  **removed** (pointer comment), §4.8 comment updated (forms no longer listed). Form selectors now
  return **0** in ui.css; `--ui-*` declarations now **0**. All other retained families untouched.

### Verification

- **Build** `app-uoIBrx_c.css` (**71.18 kB**, from 64.27 kB — +6.9 kB = `:root` block + forms
  family); JS unchanged. All forms selectors/states + `.input-group` + relocated `--ui-*` tokens
  present in the compiled output; `.hidden`/`.dropdown-open`/`.btn-close`/`.accordion-open`
  retained.
- **Authoring-bug caught by the build gate (recorded for provenance):** the new forms header
  comment was initially missing its closing `*/`, which swallowed `.form-label`→`.form-check` as
  comment content (isolated with a LightningCSS repro; fixed by closing the comment; rebuild
  restored the family).
- **Parity (Gate 1):** `forms-parity.mjs` full-form fixture on real `/login` @375/768/1280 — every
  measured value equals its ui.css-derived expected literal, incl. `.form-control` padding
  `5.25/10.5px`, 400 weight, border `#dee2e6`; `.form-select` arrow data-URI + `background-size
  16px 12px` + `padding-right 31.5px`; sm variants 12.25px/3.5px; `::placeholder` `#6c757d`;
  `::file-selector-button` padding + `border-inline-end-width 1px`; **`:focus` = navy-light
  `rgb(22,74,156)` + gold ring `0 0 0 2.8px rgba(252,209,22,.3)` (0.2rem @14px root)**;
  `:disabled` `#e9ecef`; `.form-check-input` 1em, checkbox 3.5px / radio `50%` radius;
  `:checked` navy + check/circle SVG glyphs; `:indeterminate` navy + dash glyph; `.input-group-text`
  `#e9ecef` + radius stream; `.input-group > .form-control` `flex 1 1 auto` + `min-width 0`;
  `.form-check` 21px inset; `.form-label` margin-bottom 7px.
- **Deletion sim (Gate 2):** `ui.css` aborted — **0 computed-style drift** across the whole
  fixture/state matrix @375/768/1280; only delta = the pre-existing aborted-stylesheet console
  artifact (1 error/viewport, identical to M6.2/M6.3.1 baselines). **Forms blocker row CLEARED.**
- M5.2–M5.7 guards re-verified PASS; `git diff --check` clean; `view:cache` OK; Pint passed;
  **PHPUnit 301 / 1419 / 0.**

### Status after M6.3.2

**M6.3.2 COMPLETE — forms family + the design-token block are app.css-owned and self-contained
against ui.css deletion.** Remaining blocker families: buttons base, alerts + dismissible
placement, dropdown base, list-group, `.table` layout, grid, spacing-3–5/.rounded drift,
text/display/position utilities, modal base. `ui.css` retained and linked; deletion gate still
BLOCKED. **Next in dependency order: M6.3.3 buttons base family.**

---

## M6.3.3 — Buttons base family migration to app.css (2026-09-09, LANDED)

Third retained-family migration per the §M6.1 dependency order (buttons base). Ported the whole
ui.css §4.1 family **verbatim**: `.btn` base, `.btn:hover` (currentColor edge), `.btn:focus-visible`
(outline-free), `.btn.disabled`/`.btn:disabled`, `.btn-sm`, the structural-navy variant families
`.btn-primary`/`.btn-outline-primary` (`--ui-navy` source), the flag-red destructive families
`.btn-danger`/`.btn-outline-danger` (`--ui-red` + darker `#A80F20`/`#900C1B` stops), and the
`.btn.btn-gold:focus-visible` keyboard-focus glow. The brand variants (`.btn-gold/.btn-navy/
.btn-red/.btn-subtle/.btn-outline-red/.btn-outline`) were already app.css-owned self-contained
rules; §4.4 `.btn-group` deliberately STAYS ui.css-owned (moves with the dropdown family).

### Placement decision (cascade-critical)

The ported block is **appended after every existing app.css rule** rather than woven into the
brand section, because pre-port the ui.css `.btn` base (0,1,0) loaded *after* this bundle and so
won every equal-specificity tie against the (0,1,0) brand group — `inline-block`, 5.25/10.5px
padding, 5.25px radius, 14px/400/1.5 typography, `#212529` color, 1px transparent border, `.15s
ease-in-out` transition — including on `btn btn-gold`/`btn btn-navy` combos. Appending last
reproduces that later-rule-wins outcome bit-for-bit; Tailwind Preflight/utilities lose to these
unlayered rules exactly as they did to late-loaded ui.css.

### Source changes (exactly two application files)

- `resources/css/app.css` — M6.3.3 buttons-base section appended at the end (after the M5.6
  `.btn-close` self-contract), banner comment + verbatim §4.1 text.
- `public/css/ui.css` — §4.1 header + all button rules removed; pointer comment added
  (0 button selectors remain). All other retained families untouched.

### Verification

- **Build** `app-QWOXOZW9.css` (**73.95 kB**, from 71.18 kB; JS unchanged): every `.btn*`
  selector/state present incl. the gold-focus glow; LightningCSS re-encodes the focus rings to
  8-digit hex in compiled output (`#0038a880`/`#ce112680`/`#dcb40080`) at equal resolved values.
- **Parity (Gate 1):** `buttons-parity.mjs` — 9-button fixture on real `/login` (base, primary,
  primary-sm, outline-primary, danger, outline-danger, gold, navy, disabled) × base/hover/focus/
  disabled states @375/768/1280; **25/25 assertions equal the ui.css-derived expected literals**
  (`.btn` `inline-block` + 5.25/10.5px + 5.25px radius + `rgb(33,37,41)`; `.btn-primary`
  `rgb(0,56,168)` → hover `rgb(22,74,156)`; `.btn-sm` 12.25px/3.5px; `.btn-danger`
  `rgb(206,17,38)` → hover `rgb(168,15,32)`; outline variants fill navy/red on hover; disabled
  0.65/none).
- **Deletion sim (Gate 2):** `ui.css` aborted — **0 computed-style drift** across every button ×
  state @375/768/1280 incl. token resolution from the M6.3.2 app.css `:root`; only delta = the
  pre-existing aborted-stylesheet console artifact (1 error/viewport). **Buttons base blocker row
  CLEARED.**
- M5.2–M5.7 guards re-verified PASS; `git diff --check` clean; `view:cache` OK; Pint passed;
  **PHPUnit 301 / 1419 / 0.**

### Status after M6.3.3

**M6.3.3 COMPLETE — the buttons base family is app.css-owned and self-contained against ui.css
deletion.** Remaining blocker families: alerts + dismissible placement, dropdown base,
list-group, `.table` layout, grid, spacing-3–5/.rounded drift, text/display/position utilities,
modal base. `ui.css` retained and linked; deletion gate still BLOCKED. **Next in dependency
order: M6.3.4 alerts + dismissible placement.**

---

## M6.3.4 — Alerts + dismissible placement family migration to app.css (2026-09-09, LANDED)

Fourth retained-family migration per the §M6.1 dependency order. Ported the whole ui.css §4.5
alert family **verbatim**: `.alert` base (relative, `1rem` padding, `1rem` margin-bottom,
transparent bg/border, `.375rem` radius), `.alert-dismissible` (`3rem` padding-right), the
**`.alert-dismissible .btn-close` placement rule** (absolute 0/0, z-index 2, `1.25rem 1rem`
padding — the `.alert` parent positioning contract M5.6 intentionally left in ui.css), and the
Bootstrap 5.3.2 emphasis variants `.alert-success/-danger/-warning/-info`. With this move the
`.btn-close` family is **fully app.css-owned** (M5.6 self contract + this placement).

### Source changes (exactly two application files)

- `resources/css/app.css` — alerts section APPENDED at end of file (after the M6.3.3 buttons
  base); the M5.6 `.btn-close` header comment updated (alerts-family placement now lives here;
  the pre-existing `0,3,0` specificity typo corrected to `0,2,0`).
- `public/css/ui.css` — §4.5 removed; pointer comment added (0 alert selectors remain). All
  other retained families untouched.

### Verification

- **Build** `app-D9Tw64t-.css` (**74.51 kB**, from 73.95 kB; JS unchanged): all seven alert
  selectors present incl. the dismissible placement rule; LightningCSS collapses the padding
  shorthand.
- **Parity (Gate 1):** `alerts-parity.mjs` — six-alert fixture on real `/login` incl. a
  dismissible `.alert-danger` with a `.btn-close` child @375/768/1280; **29/29 assertions equal
  the ui.css-derived expected literals** (`.alert` 14px/14px/5.25px + transparent; four emphasis
  variants' exact color triples; `.alert-dismissible` padding-right 42px; close button
  absolute/0/0/z2/17.5×14px with the M5.6 14×14px self box intact).
- **Deletion sim (Gate 2):** `ui.css` aborted — **0 computed-style drift** across every alert ×
  prop @375/768/1280; only delta = the pre-existing aborted-stylesheet console artifact (1
  error/viewport). **Alerts blocker row CLEARED.** The `.btn-close` family is now fully
  app.css-owned.
- M5.2–M5.7 guards re-verified PASS; `git diff --check` clean; `view:cache` OK; Pint passed;
  **PHPUnit 301 / 1419 / 0.**

### Status after M6.3.4

**M6.3.4 COMPLETE — the alerts family (incl. the dismissible `.btn-close` placement) is
app.css-owned and self-contained against ui.css deletion; `.btn-close` fully app.css-owned.**
Remaining blocker families: dropdown base (+ §4.4 `.btn-group`), list-group, `.table` layout,
grid, spacing-3–5/.rounded drift, text/display/position utilities, modal base. `ui.css` retained
and linked; deletion gate still BLOCKED. **Next in dependency order: M6.3.5 dropdown base.**

---

## M6.3.5 — Dropdown base + `.btn-group` migration to app.css (2026-09-09, LANDED)

Fifth retained-family migration per the §M6.1 dependency order. Ported the ui.css §4.7 dropdown
family **verbatim**: `.dropdown` (position relative), `.dropdown-toggle` (nowrap) + `::after`
caret + `:empty::after`, the `.dropdown-menu` base (absolute/z-index 1000/display none/`10rem`
min-width/`.5rem 0`/0 margin/`1rem`/`#212529`/`#fff`/padding-box/1px `rgba(0,0,0,.175)`/`.375rem`/
`0 .5rem 1rem rgba(0,0,0,.15)` shadow), the popper offsets `.dropdown-menu[data-bs-popper]`
(top 100%/left 0/`.125rem` mt) and `.dropdown-menu-end[data-bs-popper]` (right 0/left auto), and
the six `.dropdown-item` rules (base, `:hover`/`:focus`, `.active`/`:active`,
`.disabled`/`:disabled`).

**`.btn-group` shipped with this phase — inseparability proven.** §4.4 `.btn-group` is the
positioned wrapper of 2 of the 3 live Alpine dropdowns (clients/export, transactions/export):
its `position:relative; display:inline-flex; vertical-align:middle` provides the
absolutely-positioned `.dropdown-menu` its containing block / flex static-position context, and
its radius-join rules reference `.dropdown-toggle` at the selector level. The navbar user menu
uses `.dropdown` for the same role (M6.3.5's §4.7 port covers it).

### Source changes (exactly two application files)

- `resources/css/app.css` — M6.3.5 dropdown + `.btn-group` section APPENDED at end of file
  (after M6.3.4 alerts; `.btn-group` block then dropdown block, both byte-identical incl. their
  §4.4/§4.7 header comments); M5.4 `.dropdown-menu.dropdown-open` (0,2,0) still wins the display
  conflict over the moved `.dropdown-menu` base (0,1,0) by specificity; M5.4 header comment
  reworded to name the M6.3.5 section as the base's owner.
- `public/css/ui.css` — §4.4 + §4.7 removed (pointer comments; 0 dropdown / 0 `.btn-group`
  selectors remain); §4.x index paragraph updated to the current ownership (M6.2→M6.3.5 migrated
  vs still-owned set); M5.7 comment updated (its `.dropdown-menu`/`.btn-close` top/bottom
  component examples now app.css-owned).

### Verification

- **Build** `app-Cc-6A-2z.css` (**76.11 kB**, from 74.51 kB; JS unchanged): `.btn-group` base +
  all dropdown selectors incl. popper offsets + caret present; braces-balanced both files
  (288/288, 234/234); both inserted banner comments closed (M6.3.2 authoring-gate lesson).
- **Parity (Gate 1):** `dropdown-parity.mjs` — five-structure fixture on real `/login`
  replicating BOTH live compositions (`.dropdown` navbar wrapper; `.btn-group > .btn-subtle
  .dropdown-toggle + .dropdown-menu.dropdown-menu-end.dropdown-open` export wrapper) plus
  `[data-bs-popper]`/end variants and a closed menu @375/768/1280; **53/53 assertions equal the
  ui.css-derived literals** (`.btn-group` relative/inline-flex/middle; open menu
  block/absolute/z1000/140px/7px/1px `rgba(0,0,0,.176)`/5.25px/`rgba(0,0,0,.15) 0px 7px 14px 0px`;
  closed menu **display none**; popper `top:100%`/left 0/1.75px mt + end right 0/left auto; item
  hover `#f8f9fa`/active `#0d6efd`/disabled `#adb5bd`).
- **Deletion sim (Gate 2):** `ui.css` aborted — **0 computed-style drift** across every dropdown
  prop @375/768/1280 (only the pre-existing aborted-stylesheet console artifact, 1 error/
  viewport). Geometry compares menu↔wrapper deltas + self widths/heights (absolute static-position
  `y` is page-flow dependent and shifts when unrelated retained ui.css families are aborted — not
  a family regression). **Dropdown + `.btn-group` blocker rows CLEARED.**
- **Consumer/selector guards:** `rg` — 0 dropdown/`.btn-group` selectors in ui.css (comment text
  only), no `.show` reintroduced; `.dropdown-open` canonical (M5.4) untouched; prior-phase parity
  re-verified (buttons 25/25, alerts 29/29, accordion 0 drift vs M6.2 baseline, forms 0 style
  drift — its diff only flags the M6.3.2-era capture's missing console-error counter, the known
  artifact).
- M5.2–M5.7 guards re-verified PASS; `git diff --check` clean; `view:cache` OK; Pint passed;
  **PHPUnit 301 / 1419 / 0.**

### Status after M6.3.5

**M6.3.5 COMPLETE — the dropdown family incl. `.btn-group` is app.css-owned and self-contained
against ui.css deletion.** Remaining blocker families: list-group, `.table` layout, grid,
spacing-3–5/.rounded drift, text/display/position utilities, modal base. `ui.css` retained and
linked; deletion gate still BLOCKED. **Next in dependency order: M6.3.6 list-group.**

---

## M6.3.6 — List-group family migration to app.css (2026-09-09, LANDED)

Sixth retained-family migration per the §M6.1 dependency order. Ported the ui.css §4.8 list-group
family **verbatim**: `.list-group` (flex column, padding-left 0, margin-bottom 0, `.375rem`
radius), `.list-group-item` (relative/block/`.5rem 1rem`/`#212529`/no underline/`#fff`/1px
`#dee2e6`), the corner-inheritance `:first-child`/`:last-child`, the adjacency collapse
`.list-group-item + .list-group-item` (border-top-width 0), the `.disabled,`:disabled`` state
(`#6c757d`/pointer-events none/`#fff`), `.list-group-item-action` (width 100%/
`rgba(33,37,41,.75)`/text-align inherit + `:hover`/`:focus` z-index 1/`#000`/`#f8f9fa` + `:active`
`#212529`/`#e9ecef`), and `.list-group-flush` (radius 0, item border `0 0 1px`, last-item
border-bottom 0).

**Consumers stay untouched:** ONE static consumer — `students/update-photo` (flush list with
`d-flex justify-content-between align-items-center bg-transparent px-0` items + `.btn-gold` link;
no `.btn` interplay — list items are never buttons). FOUR runtime consumers add
`list-group-item list-group-item-action` via JS `classList` on client-autocomplete `<ul>`s —
`transactions/create`, `transactions/edit`, `scanners/scan`, `scholars/_form` — identical
class-name contract, so static markup AND runtime-created items resolve identically. Inline
`position-absolute` + `width:min(24rem,100%)`/`max-height:150px`/`overflow-y:auto`/`z-index:1000`
container styles unchanged; no tabindex/role added (the `:focus` half of `:hover,:focus` stays
unreachable live, exactly as v1). `rg` proves 0 `--ui-list-*` tokens exist anywhere.

### Source changes (exactly two application files)

- `resources/css/app.css` — M6.3.6 list-group section APPENDED at end of file (after M6.3.5
  dropdown; all 12 selectors byte-identical, source order preserved so the equal-specificity
  border/radius ties resolve exactly as late-loaded ui.css did); closed banner comment.
- `public/css/ui.css` — §4.8 header comment + the whole list-group block replaced by a pointer
  comment (0 list-group selectors remain); §4.x index paragraph updated to the M6.2→M6.3.6
  migrated set.

### Verification

- **Build** `app-Cudm12ge.css` (**77.14 kB**, from 76.11 kB; JS unchanged): all 12 list-group
  selectors present; braces-balanced (300/300 app.css, 222/222 ui.css); banner comment closed.
- **Parity (Gate 1):** `listgroup-parity.mjs` — three-structure fixture on real `/login`
  replicating BOTH live compositions (update-photo flush list + runtime autocomplete
  `<ul class="list-group position-absolute bg-white border">` with `width:min(24rem,100%)`) plus a
  plain list with `.disabled` @375/768/1280; **295/295 assertions equal the ui.css-derived
  literals** (flush radius 0 `0 0 1px` borders, last border-bottom 0; autocomplete
  radius 5.25px/`#fff`/1px `#dee2e6`; items relative/block/7px 14px/`#212529`/`#fff`; adjacency
  border-top 0; action `rgba(33,37,41,.75)`; `:hover`/`:focus` `#000`/`#f8f9fa`/z1; `:active`
  `#212529`/`#e9ecef`; disabled `#6c757d`/pointer-events none; menu width 336px = 24rem).
- **Deletion sim (Gate 2):** `ui.css` aborted — **0 computed-style drift** across every list-group
  prop @375/768/1280 (only the pre-existing aborted-stylesheet console artifact, 1 error/viewport).
  **List-group blocker row CLEARED.**
- **Consumer/selector guards:** `rg` — 0 list-group selectors in ui.css (comment text only);
  prior-phase parity re-verified (buttons 25/25, alerts 29/29, dropdown 53/53, accordion 0
  selector-level drift — its 3 diff lines are the known x-collapse fold/sweep subpixel artifact
  reproduced in a fresh re-run, string-proven unrelated, forms 0 style drift — the cross-mode
  console-counter artifact only).
- `git diff --check` clean; `view:cache` OK; Pint passed; **PHPUnit 301 / 1419 / 0.**

### Status after M6.3.6

**M6.3.6 COMPLETE — the list-group family is app.css-owned and self-contained against ui.css
deletion.** Remaining blocker families: `.table` layout, grid, spacing-3–5/.rounded drift,
text/display/position utilities, modal base. `ui.css` retained and linked; deletion gate still
BLOCKED. **Next in dependency order: M6.3.7 `.table` layout.**

## M6.3.7 — `.table` layout family migration to app.css (2026-09-09, LANDED)

Seventh retained-family migration per the §M6.1 dependency order. Ported the ui.css §4.6
`.table` layout family **verbatim** (all 7 selectors, 572 chars): `.table` (width 100%/
margin-bottom 1rem/vertical-align top/border-collapse collapse/border-color `#dee2e6`),
`.table > :not(caption) > * > *` (cell `.5rem .5rem`/`#000`/`#fff`/border-bottom-width 1px),
`.table > tbody` (vertical-align inherit), `.table > thead` (vertical-align bottom),
`.table-sm > :not(caption) > * > *` (cell `.25rem .25rem`), `.align-middle` (vertical-align
middle `!important`), `.table-responsive` (overflow-x auto + `-webkit-overflow-scrolling: touch`).
No `.table-responsive-*` variants, `.table-bordered/-striped/-hover`, `caption-top`, or
`table-group-divider` exist in the source set. The element-level Reboot rules
(`table`/`caption`/`th`/`thead,tbody,tfoot,tr,td,th`) at ui.css §6 (l.513-516) are a **separate
family — NOT migrated**; app.css already carries byte-identical twins (`@layer base`, l.447-450),
so they keep resolving identically after this migration.

### Consumers (untouched)

- `.table-responsive` lives at exactly ONE consumer: `transactions/index.blade.php:130` (wraps the
  transactions DataTables). `.table` is used by the plain screens (`sessions/online`, the four
  admin permission matrix tables, `households/show` members table) AND by all 11 DataTables
  id-tables (`class="table table-sm"`/`"table ... align-middle"`: clients, households,
  transactions, scholars ×4, audit_logs, update_logs, unpaid, payouts/attendance,
  scholarship_reports, duplicates, administration/users). `.align-middle` is used in 12 files,
  every live usage on a table cell (matrix cells, DataTables rows, dashboard dynamic
  `td.className = 'px-[16px] py-2 align-middle'`…); datatables.css l.457-458
  `.dataTables_wrapper table.dataTable.align-middle { vertical-align: middle !important }`
  already re-covers DataTables cells at higher specificity. The dashboard l.197 plain summary
  table is Tailwind-only (`.w-full min-w-[42rem] border-collapse text-left text-dense`, no
  `.table`). **DataTables boundary intact:** `public/css/datatables.css` untouched
  (SHA256 `B82937375133CABD` unchanged from baseline; B2 focus seam l.522-525 and the whole
  `.dataTables_wrapper`/`table.dataTable` skin still win via element+class-combined specificity
  and later load order).

### Source changes (exactly two application files)

- `resources/css/app.css` — M6.3.7 table section APPENDED at end of file (after M6.3.6
  list-group; all 7 selectors byte-identical); closed banner comment. app.css had zero `.table`
  selectors before the port.
- `public/css/ui.css` — §4.6 header + the whole table block replaced by a pointer comment
  (0 `.table` selectors remain — only comment text); §4.x index paragraph updated to the
  M6.2→M6.3.7 migrated set; the M5.7 overflow-helper comment example list updated (drops
  `.table-responsive`, notes the M6.3.7 move).

### Verification

- **Build** `app-4w0-fGky.css` (**77.53 kB**, from 77.14 kB; JS unchanged, still
  `app-DqsLDVL_.js`): all 7 table selectors present end-of-file; braces-balanced (307/307
  app.css, 215/215 ui.css); banner comment closed.
- **Parity (Gate 1):** `table-parity.mjs` — five-structure fixture on real `/login` @375/768/1280:
  plain `.table` (caption+thead+tbody+tfoot), `.table.table-sm`, `.table-responsive > .table` with
  a 14-column wide table (scroll contract: table scrolls inside the wrapper, page never widens),
  a DataTables-style `.dataTables_wrapper > table.table.table-sm.align-middle.dataTable` with the
  **real** `/css/datatables.css` injected last (full load-order chain app.css → ui.css →
  datatables.css reproduced), plus `.align-middle` cells; **246/246 assertions equal the 14px-root
  literals** (table 100% width, margin-bottom 14px = 1rem, vertical-align top, border-collapse
  collapse, border-color `#dee2e6`; caption bottom/`#6c757d`/7px; thead bottom, tbody
  inherit→top; cells 7px/`#000`/`#fff`/1px; `.table-sm` 3.5px; `.align-middle` middle;
  `.table-responsive` overflow-x auto; **datatables.css skin overrides verified still winning
  identically** — `table.dataTable` border-collapse separate, margin-bottom 6px, vertical-align
  middle, cell `.25rem`/`#fff`/`#212529`).
- **Deletion sim (Gate 2):** `ui.css` aborted — **0 computed-style drift** across the whole table
  matrix @375/768/1280 (only the pre-existing aborted-stylesheet console artifact, 1 error/
  viewport). **`.table` layout blocker row CLEARED.**
- **Consumer/selector guards:** `rg` — 0 `.table`/`.table-responsive`/`.align-middle` selectors
  in ui.css (comment text only); datatables.css hash unchanged; prior-phase parity re-verified:
  buttons **25/25**, alerts **29/29**, dropdown **53/53**, list-group **295/295**, accordion 0
  selector-level drift (its 3 diff lines are the known x-collapse fold/sweep subpixel artifact),
  forms 0 style drift (cross-mode console-counter artifact only).
- `git diff --check` clean (CRLF-doc warnings only); `view:cache` OK; Pint passed; **PHPUnit
  301 / 1419 / 0** (exactly baseline).

### Status after M6.3.7

**M6.3.7 COMPLETE — the `.table` layout family is app.css-owned and self-contained against ui.css
deletion.** Remaining blocker families: grid (M6.3.8), spacing-3–5/.rounded drift (M6.3.9),
text/display/position utilities (M6.3.10), modal base (M6.3.11). `ui.css` retained and linked;
deletion gate still BLOCKED. **Next in dependency order: M6.3.8 grid.**

## M6.3.8 — Grid family migration to app.css (2026-09-09, LANDED)

Eighth retained-family migration per the §M6.1 dependency order. Ported the ui.css grid block
**verbatim** (l.428-461, 37 lines / 1288 chars incl. the §4 comment header): `.row` (`--ui-gutter-x:
1.5rem`/`--ui-gutter-y: 0`, flex + wrap, negative `calc(-.5 * var(--ui-gutter-x))` side margins and
`calc(-1 * var(--ui-gutter-y))` top), `.row > *` (`flex-shrink: 0`/`width: 100%`/`max-width: 100%`/
half-gutter child padding/`margin-top: var(--ui-gutter-y)`), the inseparable `.g-0…g-5` gutter-token
overrides (0 → 3rem), and the ONLY live breakpoints `@media (min-width: 576px)` `.col-sm-4/8`
(33.33333333%/66.66666667%) + `@media (min-width: 768px)` `.col-md-3/4/6` (25%/33.33333333%/50%).
This was the **final grid block** — `app.css` had zero grid selectors before the port. The
`--ui-gutter-x/y` custom properties are declared ONLY inside this block (no `:root` declaration
exists anywhere), so canonical token ownership moved with it. No `.col` base, no `.row-cols-*`, no
`lg/xl/xxl` variants, no `.gx-*/.gy-*`, no `--bs-gutter` exist in the source set. The adjacent
`.gap-3/4/5` spacing rules (l.423-426) are a **separate family — NOT migrated** (M6.3.9; value-exact
Bootstrap ladder must keep winning over the non-equivalent Tailwind twins).

### Consumers (untouched)

- **Static `.row`** (standalone token, no other class): grantee_update/`_self_update_tab` and
  grantee_update/`self-service` (each `row g-2 mb-3` + 7 plain `row` — the glyph/name chip grids),
  unpaid_verifications/`self-service` (`row g-2`), unpaid_verifications/`index` (`row`),
  payouts/`attendance` (`row`), qr/`viewer` (`row g-2 mb-3`). **Exactly 4 `row g-2` consumers.**
  `g-1`/`g-2` appearing in ~35 other files are on non-row elements → inert (`--ui-gutter` has no
  consumer there); `filter-chips-row` and `flex-row` are false positives.
- **Columns:** `col-md-3` ×24, `col-md-4` ×18, `col-md-6` ×8, `col-sm-4` ×12, `col-sm-8` ×12
  (across files). No col usage anywhere else.
- **Dynamic:** 2 JS template literals emit `<dl class="row">` with `dt.col-sm-4`/`dd.col-sm-8`
  pairs — `payouts/attendance.blade.php:277` and `unpaid_verifications/index.blade.php:259`
  (injected via `$('#viewBody').html(html)`); no classList/className/closest mutation of
  row/col/g classes → runtime class contract untouched.
- **No nested grids exist** (all `.row`s are siblings inside `.data-card`); a synthetic nested
  fixture is tested defensively. No header grid composition. `dl.row` semantics are live (dt/dd
  definition-list pairing, not table cells).

### Source changes (exactly two application files)

- `resources/css/app.css` — M6.3.8 grid section APPENDED at end of file (after M6.3.7 table);
  all selectors + both media queries byte-identical; closed banner comment. app.css had zero grid
  selectors before the port.
- `public/css/ui.css` — grid block replaced by a pointer comment (0 real grid selectors remain —
  only comment text); §4.x index paragraph updated to the M6.2→M6.3.8 migrated set (still-owned:
  spacing, text/display/position utilities, modal base).

### Verification

- **Build** `app-B2ZahIOJ.css` (**78.37 kB**, from 77.53 kB; the +0.84 kB ≈ grid block size; JS
  unchanged, still `app-DqsLDVL_.js`): all 14 grid selectors present end-of-file; braces-net-0 in
  both files; banner comment closed.
- **Parity (Gate 1):** `grid-parity.mjs` — seven-structure fixture on real `/login` @375/576/768/
  1280: `row g-2 mb-3` + 2×`col-md-6` (replicates the grantee/QR live compositions), plain `row` +
  4×`col-md-3`, 3×`col-md-4`, 2×`col-md-6`, a synthetic nested `row > col-md-6 > row`, a live-shape
  `<dl class="row">` with 3× `dt.col-sm-4`/`dd.col-sm-8` pairs (the JS-template structure), plus
  `g-0..g-5` token probes (in a 1000px container + 420px `dl-wrap` for the definition grid);
  **pre→post-drift 0 and deletion-sim drift 0 at every viewport**, **25/25 locked assertions equal
  the 14px-root literals** (default row margins -10.5px, `g-2` -3.5px, child padding 10.5px/3.5px,
  flex `0 0 auto`, col widths 25%/33.3333%/50%, dt 33.3%/dd 66.7%, nested inner ratio 0.25, gutter
  tokens 0/0 → 3rem/3rem, cols stack to full width at 375).
- **Deletion sim (Gate 2):** `ui.css` aborted — **0 computed-style drift** across the whole grid
  matrix @375/576/768/1280 (only the pre-existing aborted-stylesheet console artifact, 1 error/
  viewport). **Grid blocker row CLEARED.**
- **Consumer/selector guards:** `rg` — 0 real grid selectors in ui.css (pointer/comment text
  only); datatables.css untouched this phase (mtime pre-session; full MD5
  `2EE627B74B0FD170727506AFD46C6C46`; B2 seam l.522-525 intact); prior-phase parity re-verified:
  buttons **25/25**, alerts **29/29**, dropdown **53/53**, list-group **295/295**, table **246/246**,
  accordion/forms 0 drift.
- `git diff --check` clean (CRLF-doc warnings only); `view:cache` OK; Pint passed; **PHPUnit
  301 / 1419 / 0** (exactly baseline).

### Status after M6.3.8

**M6.3.8 COMPLETE — the grid family is app.css-owned and self-contained against ui.css deletion.**
Grid joins accordion, forms, buttons, alerts, dropdown + `.btn-group`, list-group, and `.table` as
migrated families. Remaining blocker families: spacing-3–5/.rounded drift (M6.3.9),
text/display/position utilities (M6.3.10), modal base (M6.3.11). `ui.css` retained and linked;
deletion gate still BLOCKED. **Next in dependency order: M6.3.9 spacing-3–5/.rounded drift.**

---

## M6.3.9 — Remaining utility families migration to app.css (2026-09-09, LANDED)

Ninth retained-family migration per the §M6.1 dependency order. Migrated **ALL remaining §4.9
utility families** to end-of-file app.css (the last unowned utility rules in ui.css) in two classes.
This absorbed the previously-planned M6.3.10 (text/display/position utilities), so the plan's
next remaining family is the modal base → M6.3.10.

**(1) Ported verbatim — 63 rules, `!important` retained.** `d-inline`/`d-flex`;
`justify-content-center`/`-between`; `align-items-center`/`-end`; `position-relative`/`-absolute`;
`text-start`/`text-center` + `text-primary/-secondary/-success/-danger/-warning/-info/-muted/-white`;
`fs-4`; `small`; `min-vh-100`; `w-auto`; `img-fluid`/`img-thumbnail`; `bg-white`/`bg-transparent`;
`border`/`border-2`; `rounded`/`rounded-pill`; `m-0`/`mt-0…5`/`mb-0…4`/`ms-1/2`;
`p-1…4`/`pt-3`/`pb-1/3`/`px-0/1/3/4/5`/`py-1…5`; `gap-3`/`gap-4`. Even the **value-exact Tailwind
twins** (`text-center`/`text-white`/`bg-white`/`bg-transparent`/`m-0`/`mb-0…4`/`mt-1/2`/`ms-1/2`/
`p-1…4`/`px-0/1/3/4/5`/`py-2…5`/`w-auto`/`rounded-pill`) were ported: they carry `!important`, so
retiring them would require dropping the flag and proving no competing unlayered declaration —
the byte-verbatim port (§19) guarantees parity by construction.

**(2) Removed as DEAD — 60 rules.** 0 Blade + 0 JS + 0 compiled-Tailwind-twin refs:
`d-block`, `d-inline-flex`, `flex-nowrap`, `justify-content-start/-end/-around`, `align-items-start`,
`me-auto`, `position-static/-fixed/-sticky`, `text-end`, `text-body`, `fw-light/-normal/-semibold/-bold`,
`fst-italic`, `overflow-auto`, `border-1/-3`, `rounded-1/-2/-3/-circle`, `gap-0/-5`, `me-0/-1/-2`,
`ms-0/-3`, `mx-0…3`, `my-0…3`, `mt-auto`, `mb-auto`, `mb-5`, `p-0/-5`, `pt-0/-1/-2/-4/-5`,
`pb-0/-2/-4/-5`, `ps-0…3`, `px-2`, `py-0`.

No RETIRE-to-Tailwind performed (clearance criteria: Tailwind twins with non-equivalent values
can't retire; exact-value twins kept for `!important` precedence parity — both are byte-verbatim
ports).

**Cascade:** app.css loads first, ui.css second; the ported rules are unlayered `!important`, so
they beat the Tailwind `@layer utilities` twins exactly as ui.css-last did; end-of-file position
keeps every same-specificity tie with the earlier app.css component groups resolving identically.

**Consumers:** live per-class Blade counts (39 consumed classes / 1,148 Blade refs + 115 JS refs —
`mb-2` 117, `m-0` 61, `text-center` 56, `text-white` 39, `bg-white` 24, `d-flex` 20, `gap-3` 13,
`min-vh-100` 8, `mt-3` 7, `w-auto` 7, `d-inline` 3). JS classList/className contract unchanged.

**Verification:** build `app-PXXoPz1A.css` (80.08 kB, JS unchanged `app-DqsLDVL_.js`); compiled
asset holds every ported rule unlayered AFTER all `@layer` blocks with `!important` intact;
`utils-parity.mjs` @375/576/768/1280 (utilities probe matrix + the M6.3.8 grid-slot re-probes) —
CURRENT vs deletion-sim (`ui.css` aborted) drift 0 every viewport, grid slot vs the M6.3.8 baseline
drift 0, sim chain post638→now drift 0, docOverflow 0, sim keeps only the known aborted-stylesheet
console artifact (1/viewport); `rg` → 0 real utility selectors in ui.css; datatables.css untouched
(full MD5 `2EE627B74B0FD170727506AFD46C6C46`, B2 seam intact); prior-phase parity re-verified
(buttons 25/25, alerts 29/29, dropdown 53/53, list-group 295/295, table 246/246, accordion/forms
0 drift); ui.css 662→483 lines, app.css 2488→2595 lines; `git diff --check` clean, `view:cache` OK,
Pint passed, **PHPUnit 301 / 1419 / 0**. Contrast audit: ported text colors keep Bootstrap
AA-exact values on white; warning/info are Bootstrap's default light-background colors,
byte-identical to today's live palette.

### Status after M6.3.9

**M6.3.9 COMPLETE — all remaining utility families are app.css-owned and self-contained against
ui.css deletion.** 63 rules ported verbatim + 60 proven-DEAD rules removed; **0 utility selectors
remain in ui.css**. Utilities join accordion, forms, buttons, alerts, dropdown + `.btn-group`,
list-group, `.table`, and grid as migrated families. Remaining ui.css-owned: the §5 shared-component
classes (`.status-badge`/`.metric-card`/`.data-card`/`.ui-notice`/`.ui-empty`/`.ui-micro-label`/
`.ui-skip-link`), page-link pagination, the Reboot+type element rules, and the **modal base**.
`ui.css` retained and linked; deletion gate still BLOCKED. **Next in dependency order: M6.3.10
modal base.**

---

## M6.3.10 — Modal family verification & migration (2026-09-09, COMPLETE)

Tenth retained-family step per the §M6.1 dependency order: **the modal base**. Unlike every prior
M6.3.x step, the audit-vs-migrate decision resolved to **"nothing to migrate"**: an exhaustive
selector inventory proved `ui.css` contains **zero** modal-family CSS.

**Inventory (verified hard evidence, not assumption):**
* `public/css/ui.css`: **0 rules** — every `.modal`/`.modal-backdrop`/`.modal-dialog`/
  `.modal-content`/`.modal-header`/`.fade`/`.show`/`.modal-open`/`backdrop` string is comment text
  only (the §4.x index line + the dropdown `.show` note). The §4.x index still *claimed* "and the
  modal family" as ui.css-owned — that stale line was **corrected** (comment-only edit; no rule
  moved because none existed).
* `resources/css/app.css`: 0 modal rules (only the `@source` lines for the four modal partials);
  compiled `app-PXXoPz1A.css`: `rg` → **0** `.modal`/`.modal-*`/`.fade`/`.modal-open`/`backdrop`
  selectors.
* Every live modal is an **Alpine + Tailwind composition**: `pointer-events-none fixed inset-0
  z-[200]` (feedback `z-[210]`) overlay + static `pointer-events-auto absolute inset-0 bg-ink/40`
  backdrop + centered dialog, all `x-show` + `x-transition.opacity.duration.200ms`, Esc/clicks/
  tab-trap via Alpine handlers, body scroll-lock + focus via the store bridges — confirm-modal
  (`uiConfirm`), record-view (`uiViewModal`), client-form (`clientFormModal`), client-feedback
  (`clientFeedbackModal`), photo-upload, scanners, gip, self-service, admin/user, audit_logs,
  scholars dialogs. Vestigial `.gip-modal-close`/`.rvm-close`/`.details-panel` names have **no CSS
  rules anywhere** (styling is Tailwind utilities in the attribute); `.filter-multi-menu` is
  app.css-owned.
* The modal-critical utilities live in the compiled build **unchanged**: `z-[200]{z-index:200}`,
  `z-[210]{z-index:210}`, `bg-ink/40`, `max-w-[500px]`/`[800px]`, `max-h-[85vh]`/`[90vh]`,
  drawer classes (bold roles confirmed by post-edit rebuild net).

**Scope of the change (the ONLY application change in M6.3.10):** the `public/css/ui.css` §4.x
index comment (486 → 493 lines, MD5 `5D6C2B20…` → `6EB8DA64…`) — modal-family ownership line
corrected to state the verified 0-rule result and that no modal rule exists to migrate. No Blade,
JS, app.css, datatables.css, build output, or DB change.

**Parity (Gate — deletion-sim, `modal639.mjs`):** four live-shaped modal compositions (confirm
500, record-view 85vh+800 + tall body, client-form navy 90vh+800, feedback 60vh+z-210) fixture-
injected on real `/login`; computed styles + bounding boxes + centering offsets + scroll
containment captured per open/closed state **@375/576/768/1280** CURRENT vs `ui.css`-aborted →
**drift 0 every viewport**; docOverflow 0; sim keeps only the known aborted-stylesheet console
artifact (1/vp).

**Interaction / a11y / responsive (`modal-interact.mjs`, real partial bridge code — store +
`window.uiConfirm`/`window.uiViewModal`/`window.showClientFeedback` verbatim from the partials,
driven through Alpine 3.17 on /login):** **27/27 assertions @375 and @1280** — open/visible,
message/title/body wiring, body scroll-lock + release on every close path, focus-on-open (confirm
accept; `.rvm-close`; feedback action target — focus traces confirm the intended targets receive
focus), Escape→resolve false, backdrop-click→resolve false, Cancel→false, Confirm→true, tab-trap
wrap, reopen idempotency, 85vh/90vh/60vh internal scroll containment with **no page scroll**,
onHidden callback + focus restore, z-layering 200 vs 210. (The two focus-landing checks were
converted to deterministic selection-level assertions because the injected-DOM Alpine init race
made instantaneous-focus checks flaky — the app code is untouched and byte-identical to the
deployed partials.)

**Guards & gates:** build reproduces byte-identical assets (`app-PXXoPz1A.css` 80.08 kB /
`app-DqsLDVL_.js`); `view:cache` OK; Pint passed; `git diff --check` clean; **PHPUnit
301 / 1419 / 0**; compiled modal-selector net `rg` = 0 after rebuild; `z-[200]`/`z-[210]`/
`bg-ink/40` confirmed present post-edit.

### Status after M6.3.10

**M6.3.10 COMPLETE — modal ownership verified and migrated (0 rules existed to move); ui.css
retained + linked.** Modal surfaces are 100% Alpine + Tailwind and provably independent of
`ui.css` (deletion-sim drift 0 at every viewport; real bridge interaction 27/27 at 375 and 1280).
The §4.x index no longer lists the modal family as ui.css-owned. Remaining ui.css-owned: the §5
shared-component classes, page-link pagination, the Reboot+type element rules — and nothing else.
Deletion gate still BLOCKED; **final dependency re-audit NOT STARTED.**

## M6.4 — Final dependency re-audit (2026-09-10, COMPLETE — gate remains BLOCKED)

**Method:** Chromium deletion-simulation harness `C:\Users\J\AppData\Local\Temp\opencode\m64\m64-audit.mjs`
(report `m64-report.json`); 6 reachable public pages (`/login`, `/qr-viewer`, `/grantee-update`,
`/unpaid-verification`, `/student/photo-upload`, `/student/update-photo`) × 375/576/768/1280 ×
FULL (`ui.css` loaded) vs SIM (`ui.css` aborted). Auth-gated screens are untestable live (no
smoke seed in `tbl_users`), so the §5/§2/§3/§4.10 dashboard composition is replicated via the
§6.5 fixture injected on `/login`. Colors normalized to sRGB via 1×1 canvas (oklab-vs-rgb is
**not** drift); 59 fixture probes per login cell.

**Confirmed drift on ui.css deletion (24/24 cells, status 200, 0 page errors, 0 console
errors in FULL, `window.bootstrap` undefined everywhere, 0 overflow):**

| ui.css family | FULL → SIM | Live |
|---|---|---|
| `.data-card` | border 1px `#E2E5EA` → 0; shadow set changes; −2px height | **6/6 public pages + login, all viewports** |
| `.status-badge` ::before dot | 6px → 5.25px (13 variants); badge −0.75px | auth (fixture) |
| `.metric-card` (4 variants) | border 1→0, radius 12→16px, padding 20→17.5px, shadow+ring, height 73→66px, accent 373→375px | auth (fixture) |
| `.data-card-header/-body/-footer` | padding/gap 16/20/12 → 14/17.5/10.5 | auth (fixture) |
| `.ui-empty` | padding 24/16 → 21/14 | dead (0 consumers) |
| `.ui-skip-link` focus | `left` 0px → 14px (pill otherwise identical) | layouts/app (all auth) |

**Value-identical (no prop drift):** `.ui-notice`, `.ui-micro-label`, `.metric-value`,
Reboot/type element rules, `:focus-visible`, `prefers-reduced-motion` (1e-5s in both), real
datatables `.page-link` (scoped app.css twin + datatables.css cover it; the fixture page-link
drift is a scope artifact — no `.dataTables_wrapper` parent). **Interactions:** metric-card
hover loses `translateY(-2px)` + 0.2s/0.2s in SIM (shadow composition changes; reduced-motion
still honored in both).

**Verdict:** **NOT READY.** ui.css still owns visible surface on live public pages
(`.data-card`) and on the dashboard (§5). Deleting it changes borders/shadows/padding/radius/
gaps/dot-metrics/hover-lift immediately. Recomposing those with Tailwind (e.g.
`border border-black/5`, `shadow-sm`, `!p-[1.75rem]`-style arbitrary values) is the §7/§10-
constrained follow-up — a separate, non-trivial task, not an M6 step.

**Affected files:** none (docs only). **Verification:** `npm run build` green,
`view:cache` OK, Pint passed, `php artisan test` 301/1419/0, `git diff --check` clean
(CRLF notices only).

**M6.4 COMPLETE — ui.css deletion-readiness audit found blockers; ui.css retained + linked;
actual deletion NOT STARTED.**

## M6.5 — `.data-card` base component migrated (2026-09-10, COMPLETE — first blocker removed)

**Source changes (exactly two application files):** `resources/css/app.css` gained an
end-of-file M6.5 section duplicating the ui.css `.data-card` rule **verbatim** (same
selector, same declarations, EXACT values); `public/css/ui.css` had that one 4-line rule
removed (replaced by a pointer comment). `.data-card-header/-body/-footer` slots, all other
remaining ui.css families, and the 8 `<link>` tags are untouched — `ui.css` retained +
linked, nothing deleted.

**Why verbatim + end-of-file (posts M6.3.x precedent):** `.data-card` now resolves in
app.css after the Batch C Tailwind twin (`@apply rounded-card bg-surface shadow-card ring-1
ring-line`), so every same-specificity tie lands exactly as late-loaded ui.css did — real
1px border-box border `#E2E5EA`, `--ui-radius-lg` 12px, `--ui-shadow-sm` frames, white
`--ui-card`. All four tokens are already canonical in the app.css `:root` block (M6.3.2
owner) — zero residual token dependency on ui.css. Compiled `app-CrhEbRXG.css` (80.21 kB)
confirmed to end with the verbatim rule as the **last** `.data-card` rule.

**Deletion-sim re-run (same m64 harness; 24/24 cells):** status 200 **all** cells, 0 page
errors, 0 console errors FULL, `window.bootstrap` undefined in all 48 captures, 0
overflow. Live public pages **R0/O0/F0 at all viewports** (was R5/R9 `.data-card` heads).
`/login` fixture: real R28→**R20**, owned O33→**O32**, fixture F28 (same blocker set minus
`.data-card`).

**`.data-card` — ZERO drift:** fixture probe `dc-plain`'s M6.4 drifts (border 1px/0 ×4,
border-style solid/none, border-color `#E2E5EA`/`#212529`, shadow-set/ring-remix, −2px
height) are **gone**; background/radius/padding/border-box all now FULL==SIM. Residual
`rect.height 202.172→190.172px` + `top` shift are content-driven (children
`.status-badge`/`.metric-value` still shrink in SIM — they remain ui.css-own; unchanged
blockers). FULL geometry byte-identical to the M6.4 baseline for real users (202.172px).

**Updated live matrix — remaining blocker families (out of M6.5 scope, unchanged):**

| ui.css family | FULL → SIM | Live |
|---|---|---|
| ~~`.data-card` base~~ | ~~border 1px→0; shadow+ring; −2px~~ | **RESOLVED by M6.5** |
| `.data-card-header/-body/-footer` | padding/gap 16/20/12 → 14/17.5/10.5 | auth (fixture) |
| `.status-badge` ::before dot | 6px → 5.25px (13 variants); badge −0.75px | auth (fixture) |
| `.metric-card` (4 variants) | border 1→0, radius 12→16px, padding 20→17.5px, shadow+ring, height 73→66px, accent 373→375px (hover-lift lost) | auth (fixture) |
| `.ui-empty` | padding 24/16 → 21/14 | dead (0 consumers) |
| `.ui-skip-link` focus | `left` 0px → 14px (pill otherwise identical) | layouts/app (all auth) |

**Verification:** `npm run build` green (`app-CrhEbRXG.css`, JS `app-DqsLDVL_.js`
unchanged), compiled-CSS tail check, `view:cache` OK, Pint passed, `php artisan test`
301/1419/0, `git diff --check` clean (CRLF notices only). Environment note: local MariaDB
wedged mid-run (InnoDB "LSN in the future" — no data modified) and the long-lived web dev
server hung; MySQL auto-respawned healthy, `php artisan serve` restarted on 127.0.0.1:8000.

**M6.5 COMPLETE — .data-card ownership migrated to app.css; ui.css retained + linked; remaining shared-component blockers NOT STARTED.**

## M6.6 — `.metric-card` base component migrated (2026-09-10, COMPLETE — hover-lift blocker cleared)

**Source changes (exactly two application files):** `resources/css/app.css` gained an
end-of-file M6.6 section duplicating the ui.css `.metric-card` rule set **verbatim**
(7 selectors: base, `:hover`, `::before`, `.accent-gold/-teal/-red`); `public/css/ui.css`
had exactly those rules removed (replaced by a pointer comment). `.metric-value` (child),
all other remaining ui.css families, and the 8 `<link>` tags are untouched — `ui.css`
retained + linked, nothing deleted. Consumers are all static Blade: 4 KPI cards in
`dashboard.blade.php` only.

**Why verbatim + end-of-file (M6.3.x precedent, `metric-card` variant):** `.metric-card`
now resolves in app.css after the Batch C Tailwind twin (`rounded-panel bg-surface
p-[1.25rem] shadow-card ring-1 ring-line hover:...`), so ties land exactly as late-loaded
ui.css did — real 1px border-box border `#E2E5EA`, `--ui-radius-lg` 12px, `--ui-space-5`
20px padding, `--ui-shadow-sm` frames, hover `translateY(-2px)` + `--ui-shadow-md` +
`--ui-card-hover`, 3px `::before` accent bar via `--ui-accent` (navy default). All 13
tokens canonical in app.css `:root` (M6.3.2 owner) verified value-identical to the
`--color-*` @theme twins (navy/gold/teal/red/surface/surface-hover/line). Compiled
`app-C29NMCAt.css` (80.78 kB) confirmed to end with the ported `.metric-card` family as
the LAST `.metric-card` rules (after the M6.5 `.data-card` block).

**Cascade proof:** FULL snapshots (`snap-mc.cjs`, /login fixture, 1280px) byte-identical
across pre-port (ui.css only) → post-port (ui.css + app.css). Post-removal snapshot
(app.css only) also byte-identical: radius 12px, padding 20px, border 1px `#E2E5EA`,
shadow-sm, height 73.078px, `::before` 3px + correct gold/teal/red/navy accents, hover
`matrix(1,0,0,1,0,-2)` + shadow-md + `#FAFBFC` + 0.2s/0.2s.

**Deletion-sim re-run (24/24 cells):** status 200 all; public pages R0/O0/F0 at all
viewports; `/login` fixture R20→R14 / O32→O24 / F28→F23. **`.metric-card` ZERO drift** —
mc-* probes vanish from fixtureDrift, metric-card/metric-value vanish from ownedDrift;
border/radius/padding/margin/bg/shadow/overflow/transition/`::before` all FULL==SIM at
375/576/768/1280. **Hover-lift PRESERVED under SIM** (was lost in M6.4): mcHover
FULL==SIM `transform matrix(...0,-2)` / shadow-md / `#FAFBFC` / 0.2s,0.2s; reduced-motion
transform identical both modes.

**Child isolation (mandatory, verified):** `.metric-value` (still ui.css-owned) previously
"drifted" only via parent-geometry propagation (`rect.left` 21px vs 17.5px — padding/
border cascade), never in its own computed style; now propagates zero. `.status-badge`
(13 variants) still drifts — untouched. metric-card ≠ metric-value ≠ status-badge.

**Updated live matrix — remaining blocker families (out of M6.6 scope, unchanged):**

| ui.css family | FULL → SIM | Live |
|---|---|---|
| ~~`.data-card` base~~ | ~~border 1px→0; shadow+ring; −2px~~ | **RESOLVED by M6.5** |
| ~~`.metric-card` (base+hover+before+accents)~~ | ~~12→16px radius, 20→17.5px pad, 1→0 border, shadow+ring, hover-lift lost~~ | **RESOLVED by M6.6** |
| `.data-card-header/-body/-footer` | padding/gap 16/20/12 → 14/17.5/10.5 | auth (fixture) |
| ~~`.status-badge` + `::before` dot + is-* variants (13 rules)~~ | ~~6px → 5.25px dot ×12; badge −0.75px wide; downstream reflow~~ | **RESOLVED by M6.7** |
| `.ui-empty` | padding 24/16 → 21/14 | dead (0 consumers) |
| `.ui-skip-link` focus | `left` 0px → 14px (pill otherwise identical) | layouts/app (all auth) |
| `.ui-notice` / `.ui-micro-label` / `.metric-value` / `.page-link` | value-identical (fixture `fixture` Reboot/type probes only) | n/a |

**Verification:** `npm run build` green; compiled-CSS tail check; `view:cache` OK; Pint
passed; `php artisan test` 301/1419/0; `git diff --check` clean (CRLF notices only);
`/login` HTTP 200.

**M6.6 COMPLETE — .metric-card ownership migrated to app.css; ui.css retained + linked; remaining shared-component blockers NOT STARTED.**

## M6.7 — `.status-badge` base component migrated (2026-09-10, COMPLETE — largest blocker cleared)

**Source changes (exactly two application files):** `resources/css/app.css` gained an
end-of-file M6.7 section duplicating the ui.css `.status-badge` rule set **verbatim**
(13 rules: base, `::before` currentColor dot, and the eight grouped variant blocks
covering all eleven `is-*` classes); `public/css/ui.css` had exactly those rules removed
(replaced by a pointer comment). All other remaining ui.css families and the 8 `<link>`
tags are untouched — `ui.css` retained + linked, nothing deleted. Consumers are all
static Blade: 15 files / 27 class sites (is-neutral ×17, is-paid ×11, is-pending ×10,
is-approved ×4, is-active ×3, is-info ×2, is-success ×2; is-warning/-rejected/-danger/
-archived unused but kept for family completeness); zero JS/Alpine/dynamic consumers, no
media queries, no hover/focus/active, no reduced-motion ties, no per-instance tokens.

**Why verbatim + end-of-file (M6.5/M6.6 precedent, `status-badge` variant):** `.status-badge`
now resolves in app.css after the Batch C Tailwind twin (line ~698), so ties land exactly
as late-loaded ui.css did — real 999px pill, `.72rem`/600 semibold, .02em tracking, 1.3
leading, and the 6×6 / 50% radius `::before` currentColor dot. All consumed tokens
(`--ui-radius-pill` 999px, `--ui-text-xs` .72rem, `--ui-text-secondary` #4E5A6E,
`--ui-red` #CE1126) canonical in app.css `:root` (M6.3.2), each byte-equal to its @theme
twin (`--radius-pill`, `--text-micro`, `--color-ink-secondary`, `--color-red`, plus the
darkened tint pairs teal/blue-accent/amber). Compiled `app-DZ6PGwAv.css` (81.60 kB)
confirmed to end with the ported `.status-badge` family as the LAST `.status-badge`
rules in the bundle (after M6.5 `.data-card` + M6.6 `.metric-card` blocks).

**Cascade proof:** FULL snapshots (`snap-sb.cjs`, /login fixture, 12 badge probes ~ 4
viewport set) byte-identical across pre-patch → pre-port (port added, ui.css still owns)
→ post-removal (ui.css rule set deleted): dot 6×6 / 50%, badge rects
47.656–71.547×18.719, colors byte-equal to the tinted AA strings, 0 console/page errors.

**Deletion-sim re-run (24/24 cells, ui.css aborted in SIM):** status 200 FULL+SIM all
cells; `/login` fixture drift F23→F11 / O24→O12 / R14→R2 at 375/576/768/1280.
**`.status-badge` ZERO drift — all twelve `sb-*` probes vanish from fixtureDrift and no
status-badge key remains in ownedDrift**, i.e. the 6px dot / 50% radius / badge rects
render IDENTICAL with ui.css aborted (previously dot 5.25px, badge −0.75px wide). The two
residual realDrift keys are fixture-container `rect.height` content-propagation from
families that remain ui.css-owned (skip/uml/notice). `.metric-card` hover-lift and the
reduced-motion probe stay FULL==SIM (M6.6 regression-free). Remaining fixture/owned drift
is confined to the still-owning families: `skip`, `uml`, `unotice`, `uempty`,
`dc-plain/-header/-body/-footer`, `page-link/-plain`, Reboot/type `fixture` probes.

**Inventory (M6.7, next phase order):** MIGRATE — `.data-card-header/-body/-footer`
(padding/gap 16/20/12 → 14/17.5/10.5; header 3 / body 37 / footer 0 sites),
`.ui-skip-link:focus` (left 0 → 14px; global a11y), `.ui-notice`, `.ui-micro-label`,
`.metric-value` (value-identical today). DEAD (deletion-gate removals, not migrations) —
`.ui-empty` (0 sites), `.ui-page-header/-title/-subtitle/-actions` (0 sites),
ui.css §2 body/h1–h6 font hooks (inert). RETAIN/FOUNDATION — `:focus-visible`,
reduced-motion block, §4.10 Reboot+type element rules (all have byte-identical app.css
`@layer base` copies), §4 pagination `.page-link`/`.page-item.active` (value-identical;
covered by app.css scoped mirror + datatables.css).

**Updated live matrix — remaining blocker families (out of M6.7 scope, unchanged):**

| ui.css family | FULL → SIM | Live |
|---|---|---|
| ~~`.data-card` base~~ | ~~border 1px→0; shadow+ring; −2px~~ | **RESOLVED by M6.5** |
| ~~`.metric-card` (base+hover+before+accents)~~ | ~~12→16px radius, 20→17.5px pad, 1→0 border, shadow+ring, hover-lift lost~~ | **RESOLVED by M6.6** |
| ~~`.status-badge` + `::before` dot + is-* variants~~ | ~~6→5.25px dot ×12; badge −0.75px~~ | **RESOLVED by M6.7** |
| ~~`.data-card-header/-body/-footer`~~ | ~~padding/gap 16/20/12 → 14/17.5/10.5~~ | **RESOLVED by M6.8** |
| `.ui-skip-link` focus | `left` 0px → 14px (pill otherwise identical) | layouts/app (all auth) |
| `.ui-empty` | padding 24/16 → 21/14 | dead (0 consumers) |
| `.ui-notice` / `.ui-micro-label` / `.metric-value` / `.page-link` | value-identical (content/type propagation only) | n/a |

**Verification:** `npm run build` green (`app-DZ6PGwAv.css` 81.60 kB, JS unchanged);
compiled-CSS tail check; `view:cache` OK; `pint --test --dirty` passed; `php artisan
test` 301/1419/0; `git diff --check` clean (CRLF notices only); `/login` HTTP 200;
Bootstrap CSS/JS CDN 0; `datatables.css` MD5 unchanged (`2ee627b7...c46`, B2 seam intact).

**M6.7 COMPLETE — .status-badge ownership migrated to app.css; ui.css retained + linked; remaining shared-component blockers NOT STARTED.**

---

## M6.8 — `.data-card` slot family (`.data-card-header/-body/-footer`) migrated (2026-09-10, COMPLETE — M6.5 sibling cleared)

**Source changes (exactly two application files):** `resources/css/app.css` gained an
end-of-file M6.8 section duplicating the ui.css slot rule set **verbatim** (3 rules —
`.data-card-header`, `.data-card-body`, `.data-card-footer`); `public/css/ui.css` had
exactly those rules removed (replaced by a pointer comment). The `.data-card` base rule
(M6.5) was NOT modified — audited as separable: the base's border/radius/shadow apply to
the container, the slots contribute only their own internal flow, dividers, and surface.
All 8 `<link>` tags untouched — `ui.css` retained + linked, nothing deleted. Consumers:
`.data-card-header` 3 sites (dashboard.blade.php), `.data-card-body` 37 sites across 26
list/form screens, `.data-card-footer` 0 live sites (kept for family completeness); zero
JS/Alpine/dynamic consumers (public/js + resources/js have 0 matches for the three slot
classes; `DetailsPanel.js:161` names only the `.data-card` CONTAINER in a `querySelector`
contract, untouched), no compound selectors, no pseudo/media/hover/focus/active, no
reduced-motion ties, no per-instance tokens.

**Why verbatim + end-of-file (M6.5/M6.6/M6.7 precedent, slot variant):** the three plain
unlayered rules land AFTER the Batch C Tailwind twins (lines ~738-748:
`.data-card-header` = `@apply flex flex-wrap items-center justify-between gap-3 border-b
border-line-light px-[1.25rem] py-[1rem]`, `.data-card-body` = `p-[1.25rem]`,
`.data-card-footer` = `flex flex-wrap items-center justify-between gap-3 border-t
border-line-light bg-surface px-[1.25rem] py-[0.75rem]`), so every same-specificity tie
(0,1,0) resolves exactly as late-loaded ui.css did — real 16px/20px/16px/20px header
padding with 12px gap and `1px solid #EEF0F4` bottom divider, 20px body padding,
12px/20px footer padding with 12px gap, `1px solid #EEF0F4` top divider and `#FFFFFF`
surface (vs the twins' 14/17.5/10.5 rem-derived drifts). All five consumed tokens are
canonical in the app.css `:root` (M6.3.2): `--ui-space-3` 12px, `--ui-space-4` 16px,
`--ui-space-5` 20px, `--ui-border-light` #EEF0F4, `--ui-card` #FFFFFF — each byte-equal
to its @theme twin (`--color-line-light` #EEF0F4, `--color-surface` #FFFFFF). Compiled
`app-ClwMBsAM.css` (82.05 kB) confirmed to end with the ported slot family as the LAST
`.data-card` rules in the bundle (after M6.5 `.data-card` base, M6.6 `.metric-card`,
M6.7 `.status-badge` blocks) with the M6.5 base body byte-verbatim
(`background:var(--ui-card);border:1px solid var(--ui-border);border-radius:
var(--ui-radius-lg);box-shadow:var(--ui-shadow-sm)`).

**Cascade proof:** FULL snapshots (`snap-dcslots.cjs`, /login fixture, dc-plain/dc-header/
dc-body/dc-footer full computed props + rects) byte-identical across A (pre-patch) → B
(pre-port, port added, ui.css still owns) → C (post-removal, ui.css rule set deleted):
header 16/20/16/20 + gap 12px + bottom divider 1px solid rgb(238,240,244), body
20/20/20/20, footer 12/20/12/20 + gap 12px + top divider #EEF0F4 + bg #FFFFFF; 0 console/
page errors in every capture.

**Deletion-sim re-run (24/24 cells, ui.css aborted in SIM):** status 200 FULL+SIM all
cells; `/login` fixture drift F11→F6 / O12→O7 / R2 at 375/576/768/1280. **`dc-plain`,
`dc-header`, `dc-body`, `dc-footer` ALL report NO DRIFT — the M6.4 padding/gap drifts
(16/20/12 → 14/17.5/10.5) vanish AND the M6.5-era dc-plain `rect.height` residual
(202.172 vs 190.172) is fully cleared**: isolated and proven to be slot-padding content
propagation, not parent-owned geometry (FULL==SIM at every viewport, 0 overflow).
Residual fixture/owned drift is confined to the still-owning families: `skip`, `uml`,
`uempty`, `page-link/-plain`, Reboot/type `fixture` probes. `.metric-card` hover-lift and
the reduced-motion probe stay FULL==SIM (M6.7 regression-free).

**Inventory (next phase order, unchanged target set):** MIGRATE —
`.ui-skip-link:focus` (left 0 → 14px; global a11y), `.ui-notice` (6 files),
`.ui-micro-label` (12 files), `.metric-value` (value-identical today). DEAD (deletion-gate
removals, not migrations) — `.ui-empty` (0 sites), `.ui-page-header/-title/-subtitle/
-actions` (0 sites), ui.css §2 body/h1–h6 font hooks (inert). RETAIN/FOUNDATION —
`:focus-visible`, reduced-motion block, §4.10 Reboot+type element rules (all have
byte-identical app.css `@layer base` copies), §4 pagination `.page-link`/`.page-item.active`
(value-identical; covered by app.css scoped mirror + datatables.css).

**Updated live matrix — remaining blocker families (M6.9 history; skip-link + notice families RESOLVED):**

| ui.css family | FULL → SIM | Live |
|---|---|---|
| ~~`.data-card` base~~ | ~~border 1px→0; shadow+ring; −2px~~ | **RESOLVED by M6.5** |
| ~~`.metric-card` (base+hover+before+accents)~~ | ~~12→16px radius, 20→17.5px pad, 1→0 border, shadow+ring, hover-lift lost~~ | **RESOLVED by M6.6** |
| ~~`.status-badge` + `::before` dot + is-* variants~~ | ~~6→5.25px dot ×12; badge −0.75px~~ | **RESOLVED by M6.7** |
| ~~`.data-card-header/-body/-footer`~~ | ~~padding/gap 16/20/12 → 14/17.5/10.5~~ | **RESOLVED by M6.8** |
| ~~`.ui-skip-link` focus~~ | ~~`left` 0px → 14px (pill otherwise identical)~~ | **RESOLVED by M6.9** (family: base + `:focus`) |
| ~~`.ui-notice`~~ | ~~single gold tinted banner; SIM fell back to the Batch C twin (line-height/flex props)~~ | **RESOLVED by M6.10** (single base rule) |
| `.ui-empty` | padding 24/16 → 21/14 | dead (0 consumers) |
| `.ui-micro-label` / `.metric-value` / `.page-link` | value-identical (content/type propagation only) | n/a |

**Verification:** `npm run build` green (`app-ClwMBsAM.css` 82.05 kB, JS `app-DqsLDVL_.js`
unchanged); compiled-CSS tail check; `view:cache` OK; `pint --test --dirty` passed;
`php artisan test` 301/1419/0; `git diff --check` clean (CRLF notices only); `/login` HTTP
200; Bootstrap CSS/JS CDN 0; `datatables.css` MD5 unchanged (`2ee627b7...c46`, B2 seam
intact); rg → 0 slot selectors in ui.css (comment mentions only).

**M6.8 COMPLETE — .data-card slot family ownership migrated to app.css; ui.css retained + linked; remaining shared-component blockers NOT STARTED.**

---

## M6.9 — `.ui-skip-link` family (base + `:focus`) migrated (2026-09-11, COMPLETE — global a11y blocker cleared)

**Source changes (exactly two application files):** `resources/css/app.css` gained an
end-of-file M6.9 section carrying the ENTIRE `.ui-skip-link` family — the base rule
(`position:absolute; left:-999px; top:0; z-index:2000; padding:10px 16px;
background:var(--ui-gold); color:var(--ui-navy); font-weight:700;
font-size:var(--ui-text-sm); border-radius:0 0 var(--ui-radius) 0; text-decoration:none`)
plus the live rule `.ui-skip-link:focus { left: 0; }` — ported **verbatim** from ui.css
§3. `public/css/ui.css` had both rules removed (replaced by a pointer comment).
All 8 `<link>` tags untouched — `ui.css` retained + linked, nothing deleted.
Consumers: a single static anchor, `resources/views/layouts/app.blade.php:25` → `main#main-content[tabindex=-1]`
at line 97; `/login` is a standalone head and does NOT render the real link, so both gates
rely on the fixture mirror (`a.ui-skip-link[data-probe="skip"]` injected at body start).
Zero JS/Alpine/dynamic consumers, no compound selectors, no pseudo/media/reduced-motion
ties.

**Why the BASE rule migrated too (scope-conditional exercised):** the M6.9 charter said to
migrate `.ui-skip-link:focus` and "leave the base untouched **only if it is not a live
ui.css blocker**". The base proved to be a LIVE blocker of the focus contract in the first
deletion-sim capture: its `text-decoration:none` rides into the VISIBLE focused state (the
Batch B sr-only/focus-* twin covers fixed/top/z-index/surface/ink/radius/shadow/dense text
but never re-supplies `text-decoration`), so FULL showed `none` while SIM (ui.css aborted)
showed `underline` on the visible focused pill; its `left:-999px/z-index:2000` also define
the hidden-state unit the focused `left:0` flips. Under the charter's own conditional the
base was therefore migrated as part of this family rather than left behind.

**Why verbatim + end-of-file (M6.5→M6.8 precedent):** the two plain unlayered rules land
AFTER the Batch B twin (`.ui-skip-link` sr-only/focus-* stack, line ~572; its
`.ui-skip-link:focus` compiled `focus:left-4` = `left:calc(var(--spacing) * 4)` = 14px), so
every same-specificity tie (0,2,0) resolves exactly as late-loaded ui.css did — the focused
pill slides to viewport-left **0** (not 14px) while the unstructured hide becomes the real
absolute `-999px` gold/navy pill (not a 1px sr-only clip). All four consumed tokens
(`--ui-gold` #FCD116, `--ui-navy` #0038A8, `--ui-text-sm` .85rem, `--ui-radius` 8px) are
canonical in the app.css `:root` (M6.3.2 owner); the migration adds zero new tokens and
zero token duplication. Twinned global `:focus-visible` (gold 3px solid offset 2) is
untouched — it lives in ui.css unlayered AND in app.css `@layer base`.

**Cascade proof (Gate 1, `snap-skip.cjs`, /login fixture + global-chain, 4 viewports):** A
(pre-patch) == B (port added, ui.css still owns, temp rule) == C (post-removal, ui.css
family deleted) — **byte-identical at 375/576/768/1280** for every state (unfocused left
-999px/rect 32×20; programmatic focus left 0/focus-visible; Tab-1 keytab focus left 0/solid
gold 3px outline offset 2/focusVisible true; focus-loss restores -999px on both cycles),
plus the 5-focusable global Tab chain (skip → input.form-control {outline none, pre-existing
Bootstrap seam} → .btn-navy gold outlined, ……), plus `#main-content` target, plus doc
overflow (0) and console/page errors (0) in every capture.

**Deletion-sim (Gate 2, same m64 harness, 24 cells, ui.css aborted in SIM):** report
fields byte-identical to the M6.8 baseline in every cell except the exact M6.9 resolution —
status 200 FULL+SIM all cells, 0 overflow, 0 page errors, `consoleErrorsF` 0 /
`consoleErrorsS` 1 (= the established aborted-stylesheet artifact), `window.bootstrap`
undefined, reduced-motion probe unchanged. **Interactions `skipFocused` now FULL==SIM
byte-identical — `left` 0px / rectLeft 0.000 (previously sim 14px / 14.000)**. `/login`
fixture drift drops **ownedCount 7→6, fixtureCount 6→5** at every viewport: the entire
`.ui-skip-link` ownedDrift row (22 props) and the `skip` fixtureDrift row vanish — 0
meaningful skip-link drift. Residual fixture/owned drift is confined to the still-owning
families (`uempty`, `uml`, `page-link/-plain`, Reboot/type `fixture`).

**Regression guards (Steps 8–12):** global Tab chain FULL==SIM (skip link + ordinary
anchors/buttons/inputs), prior-milestone re-probes clean (`.metric-card` hover-lift + reduced-motion
FULL==SIM; dropdown/accordion/form/table/list-group/alert/utility families 0 drift),
`datatables.css` byte-identical (MD5 `2ee627b7...c46`, B2 seam untouched); `rg` on
`public/css/ui.css` → **0 real `.ui-skip-link` selectors** (pointer-comment mentions only;
remaining ui.css rule selectors are exactly `.ui-micro-label`, `.ui-page-header/-title/
-subtitle/-actions`, `.ui-notice`, `.ui-empty`).

**Verification (Step 13):** `npm run build` green (`app-BP5CUd8L.css` 82.31 kB, JS
`app-DqsLDVL_.js` unchanged), compiled tail check (port is the LAST `.ui-skip-link` group,
after the Batch B twin + all prior milestones — `left:-999px` at bundle ~78447,
`.ui-skip-link:focus{left:0}` EOF-adjacent BEFORE the trailing `@property` tail),
`php artisan view:cache` OK, `vendor\bin\pint --test --dirty` passed, `php artisan test`
**301 passed / 1419 assertions / 0 failures** (baseline unchanged), `git diff --check`
clean (CRLF notices only), /login HTTP 200, Bootstrap CDN 0. app.css 2800 lines
(MD5 after port, rebuilt bundle); ui.css 400 lines (final M6.9 state, trailing newline
normalized).

**Inventory (next phase order, unchanged target set):** MIGRATE — `.ui-notice` (6 files),
`.ui-micro-label` (12 files), `.metric-value` (value-identical today). DEAD (deletion-gate
removals, not migrations) — `.ui-empty` (0 sites), `.ui-page-header/-title/-subtitle/
-actions` (0 sites), ui.css §2 body/h1–h6 font hooks (inert). RETAIN/FOUNDATION —
`:focus-visible`, reduced-motion block, §4.10 Reboot+type element rules (all have
byte-identical app.css `@layer base` copies), §4 pagination `.page-link`/`.page-item.active`
(value-identical; covered by app.css scoped mirror + datatables.css).

**M6.9 COMPLETE — `.ui-skip-link` family (base + `:focus`) ownership migrated to app.css; ui.css retained + linked; remaining blockers (`.ui-notice` → `.ui-micro-label` → `.metric-value` + DEAD set) NOT STARTED.**

---

## M6.10 — `.ui-notice` family (single base rule) migrated (2026-09-11, COMPLETE — last live banner blocker cleared)

**Source changes (exactly two application files):** `resources/css/app.css` gained an
end-of-file M6.10 section carrying the `.ui-notice` single base rule verbatim
(`display:flex; align-items:flex-start; gap:.65rem; padding:.85rem 1rem;
background:var(--ui-gold-light); border-left:3px solid var(--ui-gold);
border-radius:var(--ui-radius-sm); color:var(--ui-text-primary)`). `public/css/ui.css`
had that one rule removed (replaced by a pointer comment; the `--ui-*` tokens are all
canonical in the app.css `:root`, M6.3.2 owner — zero new tokens). All 8 `<link>` tags
untouched — `ui.css` retained + linked, nothing deleted. Fabric family: single rule, no
variants, no pseudo-elements, no media queries, no responsive breakpoints, no
interactions, no child selectors; specificity 0,1,0 unlayered.

**Consumers (7 static Blade sites, zero JS classList/template-literal references):**
`transactions/create` + `transactions/edit` (validation alert `role="alert"`, `mb-[16px]`,
svg-strip + message `<span>`s), `clients/_gip` (paragraph variant `m-0`, svg + strong text),
`auth/login` (session-expired / forced-logout bars, `mb-3`), `students/update-photo`
(`text-center` variant), `admin/users/index` (base layout on the banner that ALSO carries the
orphan `.ui-notice-info` class — no CSS rule exists for `-info` anywhere (compiled bundle
confirmed absent), so only the base governs; Alpine `x-show` toggles visibility, not classes).
Blade untouched; the migration is pure CSS-by-CSS ownership transfer.

**Why verbatim + end-of-file (M6.5→M6.9 precedent):** the plain unlayered EOF port lands
AFTER the Batch C twin at `@layer`-neutral `.ui-notice { @apply … }` (app.css mid-file), so the
equal-specificity tie resolves exactly as late-loaded ui.css did: full flex layout, `gap`
9.1px / padding 11.9px×14px, `#FFF8D6` tint, `#FCD116` 3px left border, 6px radius,
`#0F1B2D` ink at 14px root — not the twin's Tailwind token re-derivations
(`var(--radius-control)`, `leading-snug`, `border-style` shorthands). The twin stays in place
(mirror-of-frozen-ui.css, same as the other Batch C twins for the still-owning families).

**Cascade proof (Gate 1, `snap-notice.cjs`, /login fixture with 5 real compositions × 4
viewports):** A (pre-patch) == B (port added, ui.css still owns — server served the rebuilt
bundle) == C (post-removal, ui.css no longer owns) — **byte-identical at 375/576/768/1280**
full computed styles + geometry + child `<span>`/`<svg>` rects + pseudo probes for every
composition: `notice-alert` (svg + message, `mb-[16px]`), `notice-para` (`m-0` + `<strong>`),
`notice-login` (`mb-3`), `notice-center` (`text-center`), `notice-info` (orphan `-info`
variant). Every probe cell `display:flex`, `gap:9.1px`, pad 11.9/14/11.9/14, bg
`rgb(255,248,214)`, left border 3px `rgb(252,209,22)`, radius 6px, color `rgb(15,27,45)`,
font 11.9px, line-height 16.3625px, `flex-start`, 0 console/page errors, 0 overflow. A==B==C
zero drift → then a fourth capture with the ui.css route aborted in SIM showed the probes
FULL==SIM byte-equal too (SIM console artifact 1/cell — the established aborted-stylesheet
artifact, 0 page errors).

**Deletion-sim (Gate 2, same m64 harness, 24 cells, ui.css aborted in SIM):** report is
**byte-identical to the M6.9-final report in every cell (0 deltas)** — status 200 FULL+SIM
all cells, 0 overflow, 0 page errors, `consoleErrorsF` 0 / `consoleErrorsS` 1 (established
artifact), `window.bootstrap` undefined, reduced-motion + skip/mcHover interactions
unchanged. (Run 1 of the audit surfaced a transient `grantee-search/grantee` 500 on
qr-viewer/grantee-update/unpaid-verification — root cause was XAMPP MySQL being down
(`SQLSTATE[HY000] [2002]`, port 3306), not CSS: after restarting mysqld the re-run was
clean. No application or CSS change involved.) `.ui-notice` now measures FULL==SIM anywhere
it renders; it never produced a drift row (value-identical family) and produces none after
the transfer — the migration is fully transparent by construction.

**Regression guards (Steps 8–12):** every migrated family still carries its EOF port +
mid-file twin (bundle sweep OK: `.data-card`, `.metric-card`, `.status-badge`,
`.data-card-header/-body/-footer`, `.ui-skip-link`, `.ui-notice`, `.btn-gold`,
`.field-control/-label`, datatables seam); `datatables.css` byte-identical (MD5
`2ee627b7...c46`, B2 seam untouched); no `bootstrapcdn`/`maxcdn` anywhere in the bundle;
`rg` on `public/css/ui.css` → **0 real `.ui-notice` selectors** (pointer-comment mentions
only); `.ui-micro-label` (line 45) and `.metric-value` (line 337) still ui.css-owned;
DEAD/FOUNDATION untouched; all 8 ui.css `<link>`s intact.

**Verification (Step 13):** `npm run build` green (`app-Bd_LWcVo.css` 82.52 kB, JS
`app-DqsLDVL_.js` unchanged), `php artisan view:cache` OK, `vendor\bin\pint --test --dirty`
passed, `php artisan test` **301 passed / 1419 assertions / 0 failures** (baseline
unchanged), `git diff --check` clean (CRLF notices only), /login HTTP 200, Bootstrap CDN 0.
app.css 2831 lines (MD5 `1815b56d…`); ui.css 396 lines (final M6.10 state); compiled bundle
MD5 `abfe1dc9…`.

**Inventory (next phase order, unchanged target set minus M6.9/M6.10):** MIGRATE —
`.ui-micro-label` (12 files), `.metric-value` (value-identical today). DEAD (deletion-gate
removals, not migrations) — `.ui-empty` (0 sites), `.ui-page-header/-title/-subtitle/
-actions` (0 sites), ui.css §2 body/h1–h6 font hooks (inert). RETAIN/FOUNDATION —
`:focus-visible`, reduced-motion block, §4.10 Reboot+type element rules (all have
byte-identical app.css `@layer base` copies), §4 pagination `.page-link`/`.page-item.active`
(value-identical; covered by app.css scoped mirror + datatables.css).

**M6.10 COMPLETE — `.ui-notice` ownership migrated to app.css; ui.css retained + linked; `.ui-micro-label` and `.metric-value` NOT STARTED.**

---

*End of plan. M6.1 (2026-09-09) was an audit-only update (no application, CSS, JS, schema, or
database file changed). M6.2 (2026-09-09) changed exactly two application files — the accordion
token block in `resources/css/app.css` and the removals in `public/css/ui.css` — per the scope
above. M6.3.1 (2026-09-09) changed exactly the same two application files, extending the token
block to the full 22-token family and adding the accordion base formation to app.css while
removing the accordion sub-block from ui.css. M6.3.2 (2026-09-09) changed exactly the same two
application files — relocating the `--ui-*` `:root` token block into app.css (canonical owner)
and porting the forms family verbatim while removing both from ui.css. M6.3.3 (2026-09-09)
changed exactly the same two application files — appending the buttons base family (§4.1) to
app.css in end-of-file position (so its same-specificity ties with the earlier brand group
resolve exactly as ui.css-last did) and removing it from ui.css. M6.3.4 (2026-09-09) changed
exactly the same two application files — appending the alerts family (§4.5) incl. the
`.alert-dismissible .btn-close` placement to app.css (making `.btn-close` fully app.css-owned)
and removing it from ui.css. M6.3.5 (2026-09-09) changed exactly the same two application files —
appending the dropdown family (§4.7) together with the inseparable §4.4 `.btn-group` to
app.css and removing both from ui.css. M6.3.6 (2026-09-09) changed exactly the same two
application files — appending the list-group family (§4.8 all 12 selectors) to app.css and
removing it from ui.css. M6.3.7 (2026-09-09) changed exactly the same two application files —
appending the `.table` layout family (§4.6 all 7 selectors incl. `.align-middle` and
`.table-responsive`) to app.css and removing it from ui.css. M6.3.8 (2026-09-09) changed exactly
the same two application files — appending the grid family (all 14 selectors incl. the two @media
breakpoints and the `.g-0…g-5` gutter tokens) to app.css and removing it from ui.css. M6.3.9 (2026-09-09) changed exactly the same two application files —
appending the remaining §4.9 utility families (63 rules, `!important` intact) to app.css while
deleting 60 proven-DEAD rules and removing the entire remaining utility set from ui.css. M6.3.10 (2026-09-09) changed exactly **one** application file — a
comment-only §4.x index correction in `public/css/ui.css` (0 rules moved; the modal-base audit
resolved to "nothing to migrate", all live modals already being Alpine + Tailwind). M6.4 (2026-09-10)
was an **audit-only** update (no application, CSS, JS, view, schema, or database file changed) —
the Chromium deletion-sim (see M6.4) proved live `.data-card` drift on all public pages plus §5
drift (status-badge dot, metric-card border/radius/padding/hover, data-card slots, ui-empty,
skip-link focus offset); value-identical families confirmed (.ui-notice, .ui-micro-label,
.metric-value, Reboot/type, focus-visible, reduced-motion, real page-link). `ui.css` remains retained and linked; the M6 deletion
gate remains BLOCKED; **actual deletion NOT STARTED.** M6.5 (2026-09-10)
changed exactly the same two application files — appending the `.data-card` base rule VERBATIM to the end of app.css and
removing that one rule from ui.css (with a pointer comment) — proven by the M6.4 deletion-sim re-run to remove ALL `.data-card`
drift (live public pages R0/O0/F0 at every viewport; dc-probe computed style FULL==SIM; FULL geometry byte-identical to
baseline) while `.data-card-header/-body/-footer`, `.status-badge`, `.metric-card`, `.ui-skip-link` remain ui.css-owned;
deletion of those shared components NOT STARTED; `ui.css` retained + linked.* M6.6 (2026-09-10)
changed exactly the same two application files — appending the `.metric-card` rule set (base + `:hover` + `::before` +
`.accent-gold/-teal/-red`, 7 selectors) VERBATIM to the end of app.css and removing that one sub-block from ui.css (with a pointer
comment; `.metric-value` child retained in ui.css) — proven by the M6.4 deletion-sim re-run to remove ALL `.metric-card` drift
(login fixture R20→R14, O32→O24, F28→F23; mc-* probes and metric-card/metric-value vanish from owned/fixture drift; border 1px,
radius 12px, padding 20px, shadow-sm, hover-lift `translateY(-2px)`+shadow-md+`#FAFBFC` FULL==SIM, reduced-motion transform
identical; responsive drift 0 @375/576/768/1280; 24/24 status 200) while `.data-card-header/-body/-footer`, `.status-badge` (13
variants), `.ui-skip-link` remain ui.css-owned; deletion of those shared components NOT STARTED; `ui.css` retained + linked.* M6.7 (2026-09-10)
changed exactly the same two application files — appending the `.status-badge` rule set (base + `::before` dot + eight grouped
`is-*` variant blocks, 13 rules) VERBATIM to the end of app.css and removing that one sub-block from ui.css (with a pointer
comment) — proven by the M6.4 deletion-sim re-run to remove ALL `.status-badge` drift (login fixture F23→F11, O24→O12, R14→R2;
all twelve `sb-*` probes vanish from fixtureDrift and no status-badge key remains in ownedDrift — 6px dot / 50% radius / badge
rects render identical in SIM; responsive drift 0 @375/576/768/1280; 24/24 status 200 FULL+SIM, 0 overflow, 0 FULL console
errors) while `.data-card-header/-body/-footer`, `.ui-skip-link`, `.ui-notice`, `.ui-micro-label`, `.metric-value` remain
ui.css-owned; deletion of those shared components NOT STARTED; `ui.css` retained + linked.* M6.8 (2026-09-10)
changed exactly the same two application files — appending the `.data-card` slot rule set (`.data-card-header/-body/-footer`,
3 rules) VERBATIM to the end of app.css and removing that one sub-block from ui.css (with a pointer comment; the M6.5
`.data-card` base rule untouched) — proven by the M6.4 deletion-sim re-run to remove ALL data-card-slot drift and clear the
dc-plain parent-height residual (login fixture F11→F6, O12→O7, R2; dc-plain/dc-header/dc-body/dc-footer all NO DRIFT, FULL==SIM;
responsive drift 0 @375/576/768/1280; 24/24 status 200, 0 overflow, 0 FULL console errors) while `.ui-skip-link`, `.ui-notice`,
`.ui-micro-label`, `.metric-value` remain ui.css-owned; deletion of those shared components NOT STARTED; `ui.css` retained + linked.*
* M6.9 (2026-09-11)
changed exactly the same two application files — appending the entire `.ui-skip-link` family (base + `:focus`, 2 rules) VERBATIM to the end of app.css and removing both rules from ui.css (with a pointer comment; the Batch B sr-only/focus-* twin untouched) — the base rule was proven a LIVE blocker of the focus contract by the deletion-sim (its `text-decoration:none` re-surfaced as an underline on the VISIBLE focused pill when ui.css was aborted, plus it fixes the hidden-state geometry), so per the M6.9 scope-conditional it migrated with `:focus`; proven by the deletion-sim re-run to remove ALL skip-link drift (ownedCount 7→6 / fixtureCount 6→5 on all /login viewports; `.ui-skip-link` ownedDrift row and `skip` fixtureDrift row gone; interactions `skipFocused` FULL==SIM left 0px rectLeft 0.000 in both modes — previously sim 14px; Gate-1 snapshots A==B==C byte-identical incl. the 5-focusable global Tab chain and 0 console/page errors; 24/24 status 200, 0 overflow, reduced-motion + mcHover regression-free; build `app-BP5CUd8L.css` 82.31 kB); while `.ui-notice`,
`.ui-micro-label`, `.metric-value`, `.ui-empty` remain ui.css-owned; deletion of those shared components NOT STARTED; `ui.css` retained + linked.*
* M6.10 (2026-09-11)
changed exactly the same two application files — appending the entire `.ui-notice` family (single base rule, 1 rule) VERBATIM to the end of app.css and removing that rule from ui.css (with a pointer comment; the Batch C twin kept as mirror; the orphan `.ui-notice-info` class has no CSS rule anywhere and stays untouched) — proven by the deletion-sim re-run to be fully transparent (Gate-2 report **byte-identical to the M6.9-final report in every one of the 24 cells, 0 deltas**; `.ui-notice` continues to measure value-identical FULL==SIM on every rendering page; Gate-1 snapshots A==B==C byte-identical across 5 real compositions × 4 viewports incl. child `<span>`/`<svg>` rects, 0 console/page errors; 24/24 status 200, 0 overflow, reduced-motion + mcHover/skipFocused regression-free; build `app-Bd_LWcVo.css` 82.52 kB; a transient `grantee-search/grantee` 500 on three pages traced to XAMPP MySQL being down — restarted `mysqld`, re-run clean, no CSS involvement); while `.ui-micro-label`,
`.metric-value`, `.ui-empty` remain ui.css-owned; deletion of those shared components NOT STARTED; `ui.css` retained + linked.*
## M6.11 - `.ui-micro-label` family (single base rule) ownership migrated to app.css (2026-09-12, COMPLETE)

changed exactly the same two application files - appending the ENTIRE `.ui-micro-label` family (single base rule, 1 rule)
VERBATIM to the end of app.css (end-of-file position, immediately after the M6.10 `.ui-notice` port block, same-line
EOF pointer comment in M6.10 house style) and removing that one rule from ui.css (with a pointer comment; the M6.3
Batch-C twin kept as mirror; the pointer kept) - proven by the deletion-sim re-run (M6.11 FIXED-FULL report, deletion-sim
re-run) to be fully cascade-neutral (24/24 report cells byte-identical to the M6.10-FINAL baseline report incl. every
ownedDrift/fixtureDrift family-set cell; `.ui-micro-label` continues to measure computed FULL==SIM value-identical on
every rendering page incl. the 4-viewport login fixture, 0 console errors, 0 overflow) while `.ui-micro-label` remains
ui.css-pointer-owned (0 live rules in ui.css - pointer comment only; the twin + twin-mirror live in app.css) and the
remaining families (`.metric-value`, `.ui-empty`, `.ui-notice`/inf twins, `.ui-skip-link`, `.ui-notice-info`
orphan) remain ui.css/owned; deletion of those shared components NOT STARTED; `ui.css` retained + linked.

## M6.12 - .metric-value family ownership to migrate (2026-09-12, NOT STARTED)

Exact next phase after M6.11-COMPLETE: .metric-value base rule - SAME two-file contract proven by M6.11
(M6.3 Batch-C twin retained, delete nothing shared, ui.css retained + linked, EOF port + pointer comment in house style,
Gate-1 A==B==C byte-identical, Gate-2 deletion-sim byte-identical to M6.11-FINAL report). Not started; no files changed for M6.12 yet. STOP.

**2026-09-12 M6.13 LANDED (final dependency/foundation/deletion gate re-audit — AUDIT-ONLY, no deletion. Fresh read-only census: public/css/ui.css 22,148 B md5 CC5140C1E7111B82E9D07455A0AE0552 = 48 live selector blocks / 43 families [pagination §4.4 .page-link/-item @L208-217, focus-visible @L63, reduced-motion @L89, Reboot+type element base (body/h1-h6/p/ol/dt/dd/blockquote/b/hr/abbr[title]/address/a/img,svg/table/caption/th/thead/label/button/input/select/textarea), §5 ui-page-* & ui-empty pointer-only]; app.css 115,995 B md5 F3B87650EE375395582F434E3F736978 canonical owner of every earlier-migrated twin + .page-link DataTables seam twin @L1196; datatables.css byte-pin HELD 21,563 B / md5 2EE627B74B0FD170727506AFD46C6C46 [byte-identical, carbon untouched] — DataTables boundary CLEAN, pagination family RETAINED-FOR-CASCADE (12 live consumer views incl. dashboard metric tiles @dashboard.blade.php L112/123/134/145 + DataTables partners, no app.css general page-link twin to inherit .page-link base without DRIFT → deletion would change live computed style); Bootstrap/legacy: 0 Bootstrap CDN/JS refs beyond the two ui.css+datatables.css <link>s in layout, 0 .show/.collapsed/.btn-close/.modal residues requiring ui.css, Alpine+Tailwind contract intact; VERDICT not SALVED/BLOCKED — foundation Reboot+type + pagination remain ui.css-live and deletion NOT deletion-safe without a follow-on per-family verbatim twin; ui.css RETAINED + LINKED (8 links untouched); build app-*.css 115,995 B, view:cache OK, Pint PASS, suite 301/1419/0, git diff --check clean, no git op; docs 3-file scope only; M6.14 NOT STARTED)**

## 2026-09-12 M6.14 LANDED — general .page-link/.page-item pagination ownership EPOCH (VERBATIM port → app.css EOF canonical owner; ui.css RETAINED + pointer; 8 <link>s + 12-consumer seam untouched)

Migration SUCCESS — byte-precise A/B/C (PRE 22,148B/CC5140C1E7111B82E9D07455A0AE0552 → PORT append app.css EOF + pointer → REMOVE general family from ui.css replaced with house pointer). REFRESH census (post-land): public/css/ui.css 22,524 B md5 C35D4A9BB38B74406D7A665D392A4656 — window only pointer @L206-216, ZERO general .page-link/.page-item selectors (family: .page-link base/:hover/:focus & .page-item.active .page-link @ui.css L208-220 moved VERBATIM to resources/css/app.css EOF); ui.css RETAINED + LINKED (8 <link> tags intact); resources/css/app.css 117,057 B md5 4DDB45FA9DF420E58D2AECEA27FA7A1C — canonical EOF owner of general pagination family (appended @EOF, byte-identical values: family block md5 5017A7292A00CF69972EECFCEE1BDA20 = 4 selectors incl. DT DataTables-scoped twin dataTables_wrapper .dataTables_paginate .page-link seam @L1196 RETAINED scoped, higher specificity, NOT a general twin; DT pagers win the DataTables boundary — A==B==C byte-identical); public/css/datatables.css PIN HELD 21,563 B md5 2EE627B74B0FD170727506AFD46C6C46 (byte-pinned untouched; DataTables Sti scoped .page-link seam @L1196 canonical). DataTables boundary CLEAN + rounded (DT wrapper scoped seam RETAINED app.css @L1196 — .dataTables_wrapper .dataTables_paginate .page-link full-family styles in datatables.css scoped .dataTables_wrapper .dataTables_paginate seam untouched → DT pagination remains canonical app.css seam via scoped twin @app.css L1196; Blade/JS consumers UNTOUCHED: 12 Blade files page-link / 12 page-item — 8 link tags intact, dashboard metric tiles @dashboard L112/123/134/145, zero JS/Blade changes; Alpine+Tailwind contract intact). REGRESSION HELD: `npm run build` ✓ 58 modules (app.css 117,057B → public/build/app-*.css; view:cache OK; PaPint `--test --dirty` PASS; PHPUnit **301 passed / 1,419 assertions / 0 failures** (suite baseline held; no deletion); datatables.css byte-pin re-verify HELD; git diff --check clean; ui.css retained + linked 8 <link>s; Blade/JS 0 changes; docs 3-file scope only (IMPLEMENTATION_LOG / SESSION_HANDOFF / UI_CSS_RETIREMENT_PLAN). M6.14 COMPLETE — M6.15 NOT STARTED.

## 2026-09-13 M6.15 LANDED — FINAL deletion-safety gate for ui.css (AUDIT + full-tree deletion-simulation on the REAL live app; CASE A)

Verdict: **M6.15 SAFE — ui.css deletion authorized; actual deletion NOT PERFORMED.** ui.css RETAINED + LINKED (8 <link> tags intact everywhere); ONLY docs changed (IMPLEMENTATION_LOG.md / SESSION_HANDOFF.md / UI_CSS_RETIREMENT_PLAN.md); no git ops; no schema/ACL/route/Blade/JS/CSS change; no deletion performed (CASE A — retirement plan contains no M6.15 actual-deletion procedure).

CENSUS + PINS HELD: public/css/ui.css 22,524 B md5 C35D4A9BB38B74406D7A665D392A4656 (unchanged post-M6.14; window-only pointer @L206-216, ZERO general .page-link/-item; live remainder = body font @L34 + h1-h6 font @L38-41, :focus-visible @L67, prefers-reduced-motion @L88, Reboot block @L264-296, heading keys @L300-311, .ui-page-header/-title/-subtitle/-actions @L357-380, .ui-empty @L393-397); resources/css/app.css 117,057 B md5 4DDB45FA9DF420E58D2AECEA27FA7A1C canonical owner of every twin (body @L388, h1-h6 @L396, :focus-visible @L489, prefers-reduced-motion @L497, Reboot @L426-458, heading keys @L462-473, .ui-empty @L799, DT seam @L1196, skip-link @L2783-2799, EOF pagination @L2886-2898); public/css/datatables.css PIN HELD 21,563 B md5 2EE627B74B0FD170727506AFD46C6C46 (carbon untouched; DataTables boundary CLEAN).

CONSUMER AUDIT: `.ui-page-*` + `.ui-empty` = 0 references in resources/views, resources/js, public/js, app/, routes/ and the compiled bundle → DEAD — PROVEN (0 live consumers; re-verifies the M6.7 inventory).

DELETION-SIM (chromium harness m615-lib/run/pval; 27 reachable pages × 375/576/768/1280 = 108 cells, FULL vs ui.css-aborted SIM; temp super-admin m615_sim used for login, fully reversed after): status 200 FULL==SIM everywhere; overflow 0/0; FULL console errors 0 (the 8 CSRF 419 console lines appear IDENTICALLY in BOTH modes on /transactions + /admin/users — probe-context artifact, NOT stylesheet/cascade-driven); SIM adds ONLY the established 1 aborted-stylesheet net::ERR_FAILED per capture; page errors identical FULL==SIM (3 admin pages addEventListener-null TypeError, pre-existing); window.bootstrap undefined 216/216 captures.

EXACT PARITY: ZERO computed-style/geometry drift on every REAL page across all formerly ui.css-owned families (Reboot/type incl. fixture-probed blockquote/abbr[title]/dl/dt/dd/hr/table/caption/th/thead/label/button/input/select/textarea/img/svg; :focus-visible; prefers-reduced-motion collapse identical 1e-5s; body/h1-h6 fonts). The ONLY drift is 52/108 cells on the SYNTHETIC injected fixture `<p class="ui-empty">`: padding FULL 24px/16px vs SIM 21px/14px + fixture rect h 70.39→64.39px — root cause ui.css var(--ui-space-6)/var(--ui-space-4) vs app.css Batch-C twin `@apply px-[1rem] py-6` evaluated at the 14px root → `.ui-empty` is removal-eligible DEAD (0 consumers; NO real page renders it), so this is the expected disappearance of an unused family, classified DEAD — PROVEN and NOT a blocker; the app.css twin true-value delta (14px/21px vs historical 16px/24px) is DOCUMENTED for a future Batch-C unify if the class is ever resurrected.

INTERACTIONS (6 records, /login + / + /clients @768/1280): skip-link hidden/focused/lost FULL==SIM byte-identical (focused left 0 top 10.5 z50 fixed w145.39 h36.55 gold #FCD116 3px outline; hidden left -999; Tab re-wraps); DataTables pagination normal/hover/focus/active/disabled FULL==SIM byte-identical wherever the pager renders (/clients 7 items, /scholars 2 items @375/768/1280); /transactions + /admin/users equally never render the pager in either mode (identical CSRF artifact) → parity holds in both states. reduced-motion honored identically both modes.

BOOTSTRAP/LEGACY: window.bootstrap undefined all captures; 0 Bootstrap CDN/CSS/JS links in views or compiled bundle; 0 live data-bs-* attributes (`[data-bs-popper]` live element count 0 — the app.css .dropdown-menu[data-bs-popper] selectors are inert); Alpine dropdown/accordion/modal contracts intact; DataTables seam .dataTables_paginate .page-link IDENTICAL FULL==SIM.

REGRESSION HELD: npm run build ✓ byte-identical bundle (public/build/assets/app-DTuKuttB.css 83,025 B + manifest unchanged), view:cache ✓ / view:clear ✓, Pint --test --dirty PASS, PHPUnit **301 passed / 1,419 assertions / 0 failures**, git diff --check exit 0 / 0 bytes, 8 ui.css <link>s intact.

DB HYGIENE: temp user m615_sim (id 10) + its id-19 tbl_permissions row (user_id 10, page_name *, can_access 1) + its LOGIN audit row deleted → tbl_audit_logs back to 1,612 rows / max id 1,742, only users jordi/jiro remain; no schema change; AUTO_INCREMENT gaps left as documented non-destructive artifacts.

This gate AUTHORIZES deletion (ADA); actual removal of public/css/ui.css and the 8 <link> tags is a SEPARATE future execution milestone (not yet scheduled) that must dump/archive the file first. ui.css remains RETAINED + LINKED. STOP — no M6.16.
## M6.16 — EXECUTED: ui.css RETIRED + DELETED + post-delete verification (2026-09-13)

Prerequisite: M6.15 GATE verdict "M6.15 SAFE - ui.css deletion authorized; actual deletion NOT PERFORMED."
Authorized solely by M6.15; no M6.13/M6.14/M6.15 evidence was re-audited.

EXECUTION:
- Pre-delete snapshot verified: public/css/ui.css = 22,524 B / MD5 C35D4A9BB38B74406D7A665D392A4656 / SHA256 f62d56c37aefe672... (LF 397, CRLF 0, bareCR 0); archived byte-identical to C:\Users\J\AppData\Local\Temp\opencode\m616\ui.css.archived.
- Deleted public/css/ui.css and removed its exactly 8 <link> references (byte-safe Node fs idempotent script):
  layouts/app.blade.php:13, auth/login.blade.php:17, qr/viewer.blade.php:17, grantee_update/self-service.blade.php:16,
  unpaid_verifications/self-service.blade.php:17, students/photo-upload.blade.php:15, students/update-photo.blade.php:12, students/verify.blade.php:12.
- Post-delete census: 0 live css/ui.css references anywhere in the repo (only historical css/doc comments remain); no ui.css file exists on disk anywhere in the tree.

OUT-OF-SCOPE (reported): e2e/alpine-phase0.spec.ts adapted because its assertion link[href$="ui.css"] toHaveCount(1) contradicted the retired state - flipped to toHaveCount(0) and added link[rel="stylesheet"][href*="/build/assets/app-"] toHaveCount(1) (AGENTS.md Playwright rule: tests must verify the application as it actually is).

POST-DELETE BROWSER GATE (108 cells = 27 pages x 4 viewports; PRE = archived ui.css injected at the original cascade position - after the vite stylesheet link, before inline <style>):
- Drift cells: 52 - ALL on the synthetic .ui-empty fixture p (padding*/rect.h deltas on the DEAD family, M6.15-classified); 0 unexpected real-element drift; exactly reproduces M6.15's 52-cell signature.
- Status 200/200 x108; horizontal overflow 0/0; window.bootstrap undefined 0/0 cells; console-error cells 8/8 (CSRF-419 probe-context artifact on /transactions + /admin/users, identical in both modes); page-error cells 12/12 (3 admin addEventListener-null, pre-existing); interactions (/login, /, /clients @768/1280) 0 non-identical; reduced-motion (/login, / @375/768/1280) 0 non-identical.
- POST ui.css requests = 0 (deleted link); PRE sheet applied 0 warns; PRE position (after vite css link) 0 flags.

REGRESSION HELD: npm run build ✓ byte-identical bundle (public/build/assets/app-DTuKuttB.css 83,025 B + manifest unchanged, zero git changes in public/build), view:cache ✓ / view:clear ✓, Pint --test --dirty PASS, PHPUnit **301 passed / 1,419 assertions / 0 failures**, git diff --check exit 0 / 0 bytes.

DATA/SCHEMA: no schema / ACL / route / JS / PHP change. datatables.css pin HELD (21,563 B / MD5 2EE627B74B0FD170727506AFD46C6C46). resources/css/app.css unchanged (117,057 B / MD5 4DDB45FA9DF420E58D2AECEA27FA7A1C).

DB HYGIENE: temp user m615_sim (id 11) + its tbl_permissions '*' row (user_id 11) + all M6.16 audit rows deleted -> tbl_audit_logs back to 1,612 rows / max id 1,742; only users jordi/jiro remain; no schema change; AUTO_INCREMENT gaps left as documented non-destructive artifacts (tbl_users 12, tbl_permissions 21, tbl_audit_logs 1745).

GIT: NO add / commit / reset / checkout / staging performed; the pre-existing dirty tree (29 baseline status entries captured at temp/m616/baseline-status.txt) was left untouched; only M6.16's own changes exist.

M6.17 NOT STARTED.
---
