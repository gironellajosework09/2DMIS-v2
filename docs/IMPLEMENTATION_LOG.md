## 2026-09-26 - Clients action feedback restored: unified notify RENDERER completed + full-page profile flash consumption (verified 370/1875/0 + browser probes)

**Scope.** Pure presentation/client-side. No backend endpoint, controller method, model method,
migration, schema change, business rule, ACL, DataTables, or DetailsPanel.js change. The unified
notify algorithm (`window.notify`, store, timers, persistent-warning contract, icon/ring semantics)
is UNCHANGED — this session completed the stack's missing renderer and wired the two untouched
flash sources into it. Recent responsive/full-page/header/photo/QR work untouched.

**Root cause 1 — the unified notify stack had no renderer.** Phase 23 shipped
`resources/views/partials/unified-notify.blade.php` with a complete store
(`Alpine.store('unifiedNotify')`: `items`, `open`, auto-dismiss timers per type, persistent
warnings, `dismiss(id)`) and an imperative `window.notify()` API, but the `#unifiedNotifyStack`
container was empty — NOTHING ever iterated `items`, so no toast was ever drawn on ANY screen.
Proven in chromium: after `notify()`, `#unifiedNotifyStack .toast` stayed at `0` on both the clients
profile and the clients index. This is why every action's server-side success/error message vanished
into thin air app-wide.

**Root cause 2 — the full-page client profile dropped every redirect flash.** Add Family Member
(`FamilyMemberController` → `with('success', ...)`), full-page GIP save (`GipController` → redirect
`clients.show#collapseGIP`), and full-page Change Photo (`PhotoController` → `with('success', ...)`)
all land on `clients.show`, whose view had no flash consumer (`grep session('...')` found nothing
there). Full-page Edit (`_details` `wireFormSubmit`) additionally called `window.location.reload()`,
discarding the JSON `data.message` before any `window.notify` could run. Panel mode was never an
issue: `?panel=1` renders `_details` directly, so any consumer added to `clients.show` only exists on
the full-page render (no double-fire).

**Changes.**
- `resources/views/partials/unified-notify.blade.php` — added the renderer:
  `<template x-for="item in $store.unifiedNotify.items" :key="item.id">` drawing one reusable toast
  card (mirrors the existing clients-index toast geometry: `pointer-events-auto flex w-full
  max-w-[420px] items-start gap-[12px] rounded-panel bg-surface p-[1rem] shadow-pop ring-1 ring-line`)
  with a per-type accent ring via two new helpers exposed on `unifiedNotifyComponent` — `iconFor(type)`
  and `ringFor(type)` (reuse the already-defined `ICONS` + `typeRing`) — plus an optional `actions`
  renderer and a Close button `@click.stop="$store.unifiedNotify.dismiss(item.id)"`. Container
  re-anchored `left-[24px] right-[24px] ml-auto max-w-[420px]` (was `w-full right-[24px]`, which let
  the toast's left edge clip offscreen below ~444px — the width probe caught it). The toast card
  intentionally has no `role="status"` so the container's `aria-live="polite"` handles announcements
  and the legacy `assertDontSee('role="status"')` tests in `TransactionTest`/`HouseholdTest` stay
  green.
- `resources/css/app.css` — one @source allowlist line:
  `@source "../views/partials/unified-notify.blade.php";` (the partial's accent classes
  `bg-amber/[0.12]`, `text-amber`, `bg-red/[0.12]` had never been compiled under the
  explicit-allowlist Tailwind v4 setup; `npm run build` now emits them).
- `resources/views/clients/show.blade.php` — `@push('scripts')` consumer: `session('success')`
  (rendered via `Js::from`) plus the `2dmis_client_flash` sessionStorage stash are pushed through
  `window.notify({ type:'success', title:'Success', message })` on ready (helper retries until Alpine
  and `document.body` exist; stash is read once then removed).
- `resources/views/clients/_details.blade.php` — full-page Edit success path stashes
  `sessionStorage.setItem('2dmis_client_flash', JSON.stringify({ message: data.message || 'Client
  saved successfully.' }))` before `window.location.reload()`.
- `tests/Feature/ClientProfileFlashToastTest.php` (NEW) — 5 tests: gip flash consumed; photo flash
  consumed; photo error surfaces on profile (layout `$errors->any()` alert); show page ships the
  consumer + stash writer while the panel response does NOT ship them; Add Family Member 500
  regression probe (see Disclosure).

**Verification.**
- `npm run build` OK — new `app-*.css`/`app-*.js` hashes; `bg-teal/[0.12]`, `bg-red/[0.12]`,
  `bg-amber/[0.12]`, `text-amber` confirmed present.
- `php artisan view:cache` OK.
- `php artisan test`: 370 passed / 1,875 assertions / 0 failures (the `role="status"` regression the
  new layout template could have caused was prevented by design — attribute omitted).
- `vendor\bin\pint`: no PHP changed by this work.
- `git diff --check`: clean (2 pre-existing CRLF notices on `database/schema/mysql-schema.sql` +
  `docs/IMPLEMENTATION_LOG.md` only).
- Chromium temp probe (get-only; no `main_system` writes; temp seed session + throwaway spec,
  removed): 5/5 — toast truly renders carrying the message text; full-page Edit stash-flush shows
  the toast EXACTLY once and it stays gone after dismiss + reload (proves the once-only contract);
  no toast is invented when there is no flash/stash; the renderer also works on the clients index;
  no horizontal overflow (document and notify container) at 320/375/480/640/768/1024/1440.
- Cleanup: temp server (8100) stopped, `probe-*.spec.ts`, `probe_seed_session.php`, cookie helper,
  `test-results` all removed.

**Disclosure — pre-existing Add Family Member 500 (out of scope, backend).** v2
`FamilyMemberService::link()` audits `ADD_FAMILY_MEMBER` with `target_id = null`
(`app/Services/AuditService.php` inserts it verbatim) into `tbl_audit_logs.target_id` which is
`int(11) NOT NULL` (`database/schema/mysql-schema.sql:165`) → `SQLSTATE[23000] 1048` rolls back the
whole `DB::transaction`, so the member is never linked and the store's flash never exists (no toast
possible — correct v2 behavior, but the flow is broken). v1 `add_family_member.php` wrote NO audit.
Converted the flash-consumer test into a passing regression probe. A separate backend decision is
needed: audit the family-member row id, or match v1 and drop the audit call.

**STATUS: CLIENTS ACTION FEEDBACK - ROOT CAUSE VERIFIED, COMPLETE (browser-proven renderer restored).

---

## 2026-09-25 - Clients: two surgical UI fixes — Export CSV overflow + Details Panel Add Family Member (verified 365/1847/0 + browser probes)

**Scope.** Clients module only, presentation/layout-only. No backend endpoint, controller method,
model method, migration, schema change, business rule, ACL, or DataTables change. No new
dependency. No app-wide CSS (no edits to `app.css`/`resources/css`). No JS/DetailsPanel.js change.
No change to the earlier full-page composition / responsive-header / photo / QR work. Two files.

**Fix 1 — Export CSV dropdown overflow (root cause).** The Export CSV group on the Clients index
(`clients/index.blade.php`) is an Alpine dropdown (`@click="open = !open"`, `:class=
{ 'dropdown-open': open }`) whose menu is `<ul class="dropdown-menu dropdown-menu-end">`. The
positioning rules that right-align such a menu live in app.css (M6.3.5, ported VERBATIM from
ui.css §4.7): `.dropdown-menu[data-bs-popper]` (`top:100%; left:0; margin-top:.125rem`) and
`.dropdown-menu-end[data-bs-popper]` (`right:0; left:auto`). Real Bootstrap Popper writes the
`data-bs-popper` attribute at runtime; since this menu is Alpine-driven it NEVER carried it, so
`top/left/right` were all unset. A `position:absolute` menu with no insets stays at its static
position inside the `display:inline-flex` `.btn-group` — its left edge anchored at the group's
left, opening 164px (`min-width:10rem`) to the RIGHT, past the viewport edge, at EVERY width.
Chromium probe captured the failure before the fix: open menu `x=btnX`, `right` ~38px past the
viewport and `documentElement.scrollWidth > innerWidth` (document-level horizontal page scroll) at
1440/1024/768/640/480/375/320. **Fix:** add the single `data-bs-popper` attribute to that `<ul>
class="dropdown-menu dropdown-menu-end" data-bs-popper :class="...">`, so the existing app.css
popper offsets apply: menu now drops just below the button (`top:100%`) and right-aligns
(`right:0; left:auto`), fully inside the viewport at every width with zero page overflow. Label,
icon, hrefs, `data-clients-export` handlers, `dropdown-menu`/`dropdown-item`/`dropdown-toggle`
classes, Alpine wiring, route and permissions are byte-unchanged. (The navbar user menu and the
Transactions export dropdown share the same latent missing-attribute pattern — out of scope,
Clients only, deliberately not touched.)

**Fix 2 — Details Panel Family Composition "Add Family Member" missing/inaccessible (root cause).**
The Clients Details Panel renders `clients/_details.blade.php` via AJAX with `$isPanel = true`. The
existing full-page "Add Family Member" control (`<a href="{{ route('family-members.create',
$client) }}" class="btn-subtle no-underline">+ Add Family Member</a>` — the pre-existing
route/controller/view/store, ACL `page:clients.php` group + `action:clients.php,create`, and the
client-search/relationship form) was wrapped in `@if (! $isPanel)`, so panel mode OMITTED it
entirely — no replacement, no alternative entry point. **Fix:** drop the `@if (! $isPanel)` gate so
the exact same link renders inside the panel's Family Composition accordion, reusing the existing
full-page create flow (same anchor → same route → same create form → same store + validation). No
new modal/endpoint/monitor invented. Accordion markup, `#famToggle`/`#famPanel` ids, and the
accordion script are untouched.

**Verification.**
- `php artisan view:cache` OK. `npm run build` NOT required (no new Tailwind classes / CSS;
  `data-bs-popper` is matched by existing app.css rules only).
- Full PHPUnit suite **365 / 1,847 / 0** (note: requires `C:\xampp\mysql\bin` on PATH; without it
  RefreshDatabase fails on the `mysql` client — known Gotcha).
- `git diff --check` exit 0 (pre-existing CRLF warnings only).
- Real-browser probes (chromium, temp seeded `jordi` session + throwaway spec since removed) at
  1440/1024/768/640/480/375/320:
  1. Export CSV button completely visible, no horizontal overflow (closed state) at every width.
  2. Export CSV menu OPEN: menu + both items fully in viewport, `scrollWidth == innerWidth`
     (no page overflow) at every width — the exact failure captured pre-fix is gone.
  3. Export behavior unchanged: clicking "Export Current Filter" fires
     `GET /clients/export?municipality=…` (filter mirror + search), "Export All Clients" fires
     `GET /clients/export?export_all=1`.
  4. Details Panel: row click → panel → Family Composition accordion expands; "+ Add Family
     Member" link present and visible inside the panel body, href `/family-members/{id}`; clicking
     it loads the existing create form (`#family-member-screen`, `#existing_client_search`,
     relationship select, POST action `/family-members/{id}`).
  5. Accordion `aria-expanded` toggling intact in both modes.
  6. Full-page profile regression: zero horizontal overflow at all 7 widths; Family Composition
     link present (visible once the default-collapsed accordion is expanded); `Client Profile`
     h1 + `#clientQrImage` sentinels present; header/photo/QR unchanged.
- Harness cleanup: throwaway spec + cookie helper deleted, temp 8100 server stopped, `test-results`
  removed, no DB/schema/route change (only the app's own `last_activity` heartbeat is written by
  EnsureSingleDevice on authenticated reads, as with any login).

**STATUS: CLIENTS EXPORT CSV OVERFLOW + PANEL ADD FAMILY MEMBER — COMPLETE (verified 365/1847/0 + all-width browser probes)

---

**Scope.** Presentation-only polish of the full-page client profile
(`resources/views/clients/_details.blade.php`, `@if (! $isPanel)` branch). No route/controller/
JS/DB/schema/ACL/logic change; Details Panel output stays **byte-identical** (all additions are
gated with `@class([...]) => ! $isPanel` so panel mode renders the exact prior classes). No new
dependency, no inline styles, no `!important`.

**Changes (all full-page-only).**
- `data-panel-body` card: class list now `@class(['data-card p-[1.25rem]',
  'mx-auto w-full max-w-[80rem] [&_.details-section]:mx-auto [&_.details-section]:w-full
  [&_.details-section]:max-w-[60rem] [&_.accordion]:mx-auto [&_.accordion]:w-full
  [&_.accordion]:max-w-[60rem]' => ! $isPanel])`. Full page: a centered 1280px (80rem) card with
  every shared section (`details-section`) and accordion capped at 960px (60rem) and centered —
  taming the previously full-column-width, left-aligned sections. The arbitrary-variant scoping
  compiles to descendant selectors (`.parent .details-section { … }`), so shared sections render
  the same in both modes; panel mode keeps exactly `data-card p-[1.25rem]`.
- QR Identity Card: its card is now `@class(['data-card p-[1.25rem]', 'md:w-fit md:max-w-full'
  => ! $isPanel])` — compact (fit-content) on desktop instead of stretching full column width.
- Header action group: the Add Transaction / Edit / Delete controls are wrapped in a single framed
  group (`flex w-full flex-wrap items-center gap-[8px] rounded-control ring-1 ring-line-light
  px-[4px] py-[3px] md:flex-nowrap md:px-[6px] md:inline-flex md:w-auto`); ACL checks, hrefs,
  `data-*`, form/CSRF, and their order are unchanged. Each control keeps `grow md:grow-0` so the
  group stacks full-width on mobile and sits as one compact unit at the header right on desktop.
- Identity block: `md:items-center md:gap-[24px]` (was `md:items-start md:gap-[16px]`) for
  comfortable vertical centering of photo/name against the action group; photo container centered
  on mobile (`flex w-full flex-col items-center md:w-auto md:shrink-0`).

**Verification.**
- `php artisan view:cache` OK; `npm run build` OK — new asset
  `public/build/assets/app-BgKt1fa_.css` (95.50 kB) contains the compiled descendant selectors
  (`.[&_.details-section]:mx-auto .details-section { margin-inline: auto }`, the 60rem/80rem
  caps, etc.; `80rem` ×5, `60rem` ×4).
- Full PHPUnit suite **365 / 1,847 / 0** (includes the panel-mode byte-identity coverage in
  `ClientDetailsPanelQrCardTest`); `git diff --check` clean (pre-existing CRLF warnings only).
- Real-browser probes (chromium, temp seed-session helper + throwaway spec, since removed) on
  client `TESTCLIENT 0014` at 1440/1024/768/640/480/375/320: **zero horizontal overflow** at every
  width; header identity + action group present with Add < Edit < Delete x-ordering and the Change
  Photo button; `data-panel-body` card width ≤ 1284px centered on the content column
  (`#main-content`, which has the 260px sidebar offset); all 8 key sections visible; QR card
  < 800px wide; Personal section ≤ 964px (60rem) centered. Harness + helper removed, temp
  `8100` probe server stopped, `jordi.session_token` reset to NULL.

**STATUS: FULL-PAGE PROFILE VISUAL COMPOSITION PASS — COMPLETE (verified 365/1847/0 + all-width browser probes)

---

**Scope.** Presentation-only scrollbar polish for the intentional Clients-module scroll containers.
ONE reusable treatment defined once in `resources/css/app.css` — thin (`scrollbar-width: thin` +
`scrollbar-color: var(--color-ink-muted) var(--color-bg-alt)`, a faint `#E8EBF0` track with a
`#70798B` ink thumb), rounded (`border-radius: 999px`) WebKit scrollbar at 8px, slightly darker
thumb on hover (`--color-ink-secondary`). Applied via the new `.scrollbars-subtle` utility class on
Clients-owned blades plus scoped selectors for shared/JS-managed containers. No JS, no inline
styles, no `!important`, no DOM changes, no new dependency, no overflow/layout/dimension change, no
other module touched.

**Containers styled (and HOW each is targeted).**
- Index table horizontal wrapper — class added on `clients/index.blade.php:365`
  (`<div class="scrollbars-subtle overflow-x-auto px-[1.25rem] pb-[1.25rem]" tabindex="0"
  aria-label="Client table, scrolls horizontally on narrow screens">`).
- Family-members table wrapper — `clients/_details.blade.php:256`; Transactions table wrapper —
  `clients/_details.blade.php:289`; both `<div class="scrollbars-subtle overflow-x-auto">`.
- Photo modal outer scroll wrapper — `clients/_details.blade.php:399` (present in both full-page and
  panel `?panel=1` renders since `_details` is the shared partial).
- GIP modal body — `clients/_gip.blade.php:122`
  (`<div class="scrollbars-subtle max-h-[70vh] overflow-y-auto p-[1.25rem]">`).
- Details Panel body — scoped selector `.details-panel[data-module="clients"] .details-body`
  (no class on the shared `partials/details-panel.blade.php` shell; DetailsPanel.js already tags
  `data-module="clients"` on load, so the treatment applies only while a Clients panel is open).
- Filter popover menu — scoped selector `#clients-screen .filter-multi-scroll` (the shared
  `partials/filter-chips.blade.php` is used by many modules; the `#clients-screen` ancestor limits
  the rule to the Clients index page).
- Add/Edit modal body `#clientFormModalBody` and feedback modal body `#clientFeedbackBody` — id
  selectors in app.css (both IDs are Clients-only; the partial in `clients/index.blade.php` and the
  JS-injected duplicate in `clients/_details.blade.php` share the same IDs, so both copies are
  covered without touching JS or the shared partials).

**Deliberately NOT styled.** The full-page profile scrolls at document level (no per-page overflow
container exists; the shared app layout owns the viewport scrollbar), so a "full-page Clients
content" scrollbar cannot be scoped without a global/body-level rule — the task forbade global
scrollbar styling, so it is left at the browser default. Global-search dropdown, sidebar nav, and
all non-Clients modules keep default scrollbars.

**Build.** `npm run build` regenerated `public/build/assets/app-CbyW57T0.css` (94.79 kB)
(JS `app-DqsLDVL_.js` unchanged).

**Verification.**
- Full PHPUnit suite **365 / 1,847 / 0**; `php artisan view:cache` OK; `git diff --check` clean
  (pre-existing CRLF warnings only).
- Compiled bundle contains the full selector group (utility + details-panel scope + `#clients-screen`
  filter scope + both modal-body id scopes × base/scrollbar/track/thumb/hover rules).
- Render-harness dumps (`clients/index`, `clients/{id}`, `clients/{id}?panel=1`): every container
  above carried the class/text in real output. GIP modal verified with a GIP transaction present.
- Chromium probes (index + profile) at 1440/1024/768/640/375/320: **zero horizontal overflow**
  (`scrollWidth == clientWidth`) everywhere; header order + back-link geometry unchanged
  (`back_above_title` 9px desktop / 6px mobile); `.scrollbars-subtle`-tagged index/profile table
  wrappers are still horizontally scrollable on narrow widths (375/320) and get
  `scrollbar-width: thin`; `.filter-multi-scroll` shows thin + ink colors; the details-body scope
  computes `thin` only after `data-module="clients"` is applied (i.e. scoping works). Throwaway
  harness + probe scripts/file-server removed.

## 2026-09-25 - Clients full-page back link: chevron control → text nav link (Blade/Tailwind only, verified 365/1847/0)

**Scope.** Presentation-only polish of the full-page Clients profile back navigation
(`resources/views/clients/_details.blade.php`, `@if (! $isPanel)` branch). The Details Panel
version (its own `data-panel-actions` button) is untouched. No route/href, click, history, JS,
title, identity, action-button, breakpoint, ordering, or desktop-layout change. No app.css change,
no inline styles, no `!important`, no other module touched.

**Why.** The full-page profile previously embedded a bare chevron glyph
(`<a class="back-btn no-underline">&#10094;</a>`) inside the `<h1>` — a standalone-looking
control with no label, not a navigational affordance.

**Change.**
- Removed the chevron `<a>` from inside the `<h1>` (h1 is now plain `Client Profile` text).
- Added a text-based nav link ABOVE the title container (so it sits above "Client Profile" at
  every breakpoint), reusing the project's existing `.back-btn` class which already encodes the
  2DMIS nav-link look (no border, no background, resting `--color-ink-muted`, hover/focus/active
  `--color-navy`):
  `<a href="{{ route('clients.index') }}" class="back-btn mb-[6px] inline-flex items-center gap-[6px] text-dense font-medium">&#8249; Back to Client Registry</a>`
  (`&#8249;` = `‹` U+2039, a lighter left-pointing chevron than the old `&#10094;`).
- The responsive flex container (title/identity/actions order classes) was NOT restructured — the
  link is a sibling block inserted before it, so the recent mobile `<768px` and desktop `≥768px`
  header ordering is byte-identical to before.
- `npm run build` regenerated `public/build/assets/app-DWuSgcoG.css` (92.70 kB) because the
  new `mb-[6px]` and `gap-[6px]` arbitrary utilities did not exist in the previous bundle.

**Verification (Chromium probe, 1440/768/375/320).**
- Link text renders `‹ Back to Client Registry`; `href` unchanged (`route('clients.index')` → `/clients`).
- Computed style: `background-color: rgba(0,0,0,0)`, `border: 0px none`, `text-decoration-line: none`,
  `cursor: pointer`, `display: inline-flex`, `gap: 6px` — reads as navigation, not a button.
- Resting color `#70798B` (ink-muted); hover color `#0038A8` (navy) — subtle hover state.
- Focus-visible: global gold `3px solid outline` + `outline-offset: 2px` (app.css `:focus-visible`),
  verified via keyboard focus.
- `back_above_title: true` at all widths (back y=131 above h1 y=156–159, ~6–9px gap).
- Mobile (375/320): identity-y < actions-y (ordering preserved). Desktop (1440/768): h1 + actions on
  same line, identity below (ordering preserved).
- `scrollWidth == clientWidth` (zero horizontal overflow) at all widths.
- `php artisan view:cache` OK; full `php artisan test` **365 / 1,847 / 0**; `git diff --check` exit 0
  (pre-existing CRLF warnings only). Throwaway render harness + probe photo removed.

## 2026-09-25 - Clients full-page profile mobile header order (Blade/Tailwind only, verified 365/1847/0)

**Scope.** Mobile-only reorder of the full-page Clients profile header (`resources/views/clients/
_details.blade.php`, `@if (! $isPanel)` branch). On <768px the action buttons had been rendering
immediately below "Client Profile" and BEFORE the client identity, making the page feel like an
action toolbar. Presentation only — no backend, schema, route, ACL, business rule, inline JS,
Details Panel, photo modal, or other module change. Desktop (≥768px) arrangement preserved exactly.

**Root cause.** The title row (`flex items-center gap-[12px]`) contained both the h1 and the actions
container, with the identity block as a following sibling; CSS `order` cannot interleave a sibling
between children of another flex container.

**Fix (Tailwind utility classes only; one Blade-section restructure, no duplicate markup).**
- The title row became the header's single flex-wrap container:
  `mb-[12px] flex flex-wrap gap-x-[12px] gap-y-[16px] md:items-center` (bottom margin now carries
  the old identity `mb-[12px]`; `gap-y-[16px]` replaces the old title-row `mb-[16px]`).
- h1 keeps `order-0` (first) at every width.
- Identity block moved inside that container, `w-full` (full wrapped row) with
  `order-2 ... md:order-3` → rendered second on mobile, last on desktop; its inner layout classes
  untouched (stays `flex-col items-center md:flex-row md:items-start`, `max-md:text-center`).
- Actions container `w-full` (full wrapped row) with `order-3 ... md:order-2 md:w-auto md:flex-1
  md:justify-end` → rendered last on mobile, and on ≥768px reclaims its `flex-1 justify-end`
  right-aligned slot beside the h1.
- All previously-landed responsive button classes were re-keyed from `sm:` to `md:` (`grow
  md:grow-0`, `md:inline`, `w-full md:w-auto`) so the whole header flips at the single 768px
  breakpoint that this task defines (mobile below, desktop at/above).
- No `!important`, no inline styles, no app.css change, no JS.

**Verification (Chromium rect probe, 1440/1024/768/640/480/375/320).**
- Mobile (320/375/480/640): vertical order `h1 (y=129) → identity (y=172) → actions (y=401)` —
  identity (photo → Change Photo → name/ID/category) now precedes the action buttons.
- ≥768px: h1 + actions share the same line (both y≈129-132, actions `flex-end`) with identity below
  (y=179) — original desktop arrangement, spacing, and alignment preserved (16px gap between the
  title row and identity; identity bottom margin 12px intact).
- Photo centered in its column and `img center == Change Photo center` at every width; Change Photo
  `w-full` + centered on mobile; name/ID/category `text-align:center` below 768px and
  `text-align:start` (left, common x edge) at ≥768px.
- `document.documentElement.scrollWidth == clientWidth` at every width (zero horizontal overflow);
  no button overlap (`Add+Edit` row 1 / `Delete` full-width row 2 on 375/320; single line at 480+).
- `php artisan view:cache` OK (Blade compiles — div nesting verified); `npm run build` regenerated
  `public/build/assets/app-D1UMXt_Y.css` (new utilities: `flex-wrap`/`gap-x-[12px]`/`gap-y-[16px]`/
  `order-2`/`order-3`/`md:order-2`/`md:order-3`/`md:w-auto`/`md:flex-1`; Vite removed the prior
  `app-BQzchHmS.css`); `php artisan test` **365 / 1,847 / 0**; `git diff --check` exit 0
  (pre-existing CRLF warnings only). Throwaway render harness `tests/Feature/DumpClientProfileVerify.php`
  and the probe photo removed.

## 2026-09-25 - Clients full-page profile responsive action buttons (Blade/Tailwind only, verified 365/1847/0)

**Scope.** The page-actions row in the full-page Clients profile header
(`resources/views/clients/_details.blade.php`, `@if (! $isPanel)` branch): the title row
("Client Profile" h1 + the Add Transaction / Edit / Delete buttons). Presentation only — no
backend, schema, route, ACL, business rule, inline JS, or Details Panel change. The Details
Panel's own `data-panel-actions` block and its `.details-actions-line` 2×2 grid were untouched.

**Root cause (verified by Chromium rect probe, 1440/1024/768/640/480/375/320).** The title row was
`flex items-center gap-[12px]` with the actions container `flex flex-1 flex-wrap items-center
justify-end gap-[8px]`. On ≥640px all three buttons fit inline and looked fine. Below 640px the
h1 ("Client Profile" + back arrow, ~138px) held the row at its content width while the `flex-1`
actions area shrank to ~104px (375) / ~98px (320); with `justify-end` the buttons wrapped into a
ragged right-aligned column and the "+ Add Transaction" label word-wrapped to a 50px-tall,
~98–104px-wide button (480px: Add+Edit on row 1, a lone right-aligned Delete below; 375/320:
three misaligned lines). Same cramped layout the task reported as "messy."

**Fix (Tailwind utility classes only, no app.css, no `!important`, no inline styles).**
- Title row → `flex flex-col gap-[12px] sm:flex-row sm:items-center`: below `sm` (640px) the
  h1 stacks on its own line, freeing full width for the actions; ≥640px restores the original
  inline v-centered row byte-for-byte in behavior.
- Actions container → `flex w-full flex-wrap items-center gap-[8px] sm:w-auto sm:flex-1
  sm:justify-end`: full width below 640, original `flex-1 justify-end` at 640+.
- Each button (and the Delete `<form>`) → `grow sm:grow-0`; Delete button inner → `w-full sm:w-auto`;
  Delete form display `inline` → `sm:inline` so the row-2 full-width Delete is a proper bar below
  640 and a natural inline button at 640+. Delete keeps its `btn-red` destructive styling, order,
  and stacked ratio unchanged when the ACL hides buttons.

**Verification (Chromium rect probe, 1440/1024/768/640/480/375/320).**
- ≥640px: exactly the previous one-line right-aligned layout (h=34 buttons, all three sharing the
  same baseline row) — desktop/tablet behavior preserved.
- 480px: h1 stacked; Add Transaction (165) + Edit (82) + Delete (96) on one full-width line, all h=34.
- 375px: Add Transaction (165) + Edit (81) on row 1; Delete spans full width (254) on row 2 — all
  h=34, no wrapped labels, no overlap (`overlap:false`), consistent heights/touch targets.
- 320px: Add (137) + Edit (54) row 1; Delete full-width (199) row 2 — same guarantees.
- `document.documentElement.scrollWidth == clientWidth` at every width (zero horizontal overflow).
- `npm run build` regenerated `public/build/assets/app-BQzchHmS.css` (new `grow`/`sm:grow-0`/
  `sm:inline`/`sm:w-auto` utilities; Vite removed the prior `app-CSfJ8IE6.css`); `php artisan
  view:cache` OK; `php artisan test` **365 / 1,847 / 0**; `git diff --check` exit 0 (pre-existing
  CRLF warnings only). Throwaway render harness `tests/Feature/DumpClientProfileVerify.php` and the
  probe photo removed. Rebuild assets were re-generated with the same render harness + full PHPUnit
  gate after the template edit.

## 2026-09-25 - Clients profile desktop identity alignment correction (CSS-only, verified 365/1847/0)

**Scope.** Correction of the earlier same-day responsive-polish change in the full-page Clients
profile identity header (`resources/views/clients/_details.blade.php`, `@if (! $isPanel)`
branch). Desktop (≥768px) name/ID/category had been rendering CENTERED instead of left-aligned.
Presentation only — no backend, schema, route, business rule, Details Panel, or JS change.

**Root cause (verified by computed-style probe at 1440px).** The previous change had used
`text-center md:text-left` on the identity text column. Both classes compiled correctly and the
`md:` breakpoint DID apply on the element (`flex-direction:row`, `align-items:flex-start`,
`md:w-auto` all live). BUT computed `text-align` was still `center` — because
`resources/css/app.css` carries three legacy **Bootstrap helper rules with `!important`**
(lines 2398, 2505, 2612): `.text-center { text-align: center !important; }`. `!important`
beats any non-important selector regardless of specificity or source order, so Tailwind's plain
`.md\:text-left { text-align: left }` could never win. The class pair was not "wrong" — it was
structurally incapable of working against the `!important` shim that also shadows Tailwind's own
`.text-center` on this element.

**Fix.** Replace the shadowed `text-center md:text-left` with `min-w-0 max-md:text-center`:
- `max-md:...` compiles to its own class `@media not all and (min-width:48rem){.max-md\:text-center{text-align:center}}` — a DIFFERENT selector than the shadowed `.text-center`, so it is unaffected by the bootstrap `!important` shim and centers only below the `md` (768px) breakpoint.
- Above 768px the element falls back to default `text-align:start` (left), with all three text lines sharing the same left edge.
- No `!important`, no inline styles, no app.css change, no internal `<style>` block.

**Verification (Chromium computed-style probe, 1440/1024/768/640/375/320).**
- ≥768px: row layout restored (`flex-direction:row`, `items-start`, 16px gap); name, ID, and
  category all `text-align:start` and share one left edge (x=448 at 1440/1024; x=188 at 768);
  photo centered in its 111px column (img center == button center); Change Photo centered.
- <768px: stacked column (`flex-direction:column`, `items-center`, 12px gap); name/ID/category
  `text-align:center`; photo and Change Photo centered.
- `document.documentElement.scrollWidth == clientWidth` at every width (zero horizontal overflow).
- `php artisan view:cache` OK; `npm run build` regenerated `public/build/assets/app-CSfJ8IE6.css`
  (required: new `max-md:` utility; Vite removed the prior `app-C8uo1nbK.css`);
  `php artisan test` **365 / 1,847 / 0**; `git diff --check` exit 0 (pre-existing CRLF warnings
  only). Throwaway render harness `tests/Feature/DumpClientProfileVerify.php` and the probe photo
  `public/storage/uploads/client_photos/probe_photo.jpg` removed.

**Note.** The bootstrap `!important` text helpers in `app.css` are not themselves a defect of this
task (they are a legacy-compat shim used by other views); they were left untouched. Developers
should avoid `text-center` (and any bootstrap-shadowed utility) where they need a non-important
Tailwind override — prefer `max-md:`/`md:` breakpoint variants with distinct selectors.

## 2026-09-25 - Clients profile responsive + photo centering polish (CSS/Blade only, verified 365/1847/0)

**Scope.** Full-page Clients profile header (`resources/views/clients/_details.blade.php`, the
`@if (! $isPanel)` identity block only). CSS presentation only — Tailwind utility classes on the
identity row/photo column/text column. No route, controller, model, validation, business rule,
JS, or Details Panel behavior change; no inline styles, no `!important`.

**Root cause found.** At ≤375px the page horizontally overflowed to 391px. Bisect probe
(`diag_bisect.cjs`) showed the sole contributor was `.details-full-header`: the identity row
(`flex items-start gap-[16px]`) carried a `shrink-0` photo column whose width was driven by the
`w-full` "Change Photo" button (~211px intrinsic) plus the min-content text column — wider than
the ~199px mobile content box. The 96px photo was left-aligned inside that 211px column, which is
exactly the "photo not centered" complaint.

**Changes** (three class-list edits in `_details.blade.php` lines ~129-145):
1. **Identity row stacks on mobile, side-by-side on desktop/tablet.**
   `flex items-start gap-[16px]` → `flex flex-col items-center gap-[12px] md:flex-row md:items-start md:gap-[16px]`.
2. **Photo column now centers its contents.** `shrink-0` → `flex w-full flex-col items-center md:w-auto md:shrink-0`,
   so the 96px photo centers over the "Change Photo" button (desktop/tablet/mobile) and the column
   no longer widens the row past the viewport.
3. **Name/ID/category text centers under the centered photo on mobile.**
   `min-w-0` → `min-w-0 max-md:text-center` (initially written `text-center md:text-left`; see the
   same-day correction entry above — `text-center` is shadowed by an `!important` bootstrap helper
   in `app.css`, and `max-md:` was adopted to center only below 768px while leaving desktop left).

**Verification.** `npm run build` regenerated `public/build/assets/app-C8uo1nbK.css` (required
since new `md:` utilities were added; old `app-YnumhuC6.css` removed by Vite). Re-rendered the
throwaway harness page and re-ran the Chromium probe (`diag_profile.cjs`) at
1440/1024/768/640/375/320: `document.scrollWidth == viewport` at every width (zero horizontal
overflow); photo `leftCentered` + `photoCenteredInViewport` true at ≤640 (stacked + centered);
at ≥768 the side-by-side row is restored and the photo is centered over the button (centers
within ~1px). Tables still scroll inside their `overflow-x-auto` wrappers (contained, by design).
`php artisan view:cache` OK; `php artisan test` 365 passed / 1847 assertions (0 failures);
`git diff --check` exit 0 (only pre-existing CRLF warnings on `database/schema/mysql-schema.sql`
and the log itself). Throwaway `tests/Feature/DumpClientProfile.php` render harness removed.

**Scope.** Clients module presentation/interaction only — `clients/index`, `clients/_details`,
`clients/_gip`, `partials/client-form-modal`, `FilterChips.js`, `DetailsPanel.js`,
`app.css`. No route, controller, model, GipController, schema, or business-rule change.

**Changes.**
1. **Discard guard on the client Add/Edit modal.** `requestClose()` gates Escape / X / Cancel
   (partial + both `index`/`_details` fallback stores): clean form closes, dirty form asks via
   `window.uiConfirm` "Discard unsaved changes?"; declining restores body scroll-lock and focus.
   Programmatic closes (save success, duplicate review detour) still call `hide()` directly.
2. **Modal focus + escape polish.** Focus moves to the first form field on open
   (`focusFirstModalField`), and the dialog now binds `@keydown.escape` to `requestClose()`.
3. **Focus return from the filter popover.** `FilterChips.js` records the trigger pill
   (`lastTrigger`); `closeMenu()` restores focus to it and clears `aria-expanded` on both the
   toggle and trigger. Exposed `openCategory(key, triggerEl)`; `clients/index` seg buttons pass
   the clicked element and carry `aria-haspopup="dialog"` (aria-expanded stays on the toggle).
4. **GIP save inside the Details Panel.** `_gip.blade.php` submits via `fetch`
   (X-Requested-With / CSRF / `Accept: application/json`) while `DetailsPanel.isOpen()`;
   `resp.ok` (GipController still redirects) → `notify` success + panel reload via
   `DetailsPanel.load('clients', id, { url: '/clients/{id}?panel=1' })`, re-opening the GIP
   accordion if it was open. Full-page flow is native POST + redirect, untouched.
5. **Duplicate-review returns to the untouched form.** `showDuplicateWarning`'s review handler
   `stashForm()` (DOM + values snapshot), and a `details:closed` listener restores it
   (`restoreForm()`) with values, style, and dirty flag intact.
6. **Inline field errors.** In the submit `data.errors` branch, each invalid field gets
   `is-invalid` + a `.field-error` small under its closest wrapper (matching the server-rendered
   `@error` pattern), cleared on input/change; first invalid field still focused after the
   feedback modal closes.
7. **Category badge corrected.** `categoryBadge()` always renders the single
   `.status-badge.is-category` (neutral navy) instead of misleading is-paid/is-pending/is-neutral;
   same class applied in `_details` header + panel meta badge.
8. **Results count.** `#clientsResultCount` (aria-live) shows "X of N clients" when a filter or
   search is active, updated on DataTables `draw.dt`.
9. **Audit Information accordion.** `_details` Audit section is now a default-collapsed
   accordion (`auditToggle`/`auditPanel` via `bindAccordion`).
10. **Change Photo surfaced.** Full-page profile header gains a "Change Photo" button
   (gated `$canEdit`) that opens the already-present photo modal.
11. **Mobile touch target bump.** `< 768px` seg buttons get `min-height: 40px`.

**What was NOT done.** No backend/DB/schema/route/controller (GipController untouched); duplicate
gate buttons/order/wording frozen; no inline styles, no new design system; icon-only action
buttons (32px in the 112px actions column) left as-is (documented finding); the fallback
`_details` edit flow has no inline `.field-error` injection (index-only); `FilterChips.js`/
`DetailsPanel.js` remain ES5 IIFE files copied to `public/js/components` on build.

**Files changed.** `resources/views/clients/{index,_details,_gip}.blade.php`,
`resources/views/partials/client-form-modal.blade.php`,
`resources/js/components/{FilterChips,DetailsPanel}.js` (+ `public/js/components/*` copies via
build), `resources/css/app.css` (`.status-badge.is-category`, `.result-count`, mobile seg-btn).

**Verification.** `php artisan view:cache` — full Blade tree compiles clean; caught one literal
`@error` token in a JS comment (compiled into an unclosed `if`) and removed it. PHPUnit **365
passed / 1847 assertions / 0 failures**. `npm run build` success (58 modules; components copied).
`git diff --check` clean (also removed the pre-existing `_details:100` trailing whitespace).
Preview probe: compiled view `php -l` no syntax errors. Browser/E2E not runnable in this
environment: auth-gated Playwright requires `smoke_superadmin`, absent on local `main_system`
(pre-existing). **Contract note:** `e2e/client-form-modal-phase8.spec.ts` "does NOT close on ESC"
documents the pre-polish parity; item 1/2 intentionally supersede it (ESC now closes or asks to
discard), and the spec should be updated when the smoke user is seeded.

## 2026-09-22 - Phase 27 completion - Clients duplicate gate: button gap + duplicate-list alignment (micro, verified byte-exact)

**Scope.** Clients module only - resources/views/clients/index.blade.php gate region. No route, controller, DB, business rule, unified-notify core, or other file touched.

**End-state (verified).** Two micro-cleanups confirmed present/consistent with the modal's established utilities:
- ctions parent = lex flex-wrap items-center justify-end gap-[8px] - side-by-side decision buttons (Review Existing Client btn-outline, Continue as New Client btn-gold) with the same 8px gutter as the modal footer; wraps on narrow.
- duplicate <ul> = mb-0 list-disc space-y-1 pl-0 - list-disc bullet in content gutter, pl-0 lets li text sit flush on the modal content gutter p-[1.25rem], aligned with the intro paragraph; li has no added padding.
- 0 window.notify refs in the duplicate region (contextual clientFeedbackModal gate preserved); no inline styles, no <style>, no !important.

**Verification.** pint pass; php artisan view:cache clean; phpunit 365 passed / 1847 assertions; npm run build ok; git diff --check scoped clean.
## 2026-09-21 - Phase 27 (Clients UX cleanup): drop redundant "Back to form" dismissal + pair-gate labels/classes

**Scope.** Clients module only — duplicate gate presentation + shared feedback modal footer. No business rule, route, controller, DB, or unified-notify core changed.

**Change 1 — remove redundant "Back to form".** The shared `client-feedback-modal.blade.php` footer had a static "Back to form" button that called the *same* `$store.clientFeedbackModal.hide()` as the X/close control (line 49). It was the **only** "Back to form" action in the entire Clients module (module-wide grep: 0 live buttons — only stale comments now corrected). Removed it; **X/close preserved and is now the sole dismissal**, returning focus to the still-editable form modal underneath (masked at a lower z-index). Removing a button that provably duplicated the close control is behavior-neutral — the modal was already reachable via X, backdrop click, ESC, focus-trap, and close-button handler.

**Change 2 — duplicate gate pair, exact directive.** In `clients/index.blade.php` `showDuplicateWarning` (contextual gate, uses `var fbStore = Alpine.store('clientFeedbackModal')` + `fbStore.show({ title:'Possible duplicate', type:'warning', body: warn, actions: actions })`):
- "View Existing Profile" → **"Review Existing Client"** using the **outlined/neutral** treatment (`btn-outline` — existing class, secondary priority).
- "This is a different person — Continue" → **"Continue as New Client"** retaining the **gold filled** treatment (`btn-gold`).
- Both buttons kept **side-by-side** (desktop) inside `#clientFeedbackActions` (`flex flex-wrap items-center justify-end gap-[8px]`); they wrap together on narrow screens.
- The modal **X in the header is preserved** — it dismisses the warning and returns to the still-open Add Client form with entered values intact.
- The edit form stays open underneath; `duplicate_confirm` hidden input + `form.requestSubmit()` contract preserved; no `alert()`/`confirm()`.

**What was NOT done (guard the gate).** No new modal, floating card, or toast for the duplicate gate; no unified-notify toast for the warning (contextual-keep, Phase 26 restore held); no field/form move or redesign; no change to duplicate-detection business logic; no new button/action classes (reused `btn-outline` + `btn-gold`); no `!important`, no inline `style=""`, no extra `<style>` blocks; no change to unified-notify stack for ordinary info/success/warning/error.

**Files changed.**
- `resources/views/partials/client-feedback-modal.blade.php` — removed redundant "Back to form" footer button; header comment updated to document the sole-close contract.
- `resources/views/clients/index.blade.php` — gate pair labels/classes (`btn-outline`/`btn-gold`, text per directive); stale modal comment corrected.

**Verification.**
- `php artisan view:cache` — whole blade tree compiles clean (Blade compilation covers this gate; no syntax errors).
- `php artisan test` — **365 passed (1847 assertions)**, 0 failures.
- `npm run build` — success (58 modules, assets emitted).
- PINT — clean on edited blade.
- `git diff --check` — no NEW whitespace errors from this change (2 pre-existing unrelated flags noted: `_details:100` trailing whitespace, `routes/web.php:278` EOF blank line — both outside this change's scope, left untouched).
- Browse/authoritative audit: `#clientFeedbackActions` flex container is the only action surface; X/close + backdrop + ESC all return to the form; zero live "Back to form" buttons and zero remaining duplicate-decision toasts module-wide.

## 2026-09-20 - Unified notification system (Phase 23+): ONE shared bottom-right notify stack; clients screens migrate onto window.notify

**Context.** The app previously had no single notification channel — the layout and every screen hand-rolled their own top-anchored toast stack (layout flash toast, `clients/index` + `_details` `showToast`, households/transactions stacks, scanner-centered modal). Each dedicated stack hard-coded its own `fixed ... items-end` container, so what users perceived as "the toast" lived in several divergent DOM locations/formats.

**Change.** Introduced a single, view-model-driven shared notification surface and routed the flash + clients screens through it:
- `resources/views/partials/unified-notify.blade.php` (new, `@once`): Alpine store `unifiedNotify` + one fixed bottom-right stack container (`bottom-[24px] right-[24px] z-[1100]`, `aria-live="polite"`). Imperative `window.notify(options)` contract — `{ type, title, message, actions, persistent }` with `info | success | warning | error`, per-type icon on the shared 24px semantic ring (teal/check, amber/triangle, red/x). **Warning is always persistent** (never auto-dismissed); info/success/error auto-dismiss (4s / 7s). `actions` carry decision buttons on persistent warnings. Repeated calls append to the one stack (no per-screen stacks, no position fights).
- `resources/views/layouts/app.blade.php`: `@include('partials.unified-notify')` added once; the server `login_status` flash rewritten from the dedicated top toast to `window.notify({ type:'success', title:'Welcome', ... })` behind a guarded ready-retry script.
- `resources/views/clients/index.blade.php`: `showToast` (753) delegates to `window.notify` (success) with a legacy DOM-stack fallback when `notify` is absent.
- `resources/views/clients/index.blade.php`: `showDuplicateWarning` (867) — the **duplicate gate** — now presents through the unified **persistent WARNING toast** via `window.notify({ type:'warning', persistent:true, actions:[...] })`, keeping BOTH decisions as toast actions ("View existing profile" navy, "This is a different person — Continue" gold). The business rule (name+birthdate gate) is unchanged; the edit modal stays open underneath with the user's entered values intact; `duplicate_confirm` hidden input + `form.requestSubmit()` contract preserved. The former centered `clientFeedbackModal` was NOT removed — it remains wired as the no-`notify` fallback path so the gate degrades safely on environments where the shared stack hasn't booted (guarded by an early `return` after `window.notify`, so it can never double-fire).
- `resources/views/clients/_details.blade.php`: `showToast` (842) and `showFeedback` (900) delegate to `window.notify` (success / mapped type) with the shared-stack path preferred and the centered `clientFeedbackModal` kept as the fallback for validation/photo/duplicate content (Bootstrap-style alerts inline per contract).

**Scope decision (explicit, per the unified-no-toast-X + form-open contract).** The clients screens now render success + duplicate-warning toasts through the ONE bottom-right stack. The scanner screen deliberately keeps its own centered scanner-message channel (`scannerMessageModal` / Phase 22 scanner contract) — not a 5th toast typechers, a screen-family-rule the brief set a hard guard on. Validation-error feedback on the duplicate/identity warning gate stays routed as above (persistent warning with actions) rather than being forced through the transient info/success channel, so an important decision can never auto-dismiss while the edit form is still open. No browser `alert()`/`confirm()` remains in the clients screens; the only modal-native leftover is the feedback-modal **fallback** path.

**Verification.** `php artisan view:cache` recompiles the FULL blade tree clean (covers edited `clients/index` + `clients/_details` to a single tail + the shared layout + all 9 remaining post-migration screens) — a syntax error anywhere (including the riskiest duplicate-gate region) would have failed the cache build loudly. `git diff --stat` on both edited client views shows shallow, coherent single-pass patches (index ±69, `_details` ±42 changed lines) with no wholesale rewrites. Legacy helper names + their call sites were preserved verbatim so only a thin delegation shim changed; no route/controller/session-token/business-rule touched (all per-screen ACL surface untouched).

**Verification.** `php artisan view:cache` compiles the entire blade tree clean (both edited client views + every other screen). `git diff --stat` on the two migrated client files shows shallow, coherent patches (69 / 42 changed lines) with no wholesale rewrites. Code-style pass (pint) clean.

---

**Symptom.** In the Clients Details Panel (`?panel=1`, opened from the index row click), two regressions: (1) **Delete** deleted the client immediately with no confirmation dialog; (2) **Family Composition** and **Transactions** sections rendered but never displayed their details — expanding them did nothing (full page was unaffected).

**Root cause (single point for both).** `resources/js/components/DetailsPanel.js` `executeScripts(container)` was called with the **detached** `DOMParser` document (`executeScripts(doc)` in `load()`). `replaceChild(fresh, oldScript)` inside a detached document never executes scripts in the live page, so the panel fragment's inline scripts never ran. Pre-migration (commit `3f65408`) the index view did `body.innerHTML = html; executeScripts(body)` with a **live** element, so the scripts executed natively there. Consequences in the panel:
- Delete: the inline IIFE in `_details.blade.php` binding the `uiConfirm` confirmation flow to `[data-delete-client-form]` never ran → the form submitted natively → immediate delete.
- Sections: the inline accordion IIFE binding `#famToggle`/`#famPanel` and `#txToggle`/`#txPanel` never ran → toggles inert, accordions unopenable.

**Change.** `resources/js/components/DetailsPanel.js` (source of truth; `vite.config.js` copies it verbatim to `public/js/components/` on `npm run build`) — `executeScripts(container)` now branches on `container.isConnected`:
- connected → keep the existing in-place `replaceChild` (live containers such as the edit-modal body in `_details.blade.php`);
- detached → `document.body.appendChild(fresh)`, so detached-fragment scripts execute in the live document, global scope, synchronously in document order. The IIFEs are idempotent, so repeated panel loads are safe (scripts accumulate in `body` — cosmetic only).

**Behavior preserved (no changes outside the JS):** Delete stays a `POST` (`clients.destroy` route + `expectsJson()` JSON branch, CSRF via `FormData`); confirmed in the panel via `fetch` with JSON handling, on the full page via native form submit after `uiConfirm`; the `data-delete-client-form` / `data-message` contract and the existing `confirm-modal` mechanism are untouched. Accordions remain collapsed-by-default (intentional `hidden` + `aria-expanded` design) but are now expandable.

**Verification:**
- `npm run build` — OK; rebuilt `public/js/components/DetailsPanel.js` is byte-identical to the source (`git diff --no-index` clean).
- `node --check resources/js/components/DetailsPanel.js` — syntax OK.
- `php artisan view:cache` — OK.
- `php artisan test` — **365 passed / 1,847 assertions** (baseline identical; PHPUnit is server-render only and does not exercise this JS path).
- `git diff --check` — clean for the changed files.
- Note: server-based Playwright flows (`e2e/clients.spec.ts`) cannot run locally — `smoke_superadmin` is retired and restoring it would be a prohibited DB write.

**STATUS: COMPLETE — detached-document `executeScripts` was the sole root cause; both regressions are fixed by restoring live-DOM script execution.**

---

## 2026-09-20 - Clients module Details Panel: static inline styles removed, stylesheet moved to app.css

**Change.** Styling-ownership cleanup of the shared DetailsPanel component and the clients detail view. All static inline `style=""` declarations were removed, and the DetailsPanel family now lives in `resources/css/app.css` with every rule set to its currently-rendered value.

- `resources/views/partials/details-panel.blade.php`: removed the internal `<style>` block (~350 lines) and the 5 shell inline `style=""` attributes (identity-content wrapper `flex:1;min-width:0`; `#detailsSub` 0.7 / 0.8rem / 2px; `#detailsMeta` flex / gap 8px / margin-top 8px; `#detailsActions` flex wrap / gap / padding / border / background; `#detailsBody` flex / overflow / padding / overscroll).
- `resources/css/app.css`: appended a delimited `MARKER-BEGIN/END: details-panel` section holding the component rules. Consolidations: `.details-identity > div:not(.details-avatar)` (flex:1;min-width:0 — now generic for all modules, not just clients), `.details-sub` (0.7 / 0.8rem — the values the inline won, previously hidden 0.55 / 0.75rem), `.details-meta` (gap 8px / margin-top 8px — previously hidden 10px / 12px), `.details-actions` (display / flex-wrap / gap / padding / border-bottom / background). Added `.details-profile-photo` (96×96) for the full-page identity photo. Removed the two <768px padding overrides (`.details-actions` 12px 16px / `.details-body` 18px 16px 36px): they were dead rules defeated by the inline styles at every breakpoint, so activation would have silently changed the current mobile/tablet rendering — the mobile drawer keeps today's exact paddings (actions 14px 24px, body 20px 24px 40px).
- `resources/views/clients/_details.blade.php`: the two `style="width:96px;height:96px"` identity photos (img + "No photo" placeholder) now use the `details-profile-photo` class.
- `e2e/details-panel-scroll-fix.spec.ts`: `extractStyle()` now pulls the app.css section between the markers (the partial no longer carries a `<style>` block) and the harness shell no longer replicates the inline styles.

**Retained inline/`<style>` (functional, not static styling):** the `display:none` on the `data-panel-*` sinks in `_details.blade.php` (cross-module JS innerHTML contract — a global CSS rule would break households/payouts where `data-panel-avatar`/`data-panel-title` render visibly) and the `[x-cloak]{display:none}` blocks (established project-wide Alpine convention, kept consistently in confirm-record / record-view / client-form / client-feedback modals, `_gip`, `_details`).

**Verification:**
- `php artisan view:cache` — OK.
- `npm run build` — OK (`app-Dr1XPKIi.css`, 91.84 kB).
- Playwright `e2e/details-panel-scroll-fix.spec.ts` — 21 passed (7 projects × 3 tests: stacking + scrolling contract holds at mobile 390×844, tablet 900×800, desktop 1440×900).
- `git diff --check` — clean for app.css and details-panel.blade.php; only a pre-existing trailing-whitespace flag at `_details.blade.php:100` (outside this change) remains.

**STATUS: COMPLETE**

---

## 2026-09-20 - Fix: `.back-btn` styles not applied on Client Profile back link

**Symptom.** The new back link (`<a class="back-btn no-underline">` + `&#10094;` chevron, inside the `Client Profile` `<h1>`) rendered as a default Bootstrap-blue underlined link — `.back-btn` styles appeared to have no effect.

**Diagnosis (two root causes, both at the source — not a specificity conflict):**
1. **Stale build artifact (the actual blocker).** The `.back-btn` rule was added to `resources/css/app.css` (17:30:24) AFTER the last `npm run build` (17:05:52). The served bundle `app-DzOCgF4a.css` contained NO `.back-btn` rule at all, so none of its declarations reached the browser; the element fell back to the reboot block `a { color:#0d6efd; text-decoration:underline }` (app.css L369). Plain `npm run build` over an existing `public/build` would not repair this reliably, so `public/build` was deleted first (proven approach from earlier sessions).
2. **Undefined CSS custom properties (latent source defect).** `.back-btn` referenced `var(--color-text-muted)` (base) and `var(--color-primary)` (hover/focus/active) — neither token exists in `@theme`/`:root`. At computed-value time the `color` declarations were invalid → fallback to the base anchor blue; hover had no effect. Fixed in the `.back-btn` rule itself to the canonical 2DMIS tokens: `--color-ink-muted` (#70798B) for the resting state and `--color-navy` (#0038A8, the 2DMIS primary) for :hover/:focus/:active.

**Change.** `resources/css/app.css` — two substitutions in `.back-btn` (L649, L659); regenerated `public/build` (new `app-1_V4S62Y.css`, 83.73 kB). No `!important` used (none needed: `.back-btn` is unlayered author CSS at (0,1,0), beating the (0,0,1) `a` base and the layered Tailwind utilities). Blade markup, header container, and every other component untouched.

**Verification (emitted bundle `app-1_V4S62Y.css`):**
- `.back-btn{color:var(--color-ink-muted);box-shadow:none;background:0 0;border:0;text-decoration:none}` present; `--color-ink-muted:#70798b` and `--color-navy:#0038a8` defined in bundle.
- Interaction states emitted as `.back-btn:hover,:focus,:active{color:var(--color-navy);box-shadow:none;background:0 0;border:0}` — text/icon only; only the text color changes; no background/border/shadow on any state.
- No competing selectors for the element in the bundle: `a.back-btn`, `h1 a`, `.details-full-head a` — 0 matches.
- `php artisan view:cache` OK; `git diff --check` clean (app.css).

**STATUS: COMPLETE — caused by stale bundle (rule absent at runtime) plus two undefined var() tokens in `.back-btn`; rule now canonical and shipped in a fresh build.**

---

## 2026-09-20 - Client Profile: relocate Back button above the page header (placement only)

**Change** (`resources/views/clients/_details.blade.php`, full-page mode only): moved the existing `Back` link (same anchor, same `route('clients.index')`, same `btn-subtle no-underline` styling) OUT of the page-action group (the right-aligned flex row that holds `+ Add Transaction` / `Edit` / `Delete`) and INTO its own left-aligned row directly above the `Client Profile` `<h1>` inside `.details-full-head`, wrapped in `mb-[16px]` for a clear visual gap. No CSS changes, no new/duplicate link, no breadcrumbs.
- Hierarchy now: Back navigation → page header (title + actions) → identity → content.
- The `@if (! $isPanel)` guard means panel mode (`?panel=1`) rendering stays untouched.
- The `mb-[16px]` wrapper is the only spacing added; it is directly necessary to separate the relocated row from the title. No other margins/gaps/typography/card layout touched.

**Unrelated pre-existing working-tree changes (not part of this task; observed, left untouched):** app.css currently has no live `.data-card-body` rule (the M6.8 restore from the earlier phase is no longer present in the working tree); `npm run build` therefore emitted `app-DzOCgF4a.css` (83.47 kB) which is consistent with current source.

**Verification:**
- `php artisan view:cache` — OK.
- `npm run build` — OK (current source → consistent artifact).
- `php artisan test` — **365 passed / 1,847 assertions** (baseline identical).
- `git diff --check` — exit 0 (CRLF warnings only on pre-existing files). `git status`/`git diff` confirm the only new change is the Back link relocation hunk.

**STATUS: COMPLETE — placement-only; behavior/route unchanged.**

---

## 2026-09-20 - Mockup: Client Profile page redesign (back-navigation placement)

**Created** `prototype/mockup-client-profile.html` — self-contained, high-fidelity static mockup of the 2DMIS v2 Client Profile page. Prototype-folder work only; no production files touched.

**Design decisions (per design brief):**
- **Back navigation**: `← Back to Clients List` rendered as a compact secondary text link *above* the page header, top-left, with an 8px optical negative-margin so the arrow pad starts at the text baseline as the other content (muted slate → navy on hover, subtle hover chip). Not a breadcrumb; not adjacent to the actions.
- **Page header**: `Client Profile` page title (Outfit 700, thin gold 44px accent bar), then identity row — 60px circular initials avatar **JG**, name **Dela Cruz, Juan R**, `Client ID: 1008`, neutral pill badge **Adult (30–59)** with gold status dot.
- **Actions (top-right, grouped, page-action hierarchy)**: `+ Add Transaction` solid `#0040A8`/white (matches brief exactly), `Edit` neutral outline (white surface, `#D0D5DB` border, no gold fill), `Delete` white surface + red `#CE1126` border/text (quiet destructive). All height 40px, radius 8px, restrained shadows. Gold kept solely as an accent (title bar + status dot + section rule).
- **Content stack** below a 1px header rule: white content cards (`#E2E5EA` border, `--shadow-sm`, radius 14px, 26–28px padding), gold 4px section accent.
  - Section 1 **Personal Information** — enterprise label/value grid (`<dl>`), Title Case labels vs muted, values ink, 3-col responsive (2-col ≤780px, 1-col ≤560px).
  - Section 2 **QR Identity Card** — visually distinct but consistent: tinted surface `#F7F9FC`, 4px navy left bar, white QR tile + qrserver image (same service production uses), title **Client QR Code**, helper **For easy scan access**, token appendix.
- Top bar + annotation strip included purely as neutral context framing (unchanged chrome).

**Verification (Playwright, chromium @1440px + 480px; model cannot read screenshots so DOM/computed-style assertions used):**
- Back link above header with ≥14px separation; text starts at the content edge; separated from the action cluster.
- Actions pinned to header-row right on the same line as the title; exactly 3 buttons; Add Transaction bg = `rgb(0,64,168)`; Edit = white bg + `rgb(208,213,219)` border; Delete = `rgb(206,17,38)` text on white.
- Avatar circular, initials JG, name/ID/badge present; Personal Information card precedes QR card; QR helper text visible; qrserver image loaded; labels Title Case; no breadcrumb; content cards white with 1px border + shadow; no horizontal overflow at 480px.
- Screenshots written to temp dir for stakeholder viewing (`mock-1440.png`, `mock-480.png`).

**STATUS: COMPLETE — prototype artifact only; no backend/routes/views/CSS/DB changed.**

---

## 2026-09-20 - Diagnosis + fix: `.data-card-body` padding source (stale build artifact) and restore

**Context.** `.data-card-body` (37 sites across 26 list/form screens) showed padding even though the rule carrying it had been commented out in `resources/css/app.css` (both the Batch C twin `@apply p-[1.25rem]` and the M6.8 end-of-file port `padding: var(--ui-space-3)` were commented). Diagnosis (no `!important`/inline/utility/override tacked on):

- Markup is clean: every `.data-card-body` element carries only layout utilities (`flex flex-col gap-[14px]`, `grid ...`, `max-w-[420px]`) — no `p-*`/`px-*`/`py-*`/`pt-*`/`pr-*`/`pb-*`/`pl-*`.
- Source CSS has no live `.data-card-body` padding rule; the input graph is just `resources/css/app.css` (no CSS imports in `resources/js`, Bootstrap CDN removed Phase 27, `public/css/ui.css` deleted + unlinked).
- The served `@vite` bundle `public/build/assets/app-BcYgrthh.css` (gitignored, untracked) was a STALE build still carrying a live `.data-card-body{padding:var(--ui-space-5)}` (20px) — originally compiled from the M6.8 verbatim ui.css §5 port. `npm run build` had kept reproducing the same stale artifact, so the comment-out never reached the browser. A force-clean rebuild (`Remove-Item public/build` + `npm run build`) dropped the rule entirely (`.data-card-body` → zero padding), proving the stale bundle was the sole source of the visible padding.

**Change (one production file + generated build):**
- `resources/css/app.css` (M6.8 section) — restored the `.data-card-body` block as the canonical owner with the CANONICAL value: `.data-card-body { padding: var(--ui-space-5); }` (20px). Value chosen to preserve the existing rendered behavior and match the verbatim ui.css §5 port / M6.8 documented value / Batch C twin (`p-[1.25rem]`) / docs; the commented `--ui-space-3` text contradicted the block's own "VERBATIM" provenance and never shipped. No `!important`, no new overrides, no markup changes. (Note: the M6.8 `.data-card-header` source in the same section still reads `var(--ui-space-4) var(--ui-space-3)` — a pre-existing uncommitted deviation from the documented 16/20 port — observed but OUT OF SCOPE, left untouched.)
- `public/build` — regenerated fresh so the manifest bytes match current source (`assets/app-CWLIM6xv.css` 83.52 kB, JS `app-DqsLDVL_.js` unchanged).

**Verification:**
- Emitted bundle now contains exactly `.data-card-body{padding:var(--ui-space-5)}` (verified from `app-CWLIM6xv.css`; source and bundle in sync, no duplicates).
- `npm run build` OK; `php artisan view:cache` OK; `git diff --check` clean (app.css).
- `php artisan test` — **365 passed (1,847 assertions)** — baseline identical.

**STATUS: COMPLETE — root cause was the stale compiled bundle; source restored + fresh build; no unrelated changes.**

---

## 2026-09-20 - Phase: Page-level breadcrumb navigation removal

**Context.** The page-level breadcrumb navigation was a single shared partial, `resources/views/partials/breadcrumbs.blade.php`, rendering one `<nav aria-label="Breadcrumb">` block between the page header and the card content. It was included via `@include('partials.breadcrumbs', [...])` on 39 migrated screens across all modules (dashboard, admin, clients, transactions, scanners, scholars, payouts, households, family members, duplicates, sessions, logs, reports). The topbar breadcrumb inside `partials/navbar.blade.php` is a separate presentation-only component (no `<nav aria-label="Breadcrumb">`) and was intentionally left untouched per task scope.

**Change (partial deleted, 39 views edited, one CSS allowlist entry removed):**
- `resources/views/partials/breadcrumbs.blade.php` — deleted the partial (the only markup containing `aria-label="Breadcrumb"`).
- 39 Blade views under `resources/views/**` — removed the complete `@include('partials.breadcrumbs', [...])` invocation from each page. Only the breadcrumb blocks were removed; page headers, `partials.page-header` includes, cards, layout, scripts, routes and PHP logic are untouched. 31 leftover indentation-only blank lines left at the removal sites were cleaned (strict breadcrumb-removal whitespace), and dashboard's now-obsolete "single crumb by design" comment was removed.
- `resources/css/app.css` — removed the now-dead `@source "../views/partials/breadcrumbs.blade.php";` allowlist line (Batch D section) and corrected the two Section B/D header comments that enumerated the breadcrumbs partial. No token, value, or other source change.
- Line endings of the edited Blade files verified identical to HEAD (LF); no file was re-encoded.

**Verification:**
- Source audit: `aria-label="Breadcrumb"` → 0 occurrences repo-wide; `@include('partials.breadcrumbs'` → 0 in `resources/`; no breadcrumb-specific classes (`ui-breadcrumb*`) remain. The only remaining `breadcrumb` mentions are the out-of-scope topbar/navbar component.
- `php artisan view:cache` — OK ("Blade templates cached successfully").
- `npm run build` — OK (`public/build/manifest.json` + `assets/app-BcYgrthh.css` 83.52 kB + `assets/app-DqsLDVL_.js` 105.70 kB; hashes identical to the pre-change build, i.e. the emitted CSS/JS is unchanged).
- `php artisan test` — **365 passed (1,847 assertions)** — baseline identical.
- `git diff --check` — clean (exit 0; only informational CRLF-normalization warnings from pre-existing working-tree files). No unrelated pre-existing working-tree changes were modified.

**STATUS: COMPLETE — STOP after verification; no additional UI cleanup, no other navigation elements removed.**

---

## 2026-09-16 - Phase 25: Dead design-token cleanup (36 approved `--ui-*` tokens removed from `:root`)

**Context.** Phase 24A ("Dead-Token Project") fully reconciled all `--ui-*` tokens in `resources/css/app.css`: 92 unique declared tokens (67 `:root`, 22 accordion, 2 row/gutter, 1 `--ui-accent` mid-line), 56 with live consumers, 131 individual consumer refs, 6 dead `--ui-color-*` alias refs (former L319–324), plus 36 zero-consumer tokens. Phase 25 removed exactly those 36 approved dead tokens from the `:root` block.

**Change (one edit, one production file):**
- `resources/css/app.css` — replaced the `:root` design-token block (former L282–372): removed the 36 approved declarations, kept the 31 live ones with byte-identical values/ordering/formatting, and updated the leading comment to describe the retained live subset. Groups removed: A — 6 `--ui-color-*` aliases (`structure/action/success/danger/warning/info`); B — 16 with `@theme` equivalents (`gold-dim`, `teal-light`, `red-light`, `bg`, `bg-alt`, `text-inverse`, `font-body`, `font-mono`, `text-base`, `text-lg`, `text-page-title`, `radius-xl`, `shadow-xs`, `shadow-lg`, `shadow-xl`, `shadow-glow`); C — 12 without equivalents (`space-1`, `space-2`, `space-6`, `space-7`, `page-gutter`, `duration-fast`, `duration-slow`, `sidebar-w`, `header-h`, `panel-w`, `touch-target`, `touch-target-sm`); D — 2 alias-only (`amber`, `blue-accent`; safe because their sole consumers `--ui-color-warning`/`--ui-color-info` were removed in the same phase).

**Reconciliation note.** The task's example LIVE-token list included `--ui-teal-light` with an explicit reconcile instruction; audit proved it is DEAD (zero `var()` refs), so it was correctly removed (list B). No consumer rewrites, no new tokens, no aliases, no value/format changes; `.accordion` (22), `.row`/`.g-*` (2), and `.metric-card.accent-*` (`--ui-accent`) blocks untouched.

**Verification:**
- `npm run build` OK → `public/build/assets/app-gzcFk36Z.css` (84.08 kB / gzip 15.01 kB) + `app-DqsLDVL_.js` (105.70 kB); `manifest.json` updated.
- Post-edit counts: `rg --count "^\s*--ui-"` = 61 → 55 real declarations (31 `:root` + 22 accordion + 2 grid) + 6 comment references (L1662/2706/2723/2724/2731/2769, historical `/* */` text referencing token names, left unchanged) + `--ui-accent` mid-line = 56 unique tokens (92 − 36). `rg --count "var\(--ui-"` = 123 lines (pre-edit 129 lines / 137 individual refs; 6 dead-alias lines removed → 131 individual refs on 123 lines). Zero `var()` refs or declarations of any removed token anywhere in `resources/` (only historical mentions in `docs/*` and comments). All 31 retained `:root` tokens verified present with original values.
- `php artisan test`: **365 passed (1,847 assertions)** — baseline identical.
- Playwright: smoke suite fails only at `signIn()` — `smoke_superadmin` absent from local `main_system` since C3-C hygiene (documented pre-existing env limitation, unchanged; no account created). No CSS-related or assertion failures; token removal is a pure dead-code deletion with zero consumers.

**STATUS: COMPLETE — ACCEPT / STOP. Exactly 36 approved dead tokens removed; no consumer changes, no visual impact.

---

## 2026-09-16 - Phase 26: Live design-token consumer migration (legacy `--ui-*` → canonical @theme)

**Context.** Phase 25 left 131 live `var(--ui-*)` consumer references in `resources/css/app.css` (the only file containing them). Phase 26 migrated every consumer with a Phase 24A-verified authoritative mapping to the canonical Tailwind `@theme` vocabulary via direct token substitution only — no design changes, no value changes, no consumer rewrites.

**Change (one production file):**
- `resources/css/app.css` — 79 consumer references across 26 mapped tokens substituted in place:
  - **A (exact/direct, 11 tokens / 52 refs):** `--ui-navy`→`--color-navy` (21+1 accent-fallback), `--ui-navy-light`→`--color-navy-light` (5), `--ui-navy-hover`→`--color-navy-hover` (3), `--ui-gold`→`--color-gold` (3), `--ui-gold-light`→`--color-gold-light` (1), `--ui-red`→`--color-red` (14), `--ui-focus-ring`→`--shadow-focus` (4), `--ui-font-heading`→`--font-heading` (1), `--ui-text-metric`→`--text-metric` (1), `--ui-tracking-caps`→`--tracking-caps` (1), `--ui-radius-pill`→`--radius-pill` (1).
  - **B (verified semantic, 15 tokens / 27 refs):** `--ui-text-primary`→`--color-ink` (2), `--ui-text-secondary`→`--color-ink-secondary` (1), `--ui-text-muted`→`--color-ink-muted` (1), `--ui-text-xs`→`--text-micro` (2), `--ui-text-sm`→`--text-dense` (1), `--ui-card`→`--color-surface` (3), `--ui-card-hover`→`--color-surface-hover` (1), `--ui-border`→`--color-line` (2), `--ui-border-light`→`--color-line-light` (2), `--ui-radius-lg`→`--radius-card` (2), `--ui-radius`→`--radius-btn` (1), `--ui-radius-sm`→`--radius-control` (1), `--ui-shadow-sm`→`--shadow-card` (2), `--ui-shadow-md`→`--shadow-lift` (1), `--ui-ease`→`--ease-standard` (2).
  - Affected families: `.form-control`/`.form-select`/`.form-check-input` focus/checked states, `.btn-primary`/`.btn-outline-primary`/`.btn-danger`/`.btn-outline-danger` + disabled states, `.page-link` + focus/hover/active, `.data-card`, `.data-card-header/-body/-footer`, `.metric-card` (+ accent classes; `--ui-accent` per-instance property retained, `.metric-card.accent-teal` keeps `--ui-teal` — no mapping exists for teal in Phase 24A), `.status-badge`, `.ui-skip-link`, `.ui-notice`, `.ui-micro-label`, `.metric-value`.
  - Comment corrections only where the consumed-token enumeration became factually incorrect (M6.5/M6.6/M6.7/M6.8/M6.10/M6.11 blocks + the `:root` leading comment).
- No `--ui-*` declarations were removed. No new tokens, no new aliases, no reordering, no reformatting, no selector changes.

**Retained legacy consumers (52, unchanged):** accordion plumbing `--ui-accordion-*` (34 refs), localized spacing `--ui-space-3/4/5` (8), `--ui-gutter-x/y` grid plumbing (6), `--ui-duration` (2), `--ui-accent` fallback (1), `--ui-teal` in `.metric-card.accent-teal` (1). `--ui-teal` has no authoritative mapping and correctly remains local.

**Newly-unused `--ui-*` declarations (26) — reported for the separate removal gate, NOT removed in this phase:** `--ui-navy`, `--ui-navy-light`, `--ui-navy-hover`, `--ui-gold`, `--ui-gold-light`, `--ui-red`, `--ui-card`, `--ui-card-hover`, `--ui-border`, `--ui-border-light`, `--ui-text-primary`, `--ui-text-secondary`, `--ui-text-muted`, `--ui-font-heading`, `--ui-text-xs`, `--ui-text-sm`, `--ui-text-metric`, `--ui-tracking-caps`, `--ui-radius-sm`, `--ui-radius`, `--ui-radius-lg`, `--ui-radius-pill`, `--ui-shadow-sm`, `--ui-shadow-md`, `--ui-ease`, `--ui-focus-ring`. Still-consumed declarations (25): 22 accordion + gutter-x/y + space-3/4/5 + duration + accent + teal.

**Verification:**
- Source re-scan matched the Phase 25 baseline exactly (131 refs; 130 name-simple + 1 `--ui-accent,` fallback on the `.metric-card::before` line).
- Post-migration `rg "var\(--ui-"` line count dropped 123 → 47 lines; individual named refs 130 → 51; plus the 1 accent fallback = 52 (131 − 79 = 52).
- Zero `var(--ui-*)` consumers of any mapped token remain; the only remaining refs are the 8 retained names above (accordion/space/gutter/duration/accent/teal).
- All 26 canonical tokens present (`@theme static` block, one declaration each); no `ui-color-*` dead aliases; no new `@theme`/`--space-*` tokens introduced.
- Compiled parity: rebuilt via `npm run build` (vite v6.4.3, 58 modules) → `public/build/assets/app-Bq8KkSs7.css` (84.23 kB). All 26 canonical values verified byte-identical to the migrated `--ui-*` twins; the only two textual differences are pre-existing notation (`rgb(15 27 45 / 0.06)` vs `rgba(15, 27, 45, 0.06)`), which compute identically. Compiled output contains the canonical tokens and only retained legacy refs; no `--ui-*` consumer of a mapped token.
- `php artisan test`: **365 passed (1,847 assertions)** — baseline identical.
- Playwright `e2e/smoke.spec.ts`: 4/4 fail only at `signIn()` (login stays at `/login`; `smoke_superadmin` absent from local `main_system` — the documented pre-existing environment limitation from Phase 25, unchanged in character). No CSS/UI assertion failures.
- `git diff --check`: clean for app.css. Only `resources/css/app.css` (+ IMPLEMENTATION_LOG) changed in this phase; the broader working-tree diffs are prior-phase uncommitted work.

**STATUS: COMPLETE — STOP after verification. Removal gate NOT entered (newly-unused declarations left in place pending a separate phase).**

---

## 2026-09-16 - Phase 28: Dead design-token removal gate executed (26 approved `--ui-*` declarations removed)

**Context.** Phase 27 audited the 26 value-duplicated `--ui-*` declarations (each byte-equivalent to a canonical `@theme` token) and cleared all 26 as SAFE TO REMOVE — zero direct/indirect/fallback/build/external consumers, canonical replacement present and value-equivalent, no cascade or scope dependency. This phase implements that removal.

**Precondition (reconciliation).** Independent enumeration of `resources/css/app.css` before editing: **56 unique `--ui-*` declaration names** = exactly the 26 audit targets + 30 retained (22 accordion + `--ui-gutter-x/y` + `--ui-accent` + `--ui-space-3/4/5` + `--ui-duration` + `--ui-teal`). All 26 targets present with a single declaration each; zero `var(--ui-TARGET)` consumers verified before any edit. (Note: the Phase 27 headline "55" conflated declaration lines vs unique names; the 30 retained-token breakdown was correct.)

**Change (one production file):**
- `resources/css/app.css` — removed exactly the 26 approved declarations from the `:root` block (colors `--ui-navy/-light/-hover`, `--ui-gold/-light`, `--ui-red`; surfaces `--ui-card/-hover`; borders `--ui-border/-light`; text `--ui-text-primary/-secondary/-muted`, `--ui-font-heading`, `--ui-text-xs/-sm/-metric`, `--ui-tracking-caps`; radii `--ui-radius-sm`, `--ui-radius`, `--ui-radius-lg`, `--ui-radius-pill`; shadows `--ui-shadow-sm/-md`; `--ui-ease`, `--ui-focus-ring`). Retained in `:root`: `--ui-teal`, `--ui-space-3/4/5`, `--ui-duration`. Corrected two now-stale comments: the M6.3.2 `:root` block leading comment (now states 5 retained declarations) and the Forms-family focus-identity comment (`--ui-navy-light`/`--ui-focus-ring` relocated → canonical `--color-navy-light`/`--shadow-focus`). Historical provenance comments referencing ui.css origins (lines ~173–276, ~407, ~1627, ~1867) were intentionally left as documented history.

**No other files modified in source; removed tokens are absent from compiled output.**
**Generated build:** `npm run build` (vite v6.4.3, 58 modules) → `public/build/assets/app-BcYgrthh.css` (83.52 kB, down from 84.23 kB), `manifest.json` updated, `app-DqsLDVL_.js` unchanged (105.70 kB).

**Verification:**
- Source: 0 target declarations remain; 0 `var(--ui-TARGET)` consumers project-wide (all non-vendor/non-doc files).
- Remaining legacy inventory (source): **30 unique `--ui-*` declaration names** = accordion (22) + `--ui-gutter-x/y` + `--ui-accent` + `--ui-space-3/4/5` + `--ui-duration` + `--ui-teal`. `var(--ui-*)` consumer count: **52** (accordion 34, gutter-x 4, gutter-y 2, `--ui-accent` 1, space-3 3, space-4 1, space-5 4, duration 2, teal 1) — identical to the Phase 26 baseline.
- Compiled CSS: zero target declarations/consumers; retained tokens present (`--ui-accordion-bg`, `--ui-gutter-x`, `--ui-accent` x3, `--ui-space-3`, `--ui-duration`, `--ui-teal`); all canonical `@theme` replacements present with unchanged values (lowercased by Tailwind, e.g. `#0038a8`).
- `php artisan test`: **365 passed (1,847 assertions)**, Duration ~76s.
- Playwright `e2e/smoke.spec.ts`: 4/4 fail only at `signIn()` — login redirects to `/login` because `smoke_superadmin` is absent from local `main_system`. **Known pre-existing environment limitation; unchanged in character; no new failures.**
- `git diff --check`: clean (exit 0). Working-tree diffs beyond this phase are prior uncommitted Phase 22–27 changes (untouched).

**STATUS: COMPLETE — STOP.**

---

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
| P6 — Scholars / GIP | | Scholar registry v1-parity (Phase 2 cleanup 2026-08-07) + relink (2026-08-12) + scholarship reports (2026-08-12) + GIP details (2026-08-13) + QR viewer (2026-08-13) + grantee updates (2026-08-13) + client picker for standalone create form (2026-08-13) done — P6 complete; SCHOLAR_ANALYSIS §6 step 9 implemented |
| P7 — Administration | | **Done** 2026-08-15 (user creation, page/program permission management, multi-device exemptions, audit viewer + leaderboard; 26 tests green — see entry below) |
| P8 — Hardening + cutover | | Not started |
| P12 (approved contract) — Action authorization + municipality scope | 2 additive tables, 5 pilot pages, admin screens | **Done** 2026-08-16 (37 new tests; full suite 195 tests / 887 assertions green — see entry below) |
| Phase 2A — Dashboard | KPI cards, program distribution, activity feed | **Done** 2026-08-27 (DashboardController KPI queries + dashboard view restructure; 13 new tests; suite 225 tests / 1097 assertions — see entry below) |
| Phase 2B — Global Search | Server-side autocomplete dropdown in topbar | **Done** 2026-08-27 (`ClientController@globalSearch` endpoint + dropdown Blade partial; 14 new tests; suite 239 tests / 1137 assertions — see entry below) |
| Phase 2B Remediation v2 — App Shell | Topbar prototype alignment | **Done** 2026-08-27 (DOM restructure: navbar moved inside `<main>`, sidebar `lg:fixed` top-to-bottom 260px, z-50/px-7 topbar, page padding 28px; suite 240 tests / 1137 assertions — see entry below) |
| Phase 2B Remediation v3 — App Shell Polish | Spacing, overflow, geometry | **Done** 2026-08-27 (sidebar overflow fix, Bootstrap offcanvas override, body bg #F0F2F5, sidebar padding matched to prototype, responsive page padding; suite 240 tests / 1137 assertions — see entry below) |
| Global Typography | Prototype type baseline (pre-Phase 2C) | **Done** 2026-08-27 (Inter body 14px/1.6/smoothing/overflow-x, Outfit headings 600/1.3/-0.01em, `--font-*` and `--ui-font-*` tokens flipped; suite 240 tests / 1137 assertions — see entry below) |
| IA consolidation (pre-P8) | Grouped Access Control sidebar, Scanner Engine/Payouts hubs, scholars Reports/Logs gating, single hub links, AICS dead-link removal | **Done** 2026-08-30 (suite 286 tests / 1336 assertions + Chromium smoke 4/4 — see entry below; P8 cutover NOT executed) |
| UX-1..UX-7 (presentation-only) | Panel-first interaction model: broken-panel repairs, C2 column flattens, Payouts tab workspace, Scanner unified selector, lightweight edit-in-panel, C1 confirm/prompt normalization | **Done** 2026-08-31 (suite 286 tests / 1336 assertions + Chromium smoke 4/4 — see entry below; presentation/interaction only, no schema/ACL/route changes) |
| Clients modal-first workspace (clients-only) | Modal-first Add/Edit, view-only DetailsPanel, uiConfirm delete, 6-col table, multiselect filters, compact pager | **Done** 2026-08-31 **295 tests / 1380 assertions** 0 failures, Pint clean, build + view:cache clean (full verification entry below; presentation only — Status column **blocked**, `tbl_clients` has no `status` column; the 3 pre-existing `ClientService.php` Pint flags now resolved) |
| Clients browser-verified defect fixes (clients-only) | Real fixes: filter popover open/close (excludeClose + openCategory), single search incl. Precinct No. (dual-form search read), single pagination (dom fix); BUGs 4,6,7,8,9,10 verified already-working | **Done** 2026-09-01 **297 tests / 1397 assertions** 0 failures, Pint clean, build + view:cache clean, e2e clients chromium 4/4 (full verification entry below; no schema/permission/route/business-rule change) |
| Clients details-panel UI/UX restructure (clients-only) | Info-hierarchy rework of the panel body: fixed-shell identity/actions, seven scannable sections (Personal/Contact/Additional/Household/Family/Tx/Audit), accessible collapsible Family & Transactions, avatar fit, audit from existing tbl_audit_logs | **Done** 2026-09-02 **297 tests / 1397 assertions** 0 failures, build + view:cache clean (full verification entry below; presentation only — no schema/route/controller/model/permission/business-rule change) |
| Clients UX refinement (clients-only) | Header category line, per-filter clear controls + Clear All (already present), barangay filter visibility fix, photo editing in Edit modal (1MB + GD optimization), family/transactions verification | **Done** 2026-09-02 **297 tests / 1397 assertions** 0 failures, Pint clean, build + view:cache clean (full verification entry below; **one authorized backend change** — photo 1MB limit + GD resizing/optimization; no schema/ACL/route/business-rule change, no production writes; Task 3 category dropdown **not implemented** — documented conflict) |
| Phase 20 — Transactions + Households page-level success toasts → Alpine.js | Migrated the two page-level success toasts (`session('success')` in `transactions/index` + `households/index`) off Bootstrap Toast JS (`bootstrap.Toast` via the shared layout init loop, `.toast`, `data-bs-autohide="false"`, `data-bs-dismiss="toast"`) to Tailwind + Alpine (`x-data="{ open: true }"` + `x-show="open"`, `@click="open = false"` close). Flash contract unchanged; layout loop kept verbatim for the clients Phase 9 stack | **Done** 2026-09-06 **301 tests / 1419 assertions** 0 failures, Pint clean, build + view:cache clean, Phase 20 E2E 5/5 + Phase 19 regression 4/4 chromium (full verification entry below; frontend-only, no schema/ACL/route/business-rule change; **application finding**: `transactions.index` success toast is currently unreachable via any UI flow — `TransactionController::store`/`update` redirect to `transactions.show` — E2E seeds the flash into the live file session instead; DB restored to 1604 audit rows) |
| Phase 21 — Bootstrap JS inventory + navbar hamburger scope fix | Repo-wide Bootstrap JavaScript surface inventory (classified A–F; only THREE live surfaces remain: the intentional `bootstrap.Toast.getOrCreateInstance` init loop at `layouts/app.blade.php:139` serving the clients Phase 9 stack, the dangling `data-bs-toggle="modal"` trigger at `admin/users/show.blade.php:134`, and the navbar hamburger). Migrated the hamburger (`partials/navbar.blade.php`) from **inert** Alpine (its `@click="$store.sidebar.toggle()"` + `:aria-*` were OUTSIDE any `x-data` scope — pre-existing scope defect, verified live pre-fix) to a **working** Alpine toggle via the smallest correct scope (bare `x-data` on the button). Bootstrap CDN/CSS/JS not removed (removal-gate phase); no Bootstrap Collapse involvement (drawer is Tailwind transform-driven) | **Done** 2026-09-06 **301 tests / 1419 assertions** 0 failures, Pint clean, build + view:cache clean, Phase 21 E2E 5/5 chromium + 5/5 Mobile Chrome, smoke regression 4/4 chromium (full verification entry below; frontend-only, no schema/ACL/route/business-rule change; **inventory finding**: the task's documented path `resources/views/components/navbar.blade.php` does not exist — the navbar lives at `resources/views/partials/navbar.blade.php`; DB restored to 1604 audit rows) |
| Phase 22 — Bootstrap Toast init loop removal (clients Toast verification) | Investigation-first: proved the layout init loop `bootstrap.Toast.getOrCreateInstance(el, { autohide: false }).show()` in `layouts/app.blade.php` is **redundant** — the **only** `.toast`-class consumer it could match at parse-time is the clients flash toast (`clients/index.blade.php:284`), which `wireFlashToast` (clients/index:793, invoked at :799 via `document.querySelectorAll('.toast').forEach(wireFlashToast)`) already reveals and dismisses independently; dynamic `showToast` (index:760 / _details:820) elements are created at runtime (one-loop already ran), and the Phase 19 layout `#flashToast` + Phase 20 page toasts lost the `.toast` class so no longer match. Repo-wide greps (incl. `resources/js`) found **zero** other `bootstrap.Toast` consumers, zero toast-JS events, and no E2E/feature test depending on the loop. Removed the obsolete loop + stale comment (smallest change), rewrote the comment block to document the dependency removal while keeping the Bootstrap bundle for non-toast CDN consumers | **Done** 2026-09-06 **301 tests / 1419 assertions** 0 failures, Pint clean, build + view:cache clean, compiled-view scan confirms no `bootstrap.Toast` remains (full verification entry below; frontend-only, no schema/ACL/route/business-rule change; **E2E note**: no live-toast browser run — per Phase 22 rules no ephemeral auth account was created; toast behavior is covered by the existing Bootstrap-JS-free client-feedback + flash-toast + page-toast specs) |
| Phase 23 — Bootstrap removal-gate audit | Audit-only (no code changes). Fresh repo-wide inventory + trace + classify of every remaining Bootstrap dependency. **JS bundle: REDUNDANT** — zero live `bootstrap.*()` API calls anywhere (views, `resources/js`, built `app.js`); the only `data-bs-*` attribute in the app (`admin/users/show.blade.php:134`) is provably **DEAD**: its target `#passwordModal` exists in no document (the Phase 12 modal's id is `passwordForm`), Bootstrap 5's delegated click handler finds null + would throw, and it sits in a `display:none` actions block on the full page. `dataTables.bootstrap5.min.js` (1.13.6) imports only jQuery + DataTables — applies Bootstrap CSS class names, never touches the `bootstrap` global. **CSS CDN: REQUIRED** — Bootstrap 5.3.2 CSS is consumed for rendering by (1) the DataTables bootstrap5 skin (11 screens: `dataTables.bootstrap5.min.css` + per-screen token overlays that recolor, not replace), (2) `.form-control`/`.form-select`/`.input-group`/`.form-check-input` across many forms, (3) `.btn-close` on the Phase 19/20/14 toasts/alerts, (4) `.dropdown-toggle`/`.dropdown-menu` on the clients export dropdown, (5) `.alert`/`.table`/`.pagination` chrome, (6) `ui.css` 49 `--bs-*` variable references (`.btn`, `.btn-gold`, `.btn-primary`, `.btn-close`, focus rings all rely on Bootstrap's variable-consuming base classes), and (7) 7 standalone public pages. No `data-toggle`/`data-target#/`data-dismiss` legacy attributes remain. Bootstrap CSS CDN on 8 files + JS bundle on 6 files, all 5.3.2, no SRI. **Removal-gate: `NO — DEPENDENCY REMAINS` (Bootstrap CSS)**. Bootstrap JS bundle classified REMOVAL-SAFE but deferred to a controlled phase | **Done** 2026-09-06 (audit only — no code changed) **301 tests / 1419 assertions** 0 failures, Pint clean, build + view:cache clean (full verification entry below; no schema/ACL/route/controller/DB/business change; out-of-scope items left intact: dangling trigger documented not removed, Bootstrap CDN not removed, DataTables skin not migrated) |
| Phase 24 — Bootstrap JS bundle removal (CDN `<script>` cleanup) | Removed the redundant Bootstrap 5.3.2 **JavaScript** CDN bundle (`cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js`) from the six views that loaded it: `layouts/app`, `auth/login`, `qr/viewer`, `students/photo-upload`, `unpaid_verifications/self-service`, `grantee_update/self-service`. From Phase 23's removal-gate verdict, the bundle had **zero live consumers** (no `bootstrap.*()` calls, no live `data-bs-*` except the documented DEAD trigger, DataTables skin needs CSS-only). **Bootstrap CSS left completely intact** (8 views still load `bootstrap.min.css`); DataTables skin + jQuery + Alpine untouched; `resources/js/bootstrap.js` (axios shim) untouched; dangling `admin/users/show.blade.php:134` trigger untouched. Updated the now-stale `unpaid_verifications/self-service` Batch G comment that claimed "the bootstrap.bundle CDN script is kept for coexistence". Post-change: zero bootstrap JS CDN references in views or compiled output; Bootstrap CSS at all 8 original locations; `window.bootstrap` now `undefined` on `/login` and `/qr-viewer` with **zero console errors** in live Chromium | **Done** 2026-09-06 **301 tests / 1419 assertions** 0 failures, Pint clean, build (vite v6.4.3) clean, view:cache clean + compiled-view scan shows no bootstrap JS references, live browser console check clean (full verification entry below; frontend-only, no schema/ACL/route/controller/DB/business change; E2E suites not run — they require an admin login account that does not exist and DB must remain untouched at 1604 audit rows; documented limitation) |
| Phase 25 — DataTables skin CSS ownership (Bootstrap-CSS removal phase 1) | Replaced the 11 per-screen `dataTables.bootstrap5.min.css` CDN skin links with the project-owned, self-contained `public/css/datatables.css` (no `--bs-*` consumption; owns the DataTables-generated `table(.sm)`/`form-control(-sm)`/`form-select(-sm)`/pagination chrome within `.dataTables_wrapper`). `dataTables.bootstrap5.min.js` class-emit retained; Bootstrap CSS (8 links) still required for `.btn`/form bases/standalone pages | **Done** 2026-09-06 **301 tests / 1419 assertions** 0 failures, Pint clean, build + view:cache clean, served 200 (full verification entry below; frontend-only, no schema/ACL/route/controller/model/business change; **removal-gate (Bootstrap CSS): NOT READY** — dependencies documented; E2E not run — auth-gated screens, no account, DB untouched) |
| Phase 26 — Core Bootstrap CSS component ownership (Bootstrap-CSS removal phase 2) | Rewrote `ui.css` §4 into a project-owned ownership layer for every shared Bootstrap class the app renders, byte-faithful to Bootstrap 5.3.2: `.btn` base + `.btn-sm` + `.btn-group`, variants `.btn-primary`/`.btn-outline-primary`/`.btn-danger`/`.btn-outline-danger` (+ `.btn.btn-gold:focus-visible`), form controls (`.form-control/-sm`, `.form-select/-sm`, `.form-check(-input)`), `.input-group(.text)`, `.btn-close(-white)`, `.alert` + 4 variants + dismissible, plain `.table`/`.table-sm`/`.align-middle`/`.table-responsive`, dropdown CSS (`.dropdown-toggle`/`.dropdown-menu`/`.dropdown-item`). ui.css `--bs-*` references **49 → 0**; `.btn-gold` `--bs-btn-*` bridge + `.btn:hover` gradient bridge removed; Bootstrap CSS (8 links) still loaded and still required for `.modal*`/`.accordion`/`.list-group`/`.form-label`/utility classes + 7 standalone public pages | **Done** 2026-09-07 **301 tests / 1419 assertions** 0 failures, Pint clean, build + view:cache clean, served 200, live Chromium harness 48/48 computed-style checks + zero console errors (full verification entry below; frontend-only — ui.css only, no schema/ACL/route/controller/model/business change, zero blade edits; **audit findings**: 7 standalone public pages remain Bootstrap-dependant, `admin/users/show:134` `#passwordModal` trigger proven dangling again — documented for the finalization pass, no markup change; **removal-gate (Bootstrap CSS): NOT READY** — `.modal*` (48) + `.accordion` (10) + `.list-group` (7) + `.form-label` (46) + utilities + standalone pages remain; E2E not run — auth-gated screens, no account, DB untouched) |
| Phase 27 — Final Bootstrap CSS removal (Bootstrap-CSS removal phase 3, **complete**) | Removed **all 8** Bootstrap 5.3.2 CSS CDN `<link>`s; owned the last families in project `ui.css`: §4.8 (`.form-label`/`.accordion*`/`.list-group*`), §4.9 (Bootstrap utility parity with `!important`, incl. the JS-contract `.d-none`), §4.10 (Reboot + `_type.scss`: universal `box-sizing:border-box`, `body{color:#212529}`, element + heading defaults). `admin/users/show` dangling `#passwordModal` trigger removed; `e2e/alpine-phase0.spec.ts` now asserts CDN absence + ui.css presence | **Done** 2026-09-07 **301 tests / 1419 assertions** 0 failures, Pint clean, build + view:cache clean, live Chromium harness **5/5 structural gates (0 Bootstrap CSS requests, `window.bootstrap` undefined, 0 console/page errors, 200)** + **0 computed-style diffs** across 5 pages × 8 selectors × 44 props vs the pre-removal baseline, mobile spot-checks no horizontal overflow (full verification entry below; frontend-only — ui.css + 8 Blade CDN-link removals + 1 dead-trigger removal + 1 E2E assertion; no schema/ACL/route/controller/model/business change; **removal-gate (Bootstrap CSS): READY — dependency 8 → 0**; E2E not run — auth-gated screens, no account, DB untouched at 1604 audit rows) |
| Phase 28 — Final Bootstrap-Free Forensic Audit (**audit-only**, **complete**) | Independent terminal audit. Fresh repo-wide Bootstrap search classified every remaining occurrence (active dependencies **0**; remainder = historical docs, comments, E2E absence locator, 2 inert parity selectors, third-party framework/DataTables code). Bootstrap CSS CDN/JS CDN/DataTables Bootstrap CSS CDN **all 0** (views, compiled views, built assets, served HTML). Live Chromium 6 public surfaces: HTTP 200, `window.bootstrap` undefined, 0 Bootstrap network requests, 0 console/page errors, no mobile overflow. `--bs-*` active consumption **0**. DataTables class emitter verified not to reference the Bootstrap library. CSS ownership matrix produced. **No application/CSS/JS/schema/DB change** | **Done** 2026-09-07 **301 tests / 1419 assertions** 0 failures, Pint clean, build + view:cache clean, computed-style parity spot-checked live (full verification entry below; audit-only — no code changed, no DB write; **PASS — BOOTSTRAP-FREE COMPLETE; no further Bootstrap-removal phase required**; environment observation: `tbl_audit_logs` 1605 rows vs 1604 baseline = pre-existing pre-phase `LOGIN` row id 1735, reported not modified; E2E not run — auth-gated screens, no account, DB untouched) |

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
  PATH (`$env:PATH = "C:\xampp\mysql\bin;" +env:PATH`) or every
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
  `$this->authorize('delete',client)`; the base `Controller` now uses
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
  each registered with **literal URLs** and `->defaults('key',key)` (the
  controller receives `{key}` with no route parameter), gated by
  `page:scanner_*.php` middleware per key.
- **Sidebar** (`partials/sidebar.blade.php`) — loops `config('scanner.scanners')`
  rendering each title, gated via `canAccessPage($user,page)`.
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

### 2026-08-12 — P6 Phase 3 step 1: scholar relink (`update_client_id.php` port)

Ports v1 `update_client_id.php` — the scholar registry's inline Client-ID
relink — completing step 1 of `docs/SCHOLAR_ANALYSIS.md` §9 (list/feed/sidebar
were done in Phase 2; relink was the last remaining piece).

- **Route** — `POST scholars/update-client-id` inside the `page:scholars.php`
  group (`routes/web.php`).
- **Controller** — `ScholarController::updateClientId`: updates
  `tbl_scholar_info.client_id` by row id, returns `{"message":"success"}` or
  HTTP 400 `"Invalid input"`. Stricter than v1 by one guard: the scholar row
  and the target client must both exist (prevents orphan links; matches the
  `exists:tbl_clients,id` rule `ScholarRequest` already applies).
- **View** — `resources/views/scholars/index.blade.php`: Client ID column
  renders the v1 inline "Edit" button; click → `prompt()` for the new client
  id → AJAX POST → table reload (exact v1 UX).
- **Tests** — `tests/Feature/ScholarTest.php` +3 (relink success, missing
  input → 400, nonexistent client → 400): **11 passed (32 assertions)**; full
  suite **100 passed (523 assertions)**; Pint clean; `view:cache` compiles.
- **Docs** — P6_SCHOLARS.md status + §4, SCHOLAR_ANALYSIS.md §2.1/§6/§9/§10,
  blueprint §2 row + §8 row 84, README P6 row, SESSION_HANDOFF milestone.

**Deviations:** none behavioral — v1 `update_client_id.php` has no
restriction gate and no existence checks; v2 keeps the route behind
`page:scholars.php` (it is only reachable from the gated registry) and adds
the two existence checks described above.

---

### 2026-08-12 — P6 Phase 3 step 3: scholarship reports

Ports v1 `scholarship_reports.php` + `fetch_scholarship_reports.php` +
`export_scholarship_reports.php` — the reports screen, DataTables feed, and
BOM CSV export — completing step 3 of `docs/SCHOLAR_ANALYSIS.md` §9.

- **Routes** — `GET scholarship-reports`, `POST scholarship-reports/data`,
  `GET scholarship-reports/export`, all inside a new `page:scholarship_reports.php`
  group (`routes/web.php`).
- **Controller** — new `ReportController` with `scholarship` (index),
  `scholarshipData` (feed), `scholarshipExport` (CSV). The v1 asymmetric query
  shapes are preserved exactly:
  - Feed = transactions-led (six scholar programs) INNER JOIN clients, LEFT
    JOIN the `MAX(id)` scholar_info row per client, LEFT JOIN geo. `recordsTotal`
    is the raw six-program transaction count; `recordsFiltered` is the filtered
    count. Filters: municipality, barangay, program, date_from/date_to, plus the
    DataTables search (name CONCAT / school / course / mobile). The `submitted`
    filter is **accepted but ignored** — v1's feed never reads it (parity kept).
  - Export = scholar_info-led INNER JOIN clients, with `gwa`/`units`/`remarks`/
    `status`/`date_applied` pulled via correlated subqueries on the latest
    matching transaction (`ORDER BY date_applied DESC, id DESC`); program/date/
    submitted filters via `EXISTS`/`NOT EXISTS`; header row from the first data
    row's keys (includes `lastname`/`firstname`/`middlename`/`extensionname` +
    `full_name`, matching v1); streamed with UTF-8 BOM as
    `scholarship_reports<Ymd>.csv`.
- **View** — `resources/views/scholarship_reports/index.blade.php`: v1 filter
  set (municipality with `geography.barangays` cascade, program, submitted,
  date from/to) + 18-column table, pageLength 10, order by Full Name, Export CSV.
- **Sidebar** — Scholarship Reports link gated by `scholarship_reports.php`.
- **Tests** — new `tests/Feature/ScholarshipReportTest.php` (7 tests, 60
  assertions): screen render, feed joins/values, MAX-scholar-row + program
  filter, date-range + search filters, BOM CSV header/values (incl. extension
  name), program/submitted export filters, permission gating. Suite green.
- **Docs** — P6_SCHOLARS.md status + §4, SCHOLAR_ANALYSIS.md §9, blueprint
  §2 row + §8 rows 85–87, SESSION_HANDOFF milestone.

**Deviations:** none behavioral. v1's `fetch_scholarship_reports.php` ships no
`submitted` handling in the feed (only the export honors it) — preserved as-is;
v2 additionally gates all three routes behind `page:scholarship_reports.php`
(exactly the v1 permission key, so no new permission rows are needed).

---

### 2026-08-13 — Prototype: status badge legibility on the navy profile header

Fixes the resident profile slide-in panel in `prototype/index.html`, where the
client status pill (`Active`/`Archived`) was rendered inside the navy-blue
`details-header` but reused the light-background badge styles (translucent teal
on `--navy #0038A8`) and was effectively invisible.

- **File** — `prototype/css/style.css`: added on-dark `.details-meta
  .status-badge` overrides — `Active` gets a solid teal pill with white text
  and a white dot; `Archived` gets a translucent white pill with a subtle white
  border.
- **Verification** — opened the prototype, clicked a resident row, confirmed
  the `Active`/`Archived` pill is clearly legible against the blue header.

**Deviations:** none — presentation-only change, no markup or behavior touched.

---

### 2026-08-13 — P6 GIP details (v1 `save_gip.php` port)

Ground truth correction: v1 **GIP is not a standalone page** — the fields are an
accordion + modal embedded in `view_client.php#collapseGIP`, shown only for
clients that have a `tbl_transactions.program = 'GIP'` row, and saved via
`save_gip.php` (upsert of the latest `tbl_gip_info` row per client). The v2 port
keeps that shape inside the client profile page.

- **Service** — new `app/Services/GipService.php`: `save($input,userId)`
  ports `save_gip.php` exactly — trims every field, `mb_strtoupper`-uppercases
  all profile fields **except** `ecp_contact_number` and `year_graduated`
  (v1's sanitization block), then upserts the latest `tbl_gip_info` row for the
  client (`ORDER BY id DESC LIMIT 1`). Audit parity: writes `ADD_GIP` /
  `UPDATE_GIP` rows to `tbl_audit_logs` (`target_table='tbl_clients'`,
  `target_id=client_id`, old/new JSON payloads) via the existing
  `AuditService`; an update is logged only when something actually changed
  (v1 diff-guards the `UPDATE_GIP` log too).
- **Controller** — new `app/Http/Controllers/GipController.php::store`:
  validates `client_id` exists, saves, redirects to `clients.show` with the
  `#collapseGIP` anchor (v1 redirects to `view_client.php?id=…#collapseGIP`).
- **Route** — `POST clients/{client}/gip` → `gip.store`, registered inside the
  existing `page:clients.php` group (the same key that guards the profile page;
  v1 has no separate GIP permission key).
- **Model** — `app/Models/Client.php`: added `gipInfo()` hasMany relation
  (blueprint §3 already declared it).
- **View** — new `resources/views/clients/_gip.blade.php` partial (accordion +
  `#gipModal` with all 17 v1 fields; datalist-free plain inputs for
  college/course, matching the accepted v2 scholars form convention), included
  from `clients/_details.blade.php`. Rendered only when `$hasGipTransaction`
  is true; `ClientController@show` now loads `gipInfo` and computes
  `$gip` (latest row) + `$hasGipTransaction`.
- **Tests** — new `tests/Feature/GipTest.php` (6 tests, 20 assertions):
  section hidden without a GIP transaction; section + values shown with one;
  ADD_GIP with uppercase + audit; UPDATE_GIP logs only on change (identical
  resubmission appends nothing); invalid client rejected with no side effects;
  permission-gated store. Full suite green (113 tests, 603 assertions) and
  `pint --test` passes.
- **Docs** — this entry; blueprint §2 row 169 + §8 row 88 status; P6 phase
  status in §6; `SESSION_HANDOFF.md` milestone.

**Deviations:** none behavioral. `college`/`course` are free-text inputs in v2
(the v1 modal's ~90/100 hard-coded `<option>` lists are not reproduced), which
stores the same uppercased strings and matches the established v2 scholars form.

---

### 2026-08-13 — P6 QR viewer (v1 `view_qrcode.php` port)

Ports the public scholar QR self-service page. Like the P5 self-service screens,
v1 `view_qrcode.php` has **no session check**, so it is a public top-level route
(no CSRF), reached by URL — v1 does not link it from the admin nav, and neither
does v2 (no sidebar entry).

- **Controller** — new `app/Http/Controllers/QrController.php::show` renders
  `qr.viewer`. The page itself is static; search / municipality / verify reuse
  the shared P5 `grantee-search` endpoints (`GranteeSearchController` =
  v1 `search_grantee.php`), which v1's QR page also consumed.
- **Route** — `GET qr-viewer` → `qr-viewer`, top-level public beside the P5
  `grantee-search` routes (SCHOLAR_ANALYSIS §9 step 4 + Appendix A placement).
- **View** — new `resources/views/qr/viewer.blade.php`, port of v1's
  autocomplete + municipality verify + QR display + download/reset UI, using
  `kind=grantee` (full six-program search). Per decision C the QR payload is the
  **persisted comma-form `client.full_name`** returned by the verify endpoint —
  not a re-composed name — so scans resolve through the P4 `ScanService` lookups
  (they match `tbl_clients.full_name`). QR still generated by v1's external
  `api.qrserver.com` (parity, no new package).
- **Search endpoint** — `GranteeSearchController::CLIENT_COLUMNS`: added
  `full_name` to the verify response (additive; v1's `clientOut` did not carry it
  because the v1 page re-composed the name — decision C changed that).
- **Tests** — new `tests/Feature/QrViewerTest.php` (3 tests, 11 assertions):
  page renders publicly with the v1 title/labels; `kind=grantee` autocomplete
  finds a `CEDSSG` client that the `unpaid` search excludes; verify returns the
  persisted `full_name`. Full suite green (116 tests, 614 assertions) and
  `pint --test` passes.
- **Docs** — this entry; blueprint §2 row 171 + §8 row 477 status; `P6_SCHOLARS.md`
  §4 QrController bullet; `SESSION_HANDOFF.md` milestone.

**Deviations:** none behavioral. The QR payload now encodes the persisted
`full_name` (decision C) instead of v1's JS-composed string; the two forms are
identical in the common case.

---

### 2026-08-13 — Prototype: clients table replaces Mobile with Precinct No.

In `prototype/index.html` the Client Registry table showed `Mobile`; the column
was swapped for the client's precinct number per request, and the column order
is now exactly: **Client, Precinct No., Municipality, Barangay, Category,
Status**.

- **Files** — `prototype/index.html` (thead reorder), `prototype/js/app.js`:
  `makeResident()` now sets a deterministic `precinct` value
  (`0002A`-style, derived from the seeded id), and `renderClients()` emits the
  cells in the new order. Mobile remains on the details panel, where it still
  belongs.
- **Verification** — opened the prototype, Client Registry shows the precinct
  number in the correct column position; empty-state row `colspan=7` still
  matches the 7 cells.

**Deviations:** none — presentation-only prototype change.

---

### 2026-08-13 — P6 grantee updates (v1 `save_grantee_update.php` + `disabled_update_grantee.php` + `update_logs.php` port)

Ports the public grantee self-update flow and the admin update-logs screen.
v1 `disabled_update_grantee.php` / `save_grantee_update.php` have **no session
check** (grantees use them at the payout venue), so the routes are public
(top-level, no CSRF) like the P5/QR self-service pages.

- **Controller** — new `app/Http/Controllers/GranteeUpdateController.php`:
  `selfService()` renders the form, `store()` saves, `logs()` renders the
  admin screen.
- **Service** — new `app/Services/GranteeUpdateService.php` (v1-exact): in one
  transaction it updates `tbl_clients` (name parts + municipality/barangay
  PRESERVED from the DB row — the v1 readonly fields; the rest uppercased /
  trimmed like v1), upserts the latest `tbl_scholar_info` row per
  `(client_id, program)` (UPDATE sets `updated_at = NOW()`; INSERT writes the
  comma-form `full_name` + `created_at = NOW()`), and appends to
  `tbl_update_logs` with the space-joined `FIRST MIDDLE LAST` name, the request
  IP, and the exact action `'Grantee self-updated their information.'` — a
  module log, never `tbl_audit_logs`. Required fields
  (`mobile_no/email/birthdate/sex/civil_status`) and all v1 error messages are
  preserved.
- **Routes** — public top-level `GET grantee-update` / `POST grantee-update/save`;
  public aliases `GET grantee/verify-mobile` + `GET grantee/barangays` (the v1
  helpers `verify_mobile.php` / `get_barangays.php` are public too — the aliases
  reuse the existing `ClientController@verifyMobile` /
  `GeographyController@barangays`); gated `GET update-logs` behind
  `page:update_logs.php`.
- **Views** — new `resources/views/grantee_update/self-service.blade.php`
  (autocomplete → mobile verify → municipality verify → prefilled form → save →
  QR display) and `resources/views/update_logs/index.blade.php` (server-rendered
  rows + client-side DataTables, date filter, name formatting, UTC → Asia/Manila
  `m/d/Y - h:i A`). Sidebar: Update Logs link gated by `update_logs.php`.
- **Not ported** — `fetch_update_logs.php` is dead in v1 (never referenced;
  `update_logs.php` renders its own rows), mirror of the P5 dead-export.
- **Tests** — new `tests/Feature/GranteeUpdateTest.php` (10 tests, 37
  assertions). Full suite green (126 tests, 651 assertions), `pint` clean.

**Deviations:** no behavioral. `fetch_update_logs.php` not ported (dead in v1)
as the established dead-code exception; `school`/`course`/`college_department`
are free-text inputs (the accepted v2 deviation) instead of v1's ~100/300
hard-coded `<option>` lists. Two strict-mode parities worth noting (same stored
data as v1, which runs a non-strict MySQL): the scholar INSERT supplies
`year_started`/`landbank_no = ''` explicitly (NOT NULL columns that v1 lets
MySQL coerce), and the `pwd`/`ip` values are coerced to `'NO'` when the posted
value isn't a valid `enum('YES','NO')` member — v1's form sends arbitrary/blank
text into the enum and only survives on its non-strict server.

### 2026-08-13 — P6 step 9: client picker for the standalone scholar create form

Completes the last P6 item (SCHOLAR_ANALYSIS §6 step 9). The standalone
`scholars/create` (and the shared `scholars/_form`) now pick the beneficiary
with a live client search instead of an empty `<select>`.

- **Route** — new `GET scholars/clients-search` inside the `page:scholars.php`
  group, reusing `TransactionController@searchClients` (DRY: same method the
  transactions picker uses; gated by the scholars page so a scholar-only clerk
  does not need `all_transactions.php`).
- **Form** — `resources/views/scholars/_form.blade.php` replaces the empty
  `client_id` select with a search input + hidden `client_id` + live results
  list (same JS pattern as the transactions picker). The hidden field is
  prefilled from the scholar's client on edit (fixing a latent bug — the edit
  form's select was empty) and from `?client_id=` on create. Program + scholar
  fields unchanged.
- **Tests** — `tests/Feature/ScholarTest.php` +6 (clients-search match / short
  query / gate 403 with JSON Accept / create prefill from `?client_id=` / edit
  prefill / store rejects nonexistent client). Full suite green: **132 tests,
  664 assertions**; `pint` clean.

**Deviations:** none.

### 2026-08-13 — P6 finalization: GIP audit payload v1-parity fix + close-out

Finalization audit of the whole P6 module against v1 and the P6 contract docs.
One real parity deviation was found and fixed; everything else matched.

- **Fix — `app/Services/GipService.php`.** The `ADD_GIP`/`UPDATE_GIP`
  `tbl_audit_logs` old/new JSON only encoded the 16 editable columns
  (`->only(self::COLUMNS)`). v1 `save_gip.php` encodes the **full row**
  (`SELECT *` then `json_encode`), which is exactly what `SCHOLAR_ANALYSIS`
  §1.5 / §4 item 6 document ("full-row old/new JSON"). Now `getAttributes()`
  is used for all three payloads (update-old, update-new, insert-new), so the
  audit rows carry `id` + `client_id` + the 16 columns in table order, like v1.
- **Tests — `tests/Feature/GipTest.php`** +4 assertions on the ADD_GIP payload
  (decodes `new_value`, asserts `id`/`client_id` keys, `client_id` value,
  and a sample field), locking the full-row contract against regression.
- **Verification.** Full suite green: **132 tests, 668 assertions**;
  `vendor\bin\pint` clean across the project; `php artisan view:cache`
  compiled every Blade view (incl. the P6 pages not exercised by tests —
  `qr/viewer`, `grantee_update/self-service`, `update_logs/index`,
  `scholarship_reports/index`, `clients/_gip`). Tests run on the forced
  `main_system_test` DB — production `main_system` untouched; no schema work.
- **Docs.** `P6_SCHOLARS.md` header refreshed to **COMPLETE** (was still
  claiming GIP/grantee-updates/QR viewer "remain to be built");
  `ENGINEERING_BLUEPRINT.md` P6 module status → **Done** (2026-08-13);
  `README.md` P6 row corrected (blueprint §1.7, was §1.12) + prose now lists
  P6 complete. `SCHOLAR_ANALYSIS.md` unchanged — already the canonical record.

**Deviations:** the fix itself re-aligns the implementation with the documented
v1 contract; no new deviations.

---

### 2026-08-15 — P7 Administration subsystem (blueprint §1.11)

Owner-approved P7 build per `docs/implementation/P7_ADMINISTRATION.md` +
`docs/ADMIN_ANALYSIS.md`. Approved decisions: no automatic/bootstrap seeder for
production admins (first `'*'` access is a reviewed one-time cutover SQL grant
for a nominated existing user); the seven `MANAGE_*` audit strings exactly;
**no** `active` column (v1 create-only); audit enhancements C/D/E in scope,
A/B/F deferred; no municipality/data-scope authz, no action-level CRUD, no
username/user-id checks, `AccessControlService` canonical, `AuditService` sole
`tbl_audit_logs` writer.

**Created:**

- `app/Http/Requests/{UserCreateRequest,PagePermissionRequest,ProgramPermissionRequest,ExemptionToggleRequest}.php`
  — centralized validation: username unique on `tbl_users` (varchar(100)),
  password min 8 + confirmed; page values in the **real catalog** (distinct
  `tbl_permissions.page_name` + the five P7 keys, `'*'` excluded — contract §9.2,
  v1's hard-coded array is incomplete/duplicated so it is not copied); programs
  `in:TransactionService::PROGRAMS`; `super_admin`/`grant` `sometimes|boolean`.
- `app/Http/Controllers/UserController.php` — v1 `register.php`/`add_user.php`
  create-only port (`admin/users/create` + `admin/users`), zero-permission
  start, `MANAGE_USER_CREATE` audit (payload `{'username': ...}` only — never
  `getAttributes()`).
- `app/Http/Controllers/AdminPermissionController.php` — page permissions
  (full-replace DELETE+INSERT, `'*'` managed solely by the confirmed
  `super_admin` toggle, GRANT/REVOKE audited only when the `'*'` row flips),
  program permissions (full-replace on the 17-program catalog), multi-device
  exemptions (idempotent toggle; `'*'`-holders excluded from the picker and
  rejected server-side as already-exempt; no-op toggle writes no audit).
- `app/Http/Controllers/AuditController.php` — v1 `audit_logs.php` +
  `fetch_logs.php` + `fetch_leaderboard.php` port. Viewer whitelist = the v1
  business tables **plus the four P7 subject tables** so `MANAGE_*` rows are
  readable (interpretation of Pass 6 §9.4); subject `target_id` resolves to the
  username; feed preserves the v1 JSON shape `{data, users, actions}`, UTC →
  Asia/Manila `m/d/Y - h:i A` (+ `date_raw` for the client-side date filter),
  `LIMIT 10000`, distinct per-table users/actions; per-table leaderboard ordered
  by descending action count. Feeds nest inside the `page:audit_logs.php` group
  (v1 `fetch_leaderboard.php` had no session check; v2 closes that gap).
- `resources/views/admin/...` — `users/create`, `permissions/{pages,programs,exemptions}`,
  `audit_logs/index` (client-side DataTables, user/action selects, `type=date`
  from/to filter replicating v1's `ext.search`, 5s auto-reload, leaderboard
  modal). Sidebar gains five `canAccessPage`-gated links.

**Edited:**

- `routes/web.php` — five `page:<key>` route groups (register.php,
  manage_permissions.php, manage_program_permissions.php,
  manage_multi_device_exemptions.php, audit_logs.php) inside the
  `auth + single-device` group; feeds nested under audit_logs.php.
- `resources/views/partials/sidebar.blade.php` — P7 links.

**Deviations (documented):** contract §6.2 says `pages`/`programs` are
`required, array`; implemented as `nullable|array` so an empty set is accepted
— required for v1's full-replace **remove-all** semantics (contract §9.6) and
covered by `test_remove_all_page_permissions_is_allowed` /
`test_remove_all_program_permissions`. No-op exemption toggle returns a
status message rather than an audit row (contract §11.5 no-op rule).

**Verification.** New `tests/Feature/AdministrationTest.php` — 26 tests / 101
assertions covering: page-gate authz for every P7 screen (incl. no
username/id bypass, program permission not granting screens, JSON 403 on feeds
without the gate), user creation + duplicate + confirmation + zero-permission
start + `MANAGE_USER_CREATE`, page/program full-replace + unknown-value
rejection (no writes on failure), `'*'` grant/revoke rows + audits, granted
page taking effect for the middleware, catalog incl. P7 keys, exemption
grant/revoke + idempotence + `'*'` no-op + picker exclusion, feed shapes
(clients/transactions/subject display names, UTC→Asia/Manila, defaults), and
credential-leak guard on all payloads. Full suite green: **158 tests, 769
assertions** on `main_system_test`; `vendor\bin\pint` clean on all changed
files; production `main_system` untouched; no schema work. Notes:
`AccessControlService` is a request-lifecycle singleton — tests reset it via
`forgetInstance()` after writes that flip exemption/permission state.

---

### 2026-08-16 — P12 action authorization + municipality scope (implemented)

Owner-approved Pass 12 contract (bind-points §22 of `docs/ADMIN_ANALYSIS.md`)
shipped. Adds the **action** dimension (which mutation/export a user may run on
a page) and the **municipality** dimension (which records a user may see/write)
on top of P1's PAGE + PROGRAM ACL, behind the S2 `enforcement` flag that is
**off for every pilot page** — behavior is byte-identical to pre-P12 until a
page's flag flips in `config/authorization.php`.

**Schema (additive only, local `main_system`):**

- `database/migrations/2026_08_15_000001_create_tbl_action_permissions_table.php`
  — `(user_id, page_name, action)` with `uniq_action_permission_user_page_action`
  (approved DDL verbatim; InnoDB / utf8mb4_unicode_ci, no FKs).
- `database/migrations/2026_08_15_000002_create_tbl_user_municipalities_table.php`
  — `(user_id, municipality_id)` with `uniq_user_municipality`; the reserved
  `0` value is the ALL-municipalities marker (distinct from `'*'`).
- `database/schema/mysql-schema.sql` regenerated via `schema:dump`
  (sentinel-free, migration rows renumbered to 10/11). Backup before the
  migrations: `C:\Users\J\AppData\Local\Temp\opencode\main_system_before_p12.sql`.

**New files:**

- `config/authorization.php` — `catalog` + 5 pilot pages (clients.php,
  household.php, all_transactions.php, scholars.php, register.php) each with
  `actions` and `enforcement => false`.
- `app/Models/{ActionPermission,UserMunicipality}.php` + `User` relations
  (`actionPermissions()`, `municipalityScope()`).
- `app/Http/Middleware/AuthorizeAction.php` (alias `action`, mirror of
  AuthorizePage: JSON → 403, else dashboard flash `login_status=denied`).
- `app/Support/RecordMunicipality.php` — data-only resolvers
  (`ofClient/ofTransaction/ofHousehold/ofScholar/ofGip/ofUnpaidVerification`;
  `tbl_household` joins on `head_household`).
- `app/Http/Requests/{ActionPermissionRequest,MunicipalityScopeRequest}.php`.
- `resources/views/admin/permissions/{actions,scopes}.blade.php` + sidebar links.
- `tests/Feature/{ActionPermissionTest,ScopeTest,AuthorizationAdminTest}.php`
  and `tests/Feature/AccessControlServiceTest.php` (37 tests / 118 assertions).

**Edited:**

- `app/Services/AccessControlService.php` — `canAccessAction` (with canonical
  uppercase action normalization), `permittedActions`, `hasAllMunicipalities`,
  `effectiveMunicipalityIds`, `canAccessRecord`, `applyMunicipalityScope`
  (Builder `whereIn`), all inert until `enforcement`. Page settings are read as
  a literal `config('authorization.pages')` index (dot-notation would split the
  `clients.php` key).
- `routes/web.php` — `action:<page>,<action>` on the §11 route map (18
  instances) + 4 admin routes under `page:manage_permissions.php`; comma (not
  colon) separates page and action because Laravel middleware params split on
  `,` after the first `:`.
- `app/Http/Controllers/*` (Client, Duplicate, Household, Transaction, Scholar,
  Gip, FamilyMember, Photo, AdminPermission) — scope seams on feeds/searches
  and record-level checks on single-ID/write endpoints; admin `actions`/
  `updateActions`/`scopes`/`updateScopes` full-replace saves with
  `MANAGE_ACTION_PERMISSIONS` / `MANAGE_SCOPE_ASSIGNMENTS` audits and no-op
  detection (no write/audit when unchanged).
- `bootstrap/app.php` (`action` alias), `AppServiceProvider.php`
  (`Gate::define('action', ...)`), `app/Models/User.php`.

**Verification.** New suites cover: action gate truth table (inert default,
enforced deny/allow, `'*'` bypass, VIEW = page row, unknown action fails
closed, flip-off restores behavior, Gate registration, export block/grant),
scope behavior (unscoped feeds off, scoped recordsTotal/data on, fail-closed
empty scope, ALL marker, `'*'`, record checks on client show/destroy/store and
household destroy, transaction + scholar subquery feeds), admin screens
(render, full-replace + audit strings, unknown composite/municipality
rejected, no-op silence, manage_permissions gate). Full suite green: **195
tests / 887 assertions** on `main_system_test`; `vendor\bin\pint` clean on all
changed files; production `main_system` untouched (only the 2 additive tables
added locally); v1 untouched. The only cutover step remaining per S2 (§13) is
flipping `enforcement` in `config/authorization.php` when the owner is ready.

---

### 2026-08-24 — P8 decision package prepared (hardening scope + P12 S2 cutover readiness)

- **Decision-preparation pass only — no application code, schema, grants, or
  enforcement flags changed.** Deliverable:
  `docs/implementation/P8_DECISION_PACKAGE.md` (sections A–I: hardening scope,
  pre-cutover requirements, five-page readiness matrix, rollout order, grant
  plan, super-admin bootstrap runbook, ADR-001..010 review, owner decisions,
  no-op confirmation).
- **Key findings:**
  - Local `main_system` is a schema-only copy (0 rows in all domain tables;
    only local dev users `jordi`/`jiro`) → production user/permission/
    municipality data is **not locally verifiable**; reconciliation queries
    for the cutover window are provided in the package.
  - **Contract deviation found:** P12 §20 required a test for "client update
    moving a record to an out-of-scope municipality → denied"; neither test
    nor server-side check exists (`ClientController@update` checks only the
    current municipality). Proposed as hardening item A.1 [required].
  - Login throttling (ADR-007 / v1 C2) still absent → item A.2 [required].
  - Recommended-but-not-blocking: route-composition regression test (A.3),
    config-shape guard test (A.4); optional public-endpoint throttling (A.5).
  - Deferred P7 audit enhancements: no security/correctness necessity found →
    stay deferred.
  - ADR recommendations without status changes: ACCEPT 001/002/003/004/005/009;
    REVISE→ACCEPT 008; KEEP PROPOSED 007/010 until their P8 items ship.
- **Verification:** full suite re-run green (**195 tests / 887 assertions** on
  `main_system_test`); DB inspected strictly read-only (`SHOW TABLES`,
  `DESCRIBE`, `SELECT`); `config/authorization.php` untouched (all five flags
  off); both P12 pivot tables confirmed empty; v1 untouched.

---

### 2026-08-24 — P8 hardening implemented (A.1–A.4 per approved decision package)

Owner-approved scope (`docs/implementation/P8_DECISION_PACKAGE.md`): A.1 + A.2
required, A.3 + A.4 recommended; everything else explicitly deferred (public
self-service throttling, deferred P7 audit enhancements, denial auditing,
non-pilot pages, program-gating redesign). No cutover actions in this pass.

**Created:**

- `tests/Feature/AuthorizationArchitectureTest.php` (A.3 + A.4):
  - every `action:<page>,<action>` middleware instance must sit inside the
    matching `page:` group (composition proven over the live route
    collection), AND every non-VIEW action of every pilot page must be gated
    by at least one route — action authorization cannot silently become a
    standalone layer;
  - config-shape guard: exact five pilot keys, exact catalogs, all
    `enforcement` flags false at rest, catalog membership validated.

**Edited:**

- `app/Http/Controllers/ClientController.php` (A.1) — `update()` now also
  checks the **destination** municipality: a scoped user can no longer move a
  client into a municipality outside their `tbl_user_municipalities` scope.
  The current-record check is unchanged and runs first.
- `app/Http/Controllers/AuthController.php` (A.2) — Laravel-native login
  throttling via `RateLimiter`: key = lowercase username + IP; 5 attempts /
  60 s lockout; counter cleared on success; lockout raises
  `ValidationException` ("Too many login attempts…", web → redirect with
  error, JSON → 422). Username/bcrypt/session-token contract untouched;
  v1-parity invalid-credential response unchanged below the threshold.
- `tests/Feature/ScopeTest.php` (A.1) — the missing P12 §20 regression pair:
  enforced scope user + EDIT grant cannot move an in-scope client to an
  out-of-scope municipality (403, row unchanged); can move it to an
  in-scope municipality (redirect, row updated).
- `tests/Feature/AuthTest.php` (A.2) — 3 new tests: 5 failed attempts lock
  out even valid credentials (guest + throttle message); locked account stays
  locked with wrong password and writes no session token; successful login
  still works below the threshold (token written).

**Verification.** Targeted suites green (24 tests / 150 assertions); full
suite green: **202 tests / 984 assertions** on `main_system_test` (+7 tests /
+97 assertions vs pre-P8); `vendor\bin\pint` passed on all changed files;
route registry re-verified via tinker — exactly **18** `action:` instances,
unchanged from P12, all composed under their page groups (A.3 test enforces
this permanently); `config:show authorization.pages` confirms all five
enforcement flags remain **false**; `tbl_action_permissions` and
`tbl_user_municipalities` confirmed **0 rows**; local `main_system` touched
only by read-only queries; production never connected; v1 untouched.

**ADR status changes (owner-approved, evidence-verified):** 001, 002, 003,
004, 005, 009 → **Accepted**; 008 → **Accepted (revised)** — trigger
mechanism amended to direct `AuditService` calls as shipped; 007 stays
**Proposed** (rotation + HTTPS pending) with P8 throttling recorded in its
Implementation line; 010 stays **Proposed** (backups/restore drill pending);
006 remains **Superseded**.

---

## 2026-08-22 — Functional-completeness fixes (audit items 1–7)

Seven approved functional-completeness gaps from the delegated audit were
implemented with minimal, targeted changes. No schema changes, no destructive
commands, v1 untouched, `main_system` untouched (tests run on
`main_system_test` only).

**1. Admin password reset restored (v1 `manage_php.php`).** The earlier
"PHP-editor concept removed" reading was wrong about the file's primary
function: it is a super-admin user-management/password-reset screen. The
runtime-PHP-editing concept remains excluded; the reset workflow is restored:

- `UserController@index` + `@resetPassword` (+ `admin/users/index` view,
  `page:*` route group, sidebar "User Management" link for `'*'` holders) —
  the v1 hardcoded `super_admin` username gate becomes the data-driven `'*'`
  permission row via the existing ACL service.
- `PasswordResetRequest` — required / string / **min:8** / confirmed (v1 rule).
- Protected targets: resetting any `'*'` holder is rejected ("You cannot
  change the password of a super admin.") — the data-driven translation of
  v1's `super_admin` target guard.
- Persistence: bcrypt via the model's `hashed` cast; a `password_resets`
  log row (`changed_by`/`changed_for`, v1 parity) **plus** a `PASSWORD_RESET`
  `tbl_audit_logs` entry through `AuditService` (single writer; payload holds
  only the target username, never the password). The legacy reset-log viewer/
  CSV-export/delete UI was deliberately not reproduced (smallest maintainable
  implementation; rows remain queryable in `password_resets`).

**2. Transaction full-page edit fields restored.**
`TransactionController@update` now validates and persists `comments`,
`gwa`, `units` exactly like v1 `edit_transaction.php` (comments uppercased;
nullable numerics); `transactions/edit.blade.php` gained the three fields.
Program authorization, municipality scope, and `EDIT_TRANSACTION` auditing
are unchanged (the service already audited these fields).

**3. Online-users filtering restored.** `sessions/online.blade.php` no longer
lists every user: `SessionController@online` applies the v1 semantics —
`session_token IS NOT NULL`, `last_activity >= now()-20min`, exclusion list —
with the v1 `'jordi'/'super_admin'` name exclusion expressed as "no `'*'`
permission row" (the established data-driven translation). The v1 green
"Online" badge is rendered. (`last_activity` was already refreshed per
request by `EnsureSingleDevice`.)

**4. User-create password minimum.** `UserCreateRequest` password now
`required|string|min:8|confirmed`. Correction: the 2026-08-15 entry above
claimed "password min 8 + confirmed" but the shipped code lacked `min:8`;
that statement is accurate as of this date.

**5. RBAC consolidation recorded (no behavior change).** Verified against v1:
the six granular catalog keys (`add/edit/view_client.php`,
`add/edit/view_transaction.php`) were grantable-but-inert in v1 itself (their
files never include `restriction.php`), so v2's enforcement under
`clients.php`/`all_transactions.php` is enforcement parity, not a deviation.
Recorded explicitly in ADR-003; P8 reconciliation query 7 added so ALL
distinct production `tbl_permissions.page_name` values are detected before
cutover grants/flips.

**6. Scholars client-search scope governance (S-1).**
`scholars.clients-search` now passes `scopePage=scholars.php` to
`TransactionController@searchClients` via route defaults
(`transactions.clients-search` passes `all_transactions.php`), so the search
is governed by its own page's scope context instead of a hardcoded key. No
authorization logic duplicated; transaction behavior unchanged.

**7. Login throttle username normalization.** `AuthController@login` trims
the username once and uses the same normalized value for both the throttle
key and the credentials (v1 `login.php` parity: `$username =
trim($_POST['username'])`). Padded input can no longer mint fresh attempt
buckets or diverge from what is authenticated.

**Created:** `app/Http/Requests/PasswordResetRequest.php`,
`resources/views/admin/users/index.blade.php`.

**Modified:** `UserController`, `AuthController`, `SessionController`,
`TransactionController`, `UserCreateRequest`, `routes/web.php`,
`sessions/online.blade.php`, `transactions/edit.blade.php`,
`partials/sidebar.blade.php`; tests `AuthTest`, `AccessControlTest`,
`AdministrationTest`, `TransactionTest`, `ScopeTest`.

**Verification.** Full suite green: **213 tests / 1056 assertions** on
`main_system_test` (+11 tests vs pre-fix 202/984): new regressions cover
padded-username login + shared throttle bucket, online-filter semantics,
short-password rejection, all five password-reset behaviors (screen access,
hash+both logs, min/confirm, protected target, non-super-admin blocked),
full-page comments/gwa/units persistence + audit, and scholars-governed
client search. `vendor\bin\pint` passed on all changed PHP files; affected
routes re-verified via `route:list`; Blade compiles (`view:cache`);
`main_system` migrations table re-read unchanged (sentinel + batches 1–2);
production never connected.

---

## 2026-08-23 — UI/UX analysis started (Phase 1: repository reconnaissance)

Created `docs/UI_UX_ANALYSIS.md` — Phase 1 of a multi-phase UI/UX analysis.
Documentation-only change; **no application code, routes, views, assets, or
schema were modified**, v1 and `main_system` untouched.

**Contents (factual inventory only):**
- Executive summary of the two parallel UI artifacts: the standalone
  `prototype/` SPA mock (index.html 45.6 KB / style.css 65.5 KB / app.js
  132.5 KB + 498-line PROTOTYPE_SPEC.md + root concept PNG) and the implemented
  Blade UI (46 view files: 1 layout, 6 partials, 7 standalone public pages,
  31 authed pages, 1 dead framework stub; ~53 addressable screens incl. 14
  config-driven scanner keys and 3 payout variants).
- Factual current-state description: Bootstrap 5.3.2 CDN shell, per-view inline
  `<style>` blocks (24 files), jQuery 3.7.1 + DataTables 1.13.6 on 9 list
  screens, html5-qrcode scanner, 2-second session polling, ACL-aware sidebar
  (`AccessControlService::canAccessPage`/`canAccessProgram` mirroring the
  `page:`/`action:` route gates).
- Full screen inventory (public §3.1, authenticated §3.2) mapped to routes,
  views, controllers, and ACL gates; complete repository inventory (prototype
  assets, layouts, partials, CSS, JS, routes, controllers/services, config
  drivers).
- Explicitly marked: prototype design analysis, prototype-to-V2 comparison,
  and recommendations are NOT yet performed (Phases 2–4 pending).

**Created:** `docs/UI_UX_ANALYSIS.md`.

**Verification.** File existence and content re-read after write; no code or
config files changed (`git status` shows documentation only); no tests affected
(no runtime change).

---

## 2026-08-23 — UI/UX analysis Phase 2 (prototype design analysis)

Extended `docs/UI_UX_ANALYSIS.md` with the **Phase 2 — Prototype Design
Analysis** section (§5). Documentation-only change; **no application code,
routes, views, assets, or schema were modified**, v1 and `main_system`
untouched. All Phase 1 findings preserved unchanged.

**Method.** Read the full prototype sources — `prototype/css/style.css`
(1,894 lines), `prototype/index.html` (834 lines), `prototype/js/app.js`
(2,669 lines) — plus `PROTOTYPE_SPEC.md` (read in Phase 1). No assumptions;
every claim is tied to observed code.

**Contents (factual design-system record):**
- Visual identity: actual `:root` token values (PH flag palette — navy
  #0038A8, gold #FCD116, red #CE1126; legacy #0F1B2D/#C9953E palette retained
  as a commented block), Inter/Outfit typography on a 14 px root, branding
  treatment (seal containers, gold uppercase tagline), and the noted factual
  deviation from the spec's §7 palette.
- Application shell: navy-gradient 260 px sidebar with labeled sections,
  gold active state + left bar, user-card footer; sticky 64 px topbar with
  breadcrumb, global search, notifications panel, single-device indicator;
  off-canvas drawer behavior <1024 px.
- Page structure, full component inventory (metric/data cards, tables with
  sort/hover/chevron affordances, button variants incl. gold-primary,
  forms + chip-based multi-select pickers, filter popover system, segmented
  tabs, numeric pagination, modal system with focus traps and promise-based
  confirms, 480 px slide-in details panel with per-breakpoint widths,
  toasts), dashboard widget composition, responsive tier table, and the
  accessibility pattern set (skip link, focus-visible rings, aria wiring,
  Escape priority chain).
- Design-token summary (colors, type scale, spacing, radius ladder, shadow
  ramp, motion timings incl. the 200–340 ms range vs. spec's 150–250 ms) and
  ten recorded UX conventions of the prototype.
- Factual gaps noted without judgment: no loading/skeleton components exist;
  error states limited to confirm dialogs.

**Modified:** `docs/UI_UX_ANALYSIS.md` only (banner → "PHASE 2 COMPLETE",
executive-summary status line, phase table, new §5).

**Verification.** Re-read the updated document: Phase 1 sections §1–§4 byte-
identical in content, Phase 2 section present with all ten requested analysis
areas, phase-status table shows Phases 1–2 complete / 3–4 not started;
`git status` confirms documentation-only changes.

---

## 2026-08-23 - UI/UX analysis Phase 3 (current V2 UI analysis)

Extended `docs/UI_UX_ANALYSIS.md` with the **PHASE 3 - CURRENT V2 UI
ANALYSIS** section (6). Documentation-only change; **no application code,
routes, views, assets, or schema were modified**, v1 and `main_system`
untouched. All Phase 1 and Phase 2 sections preserved unchanged.
`prototype/index.html` remains byte-untouched (pre-existing working-tree
modification left as-is).

**Method.** Re-used the representative-view inspection already performed this
session (`layouts/app`, `dashboard`, `clients/index` + `_form` + `_details`,
`households/index`, `transactions/index`, `scholars/index`,
`payouts/attendance`, `scanners/scan`, `sessions/online`,
`admin/permissions/pages`, `auth/login`,
`unpaid_verifications/self-service`) plus repo-wide greps over
`resources/views` (`@media` usage, `role=` and `aria-*` attribute totals,
native `alert()`/`confirm()` file counts, `badge bg-*` usage,
uppercase/text-transform mechanisms, jQuery/DataTables loading). No further
repository inspection was performed.

**Contents (factual record):**
- Application shell structure: fixed navbar + 220 px ACL-gated sidebar with
  margin-toggle collapse (no breakpoint behavior), flash region, 2 s session
  watchdog; shell lacks breadcrumb/search/notifications/indicator/footer.
- Page structures: canonical list-screen template (card > header actions >
  filters > DataTables POST feed > actions column) plus per-module
  variations; form pages; client profile/detail page doubling as Offcanvas
  slide-over via `?panel=1` partial fetch with script re-execution; admin
  matrix screens; config-driven scanner screen; public standalone pages.
- Component inventory table incl. exactly 4 badge uses, zero tabs, one
  Offcanvas, loading/empty/error state mechanisms, scanner audio elements.
- Dashboard confirmed as a 10-line placeholder card.
- Responsiveness: zero `@media` queries in routed views; adaptation relies
  entirely on Bootstrap utilities; shell identical at all widths.
- Accessibility quantified: aria-label x17, aria-hidden x7,
  aria-labelledby x4, aria-expanded x3, aria-controls x1; `role=` limited to
  Bootstrap scaffolding; native alert()/confirm() in 12/11 files; no
  skip-link, focus styling, or live regions.
- De-facto design conventions: Bootstrap-default palette, Roboto vs
  system-ui split across layouts, uniform shadow-lg cards, three divergent
  `uppercase` mechanisms (one inert).
- Ten repeated-pattern/inconsistency findings, including the scholars list
  screen's missing jQuery/DataTables dependency plus stray closing brace
  (factual runtime dependency issue) and the copy-pasted municipality to
  barangay cascade in 5+ call sites.
- Twelve preserve-worthy functional/UI implementation patterns current
  features rely on (ACL sidebar gating, DataTables feed contract, panel
  partial-fetch mechanism, config-driven shared templates, watchdog
  contract, geography cascade, inline-edit protocol, export query builder,
  photo pipeline, scanner loop mechanics, self-service contracts, admin
  matrix mechanics).

**Modified:** `docs/UI_UX_ANALYSIS.md` only (banner "PHASE 3 COMPLETE",
executive-summary status line, phase-status table, new section 6).

**Verification.** Re-read the updated document: sections 1-5 intact, section
6 present with all ten required analysis areas, phase-status table shows
Phases 1-3 complete / Phase 4 not started; `git status` confirms
documentation-only changes.

---

## 2026-08-23 - UI/UX analysis Phase 4 (prototype <-> V2 comparison and gap analysis)

Extended `docs/UI_UX_ANALYSIS.md` with the **PHASE 4 - PROTOTYPE <-> V2
COMPARISON AND GAP ANALYSIS** section (7.1-7.13). Analysis/planning only;
**no application code was modified** — no Blade/PHP/JS/CSS/route/controller/
service/database files touched, `prototype/index.html` untouched,
`main_system` untouched. All Phase 1-3 sections preserved unchanged.

**Method.** Documentation-level cross-reference of the persisted analyses:
every §5 prototype fact paired against §2/§3/§6 V2 facts under six lenses
(visual/UX, functional, structural, accessibility, responsive, interaction
patterns). Two targeted spot-checks in the prototype only (scanner page
composition `index.html:747-778` + `style.css:1034-1112`; milestone-
placeholder copy `index.html:785-809`); no repository re-inspection.

**Contents:**
- 7.1 methodology + four-value status vocabulary (exists/partial/differs/
  absent) and source-of-truth ground rules.
- 7.2 global shell comparison (sidebar, nav hierarchy, topbar, breadcrumbs,
  global search, user area, responsive nav, active states, density).
- 7.3 page/IA pairing table: matched, partially matched (dashboard depth,
  Scholars five-tab cluster vs separate routes/GIP partial/public pages),
  V2-exceeds (payouts/access control/audit where prototype shows P5/P7
  placeholders), missing prototype UX patterns, V2-only screens, terminology
  and navigation differences.
- 7.4 sixteen-component comparison with explicit status values.
- 7.5 eleven interaction-pattern comparisons (row-click details, CRUD flows,
  destructive confirmations, filters, search, navigation, modal/slide-in
  behavior, keyboard/Escape, feedback, scanner interactions — V2 scanner
  authoritative and richer than the mock).
- 7.6 responsive comparison at desktop/tablet/mobile anchored on the factual
  zero-custom-@media finding; Bootstrap utilities do not imply prototype
  equivalence.
- 7.7 accessibility cross-reference using quantified Phase 3 counts.
- 7.8 design-token gap analysis: aligned items (destructive red, workspace
  layering, dark chrome, rounded cards) vs divergences (primary action color,
  typography, radius/shadow ramps, status semantics, interaction states).
- 7.9 functional-vs-visual conflict analysis: A adopt-presentation-only /
  B cannot-copy-literally / C V2-behavior-authoritative (watchdog, scanner
  loop, ACL truth) / D adapt-don't-copy.
- 7.10 sixteen-row gap matrix (Area | Prototype | Current V2 | Gap | Impact |
  Recommended Direction).
- 7.11 prioritized recommendations P0-1..P3-4, each with problem/evidence/
  affected area/direction/behavior impact; none implemented.
- 7.12 Preserve / Adapt / Replace / Defer categorization.
- 7.13 conclusion: strongest V2 foundations, largest gaps, priorities,
  implementation constraints.

Headline conclusion recorded: functionally V2 meets or exceeds the prototype
nearly everywhere (including the three placeholder destinations); the
decisive gaps are experiential — mobile navigation strategy, shell wayfinding
(breadcrumbs/global search), unified non-blocking feedback vs native
dialogs/alerts, design-token identity, accessibility scaffolding, and the
placeholder dashboard.

**Modified:** `docs/UI_UX_ANALYSIS.md` only (banner "PHASE 4 COMPLETE",
executive-summary status paragraph incl. headline conclusion, phase-status
table row, new section 7).

**Verification.** Re-read the updated document: Phases 1-3 content intact
(§1-§6 headings confirmed), §7 present with 7.1-7.13, banner string matches
exactly "UI/UX ANALYSIS IN PROGRESS - PHASE 4 COMPLETE", phase-status table
shows Phases 1-4 complete; `git status` confirms documentation-only changes;
no marker artifacts left in the document.

---

## 2026-08-23 - UI/UX analysis Phase 5 (implementation plan - PLANNING ONLY)

Extended `docs/UI_UX_ANALYSIS.md` with the **PHASE 5 - UI/UX IMPLEMENTATION
PLAN** section (8.1-8.15). This is a **PLANNING-ONLY phase**: no application
code was modified — no Blade, PHP, JavaScript, or CSS files touched; no routes,
controllers, services, models, schema, migrations, or configuration changed;
no packages installed; `prototype/index.html` untouched. All Phase 1-4 content
preserved unchanged.

**Method.** Derived exclusively from the persisted Phases 1-4: the §7.11
recommendations (P0-1..P3-4), the §6.10 functional contracts (treated as
frozen interfaces), the §6.9 inconsistencies (as consolidation targets), and
the §5 prototype tokens/patterns (as visual reference values only). No new
repository inspection was performed and no code was written.

**Contents (planning record):**
- 8.1 twelve implementation principles (preserve functionality/business
  rules/ACL, source-of-truth rules, incremental, shared-over-duplicated,
  minimal dependencies, a11y-first, responsive-first, reversible,
  verify-per-batch).
- 8.2 architecture roles for layout/navbar/sidebar/partials/page files/
  inline styles/scripts; planned NEW shared artifacts limited to one plain
  stylesheet (`public/css/ui.css`), one small script (`public/js/ui.js`),
  and Blade partials — no framework, no build tooling.
- 8.3 design-system consolidation plan: single shared stylesheet decision,
  verbatim prototype tokens as `--ui-*` variables, class-level Bootstrap
  overrides, semantic slot mapping (navy structure / gold primary action /
  teal success / red destructive / amber pending / blue approved), per-area
  plans for colors, typography, spacing, radii, shadows, buttons, badges,
  cards, tables, forms, alerts, status indicators, focus states, responsive
  conventions.
- 8.4 shell modernization plan: offcanvas-lg drawer via Bootstrap native
  behavior, sidebar grouping/icons/active styling with ACL loop preserved,
  breadcrumbs/page-header partials, reserved-but-empty search/notification
  slots, explicit preserves (ACL-aware nav, page authorization, single-device
  watchdog contract) + mobile acceptance criteria.
- 8.5 fifteen-component modernization table (current implementation, target,
  location, shareable, behavior change?, risk) incl. scanner UI flagged as
  frozen-logic zone.
- 8.6 accessibility implementation plan with markup-only vs
  interaction-affecting classification per item.
- 8.7 responsive plan at desktop/tablet/mobile using Bootstrap breakpoints
  (992 px drawer threshold documented as an explicit adaptation of the
  prototype's 1024 px tier) with explicit acceptance criteria.
- 8.8 dashboard element classification: REAL DATA AVAILABLE (quick actions
  w/ ACL checks, recent transactions reuse, scanner deep-links) vs
  DATA/ENDPOINT NEEDED (read-only counts/sum/distribution/activity scope —
  all decision-gated) vs VISUAL PLACEHOLDER ONLY (pending-approvals KPI,
  announcements, calendar); no invented data.
- 8.9 nine screen-group roadmap entries with expected changes, shared
  components, and behavior/a11y/responsive risk plus testing requirements
  per group.
- 8.10 nine batches A-I with dependencies, affected files, risk, verification
  method, and rollback boundary per batch.
- 8.11 file impact map (SHARED FOUNDATION / HIGH / MEDIUM / LOW / NO CHANGE)
  incl. explicit no-change lists for controllers/services/models/routes/
  config/database and starter scaffolding.
- 8.12 verification strategy: functional (login, ACL, CRUD, forms, filters,
  tables feeds, scanner matrix, payouts, public flows), responsive viewport
  pass 1440-320, accessibility keyboard/SR/focus/contrast passes, regression
  (route diff, suite baseline 213 tests/1056 assertions, watchdog two-device),
  visual prototype comparison.
- 8.13 twelve-row risk register with likelihood/impact/mitigation.
- 8.14 recommended order A→I with rationale.
- 8.15 conclusion: first-implementations, must-stay-untouched list,
  careful-testing list, safe-standardization list, deferrals.

**Modified:** `docs/UI_UX_ANALYSIS.md` only (banner "PHASE 5 PLAN COMPLETE",
banner phase bullet + closing note, executive-summary roadmap sentence,
phase-status table row, new section 8).

**Verification.** Re-read the updated document: Phases 1-4 content intact
(§1-§7 headings confirmed), §8 present with 8.1-8.15, banner matches exactly
"UI/UX ANALYSIS IN PROGRESS - PHASE 5 PLAN COMPLETE", phase-status table shows
Phases 1-5 complete; `git status` confirms documentation-only changes; no
marker artifacts left.

---

## 2026-08-23 - UI/UX Batch A - shared foundations implemented

First implementation batch of the Phase 5 UI/UX plan (`docs/UI_UX_ANALYSIS.md`
§8.10, "A - Shared foundations"). Presentation-only: establishes the reusable
visual foundation later batches build upon. **No backend behavior changed** -
no routes, controllers, services, models, authorization, validation, database,
or business-rule modifications. `prototype/*` untouched (the pre-existing
`prototype/index.html` working-tree modification remains exactly as found).

**Created:**

- `public/css/ui.css` - shared design-system stylesheet (plain CSS, no build
  step), loaded by `layouts/app` immediately after the Bootstrap 5.3.2 CDN
  link and before the layout's inline shell styles (shell styles keep
  precedence). Contents:
  - `--ui-*` design tokens mirroring the prototype `:root` verbatim
    (`prototype/css/style.css`, analysis §5.1): navy/gold/teal/red/amber/
    blue-accent palette, surfaces/borders/text, semantic slot aliases,
    typography variables (resolving to current Roboto/system-ui reality -
    the Inter/Outfit swap stays a one-line token decision), spacing ladder,
    radius ladder 6/8/12/16/999 px, shadow ramp xs-xl + gold glow, motion
    tokens, current-shell sizing + touch-target tokens.
  - Accessibility foundations: global `:focus-visible` gold ring (3 px,
    offset 2), `.ui-skip-link` styling (anchor markup lands with Batch B),
    `prefers-reduced-motion` block (documented `!important` exception).
  - Scoped Bootstrap alignment via Bootstrap's own per-component custom
    properties: `btn-primary`/`btn-outline-primary` to navy,
    `btn-danger`/`btn-outline-danger` to flag red, new `.btn-gold`
    (gold fill / navy text), form-control/select/check focus ring gold +
    navy border, checked checkboxes navy, pagination active/hover navy
    (covers DataTables BS5 pagers without JS changes). Success/warning/info
    buttons intentionally left on stock colors until per-screen convergence.
  - Shared component classes for upcoming batches (additive, unused by
    existing pages): `.status-badge` dot-pill family with fixed semantic
    mapping (paid/active teal, approved blue, pending amber, rejected red,
    archived gray; AA-darkened text shades documented inline),
    `.metric-card` (+ accent variants) and `.metric-value`, `.data-card`
    header/body/footer, `.ui-page-header/-title/-subtitle/-actions`,
    `.ui-micro-label`, `.ui-notice`, `.ui-empty`.
  - Deliberately deferred: body background/root font-size changes,
    table/DataTables chrome restyle, modal skins, mobile touch-target
    overrides (own batches per §8.3/§8.10).

**Modified:**

- `resources/views/layouts/app.blade.php` - exactly one added line loading
  `css/ui.css` after the Bootstrap CDN `<link>`. Everything else in the
  layout is byte-intact: auth behavior, flash alerts, sidebar toggle JS,
  single-device session watchdog, `@stack` extension points.

**ui.js intentionally not created:** plan §8.2 reserves `public/js/ui.js`
for toast emitter / data-confirm wiring / keyboard-row helpers, whose first
consumers arrive in later batches (flash-toasts B/C, confirms C, keyboard
rows E/H). Creating it now would ship dead code with no Batch A consumer;
the layout loads no shared script yet and the file will be introduced
together with its first consumer.

**Verification.**

- `php artisan view:cache` compiles all Blade templates cleanly (layout edit
  parses); cache cleared afterwards.
- Full suite green on `main_system_test`: **213 tests / 1056 assertions**,
  matching the Phase 5 baseline exactly; no tests modified.
- Asset path valid: single reference via `asset('css/ui.css')`; file exists
  at `public/css/ui.css`; grep confirms no references to a nonexistent
  `public/js/ui.js`; Bootstrap still the first stylesheet in `<head>`.
- CSS brace-balance sanity check passed.
- `git status`: only `resources/views/layouts/app.blade.php` (+1 line)
  modified plus new `public/css/ui.css`; pre-existing working-tree changes
  preserved untouched.

**Backend impact:** none. Next per §8.10: Batch B - shell/navigation
(offcanvas-lg drawer, sidebar grouping/icons/active bar, page-header/
breadcrumb partials).

---

## 2026-08-23 - ARCHITECTURE DECISION: Tailwind-first production styling (inspection only - nothing implemented)

Owner architecture correction, recorded BEFORE further UI/UX batches. The
production styling direction changes from "Bootstrap 5.3.2 + shared
custom stylesheet" to **Laravel 12 + Blade + Tailwind CSS + minimal JS**,
with the prototype remaining the visual source of truth, Laravel v2 the
functional source of truth, and all backend behavior untouched. This is a
decision record only — **no dependency was installed, no code changed**
(no `package.json`/`vite.config.js`/`app.css`/layout/ui.css edits, no view
migration, Batch B NOT started).

**Inspection performed (read-only):**

- `package.json`: `tailwindcss ^4.0.0` + `@tailwindcss/vite ^4.0.0` already
  declared as devDependencies (Laravel 12 scaffolding); scripts build/dev.
- Install state: **never installed** — no `node_modules`, no
  `package-lock.json`, no `public/build`; `.gitignore` excludes both
  `/node_modules` and `/public/build`.
- `vite.config.js`: laravel-vite-plugin (inputs `resources/css/app.css`,
  `resources/js/app.js`, refresh) + `@tailwindcss/vite`; configured but
  unused by live screens (`@vite` referenced only by unrouted
  `welcome.blade.php`).
- `resources/css/app.css`: valid Tailwind v4 entry — full
  `@import 'tailwindcss'` (includes Preflight), correct `@source` globs for
  Blade/JS, one theme override (Instrument Sans) to replace with
  prototype-derived fonts at foundation time.
- Bootstrap usage: 5.3.2 CSS+JS CDN in `layouts/app.blade.php` + 7 standalone
  public pages; DataTables BS5-integration on 9 list screens; Bootstrap JS
  components functionally load-bearing (modals x5+, clients Offcanvas,
  navbar/export dropdowns).
- `composer.json`: no styling-related packages.
- Batch A state: `public/css/ui.css` active and verified (213/1056);
  presentation-only.

**Decision:** Tailwind v4 CSS-first via the already-declared `@tailwindcss/vite`
setup; §8.3's single-stylesheet end-state and Tailwind rejection are
superseded for remaining batches; principles/batches/verification of §8 stand.
Full decision record written as **§9 addendum to `docs/UI_UX_ANALYSIS.md`**:
token mapping (`--ui-*` values → v4 `@theme` namespaces, additive names),
Batch A disposition (retain during migration, retire at final pass — never a
blind delete), coexistence mechanics (skip Preflight while Bootstrap CSS is
loaded; cascade-layer spike before any screen migrates; `@vite` after the
Bootstrap CDN link), Bootstrap JS retained through B–G, revised sequence with
a new prerequisite batch **T1 "Tailwind foundation"**, and a seven-row risk
register (incl. the new node/npm operational dependency and the
gitignored-build deployment policy decision).

**Modified:** documentation only — `docs/UI_UX_ANALYSIS.md` (banner pointer
bullet + new §9 addendum; Phases 1–5 content untouched),
`docs/SESSION_HANDOFF.md` (UI/UX status bullet + next-session item updated).
`prototype/index.html` pre-existing modification untouched.

**Verification:** re-read both updated docs; `php artisan view:cache` still
compiles clean; suite intentionally not re-run (no application files
touched); `git status` confirms docs-only changes plus the pre-existing
working-tree state.

**Backend impact:** none. Next session: implement approved T1 foundation
(npm install → lockfile committed; `app.css` tokens + Preflight-skip;
layout `@vite` wiring; zero-visual-delta gate) — then Batch B Tailwind-first.

---

## 2026-08-23 — UI/UX T1 — controlled Tailwind v4 foundation implemented

Prerequisite batch for Batch B per `docs/UI_UX_ANALYSIS.md` §9.7. Establishes
Tailwind safely alongside the live Bootstrap 5.3.2 UI; migrates nothing.
Presentation/build-layer only: **no backend changes of any kind** — no
routes, controllers, services, models, middleware, authorization,
validation, database, migrations, or business rules touched. No screen
migrated (sidebar/navbar/dashboard/registries all untouched). Bootstrap CSS
+ JS retained intentionally (`@vite` sits between the Bootstrap CDN link and
the `ui.css` link in `<head>`, so existing cascade relationships are
unchanged); `public/css/ui.css` retained and frozen as the compatibility
layer until the final pass (§9.6). `prototype/index.html` byte-for-byte
untouched (pre-existing working-tree modification preserved exactly;
SHA-256 verified identical at session end).

**Created:**

- `package-lock.json` — reproducible install from the already-declared
  devDependencies; `package.json` itself NOT modified (no version bumps).
  Committed as a legitimate project artifact (§9.7-1). `/node_modules` and
  `/public/build` remain gitignored — the build-artifact deployment policy
  flagged in §9.8-4 stays an explicit owner decision.

**Modified:**

- `resources/css/app.css` — rebuilt on Tailwind v4 CSS-first architecture:
  - Partial imports replacing full `@import 'tailwindcss'`:
    `theme.css` into `layer(theme)` + `utilities.css` UNLAYERED with
    **`source(none)`**, plus a retained `@layer theme, base, components,
    utilities;` ordering statement. Preflight is NOT imported while
    Bootstrap Reboot styles live screens (§9.7-2); utilities are unlayered
    so they can override unlayered Bootstrap classes by source order at
    equal specificity once markup intentionally uses them (§9.7-4/§9.8-2) —
    no `!important` needed.
  - **Explicit source allowlist (owner decision during T1):** NO blanket
    globs over resources/views. Discovery: with the documented globs, both
    Tailwind's automatic content detection AND explicit scanning generated
    same-named utilities for existing Bootstrap classes carrying DIFFERENT
    values (`p-3` 12px vs 16px, `p-4` 16px vs 24px on ~30 screens' card
    wrappers, `p-5`, margin/gap drift, `w-50`/`w-100` fixed-vs-percent,
    `container` widths, shadow swaps) — since our sheet loads after the
    Bootstrap CDN, these wrong-valued twins would have overridden Bootstrap
    site-wide (unacceptable regression, caught before any screen changed).
    Owner chose a controlled source strategy over exclusions/blocklists/
    prefix. `source(none)` disables auto-detection; each migration batch
    adds its own narrow `@source` line (file or migrated directory) in the
    SAME change that migrates the markup — contract documented inline.
  - `@theme static` token foundation transplanted verbatim from Batch A's
    `--ui-*` values (prototype §5.1-derived; §9.5 mapping): navy/navy-light/
    navy-hover, gold/gold-light/gold-dim, teal/teal-light, red/red-light,
    amber, blue-accent; surfaces bg/bg-alt/surface/surface-hover; borders
    line/line-light; ink/ink-secondary/ink-muted/ink-inverse; font-body/
    heading/mono (Roboto now — Instrument Sans starter override dropped);
    additive type sizes text-micro/dense/page-title/metric + tracking-caps;
    radius-control/btn/card/panel/pill (6/8/12/16/999 px); shadow-hairline/
    card/lift/pop/overlay/glow ramp + gold glow; ease-standard. All names
    additive — no Tailwind default scale key clobbered. `static` emits all
    tokens as CSS variables regardless of usage (parity with ui.css's
    always-on `:root`). Spacing needs no tokens: prototype ladder equals
    stock p-1…p-7; motion durations map onto duration-150/200/300.

- `resources/views/layouts/app.blade.php` — one added line:
  `@vite(['resources/css/app.css'])` immediately after the Bootstrap CDN
  `<link>` and before the `css/ui.css` link (§9.7-3 transition order;
  rendered head order verified: Bootstrap CDN → built app.css → ui.css →
  fonts → inline shell styles). Bootstrap JS bundle, sidebar toggle JS, and
  single-device watchdog untouched.

**Cascade spike (one representative screen, dashboard.blade.php, reverted
afterwards):** with a temporary owner-approved fixture view feeding only
intentional token candidates and probe classes temporarily applied to the
dashboard card, tinker-rendered HTML confirmed: (1) Tailwind loads via the
manifest asset after the Bootstrap CDN link; (2) `.bg-navy`/`.rounded-pill`
utilities exist unlayered in a later sheet and therefore override
Bootstrap's `.card` at equal specificity — override capability proven
without `!important`; (3) navbar/sidebar partials, bootstrap.bundle.min.js,
sidebar toggle, and session-watchdog script all intact; (4) ui.css link
present and effective; (5) zero Preflight markers in output. All spike
artifacts (fixture view, temporary `@source` line, dashboard probe classes)
removed afterwards; dashboard card markup re-verified byte-identical.

**PoC assertions (temporary spike build):** intentional utilities generated
(`bg-navy`, `text-gold`, `rounded-card`, `shadow-card`, `border-line`,
`text-ink-muted`, `font-body`, `ease-standard`) while collision probes
(`p-4`, `p-3`, `gap-3`, `w-100`, `w-50`, `container`, `shadow-lg`, `mb-1`,
`mt-3`) were NOT generated from unmigrated views. Final committed build:
1.41 kB CSS = layer statement + theme tokens only, **zero utility
selectors, zero Preflight markers** — structurally guaranteed zero visual
delta on every live screen until markup opts in.

**Verification.**

- `npm install` clean (87 packages, 0 vulnerabilities): tailwindcss
  4.3.3, @tailwindcss/vite 4.3.3, vite 6.4.3, laravel-vite-plugin 1.3.0;
  `npm run build` succeeds; manifest written to gitignored public/build.
- `php artisan view:cache` compiles all Blade templates cleanly (cleared
  after).
- HTTP smoke: `php artisan serve` → GET /login 200 (Bootstrap CDN present),
  built asset URL 200, /css/ui.css 200.
- Full suite green: **213 tests / 1056 assertions** — matches baseline
  exactly; no tests modified.
- `git status`: only `resources/css/app.css` +
  `resources/views/layouts/app.blade.php` (+1 line) modified and
  `package-lock.json` created beyond pre-existing working-tree state;
  `prototype/index.html` hash unchanged; no test artifacts left behind.

**Open item carried forward (§9.8-4, owner decision):** production
asset-deployment policy — either run the npm build at deploy time or change
the `/public/build` ignore rule. Tests/rendering require the built manifest
once `@vite` lives in the layout.

**Backend impact:** none. Next per §9.9: **Batch B — shell/navigation
Tailwind-first** (offcanvas drawer, sidebar grouping/icons/active bar,
page-header/breadcrumb partials), adding Batch B's `@source` lines under
the new allowlist contract.

---

### 2026-08-24 - Batch B: Tailwind-first authenticated shell (layout + sidebar + navbar)

Implements §9.9 Batch B of `docs/UI_UX_ANALYSIS.md`: the shell chrome is
rebuilt on the T1 token/utility foundation while Bootstrap 5.3.2 stays loaded
for unmigrated screens. All routes, ACL checks, flash alerts and the watchdog
session script are preserved; the old `.collapsed`/`.shifted` margin-toggle
mechanics are replaced by a native Bootstrap **`offcanvas-lg`** responsive
drawer (<992px) that becomes a fixed 260px navy column at ≥992px.

**Files changed**

- `resources/css/app.css`
  - Added Batch B `@source` lines — three explicit FILE paths
    (`layouts/app.blade.php`, `partials/sidebar.blade.php`,
    `partials/navbar.blade.php`). Deliberately NOT a directory glob: the new
    inert stubs (`partials/page-header`, `partials/breadcrumbs`) stay
    unscanned until their first consumer batch adds their line.
  - Added unlayered shell component classes: `.sidebar-link` (+ `.active`,
    gold bar via `::before`, 18px svg sizing), `.sidebar-section-label`,
    `.ui-skip-link` (sr-only → focus-revealed skip link).
  - Documented a new **coexistence rule**: stock spacing utilities at steps
    3–5 must never appear in migrated markup, @apply arguments or even
    comments in this file — Bootstrap defines the same class names with
    different values (BS step 3/4/5 = 1rem/1.5rem/3rem vs TW
    .75rem/1rem/1.25rem), and Tailwind v4 scans this CSS file itself,
    comments included. Violations were caught by the build gate during this
    batch and fixed with arbitrary-value forms (`px-[1.25rem]` etc.) or
    longhand declarations.
- `resources/views/layouts/app.blade.php`
  - Inline `<style>` reduced to the legacy compat block (`.card` radius);
    body/nav/sidebar/content rules replaced by utilities
    (`body.bg-[#f8f9fa].font-body`, `<main id="main-content" class="pt-16 lg:ml-[260px]">`).
  - Added skip-link as first body child and a semantic `<main>` landmark.
  - New `$shellSections` catalog (single source for sidebar groups AND topbar
    breadcrumb): Overview / Registry / Assistance / Administration. The AICS
    placeholder moves from last position into the Assistance group (grouped
    order change is part of the Batch B design; within-group order preserved).
  - Watchdog script byte-preserved (`checkSession` + 2000 ms interval +
    fetch/alert flow); only the removed toggle listener is gone, replaced by
    an `aria-expanded` sync for the offcanvas hamburger.
- `resources/views/partials/sidebar.blade.php`
  - Rebuilt as `<aside id="appSidebar">` with `offcanvas-lg offcanvas-start`,
    drawer width 280px via `[--bs-offcanvas-width:280px]`, navy→#0C1622
    gradient painted through `background-image` at ALL widths — chosen
    deliberately because Bootstrap forces
    `background-color:transparent!important` at ≥992px, which a background
    utility cannot beat but background-image sidesteps entirely.
  - ≥992px geometry via plain utilities (`lg:fixed lg:top-16 lg:bottom-0
    lg:left-0 lg:z-30 lg:w-[260px] lg:flex lg:flex-col`) — safe because BS's
    `width:auto!important` reset only targets plain `.offcanvas` under
    `.navbar-expand-*`, never `.offcanvas-lg`.
  - Grouped sections with uppercase micro labels, feather-style inline SVG
    icons, gold active bar, `aria-current="page"` on the active link, brand
    row with drawer close button, footer user card. ACL calls preserved
    exactly: per-item `canAccessPage`, config-driven scanner loop, payout
    trio pages/routes, `canAccessProgram($user,'AICS')`.
- `resources/views/partials/navbar.blade.php`
  - Fixed 64px topbar (`h-16 z-40 shadow-hairline bg-dark`); `fixed-top`/
    `navbar-expand-lg` classes dropped; hamburger wired to the offcanvas
    (`data-bs-toggle="offcanvas" data-bs-target="#appSidebar"`).
  - Breadcrumb "2DMIS › {label}" resolved from `$shellSections` without any
    ACL involvement (scanner titles via `config('scanner.scanners')`),
    hidden below `sm`. Account dropdown preserved verbatim minus the
    redundant `shadow` class.
- `resources/views/partials/page-header.blade.php`,
  `resources/views/partials/breadcrumbs.blade.php` — NEW inert stubs per the
  Batch B file matrix (adoption contract documented in-file; unscanned until
  first consumed).

**Verification**

- Build gate sweep over the emitted CSS: all forbidden collision twins
  absent (`.p-3/.p-4/.p-5/.px-3/.px-4/.px-5/.py-3/.py-4/.py-5/.pl-3/.pr-3/
  .pt-*/.pb-*/.m-*/.mt-*/.mb-*/.me-*/.ms-*/.w-100/.w-50/.container/
  .shadow-lg/.h-100`); expected Batch B utilities present
  (`--bs-offcanvas-width:280px`, `lg:w-[260px]`, `lg:ml-[260px]`,
  `from-navy`, `bg-gold-dim`, `shadow-hairline`, component classes).
- ACL parity probe (super admin, offline render): sidebar href set
  **35/35 SET-EQUAL** against the pre-Batch-B baseline snapshot.
- Routed kernel render of `/`: HTTP 200, exactly one active link with
  `aria-current="page"` (Dashboard), breadcrumb label "Dashboard", all four
  section groups rendered, login-status alert rendered, built Vite asset
  linked. (The error-alert twin could not be exercised in the harness —
  `ShareErrorsFromSession` overrides seeded bags on routed requests; markup
  is unchanged from the previously verified block.)
- `php artisan view:cache` clean; full suite green: **213 tests /
  1056 assertions** (unchanged baseline); `vendor\bin\pint --dirty` passed.

---

### 2026-08-24 - Batch C: shared component vocabulary + flash toast stack

Implements §9.9 Batch C ("components — utility patterns + minimal shared JS
where justified") of `docs/UI_UX_ANALYSIS.md`. No screens are migrated
(per the batch contract: class swaps begin with the screen batches D–G).
Bootstrap 5.3.2 and frozen `public/css/ui.css` remain loaded for unmigrated
screens; `ui.css` gained zero new rules.

**Files changed**

- `resources/css/app.css` (+~190 lines, no `@source` changes)
  - New unlayered component vocabulary mirroring the FROZEN ui.css API
    name-for-name where names are Bootstrap-safe, so screen batches adopt
    one stable vocabulary and ui.css retirement at Batch I cannot strip
    their styling: `.status-badge` + `.is-success/is-paid/is-active/
    is-info/is-approved/is-warning/is-pending/is-danger/is-rejected/
    is-neutral/is-archived` (identical AA-darkened colors),
    `.data-card/-header/-body/-footer`, `.metric-card` (+ `.accent-*`
    custom-property modifiers), `.metric-value`, `.ui-notice`, `.ui-empty`,
    `.ui-micro-label`.
  - Buttons are deliberately SELF-CONTAINED Tailwind classes (no Bootstrap
    `.btn` base, no `--bs-btn-*` variables) so they survive Bootstrap CSS
    retirement: `.btn-gold` (prototype metrics; ui.css hover/active stops
    #EAC400/#DBB800 preserved), plus NEW non-colliding variants that
    replace ui.css's Bootstrap-name overrides for migrated markup:
    `.btn-navy` (structural; mirrors overridden .btn-primary),
    `.btn-red`/`.btn-outline-red` (destructive-only; mirrors overridden
    .btn-danger stops #A80F20/#900C1B), `.btn-subtle` (outline→fill,
    mirrors .btn-outline-primary).
  - NEW form field helpers for migrated forms (unmigrated screens keep
    Bootstrap controls + ui.css focus restyle): `.field-label`,
    `.field-control` (gold focus ring per §8.3), `.field-hint`,
    `.field-error`.
  - NEW applied-filter chip pair `.filter-chip`/`.filter-chip-remove`
    supporting the active-filters partial below.
  - Intentionally NOT mirrored: ui.css `.ui-page-*` (the page-header
    partial is the single source of truth) and all Bootstrap-name
    overrides (`.btn-primary`, `.form-control:focus`, `.page-link`, …)
    which must stay ui.css-only while Bootstrap loads. Global gold
    `:focus-visible` indication remains provided by ui.css for every
    element including new components; its Tailwind replacement is part of
    the Preflight decision at Batch I.
- `resources/views/layouts/app.blade.php`
  - `session('login_status')` flash converted from an in-flow Bootstrap
    alert to a fixed top-right toast stack: container carries
    `aria-live="polite"`, each toast is a native Bootstrap Toast
    (`role="status"`, `data-bs-autohide="false"` — manual dismiss only, so
    no message can silently expire; §8.13 risk 12 mitigation), token-
    styled surface with teal check chip and labelled close button. A
    three-line init loop reveals server-rendered toasts (real consumer;
    satisfies the "no dead shared JS" rule). Controllers are untouched —
    same session key, same values.
  - The `$errors` validation alert block remains byte-identical (inline
    server contract per §8.13 risk 12); verified rendering through a
    flashed session error bag.
- `resources/views/partials/active-filters.blade.php` — NEW inert stub
  (planned in §8.5 Filters / §8.11): documented `$activeFilters`
  contract, uses the static `.filter-chip*` classes (already in the built
  stylesheet), stays unscanned/unincluded until a screen batch wires it.

**Deferred out of Batch C (with reasons)**

- Confirm-modal helper replacing `confirm()` ×11 and AJAX `alert()` →
  toast helper: original plan created them here, but they have no consumer
  until screen batches E/G wire them; creating unconsumed shared JS is
  barred by the no-dead-shared-JS rule. Ships with the first consuming
  batch.
- DataTables/pagination/modal chrome restyles: achievable only via global
  overrides of Bootstrap/DataTables classes, which would visually change
  unmigrated screens during coexistence — per-screen work in E/G instead
  (matches the original plan's "per-screen commits").
- Uppercase single-rule consolidation: requires view sweeps and touches
  stored-value behavior (JS/PHP casing); belongs to H/I.

**Verification**

- Build gates over emitted CSS: all 19 component classes present;
  Preflight absent; forbidden collision twins absent (full sweep incl.
  padding/margin steps 3–5 both axes, `.w-100/.w-50/.container/.shadow-lg/
  .h-100/.border-l-3`); zero `!important`; bundle 13.53 kB → 27.56 kB
  (static vocabulary, expected).
- Full suite green: **213 tests / 1056 assertions** (baseline unchanged;
  tests assert `assertSessionHas('login_status', …)` — session state,
  not markup — so the presentation-channel change is invisible to them).
- `vendor\bin\pint --dirty` passed; `php artisan view:cache` compiles
  clean (cleared after).
- Routed probes: dashboard 200 with seeded flash → toast container +
  element + role/autohide attributes + message text + init script +
  labelled close all present, old alert-warning gone; `/clients`
  (unmigrated) 200 with Bootstrap card markup, DataTables init and BS
  button classes intact; referenced build assets exist on disk (no 404s);
  errors alert renders from a flashed session bag.
- `git diff --name-only`: no backend paths touched; `prototype/index.html`
  diff unchanged from pre-existing working-tree state.

---

### 2026-08-24 - Batch D: dashboard composition (real-data widgets only)

Implements §9.9 Batch D / §8.8 of `docs/UI_UX_ANALYSIS.md`. The dashboard
is the first migrated screen and the first consumer of the shared header
partials. Bootstrap CSS/JS and frozen ui.css remain loaded for all other
screens; ui.css gained zero new rules.

**Widget classification applied (§8.8)**

- IMPLEMENTED — Quick actions (REAL DATA AVAILABLE): Add Client
  (`clients.create`, gated by `canAccessPage(clients.php)` AND
  `canAccessAction(...,'create')`), Register Household (`households.create`,
  same double gate on household.php), New Transaction (`transactions.index`
  page-gated — the create form requires a beneficiary context
  (`transactions/create/{client}`), so the workflow starts at the existing
  search screen, matching today's user path). Scanner deep links render as
  `btn-subtle` pill chips via the SAME config-driven loop as the sidebar,
  one `canAccessPage()` per scanner page at render time. Every link points
  at an existing authorized route; page/action middleware still runs on
  navigation.
- IMPLEMENTED — Recent transactions (REAL DATA AVAILABLE): client-side
  fetch of the EXISTING DataTables feed `POST transactions.data`
  (start=0, length=5, order by created_at desc) so municipality scope and
  program restrictions are enforced inside the endpoint itself — zero
  duplicated business logic, zero backend changes. Widget markup, "View
  all" link AND its script ship only to users passing
  `canAccessPage(all_transactions.php)` (verified: restricted account gets
  neither markup nor JS). Rows render status badges from the Batch C
  vocabulary (`is-paid` / `is-pending` / `is-neutral`), amounts right-
  aligned tabular numerals, client names link to `transactions.show`
  whose record-level ACL is enforced server-side. Honest states only:
  loading notice, empty-state message when no rows exist, failure notice
  on fetch error.
- OMITTED (no fabrication): KPI cards (registered clients, transactions,
  amount disbursed — decision-gated reads with no endpoint today),
  Pending Approvals placeholder (no approval-workflow concept exists),
  Program Distribution, Announcements, Activity Calendar, Recent Activity
  (all DATA/ENDPOINT NEEDED or VISUAL PLACEHOLDER ONLY under §8.8).
  A role-neutral fallback line renders when a user has neither quick
  actions nor transactions access ("No dashboard shortcuts are available
  for your account yet.").

**Files changed**

- `resources/views/dashboard.blade.php` — rewritten from the Bootstrap
  placeholder card to the composed screen above. Adopts
  `partials.page-header` (h1 + subtitle) and `partials.breadcrumbs`
  (single root crumb → correctly renders nothing). Layout: two-column
  grid ≥ xl (quick actions 20rem rail + recent card fluid), stacked
  below; quick-action chips reflow 1→2 columns at sm; recent table sits
  in an `overflow-x-auto` wrapper (min-width 42rem) instead of any
  Bootstrap responsive class.
- `resources/css/app.css` — @source allowlist += dashboard.blade.php,
  partials/page-header.blade.php, partials/breadcrumbs.blade.php (the
  two stubs graduate to scanned now that they have their first consumer;
  no further growth expected for these files).
- `resources/views/layouts/app.blade.php` — head gains the standard
  `<meta name="csrf-token">` consumed by the dashboard fetch (additive;
  nothing else touched).

**Verification**

- Build gates over emitted CSS: Preflight absent; full forbidden-collision
  sweep clean (two real violations caught and fixed during development —
  `py-4` in dashboard rows and `mb-4` in the breadcrumbs partial were
  converted to ladder-exact `py-[16px]`/`mb-[16px]` before final build);
  zero `!important`; dashboard-only utilities confirmed present (sr-only,
  tabular-nums, min-w-[42rem], xl two-column template, hover states);
  bundle 27.56 kB → 30.23 kB.
- Full suite green after all changes: **213 tests / 1056 assertions**;
  `vendor\bin\pint --dirty` passed; Blade view:cache compiles clean.
- Render probes across both seeded accounts: super admin sees h1 header,
  all three workflow actions, 14/14 permitted scanner chips, recent
  widget with aria-busy table + fetch URL + id-link template; restricted
  account sees ONLY the honest empty-state fallback (zero actions,
  zero widget markup, zero widget script). Single-device guard behaves
  identically for both (probe satisfied it in-memory without writes).
- Feed contract probe against the live controller: valid DataTables
  envelope; row shape includes id/date_applied/program/client_name/
  status/suggested_amount. Local `tbl_transactions` currently holds 0
  rows, so the widget's empty state is the exercised path locally; the
  desc-by-created_at ordering uses the feed's own column map (col 20).
- Unmigrated screens unaffected (/clients smoke: Bootstrap cards +
  DataTables init intact); referenced build asset exists on disk;
  `git diff --name-only` shows no backend paths; prototype/index.html
  diff unchanged from pre-existing state.

---

### 2026-08-24 - Batch E (part 1): clients registry — index + create/edit wrappers

Implements the clients portion of §8.9 Group 3 / §8.10 Batch E. Scope this
session: `clients/index.blade.php` (full migration) plus the trivial
`create`/`edit` wrappers. The CRUD partials (`_form`, `_details`, `_gip`,
`show`) are inspected but deliberately deferred (see "Remaining Batch E
work"). Zero backend changes; Bootstrap and frozen ui.css untouched.

**Functional contract preserved (verified, not assumed)**

- Server-side DataTables feed `POST clients.data`: same URL, same filter
  payload injection (`d.municipality`/`d.barangay`), same columns map,
  columnDefs visibility set, order [0 asc], pageLength 25 / lengthMenu,
  createdRow data-id hook. One presentation-only init addition:
  `autoWidth: false` so column widths come from CSS instead of computed
  inline styles — this is what allows dropping the old
  `width:170px !important` hacks without layout drift.
- Municipality→barangay cascade via `geography.barangays`, Filter/Reset
  buttons, row-click → details panel: byte-same logic.
- Details panel fetch contract unchanged: `clients.show?id&?panel=1` with
  `X-Requested-With` header, spinner placeholder, innerHTML swap +
  `executeScripts()` re-execution of fetched scripts.
- Row actions (View/Edit/Delete incl. native `confirm()` on delete) are
  built server-side inside `ClientController::data()` — UNTOUCHED per the
  no-backend rule. Consequence documented below for the confirm-modal
  helper.
- ACL: page middleware `page:clients.php` governs screen access
  (probe: restricted account still denied); feed scoping enforced in the
  controller exactly as before.
- Flash channel: `session('success')` alert converted to the SAME toast
  pattern as the layout (aria-live container, role="status",
  manual-dismiss BS Toast) — same session key, controllers untouched.

**Migration details**

- Header: adopts breadcrumbs (Dashboard › Clients) + page-header partial;
  Remove Duplicates → `btn-subtle`, Add Client → `btn-gold`.
- Filters: `field-label` labels; selects keep Bootstrap `form-select`
  (ui.css already tokenizes their focus ring; mixing with self-contained
  `.field-control` would fight the native caret styling).
- Table: keeps the DataTables-Bootstrap integration classes it needs
  (`.table .table-sm`), drops decorative `table-striped/bordered/table-dark`
  in favour of a token skin SCOPED under `#clients-screen` in the view's
  own `<style>` block (navy header, subtle striping/hover, gold
  focus-visible outline for rows, DT length/filter/pagination chrome).
  Scoped selectors cannot leak to other DataTables screens. The old
  screen-local styles lost both `!important` declarations.
- P1-5 keyboard rows: `createdRow` adds `tabindex=0` + aria-label;
  delegated Enter/Space handler mirrors the click handler (same
  actions-column guard). Horizontal scroll container is keyboard-focusable
  (`tabindex=0`) for a11y.
- create/edit wrappers: header partials adopted around the untouched
  `_form` include inside a data-card shell.

**Collision-control catches during build**

- Tailwind v4 dynamic spacing generated a REAL `.w-100` utility from the
  table's legacy `w-100` class, which would have overridden Bootstrap's
  `width:100%` at cascade — removed; `style="width:100%"` (what DT reads)
  remains.
- The scanner extracted `p-5`/`mt-3` from JS template strings (panel
  loading/error HTML) — converted to bracket longhands; my `gap-3`
  usages converted to `gap-[12px]`. Final source-level gate over all
  three migrated files: zero forbidden utilities. Bundle-level note:
  `.gap-3`/`.gap-4` remain present from earlier APPROVED batches
  (dashboard quick-actions grid, page-header slot row) — not touched by
  this batch.

**Verification**

- Build: Preflight absent; zero `!important`; Batch E utilities emitted
  (btn-navy/btn-subtle/btn-gold/field-label/max-lg/lg-grid-template/
  sm:grid-cols-2/gap-[12px]); bundle ~31 kB.
- Suite after changes: **213 passed / 1056 assertions**; Pint passed;
  view:cache compiles clean across ALL views including deferred ones.
- Routed probe matrix (super admin): 20/20 contract markers present
  (header/crumbs/card shell/filters/table/DT css+init/feed URL/autoWidth/
  keyboard rows/keydown/panel contract/offcanvas/scoped skin/no-
  important/CTAs); success-flash renders toast with message + manual
  dismiss and no legacy alert; create page renders new shell with store
  form action intact (proves `_form` include works inside wrapper);
  restricted account denied; unmigrated `/transactions` smoke unaffected.
  Edit/show probes skipped locally (tbl_clients has 0 rows) — compile
  coverage only.
- git diff scope: only the three client views + app.css @source block;
  backend paths empty; prototype/index.html diff is solely the
  pre-existing intentional 3-line change.

**Confirm-modal helper decision**: NOT created this session. The only
confirmation interaction on this screen lives in controller-generated HTML
(`onsubmit="return confirm(...)"` in the actions cell), which rule 1 makes
untouchable view-side. Creating an unconsumable shared helper would violate
the no-dead-shared-JS rule. It stays deferred until transactions (Batch F)
where the confirm point is view-side, or until a batch explicitly approved
to touch controller output strings.

**Remaining Batch E work** (next session): migrate `clients/_form.blade.php`
(field vocabulary sweep preserving all names/IDs/conditionals),
`_details.blade.php` (profile reskin, panel+full-page modes),
`_gip.blade.php`, and the `show.blade.php` wrapper; then households/duplicates/
family-members if the batch is continued per Group 3 grouping.

---

## 2026-08-23 — UI/UX Batch E (part 2) — clients CRUD partials migrated

**Scope**: presentation-only migration of the four remaining registry views —
`clients/_details.blade.php`, `clients/_gip.blade.php`, `clients/_form.blade.php`,
`clients/show.blade.php` — plus four `@source` lines in `resources/css/app.css`.
No controller/service/model/route/config/schema/test changes.

**Functional contracts preserved (verified by probe)**

- `_details` dual mode: full page (`clients.show`) renders breadcrumbs + h1;
  the index offcanvas variant (`?panel=1`) renders h2 + "Open full page" with
  no wrapper chrome. The inline `<script>` stays inline because
  `executeScripts()` re-executes it on panel load; photo-modal IDs and JS-
  coupled `d-none` toggles untouched; native `confirm()` on delete kept.
- `_form`: every field name / `old()` binding / `@selected` logic /
  validation error target unchanged; script-coupled ids intact
  (municipality, barangay, birthdate, age, category, ipSelect, ipGroupDiv,
  aff-org-wrapper); `input.uppercase` casing hook kept (Tailwind's generated
  `.uppercase` is consistent with it); `#ipGroupDiv` server-rendered
  `d-none` conditional + JS toggle kept verbatim.
- `_gip`: Bootstrap accordion (`data-bs-parent` collapse wiring) and modal
  are FUNCTIONAL chrome — structure preserved; only inner content layout
  moved to tokens. FontAwesome `<i>` icons replaced with the established
  inline-stroke-SVG style (FA was never loaded in the layout).
- Route parity gate: `route()` name sets extracted from HEAD vs working
  tree — identical for all three partials; `show` adds only breadcrumb
  routes (dashboard, clients.index). All names confirmed present in
  `route:list`.

**Migration details**

- `_details`: data-card shell; button vocabulary mapping — Back/Open-full-
  page/Photo → `btn-subtle`, Add Transaction → `btn-navy` (ACL-gated via the
  same inline `canAccessPage` call), Edit → `btn-gold`, Delete → `btn-red`.
  Profile fields became a definition grid (`ui-micro-label` captions);
  household/family/transactions tables are Tailwind-first static tables;
  transaction status cells upgraded to `.status-badge is-paid/is-pending/
  is-neutral` (plan-sanctioned badge cells). The captured-preview image's
  legacy `w-100` was swapped to `w-full` BEFORE scanning (the E1 collision
  lesson applied pre-emptively).
- `_gip`: info rows → two-column definition grid; multi-line values use
  `whitespace-pre-line` instead of `nl2br` so `{{ }}` escaping still applies
  (no raw `{!! !!}` output introduced); "missing GIP record" alert →
  `.ui-notice`; Edit GIP → `btn-navy`, Add GIP → `btn-gold`.
- `_form`: row/col scaffolding → responsive grids with `gap-[12px]`/
  `gap-y-[12px]`; labels → `field-label` with new `for`/`id` pairs (ids not
  used by JS were free to add — a11y pass); error `<small class="text-
  danger">` → `<small class="field-error">`; footer Cancel/Save →
  `btn-subtle`/`btn-navy`. The aff-org stack switched from per-select
  `mb-2` to wrapper `gap-[8px]`, and the dynamic `addOrgField()` className
  string was synced to `'form-select'` in the SAME edit so static and
  JS-created selects match exactly.
- `show.blade.php`: breadcrumbs (Dashboard › Clients › Client Profile) +
  unchanged `_details` include; no page-header duplication since `_details`
  carries its own heading in both modes.
- app.css: Batch E block extended from 3 to 7 `@source` entries (show,
  _details, _gip, _form added).

**Verification**

- Source-level gate over all four files: zero forbidden utilities
  (spacing steps 3–5, gap-3/4/5, w-50/w-100, h-100, container, shadow-lg),
  zero `!important`.
- Bundle: Preflight absent; `!important` count 0; no `.w-100/.h-100/
  .container/.shadow-lg` generated; `.gap-3`/`.gap-4` remain ONLY from the
  earlier approved batches (dashboard/page-header) — unchanged provenance.
- Build clean (~33 kB css); `view:cache` compiles all views; suite:
  **213 passed / 1056 assertions**; Pint passed.
- Routed probes: create page 200 with all 11 contract markers (field-label,
  for/id pair, uppercase hook, ipGroupDiv d-none default, aff-org gap
  stack, JS className sync, geography fetch URL, btn vocabulary) and zero
  legacy scaffolding remnants. Full-page show rendered via in-memory
  (unsaved) models — read-only, no DB writes: all three GIP branches
  verified (no-GIP → nothing; GIP-txn-without-record → ui-notice + Add CTA
  + accordion wiring; record present → detail grid + whitespace-pre-line +
  edit modal + escaped multiline value), status badges emitted
  (`is-pending`), camera JS + native confirm + ACL-gated Add Transaction
  present, zero `w-100` hits across the whole page output. Panel mode:
  h2 swap, Open-full-page link, Back link absent, ACL gating live.
  Edit/panel HTTP probes remain skipped (tbl_clients empty locally) —
  covered by the direct-render equivalents above.
- git diff scope: only the four client views + app.css @source block this
  session; backend paths empty; prototype diff remains solely the
  pre-existing intentional 3-line change.

**Batch E is now complete**: Group 3's households/duplicates/family-members
screens were NOT part of the approved Batch E scope and remain for a future
batch decision. Next up per plan: **Batch F — transactions module**
(confirm-modal helper becomes viable there: its confirm points are
view-side).

---

## 2026-08-23 — UI/UX Batch F — transactions module migrated

**Scope**: presentation-only migration of the transactions screens —
`transactions/index.blade.php`, `transactions/create.blade.php`,
`transactions/edit.blade.php`, `transactions/show.blade.php` — plus a new
shared confirm-dialog partial (`partials/confirm-modal.blade.php`), five
`@source` lines in `resources/css/app.css`, and one bug fix in the shared
page-header partial. No controller/service/model/route/config/schema/test
changes.

**Defect caught and fixed (shared partial)**

- `partials/page-header.blade.php` echoed the `$actions` slot with `{{ }}`
  (escaped) — header action buttons had been rendering as literal HTML text
  since Batch B adoption; earlier string-probes missed it because
  `str_contains` matches inside entities. Fixed to `{!!actions !!}` with a
  contract comment (callers pass view-authored markup). Regression probe:
  clients index header buttons now emit real anchors, zero `&lt;a`
  residue anywhere on the page.

**Functional contracts preserved (verified by probes + line-diff)**

- Index feed: DataTables `serverSide` POST to `transactions.data` with all
  8 filter params; 21 columns; `columnDefs targets:20`; `order [[4,'asc']]`;
  pageLength/lengthMenu untouched. Feed envelope re-checked live
  (draw/recordsTotal/recordsFiltered/data keys).
- Inline-edit protocol byte-preserved except sanctioned presentation deltas:
  same td indices, same payload `{id,remarks,comments,suggested_amount,
  status,amount_paid,date_paid,gwa,units}`, same fetch headers
  (`X-CSRF-TOKEN`), same row-only update (`table.row($row).data(rowData).
  draw(false)`) and cancel reload path.
- Export URL builder: `URLSearchParams` key set IDENTICAL (9 keys =
  `export_mode` + 8 filters); dropdown moved into page-header actions,
  `.export-link[data-mode=csv|custom|custom2|gip]` unchanged.
- Filter form: `#filtersForm` GET interception → draw(); municipality→
  barangay cascade fetch + restore-on-load trigger intact.
- Delete flows swapped to the planned confirm dialog (the plan's "delete
  confirm swap"): index JS now calls `window.uiConfirm({...}).then(ok => …)`
  around the SAME fetch; show's form uses declarative `data-confirm`.
- Forms (create/edit): beneficiary radio trio + disabled juggling,
  `transactions.clients-search` live search ids, TUPAD toggle script
  (create only), `$isSelf/$isCustom` prechecks with `@checked/@disabled`,
  `_method` spoof — all logic lines byte-identical vs HEAD (line-diff gate;
  only breadcrumb arrays and class/markup changed).
- Show: definition-grid restyle of the same data; client link, destroy
  action, csrf, `$fmt` date format unchanged.

**Migration details**

- New `partials/confirm-modal.blade.php`: `window.uiConfirm({title,message,
  confirmLabel}) → Promise<boolean>` plus declarative `<form data-confirm>`
  interception via a document-level submit listener; confirmation submits
  through `HTMLFormElement.prototype.submit.call(form)` so no synthetic
  submit event fires (no loop, @csrf POST preserved); Cancel/Esc/backdrop
  resolve false (abort semantics equal to native confirm()). First real
  consumers are exactly these two transaction delete flows.
- Index: scoped `#transactions-screen` DataTables skin (no `!important`;
  `autoWidth:false` replaces width hacks; `style="width:100%"` replaces
  `w-100`); applied-filter chips per plan §5.4/§6.2 (aria-live region,
  removal URLs strip exactly one param via `collect(request()->query())->
  except()`; municipality/barangay labels resolve from models and fall back
  to the raw value when unresolvable — e.g. deep links against an empty
  reference table); toast flash replaces the Bootstrap alert; status column
  renders `.status-badge is-paid/is-pending/is-neutral` (rowData stays raw);
  money columns get `num-cell` tabular figures.
- create/edit/show: breadcrumbs + page-header pattern, data-card shell,
  field-label/for-id pairs, error ui-notice, btn vocabulary (Save/Update
  `btn-navy`, Edit `btn-gold`, Delete `btn-red`, Cancel `btn-subtle`);
  `#search_results` popover width moved from forbidden `w-50` to
  `style="width:min(24rem,100%)"`.

**Verification**

- Source-level gate over the 5 files: zero forbidden utilities, zero
  `!important` (one comment mentioning the literal string was reworded so
  automated sweeps stay clean).
- Bundle: Preflight absent; `!important`=0; no `.w-100/.h-100/.container/
  .shadow-lg`; `.gap-3/.gap-4` provenance unchanged (approved batches);
  build clean (css 34.59 kB); `view:cache` OK; suite **213 passed /
  1056 assertions**; Pint passed.
- Routed probes (jordi): index 200 with 22/22 contract markers incl. real
  export-dropdown markup after the escaping fix; chips page renders
  program/status/date chips + correct single-param removal URLs + cascade
  restore trigger; feed envelope keys present; restricted account (jiro)
  still 302-denied. Direct renders (unsaved models, read-only): create
  (14/14 markers), edit, show (status badge, ₱ formatting, data-confirm,
  destroy route). Local tbl_transactions/tbl_clients/municipalities are
  empty → routed edit/show/feed-row probes vacuous, covered by direct
  renders + line-diff parity instead.
- git diff scope: only the four transaction views + confirm-modal +
  app.css @source block + page-header fix this session; backend paths
  empty; prototype diff remains solely the pre-existing intentional change.

**Next up per plan**: Batch G (scholars dependency fix first, then scholars
module) — NOT started.

---

## 2026-08-24 — UI/UX Batch G — all remaining unmigrated views migrated

**Scope**: presentation-only migration of every remaining unmigrated view
per UI_UX_ANALYSIS §8.9/§8.10 — scholars module (4), scanner (1), payout
attendance + unpaid-verification index (2), admin users (2) + permission
matrix screens (5), online sessions + audit logs + scholarship reports +
update logs (4), public standalone heads (7: login, student verify/
update-photo/photo-upload, QR viewer, unpaid self-service, grantee
self-update), and Group 3 remainder (households index/create/show,
family-member create, duplicates index). Plus `@source` allowlist growth in
`resources/css/app.css` and **one runtime defect fix** (P1-4). No
controller/service/model/route/config/schema changes.

**Defect caught and fixed (P1-4 pattern)**

- `scholars/index.blade.php` had never pushed the jQuery/DataTables bundles
  its script depends on (the view predates the shared layout scripts push)
  and carried a stray `});` — the screen was dead on arrival. Both fixed:
  the standard bundle trio is now pushed and the orphan removed; scoped
  `#scholars-screen` DataTables skin added per the established recipe.

**Sanctioned script deltas only (everything else byte-preserved)**

- Native `confirm()` → `window.uiConfirm()` (partials/confirm-modal include):
  payout/unpaid attendance-row deletes (success path now silently reloads
  like Batch E transactions), duplicates bulk-delete form submit handler
  (zero-selection alert kept), force-logout via declarative `form[data-confirm]`,
  super-admin/all-municipalities toggles and confirmAll with revert-on-decline.
- Render-string button classes → shared vocabulary (`btn-subtle` view/edit,
  `btn-outline-red` delete, `btn-gold` select).
- Success `alert()` on AJAX paths → silent reload; error `alert()` paths
  untouched. Pre-existing native confirms inside frozen public-page scripts
  (grantee bypass, photo modal) stay byte-preserved.
- Scanner screen verified by git-diff grep: `<script>` content unchanged.

**Migration details**

- Standard screens adopt breadcrumbs + page-header + data-card shells,
  field-label/for-id pairs, token-based red error panels (`.ui-notice` has no
  variants), persistent toast for session success flashes where the pattern
  existed, scoped per-screen DataTables skins (`autoWidth:false`,
  `style="width:100%"`, navy th / zebra / hover, no `!important`), and the
  btn vocabulary footers (btn-subtle Cancel / btn-navy submit).
- Config-driven `payouts/attendance.blade.php` keeps its `$config[]`
  contract (title/scanner_route/scanner_label/seat_table/modal_title/
  programs) driving routes `payout-attendance.{variant}.data`.
- Public standalone heads keep their own `<head>` (never layout-converted)
  but pull the built CSS via `@vite(['resources/css/app.css'])` + `ui.css`;
  Bootstrap CDN remains loaded so JS-injected Bootstrap-class markup still
  renders. Camera-capture, QR decode (Decision C comma-form payload),
  self-service search flows byte-preserved.
- Permission matrix screens share a `.matrix-table` skin under their own
  screen ids; audit logs keep client-side mode + 5s auto-reload +
  leaderboard POST modal (a mistaken serverSide/processing addition was
  caught and reverted to HEAD parity); update logs use the GET filter
  `form="logsFilter"` attribute pattern; scholarship reports keep server-side
  feed + geography cascade + CSV redirect.
- Households index: actions column is controller-rendered and untouched;
  households create/family-member create keep their debounced client-search
  contracts byte-preserved (result popovers restyled via tokens, forbidden
  `w-50`/`w-100` replaced with inline widths); household show becomes a two-
  panel read-only composition with status badges.

**Verification**

- Bundle: build clean (app css 38.56 kB); Preflight absent; zero
  `!important`; `view:cache` OK then cleared.
- Suite: **213 passed / 1056 assertions** (first run without
  `C:\xampp\mysql\bin` on PATH reproduced the known ProcessFailedException —
  rerun with PATH set, all green).
- Pint passed (`--dirty`). Hazard greps clean: no `w-100`/`w-50` utilities,
  no stray native `confirm()` outside sanctioned/pre-existing spots,
  confirm-modal included wherever `uiConfirm()` is called.
- Line-diff audits vs HEAD on frozen-script files (scanner, QR viewer, both
  self-services, photo upload): script blocks unchanged except sanctioned
  swaps.

**UI/UX migration track status**: ALL views now use the shared design
vocabulary. Remaining track work: final coexistence/cutover decisions
(Bootstrap retirement pass), not part of this batch.

---

*End of current implementation log. Append new dated entries above this line.*

---

## 2026-08-27 — Phase 2 Pre-Implementation Inspection Complete

**Scope:** Complete read-only inspection of the codebase per `docs/PHASE_2_PLAN.md` §16.1 step 1. No code modified, no routes added, no tests changed.

**What was inspected:**

1. **PHASE_2_PLAN.md** — fully read (1,282 lines); all 26 required sections mapped
2. **Prototype** — `PROTOTYPE_SPEC.md`, `index.html` (949+ lines), `app.js` (3,828 lines), `style.css` (1,496+ lines); filter system architecture, dashboard widgets, notification pattern, responsive behavior
3. **DashboardController** — 13 lines, single `index()` method, passes zero data to view; all assembly done in Blade via ACL checks at render time
4. **Dashboard view** — `dashboard.blade.php` (267 lines); ACL bootstrapping, quick actions grid (3 actions, ACL-gated), scanner shortcuts, recent transactions table (5 rows via AJAX), grid layout
5. **All 24 controllers** — inventoried; key data feed methods found in ClientController (`data()`), HouseholdController (`data()`), TransactionController (`data()`), ScholarController (`data()`), DuplicateController (`data()`), ScholarshipReportController (`data()`)
6. **AccessControlService** — 307 lines; municipality-scoped ACL with `canAccessPage()`, `canPerformAction()`, `getPermittedMunicipalities()`, `getMunicipalityScope()`, `getBarangayScope()`, `applyMunicipalityScope()`
7. **All 30+ Blade views** — inventoried; layouts, auth, dashboard, clients, households, transactions, scholars, GIP, duplicates, reports, administration, settings
8. **DetailsPanel.js** — 241 lines; `load(module, entityId, options)` AJAX pattern, panel markup contract, search overlay, focus trap — **stable, no changes recommended**
9. **Per-module list views** — Clients, Households, Transactions, Scholars all use identical DataTables server-side POST pattern with inline `<script>` blocks
10. **Existing filter implementations** — Two competing models identified: AJAX apply+redraw (most screens) vs GET deep-link (transactions only)
11. **`active-filters.blade.php`** — 30-line stub, unused by any screen; designed for filter chips adoption
12. **`global-search.blade.php`** — 55 lines, client-only, no cross-module search
13. **`navbar.blade.php`** — Bell icon stub with hidden badge "0", no dropdown, no JS
14. **`public/css/ui.css`** — 499 lines, full design token system matching prototype
15. **v1 codebase** — `C:\xampp\htdocs\system\index.php` has ZERO dashboard metrics (only scanner cards); all Phase 2 dashboard KPIs are net-new
16. **Test suite** — 212 passed, 1 risky (HouseholdTest output-buffering), 1,056 assertions; 24 test files, 112 test methods
17. **Routes** — all routes listed; DataTables data feeds exist for clients/households/transactions/scholars/duplicates/scholarship reports

**Key findings:**

- Phase 2 is achievable within the current architecture without database changes, new frameworks, or破坏性 refactoring
- The v1 dashboard has ZERO metrics — all KPIs, program distribution, announcements, calendar, and activity feed are net-new features
- FilterChips must be implemented as a new shared JS component; each module has its own inline filter implementation today
- The existing `active-filters.blade.php` stub is designed for adoption but currently unused
- Two competing filter paradigms exist (AJAX redraw vs GET deep-link) that need unification
- 5 decision clusters identified in §23 requiring owner input before implementation

**Verification:**
- Suite: **212 passed / 1056 assertions** (unchanged — no code modified)
- Pint: not run (no code modified)
- `docs/PHASE_2_INSPECTION_REPORT.md` created with all 26 required sections
- `docs/SESSION_HANDOFF.md` updated to reflect Phase 2 inspection status

**Files created:** `docs/PHASE_2_INSPECTION_REPORT.md`
**Files modified:** `docs/SESSION_HANDOFF.md` (status + priorities + before-next-session + doc table)

---

## 2026-08-27 — Phase 2 Pre-Implementation Confirmation

**Scope:** Formal pre-implementation confirmation per user authorization. Read-only analysis — no code modified.

**What was completed:**

1. **v1 KPI inspection** — Inspected `index.php`, `fetch_clients.php`, `fetch_transactions.php`, scanner action files, `add_transaction.php`, `edit_transaction.php`, `all_transactions.php` to determine actual v1 business rules for all 8 dashboard KPI concepts. Key finding: v1 has ZERO dashboard metrics — all KPIs are net-new presentations of existing data.

2. **v1 filter semantics** — Inspected all 10 v1 data-fetching endpoints to document exact filter fields, SQL conditions, cascade behavior, and execution model (server-side vs client-side). Key findings: all filters are server-side except Audit Logs (client-side); municipality→barangay cascade is consistent; date range semantics differ between modules.

3. **v1 municipality scope inspection** — Inspected `restriction.php`, `tbl_users` schema, all `fetch_*.php` endpoints, `login.php`, `session.php`. Key finding: v1 has ZERO municipality scope enforcement. Municipality filter is purely UI convenience. v2's `applyMunicipalityScope()` is a net-new security enhancement.

4. **KPI Business Definitions** — Created `docs/implementation/PHASE_2_KPI_DEFINITIONS.md` documenting each KPI's definition, source table/field, status conditions, scope behavior, v1 provenance, and any ambiguity.

5. **Pre-Implementation Confirmation** — Created `docs/implementation/PHASE_2_PRE_IMPLEMENTATION_CONFIRMATION.md` with: files inspected, KPI definitions, filter semantics, scope behavior, resolved ambiguities, implementation sequence, deferred items confirmation, and no-schema-changes confirmation.

**Key decisions confirmed:**
- Disbursed amount = `SUM(amount_paid) WHERE status = 'PAID'` (Option A)
- Pending = `COUNT WHERE status = 'PENDING PAYOUT'`
- All KPIs respect municipality scope and program permissions
- Activity feed uses real audit data, permission-gated
- Calendar DEFERRED
- Notifications DEFERRED
- No database schema changes

**Verification:**
- Suite: **212 passed / 1056 assertions** (unchanged — no code modified)
- Pint: not run (no code modified)
- `docs/implementation/PHASE_2_KPI_DEFINITIONS.md` created
- `docs/implementation/PHASE_2_PRE_IMPLEMENTATION_CONFIRMATION.md` created
- `docs/SESSION_HANDOFF.md` updated (status + priorities + before-next-session + doc table)

**Files created:** `docs/implementation/PHASE_2_KPI_DEFINITIONS.md`, `docs/implementation/PHASE_2_PRE_IMPLEMENTATION_CONFIRMATION.md`
**Files modified:** `docs/SESSION_HANDOFF.md`

---

### 2026-08-27 — Phase 2A Dashboard implementation (KPI cards, program distribution, activity feed)

**Scope:** DashboardController expanded with KPI queries + dashboard view restructured to match prototype layout. Added 13 new DashboardTest tests.

**Changes:**

1. **`app/Http/Controllers/DashboardController.php`** — Injected `AccessControlService`. Added `scopedQuery()` and `scopedTransactionQuery()` helper methods that enforce municipality scope (`applyMunicipalityScope`) and program permissions (`permittedPrograms`) on all KPI queries. Added: `totalClients` (COUNT via clients.php scope), `totalTransactions` (COUNT via all_transactions.php scope + program filter), `disbursedAmount` (SUM amount_paid WHERE status=PAID), `pendingCount` (COUNT WHERE status='PENDING PAYOUT'), `programDistribution` (GROUP BY program ordered by count DESC), `activityFeed` (8 most recent audit_logs joined with tbl_users, gated by audit_logs.php permission).

2. **`resources/views/dashboard.blade.php`** — Full restructure to match prototype layout. Preserved: all ACL-gated quick actions (Add Client/Register Household/New Transaction/scanner shortcuts), recent transactions AJAX feed from existing `transactions.data` endpoint (same municipality+program scope enforcement), `<noscript>` fallback. Added: 4 KPI metric cards (Total Clients, Total Transactions, Disbursed Amount, Pending Approvals) with icon/top-accent/value/label pattern matching prototype `.metric-card`; two-column grid with program distribution bars (color-cycled, width-scaled to max) and recent transactions widget; activity feed section (avatar initial circles with color cycle, human-readable action labels, relative timestamps via Carbon, audit_logs.php permission gate). All CSS uses `--ui-*` design tokens and existing component classes from `app.css` (`.metric-card`, `.data-card`, `.status-badge`, `.btn-subtle`).

3. **`tests/Feature/DashboardTest.php`** — 13 tests covering: KPI card rendering (data + zero state), program distribution (grouped + empty state), activity feed (permitted + non-permitted user), quick actions (permitted + no permission), recent transactions widget (permitted + non-permitted user), municipality scope enforcement on KPIs, program permission filtering on KPIs, authentication requirement.

**Key decisions:**
- `disbursedAmount` = `SUM(amount_paid) WHERE status = 'PAID'` per v1 `fetch_transactions.php` field and KPI doc Option A
- `pendingCount` = `COUNT(*) WHERE status = 'PENDING PAYOUT'` (only two statuses exist in v1/v2)
- Activity feed: audit_logs.php permission gate, limit 8, human-readable action labels, relative timestamps
- Program distribution: color-cycled bars matching prototype palette (navy/gold/teal/blue/amber/red)
- No database schema changes

**Verification:**
- Suite: **225 passed / 1097 assertions** (13 new tests added)
- Risky: 1 (same `HouseholdTest::test_households_pages_load_for_permitted_user` — output buffering)
- Pint: clean
- Production `main_system`: untouched (tests use `main_system_test`)

**Files created:** `tests/Feature/DashboardTest.php`
**Files modified:** `app/Http/Controllers/DashboardController.php`, `resources/views/dashboard.blade.php`, `docs/SESSION_HANDOFF.md`

---

### 2026-08-27 — Phase 2B Global Search (autocomplete dropdown in topbar)

**Scope:** Prototype-style global search with server-side autocomplete endpoint, dropdown results, keyboard navigation, and preserved existing client DataTables search behavior.

**Changes:**

1. **`app/Http/controllers/ClientController.php`** — Added `globalSearch(Requestrequest): JsonResponse` method. Reuses same word-split AND search semantics as `data()` feed (firstname, lastname, middlename, extensionname, full_name, mobile_no, municipality, barangay). Municipality scope enforced via `applyMunicipalityScope('clients.php')`. Page access gated by `canAccessPage('clients.php')`. Min 2 chars required, max 8 results. Smart ranking (prefix matches rank first). Returns id, full_name, age, sex, municipality, barangay, and url (clients.show route) for each result.

2. **`routes/web.php`** — Added `GET /global-search` route in the `auth`+`single-device` group (NOT inside `page:clients.php` — controller checks page access server-side, allowing the search input to live in the topbar on any page).

3. **`resources/views/partials/global-search.blade.php`** — Full rewrite of the stub. Replaced simple DataTables filter with an autocomplete dropdown. The input now has `role="combobox"`, `aria-autocomplete="list"`, `aria-controls="globalSearchDropdown"`. Dropdown container uses `position:absolute` below the input with `--ui-*` design tokens. JavaScript: debounced fetch (250ms), renders results as option rows (avatar initials, name, age/sex, location), loading/empty/error states. Keyboard navigation: ArrowUp/ArrowDown cycle results, Enter navigates to highlighted result or falls back to `/clients?search=query`, Escape closes dropdown + blurs input. Outside-click closes dropdown. Also preserves existing DataTables filter when on `/clients` page. Clicking a result navigates to `clients.show`.

4. **`tests/Feature/GlobalSearchTest.php`** — 14 tests covering: authentication required, matching clients returned, short query returns empty, empty query returns empty, no-match returns empty, result limit (8), firstname match, municipality name match, word-split AND behavior, smart ranking (prefix first), unauthorized user returns empty, municipality scope enforcement, result includes show URL, dashboard renders search input.

**Search architecture:**
- Endpoint: `GET /global-search?q={query}`
- Auth: `auth` + `single-device` middleware
- Scope: `applyMunicipalityScope('clients.php')` — S2-gated (respects enforcement flag)
- Authorization: `canAccessPage(user, 'clients.php')` checked server-side
- Search semantics: word-split AND, same fields as DataTables `data()` feed minus low-value columns (precinct, region, province, occupation, etc.)
- Ranking: prefix match → lastname → full_name → municipality → barangay → other
- Limit: 8 results

**Verification:**
- Suite: **239 passed / 1137 assertions** (14 new tests added)
- Risky: 1 (same `HouseholdTest::test_households_pages_load_for_permitted_user` — output buffering)
- Pint: clean
- Views: cache compiled successfully
- Production `main_system`: untouched (tests use `main_system_test`)

**Files created:** `tests/Feature/GlobalSearchTest.php`
**Files modified:** `app/Http/Controllers/ClientController.php`, `routes/web.php`, `resources/views/partials/global-search.blade.php`, `docs/SESSION_HANDOFF.md`

---

### 2026-08-27 — Phase 2B Remediation: App Shell / Topbar Prototype Alignment

**Problem:** After Phase 2B (Global Search), the topbar used `fixed inset-x-0
top-0` spanning the full viewport width with a dark (`bg-dark`) background,
overlapping the sidebar. The sidebar had `lg:top-16` to sit below it. The
prototype specifies the opposite: sidebar goes top-to-bottom owning branding,
topbar is a sticky white bar INSIDE the content column only.

**Root cause:** The navbar was a `position: fixed` full-width bar overlaying
everything, forcing the sidebar to offset downward. The prototype uses a
sidebar-left + content-column pattern where the topbar lives inside the content
area.

**Structural changes (3 files):**

1. **`resources/views/partials/navbar.blade.php`** — restructured:
   - Changed from `fixed inset-x-0 top-0 z-40 bg-dark` to `sticky top-0
     z-40 bg-white border-b`
   - Removed logo/branding from navbar (sidebar owns branding)
   - Breadcrumb: `2DMIS › Page Name` in muted ink tones
   - Notifications button: light icon style matching white topbar
   - User dropdown: light text/button style; initials avatar on mobile,
     full "Welcome, username" on sm+
   - Hamburger button: light icon style, hidden on lg+ (desktop sidebar
     always visible)

2. **`resources/views/partials/sidebar.blade.php`** — class change:
   - Removed `lg:fixed lg:top-16 lg:bottom-0 lg:left-0 lg:z-30`
   - Added `lg:static lg:bottom-auto lg:left-auto lg:z-auto lg:h-screen
     lg:shrink-0`
   - On desktop (≥1024px): static flow element, full viewport height,
     fixed 260px width
   - On mobile (<1024px): Bootstrap offcanvas overlay (unchanged)

3. **`resources/views/layouts/app.blade.php`** — two changes:
   - `<main>` removed `pt-16` (sticky topbar is in flow, no top padding
     needed); kept `lg:ml-[260px]` (pushes content right of sidebar)
   - Toast positioning: `top-[76px]` → `top-20` (80px, below 64px sticky
     topbar)

**Prototype measurements matched:**
- Sidebar: 260px, full height, navy gradient, owns seal + "2D MIS" branding
- Topbar: 64px, white background, `border-bottom: 1px solid var(--border)`,
  inside `.main-content` only
- Responsive: sidebar off-canvas on <1024px, hamburger toggle on tablet/mobile

**Verification:**
- Suite: **239 passed / 1137 assertions** (no new tests — purely structural
  CSS/markup change)
- Risky: 1 (same pre-existing `HouseholdTest` output buffer)
- Pint: clean
- Views: cache compiled successfully
- Production `main_system`: untouched

**Files modified:** `resources/views/partials/navbar.blade.php`,
`resources/views/partials/sidebar.blade.php`,
`resources/views/layouts/app.blade.php`, `docs/SESSION_HANDOFF.md`,
`docs/IMPLEMENTATION_LOG.md`

---

### 2026-08-27 — Phase 2B Remediation v2 (App Shell Structural Reconciliation)

**Context:** The first-pass Remediation (v1) changed class attributes on
navbar/sidebar/main but left the DOM order wrong — the navbar `<nav>` was
rendered OUTSIDE `<main>` as a full-viewport-width sticky element. The sidebar
used `lg:static` (flow element) instead of `lg:fixed`. This meant the topbar
overlapped the sidebar area on desktop, and the sidebar scrolled with the page
instead of being fixed top-to-bottom. Visual inspection against the prototype
confirmed these structural mismatches.

**Root cause:** DOM order was navbar → sidebar → main (navbar is a direct
child of `<body>`, not inside `<main>`). The prototype requires sidebar
(fixed) and main as siblings, with the topbar INSIDE main (after the
260px margin-left).

**Changes:**

1. **`resources/views/layouts/app.blade.php`** — three changes:
   - Moved `@include('partials.navbar')` from before the sidebar include to
     INSIDE `<main>` as its first child. This ensures the navbar is contained
     within the `lg:ml-[260px]` content area and does not span full viewport.
   - Changed page content padding from `px-6 py-6` (24px) to `px-7 py-7`
     (28px) to match the prototype's `.page-container { padding: 28px }`.
   - Toast positioning unchanged (`top-20` = 80px, below 64px topbar).

2. **`resources/views/partials/sidebar.blade.php`** — one class change:
   - `lg:static lg:bottom-auto lg:left-auto lg:z-auto lg:h-screen ... lg:shrink-0`
     → `lg:fixed lg:top-0 lg:bottom-0 lg:left-0 lg:z-[100] lg:flex lg:w-[260px] lg:flex-col`
   - The sidebar is now `position: fixed` on desktop (≥1024px), matching the
     prototype exactly: `position: fixed; top: 0; left: 0; bottom: 0; width: 260px; z-index: 100`.
   - On mobile (<1024px): Bootstrap offcanvas behavior unchanged.

3. **`resources/views/partials/navbar.blade.php`** — two attribute changes:
   - `z-40` → `z-50` (matching prototype `z-index: 50` for topbar)
   - `px-4 sm:px-6` → `px-7` (matching prototype `padding: 0 28px`)
   - `gap-3` → `gap-4` (matching prototype `gap: 16px`)

**Structural result (DOM order matches prototype):**
```
<body>
  <aside class="lg:fixed lg:top-0 lg:bottom-0 lg:left-0 lg:z-[100] lg:w-[260px]">  ← fixed sidebar
  <div>TOAST</div>  ← fixed, top-20, z-[1100]
  <main class="lg:ml-[260px]">
    <nav class="sticky top-0 z-50 h-16 bg-white px-7">  ← navbar INSIDE main only
    <div class="px-7 py-7">
      @yield('content')
    </div>
  </main>
</body>
```

**Prototype measurements matched:**
- Sidebar: 260px, fixed top-to-bottom, navy gradient, owns seal + "2D MIS"
- Topbar: 64px, sticky, white, z-50, inside main-content only (after 260px margin)
- Page container: 28px padding
- Responsive: sidebar off-canvas on <1024px, hamburger toggle on tablet/mobile
- Search: functional, 320px width, hidden below sm breakpoint

**Verification:**
- Suite: **240 passed / 1137 assertions** (no new tests — structural CSS/markup)
- Risky: 1 (same pre-existing `HouseholdTest` output buffer)
- Pint: clean
- Production `main_system`: untouched

**Files modified:** `resources/views/layouts/app.blade.php`,
`resources/views/partials/sidebar.blade.php`,
`resources/views/partials/navbar.blade.php`,
`docs/SESSION_HANDOFF.md`, `docs/IMPLEMENTATION_LOG.md`

---

### 2026-08-27 — Phase 2B Remediation v3 (App Shell Spacing & Sidebar Polish)

**Context:** Remediation v2 fixed the major structural issue (navbar inside
main, sidebar fixed). Visual inspection revealed remaining spacing, overflow,
and geometry issues: sidebar content clipped by Bootstrap offcanvas `overflow:
hidden`, body background color mismatch, sidebar internal padding not matching
prototype, and page content padding not responsive on mobile.

**Root causes identified:**

1. **Sidebar clipping**: Bootstrap's `.offcanvas` base class applies
   `overflow: hidden` at all screen sizes. Our `lg:fixed` override positions
   the sidebar correctly but doesn't override this overflow, so nav content
   is clipped when it exceeds the viewport height.

2. **Sidebar width conflict**: Bootstrap's `.offcanvas` sets `width:
   var(--bs-offcanvas-width)` (280px from our CSS variable). Tailwind's
   `lg:w-[260px]` competes at equal specificity; `lg:w-[260px]!` (Tailwind v4
   trailing-`!` important syntax) with `!important` ensures the prototype's
   260px wins unambiguously.

3. **Body background**: `bg-[#f8f9fa]` (Bootstrap gray) vs prototype
   `--bg: #F0F2F5`. Near-equivalent but visually distinct — content area
   looks denser against the lighter background.

4. **Sidebar internal padding**: Brand padding was `px-5 py-5` (20px each)
   vs prototype `padding: 20px 22px`; nav section was `px-3 py-5` (12px/20px)
   vs prototype `padding: 16px 12px`; footer was `px-5 py-4` vs prototype
   `padding: 16px`; section margin was `mb-7` (28px) vs prototype 24px.

5. **Page content padding**: `px-7 py-7` (28px) at all sizes. Prototype
   mobile uses `padding: 20px 16px`.

**Changes:**

1. **`resources/views/partials/sidebar.blade.php`** — five changes:
   - Added `lg:w-[260px]!` to override Bootstrap's offcanvas width
   - Added `lg:overflow-visible` to override Bootstrap's `overflow: hidden`
   - Brand padding: `px-[1.25rem] py-[1.25rem]` → `px-[1.375rem] py-5`
     (22px × 20px, matching prototype `padding: 20px 22px`)
   - Nav section padding: `px-[0.75rem] py-[1.25rem]` → `px-3 py-4`
     (12px × 16px, matching prototype `padding: 16px 12px`)
   - Footer padding: `px-[1.25rem] py-[1rem]` → `px-3 py-3` (12px × 12px)
   - Section margin: `mb-7` → `mb-6` (24px, matching prototype)

2. **`resources/views/layouts/app.blade.php`** — two changes:
   - Body background: `bg-[#f8f9fa]` → `bg-[#F0F2F5]` (matching prototype
     `--bg: #F0F2F5`)
   - Page content padding: `px-7 py-7` → `px-5 py-5 sm:px-7 sm:py-7`
     (20px mobile → 28px desktop, matching prototype responsive padding)

**Prototype measurements matched:**
- Sidebar: 260px, `overflow: visible` (desktop), brand 22×20px, nav 12×16px,
  footer 12×12px, sections 24px apart
- Body: `#F0F2F5` background
- Page container: 20px mobile, 28px desktop
- Topbar: 28px horizontal padding, 16px gaps, 64px height, z-50

**Verification:**
- Suite: **240 passed / 1137 assertions** (no new tests — CSS/markup only)
- Risky: 1 (same pre-existing `HouseholdTest` output buffer)
- Pint: clean
- Production `main_system`: untouched

**Files modified:** `resources/views/partials/sidebar.blade.php`,
`resources/views/layouts/app.blade.php`,
`docs/SESSION_HANDOFF.md`, `docs/IMPLEMENTATION_LOG.md`

---

### 2026-08-27 — Global Typography Applied (Prototype Baseline)

**Context:** Apply the prototype's typography baseline to the app shell before
Phase 2C. Prototype loads Inter (300–800) + Outfit (400–800) via Google Fonts
and sets root 14px, Inter body/line-height 1.6 with font smoothing, Outfit
headings with 600 weight / 1.3 line-height / -0.01em letter-spacing.

**Changes:**

1. **`resources/views/layouts/app.blade.php`** — replaced the Roboto Google
   Fonts link with the prototype's exact load:
   `Inter:wght@300;400;500;600;700;800` + `Outfit:wght@400;500;600;700;800`
   (`display=swap`). Single import — no duplication.

2. **`resources/css/app.css`** — two changes:
   - `@theme` tokens flipped (documented token-flip): `--font-body` →
     `'Inter', system-ui, -apple-system, sans-serif`; `--font-heading` →
     `'Outfit', 'Inter', system-ui, sans-serif`. `font-body`/`font-heading`
     utilities now emit these stacks automatically.
   - Added a **Global typography** block after `@theme static`:
     `html { font-size: 14px; scroll-behavior: smooth }`,
     `body { font-family: Inter…; line-height: 1.6; font-smoothing;
     overflow-x: hidden }`, and
     `h1–h6 { font-family: Outfit…; font-weight: 600; line-height: 1.3;
     letter-spacing: -0.01em }`. Written UNLAYERED by design: Bootstrap
     Reboot is unlayered, and layered rules lose to unlayered at equal
     specificity — a layered base block would have been inert. Source order
     (app.css after the Bootstrap CDN link) wins the element-selector ties.

3. **`public/css/ui.css`** — shared `--ui-font-body`/`--ui-font-heading`
   tokens flipped to the same Inter/Outfit stacks (they feed the existing
   inert `body`/`h1–h6` element rules and `.metric-value`), so the frozen
   compatibility layer stays consistent and Roboto references are removed.

**Scope notes:**
- Standalone public layouts (`auth/login`, `students/*`, `qr/viewer`,
  `grantee_update/self-service`, `unpaid_verifications/self-service`) do NOT
  use the shell layout or app.css — left on Roboto to avoid unnecessary
  changes. They import Roboto independently (no shared global import).
- `html { font-size: 14px }` shifts every rem-based size (Tailwind spacing,
  Bootstrap `1rem` body font) ~12.5% smaller — this is the prototype's
  deliberate 14px baseline. The sidebar width/paddings are px-based and
  unaffected.
- Migrated headings carrying explicit `font-bold` (e.g. page-header h1)
  keep 700 weight; the new global 600 rule is the fallback baseline for
  headings without an explicit weight utility.

**Verification:**
- Build: `npm run build` clean; verified generated CSS contains
  `html{scroll-behavior:smooth;font-size:14px}`,
  `body{…font-family:Inter,…;line-height:1.6;overflow-x:hidden}`,
  `h1,h2,…{letter-spacing:-.01em;font-family:Outfit,Inter,…;font-weight:600;line-height:1.3}`,
  plus `font-body`/`font-heading` utilities with Inter/Outfit stacks.
- Confirmed Bootstrap 5.3.2 does NOT set `font-size` on `:root` (only
  `--bs-body-font-size: 1rem`), so the 14px root takes effect.
- Suite: **240 passed / 1137 assertions**; Risky: 1 (same pre-existing
  `HouseholdTest` output buffer).
- Pint: passed. Production `main_system`: untouched.

**Files modified:** `resources/views/layouts/app.blade.php`,
`resources/css/app.css`, `public/css/ui.css`, `docs/SESSION_HANDOFF.md`,
`docs/IMPLEMENTATION_LOG.md`

---

### 2026-08-27 — Phase 2C FilterChips (shared multi-value filter component across 7 modules)

**Context:** Replace each module's bespoke server-side filter controls with one
shared `FilterChips` component (JS + Blade partial) sitting on top of the
existing server-side DataTables feeds. Final user decisions (see
`docs/PHASE_2C_INSPECTION_REPORT.md`):
- Clients get Municipality + Barangay chips only; Scholars/GIP and Users stay
  search-only; Audit Logs keeps its client-side user/action/date filtering
  model with FilterChips acting as the UI/state layer.
- URL persistence via query string; refresh/deep-link restores state; chip
  removal updates the URL and reloads the feed; DataTables search/ordering/
  pagination/endpoint contracts are preserved.
- Municipality→Barangay cascade with sanitization (drop barangay selections
  whose parent municipality is unselected).
- One chip per date range, preserving each module's existing backend param
  names. Scholarship `submitted` may appear as a chip; the feed ignores it
  (documented v1 behavior) while export honors it.
- Semantics: multiple values in one category = OR (comma-split `whereIn`),
  different categories = AND; single value keeps `where` (backward compatible).
- Search stays a separate field (NOT a chip), composing AND, preserving the
  server-side DataTables search.

**Changes:**

1. **`resources/js/components/FilterChips.js`** — new shared ES6 component
   exposing `FilterChips.init({ host, config, onApply }) -> { getParams(),
   clearAll(), setOptions(key, options) }`. Reads `data-filter-host` +
   `data-filter-config` JSON from the partial, hydrates chips from the URL
   query string, supports multi-value checkboxes, municipality→barangay
   `dependsOn` cascade, date range chips, searchable popover menus, Clear All,
   and chip removal that rewrites the URL and reloads the feed. `setOptions` (used
   by Audit Logs) rebuilds an option list while preserving selections that still
   exist. Delegate-based change handling.

2. **`resources/views/partials/filter-chips.blade.php`** — new shared partial:
   renders the toolbar, the `data-filter-host`/`data-filter-config` JSON
   contract, category popover menus, and `data-filter-check` chips. Consumes
   `$filterChips` from each controller.

3. **`app/Support/FilterConfig.php`** — new support class with static builders
   (`geography()`, `municipalityCategory()`, `barangayCategory()`,
   `programCategory()`, `dateRange()`, `selectedValues()`) and
   `applyMultiValue()` that applies OR (`whereIn`) for comma-separated values
   and `where` for a single value, preserving backward compatibility. Feed still
   applies ACL scope before any user filter so a hostile param cannot widen the
   dataset.

4. **`app/Services/AccessControlService.php`** — added
   `accessibleMunicipalities(Useruser)` returning the ACL-scoped Municipality
   list, used by the municipality-scoped modules (Clients, Households,
   Transactions).

5. **`resources/css/app.css`** — appended the full FilterChips component CSS
   block (`.filter-chips`, `.filter-group-toggle`, `.filter-count-badge`,
   `.filter-clear-all`, `.filter-multi-*`, `.filter-check*`, `.filter-date-*`,
   `.filter-done-btn`, responsive bottom-sheet media query, `.sr-only` helper)
   and added `@source "../views/partials/filter-chips.blade.php";` to the
   Tailwind allowlist. Media-query chip padding uses literal values (not
   `@apply`) to respect coexistence rules.

6. **`vite.config.js`** — `copySharedComponents()` plugin copies
   `resources/js/components/*` → `public/js/components/*` on `closeBundle` so
   `DetailsPanel.js` and `FilterChips.js` are served as static assets (the 404
   delivery gap is fixed).

7. **Modules integrated** (controller + view + feed wiring):
   - **Clients** — `ClientController::index` builds `$filterChips`
     (`clients-filters`); `data()` uses `applyMultiValue` for municipality/
     barangay; view toolbar replaced with the partial + separate search field +
     Reset.
   - **Households** — `HouseholdController` analogous (partial + Reset; no
     search field existed).
   - **Transactions** — `TransactionController` builds program/status/
     municipality/barangay + 2 date ranges; `data()` rewritten with comma-split
     params preserving `whereForbidden` ACL at the existing lines; export
     (`applyExportFilters`) left single-select (unchanged GET deep-link
     contract).
   - **Scholarship Reports** — `ReportController::scholarship` builds
     municipality/barangay/program + `submitted` (feed-ignored, export-honored)
     + date range; `buildFeedQuery` uses `applyMultiValue`.
   - **Payouts** — `PayoutAttendanceController::index` (config-driven variants)
     builds municipality + program + scanned date range; NO barangay chip (feed
     has no barangay param); `data()` uses `applyMultiValue`.
   - **Unpaid Verifications** — `UnpaidVerificationController::index` builds
     municipality + date range; `buildFeedQuery` uses `applyMultiValue`.
   - **Audit Logs** — `AuditController::index` passes user/action categories
     (options fed by `setOptions` from the ajax response) + min/max date range;
     view keeps its client-side filtering model; `onApply` builds OR regex
     column searches; Reset → `clearAll()`.

**Scope note (ACL option parity):** Option lists are ACL-scoped **only where the
feed enforces that scope** (Clients, Households, Transactions). Scholarship,
Payouts, and Unpaid feeds are NOT municipality-scoped (pre-existing v1
behavior), so their option lists stay unscoped too — otherwise a restricted user
would see zero municipality options while the feed still returns all rows. This
matches the existing seniority of each feed.

**Verification:**
- `php -l` clean on all changed controllers + `FilterConfig`; `php artisan
  view:cache` compiles all Blade (including the new partials).
- `node --check resources/js/components/FilterChips.js` passes.
- `npm run build` clean; confirmed `public/js/components/{DetailsPanel,FilterChips}.js`
  are emitted and the compiled CSS contains the `.filter-multi-menu`,
  `.filter-group-toggle`, `.filter-count-badge` classes.
- `vendor/bin/pint --test`: passed (with `pint` style fixes applied).
- Suite: **239 passed / 1137 assertions** (1 pre-existing risky), re-run green
  after the scholarship option-parity fix (restricted user now sees the full
  municipality list again).
- Production `main_system`: untouched (additive code only).

**Files modified:** `resources/js/components/FilterChips.js`,
`resources/views/partials/filter-chips.blade.php`, `app/Support/FilterConfig.php`,
`app/Services/AccessControlService.php`, `resources/css/app.css`,
`vite.config.js`, `app/Http/Controllers/{ClientController,HouseholdController,
TransactionController,ReportController,PayoutAttendanceController,
UnpaidVerificationController,AuditController}.php`,
`resources/views/{clients,households,unpaid_verifications}/index.blade.php`,
`resources/views/transactions/index.blade.php`,
`resources/views/scholarship_reports/index.blade.php`,
`resources/views/payouts/attendance.blade.php`,
`resources/views/admin/audit_logs/index.blade.php`,
`docs/SESSION_HANDOFF.md`, `docs/IMPLEMENTATION_LOG.md`

---

### 2026-08-27 — Phase 2C FilterChips Test Suite

**Context:** Add dedicated tests for the shared FilterChips / Phase 2C behavior
after the 7-module integration (see previous entry). Tests only — no production
code, schema, or ACL behavior changed.

**Changes:** created `tests/Feature/FilterChipsTest.php` (24 tests / 90
assertions) covering the shared component's server-side contracts:

1. **Multi-select OR within a category** — Clients `municipality=A,B`
   (`whereIn`) returns the union; Transactions `program=AICS,AKAP` same.
2. **AND across categories** — Clients `municipality` AND `barangay`;
   Transactions `program` AND `date_applied` range.
3. **URL persistence / deep-link restore** — selected chips render `checked`
   from the query string (whitespace-tolerant regex because the Blade
   `@if($checked) checked @endif` emits `checked` on its own line).
4. **Chip removal** — dropping a param removes only that chip from active state.
5. **Clear All** — no query => `data-filter-count` hidden and no checked chip.
6. **Municipality → Barangay cascade** — barangay category emits
   `data-filter-depends="municipality"` + per-option `data-filter-muni`.
7. **ACL parity** —
   - scoped modules (Clients/Households/Transactions): municipality options are
     limited to the user's `tbl_user_municipalities` grant via
     `accessibleMunicipalities`;
   - with `authorization.pages` enforcement flipped on, the Clients feed applies
     the scope **before** the user municipality filter, so an out-of-scope
     hostile `municipality` param cannot widen the set;
   - non-scoped modules (Scholarship/Payouts/Unpaid): options stay **unscoped**
     (all municipalities), matching their v1 feed behavior.
8. **Program permission filtering** — Transactions options limited to permitted
   programs and the feed returns 0 for an unpermitted program request
   (`whereForbidden`).
9. **Backward compatibility** — single-value filters keep `where` (Count), and
   `FilterConfig::applyMultiValue` unit test: single → `where`, multi → `whereIn`,
   empty → no-op.
10. **Zero-result filters** — Clients/Transactions feeds return `recordsFiltered = 0`
    and an empty `data` array instead of erroring.
11. **Transactions 8-param compatibility** — deep link restores all eight filter
    chips (program/status/municipality/barangay/2× date range) and the feed
    honors status+program+date together.
12. **Audit Logs client-side model** — index emits the `user`/`action` categories
    (empty options, fed client-side) + `minDate`/`maxDate` range; the feed returns
    `{data, users, actions}` so the client populates the FilterChips options.

**Verification:** suite on `main_system_test` = **263 passed / 1227 assertions**
(24 new tests / 90 assertions added; 1 pre-existing risky `HouseholdTest`);
`vendor/bin/pint --test` passed (pint fixed unused-import in the new file);
production `main_system` untouched.

**Files modified:** `tests/Feature/FilterChipsTest.php`,
`docs/SESSION_HANDOFF.md`, `docs/IMPLEMENTATION_LOG.md`

### 2026-08-27 — Phase 2C FilterChips positioning remediation (`position: relative`)

**Context:** Real-browser verification (Playwright, chromium) of the Phase 2C
FilterChips popover exposed a layout defect that the automated suite could not
catch: the absolutely-positioned `.filter-multi-menu` had **no positioned
containing block**, so it anchored to the initial containing block (body) and
landed ~886 px below the Filter button — off-screen (`insideViewport: false`)
on all 7 integrated modules identically. Root cause: `.filter-chips` lacked a
positioned ancestor.

**Fix (minimal, per task directives):** added `position: relative;` to
`.filter-chips` in `resources/css/app.css` (rule now
`flex-direction:column;gap:.75rem;display:flex;position:relative`) and rebuilt
via `npm run build` → `vite build` (compiled output hash changed from
`app-D4ktEQ_q.css` to `app-BOwsAegm.css`; manifest updated). No change to
FilterChips architecture, JS behavior, backend contracts, ACL logic, or visual
design; the fixed-position fallback was deliberately **not** implemented.

**Verification (real browser, both widths):**
- **Desktop (1280×900):** popover `nearestPositionedAncestor: "filter-chips"`,
  `position: absolute`, `deltaY = 6px` below the button, `deltaX = 0` aligned
  to button left, **`insideViewport: true`**; selecting a municipality emitted
  the chip "Municipality: Alilem" + count badge "1" + unhidden Clear All;
  Escape closes; **zero console errors**.
- **Mobile (390×844):** popover `position: fixed` (bottom sheet), **`insideViewport:
  true`**; same full interaction works (chip / count / Clear All / Escape);
  **zero console errors**.
- Full suite on `main_system_test` = **263 passed / 1227 assertions** (1
  pre-existing risky `HouseholdTest`); `vendor/bin/pint --test` passed
  (CSS-only change); production `main_system` untouched.

**Files modified:** `resources/css/app.css`,
`public/build/assets/app-BOwsAegm.css`,
`public/build/manifest.json` (rebuilt assets),
`docs/IMPLEMENTATION_LOG.md`, `docs/SESSION_HANDOFF.md`

### 2026-08-28 — Phase 2D Responsive Polish (D1–D6 + two defects fixed)

**Context:** Phase 2D = real-browser (Playwright chromium) responsive
verification of the modernized shell across 9 widths (1440×900, 1280×900,
1024×768, 768×1024, 576×900, 430×932, 390×844, 375×812, 320×568). Approved
decisions: **D1** = DataTables `scrollX: true` for wide tables (esp.
Transactions), preserving server-side DataTables + `columns().adjust()` (no
DataTables Responsive extension, no card redesign, no column hiding); **D2** =
mobile-only touch-target tier < 768px (no global 44px), per prototype sizing;
**D3** = fix the Audit Logs SyntaxError first; **D5** = single 768px tier
(< 768px mobile, ≥ 768px tablet/desktop), keep the Bootstrap 992px sidebar
breakpoint; **D6** = smallest doc reconciliation only.

**D3 — Audit Logs SyntaxError fixed:** `resources/views/admin/audit_logs/
index.blade.php` had a duplicated `return json.data; }` block (working-tree
regression from Batch G; `git show HEAD` had only one occurrence), throwing a
SyntaxError that prevented DataTables upgrade + FilterChips init. The duplicate
was removed; the rendered page now shows an upgraded DataTable with FilterChips
init at every tested width.

**D5 — 768px breakpoint reconciled:** changed the DetailsPanel mobile media
query in `resources/views/partials/details-panel.blade.php` from
`max-width: 767px` to `max-width: 767.98px` (tablet regime stays
`min-width: 768px and max-width: 1023px`), making it identical to the
FilterChips `767.98px` tier — one 768px tier project-wide.

**D1 — Table overflow strategy:** added `scrollX: true` to the Transactions
DataTables init (`resources/views/transactions/index.blade.php`, right after
`autoWidth: false`) for its 21-column table, keeping the `.table-responsive`/
`overflow-x-auto` wrappers. Existing `scrollX` tables (scholarship reports,
payout attendance, unpaid verifications, scholars reports tab) left unchanged.
Added a cross-cutting `columns().adjust()` hook in `resources/views/layouts/
app.blade.php` inline script: a guarded `adjustDataTables()` plus offcanvas
`shown/hidden.bs.offcanvas` listeners and a DetailsPanel `transitionend`
(transform) → `requestAnimationFrame(adjustDataTables)` — the real case for
`columns().adjust()` is the vertical scrollbar appearing from scroll-lock.

**D2 + FilterChips mobile polish (`resources/css/app.css`):** rewrote the
`@media (max-width: 767.98px)` block as a floating bottom-sheet
(`left:12px; right:12px; bottom:12px; max-height:70vh; border-radius:1rem`,
`.filter-multi{position:static}`) and applied the mobile touch-target tier:
`.filter-group-toggle` min-height 40px; `.filter-done-btn` 38px;
`.filter-clear-all`/`.filter-clear-cat` 36px; `.filter-multi-search`/
`.filter-date-input` 40px + font-size 16px (prevents iOS zoom);
`.filter-check` min-height 40px + checkbox 18px; `.filter-chip` min-height
36px (merged duplicate rule); `.filter-chip-remove` 32px hit area (icon kept
0.75rem); `.dataTables_paginate .page-link` 36px; `.dataTables_filter input`/
`.dataTables_length select` min-height 36px; `.actions-col .btn` 36×36.
DetailsPanel close target bumped to 36×36 (`.details-close`) in the base rule
(applies across breakpoints). `.filter-chips{position:relative}` was already
added in the Phase 2C remediation.

**Defect fixed — Blade `@stack('scripts')`-in-comment corruption:** a Phase-2D
comment that literally contained `@stack('scripts')` inside the layout's inline
`<script>` `//` comment caused Blade to emit every screen's pushed scripts into
the middle of the layout script, corrupting the HTML and **doubling** the
DetailsPanel / FilterChips / DataTables / jQuery loads on screens that push
scripts (e.g. audit_logs). Browser console showed "Identifier 'DetailsPanel' has
already been declared", "FilterChips already declared", "jQuery is not defined".
Diagnosed with a throwaway render test (now deleted) showing DetailsPanel.js ×2,
FilterChips.js ×2, jquery.dataTables ×2, jquery-3.7.1 ×2. Fix: rewrote the
comment to avoid the literal directive in `resources/views/layouts/app.blade.php`
(now the only `@stack('scripts')` is the real one at line 159, rendered at layout
end after the layout inline script, which is jQuery-guarded). Re-verified: each
pushed script ×1, `<script src` total 6, `audit-screen` id ×1.

**Verification (real browser, Playwright chromium at http://127.0.0.1:8000):**
- Audit screen at 1440×900, 768×1024, 430×932, 320×568 — `errors: []` (zero
  console/page errors), `pageOverflow: 0`, DataTables upgraded present,
  `auditFiltersInit: true`, filter popover `absolute` on desktop/tablet vs
  `fixed` (bottom sheet) on mobile/xsmall, filter toggle 34px desktop → 40px
  mobile.
- Focused D1/D5 at 1280×900, 768×1024, 430×932, 320×568 — Transactions
  `hasScrollXBody: true`, `pageOverflow: 0`, `tableHorizScrollable: false`
  (data fits; scrollX is a safety net only). DetailsPanel widths 480px (desktop
  1280) / 384px = 50% (tablet 768) / 430px = 100% (mobile 430) / 320px = 100%
  (xsmall) — `visible: true`, `scrollLocked: true`, `rowClicked: true` at all
  widths. One benign 419 CSRF network error under fast multi-goto navigation
  (no JS exceptions; clean single-login runs report `errors: []`).

**Verification (suite/tooling):** full suite on `main_system_test` = **263
passed / 1227 assertions**, 1 pre-existing risky `HouseholdTest` (unchanged,
not modified) — identical to baseline, no regressions; `vendor/bin/pint --test`
passed; `npm run build` clean → compiled `public/build/assets/app-CSrJtVoO.css`
(48.49 kB, gzip 8.63 kB) + `app-DUr89oQr.js`, manifest updated; production
`main_system` untouched.

**Files modified:** `resources/views/layouts/app.blade.php`,
`resources/views/admin/audit_logs/index.blade.php`,
`resources/views/partials/details-panel.blade.php`,
`resources/views/transactions/index.blade.php`, `resources/css/app.css`,
`public/build/assets/app-CSrJtVoO.css`,
`public/build/manifest.json` (rebuilt assets),
`docs/IMPLEMENTATION_LOG.md`, `docs/SESSION_HANDOFF.md`

### 2026-08-28 — Phase 2E Tests & Final Verification (risky HouseholdTest resolved → 0 risky)

**Context:** Phase 2E = final test + verification phase. Approved-as-complete
Phase 2D left one pre-existing risky test: `Tests\Feature\HouseholdTest::
test_households_pages_load_for_permitted_user` (baseline 263 passed / 1227
assertions / 1 risky / Pint passed / build passed).

**Risky-test root cause:** PHPUnit 11 reported
`Test code or tested code did not close its own output buffers`. Diagnosed by
running the filtered test (same message) and reading `vendor/phpunit/phpunit/
src/Framework/TestCase.php:stopOutputBuffering()` — the risk is raised when
`ob_get_level()` at test end exceeds the level recorded at test start.

The cause is a genuine Blade defect in `resources/views/households/show.blade.php`:
`@section('content')` is opened inside `@if (!isPanel)` (line 44) but was
**never closed with `@endsection`**. Rendered full-page (`?panel` absent):
`startSection('content')` calls `ob_start()` and pushes `content` onto the
section stack; with no `stopSection()`, `ob_get_clean()` inside the Blade engine
(`PhpEngine::evaluatePath`) closed the answer's *section* buffer instead of the
engine's own buffer, leaving one output buffer open after the render → PHPUnit
flags the test risky. (The `?panel=1` mode is unaffected: `$isPanel` is true so
`startSection` never executes — this is why only the full-page test was risky.)

A throwaway diagnostic render test (created then deleted) quantified the
secondary effect: the full-page body HTML was emitted with the content section
*before* the layout skeleton (`<nav class="breadcrumb">` at position 0,
`<html>` at 5183) because `sections['content']` was never populated and the
layout `@yield('content')` rendered empty while the unclosed section buffer
captured the panel markup + layout echo.

**Fix (minimal):** added `@endsection` before the `@endif` that closes the
`@if (!isPanel)` block in `resources/views/households/show.blade.php` — closing
the `content` section after the breadcrumbs + page-header includes. This is the
smallest legitimate correction: it makes the section syntactically complete, is
executed only in full-page mode (panel/DetailsPanel path byte-identical), and
does not change the test's purpose (GET the three Household pages and assert
each responds 200). No test file was modified — the existing assertions already
verified the page loads.

**Verification (suite/tooling):** `HouseholdTest.php` → **7 passed / 38
assertions / 0 risky**; full suite on `main_system_test` = **264 passed / 1227
assertions / 0 failures / 0 risky**; `vendor/bin/pint --test` passed;
`npm run build` clean (vite 6.4.3, `app-CSrJtVoO.css` + `app-DUr89oQr.js`,
manifest updated). Production `main_system` untouched. No unrelated tests were
modified. No browser run was repeated — the fix is Blade-selection-driven and
panel mode output is unchanged; existing Phase 2B/2C feature tests
(GlobalSearchTest, FilterChipsTest), DashboardTest, and HouseholdTest all pass.

**Pre-existing defect surfaced (NOT in scope, reported):** the household
full-page `show` route emits the `#household-show-screen` panel block *before*
the layout `<html>` skeleton (layout chrome renders after the content block)
because the unconditional panel markup sits outside the `content` section —
this was already the case before the fix and is a view-structure defect, not a
regression. The primary household interaction path (DetailsPanel via
`?panel=1`, used for row-click + keyboard open) is unaffected. Recommend a
follow-up owner-approved restructure (mirror the `clients.show` +
`clients._details` pattern) if the full-page route is to be used as a
standalone page.

**Phase 2 status:** **Phase 2 full track complete** — 2A Dashboard, 2B Global
Search + shell remediation + typography, 2C FilterChips + test suite + popover
positioning remediation, 2D Responsive Polish, and 2E final verification are
all done. **264 passed / 1227 assertions / 0 risky**, Pint clean, build clean.

**Files modified:** `resources/views/households/show.blade.php`
(`@endsection` added), `docs/IMPLEMENTATION_LOG.md`, `docs/SESSION_HANDOFF.md`.

### 2026-08-29 - P8 pre-flight decision pass + §H approvals + local flip/rollback rehearsal (prep only)

**Context:** Phase 2 full track closed (264 passed / 1227 assertions / 0 risky,
Pint clean, build clean). Per `SESSION_HANDOFF.md`, the next workstream is the
**P8 cutover execution** — gated on an owner-approved pass. This entry records
the READ-ONLY pre-flight, the §H owner approvals, and the local rehearsal; no
production action was taken.

**Pre-flight (READ-ONLY):** cross-checked `P8_DECISION_PACKAGE.md`,
`MIGRATION_PLANNING.md` (note: at `docs/MIGRATION_PLANNING.md`, not under
`docs/implementation/`), `MIGRATION_PLAN.md`, `SESSION_HANDOFF.md`,
`reconciliation_queries.sql`, `config/authorization.php`, `phpunit.xml`, `.env`.
Delivered the 7-part readiness assessment (readiness, owner decisions, owner
actions, required access, exact prerequisites, draft runbook, unexecuted
actions). Prerequisite classes: code READY; decisions now APPROVED; production
steps (migrate, §C.9 reconcile, bootstrap, grants, flips, staging rehearsal,
backup/restore-on-prod, rotation) ALL **NOT YET EXECUTED**; Hostinger SSH /
PHP 8.3+ **BLOCKED on owner action**.

**§H owner decisions — ALL APPROVED 2026-08-29:** scope A.1–A.4 confirmed /
A.5 deferred; rollout order §D (clients → household → all_transactions →
scholars → register, one page at a time); grant strategy §E default (full
non-VIEW catalog + ALL-municipality marker, tighten later); bootstrap model §F
`'*'` (username substituted at window); C.9-6 policy deferred until the
production count is known; P7 audit enhancements + denial auditing deferred;
ADR set aligned to `ARCHITECTURE_DECISION.md`; staging rehearsal still required
with local rehearsal fallback now performed.

**Sync fixes:** `P8_DECISION_PACKAGE.md` §G "Status today" column was stale —
the authoritative `ARCHITECTURE_DECISION.md` already records owner sign-off
2026-08-24 for ADR-001/002/003/004/005/008/009 (matching the package's own
BUILD RECORD); corrected the snapshot, changed no ADR. 007/010 stay Proposed,
006 Superseded. Also surfaced: §C.9-6 raw query compares `VARCHAR
city_municipality` to `INT municipalities.id` → false positives; the
reconciliation artifact carries the CAST-corrected version.

**Local flip/rollback rehearsal (on `main_system_test`):** threw away
`tests/Feature/TmpFlipRehearsalTest.php` that reads the **real config file**
(no in-memory override) with a precondition asserting the file's actual state.
With `config/authorization.php` flipped to `clients.php => 'enforcement' =>
true`: page-holder without action rows → create **denied** (dashboard redirect
+ `login_status=denied`, 0 clients); holder with `CREATE` row + ALL-municipality
marker → **granted**; `'*'` super-admin → **unaffected** (3 passed / 13
assertions). Reverted the file to `false` and re-ran: the ungranted holder was
**allowed again** — the S2 rollback contract (1 passed / 4 assertions).
`git diff config/authorization.php` after revert: **clean**. Temp test deleted.
Precondition lesson: page keys contain a dot (`clients.php`), so they must be
read with array access (`config('authorization.pages')['clients.php']`), never
dot-notation. `php artisan config:cache` was deliberately NOT exercised locally —
caching config would suppress the `phpunit.xml` `DB_DATABASE=main_system_test`
env override and would point tests at `main_system`; the §D step-4
config:cache re-run requirement remains a production-only step.

**Docs updated:** `docs/implementation/P8_DECISION_PACKAGE.md` (§G realigned +
"OWNER DECISIONS & PRE-FLIGHT — 2026-08-29" block), `docs/MIGRATION_PLANNING.md`
(§7.1 S2 per-page flip + production specifics — sentinel-seed-before-migrate,
CAST-corrected Q6, config:cache note, staging/local rehearsal), `docs/SESSION_HANDOFF.md`
(Current Work, Open Decisions, Before Next Session, Documentation Status).

**No production effects:** enforcement flags all `false`; pivot tables 0 rows;
no grants; production never connected to. Suite and Pint re-verified after all
edits.

**Files modified:** `docs/implementation/P8_DECISION_PACKAGE.md`,
`docs/MIGRATION_PLANNING.md`, `docs/SESSION_HANDOFF.md`,
`docs/IMPLEMENTATION_LOG.md`. (Rehearsal test created then deleted;
`config/authorization.php` flipped and reverted — net zero diff.)

### 2026-08-29 - P8 authority re-scoping: production DBA read-only handoff + responsibility matrix

**Context:** the developer is the application developer, NOT the production
system/DB owner — no Hostinger SSH access, no direct production DB access, no
authority to modify the production database. The P8 plan is re-scoped so every
production operation belongs to the production administrator/owner. No
production operation was performed in this pass.

**Super-admin (revised):** the intended account is the **existing production
super-admin account** — nothing is created, bootstrapped, or modified now; the
username is confirmed by the owner before any eventual production execution.
The §F bootstrap becomes conditional: only if Q3 reconciliation shows **0**
`'*'` holders today, and then it is DBA-executed from reviewed SQL the
developer supplies (never runs).

**Deliverable 1 — read-only DBA handoff (`docs/DBA_RECONCILIATION_HANDOFF.md`):**
a self-contained package for the production DBA/administrator containing ONLY
SELECT/read-only SQL (Q1–Q7 from `P8_DECISION_PACKAGE.md` §C.9, with the
CAST-corrected Q6), each query annotated with what it checks, the expected/
acceptable result, and which result requires an owner decision. Includes an
optional `START TRANSACTION READ ONLY` safety wrapper; no temp tables, no
writes, safe to copy/paste. Also documents what the DBA sends back (verbatim
output + read-only confirmation) and the owner-decision table (Q3=0 / Q3>1 /
Q6>0 / Q2 empty page / Q4=0 / unrecognised `page_name`).

**Deliverable 2 — responsibility matrix:** recorded in two places —
`P8_DECISION_PACKAGE.md` → "RESPONSIBILITY MATRIX & DBA HANDOFF — 2026-08-29"
(action-by-action table) and `MIGRATION_PLANNING.md` §7.2 (runbook-step ×
party). Developer owns: code/build/test verification, local rehearsal,
documentation, deployment package (written, never executed), smoke-test
procedure definition, analysis of DBA-provided results. Administrator/Owner
owns every production action: backup, reconciliation (Q1–Q7), migration,
grants, super-admin bootstrap (if needed), config-cache, enforcement flips,
rollback, and production server operations. Hostinger SSH / PHP 8.3+ / staging
are marked OWNER/ADMIN ACTION REQUIRED — no workaround attempted.

**Explicit non-actions:** no production database modified; no production
migrations, grants, INSERT/UPDATE/DELETE, or reconciliation queries executed
by the developer; no credentials, usernames, SSH access, or DB results
invented.

**Files modified:** `docs/DBA_RECONCILIATION_HANDOFF.md` (new),
`docs/reconciliation_queries.sql`, `docs/implementation/P8_DECISION_PACKAGE.md`,
`docs/MIGRATION_PLANNING.md`, `docs/SESSION_HANDOFF.md`,
`docs/IMPLEMENTATION_LOG.md`. No application code changed.

### 2026-08-30 — Pre-P8 Information Architecture consolidation (grouped sidebar + Scanner Engine/Payouts hubs + scholars Reports/Logs gating)

**Context:** owner-approved pre-P8 IA pass that consolidates the v1-sidebar
information architecture in the Tailwind shell without touching backend
behavior or the database. P8 cutover was NOT executed (remains production-
owner-gated per the 2026-08-29 authority re-scoping).

**What changed (presentation-only):**

- `resources/views/layouts/app.blade.php` — sidebar driven by the new
  `$shellSections` catalog: top-level Scanner Engine + Payouts hub items
  (aggregated page-gated), the Access Control group toggle (children gated
  per page key with orphan grouplabel suppression), and plain Scholar/
  Clients/etc. items. AICS dead link removed.
- `resources/views/partials/sidebar.blade.php` — two-pass group rendering:
  link visibility first, then grouplabels survive only when a visible link
  follows (no orphan section headers); `aria-expanded` reflects route-active
  groups via `routeIs`; hub links gated by aggregated `canAccessPage` checks
  over the `scanner.scanners` / `payout.attendance` config surfaces plus
  `unpaid_verifications.php` and the payout scanner keys (presentation never
  widens access).
- `resources/views/partials/sidebar-link.blade.php` + `sidebar-icon.blade.php`
  (new) — extracted icon set + declarative link partial shared by the loop,
  group toggle and hub cards.
- `resources/views/partials/navbar.blade.php` — breadcrumb label resolution
  learns the `scanners.index` / `payouts.index` hubs.
- `app/Http/Controllers/ScannerController.php` — `index` for `GET /scanners`
  (kind `scanner`, title `Scanner Engine`).
- `app/Http/Controllers/PayoutAttendanceController.php` — `landing` for
  `GET /payouts` (mixed attendance/unpaid/scanner destinations, empty state
  when none permitted).
- `routes/web.php` — `scanners.index`, `payouts.index` (+ existing
  per-destination routes untouched).
- `resources/views/scanners/index.blade.php`, `resources/views/payouts/index.blade.php`
  (new) — hub grids reusing `.data-card` (inline `@php($icon=…)` is safe
  here: no `@endphp` occurs later in either file).
- `resources/views/scholars/index.blade.php` — Scholarship Reports / Update
  Logs tabs gated on `scholarship_reports.php` / `update_logs.php` via the
  ACL service (route list showed no key merges/renames; AdminPermissionController
  P7 catalog untouched).
- `resources/css/app.css` — `.sidebar-section-label`, `.sidebar-group-toggle`
  (+ chevron rotate), `.sidebar-link` active bar retained.

**Blade render bug (root cause found):** the first targeted run failed with a
render-time PHP parse error (`unexpected token "endforeach"`) in the sidebar.
`BladeCompiler::storePhpBlocks` runs `/(?<!@)@php(.*?)@endphp/s` over the
whole template, so any **inline** `@php(...)` appearing *before* the next
`@endphp` in the same file is swallowed as a raw "PHP block" and excluded
from compilation — while `php artisan view:cache` still "passes" because it
never `require`s the compiled output. Fix: removed every inline `@php(…)`
from `sidebar.blade.php` (top block-mode `@php…@endphp` precomputes
`$scannerHubActive` / `$payoutHubActive`; link-active expressions inlined
into the `@include('partials.sidebar-link', [...])` args). Verified via
`php -l` on the recompiled view and `php artisan view:cache`.

**Tests:** extended `NavigationTest` (grouped access control, single-hub
link counts keyed on rendered `href`, fallback-link top-level visibility,
scholars-only negative case, `href="#"`/AICS removal, hub reachability), plus
`ScannerTest`/`PayoutTest`/`ScholarTest` hub and tab-gating coverage.
Assertions use substring counts on raw content and `assertSee` on hub card
`h2` text — `assertSee('>X<')` was silently escaping the needle, and the
sidebar link label renders on its own line after its inline icon, so
`>Label<` is never contiguous in the DOM.

**Verification:**

- `vendor\bin\pint --dirty` — passed.
- Targeted filter (`NavigationTest|ScannerTest|PayoutTest|ScholarTest`) —
  **68 passed / 369 assertions**.
- Full suite — **286 passed / 1336 assertions / 0 failures** (Duration
  72.15s) on `main_system_test`.
- `npm run build` — Vite clean; Tailwind emits every new class used by the
  hub grids/group toggles (`xl:grid-cols-3`, `md:grid-cols-2`,
  `gap-[1rem]`, `hover:-translate-y-0.5`, `hover:shadow-lift`,
  `hover:bg-surface-hover`, `.sidebar-group-toggle`).
- Route/permission/DB audit — `route:list` confirms both hubs wired and all
  existing per-destination routes intact; no permission key added/renamed
  (P7/P12 catalogs untouched); `git status` shows no `database/` /
  `config/authorization.php` changes; no migrations, no schema, no dumps.
- Playwright/Chromium smoke — `e2e/smoke.spec.ts` **4 passed** against the
  live dev server (login, grouped sidebar + Access Control toggle,
  `/scanners` hub, `/payouts` all six destination cards, `/scholars`). A
  temporary super-admin `smoke_superadmin` row (+ `tbl_permissions.*`) was
  inserted on local `main_system` for the smoke and **deleted afterward**
  (verified 0 rows left; auto-increment counter aside, the DB is restored).

**Explicit non-actions:** P8 cutover not executed; no production ops; no
database/schema/migration changes; `config/authorization.php` untouched;
`C:\xampp\htdocs\system` untouched.

**Files modified:** `resources/views/layouts/app.blade.php`,
`resources/views/partials/sidebar.blade.php`,
`resources/views/partials/sidebar-link.blade.php` (new),
`resources/views/partials/sidebar-icon.blade.php` (new),
`resources/views/partials/navbar.blade.php`, `resources/views/scanners/index.blade.php`
(new), `resources/views/payouts/index.blade.php` (new),
`resources/views/scholars/index.blade.php`, `app/Http/Controllers/ScannerController.php`,
`app/Http/Controllers/PayoutAttendanceController.php`, `routes/web.php`,
`resources/css/app.css`, `tests/Feature/NavigationTest.php`,
`tests/Feature/ScannerTest.php`, `tests/Feature/PayoutTest.php`,
`tests/Feature/ScholarTest.php`, `e2e/smoke.spec.ts` (new),
`docs/IMPLEMENTATION_LOG.md`, `docs/SESSION_HANDOFF.md`.

---

### 2026-08-31 — Comprehensive UI/UX & Interaction Architecture Audit (read-only)

- **Deliverable:** [`docs/UI_UX_AUDIT.md`](file:///c:/xampp/htdocs/2DMIS-v2/docs/UI_UX_AUDIT.md) —
  full 16-section audit (§A Executive Assessment → §O Exact File Impact) of the
  application's UI/UX and interaction architecture.
- **Mode:** strict read-only. No application code, view, CSS, JS, route,
  controller, service, permission, or database change was made; nothing
  committed; `C:\xampp\htdocs\system` untouched.
- **Scope covered (deep):** layout shell + shared partials (sidebar/topbar/
  details-panel/confirm-modal/filter-chips), Clients, Households, Transactions,
  Scholars, Scholar Reports, Scanner engine (config + hub + shell), Payouts
  (config + hub + 3 variants), Audit logs, Sessions, Update logs, `routes/web.php`
  (full), `DetailsPanel.js`, `FilterChips.js`, `app.css`, `public/css/ui.css`.
- **Confirmed presentation defects documented (status: static trace):**
  - Households row-click panel 404 — feed returns `household_id` ("HH-…") only;
    no numeric `id` for the `households/{household}` binding.
  - Payouts detail broken — row click hands the POST JSON feed to DetailsPanel
    (no matching `[data-panel-body]`), and the `payouts` default panel URL omits
    the `{variant}` segment.
  - Audit rows dead — feed rows carry no `id`; plus 5s re-fetch of the
    LIMIT 10000 feed.
  - Access Control group collapse + breadcrumb "Dashboard" on `admin.users.show`
    — route absent from the group's `is` sets in `$shellSections`.
- **Partially-confirmed / live-repro pending (documented as such):** the
  "Access Control opens then immediately closes" report (Bootstrap Collapse
  markup is correct; candidate is the offcanvas-lg + overflow combination) —
  requires a real authenticated session, deferred.
- **Verdict:** hold P8 cutover; run a presentation-only UX-1 Foundation
  program (repair the four wiring defects, close the interaction-model matrix),
  then the owner-approved consolidation gates (C1 confirm normalization,
  C2 column flattens) and the §M E2E gate, then re-review P8.
- **Verification:** none run — zero code changed; all findings are file-trace
  based and cross-checked against `routes/web.php`, feeds, and the shell
  partials. The audit's recommended P0 E2E specs (1–8) are listed in
  `docs/UI_UX_AUDIT.md` §M for implementation after owner approval.
- **Explicit non-actions:** no fixes implemented (awaiting owner approval of the
  §N consolidation gates), no tests run, no Playwright runs, no DB/schema/permission
  changes, no commits.

### 2026-08-31 — Presentation-only UX-1..UX-7 modernization (panel-first model) + C1/C2 gates

Owner approved all phases UX-1..UX-7 plus the four gated decisions (C1 confirm/
prompt normalization, C2 column flattens, edit-in-panel scope, audit polling →
manual refresh). Every change in this entry is **presentation/interaction only**:
no schema, migration, permission key, ACL, business rule, route-semantics or
`C:\xampp\htdocs\system` change. `main_system` remains byte-identical to v1.

**UX-1 — repair the three broken panel flows + audit polling + Access Control:**
- `HouseholdController::data()`: added `'id' => (int)row->id` to the feed so the
  households row-click panel no longer 404s.
- `PayoutAttendanceController` + `payouts/attendance.blade.php`: row-click/keydown
  now call `DetailsPanel.load('payouts', id, { url: showUrl + '?panel=1', method:'GET' })`
  (GET, not the POST feed), with a variant-aware default URL via
  `document.body.dataset.payoutVariant`.
- `DetailsPanel.js` (+ public copy): `load()` rejects `method !== 'GET'`; payout
  default URL is variant-aware.
- `AuditController::data()`: added `al.id` + `'id'` to the feed; audit list rows now
  open a GET `?panel=1` detail; the 5s `setInterval` polling of LIMIT 10000 was
  **replaced with a manual Refresh button** (owner-approved).
- `layouts/app.blade.php`: Access Control subgroup `is` set now includes
  `admin.users.show` (fixes group collapse + breadcrumb).

**UX-3 — C2 column flattens (client-side `columnDefs`, feed unchanged):**
- `transactions/index.blade.php`: hidden dense/non-editable columns
  `[2,5,6,7,8,9,15,19]` (client_id, date_applied, patient_name, mobile, barangay,
  municipality, type, payout_date, created_at) — kept searchable/sortable
  (server-side parity); inline-edit `td:eq(n)` columns stayed visible.
- `scholars/index.blade.php`: removed the orphaned `Actions` `<th>` (no matching
  column) so the 6-column header maps cleanly.
- `payouts/attendance.blade.php`: hid municipality + scanned_by (move to panel).

**UX-4 — Payouts unified tab workspace:** `payouts/index.blade.php` card grid →
Bootstrap-navigation tab bar, one tab per destination (attendance variants, unpaid
grantees, scanners). Tabs are the same ACL-filtered destinations; routes, variants,
permission keys and business rules untouched (never merged).

**UX-5 — Scanner unified workspace:** `scanners/index.blade.php` card grid → a
"Scanner type" `<select>` (fed by the ACL-filtered config) + badges + Open button
that navigates to the existing config-driven scan shell (`scanners.{key}`). All 14
configurations and per-key routes untouched; no wildcard route added.

**UX-2 — lightweight edit-in-panel (owner chose "Lighter panel-edit"):**
- **Additive `expectsJson()` branches** on the existing update controllers —
  browsers (no JSON accept) still get the exact prior redirect; zero behavior
  change for normal requests:
  - `ClientController::update()` → JSON `{success,message,id}` when `expectsJson()`.
  - `ScholarController::update()` → JSON `{success,message,id}`.
  - `UserController::resetPassword()` → JSON success, plus a JSON 422 for the
    super-admin guard (redirect path preserved).
- `DetailsPanel.js` (+ public copy): added `submitPanelForm(form)` — intercepts a
  panel-hosted form submit, POSTs via `fetch` with `Accept: application/json`, and
  on success reloads the current detail view; renders inline 422 field errors.
- `clients/_details.blade.php`: hidden compact edit form (name/mobile/email/
  occupation/income/civil status/sex; birthdate/address/identity preserved via
  hidden inputs) toggled by the `data-edit-client` button, submitted through
  `submitPanelForm`.
- `scholars/show.blade.php`: hidden compact edit form (school/campus/course/year/
  landbank/regular; client_id+program preserved), toggled by `data-edit-scholar`,
  submitted via `submitPanelForm`.
- `admin/users/show.blade.php`: **fixed a pre-existing crash** — a
  `route('admin.users.edit', ...)` reference to a non-existent route threw
  `RouteNotFoundException` so the users panel could not render at all; replaced
  with a working Edit button + compact password-reset panel-edit form (uses the
  now-JSON-capable `admin.users.reset-password`), toggled by `data-edit-user`.

**UX-6 — detail-route presence in `is` sets:** module groups use wildcard sets
(`clients.*`, `scholars.*`, …) so all show routes are covered; Access Control was
already fixed in UX-1. Also fixed the dead `openClientPanel(id)` reference in
`ClientController::data()` by defining the global in `clients/index.blade.php`
(now opens the details panel).

**UX-7 + C1 — confirm/prompt normalization + CSS audit:**
- Clients delete form: native `onsubmit="return confirm(...)"` → shared `uiConfirm`
  dialog (bound in the dynamic panel fragment; `uiConfirm` already global via the
  layout `confirm-modal` partial).
- Scholars Client-ID relink: native `prompt()` → a dedicated Bootstrap modal
  (`#clientIdPromptModal`) with the same AJAX backend (`scholars.update-client-id`).
- CSS dedupe: **audit-only** — `public/css/ui.css` remains load-bearing and was NOT
  retired (per handoff); no classes removed.

**Testing & verification:**
- Full PHPUnit suite: **286 tests / 1336 assertions / 0 failures**.
- `vendor/bin/pint` clean; `npm run build` clean; Blade view-cache clean.
- `DetailsPanel.js` `resources/` and `public/` copies verified byte-identical.
- Chromium Playwright smoke `e2e/smoke.spec.ts` — **4/4 passed** against the live
  dev server. The payouts-hub case was updated to assert the new tabbed workspace
  (`getByRole('tab', { name, exact:true })` + linked `href`) — the test's intent
  (every destination present + linked) is unchanged; this updates a test to match a
  legitimate UI change, not the reverse. A temporary `smoke_superadmin` row was
  created for the run and **deleted afterward** (verified 0 rows remain).

**Explicit non-actions:** no schema/migration/dump/permission/ACL/route-semantics/
business-rule changes; no commits made; `C:\xampp\htdocs\system` untouched; the
households edit-in-panel was intentionally NOT built (Households have no `update`
route today — would require a new business workflow, out of scope) — households
stay view-only. UX-7 full CSS dedupe + broader `uiConfirm` coverage (grantee
self-update native confirms) remain out of the approved C1 scope and are recorded
for a follow-up. Full cross-browser Playwright regression not run (reserved per
rules for explicit regression).

### 2026-08-31 — Clients details panel rework: sectioned profile + full-form panel editing

Follow-up within the approved UX-1..UX-7 program. The clients details panel
(`clients._details`) — the shared component's most-used consumer — was rebuilt
presentation/interaction-only: the profile became a sectioned token-vocabulary
layout and the **compact** UX-2 panel-edit form was superseded by the **full
shared `_form` reused inside the panel** (the `#clientPanelEdit` block is now a
thin wrapper: panel-form block + panel action bar + the `_form` include, per the
Batch E restructure plan). Panel-first editing is now complete-field editing
without leaving the panel.

**Files changed:**
- `resources/views/clients/_details.blade.php` — rewritten, dual-mode intact:
  - Profile split into labeled `<details-section>` grids (Personal Information /
    Address / Assistance / Contact), PWD/IP, Household, Family Members,
    Transactions (status cells → `.status-badge is-*`), then the shared `_gip`.
  - `data-panel-body/title/sub/meta/avatar/actions` extraction contract
    preserved verbatim (panel `?panel=1` + DetailsPanel.js); panel body keeps the
    `Client Profile` title so the existing panel-render test still holds.
  - Panel action bar gained **Edit** (toggles `#clientPanelEdit`) and **Delete**;
    both gated by `permittedActions($user, 'clients.php')` through the ACL service
    (no inline role checks). Full-page header keeps Back / Add Transaction /
    Photo / Edit link / Delete form, gated identically.
  - Photo modal + camera script: element ids (`photoModal`, `video`, `canvas`,
    `capturedPreview`, `startCameraBtn`, `captureBtn`, `retakeBtn`, `cameraImage`)
    and the `d-none` toggles byte-preserved; script stays inline inside the
    `data-panel-body` fragment (re-executed via DetailsPanel `executeScripts()`)
    and wrapped in an IIFE so re-parsing never redeclares bindings.
  - Delete → shared `uiConfirm` (Batch F): while the panel is open it submits as
    JSON (panel closes, `details:deleted` → index refresh + toast); otherwise the
    form submits natively so the full-page redirect + flash flow is unchanged.
  - The panel Edit toggle is bound on `#detailsActions [data-edit-client]`, which
    is also what the index's `[data-edit-client]` back-compat handler clicks from
    `onSuccess` (bindings run after `executeScripts`), so the row-Edit → panel-Edit
    seed path still opens the form.
- `resources/views/clients/_form.blade.php` — gained `$panel` mode: inline IIFE
  script (was `@push('scripts')` — pushed scripts would vanish inside the AJAX
  panel fetch) with every functional hook preserved (`municipality`/`barangay`/
  `birthdate`/`age`/`category`/`ipSelect`/`ipGroupDiv`/`aff-org-wrapper`, the
  `input.uppercase` casing hook, the barangay cascade fetch, name/id/`old()`
  bindings, the 5-org native alert guard); `window.addOrgField` reassigned per
  (re)execution for the inline `onclick`; non-panel pages unchanged. Panel footer
  = Cancel (`data-edit-client-cancel`) / Save Changes; form carries
  `data-panel-edit-form` so `DetailsPanel.submitPanelForm()` intercepts it.
  `.panel-form-errors` box rendered only in panel mode.
- `resources/views/clients/show.blade.php` — added `@include('partials.confirm-modal')`
  (full-page delete now confirms via the shared dialog, matching the other
  modules' show pages; `uiConfirm` absent there previously).

**Interaction with shared components (all pre-existing, contract-verified):**
`DetailsPanel.js` `load()`/`executeScripts()`/`submitPanelForm()`/`isOpen()`
contract, the additive `expectsJson()` branch on `clients.update`, the
`data-delete-client-form` fragment, and the index `data-edit-client`/`data-open-details`
wiring are unchanged — verified by reading them before the rework (no JS back-end
edits needed).

**Verification:**
- PHPUnit: `ClientTest` 10 / `PhotoTest` 4 / `GipTest` 6 green; full suite
  **286 tests / 1336 assertions / 0 failures** (baseline, no test modified).
- `vendor\bin\pint` passed on the three changed views; `php artisan view:cache`
  clean (all Blade compiles, panel and full-page paths render).
- Panel/full-page render covered end-to-end by
  `test_client_details_panel_returns_partial_without_layout` (asserts the partial
  without layout, plus the layout-wrapped full page).

**Explicit non-actions:** no schema/migration/dump/permission/ACL/route-semantics/
business-rule changes; no `DetailsPanel.js` or backend edits; no commits;
`C:\xampp\htdocs\system` untouched. Households edit-in-panel still intentionally
not built (no `houses.update` route — out of scope).

---

## 2026-08-31 — Clients module modal-first action workspace (clients-only scope)

**Scope:** presentation/interaction modernization of the **Clients module only**.
No schema, migration, permission key, ACL semantics, business rule, route
semantics, or `C:\xampp\htdocs\system` change. The modal-first Add/Edit, uiConfirm
Delete, simplified table, and view-only DetailsPanel are presentation changes over
the unchanged store/update/destroy endpoints.

**Objective (per owner brief):** make the Clients registry behave like the
prototype's streamlined workspace — modal-first Add/Edit, view-only DetailsPanel,
compact 6-column registry table, inline multi-select filters, right-aligned search,
icon-only actions, compact pagination — instead of a mix of V1 full-page flows and
V2 panel patterns. No other module was touched.

### Changes

- `app/Http/Controllers/ClientController.php`
  - `create()`: added a `?modal=1` branch that returns only the shared
    `clients._form` fragment (municipalities passed, `panel=false`, `modal=true`)
    so the index's Add flow renders the form inside the modal without a full-page
    navigation to `/clients/create`.
  - `edit()`: added a `?modal=1` branch that returns the `clients._form` fragment
    populated with the client + its affOrgs/municipalities/barangays; otherwise
    unchanged (full-page `/clients/{id}/edit` route remains for compatibility).
  - `store()`: added the missing `expectsJson()` branch (analogous to the existing
    one on `update`) so the modal's AJAX submit gets a JSON success response
    instead of a redirect; the duplicate-warning gate also gained a JSON branch
    (422 + `duplicate_warning`) reusing the existing `findPotentialDuplicates`
    service. Redirect path byte-unchanged.
  - `data()`: actions cell now emits an icon-only **View** button
    (`data-view-client`, always, matching the row-click open) alongside the
    existing Edit (`data-edit-client`) and Delete (`data-confirm`) icon buttons.
    No column-ordering or feed change.
  - Attempted Status column: **REVERTED** — `tbl_clients` has **no `status`
    column** (the `status varchar(8)` column is in `tbl_details`, not
    `tbl_clients`). Per §14 "do not invent statuses" the Status column was
    **omitted** (see Blocked below); the feed/columns stayed at the prior 6.

- `resources/views/clients/index.blade.php`
  - Header: "Add Client" is now a `<button>` calling `openAddClientModal()` (no
    link navigation to `/clients/create`). Export CSV dropdown unchanged.
  - Added a shared `#clientFormModal` (Bootstrap modal, `modal-lg`, centered,
    scrollable body, stable header/footer, Tailwind `rounded-panel`/surface
    tokens) reused for both Add and Edit.
  - JS: `openAddClientModal()` / `openEditModal(id)` fetch the `?modal=1`
    fragments and re-run their inline scripts via the existing `executeScripts()`
    helper; a delegated submit handler posts the form via fetch (JSON), renders
    `data.errors` inline into a `.form-errors` box, surfaces the duplicate warning,
    on success closes the modal, redraws the table, and shows a toast. Edit reloads
    an already-open DetailsPanel. `closeClientModal()` global for the form's Cancel.
  - Delete: the actions-column `[data-confirm]` form is intercepted (stopPropagation
    + preventDefault) → `window.uiConfirm()` → JSON POST to the existing
    `clients.destroy` endpoint → table redraw + toast; closes an open DetailsPanel
    for the removed row. Endpoint/ACL/CSRF unchanged.
  - Removed the legacy `data-edit-client` seed path that opened the panel's inline
    edit; the Edit icon now opens the modal directly.
  - DataTable columns unchanged at six (Client / Precinct No / Municipality /
    Barangay / Category / Actions) with the client identity cell (name over
    "Client ID: …"), compact icon actions, and the existing compact sliding
    pager — no Status column (see Blocked).

- `resources/views/clients/_details.blade.php` — **DetailsPanel is now view-only**:
  - Removed the embedded `#clientPanelEdit` edit form and its panel-edit JS
    (dead now that the panel no longer holds a form). The panel body keeps the
    sectioned profile (Personal / Address / Assistance / Contact / Household /
    Family Members / Transactions / GIP / Photo).
  - The panel action bar's Edit button now carries `data-edit-client-modal`; the
    index script opens the edit modal (and closes the panel) instead of toggling
    an inline form. Delete still uses `window.uiConfirm()` → JSON → `details:deleted`
    while the panel is open; full-page redirection otherwise. `?panel=1` /
    `data-panel-*` extraction contract unchanged.

- `resources/views/clients/_form.blade.php` — added `$modal` mode (footer becomes
  Cancel → `closeClientModal()` / Save Client) alongside the now-legacy `$panel`
  mode; a `.form-errors` box is rendered in modal mode to host inline 422 errors.
  All name/id/`old()`/functional hooks and the inline IIFE script remain.

- Reused infrastructure (unchanged): `partials.confirm-modal` (`window.uiConfirm`),
  `FilterChips`/`FilterConfig` multi-select filters with regime-preserving
  semantics, `DetailsPanel.js`, the existing `clients.data` feed, client identity
  cell, and the Bootstrap modal component language.

- `tests/Feature/ClientTest.php` — added 5 tests: `?modal=1` create/edit fragments
  return the form without the layout; `store`/`update` respond to `postJson`/
  `putJson` with JSON success; JSON `store` returns validation errors (422). None
  of the prior tests were modified to make them pass.

- `e2e/clients.spec.ts` — **NEW** Playwright spec (chromium) asserting the
  simplified table columns and that Add Client opens the modal without navigating
  to `/clients/create`.

### Verification

- PHPUnit (full suite, `main_system_test`): **291 passed / 1362 assertions /
  0 failures** (was 286 / 1336; +5 new ClientTest tests).
- `vendor\bin\pint` passed on the changed PHP files.
- `php artisan view:cache` clean (all changed Blade compiles).
- `npm run build` clean (Vite production build succeeds).
- Existing `tes_` all green; `test_client_details_panel_returns_partial_without_layout`
  still asserts the panel partial without layout and the layout-wrapped full page.
- Playwright clients/chromium spec **could not be executed locally**: the local
  `main_system` is the byte-identical production copy and has no
  `smoke_superadmin` seed user, so the existing smoke suite also fails on login
  (environmental — a separately-seeded test DB is required). The new spec is added
  for a future seeded environment; not a code failure.

### Blocked / deferred

- **Status column — BLOCKED (environment/facts).** `tbl_clients` has no `status`
  column (`tbl_details.status` is a different, unrelated table). Per §14 ("do not
  invent statuses") and §24 (do not change schema/backend to make a UI requirement
  fit), the Status table column was omitted. Adding a schema column or repurposing
  an unrelated table is a business/owner decision, out of the presentation scope of
  this session.
- Duplicate/identity UX (Phase F): the backend gate already surfaces likely matches
  on create; the modal surfaces the same server-computed warning (Cancel /
  Continue) without any new identity rule. No new identity/dedupe rule was invented.

**Non-actions:** no schema/migration/`schema:dump`/permission/ACL/route-semantics/
business-rule change; no other module touched; `C:\xampp\htdocs\system` untouched;
no commits.

## 2026-08-31 — Clients module UI/UX refinement pass, Phase A→B (clients-only scope)

Follow-up pass on the modal-first Clients workspace, driven by the owner-facing
requirement brief. Scope-limited to **Clients module UI/UX + presentation/
interaction layer**. Backend business rules, ACL, routes, duplicate-detection
backend, FilterChips query/URL contract, and `tbl_client_aff_orgs` write behavior
are all unchanged. Two owner decisions were confirmed via question before
implementation (see below).

### Owner decisions confirmed
1. **Programs & Services form section** — keep the **7** Affiliated Organizations
   (`aff_org[]`, `tbl_client_aff_orgs`) as the authoritative member list; the
   prototype's 4 category headings are a **presentation-only** grouping; the same
   `aff_org[]` values are submitted through the byte-unchanged `syncAffiliations`
   write path. No transaction-program value is mixed in.
2. **Add a 4th Category filter** — from the authoritative `deriveCategory` values,
   wired into the shared `clients.data` feed and CSV export (multi-value, ACL-
   scoped).

### Phase A — findings (root causes)
- **Filter popover unreliable**: the shared `.filter-multi-menu` is absolutely
  positioned inside the Clients `.data-card`, whose Tailwind `overflow-hidden`
  clips it, so the choice panel can fail to appear dependably.
- **Missing 4th filter**: only Municipality/Barangay/Program existed; no Category
  category in `$filterChips` nor in the `data()`/`export` feed.
- **Modal footer was inside the scroll body**: the Cancel/primary footer was
  rendered inside the injected `_form` fragment (i.e. within the scrollable
  `.modal-body`), so it was not a fixed region.
- Remaining items (single search, 6-column compact table, per-filter Clear, no
  global Reset, Edit `e.preventDefault()`) were already correct from the prior
  pass and were retained.

### Implemented

- `app/Services/ClientService.php` — added the authoritative `CATEGORIES` const
  (4 values from `deriveCategory`), one source of truth for the Category filter
  and the category badge.
- `app/Support/FilterConfig.php` — added `staticCategory()` (a fixed-option,
  unscoped category builder mirroring `programCategory`).
- `app/Http/Controllers/ClientController.php`:
  - `index()` appends the **Category** category to `$filterChips`.
  - `data()` accepts a `category` param (multi-value `applyMultiValue` on
    `c.category`), ACL-scoped like the other filters.
  - `export()` honors `category` for **Export Current Filter**.
- `resources/views/clients/index.blade.php`:
  - **4-filter pill toolbar**: Municipality / Barangay / Programs / Category,
    each a pill with an embedded filter icon + active selection count badge
    (`is-active`/`aria-pressed` when filtered). Cohesive rounded toolbar shell
    wrapping a single rounded `#clientsSearch`.
  - Popover reliability: `#clients-screen .data-card { overflow: visible }` and a
    raised `filter-multi-menu` z-index so the shared FilterChips panel opens
    visibly. FilterChips component reused as-is (searchable multi-select,
    per-filter Clear, no global Reset, municipality→barangay cascade).
  - DataTables `dom: "<'top-chrome'lp>rtip"` → **top row** = Show-entries left +
    compact windowed pager right; **bottom row** = info left + pager right
    (windowing already 5 numbers + ellipsis).
  - Table readability: font-size 0.875rem, comfortable row padding/vertically
    centered, no forced desktop horizontal scroll (autoWidth false, no scrollX).
  - **Fixed modal footer** (gray) with Cancel (secondary) + gold primary bound
    to the in-body form via `form="clientForm"`; submit label toggles
    "Add Client"/"Save Client". Header (navy) stays non-scrolling; only `.modal-body`
    scrolls (`modal-dialog-scrollable`).
  - Validation: server-driven modal-native error list + `.is-invalid` field
    highlight + focuses the first invalid field; success keeps the in-modal flow
    (toast + table redraw); duplicate warning stays modal-native.
- `resources/views/clients/_form.blade.php`:
  - Modal mode renders **no inline footer** (the fixed modal footer owns Cancel +
    submit via `id="clientForm"`); `novalidate` added (no native browser popups).
  - Programs & Services grouped presentation: the 7 Affiliated Organizations are
    grouped under the category headings (Livelihood & Employment, Community
    Programs — only headings with members render), each a checkbox multi-select,
    still submitted as `aff_org[]`.
- `resources/css/app.css` — reusable `.client-aff-group` / `.client-aff-group-title`
  (group headings) on existing tokens.

### Tests

- Added `ClientTest` coverage: `clients.data` **category** filter; index renders
  the **Category** segment + 4 authoritative options; fixed modal footer + submit
  (`form="clientForm"`); exactly **one** search input + **six** columns.
- Updated the two modal-fragment tests (§16/§25): the fragment now asserts it is a
  standalone `id="clientForm"` form **without** an inline footer (the fixed modal
  footer owns the primary action). This reflects the approved new modal
  architecture, not a weakened assertion.

### Verification

- PHPUnit full suite: **295 passed / 1380 assertions / 0 failures** (was 291/1363;
  +4 new tests).
- Pint: clean on `ClientController.php`, `FilterConfig.php`, `ClientTest.php`. The
  whole-project `pint --test` still reports only the **pre-existing** 3 style flags
  in `app/Services/ClientService.php` (uncommitted Phase work; the `CATEGORIES`
  constant added here does not introduce new flags).
- `npm run build` clean; compiled CSS contains `.client-aff-group*`, `.seg-btn-icon`,
  `.filter-toolbar`, `.top-chrome`, `bg-neutral-100`.
- `view:cache` clean (all Blade templates compile).
- Playwright clients/chromium spec **could not be run locally** — unchanged
  environment reason: no `smoke_superadmin` seed in the production-copy DB, so a
  session cannot be established without writing to the production-copy DB (guarded
  against). Not a code failure; the spec still matches the shipped DOM.

### Deferred / notes

- The `alert()` calls remaining in the clients **delete** flows and DetailsPanel
  camera are outside the Add/Edit modal workflow and the DetailsPanel redesign is
  still deferred (out of scope).
- Category/Program filter options are server-rendered into the shared FilterChips
  menu and retain the multi-value OR-within-category / AND-across-categories and
  URL/query contract.

**Non-actions:** no schema/migration/`schema:dump`/permission/ACL/route-semantics/
business-rule change; no other module touched; `C:\xampp\htdocs\system` untouched;
no commits.

---

## 2026-08-31 — Clients module final verification pass + Pint cleanup (clients-only scope)

Follow-up verification-only pass on the completed modal-first Clients workspace.
No feature, business rule, ACL, route, schema, or other-module change. This entry
records a full green re-verification plus a cosmetic code-style cleanup.

### Changes

- `app/Services/ClientService.php` — **cosmetic only**: `vendor\bin\pint` resolved
  the three **pre-existing** style flags previously noted in the Phase A→B entry
  (`new Collection()` → `new Collection`, unary-operator/witespace normalization).
  No behavioral change — derived fields, duplicate lookup, create/update/destroy,
  and `syncAffiliations` logic are byte-equivalent.

### Verification (full requirement spec §22)

- PHPUnit full suite: **295 passed / 1380 assertions / 0 failures** on
  `main_system_test` (Clients modal/JSON/category/fixed-footer/single-search/
  6-column tests all green after the Pint touch-up).
- `vendor\bin\pint --dirty`: clean (the only flags were the three now-fixed ones
  in `ClientService.php`).
- `npm run build`: clean; compiled CSS contains `.icon-btn`, `.btn-gold`,
  `.filter-multi-menu`, `.client-aff-group*`.
- `php artisan view:cache`: clean (all Clients Blade templates compile).
- Schema fact re-confirmed: `SHOW COLUMNS FROM tbl_clients` has **no `status`
  column** — the spec's Status table column stays **BLOCKED** (per §14/§24, not
  inventing statuses), so the shipped 6-column table is the correct compliant
  fallback.

### Notes / deferred (unchanged from prior entries)

- Playwright clients/chromium spec still cannot authenticate locally (no
  `smoke_superadmin` seed in the production-copy `main_system`); it matches the
  shipped DOM and is intended for a seeded environment.
- DetailsPanel redesign and the remaining native `alert()` in delete/camera flows
  remain outside scope.
- Category/Program filters keep the multi-value OR-within-category /
  AND-across-categories and URL/query contract via the shared FilterChips.

**Non-actions:** no schema/migration/`schema:dump`/permission/ACL/route-semantics/
business-rule change; no other module touched; `C:\xampp\htdocs\system` untouched;
no commits.

---

## 2026-08-31 — Clients module UX refinement pass (clients-only scope)

**Scope:** presentation/interaction polish of the **Clients module only**, on top
of the prior modal-first workspace. No module was touched beyond Clients views and
the shared token stylesheet. No schema, migration, permission key, ACL semantics,
business rule, route, or `C:\xampp\htdocs\system` change. The controller's backend
behavior was not altered (only the presentation of already-served data changed).

**Objective (per owner brief):** converge the Clients index into one coherent,
modern 2DMIS workspace — a single search, multi-select + searchable filters with
per-filter Clear (no global Reset), a compact ~6-column table, a gold/blue modal
Add/Edit flow, and a modal-native duplicate/identity gate — while keeping every
existing endpoint, permission, and business rule intact.

### Changes

- `resources/views/clients/index.blade.php` (Clients-only):
  - Header action group reordered to the owner's primary/secondary model —
    `[+ Add Client] [Export CSV ▾]` — and the **"Remove Duplicates" action removed**
    from the UI (duplicate handling now happens only during Add). The duplicate
    management **route/controller is untouched**.
  - Added an accessible **filter icon** (SVG, `title`/`aria-label`) before the
    Municipality / Barangay / Program segment buttons.
  - **Removed the global Reset button**; per-filter Clear comes only from inside
    the shared FilterChips popover (each category keeps its own Clear).
  - Search reduced to **one control** (`#clientsSearch`, placeholder
    "Search clients..."); DataTables' built-in filter box is suppressed by setting
    `dom: 'lrtip'` (drops `f`) so no second search box appears. Search still feeds
    the existing server-side `clients.data` feed unchanged.
  - **Fixed the Edit-modal navigation bug**: the Edit icon is an
    `<a href="{clients.edit}">` (kept for full-page parity), and the delegated
    `[data-edit-client]` handler now calls **`e.preventDefault()`** (plus
    stopPropagation) before `openEditModal(id)`, so the browser no longer follows
    through to `/clients/{id}/edit` after the modal opens. Add/Edit never navigate
    to a full page.
  - Modal header now uses the navy/blue identity with a white title + supporting
    subtitle (`#clientFormModalSubtitle`): "Add Client / Register a new client in
    the registry" and "Edit Client / Update client details in the registry".
  - Replaced the native `confirm()` duplicate prompt with a **modal-native warning
    panel** (`showDuplicateWarning`): "Possible existing client found", lists the
    server-computed matches, and gives two explicit in-modal choices —
    **Review existing client** (closes the modal and opens the existing record in
    the DetailsPanel) and **This is a different person — Continue** (adds a hidden
    `duplicate_confirm=1` field and resubmits). No `alert`/`confirm`/`prompt`.
  - Actions column width widened to 112px for the fixed-size icon buttons.

- `resources/views/clients/_form.blade.php` (Clients-only, modal + full page):
  - Restructured the shared form into the requested logical groups with navy
    micro-caps section headings: **Personal Information** (last/first/middle/
    extension), **Address** (region, province, municipality*, barangay*, house no.),
    **Contact Information** (mobile, email), **Personal Details** (birthdate, age,
    gender, civil status), **Additional Information** (PWD, IP, IP group,
    occupation, monthly income), **Programs & Services**, and
    **Registration & Identification** (category, precinct, voter's ID). Field
    names/values/ids and the cascading geography + auto-age/category/ip JS all
    preserved.
  - **Affiliated Organizations** redesigned from repeated single-select dropdowns
    into a **checkbox multi-select** (still submitted as `aff_org[]` → the existing
    `syncAffiliations` backend path, byte-unchanged). Options come only from the
    existing aff_org set (`PUSO TI KABABAIHAN`, `PUSO TI MANNALON`,
    `PUSO TI AGTUTUBO`, `RIC`, `FARMER'S ORGANIZATION`, `TALA`, `LCW`) — **no
    program value was invented, renamed, or migrated** (per an owner decision, the
    transaction PROGRAMS list was intentionally NOT mixed into the client form).
    Positive selection is visually emphasized (gold-tinted checked tiles).
  - Footer per the owner's gold/blue treatment: **Cancel is secondary
    (`btn-subtle`); the primary is `btn-gold` (gold surface, navy/blue text)** —
    "Add Client" for create, "Save Client" for edit, "Save Changes" for the legacy
    panel mode. No `confirm()`/`alert()` in the form.

- `resources/css/app.css` (shared token stylesheet — Clients feature classes):
  - Added reusable `.icon-btn` base + `.icon-btn-danger` (fixed 32px tile, navy
    hover, red tint for destructive) so action buttons never resize with content.
  - Added `.client-form-group` / `.client-form-group-title` (navy section headings)
    and `.client-aff-grid` / `.client-aff-check` (checkbox multi-select tiles with
    navy accent + gold-dim checked state). All built on existing design tokens
    (`navy`, `gold-dim`, `line`, `ink`, `bg`).

- `tests/Feature/ClientTest.php` — one assertion updated per §25: the create-modal
  test now expects **"Add Client"** (the button label the owner specified) instead
  of the old "Save Client". The edit-modal test still asserts "Save Client". This
  reflects an explicit UX change (presentation), not a weakened assertion; all
  corrected field/index behavior assertions are unchanged.

### Verification

- PHPUnit full suite: **291 passed / 1363 assertions / 0 failures** (was 291/1362;
  +1 assertion from the updated create-modal label check).
- `vendor\bin\pint` passed on the changed PHP files (`ClientController.php` and
  `ClientTest.php`); the whole-project `pint --test` still reports one **pre-existing**
  style flag in `app/Services/ClientService.php` (uncommitted Phase work, not part
  of this pass and not touched).
- `npm run build` clean (Vite production build succeeds; compiled CSS contains the
  new `.icon-btn`, `.client-form-group*`, `.client-aff-*` and `accent-color:navy`
  rules).
- Playwright clients/chromium spec **could not be executed locally** — unchanged
  environment reason from the prior pass: the local `main_system` is the
  byte-identical production copy with no `smoke_superadmin` seed user, so a login
  session cannot be established without writing to the production-copy DB (guarded
  against). Not a code failure; the spec still matches the shipped DOM.

### Deferred / notes

- The remaining native `alert()` calls in the clients delete flows
  (`index.blade.php` delete branch, `_details.blade.php` delete + camera capture)
  are outside the Add/Edit modal workflow and outside this pass's scope (DetailsPanel
  redesign is explicitly deferred). The modal-native duplicate/validation/success
  feedback this pass required is fully in-modal.
- DetailsPanel was intentionally **not** redesigned; only its existing View action
  is verified to keep working.

**Non-actions:** no schema/migration/`schema:dump`/permission/ACL/route-semantics/
business-rule change; no other module touched; `C:\xampp\htdocs\system` untouched;
no commits.

## 2026-09-01 — Clients module browser-verified defect fixes (clients-only scope)

**Scope:** real browser verification (in-browser, not just PHPUnit) of the Clients
module surfaced **three genuine defects**; all three were fixed with code changes.
The remaining reported items were verified to already work in-browser and needed no
code change. Clients module + shared FilterChips component only. No schema,
migration, `schema:dump`, permission key, ACL semantics, business rule, route, or
`C:\xampp\htdocs\system` change.

**Objective (per owner brief):** stop reporting items as "verified" unless the
behavior actually works in a browser; deliver real fixes where broken, and prove
the rest.

### Changes (the three real fixes)

1. **Filter popovers opened then immediately closed (BUG 1).**
   - `resources/js/components/FilterChips.js`: the Clients page renders the
     segment buttons **outside** the FilterChips host, so the component's document
     "click outside closes menu" handler fired on the very click that opened the
     menu. Added an `excludeClose` config option (array of selectors) and
     `isExcludedTarget()` helper; document click handler now honors it. Refactored
     menu opening into `openMenu(onlyKey)` / `showMenu()` / `revealCategory(key)`
     and exposed a public `openCategory(key)` API so a category can be opened from
     outside the host element.
   - `resources/views/clients/index.blade.php`: `FilterChips.init` now passes
     `excludeClose: ['[data-filter-segment]']`; the segment click handler
     `openFilterSegment(key)` now calls `window.clientFilters.openCategory(key)`.

2. **Single search did nothing (BUG 2) / Precinct No. not searchable (BUG 3).**
   - The clients page DataTables sends search as a **top-level** `search` string
     (`d.search =('#clientsSearch').val()`), but the controller read only
     `$request->input('search.value')` (DataTables array form) — so search was a
     no-op.
   - `app/Http/Controllers/ClientController.php::data()`: now reads `search`
     accepting **both** forms — array (`search.value`) for the standard DataTables
     protocol (needed by existing tests) and plain top-level string (clients page).
   - Search placeholder updated to "Search name, precinct no., municipality,
     barangay...".

3. **Pagination rendered twice (BUG 5).**
   - `resources/views/clients/index.blade.php`: `dom: "<'top-chrome'lp>rtip"`
     rendered the pager in both the top toolbar and the bottom info row. Changed to
     `dom: "<'top-chrome'l>rt<'bottom-chrome'ip>"` (single bottom pager), and added
     `.top-chrome` / `.bottom-chrome` flex CSS.

### Verified already working (no code change needed)

- **BUG 4 (table readability):** navy thead with white text, fixed-size icon action
  buttons, 25 rows/page, no JS errors.
- **BUG 6 (toolbar/buttons):** `+ Add Client` (`btn-gold`), `Export CSV`
  (`btn-subtle dropdown-toggle`) with "Export Current Filter" / "Export All
  Clients".
- **BUG 7 (modal architecture):** Add modal opens; fixed footer `#clientFormSubmit`
  with `form="clientForm"`.
- **BUG 8 (form grouping):** all 7 groups render (Personal Information, Address,
  Contact Information, Personal Details, Additional Information, Programs &
  Services, Registration & Identification).
- **BUG 9 (Affiliated Organizations):** all 7 options present (FARMER'S
  ORGANIZATION, RIC, PUSO TI KABABAIHAN, PUSO TI MANNALON, PUSO TI AGTUTUBO, TALA,
  LCW).
- **BUG 10 (in-modal validation + duplicates):** empty submit shows the in-modal
  error box plus 7 `.is-invalid` fields; the modal-native duplicate-warning panel
  infrastructure is present.

### Verification

- **PHPUnit full suite:** **297 passed / 1397 assertions / 0 failures** (was
  295/1380). Two regression tests added to `tests/Feature/ClientTest.php`:
  `test_client_data_feed_searches_by_precinct_no` (both `search.value` array form
  and top-level string form; precinct, name, and no-match cases) and
  `test_clients_index_renders_each_filter_pill_with_its_own_popover_section`
  (per-filter `data-filter-segment` pills + `data-filter-cat` sections +
  `data-filter-options="municipality"`).
- **Pint:** `vendor\bin\pint --dirty` passed.
- **Build:** `npm run build` clean (FilterChips.js rebuild + copy to
  `public/js/components/FilterChips.js`, verified to contain `excludeClose`,
  `isExcludedTarget`, `openCategory`). `php artisan view:cache` clean.
- **In-browser verification** (throwaway `php artisan serve` on a disposable
  `main_system_test` copy DB — `main_system` untouched): menu opens and category
  isolation works (observations confirmed menu `menuDisplay:flex`, only the clicked
  category visible, 34 municipalities rendered after copying geographic reference
  rows into the scratch DB); search `DHG65745` → exactly GIRONELLA, `reyes` → REYES,
  request confirmed `search=<term>` as top-level; single pager (`pagers: 1`),
  single length select, single info line; "Alilem" filter → 3 rows, chip
  "Municipality: Alilem", pill `aria-pressed: true`.
- **Playwright clients spec** (`e2e/clients.spec.ts`, 4 tests incl. 3 new):
  **chromium 4/4 passed** against the scratch DB. firefox/webkit failed at
  **browser-launch/navigation level** (Firefox SWGL compositor + Juggler
  crash-loop errors; WebKit empty navigation) — an environment limitation on this
  machine, not an application failure and not chased.

### Environment / discipline notes

- A stale `php artisan serve` (no `DB_DATABASE` env) had been holding port 8000 and
  serving the production-copy `main_system` — it made every e2e run fail login with
  "Invalid username or password". Killed all stray serve processes and restarted
  both throwaway servers with `$env:DB_DATABASE="main_system_test"`.
- **Ordering constraint discovered:** `php artisan test` (RefreshDatabase) **wipes
  `main_system_test`**, so the run order must be PHPUnit FIRST → re-seed scratch DB
  (user/geography/clients) → Playwright e2e LAST.
- All scratch tooling ran from `C:\Users\J\AppData\Local\Temp\opencode\`; no seed
  data or scripts were left in the repo (temp `_obs*.cjs` files and `test-results`
  removed). Seed data exists only in the disposable `main_system_test` DB.

**Non-actions:** no schema/migration/`schema:dump`/permission/ACL/route-semantics/
business-rule change; `C:\xampp\htdocs\system` untouched; no commits.

---

## 2026-09-02 — CSS maintainability: hardcoded semantic colors → `@theme` tokens

**Scope:** presentation-only refactor of `resources/css/app.css`. Removes
hardcoded semantic color values from shared component CSS in favor of the
canonical `@theme` design-token source. **No visual change** — every value was
carried over byte-identical (exact hex preserved), no naming collisions, no
redesign. No markup, Blade, JS, PHP, route, schema, business-rule, or
`tailwind.config`/`@theme`-palette change (7 new additive tokens only).

### Changes

1. **New semantic tokens** added to `@theme static` under a new
   "Interaction & status derivatives" comment block:
   `--color-gold-hover: #EAC400`, `--color-gold-active: #DBB800`,
   `--color-red-hover: #A80F20`, `--color-red-active: #900C1B`,
   `--color-teal-dark: #1E6B5B`, `--color-blue-accent-dark: #2563EB`,
   `--color-amber-dark: #8A6D00`. All names additive; no existing token
   overridden.
2. **Component rules switched to tokens:**
   - `.btn-gold:hover` / `:active`: `#EAC400` / `#DBB800` →
     `var(--color-gold-hover)` / `var(--color-gold-active)`.
   - `.btn-red` and `.btn-outline-red`: `hover:bg-[#A80F20]` /
     `active:bg-[#900C1B]` → `hover:bg-red-hover` / `active:bg-red-active`.
   - `.status-badge` success/info/warning text (`text-[#1E6B5B]`,
     `text-[#2563EB]`, `text-[#8A6D00]`) → `text-teal-dark`,
     `text-blue-accent-dark`, `text-amber-dark`. Tint backgrounds untouched.
3. **Redundant `var()` fallbacks removed** in the shared FilterChips component
   CSS. Verified every consumer (clients, transactions, households, payouts,
   unpaid, audit_logs, scholarship_reports) renders via
   `layouts/app.blade.php`, which loads the same built `app.css` that
   declares `@theme` — so the tokens are always available and the fallbacks
   never resolved. Removed on `--color-line`, `--color-surface`, `--color-ink`,
   `--color-navy`, `--color-gold`, `--color-bg`, `--color-ink-muted`,
   `--color-line-light`, `--radius-control`, `--radius-panel`, `--shadow-pop`,
   `--shadow-overlay` (incl. the mobile bottom-sheet media query). No other
   stylesheet references these `.filter-*` classes.

### Left hardcoded (intentional)

- `rgba(255, 255, 255, 0.55)` / `0.9` sidebar chrome text — white-with-opacity
  over the dark navy chrome, written longhand for the documented self-scan
  reason (coexistence rule) and not a reusable semantic color in the target list.
- `#fff` white text in `.filter-count-badge` / `.filter-done-btn` — structural
  white text on the navy badge/button, consistent with `text-white` used
  elsewhere via Tailwind.
- `rgb(15 27 45 / …)` shadow-tint values inside `--shadow-*` token definitions —
  shadow tinting, not semantic colors.
- `var(--ui-accent, var(--color-navy))` metric-card accent — nested fallback
  to the `--ui-accent` per-accent override pattern; intentionally preserved.

### Verification

- **Build:** `npm run build` clean. Compiled `public/build/assets/app-B7oMxxpA.css`
  contains all 7 tokens (`--color-gold-hover:#eac400`, …, `--color-amber-dark:#8a6d00`)
  and the component rules now resolve to them: `.btn-gold:hover` →
  `var(--color-gold-hover)`, `.status-badge…` → `var(--color-teal-dark)` /
  `var(--color-blue-accent-dark)` / `var(--color-amber-dark)`; the status-badge
  tint backgrounds still compile to the same `color-mix(...,12%/…15%…,transparent)`
  as before. No `var(--color-*,…#…)` fallback patterns remain in the compiled CSS.
- **PHPUnit:** `php artisan test --filter FilterChipsTest` — **24 passed / 90
  assertions / 0 failures** (the directly affected shared filter-chips suite).
- **Lint:** no CSS linter configured in the repo (`package.json` has no
  stylelint; `pint` is PHP-only). The affected mechanism (`@apply` + raw CSS)
  compiles cleanly via the Tailwind v4 build above.

**Non-actions:** no Blade/markup change, no JS change, no PHP change, no
`public/css/ui.css`, Bootstrap CDN, prototype, schema, or route change;
`C:\xampp\htdocs\system` untouched; no commits.

### 2026-09-02 — Clients details-panel UI/UX restructure (info hierarchy + collapsibles + audit)

Presentation-only restructure of the client **DetailsPanel** body so the profile
is easy to scan, while leaving the fixed drawer shell, all `data-panel-*`
contracts, and every existing behavior (Edit modal, uiConfirm delete, photo
modal, GIP, `?panel=1`, DetailsPanel.js) intact. Reuses the established panel
voting (`details-section`, `details-section-title`, `details-grid`,
`details-field`) — no new color tokens, no backend/schema/route/permission change.

**Files changed:**
- `resources/views/clients/_details.blade.php` — restructured body only:
  - The redundant in-body identity block (photo + name + `Client Profile`
    heading + Back/Photo/Edit/Delete) is now full-page-only; in **panel mode**
    the fixed shell header + action bar carry that chrome (identity header =
    `data-panel-title/sub/avatar/meta`; action bar = `data-panel-actions`
    Edit/Delete).
  - `data-panel-avatar` moved **outside** `[data-panel-body]` so the header
    avatar (64px box) receives the photo or an initials placeholder instead of
    the old 180px image + name that overflowed the shell's avatar.
  - Body reordered into the approved sections, in order: **Personal
    Information** (Last/First/Middle/Extension/D.O.B/Age/Gender/Civil Status),
    **Contact Information & Address** (Mobile/Email/Barangay/Municipality/
    Province/Region), **Additional Information** (PWD-IP, IP Group, Occupation,
    Monthly Income, Affiliated Organization, Precinct No., Voter's ID),
    **Household Information** (House No., Household, Head of Household),
    **Family Composition** (collapsible), **Transactions** (collapsible),
    **Audit Information**.
  - Age uses the **stored `$client->age`** (authoritative, computed by
    `ClientService::deriveAge()` at write) — no second calculation.
  - Family Composition and Transactions are now accessible, button-based
    collapsibles: `aria-expanded`/`aria-controls`, visible chevron, keyboard
    accessible; wired by a new inline IIFE (re-executed by
    `DetailsPanel.executeScripts()`). Content/data preserved.
  - Transactions table stays in its own `overflow-x-auto` container (min-w
    36rem) so it scrolls horizontally without widening the drawer.
  - Empty/null values render `—` (never "undefined"/"null"); long values are
    protected by the existing `min-width:0` + `word-break:break-word` on
    `.details-field .value`.
- `resources/views/partials/details-panel.blade.php` — additive scoped CSS:
  - `.details-panel-inner` now `display:flex; flex-direction:column; flex:1;
    min-width:0` so header/actions stay fixed while the body scrolls and long
    content shrinks instead of widening the drawer.
  - `.details-avatar-photo` / `.details-avatar-initials` sized to the 64px
    header avatar (gold placeholder fallback, on-dark `#fff` already in use by
    the existing `.details-avatar`).
  - `.details-accordion-toggle` / `.details-chevron` / `.details-accordion-panel`
    for the collapsibles, using existing `--color-navy`/`--color-gold` tokens.
  - Mobile (`<768px`) override collapses `.details-grid` to one column.

**Audit-data verification (real data, no fabrication):**
- `tbl_clients` has **only** `created_at` (timestamp) — no `created_by`,
  `updated_at`, or `updated_by` columns (schema confirmed).
- **Created At** is the authoritatively stored `tbl_clients.created_at`.
- **Created By** is read (not written) from the existing `tbl_audit_logs`
  contract that `AuditService` writes and `AuditController` reads: the
  `ADD_CLIENT` row for `target_table=tbl_clients`, `target_id=client.id`,
  username resolved from `tbl_users`. **Last Updated By/At** come from the most
  recent `EDIT_CLIENT` row for the same target.
- When no source row exists, the value renders `—` (verified: client id=1 has an
  `ADD_CLIENT` by `jordi` and no `EDIT_CLIENT` → Created By/At populated, Last
  Updated By/At shown as `—`).
- No new audit tracking added; the panel only reads existing logs.

**Verification:**
- `npm run build` clean (vite v6.4.3, 56 modules).
- `php artisan view:cache` — all Blade templates cached (incl. both changed
  files).
- `php artisan test` — **297 passed / 1397 assertions / 0 failures**; notably
  `client details panel returns partial without layout` still passes.
- Static render check (panel `?panel=1` and full-page): all 7 section headings
  present and in order; no `undefined`/`null` literals; avatar resolves to photo
  or initials; audit block resolves real Created By/At and `—` for Last
  Updated By/At.
- No PHP files modified → Pint not run. No schema, route, controller, model,
  service, permission, or business-rule change. `C:\xampp\htdocs\system`
  untouched.

### 2026-09-02 — Clients details-panel follow-up: action bar + fixed-drawer scrolling (clients-only scope)

Follow-up on the details-panel restructure above. Two targeted UI adjustments,
clients-only, presentation/code-behind only — no schema, route, controller,
model, service, permission, or business-rule change, and no new audit tracking.

**1. Action bar — two new actions, using the real existing routes/ACL:**
- `resources/views/clients/_details.blade.php` — `data-panel-actions` now renders
  four actions inside a `.details-actions-line` responsive wrapper:
  - **Add Transaction** → `<a href="{{ route('transactions.create',client) }}">`
    (`btn-navy`, matching the full-page button). This is the **existing**
    `transactions.create` route (`GET transactions/create/{client}`), client id in
    the URL, and it is rendered **only** under the **same ACL condition as full-page
    mode**: `$acl->canAccessPage($user, 'all_transactions.php')`. The backend
    (`TransactionController::create()` + the `page:all_transactions.php` route
    middleware) independently enforces the same page gate plus a per-record
    `canAccessRecord` check, so no permission is bypassed or weakened.
  - **Open in Full Page** → `<a href="{{ route('clients.show',client) }}">`
    (`btn-subtle`). Uses the existing `clients.show` route (`GET clients/{client}`),
    same `page:clients.php` group that already gates the panel/index itself, so it
    navigates to the real full-page Client Details view. No duplicate page/route.
  - **Edit** (`btn-gold`, `data-edit-client-modal="{{client->id }}"`) and **Delete**
    (existing `clients.destroy` POST form, `btn-red`, `data-delete-client-form`)
    unchanged — both still gated by the existing `EDIT`/`DELETE` action checks.
  - `details-actions-line` is a plain wrapper div; all existing delegated bindings
    (`data-edit-client-modal`, `data-delete-client-form`) are document-level
    `closest()` handlers, so the added nesting does not break Edit/Delete.

**2. Fixed-drawer scrolling (header + action bar fixed, body scrolls only):**
- `resources/views/partials/details-panel.blade.php` — completed the existing flex
  architecture with the missing `min-height: 0` that a flex-column scroll body
  needs:
  - `.details-panel-inner` — added `min-height: 0` (was only `min-width: 0`).
  - `.details-header` — added `flex-shrink: 0` (header never compresses/scrolls).
  - `.details-actions` — added `flex-shrink: 0` (action bar stays fixed below the
    header).
  - `.details-body` — added `min-height: 0` alongside its existing `flex: 1;
    overflow-y: auto`. Now only the body scrolls; header/action bar stay pinned
    while the panel remains fixed to the viewport.
- No `position: fixed` on header/actions — the flex column handles it cleanly.

**3. Responsive action bar:**
- `.details-actions-line` is a `width:100%` flex-wrap container; its direct
  children (buttons + the delete form) get `flex: 1 1 calc(50% - 8px);
  min-width: 120px` — a clean even **2×2** on the 480px drawer and on the
  full-width mobile drawer, never forcing four buttons into a cramped single row.
  The delete submit button inside its form is `width:100%` so it fills the tile.
  Specificity is matched later-in-source than the shared
  `.details-actions .btn { flex: 1 1 calc(33.33% - 8px) }` rule so only the
  clients panel is affected; other panels (households/transactions/scholars/etc.)
  keep their existing behavior. Long labels wrap (min-width cap), touch targets
  stay ≥120px, no horizontal overflow. Long values/emails/org names continue to
  wrap via the existing `.details-field .value` `word-break:break-word`.

**CSS/design tokens:** reused the existing `btn-navy`/`btn-subtle`/`btn-gold`/
`btn-red` vocabulary and `--color-*` tokens. No new color tokens introduced; no
hardcoded colors added. `tailwind.config` allowlist and Bootstrap coexistence
untouched.

**Verification:**
- Static render (panel mode, client id=1): the four-action bar renders with the
  correct resolved URLs — Add Transaction → `/transactions/create/1`, Open Full
  Page → `/clients/1`, Edit modal button, Delete form. ACL: Add Transaction is
  hidden whenever `all_transactions.php` access is absent (identical condition to
  full-page mode); server-side create/show gates remain authoritative.
- `npm run build` clean (vite v6.4.3, 56 modules).
- `php artisan view:cache` — all Blade templates cached (incl. both changed files).
- `php artisan test` — **297 passed / 1397 assertions / 0 failures**; the
  `client details panel returns partial without layout` test still passes.
- No PHP files modified → Pint not run. No schema/migration/`schema:dump`/route/
  permission/ACL/business-rule change; `C:\xampp\htdocs\system` untouched; no
  commits.

### 2026-09-02 — Clients UX refinement (clients-only): header category line, filter clears, barangay fix, photo-in-Edit-modal

Follow-up on the details-panel restructure/follow-up above. Clients-module UX
refinements. **One authorized backend change** (photo 1MB + GD optimization);
all other changes are presentation/code-behind only. No schema, ACL, route,
business-rule, or production-data change; no new tables; `C:\xampp\htdocs\system`
untouched.

**1. Header — category as a distinct line below the ID (Task 2):**
- `resources/views/clients/_details.blade.php` — `data-panel-sub` now shows only
  `ID: <id>`; the category is no longer duplicated inline with the ID.
- Category moves into `data-panel-meta` as a dedicated `.details-category` pill that
  wraps to **its own line** below the ID (`flex-basis:100%`), with the household
  program-tag flowing onto the following line.
- `resources/views/partials/details-panel.blade.php` — added scoped
  `.details-meta .details-category` styling (light-on-dark for the fixed navy
  header, gold dot) matching the existing `.archived`-variant pattern for this
  dark-header zone. Header stays pinned (unchanged flex-shell).

**2. Task 3 — config CONFLICT, NOT implemented (documented):**
- The task called for a selectable Category dropdown with labels `MINOR/YOUTH/
  ADULT/SENIOR`. That conflicts with the **authoritative, age-derived** category
  rule: `ClientService::deriveCategory()` + `attributes()` always recompute (and
  persist) `category` from age and **ignore any submitted value**. The `_form`
  category field is already a `readonly` input auto-populated by inline JS from
  birthdate using the exact authoritative labels (`MINOR (0-17)`, `YOUTH (18-29)`,
  `ADULT (30-59)`, `SENIOR CITIZEN (60 AND ABOVE)`). Per the task's own directive we
  **stopped this change, left category read-only, and did not invent a conflicting
  dropdown**. No code change for Task 3.

**3. Filter clears + Clear All (Task 4) — verified already present:**
- `resources/views/partials/filter-chips.blade.php` + `resources/js/components/
  FilterChips.js` already ship: per-category **Clear** (`.filter-clear-cat`,
  hidden when that category has no selection), **per-value** chip removal
  (`.filter-chip-remove`, the `Category [Adult ×]` pattern in the applied-chips
  row), and a **Clear All** (`.filter-clear-all`) that is hidden whenever the
  active-count is zero. Each individual filter keeps its own reset, and no-value
  filters hide their clear control. No code change needed — verified and reused as-is.

**4. Barangay filter visibility fix (Task 5) — root cause:**
- `resources/js/components/FilterChips.js` — `applyCascadeVisibility()` hidden
  every barangay option until a municipality was selected, while the in-category
  search (`bindSearch`) revealed options unconditionally — so the Barangay filter
  looked empty until the user typed. The cascade also contradicted `isValidBarangay()`
  (which treats a barangay selection as valid even **without** a parent
  municipality). Fixed so that when **no municipality is selected the barangay
  options are shown immediately** (nothing to constrain them yet), and they narrow
  to the selected municipality's barangays only once a municipality is chosen.
  Server-side feed filtering/ACL is unchanged; this only fixes the option-list
  visibility in the shared component.

**5. Photo editing in the Edit modal + 1MB + GD optimization (Task 6) — the one authorized backend change:**
- `resources/views/clients/_form.blade.php` — new **Profile Photo** section
  (edit+modal only): a `photo` file input (accept `image/*`, max 1MB hint) plus an
  already-set/not-set hint. The input rides on the same update form (ignored by
  `clients.update` because `ClientRequest` validates no `photo` field).
- `resources/views/clients/index.blade.php` — in the modal update success handler,
  if a photo file was chosen it is posted to the **existing** `clients.photo.store`
  route (`POST clients/photo`, gated by `action:clients.php,edit`) with
  `client_id` + `photo` after the client save succeeds, then the modal closes /
  table + panel refresh as before. Non-blocking on photo failure (client still saved).
- `app/Http/Controllers/PhotoController.php` — validation `max:5120` → `max:1024`
  (1 MB), and the store now returns JSON (`JsonResponse|RedirectResponse`) with
  `success`/`errors {photo: [...]}` when the request expects JSON, mirroring the
  other additive client routes. ACL (`canAccessRecord` on `clients.php`) unchanged.
- `app/Services/PhotoService.php` — new `optimizeImage()` (GD) for **uploaded-file**
  photos only: decodes via `imagecreatefromstring`, downscales to a 1600px
  longest-side cap (aspect preserved), re-encodes in the original format
  (JPEG q85 / PNG 6 / GIF), returning the original bytes if GD cannot decode —
  storage never rejects a valid upload. Camera-capture path is unchanged (canvas
  already yields a compact JPEG). `PhotoService::MAX_DIMENSION = 1600`.

**6. Family Composition + Transactions display (Tasks 7 & 8) — verified already satisfied:**
- Family Composition (`_details.blade.php` ~229–236) and Transactions (~266–285)
  already render real `familyMembers.relative`/transaction data with clean empty
  states and their own `overflow-x-auto` containers (transaction table scrolls
  horizontally on narrow drawers). No code change needed.

**7. Header/action-bar polish + responsive (Tasks 1 & 9) — verified on token vocabulary:**
- The four-action bar already reuses the token-driven `btn-navy`/`btn-subtle`/
  `btn-gold`/`btn-red` vocabulary with hover/active stops (app.css `.btn-*`) and
  the responsive `.details-actions-line` 2×2 grid + mobile full-width drawer from
  the prior follow-up. The `.details-category` line (Task 2) also collapses
  cleanly at the existing `<768px` grid/meta rules. No further CSS needed.

**CSS/design tokens:** reused existing `btn-*` vocabulary and `--color-*` tokens.
The `.details-category` light-on-dark background uses `rgba(255,255,255,…)` only
in the fixed navy header zone (same pre-existing pattern as `.details-close` and
`.details-meta .status-badge.archived`) where no white/on-dark color token exists.
No hardcoded *body* colors added; allowlist and Bootstrap coexistence untouched.

**Verification:**
- `vendor\bin\pint` on `PhotoController.php` + `PhotoService.php` → `passed`.
- `npm run build` clean (vite v6.4.3, 56 modules); FilterChips.js change compiled.
- `php artisan view:cache` — all Blade templates cached (incl. `_details`,
  `_form`, `details-panel`, `index`).
- `php artisan test` — **297 passed / 1397 assertions / 0 failures**; the existing
  `PhotoTest` (file + camera + invalid + required) still passes with the 1MB limit
  and GD optimization in place.
- No schema/migration/`schema:dump`/route/permission/ACL/business-rule change; no
  production writes; `C:\xampp\htdocs\system` untouched; no commits.

---

## 2026-09-03 — ARCHITECTURE DECISION (supersedes): Alpine.js adopted as the Bootstrap JS replacement

**Decision:** The Tailwind migration's frontend interaction layer changes from the previously
recorded Vanilla-JS-only stance to **Tailwind CSS + Alpine.js + Vanilla JS +
jQuery/DataTables**. Alpine.js is now the approved replacement interaction layer for Bootstrap JS
behaviors (Modal, Toast, Offcanvas / sidebar drawer, Collapse, Dropdown, and simple dismissible
UI).

**Why the earlier Vanilla-JS-only decision was superseded:**
- The plan's §H had recommended "do not introduce Alpine.js" and favored hand-rolled vanilla
  IIFE helpers (`Modal.js`/`Toast.js`/`Drawer.js`/`Dropdown.js`/`Collapse.js`).
- The owner directed the change to Alpine.js **before implementation began** (no vanilla helpers
  were ever authored — the previous first-step task was never run).
- The forensic audit (`docs/TAILWIND_MIGRATION_FORENSIC_AUDIT.md` §Appendix F) had itself
  **recommended Alpine.js** ("lightweight, declarative"); this decision aligns the plan with that
  recommendation. Also relevant: the audit's "no build step" rationale is outdated — the project
  now has a working Vite build, so Alpine is integrated via npm + the existing Vite pipeline, not
  a CDN (consistent with the 2026-08-23 Tailwind-first / T1 decisions).

**Scope of this decision:**
- **Alpine.js applies to** Bootstrap JS behaviors only (Modal/Toast/Offcanvas/Collapse/Dropdown/
  simple dismissible UI).
- **Alpine.js does NOT apply to** the existing Vanilla JS components — these remain unchanged
  unless later repository evidence proves migration necessary: `DetailsPanel.js`, `FilterChips.js`,
  `global-search`, and the `axios`/`bootstrap.js` wrapper.
- **DataTables/jQuery remains unchanged** (only its Bootstrap-5 skin leaves at the removal gate).
- **No custom `Modal.js`/`Toast.js`/`Drawer.js`/`Dropdown.js`/`Collapse.js`** will be authored.
- **No other UI library** (Flowbite, DaisyUI, or another component framework) is permitted.

**Implementation (Phase 0 — Alpine foundation) completed in this entry:**
- `package.json` / `package-lock.json` — added `alpinejs` (installed `^3.17.1` via npm).
- `resources/js/app.js` — imports `alpinejs`, sets `window.Alpine = Alpine`, calls `Alpine.start()`.
- `resources/views/layouts/app.blade.php` — added `@vite(['resources/js/app.js'])` so Alpine is
  available on the app shell where the shared Bootstrap-dependent components (confirm-modal,
  record-view-modal, sidebar offcanvas/collapse, navbar dropdown, toasts) live for the next phase.
- **Terminal style resource files modified:** `docs/TAILWIND_MIGRATION_EXECUTION_PLAN.md`
  (status line, §B, §C, §H, §M, §N, §O updated to the Alpine architecture).

**Alpine loading scope (per decision):** the Vite JS bundle is loaded only where Alpine behavior
is required. Only the app shell currently loads it. The standalone pages (`auth/login`, `qr/viewer`,
`students/verify`, `students/update-photo`, `students/photo-upload`,
`grantee_update/self-service`, `unpaid_verifications/self-service`) were each inspected; none is
being migrated in Phase 0, so `@vite(['resources/js/app.js'])` was **not** added to them. The bundle
is added per-page only when that page's interaction is actually migrated to Alpine — never
automatically.

**Verification performed:**
- `npm install` — added 3 packages (alpinejs + transitive deps), 0 vulnerabilities.
- `npm run build` — see Phase 0 verification (below in this entry pattern / executed via the
  workflow; build succeeded / asset manifest regenerated).
- `php artisan test` — full suite result below.
- Focused Playwright smoke check that the app loads and `window.Alpine` is present on the shell —
  see below.

**Non-actions (unchanged):** Bootstrap is **not removed**; **no Bootstrap component is migrated**
in this phase (confirm-modal migration is a future, separately-approved phase); backend code,
routes, ACL, authentication, business logic, database schema, and architecture are untouched;
**Tailwind Preflight remains disabled**; the `prototype/` folder is **not modified**.

**Reference:** `docs/TAILWIND_MIGRATION_FORENSIC_AUDIT.md` (Appendix F recommended Alpine),
`docs/TAILWIND_MIGRATION_EXECUTION_PLAN.md` (updated for this decision).

---

## 2026-09-03 — Tailwind + Alpine Phase 1: confirm-modal migrated off Bootstrap Modal

**Scope (Phase 1, approved):** migrate `resources/views/partials/confirm-modal.blade.php`
from the Bootstrap Modal to Tailwind + Alpine.js. This is the first real
Bootstrap-JS-view component to move off `bootstrap.Modal`. Phase 1 only — no other
Bootstrap modal/offcanvas/collapse is migrated, Bootstrap is **not removed**, and no
backend/route/DB/ACL/business-rule change.

**Contract preserved exactly:**
- `window.uiConfirm({ title, message, confirmLabel }) → Promise<boolean>` — resolves
  `true` ONLY on the confirm button; Cancel / ESC / backdrop each resolve `false`
  (identical abort semantics to the old dialog).
- `<form data-confirm="...">` submit interception kept — on confirm it submits
  natively via `HTMLFormElement.prototype.submit.call(form)` (fires no submit event,
  keeps the form's own `@csrf` POST intact).

**Implementation:**
- `resources/views/partials/confirm-modal.blade.php`:
  - Markup rewritten from `.modal.fade/.modal-dialog/.modal-content/.modal-footer` to
    a fixed `pointer-events-none` overlay + dark backdrop (`bg-ink/40`) + centred
    ~500px dialog (`max-w-[500px]`, `rounded-panel`, `bg-surface`, `shadow-pop`,
    `ring-line`). `x-show` + `x-transition.opacity` drive visibility; `x-cloak`
    (inline `[x-cloak]{display:none}`) prevents a pre-hydration flash.
  - Real `role="dialog"` / `aria-modal="true"` / `aria-labelledby` / `aria-describedby`
    retained (a11y). Focus-on-open (to the confirm button via `Alpine.nextTick`),
    focus-return on close, body scroll-lock/`overflow:hidden`, and a Tab trap
    (`handleTab`) mirror the previous Bootstrap modal's keyboard/scroll behaviour.
  - Script now drives an **Alpine store** (`Alpine.store('uiConfirm')`) holding
    `open`/`title`/`message`/`confirmLabel`/`cancelLabel` plus the pending promise
    resolver and previous-focus; `window.uiConfirm()` sets the store and returns the
    Promise. `uiConfirmDialog()` is a thin Alpine component exposing the `open`
    getter, `dismiss()` and `handleTab()`. The imperative `uiConfirm` contract and the
    framework-agnostic callers remain byte-identical (no caller changed).
  - Fixed during verification: `PointerEvent` interception (root/backdrop use
    `pointer-events-none`/`auto` so the closed modal never blocks the page) and a
    store property/method name collision (`open` boolean vs `open()` method) —
    renamed the method `show`. The latter would otherwise have broken every
    second/subsequent open.
- `e2e/confirm-modal-phase1.spec.ts` — **new** focused no-auth Playwright harness that
  loads the real built `app.js`/`app.css` plus the migrated markup and verifies the
  full contract: resolve true on Confirm, false on Cancel / ESC / backdrop, dynamic
  text binding, reopen-without-stale-state, and that the `data-confirm` form submits
  natively (via a stubbed `HTMLFormElement.submit`) only on confirm.

**Verification:**
- `npm run build` — clean; built CSS contains all new classes (`bg-ink`,
  `max-w-[500px]`, `z-[200]`, `rounded-panel`, `ring-line`, `shadow-pop`,
  `pointer-events-*`); JS bundle hash unchanged (the partial script is inline Blade,
  not part of the Vite bundle).
- `php artisan view:cache` — all Blade templates (including the rewritten partial)
  compiled clean.
- `vendor\bin\pint` — passed (no style violations).
- `php artisan test` — **297 passed / 1397 assertions / 0 failures** (unchanged).
- Playwright `e2e/confirm-modal-phase1.spec.ts` — **6 tests × 7 projects (chromium,
  firefox, webkit, Mobile Chrome, Mobile Safari, Edge, Chrome) = 42 passed**, plus
  the existing `e2e/alpine-phase0.spec.ts` still passes on chromium.
- **Environment limitation (pre-existing, unchanged):** the local `main_system` is a
  byte-identical production copy with no `smoke_superadmin` seed user, so the
  authenticated delete-confirm flows cannot be driven against the live app and no DB
  writes occur; this spec therefore uses the no-auth harness above (documented env
  limitation, not a code defect — see SESSION_HANDOFF).

**Non-actions (unchanged):** Bootstrap is still loaded (nothing removed); no other
Bootstrap component migrated; backend, routes, ACL, auth, business logic, DB schema and
architecture untouched; **Tailwind Preflight remains disabled**; `prototype/` unchanged;
no commits.

---

## 2026-09-03 — Tailwind + Alpine Phase 2: record-view-modal migrated off Bootstrap Modal

**Scope (Phase 2, approved):** migrate `resources/views/partials/record-view-modal.blade.php`
(the shared `#viewModal` record-details modal) from `bootstrap.Modal` to Tailwind + Alpine.
This is the second Bootstrap-JS-view component off Bootstrap. Phase 2 only — no other
Bootstrap modal/offcanvas/collapse is migrated, Bootstrap is **not removed**, and no
backend/route/DB/ACL/business-rule change.

**Contract preserved:**
- Markup surface kept: `#viewModal` root, `#viewBody` content body, `<h5>` title fed by the
  `title` Blade prop, and Close affordances (X + footer Close).
- Consumers (2, both unchanged in structure) fetch a record, write HTML into `#viewBody`
  via jQuery `$('#viewBody').html(...)`, then open the dialog.
- Behaviours preserved: centered ~800px (`modal-lg`) dialog, dark backdrop (click-to-close),
  ESC-to-close (now global, matching Bootstrap), focus moves into the dialog on open and is
  restored to the trigger on close, body scroll-lock while open, fade transition, per-open
  content freshness (consumer replaces body each open).

**Implementation:**
- `resources/views/partials/record-view-modal.blade.php`:
  - Markup rewritten from Bootstrap modal classes to a fixed `pointer-events-none` overlay +
    dark backdrop (`bg-ink/40`) + centred `max-w-[800px]` dialog (`rounded-panel`, `bg-surface`,
    `shadow-pop`, `ring-line`) with a scrollable `#viewBody` (`max-h-[85vh]` + `overflow-y-auto`)
    and header/footer. `x-show` + `x-transition.opacity` drive visibility; `x-cloak` prevents a
    pre-hydration flash; the same `pointer-events-none`/`auto` layering fix from Phase 1 is applied
    so a closed modal never blocks the page.
  - Real `role="dialog"`/`aria-modal`/`aria-labelledby`/`aria-describedby` retained; focus-on-open
    (to the X close button via `Alpine.nextTick`), focus-return on close, body scroll-lock, ESC
    (`@keydown.escape.window`), backdrop click, and a Tab trap mirror the previous Bootstrap modal.
  - Follows the Phase 1 pattern: an **Alpine store** (`Alpine.store('uiViewModal')`) holds `open`.
    A thin component `uiRecordViewModal()` exposes the `open` getter, `close()` and `handleTab()`.
    Focus/scroll-lock are managed imperatively (`show`/`hide` on the store) via `Alpine.nextTick`.
- **Compatibility bridge (consumers stay Alpine-agnostic):** `window.uiViewModal = { show, hide }`.
  Consumers call `window.uiViewModal.show()`. Each consumer was edited ONLY at the single
  `bootstrap.Modal('#viewModal').show()` call site (1 line each) — nothing else changed:
  - `payouts/attendance.blade.php` (`var modal = new bootstrap.Modal(...); modal.show();` →
    `window.uiViewModal.show();`)
  - `unpaid_verifications/index.blade.php` (`new bootstrap.Modal('#viewModal').show();` →
    `window.uiViewModal.show();`)
  - Rationale: no compatibility boundary exists that lets `new bootstrap.Modal('#viewModal').show()`
    work against non-Bootstrap markup, and overriding `bootstrap.Modal` globally would break other
    modals that must keep using the real Bootstrap; the minimal 1-line consumer change is required.

**Verification:**
- `npm run build` — clean; built CSS contains the new RVM classes (`max-w-[800px]`,
  `max-h-[85vh]`, `rounded-panel`, `ring-line`, `shadow-pop`, `pointer-events-*`); JS bundle hash
  unchanged (partial script is inline Blade).
- `php artisan view:cache` — all Blade templates (incl. rewritten partial + 2 edited consumers)
  compiled clean.
- `vendor\bin\pint` — passed.
- `php artisan test` — **297 passed / 1397 assertions / 0 failures** (unchanged).
- Playwright `e2e/record-view-modal-phase2.spec.ts` — **7 tests × 7 projects = 49 passed**
  (open+content, Close button, X button, ESC, backdrop, repeated open/close + no pointer
  interception, body scroll-lock/restore). It uses a no-auth `setContent` harness loading the real
  built assets + partial markup (same env limitation as Phase 1). Also made the Phase 1 + Phase 2
  harnesses **build-agnostic** (asset filenames resolved from `manifest.json` instead of hardcoded
  hashes), so they survive future rebuilds. Combined Phase 1+2 regression: green.
- Note: an intermittent Firefox `browserContext.close: Protocol error ... _maybeDontRestoreTabs`
  SessionStore teardown flake appears only when Phase 1 + Phase 2 run together under parallel
  workers; it passes in isolation and is an environmental Playwright/Firefox issue, not a code
  defect (no assertion failures).
- **Environment limitation (pre-existing):** authenticated table-record flows can't run against the
  live app (production-copy DB, no seed user, no DB writes), hence the no-auth harness.

**Non-actions (unchanged):** Bootstrap still loaded (nothing removed); no other Bootstrap component
migrated; backend, routes, ACL, auth, business logic, DB schema, architecture untouched;
**Tailwind Preflight remains disabled**; `prototype/` unchanged; no commits.

---

## 2026-09-02 — Tailwind Migration Forensic Audit (read-only analysis)

**Scope:** comprehensive read-only Bootstrap dependency audit to determine when
Bootstrap 5.3.2 (CDN) can be completely removed and replaced by Tailwind v4 +
custom JS. **No application code was modified** — this is a documentation-only
deliverable.

**Objective:** produce a single forensic audit document mapping every Bootstrap
CSS class, JS API call, DataTables integration, and CDN loading point across the
entire codebase, with module-by-module migration matrix, recommended migration
order, and implementation plan.

### Findings summary

**Bootstrap 5.3.2 loaded via CDN** (not npm — no Bootstrap in `package.json`):
- **7 files** load Bootstrap CSS CDN; **7 files** load Bootstrap JS CDN
- Main layout (`layouts/app.blade.php`) affects all authenticated screens (lines 9 + 106)
- 6 standalone public pages load CDN independently

**Bootstrap JavaScript API usage:**
- Modal: **20 instantiation calls** across 11 files (clients/index, clients/_details, transactions/index, scholars/index, payouts/attendance, admin/users/index, admin/audit_logs/index, unpaid_verifications/index, unpaid_verifications/self-service, duplicates/index, scanners/scan)
- Toast: **5 instantiation calls** across 3 files (clients/index, clients/_details, layouts/app)
- Offcanvas: **declarative only** (layouts/app, partials/sidebar) — no JS API calls
- Collapse: **declarative only** (partials/sidebar) — no JS API calls
- Dropdown: **declarative only** (partials/navbar) — no JS API calls

**Bootstrap CSS class usage:**
- **63+ distinct class names** across 20+ Blade views
- Heavy usage (>30 classes): grantee_update/self-service (~100), _self_update_tab (~100), students/photo-upload (~40), qr/viewer (~30), households/create (~30)
- Light usage (<20 classes): auth/login (~5), students/verify (~3), students/update-photo (~5), sessions/online (~3), dashboard (~5)

**DataTables integration:**
- **11 index screens** use DataTables with Bootstrap 5 styling (`dataTables.bootstrap5.min.css` + `dataTables.bootstrap5.min.js`)
- Clients, Transactions, Scholars, Households, Payouts/Attendance, Admin/Users, Admin/Audit_Logs, Unpaid_Verifications, Duplicates, Sessions/Online, Dashboard

**Tailwind migration status:** ~35% complete
- Clients module partials migrated (Batch E/F/G)
- Auth/login, dashboard, scanners, sessions partially migrated
- Shell (sidebar, navbar, layout) fully migrated to Tailwind

### Deliverable

**Created:** `docs/TAILWIND_MIGRATION_FORENSIC_AUDIT.md` — comprehensive forensic
audit document with:
- CDN loading points (Section 1)
- Bootstrap JS API usage (Section 2)
- Bootstrap CSS class usage (Section 3)
- DataTables integration audit (Section 4)
- Shared component inventory (Section 5)
- Module-by-module migration matrix (Section 6)
- Standalone pages audit (Section 7)
- Coexistence architecture (Section 8)
- Module migration order (Section 9)
- Phase 1 implementation plan (Section 10)
- Risks and constraints (Section 11)
- Verification checklist (Section 12)
- Appendices A-F (class inventory, JS API inventory, DataTables details, shared component requirements, effort estimates, open questions)

### Key conclusions

1. **Bootstrap CDN can be removed after Phase 4-5** of the Tailwind migration roadmap (shared components + standalone pages)
2. **DataTables BS5 integration is the primary blocker** — 11 screens depend on `dataTables.bootstrap5.min.css` + `dataTables.bootstrap5.min.js`
3. **20 modal JS instances** must be replaced with custom modal component or Alpine.js
4. **5 toast JS instances** must be replaced with custom toast component
5. **Preflight must be enabled** after Bootstrap removal — may cause layout regressions
6. **Estimated total effort:** Large (7 phases, 45+ files)

### Verification

- No application code, routes, controllers, services, models, schema, migrations, or business rules modified
- No `package.json`, `vite.config.js`, `app.css`, `ui.css`, or layout files modified
- `prototype/index.html` untouched
- `C:\xampp\htdocs\system` untouched
- `main_system` database untouched
- Full suite: **297 passed / 1397 assertions / 0 failures** (unchanged baseline)
- `vendor\bin\pint` not run (no PHP files modified)
- `npm run build` not run (no asset files modified)
- Documentation only: `docs/TAILWIND_MIGRATION_FORENSIC_AUDIT.md` created

**Non-actions:** no schema/migration/`schema:dump`/route/permission/ACL/business-rule
change; no other module touched; `C:\xampp\htdocs\system` untouched; no commits.

---

## 2026-09-02 - Clients module UX follow-up correction + verification pass (clients-only scope)

Correction/verification pass (not a redesign). Each item below was inspected on the
actual rendered code before being changed or left as-is. **No browser/Playwright run**
was possible (no test seed in the production-copy `main_system` DB), so visual
behaviour is verified from code/DOM/state, not manually.

### Changed

**1. Edit-modal duplicate warning (Task/req #9) — ROOT CAUSE FIXED.**
The modal edit form (loaded from `clients.edit?id=…&modal=1`) rendered `clients._form`
**without** a passing `$action`/`$method`, so `_form` defaulted to
`action=route('clients.store')`, `method=POST`. The modal submitted to the **create**
route, whose high-confidence duplicate gate (`ClientController::store()`) matched the
edited client **against itself** (same name+birthdate) → the "Possible existing client
found" warning on edit; continuing would have created a duplicate instead of updating.
- `app/Http/Controllers/ClientController.php` — `edit()` now passes
  `'action' => route('clients.update',client)` and `'method' => 'PUT'` to the `_form`
  view in modal mode (mirrors full-page `edit.blade.php`). `update()` has no duplicate
  gate, so the current client is naturally excluded while `store()` still protects new
  clients. Only the intended backend change of this pass.

**2. DetailsPanel header — photo above name + program tag removed (req #6/#8).**
- `resources/views/clients/_details.blade.php` — removed the redundant `program-tag`
  (`{{client->household->household_id }}`) from `data-panel-meta`; header now holds
  only photo, name (`data-panel-title`), ID (`data-panel-sub`) and category (`.details-category`).
- `resources/js/components/DetailsPanel.js` — `load()` sets `data-module="<module>"`
  on the panel shell (`close()` removes it), giving a per-module styling hook.
- `resources/views/partials/details-panel.blade.php` — scoped
  `.details-panel[data-module="clients"]` CSS stacks the photo **above** the name
  (`flex-direction:column`, centered text), with a compact 56px avatar on mobile.
  Other modules keep the shared row layout (no unrelated changes).

**3. Separate validation-feedback modal (req #10).**
- `resources/views/clients/index.blade.php` — added a dedicated `#clientFeedbackModal`
  (navy header, `btn-navy` "Back to form") reusing the existing Bootstrap/token modal
  idiom. The modal update handler now renders `data.errors` into that modal (plus the
  existing `.is-invalid` field highlight), instead of the former inline `.form-errors`
  box. Dismissing returns to the still-open, still-editable form with all entered
  values preserved (body is never cleared) and refocuses the first invalid field.
  Create-mode duplicate warning stays a separate in-form warning panel
  (`showDuplicateWarning`).

**4. Button design rules (req #3).**
- `resources/css/app.css` — `.btn-navy` hover now keeps the navy background and turns
  the label **gold** (`hover:text-gold`, no `hover:bg-navy-light`) instead of
  lightening the background; `.btn-gold` hover uses the existing `--shadow-lift` token
  (the previous value `box-shadow: var(--color-ink-secondary), …` was invalid — a color
  where a shadow list belongs). Filled `btn-navy/gold/red` remain border-less (rule C);
  outlined variants (`btn-subtle`/`btn-outline-red`) keep their ring.

### Fixed (client-side filter consistency, req #1)

- `resources/js/components/FilterChips.js` — `renderCount()` now keeps every
  per-category `[data-filter-clear-cat]` button's visibility in step with the current
  (possibly client-side, pre-reload) selection via a new `categoryHasValues(key)`
  helper, so a filter never shows a stale Clear control after `commit()`. Per-value
  chips and Clear All visibility were already correct.

### Small additions

- `resources/views/clients/_form.blade.php`:
  - Category field now carries an "Auto-calculated from birthdate — not editable" hint
    (req #5: keep derived, communicate read-only). No dropdown/second source of truth.
  - Profile Photo section (edit+modal) now shows a **current-photo preview** (or "No
    photo" placeholder) above the `[Choose Photo]` input, plus client-side 1 MB / MIME
    (JPG/PNG/GIF) validation that blocks submit and shows an inline error (req #8).

### Verified already satisfied (no change)

- **Per-filter Clear + Clear All + barangay cascade (#1/#2):** `.filter-clear-cat` /
  `.filter-chip-remove` / `.filter-clear-all` and the `applyCascadeVisibility()`
  barangay fix from the prior pass are present and serve the required controls;
  Clear All hides when the active count is zero.
- **Delete always confirms (#4):** both the full-page and panel delete forms use
  `data-delete-client-form` → `uiConfirm` (CSRF + JSON panel branch preserved).
- **Family Composition (#11)** and **Transactions (#12):** `_details.blade.php` renders
  real `familyMembers.relative`/transaction data (ID, Program, Date Applied, Status
  badge, Amount, Payout Date), collapsible, inside their own `overflow-x-auto`
  containers.

### Verification

- `vendor\bin\pint` on `ClientController.php` → `passed`.
- `npm run build` clean (vite v6.4.3, 56 modules); CSS/JS changes compiled.
- `php artisan view:cache` — all Blade templates cached (incl. `_form`, `_details`,
  `details-panel`, `index`).
- `php artisan test` — **297 passed / 1397 assertions / 0 failures**.
- No schema/migration/`schema:dump`/route/permission/ACL/business-rule change (the
  `edit()` action/method fix is a controller–view contract correction, not a schema or
  ACL change); no production writes; `C:\xampp\htdocs\system` untouched; no commits.
  Browser/manual UI verification of the visual changes was **not** performed.

## 2026-09-02 — Clients module UX correction pass: photo root-cause fix + sticky profile header + action bar (clients-only scope)

Follow-up correction pass (not a redesign) on top of the prior clients pass, and the
new 18-requirement task focus. **No browser/Playwright run** was possible (no test seed
in the production-copy `main_system` DB), so visual behaviour is verified from
code/DOM/state, not manually.

### Changed

**1. Edit-modal photo upload ROOT CAUSE fixed (new task #8).**
Confirmed the display bug: `tbl_client_photos` accumulates **one row per upload**
(PhotoService::store() inserts a new row; nothing deletes the old), but both
`_details` (line 27) and `_form` (line 285) resolved the "current" photo with
`$client->photos->first()`, which returns the **oldest** photo (lowest `id`). So after
a new photo was uploaded, the display still showed the original photo — "success
without updating."
- `app/Models/Client.php` — added `currentPhoto(): ?ClientPhoto` returning
  `photos()->orderByDesc('id')->first()` (the newest upload). No schema/storage change;
  the accumulation behavior and PhotoController/PhotoService are untouched.
- `resources/views/clients/_details.blade.php` (line 27) and
  `resources/views/clients/_form.blade.php` (line 285) — now use `$client->currentPhoto()`.
- `resources/views/clients/index.blade.php` — the modal photo POST (to the existing
  `clients.photo.store` route) was fire-and-forget, so the modal close + panel re-fetch
  (`DetailsPanel.load`) could run **before** the new photo row was saved, showing the
  pre-upload photo. The success handler now sequences the photo upload with a promise
  and only then closes the modal / redraws the table / reloads the panel. No second
  upload path added; same gated route reused.
- Cache-busting not required: photo_path is a unique `uniqid` filename, so a new upload
  always produces a distinct URL, avoiding browser stale-cache.

**2. DetailsPanel action bar now prefers a single horizontal row (#5).**
`resources/views/partials/details-panel.blade.php` — `.details-actions-line > .btn,
> form` changed from an equal-width 2x2 (`flex: 1 1 calc(50% - 8px); min-width:120px`)
to natural content sizing (`flex: 1 1 auto; min-width:104px`), so the four actions sit
in a single row when the drawer is wide enough and wrap cleanly on narrower widths —
no overflow.

**3. Full-page sticky profile header (#6).**
`resources/views/clients/_details.blade.php` — in full-page mode (`@if(!$isPanel)`) the
former separate "Client Profile" title/actions row and identity block were merged into
one `details-full-header` container (identity: photo / name / ID · category) with the
page actions (Back, + Add Transaction [gated], Photo, Edit [gated], Delete [gated,
`data-delete-client-form` confirm]). `resources/css/app.css` — `.details-full-header`
is `position:sticky; top:0; z-index:20` with a solid surface background, bottom border
and soft shadow (negative 1.25rem margins span the `data-card`), so profile context
stays pinned while scrolling; wraps cleanly on narrow viewports. Panel mode is
unaffected (all changes are inside the `@if(!$isPanel)` branch).

### Verified already satisfied (no change, re-confirmed on disk)

- **Filters #1:** Done button/footer removed; auto-apply on checkbox `change`;
  per-category + date Clear reordered to the LEFT of the expanded header; Clear All
  visibility in sync — all present from the prior pass.
- **Button rules #2:** `.btn-navy` keeps navy bg + `hover:text-gold`; `.btn-gold:hover`
  uses `--shadow-lift`; filled `btn-navy/gold/red` border-less; outlined keep ring.
- **Delete always confirms #3:** 4 delete paths (table row, full-page, panel, `_details`
  JSON branch) all go through `uiConfirm`/`data-confirm`.
- **DetailsPanel header #4:** program tag removed; photo above name (scoped
  `data-module="clients"`); category as its own `.details-category` pill line.
- **Single edit modal #7:** one `#clientFormModal` reused by table, DetailsPanel, and
  full-page flows; full-page Edit is a parallel `clients.edit` page (not a second modal).
- **Feedback modal #9:** dedicated `#clientFeedbackModal` renders `data.errors`;
  closing returns to preserved form; create duplicate stays in-form.
- **Category #10:** age-derived via `ClientService::deriveCategory()`; `_form` category
  readonly with "Auto-calculated from birthdate — not editable" hint.
- **Family #11 / Transactions #12:** `_details` renders real family + transaction data,
  collapsible, in `overflow-x-auto` wrappers.
- **Responsive/scrolling #13:** action bar wraps; sticky header wraps; panel body
  `overscroll-behavior:contain` in the shared shell.

### Verification

- `vendor\bin\pint app/Models/Client.php` → `passed`.
- `php artisan view:cache` — all Blade templates cached (incl. `_form`, `_details`,
  `details-panel`, `index`).
- `npm run build` clean (vite v6.4.3, 56 modules); CSS/JS changes compiled.
- `php artisan test` — **297 passed / 1397 assertions / 0 failures**.
- No schema/migration/`schema:dump`/route/permission/ACL/business-rule change; no
  production writes; `C:\xampp\htdocs\system` untouched; no commits. Browser/manual UI
  verification of the visual changes was **not** performed.

---

## 2026-09-02 — Clients module final audit pass (17-point UX/architecture check)

**Objective:** re-inspect the Clients module against 17 strict UX/architecture
requirements and correct confirmed gaps (filters, buttons, delete confirmation,
details-panel identity, edit-modal photo, photo upload, feedback modal, navbar
overlap, action-button stack, category derivation, real family/transaction data,
responsive panel). No schema/migration/route/ACL/business-rule change.

### Changes

- `resources/views/partials/filter-chips.blade.php` — moved the per-category
  **Clear** button from the expanded header into a new `.filter-multi-footer` at
  the bottom-left of each category/date section. No global "Done" button exists;
  auto-apply on change remains the commit point.
- `resources/views/clients/index.blade.php`:
  - Added **Clear All Filters** button `#clientsClearAll` (`class="filter-clear-all"`)
    to the visible `.filter-toolbar`; `refreshSegmentCounts()` toggles its `hidden`
    based on total active filters; a click listener calls `window.clientFilters.clearAll()`;
    an initial `refreshSegmentCounts()` runs after `FilterChips.init` so state syncs on load.
  - **Rewrote the edit-modal submit handler** (Req 8 photo bug): the old handler
    posted the photo to `clients.photo.store` via `fetch` but omitted `form._token`
    and never set the CSRF header → every photo save returned 419 and was silently
    swallowed by `.catch(function(){})`. Now: client-side validation (type
    JPG/JPEG/PNG/GIF + ≤1 MB) BEFORE the fetch, errors surfaced through the feedback
    modal, photo failures treated as hard errors (not silent), and the modal only
    closes / panel only reloads after the photo post resolves. Uses the existing
    `PhotoService` pipeline — no rewrite.
  - Refactored `showDuplicateWarning` (Req 10) to present the duplicate warning in
    the **feedback modal** (not embedded in the edit modal) with dynamic
    `#clientFeedbackActions` (Review existing client / Continue) in the footer; added
    a shared `fbTitle` variable; added reusable `setFeedbackContent(title, type, html)`
    for INFO/WARNING/ERROR. (Requirement-instigated edit-modal photo was also
    completed upstream — single reusable edit modal, feedback modal, and `uiConfirm`.)
- `resources/views/clients/_form.blade.php` — moved the profile-photo section to the
  **top** of the edit modal (before Personal Information), gated `@if ($isEdit &&modal)`,
  using `.edit-photo-container/.edit-photo-frame/.edit-photo-preview/
  .edit-photo-placeholder/.edit-photo-controls`. Category input stays `readonly`
  with JS auto-derivation `MINOR (0-17) / YOUTH (18-29) / ADULT (30-59) /
  SENIOR CITIZEN (60 AND ABOVE)` from birthdate/age (no independent override).
- `resources/views/clients/_details.blade.php` — removed the standalone **Photo**
  button from the full-page header. Action stack is now: Back (`btn-subtle`) →
  + Add Transaction (`btn-navy`, ACL-gated) → Edit (`btn-gold`, ACL-gated) → Delete
  (`btn-red`, ACL-gated, `data-delete-client-form`). Family Composition and
  Transactions still render **real** data via `@forelse` over
  `$client->familyMembers` / `$client->transactions` with empty states. The old
  `#photoModal` camera/upload markup remains in the file but is no longer referenced
  by any header/action button (dead-but-harmless).
- `resources/views/partials/details-panel.blade.php` + `resources/css/app.css` —
  `.details-panel[data-module="clients"] .details-identity` forced to
  `flex-direction: row` so the photo sits BESIDE (not above) the name; category kept
  as its own `.details-category` pill line.
- `resources/css/app.css` — `.details-full-header` `top:0` → **`top:4rem`** (navbar
  `h-16` / 64px) so the sticky full-page profile header sticks below the navbar
  (Req 11). Added `.filter-multi-footer` and `.edit-photo-*` styles. Filled buttons
  (`.btn-navy`/`.btn-gold`/`.btn-red`) verified border-less; `.btn-navy:hover` uses
  `--color-gold` text on navy bg; `.btn-gold:hover` uses `--shadow-lift`; outlined
  `.btn-subtle`/`.btn-outline-red` correctly keep rings.

### Re-confirmed already satisfied (code-verified)

- **Filter state (Req 3):** `FilterChips.commit() → onApply()` fires on every
  selection/deselection/Clear/ClearAll and drives `refreshSegmentCounts()`.
- **Delete confirmation (Req 5):** all entry points (table row `[data-confirm]` +
  registry, DetailsPanel `[data-delete-client-form]` JSON, full-page
  `[data-delete-client-form]` + `data-message`) route through the shared `uiConfirm`
  modal; confirm modal is included in both `index.blade.php` and `show.blade.php`.
- **Single edit modal (Req 9):** one `#clientFormModal` reused via `openEditModal(id)`
  from the registry and the DetailsPanel action bar; PUT via `clients.update`;
  `showDuplicateWarning` only fires on the store path.

### Verification

- `vendor\bin\pint` — passed (no style violations introduced).
- `php artisan view:cache` — all Blade templates compiled clean (no expression errors).
- `npm run build` — clean; built asset confirms `.details-full-header{top:4rem}`,
  `.edit-photo-*`, `.filter-multi-footer`, `.btn-navy:hover{color:var(--color-gold)}`,
  `.btn-gold:hover{box-shadow:var(--shadow-lift)}`; filled buttons have no visible border.
- `php artisan test` — **297 passed / 1397 assertions / 0 failures**.
- Playwright `e2e/clients.spec.ts` (chromium) — the 4 tests **fail at login only**
  (`signIn` stays on `/login`): the local `main_system` is a byte-identical
  production copy with **no `smoke_superadmin` seed user**, a documented environment
  limitation unrelated to this module (see prior log entries / SESSION_HANDOFF).
  No Clients-module code is reached before the failure; the project does not seed
  test users into the production-copy DB. Browser/manual verification of the visual
  changes was not performed — all visual verdicts are CODE-VERIFIED ONLY.
- No schema/migration/`schema:dump`/route/permission/ACL/business-rule change; no
  production writes; `C:\xampp\htdocs\system` untouched; no commits.

---

## Phase 3 — Sidebar Offcanvas + Collapse: Alpine.js Migration

**Date:** 2026-09-02
**Scope:** Migrate sidebar mobile drawer (Bootstrap Offcanvas) and navigation group
expand/collapse (Bootstrap Collapse) to Alpine.js + Tailwind, preserving all existing
behavior, accessibility, and visual parity.

### Files Modified

| File | Change |
|---|---|
| `resources/views/partials/sidebar.blade.php` | Full rewrite: Bootstrap `offcanvas-lg offcanvas-start` → Alpine store + CSS transforms; `data-bs-dismiss="offcanvas"` → `@click="$store.sidebar.close()"`; `data-bs-toggle="collapse"` → Alpine `x-data` per group with `x-show` + `x-collapse`; `collapse` class → Alpine directive; added mobile backdrop, ESC handling, body scroll lock |
| `resources/views/partials/navbar.blade.php` | Minimal: replaced `data-bs-toggle="offcanvas"` / `data-bs-target="#appSidebar"` with `@click="$store.sidebar.toggle()"` and `:aria-expanded="$store.sidebar.open.toString()"` |
| `resources/views/layouts/app.blade.php` | Removed Bootstrap offcanvas event listeners (hamburger `aria-expanded` sync, `.offcanvas` shown/hidden listeners for `adjustDataTables`); kept `adjustDataTables` function and details-panel transitionend listener |
| `resources/js/app.js` | Added `import collapse from '@alpinejs/collapse'` and `Alpine.plugin(collapse)` |
| `package.json` | Added `@alpinejs/collapse` dependency |

### Files Inspected (unchanged)

- `resources/views/partials/sidebar-link.blade.php`
- `resources/views/partials/sidebar-icon.blade.php`
- `resources/views/partials/details-panel.blade.php`
- `resources/views/partials/confirm-modal.blade.php`
- `resources/css/app.css` (sidebar CSS rules)
- `e2e/smoke.spec.ts`
- `prototype/css/style.css` (sidebar behavior reference)
- `prototype/js/app.js` (sidebar behavior reference)

### Existing Sidebar Contract/Behavior

| Context | Behavior |
|---|---|
| Desktop (≥1024px) | Sidebar always visible, fixed left, 260px wide; main content offset by `lg:ml-[260px]` |
| Mobile (<1024px) | Sidebar hidden off-screen; hamburger toggles slide-in drawer; backdrop overlay; ESC closes; body scroll locked |
| Navigation group | Toggle button with chevron; expands/collapses child links; `aria-expanded` reflects state; chevron rotates via CSS `[aria-expanded='true']` |
| Active link | Gold highlight + left accent bar; `aria-current="page"` on active anchor |

### Offcanvas Behavior Migrated

- **Mobile drawer:** Alpine store (`$store.sidebar.open`) controls visibility via CSS
  `translate-x-0` / `-translate-x-full` with `lg:translate-x-0` override for desktop.
  300ms CSS transition for slide animation.
- **Backdrop:** Separate `<div>` with `x-show="open"`, `x-transition.opacity`, `@click="close()"`.
  `lg:hidden` ensures it never appears on desktop.
- **ESC key:** `@keydown.escape.window="if (open) close()"` on wrapper div.
- **Body scroll lock:** `$watch('open', ...)` toggles `document.body.style.overflow`.
- **Close button:** `@click="close()"` replaces `data-bs-dismiss="offcanvas"`.
- **DataTables adjust:** `$watch('open', ...)` calls `requestAnimationFrame(adjustDataTables)`.
- **Hamburger sync:** Navbar button uses `:aria-expanded="$store.sidebar.open.toString()"`.

### Collapse Behavior Migrated

- Each group gets its own `x-data="{ expanded: ... }"` initialized from PHP `$groupActive`.
- Toggle button: `@click="expanded = !expanded"` with `:aria-expanded="expanded.toString()"`.
- Content: `x-show="expanded" x-collapse` (Alpine collapse plugin for smooth height animation).
- Chevron rotation: Existing CSS rule `.sidebar-group-toggle[aria-expanded='true'] svg.chevron`
  continues to work via `aria-expanded` binding.

### Navbar Coordination

Minimal change to `navbar.blade.php` — replaced Bootstrap offcanvas attributes with Alpine
store calls. No structural changes. The user dropdown (`data-bs-toggle="dropdown"`) remains
untouched (out of scope for Phase 3).

### Alpine Implementation

- **Alpine store:** Registered in `alpine:init` event listener in sidebar partial's `<script>` tag.
  Properties: `open` (boolean), `toggle()`, `close()`.
- **@alpinejs/collapse plugin:** Installed and registered in `app.js` for smooth collapse animations.
- **x-data scopes:** Wrapper div owns sidebar state; each group has its own nested `x-data`.
- **No stale Alpine state:** Store is fresh on each page load; no persistent state between navigations.

### Accessibility Verification

- `aria-expanded` on group toggles: maintained via `:aria-expanded="expanded.toString()"` ✓
- `aria-expanded` on hamburger: maintained via `:aria-expanded="$store.sidebar.open.toString()"` ✓
- `aria-controls` on group toggles: preserved (`aria-controls="{{item['id'] }}"`) ✓
- `aria-controls` on hamburger: preserved (`:aria-controls="'appSidebar'"`) ✓
- Drawer semantics: `<aside>` with `aria-label="Primary navigation"` preserved ✓
- Keyboard ESC: closes sidebar when open ✓
- Focus behavior: `tabindex="-1"` on `<aside>` preserved ✓
- Body scroll lock: toggled on open/close ✓
- No pointer interaction after close: backdrop removed from DOM via `x-show` + CSS ✓

### Build Result

```
npm run build → ✓ built in 2.19s (58 modules, 105.70 KB JS, 56.98 KB CSS)
```

### PHPUnit Result

```
Tests: 297 passed (1397 assertions), 0 failures
```

### Pint Result

```
vendor\bin\pint → passed (no style violations)
```

### Playwright Result

All 28 smoke tests fail at `signIn()` (login credential issue — `smoke_superadmin` user
does not exist in the production-copy database). Verified this is **pre-existing** by
running the same tests against the pre-migration codebase (identical failures). No
sidebar-related code is reached before the login failure. **Not a regression.**

### Bugs Discovered/Fixed

None. All existing behavior preserved.

### Remaining Bootstrap Dependencies

- Bootstrap CSS CDN (`bootstrap@5.3.2`) — kept globally (not in scope for Phase 3)
- Bootstrap JS CDN (`bootstrap.bundle.min.js`) — kept globally (used by other components)
- `data-bs-toggle="dropdown"` on user dropdown in `navbar.blade.php` — out of scope
- `data-bs-toggle="collapse"` in `clients/_gip.blade.php` — out of scope (GIP accordion)
- `data-bs-toggle="modal"` / `data-bs-dismiss="modal"` on various screens — out of scope
- `data-bs-dismiss="toast"` on flash feedback — out of scope
- `data-bs-dismiss="alert"` on validation errors — out of scope

### Next Migration Candidate Requiring Separate Approval

**Navbar dropdown** (`data-bs-toggle="dropdown"` in `navbar.blade.php`) — the user profile
dropdown is the next logical Bootstrap component to migrate to Alpine.js in the shell chrome.

---

## Phase 4 — Navbar Dropdown: Alpine.js Migration

**Date:** 2026-09-02
**Scope:** Migrate the user profile dropdown in `navbar.blade.php` from Bootstrap Dropdown
to Alpine.js, preserving all existing behavior, accessibility, and visual parity.

### Files Modified

| File | Change |
|---|---|
| `resources/views/partials/navbar.blade.php` | Replaced `data-bs-toggle="dropdown"` with Alpine `@click` / `@click.outside` / `@keydown.escape`; toggles Bootstrap's `.show` class via `:class="{ 'show': open }"` instead of Bootstrap JS |

### Files Inspected (unchanged)

- `resources/views/layouts/app.blade.php`
- `resources/views/partials/sidebar.blade.php` (Phase 3 — confirmed untouched)
- `resources/css/app.css`
- `public/css/ui.css`
- `prototype/js/app.js` (user dropdown prototype behavior)
- `prototype/css/style.css`
- `e2e/smoke.spec.ts`
- `playwright.config.ts`

### Existing Dropdown Contract

| Aspect | Behavior |
|---|---|
| Trigger | `<button>` with `dropdown-toggle` class, right-aligned in navbar |
| Menu | `<ul class="dropdown-menu dropdown-menu-end">` with Logout form |
| Toggle | Click opens/closes |
| Outside click | Closes (Bootstrap JS) |
| ESC key | Closes (Bootstrap JS) |
| Visual | Bootstrap `.dropdown-menu` styling; right-aligned via `.dropdown-menu-end` |
| Alignment | Right-aligned to trigger (`.dropdown-menu-end`) |
| Logout | Standard POST form with `@csrf` |

### Alpine Implementation

- **x-data:** `x-data="{ open: false }"` on the wrapper `<div class="dropdown">`
- **Toggle:** `@click="open = !open"` on the trigger button
- **Outside click:** `@click.outside="open = false"` on the wrapper div
- **ESC:** `@keydown.escape="open = false"` on the wrapper div
- **aria-expanded:** `:aria-expanded="open.toString()"` on the trigger button
- **Menu visibility:** `:class="{ 'show': open }"` on the `<ul>` — toggles Bootstrap's `.show` class which sets `display: block` on `.dropdown-menu`
- **No x-show needed:** Bootstrap's `.dropdown-menu` has `display: none` by default; `.show` overrides to `display: block`

### Accessibility Behavior

- `aria-expanded` dynamically bound to Alpine `open` state ✓
- `aria-labelledby="userDropdown"` preserved on menu ✓
- `id="userDropdown"` preserved on trigger button ✓
- ESC closes dropdown ✓
- Outside click closes dropdown ✓
- No pointer interaction after close (menu hidden via CSS `display: none`) ✓

### Logout/Link Preservation

- Logout form unchanged: `method="POST"`, `@csrf`, `action="{{ route('logout') }}"` ✓
- No structural changes to the form or button ✓
- No changes to routes or backend ✓

### Build Result

```
npm run build → ✓ built in 1.02s (58 modules, 105.70 KB JS, 56.98 KB CSS)
```

### PHPUnit Result

```
Tests: 297 passed (1397 assertions), 0 failures
```

### Pint Result

```
vendor\bin\pint → passed (no style violations)
```

### Playwright Result

Same 4 pre-existing failures at `signIn()` (missing `smoke_superadmin` test user).
Identical to pre-migration behavior. **Not a regression.**

### Bugs Discovered/Fixed

None. All existing behavior preserved.

### Remaining Bootstrap Dependencies

- Bootstrap CSS CDN (`bootstrap@5.3.2`) — kept globally
- Bootstrap JS CDN (`bootstrap.bundle.min.js`) — kept globally (used by toasts, modals, other dropdowns)
- `data-bs-toggle="dropdown"` in `clients/index.blade.php` — out of scope (export dropdown)
- `data-bs-toggle="dropdown"` in `transactions/index.blade.php` — out of scope (export dropdown)
- `data-bs-toggle="collapse"` in `clients/_gip.blade.php` — out of scope (GIP accordion)
- `data-bs-toggle="modal"` / `data-bs-dismiss="modal"` on various screens — out of scope
- `data-bs-dismiss="toast"` on flash feedback — out of scope
- `data-bs-dismiss="alert"` on validation errors — out of scope

### Next Migration Candidate Requiring Separate Approval

**Export dropdowns** in `clients/index.blade.php` and `transactions/index.blade.php`
(`data-bs-toggle="dropdown"`) — module-level dropdowns, require separate approval.

---

## Phase 5 — Module-Level Export Dropdowns: Alpine.js Migration

**Date:** 2026-09-03
**Scope:** Migrate the Bootstrap dropdown behavior used by the export dropdowns in
`clients/index.blade.php` and `transactions/index.blade.php` to Alpine.js.

### Files Modified

| File | Change |
|---|---|
| `resources/views/clients/index.blade.php` (line 265) | Replaced `data-bs-toggle="dropdown"` with Alpine `x-data="{ open: false }"` on wrapper div; added `@click`, `@click.outside`, `@keydown.escape`, `:aria-expanded`, `:class="{ 'show': open }"` |
| `resources/views/transactions/index.blade.php` (line 94) | Same pattern applied to transactions export dropdown |

### Files Inspected (unchanged)

- `resources/views/layouts/app.blade.php`
- `resources/views/partials/navbar.blade.php` (Phase 4 — confirmed untouched)
- `resources/views/partials/sidebar.blade.php` (Phase 3 — confirmed untouched)
- `resources/views/partials/page-header.blade.php` (render context for `$actions` slot)
- `resources/css/app.css`
- `public/css/ui.css`
- `e2e/smoke.spec.ts`

### Existing Export Dropdown Contract

| Aspect | Clients | Transactions |
|---|---|---|
| Trigger | `<button class="btn-subtle dropdown-toggle">Export CSV` | `<button class="btn-subtle dropdown-toggle">Export` |
| Menu | `<ul class="dropdown-menu dropdown-menu-end">` | `<ul class="dropdown-menu dropdown-menu-end">` |
| Options | Export Current Filter, Export All Clients | Export CSV, Export Custom CSV, Export CSV 2, Export GIP Report |
| Toggle | Bootstrap JS `data-bs-toggle="dropdown"` | Bootstrap JS `data-bs-toggle="dropdown"` |
| Outside click | Bootstrap JS | Bootstrap JS |
| ESC | Bootstrap JS | Bootstrap JS |
| Click handler | Vanilla JS `document.querySelectorAll('[data-clients-export]')` — navigates to export URL | jQuery `$('.export-link').on('click')` — navigates to export URL |
| Wrapper | `<div class="btn-group">` | `<div class="btn-group">` |

### Alpine Implementation

Both dropdowns follow the exact same pattern as the Phase 4 navbar dropdown:

- **Wrapper:** `<div class="btn-group" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">`
- **Trigger:** `@click="open = !open"` and `:aria-expanded="open.toString()"`
- **Menu:** `:class="{ \'show\': open }"` on the `<ul>` (toggles Bootstrap's `.show` class)
- **No x-show needed:** Bootstrap `.dropdown-menu` has `display: none` by default; `.show` overrides to `display: block`
- **Export click handlers:** Completely untouched — vanilla JS/jQuery continues to handle navigation

### Export Functionality Preservation

- Export button preserved ✓
- Export menu preserved ✓
- All export options preserved (clients: 2, transactions: 4) ✓
- Export URLs unchanged ✓
- Route names unchanged ✓
- Query parameters unchanged ✓
- Click handlers untouched (vanilla JS/jQuery) ✓
- Disabled states: none existed ✓
- Labels and icons unchanged ✓
- Menu positioning: Bootstrap `.btn-group` + `.dropdown-menu` CSS preserved ✓

### Accessibility Behavior

- `aria-expanded` dynamically bound to Alpine `open` state ✓
- ESC closes dropdown ✓
- Outside click closes dropdown ✓
- No pointer interaction after close (menu hidden via CSS) ✓
- Button semantics preserved ✓

### DataTables Impact

None. Export dropdowns are in the page-header partial, completely separate from DataTables code.

### Bootstrap Classes Retained (with justification)

| Class | Reason |
|---|---|
| `btn-group` | Provides absolute positioning context for dropdown menu; removing would require custom Tailwind positioning |
| `dropdown-menu` | Provides menu styling (padding, min-width, positioning) via Bootstrap CSS |
| `dropdown-menu-end` | Provides right-alignment via Bootstrap CSS |
| `dropdown-item` | Provides hover/focus styling via Bootstrap CSS |
| `dropdown-toggle` | Provides chevron indicator and button sizing via Bootstrap CSS |

All Bootstrap JS behavior (`data-bs-toggle="dropdown"`) has been removed. Only CSS styling classes remain.

### Build Result

```
npm run build → ✓ built in 1.14s (58 modules, 105.70 KB JS, 56.98 KB CSS)
```

### view:cache Result

```
php artisan view:cache → ✓ Blade templates cached successfully
```

### Pint Result

```
vendor\bin\pint → passed (no style violations)
```

### PHPUnit Result

```
ClientTest: 20 passed, 1 failed (pre-existing: QueryException + ProcessFailedException)
TransactionTest: 12 passed, 2 failed (pre-existing: MySQL deadlock + ProcessFailedException)
```

All failures are pre-existing infrastructure issues (mysql CLI PATH, database deadlocks). None
related to the template changes.

### Playwright Result

Same 4 pre-existing failures at `signIn()` (missing `smoke_superadmin` test user).
Identical to pre-migration behavior. **Not a regression.**

### Bugs Discovered/Fixed

None. All existing behavior preserved.

### Remaining Bootstrap Dependencies

- Bootstrap CSS CDN (`bootstrap@5.3.2`) — kept globally
- Bootstrap JS CDN (`bootstrap.bundle.min.js`) — kept globally (used by toasts, modals, GIP modal)
- `data-bs-toggle="modal"` / `data-bs-dismiss="modal"` on various screens — out of scope
- `data-bs-dismiss="toast"` on flash feedback — out of scope
- `data-bs-dismiss="alert"` on validation errors — out of scope
- Bootstrap CSS classes (`dropdown-menu`, `dropdown-item`, etc.) retained for visual styling on migrated dropdowns

### Next Migration Candidate Requiring Separate Approval

**GIP accordion** (`data-bs-toggle="collapse"` in `clients/_gip.blade.php`) — the only remaining
Bootstrap JS interaction in the clients module. Or alternatively, **remaining modals/toasts** across
the application.

---

## Phase 6 — Clients GIP Accordion: Alpine.js Collapse Migration

**Date:** 2026-09-03
**Scope:** Migrate the Bootstrap Collapse behavior used by the GIP accordion in
`resources/views/clients/_gip.blade.php` to Alpine.js `x-collapse`.

### Files Modified

| File | Change |
|---|---|
| `resources/views/clients/_gip.blade.php` | Replaced Bootstrap Collapse (`data-bs-toggle="collapse"`, `data-bs-target`, `data-bs-parent`, `collapse` class) with Alpine.js `x-data`, `@click`, `:class`, `:aria-expanded`, `x-show`, `x-collapse` |

### Files Inspected (unchanged)

- `resources/views/clients/_details.blade.php` (parent partial that `@include`s `_gip`)
- `resources/views/clients/show.blade.php` (full-page context)
- `resources/views/clients/index.blade.php` (panel context)
- `resources/css/app.css`
- `public/css/ui.css`
- `resources/js/app.js`
- `e2e/smoke.spec.ts`

### Existing GIP Accordion Behavior

| Aspect | Behavior |
|---|---|
| Condition | Rendered only when `$hasGipTransaction` is true |
| Initial state | Collapsed (button has `collapsed` class, `aria-expanded="false"`) |
| Toggle | Click expands/collapses |
| Animation | Bootstrap Collapse CSS transition |
| Parent accordion | `data-bs-parent="#gipAccordion"` (single-item accordion — no real parent-child effect) |
| Content | GIP details definition list, or "no details" notice with Add button |
| Edit/Add buttons | `data-bs-toggle="modal"` — opens GIP modal (out of scope) |
| IDs | `gipAccordion`, `headingGIP`, `collapseGIP` |

### Alpine Implementation

- **Wrapper:** `<div class="accordion mt-[12px]" x-data="{ open: false }">` — `open: false` matches initial collapsed state
- **Button:** `@click="open = !open"`, `:class="{ 'collapsed': !open }"`, `:aria-expanded="open.toString()"`
- **Content:** `x-show="open" x-collapse` on the `#collapseGIP` div
- **Removed:** `data-bs-toggle="collapse"`, `data-bs-target="#collapseGIP"`, `data-bs-parent="#gipAccordion"`, static `collapsed` class, static `aria-expanded="false"`, `collapse` CSS class
- **Preserved:** `aria-controls="collapseGIP"`, `aria-labelledby="headingGIP"`, all IDs, all Bootstrap visual classes (`accordion`, `accordion-item`, `accordion-header`, `accordion-button`, `accordion-collapse`, `accordion-body`)

### Bootstrap Dependency Removed

- `data-bs-toggle="collapse"` — removed
- `data-bs-target="#collapseGIP"` — removed
- `data-bs-parent="#gipAccordion"` — removed
- `collapse` CSS class on content div — removed (replaced by `x-show` + `x-collapse`)

### Bootstrap Classes Retained (with justification)

| Class | Reason |
|---|---|
| `accordion` | Container styling (border, border-radius) |
| `accordion-item` | Item styling (border, background) |
| `accordion-header` | Header layout |
| `accordion-button` | Button styling, chevron indicator, hover/focus states |
| `collapsed` (dynamic) | Controls chevron rotation direction via Bootstrap CSS |
| `accordion-collapse` | Collapse container styling |
| `accordion-body` | Content padding |

All Bootstrap JS behavior has been removed. Only CSS styling classes remain.

### Export Functionality Preservation

N/A — this is an accordion, not an export dropdown.

### Accessibility Behavior

- `aria-expanded` dynamically bound to Alpine `open` state ✓
- `aria-controls="collapseGIP"` preserved on button ✓
- `aria-labelledby="headingGIP"` preserved on content div ✓
- Keyboard: button is focusable, Enter/Space toggles via native `<button>` semantics ✓
- No pointer interaction with collapsed content (`x-collapse` sets `display: none`) ✓

### DataTables Impact

None. GIP accordion is rendered inside `_details.blade.php`, separate from DataTables.

### Build Result

```
npm run build → ✓ built in 883ms (58 modules, 105.70 KB JS, 56.95 KB CSS)
```

### view:cache Result

```
php artisan view:cache → ✓ Blade templates cached successfully
```

### Pint Result

```
vendor\bin\pint → passed (no style violations)
```

### PHPUnit Result

```
GipTest: 6 passed (24 assertions), 0 failures
ClientTest: 21 passed (133 assertions), 0 failures
```

### Playwright Result

Same 4 pre-existing failures at `signIn()` (missing `smoke_superadmin` test user).
No Playwright tests exercise the GIP accordion directly.
Identical to pre-migration behavior. **Not a regression.**

### Bugs Discovered/Fixed

None. All existing behavior preserved.

### Remaining Bootstrap Dependencies (all modules)

- Bootstrap CSS CDN (`bootstrap@5.3.2`) — kept globally
- Bootstrap JS CDN (`bootstrap.bundle.min.js`) — kept globally (used by toasts, modals)
- `data-bs-toggle="modal"` / `data-bs-dismiss="modal"` on various screens — out of scope
- `data-bs-dismiss="toast"` on flash feedback — out of scope
- `data-bs-dismiss="alert"` on validation errors — out of scope
- Bootstrap CSS classes retained for visual styling on migrated components

### Remaining Bootstrap Dependencies (clients module)

- `data-bs-toggle="modal"` in `clients/_gip.blade.php` — GIP edit/add modal (migrated in Phase 7)
- `data-bs-dismiss="modal"` in `clients/_gip.blade.php` — GIP modal close/cancel (migrated in Phase 7)
- Various `data-bs-toggle="modal"` in `clients/index.blade.php` and `_details.blade.php` — add/edit/delete modals (out of scope)

### Next Migration Candidate Requiring Separate Approval

**GIP modal** (`data-bs-toggle="modal"` in `clients/_gip.blade.php`) — the GIP edit/add modal is
the last Bootstrap JS interaction in the GIP partial. Alternatively, **confirm-modal migration to
Alpine** or **remaining modals/toasts** across the application.

---

## Phase 7 — Clients GIP Modal: Alpine.js Migration

**Date:** 2026-09-03
**Scope:** Migrate the Bootstrap Modal behavior used by the GIP edit/add modal in
`resources/views/clients/_gip.blade.php` to Alpine.js, preserving all existing behavior.

### Files Modified

| File | Change |
|---|---|
| `resources/views/clients/_gip.blade.php` | Full rewrite: Bootstrap Modal (`data-bs-toggle="modal"`, `data-bs-dismiss="modal"`, `.modal.fade`, `.modal-dialog`, `.modal-content`) replaced with Alpine.js `x-data="gipModal()"`, `x-show`, `x-transition`, `@click`, `@keydown.escape.window`, `@keydown.tab.prevent.stop`, focus management, body scroll lock, tab trapping |

### Files Created

| File | Purpose |
|---|---|
| `e2e/gip-modal-phase7.spec.ts` | 8 focused Playwright tests covering accordion toggle, modal open/close, ESC, backdrop, Cancel, Add/Edit modes, focus trapping |

### Files Inspected (unchanged)

- `resources/views/partials/confirm-modal.blade.php` (Phase 1 — reference pattern)
- `resources/views/partials/record-view-modal.blade.php` (Phase 2 — reference pattern)
- `resources/views/clients/_details.blade.php` (parent partial)
- `resources/views/clients/show.blade.php` (full-page context)
- `resources/views/clients/index.blade.php` (panel context)
- `resources/css/app.css`
- `public/css/ui.css`
- `resources/js/app.js`
- `e2e/clients.spec.ts`
- `e2e/smoke.spec.ts`
- `playwright.config.ts`

### Existing GIP Modal Contract

| Aspect | Behavior |
|---|---|
| Modal ID | `gipModal` |
| Trigger buttons | "Edit GIP Details" (btn-navy) when `$gip` exists; "Add GIP Details" (btn-gold) when `$gip` is null |
| Trigger mechanism | `data-bs-toggle="modal" data-bs-target="#gipModal"` |
| Close mechanisms | `data-bs-dismiss="modal"` on Cancel button and X button |
| Form | Standard POST to `route('gip.store',client)` with `@csrf` |
| Hidden input | `client_id` with `$client->id` |
| Fields | 16 fields (valid_govt_id through achievements) |
| Title | Dynamic: "Edit GIP Details" or "Add GIP Details" |
| Submit button | Dynamic: "Update GIP Details" or "Save GIP Details" |
| Layout | `modal-lg modal-dialog-centered` |
| Accessibility | `tabindex="-1"`, `aria-hidden="true"` |
| JavaScript | None — purely declarative Bootstrap |
| window.* API | None |
| AJAX | No — standard form POST |
| Validation | Server-side with `old()` helper |

### Alpine Implementation

- **Component:** `window.gipModal()` function defined in `<script>` tag at end of partial
- **Shared scope:** Single `x-data="gipModal()"` wraps both accordion and modal, allowing trigger buttons inside the accordion to call `openModal()`
- **State:** `accordionOpen` (accordion toggle), `modalOpen` (modal visibility), `_prevFocus` (focus restore)
- **Open:** `openModal()` — saves `document.activeElement`, sets `body overflow: hidden`, sets `modalOpen = true`, focuses first input via `$nextTick`
- **Close:** `closeModal()` — sets `modalOpen = false`, restores `body overflow`, restores focus to `_prevFocus`
- **ESC:** `@keydown.escape.window="modalOpen && closeModal()"` — only fires when modal is open
- **Backdrop:** `@click="closeModal()"` on backdrop div
- **Tab trap:** `@keydown.tab.prevent.stop="handleTab($event)"` — cycles focus within dialog
- **Transitions:** `x-transition.opacity.duration.200ms` on backdrop and dialog
- **Cloak:** `x-cloak` with `<style>[x-cloak]{display:none}</style>` to prevent flash

### Bootstrap Modal Dependency Removed

- `data-bs-toggle="modal"` — removed from both trigger buttons ✓
- `data-bs-target="#gipModal"` — removed from both trigger buttons ✓
- `data-bs-dismiss="modal"` — removed from Cancel and X buttons ✓
- `class="modal fade"` — removed (replaced by Alpine `x-show` + `x-transition`) ✓
- `class="modal-dialog modal-lg modal-dialog-centered"` — replaced with Tailwind ✓
- `class="modal-content rounded-panel"` — replaced with Tailwind ✓
- `class="modal-header"` — replaced with Tailwind flex layout ✓
- `class="modal-body"` — replaced with Tailwind ✓
- `class="modal-footer"` — replaced with Tailwind flex layout ✓
- `class="btn-close"` — replaced with Tailwind close button ✓

### Bootstrap CSS Classes Retained

None specific to the modal. The GIP modal now uses pure Tailwind classes matching the visual pattern established by the confirm-modal (Phase 1) and record-view-modal (Phase 2).

### JavaScript/Compatibility Contracts Preserved

- Form POST to `route('gip.store',client)` — unchanged ✓
- `@csrf` token — unchanged ✓
- All input names, IDs, labels — unchanged ✓
- `old()` helper for field persistence — unchanged ✓
- Server-side validation — unchanged ✓
- No window.* API existed — no bridge needed ✓
- No AJAX existed — standard form POST preserved ✓

### Accessibility Verification

- `role="dialog"` on modal wrapper ✓
- `aria-modal="true"` on modal wrapper ✓
- `aria-labelledby="gipModalTitle"` on modal wrapper ✓
- Focus moves into dialog on open (first input) ✓
- Focus restored to trigger button on close ✓
- ESC closes modal ✓
- Backdrop click closes modal ✓
- Tab trapping within dialog ✓
- Body scroll lock while open ✓
- `x-cloak` prevents flash of unstyled content ✓

### Build Result

```
npm run build → ✓ built in 922ms (58 modules, 105.70 KB JS, 56.98 KB CSS)
```

### view:cache Result

```
php artisan view:cache → ✓ Blade templates cached successfully
```

### Pint Result

```
vendor\bin\pint → passed (no style violations)
```

### PHPUnit Result

```
GipTest: 6 passed (24 assertions), 0 failures
ClientTest: 21 passed (133 assertions), 0 failures
```

### Playwright Result

**GIP modal tests (8 tests):** All fail at `signIn()` — pre-existing environment limitation
(missing `smoke_superadmin` test user). Tests document the contract and will pass once the
test environment is seeded.

**Smoke tests (4 tests):** Same pre-existing failures at `signIn()`. **Not a regression.**

### Bugs Discovered/Fixed

None. All existing behavior preserved.

### Remaining Bootstrap Dependencies (clients module)

- `data-bs-toggle="modal"` / `data-bs-dismiss="modal"` in `clients/index.blade.php` — client form modal, feedback modal (out of scope)
- `data-bs-toggle="modal"` / `data-bs-dismiss="modal"` in `clients/_details.blade.php` — photo modal, client form modal (out of scope)
- `bootstrap.Modal` calls in `clients/index.blade.php` and `_details.blade.php` — client form modal, feedback modal (out of scope)

### Next Migration Candidate Requiring Separate Approval

**Client form modal** (`clients/index.blade.php` and `clients/_details.blade.php`) — the Add/Edit
client modal with `bootstrap.Modal` calls and dynamic form loading. Or **feedback modal** in
`clients/index.blade.php`. Or **remaining modals/toasts** across other modules.

---

### 2026-09-03 — Phase 8: Client form modal → Alpine.js (Add/Edit)

Delivery of the migration candidate named at the end of the Phase 7 entry: the
Add/Edit **client form modal** in `clients/index.blade.php` and the dynamic
modal created from `clients/_details.blade.php`. The modal is now driven by
Alpine.js while preserving the exact imperative bridge
(`window.openAddClientModal()` / `window.openEditModal(id)` /
`window.closeClientModal()`), the static-backdrop contract
(`backdrop:'static', keyboard:false` → no ESC / no outside-click close), the
AJAX form-body load into `#clientFormModalBody`, the footer submit wired to the
in-body form (`id="clientFormSubmit" form="clientForm"`), and the Tab focus
trap. **Presentation/code-behind only**: no schema, ACL, route, controller,
model, permission, or business-rule change; `C:\xampp\htdocs\system` untouched.
The feedback modal and toast remain on Bootstrap (out of scope).

**1. New partial `resources/views/partials/client-form-modal.blade.php`:**
- Modal markup with `x-data="clientFormModalComponent()"`,
  `x-show="$store.clientFormModal.open"`, `x-cloak`, `role="dialog" aria-modal`
  (`aria-labelledby="cfmTitle"`), a **static** backdrop (no outside-click close),
  Tab focus trap via `@keydown.tab.prevent.stop="handleTab($event)"`, and the
  header (`cfmTitle` / `cfmSubtitle`) + body (`#clientFormModalBody`) + footer
  (`Cancel` / `#clientFormSubmit form="clientForm"`) skeleton.
- **Blade-isolation rationale**: the modal's Alpine `@` directives were moved
  out of the very large `index.blade.php` into this partial. Inlining them left
  a later `@if (session('success'))` un-compiled (literal) while its `@endif`
  compiled to `<?php endif; ?>`, causing a runtime "unexpected token endif"
  parse error. Isolating the directives to a small partial fixes the compile.

**2. `resources/views/clients/index.blade.php`:**
- Removed the inline (Bootstrap) client-form modal markup; now
  `@include('partials.client-form-modal')`.
- Removed the old `getOrCreateModal()` / Bootstrap `window.openAddClientModal`
  / `window.openEditModal` / `closeClientModal` Modal-API bridge. Replaced with
  an Alpine store (`Alpine.store('clientFormModal')`) holding `open / title /
  subtitle / submitLabel / _prevFocus` plus `show(mode,id)` (sets edit/add
  labels, locks body scroll, AJAX-loads the create/edit `?modal=1` form into
  `#clientFormModalBody`, stamps `form.dataset.clientId = id` on edit) and
  `hide()` (restores scroll, returns focus to the prior trigger, resets the
  body to its loading state).
- Added `window.clientFormModalComponent()` exposing `handleTab(e)` (Alpine
  focus trap reading from the store).
- `window.openAddClientModal`/`openEditModal`/`closeClientModal` are now thin
  callers of the store, so all imperative callers (details panel, row actions,
  form submit handler, duplicate-warning "Review existing client") keep working
  unchanged.

**3. `resources/views/clients/_details.blade.php`:**
- The dynamic `getOrCreateModal()` path now builds Alpine-compatible markup
  (`el.setAttribute('x-data','clientFormModalComponent()')` +
  `Alpine.initTree(el)`), defines its own guarded
  `Alpine.store('clientFormModal')` (the index-page guard
  `if (typeof window.openEditModal === 'function') return;` still prevents
  double definition), and reimplements `window.openEditModal(id)` +
  `wireFormSubmit(body,id)` to open via the store and submit through the
  existing AJAX + feedback-modal flow (incl. photo upload). Cancel closes via
  `Alpine.store('clientFormModal').hide()`.

**Behavior parity preserved (verified):**
- Add labels ("Add Client" / "Register a new client in the registry" / submit
  "Add Client"); Edit labels ("Edit Client" / "Update client information" /
  submit "Save Client").
- `backdrop:'static', keyboard:false` → **ESC and outside/backdrop click do NOT
  close** the modal (parity with the previous Bootstrap modal).
- Close via Cancel, the header X, and programmatic `closeClientModal()`.
- Body scroll lock while open; focus returned to the trigger on close.
- Tab trapping within the dialog.

**Blade/Build/test verification:**
- `render_probe` confirmed the partial renders full markup (`id="clientFormSubmit"`,
  `form="clientForm"`, `cfmTitle`, `@keydown`) — earlier "empty render" was a
  debug artifact (the partial file had been zero-length during bisection) and
  is resolved by the committed partial.
- `php artisan view:clear` / `php artisan view:cache` → clean.
- `vendor\bin\pint` → clean.
- `php artisan test` → **297 passed (1397 assertions), 0 failures** (the
  previously-failing `ClientTest > clients index renders fixed modal footer and
  submit` now passes).
- Playwright `e2e/client-form-modal-phase8.spec.ts` added documenting the modal
  contract (labels, static-backdrop no-ESC/no-outside-click, close paths, focus
  trap, edit `data-client-id` load). Like every prior phase, the E2E tests fail
  at `signIn()` only because the `smoke_superadmin` test user is absent from the
  production-copy DB (`C:\xampp\htdocs\system` untouched; not a regression).

**Remaining Bootstrap dependencies (clients module):** feedback modal +
toast (out of scope) and the `data-bs-*` / `bootstrap.Modal` calls that drive
them — unchanged.

**Next migration candidate requiring separate approval:** **feedback modal**
(`#clientFeedbackModal`) in `clients/index.blade.php` and `_details.blade.php`,
then the remaining modals/toasts across other modules.

---

### 2026-09-04 — Phase 9: Client feedback modal + toast → Alpine.js (pure JS)

Delivery of the migration candidate named at the end of the Phase 8 entry: the
**client feedback modal** (`#clientFeedbackModal`) and its directly-associated
**client toast channel** (`#clientsToastStack` / `showToast()`), converted from
Bootstrap JS to Tailwind + Alpine.js (and plain JS for the toast). Behavior is
preserved exactly. **Presentation/code-behind only**: no schema, ACL, route,
controller, model, permission, or business-rule change;
`C:\xampp\htdocs\system` untouched. The **photo modal** in `_details.blade.php`
and all unrelated toasts/modals across other modules remain on Bootstrap (out
of scope).

**Feedback modal contract (parity with the previous Bootstrap modal):**
- Default Bootstrap semantics (no `backdrop:'static'`, no `keyboard:false`) →
  **backdrop click AND ESC close** the feedback modal.
- Navy header + white title; dynamic body `#clientFeedbackBody` and dynamic
  footer actions `#clientFeedbackActions` filled by callers (duplicate warning,
  validation errors, photo errors, server errors); static "Back to form" footer
  button closes back to the still-editable form modal (masked below at a lower
  z-index).
- On open, focus moves into the dialog; on close, focus returns to the
  previously-active element (the form modal's first focusable or the invalid
  field). Body scroll stays locked while any modal layer is open; the feedback
  modal only restores scroll when the client form modal is also closed.
- Tab focus trap within the dialog.
- Imperative bridge preserved: `window.showClientFeedback(options)`.

**Toast contract (parity):** `autohide:false` (persistent, manual dismiss
only), green-check icon, `role="status"`, `aria-live="polite"` stack, removed
from DOM on close; HTML-escaped message in `index` (`$('<div>').text(msg)`),
raw message in `_details` (both match the original call sites).

**1. New partial `resources/views/partials/client-feedback-modal.blade.php`:**
- Modal markup with `x-data="clientFeedbackModalComponent()"`, `x-cloak`,
  `role="dialog" aria-modal` (`aria-labelledby="clientFeedbackTitle"`), a
  **non-static** backdrop (outside-click closes) at `z-[210]`, ESC handler
  (`@keydown.escape`), Tab focus trap (`@keydown.tab.prevent.stop="handleTab"`),
  and header (`#clientFeedbackTitle`) + body (`#clientFeedbackBody`) + footer
  (`#clientFeedbackActions` + "Back to form").
- Guarded `Alpine.store('clientFeedbackModal')` (open/title/type/_onHidden +
  `show(options)`/`hide()`), `window.clientFeedbackModalComponent()`
  (`handleTab` reading `this.$refs.dialog`), and the bridge
  `window.showClientFeedback(options)`.

**2. `resources/views/clients/index.blade.php`:**
- Replaced the static Bootstrap feedback modal markup with
  `@include('partials.client-feedback-modal')`.
- `showDuplicateWarning()` and `setFeedbackContent()` now drive
  `Alpine.store('clientFeedbackModal')`; `setFeedbackContent()` accepts an
  optional `extraOnHidden` callback (passed through to `show()`).
- Removed the now-unused `var fbTitle`.
- Rewrote `showToast()` to pure JS (builds the toast element, adds `.show`,
  wires the close button to remove it from the DOM; no `bootstrap.Toast` /
  `data-bs-*`). Autohide stays false by contract.
- Added `wireFlashToast(stack)` (reveal `.show` + wire close) and a
  `document.querySelectorAll('.toast').forEach(wireFlashToast)` call replacing
  the `bootstrap.Toast` reveal for the server-rendered session flash.
- Removed `data-bs-autohide="false"` / `data-bs-dismiss="toast"` from the
  session-success flash markup.

**3. `resources/views/clients/_details.blade.php`:**
- Rewrote `showToast()` to pure JS (raw, non-escaped message, matching its
  original call sites; manual dismiss).
- Rewrote `showFeedback()` → `getOrCreateFeedbackModal()` +
  `Alpine.store('clientFeedbackModal').show(...)`. The dynamic full-page/panel
  fallback builds Alpine-compatible markup (root `x-data="clientFeedbackModalComponent()"`,
  `@click`/`@keydown` directives, `Alpine.initTree(el)`), reuses the same
  guarded store as the index partial, and registers
  `window.clientFeedbackModalComponent` (full-page `clients/show` fallback).
- Repaired an accidental earlier edit that had dropped
  `title: 'Edit Client',` from the `clientFormModal` store.

**4. `resources/css/app.css`:** added `@source` lines so Tailwind scans
`partials/client-form-modal.blade.php` and `partials/client-feedback-modal.blade.php`.
Verified built CSS contains `z-[210]`, `max-h-[60vh]`, `bg-ink/40`.

**Accessibility verification:** dialog `role="dialog"` + `aria-modal="true"` +
`aria-labelledby`; backdrop `aria-hidden="true"`; focus enters the dialog on
open, returns to the trigger on close; Tab trap; toast `role="status"` +
`aria-live="polite"` stack; decorative icons `aria-hidden`.

**Build/test verification:**
- `npm run build` → clean (Vite v6.4.3).
- `php artisan view:cache` → clean.
- `vendor\bin\pint --test` → clean.
- `php artisan test` → **297 passed (1397 assertions), 0 failures**.
- `render_probe` confirmed the partial renders full markup
  (`clientFeedbackModal`, `clientFeedbackModalComponent`, backdrop `@click`,
  ESC, Tab trap, `clientFeedbackTitle/Body/Actions`, `x-cloak`).

**Regression check:** the only remaining `data-bs-dismiss="modal"` in the
clients module is the **photo modal** (`_details.blade.php:346`, out of scope);
no `bootstrap.Modal` / `bootstrap.Toast` / `data-bs-autohide` /
`data-bs-dismiss="toast"` remain for the feedback modal or toast.

**Playwright:** `e2e/client-feedback-phase9.spec.ts` added documenting the
feedback modal + toast contract (dialog roles/attrs, opens via
`window.showClientFeedback`, close paths — X / Back-to-form / ESC /
outside-click, Tab trap, toast stack `aria-live` contract, no stale
`data-bs-dismiss="toast"`). Like every prior phase, the E2E tests fail at
`signIn()` only because the `smoke_superadmin` test user is absent from the
production-copy DB (the DB was not modified; not a regression).

**Remaining Bootstrap dependencies (clients module):** the **photo modal**
(`#photoModal`) in `_details.blade.php` and its `shown.bs.modal` /
`hidden.bs.modal` wiring — out of scope, untouched.

**Next migration candidate requiring separate approval:** the **photo modal**
(`#photoModal`) in `clients/_details.blade.php`, then the remaining
modals/toasts across other modules.

---

### 2026-09-04 — Phase 10: Client photo modal → Tailwind + Alpine.js

Delivery of the migration candidate named at the end of the Phase 9 entry: the
**client photo modal** (`#photoModal`) in `resources/views/clients/_details.blade.php`
converted from Bootstrap JS to Tailwind + Alpine.js. This is an **upload/camera
surface** (not a viewer): it captures or uploads a profile photo and posts to the
existing `clients.photo.store` endpoint. **Presentation/code-behind only**: no
schema, ACL, route, controller, model, permission, image-storage/URL, or
business-rule change; `C:\xampp\htdocs\system` untouched. All other
modals/toasts across other modules remain on Bootstrap (out of scope).

**Existing Bootstrap contract (documented before change):**
- `#photoModal` with classes `modal fade`, `tabindex="-1"`, `aria-hidden="true"`;
  `modal-dialog modal-md modal-dialog-centered`; `modal-content rounded-panel`.
- Rendered once per client-details view in both full-page (`clients/show`) and
  details-panel (`?panel=1`) modes. **Dormant/triggerless** in the clients
  module — the standalone Photo trigger was removed in an earlier phase
  (forensic audit), so the modal has no `data-bs-toggle`/`data-bs-target`
  opener. It remains openable programmatically via `bootstrap.Modal`.
- Content: header ("Client Profile Photo" + `.btn-close` with
  `data-bs-dismiss="modal"` + `aria-label="Close"`); body with hidden
  `client_id`, hidden `camera_image` (`#cameraImage`), `<video id="video">`,
  `<canvas id="canvas">`, `<img id="capturedPreview">` (alt "Captured preview"),
  file input `#photoFile` (accept `image/*`), and buttons "Use Camera"
  (`#startCameraBtn`), "Capture" (`#captureBtn`), "Retake" (`#retakeBtn`);
  footer "Save Photo" (`btn-navy`) inside a `POST` form to
  `clients.photo.store` with `@csrf`.
- `shown.bs.modal`: reveal file picker, clear stale captured preview.
- `hidden.bs.modal`: stop the camera `MediaStream`, hide video, show
  Use Camera, hide Capture/Retake.
- Camera flow: `startCameraBtn`→`getUserMedia({video:true})`; `captureBtn`→
  draw `video` to `canvas`→`toDataURL('image/jpeg',0.9)` into `#cameraImage` +
  `#capturedPreview`; `retakeBtn`→clear capture and resume live video.

**Alpine implementation (`_details.blade.php`):**
- Markup moved to `x-data="clientPhotoModal()"` with `x-cloak`,
  `role="dialog"`, `aria-modal="true"`, `aria-labelledby="photoModalTitle"`,
  `aria-describedby="photoModalBody"`, `z-[200]` fixed overlay, a non-static
  `bg-ink/40` backdrop (`@click="close()"` closes), max-width `[600px]` dialog,
  `overflow-y-auto` + `p-4` viewport, `rounded-panel bg-surface shadow-pop
  ring-1 ring-line` visual treatment matching the confirm/GIP/feedback modals.
- Dialog: `x-ref="dialog"`, `@keydown.escape="close()"`,
  `@keydown.tab.prevent.stop="handleTab($event)"`.
- Header close button switched from `.btn-close`/`data-bs-dismiss="modal"` to
  an inline `@click="close()"` icon button with `aria-label="Close"`. A "Cancel"
  footer button (`@click="close()"`) was added as the visible-close twin of the
  X (matching the other migrated modals' Cancel affordance).
- `window.clientPhotoModal()` owns presentation state only: `open`,
  `showVideo`, `showPreview`, `showStartBtn`, `showCaptureBtn`,
  `showRetakeBtn`, `_stream`, `_prevFocus`. The `shown.bs.modal`/`hidden.bs.modal`
  work is reproduced by `openModal()` (reveal file picker, clear stale preview,
  focus first control, lock body scroll) and `close()` (stop stream, reset
  camera controls, unlock body scroll, restore focus). Camera
  `startCamera()`/`capture()`/`retake()`/`_stopStream()` mirror the original
  logic byte-for-byte in behavior.
- The `video`/`canvas`/`capturedPreview`/`photoFile` elements keep their exact
  IDs (`#video`, `#canvas`, `#capturedPreview`, `#photoFile`, `#cameraImage`,
  `#startCameraBtn`, `#captureBtn`, `#retakeBtn`); the `d-none` toggles became
  Alpine `x-show` bindings. Image-loading mechanism unchanged (no new photo
  endpoint, storage, or URL).

**Compatibility bridge:** `window.openClientPhotoModal()` — a framework-agnostic
imperative opener (matching the `openEditModal`/`uiConfirm` pattern) that calls
`Alpine.$data(el).openModal()` on the `#photoModal` component. No existing
`window.*` photo contract was relied on elsewhere, so nothing else needed
patching.

**Dynamic DOM / Alpine initialization:** in panel mode `_details` is inserted
via `DetailsPanel`'s `innerHTML` + `executeScripts()`; Alpine's MutationObserver
initializes the new `x-data` root and the trailing inline `<script>` (defining
`window.clientPhotoModal`) runs synchronously first — the same proven pattern
as the GIP modal (Phases 6–7). No MutationObserver/polling was added beyond
Alpine's built-in behavior. No duplicate components or leaks.

**Accessibility verification:** dialog `role="dialog"` + `aria-modal="true"` +
`aria-labelledby="photoModalTitle"` + `aria-describedby="photoModalBody"`;
backdrop `aria-hidden="true"`; focus enters the dialog on open (first focusable
control), returns to the trigger on close (`_prevFocus`); Tab trapped;
body scroll locked while open; ESC and backdrop both close (default Bootstrap
semantics — the original had no `backdrop:'static'`/`keyboard:false`); close
button has `aria-label="Close"`; captured-preview image keeps `alt="Captured
preview"`.

**Bootstrap JS removed (for `#photoModal` only):** `data-bs-dismiss="modal"`,
the Bootstrap `modal`/`modal-dialog`/`modal-content`/`modal-header`/`modal-body`/
`modal-footer`/`btn-close` structure, and the `shown.bs.modal`/`hidden.bs.modal`
event listeners. No `bootstrap.Modal` call controlled `#photoModal`, so none
needed removal. All other Bootstrap usage in the repo is untouched and
legitimate.

**Files modified:** `resources/views/clients/_details.blade.php` (photo modal +
component script), `e2e/client-photo-modal-phase10.spec.ts` (new).

**Tests:** `npm run build` clean (Vite v6.4.3, CSS contains
`max-w-[600px]`/`pointer-events-none`/`bg-surface`/`bg-ink/40`);
`php artisan view:cache` clean; `vendor\bin\pint --test` clean;
`php artisan test` → full suite **297 passed (1397 assertions), 0 failures**
(client/photo/GIP tests all pass); Playwright `e2e/client-photo-modal-phase10.spec.ts`
added documenting the modal contract (dialog roles/attrs, `openClientPhotoModal`
open, X/Cancel/ESC/backdrop close, Tab trap, no Bootstrap-JS dependency). Like
every prior phase, the E2E tests fail at `signIn()` only because the
`smoke_superadmin` test user is absent from the production-copy DB (DB not
modified; not a regression).

**Regression check:** `grep` confirms no `bootstrap.Modal`, no
`data-bs-target="#photoModal"`, no `data-bs-dismiss="modal"`, and no live
`shown.bs.modal`/`hidden.bs.modal` listener remain for the client photo modal.
Remaining `#photoModal` Bootstrap markup (students `photo-upload`,
scholars `show`) is a separate screen and intentionally untouched. Feedback
modal, feedback toast, client form modal, record-view modal, and confirm modal
remain Alpine/pure-JS; GIP modal remains Alpine; unrelated Bootstrap components
(other modules) remain Bootstrap.

**Next migration candidate requiring separate approval:** module screens still
on Bootstrap (see the Tailwind migration plan §D/§N order), then the layout's
shared flash toast, then removal-gate items (DataTables Bootstrap skin / Bootstrap
CDN).

---

### 2026-09-04 — Phase 11: Bootstrap interactive dependency discovery (inventory only)

**Phase 11 — Discovery Only.** No migration, no code change, no remediation.

Repository-wide discovery of the remaining Bootstrap frontend dependencies after
the completed Clients-module migration (Phases 0–10). Findings:

- **Bootstrap JS is CDN-only** (`bootstrap.bundle.min.js@5.3.2`); it is **not**
  bundled by Vite (`resources/js/app.js` = Alpine + `@alpinejs/collapse`;
  `resources/js/bootstrap.js` = axios only; `package.json` has no `bootstrap`).
- **Remaining Bootstrap JS components** (all outside the Clients module):
  - **Modals (6):** `#passwordModal` (admin/users index + `passwordModal` trigger on
    users/show), `#leaderboardModal` (admin/audit_logs index, AJAX on `show.bs.modal`),
    `#clientIdPromptModal` (scholars/index, imperative `getOrCreateInstance`),
    `#messageModal` (scanners/scan, imperative `new bootstrap.Modal`), the dynamically
    created confirmation modal (unpaid_verifications/self-service,
    `new bootstrap.Modal`), and `#photoModal` (students/photo-upload, data-API).
  - **Toasts (2 serverside + layout):** `layouts/app` shared `login_status` flash
    toast (`bootstrap.Toast.getOrCreateInstance` loop at app.blade.php:127-129),
    plus identical server-rendered toasts in `transactions/index` and
    `households/index` that ride the same layout loop. The Clients toast (Phase 9)
    is already pure-JS (`wireFlashToast`).
  - **Alerts / dismissible banners:** `data-bs-dismiss="alert"` on `.btn-close` in
    `layouts/app` (validation error), `duplicates/index`, `family_members/create`,
    `households/create`, `admin/users/index`.
- **Dangling Bootstrap triggers (no modal target on page — latent no-ops):**
  `scholars/show.blade.php:34` (`#photoModal`), `admin/users/show.blade.php:134`
  (`#passwordModal`).
- **Confirmed NOT Bootstrap JS** (Alpine or pure JS, only class names remain):
  sidebar offcanvas/collapse (Alpine x-show/x-collapse), navbar + clients +
  transactions export dropdowns (Alpine), scholars in-page tabs (custom jQuery,
  not `bootstrap.Tab`), tooltip/popover references (custom CSS/JS, not Bootstrap).
  No Carousel, ScrollSpy, Bootstrap Button, Navbar-collapse, or Bootstrap
  Tooltip/Popover/Pill usage found.
- **Safest single next target:** `#passwordModal` in `admin/users/index.blade.php`
  (self-contained form modal, data-API + one `show.bs.modal` populate listener,
  low risk, no dynamic DOM). See the full discovery report (A–V) delivered to the
  owner.

**No files were modified during Phase 11; working tree unchanged.**

---

### 2026-09-04 — Phase 12: Admin/Users password reset modal → Tailwind + Alpine.js

Delivery of the Phase 11-recommended target: the **admin/users password reset
modal** (`#passwordModal`) in `resources/views/admin/users/index.blade.php`
converted from Bootstrap JS to Tailwind + Alpine.js. **Frontend-only**: no
schema, ACL, route, controller, request-rule, authorization, or password-reset
business-logic change. Only this modal was migrated; all other Bootstrap
components/toasts/alerts and the Bootstrap CDN are untouched (out of scope).

**Original Bootstrap contract (documented before change):**
- Markup `#passwordModal` = `.modal.fade` > `.modal-dialog.modal-dialog-centered`
  > `#passwordForm.modal-content` (the form wrapped the panel), with
  `@csrf` + `@method('PUT')`, header (`.modal-title` "Reset Password" +
  `.btn-close` `data-bs-dismiss="modal"`), body (hidden `#user_id` +
  disabled `#modal_username` + `password` / `password_confirmation` with
  `required minlength="8"`), footer (Cancel `data-bs-dismiss="modal"` + Save
  submit).
- **Trigger:** server-rendered table rows used a `data-bs-toggle="modal"` +
  `data-bs-target="#passwordModal"` button carrying `data-id` /
  `data-username`. (The DataTables AJAX rows render a `.reset-btn` button with
  `data-id`/`data-username` but had **no** modal trigger or handler — a latent
  dead button in the live table.)
- **`show.bs.modal`:** a listener read `e.relatedTarget` (the triggering
  button), set `#passwordForm.action = /admin/users/{id}/password`, and
  populated `#user_id` and `#modal_username`.
- **Form contract:** `#passwordForm`, POST, `@csrf`, `@method('PUT')`, hidden
  `user_id`, fields `password`/`password_confirmation`; submits to
  `admin.users.reset-password` (PUT `/admin/users/{user}/password`) unchanged.

**Alpine implementation (`admin/users/index.blade.php`):**
- Markup moved to `x-data="passwordResetModalComponent()"` on `#passwordForm`
  with `x-cloak`, `role="dialog"`, `aria-modal="true"`,
  `aria-labelledby="passwordModalTitle"`, `aria-describedby="passwordModalBody"`,
  `z-[200]` fixed overlay, `bg-ink/40` backdrop (`@click="close()"`), centered
  `max-w-[480px]` dialog, `overflow-y-auto`, `rounded-panel bg-surface shadow-pop
  ring-1 ring-line`, `bg-navy` header + `bg-neutral-100` footer matching the
  other migrated modals. Header X = inline `@click` + `aria-label="Close"`;
  footer Cancel (`@click="close()"`) + Save submit.
- New Alpine **store** `passwordResetModal` (`open`, `openFor(id, username)`,
  `close`) registered on `alpine:init`; its `openFor()` reproduces the
  `show.bs.modal` work (sets form action from id, fills `#user_id` and
  `#modal_username`), locks body scroll, and focuses the dialog's close button
  on open (Bootstrap's default focus-first-focusable); `close()` unlocks body
  scroll and restores focus to the trigger (`_prevFocus`). Tab trap via
  `handleTab($event)` on `@keydown.tab.prevent.stop`; ESC via
  `@keydown.escape.window="close()"`; backdrop click closes (Bootstrap default
  `backdrop:true` semantics).
- **Trigger → Alpine data flow (no Bootstrap `relatedTarget`):** both the
  server-rendered rows (now class `reset-btn`, `data-bs-*` removed, kept
  `data-id`/`data-username`/`aria-haspopup="dialog"`) and the DataTables AJAX
  `.reset-btn` rows route through a single delegated
  `$(document).on('click', '.reset-btn', ...)` handler that calls
  `window.openPasswordResetModal(id, username)`. Disabled (protected
  super-admin) buttons do not dispatch clicks, so they remain inert.
- **Compatibility bridge:** `window.openPasswordResetModal(id, username)` →
  `Alpine.store('passwordResetModal').openFor(id, username)`. No pre-existing
  global password-modal API existed, so the bridge is the minimal imperative
  entry point (mirrors `uiConfirm`/`uiViewModal`/`openClientPhotoModal`).
- **Dynamic DOM:** modal is server-rendered, non-dynamic, self-contained — no
  MutationObserver/polling/repeated init added. The store is registered on
  `alpine:init` in an inline script in the content section (runs before
  `@vite app.js` → `Alpine.start()`), matching the record-view-modal / client
  photo modal registration timing.

**Accessibility verified:** `role="dialog"` + `aria-modal="true"` +
`aria-labelledby="passwordModalTitle"` + `aria-describedby="passwordModalBody"`;
backdrop `aria-hidden="true"`; focus enters the dialog on open (close button,
matching Bootstrap default) and returns to the trigger on close; Tab trapped;
body scroll locked while open; ESC and backdrop close; close button
`aria-label="Close"`; trigger has `aria-haspopup="dialog"`.

**Bootstrap JS removed (for `#passwordModal` only):** the trigger's
`data-bs-toggle="modal"`/`data-bs-target="#passwordModal"`, the modal's
`data-bs-dismiss="modal"` controls, the `.modal/.modal-dialog/.modal-content/
.modal-header/.modal-body/.modal-footer/.modal-title` structural classes, and
the `show.bs.modal` listener. No `bootstrap.Modal` call controlled it. All
other Bootstrap usage (leaderboard, client-ID, scanner message, unpaid
self-service, students photo modals, alert banners, shared layout flash toast,
Bootstrap CDN) remains in place.

**Files modified:** `resources/views/admin/users/index.blade.php`,
`e2e/admin-users-password-modal-phase12.spec.ts` (new).

**Tests:** `npm run build` clean (Tailwind generated `max-w-[480px]`,
`bg-ink/40`, `bg-neutral-100`, `max-h-[90vh]`, etc.); `php artisan view:cache`
clean and the compiled view carries **no** `data-bs-toggle="modal"`,
`show.bs.modal`, `bootstrap.Modal`, or `relatedTarget` for the password modal;
`vendor\bin\pint --test` clean; `php artisan test` → full suite
**297 passed (1397 assertions), 0 failures** (`--filter=AdministrationTest` →
32 passed / 133 assertions). Playwright
`e2e/admin-users-password-modal-phase12.spec.ts` added documenting the modal
contract (dialog roles/attrs, `openPasswordResetModal` open + data transfer,
X/Cancel/ESC/backdrop close, Tab trap, no Bootstrap-JS dependency). Like every
prior phase, E2E fails at `signIn()` only because the `smoke_superadmin` test
user is absent from the production-copy DB (DB not modified; not a regression).

**Dangling / unrelated reference (intentionally left unchanged):**
`resources/views/admin/users/show.blade.php:134` still carries
`data-bs-toggle="modal" data-bs-target="#passwordModal"` but the page (used as a
details-panel partial) has **no** `#passwordModal` markup — a pre-existing
no-op trigger. Per the migration boundary this was **not** modified or repaired
in Phase 12; it is logged here as a separate discovered issue for a later phase.

**Regression check:** clients module remains Bootstrap-JS-clean; remaining
Bootstrap deps verified intact: `#leaderboardModal` (audit_logs `show.bs.modal`),
`#clientIdPromptModal` (scholars `getOrCreateInstance`), `#messageModal`
(scanners `new bootstrap.Modal`), unpaid self-service dynamic modal
(`new bootstrap.Modal`), students `#photoModal`
(`data-bs-toggle/target` + `shown/hidden.bs.modal`), alert banners
(`data-bs-dismiss="alert"`), and the shared layout flash toast
(`bootstrap.Toast.getOrCreateInstance`).

**Next migration candidate requiring separate approval:** see §O of the
Tailwind migration plan — next safest self-contained Bootstrap modal
candidates: `#messageModal` (scanners/scan), then the `data-bs-dismiss="alert"`
banners, then `#leaderboardModal` (audit_logs).

---

### 2026-09-06 - Phase 21: Bootstrap JS surface inventory + navbar hamburger Alpine scope fix

**Phase 21 deliverable:** a fresh, repository-wide inventory of the Bootstrap
JavaScript surface in the Laravel layer, then — only where confirmed genuine and
in scope — migration of the **navbar hamburger** to a working Alpine binding
with a correct `x-data` scope. **Frontend-only**: no schema, ACL, route,
controller, model, service, permission, validation-rule or business-rule
change. The Bootstrap CDN, CSS, JS bundle and DataTables Bootstrap skin are
**not** removed (removal-gate items for a later phase).

**Path correction (inventory finding):** the task document references
`resources/views/components/navbar.blade.php`, which does **not** exist in this
project; the real navbar is `resources/views/partials/navbar.blade.php`. All
Phase 21 work targets the actual file.

**Stage A — fresh Bootstrap JS inventory (classified):**
- **B (intentionally retained):** `layouts/app.blade.php:139` —
  `bootstrap.Toast.getOrCreateInstance(el, { autohide: false }).show()` init
  loop. Still serves the `clients/index` Phase 9 feedback stack (its own inline
  `wireFlashToast` is a pre-existing double-init, out of scope). Kept verbatim.
- **D (dangling/dead, out of scope):** `admin/users/show.blade.php:134` —
  `data-bs-toggle="modal" data-bs-target="#passwordModal"` pointing at a modal
  that does not exist on that details-panel page (already logged in Phase 12).
- **E (genuine migration candidate → migrated):** the navbar hamburger in
  `partials/navbar.blade.php` — Alpine directives present but **inert** because
  they live outside any `x-data` scope.
- **Zero live Bootstrap Collapse surfaces:** no `bootstrap.Collapse`,
  `new bootstrap.Collapse` or `data-bs-toggle="collapse"` anywhere in
  `resources/views/`. `clients/_gip.blade.php` `collapsed`/`accordion-collapse`
  classes are Alpine-driven (Phase 7) CSS-only remnants (F).
- **No live** `bootstrap.Dropdown`/`Popover`/`Tooltip`/`Tab` in views;
  `data-bs-parent`/`data-bs-spy`/`data-bs-slide`/`data-bs-ride` occur only in
  comments/documentation (F).
- Only remaining `data-bs-target` in views is the known dangling
  `admin/users/show.blade.php:134` trigger.
- Bootstrap CDN retained on `layouts/app.blade.php` (+ the public standalone
  pages `auth/login`, `qr/viewer`, `grantee_update/self-service`,
  `students/*`, `unpaid_verifications/self-service`) — removal is gated.

**Root cause verified live (pre-fix, temp chromium spec):** the hamburger's
`@click="$store.sidebar.toggle()"`, `:aria-controls="'appSidebar'"` and
`:aria-expanded="$store.sidebar.open.toString()"` never compiled —
`_x_dataStack:false`, `_x_bindings:false`, `_x_scope:false` on the element;
`aria-expanded`/`aria-controls` remained null/literal in the DOM; clicking left
the drawer closed (`-translate-x-full` persisted). This is a **pre-existing
Alpine scope defect**, not a Bootstrap-migration problem — the directives were
never bound since the shell was rebuilt Tailwind-first. The drawer itself
(`#appSidebar`, `$store.sidebar` store registered under `alpine:init` in
`partials/sidebar.blade.php`) was already Alpine-owned.

**Change applied (smallest correct scope):** added a bare `x-data` to the
hamburger `<button>` in `resources/views/partials/navbar.blade.php` (attribute
order normalized: `aria-label` moved before `x-data`). The button drives the
existing shared `$store.sidebar` (open/toggle/close) exactly as authored in
Phase 3. No change to `sidebar.blade.php`, the layout, or any other file.

**Post-fix probes (temp chromium specs):** bindings compiled
(`_x_dataStack:true`, `_x_bindings:true`); toggle opens/closes the drawer;
`aria-expanded` flips `"false"`⇄`"true"`; all three close paths verified — drawer
X button (`aria-label="Close"` in `#appSidebar`), backdrop click, ESC — with
body scroll lock released (`overflow:""`). Design fact confirmed live: while
open the drawer (z-100) overlays the topbar (z-50), so re-clicking the
hamburger to close is **not** part of the design; X/backdrop/ESC are the close
paths and were preserved.

**Files modified:** `resources/views/partials/navbar.blade.php` (hamburger
button only: added `x-data`, reordered `aria-label`).
**Files created:** `e2e/navbar-phase21.spec.ts` (5 tests, serial).

**Tests:** `npm run build` clean (vite v6.4.3 — `app-D3mz-Mcl.css` /
`app-DqsLDVL_.js`); `php artisan view:cache` clean and the compiled navbar view
(`0e26c4fce3a73253cccd48d6b4147291.php`) carries the `x-data` + Alpine
directives on the hamburger; `vendor\bin\pint --test` clean; `php artisan test`
→ full suite **301 passed (1419 assertions), 0 failures** (unchanged Phase 20
baseline — frontend-only change).

**E2E (live):** `e2e/navbar-phase21.spec.ts` (**5/5 chromium** and **5/5 Mobile
Chrome**, serial). Covers: mobile closed state + Alpine binding poll (`expect.poll`
on `_x_dataStack`) + Open + `aria-expanded="true"`; close via X/backdrop/ESC with
aria sync + `overflow:""` scroll-lock release; repeated open/close cycles +
sidebar Dashboard link navigation; desktop regression (hamburger hidden,
`lg:translate-x-0` permanent sidebar, topbar greeting `Welcome, smoke_superadmin`);
no-Bootstrap-Collapse-dependency check (hamburger and drawer carry zero
`data-bs-*`). Mobile Chrome run was fixed by `test.describe.configure({ mode:
'serial' })` — the earlier parallel-worker 2/5 failure was the known
single-device login collision (`session_token` clobbered per login → watchdog
redirects stale workers to `/login`), not an application bug. Smoke regression
`e2e/smoke.spec.ts` re-ran **4/4 chromium** (shell/regression clean).

**Regression check (§22-style):** post-migration `resources/` scan — zero live
`bootstrap.Collapse`/`new bootstrap.Collapse`/`data-bs-toggle="collapse"`; the
only live Bootstrap JS wiring left is the intentional layout init loop (clients
Phase 9 stack) and the documented dangling `admin/users/show.blade.php:134`
trigger.

**Environment:** ephemeral `smoke_superadmin` (id 9) + `'*'` permission row
inserted into the local production-copy DB for E2E only; fully removed
afterwards (user + permission + 35 LOGIN audit rows; audit trail returned to
exactly **1604** rows — one mid-session user-3 LOGIN row that the
AUTO_INCREMENT sequence proved was not part of the 1604 baseline was also
removed; verified `tbl_household` id 1 only, jordi/jiro only, legitimate jordi
`'*'` permission intact).

**Next candidate requiring approval:** the only remaining live Bootstrap Toast
surface (the clients Phase 9 feedback stack — still served by the layout init
loop), then removal-gate items (DataTables Bootstrap skin / Bootstrap CDN).

---

### 2026-09-06 - Phase 22: Bootstrap Toast init loop removal (clients Toast stack verification)

**Phase 22 deliverable:** an investigation-first verification of whether the
layout init loop `bootstrap.Toast.getOrCreateInstance(el, { autohide: false })
.show()` at `resources/views/layouts/app.blade.php:139` was still required by
the Phase 9 clients toast stack — and, having **proven it redundant**, the
smallest possible removal. **Frontend-only**: no schema, ACL, route, controller,
model, service, permission, validation-rule or business-rule change. Bootstrap
CDN/CSS/JS and the DataTables Bootstrap skin are **not** removed (removal-gate
items for a later phase).

**Finding — the loop is redundant.** Repo-wide inspection showed the layout loop
was the **only** `bootstrap.Toast` invocation in the application, with **zero**
other consumers:

- The **only** static `.toast`-class element the loop could match at page-parse
  time is the clients flash toast (`clients/index.blade.php:284`). That toast is
  already revealed and wired independently by the inline `wireFlashToast`
  (`clients/index.blade.php:793`, invoked at :799 via
  `document.querySelectorAll('.toast').forEach(wireFlashToast)`).
- The dynamic `showToast` elements (`clients/index.blade.php:760` and
  `clients/_details.blade.php:820`) are created into `#clientsToastStack` at
  runtime, after the parse-time loop has already run — they reveal themselves
  with `.show` and dismiss by manual DOM removal, pure JS, no `bootstrap.Toast`
  involvement.
- The Phase 19 shared layout flash toast (`#flashToast`) and the Phase 20
  transactions/households page toasts all **lost the `.toast` class** in their
  phases, so the loop no longer matches them (their own comments confirmed this).
- Project-wide greps (including `resources/js`) found no other
  `bootstrap.Toast` / `Toast.getInstance` / `data-bs-dismiss` (non-false) /
  `data-bs-autohide` consumers, and no `.bs.toast` events. The Phase 9 E2E spec
  (`client-feedback-phase9.spec.ts:139`) asserts only the static toast-channel
  container contract — loop-independent; the phase19/phase20 toasts now carry zero
  Bootstrap toast attributes.

**Change (smallest possible):** removed the obsolete loop and its stale comment
block from `layouts/app.blade.php`, replacing them with a concise comment
documenting that no Bootstrap Toast initialization remains while the Bootstrap
bundle is kept for the remaining non-toast CDN consumers. No other application
code changed. Verified the compiled layout view contains no `bootstrap.Toast` /
`getOrCreateInstance(toastEl` after `view:cache`.

**Verification:**
- `php artisan test` — **301 tests / 1419 assertions, 0 failures**.
- `npm run build` (vite v6.4.3) — clean.
- `php artisan view:cache` + `view:clear` — clean; compiled-view scan confirms no
  `bootstrap.Toast` remains.
- `vendor\bin\pint --test` — passed.
- **E2E note:** per Phase 22 rules (no ephemeral auth account unless absolutely
  necessary) no live-toast browser run was performed; the toast stack contract is
  covered by the existing Bootstrap-JS-free `client-feedback-phase9`,
  `flash-toast-phase19` and `page-toasts-phase20` specs. No DB writes; DB
  untouched (1604 audit rows).

**Files changed:** `resources/views/layouts/app.blade.php` (removed obsolete
Bootstrap Toast init loop + stale comment).

**Next candidate requiring approval:** the removal-gate items — the DataTables
Bootstrap skin and the Bootstrap CDN/CSS/JS bundle (and the dangling
`data-bs-toggle="modal"` trigger at `admin/users/show.blade.php:134`, already
logged in Phase 12/15).

---

### 2026-09-06 - Phase 23: Bootstrap removal-gate audit

**Phase 23 deliverable:** a fresh, evidence-based removal-gate audit. **Audit
only — NO application code was changed.** The phase establishes exactly what
still prevents removing the Bootstrap CDN/bundle, answers the removal-gate
question, and defers every non-proven removal to a controlled later phase.

**Git status:** app code unchanged; only this doc, `TAILWIND_MIGRATION_
EXECUTION_PLAN.md` and `SESSION_HANDOFF.md` were updated.

**A. Bootstrap JavaScript dependency audit — the JS bundle is REDUNDANT.**
Repo-wide fresh search (views, `resources/js`, `resources/css`, `public`,
`public/build`, `routes`, `package.json`, `vite.config.js`) found **zero live
`bootstrap.*()` API references** — no `bootstrap.Modal`, `Collapse`,
`Dropdown`, `Tooltip`, `Popover`, `Tab`, `Offcanvas`, `Toast`; no `new
bootstrap.`, no `getInstance`, no `getOrCreateInstance` in any application
code (every remaining `bootstrap.*` token is a historical comment). Built
`app.js` (105.70 kB) contains zero `bootstrap`/`data-bs-` hits; `resources/js`
imports only Laravel's `bootstrap.js` (axios) + Alpine. `package.json` has NO
`bootstrap` npm dependency.

**B. Data-attribute audit — ONE live attribute, provably DEAD.**
`admin/users/show.blade.php:134`: `data-bs-toggle="modal" data-bs-target=
"#passwordModal"`. Traced target: no element with `id="passwordModal"` exists
in **any document** (the Phase 12 Alpine reset modal on `admin/users/index`
uses `id="passwordForm"`; the reset-password routes are `admin.users.reset-
password` exercised by the index modal and the show panel's inline
`#userPanelEdit` form). Full-page mode: the button sits in a `display:none`
`data-panel-actions` block, never visible. Panel mode (DetailsPanel injection
into the index page): the Bootstrap 5 delegated click handler would find a
null target and throw before any UI action — the write-up in that phase's
entries already logged it as dangling/no-op (Phases 12/21). It belongs to
accepted Phase 12 UI, so per the §12 "outside an accepted contract" rule it
is **left untouched and documented** here.
No `data-toggle=`/`data-target=`/`data-dismiss=` (legacy v4) attributes
exist anywhere.

**C. CSS dependency audit — Bootstrap CSS is REQUIRED.**
Bootstrap 5.3.2 CSS is consumed for rendering by multiple surfaces:
1. **DataTables bootstrap5 skin** — 11 screens load
   `dataTables.bootstrap5.min.css`; the per-screen "token skin" rules
   (e.g. `#clients-screen .dataTables_wrapper .page-link {...}`) **recolor,
   not replace**, Bootstrap's `.pagination`/`.page-item`/`.page-link`/
   `.form-control`/`.form-select` base layout.
2. **Form controls** — `.form-control`/`.form-select`/`.input-group`/
   `.form-check-input` used extensively (clients `_form`/`_gip`, households,
   scholars, update_logs, grantee_update `_self_update_tab`, login, qr/viewer).
   `ui.css` only overrides the `:focus` ring; all base styling is Bootstrap's.
3. **`.btn` base + the `--bs-btn-*` variable bridge** — `ui.css` contains **49
   `--bs-*` references**: `.btn`, `.btn-gold`, `.btn-primary`, `.btn-outline-
   primary`, `.btn-danger`, `.btn-outline-danger`, `.form-control:focus`,
   `.form-select:focus`, `.form-check-input:checked/:focus`, `.page-link`.
   These classes literally assign Bootstrap CSS custom properties; they only
   render because Bootstrap's `.btn`/`.form-control` rules consume them.
   Removing Bootstrap CSS would strip the styling of every `btn-gold`/
   `btn-primary`/`form-control` element app-wide.
4. **`.btn-close`** — kept on migrated toasts/alerts by contract (Phase 12
   users modal close, Phase 14 alert dismissals, Phase 19 `#flashToast`,
   Phase 20 households/transactions toasts, `layouts/app` error alert).
   `.btn-close`'s X glyph + sizing come from Bootstrap CSS.
5. **Dropdowns** — `clients/index` export dropdown uses `.dropdown-toggle`/
   `.dropdown-menu`/`.dropdown-menu-end` with Alpine toggling `.show`.
   Structure/positioning come from Bootstrap CSS.
6. **Tables/alerts/grids** — `.table`, `.table-sm`, `.alert alert-danger
   alert-dismissible` (layout), `.row`/`.col-md-*`/`.g-2`/`.d-flex`
   (grantee_update self-update tab).
7. **7 standalone public pages** load the Bootstrap CSS CDN in their own
   `<head>`: `auth/login`, `qr/viewer`, `students/verify`, `students/
   update-photo`, `students/photo-upload`, `unpaid_verifications/self-service`,
   `grantee_update/self-service`. They nest no layout shell and rely on
   Bootstrap CSS directly.

**D. DataTables integration audit.**
- Packages: **none in npm**. DataTables 1.13.6 is loaded per-screen from CDN
  (`jquery.dataTables.min.js` + `dataTables.bootstrap5.min.js` +
  `dataTables.bootstrap5.min.css`), jQuery 3.7.1 from `code.jquery.com`.
- **Does NOT require Bootstrap JS.** Fetched actual
  `dataTables.bootstrap5.min.js` (1.13.6): it UMD-wraps `jquery` +
  `datatables.net`, extends defaults (`dom`, `renderer:"bootstrap"`) and
  classes (`sFilterInput:"form-control form-control-sm"`, `sLengthSelect:
  "form-select form-select-sm"`, `sPageButton:"paginate_button page-item"`,
  client-side page-button renderer). It NEVER references `bootstrap.Modal` or
  the `bootstrap` global. DataTables can operate independently of Bootstrap JS.
- **Depends on Bootstrap CSS class names** for the rendered chrome:
  pagination (`ul.pagination`/`li.page-item`/`a.page-link`), length select
  (`form-select form-select-sm`), filter input (`form-control form-control-sm`).
  The app's token overlays recolor these; Bootstrap CSS supplies the base.
- No Bootstrap-specific DataTables JS configuration exists in the app (no
  `$('#t').DataTable({...})` options referencing Bootstrap; the DetailsPanel
  clicking pattern replaces the datatables-default modal with the Tailwind
  panel).

**E. CDN audit.**
- All references are **Bootstrap 5.3.2** from `cdn.jsdelivr.net`: CSS CDN in
  **8 files** (`layouts/app`, `auth/login`, `qr/viewer`, `students/verify`,
  `students/update-photo`, `students/photo-upload`,
  `unpaid_verifications/self-service`, `grantee_update/self-service`); JS
  bundle in **6 files** (all of the above except `students/verify` and
  `students/update-photo`). No integrity/SRI attributes. No `cdnjs`/`unpkg`
  Bootstrap references (only `unpkg.com/html5-qrcode` on `scanners/scan`).
- Every authenticated screen inherits Bootstrap via `layouts/app`; seven
  standalone public pages carry their own copies.

**F. Layout audit.**
- `layouts/app.blade.php`: Bootstrap CSS at head line 9, `@vite(app.css)`
  line 10, `ui.css` line 11, then `@stack('styles')` (screens push the
  DataTables skin CSS). Bootstrap JS bundle at end of body line 111, before
  the inline session-check script, `@vite(app.js)` and `@stack('scripts')`.
- No `bootstrap.*()` usage remains in the layout (Phase 22 removed the Toast
  loop). The Phase 19 `#flashToast` and Phase 20 page toasts are fully Alpine
  (`x-data`/`x-show`) except their `.btn-close` glyph which still needs
  Bootstrap CSS.

**G. Dangling trigger** — the only `data-bs-*` in the app; classified DEAD /
INERT (see B); left untouched.

**H. Removal-gate decision: `NO — DEPENDENCY REMAINS`** — Bootstrap **CSS**
(5.3.2 CDN) must stay for the DataTables bootstrap5 skin, form controls,
`.btn`/`--bs-btn-*` bridge in `ui.css`, `.btn-close`, dropdowns, pages'
tables/alerts/grids, and 7 standalone public pages. The Bootstrap **JS
bundle** is REDUNDANT (zero consumers) and classified REMOVAL-SAFE — removal
is deferred to a controlled subsequent phase (a pure `<script>` deletion is
low-risk but is a global change, out of the audit-only scope).

**Verification** (audit-only — no code changed, no DB writes):
- `php artisan test` — **301 tests / 1419 assertions, 0 failures**.
- `npm run build` (vite v6.4.3) — clean (built `app.js` 105.70 kB, 0
  bootstrap hits). `php artisan view:cache` + `view:clear` — clean; compiled
  views contain exactly one Bootstrap data-attribute (the documented dangling
  trigger) and zero `bootstrap.*()` calls. `vendor\bin\pint --test` — passed.
- **E2E/browser:** not run. Audit introduced no behavior change; reproducing
  authenticated on-browser checks would require recreating the ephemeral
  `smoke_superadmin` account — explicitly discouraged by the phase for an
  audit-only phase. Existing E2E specs still cover the migrated surfaces.
  Documented as a limitation.

**Files changed:** none (app code). Docs: this log,
`TAILWIND_MIGRATION_EXECUTION_PLAN.md`, `SESSION_HANDOFF.md`.

**Next recommended phase (identify only):** a controlled "Bootstrap JS bundle
removal" phase (delete the 6 bundle `<script>` includes; verify zero console
errors and full E2E) followed by a separate "DataTables skin / Bootstrap CSS
ownership" phase. The dangling trigger may be cleaned in the JS-removal phase.

---

### 2026-09-07 - Phase 27: Final Bootstrap CSS removal (8 CDN `<link>`s → project-owned `ui.css` §4.8–4.10)

**Phase 27 deliverable:** the third and final controlled **Bootstrap-CSS** ownership migration (the
Phase 23 removal-gate's last candidate). Removed **all 8** Bootstrap 5.3.2 CSS CDN `<link>` tags and
owned every last reusable family the remaining screens render — `.form-label`, the `.accordion*`
subsystem (permissions), the `.list-group*` subsystem, the Bootstrap **utility** layer (the many
`!important` display/flex/spacing/text/grid helpers used across the standalone pages and JS-injected
class names), and Bootstrap **Reboot + `_type.scss`** element defaults — byte-faithful to 5.3.2.
Plus the 7 standalone public pages' shared reset. **Post-change: zero Bootstrap CSS dependency
anywhere; removal-gate READY.**

**What changed** — `public/css/ui.css` + 9 Blade files + 1 E2E spec:
- `ui.css` **§4.8** new: `.form-label` (`margin-bottom:.5rem`), full `.accordion`/`.accordion-button`/
  `.accordion-item`/`.accordion-header`/`.accordion-body` contract (chevron data-URIs for the closed
  `%23212529` and `.collapsed` `%23084298` states, collapse border-width rules, focus
  `#86b7fe`/`rgba(13,110,253,.25)`, active subtle/emphasis tokens, `disabled` pointer-events), and
  `.list-group`/`.list-group-item`/`.list-group-flush`/`.list-group-item-action` (5.3 values:
  `--bs-list-group-border-color` resolves to `#dee2e6`, item padding `.5rem 1rem`, flush borders
  `0 0 1px`, last-child border-bottom 0).
- `ui.css` **§4.9** new — Bootstrap utility parity with the CDN's `!important` semantics so the
  utilities that today beat Tailwind's non-important twins keep doing so (values reproduced
  byte-faithful at the app's 14px root): `.d-none` (critical JS classList contract — no Tailwind
  equivalent), `.d-block`, `.d-inline-block`, `.d-flex`, `.d-inline-flex`, flex-direction/justify/
  align helpers, `.position-relative/-absolute` + `.top-0`/`.bottom-0`, `.text-start/-end/-center` +
  `.text-primary/-secondary/-success/-danger/-warning/-info/-muted/-white/-body`, `.fw-bold`,
  `.fst-italic`, `.fs-4`, `.small`, `.w-auto`, `.min-vh-100`, `.img-fluid`, `.img-thumbnail`,
  `.bg-white`, `.bg-transparent`, `.border` + width 1–3, `.rounded(-1/-2/-3/-circle/-pill)`,
  `.overflow-*`, the full margin/padding ladder 0–5 (`mb-3=1rem`, `mb-4=1.5rem`, `p-3=1rem`, `gap-3
  =1rem` — Bootstrap scale, which is what users see today) + auto sides, `.gap-0–5`, and the grid:
  `.row`, `.g-0…g-5`, `@media(min-width:576px)` `.col-sm-4`/`.col-sm-8`,
  `@media(min-width:768px)` `.col-md-3`/`.col-md-4`/`.col-md-6`.
- `ui.css` **§4.10** new — Reboot + `_type.scss` element parity: **`*,*::before,*::after{box-sizing:
  border-box}`** (Bootstrap's global box model; its loss was flipping every un-owned element back to
  content-box — proven by the computed-style diff below), `body{margin:0;color:#212529}`
  (`--bs-body-color`), headings/p/ul/ol/dl/dt/dd/blockquote/hr/abbr/address/a/caption/th/label/
  button/input-select-button-textarea-inheritance/textarea, and the `_type.scss` heading sizes with
  their `1200px` media query.
- Removed the 8 Bootstrap CSS CDN `<link>` tags and refreshed the stale comments that promised
  Bootstrap would keep loading: `layouts/app.blade.php`, `auth/login.blade.php`,
  `qr/viewer.blade.php`, `grantee_update/self-service.blade.php`,
  `unpaid_verifications/self-service.blade.php`, `students/photo-upload.blade.php`,
  `students/update-photo.blade.php`, `students/verify.blade.php`. (`layouts/app` comment: Phase 24
  JS removal + Phase 27 CSS removal.)
- `admin/users/show.blade.php`: **removed the proven-dangling `#passwordModal` trigger button**
  (finalization-pass candidate from Phases 23/26 — `data-bs-toggle`/`data-bs-target="#passwordModal"`,
  target exists in no document, no Bootstrap JS loads). Kept the Edit button + the native inline
  `userPanelEdit` password form.
- `e2e/alpine-phase0.spec.ts`: the login-page smoke now asserts the Bootstrap CSS link is **absent**
  (`link[href*="bootstrap@5.3.2"]` count 0) and the built `ui.css` link is present (count 1); test
  renamed "login page boots cleanly with Bootstrap CSS removed (Phase 27)".

**Fallout caught by verification (and fixed):** the initial §4.8–4.10 pass left two real gaps.
The live computed-style harness (pre-removal baseline → post-removal) showed (a) `body`/`.data-card`
color drifting `rgb(33,37,41)` → `rgb(0,0,0)` (missing Reboot `body{color:#212529}`), and (b) every
padded element's width shifting — `.form-control` 549→600 px, `.btn-navy` 271→303.5 px, login
`.data-card` 420→369 px, body content 1280→1232 px — all from the **lost universal `box-sizing:
border-box`** (content-box restores). Fixed with the two §4.10 lines above; the harness re-run is a
**clean diff** against the baseline (below).

**Post-change data:**
- Repo-wide `bootstrap@5.3.2` string: **only** the intentional `e2e/alpine-phase0.spec.ts` absence
  locator. `rg` over all Blade/CSS/JS: zero Bootstrap CDN references in views, zero in compiled
  output.
- `--bs-*` live consumption: **0** (comment-only mentions in `datatables.css`/`app.css` + docs;
  all new §4.8–4.10 values are literal hexes or `--ui-*`/`--bs-free` locals — the audit stays
  clean).
- JS contract: 0 live `bootstrap.*()` calls; `window.bootstrap` undefined on all standalone pages.
- `ui.css`: 1489 lines, braces balanced (verification ran before finalization; re-validated by
  served HTTP 200 + zero console errors), sections 4.8–4.10 inserted between the existing §4
  pagination block and §5.
- DataTables skin (`datatables.css`, Phase 25) + jQuery + DataTables + Alpine: untouched
  (out of scope).

**Verification:** full suite **301 passed (1419 assertions), 0 failures** (Phase 26 state; this
phase is CSS + one Blade removal + one spec-assertion — no PHP logic touched); Pint clean
(`vendor\bin\pint --test`); `npm run build` clean (unchanged hash
`app-D3mz-Mcl.css` 57.62 kB — ui.css is a static asset, not part of the Vite build); `view:cache`
clean. **Live Chromium harness** (`bs-capture.mjs`, project-root scratch, removed after use):
pre-removal baseline `before.json` then post-removal `after.json` across `/login`, `/qr-viewer`,
`/student/update-photo?search=`, `/unpaid-verification`, `/grantee-update` — post-removal structural
gates 5/5: **0 Bootstrap CSS requests, `window.bootstrap` undefined, 0 console errors, 0 page
errors, 200**; computed-style after↔before diff on all 5 pages × 8 selectors (body,
`input.form-control`, `button.btn-navy`, `label.field-label`, `.data-card`, `.alert`, `select.form-
select`, `h1`) × 44 props each: **0 diffs**. Family spot-checks live: `.list-group` flex column +
flush item borders 1px bottom (`#dee2e6`, last-child 0), `bg-transparent` rgba(0,0,0,0); `.d-none`
display none (JS contract intact), `h1.mb-3` = 14px / `h1.mb-1` = 3.5px (Bootstrap values winning as
today). Mobile spot-checks at 375×667 / 768×1024 on `/login` + `/student/update-photo`: **no
horizontal overflow**, cards centered.

**Notes / decisions documented:**
- **Odd claim-checked:** `.list-group`/`.accordion`/`.form-label` live DOM exists only on
  auth-gated screens (`grantee_update/_self_update_tab`, `admin/users/show`, `clients/_gip`,
  permissions) — byte-faithful rules verified textually against the 5.3.2 dist and the two
  reachable list-group branches (update-photo) verified live. E2E suites not run: auth-gated
  screens require an admin account (none exists) and DB must remain untouched at 1604 audit rows —
  documented limitation.
- **Tailwind Preflight deliberately NOT enabled** in place of §4.10 (the plan contemplated flipping
  it on once Reboot was gone). Preflight would change bare headings to `font-size:inherit`, `img` to
  `display:block`, `hr`/`button`/table defaults — visible drift on authenticated screens. §4.10
  Reboot + type parity preserves pixel parity. A Preflight review is future baseline work, not this
  phase.
- `tailwind @source` allowlist unchanged: the 7 standalone pages are scanned; the unscanned
  partials (`grantee_update/_self_update_tab`, `admin/users/show`) are fully owned by
  ui.css §4.8–4.10.

**Result:** PASS. Bootstrap CSS dependency: **8 CDN links → 0**. Removal-gate (Bootstrap CSS):
**READY**. Remaining third-party CSS = project-owned `datatables.css` (Phase 25); jQuery/DataTables/
Alpine intact (out of scope). Finalization handled in the docs pass that follows this entry.

---

### 2026-09-07 - Phase 28: Final Bootstrap-Free Forensic Audit (AUDIT ONLY)

**Phase 28 deliverable:** the final independent forensic audit establishing whether the project is
genuinely Bootstrap-free. **Audit only — no application source, CSS, JS, schema, or database was
changed.** Default behavior throughout: INSPECT → VERIFY → REPORT → STOP. No migration, no redesign,
no refactor, no DataTables/jQuery replacement, no new dependencies were performed.

**A. Migration history read.** `docs/IMPLEMENTATION_LOG.md` (Phases 0–27), `docs/TAILWIND_MIGRATION_
EXECUTION_PLAN.md`, `docs/SESSION_HANDOFF.md`, and the Phase 23–27 reports were read in full. All
previously accepted phases are treated as contractual: Phase 24 removed the Bootstrap JS bundle (6 CDN
`<script>`s → 0), Phase 25 created `public/css/datatables.css` and replaced the 11 DataTables bootstrap5
CSS skin CDN links, Phase 26 owned the shared Bootstrap-CSS component families in `ui.css` §4 (49
`--bs-*` refs → 0), Phase 27 removed the 8 Bootstrap CSS CDN `<link>`s and owned Reboot/type parity
plus the last families in `ui.css` §4.8–4.10 and removed the dangling `admin/users/show` trigger.

**B. Repository-wide Bootstrap search.** Fresh search across views, `resources/js`, `resources/css`,
`public/css`, `public/build`, `routes`, `package.json`, `vite.config.js`, tests and `e2e` for every
listed term (`bootstrap`, `Bootstrap`, `bootstrap@5.3.2`, `bootstrap.min.css`, `bootstrap.css`,
`bootstrap.bundle`, `bootstrap.min.js`, `bootstrap.js`, `window.bootstrap`, `bootstrap.Modal`,
`bootstrap.Toast`, `bootstrap.Dropdown`, `bootstrap.Collapse`, `bootstrap.Popover`, `bootstrap.Tooltip`,
`bootstrap.Tab`, `bootstrap.Offcanvas`, `data-bs-`, `--bs-`). Every occurrence was classified:

- **A. Active dependency: 0.** No live Bootstrap runtime/application dependency of any kind.
- **B. Historical documentation:** the thousands of hits in `docs/*.md` (`IMPLEMENTATION_LOG.md` 548,
  `TAILWIND_MIGRATION_EXECUTION_PLAN.md` ~300, `SESSION_HANDOFF.md` ~166, `TAILWIND_MIGRATION_FORENSIC_
  AUDIT.md`, `UI_UX_ANALYSIS.md`, etc.) describe the completed migration.
- **C. Comments:** every remaining `bootstrap.*` / `data-bs-*` occurrence in Blade views is a
  historical comment (e.g. `partials/record-view-modal.blade.php` L4/12/22, `layouts/app.blade.php`
  L77/132–137, `scanners/scan.blade.php` L267, `clients/_details.blade.php` L342–345, scholars/users/
  audit_logs/transactions/households/unpaid index comments). `app.css` L393/446 and `datatables.css`
  L11/455–577 `--bs-*` mentions are comments documenting inlined literal values.
- **D. Test locator:** `e2e/alpine-phase0.spec.ts` L38 asserts `link[href*="bootstrap@5.3.2"]` count 0
  (intentional absence assertion renamed "login page boots cleanly with Bootstrap CSS removed
  (Phase 27)"); the 15 other E2E specs and `HouseholdTest`/`TransactionTest` contain negative
  assertions or historical references.
- **E. Dead/inert source:** `ui.css` L808/813 `[data-bs-popper]` parity rules (no Bootstrap JS loads,
  the Alpine-toggled `dropdown-menu.show` rules drive the real menus) and `datatables.css` L582
  `[data-bs-theme=dark]` (no theme toggle uses it) — inert parity selectors only.
- **F. Third-party source:** the Laravel framework `bootstrap/` directory and `resources/js/
  bootstrap.js` (Laravel's axios shim — 4 lines, imports axios, zero Bootstrap-theme code);
  `phpunit.xml` `bootstrap=` attribute; `composer.lock` framework `bootstrap.php` entries; and
  **`dataTables.bootstrap5.min.js`** (see §7).

**C. CSS network audit — PASS.** Blade/template asset references: `cdn.jsdelivr.net/npm/bootstrap`,
`bootstrap@5.3.2/dist/css`, `bootstrap@5.3.2/dist/js` all **0** in all views, compiled views
(`view:cache` then `rg` over `storage/framework/views`), built assets (`public/build`), and served
HTML. The only remaining external `<link>` on the shared layout and the standalone pages is Google
Fonts; the only remaining third-party JS CDNs belong to jQuery/DataTables (`code.jquery.com`,
`cdn.datatables.net`) and `unpkg.com/html5-qrcode` (scanners/scan). None are Bootstrap.

**D. Runtime Bootstrap audit — PASS.** Live Chromium on the six reachable public surfaces
(`/login`, `/qr-viewer`, `/unpaid-verification`, `/grantee-update`, `/student/update-photo?search=`,
`/student/verify/{id}`): **all HTTP 200**, `typeof window.bootstrap === "undefined"` everywhere,
**zero** Bootstrap network requests observed, **zero** console errors, **zero** page errors. Runtime
scan for `bootstrap.Modal/Toast/Dropdown/Collapse/Tooltip/Popover/Tab/Offcanvas`: **0 live calls**
(the only matches are comments in views). `e2e` negative assertions already enforce `window.bootstrap
=== undefined` on the shell surfaces.

**E. CSS variable audit — PASS.** Active `--bs-*` consumption **= 0** repository-wide:
`var(--bs-` occurs nowhere in `resources` or `public` (rg across both returned zero). Every `--bs-`
string in `resources/css/app.css` (2, comments) and `public/css/datatables.css` (15, comments
annotating inlined literal values such as `color:#6c757d; /* Bootstrap --bs-secondary-color */`) is
non-executing documentation; no literal value depends on a `var(--bs-*)`. `ui.css` declares/consumes
zero `--bs-*`. The final project does not depend on Bootstrap custom properties.

**F. Class ownership audit — PASS (ownership matrix below).** A Bootstrap-looking class name does
not imply the Bootstrap stylesheet; every family the app renders is owned by Tailwind, `ui.css`,
`app.css`, or `datatables.css`, with live computed-style parity spot-checked (below).

| Component       | Active usage | CSS owner                                  | Bootstrap required |
| --------------- | -----------: | ------------------------------------------ | -----------------: |
| `.btn` (base)   |          yes | `ui.css` §4.1 (Bootstrap-faithful base+sm) | NO |
| `.btn-navy/.btn-gold/.btn-red/.btn-subtle/.btn-outline-red/.btn-outline/.btn-icon` | yes | `app.css` (Tailwind-vocabulary project buttons) | NO |
| `.btn-primary/.btn-outline-primary/.btn-danger/.btn-outline-danger` | few | `ui.css` §4.1 variants | NO |
| `.form-control/form-select/form-check/.input-group` | yes | `ui.css` §4.2, §4.3 | NO |
| `.alert` | yes | `ui.css` §4.5 | NO |
| `.table/.table-sm/.table-responsive` | yes | `ui.css` §4.6 | NO |
| `.btn-close` | yes | `ui.css` §4.3 | NO |
| `.btn-group` | yes | `ui.css` §4.4 | NO |
| `.dropdown-toggle/.dropdown-menu/.dropdown-item` | yes | `ui.css` §4.7 (`.show` Alpine-toggled) | NO |
| `.modal*` | yes | Alpine + Tailwind (all 15 modals migrated Phases 8–18) | NO |
| `.accordion*` | yes | `ui.css` §4.8 | NO |
| `.list-group*` | yes | `ui.css` §4.8 | NO |
| `.form-label` | yes | `ui.css` §4.8 | NO |
| `.d-none`/`.d-flex`/grid/utilities | yes | `ui.css` §4.9 | NO |
| Reboot/type elements | yes | `ui.css` §4.10 | NO |
| DataTables chrome | yes (11 screens) | `datatables.css` (project-owned) | NO |

**G. DataTables final audit — PASS.** All 11 DataTables screens confirmed (`admin/audit_logs/index`,
`admin/users/index`, `clients/index`, `duplicates/index`, `households/index`, `payouts/attendance`,
`scholars/index`, `scholarship_reports/index`, `transactions/index`, `unpaid_verifications/index`,
`update_logs/index`): each loads `jquery.dataTables.min.js` (11 refs) + `dataTables.bootstrap5.min.js`
(11 refs, the class-emitter renderer) + project-owned `css/datatables.css` (11 refs). The DataTables
bootstrap5 integration JS is **intentionally retained** and is **NOT the Bootstrap library**: fetched
the 1.13.6 dist (2,358 bytes) — it UMD-wraps only `jquery` + DataTables, extends defaults
(`dom`, `renderer:"bootstrap"`, `sFilterInput:"form-control form-control-sm"`, `sLengthSelect:
"form-select form-select-sm"`, `sPageButton:"paginate_button page-item"`) and contains **zero**
references to `bootstrap.Modal`, `window.bootstrap`, or any external Bootstrap library load. It has
**no Bootstrap runtime dependency**; the class names it emits are owned by `datatables.css`. The
DataTables bootstrap5 **CSS** skin CDN is gone (`dataTables.bootstrap5.min.css` = 0). DataTables
behavior (search/sort/pagination/AJAX) is unchanged — zero app changes in this phase, and the
Phase 25 swap was a pure in-place `<link>` swap with token overrides kept winning.

**H. CSS architecture audit — PASS.** Ownership chain as designed: Tailwind → `@vite`-built
`app.css` (tokens + project buttons/shell) → `ui.css` (shared semantic UI + Bootstrap-parity §4) →
`datatables.css` (DataTables only, scoped to `.dataTables_wrapper`). Observations (no changes made
— not Bootstrap dependencies, per scope rules): the `ui.css` **header comment (L5–7)** still says it
is "loaded immediately AFTER the Bootstrap 5.3.2 CDN <link>" — a **stale historical comment** from
before Phase 27; functionally inert (the cascade rationale no longer applies; nothing depends on it).
No duplicated component definitions, no accidental Bootstrap source copies, no unnecessary
`!important` (the §4.9 `!important` utilities mirror Bootstrap's own important semantics — that is
intentional parity), no conflicting selectors, no obsolete variable bridges (the `--bs-btn-*` bridge
was removed in Phase 26), no DataTables skin duplication (one `datatables.css`).

**I. Asset graph audit — PASS.** Shared layout: `@vite(app.css)` + `css/ui.css` + Google Fonts +
`@vite(app.js)`/Alpine + per-screen `@stack` assets. Login: `@vite(app.css)` + `css/ui.css` + Roboto
Fonts (no Alpine). QR viewer: `@vite(app.css)` + `css/ui.css` + Roboto (dedicated inline script).
Student verify: `@vite(app.css)` + `css/ui.css` + Roboto. Student update-photo: same. Student
photo-upload: `@vite(app.css, app.js)` (Alpine `photoModal`) + `css/ui.css` + Roboto. Unpaid
self-service: `@vite(app.css, app.js)` + `css/ui.css` + Roboto. Grantee-update self-service:
`@vite(app.css)` + `css/ui.css` + Roboto. DataTables screens: layout assets + jquery/DataTables/DT-bs5-
renderer + `css/datatables.css` + per-screen token <style>. **No asset is Bootstrap-dependent.** The
only third-party assets are Google Fonts, jQuery, DataTables, DataTables bootstrap5 class renderer,
and html5-qrcode — none load Bootstrap.

**J. Public-page final verification.** Six public surfaces verified live in Chromium at desktop
(1280×800): HTTP 200 on all; zero Bootstrap CSS requests; zero Bootstrap JS requests; `window.bootstrap`
undefined; zero console/page errors; forms and buttons render (labels visible/associated; `input`
controls styled); documented computed-style spot values confirmed (below). **Authenticated pages:
AUTH-GATED — NOT BROWSER-EXECUTED** — the local `main_system` is the byte-identical production copy
with no admin test account and the DB must not be written; verified statically (head asset graph,
Blade scan of every screen, compiled views) — documented limitation, consistent with Phases 19–27.

**K. Responsive final check — PASS.** Chromium at 375×667 and 768×1024 on `/login`,
`/student/update-photo?search=`, `/qr-viewer`, `/unpaid-verification`: **no horizontal overflow**
(`scrollWidth <= clientWidth`), zero console errors. Cards centered; no Bootstrap CSS dependency.

**L. Accessibility regression audit.** Focused re-audit of the Bootstrap-removal-affected components
(no redesign): modals all carry `role="dialog"` + `aria-modal="true"` + `aria-labelledby` +
focus-restore + ESC + backdrop-close + scroll-lock + Tab-trap (Alpine stores — confirmed in
`photo-upload`, `unpaid_verifications/self-service`, `partials/record-view-modal`,
`client-feedback-modal`, `confirm-modal`); `.btn-close` keeps `aria-label="Close"` on every toast/
alert close; form labels remain `<label for>`-associated (login, verify, grantee-update fields,
photo-upload camera select `aria-label`); focus indicators preserved via `:focus-visible` outlines
(`ui.css` §4 navies/golds + Bootstrap-blue `.btn-close` glow kept); accordion (permissions) keeps
`data-bs-bs`-free native Bootstrap-scoped button + collapse semantics owned by `ui.css` §4.8; `d-none`
JS toggle contract unchanged. No Bootstrap-removal regression found.

**M. Functional/automated regression — PASS.**
- PHPUnit: **301 tests / 1419 assertions / 0 failures** (34.22 s).
- Pint: clean (`vendor\bin\pint --test` → `"tool":"pint","result":"passed"`).
- `npm run build`: clean (vite v6.4.3, `app-D3mz-Mcl.css` 57.62 kB, `app-DqsLDVL_.js` 105.70 kB);
  built assets contain zero `bootstrap`/`data-bs-`/`--bs-` hits.
- `view:cache`: clean; compiled-view scan shows zero `bootstrap@5.3.2`, zero `jsdelivr bootstrap`
  links, zero live `--bs-`; `view:clear` done after.
- No tests modified; no application behavior modified to pass.
- E2E suites not run: auth-gated screens require an admin account that does not exist; DB must remain
  untouched — documented limitation (Phase 27 status unchanged; the Phase 27 absence assertion now
  also verified live by this phase's Chromium run on `/login`).

**N. Database integrity — PASS.** This phase ran **no** migration, **no** `artisan migrate`, no schema
change. All phases' writes go through the `main_system_test` DB (phpunit.xml forces
`DB_DATABASE=main_system_test`); the shared `main_system` was touched only by read-only SELECTs and
public GETs (no login POST, no audit writes by this audit). Table list (42 tables) and the
`migrations` table (12 rows: 9 real + `__legacy_v1_baseline_schema__` sentinel + 2 P12 tables) are
unchanged. **Environment observation (pre-existing, not caused by this phase):** `tbl_audit_logs`
currently holds 1605 rows vs the documented 1604 baseline — one `LOGIN` row (id 1735, user_id 3,
created 2026-09-07 02:14) created **before** this phase began (~12:00); the 1604-row baseline was
itself already a post-P27 recorded number and this row is outside this phase's actions. No rows were
added or removed by Phase 28 verification; reported as an environment finding only, **not modified**
(deleting it would itself be a DB write, forbidden in an audit-only phase).

**O. Final dependency verdict — PASS.**
- CSS: Bootstrap CSS CDN **0**; Bootstrap CSS asset **0**; active Bootstrap CSS dependency **0**.
- JS: Bootstrap JS CDN **0**; Bootstrap JS asset **0**; live Bootstrap API calls **0**;
  `window.bootstrap` **undefined**.
- Variables: active `--bs-*` consumption **0**.
- DataTables: Bootstrap skin CSS **0**; project-owned DataTables CSS **YES**; DataTables
  functionality preserved **YES**.
- Application: **no active Bootstrap dependency**.

**P. Historical references.** Historical Phase reports, migration documentation, inert comments,
the E2E absence locator, and inert parity selectors remain in the repo by design. Zero-text-result
grep is not the goal — zero **active** dependency is, and it is achieved.

**Result: PASS. Bootstrap CSS: REMOVED. Bootstrap JS: REMOVED. DataTables Bootstrap CSS: REMOVED.
Active `--bs-*` dependencies: 0. Application Bootstrap dependency: 0. BOOTSTRAP-FREE — COMPLETE.
No further Bootstrap-removal phase is required.**

---

**Phase 26 deliverable:** second controlled **Bootstrap-CSS** ownership migration (the Phase 23
removal-gate's shared-component candidate). Project-owns every shared Bootstrap class that screens
loading `ui.css` actually render, byte-faithful to Bootstrap 5.3.2, so those screens keep rendering
identically with Bootstrap CSS loaded or removed. `ui.css` `--bs-*` references: **49 → 0**. Bootstrap
CSS CDN (8 links) is **untouched and still required** for its remaining consumers (below).

**What changed** (single source file — `public/css/ui.css` only; zero blade edits):
- Rewrote `ui.css` §4 ("Shared component ownership (Bootstrap CSS independence)", sections
  4.1–4.7) as a project-owned ownership layer. Scope was driven by a real-usage census of every
  shared Bootstrap class in the Blade templates:
  - **4.1 Buttons:** `.btn` base (private `--ui-btn-*` tokens, `color:#212529`, `.375rem` radius —
    stock Bootstrap at the app's 14px root (5.25px), `border:1px solid transparent`,
    `background-color:transparent`, `.btn:hover{border-color:currentColor}` replicating
    Bootstrap's unset-hover-var behavior, `.btn:focus-visible{outline:0}` +
    `box-shadow:0 0 0 .25rem` glow, combined `.btn:active` block, `.btn.disabled,:disabled`
    `pointer-events:none;opacity:.65`), `.btn-sm`. Project variants with full
    hover/focus-visible/active/active:focus-visible/disabled states: `.btn-primary`,
    `.btn-outline-primary`, `.btn-danger`, `.btn-outline-danger` (focus/active glows
    `rgba(0,56,168,.5)` / `rgba(206,17,38,.5)`; variant disabled keeps filled bg + brand colors).
    `.btn.btn-gold:focus-visible` gold glow only — **`.btn-gold` base and the
    `--bs-btn-*` bridge and the `.btn:hover` gradient bridge are REMOVED** (app.css fully owns
    `.btn-gold`/`.btn-navy`/`.btn-red`/`.btn-subtle`/`.btn-outline-red`/`.btn-outline`/`.btn-icon`;
    the old hover-bg was invisible under the gradient; standalone `.btn-gold` elements keep the
    global gold `:focus-visible` outline).
  - **4.2 Form controls:** `.form-control` (+ file-selector-button, navy focus border +
    `--ui-focus-ring`, `#6c757d` placeholder, `#e9ecef` disabled bg, `.form-control-sm`),
    `.form-select` (+ `stroke='%23343a40'` chevron data-URI, focus, `[multiple]`, disabled,
    `.form-select-sm`), `.form-check(-label/-input)` (navy re-point: checked/indeterminate/radio
    white SVG data-URIs, `.form-check-input:disabled` opacity). **JS-targeted class names
    unchanged.**
  - **4.3 Close buttons:** `.btn-close` + X data-URI, hover `opacity:1`, focus **Bootstrap blue
    glow `rgba(13,110,253,.25)`** kept, `.btn-close:disabled`, `.btn-close-white` filter.
  - **4.4 Button groups:** `.btn-group` (inline-flex, `>.btn` radius join rules,
    `.btn:active`/`.btn:focus` z-index + `.btn:first-child:not(:last-child)` spacing-as-border
    alternative), `.btn-group-sm`, `.btn-group-lg`.
  - **4.5 Alerts:** `.alert` base + `.alert-danger`/`.alert-success`/`.alert-warning`/`.alert-info`
    (Bootstrap 5.3 emphasis/subtle hexes: danger `#58151c/#f8d7da/#f1aeb5`, success
    `#0a3622/#d1e7dd/#a3cfbb`, warning `#664d03/#fff3cd/#ffe69c`, info `#055160/#cff4fc/#9eeaf9`),
    `.alert-dismissible` + embedded `.btn-close` padding.
  - **4.6 Plain tables:** `.table` (`margin-bottom:1rem` Bootstrap-faithful — DataTables spacing is
    unaffected because `datatables.css` only zeroes scroll tables; the 6 non-DT tables carry the
    Bootstrap `mb-0` utility so they render 0 today; **`mb-0` ownership is explicit future work**),
    cell contract (padding `.5rem`, `color:#000` — Bootstrap's current emphasis color; DataTables
    scopes `#212529` to its own chrome), `.table-sm`, `.align-middle`, `.table-responsive`.
  - **4.7 Dropdown CSS:** `.dropdown` (relative), `.dropdown-toggle` + `.dropdown-menu` arrow
    caret + `:hover`/`:focus` variants, `.dropdown-menu` (absolute, z-index 1000, `.375rem` radius,
    10rem min-width, padding `.5rem 0`, `.175`-alpha border, shadow), the `[data-bs-popper]` and
    `.dropdown-menu-end` parity rules, `.dropdown-menu.show` (`display:block`), `.dropdown-item`
    (+ `:hover/:focus`, `.active`, `.disabled`). The real menus (navbar, clients/transactions export
    menus) carry NO `data-bs-popper` — Alpine toggles `.show` — so unanchored rules keep current
    rendering (anchored rules kept verbatim for parity).
- Retained untouched: pagination block (`ui.css`), every Blade template, `app.css`, `datatables.css`,
  the 8 Bootstrap CSS links, DataTables skin JS, jQuery, Alpine, all backend/route/DB behavior.

**Post-change data:**
- `ui.css`: 1042 lines, braces balanced 138/138, **0 `--bs-*` references** (was 49). Repo-wide
  audit: `var(--bs-*)` live consumption = **0 anywhere**; only comment-only mentions remain
  (datatables.css 15 incl. 1 prose line, app.css 2 comments, docs).
- JS contract unchanged (Phase 24 state): 0 live `bootstrap.*()` calls (comment-only in
  `partials/record-view-modal.blade.php`), 0 Bootstrap JS CDN links.
- Bootstrap CSS `<link>`s still 8; DataTables skin CSS still `datatables.css` (Phase 25).

**Audits (this phase, reference-only):**
- **7 standalone public pages** remain Bootstrap-CSS-dependent (load the CDN, not `ui.css/app.css`):
  `auth/login`, `qr/viewer`, `students/verify`, `students/update-photo`, `students/photo-upload`,
  `unpaid_verifications/self-service`, `grantee_update/self-service` — mix Bootstrap classes
  (`btn`, `btn-gold`, `btn-outline-primary`, `btn-sm`, `alert-*`, `d-none`, `d-flex`, `col-md`) +
  Tailwind utilities + custom (`data-card`, `field-label`). No CDN removal — they are their own
  final-phase family.
- **Dangling trigger re-verified** (`admin/users/show.blade.php:134`): the Reset Password button's
  `data-bs-toggle="modal" data-bs-target="#passwordModal"` is **dead** — `#passwordModal` exists
  only in `admin/users/index.blade.php`, not on the show page, and no Bootstrap JS loads. The show
  page's real flow is the native `data-panel-edit-form` POST to `admin.users.reset-password`.
  Documented as a finalization-pass removal candidate — **no markup changed** (CSS-only phase).
- Remaining Bootstrap-CSS consumers after Phase 26: `.modal*` subsystem (48 class refs, 15 modal
  instances), `.accordion` (10, permissions), `.list-group` (7, permissions), `.form-label` (46,
  margin-bottom only), `.d-none`/`.d-flex`/`.toast` utilities (incl. clients flash toast — 90%
  Tailwind-covered), plus the 7 standalone pages.

**Verification:** full suite **301 passed (1419 assertions), 0 failures**; Pint clean; `npm run
build` clean (identical hashes app-D3mz-Mcl.css 57.62 kB / app-DqsLDVL_.js 105.70 kB); `view:cache`
clean + compiled-view scan shows 0 `--bs-` (0 stale compiled references); `view:clear` done; served
HTTP 200 (`/login`, `/qr-viewer`, `/student/update-photo`, `/unpaid-verification`,
`/grantee-update`, `/css/ui.css`, `/css/datatables.css`). **Live Chromium harness** (source file
removed after run): injected `ui.css` + production `app.css` into the `/login` harness — 48/48
computed-style assertions pass at the app's 14px root (`.btn-primary` white/navy/5.25px radius,
`.btn` `.375rem` byte parity, `.btn-sm` 12.25px/3.5px, `.btn.btn-gold` app.css gradient + navy,
`#dee2e6` form-control, form-select chevron SVG, checked-navy, input-group + `#e9ecef` text,
btn-close 14px/opacity .5/`.btn-close-white` filter, btn-group inline-flex + 0px joins, alert
hexes, `.table` 14px bottom/7px cells/`#000`, `.table-sm` 3.5px, table-responsive, dropdown
relative/menu absolute+block+140px+shadow, dropdown-item padding, caret) — **zero console errors**.
E2E suites not run: auth-gated screens require an admin account (none exists) and DB must remain
untouched at 1604 audit rows — documented limitation, visuals verified statically + via the harness.

**Result:** PASS. `--bs-*` live refs 49 → 0. Removal-gate (Bootstrap CSS): still **NOT READY** —
remaining consumers above. Phase 27 recommendation: own the `.modal*` subsystem + `.accordion`/
`.list-group`/`.form-label`/`.d-none`-class utilities app-wide (browser-verified per screen), then
the 7 standalone public pages, then remove the final Bootstrap CSS `<link>`; the dangling
`admin/users/show:134` trigger is a finalization-pass removal.

**Phase 25 deliverable:** first controlled **Bootstrap-CSS** ownership migration (the
Phase 23 removal-gate's CSS-side candidate, executed as its own phase after Phase 24 removed the
JS bundle). Migrated the **only** Bootstrap-CSS dependency with a project-owned, self-contained
replacement: the DataTables 1.13.6 Bootstrap 5 **skin CSS** on the 11 DataTables screens.
Without a safe self-contained replacement it would stay (per the phase rule); the skin qualified.
Bootstrap CSS as a whole is **untouched and still required** — this phase owns DataTables chrome
only.

**What changed:**
- NEW `public/css/datatables.css` (~430 lines, plain CSS, no build step). **Self-contained:
  zero `var(--bs-` consumption** (the 14 `--bs-` strings present are documentation comments).
  It (a) reasserts the upstream 1.13.6 bootstrap5 skin core chrome with identical
  selectors/values — sort arrows, processing loader + keyframes, wrapper/length/filter/info/
  paginate layout, `table.dataTable` base (`clear`, 6px margins, `max-width:none`,
  `border-collapse:separate`), `box-sizing`, alignment `dt-*` utilities, scroll head/body/foot,
  responsive 767px centering, `--dt-*` tokens, `[data-bs-theme=dark]` overrides — and (b) adds a
  **Bootstrap-equivalent layer scoped strictly to `.dataTables_wrapper`** owning the Bootstrap
  class names DataTables' generated markup carries: `.table(-sm)` cell contract + `.align-middle`,
  `.form-control(-sm)` filter input and `.form-select(-sm)` length select (incl. dropdown chevron,
  min-heights, `::placeholder`), and the full `.pagination`/`.page-item`/`.page-link` contract
  (flex, join margin, radii, hover, focus/focus-visible, active, disabled) using project tokens
  (navy / navy-hover / `--ui-focus-ring` gold) exactly where `ui.css` already re-points Bootstrap,
  and Bootstrap 5.3.2 literal values (`#dee2e6`, `#e9ecef`, `#6c757d`, `#0a58ca` focus color, 1rem
  font, 0.375rem radius) elsewhere — so rendering is byte-faithful with Bootstrap loaded and still
  correct after it is removed.
- Swapped the 11 per-screen `<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/
  dataTables.bootstrap5.min.css">` includes for `<link rel="stylesheet"
  href="{{ asset('css/datatables.css') }}">`, **in place** (each screen's own `@push('styles')`,
  immediately before its token `<style>` so token overrides keep winning): `admin/audit_logs/index`,
  `admin/users/index`, `clients/index`, `duplicates/index`, `households/index`,
  `payouts/attendance`, `scholars/index`, `scholarship_reports/index`, `transactions/index`,
  `unpaid_verifications/index`, `update_logs/index`.
- Retained untouched: every per-screen token skin, `dataTables.bootstrap5.min.js` (11 refs — the
  class-name emit contract DataTables needs; its classes are now owned by `datatables.css`),
  jQuery 3.7.1, Bootstrap 5.3.2 CSS (8 links), the DEAD `admin/users/show:134` trigger, all
  backend/route/DB/Alpine/JS behavior.

**Post-change data:**
- `dataTables.bootstrap5.min.css`: **11 → 0** refs in views and in compiled output (the only
  mention left is the header comment in `datatables.css` itself). `asset('css/datatables.css')` in
  all 11 screens; compiled-view scan (`rg --no-ignore` leaves the `.gitignore` ghost) = 11 files
  referencing `css/datatables.css`, 0 referencing the CDN skin.
- `public/css/datatables.css` served HTTP 200 (20,561 B); `var(--bs-` consumption = 0.
- Bootstrap CSS `<link>`s still 8 (layout + 7 standalone pages); Bootstrap JS bundle still absent
  (Phase 24).

**CSS ownership after Phase 25:** Tailwind + `app.css` (`--color-*`/`--radius-*` shell + buttons);
`ui.css` (`--ui-*` tokens, badges/cards, `.form-*`/`.page-link` focus, pagination re-points); and
**`datatables.css` (all DataTables chrome — now Bootstrap-independent)**. Remaining Bootstrap
reliance (evidence): Bootstrap `.btn` base consumed by the ui.css 49-`--bs-btn-*` bridge (26
`btn btn-*` class combinations incl. `btn-primary`×1, `btn-danger`×1, `btn-outline-primary`×2,
plus `btn-close`×14, `btn-group`×2), `.form-control`/`.form-select`/`.form-check-input` bases
(ui.css only themes focus/checked), `.alert`, `.table`/`.table-sm` outside DataTables, the
`table-responsive` wrapper (`transactions/index:130`), `.dropdown-toggle`/`.dropdown-menu` (2), and
the 7 standalone public pages (each loads Bootstrap CSS itself).

**Verification:** full suite **301 passed (1419 assertions), 0 failures (40.04s)**; Pint clean;
`npm run build` clean (vite v6.4.3); `view:cache` clean + compiled-view scan as above +
`view:clear`; live HTTP checks OK (`/login` 200, `css/datatables.css` 200). **E2E suites not
run** — they require an admin login account that does not exist and DB must remain untouched at
1604 audit rows (documented limitation, consistent with Phases 20-24). DataTables screens are
auth-gated so no live browser render of DataTables was possible; the swap preserves the visual
contract byte-for-byte under the still-loaded Bootstrap CSS (verified statically + via the
compiled output and the full suite).

**Files changed:** `public/css/datatables.css` (new); the 11 DataTables screen blades above (one
`<link>` each). Docs: this log, `TAILWIND_MIGRATION_EXECUTION_PLAN.md`, `SESSION_HANDOFF.md`.

**Removal-gate (Bootstrap CSS): `NOT READY`** — remaining consumers: `.btn`/`--bs-btn-*` bridge,
`.form-*`/`.form-check-*` bases, `.btn-close`, `.alert`, `.table` outside DataTables,
`.table-responsive`, dropdowns, 7 standalone pages. **Next candidate (Phase 26, identify only):**
own the `.btn` + `.form-control`/`.form-select`/`.form-check-input` bases in project CSS
(app-wide — required browser verification per screen), then the standalone public pages; the
dangling `admin/users/show:134` trigger remains a finalization-pass item.

---

### 2026-09-06 - Phase 24: Bootstrap JS bundle removal (CDN `<script>` cleanup)

**Phase 24 deliverable:** remove the redundant Bootstrap 5.3.2 JavaScript CDN
bundle, keep Bootstrap CSS fully intact, keep everything else working. This was
the "controlled Bootstrap JS bundle removal" phase recommended by Phase 23.

**Workflow followed:** inspect → remove six JS includes → verify → regression
test → document → STOP.

**Pre-change inventory:** exactly **six** `<script src="https://cdn.jsdelivr.
net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">` tags existed:

| View | Line (pre-removal) |
|---|---|
| `resources/views/layouts/app.blade.php` | 111 |
| `resources/views/auth/login.blade.php` | 73 |
| `resources/views/qr/viewer.blade.php` | 101 |
| `resources/views/students/photo-upload.blade.php` | 103 |
| `resources/views/unpaid_verifications/self-service.blade.php` | 111 |
| `resources/views/grantee_update/self-service.blade.php` | 241 |

**Changes made:**
1. Removed all six Bootstrap JS CDN includes (nothing skipped).
2. Updated the now-false `unpaid_verifications/self-service.blade.php` Batch-G
   head comment that previously claimed "the bootstrap.bundle CDN script is
   kept for coexistence" — it now records the Phase 24 removal (Bootstrap JS
   had zero live consumers; Bootstrap CSS retained). Historical Phase 17 note
   preserved.

**Bootstrap CSS — intentionally retained, untouched:** all 8 CSS `<link>
bootstrap@5.3.2/dist/css/bootstrap.min.css` references confirmed present after
removal (8 files: `layouts/app`, `auth/login`, `qr/viewer`, `students/verify`,
`students/update-photo`, `students/photo-upload`,
`unpaid_verifications/self-service`, `grantee_update/self-service`).
DataTables `dataTables.bootstrap5.min.css` + skin JS + jQuery CDN and Alpine
untouched; no `ui.css` change; dangling `admin/users/show.blade.php:134`
trigger intentionally untouched (with Bootstrap's delegated handler removed,
its previously-possible TypeError can no longer occur; cleanup deferred to a
finalization phase).

**Post-change static audit:**
- `bootstrap.bundle.min.js` / `bootstrap.min.js` in views: **0 references**.
- `bootstrap@5.3.2/dist/css` in views: **8** (unchanged).
- Live `bootstrap.*()` API calls (`new bootstrap.` / `bootstrap.Modal|Toast|
  Collapse|Dropdown|Tooltip|Popover|Tab|Offcanvas`): **0** — the only matches
  left are historical Blade comments (`partials/record-view-modal`,
  migration notes), not executable.
- Live `data-bs-*` attributes: only `admin/users/show.blade.php:134` (the
  documented dead/inert trigger).
- `resources/js/bootstrap.js` (Laravel axios shim): **untouched**; built
  `app.js` re-confirmed **0** bootstrap hits.

**Verification:**
- `php artisan test` — **301 tests / 1419 assertions / 0 failures** (58.02s).
- `npm run build` (vite v6.4.3) — clean (built in 1.21s).
- `vendor\bin\pint --test` — passed.
- `php artisan view:cache` + compiled-view scan — no compiled view references
  `bootstrap.bundle` / `cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js`;
  `view:clear` done.
- **Live browser console check (Chromium):** `/login` and `/qr-viewer` (two of
  the six edited, public pages) both return HTTP 200, expose **no** bootstrap
  bundle script and **no** jsdelivr bootstrap JS source in the document,
  report `typeof window.bootstrap === 'undefined'`, and produce **zero**
  console/page errors (no `bootstrap is not defined`, no `bootstrap.Modal`/`
  Toast`/etc. failures). This closes the §12 console-error gate — Phase 23's
  removal-safety conclusion is confirmed, not contradicted.
- **E2E suites (navbar-phase21, flash-toast-phase19, page-toasts-phase20,
  client-feedback-phase9, smoke, DataTables-backed pages): not run.** They all
  require an authenticated admin session; no ephemeral account has been
  created and the DB must remain byte-identical at the 1604 audit-row
  baseline (AGENTS.md) — documented limitation, consistent with Phases 20–23.

**Files changed:** the six views (Bootstrap JS bundle line removed) +
`unpaid_verifications/self-service.blade.php` comment text (part of the same
file); docs: this log, `TAILWIND_MIGRATION_EXECUTION_PLAN.md`,
`SESSION_HANDOFF.md`.

**Out of scope and untouched:** Bootstrap CSS, DataTables skin, `ui.css`,
dangling trigger, `resources/js/bootstrap.js`, Alpine, jQuery, auth/routes/
controllers/DB.

**Next candidate (identify only):** a DataTables-skin / Bootstrap-**CSS**
ownership phase (decide whether the 8 remaining Bootstrap CSS CDN includes can
be consolidated or must stay for the DataTables/`ui.css`/`.btn` base chrome),
or the dangling-trigger cleanup within a finalization pass. Not implemented.

---

### 2026-09-06 - Phase 20: Transactions + Households page-level success toasts -> Alpine.js

Migrated **only** the two page-level success toasts rendered when a controller
flashes `session('success')` — `resources/views/transactions/index.blade.php`
and `resources/views/households/index.blade.php` — from Bootstrap Toast JS to
**Tailwind classes + Alpine.js state**, as two independent surfaces (per-page
`x-data` scope; never shared with the layout's `#flashToast`). **Frontend-only**:
no schema, ACL, route, controller, validation-rule or business-rule change — the
flash contract is unchanged (presentation channel only).

**Why these surfaces:** at the end of Phase 19 the only remaining child-view
`.toast` elements served by the shared layout init loop (which runs
`bootstrap.Toast.getOrCreateInstance(el, { autohide: false }).show()` on every
`.toast`) were the transactions and households success toasts plus the Phase 9
clients feedback stack. This phase removes the two success toasts from that
loop's surface set; the clients stack stays Bootstrap-owned.

**Old contract (documented before change):** a fixed stack container
(`pointer-events-none fixed inset-x-0 top-[76px] z-[1100] flex flex-col
items-end gap-2 px-[1rem] sm:px-[1.75rem]" aria-live="polite"`) renders one
toast when `session('success')` exists: a `.toast` div (`role="status"`,
`data-bs-autohide="false"`, Tailwind utilities `rounded-panel bg-surface
shadow-pop ring-1 ring-line`) with `{{ session('success') }}` and a `.btn-close`
button (`data-bs-dismiss="toast"`, `aria-label="Close"`). The layout inline
script initialized it (and every other `.toast` on the page).

**Migration decisions (coexistence-mandated):**
- Each toasted div lost the `.toast` class (Bootstrap's `.toast:not(.show)`
  `{display:none}` gate; its own Tailwind utilities fully own the visuals), kept
  `role="status"`, and gained `x-data="{ open: true }"` and `x-show="open"`. The
  close button kept `.btn-close` + `aria-label="Close"` (CSS-only token) and its
  dismissal became Alpine `@click="open = false"`. `data-bs-autohide="false"`
  and `data-bs-dismiss="toast"` are gone from both toasts. The per-page message
  sink is preserved: transactions uses a `<span class="text-dense leading-snug
  text-ink">`, households a `<div class="min-w-0 flex-1 text-dense leading-snug
  text-ink">`.
- **The shared init loop in the layout is preserved verbatim** (comment updated
  in Phase 19 to point at the clients stack): with `.toast` gone from
  transactions/households, the loop now matches only the `clients/index` Phase 9
  feedback stack (which has its own inline `wireFlashToast` — pre-existing
  double-init, out of scope).
- Persistence is deterministic: the toast renders visible from server HTML (no
  `.show()` dance) and stays until the close button flips `open=false` — parity
  with the old `autohide:false`.
- Flash is one-shot exactly as before: `@if (session('success'))` guard +
  message slot unchanged.

**Accessibility verified:** container `aria-live="polite"` + toast
`role="status"` retained; close `aria-label="Close"` retained; visible on
server render, hidden only by user click.

**Application finding (documented, not fixed):** the transactions index success
toast cannot currently be produced by any application route. Both
`TransactionController::store` (line 134) and `update` (line 215) redirect to
`transactions.show`, which renders no toast; nothing redirects to
`transactions.index` with a success flash. Households has a real trigger:
`HouseholdController::store` (line 56) → `households.index` with
`'Household added successfully!'`, and `households.destroy` is a POST returning
JSON `{success:true}`. This kept the E2E honest: households was verified via its
real store→toast→destroy flow; transactions was verified by seeding the one-shot
flash into the live file session (see E2E below).

**Files modified:** `resources/views/households/index.blade.php` (lines 82–95),
`resources/views/transactions/index.blade.php` (lines 107–120),
`resources/views/layouts/app.blade.php` (init-loop comment only).
**Files created:** `tests/Feature/HouseholdTest.php` + 2 tests
(`test_households_success_flash_renders_migrated_alpine_toast`,
`test_households_success_flash_absent_renders_no_toast`),
`tests/Feature/TransactionTest.php` + 2 tests (same pair, transactions),
`e2e/page-toasts-phase20.spec.ts` (5 tests).

**Tests:** `npm run build` clean (`app-D3mz-Mcl.css` / `app-DqsLDVL_.js`);
`php artisan view:cache` clean — compiled views
(`de54507dae5dd1bb78fdc46e9bcb5ea3.php` transactions, 432 lines;
`c7ea3b9fd1731fe45481935082355a80.php` households, 247 lines) contain zero
`data-bs-autohide`/`data-bs-dismiss="toast"`; compiled layout
`430db873d3cabe5ad259240f9b6593f2.php` still carries the Phase 19 toast;
`vendor\bin\pint --test` clean; `php artisan test` → full suite **301 passed
(1419 assertions), 0 failures** (4 new tests / 22 assertions above Phase 19's
297/1397).

**E2E (live, chromium):** `e2e/page-toasts-phase20.spec.ts` (**5/5 passing**,
serial). Households uses the REAL trigger (store → `households.index` flash →
toast; destroys its own household via the real `POST /households/{id}` endpoint
at the end so `tbl_household` returns to id 1 only). Transactions — no UI
trigger exists (finding above) — seeds the one-shot flash through a throwaway
PHP helper (outside the repo, hits the real framework from the temp dir) that
decrypts the browser's `2dmis_session` cookie value (EncryptCookies payload +
`CookieValuePrefix`, not a raw id — this was the root cause of three failed runs
plus an un-awaited `context.cookies()`) and writes `session->flash('success',
…)` + `save()` into the browser's live file session. Phase 19 regression
`e2e/flash-toast-phase19.spec.ts` **4/4 passing**. Ephemeral `smoke_superadmin`
account (id 8) + `'*'` permission row used and fully removed afterwards;
`tbl_audit_logs` verified back to **1604** rows (baseline), `tbl_household` to
id 1 only, permission rows to 11, no user-8 remnants.

**Regression check (§22-style):** `resources/` scan for `bootstrap.Toast`,
`getOrCreateInstance`, `data-bs-dismiss="toast"`, `data-bs-autohide`: the only
live Bootstrap toast wiring left is the intentional layout init loop serving the
`clients/index` Phase 9 stack (out of scope); all other matches are comments
(compiled-views scan included).

**Environment note (not an app change):** during E2E the local MariaDB
(`main_system` copy) died on a corrupt `mysql.proxies_priv` system-table index
(OS error 206 / crash dumps) — an environment fault unrelated to the app code.
It recovered with `--skip-grant-tables` + recreation of the corrupt system
table; `main_system` data was verified intact (1604 audit rows, id-1
household, no smoke remnants) before and after. Running with
`C:\xampp\mysql\bin` on PATH is required for `php artisan test` (known gotcha).

---

### 2026-09-05 - Phase 19: Shared Layout Flash Toast -> Alpine.js

Migrated **only** the shared flash toast in `resources/views/layouts/app.blade.php`
(the `#flashToast` element rendered on every layout page when the server flashes
`login_status`) from Bootstrap Toast JS (`bootstrap.Toast.getOrCreateInstance(
toastEl, { autohide: false }).show()`) to **Tailwind classes + Alpine.js state**.
**Frontend-only**: no schema, ACL, route, controller, middleware, validation-rule
or business-rule change — the flash contract is byte-identical (presentation
channel only).

**Why this surface:** the layout toast is the single Bootstrap UI that appears
on *every* layout page and is wired by a layout-level inline script; the
`data-bs-dismiss="toast"` + `.toast` class dependency made it the highest-value
next self-contained candidate (the safest Bootstrap JS surfaces left).

**Old contract (documented before change):** a fixed stack container
(`pointer-events-none fixed inset-x-0 top-20 z-[1100] flex flex-col items-end
gap-2 px-[1rem] sm:px-[1.75rem]" aria-live="polite"`) renders one toast when
`session('login_status')` exists: a `.toast` div (`role="status"`,
`data-bs-autohide="false"`, Tailwind utilities `rounded-panel bg-surface
shadow-pop ring-1 ring-line`) with a teal check icon, the message text
(`{{ session('login_status') }}`), and a `.btn-close` button
(`data-bs-dismiss="toast"`, `aria-label="Close"`). A layout inline script ran
`document.querySelectorAll('.toast').forEach(... getOrCreateInstance(...).
show())` — which served **both** the layout toast **and** every page-level
`.toast` rendered by child views (clients Phase 9 feedback stack,
transactions/households success toasts).

**Migration decisions (coexistence-mandated):**
- The toasted div lost the `.toast` class (Bootstrap's `.toast:not(.show)
  {display:none}` gate; its own Tailwind utilities fully own the visuals), kept
  `role="status"`, and gained `id="flashToast"`, `x-data="{ open: true }"` and
  `x-show="open"`. The close button kept `.btn-close` + `aria-label="Close"`
  (CSS-only token; Bootstrap **CSS** CDN stays loaded) and its dismissal became
  Alpine `@click="open = false"`. `data-bs-autohide="false"` and
  `data-bs-dismiss="toast"` are gone from the layout toast.
- **The shared init loop is preserved verbatim** (comment updated): it still
  owns the remaining child-view `.toast` surfaces (Phase 9 clients stack,
  transactions, households). Removing the class guarantees the layout toast is
  no longer re-initialized by Bootstrap; those other surfaces keep working.
- Persistence is deterministic and JS-tree-independent: the toast renders
  visible from server HTML (no `.show()` dance) and stays until the close
  button flips `open=false` — parity with the old `autohide:false`.
- The flash is server-side and one-shot exactly as before: the
  `@if (session('login_status'))` guard + message slot are unchanged, so
  middleware/controller-flashed values (AuthorizePage/AuthorizeAction/
  EnsureSingleDevice, AdminPermissionController, SessionController) render
  through Alpine with identical content. The `login_status` surfaces that live
  outside the layout (admin/users/index line 71; the `@if` branches on
  `auth/login.blade.php`) were **not** touched — they stay on their own pages.

**Accessibility verified:** container `aria-live="polite"` + toast
`role="status"` retained; close button `aria-label="Close"` retained; visible
on server render and hidden only by user click (never silently auto-dismisses).

**Files modified:** `resources/views/layouts/app.blade.php`,
`e2e/flash-toast-phase19.spec.ts` (new).

**Tests:** `npm run build` clean (`app-DqsLDVL_.js`, Tailwind tokens
`rounded-panel`, `shadow-pop`, `ring-line` emitted); `php artisan view:cache`
clean — the compiled layout keeps only a **comment** mention of
`bootstrap.Toast`/`getOrCreateInstance` (the legacy loop for other surfaces)
and the toast region has zero `data-bs-dismiss`/`data-bs-autohide`; `vendor\bin
\pint --test` clean; `php artisan test` → full suite **297 passed (1397
assertions), 0 failures**.

**E2E (live, first auth-gated phase):** the shared toast only exists behind
login, so for this phase only an **ephemeral super-admin smoke account**
(`smoke_superadmin` + a `tbl_permissions` `'*'` row, bcrypt of `SmokeAdmin2026!`)
was INSERTed into the **local** production-copy `main_system`, used, and fully
removed afterwards (user row + `'*'` permission row + its LOGIN `tbl_audit_logs`
rows; audit trail otherwise untouched — verified count 1604 before, 1604 after).
`e2e/flash-toast-phase19.spec.ts` (**4 chromium tests passing**, run twice for
stability) drives a **zero-DB-write flash trigger**: an injected plain form
(no `data-confirm`) POSTs to `session.force-logout` with a non-existent
`user_id=99999999` → `User::find()` null → `back()->with('login_status',
'User not found.')` (no write, no audit), the real browser follows the redirect
back to the same layout page and the toast renders there — no API/fetch cookie
transport (two failed approaches grounded: `page.request.post` auto-following
the redirect consumed the one-shot flash; in-page `fetch` with
`redirect:'manual'` does not commit Set-Cookie, verified by debug). Coverage:
toast renders with the exact flashed message; position/visual contract
(top-20 aria-live stack, teal check, `rounded-panel`); persistence (still
visible after 3.2 s — no auto-hide timer); one-shot flash across navigation;
zero Bootstrap-toast attributes and zero legacy `.toast` matches on a
layout-only page; Alpine binding asserted (`_x_dataStack`); close button hides
via Alpine state (retried `toPass` for Alpine boot timing; same-URL navigation
latency under Laravel's file-session flock absorbed with 20 s toast-wait
budgets).

**Regression check (§22):** grep across `resources/ app/ routes/` for
`bootstrap.Toast`, `getOrCreateInstance`, `data-bs-dismiss="toast"`,
`data-bs-autohide`, `data-bs-toggle`: the only live `bootstrap.Toast` JS left is
the intentional layout loop serving remaining child-view `.toast` surfaces
(`transactions/index`, `households/index` success toasts,
`clients/index` Phase 9 stack — all out of scope, documented); plus the
pre-existing dangling `admin/users/show.blade.php:134` `data-bs-toggle="modal"`
trigger (Phase 12 leftover, out of scope). The layout itself is toast-clean.

**Next migration candidate requiring separate approval:** the remaining
page-level `.toast` surfaces (transactions/households success toasts,
clients Phase 9 stack) — the same Alpine pattern can be applied per-page; after
that the `data-bs-dismiss="alert"` banners and the still-Bootstrap scanner
`#messageModal`.

---

### 2026-09-05 - Phase 18: Students Photo Modal -> Alpine.js

Migrated **only** the `#photoModal` camera modal in `resources/views/students/
photo-upload.blade.php` (the standalone public student flow page, own
`<!DOCTYPE html>`) from Bootstrap Modal JS to **Tailwind + Alpine.js**.
**Frontend-only**: no schema, ACL, route, controller, validation-rule or
business-rule change; the camera algorithm (`getUserMedia`,
`loadCameraDevices`, `switchCamera`, canvas capture, retake) is preserved
**verbatim**.

**Structure determination (inspect-before-edit):** the students `#photoModal`
exists **only** on `students/photo-upload.blade.php` and is driven by a
`data-bs-toggle="modal" data-bs-target="#photoModal"` "Take Photo" button. The
"Photo" button in `scholars/show.blade.php:34` was a **dead trigger** — its
`data-bs-*` pair targeted a `#photoModal` that never existed on that page
(clicking it only logged a Bootstrap not-found; it did nothing). Its dangling
attributes were stripped (class/text/placement kept), so
`scholars/show.blade.php` now has zero `data-bs`/`photoModal` matches. The other
`#photoModal` in the repo is the Phase 10 **clients** photo modal
(`clients/_details.blade.php`) — untouched by design.

**Old contract (documented before change):** the trigger button (`btn-navy
mb-2 w-full` "Take Photo"); a `.modal.fade` `#photoModal` (`role="dialog"`,
`tabindex="-1"`, `aria-hidden="true"`) containing `#cameraSelect`,
`#video` (autoplay), `#capturedPreview` (`d-none`), `#cameraButtons` >
`#captureBtn` ("Capture"), `#previewButtons` (`d-none`) > `#retakeBtn`
("Retake") + `#saveBtn` ("Save"), and `#photoForm` POST → `student.photo-upload
.store` (+`@csrf` + hidden `#cameraImage`). **No visible X/close button** —
close mechanisms were backdrop click + ESC only (Bootstrap defaults
`backdrop:true, keyboard:true`). `shown.bs.modal` → `initCamera()` (async
`getUserMedia({video:true})` → `video.srcObject` → `loadCameraDevices()` →
populate `#cameraSelect`; catch → `alert("Camera access denied or not
available.")`); `#cameraSelect` change → `switchCamera(deviceId)` (stops
current stream, `getUserMedia` with `exact` deviceId); Capture →
canvas `toDataURL('image/jpeg', 0.9)` → `#capturedPreview.src` + d-none flips +
`#cameraImage.value`; Retake → flips back; Save → `photoForm.submit()`.
`hidden.bs.modal` → `if (stream) stream.getTracks().forEach(track =>
track.stop())`; **no** `srcObject`/preview/`cameraImage`/UI reset — i.e., a
stale preview persists across close/reopen.

**Alpine implementation (deviations documented):**
- Page Vite tag grew to `@vite(['resources/css/app.css',
  'resources/js/app.js'])` (Alpine bundle) and the standalone page's own
  `<style>` gained `[x-cloak]{display:none}` (Phase 10 precedent
  `clients/_details.blade.php:339`).
- **`x-data="photoModalComponent()"` moved to `<body>`** (from the modal root).
  Debug-driven root-cause: Alpine 3.x never binds `@click`/`x-show` on elements
  **outside an `x-data` scope** — the same latent reason the navbar hamburger
  (`navbar.blade.php:50`) and the Audit Logs Leaderboard button
  (`admin/audit_logs/index.blade.php:72`) are inert today (pre-existing, out of
  scope, recorded for a later phase). With the whole (tiny) standalone body in
  the component, the trigger and the modal share one scope. The modal root
  dropped its own `x-data`.
- New `alpine:init` **store** `photoModal` (`open`, `_prevFocus`,
  `openModal()`, `close()`): openModal guards on `this.open`, saves
  `document.activeElement`, locks body scroll, sets `open=true`, then
  `Alpine.nextTick` → focus `#cameraSelect` + `initCamera()` (the modal is now
  visible → camera-start parity with `shown.bs.modal`). close guards, sets
  `open=false`, restores scroll + focus, nulls `_prevFocus`, calls
  `stopCamera()` (parity with `hidden.bs.modal`). The method is named
  **`openModal`** (not `open`) to avoid the duplicate-key store quirk latent in
  Phase 16's `leaderboardModal` store.
- Trigger: `@click="$store.photoModal.openModal()"` (same class/text/placement).
- Modal structure: root `#photoModal` now `x-cloak` + `x-show` (the whole modal
  leaves the tree when closed — a plain always-rendered fix has no visual
  effect but keeps `role="dialog"` reachable in the closed state), `role="dialog"`
  `aria-modal="true"` `aria-labelledby="photoModalTitle"`, backdrop
  `x-show="$store.photoModal.open"` `@click="$store.photoModal.close()"`, dialog
  `x-ref="dialog"` `x-show` with `@keydown.escape.window="$store.photoModal
  .close()"` and `@keydown.tab.prevent.stop="handleTab($event)"`. All inner ids
  and the form contract preserved verbatim. `window.photoModalComponent()` =
  `{ handleTab }` tab trap (focusables exclude `input[type=hidden]`), closing
  wrap at first/last.
- Close set preserved: **backdrop + ESC only** (no X/Close added — the old
  modal had none). Focus entry `#cameraSelect` (first interactive control; the
  old Bootstrap modal started from the body/backdrop and the spec asked for
  focus into the dialog with the old "no close button" reality). Focus restored
  to the trigger on close; body scroll locked/unlocked per open.
- The old `shown.bs.modal`/`hidden.bs.modal` **listeners were removed** (their
  replacement is the Alpine lifecycle); the bootstrap bundle `<script>` and
  Bootstrap CSS CDN **stay loaded** (coexistence; CDN removal is a removal-gate
  item).

**Accessibility verified:** `role="dialog"` + `aria-modal="true"` +
`aria-labelledby="photoModalTitle"`; backdrop `aria-hidden="true"`; focus
enters the dialog on open (`#cameraSelect`), returns to the trigger on close;
Tab/Shift+Tab trapped; body scroll locked while open; ESC and backdrop close.

**Files modified:** `resources/views/students/photo-upload.blade.php`,
`resources/views/scholars/show.blade.php`, `e2e/photo-modal-phase18.spec.ts`
(new).

**Tests:** `npm run build` clean (tokens `max-w-[500px]`, `z-[200]`,
`bg-ink/40`, `rounded-panel`, `shadow-pop`, `ring-line` emitted);
`php artisan view:cache` clean — the compiled photo-upload view keeps only
**comment** mentions of `shown.bs.modal`/`hidden.bs.modal` and has zero live
Bootstrap-modal calls; `vendor\bin\pint --test` clean; targeted
`php artisan test tests/Feature/PhotoTest.php tests/Feature/StudentTest.php` →
**11 passed (39 assertions)**; `php artisan test` → full suite
**297 passed (1397 assertions), 0 failures**.

**E2E (live):** `e2e/photo-modal-phase18.spec.ts` runs **4 chromium tests
passing** against the dev server. The student verify→photo-upload flow is
public; the suite drives it with a real production-copy client row
(`E2E_CLIENT_ID`/`E2E_BIRTHDATE`/`E2E_MOBILE`, defaults client id=1:
2006-07-14 / 09123456789) plus per-test Chromium fake-camera launch flags
(`--use-fake-ui-for-media-stream` + `--use-fake-device-for-media-stream` — no
global permission/server change). Coverage: dialog semantics hidden until open;
no Bootstrap-Modal dependency in the page's scripts (`bootstrap.Modal(`,
`getOrCreateInstance`, `data-bs-toggle/target`,
`addEventListener('shown.bs.modal'`/`'hidden.bs.modal'` absent);
focus entry + scroll lock; capture/retake dataURL flow; full camera lifecycle
(open→`video.srcObject` live track → ESC→track `ended` → reopen→**fresh** live
stream → backdrop→`ended`; single `#video` instance, no stale stream) with
focus + scroll restored. `#photoForm` asserted by presence/contract (a
zero-height POST form is never "visible").

**Regression check (§22) —** grep across `resources/` for `#photoModal`,
`bootstrap.Modal`, `getOrCreateInstance`, `.*.bs.modal`, `data-bs-toggle`
/`data-bs-target`/`data-bs-dismiss`, `relatedTarget`: remaining matches are
either comments in migrated views, the preserved Phase 10 clients `#photoModal`,
the preserved out-of-scope surfaces (`layouts/app` flash toast
`bootstrap.Toast.getOrCreateInstance`, `admin/users/show` dangling
`data-bs-*` password trigger, transactions/households toast `data-bs-dismiss`),
or Phase content. `scholars/show.blade.php` is clean.

**Known pre-existing issue (not fixed in Phase 18):** Alpine triggers outside
any `x-data` scope are never bound — the navbar hamburger
(`partials/navbar.blade.php:50`) and the Audit Logs Leaderboard button
(`admin/audit_logs/index.blade.php:72`) are inert; their Phase 12/16 E2E specs
asserted markup/dependency only, never a live click. Flagged for a future touch
phase.

---

### 2026-09-05 - Phase 17: Unpaid Verification Dynamic Final Confirmation Modal -> Alpine.js

Migrated **only** the Unpaid Verification **dynamic Final Confirmation modal**
(`resources/views/unpaid_verifications/self-service.blade.php`, built at runtime
by `showConfirmation()`) from Bootstrap Modal JS to **Tailwind + Alpine.js**.
**Frontend-only**: no schema, ACL, route, controller, validation-rule,
business-rule, fetch/AJAX, or `saveUnpaid` change. The page is public
(`/unpaid-verification`), has its **own `<!DOCTYPE html>`** (does not extend the
layout), and was the last `new bootstrap.Modal` runtime-built surface.

**Existing contract (documented before change):**
- Triggers: `#btnSelf` -> `showConfirmation(false)`; `#submitProxyBtn` ->
  validate -> `showConfirmation(true, lname, fname, mname, rel)` + sets
  `window.proxyExtra`. No AJAX happens inside the modal; Confirm runs
  `saveUnpaid(...)` (the untouched **fetch**-based POST) **after** close.
- The modal was **created fresh per open**: `document.createElement('div')` with
  `.modal.fade`, innerHTML template (header `.bg-primary` "Final Confirmation" +
  `.btn-close data-bs-dismiss="modal"`; body = proxy summary
  `lname, fname mname` + `Relationship: rel`, or self-confirm text, plus a
  `.alert.alert-warning.small` "Important: You can only submit once…" warning;
  footer = `.btn.btn-secondary` Cancel `data-bs-dismiss="modal"` +
  `#confirmYesBtn.btn.btn-primary` "Yes, Confirm Submission"),
  `document.body.appendChild` -> `new bootstrap.Modal(modal).show()`. Confirm ->
  `hide()` + `remove()` + `saveUnpaid(...)`; `hidden.bs.modal` -> `remove()`.
  Bootstrap defaults: backdrop click + ESC close (neither confirms). No
  persistent hidden modal DOM. No `#confirmYesBtn` to simulate — each open was
  a fresh instance with the current proxy/self values baked in.
- Bootstrap CSS CDN, `css/ui.css`, and the bootstrap bundle stay loaded on the
  page (coexistence; CDN removal is a later removal-gate item).

**Alpine implementation (Phase 12/15/16 conventions):**
- The page now loads Alpine by extending its Vite tag to
  `@vite(['resources/css/app.css', 'resources/js/app.js'])` (app.js = Alpine +
  collapse + `window.Alpine`; bootstrap.js only sets `window.axios` — both safe
  on the standalone page; only the layout + welcome previously included it).
- `showConfirmation()` still **builds the modal DOM dynamically per open**
  (`document.createElement('div')` + same template), `document.body.appendChild`,
  `Alpine.initTree(modal)`, then hands it to the store — preserving the exact
  create -> append -> init -> show -> … -> remove -> recreate lifecycle (no
  persistent hidden DOM, exactly as the spec's "$7 no persistent hidden DOM"
  requires). Content preserved verbatim: title "Final Confirmation", proxy/self
  body text, the `.alert.alert-warning.small.mb-0` warning, Cancel +
  `#confirmYesBtn` "Yes, Confirm Submission" (id kept). Presentation is now a
  fixed overlay (`z-[200]`, `bg-ink/40` backdrop, `rounded-panel` + `shadow-pop`
  + `ring-line` surface, `bg-navy` header, footer with `btn-subtle`/`btn-navy`).
- New Alpine **store** `unpaidConfirmationModal` registered on `alpine:init`
  (`open`, `_prevFocus`, `_onConfirm`, `_rootEl`): `openFor(rootEl, onConfirm)`
  saves the prior focused element, locks body scroll, opens, focuses the Close
  button on nextTick; `close()` hides, restores scroll + focus, and
  `Alpine.destroyTree` + removes the root node; `confirm()` captures the saved
  callback, closes/removes, then runs it. `unpaidConfirmationModalComponent()`
  (registered on `window`) provides `open`/`close`/`confirm`/`handleTab` (Tab
  trap via `x-ref="dialog"`, `@keydown.escape.window` + `@click` backdrop close).
- **Close set preserved:** X button, Cancel, backdrop click, and ESC all close
  without confirming (matching Bootstrap's `backdrop: true, keyboard: true`);
  only `#confirmYesBtn` confirms. Focus returns to the trigger element (the same
  behavior Bootstrap restored) and body scroll is locked/unlocked per open.
- **Business flow preserved:** `confirm()` runs `saveUnpaid(...)` **after**
  close/remove, unchanged (same POST `unpaid-verification.submit` with the same
  payload incl. `window.proxyExtra`); `#alertBox`/`#successBox`/`alert()`
  outcomes untouched.

**Bootstrap JS removed (for this modal only):** `new bootstrap.Modal(...)`,
`bsModal.show()/hide()`, `hidden.bs.modal` cleanup, `.modal-fade/
.modal-dialog-centered/.modal-content/.modal-header/.modal-body/.modal-footer/
.modal-title` structure, `.btn-close`, and both `data-bs-dismiss="modal"`
buttons. The bootstrap bundle `<script>` tag is retained (unused by this modal;
CDN removal is a removal-gate item). No other Bootstrap surface was touched
(students `#photoModal`, layout flash toast `bootstrap.Toast`, DataTables
Bootstrap-5 skin, Bootstrap CDN/CSS all still in place).

**Files modified:** `resources/views/unpaid_verifications/self-service.blade.php`,
`e2e/unpaid-verification-modal-phase17.spec.ts` (new).

**Tests:** `npm run build` clean (app-*.css 57.59 kB, app-*.js 105.70 kB);
`php artisan view:cache` clean (the compiled self-service view carries **no real
`bootstrap.Modal` / `new bootstrap.Modal` / `hidden.bs.modal` / `data-bs-*`**
references — only two HTML-comment mentions of the removed API);
`vendor\bin\pint --test` passed; targeted `tests/Feature/PayoutTest.php` +
`tests/Feature/GranteeUpdateTest.php` -> **29 passed (159 assertions)** incl.
`unpaid verification self service is public` and the store/verify/search tests;
full suite `php artisan test` -> **297 passed (1397 assertions), 0 failures**.
Playwright `e2e/unpaid-verification-modal-phase17.spec.ts` (5 tests) added — this
page is **public**, so it runs live on `chromium` against the running dev server:
**5/5 passed** (dynamic creation on trigger + dialog/aria semantics + focus
entry, no Bootstrap-Modal-js dependency in the inline script, the full close set
X/Cancel/backdrop/ESC each removes the node + restores focus, repeated reopen
yields exactly one fresh instance, body scroll lock/restore, and confirm
closes/removes then fires the untouched `saveUnpaid` POST stubbed via `page.route`
so no DB write occurs). `#confirmSection` is revealed in E2E the same way the
page's verify-success branch does (the DB-backed verify step can't run without
seeded grantee/program data).

**Regression search:** the only `bootstrap.Modal`/`show|hidden.bs.modal`/
`getOrCreateInstance` matches in the self-service view are code comments (no
runtime usage); remaining live Bootstrap usages elsewhere (students `#photoModal`,
layout flash toast `bootstrap.Toast`, DataTables skin, CDN) verified intact.
`[x-cloak]` has no CSS rule in `app.css`/`ui.css` (as in every prior phase, the
attribute is inert but consistently included); no flash issue because the modal
is created already-open and `x-show` is bound to store state.

**Next migration candidate requiring separate approval:** the students
`#photoModal` (`scholars/show.blade.php` + `students/photo-upload.blade.php`
with `shown.bs.modal`/`hidden.bs.modal` camera lifecycle), then the layout's
shared flash toast (Bootstrap Toast), then removal-gate items (DataTables
Bootstrap-5 skin / Bootstrap CDN).

---

### 2026-09-04 - Phase 16: Audit Logs Leaderboard Modal -> Alpine.js

Migrated **only** the Audit Logs **Leaderboard Modal**
(`resources/views/admin/audit_logs/index.blade.php` `#leaderboardModal`) from
Bootstrap Modal JS to **Tailwind + Alpine.js**. **Frontend-only**: no schema,
ACL, route, controller, validation-rule, business-rule, DataTables, audit-log
filtering/feed, or jQuery change. The audit feed table, FilterChips, details
panel, refresh button, and all other components are untouched.

**Existing contract (documented before change):**
- Trigger: a **server-rendered** `<button class="btn-gold"
  data-bs-toggle="modal" data-bs-target="#leaderboardModal">Leaderboard</button>`
  in the page-header `actions` string (NOT DataTables-generated).
- Modal markup: `.modal.fade#leaderboardModal` > `.modal-dialog
  .modal-dialog-centered .modal-lg` > `.modal-content.rounded-panel` > header
  (`.modal-title#leaderboardLabel` "User Activity Leaderboard" + `.btn-close
  data-bs-dismiss="modal"`) > body (`.table.leaderboard-table#leaderboardTable`
  with Rank / User / Total Actions thead + empty tbody).
- Open -> AJAX: `$('#leaderboardModal').on('show.bs.modal', ...)` fired a
  jQuery `$.ajax` **POST** `admin.audit-logs.leaderboard` with
  `data: { table:('#table').val() }` and the `X-CSRF-TOKEN` header. `.done`
  then `.empty()`-ed the tbody and appended one `<tr>` per row: rank
  (`index + 1`), username (HTML-escaped via `$('<div>').text(...).html()`),
  `total_actions`. **No error handler** on the AJAX (`.done` only), no loading/
  empty state, **no caching** — one fresh request per open, and a late response
  still populated the (hidden) tbody even if the modal closed first.
- Backend: `AuditController@leaderboard` returns a JSON array of
  `{username, total_actions}` ordered desc (`admin.audit-logs.leaderboard`,
  POST).

**Alpine implementation (Phase 12/15 conventions):** the modal was rebuilt as a
fixed overlay (`z-[200]`) with Tailwind token styling (`bg-ink/40` backdrop,
`rounded-panel` + `shadow-pop` + `ring-line` surface, `bg-navy` header, footer
with `btn-subtle` Close); `x-data="leaderboardModalComponent()"` on the wrapper
with `x-show="$store.leaderboardModal.open"` on backdrop + dialog,
`@click="close()"` on backdrop, `@keydown.escape.window="close()"`, and
`@keydown.tab.prevent.stop` tab trap with `x-ref="dialog"`. The trigger keeps
its text/class/placement and now calls
`@click="$store.leaderboardModal.open()"`.
- New Alpine **store** `leaderboardModal` (`open`, `_prevFocus`) registered on
  `alpine:init`: `open()` saves the prior focused element, locks body scroll,
  opens, calls `load()`, and focuses the close button on nextTick; `close()`
  hides, restores scroll + focus.
- **AJAX preserved as jQuery:** `load()` runs the **same** `$.ajax` POST to
  `{{ route('admin.audit-logs.leaderboard') }}` with
  `data: { table:('#table').val() }` and the same CSRF header, and the
  **same** `.done(...)` re-render (empty + append with escaped username). It is
  called from `open()` — exactly **once per open**, no caching, no double-fire,
  no second user action. A late response still populates the tbody exactly as
  the old `show.bs.modal` behavior did (no early-return guard was added).

**Bootstrap JS removed (for `#leaderboardModal` only):** the trigger's
`data-bs-toggle="modal"` + `data-bs-target="#leaderboardModal"`, the X's
`data-bs-dismiss="modal"`, the `.modal/.modal-dialog/.modal-dialog-centered/
.modal-content/.modal-header/.modal-body/.modal-footer/.modal-title` structure,
`.btn-close`, and the `show.bs.modal` jQuery handler. No
`getOrCreateInstance`/`hidden.bs.modal` controlled it. The unused
`leaderboardUrl` const (exclusively the leaderboard AJAX URL) was removed. Other
Bootstrap surfaces (students `#photoModal`, unpaid self-service dynamic modal,
layout flash toast `bootstrap.Toast`, Bootstrap CDN, DataTables skin) remain in
place, untouched.

**Files modified:** `resources/views/admin/audit_logs/index.blade.php`,
`e2e/leaderboard-modal-phase16.spec.ts` (new).

**Tests:** `npm run build` clean (app-*.css 57.59 kB, app-*.js 105.70 kB);
`php artisan view:cache` clean (compiled view carries no
`bootstrap.Modal`/`getOrCreateInstance`/`show.bs.modal`/`data-bs-dismiss="modal"`/
`data-bs-toggle="modal"`); `vendor\bin\pint --test` passed;
`php artisan test` -> full suite **297 passed (1397 assertions), 0 failures**;
targeted `tests/Feature/AdministrationTest.php` -> 32 passed (133 assertions)
incl. `test_leaderboard_returns_descending_totals`;
`tests/Feature/FilterChipsTest.php` -> 24 passed (90 assertions) incl. the
audit-logs index render test. Playwright
`e2e/leaderboard-modal-phase16.spec.ts` added documenting the modal contract
(trigger + dialog semantics/aria, `#leaderboardTable` columns, no Bootstrap-Modal
dependency, `open()` -> `$.ajax` POST wiring). Like every prior phase, E2E fails
at `signIn()` only because the `smoke_superadmin` test user is absent from the
production-copy DB, and the audit route + leaderboard AJAX require an
authenticated `audit_logs.php` session (DB not modified; not a regression).

**Regression search:** `#leaderboardModal` referenced only in
`audit_logs/index.blade.php` (Alpine version; the custom
`#leaderboardModal .leaderboard-table th` CSS still applies since the id is
unchanged); no `data-bs-target="#leaderboardModal"` remains; the only
`bootstrap.Modal`/`show.bs.modal` mentions in the file are HTML comments. Live
Bootstrap modal usages elsewhere (students `#photoModal`, unpaid self-service,
layout flash toast `bootstrap.Toast`) verified intact.

**Next migration candidate requiring separate approval:** the unpaid
self-service dynamic modal (`unpaid_verifications/self-service.blade.php`,
`new bootstrap.Modal` + `hidden.bs.modal` + data-bs-dismiss surfaces), then the
students `#photoModal`, then the layout's shared flash toast (Bootstrap Toast),
then removal-gate items (DataTables skin / Bootstrap CDN).

---

### 2026-09-04 - Phase 15: Scholars Client ID Prompt Modal -> Alpine.js

Migrated **only** the Scholars **Client ID Prompt Modal**
(`resources/views/scholars/index.blade.php` `#clientIdPromptModal`) from
Bootstrap Modal JS to **Tailwind + Alpine.js**. **Frontend-only**: no schema,
ACL, route, controller, validation-rule, business-rule, DataTables, or jQuery
change. The Scholars workflow, client-ID relink flow, jQuery AJAX, DataTables,
and all other Bootstrap components are untouched.

**Existing contract (documented before change):**
- Trigger: `.edit-client-id` buttons are rendered **inside DataTables AJAX rows**
  (column render fn emits `<button class="btn-subtle edit-client-id"
  data-id="..." data-clientid="...">Edit</button>`), opened via a **delegated**
  `$(document).on('click', '.edit-client-id', ...)` handler — required for
  dynamically generated rows.
- Open logic: set `#clientIdPromptInput.value =(this).data('clientid')`, stash
  `modalEl._relinkId =(this).data('id')` and `modalEl._relinkCurrent`, then
  `bootstrap.Modal.getOrCreateInstance(modalEl).show()`.
- Field: `#clientIdPromptInput` (`type="number"`, `min="1"`), label "New Client
  ID", button id `#clientIdPromptConfirm` ("Relink"), X + Cancel buttons with
  `data-bs-dismiss="modal"`. Backdrop + ESC close (Bootstrap `.modal` default);
  neither fires the submit.
- Confirm (`#clientIdPromptConfirm` click): reads `_relinkId`/`_relinkCurrent`,
  `newVal = input.value.trim()`; if empty or equal to current, no-op. Else
  jQuery `$.ajax` `POST scholars.update-client-id` with `{id, client_id}`;
  success -> `bootstrap.Modal...hide()` then
  `window.scholarsTable.ajax.reload(null, false)`; error -> hide +
  `alert('Error updating Client ID')`. No submitting/loading state existed and
  none was added.
- Backend: `ScholarController@updateClientId` -> HTTP 200 `{message:'success'}`,
  or HTTP 400 `{message:'Invalid input'}` (`scholars.update-client-id` route,
  POST).

**Alpine implementation (Phase 12 conventions):** the modal was rebuilt as a
fixed overlay (`z-[200]`) with Tailwind token styling (`bg-ink/40` backdrop,
`rounded-panel` + `shadow-pop` + `ring-line` surface, `bg-navy` header, footer
with `btn-subtle` Cancel + `btn-navy` Relink); `x-data="clientIdPromptModalComponent()"`
on the wrapper with `x-show="$store.clientIdPromptModal.open"` on backdrop +
dialog, `@click="close()"` on backdrop, `@keydown.escape.window="close()"`, and
`@keydown.tab.prevent.stop` tab trap with `x-ref="dialog"`.
- New Alpine **store** `clientIdPromptModal` (`open`, `_prevFocus`, `relinkId`,
  `relinkCurrent`) registered on `alpine:init`:
  `openFor(id, current)` stores the relink identity, sets
  `#clientIdPromptInput.value = current`, locks body scroll, opens, focuses the
  input on nextTick; `close()` resets scroll/overflow and restores focus to the
  previously focused element. `relinkId`/`relinkCurrent` replace the old
  `modalEl._relinkId`/`_relinkCurrent` element-property stash.
- **jQuery kept as jQuery:** the delegated `.edit-client-id` handler now just
  calls `Alpine.store('clientIdPromptModal').openFor(id, current)` (same
  `data-id`/`data-clientid`). The `#clientIdPromptConfirm` handler reads
  `store.relinkId`/`store.relinkCurrent`, and the **existing `$.ajax`** to
  `scholars.update-client-id` runs unchanged; success ->
  `store.close()` + `window.scholarsTable.ajax.reload(null, false)`; error ->
  `store.close()` + the same `alert('Error updating Client ID')`. No loading
  state added.

**README note:** the scholars page also hosts `#photoModal`
(`show.blade.php:34 data-bs-toggle/target`) — NOT in scope, left intact.

**Bootstrap JS removed (for `#clientIdPromptModal` only):** the modal's
`data-bs-dismiss="modal"` attributes (X + Cancel), the
`.modal/.modal-dialog/.modal-dialog-centered/.modal-content/.modal-header/
.modal-body/.modal-footer/.modal-title` structure, `.btn-close`, and the
`bootstrap.Modal.getOrCreateInstance(...).show()/.hide()` calls. No
`show.bs.modal`/`hidden.bs.modal`/`relatedTarget`. Other Bootstrap Modal
surfaces (`#leaderboardModal` audit_logs, `#photoModal` students/scholars/show,
unpaid self-service dynamic modal, admin-users reset selector, Bootstrap CDN,
toasts, alert banners) remain in place.

**Files modified:** `resources/views/scholars/index.blade.php`,
`e2e/scholars-client-id-modal-phase15.spec.ts` (new).

**Tests:** `npm run build` clean (app-*.css 57.56 kB, app-*.js 105.70 kB);
`php artisan view:cache` clean (compiled scholars view carries no
`bootstrap.Modal`/`getOrCreateInstance`/`data-bs-dismiss="modal"`;
`vendor\bin\pint --test` passed; `php artisan test` -> full suite
**297 passed (1397 assertions), 0 failures**; targeted
`tests/Feature/ScholarTest.php` -> 19 passed (53 assertions) incl. "scholar
client id can be relinked" + relink 400 rejections. Playwright
`e2e/scholars-client-id-modal-phase15.spec.ts` added documenting the modal
contract (dialog roles/attrs, input/label fields, no Bootstrap-Modal
dependency, trigger `data-id`/`data-clientid` contract). Like every prior
phase, E2E fails at `signIn()` only because the `smoke_superadmin` test user is
absent from the production-copy DB, and the `.edit-client-id` rows only render
after authenticated DataTables AJAX (DB not modified; not a regression).

**Regression check:** `scholars/index.blade.php` has zero live
`data-bs-*` modal attributes and no `bootstrap.Modal`/`getOrCreateInstance`
(only an HTML comment mentions them); `#clientIdPromptModal` is referenced from
no other view. Other components' Bootstrap Modal dependencies (`#leaderboardModal`
audit_logs, `#photoModal` scholars/show + students, unpaid self-service,
admin-users) verified intact.

**Next migration candidate requiring separate approval:** `#leaderboardModal`
(admin/audit_logs) — a Bootstrap Modal opened imperatively via
`getOrCreateInstance` with `show.bs.modal` and AJAX-loaded body content; next
safest self-contained candidate.

---

### 2026-09-04 - Phase 14: Bootstrap Alert dismissals -> Alpine.js

Migrated **only** the Bootstrap Alert dismissal behavior (`data-bs-dismiss="alert"`)
to Alpine.js. **Frontend-only**: no schema, ACL, route, controller,
validation-rule, business-rule, or server-side rendering change. Exact alert
components (from the Phase 11 inventory, verified by repository search - all
`data-bs-dismiss="alert"` occurrences in the repo):

1. **Shared layout validation alert** (`layouts/app.blade.php`): renders on
   `$errors->any()`, iterates `$errors->all()`, `role="alert"`. Kept the
   `@if`/`@foreach` server logic untouched; removed the Bootstrap
   `fade show` visibility classes (Alpine now controls visibility) and replaced
   `data-bs-dismiss="alert"` with `@click="open = false"`.
2. **duplicates/index.blade.php** - two alerts: `session('success')` notice and
   the `$errors->any()` errors alert.
3. **admin/users/index.blade.php** - two alerts: `session('login_status')`
   notice (`ui-notice ui-notice-info`) and the `$errors->any()` errors alert
   (`$errors->first()`). (Only these alert blocks changed; the Phase 12
   password modal was not touched.)
4. **family_members/create.blade.php** - `$errors->any()` errors alert
   (`$errors->first()`).
5. **households/create.blade.php** - `$errors->any()` errors alert
   (`$errors->first()`).

**Existing behavior (documented before change):** every in-scope alert was
server-rendered, conditionally visible via a Blade `@if` (no auto-timeout, no
auto-hide, no fade-out on close), dismissed **only** manually via its
`.btn-close` close button wired with Bootstrap Alert JS
(`data-bs-dismiss="alert"`), which removed the element from the DOM. Dismissal
affected **no server-side state** and no JavaScript referenced the alerts by
id/class, so Alpine state-hiding is behaviorally equivalent.

**Alpine implementation:** each alert was given its own, page-agnostic
`x-data="{ open: true }"` + `x-show="open"`, and the close button replaced
`data-bs-dismiss="alert"` with `@click="open = false"`. Because every alert has
an independent `x-data` scope, dismissal is per-alert (dismissing one never
hides a sibling - required for the recurring error/success pairs). The shared
layout alert works on every page that renders it (Alpine ships globally via the
layout's `@vite app.js`; no new dependency). Server-side content, conditions,
and `role="alert"` were preserved exactly.

**Close button / accessibility:** the `.btn-close` visual (Bootstrap **CSS**,
not JS - still loaded globally, left in place) is preserved for exact appearance
parity; each close button keeps `aria-label="Close"`, `type="button"`, and full
keyboard focusability. `@click="open = false"` is a plain DOM/state toggle with
no lifecycle change (immediate dismissal, matching the old click-to-remove).

**Fade/transition:** the old Bootstrap alerts had no dismiss animation (only
the shared layout carried `fade show`, which cued the initial visible state, not
a hide animation). Dismissal is immediate via Alpine - no `x-transition` delay
introduced, preserving the original UX.

**Bootstrap Alert JS dependencies removed:** all seven `data-bs-dismiss="alert"`
attributes. Verified no `bootstrap.Alert`, `show.bs.alert`, or
`closed.bs.alert` exist anywhere. The remaining `data-bs-dismiss` attributes in
the repo are all out-of-scope and intact: `data-bs-dismiss="toast"` (shared
layout flash toast + households/index + transactions/index - Bootstrap Toast,
deferred) and `data-bs-dismiss="modal"` (`#clientIdPromptModal` scholars, unpaid
self-service, `#leaderboardModal` audit_logs - Bootstrap Modals, deferred).

**Out-of-scope alerts left untouched:** the `.alert`-classed alerts in
`qr/viewer.blade.php`, `grantee_update/self-service.blade.php`,
`grantee_update/_self_update_tab.blade.php`, and
`unpaid_verifications/self-service.blade.php` are Bootstrap **CSS-only**
(injected via `innerHTML`, no dismissal button, no `data-bs-dismiss`) - not
Bootstrap Alert JS, so not migrated.

**Files modified:** `resources/views/layouts/app.blade.php`,
`resources/views/duplicates/index.blade.php`,
`resources/views/admin/users/index.blade.php`,
`resources/views/family_members/create.blade.php`,
`resources/views/households/create.blade.php`,
`e2e/alert-dismiss-phase14.spec.ts` (new).

**Tests:** `npm run build` clean; `php artisan view:cache` clean (exactly 7
compiled views carry the Alpine dismiss state, matching the 7 migrated alerts,
and no compiled view carries `data-bs-dismiss="alert"`); `vendor\bin\pint
--test` clean; `php artisan test` -> full suite **297 passed (1397
assertions), 0 failures** (35.5s).

**Environment note (resolved):** the first full-suite run hung and left MySQL in
crash recovery. Diagnosis confirmed tests target a dedicated `main_system_test`
DB (`phpunit.xml` forces `DB_DATABASE=main_system_test`), never the production
`main_system` copy. After a clean MySQL restart the production copy was verified
intact (`tbl_clients` = 1002 rows) and the full suite passed 297/1397/0.
No code, schema, or data change was involved in the recovery.

**Playwright:** `e2e/alert-dismiss-phase14.spec.ts` added documenting the
alert contract (dismiss via close button hides only that alert, no Bootstrap
Alert JS dependency, `role="alert"` preserved). Like every prior phase, E2E
fails at `signIn()` only because the `smoke_superadmin` test user is absent from
the production-copy DB (DB not modified; not a regression).

**Next migration candidate requiring separate approval:** see the plan - next
candidate is `#clientIdPromptModal` (scholars, Bootstrap Modal via
`getOrCreateInstance`) or `#leaderboardModal` (audit_logs, Bootstrap Modal with
AJAX content); the shared-layout flash toast (Bootstrap Toast) and the
remaining Bootstrap data-API modals stay deferred. Recommended ordering per the
plan after the alert banners: the `data-bs-dismiss="modal"` surfaces next.

---

### 2026-09-04 — Phase 13: Scanner message modal → Tailwind + Alpine.js

Delivery of the next Phase-12-recommended target: the **scanner message modal
(`#messageModal`)** in `resources/views/scanners/scan.blade.php` converted from
Bootstrap Modal JS to Tailwind + Alpine.js. **Frontend-only**: no schema, ACL,
route, controller, request-rule, authorization, scanner-workflow, or
business-logic change. Only this one modal was migrated; all other Bootstrap
components/toasts/alerts/CDN are untouched (out of scope).

**Existing `#messageModal` contract (documented before change):**
- Markup = `.modal.fade#messageModal` > `.modal-dialog.modal-dialog-centered`
  > `.modal-content` > header (`.modal-title#modalTitle` + `.btn-close`
  `data-bs-dismiss="modal"`) > body `#modalMessage` (empty) > footer
  (`#modalOkBtn` `.btn-navy` `data-bs-dismiss="modal"`).
- `showModal(msg, type, title, onOk)`: sets `#modalTitle.innerText` /
  `#modalMessage.innerText` (**plain-text insertion**), captures
  `afterModal = onOk || reloadPage`, `new bootstrap.Modal(...).show()`, then
  `playSound(type || 'error')`.
- OK click listener: `if (afterModal) afterModal();` — the callback runs
  **synchronously on OK click**, before Bootstrap's hide animation finished.
  Default callback `reloadPage`; `nextAfterModal()` yields `resumeAfterModal`
  (hides scan/result areas, re-renders the html5-qrcode scanner) or
  `reloadPage` per `SCANNER.resume`.
- Backdrop + ESC close via Bootstrap defaults WITHOUT firing the callback
  (only OK fires it). Focus entered the modal; restored on hide.

**Alpine implementation (`scanners/scan.blade.php`):**
- Markup replaced with an Alpine `#messageModal` on
  `x-data="scannerMessageModalComponent()"` + `x-cloak`, `role="dialog"`,
  `aria-modal="true"`, `aria-labelledby="modalTitle"`,
  `aria-describedby="modalMessage"`, `z-[200]` overlay, `bg-ink/40` backdrop
  (`@click="close()"` preserves Bootstrap `backdrop:true` semantics), centered
  `max-w-[480px]` dialog, `bg-navy` header + `bg-neutral-100` footer, OK button
  `#modalOkBtn` bound to `@click="$store.scannerMessageModal.handleOk()"`.
- New Alpine **store** `scannerMessageModal` (`open`, `title`, `body`,
  `afterModal`, `_prevFocus`) registered on `alpine:init`:
  `openWith(opts)` populates `#modalTitle`/`#modalMessage` via **innerText**
  (preserves newlines in the multi-line "Already Saved" message — NOT `x-text`,
  which would collapse `\n`), locks body scroll, opens, focuses OK on nextTick;
  `close()` hides, clears `afterModal` (no stale callback), restores scroll +
  focus to the previous element; `handleOk()` captures the callback, closes,
  then runs it **synchronously** (OK -> callback immediately, no
  transitionend/async dependency — preserves the callback sequencing).
- **Bridge/API preserved:** `window.showModal(msg, type, title, onOk)` keeps
  its exact original signature and delegates to
  `Alpine.store('scannerMessageModal').openWith(...)`, then `playSound` — all
  17 in-file callers unchanged.
- Removed the old module-level `let afterModal` and the direct
  `#modalOkBtn` click listener (superseded by the store's `handleOk`).
- Tab trap via the standard project `handleTab($event)` on
  `@keydown.tab.prevent.stop` with `x-ref="dialog"`; ESC via
  `@keydown.escape.window`.

**Accessibility:** `role="dialog"` + `aria-modal="true"` + labelled/described;
backdrop `aria-hidden="true"`; focus enters the dialog on open (OK, primary
action); focus restored on close; Tab trapped; body scroll locked while open;
ESC + backdrop close (matching old behavior, neither fires the callback);
close control has `aria-label="Close"`; OK keyboard-accessible.

**Bootstrap JS removed (for `#messageModal` only):** the modal's
`data-bs-dismiss="modal"` attributes, the `.modal/.modal-dialog/
.modal-dialog-centered/.modal-content/.modal-header/.modal-body/.modal-footer/
.modal-title` structure, the `new bootstrap.Modal(...)` call, and the direct
OK listener. No `show.bs.modal`/`hidden.bs.modal`/`getOrCreateInstance`
controlled it. All other Bootstrap usage (leaderboard, client-ID, unpaid
self-service, students photo modals, alert banners, shared layout flash toast,
Bootstrap CDN) remains in place.

**Files modified:** `resources/views/scanners/scan.blade.php`,
`e2e/scanner-message-modal-phase13.spec.ts` (new).

**Tests:** `npm run build` clean; `php artisan view:cache` clean;
`vendor\bin\pint --test` clean; `php artisan test` -> full suite
**297 passed (1397 assertions), 0 failures**; `--filter=ScannerTest` ->
18 passed (125 assertions). Playwright
`e2e/scanner-message-modal-phase13.spec.ts` added documenting the modal
contract (dialog roles/attrs, `showModal` open + plain-text populating, OK runs
callback once + closes, backdrop/ESC/X close without callback, focus entry, no
Bootstrap-JS dependency). Like every prior phase, E2E fails at `signIn()` only
because the `smoke_superadmin` test user is absent from the production-copy DB
(DB not modified; not a regression).

**Regression check:** `scanners/scan.blade.php` no longer references
`new bootstrap.Modal`, `show.bs.modal`, `hidden.bs.modal`, `data-bs-*`,
`getOrCreateInstance`, or `relatedTarget` for `#messageModal` (only an HTML
comment mentions `bootstrap.Modal`); the 17 `showModal(` callers remain and
point through the Alpine bridge. No other scanner view uses `messageModal`.

**Next migration candidate requiring separate approval:** see the plan — next
safest self-contained Bootstrap surface: the `data-bs-dismiss="alert"`
banners, then `#clientIdPromptModal` (scholars) or `#leaderboardModal`
(audit_logs).

---

### 2026-09-07 - ui.css removal audit + retirement plan (AUDIT ONLY + document)

**Deliverable:** an independent answer to "can `public/css/ui.css` be safely removed
now?" plus the execution roadmap that makes deletion safe. **Audit only — no
application source, CSS, JS, schema, or database was changed.**

**Verdict: `NOT SAFE — MIGRATION REMAINING`.** `ui.css` is the only provider of:

1. the `--ui-*` design tokens consumed by the built `app.css` (39 `var(--ui-*)` refs),
   `public/css/datatables.css` (L532/552/562/567/568/571/572 — DataTables pagination
   navy/gold + focus ring across all 11 index screens), and three views
   (`dashboard` 29 refs / 17 unique tokens via arbitrary-value utilities,
   `scanners/scan.blade.php` L38 `--ui-text-sm`, `clients/index.blade.php` L47/L135
   `--ui-focus-ring`) — the build defines **0** `--ui-navy`/`--ui-focus-ring`;
2. the §4.10 Reboot + `_type.scss` element-parity layer (`box-sizing`, `body`
   reset, heading sizes) — the build has **0** `box-sizing` and Tailwind Preflight
   is intentionally NOT enabled (§I);
3. the §3 global `:focus-visible` gold outline + `prefers-reduced-motion` —
   the build has **0** of either (only component-scoped focus rules);
4. the Bootstrap-parity vocabulary still used across ~46 of 68 blades: forms
   (31 files), `btn btn-*` (12), `.btn-close` (10), toasts/alerts (5+), lists
   (7), dropdowns (3), accordion (5), tables (9/12), `.align-middle` (12),
   `.table-responsive` (1), and the JS-contract `.d-none` (10) plus
   spread/utility/grid classes.

**Not blockers (already mirrored in app.css):** `.status-badge*`, `.data-card*`,
`.metric-card*`, `.ui-notice`, `.ui-empty`, `.ui-micro-label`, `.ui-skip-link`, and
the new-name Tailwind vocabulary (`.btn-navy/.btn-gold/...`, `.field-*`, `.filter-*`,
`.icon-btn`). Verified safe: `details-panel.blade.php` defines `--panel-w` itself.

**Shared-name value-drift hazard (the coexistence rule):** Tailwind twins already in
the build carry different values (`.mt-5` 3rem vs 1.25rem; `.py-3` 1rem vs 0.75rem) —
today `ui.css` loads after the Vite CSS and `!important`-wins; deletion would silently
re-space unmigrated screens. This is the exact collision the §E allowlist exists to
prevent.

**Artifact:** `docs/UI_CSS_RETIREMENT_PLAN.md` — six migration steps
(M1 tokens→`@theme`, M2 Reboot/type port or §I Preflight decision, M3 global
focus/reduced-motion, M4 Bootstrap-parity completion per family, M5 JS-contract
trace, M6 regressions + deletion gate), with consumer inventory, gates, risks, and
rollback.

**Files modified:** `docs/UI_CSS_RETIREMENT_PLAN.md` (new),
`docs/TAILWIND_MIGRATION_EXECUTION_PLAN.md` (§J cross-reference + findings),
`docs/IMPLEMENTATION_LOG.md` (this entry).

**Tests:** no application code changed; suite state unchanged at **301 tests /
1419 assertions / 0 failures** (not re-run). Read-only verification: repo + built
CSS greps documented in the plan's evidence tables.

### 2026-09-07 - M1: port `--ui-*` tokens into `app.css` `@theme static` (retirement plan M1)

**Scope:** the token-migration step of `docs/UI_CSS_RETIREMENT_PLAN.md` §4 M1.
Removed the application's **live dependency** on the `--ui-*` token definitions in
`public/css/ui.css` by remapping every consumer to the canonical Tailwind v4
`@theme static` tokens (which are value-identical). `ui.css` itself was **not**
modified or deleted; Tailwind Preflight was **not** enabled; Bootstrap-parity
migrations (M2–M6) were **not** started.

**Changes (exact):**

1. `resources/css/app.css` — added `--shadow-focus: 0 0 0 0.2rem rgba(252, 209, 22, 0.3);`
   to the `@theme static` shadow ramp (value-exact copy of `ui.css --ui-focus-ring`);
   renamed the app.css-local custom property `--ui-accent` → `--accent` (self-contained
   metric-card accent var, defined + consumed only inside app.css; rename done for gate
   hygiene so zero `--ui-` names remain in editable scope).
2. `public/css/datatables.css` — 7 pagination refs remapped: `var(--ui-navy)` →
   `var(--color-navy)` (L532/567/568/571), `var(--ui-navy-hover)` → `var(--color-navy-hover)`
   (L552), `var(--ui-focus-ring)` → `var(--shadow-focus)` (L562/572); provenance comments
   updated.
3. `resources/views/dashboard.blade.php` — all 29 `var(--ui-*)` arbitrary-value references
   remapped to canonical tokens: `--ui-radius`→`var(--radius-btn)`, `--ui-radius-sm`→
   `var(--radius-control)`, `--ui-radius-lg`→`var(--radius-card)`, `--ui-gold`→
   `var(--color-gold)`, `--ui-gold-dim`→`var(--color-gold-dim)`, `--ui-teal`→
   `var(--color-teal)`, `--ui-red`→`var(--color-red)`, `--ui-border`→`var(--color-line)`,
   `--ui-border-light`→`var(--color-line-light)`, `--ui-card`→`var(--color-surface)`,
   `--ui-bg-alt`→`var(--color-bg-alt)`, `--ui-text-primary`→`var(--color-ink)`,
   `--ui-text-secondary`→`var(--color-ink-secondary)`, `--ui-text-muted`→
   `var(--color-ink-muted)`, `--ui-shadow-sm`→`var(--shadow-card)`, `--ui-shadow-md`→
   `var(--shadow-lift)`, `--ui-ease`→`var(--ease-standard)` (all value-identical per the
   audit mapping; edits ordered longest-name-first so `--ui-radius` did not clobber
   `-sm`/`-lg`).
4. `resources/views/clients/index.blade.php` — L47/L135 `outline: 2px solid
   var(--ui-focus-ring)` → `var(--shadow-focus)` (token name swap only; declaration
   semantics preserved exactly).
5. `resources/views/scanners/scan.blade.php` — L38 `font-size: var(--ui-text-sm)` →
   `var(--text-dense)` (both `.85rem`).
6. `resources/views/partials/global-search.blade.php` — no-fallback class refs
   (`focus:border-[var(--ui-gold)]`, `focus:ring-[var(--ui-gold)]/30`) and fallback
   inline/JS refs (`--ui-card`, `--ui-border-light`, `--ui-navy`, `--ui-text-muted`,
   `--ui-text-primary`, `--ui-bg-alt`) all remapped to their canonical `var(--color-*)`
   names; fallback values preserved verbatim.

**Token remapping table (ui.css → app.css `@theme static`, value-exact):**
`--ui-navy`→`--color-navy`; `--ui-navy-hover`→`--color-navy-hover`; `--ui-gold`→
`--color-gold`; `--ui-gold-dim`→`--color-gold-dim`; `--ui-teal`→`--color-teal`; `--ui-red`→
`--color-red`; `--ui-bg-alt`→`--color-bg-alt`; `--ui-card`→`--color-surface`; `--ui-border`→
`--color-line`; `--ui-border-light`→`--color-line-light`; `--ui-text-primary`→`--color-ink`;
`--ui-text-secondary`→`--color-ink-secondary`; `--ui-text-muted`→`--color-ink-muted`;
`--ui-text-sm`→`--text-dense`; `--ui-radius`→`--radius-btn`; `--ui-radius-sm`→
`--radius-control`; `--ui-radius-lg`→`--radius-card`; `--ui-shadow-sm`→`--shadow-card`;
`--ui-shadow-md`→`--shadow-lift`; `--ui-ease`→`--ease-standard`;
`--ui-focus-ring`→`--shadow-focus` **(new token added)**.

**Verification (M1 gate):** `npm run build` clean (Vite 6.4.3, 58 modules). Built CSS
`public/build/assets/app-BfKwCcaC.css` now emits `--color-navy:#0038a8`,
`--shadow-focus:0 0 0 .2rem #fcd1164d` (== rgba(252,209,22,0.3)) and **0**
`var(--ui-*` references. Static gate `rg -- "--ui-" resources public build` → only
provenance comments in `app.css` `@theme static` header (documentation, not refs);
**0 live `var(--ui-` refs** across `resources`, `public/css/datatables.css`, and the
build (excluding the retained `public/css/ui.css`, whose self-references remain by design).

**Files modified:** `resources/css/app.css`, `public/css/datatables.css`,
`resources/views/dashboard.blade.php`, `resources/views/clients/index.blade.php`,
`resources/views/scanners/scan.blade.php`, `resources/views/partials/global-search.blade.php`,
`docs/IMPLEMENTATION_LOG.md` (this entry), `docs/UI_CSS_RETIREMENT_PLAN.md` (M1 status),
`docs/SESSION_HANDOFF.md` (status + next step).

**Tests:** no PHP changed; suite state unchanged at **301 tests / 1419 assertions /
0 failures** (not re-run). Build verified; live browser parity not re-run - auth-gated
screens, no admin account, DB untouched.

### 2026-09-07 - M2: port Reboot + `_type.scss` element parity into `app.css` `@layer base` (retirement plan M2)

**Scope:** the Reboot/type ownership step of `docs/UI_CSS_RETIREMENT_PLAN.md` §4 M2.
**Decision taken: Option B** — Tailwind Preflight remains **OFF** (matches §I). The
element-reset + heading-type rules that `ui.css` §4.10 owns today were ported **verbatim**
into `resources/css/app.css` under a `@layer base` block, so once `ui.css` retires (M6)
these are the canonical source. `ui.css` was **not modified, deleted, or unlinked**;
M3/M4 were **not** started; Bootstrap-parity component classes were **not** migrated.

**Rules moved (verbatim from `ui.css` §4.10, lines 1269–1316, values byte-identical):**
universal `box-sizing: border-box` on `*, *::before, *::after`; `body { margin: 0; color: #212529; }`;
heading margin resets (`h1–h6 { margin-top: 0; margin-bottom: .5rem }`); `p`/`ol,ul,dl`
margins + `ol,ul { padding-left: 2rem }` + nested-list margin; `dt`/`dd`; `blockquote`;
`b,strong { font-weight: bolder }`; `hr` (margin/color/border/opacity); `abbr[title]`;
`address`; `a { color:#0d6efd; text-decoration: underline }` + `a:hover` + non-link resets;
`img, svg { vertical-align: middle }`; `table` (caption-side/border-collapse); `caption`;
`th` align; `thead/tbody/tfoot/tr/td/th` border reset; `label { display: inline-block }`;
`button { border-radius: 0 }`; `button:focus:not(:focus-visible)`; `input/button/select/
optgroup/textarea` (margin/font/line-height inherit); `button, select { text-transform: none }`;
`[role="button"] { cursor: pointer }`; `select { word-wrap: normal }`; `textarea { resize: vertical }`;
`_type.scss` heading keys (`h1–h6, .h1–.h6` fluid/static sizes) + the `@media (min-width:1200px)`
fixed heading sizes.

**Placement rationale (0 visual-diff guarantee):** the port lives in `@layer base`
(native cascade layer, order declared at `app.css:60`). Because `ui.css` is still linked
and loads **after** the Vite CSS and is **unlayered**, ui.css's identical §4.10 rules
**outrank** the layered copy at equal specificity, so the effective rendered values are
**unchanged**. No new **unlayered**
element rules were added (the pre-existing prototype html/body/h1–h6 typography block at
`app.css:291–309` stays unlayered and sets distinct font/leading/weight props that §4.10
does not touch), so nothing new can override anything. When `ui.css` retires at M6, these
layered rules become the sole source and render the same values.

**Verification (M2 gate):** `npm run build` clean → `public/build/assets/app-dN0ghNSi.css`;
compiled CSS contains `@layer base` block (braces balanced) with `*,:before,:after{
box-sizing:border-box}`, `body{color:#212529;margin:0}`, link/table/label/button/form
defaults, and the `_type.scss` heading sizes + 1200px media query. Served `/login` loads
`app-dN0ghNSi.css` then `ui.css` (both active; `ui.css` byte-identical, git-clean). Live
Chromium parity harness (scratch `m2-parity.mjs`, removed after use) on 5 public pages
(`/login`, `/qr-viewer`, `/student/update-photo?search=`, `/unpaid-verification`,
`/grantee-update`) × 375/768/1280: universal `box-sizing:border-box` and
`body{margin:0;color:rgb(33,37,41)}` **all pass**; **0 console errors, 0 page errors,
0 horizontal overflow** at every viewport. The only computed "deviations" observed
(`label`→`block` via ui.css `.form-label`; `button`→`8px` via `.btn`/`rounded-btn`; a
input-group corner) are **pre-existing** higher-specificity component/utility rules that
also outrank ui.css §4.10 today — **not drift** introduced by M2 (verified the winning
rules are in ui.css §4.8 / app.css components). PHPUnit: **301 tests / 1419 assertions /
0 failures**; Pint clean; `view:cache` clean.

**Files modified:** `resources/css/app.css` (added `@layer base` Reboot/type block),
`docs/IMPLEMENTATION_LOG.md` (this entry), `docs/UI_CSS_RETIREMENT_PLAN.md` (M2 status +
DoD checkboxes), `docs/SESSION_HANDOFF.md` (status + next step).

**Tests:** no PHP changed; suite state unchanged at **301 tests / 1419 assertions /**
0 failures (re-run this session: confirmed). Pint clean. Live parity harness green on all
reachable public surfaces; auth-gated screens not browser-verifiable (no admin account,
DB untouched) - parity for those is guaranteed structurally (ui.css still owns them
unlayered with identical values).

### 2026-09-07 - M3: port global `:focus-visible` + `prefers-reduced-motion` into `app.css` (retirement plan M3)

**Scope:** the accessibility-foundations ownership step of `docs/UI_CSS_RETIREMENT_PLAN.md`
§4 M3. Two global a11y rules owned today by `ui.css` §3 were ported **verbatim** into
`resources/css/app.css` `@layer base`. `ui-skip-link` was left in app.css (already owned
at `app.css:447`); `ui.css` was **not modified, deleted, or unlinked**; Preflight stays
**OFF**; M4 was **not** started; no Bootstrap-parity component classes changed; no Blade
markup changed.

**Rules moved (verbatim from `ui.css` §3, values byte-identical):**
1. Global `:focus-visible { outline: 3px solid var(--color-gold); outline-offset: 2px; }`
   — golden 3 px ring, offset 2 (prototype §5.7). Uses the canonical `--color-gold`
   (`#FCD116`, M1) in place of `--ui-gold` so the port depends on no ui.css token; the
   rendered hue/width/offset are identical to the baseline.
2. `@media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration:
   0.01ms !important; animation-iteration-count: 1 !important; transition-duration:
   0.01ms !important; scroll-behavior: auto !important; } }` — verbatim.

**Placement rationale (0 visual-diff guarantee):** both blocks were added inside the
existing `@layer base` (retirement plan M2). Because `ui.css` §3 is still linked and is
**unlayered**, its identical rules **outrank** these layered copies at equal specificity
(the motion guard's `!important` loses only to ui.css's identical unlayered `!important`
copy), so the rendered focus ring and motion behavior are **unchanged**. When `ui.css`
retires at M6 these layered rules become the sole source and render the same values.

**Verification (M3 gate):** `npm run build` clean → `public/build/assets/app-ByXmc8gJ.css`;
static greps on the compiled CSS confirm `@media(prefers-reduced-motion:reduce)` is present
and a **global unscoped** `:focus-visible{outline:3px solid var(--color-gold);outline-
offset:2px}` rule exists; `:focus-visible` selector count **7** (global + 6 scoped: the two
`focus-visible\:*` Tailwind ring variants, `.btn-navy`, `.icon-btn`, `.filter-group-toggle`,
`.filter-multi-search`). Live Playwright focus harness (scratch `m3-focus.mjs`, removed
after use): injected bare `<button>` + bare `<a>` on `/login`, keyboard-focused at
375/768/1280 — both computed `outline: 3px solid rgb(252,209,22)` with `2px` offset,
**matching the ui.css baseline exactly (6/6 assertions)**; **0 console errors, 0 page
errors, 0 horizontal overflow** at every viewport. PHPUnit: **301 tests / 1419 assertions /
0 failures**; Pint clean; `view:cache` clean; `ui.css` git-clean (unchanged, still linked).

**Files modified:** `resources/css/app.css` (added global `:focus-visible` +
`prefers-reduced-motion` to `@layer base`), `docs/IMPLEMENTATION_LOG.md` (this entry),
`docs/UI_CSS_RETIREMENT_PLAN.md` (M3 status + DoD checkbox), `docs/SESSION_HANDOFF.md`
(status + next step).

**Tests:** no PHP changed; suite state **301 tests / 1419 assertions / 0 failures**
(re-run: green). Pint clean. Focus-ring parity verified live on public surface; auth-gated
screens not browser-verifiable (no admin account, DB untouched) — parity structurally
guaranteed (ui.css §3 unlayered with identical values).

### 2026-09-07 - M4.1: utility drop for `.d-flex`/`.align-middle`/`.text-muted`/`.bg-white`/`.mx-auto`/`.fw-bold` (retirement plan M4.1)

**Scope:** `docs/UI_CSS_RETIREMENT_PLAN.md` §M4, sub-step M4.1 ("utility drop"). Migrate or
deliberately retain the six Bootstrap utility families. `ui.css` **not** modified/deleted/
unlinked; Tailwind Preflight **OFF**; excluded families (`.d-none`, spacing, `.row`/`.col-*`,
forms, buttons, alerts, tables, dropdowns, accordion, `.list-group`, `.btn-close`,
`.btn-group`) **not touched**; M4.2+ **not** started; no CSS source changed (markup-only), no
PHP changed.

**Before counts (resources/views, word-boundary):**
`d-flex` 10 · `align-middle` 12 · `text-muted` 2 · `bg-white` 24 · `mx-auto` 16 · `fw-bold` 5.
Zero references to any of the six in `resources/js`/`public/js` (no JS contract at risk).

**Migrated (markup-only to existing Tailwind, value-identical, Tailwind already emitted in
the build):**
- `.d-flex` → `flex` on **9/10**: `auth/login.blade.php:35`; `grantee_update/self-service`
  (L80 `col-md-6 flex align-items-end`, L232 `mt-3 flex gap-2`), `grantee_update/
  _self_update_tab` (L36, L188); `unpaid_verifications/self-service` (L69 `col-md-6 flex
  align-items-end`, L79 `flex flex-wrap …`); `qr/viewer.blade.php` (L81 `col-md-6 flex
  align-items-end`, L95 `mt-3 flex …`). All migrated targets have **no competing
  `display` rule** (`.col-md-6` sets only `flex:0 0 auto; width`; Bootstrap `align-items-*` /
  `justify-content-*` set no `display`), so non-`!important` Tailwind `flex` is effective
  and renders identically to bootstrap `.d-flex { display:flex !important }`.
- `.fw-bold` → `font-bold` on **5/5**: `clients/_gip.blade.php:13` (`accordion-button
  font-bold` — `.accordion-button` sets no `font-weight`), `scanners/scan.blade.php` L434 +
  L444 (JS template `fs-4 font-bold text-primary`), `grantee_update/self-service:435` +
  `_self_update_tab:415` (JS template `mt-2 font-bold`). Tailwind `font-bold` resolves to
  `font-weight:700` = ui.css `.fw-bold { font-weight:700 !important }`, no competing
  `font-weight` on any target.
- `.mx-auto` (**16**) required **no change**: the class string is **identical** in
  Bootstrap and Tailwind; Tailwind `mx-auto` (`margin-inline:auto`) is emitted from scanned
  screens and applies globally, so every `.mx-auto` element already renders
  `margin-left/right:auto` with no competing rule. Documented; no edit needed.

**Retained with documented justification (deliberate, not visual drift):**
- `.d-flex` ×1 (`students/update-photo.blade.php:35`, `list-group-item d-flex …`):
  `ui.css .list-group-item { display: block }` (0-1-0, unlayered) would beat the
  non-`!important` Tailwind `flex` at equal specificity while ui.css is linked (unlayered
  outranks `@layer utilities`) → displaying the item as flex would become `block` = a real
  change. Also enchains the excluded `list-group` family. Retained for M4.x list-group step.
- `.align-middle` ×12 (all `table`/`table-sm` on DataTables + permission matrix rows):
  pure `!important` table `vertical-align:middle`; the non-`!important` Tailwind twin loses
  the cascade to unlayered `ui.css .table { vertical-align:top }` at equal specificity →
  computed drift. Table family is outside M4.1. Retained for M4 table step.
- `.text-muted` ×2 (`family_members/create:112`, `households/create:186` — JS template
  strings): value mismatch — `ui.css .text-muted` = `rgba(33,37,41,.75)` whereas Tailwind
  `text-gray-500` = `#6b7280` is a different gray. No exact Tailwind twin. Retained.
- `.bg-white` (24 matches; only ~5 are genuine Bootstrap `.bg-white`, the rest are
  already-Tailwind `dark:bg-white`/`hover:bg-white/10`/`focus:bg-white` which need no
  change): genuine full-class usages are `family_members/create:62` (`border bg-white …
  d-none`), `transactions/create:85`, `transactions/edit:89`, `scanners/scan:190`,
  `scholars/_form:17` (all `list-group position-absolute bg-white border` JS-driven
  autocomplete containers). Each coexists with an excluded family (`list-group`, `d-none`)
  or is a JS dropdown container. Retained with its paired family for M4.x list-group step.

**Affected files (M4.1 markup):** `resources/views/auth/login.blade.php`,
`resources/views/clients/_gip.blade.php`, `resources/views/grantee_update/self-service.blade.php`,
`resources/views/grantee_update/_self_update_tab.blade.php`,
`resources/views/unpaid_verifications/self-service.blade.php`, `resources/views/qr/viewer.blade.php`,
`resources/views/scanners/scan.blade.php` (+ docs below). `students/update-photo`,
`align-middle`/`text-muted`/`bg-white` sites: **unchanged** (retained).

**Verification:**
- `npm run build` clean; emitted `app-ByXmc8gJ.css` unchanged hash (markup-only — no CSS
  source changed); `flex{display:flex}`, `font-bold`, `mx-auto`, `bg-white` present in
  compiled CSS.
- Live Playwright (scratch harness, removed): `/login` at 375/768/1280 — the migrated
  `.flex.align-items-center…` container computes `display:flex` (= baseline) **12/12
  assertions**; injected `font-bold` element computes `font-weight:700` (= baseline);
  **0 console errors, 0 page errors, 0 horizontal overflow**.
- Post-migration grep: `d-flex` → 1 (retained `update-photo:35`); `fw-bold` → **0**;
  `mx-auto`/`align-middle`/`text-muted`/`bg-white` unchanged by design.
- PHPUnit **301 tests / 1419 assertions / 0 failures** (re-run: green). Pint clean.
  `ui.css` git-clean (unchanged, still linked). Preflight OFF. Excluded families untouched.

**Tests:** no PHP/CSS changed; suite **301 / 1419 / 0**. Focus/display/weight parity verified
live on the public reachable surface (/login); the migrated `flex`/`font-bold` sites on
auth-gated screens are guaranteed by the cascade analysis (no competing rule) + global
Tailwind utility emission. Docs updated: this entry, `UI_CSS_RETIREMENT_PLAN.md` (Status +
M4.1 note), `SESSION_HANDOFF.md` (item 14).

### 2026-09-07 - M4.2: forms family migration (`.form-control`/`.form-select`/`.form-check-input`/`.form-label`/`.input-group` + form-state secondary classes) (retirement plan M4.2)

**Scope:** `docs/UI_CSS_RETIREMENT_PLAN.md` §M4, sub-step M4.2 ("Forms (heaviest)"). Migrate
only the forms usage that can be safely represented by the existing canonical Tailwind/
`app.css` vocabulary, preserving behavior, appearance, accessibility, validation, and backend
contracts. `ui.css` **not** modified/deleted/unlinked; Tailwind Preflight **OFF** (only
`theme.css`/`utilities.css` imported, `preflight` appears in comments only); excluded M4
families (buttons `.btn*`, alerts, tables/DataTables, dropdowns, accordion, list-group,
`.d-none`, `.row`/`.col-*`/`.g-*`, spacing, `.btn-close`) **not touched**; M4.3+ **not**
started; no CSS source changed, no PHP changed, no JS changed.

**Phase A inventory (before):**
`.form-control` 189 · `.form-select` 60 · `form-check*` 37 · `.form-label` 48 (46 real
consumers + 2 comments) · `.input-group` 5 · `.form-control-sm` 12 · `.form-select-sm` 7 ·
`.is-invalid` 4 (custom, **not ui.css**) · `.is-valid`/`invalid-feedback`/`valid-feedback`/
`needs-validation`/`was-validated`/`form-control-plaintext`/`form-control-lg`/`form-select-lg`/
`form-check-inline` 0. Zero JS-module or view-JS references to any target class as a selector/
toggler (`resources/js`/`public/js` count = 0); the only validation contract on these classes
is the project's custom `.is-invalid` flow in `clients/index.blade.php` (scoped `<style>` +
`classList.toggle`), which is **not** provided by `ui.css` (no `.is-invalid` rule exists in
`ui.css`) and is untouched.

**ui.css ownership (recorded, source of truth):** `.form-control` = block, w-100%, pad
`.375rem .75rem`, font 1rem/400/1.5, color `#212529`, appearance none, bg #fff, border
1px `#dee2e6`, radius `.375rem`, navy focus border + `--ui-focus-ring` shadow, placeholder
`#6c757d`, disabled `#e9ecef`; `.form-select` = w-100%, pad `.375rem 2.25rem .375rem .75rem`,
custom SVG chevron bg, navy focus ring; `.form-check-input` = 1em box, radio/checkbox native
`appearance:none` + SVG checked/indeterminate states + navy checked; `.form-label` =
`margin-bottom: .5rem` (only); `.input-group` = flex-wrap flex + `.form-control/.form-select`
`flex:1 1 auto` + `.input-group-text` + radius-collapse/margin-left `-1px` seam rules tied to
`.btn`.

**Migration scope decision (cascade discipline):** the only class with an exact value-parity
Tailwind twin is `.form-label` (`margin-bottom:.5rem` = Tailwind `mb-2` = `calc(var(--spacing)*2)`
= `.5rem`). All other M4.2 target classes were **retained** because migration would not
preserve the current rendered result under Preflight-OFF + still-linked `ui.css`:

| Family | Before | Migrated | Retained | Reason |
| --- | ---: | ---: | ---: | --- |
| `.form-label` | 48 (46 real) | 46 | 2 (comments) | exact parity `.5rem` → `mb-2` |
| `.form-control` | 189 | 0 | 189 | redesigned `app.css .field-control` ≠ Bootstrap metrics (dense 0.85rem/8px/ink vs 1rem/6px·12px/#212529); no Tailwind twin reproduces the exact control incl. `--ui-focus-ring` + file/password/number/date/textarea variants |
| `.form-select` | 60 | 0 | 60 | native `<select>` + custom SVG chevron + navy focus ring; a Tailwind `<select>` would change appearance/native behavior |
| `.form-check-input` | 37 | 0 | 37 | `appearance:none` + SVG checked/radio/indeterminate + navy checked states — high-risk native control redesign |
| `.input-group` | 5 | 0 | 5 | flex layout + radius-collapse/margin `-1px` seam tied to `.btn`/`.input-group-text`; migration would pull excluded button family into scope |
| secondary (`-sm`, `is-invalid`) | 23 | 0 | 23 | `-sm` all sit in DataTables toolbar/inline-edit (excluded table family); `.is-invalid` is a custom non-ui.css contract |

**Migrated (markup-only):** `.form-label` = `class="form-label"` → `class="mb-2"` on **46**
labels across `resources/views/grantee_update/self-service.blade.php` (23) and
`resources/views/grantee_update/_self_update_tab.blade.php` (23). No `for=` / text / structure
changed; validation, autocomplete, disabled states, Alpine/JS contracts untouched.
`layouts/app.blade.php` + `qr/viewer.blade.php` still mention `form-label` **in comments only**
(describing frozen ui.css ownership) — left intact.

**Cascade/parity verification:** root font-size is `14px` (`html{font-size:14px}` @ app.css
L291), so `.5rem = 7px`; **before** (`.form-label` .5rem) and **after** (`mb-2` = .5rem)
compute **identically = 7px** at 375/768/1280. Live direct comparison on `/grantee-update`
(injecting a `.form-label` element vs the migrated `.mb-2` labels) → equal at every viewport.
`.form-control` untouched → still the Bootstrap rem-based padding (`5.25px 10.5px` at the 14px
root) and focus/validation states unchanged.

**Files changed:** `resources/views/grantee_update/self-service.blade.php`,
`resources/views/grantee_update/_self_update_tab.blade.php` (markup only), plus docs
(`IMPLEMENTATION_LOG.md` this entry, `UI_CSS_RETIREMENT_PLAN.md`, `SESSION_HANDOFF.md`).
No `resources/css/app.css` change this phase (build CSS hash unchanged: `app-ByXmc8gJ.css`).

**Verification:** `npm run build` clean (CSS hash unchanged — markup-only; `mb-2` already
emitted). Playwright on `/grantee-update` + `/login` @ 375/768/1280: label parity before==after
(21/21 assertions), `.form-control` class+metrics untouched, **0 console/page errors, 0
horizontal overflow, 0 layout shift** (login 6/6). Post-migration grep: `.form-label` → 0 real
consumers (2 comments only). PHPUnit **301 tests / 1419 assertions / 0 failures**; Pint clean.
`ui.css` git-clean (unchanged, still linked); Preflight OFF; M4.3+ untouched; scratch harnesses
removed.

**Known limitations:** auth-gated form screens (admin/users, permissions matrices, scholars,
transactions) and DataTables `-sm` inputs cannot be browser-verified locally (no admin
account, DB untouched) — retained classes guarantee byte-parity structurally (ui.css still
owns them). `.is-invalid` custom validation untouched (not a ui.css dependency).

**Tests:** no PHP/CSS/JS changed; suite **301 / 1419 / 0**. Docs updated: this entry,
`UI_CSS_RETIREMENT_PLAN.md` (Status + M4.2 note), `SESSION_HANDOFF.md` (item 14).

### 2026-09-08 - M4.3: layout/grid family migration (`.row`/`.col-*`/`.g-*`/`.gx-*`/`.gy-*`) (retirement plan M4.3)

**Scope:** `docs/UI_CSS_RETIREMENT_PLAN.md` §M4, sub-step M4.3 ("Layout/Grid"). Migrate only
the layout/grid usage that can be safely represented by the existing canonical Tailwind/
`app.css` vocabulary, preserving exact layout, spacing, responsiveness, and behavior. `ui.css`
**not** modified/deleted/unlinked; Tailwind Preflight **OFF**; excluded M4 families (buttons
`.btn*`, forms `.form-*`, alerts, tables/DataTables, dropdowns, accordion, list-group,
`.d-none`, `.btn-close`, spacing `.m-*`/`.p-*`, plus the M4.1-retained `.d-flex`/`.align-middle`/
`.text-muted`/`.bg-white`/`.fw-bold`/`.mx-auto`) **not touched**; M4.4+ **not** started; no CSS
source changed, no PHP changed, no JS changed.

**Phase A inventory (before, unchanged after):** `.row` **20** (4 × `row g-2 mb-3` header rows,
14 × bare `row` field-group rows, 2 × `<dl class="row">` JS-generated DataTables detail grids) ·
`col-sm-*` **48** (24 × `col-sm-4` + 24 × `col-sm-8`, all inside the `<dl class="row">` templates) ·
`col-md-*` **54** (12 × `col-md-6`, 24 × `col-md-3`, 18 × `col-md-4`) · `.g-2` **4** · `.gx-*`/`.gy-*`
**0** · `.col`, `.col-auto`, `.col-lg-*`, `.col-xl-*`, `.col-xxl-*` **0**. Consumers:
`grantee_update/self-service` (8 rows/25 cols), `grantee_update/_self_update_tab` (8/25),
`qr/viewer` (1/2), `unpaid_verifications/self-service` (1/2), `payouts/attendance` (dl+24 sm),
`unpaid_verifications/index` (dl+24 sm). No spacing-family migration performed; `mb-2`/`mb-3`
on columns/rows read but left **untouched** (out of scope).

**ui.css ownership (recorded, source of truth):** `.row` (ui.css L1227-1243) = `--ui-gutter-x:
1.5rem; --ui-gutter-y: 0; display:flex; flex-wrap:wrap; margin-top: calc(-1 * gutter-y);
margin-right/left: calc(-.5 * gutter-x)`; `.row > *` (L1236-1243) = `flex-shrink: 0; width:
100%; max-width: 100%; padding-right/left: calc(gutter-x * .5); margin-top: gutter-y`; `.g-2`
(L1246) = `--ui-gutter-x: .5rem; --ui-gutter-y: .5rem` (g-0/1/3/4/5 exist, unused); media
`min-width: 576px` `.col-sm-4/.col-sm-8` = `flex: 0 0 auto; width: 33.33333333%/66.66666667%`; media
`min-width: 768px` `.col-md-3/4/6` = `flex: 0 0 auto; width: 25%/33.33333333%/50%`. This is a
true **gutter-compensation** system (negative-margin + child-padding + margin-top), **not**
`gap`-based.

**Classification (per §6):** header rows = A (Bootstrap grid, 2-col md+/stack <md) + G-adjacent
(second column holds a `.btn-navy` button — excluded family, but the grid itself is the working
object); field rows = A (3/4-column md+/stack <md) interleaved with spacing `mb-2`; `<dl>` grids =
A (2-col sm+/stack <sm) **inside JS template literals for DataTables detail modals** (dynamic
markup + excluded table/modal context). No Bootstrap `.row`-inside-`.col-*` nesting exists (the
update-form rows are direct flex children of `#updateForm`; the `<dl>` grids are flat) — Nested
Grid Rule (N/A) confirmed.

**Computed baseline (live, `/grantee-update` @375/768/1280; root 14px):**
header `.row.g-2`: `display:flex; flex-wrap:wrap; margin -7px top / -3.5px left/right; gap:
normal` (not gap-*); columns `padding: 0 3.5px; flex-shrink:0; flex-basis:auto` → width 283px
(100%, stacked, 375) / 338px (50%, 768) / 478px (50%, 1280). Field `.row`: `margin-top: 0;
margin -10.5px left/right; gap: normal`; columns `padding: 0 10.5px` → col-md-3 297px(100%)/172.5px(25%)/242.5px(25%),
col-md-4 297px/230px(33.333%)/323.328px(33.333%), col-md-6 297px/345px(50%)/485px(50%).

**Safe-migration analysis (why retained):** a faithful Tailwind replacement would require the
bespoke stacked construction `flex flex-wrap` + `-mx-*` + per-child `*:px-*` (+ `-mt-*`/`*:mt-*`
for g-2) on every `.row` plus `w-full` + `md:w-*/md:flex-none` (flex: 0 0 auto → `basis`) on
every column — i.e., re-implementing the Bootstrap gutter model with raw utilities. Per §7/§10
this is a custom compatibility construction whose value-parity only holds by arithmetic
coincidence at the 14px root and which cannot be provably identical (gutter outer-edge
compensation, `flex-shrink:0` width-basis, wrap transitions) at all breakpoints; §19 declares
**retention a valid result** when exact parity cannot be achieved safely. `gap-2`≠`.g-2`
(Bootstrap: 3.5px pad + 3.5px negative margin each side; Tailwind gap: 7px free space, zero
edge compensation) — direct value collision, retained. `.col-sm-*` additionally sit in dynamic
JS string markup under an excluded DataTables/modal context and are not locally verifiable
(auth-gated). M4.3 therefore **migrates nothing and retains all 126 grid/column/gutter classes**
(unanimous, documented).

**Files changed:** none (markup/views unchanged in M4.3). Docs only:
`IMPLEMENTATION_LOG.md` (this entry), `UI_CSS_RETIREMENT_PLAN.md` (Status + M4.3 note),
`SESSION_HANDOFF.md` (item 14 + Documentation Status row). No `resources/css/app.css` change;
build CSS hash unchanged (`app-ByXmc8gJ.css`), JS hash unchanged (`app-DqsLDVL_.js`).

**Verification:** `npm run build` clean. Playwright `/grantee-update` + `/login` @ 375/768/1280:
geometry captured above proves the grid renders the documented Bootstrap contract (gutter
`gap:normal`, negative-margin compensation, col widths 25%/33.333%/50% at md+, 100% below),
**0 console/page errors, 0 horizontal overflow** at all widths (login 375 0 overflow). PHPUnit
**301 tests / 1419 assertions / 0 failures**; Pint clean. `ui.css` git-clean (unchanged, still
linked); Preflight OFF; M4.4+ untouched; scratch harness (`m43-geometry.mjs`) removed.

**Known limitations:** `<dl class="row">`/`.col-sm-*` detail grids render only inside auth-gated
DataTables modals (no admin account locally, DB untouched) — retained Bootstrap classes
guarantee structural parity (ui.css owns them); verified statically, not browser-measured.
Spacing utilities (`mb-2`/`mb-3`) interleaved with the grids were read for grid-context only and
left untouched per scope.

**Tests:** no PHP/CSS/JS changed; suite **301 / 1419 / 0**. Docs updated: this entry,
`UI_CSS_RETIREMENT_PLAN.md` (Status + M4.3 note), `SESSION_HANDOFF.md` (item 14).

### 2026-09-08 - M4.4: buttons family migration (`.btn`/`.btn-sm`/`.btn-lg`/`.btn-primary`/`.btn-danger`/`.btn-outline-primary` + project variants) (retirement plan M4.4)

**Scope:** `docs/UI_CSS_RETIREMENT_PLAN.md` §M4, sub-step M4.4 ("Buttons"). Migrate only
button usage that can be safely represented by the existing canonical Tailwind/`app.css`
vocabulary, preserving exact appearance, dimensions, states, accessibility, and JS contracts.
`ui.css` **not** modified/deleted/unlinked; Tailwind Preflight **OFF**; excluded families
(`.btn-group` ×2 — Alpine dropdown contracts in `clients/index` + `transactions/index`; `.btn-close`
×15) **not touched**; forms/grid/layout/spacing/tables/dropdowns/accordion/list-group/`.d-none`
**not touched**; M4.5+ **not** started; no CSS source changed, no PHP changed, no JS changed.

**Phase A inventory (before, unchanged after):** Bootstrap-parity `.btn` base **25** (all in
`btn <variant>` composites); `.btn-sm` **4**; `.btn-lg` **0**; `.btn-primary` **1**; `.btn-danger`
**1**; `.btn-outline-primary` **2**; `.btn-secondary/success/warning/info/light/dark/link` and all
other `.btn-outline-*` variants **0**. Project variants (canonical `app.css` vocabulary, listed
for the composite impact): `.btn-gold` **34** (7 with `.btn`), `.btn-navy` **47** (1), `.btn-red`
**10** (4), `.btn-subtle` **81** (6), `.btn-outline` **3** (3), `.btn-outline-red` **3** (0).
Composites by file: `btn btn-gold` admin/users/show:133, households/show:150, scholars/gip-show:88,
scholars/show:209, payouts/show:100, transactions/show:145, clients/_details:327 · `btn btn-navy`
clients/_details:323 · `btn btn-red` clients/_details:333, payouts/show:107, unpaid_verifications/show:91,
transactions/show:151 · `btn btn-subtle` admin/audit_logs/show:86, households/show:152, payouts/show:110,
unpaid_verifications/show:94, transactions/show:154, clients/_details:325 · `btn btn-outline`
scholars/gip-show:88, scholars/show:210,211 · `btn btn-sm btn-primary`/`btn btn-sm btn-danger`
payouts/attendance:167/168 (**JS DataTables template literal**) · `btn btn-sm btn-outline-primary`
grantee_update/self-service:433 + _self_update_tab:413 (**JS template literal**, QR download).

**ui.css ownership (recorded, source of truth):** `.btn` (ui.css L233-251) = `display:inline-block;
padding: .375rem .75rem (5.25/10.5px @14px root); font 1rem/400/1.5; color:#212529; bg transparent;
border: 1px solid transparent; border-radius: .375rem (5.25px); text-align:center; cursor; transition
.15s ease-in-out…`; `.btn:hover{border-color: currentColor}`; `.btn:focus-visible{outline:0}`;
`.btn:disabled{pointer-events:none; opacity:.65}`. `.btn-sm` (L270-274) = `padding .25rem .5rem
(3.5/7px); font .875rem; radius .25rem (3.5px)`. `.btn-primary` (L277-305) = `#fff on --ui-navy
#0038A8, hover/active --ui-navy-light #164A9C / --ui-navy-hover #002B7F, focus ring
rgba(0,56,168,.5) .25rem`. `.btn-danger` (L340-365) = `#fff on --ui-red #CE1126, hover/active
#A80F20/#900C1B, focus ring rgba(206,17,38,.5)`. `.btn-outline-primary` (L307-335) = `navy text,
transparent bg, navy border, hover→navy fill white`.

**Canonical vocabulary (inspected, app.css):** the shared block `.btn-gold/.btn-navy/.btn-red/
.btn-subtle/.btn-outline-red` (@app.css L523-529) is **self-contained**: `inline-flex; cursor;
justify-center/items-center; gap .5rem; rounded-btn (8px); text-dense (.85rem/600/snug);
padding-block .5625rem (7.875px) / inline 1rem (14px); no-underline; .2s ease-standard;
disabled:opacity-60` + variant colors. `.btn-outline` (L540-545) is **not** self-contained
(border+color only, no metrics) and always pairs with `.btn`. Design intent (app.css L499-506):
variants are meant to be used **without** Bootstrap `.btn`.

**Cascade finding (empirical, decisive):** with `class="btn btn-<variant>"`, both ui.css `.btn`
(unlayered, linked AFTER app.css at `layouts/app.blade.php` L12-13: `@vite` then `ui.css`) and the
app.css variant (unlayered, same specificity 0,1,0) apply — and the later/unlayered **`.btn` WINS
the conflicting metrics**. Live-computed probe (html buttons injected on `/login`, measured default/
hover/active/focus @375/768/1280; identical at all widths): `btn btn-navy` ⇉
`display:inline-block; padding 5.25/10.5px; radius 5.25px; font 14px/400; color #212529; bg
rgba(0,0,0,0); border 1px solid transparent` — i.e. the **legacy ghost `.btn` metrics**, NOT the
self-contained variant metrics. The same delta holds for gold/red/subtle/outline (12+ differing
computed properties per state after removing `.btn`: display, padding ×4, font-size/weight,
line-height, color, bg-color, border ×3, radius, text-align, transition-duration, and hover/active/
focus box-shadows). Measuring `.btn-navy` alone yields the intended `inline-flex; 7.875/14px;
8px radius; 11.9px/600; white on navy`.

**Safe-migration analysis (why retained):** M4.4 §8/§19/§21 demand **exact** parity of the current
rendered contract. Removing `.btn` from any composite **changes** display/dimensions/typography/
color/background/border/radius/transition in every state (proved above) — a visual regression
forbidden by §1 ("Do not redesign buttons") and §19 (any failing condition → retain + document).
The self-contained variant look is the *intended future* state, but the current rendering IS the
Bootstrap `.btn` look, and M4.4's mandate is parity — so all `.btn` composites stay. The pure
Bootstrap variants (`.btn-sm/.btn-primary/.btn-danger/.btn-outline-primary`) have no canonical
Tailwind twin (navy-light hover, 4px focus ring, `.btn-sm` 3.5/7px metrics) and live only in
**dynamic JS template literals** on DataTables (auth-gated, excluded family) / grantee QR
templates. `.btn-outline` (app.css, non-self-contained) hard-depends on `.btn` for metrics.
M4.4 therefore **migrates nothing and retains all 25 `.btn` + 45 variant-class usages**
(unanimous, documented); zero view/CSS/JS changes.

**JS/Alpine contract findings:** `.btn-group` (2) + `.btn-close` (15) excluded and untouched
(noted inspection-only). `.btn-subtle dropdown-toggle` (clients/index), `.btn-subtle view-btn`,
`.btn-outline-red delete-btn`, `.btn btn-sm btn-* view-btn/delete-btn` (payouts/attendance JS) use
**secondary hooks** (`.dropdown-toggle`, `view-btn`, `delete-btn`, `.reset-btn`, `data-*` ids) — no
selector uses `.btn`/`.btn-variant` /`.btn-*` as a JS contract; `data-edit-*`/`data-sim`/`data-qr-*`
attributes drive Alpine/modals, unaffected. No button element has `form`/`name`/`value` cross-form
verbs; `type="submit"`/`type="button"` preserved everywhere (untouched markup).

**Dynamic-template findings:** `payouts/attendance` (L167-168) and `unpaid_verifications/index`
(L169) build buttons inside JS template literals for DataTables action columns; the grantee QR
download links (`btn btn-sm btn-outline-primary`) are JS-generated after verification. Retained as
Bootstrap (ui.css owns them) — no local browser verification possible (auth-gated; the QR links
render behind the DB-driven verify flow), documented static-only.

**Files changed:** none (markup/views unchanged in M4.4). Docs only: `IMPLEMENTATION_LOG.md` (this
entry), `UI_CSS_RETIREMENT_PLAN.md` (Status + M4.4 note), `SESSION_HANDOFF.md` (item 14 +
Documentation Status row). No `resources/css/app.css` change; build CSS/JS hashes unchanged
(`app-ByXmc8gJ.css`, `app-DqsLDVL_.js`).

**Verification:** `npm run build` clean. Playwright `/login` @375/768/1280: computed-style probe of
all live button configurations (default/hover/active/focus) recorded; **0 console/page errors, 0
horizontal overflow** at all widths (sw==cw). PHPUnit **301 tests / 1419 assertions / 0 failures**;
Pint clean. `ui.css` git-clean (unchanged, still linked); Preflight OFF; M4.5+ untouched; scratch
harnesses (`m44-btn-probe.mjs`, `m44-analyze.mjs`, probe json) removed.

**Known limitations:** the four pure-Bootstrap variant + `.btn-sm` buttons render only inside
auth-gated DataTables modals / DB-driven verify flows — retained Bootstrap classes guarantee
byte-parity structurally (ui.css owns them), verified statically and via the injected-probe
(computed metrics match ui.css declarations), not on their real pages. The hover/active/focus
captures reflect mid-`0.15s`-transition values in some states; end-stop colors are the ui.css
declarations recorded above. `.btn-group`/`.btn-close` remain excluded per scope.

**Tests:** no PHP/CSS/JS changed; suite **301 / 1419 / 0**. Docs updated: this entry,
`UI_CSS_RETIREMENT_PLAN.md` (Status + M4.4 note), `SESSION_HANDOFF.md` (item 14).

### 2026-09-08 - M4.5: alerts/feedback family migration (`.alert` + alert variants/alerts/show/flash) (retirement plan M4.5)

**Scope:** `docs/UI_CSS_RETIREMENT_PLAN.md` §M4, sub-step M4.5 ("Alerts/Feedback"). Migrate
only alert/feedback consumers with an exact canonical representation; retain + document the rest.
`ui.css` **not** modified/deleted/unlinked; Preflight **OFF**; excluded families **not touched**:
`.toast` (0 in scope), migrated Alpine toast/flash components (`layouts/app` flash toast, etc.),
`.btn`, `.btn-close` (15 — incl. the one inside the dismissible validation alert below),
`.d-none` (successBox state hook), `.form-*`, `.row`/`.col-*`/`.g-*`, tables/DataTables,
dropdowns, accordion, list-group, spacing utilities, session flash backend, controllers,
routes, validation logic. M4.6+ **not** started; no CSS/PHP/JS changed.

**Inventory (before == after):** Bootstrap-parity `.alert` classes live in **17 class usages
+ 2 selector-string refs**, all retained:
- **Server-rendered (2):** `layouts/app.blade.php:101` — `<div class="alert alert-danger
  alert-dismissible" role="alert" x-data="{ open: true }" x-show="open">` session validation
  error list + `btn-close` dismissal (@click `open=false`) — the only dismissible alert;
  `unpaid_verifications/self-service.blade.php:109` — `#successBox` skeleton `<div class="alert
  alert-success d-none mt-4 text-center">`, shown/filled by JS (`classList.remove('d-none')` +
  `textContent`, L382-383) — `.d-none` visibility state hook.
- **JS-generated (Blade-embedded template literals, 15):** `grantee_update/self-service`
  327/331/348/428/447, `grantee_update/_self_update_tab` 307/311/328/408/427,
  `qr/viewer` 175/195/221, `unpaid_verifications/self-service` 257/266/269/345. All injected
  into empty containers (`#alertBox`, `#saveMsg`, `#selfSaveMsg`) which carry **no** `.alert`
  class themselves. Variants used: alert-danger ×11, alert-success ×3 (incl. the QR-success
  block with `text-center`), alert-warning ×1 (`small mb-0` inside the Alpine confirmation
  modal template).
- **0 consumers + 0 ui.css rules for:** `alert-primary`, `alert-secondary`, `alert-info`,
  `alert-light`, `alert-dark`, `alert-heading`, `alert-link` (absent both sides — nothing to
  migrate). `.alert-info` rule exists in ui.css but has **no live consumer**.
- **Not part of the family (recorded for completeness, untouched):** `role="alert"` ARIA on
  custom Tailwind error bars (the red left-border `<div ... role="alert" x-data>` in
  `family_members/create`, `households/create`, `duplicates/index`, `admin/users/index:79`) and
  the `ui-notice`/`ui-notice-info` info bars (`transactions/create`, `transactions/edit`,
  `admin/users/index:72`) — already canonical `app.css`/Tailwind components, **not** `.alert`
  consumers; `panel-form-errors`/`form-errors` custom contract; the string `alert(...)` =
  the **JavaScript `window.alert()`** legacy calls (~20), not the class family.

**ui.css ownership (Step 2, source of truth):** L691-731 — `.alert { position:relative;
padding:1rem 1rem; margin-bottom:1rem; color:inherit; background:transparent; border:1px solid
transparent; border-radius:.375rem }`; `.alert-dismissible { padding-right:3rem }`;
`.alert-dismissible .btn-close { position:absolute; top:0; right:0; z-index:2; padding:1.25rem
1rem }`; variants on the stock Bootstrap 5.3 emphasis palette: `-success` `#0a3622`/
`#d1e7dd`/`#a3cfbb`, `-danger` `#58151c`/`#f8d7da`/`#f1aeb5`, `-warning` `#664d03`/`#fff3cd`/
`#ffe69c`, `-info` `#055160`/`#cff4fc`/`#9eeaf9`. No `-heading`/`-link`/`-primary/secondary/
light/dark` rules.

**app.css feedback vocabulary (Step 2):** **no `.alert` rules anywhere** in
`resources/css/app.css`. The nearest component `.ui-notice` (L702-704) is an informational
gold bar (`flex items-start gap .65rem rounded-control border-l-3 border-gold bg-gold-light
text-dense leading-snug text-ink`) — a **redesigned** info box, not an `.alert` twin (no
palette variants, different radius/metrics, no dismissal seam). The custom red error bars
(`border-l-[var(--color-red)] bg-[rgb(206_17_38_/_0.06)] rounded-[var(--radius-control)]`)
are Tailwind-canonical but distinct components. No canonical Tailwind alert exists to map to.

**Cascade analysis (Step 3) — why RETAIN ALL:** the alert variants are committed to exact
palette bytes + Bootstrap alert metrics (1rem pad, 1rem mb, `relative`, 1px flat border, `.375rem`
radius, 1.6 line-height via root). A Tailwind equivalent would be the arbitrary-value stack
`relative p-4 mb-4 border border-<variant-border> rounded-[5.25px] text-<variant> bg-<variant>`
hardcoding Bootstrap hex bytes — a bespoke reimplementation §10 forbids, not canonical
vocabulary; it also **cannot reach the JS-generated alerts** (markup lives in Blade-embedded
template literals; changing them = JS behavior change, out of scope). Live-parity probe
(elements injected in-flow on public `/grantee-update`, 375/768/1280) confirmed the current
contract renders exactly as ui.css declares at all widths: base/danger/success = 14px pad,
14px mb, 5.25px radius, 14px/22.4px/400; warning+`small` = 12.25px font, 0 mb; dismissible =
42px (3rem) right pad with `.btn-close` at (dx 1, dy 1)px from the top-right, z-2 → parity
holds; migration would *change* appearance → §8/§19 retain.

**Dismissal/JS contract findings (Step 5):** dismissal paths — `layouts/app` alert via Alpine
`x-show` toggled by the `.btn-close` `@click` (`.btn-close` untouched); `successBox` via
`.d-none` removal (untouched); the JS-generated danger/success alerts are replaced wholesale by
`innerHTML` (no dismissal). **`.alert` is a live JS selector contract:**
`grantee_update/self-service:444` and `_self_update_tab:424` (`msg.querySelector('.alert')
.insertAdjacentHTML(...)` appended after the QR-success alert) — removing `.alert` from the
injected markup throws `TypeError: null` on the QR-error path. No reference to any `.alert*`
class exists in `resources/js`/`public/js` (0 matches). No Alpine store/`$store` selector uses
`.alert`. `role="alert"` present on the server alert; JS alerts carry no `role`. The 20
`window.alert()` calls are the browser dialog, unrelated to the class family.

**Verification (Steps 6-8):** `npm run build` clean — CSS/JS hashes unchanged
(`app-ByXmc8gJ.css`, `app-DqsLDVL_.js`); Playwright `/grantee-update` @375/768/1280 — probe
default states above, **0 console/page errors, 0 horizontal overflow** (sw==cw) at every width,
close-button inside `.alert-dismissible` positioned correctly (right-gap 1px, z-2, padding
`1.25rem/1rem` → 42px content edge); PHPUnit **301 / 1419 / 0**, Pint clean. `ui.css` intact +
linked, Preflight OFF, M4.6+ untouched, no `.btn`/`.btn-close`/toast/flash/`d-none` migration,
0 view/CSS/JS changes; scratch harnesses (`m45-alert-probe.mjs`, `m45-analyze.mjs`, probe json)
removed.

**Known limitations:** 11 of 17 alert usages only render behind DB-driven verify/QR flows
(auth-adjacent, not locally browser-verifiable) — retained Bootstrap classes guarantee parity on
the server pages structurally; the live in-flow probe on the public host page verified the shared
`.alert` contract that all 17 consume.

**Tests:** no PHP/CSS/JS changed; suite **301 / 1419 / 0**. Docs updated: this entry,
`UI_CSS_RETIREMENT_PLAN.md` (Status + M4.5 note), `SESSION_HANDOFF.md` (item 14).

### 2026-09-08 - M4.6: remaining component families (tables / dropdowns / `.btn-close` / accordion / list-group / `.d-none` + utilities) (retirement plan M4.6 — final `ui.css` M4 pass)

**Scope:** `docs/UI_CSS_RETIREMENT_PLAN.md` §M4, sub-step M4.6 (final sub-step of the
Bootstrap-parity family pass, prior to M5/M6). Inspect the remaining live component families,
migrate only demonstrably safe consumers. **Verdict: RETAIN ALL — zero view/CSS/JS changes.**
`ui.css` **not** modified/deleted/unlinked; Tailwind Preflight **OFF**; `public/css/datatables.css`
unchanged (project-owned skin); DataTables 1.13.6 CDN runtime (`jquery.dataTables.min.js` +
`dataTables.bootstrap5.min.js`) untouched; no backend/database changes; no M5/M6 work; no
`.d-flex` (M4.1-migrated) or `.d-none` side-migration; no global utility rewrite.

**Step 1 — inventory (before == after).** All consumers are the result of prior Phase
21/25/26 migrations that kept Bootstrap-parity classes for the still-CDN-free screens:
- **Tables:** `.table` on **21 live tables** — 15 DataTables instances (clients/index `table-sm`,
  audit_logs/index `table-sm align-middle`, admin/users/index `align-middle mb-0`,
  payouts/attendance `table-sm`, households/index `table-sm`, scholars/index `table-sm` (×3) +
  `align-middle mb-0`, update_logs/index `table-sm align-middle mb-0`, transactions/index
  `table-sm` (inside `.table-responsive`), unpaid_verifications/index `table-sm`,
  scholarship_reports/index `table-sm`, duplicates/index `align-middle`) + 6 plain tables
  (audit_logs/index `leaderboard-table`, admin/permissions/actions+pages+scopes+programs
  `matrix-table align-middle mb-0`, households/show `members-table align-middle mb-0`,
  sessions/online `align-middle mb-0`). `.table-sm` ×12 (DataTables), `.table-responsive` ×1
  (DataTables wrapper, transactions/index:130, `tabindex`+`aria-label` scroll contract),
  `.align-middle` ×12 (M4.1 already retained with `!important` justification).
- **Dropdowns/button groups:** 3 Alpine-driven menus, all toggled by `:class="{ 'show': open }"`
  + `@click`/`@click.outside`/`@keydown.escape`: clients/index:265 (`btn-group` +
  `.btn-subtle.dropdown-toggle` + `.dropdown-menu.dropdown-menu-end` + 2 `.dropdown-item`),
  transactions/index:94 (same + 4 `.dropdown-item.export-link`), partials/navbar:80
  (`.dropdown` + `.dropdown-toggle` + `.dropdown-menu.dropdown-menu-end` + logout `.dropdown-item`).
- **`.btn-close` (15, HIGH-RISK):** alert (layouts/app:105), shell flash toast (layouts/app:92),
  toasts (clients/index:290 + :769 JS, households/index:97, transactions/index:122,
  clients/_details:840 JS), error bars (duplicates/index:77+88, admin/users/index:74+81,
  family_members/create:52, households/create:68), mobile sidebar
  (partials/sidebar:50 — `btn-close-white ms-auto lg:hidden`).
- **Accordion (1):** clients/_gip:10-25 — single Alpine-driven accordion (`.accordion`,
  `.accordion-item`, `.accordion-header`, `.accordion-button` + `.collapsed` bound via Alpine
  `:class="{ 'collapsed': !accordionOpen }"`, `.accordion-collapse` + `x-show`/`x-collapse`,
  `.accordion-body`). `clients/_details` `.details-accordion-*` are **project-owned** classes
  (not Bootstrap) — out of scope.
- **List-group (5):** `#search_results`/`#client_search_results` autocomplete containers
  (scanners/scan:190, transactions/create:85, transactions/edit:89, scholars/_form:17) —
  `.list-group.position-absolute.bg-white.border` + **JS-created** `li` via
  `classList.add('list-group-item', 'list-group-item-action')` (scan:650, create:208, edit:209,
  _form:94); one static `.list-group.list-group-flush` + `.list-group-item.d-flex.
  justify-content-between.align-items-center.bg-transparent.px-0` (students/update-photo:33-35).
  The **public** self-service autocompletes (qr/grantee/unpaid) use `.suggestions-list d-none`,
  NOT `.list-group`.
- **`.d-none` (behavioral):** **74** class-attr usages in views + JS `classList.add/remove('d-none')`
  hooks (incl. the 3 public self-service `suggestList` show/hide + `#successBox` + confirm-section
  reveal + modal/show flows). Complements `.d-block`, `.d-flex`, `.d-inline-flex`, `position-*`
  used as state pairs.
- **Remaining utilities (recorded, not inventoried-to-the-token):** `text-center`/`text-danger`/
  `text-uppercase`, `mt-2/3/4`, `mb-0/2/3/4`, `px-0`, `position-relative/-absolute`, `bg-white`,
  `.border`, `overflow-hidden`, `justify-content-between`, `align-items-center`, etc. — all
  stock-Bootstrap values in ui.css §4.9 with `!important` (e.g. `.mt-4`/`.mb-4` = **1.5rem
  (24px)**, NOT Tailwind 1rem; `.text-danger` = **#dc3545**, NOT `--color-red` #CE1126;
  `.align-middle` = `vertical-align:middle !important`).

**Step 2 — JS/Alpine contract findings (decisive):** `dropdown-menu`'s `.show` is an **Alpine
state contract** (`:class="{ 'show': open }"` ×3) → cannot be renamed (Step 4 rule).
`accordion-button`'s `.collapsed` is an **Alpine class-binding contract**
(`:class="{ 'collapsed': !accordionOpen }"` + `:aria-expanded`, `x-collapse` handles open/close
animation) → Bootstrap classes are styling-only under Alpine ownership but the `.collapsed`
state class toggles the SVG chevron transform (ui.css L929-947) and corner radii. `list-group-
item`/`list-group-item-action` are **added by JS at runtime** in 4 files (`classList.add(...)`
string contract) → removing the classes breaks the injected items. `.d-none` is a live
`classList.add/remove` **state hook in ~dozens of JS paths** incl. the public pages (Step 8: all
five migration conditions fail — JS/Alpine rely on it). `.btn-close` is a **JS/Aria contract**
(`@click="open = false"`, `aria-label="Close"`) used inside excluded parents (alert, toast,
sidebar, error bars) → untouched per Step 5. `.table` on DataTables screens is consumed by the
**DataTables 1.13.6 bootstrap5 integration** (CDN `dataTables.bootstrap5.min.js` +
`.dataTables_wrapper` chrome from `datatables.css` scoped `#clients-screen .dataTables_wrapper
.page-link/.page-item.active` custom rules etc.) → the base `.table`/`.table-sm` cell metrics
feed that surface; changing the base breaks the integration contract. 0 JS references to
`.accordion*`/`.table`/`.dropdown-menu`/`.list-group` as selectors beyond the injected-item
`classList.add` strings and the `.show`/`.collapsed` Alpine bindings.

**Steps 3-10 — cascade/parity analysis per family (why RETAIN):**
- **Tables:** a Tailwind twin of `.table` = `w-full mb-4 border-collapse` +
  `px-2 py-2 text-black/white + border-b` + `align-bottom` thead + cell `vertical-align`
  defaults — a bespoke byte stack (§10-forbidden) that must also replicate the DataTables
  integration seam; plain tables (permission matrices, sessions/online, households/`members-table`)
  carry their own view-scoped chrome. `.table-sm` (3.5px cells) and `.table-responsive`
  (`overflow-x:auto` wrapper) sit on excluded DataTables screens. `.align-middle` already
  justified in M4.1 (`!important`, excluded-family coupling). → **RETAIN ALL** — DataTables
  coupling (15) + no canonical plain-table vocabulary + §10 byte-stack prohibition.
- **Dropdowns/btn-group:** ui.css §4.7 = caret `::after` borders, `.dropdown-menu`
  (min-width 10rem, 1px `rgba(0,0,0,.175)` border, `.375rem` radius, `0 .5rem 1rem` shadow,
  z-1000, `position:absolute`, display toggle via `.show`), `.dropdown-item` (1rem/0.25rem
  padding, hover `#f8f9fa`, active navy `#0d6efd`), plus `[data-bs-popper]` offsets. `.show`
  state contract + Alpine ownership → migrate would require re-deriving positioning, caret
  SVG, focus/hover, end-alignment AND renaming the state hook. → **RETAIN ALL 3.**
- **`.btn-close`:** ui.css §4.3 = content-box 1em×1em + .25em pad (14px+3.5px → 21×21 box),
  inline SVG data-URI (16×16 path, `fill='%23000'`), radius `.375rem`, opacity .5, hover .75,
  focus ring `rgba(13,110,253,.25)` .25rem, disabled .25, `.btn-close-white` `invert(1)
  grayscale(100%) brightness(200%)`. Tailwind has no close-button (icon glyph must be authored);
  parity = bespoke SVG + arbitrary ring/opacity + position padded variants. Parents (alerts,
  toasts, sidebar, error bars) are excluded/deployed contracts. → **RETAIN ALL 15.**
- **Accordion:** ui.css §4.8 = full Bootstrap 5.3.2 accordion (border `#dee2e6`, inner radius,
  active `#084298`/`#cfe2ff` + inset shadow, chevron SVG swap on `.collapsed` via
  `--ui-accordion-btn-active-icon` + `transform rotate(-180deg)`, focus ring, body 1.25rem/1rem
  padding). Alpine already owns open/close (x-show/x-collapse); the styling + `.collapsed`
  state class are the presentation contract. → **RETAIN.**
- **List-group:** ui.css §4.8 = `.list-group` flex-column + `.375rem` radius, `.list-group-item`
  (1rem/.5rem padding, `.5rem 1rem`… measured 14px, `#212529/#fff`, 1px `#dee2e6` border with
  collapse via `+` sibling width-0), `.list-group-item-action` (hover `#f8f9fa`, active `#e9ecef`),
  `.list-group-flush` (radius 0, 0 0 1px borders). JS injects `list-group-item`/`-action` classes
  at runtime; static flush item composite also carries Bootstrap utilities. → **RETAIN (JS
  contract + presentational byte set).**
- **`.d-none`:** behavioral display-state utility behind `classList`/`x-show`-adjacent flows on
  public + auth pages; Step 8's five migration conditions fail (JS state hooks present; no
  computed equivalence need borne out; replaced only at M5). → **RETAIN ALL 74 + JS hooks.**
- **Utilities:** broad-basis rewrite explicitly forbidden; values diverge from Tailwind
  (`mt-4/mb-4` 24px vs 16px; `.text-danger` #dc3545 vs `--color-red`; `!important` everywhere);
  several are coupled to retained families (`.text-center` inside M4.5 `.alert` composites,
  `.position-absolute` on list-group containers, `.bg-white`/`.border` on search results).
  → **RETAIN (no isolated, uncoupled, byte-exact migration met the Step 9 bar).**

**Step 11 — browser verification (current-state contract, live-tab probe):** Playwright probe
on public `/grantee-update` @**375/768/1280** injected in-flow representatives of every active
family and measured computed styles vs the ui.css declarations above: `.table` cell = 7px/7px
pad (0.5rem), `#000`/`#fff`; `.table-sm` = 3.5px (0.25rem); `.align-middle` = `middle`;
`.table-responsive` = `overflow-x:auto`; `.dropdown-menu` closed `display:none`/abspos/z-1000,
`+ .show` → `display:block` (probed by toggling the class live), min-width 140px, 5.25px
radius, shadow; `.dropdown-item` 14px pad `#212529`, active `#fff`/`#0d6efd`; `.btn-close`
21×21 (content-box 1em+.25em), opacity .5, SVG present, 5.25px radius (hover .75/focus ring as
declared — synthetic `:hover` can't force the UI state, cited from ui.css); accordion chevrons
SVG in `/collapsed` states, open = `#084298`/`#cfe2ff` + inset shadow, item border `#dee2e6`;
`.list-group-item` 14px pad `#212529/#fff` `#dee2e6`; flush variant radius 0; `.d-none`
`display:none !important`. **0 console/page errors, 0 horizontal overflow (sw==cw) at every
width.** No behavior differences measured because nothing was migrated (before==after).

**Step 12 — regression:** `npm run build` clean — hashes unchanged (`app-ByXmc8gJ.css`,
`app-DqsLDVL_.js`); PHPUnit **301 / 1419 / 0**; Pint clean. `datatables.css` diff is
pre-existing/untouched by this pass; `dataTables.bootstrap5.min.js` CDN + integration intact.

**Step 13 — scope:** no M5/M6, no `ui.css` deletion/weakening, Preflight OFF, DataTables skin +
CDN intact, no backend/DB, no unrelated refactor. Scratch harnesses
(`m46-probe.mjs`, `m46-analyze.mjs`, probe json) removed.

**Known limitations:** 16 of the 21 tables, all 3 dropdowns, 13 of 15 `.btn-close`, the
accordion and 4 of 5 list-groups render only on auth-gated screens (no local account) — verified
by the injected-contract probe on the public host page and by static declaration cross-check,
not on the real pages. Retention is therefore the byte-safe choice on every family.

**Tests:** no PHP/CSS/JS changed; suite **301 / 1419 / 0**. Docs updated: this entry,
`UI_CSS_RETIREMENT_PLAN.md` (Status + M4.6 note - M4 complete; M5/M6 pending),
`SESSION_HANDOFF.md` (item 14).

### 2026-09-08 - M5.1: behavioral-contract & `ui.css` dependency audit (retirement plan M5 — AUDIT ONLY)

**Scope:** `docs/UI_CSS_RETIREMENT_PLAN.md` §M5 (remove `d-none`/utility reliance or bridge it —
JS/Alpine behavioral contracts). Forensic, **read-only** audit: enumerate every live dependency
still blocking `ui.css` deletion, classify per contract type, map the ownership of each family,
and refine the M5 execution roadmap. **Verdict: ui.css NOT SAFE to delete — M5.2+ migrations
required.** **Zero application files changed** (Blade/CSS/JS/PHP/config/routes/DB untouched);
`ui.css`, `datatables.css`, `app.css`, build assets byte-identical (hashes re-verified);
Tailwind Preflight **OFF**; DataTables 1.13.6 CDN intact; no M6, no deletion, no backend change.

**Step 1 — baseline (captured, unchanged).** `public/css/ui.css` =
`ABB10A4BD2E0FDF111B5E996824F0F0C7AE69F48FC22891D1BB7E9B498A92E44` (1,492 lines);
`public/css/datatables.css` = `4BED7A2C0C8E64564B01891143EB199DC04AF73F5ED0F3E8BA71829EDDDF4F9F`
(586 lines); `resources/css/app.css` =
`2E257D09E03B49E06D2195FDBF4F4D640E4226A0A4D0BB3490A379957C62A861` (1,125 lines).
Build: `public/build/assets/app-ByXmc8gJ.css` + `app-DqsLDVL_.js`. PHPUnit baseline
**301 / 1419 / 0** (unchanged). `git status`: only the pre-existing M1–M4 working set
(12 `resources`/`public`/`docs` files + untracked retirement plan) — the audit added nothing to it.

**Step 2 — serving/cascade topology.** `ui.css` is `<link>`ed from **8 layouts** (`layouts/app`
plus 7 standalone heads: `auth/login`, `qr/viewer`, `students/verify`,
`students/update-photo`, `students/photo-upload`, `grantee_update/self-service`,
`unpaid_verifications/self-service`), each `@vite(app.css)` before it. `datatables.css` is
`<link>`ed from **10 screens** via `@stack('styles')` (clients/index, households/index,
duplicates/index, scholars/index, payouts/attendance, admin/users/index,
admin/audit_logs/index, update_logs/index, unpaid_verifications/index,
scholarship_reports/index). Cascade: `@vite` (layered app.css) → `ui.css` (unlayered) →
`@stack('styles')`. Equal-specificity unlayered `ui.css` rules therefore **win** every tie over
layered app.css; `datatables.css` beats `ui.css` on DT markup only via its higher-specificity
`.dataTables_wrapper …` selectors.

**Step 3 — ui.css section inventory (1,492 lines).** §1 `:root --ui-*` tokens (L23–132,
self-referential only today; **0** `var(--ui-` consumers remain in `resources`/`public`/`build`
outside comments — M1 gate holds); §2 base typography hooks incl. `.ui-micro-label` (L150–158,
mirrored at app.css:713); §3 a11y (global `:focus-visible` + `prefers-reduced-motion` +
`.ui-skip-link`, ported in M3); §4.1 buttons; §4.2 form controls + `.input-group`; §4.3
`.btn-close`; §4.4 `.btn-group`; §4.5 `.alert*`; §4.6 `.table`/`.table-sm`/`.align-middle`/
`.table-responsive`; §4.7 `.dropdown*` + `.page-link`; §4.8 `.form-label`/`.accordion*`/
`.list-group*`; §4.9 utility parity (`!important`, spacing ladder, `.row`/`.col-*` grid,
`.d-none`, text helpers); §4.10 Reboot+`_type` (mirrored in M2); §5 app components
(`.status-badge*`, `.metric-card*`, `.data-card*`, `.ui-page-header*`, `.ui-notice`,
`.ui-empty` — the page-header family has **0 live Blade consumers**, the rest mirror app.css).

**Step 4 — behavioral contracts (the M5 trace).**

- **`.show` — MIXED contract.** (a) **Alpine state class** on the 3 dropdowns
  (`clients/index:269`, `transactions/index:94-104`, `partials/navbar:80-93`): the menu is
  `.dropdown-menu.dropdown-menu-end` with `:class` binding `{ 'show': open }`; `open` is
  `x-data` local state reset by `@click`/`@click.outside`/`@keydown.escape`; its
  `display:block` comes from ui.css §4.7 `.dropdown-menu.show`. Renaming `.show` breaks all 3
  menus → **live Alpine contract**. (b) **App-authored marker** on toasts (`clients/index:771,
  795`, `clients/_details:842` `classList.add('show')`): **no `.show` or `.toast` rule exists in
  ui.css or datatables.css** — toasts are rendered by Tailwind utilities and `.show` is a pure
  JS marker with zero CSS effect → **not a ui.css dependency** (app-owned).
- **`.collapsed` — Alpine class-binding (1 consumer).** `clients/_gip:14` binds
  `{ 'collapsed': !accordionOpen }` on the `.accordion-button`; the body uses
  `x-show="accordionOpen"` + `x-collapse`. Styling (active `#084298`/`#cfe2ff`, chevron swap,
  corner-radius on `.collapsed`) comes from ui.css §4.8 accordion — the **only** accordion
  consumer in the app. Live contract (display is Alpine, but the `.collapsed` styling + chevron
  are ui.css-only; app.css has **0** accordion rules).
- **`.d-none` — behavioral JS hook.** 21 static class usages + runtime toggles via
  `classList.add/remove('d-none')` in `family_members/create` (existing-client results),
  `clients/_form` (IP-group switch), `grantee_update/self-service` +
  `grantee_update/_self_update_tab` (suggest list / mobile-verify / update-form),
  `qr/viewer` (suggest list / QR container), `households/create` (results list),
  `unpaid_verifications/self-service` (`#successBox`), `students/photo-upload` (camera 4-way),
  plus `jQuery.toggleClass('d-none', …)` inline-edit show/hide in `transactions/index:281-282`.
  Tailwind emits **0** `.d-none`; ui.css §4.9 supplies `display:none !important`. Renaming/
  removing silently leaves 74+toggles-driven sections permanently visible → **hard behavioral
  blocker**, bridged only by a Tailwind `hidden` swap or an `@utility d-none` port.
- **`.btn-close` — JS-created-element contract.** 15 consumers: 13 static
  (Alpine `@click`/`closeModal()` dismiss handlers inside toasts/alerts/modals + sidebar:50
  `btn-close btn-close-white ms-auto lg:hidden`) and 2 JS-created (`clients/index:769`,
  `clients/_details:840` `el.innerHTML` close buttons). Glyph/metrics/`.btn-close-white` filter
  come only from ui.css §4.3 (21×21, opacity .5, SVG, focus ring). No app.css twin.
- **`.alert` — live JS selector hook** (`grantee_update` msg.querySelector('.alert') ×2 on the
  QR-error append path) + 17 class usages incl. Blade JS template literals ×15 (M4.5 RETAIN —
  re-confirmed unchanged).
- **`list-group-item`/`list-group-item-action` — runtime `classList.add` strings** in 4 files
  (`scanners/scan:650`, `transactions/create:208`, `transactions/edit:209`,
  `scholars/_form:94`) + static `list-group-item` composite in `students/update-photo:35`
  (M4.6 — re-confirmed). Styling = ui.css §4.8 only.
- **`actions-col` (DataTables `columnDefs.className`, 3 screens: `clients/index:554`,
  `unpaid_verifications/index:165`, `payouts/attendance:165`)** — **0 ui.css rules**; the only
  CSS consumer is app.css `.actions-col .btn` (mobile touch min-size, app.css:1118) →
  app-owned DT-authored class, **not** a ui.css dependency.
- **`.n` (DT action-cell param/class + `$(…).closest('.n')` row-click guards)** — styled by
  per-screen scoped selectors (`#…-screen .n`) + app.css `.n .btn`; **not** ui.css. No contract
  rename. (Note: `clients/index` columnDefs targets col 5 as `actions-col` yet its `<th>` and
  row-guard use `.n` — pre-existing mismatch, presentation-only, recorded not acted on.)
- **`.client-cell*`** (DT render, clients) — app.css-owned; **not** ui.css.
- **`.is-invalid`/`.is-open`/`.is-active`/`.filled`/`.hover-bg`/`.p-1`/`.toast`** —
  app.css-owned or per-screen scoped; not ui.css contracts.

**Step 5 — DataTables integration audit.** 12 views load the 1.13.6 CDN scripts
(`jquery.dataTables.min.js` + `dataTables.bootstrap5.min.js`; jQuery 3.7.1) and `datatables.css`.
`datatables.css` §B chrome is **self-contained** for the generated markup: B1 tables
(`.dataTables_wrapper .pagination table.dataTable`, `.table-sm`, `.align-middle` scoped),
B3 pagination (`.pagination/.page-item/.page-link` incl. focus ring + navy active), plus the
pre-B layout/chrome rules — all referenced via project tokens (`var(--color-navy)`,
`var(--shadow-focus)`, no `--bs-*` beyond the inert `:root[data-bs-theme=dark]` block).
**One cross-file dependency (Category 2):** B2's explicit note that DT filter/length input
**focus** states are supplied by ui.css classes `.form-control:focus` / `.form-select:focus`
(`datatables.css:477-480`). Deletion of `.form-control:focus`/`.form-select:focus` without
re-owning them in app.css or datatables.css degrades the DT search input + length select focus
ring on all 10 DT screens. The `dataTables.bootstrap5.min.js` generated controls also carry
`.form-control.form-control-sm` / `.form-select.form-select-sm` (B2 provides metrics; base +
focus remain ui.css §4.2). DT tables' base `.table` family is double-covered (scoped B1 rules
out-specify unscoped ui.css §4.6) → **DT chrome is NOT a ui.css deletion blocker except the B2
focus seam**; the **plain (non-DT) tables remain ui.css-dependent** (see Step 6).

**Step 6 — app.css / Tailwind bundle ownership (measured in `app-ByXmc8gJ.css`).**
Bundle **EMITS** (native Tailwind utilities, allowlist-sourced): `.align-middle`
(`vertical-align:middle` — value-identical), `.ms-1`, `.mt-4` (spacing ladder), `text-center`
(identical), and — a **phantom** — `.w-100` (`width:calc(var(--spacing)*100)` = 400px) emitted
only because app.css's own coexistence-rule header comment literally spells `w-100`; there is
**0 real consumer** (drift-trap only, matches the §6.4 hazard the comment warns about).
Bundle **does NOT emit** (0 occurrences): `.d-none`, `.show`, `.collapsed`, `.dropdown-menu`,
`.accordion*`, `.list-group*`, `.row`, `.col-*`, `.text-muted/-danger/-success/-uppercase/
-nowrap/-end`, `.form-control/-select/-check*`, `.input-group*`, `.btn-close`, `.alert*`,
`.btn-outline-primary`, `.page-link`, `.table-sm`, `.table-responsive`, `.small`,
`.font-weight-bold`, `.me-2`. Every one of these that has a Blade/JS consumer is a **genuine
ui.css dependency**; plain-table consumers of `.table`/`.table-sm`/`.align-middle` (6 plain
tables: 4 permission matrices, sessions/online, households/`members-table`) rely on ui.css §4.6
unscoped rules (`.align-middle` fine via native utility; `.table`/`.table-sm` base + borders
still needed).

**Step 7 — classification matrix (final).**

| Class/Family | Category | Consumers | Blocker to delete ui.css §N |
|---|---|---|---|
| `.d-none` (+ `.toggleClass` hooks) | 3 behavioral | 21 static, 74 total refs, 10+ files | §4.9 — YES (bridge via `hidden`/`@utility`) |
| `.show` (dropdowns) | 3 Alpine x-bind | 3 menus (L3 refs) | §4.7 — YES (rename = break) |
| `.show` (toasts) | 3 app marker | 3 classList | none (§ has no rule) — NO |
| `.collapsed` (accordion) | 3 Alpine x-bind | 1 (clients/_gip) | §4.8 — YES |
| `.accordion*` | 1 component | 1 file | §4.8 — YES (single consumer) |
| `.btn-close` (+white) | 1 + 3 JS-created | 15 | §4.3 — YES |
| `.alert*` | 1 + 3 JS selector | 17 + 2 selector | §4.5 — YES (M4.5 RETAIN) |
| `list-group*` | 1 + 3 classList | 5 files | §4.8 — YES |
| `.table`/`.table-sm` (plain) | 1 component | 6 plain tables | §4.6 — YES (DT side covered by B1) |
| `.table-responsive` | 1 component | 1 (transactions:130) | §4.6 — YES |
| `.row`/`.col-sm-*` grid | 1 component | grantee ×2, dl-row modals ×2 | §4.9 — YES (M4.3 RETAIN) |
| `.text-muted/-danger/…` | 1 utility | JS templates + static | §4.9 — YES |
| `.btn*`, `.form-*`, `.input-group*` | 1 component | 31 files | §4.1/4.2 — YES (M4.2/M4.4 RETAIN) |
| `.dropdown-*`/`.page-link` | 1/1 | 3 menus + DT | §4.7 — YES (dropdowns; DT covered) |
| `--ui-*` tokens | 5 dead/self-ref | 0 | §1 — NO (M1 gate) |
| `.ui-page-header*` | 5 dead | 0 | §5 — NO |
| `.ui-micro-label` | 4 mirrored | — | app.css:713 owns — NO |
| §3 a11y, §4.10 Reboot/type | 4 mirrored | — | M2/M3 ports — NO |
| §5 status/metric/data-card etc. | 4 mirrored | — | app.css owns — NO |
| `actions-col` | 3 app-owned | 3 DT | — NO (app.css:1118) |
| `datatables.css` B2 focus seam | 2 cross-file | 10 DT screens | fold into M5.2 — YES (prereq) |

**Step 8 — refined M5 roadmap (M5.1 output, supersedes the old one-row M5).**
- **M5.2 prerequisite (tidy):** port DT filter/length **focus** rules (`.form-control:focus`/
  `.form-select:focus` equivalents) into `datatables.css` B2 (removes the Category-2 seam) and
  remove the phantom `.w-100` spelling from the app.css header comment (stops the drift-trap
  utility). Small, isolated.
- **M5.3 `.d-none` bridge:** prefer name-swap to Tailwind `hidden` (same `!important` display
  none; click-toggle JS lines unchanged) — 21 static edits + JS strings; else `@utility d-none`.
- **M5.4 dropdown `.show`:** Tailwind conditional in `app.css` (e.g., `.dropdown-menu.show`
  `@apply block`) or Alpine `x-show`; remove caret/menu rules from §4.7 after.
- **M5.5 accordion**: convert `clients/_gip` to the `x-collapse` visual already present
  (class-free) or port §4.8 accordion rules to app.css; then trim.
- **M5.6 `.btn-close`:** add `@utility btn-close` (metrics+bg SVG) to app.css; swap 13 static;
  JS-created `el.innerHTML` templates pick it up automatically.
- **M5.7 resean utilities/grid/text:** after `@source` grows, re-check `.text-muted` →
  `text-ink-muted`, `.text-danger` → `text-red`, spacing 3–5 padding, and the plain `.table`
  base before trimming §4.6/§4.9.
- Each sub-step: same per-family gate (inventory, parity probe, no sibling-family migration).

**Verification (Steps 11–13):** read-only audit — `git status` identical to the M1–M4 working
set (no app file touched by M5.1); CSS/JS/build hashes unchanged vs Step 1; PHPUnit baseline
**301 / 1419 / 0** (no PHP changed, suite state identical); Pint untouched. No scratch files
left. `ui.css` retained + linked; Preflight OFF; DataTables CDN + skin intact; no M5.2+ work;
no M6.

**Known limitations:** auth-gated screens cannot be browser-verified without an admin account
(no local account, DB untouched) — the 3 dropdowns/accordion/`.btn-close` dismissal flows are
verified by static/Alpine contract inspection, as in M4.x. The `.n` vs `actions-col` mismatch on
`clients/index` is recorded, not "fixed".

**Tests:** no PHP/CSS/JS changed; suite **301 / 1419 / 0**. Docs updated: this entry,
`UI_CSS_RETIREMENT_PLAN.md` (Status + M5 note — M5.1 audit LANDED, roadmap refined, M5.2+ pending),
`SESSION_HANDOFF.md` (header + item 14 + documentation table).

---

### 2026-09-08 - M5.2: DataTables B2 focus seam ownership + phantom `.w-100` cleanup (retirement plan M5.2 — APPLIED)

**Scope:** `public/css/datatables.css` (B2 focus seam ownership), the responsible comment text in
`resources/views/scholars/_form.blade.php:11` (phantom `.w-100` emitter), and this documentation
set: `IMPLEMENTATION_LOG.md`, `UI_CSS_RETIREMENT_PLAN.md`, `SESSION_HANDOFF.md`. NOT touched:
`ui.css` (byte-identical, hash `ABB10A4B…A92E44`), `app.css` (byte-identical, hash
`2E257D09…62A861`, net delta 0), DataTables JS/CDN, all Blade markup (one comment text only),
PHP/DB, Preflight. Prohibited M5.3–M5.7 / M6 **not started**.

**Step 1 — baseline.** `git status` = the M1–M4 working set (no M5.2 additions yet);
hashes recorded: `ui.css` `ABB10A4BD2E0FDF111B5E996824F0F0C7AE69F48FC22891D1BB7E9B498A92E44`,
`datatables.css` `4BED7A2C0C8E64564B01891143EB199DC04AF73F5ED0F3E8BA71829EDDDF4F9F` (snapshot before
edit), `app.css` `2E257D09E03B49E06D2195FDBF4F4D640E4226A0A4D0BB3490A379957C62A861`; build
`app-ByXmc8gJ.css` (59,634 B) + `app-DqsLDVL_.js` (105,701 B); PHPUnit expectation 301/1419/0.

**Step 2 — B2 seam definition (what is actually delegated).** `datatables.css:477-480` explicitly
hands the DT filter/length focus state to ui.css `.form-control:focus` / `.form-select:focus`.
Cascade analysis: the B2 base rules `.dataTables_wrapper .dataTables_filter input` and
`.dataTables_wrapper .dataTables_length select` (specificity 0,2,1) already own
`color:#212529` / `background-color:#fff` / `border-color:#dee2e6` and out-specify the ui.css
focus rules (0,2,0). Therefore the **only** declarations actually resolved from ui.css on a
focused DT control are `outline: 0` and the `box-shadow` focus ring — that is the exact seam
migrated.

**Step 3 — changes made.**

1. `public/css/datatables.css`:
   - Replaced the 4-line B2 delegation comment with an 8-line ownership comment (seam now
     closed in datatables.css; mirrors ui.css `.form-control:focus`/`.form-select:focus`).
   - Added immediately after the select base rule:
     ```css
     .dataTables_wrapper .dataTables_filter input:focus,
     .dataTables_wrapper .dataTables_length select:focus {
         outline: 0;                      /* ui.css .form-control/.form-select:focus */
         box-shadow: var(--shadow-focus); /* canonical @theme token (was --ui-focus-ring) */
     }
     ```
     Deliberately **not** re-declaring `color`/`background`/`border-color` on `:focus`: the B2
     base rules already own them and re-declaring would drift the focused border from `#dee2e6`
     to navy `#164A9C`. Ring token re-pointed at `var(--shadow-focus)` (the canonical `@theme`
     token, `0 0 0 .2rem #fcd1164d` — same ring B3 uses), replacing the legacy `--ui-focus-ring`.
   - New hash: `B82937375133CABD50C1AD7BB590FE124D9D9E9964A106CA65D19B65ABB326AE` (594 lines).
     Verified by strict byte-level reconstruction: OLD snapshot + intended patch == current file,
     zero unintended drift.
2. `resources/views/scholars/_form.blade.php:11` (phantom `.w-100` emitter — attribution
   corrected from M5.1): the Blade comment contained the literal token `w-100`; the file is on
   the app.css `@source` allowlist (`resources/css/app.css:131`), so Tailwind's candidate
   scanner emitted `.w-100{width:calc(var(--spacing)*100)}` from the comment text. The comment
   now reads "instead of the forbidden full-width Bootstrap utility". Only line 11 changed;
   indentation identical.
   - M5.1's attribution (app.css coexistence comment) was **wrong**: editing app.css's comment
     produced a byte-identical bundle (app.css is not scanned by Tailwind — only `@source` files
     are). The app.css comment was left untouched.

**Step 4 — build & bundle verification.** Rebuild produced `app-DjX_TTSN.css` (59,594 B) +
`app-DqsLDVL_.js` (105,701 B, unchanged); old `app-ByXmc8gJ.css` removed (emptyOutDir);
`manifest.json` updated. Bundle checks: `.w-100` count **0**; real utilities intact — `.p-4` 1,
`.gap-3` 1, `.w-full` 1, `.mt-4` 1, `.ms-1` 1, `.align-middle` 1, `text-center` present;
`--shadow-focus:0 0 0 .2rem #fcd1164d` present in the bundle (resolves to
`rgba(252,209,22,.3) 0 0 0 2.8px`). Repo-wide `rg '\bw-100\b'`: only the app.css comment
(proven unscanned; left as-is) and a `-100` color-name substring inside the welcome page's
pre-compiled inline Tailwind (static, not in `@source`, not part of the app build) — **0
legitimate consumers**.

**Step 5 — tooling incident (byte drift) & fix.** After the rebuild, `app.css` hash drifted to
`0917…` while `ui.css` matched its baseline. Root cause: the edit tool stripped leading
whitespace on the replaced comment lines (baseline indent is 5 spaces on lines 20–24; the tool
wrote the patch's lines verbatim). Because only 5 leading spaces were lost on 5 lines and the
hash was 128-bit, the mismatch looked unrelated (outcome lacks Provenance). Fixed with a byte-
precise Node restore (`restore.js` under `%TEMP%\opencode\m52`); hash re-verified to
`2E257D09…` before writing. Lesson: after any in-place edit of a hashed artifact, re-verify the
whole-file hash; prefer scripted byte writes for byte-precise files; use
`cmd /c "git show HEAD:path > file"` for raw bytes (PowerShell `Out-String` corrupts line
endings).

**Step 6 — non-browser checks.** `php artisan view:cache` OK ("Blade templates cached");
`vendor\bin\pint --test --dirty` passed (only the harmless "Module openssl is already loaded"
warning). PHPUnit: first run failed 297/4 (PATH missing `C:\xampp\mysql\bin` — RefreshDatabase
cannot find `mysql`); with the PATH fix: **301 passed / 1,419 assertions / 0 failures**.

**Step 7 — browser parity verification (Playwright harness, scratch under
`%TEMP%\opencode\m52`, outside the repo).** To cover the auth-gated DT screens, built
`harness-old.html` (links the pre-edit `datatables-OLD.css`) and `harness-new.html`
(`datatables.css`), both mirroring the layout CSS order (app bundle → ui.css → datatables.css)
and the `dataTables.bootstrap5.min.js` output markup (`.dataTables_wrapper` + filter
`input.form-control.form-control-sm` + length `select.form-select.form-select-sm`, plus plain
non-DT `.form-control`/`.form-select` controls). `parity.js` computes 22 style props ×
filter/length/plain × unfocused+focused at 375/768/1280 and diffs OLD vs NEW.
- **Drift: 0.** Across every element × prop × state × width the computed styles are identical.
- Focused DT filter @1280 (NEW): `box-shadow rgba(252,209,22,0.3) 0px 0px 0px 2.8px`,
  `outline 0px`, `border-top-color rgb(222,226,230)` (B2 base wins — no `#164A9C` drift),
  `background-color rgb(255,255,255)`, `color rgb(33,37,41)`; length select identical old vs new.
- 0 console errors, 0 page errors; no horizontal overflow (`scrollWidth == innerWidth` at every
  width). Plain non-DT controls unchanged (all focus styling still from ui.css).

**Verification (Steps 8–9):** `git status` shows exactly the M1–M4 working set + these M5.2
changes: modified `public/css/datatables.css`, modified `resources/views/scholars/_form.blade.php`
(1 line), the 3 docs. `app.css`/`ui.css` hash-identical to baseline. No scratch files left in
the repo.

**Known limitations:** auth-gated DataTables screens can't be live-probed without an admin
account (DB untouched); computed-style parity was validated via the markup/CSS-mirror harness
(M5.2 closure proof), as carried forward from M4.x/M5.1.

**Tests:** build green; `view:cache` OK; Pint passed; suite **301 / 1419 / 0**. Docs updated:
this entry, `UI_CSS_RETIREMENT_PLAN.md` (Status + §M5 M5.2 LANDED + corrected phantom-source
attribution + sub-roadmap renumbered M5.3+), `SESSION_HANDOFF.md` (header + item 14 +
documentation table).

---

### 2026-09-08 - M5.3: `.d-none` behavioral migration (retirement plan M5.3 — APPLIED)

**Scope:** `.d-none` consumers only + `public/css/ui.css` (removal of the single `.d-none` rule)
+ this documentation set (`IMPLEMENTATION_LOG.md`, `UI_CSS_RETIREMENT_PLAN.md`,
`SESSION_HANDOFF.md`). NOT touched: `.show`/dropdowns, `.collapsed`/accordion, `.btn-close`,
`.alert` rules, `.table*`, `.row`/`.col-*`, `.text-*`, `.btn*`, `.form-*`, `.input-group*`,
DataTables, jQuery/Alpine architecture, Preflight, DB/schema, PHP/backend, app.css, JS files
(no external JS touched — all runtime logic is inline Blade), layout/global cleanup, M5.4+,
M6. Nothing committed/staged.

**Step 1 — baseline.** ui.css `ABB10A4B…A92E44`, app.css `2E257D09…62A861`, datatables.css
`B82937…326AE` (M5.2, 595 lines), build `app-DjX_TTSN.css` + `app-DqsLDVL_.js`, PHPUnit
expectation 301/1419/0. Git working tree = M1–M4 set + M5.2 set (datatables.css, scholars/_form
comment).

**Step 2 — full `.d-none` inventory (measure): 76 matches** across `resources/` + `public/`
(excluding build/vendor/docs). Classification:
- 1 CSS owner rule: `public/css/ui.css:1057` `.d-none { display: none !important; }`.
- **17 static (markup) consumers**: `family_members/create:62`, `households/create:75`,
  `clients/_form:264` (conditional Blade `@if`), `grantee_update/_self_update_tab:13,17,43`,
  `grantee_update/self-service:57,61,87`, `qr/viewer:70,88`, `unpaid_verifications/
  self-service:58,76,85,109` (`.alert alert-success d-none …`), `students/photo-upload:86`
  (`img … d-none`), `students/photo-upload:91`.
- **55 runtime (behavioral) operations** across 9 files: `classList.add('d-none')` ×22,
  `classList.remove('d-none')` ×31, and jQuery **two-arg** `toggleClass('d-none', boolean)` ×2
  (`transactions/index:281-282`, inline-edit row buttons). All are on PRE-EXISTING elements;
  no `d-none` creation via template literals or `innerHTML` exists anywhere.
- **3 inert comments** (explicitly NOT consumers): `resources/css/app.css:96`, `clients/_form:17`,
  `clients/_details:6`. No Alpine `:class`/`x-show`, no external JS, no `@stack('styles')`,
  no `style=` display.

**Step 3 — existing contract.** `.d-none { display: none !important; }`: `display:none`,
`!important`, specificity (0,1,0), unlayered, single rule, zero compound selectors, zero state
mechanism, no competing override; the `.d-inline/.d-block/.d-flex/.d-inline-flex/.flex-*` siblings
are independent consumers — untouched.

**Step 4 — Tailwind `hidden` comparison.** The built app bundle **already emits
`.hidden{display:none}`** inside `@layer utilities` (Tailwind v4, **non-important**, layered),
driven by existing consumers (`navbar`, `global-search`). The migration question resolves to
cascade equivalence: layered non-important `.hidden` must beat every rule currently losing to
unlayered-`!important` `.d-none`. Repo-wide audit: **zero dedicated selectors exist for any
consumer** (by id or class; `suggestions-list` has zero CSS; `.alert` sets no display; no
unlayered element-level `display` rule in ui.css/app.css beyond `label{display:inline-block}`).
Therefore no competing `display` declaration exists at any specificity/layer → `.hidden` and
`.d-none` compute identically on every consumer. `!important` was never load-bearing.

**Step 5 — strategy (chosen & justified): native Tailwind `hidden` (Option A).** A full,
one-for-one rename across every static + runtime reference (72 total) plus removal of the ui.css
rule. Rejected the `@utility d-none { display:none !important; }` bridge (Option B): it would
preserve a second "d-none" vocabulary for no behavioral benefit, and the complete lifecycle is
provably covered (exhaustive inventory, zero template-literal/dynamic consumers, all runtime ops
on existing elements), satisfying the "preferred strategy" precondition. Result: no `.d-none`
owner anywhere — final state is **all consumers → hidden**, the plan's terminal goal.

**Step 6 — exact changes (72 swaps + 1 rule removal).** In each of the 9 views the token
`d-none` → `hidden` (17 `class="… d-none"` → `class="… hidden"`; 55 `'d-none'` → `'hidden'`
classList/jQuery args; no class reordering, no unrelated retouching). `public/css/ui.css:1057`
`.d-none { display: none !important; }` deleted (sibling helper rules preserved). No `@source`
change (all 9 consumer files already on the allowlist; `grantee_update/_self_update_tab` is a
partial — not scanned, but its markup is served by `self-service.blade.php` which is scanned, and
`hidden` is emitted by prior consumers regardless).

**Step 7 — build & bundle.** `npm run build` → **byte-identical output** (`app-DjX_TTSN.css`
59,594 B, `app-DqsLDVL_.js` 105,701 B — same hashed filenames). Cause: `.d-none` was never
Tailwind-emitted and `.hidden{display:none}` already existed, so the CSS content is unchanged.
Bundle checks: `.hidden{display:none}` present; `.d-none{` **absent**; `.w-100{` **absent**
(M5.2 phantom still dead). Post-change repo sweep: the only remaining `d-none` substrings are the
two inert comments (`app.css:96`, `_details:6`) — no live reference survives.

**Step 8 — non-browser checks.** `php artisan view:cache` OK. `vendor\bin\pint --test --dirty`
passed (openssl warning noise only). PHPUnit: first run hit 19 connection-refused failures
because `mysqld` restarted mid-run (service came back up; env issue, not code); re-run green:
**301 passed / 1,419 assertions / 0 failures** (baseline identical).

**Step 9 — browser verification (Playwright parity harness, scratch under
`%TEMP%\opencode\m53`, outside the repo).** `before.html` (ui-OLD.css `ABB10A4B…` — with
`.d-none`) vs `after.html` (ui-NEW.css `8D6682…` + app bundle via `app-bundle.css`) — same markup
mirroring the real consumer composites (suggest lists, `#qrContainer`, `.alert` success box,
`img` preview, row action buttons) and same CSS order (app bundle → ui.css) as layouts. Shared
`lifecycle.js` drives: initial hidden → reveal (`remove`) → re-hide (`add`) → ×4 repeated toggle →
jQuery-semantics two-arg force toggle → dynamic insertion of a new `hidden` element.
- **Drift = 0** across 375/768/1280 × 13 elements × 23 props × 6 steps (plus final re-hide check)
  — every computed style identical before vs after.
- Focused probes (AFTER @1280): initial `display:none` / `visibility:visible` / `opacity:1` /
  offset 0×0; revealed `display:block` (successBox 1280×52) and `display:inline` for the `img`;
  re-add → all elements back to `display:none`; ×4 multi-toggle deterministic.
- 0 console errors, 0 page errors, 0 horizontal overflow drift in every step (CSS/LS
  `scrollWidth == innerWidth` parity).

**Step 10 — ui.css ownership after M5.3.** `.d-none` is **absent** from ui.css (hash now
`8D668247143ED9029B3854FA465A66BEE9B9AB40A07325ECCEDAED3A661CF394`). `.d-inline/.d-block/
.d-flex/.d-inline-flex` and the rest of §4.9 remain (independent consumers, future phases). No
FTP-bridge was needed. ui.css still linked; NOT deleted (M6). Remaining ui.css blockers for M5.4+:
`.show` on dropdowns (3 Alpine), `.collapsed` (1 accordion), `.btn-close` (15), `.d-flex/
.d-block/.d-inline*` utilities, `.alert-*` (JS querySelector contract, M4.5-justified retain),
`.table*`/`.row`/`.col-*` (DT + grid contracts), `.btn*`, `.form-*`, `.modal`, `.dropdown`,
plain-table base sets (M5.7).

**Step 11 — M5.2 regression.** `datatables.css` hash unchanged `B82937375133CABD50C1AD7BB590F
E124D9D9E9964A106CA65D19B65ABB326AE`; B2 focus rule present (`input:focus, select:focus {
outline: 0; box-shadow: var(--shadow-focus); }`); `.w-100` still absent. M5.2 intact.

**Known limitations:** auth-gated create/edit screens not live-tested (no admin account; DB
untouched) — lifecycle fidelity proven via the markup/CSS-mirror harness (same standard as
M4.x/M5.1/M5.2) plus `view:cache` Blade compile and suite 301/1419/0.

**Tests:** build green (`app-DjX_TTSN.css`); `view:cache` OK; Pint passed; suite
**301 / 1419 / 0**. Docs updated: this entry, `UI_CSS_RETIREMENT_PLAN.md` (Status + §M5
M5.3 LANDED + sub-roadmap renumbered M5.4+ + §9 checkbox), `SESSION_HANDOFF.md` (header +
item 14 + documentation table). `.d-none` consumers after M5.3: **0 live references**, 2 inert
comments documented as leftovers.

---

### 2026-09-08 - M5.4: dropdown `.show` behavioral migration (retirement plan M5.4 — APPLIED)

**Scope:** dropdown `.show` only + `partials/navbar.blade.php` + the two Export dropdowns
(`clients/index`, `transactions/index`) + `resources/css/app.css` (canonical open-state rule) +
`public/css/ui.css` (removal of `.dropdown-menu.show`) + this documentation set. NOT touched:
toast `.show`/`.toast` (3 runtime `classList.add('show')` in clients views — unchanged),
`.collapsed`/accordion, `.btn-close`, `.alert`, `.d-none`/`hidden`, DataTables, `.btn*`,
`.form-*`, grid/table/utilities, Preflight, DB/backend, jQuery/Alpine architecture. Nothing
committed/staged.

**Step 1 — baseline.** ui.css `8D668247…`, app.css `2E257D09…` (M5.3), datatables.css
`B82937…` (M5.2), build `app-DjX_TTSN.css` + `app-DqsLDVL_.js`, PHPUnit 301/1419/0.
Git working tree = M1–M5.3 set.

**Step 2 — full `.show` inventory (measure).** Repo-wide search (models, comments, JS, CSS):
- **3 Alpine dropdown bindings** — `partials/navbar.blade.php:86`, `clients/index.blade.php:269`,
  `transactions/index.blade.php:98` — all `:class="{ 'show': open }"` on `.dropdown-menu`.
- **3 toast runtime ops (not dropdown, not ui.css deps)** — `clients/index.blade.php:771,795`,
  `clients/_details.blade.php:842` — `classList.add('show')`. No `.toast/.show` or `.show` CSS
  rule for toasts exists (M5.1 established). **Untouched.**
- **1 CSS state rule** — `public/css/ui.css:817-819` `.dropdown-menu.show { display: block; }`.
- **0 JS/CSS toggles in `resources/js`, `public/js`, `datatables.css`.** Plain-Tailwind `show`
  tokens elsewhere are route/verb names (`clients.show`, `.show(options)`), `x-show` modifiers,
  comments, or `households/show` URLs — not the state class.

**Step 3 — existing contract (dropdown CSS, ui.css §4.7).** `.dropdown { position:relative }`;
`.dropdown-toggle { white-space:nowrap }` + `::after` caret; `.dropdown-menu { position:absolute;
z-index:1000; display:none; min-width:10rem; padding:.5rem 0; margin:0; font-size:1rem;
color:#212529; background:#fff; border:1px solid rgba(0,0,0,.175); border-radius:.375rem;
box-shadow:0 .5rem 1rem rgba(0,0,0,.15) }`; `[data-bs-popper]` top:100%/left:0/marg-top .125rem +
end right:0/left:auto; **`.dropdown-menu.show { display:block }`** (unlayered, 0,2,0);
`.dropdown-item` base + hover/focus/active/disabled. All three menus use
`dropdown-menu dropdown-menu-end` WITHOUT `data-bs-popper` → no offsets; menu anchors at its
static position. Measured (real Alpine, AFTER harness): closed `display:none`, 0×0; open
`display:block`, `position:absolute`, top auto→static offset, z-index 1000, min-width 140px,
padding 7px 0, radius 5.25px, shadow `rgba(0,0,0,.15) 0 7px 14px 0`, item `3.5px/14px`, #212529.

**Step 4 — strategy (chosen & justified): B — canonical app-owned state class `dropdown-open`.**
`app.css` gains exactly one narrowly-scoped rule `.dropdown-menu.dropdown-open { display: block; }`
(top-level/unlayered, 0,2,0); the three Alpine `:class` bindings swap `'show'` → `'dropdown-open'`;
ui.css `.dropdown-menu.show` is deleted. Rationale — **rejected A** (plain `:class="{ 'block':
open, 'hidden': !open }"`): utilities here are imported UNLAYERED and ui.css loads AFTER the
bundle, so `.block` (0,1,0) loses the source-order tie to the `.dropdown-menu{display:none}` base
(0,1,0) → the menu could never open; `!block`/`!hidden` important variants work but keep two
always-bound classes and `!important` where none is needed. **Rejected C** (`x-show="open"`):
initialization flash risk, and removing the base `display:none` would be required (inline display
removes on open → falls back to the hidden base); class-based state also preserves the exact
`.dropdown-item`/positioning cascade untouched. B mirrors the old `.show` cascade mechanics
(higher-specificity unlayered rule wins over the 0,1,0 base) using application-owned vocabulary —
**no generic `.show` replacement** and no arbitrary-value stacks.

**Step 5 — exact changes.** (1) `partials/navbar.blade.php:86` — `:class="{ 'show': open }"` →
`:class="{ 'dropdown-open': open }"`. (2) `clients/index.blade.php:269` — `:class="{ \'show\':
open }"` → `:class="{ \'dropdown-open\': open }"`. (3) `transactions/index.blade.php:98` — same.
(4) `resources/css/app.css` (end of file) — new block
`.dropdown-menu.dropdown-open { display: block; }` with an ownership comment (M5.4 token; NOT a
generic `.show`; closed state still owned by the ui.css base rule). (5) `public/css/ui.css` —
`.dropdown-menu.show` rule removed; §4.7 header comment updated to name the `dropdown-open`
contract. Alpine state/events (`x-data="{ open: false }"`, `@click="open = !open"`,
`@click.outside`, `@keydown.escape`, `:aria-expanded`) and all DOM structure/classes untouched.

**Step 6 — build & bundle.** `npm run build` → `app-kffPtn-M.css` (59.64 kB, was 59.59 kB:
**+42 B = the single migration rule**, minified) + `app-DqsLDVL_.js` (unchanged; manifest updated,
`@vite` picks up the new handle). Bundle check: `dropdown-menu.dropdown-open{display:block}`
present; `rg .show{` / `.dropdown-menu.show` → 0; `.hidden{display:none}` present (M5.3 intact);
`.w-100{` absent (M5.2 intact); no phantom `.show` utility.

**Step 7 — non-browser checks.** `php artisan view:cache` OK (compiles the three edited views + app
layout). `vendor\bin\pint --test --dirty` passed (openssl noise only). PHPUnit **301 passed /
1,419 assertions / 0 failures** (53.76s).

**Step 8 — browser verification (real-Alpine Playwright parity harness, scratch
`%TEMP%\opencode\m54`).** `ui-OLD.css` = pre-M5.4 ui.css plus the re-inserted
`.dropdown-menu.show{display:block}` (reconstructs the exact pre-change contract); `ui-NEW.css` =
M5.4 ui.css; `app-NEW/OLD.css` = built bundle (DROP-OLD bundle absent causes no diff — the
`.dropdown-open` rule only matches when the class exists, and before.html never sets it). Two menu
composites mirror production (navbar `.dropdown alert` close — navbar trigger with Tailwind
utilities + `dropdown-menu dropdown-menu-end`; export composite with `.btn-subtle dropdown-toggle`
and two `.dropdown-item`s). Alpine loaded from `node_modules/alpinejs/dist/cdn.min.js`.
- Lifecycle per width 375/768/1280: closed → click trigger (open) → outside-click (close) →
  click trigger (open) → Escape (close) → click export trigger (independent second dropdown
  open) — then computed-style compare before vs after on 26 props per menu + item + trigger +
  aria-expanded + class token + body overflow.
- **Drift = 0** everywhere; class token differs exactly `show`→`dropdown-open` (expected);
  **0 console errors, 0 page errors, 0 overflow drift**; body `scrollWidth == innerWidth` in
  every state.
- Focused AFTER probe (all widths): closed `display:none`, rect 0×0, aria `false`; open
  `display:block`, `position:absolute`, z-index 1000, top static-offset, min-width 140px,
  padding `7px`, radius `5.25px`, shadow `rgba(0,0,0,.15) 0 7px 14px 0`, item `display:block`
  #212529 / transparent, 3.5/14px, nowrap; aria `true`; outside-click and Escape both close
  (`display:none`, aria `false`). Repeat open/close cycles stable.

**Step 9 — ownership after M5.4.** ui.css contains **no `.dropdown-menu.show` and no `.show`
rule** — dropdown state does NOT load in ui.css (`rg \.dropdown-menu\.show\|\.show` → only the
explanatory comment). ui.css hash `2DE83CC8…`; app.css hash `ADA35329…`; datatables.css
`B82937…` unchanged. Genericism: no `.show` rule exists anywhere in CSS; app.css owns the state.
Toast `.show` ops are runtime-only (clients views) with no CSS dependency — confirmed unchanged.

**Step 10 — M5.3/M5.2 regression.** `.d-none` still absent from ui.css; `hidden` canonical;
`datatables.css` B2 focus seam present; bundle `.w-100` absent.

**Known limitations:** actual screens are auth-gated (no admin session; DB untouched), so the
three dropdowns were exercised via the production-mark-up mirror harness with the real Alpine
runtime — same standard as M4/M5.2/M5.3 — plus `view:cache` compile and the 301-test suite.

**Tests:** build green (`app-kffPtn-M.css`); `view:cache` OK; Pint passed; suite
**301 / 1419 / 0**. Docs updated: this entry, `UI_CSS_RETIREMENT_PLAN.md` (Status + §M5
M5.4 LANDED + sub-roadmap renumbered M5.5+ + §9 checkbox), `SESSION_HANDOFF.md` (header +
item 14 + documentation table). Dropdown state consumers now bind `.dropdown-open` (3); toast
`.show` ops (3) unchanged.

### 2026-09-08 - M5.5: accordion `.collapsed` behavioral migration (retirement plan M5.5 — APPLIED)

**Step 1 — inventory.** Repo-wide `collapsed`/`accordion` scan (resources + public, excluding
build/vendor/node_modules): the ONLY live accordion consumer is `resources/views/clients/_gip.blade.php`
(rendered only when `$hasGipTransaction`). `.collapsed` appears in ui.css §4.8 in exactly **three**
state selectors: `.accordion-button:not(.collapsed)`, `.accordion-button:not(.collapsed)::after`,
`.accordion-item:last-of-type .accordion-button.collapsed`. Remaining `collapsed` hits are inert
comments only (app.css:96, layouts/app:10, qr/viewer:14, details-panel `.details-accordion-*` is an
independent app-owned component using `.is-open` — not Bootstrap, not in scope). No `.collapsed`
reference exists in `resources/js`, `public/js`, or `datatables.css`. Repo blocklist consumers: none.

**Step 2 — exact contract (before).** Alpine state: `x-data="gipModal()"`, `accordionOpen: false`
(closed initial). Binding: `:class="{ 'collapsed': !accordionOpen }"` — inverted Bootstrap predicate
(class PRESENT when closed). `@click="accordionOpen = !accordionOpen"`. `:aria-expanded="accordionOpen
.toString()"`, `aria-controls="collapseGIP"`, `aria-labelledby` on the panel. **Visibility is NOT
`.collapsed`-owned**: the panel is `<div id="collapseGIP" class="accordion-collapse" x-show="accordionOpen"
x-collapse …>` — Alpine `x-show` + the bundled `@alpinejs/collapse` plugin (250 ms height transition)
handle show/hide entirely. So `.collapsed` controls only **visual state**:
1. `.accordion-button:not(.collapsed)` — open color `#084298`, open background `#cfe2ff`, inset bottom
   border-shadow (1px `#dee2e6`), via the `--ui-accordion-*` vars.
2. `.accordion-button:not(.collapsed)::after` — open chevron: blue active-icon (data URI) + `rotate(-180deg)`,
   transitioning from the base dark chevron (transform `none`).
3. `.accordion-item:last-of-type .accordion-button.collapsed` — closed last-item corner: bottom radius
   `calc(.375rem - 1px)` (the closed button is the last painted surface); when open, the panel absorbs it
   via `.accordion-item:last-of-type .accordion-collapse { border-radius: .375rem }`.
Pseudo-elements: `::after` chevron (SVG data-URI, 1.25rem box, margin-left auto, flex-shrink 0,
background-size 1.25rem, `transform .2s ease-in-out`); `::before` empty. Base `.accordion` item/button/
collapse/body formation (borders, padding 1rem/1.25rem, radius .375rem, transitions) stays in ui.css §4.8
— only the three state rules migrate.

**Step 3 — chosen strategy (canonical app-owned state class, no inversion).** `accordionOpen` is a
boolean "open"; the least-inverting, simplest token is **`.accordion-open`** present when open:
`:class="{ 'accordion-open': accordionOpen }"`. Selectors rewritten in app.css:
- `.accordion .accordion-button.accordion-open` — open color/background/inset shadow (uses the SAME
  `--ui-accordion-*` vars so values stay byte-faithful; the token block stays in ui.css §4.8).
- `.accordion .accordion-button.accordion-open::after` — active-icon + `rotate(-180deg)`.
- `.accordion .accordion-item:last-of-type .accordion-button:not(.accordion-open)` — closed corner
  (predicate flips: closed = class absent).
**Critical cascade decision:** rules are prefixed `.accordion …` raising specificity to 0,3,0 so the
open-state inset box-shadow beats ui.css `.accordion-button:focus` (0,2,0). Today's ui.css order puts
`:focus` BEFORE `:not(.collapsed)`, so at equal specificity the later open-state inset wins over the
focus ring on an open+focused button. Because the app bundle loads BEFORE ui.css, an unprefixed
`.accordion-button.accordion-open` (0,2,0) would LOSE the source-order tie to the later-loading
ui.css `:focus` (0,2,0) → the open+focused button would show the focus ring instead of the inset
shadow (visual flip). The `.accordion` prefix restores the exact old tie-break.
Rejected: plain `hidden`/`x-show` swaps (`.collapsed` is NOT a visibility mechanism — visibility is
`x-show`+`x-collapse`); a `.accordion-collapsed` token (would require an inverted binding + `:not()`
everywhere); `@utility` bridge (unnecessary — no static/tool consumers, single Alpine consumer).

**Step 4 — exact changes.**
- `resources/views/clients/_gip.blade.php:14` — `:class="{ 'collapsed': !accordionOpen }"` →
  `:class="{ 'accordion-open': accordionOpen }"`. Alpine state/@click/aria/x-show/x-collapse/events all
  untouched.
- `resources/css/app.css` — one new unlayered top-level block (after the M5.4 dropdown rule, end of
  file) with the three rules above, referencing the ui.css `--ui-accordion-*` vars + an ownership
  comment documenting the state flip, the `:focus` specificity rationale, and the corner predicate.
- `public/css/ui.css` — removed the three `.collapsed` selectors (`.accordion-button:not(.collapsed)`,
  `.accordion-button:not(.collapsed)::after`, `.accordion-item:last-of-type .accordion-button.collapsed`);
  §4.8 comment updated ("Alpine owns open/close + collapsed-state via the app.css `.accordion-open`
  token, this retains base styling only"). ALL base `.accordion(.item/.button/.header/.collapse/.body)`
  rules and the `--ui-accordion-*` token block RETAINED (legitimate base formation, not `.collapsed`-
  dependent). Hash `2DE83CC8…` → `05B6D4BC…`; app.css `ADA35329…` → `574AD8EA…` (recomputed).

**Step 5 — static contract audit.** `rg '\.collapsed|collapsed' resources public` → only 3 inert
comment hits (ui.css:872 — "collapsed-state via the app.css `.accordion-open` token", app.css:1142/1149 —
documentation inside the ownership comment). **No live accordion `.collapsed` consumer. No `.collapsed`
selector in ui.css.** `accordion-open` = exactly 1 view binding + 3 app.css rules.

**Step 6 — build + bundle verification.** `npm run build` → `app-DjDvJDSm.css` (60.23 kB, **+595 B** =
the three rules). Bundle grep: all 3 `.accordion … accordion-open` rules present; `rg '\.collapsed\{|
\.collapsed\.|:not\(\.collapsed\)'` → 0; `.hidden{display:none}` present (M5.3 intact); `.w-100` absent
(M5.2); `.show{` absent (M5.4); `.dropdown-open` present. JS unchanged; `manifest.json` points to the new
css.

**Step 7 — parity (real Alpine + @alpinejs/collapse, `%TEMP%\opencode\m55`).** Off-repo mirror harness of
the `_gip` accordion markup (button `accordion-button font-bold` + 16-row `dl grid` inside
`accordion-body p-[1rem]`). `before.html` = reconstructed pre-M5.5 contract: current ui.css + re-appended
retired `.collapsed`/`:not(.collapsed)` rules AND the built bundle MINUS the three `accordion-open` rules
(stripped from `app-DjDvJDSm.css` → `app-bundle-BEFORE.css`) — a faithful old bundle is essential
(without stripping, the new `:not(.accordion-open)` corner rule matches the old class set and masks the
old contract; caught and fixed during verification). `after.html` = current ui.css + current bundle.
Both load real `alpinejs/dist/cdn.min.js` + `@alpinejs/collapse/dist/cdn.min.js` (plugin self-registers
via `alpine:init`; production bundles it in `app-*.js`). Lifecycle @375/768/1280: initial closed → click
open → click closed → Enter open → Space closed → click open → `focus` open+focused (box-shadow
tie-proof) → Enter closed (5 full cycles). ~30 computed props + bbox + `aria-expanded`/`aria-controls`/
class + panel display/height + page overflow collected per state. **Result: drift 0, 0 console errors,
0 page errors, no overflow** on every state × width; the only token delta is the expected class swap
`collapsed` ↔ `accordion-open`. Focused open probe: box-shadow `rgb(222,226,230) 0px -1px 0px 0px inset`
(BEFORE==AFTER — inset beats the focus ring on both, so the `.accordion` specificity scope reproduces
the old tie-break exactly); open chevron transform `matrix(-1,0,0,-1,0,0)` + active icon byte-identical;
closed chevron `none`; item/button corners 4.25/5.25 px identical in closed and open.

**Step 8 — accessibility.** `aria-expanded` toggles `false⇄true` identically (Alpine `:aria-expanded`),
`aria-controls="collapseGIP"`/`aria-labelledby` present, button semantics unchanged; keyboard: Enter AND
Space toggle the accordion in both harnesses (native button activation → Alpine `@click`); focus remains
on the trigger; no focus-order change (markup untouched).

**Step 9 — regression.** `php artisan view:cache` OK; `vendor\bin\pint --test --dirty` passed; suite
**301 / 1419 / 0** (PHPUnit, PATH incl. `C:\xampp\mysql\bin`). `git diff --check` clean. **Previous-migration
guards:** M5.2 — datatables.css hash `B82937…` unchanged, bundle `.w-100` absent; M5.3 — `.d-none` absent
from ui.css, `hidden` canonical, bundle `.hidden` present; M5.4 — dropdowns still bind `.dropdown-open`
(3 views), no `.show{`/`.dropdown-menu.show` in ui.css or bundle, toast `.show` ops (3) untouched.
Nothing staged (`git diff --cached` empty); no commits.

**Step 10 — ownership after M5.5.** ui.css contains **no `.collapsed`/`:not(.collapsed)` selector and no
live accordion collapsed-state dependency**. Accordion responsibilities remaining in ui.css §4.8
(legitimate base, `.collapsed`-independent): `.accordion` `--ui-accordion-*` tokens, `.accordion-button`
base (+`:focus` ring/border), `.accordion-button::after` default chevron, `.accordion-header`,
`.accordion-item` radii, `.accordion-collapse`/`.accordion-body`. These stay until M6. **Final
`.collapsed` inventory: 1 live consumer → 0 live consumers (after), 3 state selectors → 0 selectors**,
remaining = 3 inert comment mentions. **Remaining ui.css blockers for M5.6+:** `.btn-close` (15 incl. 2
JS-created), `list-group-item` classList ×4, `.alert` querySelector, `.d-flex/.d-block/.d-inline*`
utilities, `.table*`/grid/text re-scan (M5.7), `.btn*`/`.form-*`/`.modal` base. **M5.6 NOT STARTED.
M5.7 NOT STARTED. M6 NOT STARTED.** ui.css retained and linked.

**Tests:** build `app-DjDvJDSm.css`; view:cache OK; Pint passed; suite **301 / 1419 / 0**. Docs updated:
this entry, `UI_CSS_RETIREMENT_PLAN.md` (Status + §M5 M5.5 LANDED + sub-roadmap renumbered M5.6+ + §9
checkbox), `SESSION_HANDOFF.md` (header + item 14 + documentation table). Accordion state now binds
`.accordion-open` (1 view); `.collapsed` live consumers 0.

## 2026-09-08 — M5.6 `.btn-close` ownership & behavioral migration (2DMIS v2)

**Objective.** Remove the live `.btn-close` self-ownership from `public/css/ui.css` (§4.3) into the
canonical `resources/css/app.css`, preserving close-button behavior, appearance, accessibility,
the two JS-created-button contracts, and responsive layout **exactly**. Ownership migration, not a
redesign.

**Inventory (re-audited, supersedes M5.1's "15 incl. 2 JS-created" count — current tree has 14 consumer
lines: 12 static + 2 JS-created):**
- Static (12): `family_members/create.blade.php:52`, `duplicates/index.blade.php:77+88`,
  `households/create.blade.php:68`, `admin/users/index.blade.php:74+81`, `households/index.blade.php:97`,
  `transactions/index.blade.php:122`, `layouts/app.blade.php:92` (flash toast) + `:105`
  (`.alert-dismissible` validation alert), `clients/index.blade.php:290` (modal-header pull-in),
  `partials/sidebar.blade.php:50` (**`.btn-close-white`** companion, mobile sidebar, `ms-auto lg:hidden`).
  All are `<button type="button" class="btn-close[ shrink-0]" @click="…open=false" aria-label="Close">`
  — Alpine-click dismissal, no external JS listener.
- JS-created (2): `clients/index.blade.php:769` (`showToast`) and `clients/_details.blade.php:840`
  (feedback toast) both build `'<button type="button" class="btn-close shrink-0" aria-label="Close"></button>'`
  inside an `innerHTML` composite, then wire dismissal via
  `el.querySelector('button[aria-label="Close"]').addEventListener('click', … el.remove())`.
  Recreated deterministically (toasts are ephemeral by contract).

**JS selector audit:** NO JS selector targets the `.btn-close` class. DetailsPanel wires the details
panel close by ID (`#detailsClose`); both toast paths target `button[aria-label="Close"]`. Therefore
the literal class is NOT a behavioral contract — it could be renamed freely, but retaining it means
all 16 consumers (incl. both dynamically generated buttons) stay byte-identical with zero markup/JS
churn. **Class RETAINED.**

**Existing contract (ui.css §4.3, retired verbatim):** `.btn-close` — `box-sizing:content-box`,
`width/height:1em`, `padding:.25em`, `color:#000`, background `transparent url("data:image/svg+xml,…16×16…")
center/1em auto no-repeat` (Bootstrap 5.3.2 chevron ×data-URI), `border:0`, `border-radius:.375rem`,
`opacity:.5`; `:hover` → `opacity:.75` (color #000, text-decoration none); `:focus` → `outline:0`,
`box-shadow:0 0 0 .25rem rgba(13,110,253,.25)`, `opacity:1`; `.disabled, :disabled` →
`pointer-events:none; user-select:none; opacity:.25`; `.btn-close-white` → `filter:invert(1)
grayscale(100%) brightness(200%)`. `.alert-dismissible .btn-close` (§4.5, alert-family child
positioning) is NOT part of the self contract.

**Strategy & why (supersedes the pre-M5.6 plan's `@utility btn-close` + 13 static swaps):**
- Plain top-level/unlayered rules in `app.css`, **not** `@utility`. Tailwind v4 `@utility` registers
  into the utilities **layer**; unlayered ui.css siblings always outrank layered rules on cascade
  priority independent of specificity, whereas plain app.css rules keep the exact source-order-plus-
  specificity conflict resolution the old ui.css block had. `@utility` would also gate generation on
  utility-candidate scanning; plain rules are unconditional, matching the "one true list" philosophy
  used for `.dropdown-open`/`.accordion-open`.
- Class name retained (no `app-close` rename): no JS selector binds it, so a rename adds 16-markup
  churn + a `.btn-close-white` companion rename + risk of missing a future instance for zero
  behavioral gain. Principle honored: *remove the dependency on ui.css, not the established
  behavioral contract*.
- `.btn-close-white` migrates WITH `.btn-close` — it is a companion modifier of the same glyph
  (1 consumer: the mobile sidebar), same ownership dependency; not independent.
- `.alert-dismissible .btn-close` REMAINS in ui.css §4.5 with its `.alert` parent (alerts stay
  ui.css-owned per M5.6 HARD SCOPE; that rule is the alert's child-position contract alongside
  `.alert-dismissible{padding-right:3rem}`).

**Cascade proof.** Bundle (app.css `.btn-close`, 0,1,0) loads BEFORE ui.css. The only ui.css rule
that later touches `.btn-close` is `.alert-dismissible .btn-close` (0,3,0 position/padding) — it
overrode the same `padding`.25em` pre-migration too, so nothing flips. No other ui.css rule competes
with the self contract (`.btn-close:focus` box-shadow, `:hover`, disabled states all unopposed).
Tailwind Preflight's button reset is beaten by the unlayered app.css rule exactly as it was by ui.css.

**Files changed:** `resources/css/app.css` (+6 rules + M5.6 comment in the M5-dropdown/accordion block
area; hash `574AD8EA…` → `EC35FA3A…`), `public/css/ui.css` (§4.3 6 rules REMOVED + comment reworded;
hash `05B6D4BC…` → `36CCD82D…`), `public/build/assets/app-BZH684Eh.css` (new bundle, 61.01 kB,
+779 B; JS bundle `app-DqsLDVL_.js` unchanged). **No blade/JS file changed** (class retained).

**Parity harness (`%TEMP%\opencode\m56`).** before = current ui.css + re-appended retired §4.3 block
+ new bundle MINUS the six contiguous minified `.btn-close*` rules (61008 → 60229 B); after = current
ui.css + new bundle. Six static contexts (plain, modal-hdr flex `shrink-0`, `.alert-dismissible`,
`.sidebar` `btn-close-white`, flash-toast flex, disabled clone) + JS-created toasts (replica of the
exact `showToast` `innerHTML` snippet) with click-remove + repeated creation. Lifecycle per viewport:
idle → hover (each reachable) → focus(alert) → focus(modal) → create→click-remove→recreate →
alert-click-close → Enter-activation → Space-activation. ~60 computed props × `::before`/`::after`
+ bbox per button. **Result @375/768/1280: 0 drift, 0 console errors, 0 page errors, no overflow.**
Pinned: idle 14×14, content-box, pad 3.5px, border 0, radius 5.25px, opacity .5, glyph SVG data URI
(idle→hover `.75` →focus ring `rgba(13,110,253,.25) 0 0 0 3.5px` + op 1); `.alert-dismissible` idle
pad 17.5px/14px + `position:absolute; top:0; right:0` (ui.css alert contract intact);
`.btn-close-white` `filter:invert(1) grayscale(1) brightness(2)` — all BEFORE==AFTER.

**Static validation.** `rg '\.btn-close' public/css/ui.css` → §4.3 note + `.alert-dismissible .btn-close`
(alert family, by design) only; `btn-close-white` → 0 rules in ui.css. Bundle contains the six
rules. `rg` across `resources/js`+`public/js` for `.btn-close` selectors → 0. Consumer text in
blades: 16 lines, all classified (12 static + 2 JS creation + M5 comment refs in app.css/ui.css).

**Guards.** M5.2 datatables.css `B82937…` unchanged; `.w-100` absent from bundle. M5.3 no `.d-none`
in ui.css/"hidden" canonical. M5.4 `.dropdown-open` present on all 3 dropdowns; no
`.dropdown-menu.show`; toast `.show` ops untouched. M5.5 `.accordion-open` in `_gip`; no
`.collapsed` selectors. All verified by `rg` + hashes.

**Tests:** build `app-BZH684Eh.css`; `view:cache` OK; Pint passed; suite **301 / 1419 / 0**
(59.11s); `git diff --check` clean; nothing staged. Docs updated: this entry,
`UI_CSS_RETIREMENT_PLAN.md` (Status M5.6 LANDED + ◆ M5.6 bullet + sub-roadmap renumbered M5.7+
+ §9 checkbox), `SESSION_HANDOFF.md` (header + M5.6 summary + item-14 tail + documentation table).
Downstream: `.btn-close` self-ownership **ui.css §4.3 → app.css (0 rules remain)**; only the
alert-family `.alert-dismissible .btn-close` positioning selector stays. **M5.7 NOT STARTED.
M6 NOT STARTED.** ui.css retained and linked.

## 2026-09-08 — M5.7 final utility / grid / text dependency re-scan (2DMIS v2)

**Objective.** Close out the M5 line: forensic, value-level audit of every remaining utility, grid
and text family in `public/css/ui.css` against the **compiled** Tailwind bundle; migrate ONLY rules
whose canonical twin is provably value-exact AND cascade-isolated (no competing unlayered
declaration on any consumer); retain everything else with a documented reason; verify via parity,
regression and guards; then hand off to the M6 deletion gate. Values are compared by compiled
declaration — never by class name.

**Scope.** §4.9 utility sections (display, flex, justify/align, margin-auto, position, text-align/
color, typography, sizing, images, backgrounds, borders/radius, overflow, spacing ladder, gap),
§4.7 grid (`.row/.col-*/.g-*`), §4.6 `.table*`/`.align-middle` (M4.6-owned) and the datatables.css
seam. GATE OPEN (auth-gated admin pages not browser-scannable) documented; covered by static
per-family audit + REP-CONTEXT replicating the auth-gated markup verbatim.

**Migrated (11 ui.css rules removed — ZERO Blade/JS file edits; the consumer class literal IS the
Tailwind class string, so the value-exact twins were already compiled into the bundle):**
| Retired rule | Twin in `app-BZH684Eh.css` | Cascade-isolation proof |
|---|---|---|
| `.gap-1` | `.gap-1{gap:calc(var(--spacing)*1)}` = .25rem | Tailwind `--spacing*.25rem = .25rem`, exactly the Bootstrap ladder step 1; no ui.css component sets `gap` on the 4 consumers (`.status-badge`/`.data-card-*`/`.ui-*` gap rules target other classes) |
| `.gap-2` | same ×2 = .5rem | ditto (69 occurrences); poisoned probe read 7px AFTER vs 99px BEFORE — interceptor proven live |
| `.flex-wrap` | `.flex-wrap{flex-wrap:wrap}` | ui.css `flex-wrap:wrap` lives only on `.row`/`.data-card-*`/`.ui-*` classes the 44 consumer divs never carry |
| `.top-0` / `.bottom-0` | `.top-0{top:calc(var(--spacing)*0)}` etc. | only top/bottom declarations on the fixed-shell consumers (`.sidebar`); ui.css `top/bottom` live on `.dropdown-menu` (top:100%) / `.alert-dismissible .btn-close` (top:0; bottom:auto) |
| `.overflow-hidden` / `-visible` / `-x-auto` / `-y-auto` | identical values | surviving ui.css `overflow` declarations are component-scoped (`.form-control`, `.table-responsive`, `.metric-card`, `.data-card-*`) — never on the consumer wrappers |
| `.ms-auto` | `.ms-auto{margin-inline-start:auto}` | the only margin-inline declaration on consumers (sidebar close btn + group-toggle chevron); LTR, `dir` unset → logical==physical |
| `.mx-auto` | `.mx-auto{margin-inline:auto}` | only margin-inline on `.data-card` consumer (`.data-card` sets no margin); `.mx-auto` p, .h3 leftover consumers unaffected (p/h3 have no margin-inline)

**Retained (forensic reasons; each is either value-mismatched or would lose to an unlayered
competitor if the class were dropped):**
- **Spacing ladder `m*`/`p*`:** steps 3–5 values differ from Tailwind (`mt-3` Bootstrap 1rem vs
  twin .75rem; `mt-4` 1.5rem vs 1rem; `mt-5` 3rem vs 1.25rem; same for mt/mb/ms/mx/me/p*/pt/pb/px/
  py/gap at steps 3–5). Steps 0–2 are value-equal, BUT the twins are **layered** and lose to
  unlayered competitors the consumers actually carry: §4.10 `h1/h2/p/dd` margins, `.list-group-item`
  `padding:.5rem 1rem` + `background-color:#fff` (beats a layered `px-0`/`bg-transparent` twin),
  `.table{vertical-align:top}` (beats a layered `align-middle` twin). Dropping even value-equal steps
  would flip real screens.
- **`.rounded`, `.rounded-1/2/3`, `.rounded-circle`, `.rounded-pill`, `.border-1/2/3`:** Bootstrap
  `.rounded` = .375rem vs Tailwind `.rounded` = .25rem (the ~124 consumers hold .375rem today via
  `!important`); `.border` twin = `border-width:1px` + `var(--tw-border-style)` with **no #dee2e6
  color** — a live flip.
- **Text colors** (`.text-primary/secondary/success/danger/warning/info/muted/white/body`): no
  exact Tailwind token; `text-danger` `.dc3545` etc. are contract values.
- **`.text-start/-center/-end`:** `.text-center` has a concrete live flip — unlayered §4.10
  `th{text-align:inherit}` beats the layered twin, so the 8+ `<th class="text-center">` headers
  would snap to inherited alignment.
- **`.bg-white`, `.bg-transparent`:** `.bg-transparent` flip proven (see spacing row);
  `.bg-white` deferred on auth-scope parity.
- **`.align-middle`:** the `<table class="table align-middle">` cell-centering MECHANISM; the
  layered twin loses to unlayered `.table{vertical-align:top}` (all cells would snap to top).
- **Display/position** (`.d-inline`, `.d-block`, `.d-flex`, `.d-inline-flex`, `.position-static/
  relative/absolute/fixed/sticky`): class names differ from Tailwind (`inline/block/flex/
  inline-flex`), i.e. renames + cascade exposure for negligible value.
- **Typography/sizing/images** (`.fw-light/normal/semibold/bold`, `.fst-italic`, `.fs-4`, `.small`,
  `.img-fluid`, `.img-thumbnail`, `.min-vh-100`, `.w-auto`): value-mismatched or renamed vs twins
  (`font-weight` tokens partial), low consumer counts.
- **Grid §4.7** (`.row`, `.col-sm-*`, `.col-md-*`, `.g-*`): M4.3 gutter-compensation model, 128
  `.row` consumers fully live.
- **Tables §4.6** (`.table`, `.table-sm`, `.table-responsive`, `.align-middle`): M4.6 ownership +
  datatables.css seam (`.table.dataTable.align-middle` at datatables.css:457).
- **`mt-auto`/`mb-auto`/`flex-nowrap`/`me-auto`/`overflow-auto`/`my-*`/`mx-*` 0–2 remainder:**
  zero-consumer DEAD rules retained solely pending the M6 inventory.

**DEAD set (0 consumer occurrences in class-attribute scan; classified for M6),** incl.
`mt-auto, mb-auto, flex-nowrap, me-auto, overflow-auto, d-block, d-inline-block, d-inline-flex,
position-static, position-fixed, text-end, text-body, text-secondary, fw-light, fw-normal (bare),
fst-italic, rounded-1, rounded-2, rounded-3, rounded-circle (0), border-1, border-3, ps-0, pe-0,
py-0, pt-0, pb-0, my-0, mx-0, g-0, g-4, g-5, gap-5`; near-dead (comment/JS-string occurrence only):
`text-info` 2, `text-muted` 2, `rounded-pill` 9, `fw-semibold`/`fw-bold` 0 in class markup.

**Parity (Playwright, `%TEMP%\opencode\m57`).** BEFORE = `ui-BEFORE.css` (hash `4306871F…`) +
current bundle; AFTER = edited ui.css (`B593DDF8…`) + current bundle; a fresh page per variant
fulfills `page.route('**/css/ui.css')` with the chosen stylesheet. Full-page digest = every body
element's computed style (47 props) + bounding box (2px tolerance) + overflow detection. Pages:
`/login`, `/qr-viewer`, `/grantee-update`, `/unpaid-verification` (all load ui.css — verified) +
REP-CONTEXT replicating the auth-gated shells: sidebar `fixed top-0 bottom-0 overflow-visible
ms-auto` close/chevron, sticky-nav `top-0`, `.data-card mx-auto`, `flex flex-wrap gap-3` quick
actions, `gap-1` tab nav, `justify-content-center gap-2` metric band, `h-2 overflow-hidden` strip,
`overflow-x-auto` table shells incl. `<th class="text-center">` + `table align-middle`.
@375/768/1280: **0 drift, 0 console errors, 0 page errors, no overflow**. Interceptor proven live:
`ui-PROBE.css` (ui-BEFORE + `.gap-2{gap:99px!important}`) read **99px** on the real
`/unpaid-verification` gap-2 element vs **7px** (0.5rem @14px root) in AFTER — both variants
genuinely applied, so the 0-drift run is meaningful, not a no-op.

**Guards re-verified.** ui.css has no `.d-none`, `.show{`, `.collapsed{`, `.btn-close{` (the only
`btn-close` selector is the alert-family `.alert-dismissible .btn-close`). Bundle has no `.d-none`,
`.show{`, `.collapsed{`, `.w-100{`. `.dropdown-open` present on navbar/clients/transactions
dropdowns; `.accordion-open` in `_gip`; toast `.show` ops untouched. datatables.css intact: 595
lines LF, B2 seam + `--color-navy`/`--shadow-focus` + `.align-middle` present, current SHA-256
`2EE627B7…` (M5.2's logged `B82937…` was a pre-normalization byte stream; the sole HEAD-delta is
the M5.2 seam block). ui.css prod selector count −11 in §4.9; comments updated at each retired
block with the M5.7 rationale.

**Tests.** build `app-BZH684Eh.css` (byte-identical 61.01 kB — the 11 twins were already
compiled); `view:cache` OK; Pint passed (`--test --dirty`); suite **301 / 1419 / 0** (51.46s);
`git diff --check` clean; nothing staged; M5.7 touched ONLY `public/css/ui.css` among
application files (working tree also carries the prior uncommitted M5.2–M5.6 batch).

**Docs updated:** this entry, `UI_CSS_RETIREMENT_PLAN.md` (Status M5.7 LANDED + ◆ M5.7 bullet +
refined sub-roadmap → M6 + §9 checkbox), `SESSION_HANDOFF.md` (item-14 header + M5.7 tail +
documentation table). **M6 NOT STARTED.** `ui.css` retained and linked; Bootstrap-parity
vocabulary untouched until M6.

### 2026-09-09 - M6.1: ui.css deletion-readiness gate audit (AUDIT ONLY)

**Scope:** the §9 M6 deletion gate opened per the retirement plan — full inventory, per-family
ownership, and a REAL browser simulated-deletion run (request-interception abort of
`/css/ui.css`; nothing else touched) across 375/768/1280 on the four public serving surfaces
plus an injected parity harness for the auth-gated shell + DataTables families.
**Verdict: NOT SAFE — the M6 gate FAILS; `ui.css` deletion remains BLOCKED.** Every retained
M4–M5 family with a live consumer drifts measurably the instant `/css/ui.css` disappears.
**Zero application files changed** (no deletion, no unlink, no Blade/CSS/JS/PHP/DB edit);
all 8 `<link>`s intact; `ui.css` byte-identical; no M6 execution, no M6.2.

**Baseline (working tree = authoritative; carries the uncommitted M5.2–M5.7 batch).** ui.css
SHA-256 `3E2964AC39D94058D0E4BED2D21E3BD556BE05384788494DB6B98B00D3760B06` (PostCSS parse =
**333 rules**; 285 simple-class rows); datatables.css `B82937375133CABD50C1AD7BB590FE124D9D9E9964A106CA65D19B65ABB326AE`;
resources/css/app.css `EC35FA3AB45F8CBB27039569F38DD0079EE23875F0FC5411948D6F313A02E5E5`;
build `app-BZH684Eh.css` (61,008 B) + `app-DqsLDVL_.js` (manifest current). PHPUnit **301/1419/0**.
`git diff --check` clean (CRLF warnings only). Dev server `:8000` + MySQL `:3306` up.

**Inventory + consumer scan** (`%TEMP%\opencode\m61\consumer-scan.mjs`; allowlist = blade/php/js/
vue in resources+public, `node_modules/vendor/docs/build` excluded): of the 285 simple classes:
**94 have ≥1 live consumer** (21 with exactly 1), **191 have 0**. Heaviest live families: `mb-2`
(116), `rounded` (101), `text-center` (63), `text-danger` (63), `form-select` (52), `mb-0` (40),
`form-check` (37), `data-card-body` (37), `mb-3` (32), `text-white` (28), `py-2` (27),
`col-sm-4/8` (48 combined), `col-md-3/4/6` (54 combined), `alert` (19), `btn-close` (14),
`alert-danger` (14), `form-control-sm` (12), `accordion*` (10+), `list-group*` (11+),
`img-thumbnail` (2), `table-responsive` (1), `d-flex` (1). Complex selectors add more reach
(31 screens use form/alert/btn composites; 23+ `@push('styles')`).

**Bundle ownership (grep on compiled `app-BZH684Eh.css`).** App/build EMITS: the
`.btn-gold,.btn-navy,.btn-red,.btn-subtle,.btn-outline-red` group (cursor/radius/metrics),
`.btn-close` self-contract, `.dropdown-menu.dropdown-open` (display:block state),
`.accordion .accordion-button.accordion-open` (+`::after` active state), `.metric-card`,
`.metric-value`, `.status-badge`, `.data-card-*`, `h1–h6`, global `:focus-visible` +
`prefers-reduced-motion`, `table{display:table}` (Tailwind display utility), and value-matched
twins (`.text-center`, `.align-middle`, `.text-white`, `.mb-2`, `.py-2`, `.border-2`, `.w-auto`,
`.ms-1/-2`, spacing 0–2, `px-0/1/4`, `rounded-pill`, `.mt-4`…). NOT emitted (ui.css-only
today): `.btn` base + `.btn-primary/-outline-primary/-danger/-outline-danger/-sm`, `.btn-group`,
`.form-control/-select/-check-input/-sm` + `.input-group*`, `.alert*` + `.alert-dismissible
.btn-close` placement, `.dropdown-menu/-item/-toggle` base, `.accordion*` base + `.accordion-button::after`
chevron, `.list-group*`, `.table` layout (width/margin/collapse), `.row/.col-*/.g-*` grid,
`.page-link` navy re-point, utilities `text-danger/-warning/-muted/-primary…`, `d-*`,
`position-*`, `fw-*`, `img-thumbnail`, `table-responsive`, `.small`, `fs-4`, spacing 3–5.

**Browser simulated deletion** (`deletion-sim.mjs`; Chromium; `ctx.route('**/css/ui.css',
r=>r.abort())` vs normal load; parity container injects table/alert-dismissible/accordion/
list-group/dropdown-open/DataTables filter+length+paginate/row-col markup into every page;
utility probe set same-name classes; 47-property computed-style snapshot @375/768/1280 × 4
pages). Results — **0 console errors, 0 page errors, 0 horizontal overflow on all 12 combos**
(both variants identical) and the global **`:focus-visible` gold 3px outline is byte-identical**
in both → the a11y seam (app.css `@layer base`) survives deletion. Everything else drifts,
measured per family (current ⇒ simulated):

- `.btn` base (`button.btn.btn-outline-primary.dropdown-toggle`): line-height 21px⇒22.4px,
  color `rgb(0,56,168)`⇒`rgb(0,0,0)`, background ⇒ `rgb(240,240,240)`, border-radius 5.25px⇒0,
  cursor∈{pointer}⇒fallback, white-space nowrap⇒normal, vertical-align middle⇒baseline.
- `.form-control`: display block⇒inline-block, width 100%⇒161px intrinsic, border-radius
  5.25px⇒0, border-color `#dee2e6`⇒`rgb(118,118,118)`, appearance none⇒auto (same for
  `.form-select` 162px/min-height 21px); `.input-group`: flex⇒block (parts stack).
- `.alert` + variants + `.alert-dismissible`: position relative⇒static, margin-bottom 14px⇒0,
  color/background/border-color (success/info palettes) ⇒ black-on-transparent, radius 5.25px⇒0,
  border-width 1px⇒0.
- `.alert-dismissible .btn-close`: position absolute⇒static, z-index 2⇒auto, padding
  17.5/14px⇒3.5px → the close button falls back into the alert body flow.
- `.dropdown-menu`/`.dropdown-open`: position absolute⇒static, z-index 1000⇒auto,
  background⇒transparent, border/radius/shadow (0.15 fade) lost (display:block survives only
  via the app.css open token); `.dropdown-item`: block⇒inline, width 100%⇒auto, padding lost;
  `.dropdown-toggle` = same drift as `.btn`.
- `.accordion-item/.button/.collape/.body`: radius/borders 5.25px⇒0, `.accordion-button`
  flex⇒inline-block, width 100%⇒auto, **font-size 14px⇒21.9–28px** (button UA default),
  background/color lost; `.accordion-body` padding 14/17.5px⇒0.
- `.list-group`/`-flush`/`-item`: flex⇒block, `list-group-item` position relative⇒static,
  bg⇒transparent, border-color `#dee2e6`⇒`#212529`, padding lost.
- `table.table`: width 100%⇒auto, margin-bottom 14px⇒0, vertical-align top⇒baseline.
- `.row`/`.col-md-6/-4`/`.col-sm-4`/`.g-2`: flex⇒block, negative gutters −3.5/−7px⇒0, column
  pads 3.5/10.5px⇒0, columns to 100% width → grid collapses to stacked blocks on every CRUD form.
- `.dataTables_wrapper .page-link` (parity only): navy `--ui-navy`⇒`#0d6efd` default; on the 10
  real DT screens this is covered by datatables.css `.dataTables_wrapper .pagination .page-link`
  (`var(--color-navy)`) — **not a real-screen blocker**.
- Utility probes: `.d-flex` flex⇒block, `.text-danger` `#dc3545`⇒`#212529`, `.position-relative`
  relative⇒static (no twin → class vanishes); value-drift twins `.mt-3`/`.px-3`/`.gap-3`
  14px⇒10.5px, `.rounded` 5.25px⇒3.5px (Tailwind 0.75/0.25rem @14px root); no-drift twins
  `.text-center`, `.text-white`, `.mb-2`, `.py-2`, `.border-2`, `.w-auto` identical.

**`--ui-*` audit.** 67 `:root` tokens declared; 58 referenced within ui.css; **7 consumed by
app.css**: the `.accordion .accordion-button.accordion-open` rule (M5.5 migration) consumes
`--ui-accordion-active-color/-bg/-border-width/-border-color/
-btn-active-icon/-btn-icon-transform/-inner-border-radius`, all declared ONLY in ui.css `:root`.
Deleting ui.css leaves those variables undefined → the M5.5 active-state declarations are
omitted → the migrated accordion-open contract degrades. **29 tokens unused anywhere**
(`gold-dim`, `teal-light`, `red-light`, `bg`, `bg-alt`, `text-inverse`, `color-structure/action/
success/danger/warning/info`, `font-mono`, `text-base/-lg`, `space-2/-7`, `page-gutter`,
`radius-xl`, `shadow-xs/-lg/-xl/-glow`, `duration-fast/-slow`, `sidebar-w`, `header-h`,
`panel-w`, `touch-target`, `touch-target-sm`) — M6.2 cleanup candidates. datatables.css uses 0
`--ui-*` (self-contained).

**Guards M5.2–M5.7 re-verified — PASS.** ui.css: 0 `.dataTables_*`, 0 `.d-none`, 0 `.show{`,
0 `.collapsed{`, 0 `.btn-close{` self (only `.alert-dismissible .btn-close`). Build: 0 `.w-100`,
0 `.show{`, 0 `.collapsed{`. datatables.css intact (595 lines LF; B2 seam + `.align-middle` +
`--color-navy`/`--shadow-focus` present). Pagination family: ui.css `.page-link` (navy) is
out-specified by datatables.css on DT screens and has **0 non-DT consumers** → effectively DEAD
family (SAFE for the DT seam). `.page-link` drift only reproduced on the injected parity harness,
not real screens.

**e2e smoke.** `alpine-phase0.spec.ts` PASS in chromium (login boots clean with Bootstrap CSS
removed + ui.css present). `smoke.spec.ts` (4 tests) FAIL at `signIn` only — `smoke_superadmin`
seed does not exist in the local `main_system` copy (stays at `/login`); environment gap, not a
CSS regression; documented, no fix applied.

**Intercept validity.** 12/12 surfaces show drift confined to ui.css-only families while app.css
twins (focus seam, `.text-center`/`.text-white`/`.mb-2` twins) stayed byte-identical — proving
the abort removed exactly and only `ui.css`. No fake-pass, no selector edits, no !important
probes beyond measurement.

**Blockers (family ⇒ owner ⇒ sim evidence).** See `UI_CSS_RETIREMENT_PLAN.md` §M6.1 table for
the full A–F matrix (94 live classes). Headline live families blocking the gate: **buttons base
(many), forms (31 screens), alerts + dismissible placement, dropdown base (3 Alpine dropdowns),
accordion base + token-source (1), list-group (5 autocomplete/inject sites), `.table` layout,
grid `.row/.col-*/.g-*` (every CRUD form + dashboard), utility families `text-*`, `d-*`,
`position-*`, `fw-*` with live consumers, spacing 3–5 drift twins, `.img-thumbnail`,
`table-responsive`, `.small`, `fs-4`**. SAFE/B families verified: `.btn-gold/-navy/-red/-subtle/
-outline(-red)` (app.css group), `.btn-close` self, `.metric-card`, `.metric-value`, `.status-badge`,
`.data-card-*`, `h1–h6`, global `:focus-visible`/reduced-motion, `hidden` (M5.3),
`.dropdown-open`/`.accordion-open` states, matching-value utilities (`.text-center`, `.text-white`,
`.align-middle`, `.mb-2`, `.py-2`, `.border-2`, `.w-auto`, `.ms-1/-2`, spacing 0–2, `px-0/1/4`,
`rounded-pill`), DataTables seam (0 ui.css rules + full datatables.css coverage), `.page-link`
family (DEAD non-DT). DEAD set (0 consumers) ≈ 191 classes incl. `mt-auto`, `me-auto`,
`flex-nowrap`, `ms-*` stragglers, `d-block/-inline-flex`, `position-fixed/-sticky`,
`text-end/-body`, `fw-*`, `fst-italic`, `rounded-1/2/3/-circle`, `border-1/-3`, most `my-*`/`mx-*`/
`pt-*`/`pb-*`/`ps-*`/`px-2`/`py-0`, `gap-5`, `g-0/1/3/4/5`, `data-card-footer` etc.

**M6.2 recommendation (NOT STARTED).** The gate cannot pass until, per family: move the 7
`--ui-accordion-*` tokens (and with them a decision on the remaining `:root` block) into
`@theme static` (the §6.1 remap-by-value rule) — or the accordion base + chevron into app.css;
`@apply`-free parity ports or Blade rewrites for forms/buttons-base/alerts/dropdown-base/
list-group/grid/tables; resolve spacing-3–5 drift twins per §6.4 by explicit paddings; delete
the DEAD set; then re-run this exact gate (matrix + sim) with an authenticated-shell run when a
test account exists (§6.5 residual).

**Tests.** PHPUnit **301 / 1419 / 0** (re-run today, unchanged); `git diff --check` clean;
Pint not needed (no source change); build untouched. No application/db/file changed by the audit.

**Docs updated:** this entry, `UI_CSS_RETIREMENT_PLAN.md` (M6.1 status + §M6.1 matrix section +
§9 checkbox + end-note correction), `SESSION_HANDOFF.md` (Last-Updated header, item-14 header,
M6.1 tail, documentation table). **M6 (deletion) NOT executed; `ui.css` retained and linked;
Bootstrap-parity vocabulary untouched.**

### 2026-09-09 - M6.2: accordion token ownership resolution (canonical owner = app.css)

**Scope:** resolve the M6.1 blocker where the M5.5 accordion open-state rules in
`resources/css/app.css` consumed seven `--ui-accordion-*` tokens declared only by `ui.css`.
This phase moves those seven tokens to a canonical declaration in app.css (same names, same
byte-identical values, same `.accordion`-scoped cascade) and removes ONLY those declarations from
ui.css. Explicit NON-goals (phase boundary): no `ui.css` deletion, no `<link>` removal, no other
token/died cleanup, no component-family migration, no Blade/JS/PHP/database change. M6.3 is NOT
started.

**Correction to the M6.1 §`--ui-*` record:** M6.1 noted these seven tokens as "declared only in
ui.css `:root`". On inspection the declaration site is **`ui.css` §4.8 `.accordion` scoped block**
(`public/css/ui.css` l.856–879; app.css omits Preflight-native root font-size). The M6.2 behavior
and relocation are unaffected by the scope detail; the exact scope is preserved below.

**Token inventory (`rg -- '--ui-accordion-[A-Za-z0-9_-]+'`, repo-wide, excluding build):**

| Token | Value (verbatim) | Old owner | New owner | Consumers |
|---|---|---|---|---|
| `--ui-accordion-border-color` | `#dee2e6` | ui.css `.accordion` §4.8 | app.css `.accordion` (M6.2) | app.css l.1181 (open inset); ui.css §4.8 `.accordion-item` border (l.918) |
| `--ui-accordion-border-width` | `1px` | ui.css `.accordion` §4.8 | app.css `.accordion` (M6.2) | app.css l.1181 (inset calc); ui.css §4.8 `.accordion-item` border (l.918) |
| `--ui-accordion-inner-border-radius` | `calc(.375rem - 1px)` | ui.css `.accordion` §4.8 | app.css `.accordion` (M6.2) | app.css l.1190–1191 (closed corner); ui.css §4.8 first-of-type inner radius (l.925–926) |
| `--ui-accordion-btn-icon-transform` | `rotate(-180deg)` | ui.css `.accordion` §4.8 | app.css `.accordion` (M6.2) | app.css l.1186 (`::after` open rotation) |
| `--ui-accordion-btn-active-icon` | `url("data:image/svg+xml,… fill='%23084298' …")` | ui.css `.accordion` §4.8 | app.css `.accordion` (M6.2) | app.css l.1185 (`::after` open chevron) |
| `--ui-accordion-active-color` | `#084298` | ui.css `.accordion` §4.8 | app.css `.accordion` (M6.2) | app.css l.1179 (open text) |
| `--ui-accordion-active-bg` | `#cfe2ff` | ui.css `.accordion` §4.8 | app.css `.accordion` (M6.2) | app.css l.1180 (open fill) |

The remaining 15 `--ui-accordion-*` tokens stay in ui.css §4.8 (base formation continues to use
them); ui.css now declares **none** of the seven and only references border-width/-color and
inner-border-radius from base-formation rules, resolving them by inheritance from the app.css
`.accordion` scope. `--ui-accordion-inner-border-radius` still embeds `.375rem - 1px` whose
`--ui-accordion-border-radius` source remains ui.css-owned until the base migration.

**Source changes (exactly two application files):**
- `resources/css/app.css` — new canonical block above the M5.5 open-state rules:
  `.accordion { --ui-accordion-border-color: #dee2e6; --ui-accordion-border-width: 1px;
  --ui-accordion-inner-border-radius: calc(.375rem - 1px); --ui-accordion-btn-icon-transform:
  rotate(-180deg); --ui-accordion-btn-active-icon: url("…fill='%23084298'…");
  --ui-accordion-active-color: #084298; --ui-accordion-active-bg: #cfe2ff; }` + M5.5 comment
  header updated to point at the app.css-owned tokens. Same scope, same selector strategy
  (0,3,0), no `!important`, no layer/source-order change (`@vite` then `ui.css` then screen).
- `public/css/ui.css` — §4.8 `.accordion` block: the seven declarations REMOVED; one pointer
  comment added. Retained 15 declarations byte-identical. No other token touched.

**Cascade reasoning:** custom properties cascade through the `.accordion` element scope regardless
of owning stylesheet; app.css (built, unlayered) declares the seven, ui.css (later, unlayered)
declares the remaining 15 with no name overlap, so resolved values per element are identical to
M5.5/M6.1. No selector specificity, layer, or source-order change; components tie-break exactly
as before.

**Browser parity (real Alpine + `@alpinejs/collapse` via the actual built JS bundle, injected
onto the real `/login` page — built CSS + ui.css + screen styles in production order):** harness
`accordion-parity.mjs` drives the exact `_gip` markup+component contract (accordion-open —
`:class`/`@click`/`:aria-expanded`, `x-show`+`x-collapse` panel) through closed→open→closed
(pointer) then Enter→Space→Enter (keyboard) at **375/768/1280**. Baseline captured on the
pre-change build, re-run post-change: **computed-style drift = 0** on every compared property
(button, `button::after`, panel, item) across all six states/viewports; console errors 0,
overflow 0 both runs. Tokens resolve to final computed values: color `rgb(8,66,152)`, bg
`rgb(207,226,255)`, inset `rgb(222,226,230) 0px -1px 0px 0px`, ::after `matrix(-1,0,0,-1,0,0)`
(rotate(-180°)), closed corner `4.25px` (= `calc(.375rem - 1px)` at the app.css 14px root
`html{font-size:14px}`), focus ring `3.5px` (= `.25rem`).

**Deletion simulation (§13):** focused sim — `page.route('**/css/ui.css', abort)` on the post-change
build. The **open and closed STATE now survive**: color/bg/inset shadow/active chevron/icon
rotation/closed corner identical to the full-load reference; panel behavior identical
(height 63.17 px open, 0 closed). Remaining sim drift is the **base formation** (`.accordion-button`
padding/font-size, `.accordion-item` border, `::after` content/size/transition + focus ring) —
the ui.css §4.8 accordion **base** family, which M6.2 scope explicitly does not migrate; that is
recorded as the residual blocker for the later base-family phase (§13 STOP-and-report clause,
honored — no scope expansion). One measurement nuance documented: `getComputedStyle(el,'::after')`
reports `transform:none` for the non-generated pseudo (ui.css base `content` absent under abort);
force-generating the pseudo (`content:""`) returns the identical `matrix(-1,0,0,-1,0,0)` and the
active SVG — proving the token-owned declarations resolve under simulated deletion.

**Build verification:** `npm run build` → `assets/app-D8NYbDco.css` (61.57 kB; prior 61.01 kB —
+7 token declarations, no asset explosion). Compiled output contains the seven canonical tokens,
all three M5.5 rules (minifier writes `:after`), `.hidden`, `.dropdown-open`, `.btn-close`. JS
unchanged (`app-DqsLDVL_.js`).

**Dependency verification (§9):** `rg -- '--ui-accordion-(active-color|active-bg|border-width|border-
color|btn-active-icon|btn-icon-transform|inner-border-radius):'` → declarations exist ONLY in
app.css (7), zero in ui.css. `--ui-accordion-` : 7 declarations + references in app.css; 15
declarations + base references in ui.css. No other consumers repo-wide. Zero `!important` in the
accordion region.

**Guards M5.2–M5.7 (re-verified):** datatables.css `:focus` seam intact; `.hidden` in build (M5.3);
`.dropdown-open` in build, ui.css 0 `.show{` (M5.4); `.accordion-open` rules in build, ui.css 0
`.collapsed{` (M5.5); `.btn-close` self-ownership app.css, ui.css 0 `.btn-close{` self (M5.6);
eleven migrated utilities untouched (M5.7). Guards PASS — no regression.

**Tests:** `git diff --check` clean; `php artisan view:cache` OK; `vendor/bin/pint --test` passed;
PHPUnit **301 / 1419 / 0** (exactly the baseline — no count/assertion change, matching the
no-app-behavior-change contract).

**Docs updated:** this entry, `UI_CSS_RETIREMENT_PLAN.md` (§M6.2 section + §9 M5 checkbox tail),
`SESSION_HANDOFF.md` (Last-Updated header, item-14 header, M6.2 tail, documentation table).
**M6.2 COMPLETE. M6.3 NOT STARTED. `ui.css` still exists and is still linked (all 8 `<link>`s
intact); no token beyond the seven moved; no dead-token cleanup performed.**

### 2026-09-09 - M6.3.1: accordion base+chevron family migration to app.css (canonical owner)

**Scope:** the first M6.3 retained-family migration — the Alpine x-collapse accordion base
formation (clients/_gip) becomes fully app.css-owned, completing the accordion family started by
M5.5 (open-state) and M6.2 (state tokens). **No deletion, no unlink, no Blade/JS/PHP/DB change.**
The browser-parity harness from M6.2 was extended to cover the base-formation computed props
(`%TEMP%\opencode\m63\accordion-parity.mjs`, diff tool `diff-accordion.mjs`).

**Source changes (exactly two application files):**
- `resources/css/app.css`: the M6.2 `.accordion` token block grew from 7 to the full **22-token**
  family — the remaining fifteen `--ui-accordion-*` tokens (color, bg, transition, border-radius,
  btn-padding-x/y, btn-color, btn-bg, btn-icon, btn-icon-width, btn-icon-transition,
  btn-focus-border-color, btn-focus-box-shadow, body-padding-x/y) moved **VERBATIM** from ui.css
  §4.8, byte-identical values. A new **M6.3.1 base-formation section** was appended right after the
  M5.5 open-state rules, porting the entire ui.css §4.8 formation **VERBATIM**: `.accordion-button`
  (box/typography/padding/transition incl. `overflow-anchor`), `.accordion-button:focus`
  (z-index/border-color/ring), `.accordion-button::after` (chevron content/size/repeat/transition),
  `.accordion-header`, `.accordion-item` (border via moved tokens), `:first-of-type` corners +
  inner button corners, `:not(:first-of-type)` border-top, `:last-of-type` corners +
  `.accordion-collapse` radius, `.accordion-body` padding. M5.5 selectors/values untouched;
  M5.5 comments' "base stays in ui.css" phrase updated to the M6.3.1 reality.
- `public/css/ui.css`: the `.accordion` 15-token block + all base-formation rules **removed** from
  §4.8 (~90 lines); the §4.8 header comment trimmed to the list-groups + `.form-label`. `--ui-accordion-*`
  and `.accordion*` selectors now return **0** in ui.css (comment mention only). After this change the
  accordion family is 100% app.css-owned and self-contained against ui.css deletion.

**Why it is safe (cascade analysis):** every ported selector/value is byte-identical to the ui.css
source (Bootstrap 5.3.2 byte-faithful family). Pre-change load order (`@vite` → `ui.css` → screen
styles) had the ui.css base load late; post-change the base lives in the bundle and ui.css rules are
gone, so no source-order/specificity tie with later ui.css exists anymore (this is exactly what
deletes the historical 0,3,0-scoping rationale for the M5.5 rules — selectors kept unchanged). Both
token blocks were `.accordion`-scoped with single ownership in both states (no duplicate
declaration ever existed post-M6.2), so resolved custom-property values are unchanged.

**Verification:**
- **Build:** `npm run build` → `assets/app-DspRAT0c.css` (**64.27 kB**, from 61.57 kB — the base +
  15 tokens); JS unchanged (`app-DqsLDVL_.js`); all **22 accordion tokens** + every base selector
  (button/focus/*:after*/header/item first/not-first/last/collapse/body) + retained `.hidden`,
  `.dropdown-open`, `.btn-close`, `.accordion-open` found in the compiled output (minifier emits
  `:after` for `::after`).
- **Real-Alpine parity (Gate 1 — behavior unchanged):** extended harness on real `/login` with the
  built JS bundle; post-change `current` vs the pre-change M6.2 browser capture (`baseline2.json`,
  real full-load) across closed→open→closed + Enter→Space→Enter @375/768/1280: **0 computed-style
  drift** on every shared property (button box/typography/padding/colors/inset/ring/corners,
  chevron values incl. transform and content, panel height behavior, item border/radius).
- **New base props (not in the M6.2 capture) asserted equal to their ui.css-derived expected
  values:** button `transition` = `color .15s ease-in-out, … border-radius .15s ease`;
  `overflow-anchor: none`; `align-items: center`; `::after` `flex-shrink: 0`,
  `background-repeat: no-repeat`, `content: ""` (pseudo generated); `.accordion-body` padding =
  `14px 17.5px 14px 17.5px` (1rem/1.25rem at the 14px root — the Bootstrap value, i.e. the ported
  base still beats the later-in-bundle `p-[1rem]` twin exactly as late-loaded ui.css did);
  `.accordion-header` margin-bottom `0`. All **identical in full-load and deletion-sim**.
- **Deletion simulation (Gate 2 — family survives):** with `ui.css` aborted, the post-change
  accordion renders **0 drift** vs full-load — button box/typography/colors/ring, item border
  (`1px solid rgb(222, 226, 230)`), chevron `content`/SVG, body padding, corners all intact. The
  only sim delta is the pre-existing **1 console error** in every viewport — the aborted-stylesheet
  `http…ui.css` resource message, numerically identical to the pre-change baseline (baseline2 sim
  also =1), not a regression. **The accordion blocker row is cleared from the M6 matrix.**
- **Guards M5.2–M5.7:** re-verified PASS (M6.3.1 touches none of `.hidden`/`.dropdown-open`/
  `.btn-close`/datatables B2 seam/eleven utilities; plus ui.css now 0 accordion selectors).

**Tests:** `git diff --check` clean; `php artisan view:cache` OK; `vendor/bin/pint --test` passed;
PHPUnit **301 / 1419 / 0** (exactly baseline).

**Status — removed from M6 blocking matrix:** accordion base + `::after` chevron + the 7-token
source dependency (M6.2) + the 15-token base family (M6.3.1) — **all moved to app.css, family
safely app-owned**. Remaining blocker families (unchanged): buttons base, forms, alerts +
dismissible placement, dropdown base, list-group, `.table` layout, grid, spacing-3–5/.rounded
drift, text/display/position utilities, modal base. `ui.css` still exists and still linked (8
`<link>`s intact); **M6.3.1 COMPLETE — next: M6.3.2 forms family.**

### 2026-09-09 — M6.3.2: forms family + ui.css `:root` design-token block relocated to app.css (2DMIS v2)

**Scope (M6.3 dependency order — forms):** two stages per the M6 plan. **Stage A** resolves the
plan's flagged "fate of the remaining 60-token `:root` block": the entire `--ui-*` Batch A
design-token block (29 declarations of `--ui-*` in ui.css §1, values byte-identical, ui.css
historical lines 24–131) was relocated **verbatim** into `app.css` as a plain unlayered
`:root { … }` rule placed immediately after `@theme static`. Rationale: forms/buttons/alerts/
pagination rules still living in ui.css resolve `var(--ui-navy)`, `--ui-navy-light`,
`--ui-focus-ring`, `--ui-red` etc. — before the move those tokens existed only in ui.css `:root`,
so any later ui.css deletion would break them; after the move app.css is the single canonical
owner with identical values, and ui.css declares zero `--ui-*` tokens. The token names/values were
NOT remapped onto the `@theme` `--color-*`/`--shadow-*`/`--text-*` equivalents — kept as verbatim
parallel names so the still-retained component families resolve identically (and the existing
`--shadow-focus` === `--ui-focus-ring` value overlap is preserved as a byte-faithful duplicate).
**Stage B** ports the forms family: ui.css §4.2 (`.form-control` incl. `[type=file]`, `:focus`,
`::placeholder`, `:disabled`, `::file-selector-button`, `.form-control-sm`;
`.form-select` incl. `:focus`, `[multiple]`, `:disabled`, `.form-select-sm`; `.form-check` /
`.form-check-input` incl. `[type=checkbox]`/`[type=radio]`, `:active`, `:focus`, `:checked`
(glyphs), `[type=checkbox]:indeterminate`, `:disabled` + label flow; `.input-group` +
children + `.input-group-text`) and the `.form-label` rule (ui.css §4.8) — all **verbatim**
into a new `#forms family` section in app.css directly before the M5.6 `.btn-close` section.
Values byte-identical; the app's own focus identity (navy border + gold ring via the relocated
`--ui-navy-light`/`--ui-focus-ring`) and the JS contracts (class names `.form-control` /
`.form-select` / `.form-check-input` stay exactly as before — zero blade/JS edits).

- `resources/css/app.css`: new `:root` design-token block (labels `--ui-navy` … `--ui-focus-ring`,
  + comment stating canonical ownership) after `@theme static`; new `#forms family` section
  (`.form-label` at the head, then §4.2 rules verbatim). M6.3.1 accordion section untouched.
- `public/css/ui.css`: `:root` token block **removed** → pointer comment (l.20–24); §4.2 forms
  sub-block + `.form-label` **removed** → pointer comment (l.303–311); §4.8 header comment updated
  (forms no longer lives here — only the autocomplete/suggestion list-groups remain of that
  section). Form selectors now return **0** in ui.css; `--ui-*` declarations now **0** in ui.css.
  All other retained families (buttons/.btn-group, alerts, dropdowns, list-group, `.table`,
  grid, utilities incl. spacing-3–5, modal, pagination) untouched.

**How the port was done:** a node one-off (`_m632_forms.mjs`) sliced the exact §4.2 + `.form-label`
byte ranges out of ui.css by anchored `indexOf` and inserted them into app.css; the token block
was inserted via the Edit tool with every value copied byte-for-byte from the ui.css `:root`
source (the M6.2/M6.3.1 byte-faithful discipline). **One authoring bug was caught by the build
gate:** the new `#forms family` header comment was originally written without its closing `*/`,
which made the compiler swallow `.form-label`→`.form-check` as comment content (bundle showed
`.input-group*` present but the base family missing). Isolated via a LightningCSS repro (the
dangling comment was confirmed by an open/close `/* */` count of 1/0; a standalone `vite build`
repro reproduced the drop exactly, and a simple `.zsimple` rule survived — proving the comment
was the trigger). Fixed by closing the comment; rebuild restored the full family. This incident
is recorded because it demonstrates the build-gate/parity checks catching a CSS-level regression
that the diff-based source inspection alone would not.

**Why it is safe (cascade analysis):** every ported value is byte-identical to the ui.css source
(Bootstrap 5.3.2 byte-faithful + the app's navy/gold focus identity). The forms rules set only
longhand properties on global class selectors — identical values existed in ui.css before the
change, so computed rendering is unchanged. The moved `:root` tokens have identical values and
single ownership after the move (ui.css declares none), so `var()` resolution is unchanged for
the still-ui.css families (buttons `.btn` gold focus `--ui-focus-ring`, `.btn-outline-danger`
`--ui-red`, pagination `--ui-navy-light`/`--ui-focus-ring`). The bundle loads after the Bootstrap
CDN stylesheet, so the ported definitions still win specificity ties exactly as ui.css did.

**Verification:**
- **Build:** `npm run build` → `assets/app-uoIBrx_c.css` (**71.18 kB**, from 64.27 kB — +6.9 kB =
  the ~60-token `:root` block + full forms family); JS unchanged (`app-DqsLDVL_.js`). Compiled
  output contains `.form-label`, `.form-control` (+`[type=file]`/`:focus`/`:disabled`/
  `::placeholder`/`::file-selector-button`/`.form-control-sm`), `.form-select` (+`:focus`/
  `[multiple]`/`:disabled`/`.form-select-sm`), `.form-check`/`.form-check-input`
  (+`:checked`/`[type=radio]`/`:indeterminate`/`:disabled`), `.input-group`(+children)/
  `.input-group-text`, and the relocated `--ui-*` tokens; retained-class guards
  `.hidden`/`.dropdown-open`/`.btn-close`/`.accordion-open` all present.
- **Real-browser parity (Gate 1 — behavior unchanged):** new `forms-parity.mjs` harness on the
  real `/login` injects a full-form fixture (`.form-label`, `.form-control` base/disabled/sm/file,
  `.form-select` base/sm, `.form-check`+checkbox/radio/indeterminate, `.input-group`+`.input-group-text`)
  and captures computed styles @375/768/1280.
- **Value assertions (no pre-change capture exists — forms lived in ui.css until now, so the
  M6.3.1 assertion route is used):** every measured value equals its ui.css-derived expected
  literal at the 14px root — `.form-control` padding `5.25/10.5px`, color `rgb(33,37,41)`, border
  `#dee2e6`; `.form-select` padding-right `31.5px` (2.25rem) + the `%3csvg…m2 5 6 6 6-6` arrow
  data-URI + `background-size 16px 12px`; `.form-control-sm`/`.form-select-sm` `12.25px`
  font + 3.5px radius; `::placeholder` `rgb(108,117,125)`; `::file-selector-button` padding
  5.25/10.5px + `border-inline-end-width 1px`; **`:focus` = navy-light `rgb(22,74,156)` border +
  gold `rgba(252,209,22,.3) 0px 0px 0px 2.8px` ring (0.2rem @14px root) — the app focus identity
  confirmed** (control + select); `:disabled` bg `rgb(233,236,239)`; `.form-check-input` base
  `1em`=14px, checkbox radius 3.5px, radio `50%`; `:checked` navy `rgb(0,56,168)` bg+border with
  the `m6 10 3 3 6-6` check / `-4 -4 8 8` circle SVG glyphs; `[type=checkbox]:indeterminate`
  navy + `M6 10h8` dash glyph; `.input-group-text` `#e9ecef` bg + radius corner-stream;
  `.input-group > .form-control` flex-grow/shrink `1`, basis `auto`, `min-width 0`;
  `.form-check` `padding-left 21px`, `min-height 21px`; `.form-label` `margin-bottom 7px`.
- **Deletion simulation (Gate 2 — family survives):** with `ui.css` aborted, **0 computed-style
  drift** vs full-load across every fixture element/state @375/768/1280 (the diff tool reports
  the only delta as the pre-existing **1 console error**/viewport — the aborted-stylesheet
  `ui.css` resource artifact, numerically identical to the M6.2/M6.3.1 baselines, not a
  regression). **The forms blocker row is cleared from the M6 matrix.**
- **Guards M5.2–M5.7:** re-verified PASS (M6.3.2 touches none of `.hidden`/`.dropdown-open`/
  `.btn-close`/datatables B2 seam; plus ui.css now also 0 form selectors and 0 `--ui-*`
  declarations).

**Tests:** `git diff --check` clean; `php artisan view:cache` OK; `vendor/bin/pint --test` passed;
PHPUnit **301 / 1419 / 0** (exactly baseline).

**Status — removed from M6 blocking matrix:** accordion (M6.2 + M6.3.1) **and forms + the
ui.css `:root` design-token block (M6.3.2)** — moved to app.css; the remaining still-ui.css
families resolve their tokens from the app.css `:root` with unchanged values. Remaining blocker
families: buttons base, alerts + dismissible placement, dropdown base, list-group, `.table`
layout, grid, spacing-3-5/.rounded drift, text/display/position utilities, modal base. `ui.css`
still exists and still linked (8 `<link>`s intact); **M6.3.2 COMPLETE — next: M6.3.3 buttons
base family.**

### 2026-09-09 - M6.3.3: buttons base family migration to app.css (canonical owner)

**Scope (M6.3 dependency order - buttons base):** ported the whole ui.css §4.1 buttons base
family VERBATIM to app.css: `.btn` base (display/padding `.375rem .75rem`/font metrics/border
`1px solid transparent`/radius `.375rem`/transition), `.btn:hover` (currentColor edge),
`.btn:focus-visible` (outline 0), `.btn.disabled`/`.btn:disabled` (pointer-events none /
opacity `.65`), `.btn-sm`, the structural-navy variant families `.btn-primary` and
`.btn-outline-primary` (— `--ui-navy` source, hover `--ui-navy-light`, active `--ui-navy-hover`,
focus ring `0 0 0 .25rem rgba(0,56,168,.5)`, outline-fill flips to navy on hover),
the flag-red destructive families `.btn-danger` and `.btn-outline-danger` (`--ui-red` + darker
hover `#A80F20` / active `#900C1B` stops + `rgba(206,17,38,.5)` ring), and the
`.btn.btn-gold:focus-visible` keyboard-focus glow (`rgba(220,180,0,.5)` ring). The app's brand
variants (`.btn-gold/.btn-navy/.btn-red/.btn-subtle/.btn-outline-red/.btn-outline`) were ALREADY
app.css-owned (self-contained group); the bridges between the two worlds are the equal-
specificity ties on `btn btn-gold`/`btn btn-navy` combos.

**Placement decision (cascade-critical):** the ported block is APPENDED AFTER every existing
app.css rule (after the M5.6 `.btn-close` self-contract), not woven into the brand section. In
v1 the ui.css `.btn` base (0,1,0) loaded after this bundle and therefore won every
equal-specificity tie (0,1,0) with the earlier app.css self-contained brand group — display
`inline-block`, padding 5.25/10.5px, border-radius 5.25px, font 14px/400/1.5, text-align center,
color `#212529`, border `1px transparent`, transition `.15s ease-in-out` — including on
`btn btn-gold`/`btn btn-navy` combos where the brand group's inline-flex/semibold/dense metrics
lose exactly as v1 rendered them. Appending the ported block last reproduces that
later-rule-wins outcome bit-for-bit; Tailwind Preflight/utilities are beaten by these unlayered
rules exactly as they were by ui.css.

**Source (two app files):** `resources/css/app.css` — M6.3.3 buttons-base section appended at
the end (l.1659+, banner comment + verbatim ui.css §4.1 text incl. its original header comment);
`public/css/ui.css` — §4.1 header + all button rules REMOVED, replaced by a pointer comment
(0 button selectors remain in this file). §4.4 `.btn-group` deliberately STAYS ui.css-owned
(moves with the dropdown family). No Blade/JS/PHP/DB change.

**Build:** `assets/app-QWOXOZW9.css` (**73.95 kB**, from 71.18 kB — +2.77 kB for the ~5 kB
minified port; JS unchanged `app-DqsLDVL_.js`). Bundle checks: every `.btn*` selector/state
present incl. the `.btn.btn-gold:focus-visible` glow; LightningCSS re-encodes the `rgba()` focus
rings to 8-digit hex in compiled output (`#0038a880` / `#ce112680` / `#dcb40080`) — same resolved
values; `.hidden`/`.dropdown-open`/`.btn-close`/`.accordion-open` + forms/accordion families
retained. The M6.3.2 authoring-gate lesson applied: the inserted banner comment is closed.

**Parity (Gate 1):** new `buttons-parity.mjs` harness — a 9-button fixture (base, primary,
primary-sm, outline-primary, danger, outline-danger, gold, navy, disabled) injected into real
`/login`; captures base computed styles (16 props incl. padding/font metrics/border/radius/
cursor/user-select/text-align/transition), hover state (color/bg/border/box-shadow on all 8
enabled), focus state (outline/box-shadow/color/bg), and the disabled state @375/768/1280.
No pre-change capture exists (buttons lived in ui.css until now), so the M6.3.2 value-assertion
route is used: **25/25 assertions** equal the ui.css-derived expected literals at the 14px root —
`.btn` `inline-block` + padding `5.25/10.5px` + radius `5.25px` + color `rgb(33,37,41)` + border
`1px transparent` (computed `rgba(0,0,0,0)`); `.btn-primary` bg `rgb(0,56,168)`/white text, hover
`rgb(22,74,156)`; `.btn-sm` `12.25px` font + `3.5/7px` padding + `3.5px` radius;
`.btn-outline-primary` navy text + navy fill/white text on hover; `.btn-danger` bg
`rgb(206,17,38)`/hover `rgb(168,15,32)`; `.btn-outline-danger` red text, red fill on hover;
disabled `opacity 0.65` + `pointer-events none`.

**Deletion simulation (Gate 2 - family survives):** with `ui.css` aborted, **0 computed-style
drift** across every button x state @375/768/1280 (the diff tool reports the only delta as the
pre-existing **1 console error**/viewport — the aborted-stylesheet artifact, numerically
identical to the M6.2/M6.3.1/M6.3.2 baselines, not a regression). The `.btn` variants render
identically without ui.css, including token resolution (`--ui-navy`/`--ui-navy-light`/
`--ui-navy-hover`/`--ui-red` + focus rings) from the M6.3.2 app.css `:root`. **The buttons base
blocker row is cleared from the M6 matrix.**

**Guards M5.2-M5.7:** re-verified PASS (M6.3.3 touches none of `.hidden`/`.dropdown-open`/
`.btn-close`/`.accordion-open`/datatables B2 seam).

**Tests:** `git diff --check` clean; `php artisan view:cache` OK; `vendor/bin/pint --test`
passed; PHPUnit **301 / 1419 / 0** (exactly baseline).

**Status — removed from M6 blocking matrix:** accordion (M6.2 + M6.3.1), forms + the ui.css
`:root` design-token block (M6.3.2), **and buttons base (M6.3.3)** — all app.css-owned via
verbatim ports. Remaining blocker families: alerts + dismissible placement, dropdown base
(+ §4.4 `.btn-group`), list-group, `.table` layout, grid, spacing-3-5/.rounded drift,
text/display/position utilities, modal base. `ui.css` still exists and still linked (8 `<link>`s
intact); **M6.3.3 COMPLETE — next: M6.3.4 alerts + dismissible placement.**

### 2026-09-09 - M6.3.4: alerts + dismissible placement family migration to app.css (canonical owner)

**Scope (M6.3 dependency order - alerts + dismissible placement):** ported the whole ui.css
§4.5 alert family VERBATIM to app.css: `.alert` base (position relative, padding `1rem`,
margin-bottom `1rem`, color inherit, transparent bg/border, radius `.375rem`),
`.alert-dismissible` (padding-right `3rem`), the **`.alert-dismissible .btn-close` placement
rule** (absolute top/right 0, z-index 2, padding `1.25rem 1rem` — the `.alert` PARENT
positioning contract M5.6 deliberately kept in ui.css), and the Bootstrap 5.3.2 emphasis
variants `.alert-success`/`.alert-danger`/`.alert-warning`/`.alert-info` (value-faithful
palette: `#0a3622`/`#d1e7dd`/`#a3cfbb`, `#58151c`/`#f8d7da`/`#f1aeb5`,
`#664d03`/`#fff3cd`/`#ffe69c`, `#055160`/`#cff4fc`/`#9eeaf9`). With this move the `.btn-close`
family is **fully app.css-owned**: the M5.6 self contract + this dismissible placement.

**Source (two app files):** `resources/css/app.css` — M6.3.4 alerts section APPENDED at the end
of the file (after the M6.3.3 buttons base; the `.alert-dismissible .btn-close` (0,2,0) placement
keeps beating the (0,1,0) `.btn-close` self contract for position/padding exactly as before), and
the M5.6 `.btn-close` header comment updated (alerts no longer ui.css-owned — the whole family
now lives here; the pre-existing `0,3,0` specificity typo in that comment corrected to `0,2,0`);
`public/css/ui.css` — §4.5 header + all alert rules REMOVED, replaced by a pointer comment
(0 alert selectors remain). No Blade/JS/PHP/DB change.

**Build:** `assets/app-D9Tw64t-.css` (**74.51 kB**, from 73.95 kB — +0.56 kB; JS unchanged
`app-DqsLDVL_.js`). Bundle checks: all seven alert selectors present incl. the dismissible
placement rule and the four emphasis variants; LightningCSS collapsed the padding shorthand
(`1rem 1rem` → `1rem`); `.btn`/`.form-control`/`.accordion-button`/`.btn-close`/`.hidden`/
`.dropdown-open` still present. The M6.3.2 authoring-gate lesson applied: the inserted banner
comment is closed.

**Parity (Gate 1):** new `alerts-parity.mjs` harness — six-alert fixture (base, success, danger,
warning, info, and a `alert alert-danger alert-dismissible` with a `.btn-close` child) injected
into real `/login`; captures base/variant computed styles (10 props incl. padding/margin/color/
background/border/radius) and the dismissible child position/padding/box @375/768/1280.
No pre-change capture exists (alerts lived in ui.css until now), so the value-assertion route is
used: **29/29 assertions** equal the ui.css-derived expected literals at the 14px root — `.alert`
relative + 14px padding + 14px margin-bottom + transparent bg/border + 5.25px radius; the four
emphasis variants' exact color triples; `.alert-dismissible` padding-right 42px; the close button
absolute/0/0/z-index 2/17.5×14px padding AND the M5.6 self-contract box intact (14×14px).

**Deletion simulation (Gate 2 — family survives):** with `ui.css` aborted, **0 computed-style
drift** across every alert × prop @375/768/1280 (the diff tool reports the only delta as the
pre-existing **1 console error**/viewport — the aborted-stylesheet artifact, numerically
identical to the M6.2/M6.3.1/M6.3.2/M6.3.3 baselines, not a regression). **The alerts blocker
row is cleared from the M6 matrix.**

**Guards M5.2-M5.7:** re-verified PASS (M6.3.4 touches none of `.hidden`/`.dropdown-open`/
`.btn-close` self contract — it MOVES the dismissible placement rule, keeping its (0,2,0)
override — /`.accordion-open`/datatables B2 seam).

**Tests:** `git diff --check` clean; `php artisan view:cache` OK; `vendor/bin/pint --test`
passed; PHPUnit **301 / 1419 / 0** (exactly baseline).

**Status — removed from M6 blocking matrix:** accordion (M6.2 + M6.3.1), forms + the ui.css
`:root` design-token block (M6.3.2), buttons base (M6.3.3), **and alerts + dismissible placement
(M6.3.4)** — all app.css-owned via verbatim ports. Remaining blocker families: dropdown base
(+ §4.4 `.btn-group`), list-group, `.table` layout, grid, spacing-3-5/.rounded drift,
text/display/position utilities, modal base. `ui.css` still exists and still linked (8 `<link>`s
intact); **M6.3.4 COMPLETE — next: M6.3.5 dropdown base.**

### 2026-09-09 - M6.3.5: dropdown base family + `.btn-group` migration to app.css (canonical owner)

**Scope (M6.3 dependency order - dropdown base):** ported the ui.css §4.7 dropdown family
VERBATIM to app.css: `.dropdown` (position relative), `.dropdown-toggle` (white-space nowrap) +
`::after` caret + `:empty::after`, the `.dropdown-menu` base (absolute/z-index 1000/display none/
`10rem` min-width/`.5rem 0` padding/0 margin/`1rem` font-size/`#212529`/`#fff`/padding-box/
1px `rgba(0,0,0,.175)` border/`.375rem` radius/`0 .5rem 1rem rgba(0,0,0,.15)` shadow), the popper
offsets `.dropdown-menu[data-bs-popper]` (top 100%, left 0, margin-top `.125rem`) and
`.dropdown-menu-end[data-bs-popper]` (right 0, left auto), and the six `.dropdown-item` rules
(base, `:hover`/`:focus`, `.active`/`:active`, `.disabled`/`:disabled`).

**`.btn-group` in scope (inseparability proven):** the §4.4 `.btn-group` block (base
`position:relative; display:inline-flex; vertical-align:middle`, the `> .btn` child
flex/z-index, and the radius-join rules incl. the `.dropdown-toggle`/`.btn-check` exclusions)
shipped WITH this phase — 2 of the 3 live Alpine dropdowns (clients/export, transactions/export)
use `.btn-group` as their positioned wrapper (`position:relative` + the flex static-position
context is what the absolutely-positioned `.dropdown-menu` relies on). The `.btn-group > .btn`
radius joins reference `.dropdown-toggle`, coupling the families at the selector level too.
The third (navbar user menu) uses `.dropdown` — same role.

**Consumer analysis:** the three Alpine dropdowns (navbar `partials/navbar.blade.php`,
clients/index export, transactions/index export) bind `.dropdown-menu.dropdown-open` via
`:class="{ 'dropdown-open': open }"` with `@click.outside` + `@keydown.escape` + `:aria-expanded`
— **no `.show`, no `data-bs-popper` in any live consumer** (the popper rules are ported for
Bootstrap parity, not used). The global-search autocomplete is fully self-contained (inline
styles + own `display` toggling) and never used this family. No Blade/JS/PHP/DB change;
`.dropdown-open` stays the canonical M5.4 open-state class (app.css).

**Source (two app files):** `resources/css/app.css` — M6.3.5 dropdown + `.btn-group` section
APPENDED at the end (after M6.3.4 alerts): `.btn-group` block first, then the dropdown block,
both byte-identical to the ui.css source incl. their §4.4/§4.7 header comments. The M5.4
`.dropdown-menu.dropdown-open` rule now wins its display conflict over the moved `.dropdown-menu`
base (0,2,0 vs 0,1,0) by specificity exactly as before; the M5.4 header comment reworded to name
the M6.3.5 dropdown section as the base's owner. `public/css/ui.css` — §4.4 and §4.7 removed,
replaced by pointer comments (0 dropdown / 0 `.btn-group` selectors remain); the §4.x index
paragraph updated to reflect the M6.2→M6.3.5 migrated families and the still-owned set; the M5.7
`top-0`/`bottom-0` comment updated (its `.dropdown-menu`/`.btn-close` component examples moved).
No Blade/JS/PHP/DB change.

**Build:** `assets/app-Cc-6A-2z.css` (**76.11 kB**, from 74.51 kB — +1.60 kB; JS unchanged
`app-DqsLDVL_.js`). Braces-balanced both files (288/288 app.css, 234/234 ui.css). Bundle
contains the `.btn-group` base and all dropdown selectors incl. popper offsets + caret.
The M6.3.2 authoring-gate lesson applied: both inserted banner comments are closed.

**Parity (Gate 1):** new `dropdown-parity.mjs` harness — five-structure fixture on real `/login`
replicating BOTH live compositions (`.dropdown` navbar wrapper; `.btn-group > .btn-subtle
.dropdown-toggle + .dropdown-menu.dropdown-menu-end.dropdown-open` export wrapper) plus
`[data-bs-popper]`/end variants and a closed menu; captures wrapper postures, toggles + `::after`
carets, open/closed menu computed styles, popper offsets, item base/`:hover`/`.active`/`.disabled`
states, and menu↔wrapper geometry deltas @375/768/1280. **53/53 assertions** equal the
ui.css-derived expected literals at the 14px root: `.dropdown` relative; `.btn-group`
relative/inline-flex/middle; caret `.3em` 4px@14px root; open menu block/absolute/z-index 1000/
140px min-width/7px padding/1px `rgba(0,0,0,.176)` border/5.25px radius/
`rgba(0,0,0,.15) 0px 7px 14px 0px` shadow; closed menu **display none**; popper `top:100%`/
left 0/1.75px margin-top and end right 0/left auto; items block/3.5px top/14px left/nowrap + the
`:hover` `#f8f9fa` / `.active` `#0d6efd` / `.disabled` `#adb5bd` state colors.

**Deletion simulation (Gate 2 — family survives):** with `ui.css` aborted, **0 computed-style
drift** across every dropdown prop @375/768/1280 (only the pre-existing **1 console
error**/viewport — the aborted-stylesheet artifact, numerically identical to every prior
baseline). Geometric checks compare the menu↔wrapper deltas + self widths/heights (fixture
absolute static-position `y` is page-flow dependent and shifts when the unrelated remaining
ui.css families are aborted — not a family regression). **The dropdown + `.btn-group` blocker
rows are cleared from the M6 matrix.**

**Guards:** `rg` proves 0 dropdown/`.btn-group` selectors remain in ui.css (only comment text)
and no `.show` reintroduced anywhere; M5.2-M5.7 unaffected (dropdown `.show` was already removed
in M5.4; `.dropdown-open` untouched). Prior-phase parity re-verified after the M6.3.5 append:
buttons **25/25**, alerts **29/29**, accordion **0 drift vs M6.2 baseline**, forms **0 style
drift** (diff only flags the M6.3.2-era capture's missing console-error counter — the known
artifact, not style). `git diff --check` clean (CRLF-doc warnings only); `php artisan view:cache`
OK; `vendor/bin/pint --test` passed; PHPUnit **301 / 1419 / 0** (exactly baseline).

**Status — removed from M6 blocking matrix:** accordion (M6.2 + M6.3.1), forms + the ui.css
`:root` design-token block (M6.3.2), buttons base (M6.3.3), alerts + dismissible placement
(M6.3.4), **and dropdown base + `.btn-group` (M6.3.5)** — all app.css-owned via verbatim ports.
Remaining blocker families: list-group, `.table` layout, grid, spacing-3-5/.rounded drift,
text/display/position utilities, modal base. `ui.css` still exists and still linked (8 `<link>`s
intact); **M6.3.5 COMPLETE — next: M6.3.6 list-group.**

### 2026-09-09 - M6.3.6: list-group family migration to app.css (canonical owner)

**Scope (M6.3 dependency order - list-group):** ported the ui.css §4.8 list-group family VERBATIM
to app.css: `.list-group` (flex column, padding-left 0, margin-bottom 0, `.375rem` radius),
`.list-group-item` (relative/block/`.5rem 1rem`/`#212529`/no underline/`#fff`/1px `#dee2e6`), the
corner-inheritance `:first-child`/`:last-child`, the adjacency collapse
`.list-group-item + .list-group-item` (border-top-width 0), the `.disabled,`:disabled`` state
(`#6c757d`/pointer-events none/`#fff`), `.list-group-item-action` (width 100%/
`rgba(33,37,41,.75)`/text-align inherit + `:hover`/`:focus` z-index 1/`#000`/`#f8f9fa` + `:active`
`#212529`/`#e9ecef`), and `.list-group-flush` (radius 0, item border `0 0 1px`, last-item
border-bottom 0). All 12 selectors byte-identical; the block is APPENDED at the end of app.css
(after the M6.3.5 dropdown section) so every equal-specificity border/radius tie resolves exactly
as late-loaded ui.css did — source order preserved within the family (e.g.
`.list-group-flush > .list-group-item:last-child` 0,3,0 beats
`.list-group-flush > .list-group-item` 0,2,0 for the final border-bottom).

**Consumer analysis:** ONE static consumer — `students/update-photo.blade.php`
`<ul class="list-group list-group-flush">` with three `<li class="list-group-item d-flex
justify-content-between align-items-center bg-transparent px-0">` rows containing a `.btn-gold`
link (no `.btn` interplay — list items are never buttons). FOUR runtime consumers add
`list-group-item list-group-item-action` via JS `classList` on client-autocomplete `<ul>`s —
`transactions/create.blade.php`, `transactions/edit.blade.php`, `scanners/scan.blade.php`,
`scholars/_form.blade.php` — identical class-names contract, so BOTH static markup and
runtime-created items resolve identically; no JS/Blade/PHP/DB change; no tabindex/role added,
which is preserved byte-for-byte (the `:focus` half of `:hover,:focus` stays unreachable live, as
in v1). The autocomplete containers keep their inline `position-absolute` +
`width:min(24rem,100%)`/`max-height:150px`/`overflow-y:auto`/`z-index:1000` styles unchanged.
`rg` proves 0 `--ui-list-*` tokens exist anywhere — nothing else references this family.

**Source (two app files):** `resources/css/app.css` — M6.3.6 list-group section APPENDED at the
end (after M6.3.5) with a closed banner comment. `public/css/ui.css` — the §4.8 header comment +
the whole list-group block replaced by a new §4.8 pointer comment (0 list-group selectors remain;
the pointer recounts accordion/forms ownership then declares list-group app.css-owned); §4.x
index paragraph updated to the M6.2→M6.3.6 migrated set. No Blade/JS/PHP/DB change.

**Build:** `assets/app-Cudm12ge.css` (**77.14 kB**, from 76.11 kB — +1.03 kB; JS unchanged
`app-DqsLDVL_.js`). Braces-balanced both files (300/300 app.css, 222/222 ui.css). Bundle contains
all 12 list-group selectors.

**Parity (Gate 1):** new `listgroup-parity.mjs` harness — three-structure fixture on real
`/login` replicating BOTH live compositions (update-photo flush list incl. its bg-transparent/px-0
item styling context; the runtime autocomplete `<ul class="list-group position-absolute bg-white
border">` with `width:min(24rem,100%)` + 3 action items) plus a plain list with a `.disabled`
item; captures wrapper postures, item bases, corner inheritance, adjacency collapse, action
`:hover`/`:focus` (probed via `tabindex="-1"` on the fixture only — live items have no tabindex,
matching v1)/`:active` states, disabled state, and 24rem menu width @375/768/1280. **295/295
assertions equal** the ui.css-derived expected literals at the 14px root: flush radius 0 with
`0 0 1px` item borders and last-item border-bottom 0; autocomplete radius 5.25px/`#fff`/1px
`#dee2e6`; items relative/block/7px 14px/`#212529`/`#fff`; adjacency border-top 0; action base
`rgba(33,37,41,.75)`/inherit; `:hover`/`:focus` `#000`/`#f8f9fa`/z1; `:active` `#212529`/`#e9ecef`;
disabled `#6c757d`/pointer-events none; menu width 336px.

**Deletion simulation (Gate 2 — family survives):** with `ui.css` aborted, **0 computed-style
drift** across every list-group prop @375/768/1280 (only the pre-existing **1 console
error**/viewport — the aborted-stylesheet artifact, numerically identical to every prior
baseline). **The list-group blocker row is cleared from the M6 matrix.**

**Guards:** `rg` proves 0 list-group selectors remain in ui.css (only comment text). Prior-phase
parity re-verified after the M6.3.6 append: buttons **25/25**, alerts **29/29**, dropdown **53/53**,
accordion **0 selector-level drift** — the only 3 diff lines are the known fold/sweep artifact
(x-collapse open-panel `63.1719px`↔`63.0156px` subpixel height + overflow visible↔hidden
post-transition sweep, reproduced identically in a fresh re-run; string-proven no appended
list-group selector can match any accordion fixture element), forms **0 style drift** (diff only
flags the cross-mode `current=0 sim=1` console-error counter vs the M6.3.2-era capture — the
known artifact; same-mode current 0↔0 and simulated 1↔1 are identical). `git diff --check` clean
(CRLF-doc warnings only); `php artisan view:cache` OK; `vendor/bin/pint --test` passed; PHPUnit
**301 / 1419 / 0** (exactly baseline).

**Status — removed from M6 blocking matrix:** accordion (M6.2 + M6.3.1), forms + the ui.css
`:root` design-token block (M6.3.2), buttons base (M6.3.3), alerts + dismissible placement
(M6.3.4), dropdown base + `.btn-group` (M6.3.5), **and list-group (M6.3.6)** — all app.css-owned
via verbatim ports. Remaining blocker families: `.table` layout, grid, spacing-3-5/.rounded drift,
text/display/position utilities, modal base. `ui.css` still exists and still linked (8 `<link>`s
intact); **M6.3.6 COMPLETE — next: M6.3.7 table layout.**

---

## M6.3.7 (2026-09-09) — `.table` layout family ownership migrated to `app.css`

**What changed:** ported the ui.css §4.6 `.table` layout family VERBATIM (all 7 selectors, 572
chars) to end-of-file `resources/css/app.css` and removed it from `public/css/ui.css` (pointer
comment + §4.x index updated to the M6.2→M6.3.7 migrated set; M5.7 overflow-helper comment
example list refreshed). The element-level Reboot `table`/`caption`/`th`/`thead,tbody,tfoot,tr,td,th`
rules (ui.css §6, l.513-516) were NOT migrated — a separate family whose byte-identical twins
already exist in `app.css` (`@layer base`, l.447-450). `public/css/datatables.css` untouched
(hash `B82937375133CABD` unchanged; B2 seam intact). Migration done via a committed
`_m637_table.mjs` node harness (braces balanced afterwards: 307/307 app.css, 215/215 ui.css).

**Affected files:** `resources/css/app.css` (M6.3.7 table section appended), `public/css/ui.css`
(§4.6 removed → pointer; index + comments only). No Blade, JS, schema, or DB changes.

**Consumers (untouched):** `.table-responsive` only at `transactions/index.blade.php:130`;
`.table` on the plain screens (sessions/online, 4 admin permission matrix tables,
households/show members) and all 11 DataTables id-tables (`table table-sm`/`table … align-middle`);
`.align-middle` in 12 files, all table cells (datatables.css scoped rule re-covers DataTables).
  Dashboard l.197 plain table is Tailwind-only (no `.table`).

**Verification (all green):** build `app-4w0-fGky.css` (77.53 kB, +0.39; JS unchanged
`app-DqsLDVL_.js`); table-parity harness (plain + `table-sm` + `.table-responsive` wide + real
datatables.css-injected DataTables wrapper + `.align-middle` cells @375/768/1280) **246/246
assertions** equal the 14px-root literals — table 100% width / margin-bottom 14px (=1rem) /
vertical-align top / border-collapse collapse / border-color `#dee2e6`; caption bottom/`#6c757d`/7px;
thead bottom, tbody inherit→top; cells 7px-`#000`-`#fff`-1px; `.table-sm` 3.5px; `.align-middle`
middle; `.table-responsive` overflow-x auto (+ wide table scrolls inside the wrapper, page never
widens); datatables.css skin overrides verified still winning identically (separate/middle/6px/
`.25rem`/`#212529`). **Deletion simulation** (`ui.css` aborted): **0 computed-style drift** across
the whole table matrix @375/768/1280 (only the known 1 console error/viewport artifact);
**`.table` layout blocker row CLEARED.** Guards: `rg` → 0 `.table`/`.table-responsive`/`.align-middle`
selectors in ui.css; prior-phase parity re-verified — buttons 25/25, alerts 29/29, dropdown 53/53,
list-group 295/295, accordion 0 selector-level drift (its 3 diff lines are the known fold/sweep
subpixel artifact), forms 0 style drift (cross-mode console-counter artifact only);
`git diff --check` clean (CRLF-doc warnings only); `view:cache` OK; Pint passed; **PHPUnit
301 / 1419 / 0** (exactly baseline).

**Status:** M6.3.7 COMPLETE — the `.table` layout family is app.css-owned and self-contained
against ui.css deletion. `.table` joins accordion, forms, buttons, alerts, dropdown + `.btn-group`,
and list-group as migrated families. Remaining blocker families: grid, spacing-3-5/.rounded drift,
text/display/position utilities, modal base. `ui.css` still exists and still linked (8 `<link>`s
intact); **M6.3.7 COMPLETE - next: M6.3.8 grid (NOT STARTED).**

---

## M6.3.8 (2026-09-09) — grid family ownership migrated to `app.css`

**What changed:** ported the ui.css grid block VERBATIM (l.428-461 — `.row`, `.row > *`, `.g-0`
…`.g-5` gutter-token overrides, `@media (min-width: 576px)` `.col-sm-4`/`.col-sm-8`, `@media
(min-width: 768px)` `.col-md-3`/`.col-md-4`/`.col-md-6`; 37 lines, 1288 chars incl. the §4 comment
header) to end-of-file `resources/css/app.css` and removed it from `public/css/ui.css` (pointer
comment + §4.x index updated to the M6.2→M6.3.8 migrated set — still-owned now: spacing,
text/display/position utilities, modal base). This was the FINAL grid block; `app.css` had zero
grid selectors before the port. The `--ui-gutter-x/y` tokens are self-contained inside the block
(no `:root` declaration anywhere), so canonical ownership moved with it. Adjacent `.gap-3/4/5`
spacing rules (l.423-426) were NOT moved (spacing family, M6.3.9). `public/css/datatables.css`
untouched (now recorded with full MD5 `2EE627B74B0FD170727506AFD46C6C46`; B2 seam intact).
Migration done via the `_m638_grid.mjs` node harness (braces balanced afterwards: net 0 both files).

**Affected files:** `resources/css/app.css` (M6.3.8 grid section appended), `public/css/ui.css`
(grid block removed → pointer; index + comments only). No Blade, JS, schema, or DB changes.

**Consumers (untouched):** static `.row` — grantee_update/`_self_update_tab` + grantee_update/
`self-service` (each `row g-2 mb-3` + 7 plain `row`), unpaid_verifications/`self-service`
(`row g-2`), unpaid_verifications/`index` (`row`), payouts/`attendance` (`row`), qr/`viewer`
(`row g-2 mb-3`); **exactly 4 `row g-2` consumers**; `g-1`/`g-2` on ~35 files are on non-row
elements → inert (no `--ui-gutter` consumer there); `filter-chips-row`/`flex-row` false positives.
Columns: `col-md-3` ×24, `col-md-4` ×18, `col-md-6` ×8, `col-sm-4` ×12, `col-sm-8` ×12. Dynamic:
2 JS template literals emit `<dl class="row">` with `dt.col-sm-4`/`dd.col-sm-8` pairs
(payouts/attendance l.277, unpaid_verifications/index l.259) — class contract unchanged, no
classList manipulation of row/col/g. No nested grids exist (all `.row`s siblings inside
`.data-card`; synthetic nested fixture tested defensively). No header grid composition.

**Verification (all green):** build `app-B2ZahIOJ.css` (78.37 kB, +0.84 ≈ grid block size; JS
unchanged `app-DqsLDVL_.js`); grid-parity harness (fixtures on real `/login` @375/576/768/1280 —
`row g-2 mb-3` + 2×`col-md-6`, plain `row` + 4×`col-md-3`/3×`col-md-4`/2×`col-md-6`, nested
`row`, `dl.row` + `dt.col-sm-4`/`dd.col-sm-8` pairs, `g-0..g-5` token probes) **pre→post drift 0
and deletion-sim (`ui.css` aborted) drift 0 at every viewport**, 25/25 locked assertions equal the
14px-root literals (row margins -10.5px default / -3.5px `g-2`, child padding 10.5px/3.5px, flex
`0 0 auto`, width ratios 0.25/0.333/0.5, `dl` dt 33.3%/dd 66.7%, nested inner 0.25, gutter tokens
0/0 → 3rem/3rem, cols stack full-width at 375); sim keeps only the known aborted-stylesheet
console artifact (1/viewport); no page overflow at 375/576. **Grid blocker row CLEARED.**
Guards: `rg` → 0 real grid selectors in ui.css (pointer/comment text only); datatables.css
untouched this phase (mtime pre-session, zero writes); prior-phase parity re-verified — buttons
25/25, alerts 29/29, dropdown 53/53, list-group 295/295, table 246/246, accordion/forms 0 drift;
`git diff --check` clean (CRLF-doc warnings only); `view:cache` OK; Pint passed; **PHPUnit
301 / 1419 / 0** (exactly baseline).

**Status:** M6.3.8 COMPLETE — the grid family is app.css-owned and self-contained against ui.css
deletion. Grid joins accordion, forms, buttons, alerts, dropdown + `.btn-group`, list-group, and
`.table` as migrated families. Remaining blocker families: spacing-3–5/.rounded drift, text/
display/position utilities, modal base. `ui.css` still exists and still linked (8 `<link>`s
intact); **M6.3.8 COMPLETE - next: M6.3.9 spacing-3–5/.rounded drift (NOT STARTED).**

## M6.3.9 (2026-09-09) — remaining utility families ownership migrated to `app.css`

**What changed:** migrated ALL remaining `public/css/ui.css` §4.9 utility families to end-of-file
`resources/css/app.css` (the last unowned utility rules in ui.css — spacing, radius, text
align/color, display, position, border, background, typography size helpers) in two classes.
**(1) PORTED VERBATIM (63 rules, Bootstrap `!important` retained):** `d-inline`, `d-flex`;
`justify-content-center`/`-between`; `align-items-center`/`-end`; `position-relative`/`-absolute`;
`text-start`/`text-center`; `text-primary`/`-secondary`/`-success`/`-danger`/`-warning`/`-info`/
`-muted`/`-white`; `fs-4`; `small`; `min-vh-100`; `w-auto`; `img-fluid`/`img-thumbnail`;
`bg-white`/`bg-transparent`; `border`/`border-2`; `rounded`/`rounded-pill`; `m-0`, `mt-0..5`,
`mb-0..4`, `ms-1`/`ms-2`; `p-1..4`, `pt-3`, `pb-1`/`pb-3`, `px-0/1/3/4/5`, `py-1..5`; `gap-3`/`gap-4`.
Even the value-exact Tailwind twins (`text-center`/`text-white`/`bg-white`/`bg-transparent`/
`m-0`/`mb-0..4`/`mt-1/2`/`ms-1/2`/`p-1..4`/`px-0/1/3/4/5`/`py-2..5`/`w-auto`/`rounded-pill`) were
ported: they carry `!important`, so retiring them would require dropping the flag + proving no
competing unlayered declaration — the byte-verbatim port guarantees parity by construction.
**(2) REMOVED AS DEAD (60 rules — 0 Blade + 0 JS + 0 compiled-Tailwind-twin refs):** `d-block`,
`d-inline-flex`, `flex-nowrap`, `justify-content-start`/`-end`/`-around`, `align-items-start`,
`me-auto`, `position-static`/`-fixed`/`-sticky`, `text-end`, `text-body`,
`fw-light`/`-normal`/`-semibold`/`-bold`, `fst-italic`, `overflow-auto`, `border-1`/`-3`,
`rounded-1`/`-2`/`-3`/`-circle`, `gap-0`/`-5`, `me-0`/`-1`/`-2`, `ms-0`/`-3`, `mx-0..3`, `my-0..3`,
`mt-auto`, `mb-auto`, `mb-5`, `p-0`/`-5`, `pt-0`/`-1`/`-2`/`-4`/`-5`, `pb-0`/`-2`/`-4`/`-5`,
`ps-0..3`, `px-2`, `py-0`. No RETIRE-to-Tailwind performed. Applied via the `_m639_apply.mjs`
node harness (braces balanced afterwards: net 0 both files).

**Affected files:** `resources/css/app.css` (M6.3.9 block APPENDED at end — 2488 → 2595 lines,
4,399 B of rules), `public/css/ui.css` (lines 229–437 replaced by the M6.3.9 pointer comment + the
updated §4.x index = M6.2→M6.3.9 migrated set + restored §4.10 Reboot/type comment — 662 → 483
lines; **0 real utility selectors remain**). No Blade, JS, schema, or DB changes.

**Cascade:** app.css loads first, ui.css second; the ported rules are **unlayered `!important`**, so
they beat the Tailwind `@layer utilities` twins exactly as ui.css-last did; end-of-file position
keeps every same-specificity tie with the earlier app.css component groups resolving identically.

**Consumers (untouched):** live per-class Blade counts verified during the migration (39 consumed
classes / 1,148 Blade refs + 115 JS refs — top: `mb-2` 117, `m-0` 61, `text-center` 56, `text-white`
39, `bg-white` 24, `d-flex` 20, `mt-3` 7, `gap-3` 13, `d-inline` 3, `min-vh-100` 8, `w-auto` 7).
JS side: classList / toggleClass / className touches only add/remove the exact class names — no
selector strings changed; runtime-contract greps clean.

**Verification (all green):** build (vite 6.4.3) → `app-PXXoPz1A.css` (80.08 kB, +1.71 vs 78.37;
JS unchanged `app-DqsLDVL_.js`); compiled asset contains every ported rule — spot-verified
unlayered, AFTER all `@layer` blocks, `!important` intact. New `utils-parity.mjs` harness
(utilities probe matrix replicating all 63 ported rules + the M6.3.8 grid-slot re-probes) on real
`/login` @375/576/768/1280: **CURRENT vs deletion-sim (`ui.css` aborted) drift 0** (utils + grid,
every viewport); **grid slot vs M6.3.8 baseline (`grid-post638`) drift 0** and vs the pre-638
baseline drift 0 (regression net REGRESSION-1); sim chain post638→now drift 0 (REGRESSION-2);
docOverflow 0; sim keeps only the known aborted-stylesheet console artifact (1/viewport). Guards:
prior-phase parity re-verified (buttons 25/25, alerts 29/29, dropdown 53/53, list-group 295/295,
table 246/246, accordion/forms 0 drift); datatables.css untouched (full MD5
`2EE627B74B0FD170727506AFD46C6C46`, B2 seam intact); `git diff --check` clean (CRLF-doc warnings
only); `view:cache` OK; Pint passed; **PHPUnit 301 / 1419 / 0** (exactly baseline). Contrast
audit: ported text colors keep Bootstrap AA-exact values on white (primary 4.50, secondary 4.69,
success 4.53, danger 4.53, muted 4.69) — warning/info are Bootstrap's default light-background
colors, byte-identical to today's live palette (no regression).

**Status:** M6.3.9 COMPLETE — all remaining utility families are app.css-owned and self-contained
against ui.css deletion. 63 rules ported verbatim + 60 proven-DEAD rules removed; 0 utility
selectors remain in ui.css. Utilities join accordion, forms, buttons, alerts, dropdown +
`.btn-group`, list-group, `.table`, and grid as migrated families. Remaining ui.css-owned: the §5
shared-component classes (`.status-badge`/`.metric-card`/`.data-card`/`.ui-notice`/`.ui-empty`/
`.ui-micro-label`/`.ui-skip-link`), page-link pagination, the Reboot+type element rules, and the
**modal base**. `ui.css` still exists and still linked (8 `<link>`s intact); **M6.3.9 COMPLETE -
next: M6.3.10 modal base (NOT STARTED).**

## M6.3.10 (2026-09-09) — modal family verdict: NOTHING to migrate (all live modals are Alpine + Tailwind)

**What changed:** the M6.3.10 modal-base step resolved to an **audit proving zero ownership**. An
exhaustive inventory found **0 modal-family CSS rules in `ui.css`**, `app.css`, or the compiled
build — every live modal (confirm, record-view, client-form, client-feedback, photo-upload,
scanners, gip, self-service, admin/user, audit_logs, scholars, details-panel) is an **Alpine +
Tailwind composition**: `pointer-events-none fixed inset-0 z-[200]` overlay (feedback `z-[210]`) +
static `pointer-events-auto absolute inset-0 bg-ink/40` backdrop + centered dialog, all driven by
`x-show` + `x-transition.opacity.duration.200ms`, Alpine Esc/backdrop/tab-trap handlers, and the
store bridges (`uiConfirm`, `uiViewModal`, `clientFormModal`, `clientFeedbackModal`,
`window.uiConfirm`/`window.uiViewModal`/`window.showClientFeedback`). Vestigial
`.gip-modal-close`/`.rvm-close`/`.details-panel` class names have **no CSS rules anywhere** (styling
via Tailwind utilities in the attribute). The only application change was a **comment-only** §4.x
index correction in `public/css/ui.css` (the stale "and the modal family" still-owned claim was
replaced with the verified 0-rule statement; 483 → 493 lines, MD5 `5D6C2B20…` → `6EB8DA64…`). No
Blade, JS, `app.css`, `datatables.css`, schema, or DB change. Build output byte-identical
(`app-PXXoPz1A.css` 80.08 kB / `app-DqsLDVL_.js`).

**Modal-critical utilities confirmed in the compiled asset (post-edit rebuild net):**
`z-[200]{z-index:200}`, `z-[210]{z-index:210}`, `bg-ink/40`, `max-w-[500px]`/`[800px]`,
`max-h-[85vh]`/`[90vh]`; compiled modal-selector net `rg` = **0**.

**Verification (all green):**
* **Deletion-sim parity (`modal639.mjs`):** four live-shaped modal compositions (confirm 500,
  record-view 85vh/800 + 1400px body, client-form navy 90vh/800, feedback 60vh/z-210) fixture-
  injected on real `/login`; computed styles + bounding boxes + centering offsets + scroll
  containment, open + closed state, **@375/576/768/1280**, CURRENT vs `ui.css`-aborted → **drift 0
  every viewport**, docOverflow 0, sim keeps only the known aborted-stylesheet console artifact
  (1/vp).
* **Interaction / a11y / responsive (`modal-interact.mjs`, real partial bridge code driven through
  Alpine 3.17 on /login):** **27/27 @375 and @1280** — open/visible, message/title/body wiring,
  body scroll-lock + release on every close path, focus-on-open (confirm accept, `.rvm-close`,
  feedback action target — focus traces confirm the intended targets), Escape→resolves false,
  backdrop-click→resolves false, Cancel→false, Confirm→true, tab-trap wrap, reopen idempotency,
  85/90/60vh internal scroll containment with no page scroll, onHidden callback + focus restore,
  z-layering 200 vs 210. Two focus-landing checks were converted to deterministic selection-level
  assertions (injected-DOM Alpine init race made instantaneous checks flaky; app code is untouched).
* **Guards:** build byte-identical; `view:cache` OK; Pint passed; `git diff --check` clean (CRLF
  doc warnings only); **PHPUnit 301 / 1419 / 0** (exactly baseline); `ui.css` grep → 0 real modal
  selectors (2 comment matches only).

**Affected files:** `public/css/ui.css` (comment-only), plus this log + `UI_CSS_RETIREMENT_PLAN.md`
+ `SESSION_HANDOFF.md`.

**Status:** M6.3.10 COMPLETE — modal ownership migrated (nothing existed to move); `ui.css`
retained + linked; deletion gate still BLOCKED. Remaining ui.css-owned: §5 shared components,
page-link pagination, the Reboot+type element rules. **Next: final dependency re-audit
(NOT STARTED).**

## M6.4 (2026-09-10) — final dependency re-audit: deletion gate BLOCKED (real drift proven)

**What changed:** audit-only. No application, CSS, JS, view, schema, or database file
changed. Built a Chromium deletion-simulation harness at
`C:\Users\J\AppData\Local\Temp\opencode\m64\m64-audit.mjs` (report:
`C:\Users\J\AppData\Local\Temp\opencode\m64\m64-report.json`) that sweeps 6 reachable public
pages (`/login`, `/qr-viewer`, `/grantee-update`, `/unpaid-verification`,
`/student/photo-upload`, `/student/update-photo`) at 375/576/768/1280px under **FULL**
(`ui.css` loaded) vs **SIM** (`ui.css` request aborted via `page.route`). Because the
auth-gated dashboard screens are untestable live (no smoke seed in `tbl_users`), the
dashboard §5/§2/§3/§4.10 composition is replicated by injecting the documented §6.5
fixture on `/login`. Captures computed styles + pseudo-elements + layout rects for every
ui.css-owned token, console/page errors, failed requests, `window.bootstrap`, doc
overflow, and two interactions (metric-card hover, skip-link focus) with reduced-motion
emulation. Colors are normalized via 1×1 canvas to sRGB so oklab-vs-rgb representation
of the *same* color is not counted as drift (59 fixture probes captured per login run).

**Deletion-sim drift (FULL vs SIM), 24/24 cells (6 pages × 4 viewports), status 200 all,
0 page errors, 0 console errors in FULL (SIM pages: 1 expected aborted-stylesheet
artifact), 0 non-ui.css failed requests, `window.bootstrap` undefined in all 48 captures,
0 doc overflow:**

- **LIVE public pages — `.data-card` drifts on all 7 reachable heads, every viewport:**
  border 1px solid `#E2E5EA` → 0; `box-shadow` set changes (drops `shadow-md` frames,
  gains a `#E2E5EA` 1px ring); wrapper height −2px. Everything else on those pages is
  value-identical (the R5/R9 cells are the same `.data-card` propagation).
- **Dashboard §5 (fixture on `/login`, 28 drifting probes / 59):**
  `.status-badge` `::before` dot 6px → 5.25px (13 variants, badge width −0.75px);
  `.metric-card` (4 variants) border 1→0, radius 12→16px, padding 20→17.5px, shadow
  gains ring, height 73→66px, accent bar 373→375px; `.data-card-header/-body/-footer`
  padding/gap shrink (16/20/12 → 14/17.5/10.5); `.ui-empty` padding 24/16 → 21/14
  (dead class, 0 consumers); `.ui-skip-link` focus `left` 0px → 14px.
- **Value-identical (no prop drift, only layout cascade):** `.ui-notice`,
  `.ui-micro-label`, `.metric-value`, Reboot/type element rules, `:focus-visible`,
  `prefers-reduced-motion` (transitionDuration 1e-5s in both modes), datatables
  `.page-link` (real tables covered by the scoped app.css twin + datatables.css; the
  fixture's page-link drift is a scope artifact — no `.dataTables_wrapper` parent).
- **Interactions:** metric-card hover loses `translateY(-2px)` + 0.2s/0.2s transition in
  SIM (shadow composition changes too); skip-link focus pill renders identically
  (bg-surface/ink, radius 8, z-50) with only the `left` offset differing (0 vs 14px).

**Verdict / reason for BLOCKED:** deleting `ui.css` changes live visual output
immediately — public cards' borders/shadows, dashboard cards' border/radius/padding/gap,
status-badge dot metrics, metric-card padding/lift hover, and the skip-link focus offset.
The plan's §1 no-redesign and §7 no-arbitrary-value constraints (verified above) make
these `!p-[1.75rem]`/`gap-3`-style recompositions a post-gate follow-up, not an M6 task.

**Affected files:** none (docs only — this log, `UI_CSS_RETIREMENT_PLAN.md`,
`SESSION_HANDOFF.md`).

**Verification:** `npm run build` green (app-*.css regenerated), `php artisan view:cache`
OK, `vendor\bin\pint --test` passed, `php artisan test` 301 passed / 1419 assertions / 0
failures, `git diff --check` clean (CRLF notices only).

**Status:** **M6.4 COMPLETE — ui.css deletion-readiness audit found blockers; ui.css
retained + linked; actual deletion NOT STARTED.**

## M6.5 (2026-09-10) — .data-card base ownership migrated ui.css → app.css (first component unblocked)

**What changed:** CSS ownership migration, first M6.4 blocker removed. `.data-card` (base
rule only) moved from `public/css/ui.css` (§5 cards block, rules l.417-424) to an
end-of-file M6.5 section in `resources/css/app.css`, copied VERBATIM - same selector,
same four declarations, EXACT values:

    .data-card {
        background: var(--ui-card);
        border: 1px solid var(--ui-border);
        border-radius: var(--ui-radius-lg);
        box-shadow: var(--ui-shadow-sm);
    }

The consumed tokens are already canonical in the app.css `:root` block (M6.3.2 owner):
`--ui-card #FFFFFF`, `--ui-border #E2E5EA`, `--ui-radius-lg 12px`,
`--ui-shadow-sm` (0 1px 3px rgba(15,27,45,.06), 0 1px 2px rgba(15,27,45,.04)) - so the
port has zero residual token dependency on ui.css. The `ui.css` block was replaced with a
pointer comment; `.data-card-header/-body/-footer` slot rules remain ui.css-owned (out of
scope). `ui.css` is still present and linked in all 8 `<link>` tags; no link removed, no
Bootstrap/CDN change. `datatables.css` byte-identical (MD5 `2ee627b7...6c46`, 595 lines).
Baselines before edit: ui.css 493 lines MD5 `6eb8da64...000b`; app.css 2594 lines MD5
`dcb08b1b...72f`. After: ui.css 492 lines MD5 `5d00dcce...87d1`; app.css 2611 lines MD5
`7b480c5c...9406`. Rebuilt via `npm run build` -> `app-CrhEbRXG.css` 80.21 kB (was
`app-PXXoPz1A.css` 80.08 kB; JS `app-DqsLDVL_.js` unchanged). Compiled CSS confirmed to end
with the verbatim rule as the LAST `.data-card` rule (after the Batch C Tailwind twin at
source l.734), so every equal-specificity tie resolves exactly as late-loaded ui.css did.

**Scope guard:** ONLY `.data-card` base touched. `.metric-card`, `.status-badge` (13
variants), `.data-card-header/-body/-footer`, `.ui-notice`, `.ui-empty`,
`.ui-micro-label`, `.ui-skip-link`, `.page-link`/`.page-item`, buttons, alerts,
dropdowns, accordions, tables, grid, utilities, modals, DataTables, forms, Reboot/type,
tokens - NOT migrated. No PHP/controller/model/route/schema/JS/Alpine/auth/Blade changes
(no selector mismatches to fix). Consumers are all static Blade (`rg "data-card"`: 104
matches across resources/views, 44 files; no JS-generated `.data-card`).

**Deletion-sim re-run (same m64-audit.mjs harness, 24/24 cells, 60s goto timeout):**
status 200 ALL cells (0 non-200), 0 page errors, 0 console errors FULL (SIM: 1 expected
aborted-stylesheet artifact), 0 doc overflow, `window.bootstrap` undefined in all 48
captures. Live public pages now R0/O0/F0 at all 4 viewports (was R5/R9 on `.data-card`
heads in M6.4). `/login` fixture: real cells R28→R20, owned O33→O32, fixture F28 (unchanged
count - the same blocker set minus `.data-card`).

**`.data-card` itself: ZERO drift.** Fixture probe `dc-plain` computed style - border
widths 1px/0 (4 props), border-style solid/none, border-color `#E2E5EA` vs `#212529`,
`box-shadow` ui-shadow-set vs ring-remix (all drifted in M6.4) - are now IDENTICAL
FULL==SIM. Background, radius, padding, and layout border-box all match. Residual
`rect.height 202.172px → 190.172px` and `rect.top` shift are CONTENT-driven: the fixture
card's children (`.status-badge` `::before` dot 6→5.25px, `.metric-value`, etc.) still
render smaller in SIM because those families remain ui.css-owned (documented blockers,
unchanged). FULL geometry is byte-identical to the M6.4 baseline (height 202.172px, top
1961.922px both runs) - zero visual regression for real users. Real dashboard element
`.data-card~22` shows the same single content-cascade signature.

**Unchanged residual (M6.4 blockers, out of M6.5 scope):** `.status-badge` dot/font,
`.metric-card` border/radius/padding/shadow/hover-lift, `.data-card-header/-body/-footer`
padding/gap, `.ui-skip-link` focus `left` 0→14px, `.ui-empty` (dead). Interactions and
reduced-motion identical to M6.4: metric-card hover loses `translateY(-2px)` + 0.2s/0.2s
in SIM; skip-link focus pill otherwise identical; reduced-motion transitionDuration
1e-5s both modes.

**Environment note (unrelated to M6.5):** midway through verification the local MariaDB
crashed/wedged (InnoDB "LSN in the future" warnings on ibdata1 space 0 pages 8/438, no
clean shutdown after a restart) and the long-lived `php artisan serve` process hung
(requests returned 0 bytes). MySQL auto-respawned healthy at 15:23 (verified: root
query + `/login` 200 in ~0.4s); the web dev server was restarted on 127.0.0.1:8000. No
data loss observed; no schema/database was modified (AGENTS bytes-identical rule held).

**Affected files:** `resources/css/app.css` (M6.5 end-of-file section, +17 lines),
`public/css/ui.css` (only the `.data-card` block removed, +comment -8 lines), plus this
log, `UI_CSS_RETIREMENT_PLAN.md`, `SESSION_HANDOFF.md`.

**Verification:** `npm run build` green (app-CrhEbRXG.css 80.21 kB), compiled CSS tail
check confirmed verbatim rule last, `php artisan view:cache` OK, `vendor\bin\pint --test`
passed, `php artisan test` 301 passed / 1419 assertions / 0 failures, `git diff --check`
clean (CRLF notices only).

**Status:** **M6.5 COMPLETE — .data-card ownership migrated to app.css; ui.css retained + linked; remaining shared-component blockers NOT STARTED.**

## M6.6 (2026-09-10) — .metric-card ownership migrated ui.css → app.css (hover-lift blocker cleared)

**What changed:** CSS ownership migration, second M6.4 blocker removed. The ENTIRE
`.metric-card` rule set (base + `:hover` + `::before` + `.accent-gold/-teal/-red`) moved
from `public/css/ui.css` (§5 metric-card block, old l.380-407) to an end-of-file M6.6
section in `resources/css/app.css`, copied VERBATIM - same selectors, same declarations,
EXACT values:

    .metric-card {
        position: relative;
        overflow: hidden;
        background: var(--ui-card);
        border: 1px solid var(--ui-border);
        border-radius: var(--ui-radius-lg);
        box-shadow: var(--ui-shadow-sm);
        padding: var(--ui-space-5);
        transition: transform var(--ui-duration) var(--ui-ease),
                    box-shadow var(--ui-duration) var(--ui-ease);
    }
    .metric-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--ui-shadow-md);
        background: var(--ui-card-hover);
    }
    .metric-card::before {
        content: "";
        position: absolute;
        inset: 0 0 auto 0;
        height: 3px;
        background: var(--ui-accent, var(--ui-navy));
    }
    .metric-card.accent-gold { --ui-accent: var(--ui-gold); }
    .metric-card.accent-teal { --ui-accent: var(--ui-teal); }
    .metric-card.accent-red  { --ui-accent: var(--ui-red); }

All 13 consumed tokens are canonical in the app.css `:root` block (M6.3.2 owner):
`--ui-card/-border/-radius-lg/-shadow-sm/-shadow-md/-space-5/-duration/-ease/
-card-hover/-navy` plus accent literals `--ui-gold/-teal/-red` (verified values match the
M6.5 audit: `--ui-card` #FFFFFF, `--ui-border` #E2E5EA, `--ui-radius-lg` 12px,
`--ui-space-5` 20px, `--ui-duration` 200ms, `--ui-ease` cubic-bezier(.4,0,.2,1),
`--ui-card-hover` #FAFBFC, `--ui-shadow-md` 0 4px 6px -1px rgba(15,27,45,.06) + 0 2px 4px
-2px rgba(15,27,45,.04), `--ui-navy` #0038A8). `--ui-accent` is a per-instance custom
property set by the accent classes; `::before` falls back to `--ui-navy`. Zero `--bs-*`
and zero residual ui.css token dependency after the port. The ported plain unlayered rules
land AFTER the Batch C `.metric-card` Tailwind twin (source l.773), so every
equal-specificity tie (0,1,0 base; 0,2,0 hover + accent + `::before`) resolves exactly as
late-loaded ui.css did - real 1px border-box border, 12px radius, 20px padding,
`--ui-shadow-sm` frame, hover `translateY(-2px)` + `--ui-shadow-md` + `--ui-card-hover`.
`ui.css` block replaced with a pointer comment; `.metric-value` (child) remains
ui.css-owned (out of M6.6 scope). `ui.css` still present + linked in all 8 `<link>` tags;
`datatables.css` byte-identical (MD5 `2ee627b7...6c46`, 595 lines). Baselines before edit
(M6.5 end-state): ui.css 492 lines MD5 `5d00dcce...87d1`; app.css 2611 lines MD5
`7b480c5c...9406`. After: ui.css 468 lines MD5 `cc38e378...e35`; app.css 2653 lines MD5
`f23e2324...d174`. Rebuilt via `npm run build` → `app-C29NMCAt.css` 80.78 kB (was
`app-CrhEbRXG.css` 80.21 kB; JS `app-DqsLDVL_.js` unchanged). Compiled CSS verified: the
ported `.metric-card{` base (border-radius `var(--ui-radius-lg)`), `.metric-card:hover`,
`.metric-card::before` (`var(--ui-accent,var(--ui-navy))`) and
`.metric-card.accent-*{--ui-accent:...}` are the LAST `.metric-card` rules in the bundle,
after the M6.5 `.data-card` block.

**Scope guard:** ONLY the 7-selector `.metric-card` rule set touched. `.data-card`,
`.data-card-header/-body/-footer`, `.status-badge`, `.ui-notice`, `.ui-empty`,
`.ui-micro-label`, `.ui-skip-link`, `.page-link`, `.metric-value` and every other
remaining ui.css declaration NOT migrated. No PHP/controller/model/route/schema/JS/
Alpine/auth/Blade changes. Consumers are all static Blade: `rg "metric-card"` → exactly 4
matches, all in `resources/views/dashboard.blade.php` (KPI cards: plain + accent-gold/
-teal/-red; each contains an icon chip row + a `.metric-value` child + a label). No
JS/Alpine/dynamic consumers; no injection of `.metric-card` from JS.

**Cascade proof (port added, ui.css still loaded):** FULL snapshot of the injected
fixture (`snap-mc.cjs`, /login, 1280px) before port == after port byte-for-byte: radius
12px, padding 20px, border 1px `#E2E5EA`, box-shadow `--ui-shadow-sm` frames, height
73.078px, `::before` 3px, accents `rgb(252,209,22)`/`rgb(45,139,122)`/`rgb(206,17,38)`/
`rgb(0,56,168)` (gold/teal/red/navy) - hover `transform matrix(1,0,0,1,0,-2)`,
`--ui-shadow-md`, `#FAFBFC`, `transition-duration 0.2s, 0.2s`. No unintended cascade
change; identical FULL→SIM.

**Deletion-sim re-run (same m64 harness, 24/24 cells, 60s goto):** status 200 ALL cells,
0 page errors, 0 FULL console errors, 0 doc overflow, `window.bootstrap` undefined in all
48 captures, 0 failed non-ui.css requests. Live public pages R0/O0/F0 at all 4 viewports
(unchanged). `/login` fixture: R20→R14, O32→O24, F28→F23 - exactly the `.metric-card`
set (4 cards + borderline `metric-value` propagation) cleared.

**`.metric-card` itself — ZERO drift:** fixture probes mc-gold/mc-teal/mc-red/mc-plain
vanish from fixtureDrift, and `.metric-card`/`.metric-value` vanish from ownedDrift.
Border width/color/style, radius, padding, margin, background, shadow, box-sizing,
overflow, transition, ::before height/background all FULL==SIM at every viewport.
Hover-lift PRESERVED under SIM: `mcHover` FULL == SIM (`transform matrix(1,0,0,1,0,-2)`,
`--ui-shadow-md`, `#FAFBFC`, 0.2s/0.2s) - previously SIM lost the lift + dropped to a
single 0.2s duration. Reduced-motion: transitionDuration 1e-5s + transform IDENTICAL in
both modes (was `transform:matrix(...-2)` vs `transform:none`). Responsive: identical
R/O/F counts at 375/576/768/1280 (metric-card drift 0 at every breakpoint), 0 horizontal
overflow, cards inside the KPI grid stack per the existing `grid-cols-1 sm:grid-cols-2
xl:grid-cols-4` (unchanged markup).

**Child-component isolation (verified, NOT silently fixed):** the M6.4/5 `.metric-value`
drift was purely PARENT-GEOMETRY propagation - its only drifted props were `rect.width`
1238.0px vs 1245.0px, `rect.top`, `rect.left` 21.0 vs 17.5px (padding+border cascade from
the card). Its OWN computed style (font-family/size/weight/leading/color) never differed.
Now the card geometry matches, so those propagate zero. `.metric-value` remains
ui.css-owned and `.status-badge` (13 variants) still drifts as before — metric-card
migration ≠ status-badge migration ≠ metric-value migration.

**Remaining ui.css blockers (M6.4 evidence, unchanged):** `.status-badge` `::before` dot
6→5.25px ×13 variants, `.data-card-header/-body/-footer` padding/gap 16/20/12→14/17.5/
10.5, `.ui-skip-link` focus `left` 0→14px, `.ui-empty` (dead), `.data-card` base (M6.5-
clean) leaving only content-cascade on dc-plain, `.ui-notice`/`.ui-micro-label`/
`.metric-value` value-identical, Reboot/type element probes (`fixture`), datatables
`.page-link` scope artifact. `M6.4 -> M6.5 -> M6.6 regression chain`: datatables focus
seam + datatables.css byte-identical, `.hidden`, `.dropdown-open`, `.accordion-open`,
`.btn-close`, utilities, buttons, alerts, dropdown/btn-group, list-group, tables, grid,
modal, `.data-card` — none altered.

**Bootstrap-free guards:** Bootstrap CSS CDN 0, JS CDN 0, `window.bootstrap` undefined
(48 captures), active `--bs-*` consumption 0 (resources/css + public/css = 0), Bootstrap
npm dependency absent (`package.json` 0 refs), `dataTables.bootstrap5.min.js` refs
unchanged (intentional class emitter), `datatables.css` MD5 unchanged.

**Affected files:** `resources/css/app.css` (M6.6 end-of-file section, +42 lines),
`public/css/ui.css` (only the 7 `.metric-card` rules removed, +pointer comment -24
lines), plus this log, `UI_CSS_RETIREMENT_PLAN.md`, `SESSION_HANDOFF.md`.

**Verification:** `npm run build` green (app-C29NMCAt.css 80.78 kB), compiled-CSS tail
check (ported `.metric-card` family LAST), `php artisan view:cache` OK,
`vendor\bin\pint --test` passed, `php artisan test` 301 passed / 1419 assertions / 0
failures, `git diff --check` clean (CRLF notices only), `/login` HTTP 200.

**Status:** **M6.6 COMPLETE — .metric-card ownership migrated to app.css; ui.css retained + linked; remaining shared-component blockers NOT STARTED.**

## M6.7 (2026-09-10) — .status-badge ownership migrated ui.css → app.css (largest remaining blocker cleared)

**What changed:** CSS ownership migration, largest remaining M6.4 blocker removed. The
ENTIRE `.status-badge` rule set (13 rules: base, `::before` dot, and the eight grouped
semantic variant blocks covering all eleven `is-*` classes) moved from
`public/css/ui.css` §5 (old l.328-378) to an end-of-file M6.7 section in
`resources/css/app.css`, copied VERBATIM - same selectors, same declarations, EXACT values:

    .status-badge {
        display: inline-flex; align-items: center; gap: .45em;
        padding: .28em .8em; border-radius: var(--ui-radius-pill);
        font-size: var(--ui-text-xs); font-weight: 600;
        letter-spacing: .02em; line-height: 1.3; white-space: nowrap;
    }
    .status-badge::before {
        content: ""; flex: none; width: 6px; height: 6px;
        border-radius: 50%; background-color: currentColor;
    }
    .status-badge.is-success/.is-paid/.is-active { bg rgba(45,139,122,.12); color #1E6B5B; }
    .status-badge.is-info/.is-approved      { bg rgba(59,130,246,.12); color #2563EB; }
    .status-badge.is-warning/.is-pending    { bg rgba(212,169,0,.15); color #8A6D00; }
    .status-badge.is-danger/.is-rejected    { bg rgba(206,17,38,.10); color var(--ui-red); }
    .status-badge.is-neutral/.is-archived   { bg rgba(78,90,110,.10); color var(--ui-text-secondary); }

All four consumed tokens are canonical in the app.css `:root` block (M6.3.2 owner):
`--ui-radius-pill` 999px, `--ui-text-xs` .72rem, `--ui-text-secondary` #4E5A6E,
`--ui-red` #CE1126; every Tailwind @theme twin used by the Batch C copy is byte-equal
(`--radius-pill` 999px, `--text-micro` .72rem, `--color-teal-dark` #1E6B5B,
`--color-blue-accent-dark` #2563EB, `--color-amber-dark` #8A6D00,
`--color-ink-secondary` #4E5A6E, `--color-red` #CE1126). The ported plain unlayered rules
land AFTER the Batch C `.status-badge` Tailwind twin (source l.698), so every
equal-specificity tie (0,1,0 base; 0,2,0 ::before + variants) resolves exactly as
late-loaded ui.css did - real 999px pill, .72rem/600 semibold, .02em tracking, 1.3
leading, and the 6x6 / 50% `::before` currentColor dot. `ui.css` block replaced with a
pointer comment. `ui.css` still present + linked in all 8 `<link>` tags; `datatables.css`
byte-identical (MD5 `2ee627b7...6c46`, 595 lines). Baselines before edit (M6.6
end-state): ui.css 468 lines MD5 `cc38e378...e35`; app.css 2653 lines MD5
`f23e2324...d174`. After: ui.css 427 lines MD5 `b024be40...f01`; app.css 2714 lines MD5
`04947812...f098` (the appended block survived Tailwind) and rebuilt via `npm run build`
-> `app-DZ6PGwAv.css` 81.60 kB (was `app-C29NMCAt.css` 80.78 kB; JS `app-DqsLDVL_.js`
unchanged). Compiled CSS verified: the ported `.status-badge` family is LAST in the
bundle, after the M6.5 `.data-card` and M6.6 `.metric-card` blocks (2 `.status-badge`,
2 `.status-badge:before`, 5 variant-grouped rules minified to #rrggbbaa forms).

**Scope guard:** ONLY the 13-rule `.status-badge` family touched. `.metric-value`,
`.data-card-header/-body/-footer`, `.ui-notice`, `.ui-empty`, `.ui-micro-label`,
`.ui-skip-link`, `.ui-page-header/-title/-subtitle/-actions`, Reboot/type elements,
`:focus-visible`, reduced-motion and the pagination `.page-link`/`.page-item` rules were
NOT migrated (each is a separate family; inventory below). No PHP/controller/model/route/
schema/JS/Alpine/auth/Blade/preview changes. Consumers are all static Blade: `rg
"status-badge"` -> 15 files / 27 class sites; variants in use: is-neutral 17, is-paid 11,
is-pending 10, is-approved 4, is-active 3, is-info 2, is-success 2; is-warning/-rejected/
-danger/-archived currently unused (kept - family completeness, no dead-rule removal in a
component migration). Zero JS/Alpine/dynamic consumers; zero `--bs-*`; the family has no
media queries, no hover/focus/active states, no reduced-motion ties, no per-instance
tokens, and no parent/child cascade OUTSIDE the family (the `::before` dot belongs to it).

**Inventoried remaining ui.css (M6.7 authoritative inventory, classified):**
- MIGRATE (live, self-contained, subsequent milestones): `.metric-value` (1 rule, live
  dashboard; __value-identical__ today after M6.6), `.data-card-header/-body/-footer`
  (3 rules; live header 3 + body 37 + footer 0 sites; Twin diverges padding/gap
  .75rem/.75rem/.375rem vs 1rem/1.25rem/.875rem-1.25rem), `.ui-notice` (1 rule, live 6
  files), `.ui-micro-label` (1 rule, live 12 files), `.ui-skip-link` + `:focus` (2 rules,
  global a11y; Twin diverges focus left 0px vs 14px).
- DEAD (0 live consumers; deletion-gate removals, NOT component migrations): `.ui-empty`
  (0 sites), `.ui-page-header/-page-title/-page-subtitle/-page-actions` (0 sites;
  app.css intentionally never mirrored them), ui.css §2 body/h1-h6 font hooks (inert -
  beaten by unlayered app.css body/h1-h6 at equal specificity, later source).
- RETAIN-FOR-CASCADE / FOUNDATION: `:focus-visible`, the reduced-motion block and the
  §4.10 Reboot + type element rules (each has a BYTE-IDENTICAL app.css copy in `@layer
  base`; while ui.css is linked its unlayered copy wins, rendering identical values),
  and the §4 pagination `.page-link`/`.page-item.active` navy pair (value-identical
  today - an app.css `.dataTables_wrapper` scoped mirror + datatables.css cover it).

**Cascade proof (`snap-sb.cjs`, /login fixture, 1280px, 12 badge probes with full
computed props + `::before` + rects):** pre-patch vs pre-port (port added, ui.css still
unsigned) vs post-removal (ui.css rule set deleted) BYTE-IDENTICAL: pill radius 999px,
`.72rem`/600 font, rects 47.656/66.234/.../50.547 wide x 18.719 high, dot 6x6 + 50%
radius + `flex: none`, colors `rgb(30,107,91)`/`rgb(37,99,235)`/`rgb(138,109,0)`/
`rgb(206,17,38)`/`rgb(78,90,110)` on the exact rgba tints, plain `#212529` transparent;
0 console + 0 page errors in all three captures.

**Deletion-sim re-run (same m64 harness, 24 cells, 60s goto):** status 200 FULL and SIM
in ALL cells, 0 failed non-ui.css requests, 0 page errors, 0 doc overflow, 0 FULL console
errors (`consoleErrorsS` = 1/cell = the known aborted-stylesheet artifact),
`window.bootstrap` undefined in all 48 captures. `/login` fixture drift collapsed
F23->F11, O24->O12, R14->R2 at EVERY viewport (375/576/768/1280). **`.status-badge`
ZERO drift:** all twelve `sb-*` fixture probes vanish from fixtureDrift and no
status-badge/key remains in ownedDrift - the 6px dot, 50% radius and badge rects now
render IDENTICAL with ui.css aborted (was: dot 5.25px, badge -0.75px wide, downstream
rect reflow). The two residual realDrift keys are fixture-container `rect.height`
content-driven propagation (skip/uml/tinted notice etc. still ui.css-owned). `.metric-card`
hover-lift (mcHover FULL==SIM) and the reduced-motion probe remain byte-identical -
no regression to M6.6/M6.5.

**Regression guards (Step 6):** `datatables.css` byte-identical (MD5 `2ee627b7...c46`,
B2 focus seam intact - untouched this milestone). The 24-cell sweep re-probes the whole
M5.2..M6.6 chain every run: dropdown `.dropdown-open`, accordion `.accordion-open`,
`.btn-close`, utilities set, buttons, alerts, list-group, `.table`, grid, modal
(none exist), `.data-card` (dc-plain residual is content-cascade only), `.metric-card`
(clean) - owned/fixture drift set unchanged minus the status-badge keys; reduced-motion
FULL==SIM; sim chain post638 drift 0; 0 overflow.

**Verification (Step 7):** `npm run build` green (`app-DZ6PGwAv.css` 81.60 kB),
compiled-CSS tail check (ported `.status-badge` family LAST), `php artisan view:cache`
OK, `vendor\bin\pint --test --dirty` passed, `php artisan test` **301 passed / 1419
assertions / 0 failures** (baseline unchanged - no legit change), `git diff --check`
clean (CRLF notices only), `/login` HTTP 200, Bootstrap CSS/JS CDN 0 in /login HTML.

**Affected files:** `resources/css/app.css` (M6.7 end-of-file section, +61 lines),
`public/css/ui.css` (only the 13 `.status-badge` rules removed, +pointer comment -41
lines), plus this log, `UI_CSS_RETIREMENT_PLAN.md`, `SESSION_HANDOFF.md`.

**Status:** **M6.7 COMPLETE — .status-badge ownership migrated to app.css; ui.css retained + linked; remaining shared-component blockers NOT STARTED.**

## M6.8 (2026-09-10) — .data-card slot family (.data-card-header/-body/-footer) ownership migrated ui.css → app.css

**What changed:** CSS ownership migration; the THREE live `.data-card` slot rules moved
from `public/css/ui.css` §5 (old l.359-380) to an end-of-file M6.8 section in
`resources/css/app.css`, copied VERBATIM (same selectors, same declarations, EXACT
values):

    .data-card-header {
        display: flex; flex-wrap: wrap; align-items: center;
        justify-content: space-between; gap: var(--ui-space-3);
        padding: var(--ui-space-4) var(--ui-space-5);
        border-bottom: 1px solid var(--ui-border-light);
    }
    .data-card-body {
        padding: var(--ui-space-5);
    }
    .data-card-footer {
        display: flex; flex-wrap: wrap; align-items: center;
        justify-content: space-between; gap: var(--ui-space-3);
        padding: var(--ui-space-3) var(--ui-space-5);
        border-top: 1px solid var(--ui-border-light);
        background: var(--ui-card);
    }

All five consumed tokens are canonical in the app.css `:root` block (M6.3.2 owner):
`--ui-space-3` 12px, `--ui-space-4` 16px, `--ui-space-5` 20px, `--ui-border-light`
#EEF0F4, `--ui-card` #FFFFFF; the Batch C Tailwind equal-value twins are
`--color-line-light` #EEF0F4 and `--color-surface` #FFFFFF (byte-equal). The ported plain
unlayered rules land AFTER the Batch C `.data-card-header/-body/-footer` Tailwind twins
(source lines ~738-748), so every equal-specificity tie (0,1,0) resolves exactly as
late-loaded ui.css did -> real 16px/20px/16px/20px header padding with 12px gap and
1px solid #EEF0F4 bottom divider, 20px body padding, 12px/20px footer padding with 12px
gap, 1px top divider and #FFFFFF surface. The `.data-card` base rule (M6.5) was NOT
modified (audited: no declaration is inseparably required by the slots; the base's
border/radius/shadow apply to the container, slots add their own internal dividers).
`ui.css` block replaced with a pointer comment. `ui.css` still present + linked in all 8
`<link>` tags; `datatables.css` byte-identical (MD5 `2ee627b7...c46`, 595 lines).
Baselines before edit (M6.7 end-state): ui.css 427 lines MD5 `b024be40...f01`; app.css
2714 lines MD5 `04947812...f098`. After: ui.css 406 lines MD5 `f93d9340...5a`; app.css
2751 lines MD5 `6e238c56...0d0`, rebuilt via `npm run build` -> `app-ClwMBsAM.css` 82.05
kB (was `app-DZ6PGwAv.css` 81.60 kB; JS `app-DqsLDVL_.js` unchanged). Compiled CSS
verified: the ported `.data-card-header/-body/-footer` family is the LAST `.data-card`
group in the bundle, after the M6.7 `.status-badge` and M6.6 `.metric-card` blocks; the
M6.5 `.data-card` base rule body is byte-verbatim
(`background:var(--ui-card);border:1px solid var(--ui-border);border-radius:
var(--ui-radius-lg);box-shadow:var(--ui-shadow-sm)`).

**Inventory + classification (Steps 1-2):** exactly 3 selectors exist in ui.css, all
MIGRATE and single-class (no compound selectors, no pseudo-elements/classes, no media
queries, no state/hover/focus rules, no parent/child cascade OUTSIDE the family - they
are horizontal segments stacked inside the `.data-card` container but carry no selectors
touching it or siblings); no selector overlaps any other remaining family
(.metric-card/.status-badge/.ui-notice/.ui-micro-label/.metric-value). Consumers,
repository-wide: `.data-card-header` 3 sites (dashboard.blade.php only),
`.data-card-body` 37 sites across 26 list/form screens, `.data-card-footer` 0 live sites
(kept for family completeness - no dead-rule removal in a component migration). JS/runtime
(public/js + resources/js): ZERO matches for the three slot classes; the only `.data-card`
reference is `DetailsPanel.js:161` `querySelector` using the CONTAINER class for the
M6.5-migrated base - a selector contract that does not name any slot, left untouched. No
classList/className construction/templates emit slot classes. Slots do not depend on
`.data-card` parent state or sibling selectors; identical cascade behavior is preserved
by the plain unlayered end-of-file port.

**Cascade proof (Gate 1, `snap-dcslots.cjs`, /login fixture, 4 viewports):** A (pre-patch)
== B (post-port, ui.css still owns) == C (post-removal, ui.css rule set deleted)
byte-identical for dc-plain/dc-header/dc-body/dc-footer - full computed props (display,
flex-flow, align, justify, gap, padding T/R/B/L, four border sides width+style+color,
radius, background, box-sizing, font/line-height/color, overflow, margins) plus rects:
header 16px/20px/16px/20px + gap 12px + bottom divider 1px solid rgb(238,240,244), body
20px/20px/20px/20px, footer 12px/20px/12px/20px + gap 12px + top divider #EEF0F4 + bg
#FFFFFF; 0 console/page errors in every capture.

**Deletion-sim (Gate 2, same m64 harness, 24 cells, ui.css aborted in SIM):** status 200
FULL and SIM in ALL cells, 0 failed non-ui.css requests, 0 page errors, 0 doc overflow,
0 FULL console errors (`consoleErrorsS` = 1/cell = the established aborted-stylesheet
artifact), `window.bootstrap` undefined in all 48 captures. /login fixture drift collapsed
F11->F6, O12->O7, R2 unchanged at every viewport (375/576/768/1280). **dc-plain,
dc-header, dc-body, dc-footer ALL report NO DRIFT** - the slot padding/gap drifts
(16/20/16/20 vs 14/17.5/14/17.5, gap 12 vs 10.5, 20 vs 17.5, 12/20 vs 10.5/17.5) are gone
AND the M6.5-era dc-plain `rect.height` residual (202.172 vs 190.172) is fully cleared:
isolated and proven to be slot-padding content propagation, not parent-owned geometry.
Residual fixture/owned drift is confined to still-owning families: `skip`, `uml`,
`uempty`, `page-link/-plain`, Reboot/type `fixture` probes, owned `.ui-skip-link`,
`.ui-micro-label`, `.ui-empty`, `.page-item/.page-link`. `.metric-card` hover-lift and
the reduced-motion probe stay FULL==SIM (M6.7/6/5 regression-free).

**Regression guards (Step 9):** `datatables.css` byte-identical (MD5 `2ee627b7...c46`, B2
focus seam untouched). The 24-cell sweep re-probes the whole M5.2..M6.7 chain every run:
dropdown `.dropdown-open`, accordion `.accordion-open`, `.btn-close`, utilities set,
buttons, alerts, list-group, `.table`, grid, modal (none), `.data-card` (clean),
`.metric-card` (clean), `.status-badge` (clean) - owned/fixture drift set unchanged minus
the four dc-* keys; reduced-motion FULL==SIM; sim chain post638 drift 0; 0 overflow. No
`.d-none`, `.show`, `.collapsed`, `.w-100` regression (those contracts live in Blade/JS,
untouched, zero ui.css ownership).

**Verification (Steps 10-11):** `npm run build` green (`app-ClwMBsAM.css` 82.05 kB, JS
unchanged), compiled-CSS tail check (slot port LAST; M6.5 base verbatim), `php artisan
view:cache` OK, `vendor\bin\pint --test --dirty` passed, `php artisan test` **301 passed
/ 1419 assertions / 0 failures** (baseline unchanged - no legit change), `git diff
--check` clean (CRLF notices only), `/login` HTTP 200, Bootstrap CSS/JS CDN 0 in /login
HTML. Playwright 375/576/768/1280: 0 overflow, 0 console/page errors, 0 failed requests,
slot geometry stable in FULL and SIM.

**Final ownership sweep (Step 12):** `rg` on `public/css/ui.css` for the three slot
selectors -> 0 rule selectors (only the M6.8 pointer comment mentions the class names);
all three selectors present in `resources/css/app.css`; the M6.5 `.data-card` base rule
intact.

**Remaining blockers (exact next phase):** ui-skip-link `:focus` (left 0 vs 14px; global
a11y) -> `.ui-notice` (live 6 files) -> `.ui-micro-label` (live 12 files) -> `.metric-value`
(value-identical today) -> plus DEAD (`.ui-empty`, `.ui-page-header/-title/-subtitle/
-actions`, inert §2 font hooks - deletion-gate removals, NOT migrations) and RETAIN/
FOUNDATION (`:focus-visible`, reduced-motion, §4.10 Reboot/type - byte-identical @layer
base twins; `.page-link`/`.page-item` navy - value-identical, app.css scoped mirror +
datatables.css cover).

**Affected files:** `resources/css/app.css` (M6.8 end-of-file section, +37 lines),
`public/css/ui.css` (only the 3 slot rules removed, +pointer comment -21 lines), plus
this log, `UI_CSS_RETIREMENT_PLAN.md`, `SESSION_HANDOFF.md`.

**Status:** **M6.8 COMPLETE — .data-card slot family ownership migrated to app.css; ui.css retained + linked; remaining shared-component blockers NOT STARTED.**

---

## M6.9 — `.ui-skip-link` family (base + `:focus`) ownership migrated to app.css (2026-09-11, COMPLETE — global a11y blocker cleared)

**Scope:** migrate the live `.ui-skip-link` CSS ownership from `public/css/ui.css` to
`resources/css/app.css` per the M6.x retirement plan. The charter's live blocker was
`.ui-skip-link:focus { left: 0 }` (m64 audit: `left` 0px FULL vs 14px SIM — the focused
visible pill snaps to viewport-left 14px instead of 0 once ui.css is gone). During
implementation the base `.ui-skip-link` rule was re-classified under the charter's own
scope-conditional ("leave the base untouched only if it is NOT a live ui.css blocker"):
the first deletion-sim capture proved the base IS a live blocker of the focus contract —
its `text-decoration:none` rides into the VISIBLE focused state (FULL `none` vs SIM
`underline` on the visible pill; the Batch B sr-only/focus-* twin never re-supplies
`text-decoration`) and its `left:-999px/z-index:2000` define the hidden-state unit the
focused `left:0` flips. The family (base + `:focus`, 2 rules) was migrated VERBATIM as a
unit.

**Source changes (exactly two application files):**
- `resources/css/app.css` — M6.9 end-of-file section: `.ui-skip-link { ... }` (headered
  `/* ═══ M6.9 — .ui-skip-link ownership: base + :focus family migrated */`) + the
  `.ui-skip-link:focus { left: 0; }` rule, byte-verbatim from ui.css, appended AFTER the
  Batch B sr-only/focus-* twin so every same-specificity tie (0,1,0 base / 0,2,0 focus)
  resolves exactly as late-loaded ui.css did. app.css 2800 lines after the family port (2773 after the
  first `:focus`-only port; rewritten to the full family in-place). Build: `npm run build` →
  `public/build/assets/app-BP5CUd8L.css`
  82.31 kB (JS `app-DqsLDVL_.js` unchanged). Compiled bundle verified: the port is the
  LAST `.ui-skip-link` group (twin at ~l.33140-33286; port base `{z-index:2000;background:
  var(--ui-gold);color:var(--ui-navy);font-weight:700;font-size:var(--ui-text-sm);
  border-radius:0 0 var(--ui-radius) 0;padding:10px 16px;text-decoration:none;
  position:absolute;top:0;left:-999px}` then `.ui-skip-link:focus{left:0}`) immediately
  before the trailing `@property` tail — LightningCSS re-ordered declarations inside the
  base rule (semantic-neutral) but left every value byte-verbatim.
- `public/css/ui.css` — both skip-link rules removed, replaced by a pointer comment
  explaining the M6.9 migration + the live-blocker justification. Final M6.9 state: 400
  lines, trailing-newline normalized (git diff --check clean); `rg` → 0 real
  `.ui-skip-link` selectors (comment mentions only). Remaining ui.css rule selectors are
  exactly the deferred set: `.ui-micro-label`, `.ui-page-header/-title/-subtitle/-actions`,
  `.ui-notice`, `.ui-empty`.

**Consumers (untouched):** single static anchor `resources/views/layouts/app.blade.php:25`
(`<a href="#main-content" class="ui-skip-link">`) → `main#main-content[tabindex=-1]` :97.
`/login` is a standalone head (does NOT extend layouts.app), so both gates probe the
harness fixture mirror (`a.ui-skip-link[data-probe="skip"]` at body start). Zero JS/Alpine
refs. Tokens: `--ui-gold` #FCD116 / `--ui-navy` #0038A8 / `--ui-text-sm` .85rem /
`--ui-radius` 8px — all canonical in the app.css `:root` (M6.3.2 owner); zero new tokens,
zero ui.css token dependency. `:focus-visible` (gold 3px solid offset 2, ui.css unlayered /
app.css `@layer base` twin) untouched.

**Cascade proof (Gate 1, `snap-skip.cjs`, /login fixture + global Tab chain, viewports
375/576/768/1280):** A (pre-patch) == B (port added, ui.css still owned) == C (post-removal,
ui.css family deleted) **byte-identical** for every state: unfocused `left:-999px`
`position:absolute` rect 32×20; programmatic focus `left:0` focusVisible true; Tab-1 keytab
focus `left:0` focusVisible true rect 138.234×36.547, fixed, top 10.5px, bg #FFFFFF,
color rgb(15,27,45), z-index 50, padding 8.75/14px, radius 8px, font 11.9px/600; focus-loss
restores -999px on both cycles; the 5-focusable global chain FULL==SIM (skip-link gold
outline 3px solid rgb(252,209,22) offset 2; `input.form-control` outline none — the
pre-existing Bootstrap seam; `.btn-navy` gold outlined); `#main-content` target; doc
overflow 0; console/page errors 0 in every capture (settle wait 300ms after Tab eliminates
a `.btn-navy` transient outline-color timing artifact).

**Deletion-sim (Gate 2, m64 harness, 24 cells, ui.css aborted in SIM):** every report field
byte-identical to the M6.8 baseline except the exact M6.9 resolution; interactions
`skipFocused` now **FULL==SIM byte-identical — `left` 0px / rectLeft 0.000** in both modes
(M6.8 sim was 14px / 14.000); `/login` fixture drift **ownedCount 7→6, fixtureCount 6→5** at
all four viewports — the entire `.ui-skip-link` ownedDrift row (22 props) and the `skip`
fixtureDrift row vanish; residual drift confined to the still-owning families (`uempty`,
`uml`, `page-link/-plain`, Reboot/type `fixture`). 24/24 status 200 FULL+SIM, 0 overflow, 0
page errors, `consoleErrorsF` 0 / `consoleErrorsS` 1 (= established aborted-stylesheet
artifact), `window.bootstrap` undefined, reduced-motion probe FULL==SIM unchanged.

**Regression guards (Steps 8–12):** global `:focus-visible` chain + Tab-walk FULL==SIM;
prior-milestone re-probes clean (`.metric-card` hover-lift + reduced-motion FULL==SIM;
`.data-card` / `.metric-card` / `.status-badge` / dropdown / accordion / forms / table /
list-group / alert / utility families 0 drift); `datatables.css` byte-identical (MD5
`2ee627b7...c46`, 595 lines, B2 seam untouched); Bootstrap CDN 0; /login HTTP 200.

**Verification (Step 13):** `npm run build` green (`app-BP5CUd8L.css` 82.31 kB); compiled-CSS
tail check as above; `php artisan view:cache` OK; `vendor\bin\pint --test --dirty` passed;
`php artisan test` **301 passed / 1419 assertions / 0 failures** (baseline unchanged);
`git diff --check` clean (CRLF notices only). app.css 2800 lines; ui.css 400 lines (final
M6.9 state).

**Final ownership sweep (Step 14):** `rg` on `public/css/ui.css` → 0 real `.ui-skip-link`
selectors; the family (base + `:focus`) is the LAST `.ui-skip-link` group in the compiled
bundle; no `.ui-skip-link` blocks remain in ui.css; ui.css retained + linked (8 `<link>`s
untouched), nothing deleted/committed.

**Remaining blockers (exact next phase):** `.ui-notice` (live 6 files) -> `.ui-micro-label`
(live 12 files) -> `.metric-value` (value-identical today) -> plus DEAD (`.ui-empty`,
`.ui-page-header/-title/-subtitle/-actions`, inert §2 font hooks — deletion-gate removals,
NOT migrations) and RETAIN/FOUNDATION (`:focus-visible`, reduced-motion, §4.10 Reboot/type —
byte-identical `@layer base` twins; `.page-link`/`.page-item` navy — value-identical,
app.css scoped mirror + datatables.css cover).

**Affected files:** `resources/css/app.css` (M6.9 end-of-file section, +27 lines family
port), `public/css/ui.css` (skip-link family removed, +pointer comment −10 lines), plus
this log, `UI_CSS_RETIREMENT_PLAN.md`, `SESSION_HANDOFF.md`.

**Status:** **M6.9 COMPLETE — `.ui-skip-link` family (base + `:focus`) ownership migrated to app.css; ui.css retained + linked; remaining blockers (`.ui-notice` → `.ui-micro-label` → `.metric-value` + DEAD set) NOT STARTED.**

## M6.10 — `.ui-notice` family (single base rule) ownership migrated to app.css (2026-09-11, COMPLETE — last live banner blocker cleared)

**Changed files (exactly two application files + the three docs):**
- `resources/css/app.css` — M6.10 end-of-file section (after the M6.9 block): the `.ui-notice`
  single base rule ported VERBATIM from ui.css (headered
  `/* ═══ M6.10 — .ui-notice ownership: tinted informational notice (single rule) */`):
  `display:flex; align-items:flex-start; gap:.65rem; padding:.85rem 1rem;
  background:var(--ui-gold-light); border-left:3px solid var(--ui-gold);
  border-radius:var(--ui-radius-sm); color:var(--ui-text-primary)`. Final M6.10 state: 2831
  lines (MD5 `1815b56d...`); the port lands after the Batch C twin (`.ui-notice { @apply … }`,
  mid-file) so every equal-specificity tie keeps ui.css-late semantics — the twin stays as a
  mirror. All four tokens are canonical app.css `:root` values (M6.3.2 owner); zero new
  tokens.
- `public/css/ui.css` — the `.ui-notice` rule removed, replaced by a pointer comment
  explaining the M6.10 migration + the orphan `.ui-notice-info` note. Final M6.10 state: 396
  lines (MD5 `09ea45a6...`). 0 real `.ui-notice` selectors remain (4 rg hits = comment text).
  `.ui-micro-label` (line 45) and `.metric-value` (line 337) still ui.css-owned; DEAD /
  FOUNDATION untouched.

**Inventory / consumers (Steps 1–3):** single rule, no variants, no `-info` CSS anywhere
(orphan class, compiled bundle confirmed absent — only the base governs that banner),
no pseudo-elements, no media/responsive, no interactions, no child selectors, no JS
classList/template-literal references (0 matches in `public/js`, `resources/js`). 7 static
Blade sites in 6 files: `transactions/create` + `transactions/edit` (validation alert
`role="alert"`, `mb-[16px]`, svg-strip + message spans), `clients/_gip` (`<p class="ui-notice
m-0">`, svg + `<strong>` text), `auth/login` (two session bars `mb-3`), `students/update-photo`
(`text-center` variant), `admin/users/index` (base on the `ui-notice-info` banner; Alpine
`x-show` toggles visibility, not classes). Blade/JS/PHP untouched — pure CSS ownership
transfer.

**Strategy (cascade, Step 4):** verbatim EOF port (no Tailwind rewrite, no rename, no
@utility bridge, exact-value twins not retired). Precedent M6.5→M6.9.

**Gate 1 A/B/C (Step 5/6, `snap-notice.cjs`, /login fixture × 5 real compositions × 4
viewports):** A (baseline) == B (port added, ui.css still owns, served the rebuilt bundle)
== C (post-removal) — **byte-identical at 375/576/768/1280** full computed styles +
geometry + child `<span>`/`<svg>` rects + pseudo for all five probes (`notice-alert`,
`notice-para`, `notice-login`, `notice-center`, `notice-info`); every probe
`display:flex gap:9.1px pad 11.9/14/11.9/14 bg rgb(255,248,214) left-border 3px
rgb(252,209,22) radius 6px color rgb(15,27,45) font 11.9px lh 16.3625px items flex-start`;
0 console/page errors, 0 overflow. PLUS a fourth capture with the ui.css route aborted in
SIM: probes FULL==SIM byte-equal (SIM console artifact 1/cell — the one established
artifact — 0 page errors).

**Gate 2 deletion-sim (Step 7, same m64 harness, 24 cells):** report **byte-identical to the
M6.9-final report in every cell — 0 deltas.** Status 200 FULL+SIM all cells, 0 overflow, 0
page errors, `consoleErrorsF` 0 / `consoleErrorsS` 1 (established artifact),
`window.bootstrap` undefined, skip/mcHover/reduced-motion interactions unchanged, `.ui-notice`
FULL==SIM everywhere. (First audit run showed a transient `grantee-search/grantee` 500 on
qr-viewer / grantee-update / unpaid-verification — `SQLSTATE[HY000] [2002]` in the Laravel
log: XAMPP MySQL had stopped (port 3306 refused). Restarted `mysqld.exe`, re-ran, clean.
Environmental — no CSS/application involvement.)

**Regression guards (Steps 8–9):** compiled-bundle family sweep OK (`.data-card`,
`.metric-card`, `.status-badge`, `.data-card-header/-body/-footer`, `.ui-skip-link`,
`.ui-notice`, `.btn-gold`, `.field-control/-label`, datatables seam); `datatables.css`
byte-identical (MD5 `2ee627b7...c46`, B2 seam untouched); no `bootstrapcdn`/`maxcdn` in the
bundle; all 8 ui.css `<link>`s intact; `/login` HTTP 200.

**Verification (Step 10):** `npm run build` green (`app-Bd_LWcVo.css` 82.52 kB, JS
`app-DqsLDVL_.js` unchanged); `php artisan view:cache` OK; `vendor\bin\pint --test --dirty`
passed; `php artisan test` **301 passed / 1419 assertions / 0 failures** (baseline
unchanged); `git diff --check` clean (CRLF notices only). Compiled bundle MD5 `abfe1dc9...`.

**Final ownership sweep (Steps 11–14):** 0 real `.ui-notice` selectors in ui.css; the port is
the LAST `.ui-notice` group in the compiled bundle (after the M6.9 skip-link block and the
trailing `@property` tail untouched); `.ui-micro-label` + `.metric-value` remain ui.css-owned;
ui.css retained + linked (8 `<link>`s), nothing deleted/committed. Migrated families now total
`.data-card*` (M6.5+M6.8), `.metric-card*` (M6.6), `.status-badge` (M6.7), `.ui-skip-link`
(M6.9), `.ui-notice` (M6.10) — all vertex-identical under the deletion-sim.

**Remaining blockers (exact next phase):** `.ui-micro-label` (live 12 files) ->
`.metric-value` (value-identical today) -> plus DEAD (`.ui-empty`, `.ui-page-header/-title/
-subtitle/-actions`, inert §2 font hooks — deletion-gate removals, NOT migrations) and
RETAIN/FOUNDATION (`:focus-visible`, reduced-motion, §4.10 Reboot/type — byte-identical
`@layer base` twins; `.page-link`/`.page-item` navy — value-identical, app.css scoped mirror
+ datatables.css cover).

**Affected files:** `resources/css/app.css` (M6.10 end-of-file section, +31 lines family
port), `public/css/ui.css` (`.ui-notice` rule removed, +pointer comment −14 lines), plus
this log, `UI_CSS_RETIREMENT_PLAN.md`, `SESSION_HANDOFF.md`.

**Status:** **M6.10 COMPLETE — `.ui-notice` ownership migrated to app.css; ui.css retained + linked; `.ui-micro-label` and `.metric-value` NOT STARTED.**
## M6.11 - `.ui-micro-label` family (single base rule) ownership migrated to app.css (2026-09-12, COMPLETE)

**Changed files (exactly two application files + the four docs):**
- `resources/css/app.css` - appended the ENTIRE `.ui-micro-label` family (single base rule: `font-size:
  var(--ui-text-xs); font-weight: 600; letter-spacing: var(--ui-tracking-caps); text-transform: uppercase; color:
  var(--ui-text-muted)`) VERBATIM to the end of app.css (EOF, after the M6.10 `.ui-notice` EOF port block, same-line
  pointer comment in house style).
- `public/css/ui.css` - removed that one `.ui-micro-label` rule and replaced it with a pointer comment (M6.10 house
  style; the M6.3 Batch-C twin kept as mirror).
- `public/css/ui.css` retained + linked (zero consumers removed); `resources/css/app.css` canonical owner.
- Docs: this log + `docs/UI_CSS_RETIREMENT_PLAN.md` + `docs/SESSION_HANDOFF.md` updated (M6.11 entry).

**Gate-1 (A==B==C byte-identical):** snapshots A (ui.css live baseline) / B (after EOF port in app.css) / C (after the
rule removal from ui.css + pointer) are byte-identical across 4 viewports (375/576/768/1280) x 5 rendering surfaces
(login, sessions/+, grantee_update, scholars, households) x the 5-probe micro-label fixture set; `.ui-micro-label`
computed value (font-size 10.08px / font-weight 600 / letter-spacing 0.6048px / text-transform uppercase / color
`#70798B` = `rgb(112,121,139)`) FULL==SIM value-identical on every cell, 0 console/page errors, 0 overflow.

**Gate-2 (deletion-sim parity):** M6.11 deletion-sim re-run of the ui.css abort produced a report **byte-identical to
the M6.10-FINAL baseline report** (all 24 cells, 0 deltas) - proving the port removed ALL `.ui-micro-label` drift and
is fully cascade-neutral. The m64 report (49134B, md5 `216830713CE9A8E4A535F5E51CB8CA38`) is byte-identical to the
m68 baseline report (`m64-report-m610.json` 49134B).

**Regression:** `php artisan test` **301 passed / 1419 assertions / 0 failures** (baseline unchanged); Pint --test
--dirty passed; `php artisan view:cache` clean; npm build green (`app-WdxWzFTe.css` 82.67 kB, updated twin `app-`).
0 console errors, 0 overflow, reduced-motion + mcHover regression-free, 24/24 status 200.

**Ownership census (post-M6.11):** `.ui-micro-label` live rules - ui.css **0** (pointer comment only), app.css **2**
(M6.3 Batch-C twin + M6.11 EOF port = canonical owner); datatables.css **0**. `.metric-value`, `.ui-empty`,
`.ui-notice`/inf twins, `.ui-skip-link` remain ui.css-owned; deletion of those shared components NOT STARTED;
`ui.css` retained + linked.

## M6.12 - .metric-value family ownership to migrate (2026-09-12, NOT STARTED)

Exact next phase after M6.11-COMPLETE: .metric-value base rule - SAME two-file contract proven by M6.11
(M6.3 Batch-C twin retained, delete nothing shared, ui.css retained + linked, EOF port + pointer comment in house style,
Gate-1 A==B==C byte-identical, Gate-2 deletion-sim byte-identical to M6.11-FINAL report). Not started; no files changed for M6.12 yet. STOP.

**2026-09-12 M6.13 LANDED (final dependency/foundation/deletion gate re-audit — AUDIT-ONLY, no deletion. Fresh read-only census: public/css/ui.css 22,148 B md5 CC5140C1E7111B82E9D07455A0AE0552 = 48 live selector blocks / 43 families [pagination §4.4 .page-link/-item @L208-217, focus-visible @L63, reduced-motion @L89, Reboot+type element base (body/h1-h6/p/ol/dt/dd/blockquote/b/hr/abbr[title]/address/a/img,svg/table/caption/th/thead/label/button/input/select/textarea), §5 ui-page-* & ui-empty pointer-only]; app.css 115,995 B md5 F3B87650EE375395582F434E3F736978 canonical owner of every earlier-migrated twin + .page-link DataTables seam twin @L1196; datatables.css byte-pin HELD 21,563 B / md5 2EE627B74B0FD170727506AFD46C6C46 [byte-identical, carbon untouched] — DataTables boundary CLEAN, pagination family RETAINED-FOR-CASCADE (12 live consumer views incl. dashboard metric tiles @dashboard.blade.php L112/123/134/145 + DataTables partners, no app.css general page-link twin to inherit .page-link base without DRIFT → deletion would change live computed style); Bootstrap/legacy: 0 Bootstrap CDN/JS refs beyond the two ui.css+datatables.css <link>s in layout, 0 .show/.collapsed/.btn-close/.modal residues requiring ui.css, Alpine+Tailwind contract intact; VERDICT not SALVED/BLOCKED — foundation Reboot+type + pagination remain ui.css-live and deletion NOT deletion-safe without a follow-on per-family verbatim twin; ui.css RETAINED + LINKED (8 links untouched); build app-*.css 115,995 B, view:cache OK, Pint PASS, suite 301/1419/0, git diff --check clean, no git op; docs 3-file scope only; M6.14 NOT STARTED)**

## 2026-09-12 M6.14 LANDED — general .page-link/.page-item pagination ownership EPOCH (VERBATIM port → app.css EOF canonical owner; ui.css RETAINED + pointer; 8 <link>s + 12-consumer seam untouched)

Migration SUCCESS — byte-precise A/B/C (PRE 22,148B/CC5140C1E7111B82E9D07455A0AE0552 → PORT append app.css EOF + pointer → REMOVE general family from ui.css replaced with house pointer). REFRESH census (post-land): public/css/ui.css 22,524 B md5 C35D4A9BB38B74406D7A665D392A4656 — window only pointer @L206-216, ZERO general .page-link/.page-item selectors (family: .page-link base/:hover/:focus & .page-item.active .page-link @ui.css L208-220 moved VERBATIM to resources/css/app.css EOF); ui.css RETAINED + LINKED (8 <link> tags intact); resources/css/app.css 117,057 B md5 4DDB45FA9DF420E58D2AECEA27FA7A1C — canonical EOF owner of general pagination family (appended @EOF, byte-identical values: family block md5 5017A7292A00CF69972EECFCEE1BDA20 = 4 selectors incl. DT DataTables-scoped twin dataTables_wrapper .dataTables_paginate .page-link seam @L1196 RETAINED scoped, higher specificity, NOT a general twin; DT pagers win the DataTables boundary — A==B==C byte-identical); public/css/datatables.css PIN HELD 21,563 B md5 2EE627B74B0FD170727506AFD46C6C46 (byte-pinned untouched; DataTables Sti scoped .page-link seam @L1196 canonical). DataTables boundary CLEAN + rounded (DT wrapper scoped seam RETAINED app.css @L1196 — .dataTables_wrapper .dataTables_paginate .page-link full-family styles in datatables.css scoped .dataTables_wrapper .dataTables_paginate seam untouched → DT pagination remains canonical app.css seam via scoped twin @app.css L1196; Blade/JS consumers UNTOUCHED: 12 Blade files page-link / 12 page-item — 8 link tags intact, dashboard metric tiles @dashboard L112/123/134/145, zero JS/Blade changes; Alpine+Tailwind contract intact). REGRESSION HELD: `npm run build` ✓ 58 modules (app.css 117,057B → public/build/app-*.css; view:cache OK; PaPint `--test --dirty` PASS; PHPUnit **301 passed / 1,419 assertions / 0 failures** (suite baseline held; no deletion); datatables.css byte-pin re-verify HELD; git diff --check clean; ui.css retained + linked 8 <link>s; Blade/JS 0 changes; docs 3-file scope only (IMPLEMENTATION_LOG / SESSION_HANDOFF / UI_CSS_RETIREMENT_PLAN). M6.14 COMPLETE — M6.15 NOT STARTED.

## 2026-09-13 — House hygiene: docs EOL normalization (SESSION_HANDOFF.md CRLF -> LF) — git diff --check gate RESTORED to CLEAN

Forensic root cause of the long-standing `git diff --check` failure (exit=2, huge stdout):
`docs/SESSION_HANDOFF.md` was saved with **CRLF line endings on disk** (2,348 bare `\r` bytes).
git treats those bare CR bytes at end-of-line as trailing whitespace, so every added/edited
line in that file was flagged. The other two in-scope docs were already LF.

Fix (presentation/EOL-only, byte-precise):
- SESSION_HANDOFF.md: CRLF -> LF. `strippedLines=0` (NO content line had trailing [ \t]);
  byte delta exactly `-2,348` = the 2,348 CR bytes, byte-for-byte. Content untouched.
- IMPLEMENTATION_LOG.md: verified LF, `strippedLines=0`, byte-identical (768,079 B).
- UI_CSS_RETIREMENT_PLAN.md: verified LF, `strippedLines=0`, byte-identical (193,882 B).

Gate re-verify (via byte-clean write->run->read, NOT the mangled echo channel):
`git diff --check` -> **exit=0, stdoutBytes=0 — CLEAN**. NO git add/commit/stage performed
(untouched per house rule); no schema/ACL/route/controller/model/asset/Business-rule
change; no deletion of ui.css (still BLOCKED per M6.15 gate); docs byte counts match the
M6.13 baseline census, proving earlier strip experiments did not corrupt the docs.

## 2026-09-13 — House hygiene: docs EOL normalization (SESSION_HANDOFF.md CRLF -> LF) — git diff --check gate RESTORED to CLEAN

Forensic root cause of the long-standing `git diff --check` failure (exit=2, huge stdout):
`docs/SESSION_HANDOFF.md` was saved with **CRLF line endings on disk** (2,348 bare `\r` bytes).
git treats those bare CR bytes at end-of-line as trailing whitespace, so every added/edited
line in that file was flagged. The other two in-scope docs were already LF.

Fix (presentation/EOL-only, byte-precise):
- SESSION_HANDOFF.md: CRLF -> LF. `strippedLines=0` (NO content line had trailing [ \t]);
  byte delta exactly `-2,348` = the 2,348 CR bytes, byte-for-byte. Content untouched.
- IMPLEMENTATION_LOG.md: verified LF, `strippedLines=0`, byte-identical (768,079 B).
- UI_CSS_RETIREMENT_PLAN.md: verified LF, `strippedLines=0`, byte-identical (193,882 B).

Gate re-verify (via byte-clean write->run->read, NOT the mangled echo channel):
`git diff --check` -> **exit=0, stdoutBytes=0 — CLEAN**. NO git add/commit/stage performed
(untouched per house rule); no schema/ACL/route/controller/model/asset/Business-rule
change; no deletion of ui.css (still BLOCKED per M6.15 gate); docs byte counts match the
M6.13 baseline census, proving earlier strip experiments did not corrupt the docs.

## 2026-09-13 M6.15 LANDED — FINAL deletion-safety gate for ui.css (AUDIT + full-tree deletion-simulation on the REAL live app; CASE A)

Verdict: **M6.15 SAFE — ui.css deletion authorized; actual deletion NOT PERFORMED.** ui.css RETAINED + LINKED (8 <link> tags intact everywhere); ONLY docs changed (IMPLEMENTATION_LOG.md / SESSION_HANDOFF.md / UI_CSS_RETIREMENT_PLAN.md); no git ops; no schema/ACL/route/Blade/JS/CSS change; no deletion performed (CASE A — the retirement plan contains no M6.15 actual-deletion procedure).

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

## C1 — Component Containment & Visual Hierarchy Refinement (2026-09-14)

### Scope
Three visual-architecture defects: modal corner-hierarchy leak, filter group flat containment, details-panel avatar flex stretching.

### Changes

#### C1-1: Modal Shell overflow-hidden (12 shells, 9 files)
Added `overflow-hidden` Tailwind class to every modal shell element that had `rounded-panel bg-surface shadow-pop ring-1 ring-line`. This clips the navy header and grey footer backgrounds to the shell's 16px rounded corners, preventing square-colored corners from leaking past the radius.

Files: `admin/audit_logs/index.blade.php`, `admin/users/index.blade.php`, `scanners/scan.blade.php`, `clients/_details.blade.php` (3 shells), `clients/_gip.blade.php`, `partials/client-feedback-modal.blade.php`, `partials/client-form-modal.blade.php`, `unpaid_verifications/self-service.blade.php`, `scholars/index.blade.php`, `partials/record-view-modal.blade.php`.

**Safety:** Scrollable bodies have their own `overflow-y-auto`; the 4 modals without `max-h` are unconstrained so `overflow-hidden` is a harmless safety net. No scroll regression.

#### C1-2: Filter Group Containment (app.css)
Replaced flat `border-top` dividers on `.filter-multi` sections with individual card-style containment: `background: var(--color-bg)` (light grey), `border: 1px solid var(--color-line-light)`, `border-radius: var(--radius-card)` (12px), `margin-bottom: 0.5rem`. Last child gets no bottom margin.

#### C1-3: Details-Panel Avatar Flex Fix (details-panel.blade.php)
Two changes:
1. Selector exclusion: `.details-identity > div` → `> div:not(.details-avatar)` — prevents the `flex: 1` rule from targeting the avatar shell.
2. Defensive sizing: `.details-avatar` gets `flex: 0 0 auto` — prevents flex grow/shrink.

**Result:** Avatar shell now renders at intended 64px×64px desktop / 56px×56px mobile (was stretching to ~209px×64px due to flex parent).

### Verification
- **PHPUnit:** 301 passed / 1,419 assertions / 0 failures.
- **Pint:** PASS (`--test`).
- **View cache:** Blade templates cached successfully.
- **Build:** `app-DN45AQrl.css` (85.09 kB) — new `overflow-hidden` utility generated.
- **Playwright assertions (30/30 PASS):**
  - Modal shell overflow:hidden confirmed desktop + mobile.
  - Modal shell border-radius: 16px confirmed.
  - Modal header border-radius: 0px confirmed (clipped by shell).
  - Filter group background = rgb(240,242,245) confirmed desktop + mobile (6 groups × 2 viewports).
  - Filter group border-radius = 12px confirmed desktop + mobile.
  - Avatar shell width = 64px desktop, 56px mobile (was 209px/195px).
  - Avatar flex-grow = 0, flex-shrink = 0 confirmed.

### DB/Schema: No change.
### JS/PHP/Route/Auth: No change.
### datatables.css: Untouched pin (21,563 B).
### public/css/ui.css: Absent (deleted in M6.16).

### Smoke user cleanup
`smoke_superadmin` (id=13) + its tbl_permissions row deleted. DB clean.

M6.17 NOT STARTED.
---

### 2026-09-15 — C2 CLIENT QR FULL-NAME PAYLOAD — STOP, no code change
Cycle C2 (client QR payload → `LAST NAME, FIRST NAME [EXTENSION NAME] MIDDLE NAME`)
completed as **STOP + report only** per the task's own STOP protocol and owner
confirmation. Forensic trace and quantified scanner incompatibility:

- No "details-panel QR card" exists in production v2. Real client-QR surfaces are
  the 3 sites that generate via external `api.qrserver.com`:
  `qr/viewer.blade.php:201` (payload = persisted `data.client.full_name`,
  documented Decision C — encode the persisted comma-form name the P4 scanners
  match; never a re-composed name), `grantee_update/self-service.blade.php:420`,
  `grantee_update/_self_update_tab.blade.php:401` (JS-composed
  `LASTNAME, FIRSTNAME MIDDLENAME`, no extension).
- Scanner (`ScanService` / `config/scanner.php`): all 14 programs resolve the
  scanned QR text by exact equality against persisted `tbl_clients.full_name`
  (`findClientByName`, `TRIM(full_name) COLLATE utf8mb4_general_ci = ?`),
  `t.patient_name`, or seat join `s.name = c.full_name` + `LIKE %full_name%`
  fallbacks.
- Stored `full_name` order = `LASTNAME, FIRSTNAME MIDDLENAME EXTENSION`
  (extension AFTER middle; `ClientService::deriveFullName`). Read-only DB
  analysis (local `main_system`, no writes): 1,002 clients; 77 (7.7%) have
  extension + middle; for exactly those 77 the required new-order payload differs
  from the stored key (e.g. `TESTCLIENT 0014, MARIA L JR` stored vs
  `TESTCLIENT 0014, MARIA JR L` required) → their new QRs would fail every
  scanner lookup. Remaining 925 clients identical in both orders.
- STOP conditions triggered ("payload cannot be safely changed without breaking
  the scanner"; full names are non-unique keys). No application code changed;
  DB untouched; no new dependency. Gates: `git diff --check` clean (exit 0),
  view:cache OK, Pint `--test --dirty` passed, PHPUnit 301 passed / 1,419
  assertions (initial run failed only on the documented `C:\xampp\mysql\bin`
  PATH gotcha; clean on re-run). Owner-approved safe paths documented in the
  report (keep Decision C recommended; unique QR token needs additive migration;
  column-recomposition scanner lookup = separate reviewed milestone).

Report: `C:\Users\J\AppData\Local\Temp\opencode\c2-client-qr-name\C2_CLIENT_QR_FULL_NAME_REPORT.md`
---

## 2026-09-15 C3-B LANDED - Canonical display-name formatter for client display surfaces

Display-name formatter delivered across ALL client display surfaces (returns
`LAST, FIRST (EXT) MIDDLE` in v1 user-facing order, e.g. `TESTCLIENT 0014, MARIA (JR) L`),
including the client-search/autocomplete dropdowns via a new additive `display_name` transport.

**Core:** `ClientService::deriveDisplayName()` + `Client::displayFullName()` accessor (no
schema/QR/scanner/stored-`full_name` change), `ClientController::data()` (`fullname` +
`aria-label`), `ClientController::store()` duplicate payload (`display_full_name`),
`TransactionController` (ClientService-injected: `data()` `client_name`, CSV writers,
private `displayName()` helper).

**Views swapped to canonical:** `clients/_details` (5 sites), `clients/index` modal (2),
`transactions/create|edit|show`, `households/show` (6), `family_members/create`,
`scholars/gip-show` (2), `scholars/_form`. **Search transport (additive `display_name`, JS
consumer prefers `display_name ||` old composition — no formatter duplication in JS):**
`TransactionController::searchClients` (also feeds scholars), `FamilyMemberController::search`,
`HouseholdController::searchClientsForHousehold`, `ClientController::globalSearch`; JS in
`transactions/create|edit`, `scholars/_form`, `family_members/create`, `households/create`,
`partials/global-search` (2 sites, avatar-split intact).

**Untouched by design (hard rules):** QR payload/token (`qr/viewer`, `ScanService`,
`config/scanner.php`), `transactions/edit.blade.php:22` self-compare, `scholars/show` (tbl_scholar_info),
HouseholdController `search()` head-of-household picker (`head_name` stored proxy),
`HouseholdService.php:69`, `GranteeUpdateController::formatName`, unpaid self-service `.toUpperCase()`
stored-name displays, `update_logs/index` historical snapshots, `ReportController` CONCAT feeds,
`scanners/scan.blade.php` (incl. L644 — its autocomplete keeps stored order), scholars/scholar-index
`scholar_info`.

**Files:** 18 (17 modified + 1 new test `tests/Unit/ClientDisplayNameTest.php`, 13 tests).
C3-B tracked diff: 195 insertions / 71 deletions (stat + diff --check evidence in
`C:\Users\J\AppData\Local\Temp\opencode\c3b-display-name\`).

**Verification gates:** PHPUnit full suite **314 passed / 1,433 assertions / 0 failures**
(uses `main_system_test`; `main_system` untouched — 1,002 clients, users jiro/jordi held);
Pint `--test --dirty` PASS (one auto-fix `single_blank_line_at_eof`); `npm run build` OK
(vite 6.4.3, bundle `app-DqsLDVL_.js`); `php artisan view:cache` OK; `git diff --check` clean.

**Playwright:** new `e2e/client-display-name-phase22.spec.ts` (3 tests: extension-client
canonical in table+panel; non-extension without parentheses; GIRONELLA twin disambiguation +
panel match). **24/24 green** (3 tests x 8 projects: chromium, firefox, webkit, Mobile Chrome,
Mobile Safari, Microsoft Edge, Google Chrome) when projects run per-project sequentially.
NOTE: a stress run of ALL projects concurrently (24 parallel logins) failed at login/table load
under the auth throttle/lockout — environmental (repeated rapid logins from one IP), not an app
defect; the same spec passes 3/3 chromium immediately after.

**Pre-existing spec staleness (NOT C3-B regressions, NOT touched):** `clients.spec.ts:14`
(`#clientFormModalTitle`) and `:40` (`data-filter-done`) target elements the C2 index-refactor
removed (current UI: `#clientFormModalBody`, `data-filter-segment`); last spec edit at commit
`abe331a` predates the refactor; C3-B's index diff is 5 display-name lines only. Flagged for a
future spec-repair task, out of this cycle's scope.

**DB hygiene:** ephemeral Playwright user `smoke_superadmin` (id 16, bcrypt `SmokeAdmin2026!`)
+ its `tbl_permissions` `'*'` row + all test-run audit rows `id > 1779` deleted; `tbl_users`
restored to jordi/jiro only with the test-run `last_activity` bookkeeping timestamp reverted
(`02:21:24` -> `02:12:13`) — post-restore dump INSERT lines byte-identical to the pre-smoke dump
(diff line count 0); `tbl_clients` 1,002; audit max id 1779; permissions 11 rows; no schema
change; `id=16` AUTO_INCREMENT gap left as a documented non-destructive artifact (tbl_users next
id ~17).

FULL REPORT: `C:\Users\J\AppData\Local\Temp\opencode\c3b-display-name\C3B_DISPLAY_NAME_REPORT.md`
---

## 2026-09-15 C3-C LANDED - persistent client QR code identity token (`qr_token`)

**Goal:** give every client a stable, opaque, long-lived, reusable, non-expiring QR identity
token so scanners can later resolve a scan to the exact client without relying on the mutable
`full_name` string. C3-C adds ONLY the identity column — the QR **payload** (comma-form
`full_name` into api.qrserver.com) and the scanner token-resolution are intentionally deferred
to C3-D/C3-E. `qr_token` is a persistent client identity token, **not** an appointment ticket.

**Schema (additive, reviewed):** `database/migrations/2026_09_15_000000_add_qr_token_to_tbl_clients_table.php`
(recorded id=13, batch 3). Staged on existing data — bytes of every pre-existing column are
PROVEN unchanged (see forensics): `qr_token` added nullable -> all 1,002 existing rows
backfilled via `Client::ensureQrTokens()` -> unique index `tbl_clients_qr_token_unique` applied
-> column set NOT NULL. Column: `char(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin`. The
case-sensitive `utf8mb4_bin` collation is deliberate: the table's default `utf8mb4_unicode_ci`
is case-insensitive and would make case-distinct tokens collide under the unique index.
Migration is guarded to be idempotent in both directions (client + index exist checks). NOTE:
Laravel 12 anonymous migrations have no `$this->command` (first run failed on a stray info()
line; removed; second run completed 290.72ms) — do not use `$this->command` in future migrations.

**Token contract:** opaque, CSPRNG-drawn (`Str::random(16)` -> `random_int`), 16 chars, base62
`[0-9A-Za-z]` (~95 bits). Encodes NO name, client id, birthdate, program, or transaction data;
never derived deterministically from client attributes. Unique index is the final authority
(backfill retries in-run collisions before hitting the DB).

**Model (`app/Models/Client.php`):** `booted()` `creating` hook assigns `qr_token` when empty
(the single guaranteed assignment point); static `generateQrToken()`; static `ensureQrTokens()`
(chunkById 500, only NULL/blank rows written, existing non-null tokens never overwritten —
re-running returns 0). `qr_token` is deliberately NOT in `$fillable`, so mass-assignment and the
single `ClientService::update` write path can never overwrite it; name/extension/middle-name/household
edits leave it untouched (pin-tested).

**Forensics (local `main_system` = byte-identical production copy):**
- Pre-forensic state: 1,002 clients, max id 1003, min id 1; 0 blank `full_name`; 0 duplicate
  `full_name`/name groups; FK-referencing tables unchanged (audit_logs 1,647, client_aff_orgs
  1,807, transactions 501, users 2, permissions 11).
- Full backup first: `pre-c3c-backup-main_system.sql` (1,522,836 bytes).
- Post-migration: 1,002/1,002 tokens assigned; every row valid `^[0-9A-Za-z]{16}$`; NULL=0,
  blank=0, duplicate groups=0, distinct=1,002; `char(16) NOT NULL utf8mb4_bin` + UNIQUE key
  verified via information_schema (NON_UNIQUE=0).
- Byte-identical proof: pre-backup restored into scratch DB `c3c_pre`; per-row client signature
  (30 pre-existing columns, qr_token excluded) SHA256 **identical** both sides (1002/1002);
  all-table row counts identical except `migrations` pre=12 -> post=13 (the one new migration
  row, batch 3). Scratch DB dropped after comparison.

**Tests:** new `tests/Feature/ClientQrTokenTest.php` (11 tests, 224 assertions). The test
database is post-migration (column NOT NULL + UNIQUE), so the pre-migration "rows without
tokens" state is unreproducible there — the real backfill is covered forensically above; the
suite pins the post-migration invariants instead (every client auto-token, alphabet/length,
cross-client uniqueness, canonical name columns + client-id relationships preserved, edits never
change the token incl. through `ClientService::update`, mass-assignment guard via non-fillable,
backfill re-run is a 0-op). Full suite **325 passed / 1,657 assertions / 0 failures**
(uses `main_system_test`; `main_system` untouched).

**Other gates:** Pint `--test --dirty` PASS; `npm run build` OK (unchanged bundle
`app-DqsLDVL_.js` — no JS touched); `php artisan view:cache` OK; `git diff --check` clean.
Baseline dump regenerated (`database/schema/mysql-schema.sql`) with the deploy-only
`__legacy_v1_baseline_schema__` sentinel (id 10) stripped; new migration recorded as id 13 —
adds exactly `qr_token` column + unique key + the migration row (schema diff: 5 insertions /
2 deletions).

**Playwright (`e2e/client-qr-token-phase23.spec.ts`, 3 tests, chromium): 3/3 green.** Pins the
unchanged contract end-to-end: (1) clients list + details panel render the C3-B canonical
display name with NO QR material on the panel; (2) public `/qr-viewer` still loads, populates
the 35 municipalities, suggests the persisted comma-form `full_name` (Decision C payload —
suggestion text asserted exact, and it is never a 16-char token) and arms the Verify flow;
(3) `/scanners` hub + the CEAP scanner engine render unchanged (hint: grantee name search only
matches CEAP-family pending-payout clients, so the fixture client needs a qualifying
transaction — `TESTCLIENT 0013` used).

**DB hygiene:** pre-smoke dump of `tbl_users`+`tbl_permissions` captured; ephemeral user
`smoke_superadmin` (id 17) + its `'*'` permission row (id 26) + all test-run audit rows
`id > 1779` deleted; jordi's test-run `last_activity` reverted to `02:12:13`; users back to
jordi/jiro, permissions 11 rows, audit max id 1779. Post-restore dump value rows byte-identical
to pre-smoke (only declared AUTO_INCREMENT counter advanced 17 -> 18 — non-destructive
documented artifact). `qr_token` column remains populated for all 1,002 rows (intended: it is
the C3-C deliverable).

**Files:** 6 C3-C files (1 new migration, 1 model edit, 1 regenerated schema dump, 1 new Feature
test, 1 new e2e spec) + docs. Untouched by hard rule: `qr/viewer.blade.php`,
`grantee_update/self-service`, `_self_update_tab`, config/scanner.php, ScanService, scanner
routes/JS, `patient_name`, name formatter/ClientService display logic, transactions/households/
scholars/unpaid views, no new package.

FULL REPORT: `C:\Users\J\AppData\Local\Temp\opencode\c3c-qr-token\C3C_QR_TOKEN_REPORT.md`
---

## 2026-09-15 C3-D LANDED - token-first client resolution in the scanner engine (legacy fallback preserved)

**Goal:** introduce a backward-compatible, token-first client identity resolution layer so the
P4 scanners can resolve BOTH new `qr_token` values AND existing legacy `full_name` QR payloads
— WITHOUT changing any QR generator. C3-D is additive and stops here by design: C3-E remains
responsible for switching QR payload generation to `qr_token`; the Details Panel QR card is
explicitly NOT part of C3-D.

**Files:** `app/Services/ScanService.php` (the single scanner engine) + new
`tests/Feature/ScannerTokenResolutionTest.php`. Nothing else. Schema unchanged (no migration;
the `database/schema/mysql-schema.sql` baseline is byte-identical to C3-C). No QR generator
touch (`qr/viewer.blade.php`, `grantee_update/self-service.blade.php`,
`_self_update_tab.blade.php`, api.qrserver.com unchanged). No model/name-formatter change
(`displayFullName()`, `deriveDisplayName()`, stored `full_name`/`match_name`/`patient_name`,
seat/exam/results tables all untouched).

**Resolution order (implemented, from spec):** scanned value -> 1) exact `qr_token` lookup
(`where('qr_token', $scanned)` ~ equality under `utf8mb4_bin`, index-usable) -> 2) legacy
`full_name` resolution (`TRIM(full_name) COLLATE utf8mb4_general_ci`, byte-identical to
before C3-D) -> 3) existing specialized strategy behavior.

**Engine change map (all in `app/Services/ScanService.php`):**
- `public function resolveClient(string $scanned): ?Client` added — the only client-identity
  boundary. Exact token match first (`Client::query()->where('qr_token', $scanned)->first()`,
  uses `tbl_clients_qr_token_unique`; EXPLAIN `type=const`, `key=tbl_clients_qr_token_unique`,
  `key_len=64`, `rows=1`; non-existent tokens fold to `Impossible WHERE noticed after reading
  const tables` — no full-table scan, no LIKE/contains/case-fold/regex, no client-id parsing,
  no N+1). Then legacy `findClientByName($scanned)`.
- `lookupClient` (client: ceap, ceap_new, cedssg, cedssg_new, otces, otea, generic) ->
  `resolveClient`.
- `lookupClientGeo` (client_geo: toda, tupad) -> `resolveClient`.
- `lookupExistingProgram` (existing_program: ongoing_scholars) -> `resolveClient`.
- `lookupExamDerived` (exam_derived: new_scholars) -> `resolveClient`; the unchanged name-keyed
  exam/result query (`TRIM(fullname) COLLATE utf8mb4_general_ci`) keeps the scanned value as its
  key for legacy scans (byte-identical), and uses the client's persisted `full_name` when the
  scan resolved by token (`$client->qr_token === $scanned`) so the unchanged linkage is reached.
- `findClientByName` remains as the PRIVATE legacy fallback — nothing replaced it.
- Specialized strategies stay specialized and pass NO token resolution (acceptance gate):
  `transaction` (cedssg_update, patient_name-keyed), `transaction_partial` (payout_unpaid),
  `seat_join` (payout, dispatched directly in `lookup()`).

**Legacy invariance proven:** existing `tests/Feature/ScannerTest.php` (18 tests / 125
assertions) still green — all 14 programs keep their legacy name resolution untouched.

**Tests:** new `tests/Feature/ScannerTokenResolutionTest.php` — 17 tests / 51 assertions:
token resolves the exact client; unknown token falls back to the legacy name path; a literal
16-char full_name that looks like a token still resolves by name; legacy name still resolves;
token lookup is exact (no prefix/suffix matches); token lookup is case-sensitive (case-mutated
token fails); token resolves to exactly one client; name edits do not affect token resolution;
extension client (stored `LAST, FIRST MIDDLE EXT`) resolves by token AND is independent of the
C3-B `displayFullName()` formatter; GIRONELLA duplicate-name pair resolves independently by
token; distinct tokens -> distinct clients; client_geo (toda) token OK; existing_program
(ongoing_scholars) token OK; transaction strategy REJECTS the client token (patient_name
only); seat strategy REJECTS the client token (seat name only); exam strategy legacy unchanged
+ token resolves to the same program; output shape unchanged (`['id','full_name']`,
token == name payload); EXPLAIN proves the UNIQUE-index const lookup.
Forensic baseline re-confirmed on live `main_system`: 1,002 clients, tokens all distinct /
length 16 / 0 missing, utf8mb4_bin case-sensitivity verified, UNIQUE index present; 0 legacy
`full_name` shaped like a token and 0 full_names equal to another client's token (token-first
cannot shadow a real legacy name).

**Gates:** full PHPUnit suite **342 passed / 1,708 assertions / 0 failures** (`main_system_test`
only; `main_system` untouched). Pint `--test` PASS. `php artisan view:cache` OK.
`npm run build` OK. `git diff --check` clean.

**Changed-file scope:** `git status` shows exactly ScanService + the new test from C3-D; every
other dirty file in the working tree pre-dates C3-D (prior milestone streams — Tailwind/M6/C1/
C2/C3-B/C3-C). No files reverted, none added beyond scope.

**Docs statements (explicit):** qr_token resolves FIRST; legacy `full_name` remains the
FALLBACK; specialized strategies (transaction / transaction_partial / seat_join) unchanged;
existing legacy QRs remain fully valid; QR payload generation has NOT switched to qr_token yet;
Details Panel QR card NOT implemented; C3-E owns the QR generation switch; C3-F (removing the
legacy fallback) not started.

FULL REPORT: `C:\Users\J\AppData\Local\Temp\opencode\c3d-token-resolution\C3D_TOKEN_RESOLUTION_REPORT.md`
**C3-D STATUS: ACCEPT / STOP — C3-E intentionally not started.**

---

## 2026-09-15 C3-E LANDED - QR payload switch: all live producers encode the client `qr_token`

**Goal:** change ALL live QR generators from the legacy persisted `full_name` payload to the
persistent, immutable `Client::qr_token` — the token-first identity already resolved by the P4
scanners since C3-D. The legacy `full_name` fallback inside `ScanService::resolveClient()` is
PRESERVED (legacy printed QRs remain scannable); the switch is producer-side only.

**Producer census (all verified on the live tree):** three live producers were found:
1) `resources/views/qr/viewer.blade.php` (`data.client.full_name` payload, sourced from
`GranteeSearchController::verify`), 2) `resources/views/grantee_update/self-service.blade.php`
(JS-composed `LAST, FIRST MIDDLE` from form fields after `grantee-update.store`), and
3) `resources/views/grantee_update/_self_update_tab.blade.php` (same composition). Both
self-service views POST to `grantee-update.store` (`GranteeUpdateController::store` →
`GranteeUpdateService::update`), whose success payload previously returned only
`['success' => true]` — the token is now carried on that response (never composed client-side;
the JS `selectedClientId` is the DB client id and is NOT encoded). Dead remnant confirmed and
left untouched: `resources/views/scholars/show.blade.php:210` `data-qr-scholar` has no handler
in shipped JS (only `prototype/js/app.js`).

**Controlled changes (C3-E only, 5 production files + 2 test files):**
- `app/Http/Controllers/GranteeSearchController.php` — `CLIENT_COLUMNS` adds `qr_token`
  (the viewer's payload source; `full_name` stays for the human-facing display and the
  download filename). Docblock updated.
- `app/Services/GranteeUpdateService.php` — success returns
  `['success' => true, 'qr_token' => (string) Client::query()->where('id', $clientId)->value('qr_token')]`;
  return type widened to `array{success: bool, message?: string, qr_token?: string}`.
- `resources/views/qr/viewer.blade.php` — QR `data=` now `encodeURIComponent(client.qr_token)`;
  `fullName` remains ONLY for `#qrName` display + download filename. Batch G comment updated.
- `resources/views/grantee_update/self-service.blade.php` and
  `_self_update_tab.blade.php` — QR `data=` now `encodeURIComponent(data.qr_token)`; the
  onerror copy-text fallback displays `${token}` (was `${fullName}`); `fullName` remains the
  human label + download name. Top comment updated in `self-service.blade.php`.
- `app/Http/Controllers/QrController.php` — docblock updated (payload = qr_token; legacy
  fallback preserved in ScanService).

**Unchanged (absolute boundaries):** schema/migrations (dump unchanged since C3-C);
`qr_token` never regenerated by name edits (immutable identity); `ScanService` untouched by
C3-E (C3-D resolver contract intact, including the legacy fallback, the exact/case-sensitive
token lookup and the protected specialized strategies `transaction`/`transaction_partial`/
`seat_join`); `displayFullName()`/`deriveDisplayName()` untouched; QR provider + dimensions
(`api.qrserver.com`, `220x220`) and the Sunday-info note fallback behavior unchanged; no
Details Panel QR card; **C3-F NOT started** (legacy fallback removal is out of C3-E scope);
no production\/`main_system` writes (the E2E self-service/self-update submit flow was NOT run
because `grantee-update.store` writes to `main_system`).

**Tests:** `QrViewerTest` grew to **7 tests / 23 assertions**: verify returns `qr_token`;
token is base-62 length-16; the viewer page wires `data.client.qr_token` + `encodeURIComponent(qrPayload)`
and does NOT wire `encodeURIComponent(fullName)`; the extension client payload is the token, NOT
the `displayFullName()`/`full_name` display value. `GranteeUpdateTest` grew to **15 tests / 51
assertions**: store success returns `qr_token`; token is base-62 length-16; **token unchanged
after a name edit (name-invariance / immutable-identity gate)**; `self-service` and
`_self_update_tab` pages wire the token payload from the store response. `ScannerTest`
(18/125) and `ScannerTokenResolutionTest` (17/51) untouched and green — legacy full_name QR
resolution still fully valid.

**Gates:** full PHPUnit suite **351 passed / 1,734 assertions / 0 failures**. Pint `--test`
PASS. `php artisan view:cache` OK (`view:clear` after). `npm run build` OK (bundle unchanged —
blade inline JS only). `git diff --check` clean (CRLF warnings pre-existing only). Playwright
`--project=chromium -g "QR viewer"` in `e2e/client-qr-token-phase23.spec.ts`: **2 passed** —
the C3-E test intercepts the public verify POST, reads the rendered `#qrImage img` src, decodes
`data`, asserts `/^[0-9A-Za-z]{16}$/` and equality with the server `client.qr_token`, and
asserts `#qrName` shows full_name (not the token). The municipality for the verify is resolved
from the public `grantee-search` autocomplete response (arbitrary municipalities correctly fail
verify). Auth-gated phase23 spec tests depend on `smoke_superadmin`, which does not currently
exist on local `main_system` (cleaned after C3-C) — pre-existing env state, not a C3-E issue.

**Changed-file scope (C3-E additions on top of the pre-existing dirty working tree):**
`app/Http/Controllers/GranteeSearchController.php`, `app/Http/Controllers/QrController.php`,
`app/Services/GranteeUpdateService.php`, `resources/views/qr/viewer.blade.php`,
`resources/views/grantee_update/self-service.blade.php`,
`resources/views/grantee_update/_self_update_tab.blade.php`,
`tests/Feature/QrViewerTest.php`, `tests/Feature/GranteeUpdateTest.php`,
`e2e/client-qr-token-phase23.spec.ts`. The `.d-none→.hidden`/label/`ui.css` hunks visible in
those blades' working-tree diffs are PRE-EXISTING (prior Tailwind/ui.css milestone streams),
not C3-E. `ScanService.php` + `ScannerTokenResolutionTest.php` remain C3-D's, untouched.

**Docs statements (explicit):** QR payload generation HAS switched to `qr_token` for all three
live producers; legacy `full_name` QRs remain scannable via the C3-D fallback (NOT removed);
`qr_token` is long-lived / reusable / non-expiring and unaffected by grantee name edits; the
Details Panel QR card is NOT implemented; C3-F (legacy fallback removal) NOT started.

FULL REPORT: `C:\Users\J\AppData\Local\Temp\opencode\c3e-qr-payload-switch\C3E_QR_PAYLOAD_SWITCH_REPORT.md`
**C3-E STATUS: ACCEPT / STOP — C3-F intentionally not started.**
---

## 2026-09-15 C3-F LANDED — Details Panel QR identity card (additive-only, no legacy fallback removal)

**Goal:** add ONE QR identity card to the existing Client Details Panel — QR payload
equals the client's persisted `qr_token` only; human display equals
`Client::displayFullName()` only; plus Print + Download actions. Legacy `full_name`
fallback in `ScanService::resolveClient()` NOT removed; scanner config, schema, DB, and
all existing QR producers intentionally untouched.

**What changed (C3-F only):**
- `resources/views/clients/_details.blade.php` — QR Identity Card section inserted under
  the Personal Information section (before CONTACT INFORMATION & ADDRESS) inside
  `[data-panel-body]`: `<img id="clientQrImage" src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={{ urlencode($client->qr_token) }}&format=png">`;
  `<div id="clientQrName">{{ $client->displayFullName() }}</div>`;
  `<div id="clientQrId">Client ID: {{ $client->id }}</div>`;
  Print button (`#clientQrPrintBtn`, btn-navy) + Download link (`#clientQrDownloadLink`,
  btn-subtle, `download="{{ $client->qr_token }}_qr.png"`).
  Client-side print IIFE added (inline `<script>` inside `data-panel-body`; opened via
  `window.open` + `doc.write` + `win.print()`; popup HTML contains `print()` — NOT the
  literal `<html>` string — to satisfy the pre-existing C1 partial assertion).
  Action grid (`data-panel-actions`) untouched.
- `tests/Feature/ClientDetailsPanelQrCardTest.php` — **15 tests / 106 assertions** added:
  panel renders QR Identity Card under Personal Information; payload equals `qr_token`;
  payload is exactly 16 base62 chars; payload is NOT `full_name` / `displayFullName()` /
  client id; display name uses the C3-B formatter; extension client shows
  `TESTCLIENT 0014, MARIA (JR) L` (NOT `MARIA L JR`); token unchanged after name edit +
  panel re-render; all four panel actions intact (super-admin user); QR card position before
  action grid; Print action present + `win.print` reachable; Download action encodes token
  and filename is `{token}_qr.png`; GIRONELLA duplicate pair distinguishable by id+token;
  QR card responsive-friendly markup; QR generator conventions (220x220, format=png).
- `e2e/client-details-qr-card-phase24.spec.ts` — focused Playwright spec written (2 tests):
  test 1: login, search/open TESTCLIENT 0014 panel, verify `#clientQrImage` src
  `data=` equals server `qr_token` via the `grantee-search` verify endpoint; Print/Download
  present; action grid intact; test 2: card absent before panel open, 220x220 attrs,
  absent after close. **Auth-gated (needs `smoke_superadmin`, absent on local
  `main_system` since C3-C hygiene — documented env-block, same as C3-E; spec would run
  green when the account is restored).**
- `e2e/client-display-name-phase22.spec.ts` — stale C3-B "no QR card" assertion replaced
  with QR presence check + `#clientQrName` text assertion.

**Unchanged (absolute boundaries):** `ScanService::resolveClient()` and scanner config
untouched; `qr_token` generation/schema/migrations unchanged; DB untouched (no INSERT /
UPDATE / DELETE / ALTER / DROP / TRUNCATE); QR payload format unchanged; all existing QR
producers (`qr/viewer`, `grantee_update/self-service`, `_self_update_tab`) unchanged;
public QR viewer unchanged; CEAP appointments unchanged; client CRUD untouched; no new
package, library, or framework added; `GranteeSearchController`, `GranteeUpdateService`
unchanged; `clients.show` layout page unchanged (card is inside the shared `_details`
partial, works in both panel + full-page modes).

**Gates:** full PHPUnit suite **366 passed / 1,840 assertions / 0 failures**. Pint
`--test` PASS. `php artisan view:cache` OK. `npm run build` OK. `git diff --check` clean
(CRLF warnings pre-existing only). Playwright `--project=chromium -g "QR viewer"` in
`e2e/client-qr-token-phase23.spec.ts`: **2 passed** (auth-free QR viewer regression).
Auth-gated phase22/phase24 Playwright tests depend on `smoke_superadmin`, absent from
local `main_system` — documented env-block.

**Changed-file scope (C3-F only, atop the pre-existing dirty working tree):**
`resources/views/clients/_details.blade.php`, `tests/Feature/ClientDetailsPanelQrCardTest.php`,
`e2e/client-details-qr-card-phase24.spec.ts`, `e2e/client-display-name-phase22.spec.ts`
(stale C3-B assertion update), `docs/IMPLEMENTATION_LOG.md`, `docs/SESSION_HANDOFF.md`.
All other dirty files in the working tree pre-date C3-F (prior milestone streams —
Tailwind/M6/C1/C2/C3-B/C3-C/C3-D/C3-E). ScanService.php untouched; GranteeSearchController
untouched; GranteeUpdateService untouched; qr/viewer.blade.php untouched;
grantee_update/self-service.blade.php untouched;
grantee_update/_self_update_tab.blade.php untouched.

**Docs statements (explicit):** Details Panel QR card IS implemented; QR payload is
`qr_token` (immutable, 16-char base62); display uses `displayFullName()`; legacy
`full_name` fallback in `ScanService::resolveClient()` is NOT removed; legacy printed
QRs remain scannable; `qr_token` is long-lived / reusable / non-expiring; no further
milestones in this chain.

FULL REPORT: `C:\Users\J\AppData\Local\Temp\opencode\c3f-details-panel-qr-card\C3F_CLIENT_DETAILS_PANEL_QR_CARD_REPORT.md`
**C3-F STATUS: ACCEPT / STOP — end of C3 chain.**

---

## 2026-09-15 C3-F UI CLEANUP LANDED — QR identity card compact horizontal presentation

**Goal:** presentation-only correction of the C3-F QR identity card in the Client
Details Panel. The QR was too large and carried unnecessary Print/Download controls.
Changed ONLY the card's visual presentation.

**What changed (presentation only):**
- `resources/views/clients/_details.blade.php` — the QR card is now a compact
  horizontal layout: `details-section > data-card p-[1.25rem]` containing
  `flex flex-wrap items-center gap-[16px]` with a `120px x 120px` QR image
  (`h-[120px] w-[120px] shrink-0`, `width="120" height="120"`) BESIDE static
  explanatory text `Client QR Code` (`#clientQrLabel`) / `For easy scan access`
  (`#clientQrHint`). On very narrow widths `flex-wrap` lets the text wrap
  naturally; the QR stays fixed at 120px (no panel-width overflow). Print button,
  Download link, and the client-side print window IIFE were removed. The img
  `src` (qrserver endpoint, `size=220x220`, `data={{ urlencode($client->qr_token) }}`,
  `format=png`) is BYTE-IDENTICAL to C3-F — only the rendered size changed.
- `tests/Feature/ClientDetailsPanelQrCardTest.php` — updated to the new contract
  (14 tests / 113 assertions): card still renders; explanatory text present;
  QR precedes the text (horizontal); 120px via CSS + width/height attrs;
  the canonical C3-B display-name assertion now targets the panel header
  (`data-panel-title`, unchanged) instead of the removed `#clientQrName`;
  Print/Download absence (`clientQrPrintBtn`, `>Print<`, `win.print`,
  `clientQrDownloadLink`, `>Download QR<`, `download=`) replaces the old
  presence tests; generator conventions unchanged (`QR_API`, `size=220x220`,
  `format=png`).
- `e2e/client-details-qr-card-phase24.spec.ts` — updated: asserts `#clientQrLabel`
  `Client QR Code`, `#clientQrHint` `For easy scan access`, ~120px (attrs +
  boundingBox 100-130), Print/Download absence, QR payload == server `qr_token`
  (client id now read from the row `data-id`), action grid intact.
- `e2e/client-display-name-phase22.spec.ts` — dropped the removed `#clientQrName`
  assertion (canonical name still verified via `#detailsPanelTitle`).

**Unchanged (absolute boundaries):** QR payload (token), qrserver endpoint/producer/
`size=220x220`, scanner behavior, `ScanService`, scanner config, `qr_token`
generation/schema/migrations, database row data, Client model, `ClientService`
(displayFullName/deriveDisplayName still used by the panel header/profile),
`GranteeSearchController`, `GranteeUpdateService`, `QrController`, all existing QR
producers (`qr/viewer`, both self-service blades), CEAP appointments, client CRUD,
`DetailsPanel.js`, the 2x2 action grid (Add Transaction / Open Full Page / Edit /
Delete), panel mode + full-page mode. No production/`main_system` writes (PHPUnit on
isolated `main_system_test` only).

**Gates:** full PHPUnit suite **365 passed / 1,847 assertions / 0 failures** (was 366 —
the two Print/Download presence tests were replaced by one combined absence test).
Pint `--test` PASS. `php artisan view:cache` OK. `npm run build` OK (Tailwind emitted
the new arbitrary 120px utilities). `git diff --check` clean (CRLF warnings pre-existing
only). Playwright `--project=chromium -g "QR viewer"` in `client-qr-token-phase23.spec.ts`:
**2 passed** (public QR viewer unchanged). Auth-gated phase22/phase24 specs fail at login
only — `smoke_superadmin` absent on local `main_system` since C3-C hygiene (pre-existing
env limitation, unchanged).

**Changed-file scope (this correction only, atop the pre-existing dirty working tree):**
`resources/views/clients/_details.blade.php`, `tests/Feature/ClientDetailsPanelQrCardTest.php`,
`e2e/client-details-qr-card-phase24.spec.ts`, `e2e/client-display-name-phase22.spec.ts`,
`docs/IMPLEMENTATION_LOG.md`, `docs/SESSION_HANDOFF.md`.

FULL REPORT: `C:\Users\J\AppData\Local\Temp\opencode\c3f-ui-cleanup\C3F_UI_CLEANUP_REPORT.md`
**C3-F UI CLEANUP STATUS: ACCEPT / STOP — presentation-only correction complete. No C3-G or other milestone started.**

## 2026-09-16 DETAILS PANEL FIX LANDED — mobile/tablet stacking + scroll fix (CSS-only)

**Root cause (forensic-verified, report at
`C:\Users\J\AppData\Local\Temp\opencode\forensic-details-panel-mobile-scroll\FORENSIC_REPORT.md`):**
the panel IS full-height (`position: fixed; top: 0; right: 0; bottom: 0`) and
`#detailsBody` (`flex:1; overflow-y:auto; min-height:0`) is already a valid scroll
container; the bug was purely stacking. `.details-panel` has an animated
`transform`, so the `position: fixed` `#detailsBackdrop` resolves `inset:0`
against the panel box (full viewport on mobile, the 50vw panel box on tablet) and
paints **above** the static `.details-panel-inner` at `z-index: 150`. With
`.open` setting `pointer-events: auto`, the dark overlay covered the entire drawer:
content un-clickable and wheel scroll swallowed below 1024px (≥1024px is safe
only because `@media` hides the backdrop with `display:none`). Desktop sub-1024,
all mobile, and tablet (768–1023px) were affected. Bug predates C3-F
(panel CSS last committed `abe331a`).

**What changed (CSS-only, one file):**
- `resources/views/partials/details-panel.blade.php`
  - `.details-panel .details-backdrop`: `z-index: 150` → `z-index: 1`
    (moves the dark overlay underneath the drawer content layer).
  - `.details-panel-inner`: prepended `position: relative; z-index: 2;`
    (content now paints above the backdrop; backdrop still sits above the page
    behind the drawer, keeping the dimmed look and page-behind lockout).
- `e2e/details-panel-scroll-fix.spec.ts` — NEW auth-free regression spec
  (harness reads the real partial `<style>` block from disk):
  mobile 390x844 (10 checks: open, overflow-y auto, topmost hit-test over content
  and over the QR image ≠ backdrop, QR 120px cards intact/label/hint, wheel
  scroll increases `#detailsBody.scrollTop`, body `overflow:hidden`, ESC close,
  reopen + X close; X not under the backdrop), tablet 900x800
  (same stacking + scroll contract), desktop 1440x900 (backdrop `display:none`
  unchanged, panel scrolls, X close unchanged).

**Intentional consequence (expected, not preserved):** backdrop **click-to-close**
is inert on mobile/tablet now (it sits below the content layer); X and ESC close
remain the sanctioned exits, matching the approved fix.

**Gates:** full PHPUnit suite **365 passed / 1,847 assertions / 0 failures**
(baseline unchanged — no PHP code touched). Pint `--test` PASS. `php artisan
view:cache` OK. `npm run build` OK (vite v6.4.3). `git diff --check` clean
(pre-existing CRLF warnings only). Playwright `details-panel-scroll-fix.spec.ts`
**21/21 passed across all 7 projects** (chromium, firefox, webkit, Mobile Chrome,
Mobile Safari, Edge, Chrome) — mobile/tablet/desktop × wheel (real `mouse.wheel`
on every engine; synthetic-wheel fallback only where mobile WebKit rejects
`mouse.wheel`). No production/`main_system` writes.

**Environmental limitation (unchanged, disclosed):** authenticated E2E against the
live app remains blocked — `smoke_superadmin` is absent from local `main_system`
since C3-C hygiene and must not be recreated. This fix's regression is therefore
covered by the auth-free harness above; no live-browser test was claimed.

**Changed-file scope (this fix only, atop the pre-existing dirty working tree):**
`resources/views/partials/details-panel.blade.php` (2 CSS declarations only —
the `.details-avatar`/identity tweaks in the same file are a pre-existing working-
tree change, not part of this fix), `e2e/details-panel-scroll-fix.spec.ts` (new),
`docs/IMPLEMENTATION_LOG.md`. No JS/QR/schema/DB/controller/route changes.
**STATUS: COMPLETE — ACCEPT / STOP — details panel mobile/tablet scroll + darkening fixed with the approved CSS-only stacking change.

---

## 2026-09-16 — Details Panel body darkening remainder fixed (forensic-approved 1-line CSS fix)

**Context.** The accepted stacking fix (backdrop `z-index: 150→1`; `.details-panel-inner` `position:relative; z-index:2`) restored scrolling/interaction but left the drawer body visually darkened below 1024px. A read-only forensic inspection (`C:\Users\J\AppData\Local\Temp\opencode\darkness-forensics\FORENSIC_REPORT.md`) proved empirically that `#detailsBackdrop` (`rgba(12,22,34,0.45)`) was still the sole cause: the panel's non-`none` transform makes the fixed backdrop's containing block the panel box; at z-index 1 it paints above the panel's white background but below the transparent `.details-panel-inner`/`#detailsBody`, compositing through every transparent region (`rgb(145,150,155)` == exact 0.45 backdrop over `#FFFFFF`). Header/actions/QR stayed bright because their own backgrounds are opaque.

**Change (exactly one declaration, exactly one file):**
- `resources/views/partials/details-panel.blade.php` → `.details-panel-inner` added `background: var(--color-surface);` (all existing declarations unchanged). No other production file touched — no JS, no backdrop, no `#detailsBody`, no QR/scanner/schema/DB/route/controller changes.

**Verification:**
- Pixel proof via read-only harness (real partial `<style>` + real built `app.css`, temp dir): mobile 390×844, tablet 900×800 — body samples now `rgb(255,255,255)` (previously `145,150,155`), header stays navy `rgb(11,65,162)`, actions/QR unchanged; tablet page-behind stays undimmed `240,242,245`; desktop 1440×900 unchanged (backdrop already `display:none`).
- `npm run build` OK (app-CiHyDIU8.css manifest).
- `php artisan view:cache` / `view:clear` OK.
- `php artisan test`: 365 passed / 1,847 assertions / 0 failures (baseline identical).
- `vendor\bin\pint --test`: passed.
- `git diff --check`: clean (2 pre-existing CRLF notices only).
- `e2e/details-panel-scroll-fix.spec.ts`: 21 run — 19 passed; 2 Firefox tests failed only on flaky context-teardown (`browserContext.close: Test ended`, hjuggler SWGL crash during graceful close), both passed on immediate targeted rerun. No functional/assertion failure; unchanged from pre-fix flakiness.

**Focused QR/client-details specs.** Server-side regression coverage already passed in the full suite (`ClientDetailsPanelQrCardTest`, `ClientQrTokenTest`, `ScannerTokenResolutionTest`, `QrViewerTest`, `ClientTest`). Authenticated E2E remains blocked by the absent local `smoke_superadmin` (env limitation, unchanged); no auth state was changed and no account created, per instructions.

**STATUS: DETAILS PANEL BODY DARKENING FIX — COMPLETE

---

## 2026-09-20 (supplemental correction) — Details panel delete-confirm + Family Composition/Transactions accordions: verified root cause + fix CORRECTED

**Correction to the claim above.** The 2026-09-20 "single point" root-cause note (executeScripts `container.isConnected`) was **empirically disproven** in a real Chromium browser. A DOMParser-created **detached Document is its own shadow-including root, so `Document.isConnected` is always `true`** — the in-place `replaceChild` branch was (and would always have been) selected even for detached fragments, meaning fragment inline scripts still never executed in the live page. A browser probe captured `parserDocIsConnected: true` for `new DOMParser().parseFromString(html,'text/html')` on a detached fragment, confirming exactly this.

**Verified root cause (real browser, real fragment, real public JS/CSS artifacts via the real controller).** `executeScripts(container)` only executes a fragment's inline scripts when the script node is inserted into the **live document**. Panel loads parse via `DOMParser` into a **detached** document; the previous in-place `replaceChild`/`isConnected` logic operated inside the detached document, which never runs scripts. Result: the panel's delete-confirm IIFE and the Family Composition/Transactions accordion IIFEs never ran → delete bypassed confirmation, accordions inert.

**Fix (landed + real-browser verified).**
- `resources/js/components/DetailsPanel.js` `executeScripts(container)`: scripts from a **detached** container are now unconditionally appended to the **live** `document.body` (fresh copy, global scope, executed synchronously in order — the legacy innerHTML contract). Live containers keep the in-place `replaceChild` contract. The `isConnected` predicate is no longer used (it cannot distinguish a detached DOMParser Document).
- **Second root cause found by the same browser trace:** the original delete-confirm **modal** (`#uiConfirmModal`, `z-[200]`) sits at the **same z-index as the details panel** (`.details-panel`, `z-index:200`), and because `DetailsPanel.load()` appends the panel shell to `body` *after* the page-rendered modal partial, the equal-z, later-in-DOM panel covered the modal's Accept button (pointer-events intercepted; Playwright click trace: "`.details-section` … intercepts pointer events"). Aligned with the existing app convention (client-feedback modal already uses `z-[210]`): confirm modal raised to `z-[210]` in `resources/views/partials/confirm-modal.blade.php`.
- `public/js/components/DetailsPanel.js` rebuilt (byte-identical to source; only DelimitsPanel source is authoritative).

**Verification (real browser, chromium, temp diagnostic harness — since removed).**
- Delete → confirm opens; Accept fires the intercepted DELETE (harness route interception, no DB writes); Cancel/Esc abort with zero requests.
- Family Composition + Transactions accordions toggle `aria-expanded`/`hidden` on user clicks.
- `npm run build` OK; `php artisan test` (PHPUnit baseline) 365 passed / 1,847 assertions / 0 failures.
- Both remaining real-spec failures (`client-details-qr-card-phase24.spec.ts` ×2) are a **pre-existing, documented env limitation** — the local `smoke_superadmin` was retired (C3-C hygiene); the failed assertion is the sign-in step (`/login` redirect), unrelated to these changes (auth state untouched, no account created). Accordion + confirm-modal E2E remain green in the non-auth harness that exercises the real controller + real partial + real public JS/CSS.

**Cleanup.** All TEMP-DIAG scaffolding removed and verified gone: `diag/panel` + `diag/panel/{client}` routes and the `TEMP-DIAG-START/END` block in `routes/web.php` (file back to 279 lines, `php -l` clean), `resources/views/diag_panel.blade.php` deleted, plus `e2e/_diag-*.spec.ts` (4 files) deleted. No `public/js/components/DetailsPanel.js` drift: source-byte check re-verified public copy matches `resources/js/components/DetailsPanel.js`.

**STATUS: DETAILS PANEL DELETE-CONFIRM + ACCORDIONS — ROOT CAUSE VERIFIED, FIX COMPLETE (real-browser proven), logging corrected.
