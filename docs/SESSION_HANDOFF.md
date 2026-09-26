# 2DMIS v2 — Session Handoff

**CLIENTS ACTION FEEDBACK / UNIFIED NOTIFY RENDERER 2026-09-26 — LANDED:** Root cause of "no toast
feedback" was twofold. (A) The Phase-23 unified notify stack (`resources/views/partials/unified-
notify.blade.php`) had a COMPLETE store/API (`window.notify` → `Alpine.store('unifiedNotify').items`,
auto-dismiss timers, persistent warnings) but NO RENDERER — no markup ever iterated `items`, so NO
toast ever appeared anywhere (proven empirically in chromium: after `notify()`, `.toast` count stayed
0 on both the clients profile and the clients index). (B) The full-page client profile
(`clients/show.blade.php`) dropped every redirect success flash (Add Family Member, full-page GIP
save, Change Photo), and full-page Edit called `window.location.reload()` discarding its JSON
`data.message`. Fix — EXISTING system only, zero backend changes:
- `partials/unified-notify.blade.php` — added the missing `<template x-for>` renderer inside
  `#unifiedNotifyStack` (one reusable toast card; per-type teal/amber/red accent ring via new
  `iconFor()`/`ringFor()` helpers exposed on `unifiedNotifyComponent`; Close + optional `actions`
  renderers call the store's own `dismiss()`); container re-anchored to
  `left-[24px] right-[24px] ml-auto max-w-[420px]` so toasts stay in-viewport (24px margins) on
  ≤444px instead of clipping off the left edge (probe caught that bug). Toast card carries no
  `role="status"` — container `aria-live="polite"` handles announcements and the two legacy
  `assertDontSee('role="status"')` regression tests stay green.
- `resources/css/app.css` — ONE allowlist line `@source "../views/partials/unified-notify.blade.php";`
  (the partial's accent classes `bg-amber/[0.12]` / `text-amber` / `bg-red/[0.12]` had NEVER been
  compiled; `npm run build` now emits them). All edit/Add/GIP/photo/QR/header/responsive work
  untouched.
- `clients/show.blade.php` — `@push('scripts')` consumer pushes `session('success')` (via `Js::from`)
  and a `2dmis_client_flash` sessionStorage stash through `window.notify` on ready (retry loop until
  Alpine + body are there). Panel mode can never double-fire: `?panel=1` renders `_details` directly,
  so the consumer script only exists on the full-page render.
- `clients/_details.blade.php` — full-page Edit success stashes `{ message }` into
  `2dmis_client_flash` before `window.location.reload()` so the message survives the reload (string
  fallback matches the existing index `showToast` fallback).
- `tests/Feature/ClientProfileFlashToastTest.php` (NEW, 5 tests) — GIP flash consumed; photo flash
  consumed; photo error surfaces on profile; consumer + stash writer present on full profile and
  absent on the panel; Add Family Member 500 regression probe (see Disclosed).
Verified: `npm run build` OK (new CSS/JS hashes); `php artisan view:cache` OK; PHPUnit **370 /**
**1,875 / 0** (needs `C:\xampp\mysql\bin` on PATH); chromium temp probe 5/5 — toast really renders
with the message text, full-page Edit stash-flush shows the toast EXACTLY once then stays gone after
an explicit dismiss + reload, no toast is invented without a flash/stash, the same renderer works on
the clients index, and no horizontal overflow at 320/375/480/640/768/1024/1440. Temp spec, cookie
helper, seed script, test-results deleted; temp 8100 server stopped. Full detail:
`docs/IMPLEMENTATION_LOG.md` 2026-09-26 unified-notify-renderer entry.
DISCLOSED PRE-EXISTING REGRESSION (out of scope — backend, needs its own decision): **Add Family
Member 500s.** v2 `FamilyMemberService::link()` → `AuditService::log()` writes `ADD_FAMILY_MEMBER`
with `target_id = null` into `tbl_audit_logs.target_id` (`int(11) NOT NULL`, schema line 165) →
`SQLSTATE[23000] 1048` → the whole `DB::transaction` rolls back, so the link NEVER persists and no
flash/toast can ever exist for that flow. v1 `add_family_member.php` wrote no audit. Pending
backend decision: audit the new family-member row id, or match v1 and drop the audit call.

**CLIENTS TWO SURGICAL UI FIXES 2026-09-25 — LANDED:** (1) Export CSV dropdown overflow,
(2) Details Panel Family Composition "Add Family Member". Clients-only, presentation-only; no
backend/DB/schema/business/ACL/DataTables/JS/DetailsPanel changes; no app-wide CSS; two files.
FIX 1 — root cause: the Alpine-driven Export CSV menu (`clients/index.blade.php`)
`<ul class="dropdown-menu dropdown-menu-end" :class="{ 'dropdown-open': open }">` never carried
`data-bs-popper`, so the app.css (M6.3.5/§4.7 ported VERBATIM from ui.css) popper offsets —
`.dropdown-menu-end[data-bs-popper] { right:0; left:auto }` — never applied; with no insets the
absolutely-positioned menu anchored at the `.btn-group` left edge and opened 164px to the right,
past the viewport at EVERY width, causing `documentElement.scrollWidth > innerWidth` page overflow
(Chromium probe captured `right` ~38px past viewport at 320…1440). Fix: add the single
`data-bs-popper` attribute to that `<ul>` → menu drops below the button (`top:100%`) and
right-aligns, fully in-viewport with zero overflow everywhere. Label/icon/hrefs/`data-clients-
export` handlers/Alpine/route/permissions byte-unchanged. (Navbar user menu + Transactions export
share the same latent missing-attribute pattern — out of scope, untouched.) FIX 2 — root cause:
the existing full-page "+ Add Family Member" link
(`route('family-members.create', $client)` — pre-existing controller/store/form, ACL
`page:clients.php` + `action:clients.php,create`) was wrapped in `@if (! $isPanel)`, so panel mode
(`?panel=1`, `$isPanel=true`) omitted it with NO alternative entry point. Fix: drop the `@if`
gate so the exact same link renders inside the panel's Family Composition accordion → reuses the
existing create flow (same anchor → same route → same form/store/validation). No new
endpoint/modal/monitor. Verified: `php artisan view:cache` OK; `npm run build` NOT required (no
new Tailwind classes; `data-bs-popper` uses existing app.css rules); PHPUnit `365 / 1,847 / 0`
(needs `C:\xampp\mysql\bin` on PATH — known Gotcha); `git diff --check` exit 0. Real-browser
probes (chromium, seeded `jordi` session, temp spec removed) at
1440/1024/768/640/480/375/320: closed + OPEN menu both fully in viewport with `scrollWidth ==
innerWidth` everywhere; "Export Current Filter" still fires `/clients/export?municipality=…` and
"Export All Clients" `/clients/export?export_all=1`; panel accordion toggles, "+ Add Family
Member" visible with href `/family-members/{id}`, click loads the existing create form; full-page
profile regression clean (no overflow, h1 + `#clientQrImage` intact). Cleanup: spec + cookie
helper deleted, temp 8100 server stopped, `test-results` removed. Full detail:
`docs/IMPLEMENTATION_LOG.md` 2026-09-25 two-surgical-fixes entry.

**CLIENTS FULL-PAGE PROFILE VISUAL COMPOSITION 2026-09-25 — LANDED:** presentation-only polish of
the full-page client profile (`clients/_details.blade.php`, `@if (! $isPanel)` branch); Details
Panel output remains BYTE-IDENTICAL (every addition gated via `@class([...]) => ! $isPanel` — panel
mode renders the exact prior classes). (1) `data-panel-body` card now `@class(['data-card
p-[1.25rem]', 'mx-auto w-full max-w-[80rem] [&_.details-section]:mx-auto [&_.details-section]:w-full
[&_.details-section]:max-w-[60rem] [&_.accordion]:mx-auto [&_.accordion]:w-full
[&_.accordion]:max-w-[60rem]' => ! $isPanel])` — centered 1280px card; every shared section and
accordion capped at 960px/60rem and centered (arbitrary variants compile to descendant selectors,
so shared sections keep identical markup in both modes). (2) QR Identity Card:
`@class(['data-card p-[1.25rem]', 'md:w-fit md:max-w-full' => ! $isPanel])` — compact on desktop.
(3) Header action group: Add Transaction / Edit / Delete wrapped in one framed group
(`flex w-full flex-wrap items-center gap-[8px] rounded-control ring-1 ring-line-light px-[4px]
py-[3px] md:flex-nowrap md:px-[6px] md:inline-flex md:w-auto`); ACL checks/hrefs/`data-*`/form/CSRF/
order unchanged; each control keeps `grow md:grow-0`. (4) Identity block `md:items-center
md:gap-[24px]` (was `md:items-start md:gap-[16px]`); photo container `flex w-full flex-col
items-center md:w-auto md:shrink-0`. No routes/controllers/JS/DB/schema/ACL change, no inline
styles, no `!important`, no new dependency. Verified: `php artisan view:cache` OK, `npm run build`
regenerated `public/build/assets/app-BgKt1fa_.css` (95.50 kB; compiled `80rem`×5/`60rem`×4 and all
descendant selectors present), PHPUnit **365 / 1,847 / 0** (incl. panel byte-identity coverage),
`git diff --check` exit 0. Real-browser probes (chromium, temp seed-session + throwaway spec,
removed) on TESTCLIENT 0014 at 1440/1024/768/640/480/375/320: zero horizontal overflow everywhere;
identity + action group order Add<Edit<Delete + Change Photo present; card ≤1284px centered on the
content column (`#main-content` 260px sidebar offset — probe compares against the column, not the
viewport); 8 key sections visible; QR card <800px; Personal section ≤964px centered. Temp 8100
probe server stopped; `jordi.session_token` reset NULL. Full detail: `docs/IMPLEMENTATION_LOG.md`
2026-09-25 full-page visual composition entry.

**CLIENTS MODULE SUBTLE SCROLLBARS 2026-09-25 — LANDED:** presentation-only scrollbar polish for
the intentional Clients-module scroll containers. ONE reusable treatment in `resources/css/app.css`
(`scrollbar-width: thin` + `scrollbar-color: var(--color-ink-muted) var(--color-bg-alt)` = `#E8EBF0`
faint track / `#70798B` ink thumb; rounded 8px WebKit scrollbar via `::-webkit-scrollbar` /
`-track` / `-thumb` / `-thumb:hover`, hover `--color-ink-secondary`). Delivered two ways, scoped
strictly to Clients: (1) the new `.scrollbars-subtle` utility class on Clients-owned blades —
index table wrapper `clients/index.blade.php:365`, family + transactions table wrappers
`clients/_details.blade.php:256,289`, photo modal outer wrapper `:399`, GIP modal body
`clients/_gip.blade.php:122`; (2) CSS-only scoped selectors for shared/JS-managed containers —
`.details-panel[data-module="clients"] .details-body` (DetailsPanel.js already tags the shell with
`data-module="clients"`, so the rule holds only while a Clients panel is open), `#clients-screen
.filter-multi-scroll` (shared `partials/filter-chips.blade.php` used by many modules), and id
selectors `#clientFormModalBody` / `#clientFeedbackBody` (Clients-only IDs shared by the
`clients/index.blade.php` partials AND their JS-injected duplicates in `_details.blade.php`, so both
copies are covered without touching JS). NO JS, NO inline styles, NO `!important`, NO DOM changes,
NO new dependency, NO overflow/layout/dimension change. DELIBERATELY NOT styled: the full-page
profile scrolls at document level (no per-page overflow container; a body/html rule would be global,
which the task forbade) — global-search dropdown, sidebar nav, and all non-Clients modules keep
default scrollbars. Verified (Chromium probes at 1440/1024/768/640/375/320 on index + profile):
zero horizontal overflow everywhere, header order + back-link geometry unchanged (`back_above_title`
9px desktop / 6px mobile), `.scrollbars-subtle` table wrappers still horizontally scrollable on
narrow widths, `.filter-multi-scroll` computes `thin` + ink colors, details-body scope applies `thin`
only once `data-module="clients"` is set. Render-harness dumps verified every target (GIP modal uses
a real GIP transaction). Gates: `npm run build` regenerated `public/build/assets/app-CbyW57T0.css`
(94.79 kB; JS `app-DqsLDVL_.js` unchanged), `php artisan view:cache` OK, PHPUnit **365 / 1,847 / 0**,
`git diff --check` exit 0 (pre-existing CRLF warnings only). Throwaway harness + probes removed.
Full detail: `docs/IMPLEMENTATION_LOG.md` 2026-09-25 scrollbars entry.

**FULL-PAGE BACK LINK TEXT NAV 2026-09-25 — LANDED:** presentation-only polish of the full-page
Clients profile back navigation (`clients/_details.blade.php`, `@if (! $isPanel)` branch).
The bare chevron glyph that sat inside the `Client Profile` `<h1>` was replaced by a proper
text-based nav link sitting ABOVE the title: `<a href="{{ route('clients.index') }}" class="back-btn
mb-[6px] inline-flex items-center gap-[6px] text-dense font-medium">&#8249; Back to Client
Registry</a>` (`&#8249;` = `‹` U+2039). Reuses the project's existing `.back-btn` class (no
border/background, resting `--color-ink-muted`, hover/focus/active `--color-navy`) — no app.css
change, no inline styles, no JS. Details Panel `data-panel-actions` untouched. The responsive
flex container (title/identity/actions `order-*` classes from the two prior entries) was NOT
restructured — the link is a sibling block before it, so mobile `<768px` and desktop `≥768px`
ordering is byte-identical. Verified via Chromium probe at 1440/768/375/320: `‹ Back to Client
Registry` computed to `bg transparent / border 0 / text-decoration none / cursor pointer`, resting
`#70798B` → hover `#0038A8`, global gold focus-visible outline present, back above title
(`y=131 < h1`), mobile `identity→actions` order intact at 375/320 and desktop `h1+actions inline`
intact at 1440/768, zero horizontal overflow everywhere. Gates: `npm run build` regenerated
`public/build/assets/app-DWuSgcoG.css` (new `mb-[6px]`/`gap-[6px]` utilities absent from prior
bundle), `php artisan view:cache` OK, PHPUnit **365 / 1,847 / 0**, `git diff --check` exit 0
(pre-existing CRLF warnings only). Throwaway harness + probe photo removed. Full detail:
`docs/IMPLEMENTATION_LOG.md` 2026-09-25 back-link entry.

**CLIENTS PROFILE MOBILE HEADER ORDER 2026-09-25 — LANDED:** mobile-only (<768px) reorder of the
full-page Clients profile header (`clients/_details.blade.php`, `@if (! $isPanel)` branch).
Presentation only — backend/schema/ACL/routes/Details Panel/photo modal/other modules untouched.
Problem: on narrow screens the action buttons rendered immediately below "Client Profile" and
BEFORE the client identity, reading as an action toolbar rather than a profile. Root cause: the
title row `flex items-center` held h1 + actions while the identity block was a following sibling —
flex `order` can't interleave a sibling between container children. Fix (Tailwind utilities only,
no app.css / no `!important` / no inline styles / no JS, no duplicated markup): the title row
became the header's single flex-wrap container (`mb-[12px] flex flex-wrap gap-x-[12px]
gap-y-[16px] md:items-center`); identity moved inside, `w-full` with `order-2 md:order-3`; actions
`w-full` with `order-3 md:order-2 md:w-auto md:flex-1 md:justify-end`; the previously-landed button
classes re-keyed `sm:`→`md:` (`grow md:grow-0`, `md:inline`, `w-full md:w-auto`) so the header
flips at one 768px breakpoint. Result (Chromium rect probe, 1440/1024/768/640/480/375/320): mobile
order = h1 (y=129) → identity/photo/name y=172 → actions y=401; ≥768px = h1 + actions on one line
(y≈129-132, right-aligned) with identity below (y=179) — original desktop spacing/alignment intact.
Photo + Change Photo centered at every width; name/ID/category `max-md:text-center` (center mobile,
left desktop); zero horizontal overflow (`scrollWidth == viewport`); no button overlap. Gates:
`php artisan view:cache` OK (Blade compiles; div nesting verified), `npm run build` regenerated
`public/build/assets/app-D1UMXt_Y.css` (new `flex-wrap`/`gap-*`/`order-2`/`order-3`/`md:order-*`/
`md:w-auto`/`md:flex-1` utilities; old CSS removed by Vite), PHPUnit **365 / 1,847 / 0**,
`git diff --check` exit 0 (pre-existing CRLF warnings only). Throwaway
`tests/Feature/DumpClientProfileVerify.php` + probe photo removed. Files: `clients/_details.blade.php`
(header container + order classes only), rebuilt `public/build/assets/app-D1UMXt_Y.css`. Full
detail: `docs/IMPLEMENTATION_LOG.md` 2026-09-25 mobile-header-order entry.

**CLIENTS PROFILE RESPONSIVE ACTION BUTTONS 2026-09-25 — LANDED:** presentation-only polish of
the full-page Clients profile's page-actions row (`clients/_details.blade.php`, `@if (! $isPanel)`
branch). Root cause (Chromium rect probe at 1440/1024/768/640/480/375/320): the title row kept
`flex items-center` (h1 ≈138px + `flex-1` actions) at every width, so below 640px the actions area
shrank to ~104px@375 / ~98px@320 — buttons wrapped into a ragged right-aligned column and
"+ Add Transaction" word-wrapped into a ~98–104px×50px button (480: Add+Edit row 1 + lonely
right-aligned Delete row 2; 375/320: three misaligned lines). Fix (Tailwind utility classes only,
no app.css / no `!important` / no inline styles / no JS): title row → `flex flex-col gap-[12px]
sm:flex-row sm:items-center` (h1 stacks below 640px, freeing full width); actions → `flex w-full
flex-wrap items-center gap-[8px] sm:w-auto sm:flex-1 sm:justify-end`; buttons + Delete form →
`grow sm:grow-0`; Delete button → `w-full sm:w-auto`, form `inline` → `sm:inline`. Results: ≥640px
byte-identical one-line right-aligned row; 480 → Add+Edit+Delete on one full-width line; 375 →
Add+Edit row 1, Delete full-width (254) row 2; 320 → Add+Edit row 1, Delete full-width (199) row 2
— all h=34, zero overlap, `scrollWidth == viewport` everywhere, Delete keeps `btn-red`. Details
Panel `data-panel-actions` / `.details-actions-line` 2×2 grid untouched. Gates: `npm run build`
regenerated `public/build/assets/app-BQzchHmS.css` (new `grow`/`sm:grow-0`/`sm:inline`/`sm:w-auto`
utilities; old CSS removed by Vite), `php artisan view:cache` OK, PHPUnit **365 / 1,847 / 0`,
`git diff --check` exit 0 (pre-existing CRLF warnings only). Throwaway
`tests/Feature/DumpClientProfileVerify.php` + probe photo removed. Files: `clients/_details.blade.php`
(title/actions row only), rebuilt `public/build/assets/app-BQzchHmS.css`. Full detail:
`docs/IMPLEMENTATION_LOG.md` 2026-09-25 action-buttons entry. **NOTE:** this entry's `sm:`-based
button classes were later re-keyed to `md:` by the MOBILE HEADER ORDER entry above (single 768px
breakpoint) — the action-button layout behavior is otherwise preserved.

**CLIENTS PROFILE DESKTOP ALIGNMENT CORRECTION 2026-09-25 — LANDED:** follow-up to the
responsive-polish entry below. Desktop (≥768px) name/ID/category had been rendering CENTERED.
Root cause (probed computed styles at 1440px): the earlier `text-center md:text-left` pair was
shadowed by three legacy **Bootstrap helper rules with `!important`** in `resources/css/app.css`
(lines 2398/2505/2612 — `.text-center { text-align:center !important; }`) — `!important` beats the
plain `.md\:text-left` regardless of specificity/order, so text stayed centered at every width.
Fix: `min-w-0 text-center md:text-left` → `min-w-0 max-md:text-center` (a DISTINCT selector that
escapes the shim; centers only `<768px`, left default `≥768px`; no `!important`, no app.css
change). Verified by Chromium computed-style probe at 1440/1024/768/640/375/320: ≥768 → row
layout, name/ID/category all `text-align:start` sharing one left edge, photo centered in its
111px column (img center == button center), Change Photo centered; <768 → stacked column, text
centered, photo + button centered; `scrollWidth == viewport` everywhere. Gates: `npm run build`
regenerated `app-CSfJ8IE6.css` (required — new `max-md:` utility), `view:cache` OK, PHPUnit
**365 / 1,847 / 0**, `git diff --check` exit 0 (pre-existing CRLF warnings). Throwaway
`tests/Feature/DumpClientProfileVerify.php` + probe photo removed. Files: `clients/_details.blade.php`
(one class change), rebuilt `public/build/assets/app-CSfJ8IE6.css`. Full detail:
`docs/IMPLEMENTATION_LOG.md` 2026-09-25 desktop-alignment-correction entry. Lesson: avoid
`text-center` (or any bootstrap-shadowed utility) where a non-important Tailwind override is
needed; prefer `max-md:`/`md:` variants that produce distinct selectors.

**CLIENTS PROFILE RESPONSIVE + PHOTO CENTERING 2026-09-25 — LANDED (superseded by above):**
presentation-only polish
of the full-page Clients profile header (CSS/Blade only; Details Panel, backend, schema, business
rules untouched). Root cause: at ≤375px the page overflowed to 391px — the identity row's
`shrink-0` photo column was as wide as the `w-full` "Change Photo" button (~211px), exceeding the
~199px mobile content box, and the 96px photo sat left-aligned inside it. Fix (three Tailwind
class edits in `clients/_details.blade.php`'s `@if (! $isPanel)` identity block): identity row
stacks centered on mobile (`flex flex-col items-center gap-[12px] md:flex-row md:items-start
md:gap-[16px]`); photo column centers contents (`flex w-full flex-col items-center md:w-auto
md:shrink-0`); name/ID/category text centers under the photo on mobile — FINAL class is
`min-w-0 max-md:text-center` (the initial `text-center md:text-left` was corrected per the entry
above). Gates: `npm run build` regenerated `app-C8uo1nbK.css` (new `md:` utilities; old
CSS removed by Vite), Chromium probe at 1440/1024/768/640/375/320 shows `scrollWidth == viewport`
everywhere (zero overflow) with photo centered at ≤640 and centered-over-button at ≥768; tables
stay inside their `overflow-x-auto` wrappers (contained, by design). PHPUnit **365 / 1,847 / 0**,
`view:cache` OK, `git diff --check` clean (pre-existing CRLF warnings only). Throwaway render
harness `tests/Feature/DumpClientProfile.php` removed. Files: `clients/_details.blade.php`, rebuilt
`public/build/assets/app-C8uo1nbK.css`. Full detail: `docs/IMPLEMENTATION_LOG.md` 2026-09-25
responsive-polish entry.

**CLIENTS UX POLISH 12-POINT PASS 2026-09-25 — LANDED:** interaction/a11y-only pass over the
Clients module (no backend/schema/business rule; GipController untouched). (1) Add/Edit modal
discard guard — Escape/X/Cancel route through `requestClose()` (UI-confirm on dirty, programmatic
closes skip); (2) modal focus-first-field on open; (3) FilterChips focus return to the trigger
pill + ARIA on seg buttons; (4) GIP submit via fetch while `DetailsPanel.isOpen()`, success via
`notify` + panel reload preserving accordion state; (5) duplicate-review stash/restore of the
in-progress add form via a `details:closed` listener (values/style/dirty intact); (6) inline
`.field-error` per invalid field cleared on input; (7) category badge = single `.status-badge
.is-category`; (8) results count "X of N clients" on draw when filtered; (9) Audit Information
now a default-collapsed accordion; (10) footer photo modal surfaced via a Change Photo button
(`$canEdit`); (11) mobile seg-btn min-height 40px. Gates: `view:cache` clean (caught+fixed a
literal `@error` in a JS comment that compiled into an unclosed `if`), PHPUnit **365 / 1,847 /
0**, `npm run build` OK (copies FilterChips/DetailsPanel to `public/js/components`),
`git diff --check` clean (pre-existing `_details:100` trailing whitespace removed). Playwright
auth-gated specs still env-blocked (no `smoke_superadmin`). **Contract note:** Phase 8 spec
"modal does NOT close on ESC" is superseded by (1)/(2) — ESC now closes/(discard-confirms);
update that spec when the smoke user is seeded. Remaining findings (documented, not changed):
icon-only action buttons stay 32px in the 112px actions column; the `_details` full-page fallback
edit modal lacks the index's inline `.field-error` injection. Files: `clients/{index,_details,_gip}
.blade.php`, `partials/client-form-modal.blade.php`, `js/components/{FilterChips,DetailsPanel}.js`
(+ public copies), `css/app.css`, both docs. Full detail: `docs/IMPLEMENTATION_LOG.md` 2026-09-25
entry.

**C3-F UI CLEANUP 2026-09-15 — LANDED:** presentation-only correction of the C3-F
QR identity card in the Client Details Panel. The card is now a compact horizontal
layout: a fixed `120px x 120px` QR (`h-[120px] w-[120px] shrink-0`,
`width/height="120"`) beside static explanatory text `Client QR Code` /
`For easy scan access` (`flex flex-wrap items-center gap-[16px]`; wraps naturally on
narrow mobile widths, never overflows). Print button, Download link, and the
client-side print IIFE were REMOVED. The QR `src` is byte-identical to C3-F
(qrserver endpoint, `size=220x220`, `data={{ urlencode($client->qr_token) }}`,
`format=png`) — pixel payload contract untouched. `ClientService::displayFullName()`
still drives the panel header/profile (C3-B) via `data-panel-title`; the card text is
static copy only. Action grid (Add Transaction / Open Full Page / Edit / Delete),
panel/full-page modes, DetailsPanel.js, scanner/QR-producer/schema/DB all untouched.
Tests: `ClientDetailsPanelQrCardTest` updated to the new contract — **14 tests / 113
assertions** (QR card present, payload == token, 16-char base-62, not name/display/id,
120px CSS+attrs, QR-before-text horizontal order, explanatory copy, Print/Download
absent, C3-B name via panel header, GIRONELLA distinct, conventions intact); phase22 +
phase24 Playwright specs updated (5-bullet user-visible contract, ~120px boundingBox
100-130, client id from row `data-id`). Gates: full PHPUnit **365 / 1,847 / 0**, Pint
PASS, view:cache OK, npm build OK (120px utilities emitted), diff-check clean, Playwright
QR viewer 2/2 chromium. Auth-gated phase22/phase24 specs still env-blocked by absent
`smoke_superadmin` (log in fails to `/login`; pre-existing, not this change). Changed
files: `_details.blade.php`, `ClientDetailsPanelQrCardTest.php`,
`client-details-qr-card-phase24.spec.ts`, `client-display-name-phase22.spec.ts`, both
docs. Report:
`C:\Users\J\AppData\Local\Temp\opencode\c3f-ui-cleanup\C3F_UI_CLEANUP_REPORT.md`
**C3-F UI CLEANUP STOP — no C3-G or other milestone started.**

**C3-F 2026-09-15 — LANDED:** Details Panel QR identity card added additively to the
existing Client Details Panel — QR payload = the client's persistent `qr_token` only
(api.qrserver.com `220x220`, `encodeURIComponent`-safe base62); human display =
`Client::displayFullName()` (C3-B formatter) only. The card includes the QR image
(`#clientQrImage`), display name (`#clientQrName`), literal `Client ID: {{ $client->id }}`
(`#clientQrId`), a client-side Print button (`#clientQrPrintBtn` — opens a print window via
`window.open` / `doc.write` + `win.print()`; no server endpoint) and a Download link
(`#clientQrDownloadLink`, `download="{token}_qr.png"`). Inserted under the Personal
Information section inside `[data-panel-body]` in `clients/_details.blade.php`; the action
grid (Add Transaction / Open Full Page / Edit / Delete) is untouched and fully visible under
a super-admin user. The public QR viewer, all existing QR producers (viewer + both self-
service blades), `ScanService::resolveClient()` and its `full_name` legacy fallback, scanner
config, `qr_token` schema/migrations, DB row data, CEAP appointments, client CRUD, and
`GranteeSearchController` / `GranteeUpdateService` are ALL untouched. Tests: new
`ClientDetailsPanelQrCardTest` **15 / 106** (panel renders card; payload == token; 16-char
base-62; not full_name/displayFullName/id; C3-B formatter; extension `MARIA (JR) L`;
token unchanged after name edit; actions intact; card before actions; print present;
download present + filename correct; GIRONELLA pair distinguishable; responsive markup;
qrserver conventions); `phase22` stale C3-B assertion updated; new `phase24` spec written
(2 tests — auth-gated, env-blocked by absent `smoke_superadmin`). Gates: full PHPUnit
**366 / 1,840 / 0**, Pint PASS, view:cache OK, build OK, diff-check clean, Playwright QR
viewer 2/2 chromium. AUTH-GATED PLAYWRIGHT: both phase22 and phase24 specs fail at login
(`smoke_superadmin` absent on `main_system` since C3-C hygiene — pre-existing env state,
NOT a C3-F issue; spec authored correctly, would pass when the account is restored).
Changed files: `_details.blade.php`, `ClientDetailsPanelQrCardTest.php`,
`client-details-qr-card-phase24.spec.ts`, `client-display-name-phase22.spec.ts`,
`docs/IMPLEMENTATION_LOG.md`, `docs/SESSION_HANDOFF.md`. Report:
`C:\Users\J\AppData\Local\Temp\opencode\c3f-details-panel-qr-card\C3F_CLIENT_DETAILS_PANEL_QR_CARD_REPORT.md`
**C3-F STOP — end of C3 chain; no further milestones.**

**C3-E 2026-09-15 — LANDED:** QR payload switch — ALL three live QR generators now encode the
client's persistent `qr_token`, never `full_name`/a composed name. Producers migrated:
`qr/viewer` (payload `data.client.qr_token`, via `GranteeSearchController::verify` —
`CLIENT_COLUMNS` adds `qr_token`), and both `grantee_update/self-service` +
`grantee_update/_self_update_tab` (payload `data.qr_token`, via `GranteeUpdateController::store`
→ `GranteeUpdateService::update`, whose success payload now returns the token — the token is
never composed client-side). Human-facing displays (`#qrName`, the name label under the QR,
download filenames) still use `full_name`; QR provider/dimensions (`api.qrserver.com`, `220x220`)
unchanged. The `ScanService::resolveClient()` C3-D contract is untouched: `qr_token` resolves
FIRST, legacy `full_name` remains the FALLBACK — legacy printed QRs remain scannable, legacy
fallback NOT removed (C3-F NOT started). `qr_token` is immutable/long-lived/reusable: name edits
via the grantee self-service do NOT regenerate it (name-invariance explicitly tested). No schema
change (dump unchanged since C3-C), no production/`main_system` writes. Tests: `QrViewerTest`
7/23 + `GranteeUpdateTest` 15/51 (new: store returns token, base-62 length-16, token unchanged
after name edit, both self-service views wire the token payload; viewer wires
`data.client.qr_token`, never `encodeURIComponent(fullName)`); ScannerTest 18/125 +
ScannerTokenResolutionTest 17/51 untouched/green. Gates: full PHPUnit **351/1,734/0**, Pint PASS,
view:cache OK, `npm run build` OK, diff-check clean. Playwright `--project=chromium -g
"QR viewer"` **2 passed** (C3-E test decodes the rendered QR `data`, `/^[0-9A-Za-z]{16}$/`, and
equals the server `client.qr_token`; municipality resolved from the public search API).
Self-service/self-update E2E browser submit NOT executed (would write to `main_system`) — payload
wiring proven deterministically at the PHPUnit render level. Auth-gated phase23 spec tests need
`smoke_superadmin`, absent on local `main_system` since C3-C hygiene (pre-existing, not C3-E).
Changed files: GranteeSearchController, QrController, GranteeUpdateService, 3 blades, the 2 test
files, + `e2e/client-qr-token-phase23.spec.ts` (the `.d-none→.hidden`/label/`ui.css` hunks in
those blades pre-date C3-E). Report:
`C:\Users\J\AppData\Local\Temp\opencode\c3e-qr-payload-switch\C3E_QR_PAYLOAD_SWITCH_REPORT.md`

**C3-D 2026-09-15 — LANDED:** token-first client identity resolution inside the P4
scanner engine, backward-compatible — scanners now resolve BOTH new `qr_token`
values AND existing legacy `full_name` QR payloads; QR generation is still
UNCHANGED. `ScanService::resolveClient($scanned)` added (public, the only
client-identity boundary): 1) exact `qr_token` equality lookup
(`utf8mb4_bin`, through the `tbl_clients_qr_token_unique` UNIQUE index — EXPLAIN
`type=const`, `key_len=64`, `rows=1`; nonexistent tokens fold to "Impossible
WHERE", no scanning), 2) then the unchanged legacy `TRIM(full_name) COLLATE
utf8mb4_general_ci` resolution (`findClientByName` stays as the private legacy
fallback). All four client-identity strategies rewired: `lookupClient`
(client), `lookupClientGeo` (client_geo), `lookupExistingProgram`
(existing_program), `lookupExamDerived` (exam_derived — legacy scans keep the
scanned value as the name-keyed exam/result key byte-identical; token scans
reach the unchanged exam/result linkage through the client's persisted
`full_name`). Specialized strategies stay specialized and pass NO token
resolution: `transaction` (cedssg_update), `transaction_partial`
(payout_unpaid), `seat_join` (payout — dispatched directly in `lookup()`). No
scanner semantics, no `displayFullName()`/`full_name`/`match_name`/
`patient_name`/seat/exam/result change, no Details Panel QR card, no QR
generator change (`qr/viewer`, `grantee_update/self-service`,
`_self_update_tab`, api.qrserver.com untouched), no schema change (schema
dump unchanged), no production touch. **Docs status is explicit: qr_token
resolves FIRST; legacy full_name remains the FALLBACK; specialized strategies
are unchanged; existing legacy QRs remain valid; QR payload generation has NOT
switched to qr_token yet; the Details Panel QR card is NOT implemented yet;
C3-E owns switching QR payload generation to `qr_token`.** New tests
`tests/Feature/ScannerTokenResolutionTest.php` **17 tests / 51 assertions**:
token→exact client, unknown-token→legacy fallback, name-shaped-token-full_name
still resolves, legacy name resolves, exact (no prefix/suffix), case-sensitive
(case-mutated token fails), single-client, name edits don't affect token
resolution, extension client resolves by token independent of the C3-B
display formatter, GIRONELLA duplicate-name pair resolves independently by
token, distinct tokens→distinct clients, client_geo/toda token OK, existing_program/
ongoing_scholars token OK, transaction strategy REJECTS tokens (patient_name
only), seat strategy REJECTS tokens (seat name only), exam strategy legacy
unchanged + token resolves, output shape unchanged, EXPLAIN proves the UNIQUE
index lookup. Gates: full PHPUnit suite **342/1,708/0** (16 of the 17 new
tests were authored to reflect the final design before landing; ScannerTest
18/125 legacy regression intact), Pint `--test` PASS, view:cache OK,
`npm run build` OK, `git diff --check` clean. Changed files: `app/Services/
ScanService.php` + `tests/Feature/ScannerTokenResolutionTest.php` ONLY (working
tree otherwise holds pre-existing milestone/dirty files). Report:
`C:\Users\J\AppData\Local\Temp\opencode\c3d-token-resolution\`
**C3-D STOP — C3-E (QR payload switch) intentionally NOT started.**

**C3-C 2026-09-15 — LANDED:** persistent client QR identity token (`qr_token`) added
additively to `tbl_clients` — `char(16)` base62 `[0-9A-Za-z]` force-`utf8mb4_bin` (case-sensitivity
required by the unique index), CSPRNG `Str::random(16)`, opaque (no name/id/date/program
encoded), unique index `tbl_clients_qr_token_unique`, NOT NULL. Migration is staged
(nullable → backfill all 1,002 existing rows via `Client::ensureQrTokens()` → unique index →
NOT NULL), idempotent-guarded. `Client::booted()` `creating` hook auto-assigns; `qr_token`
NOT in `$fillable` → immutable to mass-assignment and the single ClientService write path.
**Forensics:** post-migration 1,002/1,002 valid tokens, NULL=0, blank=0, dup groups=0, distinct=1,002,
char(16) utf8mb4_bin + UNIQUE verified; pre-backup restored to scratch and per-row signature
(30 col, qr_token excluded) SHA256-identical, all table counts identical except `migrations`
pre=12→post=13; scratch DB dropped. Byte-identical: **yes**. Gates: PHPUnit **325/1,657/0**,
Pint PASS, build OK (bundle unchanged), view:cache OK, diff --check clean; schema dump
regenerated with sentinel stripped (adds exactly qr_token col + unique key + migration row).
Playwright `client-qr-token-phase23.spec.ts` **3/3 chromium** (list/panel C3-B display-no-QR,
public QR viewer muni+comma-form suggestion contract, scanners hub + CEAP engine unchanged).
QR **payload** generation is deferred to C3-E by design; scanner **token
resolution** landed additively in C3-D (see top block). C3-C itself adds no QR
UI, no scanner change, no name-formatter change. DB restored byte-identical (smoke_superadmin
id 17 + perm row + audit>1779 deleted; jordi last_activity reverted; value-row dump diff 0;
clients 1,002; audit max 1779; permissions 11). Report:
`C:\Users\J\AppData\Local\Temp\opencode\c3c-qr-token\C3C_QR_TOKEN_REPORT.md`

**C3-B 2026-09-15 — LANDED:** canonical display-name formatter (`LAST, FIRST (EXT) MIDDLE`)
wired into every client display surface including search/autocomplete (additive
`display_name` transport; JS prefers `display_name ||` old composition — no JS formatter
duplication). ClientService::deriveDisplayName + Client::displayFullName; data()/store()/
search payloads; 7 view swaps; 5 search feeds enriched. 18 files (17 mod + new unit test,
13 tests). Scanner/QR/schema/stored `full_name`/scholar_info/head-of-household-picker/
unpaid-self-service/update-logs untouched. Gates: PHPUnit **314/1,433/0** (main_system_test;
main_system untouched), Pint `--test --dirty` PASS, `npm run build` OK, view:cache OK,
diff --check clean. Playwright `client-display-name-phase22.spec.ts` **24/24** (3 tests × 8
projects run per-project; a concurrent all-projects run logged in 24× and hit the auth
throttle/lockout — environmental, chromium reruns 3/3). Pre-existing stale specs flagged
(clients.spec:14 `#clientFormModalTitle`, :40 `data-filter-done` — C2 refactor removed those
elements; spec last edited at abe331a; NOT caused by C3-B, out of scope). DB restored
byte-identical (smoke_superadmin id 16 + permissions + audit>1779 deleted; jordi
`last_activity` bookkeeping timestamp reverted; dump INSERT diff 0 lines; clients 1,002;
audit max 1779; permissions 11). Report:
`C:\Users\J\AppData\Local\Temp\opencode\c3b-display-name\C3B_DISPLAY_NAME_REPORT.md`

**C2 2026-09-15 — DONE (STOP, no code):** client QR full-name payload cycle
completed as STOP + report only. Proven in forensics: no "details-panel QR card"
exists in v2 (QR surfaces = `qr/viewer.blade.php:201` [persisted `full_name`,
Decision C], `grantee_update/self-service.blade.php:420`, `_self_update_tab.blade.php:401`
[composed, no extension]); all 14 scanners resolve by exact equality to persisted
`tbl_clients.full_name` (`LAST, FIRST MIDDLE EXT`); required new order
(`LAST, FIRST EXT MIDDLE`) mismatches stored keys for 77/1,002 clients
(extension+middle) and would break every lookup. STOP conditions honored; zero
files changed; DB untouched; gates green (301/1419, Pint --dirty, view:cache,
diff-check clean). Report:
`C:\Users\J\AppData\Local\Temp\opencode\c2-client-qr-name\C2_CLIENT_QR_FULL_NAME_REPORT.md`.
Safe owner paths documented (keep Decision C / additive unique-QR-token / separate
scanner-recomposition milestone). Do NOT re-attempt the format change without a
scanner strategy approval.**

**Last Updated:** 2026-09-15 (C3-D LANDED — see top block above; C3-C/C3-B/C2/M6 history unchanged below). M-history continues: 2026-09-10 (ui.css retirement **M6.4 — final dependency re-audit COMPLETE, verdict NOT READY**: audit-only Chromium deletion-sim harness `m64-audit.mjs` [FULL vs `ui.css` aborted] × 6 public pages × 375/576/768/1280 + §5/§2/§3/§4.10 dashboard fixture on `/login` (59 probes, sRGB-normalized colors) — **proven live drift on `.data-card` for all 7 reachable heads at every viewport** (border 1px `#E2E5EA` → 0, shadow-set change, −2px height) plus dashboard §5 drift (`.status-badge` dot 6→5.25px ×13 variants + badge −0.75px, `.metric-card` border 1→0 / radius 12→16px / padding 20→17.5px / shadow+ring / hover-lift lost, `.data-card-header/-body/-footer` padding & gap 16/20/12→14/17.5/10.5, `.ui-empty` padding [dead], `.ui-skip-link` focus `left` 0→14px); **value-identical** `.ui-notice`/`.ui-micro-label`/`.metric-value`/Reboot/type/`:focus-visible`/reduced-motion/real `.page-link` (scoped app.css twin + datatables.css cover it); 24/24 status 200, 0 page errors, 0 FULL console errors, 0 non-`ui.css` failed requests, `window.bootstrap` undefined all 48 captures, 0 overflow; build green, view:cache OK, Pint pass, **PHPUnit 301/1419/0**; **ui.css retained + linked; deletion gate REMAINS BLOCKED; actual deletion NOT STARTED.**) **→ M6.5 LANDED 2026-09-10 — first block CLEARED: `.data-card` base ownership migrated ui.css → app.css** (only that one 4-line rule; end-of-file VERBATIM port appended to app.css so it sits after the Batch C Tailwind twin — same-specificity ties resolve exactly as late-loaded ui.css did; tokens `--ui-card`/`--ui-border`/`--ui-radius-lg`/`--ui-shadow-sm` already canonical in the app.css `:root` from M6.3.2, zero residual ui.css token dependency; ui.css block replaced with pointer comment, slots `.data-card-header/-body/-footer` remain ui.css-owned; `ui.css` retained + linked, 8 `<link>`s untouched, `datatables.css` byte-identical MD5 `2ee627b7…c46`); build green `app-CrhEbRXG.css` 80.21 kB (JS `app-DqsLDVL_.js` unchanged), compiled tail = verbatim rule as the LAST `.data-card`; **deletion-sim re-run (24/24 cells, status 200 ALL): live public pages R0/O0/F0 at every viewport; `/login` fixture real R28→20 / owned O33→32 / fixture F28 == baseline (same blocker set minus .data-card); `dc-plain` reported style now FULL==SIM on every own declaration (M6.4 border 1px/0 · solid/none · `#E2E5EA`/`#212529` · shadow-ring drift all gone), residual `rect.height 202.172→190.172px` is content-driven (badge/metric children still shrink in SIM — unchanged blockers), FULL geometry byte-identical to M6.4 baseline (202.172px) = zero real-user regression**; remaining blockers documented (`.status-badge` dot, `.metric-card` border/radius/pad+hover, slots, `.ui-skip-link` focus `left`, `.ui-empty`); env note: local MariaDB wedged mid-run (InnoDB "LSN in the future" warnings — **no data modified**, AGENTS bytes-identical rule held), MySQL auto-respawned healthy, web dev server restarted on 127.0.0.1:8000; Pint pass, view:cache OK, **PHPUnit 301/1419/0**, `git diff --check` clean. **→ M6.6 stack REMAINS: `.data-card-header/-body/-footer`, `.metric-card`, `.status-badge`, `.ui-skip-link`, `.ui-empty` — NOT STARTED; ui.css retained + linked; actual deletion NOT STARTED.** Then M6.3.10 history — modal family **VERIFIED ZERO-OWNED**: exhaustive inventory → 0 modal-family CSS rules anywhere (`ui.css`/`app.css`/compiled — every `.modal`/`.fade`/`.show`/`backdrop` match is comment text); all live modals are Alpine + Tailwind — `pointer-events-none fixed inset-0 z-[200]`/`z-[210]` overlay + static `bg-ink/40` backdrop + centered dialog, `x-show` + `x-transition.opacity.duration.200ms`, store bridges `uiConfirm`/`uiViewModal`/`clientFormModal`/`clientFeedbackModal`/`window.uiConfirm`/`window.uiViewModal`/`window.showClientFeedback` (confirm, record-view, client-form, client-feedback, photo-upload, scanners, gip, self-service, admin/user, audit_logs, scholars, details-panel); `modal639.mjs` deletion-sim (`ui.css` aborted) parity @375/576/768/1280 **drift 0** (computed styles/bbox/centering-offsets/scroll-containment, open+closed); `modal-interact.mjs` real partial-bridge interactions **27/27 @375 AND @1280** (open/visible/title-body wiring/scroll-lock+release on every close path/Escape→false/backdrop→false/Cancel→false/Confirm→true/tab-trap wrap/reopen idempotence/85vh-90vh-60vh internal scroll with no page overflow/focus-in-dialog/onHidden+focus-restore/z-200-vs-210); ONLY application change = `ui.css` §4.x index **comment** corrected (stale "and the modal family" still-owned claim → verified-0 statement; 483→493 lines, MD5 `5D6C2B20…`→`6EB8DA64…`); build byte-identical (`app-PXXoPz1A.css` 80.08 kB / `app-DqsLDVL_.js`, 0 modal selectors compiled, `z-[200]`/`z-[210]`/`bg-ink/40` confirmed), view:cache OK, Pint pass, `git diff --check` clean (CRLF-doc warnings only), **PHPUnit 301/1419/0**; **ui.css retained + linked; deletion gate still BLOCKED; final dependency re-audit NOT STARTED.** Then M6.3.9 history: **M6.3.9 — remaining utility families migrated**: all §4.9 utilities ported verbatim to app.css + 60 proven-DEAD rules removed; 0 utility selectors left in ui.css; M6.3.8 grid history: ui.css grid block `.row`/`.row > *`/`.g-0…g-5` gutter-token overrides/`@media 576` `.col-sm-4/8`/`@media 768` `.col-md-3/4/6` — all 14 selectors incl. both breakpoints, 37 lines/1288 chars, ported verbatim to end-of-file app.css (canonical owner — the final grid block; app.css had zero grid selectors before; `--ui-gutter-x/y` tokens declared ONLY inside the block so token ownership moved with it; `.gap-3/4/5` spacing rules NOT moved → M6.3.9); consumer scope — static `.row` on grantee_update (self-update tab + self-service), unpaid_verifications (self-service + index), payouts/attendance, qr/viewer: exactly 4 `row g-2` + 6 plain `row`, `g-1/g-2` on non-row elements inert, columns `col-md-3`×24/`col-md-4`×18/`col-md-6`×8/`col-sm-4`×12/`col-sm-8`×12; 2 JS templates emit `dl.row` + `dt.col-sm-4`/`dd.col-sm-8` (payouts/attendance:277, unpaid_verifications/index:259); no nested grids, no header grid, `dl.row` semantics live; grid-parity fixture @375/576/768/1280 pre→post AND deletion-sim (`ui.css` aborted) drift 0 at every viewport, 25/25 locked assertions equal the 14px-root literals (row margins -10.5px default / -3.5px `g-2`, child padding 10.5px/3.5px, flex `0 0 auto`, width ratios 0.25/0.333/0.5, dl 33.3%/66.7%, nested inner 0.25, gutter tokens, full-width stack @375); `rg` → 0 real grid selectors left in ui.css; datatables.css untouched (full MD5 2EE627B74B0FD170727506AFD46C6C46, B2 seam l.522-525 intact); build `app-B2ZahIOJ.css` 78.37 kB, JS unchanged; prior-phase parity re-verified (buttons 25/25, alerts 29/29, dropdown 53/53, list-group 295/295, table 246/246, accordion/forms 0 drift); git diff --check clean, view:cache OK, Pint passed, PHPUnit 301/1419/0; grid blocker row CLEARED — remaining blocker families: spacing-3–5/.rounded drift, text/display/position utilities, modal base; deletion still BLOCKED by the remaining families). **M6.3.9 (2026-09-09) — remaining utility families migrated**: ui.css §4.9 — ALL utility families (spacing, radius, text align/color, display, position, border, background, typography helpers) migrated to end-of-file app.css in two classes: **63 ported VERBATIM** with Bootstrap `!important` intact (d-inline/d-flex, justify-content-center/-between, align-items-center/-end, position-relative/-absolute, text-start/text-center, text-primary/secondary/success/danger/warning/info/muted/white, fs-4, small, min-vh-100, w-auto, img-fluid/img-thumbnail, bg-white/bg-transparent, border/border-2, rounded/rounded-pill, m-0/mt-0…5/mb-0…4/ms-1/2, p-1…4/pt-3/pb-1/3/px-0/1/3/4/5/py-1…5, gap-3/4) — even the value-exact Tailwind twins (text-center/text-white/bg-white/bg-transparent/m-0/mb-0…4/mt-1/2/ms-1/2/p-1…4/px-0/1/3/4/5/py-2…5/w-auto/rounded-pill) because they carry `!important` (retiring them would require dropping the flag + proving no competing unlayered declaration; byte-verbatim port guarantees parity by construction); **60 REMOVED as proven DEAD** (0 Blade + 0 JS + 0 compiled-twin: d-block/d-inline-flex/flex-nowrap/justify-content-start/-end/-around/align-items-start/me-auto/position-static/-fixed/-sticky/text-end/text-body/fw-light/-normal/-semibold/-bold/fst-italic/overflow-auto/border-1/-3/rounded-1/-2/-3/-circle/gap-0/-5/me-0/-1/-2/ms-0/-3/mx-0…3/my-0…3/mt-auto/mb-auto/mb-5/p-0/-5/pt-0/-1/-2/-4/-5/pb-0/-2/-4/-5/ps-0…3/px-2/py-0); no RETIRE-to-Tailwind; ported rules unlayered `!important` beat the `@layer utilities` twins exactly as ui.css-last did; consumers live-counted (39 classes / 1,148 Blade + 115 JS refs), JS classList contract unchanged; ui.css 662→483 lines, app.css 2488→2595; build app-PXXoPz1A.css 80.08 kB (JS unchanged); utils-parity @375/576/768/1280 CURRENT vs deletion-sim drift 0 (utilities + grid slot re-probe) AND grid slot vs M6.3.8 baseline drift 0 AND sim chain post638→now 0, docOverflow 0, only the known aborted-stylesheet console artifact in sim; rg → 0 real utility selectors in ui.css; datatables.css untouched (full MD5 2EE627B74B0FD170727506AFD46C6C46, B2 seam intact); prior-phase parity re-verified (buttons 25/25, alerts 29/29, dropdown 53/53, list-group 295/295, table 246/246, accordion/forms 0 drift); git diff --check clean, view:cache OK, Pint passed, PHPUnit 301/1419/0 — utilities blocker row CLEARED — remaining blocker families: modal base (M6.3.10) + the §5 shared-component classes / page-link / Reboot element rules (stay ui.css-owned); deletion still BLOCKED by the remaining families). → **M6.6 LANDED 2026-09-10 - `.metric-card` rule set ownership migrated ui.css -> app.css** (7 selectors - base + `:hover` + `::before` + `.accent-gold/-teal/-red` - end-of-file VERBATIM port so it beats the Batch C Tailwind twin exactly as late-loaded ui.css did; border 1px `--ui-border`, `--ui-radius-lg` 12px, `--ui-space-5` 20px padding, `--ui-shadow-sm`, hover translateY(-2px)+`--ui-shadow-md`+`--ui-card-hover`, 0.2s/0.2s, 3px `::before` `--ui-accent` (navy default) - all 13 tokens canonical in app.css `:root` from M6.3.2; ui.css sub-block replaced with pointer comment, `.metric-value` child stays ui.css-owned; `ui.css` retained + linked, 8 `<link>`s untouched, `datatables.css` byte-identical MD5 `2ee627b7...c46`); FULL snapshot byte-identical pre-port -> post-port -> post-removal (radius 12px, pad 20px, border 1px, shadow-sm, height 73.078px, ::before 3px + gold/teal/red/navy accents; hover matrix(1,0,0,1,0,-2)+shadow-md+#FAFBFC+0.2s,0.2s); deletion-sim re-run 24/24 status 200 -> login fixture R20->R14 / O32->O24 / F28->F23, metric-card & metric-value GONE from owned/fixture drift (mv old drift was parent-geometry propagation only: rect.left 21px vs 17.5px, its own style never differed), `.metric-card` ZERO drift @375/576/768/1280, hover-lift PRESERVED (mcHover FULL==SIM), reduced-motion transform identical, 0 overflow, 0 FULL console errors, `window.bootstrap` undefined 48/48; build green `app-C29NMCAt.css` 80.78 kB (JS `app-DqsLDVL_.js` unchanged), compiled tail check (ported `.metric-card` family LAST, after the M6.5 `.data-card` block); view:cache OK, Pint passed, PHPUnit 301/1419/0, git diff --check clean, /login 200; remaining blocker families: `.data-card-header/-body/-footer`, `.status-badge` (13 variants), `.ui-skip-link`, `.ui-empty`, `.ui-notice`/`.ui-micro-label`/Reboot/type/`.page-link` (value-identical) - stay ui.css-owned; deletion still BLOCKED by the remaining families). → **M6.7 LANDED 2026-09-10 - `.status-badge` rule set ownership migrated ui.css -> app.css** (13 rules - base + `::before` currentColor dot + eight grouped is-* variant blocks covering all eleven is-* classes - end-of-file VERBATIM port so every tie with the Batch C Tailwind twin (l~698) resolves exactly as late-loaded ui.css did; pill 999px, `.72rem`/600, .02em, 1.3, 6x6/50% dot; tokens --ui-radius-pill/--ui-text-xs/--ui-text-secondary/--ui-red canonical in app.css :root (M6.3.2), each byte-equal to its @theme twin; ui.css sub-block replaced with pointer comment; `ui.css` retained + linked, 8 <link>s untouched, `datatables.css` byte-identical MD5 `2ee627b7...c46`); 15 blade files / 27 badge sites (is-neutral 17, is-paid 11, is-pending 10, is-approved 4, is-active 3, is-info 2, is-success 2; 4 variants unused but kept), zero JS/dynamic consumers, no media/hover/focus/reduced-motion ties, no per-instance tokens; FULL snapshots (`snap-sb.cjs`, 12 probes) byte-identical pre-patch -> pre-port -> post-removal (dot 6x6/50%, rects 47.656-71.547x18.719, AA colors byte-equal, 0 console/page errors); deletion-sim re-run (24/24, ui.css aborted) -> login fixture F23->F11 / O24->O12 / R14->R2 @375/576/768/1280, ALL twelve sb-* probes vanish from fixtureDrift and no status-badge key remains in ownedDrift (6px dot/50% radius/badge rects identical in SIM vs 5.25px/-0.75px before), residual realDrift = 2 fixture-container rect.height content-propagation keys only, `.metric-card` hover-lift + reduced-motion still FULL==SIM (M6.6 regression-free); build green `app-DZ6PGwAv.css` 81.60 kB (JS `app-DqsLDVL_.js` unchanged), compiled tail (ported `.status-badge` family LAST, after M6.5 `.data-card` + M6.6 `.metric-card`); app.css 2714 lines MD5 `04947812...f098`, ui.css 427 lines MD5 `b024be40...f01`; view:cache OK, Pint `--test --dirty` passed, PHPUnit 301/1419/0, git diff --check clean, /login 200, Bootstrap CDN 0; **remaining blockers by inventory: MIGRATE** `.data-card-header/-body/-footer` (padding/gap 16/20/12 -> 14/17.5/10.5; header 3/body 37/footer 0 sites), `.ui-skip-link:focus` (left 0 -> 14px, global a11y), `.ui-notice`, `.ui-micro-label`, `.metric-value` (value-identical); **DEAD (deletion-gate removals, NOT migrations)** `.ui-empty` (0 sites), `.ui-page-header/-title/-subtitle/-actions` (0 sites), ui.css §2 body/h1-h6 font hooks (inert); **RETAIN/FOUNDATION** `:focus-visible` + reduced-motion + §4.10 Reboot/type (byte-identical @layer base twins) + .page-link/.page-item navy (value-identical, app.css scoped mirror + datatables.css cover); deletion still BLOCKED until the remaining MIGRATE families pass their gates).** → **M6.8 LANDED 2026-09-10 — `.data-card` slot family (`.data-card-header/-body/-footer`) ownership migrated ui.css → app.css** (3 selectors, end-of-file VERBATIM port after the M6.7 block so the same-specificity (0,1,0) ties with the Batch C Tailwind twins (~l.738-748) resolve exactly as late-loaded ui.css did — real header padding 16/20/16/20 + 12px gap + 1px solid `#EEF0F4` bottom divider, 20px body padding, footer 12/20/12/20 + 12px gap + top divider + `#FFFFFF` surface — all five consumed tokens (`--ui-space-3/-4/-5`, `--ui-border-light`, `--ui-card`) canonical in the app.css `:root` M6.3.2 and byte-equal to the @theme twins (`--color-line-light`, `--color-surface`); the M6.5 `.data-card` base NOT modified; ui.css slot block replaced by a pointer comment, `ui.css` retained + linked, 8 `<link>`s untouched, `datatables.css` byte-identical MD5 `2ee627b7…c46`); consumers static Blade only (header 3 sites dashboard, body 37 across 26 files, footer 0 kept for family completeness), ZERO JS/classList/dynamic refs (`DetailsPanel.js:161` names only the `.data-card` container); cascade-proof `snap-dcslots.cjs` A==B==C byte-identical (header/body/footer full computed props + rects, 0 console/page errors); deletion-sim `m64-audit.mjs` re-run → login fixture drift F11→F6 / O12→O7 / R2 at all 4 viewports with **dc-plain/dc-header/dc-body/dc-footer ALL NO DRIFT** (the M6.4 padding/gap 16/20/12→14/17.5/10.5 drifts gone AND the M6.5-era dc-plain `rect.height` residual 202.172 vs 190.172 cleared — proven slot-padding content propagation, not parent-owned geometry), residual drift confined to still-owning families (`skip`, `uml`, `uempty`, `page-link/-plain`, Reboot/type); 24/24 status 200, 0 overflow, 0 FULL console errors (SIM keeps only its 1 aborted-stylesheet artifact), `window.bootstrap` undefined all parts, mcHover + reduced-motion FULL==SIM (M6.7 regression-free); build green `app-ClwMBsAM.css` 82.05 kB (JS `app-DqsLDVL_.js` unchanged) with the slot port LAST in the bundle and the M6.5 base body byte-verbatim; `view:cache` OK, Pint passed, PHPUnit 301/1419/0, `git diff --check` clean, /login 200, Bootstrap CDN 0, rg → 0 slot selectors in ui.css; app.css `6e238c56…d0d`/2751, ui.css `f93d9340…5a`/406; remaining MIGRATE blockers `.ui-skip-link:focus` (left 0→14px), `.ui-notice`, `.ui-micro-label`, `.metric-value` + DEAD (`.ui-empty`, `.ui-page-*`) + RETAIN/FOUNDATION — deletion still BLOCKED until those pass their gates). → **M6.9 LANDED 2026-09-11 — `.ui-skip-link` family (base + `:focus`) ownership migrated ui.css → app.css** (2 rules, end-of-file VERBATIM port of BOTH the base [absolute left -999px / top 0 / z 2000 / 10px 16px / gold #FCD116 / navy #0038A8 / 700 / .85rem / radius 0 0 8px 0 / no-underline] and the live .ui-skip-link:focus { left: 0 }; all four tokens — `--ui-gold`/`--ui-navy`/`--ui-text-sm`/`--ui-radius` — already canonical in the app.css `:root` (M6.3.2), zero new tokens; ui.css block replaced with pointer comment, `ui.css` retained + linked, 8 `<link>`s untouched, `datatables.css` byte-identical MD5 `2ee627b7…c46`). **Scope-conditional exercised:** the first deletion-sim capture proved the BASE rule is a LIVE blocker of the focus contract — its `text-decoration:none` rides into the VISIBLE focused state (FULL none vs SIM underline; the Batch B sr-only/focus-* twin never re-supplies text-decoration) and its left/z define the hidden-state unit — so it migrated with `:focus` per the charter; consumers single static anchor layouts/app (auth shell only, /login standalone), zero JS refs; cascade-proof `snap-skip.cjs` A==B==C byte-identical @375/576/768/1280 (unfocused left -999/rect 32x20, prog + Tab keytab focus left 0 fv=true rect 138.234x36.547 fixed top 10.5 white ink 50 pad 8.75/14 radius 8 font 11.9/600, loss cycles restore -999, 5-focusable global Tab chain FULL==SIM [skip gold 3px offset 2; input.form-control outline none pre-existing; .btn-navy gold outlined], 0 console/page errors, 0 overflow); deletion-sim `m64-audit.mjs` re-run — interactions `skipFocused` now **FULL==SIM byte-identical left 0px / rectLeft 0.000** (M6.8 sim was 14px/14.000); login fixture ownedCount 7→6 / fixtureCount 6→5 at all viewports with the ENTIRE `.ui-skip-link` ownedDrift row (22 props) + `skip` fixtureDrift row GONE — 0 meaningful skip drift; every other report field byte-identical to M6.8 baseline (24/24 status 200, 0 overflow, 0 page errors, consoleErrorsF 0 / S 1 aborted artifact, bootstrap undefined, reduced-motion FULL==SIM); build green `app-BP5CUd8L.css` 82.31 kB (JS `app-DqsLDVL_.js` unchanged), compiled tail = port LAST `.ui-skip-link` group before the @property tail with the M6.8 slot/M6.7 badge/M6.6 metric/M6.5 dc blocks intact; app.css 2800 lines, ui.css 400 lines final (trailing newline normalized); `view:cache` OK, Pint passed, PHPUnit 301/1419/0, `git diff --check` clean, /login 200, Bootstrap CDN 0, rg → 0 real `.ui-skip-link` selectors in ui.css; remaining MIGRATE blockers `.ui-notice` (6 files) → `.ui-micro-label` (12 files) → `.metric-value` + DEAD (`.ui-empty`, `.ui-page-*`) + RETAIN/FOUNDATION — deletion still BLOCKED until those pass their gates). M6.10 (2026-09-11): `.ui-notice` family migrated — single base rule ported VERBATIM to app.css EOF (M6.10 block, after the M6.9 skip block), removed from ui.css with a pointer comment, Batch C twin kept as mirror; 7 static Blade sites in 6 files (transactions create/edit alerts, clients/_gip m-0 para, auth/login bars, students/update-photo text-center, admin/users base on the orphan `ui-notice-info` banner), 0 JS classList refs; `snap-notice.cjs` Gate 1 A==B==C byte-identical at 375/576/768/1280 across 5 compositions (alert/para/login/center/info incl. child rects, 0 console/page errors) + SIM-abort capture FULL==SIM; m64 Gate 2 report **byte-identical to the M6.9-final report — 0 deltas** (24/24 status 200, 0 overflow, 0 page errors, consoleErrorsF 0 / S 1 established artifact, bootstrap undefined, skip/mcHover/reduced-motion unchanged); NOTE: one audit run surfaced a transient `grantee-search/grantee` 500 on qr-viewer/grantee-update/unpaid-verification rooted in XAMPP MySQL being down (SQLSTATE[HY000] [2002] port 3306, `restart mysqld if it recurs`) — no CSS involvement, re-run clean; build green `app-Bd_LWcVo.css` 82.52 kB (JS `app-DqsLDVL_.js` unchanged), compiled family sweep OK (data-card/metric-card/status-badge/data-card slots/ui-skip-link/ui-notice/btn-gold/fields/datatables seam), datatables.md5 2ee627b7 unchanged, no bootstrapcdn in bundle, 8 ui.css links intact; app.css 2831 lines (md5 1815b56d), ui.css 396 lines (md5 09ea45a6), both utf8/LF; `view:cache` OK, Pint passed, PHPUnit 301/1419/0, `git diff --check` clean; remaining MIGRATE blockers `.ui-micro-label` (12 files) → `.metric-value` + DEAD (`.ui-empty`, `.ui-page-*`) + RETAIN/FOUNDATION — deletion still BLOCKED and the final deletion gate NOT due)
**M5.1** forensically mapped every live dependency still blocking `ui.css` deletion:
JS/Alpine behavioral contracts (`.show` on 3 Alpine dropdowns — the toast `.show` markers are an
app-owned marker with zero CSS rule, NOT ui.css dependencies; `.collapsed` single accordion;
`.d-none` 74+ classList/jQuery hooks; `.btn-close` 15 incl. 2 JS-created; `list-group-item`
`classList.add` ×4; `.alert` live `querySelector` hook), DataTables integration (datatables.css
B1/B3 chrome self-contained; B2 filter/length **focus** seam → ui.css — Category 2),
bundle ownership (`.align-middle`/`ms-1`/`mt-4`/`text-center` native; phantom `.w-100` — actually
emitted by a literal `w-100` token in the `scholars/_form.blade.php` Blade comment, corrected by
M5.2); verdict — ui.css still NOT SAFE to delete.
**M5.2 LANDED (2026-09-08):** `datatables.css` now OWNS the DT filter/length focus ring
(`outline:0` + `box-shadow:var(--shadow-focus)`, scoped, color/background/border stay B2 base —
no `#164A9C` drift); phantom `.w-100` eliminated (Blade comment reworded; bundle `.w-100` = 0);
`ui.css` + `app.css` byte-identical; Playwright parity harness **0 drift** @375/768/1280 × 4
elements × 22 props × focused/unfocused; build `app-DjX_TTSN.css`; Pint pass; suite 301/1419/0.
**M5.3 LANDED (2026-09-08):** all **72 live `.d-none` references** (17 static + 55 runtime
classList/jQuery ops, 3 inert comments excluded) renamed to the native Tailwind `hidden`
utility across 9 views; `.d-none { display:none !important }` rule **REMOVED** from ui.css (hash
`ABB10A4B…` → `8D6682…`); cascade-equivalence proven (0 dedicated consumer selectors; layered
`.hidden` already in bundle) → no bridge needed; runtime symmetry kept (`classList.toggle/
add/remove('hidden')`, jQuery two-arg `toggleClass('hidden', …)`); build **byte-identical**
(`app-DjX_TTSN.css` still — `.hidden` present, `.d-none`/`.w-100` absent); Playwright lifecycle
parity harness **0 drift, 0 console/page errors, no overflow** @375/768/1280 × 13 elements × 23
props × 6 steps; Pint pass; suite 301/1419/0 (one transient mysqld-restart run excluded).
**M5.4 LANDED (2026-09-08):** dropdown `.show` dependency REMOVED from ui.css — the **3 Alpine
dropdowns** (navbar user menu + clients/transactions Export) now bind the canonical app-owned
**.dropdown-open** class (`:class="{ 'dropdown-open': open }"`), backed by one narrowly-scoped
`app.css` rule `.dropdown-menu.dropdown-open { display:block }` (unlayered 0,2,0 — out-ranks the
ui.css `display:none` base on specificity while ui.css loads last; no generic `.show`
replacement, no arbitrary stacks; `x-show`/plain `block`+`hidden` rejected — both lose to the
0,1,0 base); `.dropdown-menu.show{display:block}` deleted from ui.css (§4.7 comment updated);
toast `.show` ops (3 × `classList.add` in clients views) UNTOUCHED; ui.css hash `8D6682…` →
`2DE83CC8…`, app.css → `ADA35329…`; build `app-kffPtn-M.css` (+42 B = the migration rule; `.hidden`
present, `.show{`/`.w-100` absent); **real-Alpine Playwright parity harness 0 drift** (375/768/1280
× 2 menu composites × closed→open→outside-click→open→Escape→open-other, 26 props + bbox, 0
console/page errors, no overflow; class token differs exactly `show`→`dropdown-open`); view:cache
OK; Pint pass; suite 301/1419/0. **M5.5 LANDED (2026-09-08):** accordion `.collapsed` dependency
REMOVED from ui.css — the single live accordion (`clients/_gip`, only when `$hasGipTransaction`) now
binds the canonical app-owned **`.accordion-open`** class (`:class="{ 'accordion-open': accordionOpen }"`,
direct boolean, no inversion; @click/aria/x-show untouched), backed by three `.accordion`-scoped `app.css`
rules (open color/bg/inset via the `--ui-accordion-*` tokens; open chevron active-icon + `rotate(-180deg)`;
`:not(.accordion-open)` closed corner). The `.accordion` prefix gives 0,3,0 so the open inset keeps
beating the LATER-loading ui.css `.accordion-button:focus` (0,2,0) — reproducing the old
`:focus`-before-`:not(.collapsed)` file-order tie-break; panel visibility was already Alpine-owned
(`x-show` + `x-collapse`), so state/visibility separation required no visibility change. ui.css §4.8
`.collapsed`/`:not(.collapsed)` selectors **deleted** (base `--ui-accordion-*` tokens + `.accordion*`
formation retained); hash `2DE83CC8…` → `05B6D4BC…`, app.css → `574AD8EA…`; build `app-DjDvJDSm.css`
(+595 B = the three rules; `.hidden` present, `.show{`/`.w-100` absent); **real-Alpine +
`@alpinejs/collapse` Playwright parity harness 0 drift** (375/768/1280 × 5 cycles closed→open→closed→
Enter→Space→open→open+FOCUS, ~30 props + bbox + aria + panel + overflow, 0 console/page errors, no
overflow; class token differs exactly `collapsed`→`accordion-open`; open+focused box-shadow inset
BEFORE==AFTER). view:cache OK; Pint pass; suite 301/1419/0. **M5.6 LANDED (2026-09-08):** `.btn-close`
self-ownership REMOVED from ui.css §4.3 — the `.btn-close`/`.btn-close-white` contract (box
`content-box` `1em×1em` `.25em` pad, `#000`, Bootstrap 5.3.2 SVG data-URI chevron
`center/1em auto no-repeat`, `border:0`, radius `.375rem`, `opacity:.5`; `:hover` `.75`;
`:focus` ring `0 0 0 .25rem rgba(13,110,253,.25)` + `opacity:1`; `.disabled/:disabled`; white
filter companion) moved to `app.css` as plain top-level rules with byte-identical values. The class
literal is **RETAINED** — no JS selector binds it (both JS-created toasts wire via
`button[aria-label="Close"]` → `el.remove()`, DetailsPanel via `#detailsClose`), so all 16 consumers
(12 static + 1 sidebar white companion + 2 JS-created) stay byte-identical, **zero blade/JS churn**.
`.alert-dismissible .btn-close` (position/top/right/z-index/padding) stays in ui.css §4.5 as the
`.alert` PARENT positioning contract (alerts remain ui.css-owned). Plain rules chosen over `@utility`
(which would demote to the utilities layer and lose to unlayered ui.css siblings). Harness
(`%TEMP%\opencode\m56`: six static contexts + JS-create/remove/recreate + hover/focus/click/
Enter/Space) @375/768/1280: **0 drift, 0 console/page errors, no overflow**; glyph = same SVG data
URI; build `app-BZH684Eh.css` (+779 B). view:cache OK; Pint pass; suite 301/1419/0.
**M5.7 LANDED 2026-09-08 (see item 14).** → **M6.1 (2026-09-09) AUDIT ONLY: deletion-readiness gate FAILED — `ui.css` deletion remains BLOCKED (see item 14).** → **M6.2 DONE 2026-09-09 — accordion token ownership resolved (7 `--ui-accordion-*` tokens moved verbatim to app.css; ui.css declares none; real-Alpine parity 0 drift; open/closed STATE survives simulated ui.css deletion; accordion *base* still blocked; see item 14).** → **M6.3.1 DONE 2026-09-09 — accordion base+chevron family migrated to app.css (full 22-token family + base formation verbatim; ui.css returns 0 accordion selectors/tokens; parity 0 drift; accordion now survives simulated ui.css deletion INTACT — blocker row cleared; see item 14).** → **M6.3.2 DONE 2026-09-09 — forms family + design-token block migrated (the full `--ui-*` `:root` block relocated verbatim to app.css [canonical owner, ui.css declares 0 tokens] and the whole forms family `form-label`/`form-control`+states/`form-select`+states/`form-check-input`+checked+indeterminate/`input-group`+children ported verbatim; forms parity fixture equals every ui.css-derived expected value incl. navy border + gold 2.8px focus ring; deletion-sim 0 drift across the form matrix — forms blocker row cleared; see item 14).** → **M6.3.3 DONE 2026-09-09 — buttons base family migrated (ui.css §4.1 `.btn` base + hover/focus/disabled + `.btn-sm` + `.btn-primary/-outline-primary/-danger/-outline-danger` + `.btn.btn-gold` focus glow ported verbatim and APPENDED at end of app.css so every same-specificity tie with the earlier app.css brand group resolves exactly as late-loaded ui.css did; buttons parity fixture @375/768/1280 25/25 assertions equal ui.css-derived literals; deletion-sim 0 drift across the whole button matrix — buttons base blocker row cleared; see item 14).** → **M6.3.4 DONE 2026-09-09 — alerts + dismissible placement family migrated (ui.css §4.5 `.alert` base + `.alert-dismissible` + the `.alert-dismissible .btn-close` placement rule [absolute 0/0, z2, 1.25rem/1rem — the parent contract M5.6 kept with alerts] + `.alert-success/-danger/-warning/-info` ported verbatim to end-of-file app.css — `.btn-close` now FULLY app.css-owned; alerts parity fixture @375/768/1280 29/29 assertions equal ui.css-derived literals; deletion-sim 0 drift across the alert matrix — alerts blocker row cleared; see item 14). **M6.3.5 DONE 2026-09-09 — dropdown base + `.btn-group` family migrated (ui.css §4.7 `.dropdown`/`.dropdown-toggle`+`::after` caret/`.dropdown-menu` base + `[data-bs-popper]` offsets/`.dropdown-item` states ported VERBATIM to end-of-file app.css together with §4.4 `.btn-group` — INSEPARABLE: the clients/export + transactions/export dropdowns use `.btn-group` as their positioned wrapper, navbar uses `.dropdown`; all three Alpine menus still bind canonical `.dropdown-open` [M5.4], no `.show`, no `data-bs-popper` in live consumers, global-search self-contained; dropdown parity fixture [5 structures @375/768/1280] 53/53 assertions equal ui.css-derived literals [`.btn-group` relative/inline-flex/middle, open menu block/absolute/z1000/140px/7px/5.25px/shadow, closed display none, popper top 100%/left 0/1.75px + end right 0/left auto, item hover `#f8f9fa`/active `#0d6efd`/disabled `#adb5bd`]; deletion-sim 0 drift across the dropdown matrix — dropdown + `.btn-group` blocker rows cleared; see item 14). M6 execution (deletion) NOT started — gate remains BLOCKED by the remaining families (list-group, tables, grid, utilities, modal).** → **M6.3.6 DONE 2026-09-09 — list-group family migrated (ui.css §4.8 `.list-group`/`.list-group-item`+`:first-child`/`:last-child` corner inheritance+`.list-group-item + .list-group-item` adjacency collapse/`.disabled,`:disabled``/`.list-group-item-action`+`:hover`/`:focus`/`:active`/`.list-group-flush`+`> .list-group-item`+`> .list-group-item:last-child` — all 12 selectors ported VERBATIM to end-of-file app.css so every equal-specificity border/radius tie resolves exactly as late-loaded ui.css did [e.g. `.list-group-flush > .list-group-item:last-child` 0,3,0 wins the final border-bottom]; consumers: ONE static flush list [students/update-photo] + FOUR runtime client-autocomplete ULs [transactions create+edit, scanners/scan, scholars/_form] adding `list-group-item list-group-item-action` via JS classList — class contract unchanged so static + runtime-created items resolve identically; no `.btn` interplay (items are never buttons), no `--ui-list-*` tokens exist; list-group parity fixture [3 structures replicating BOTH live compositions + a `.disabled` item @375/768/1280] 295/295 assertions equal the ui.css-derived literals [flush radius 0 + `0 0 1px` item borders + last border-bottom 0, autocomplete 5.25px/`#fff`/1px `#dee2e6`, items relative/block/7px 14px/`#212529`/`#fff`, adjacency border-top 0, action `rgba(33,37,41,.75)`/inherit, `:hover`/`:focus` `#000`/`#f8f9fa`/z1, `:active` `#212529`/`#e9ecef`, disabled `#6c757d`/pointer-events none, menu width 336px=24rem]; deletion-sim 0 drift across the list-group matrix; block appended at end of app.css — list-group blocker row cleared; M6.3.7 `.table` layout next; see item 14).** → **M6.3.7 DONE 2026-09-09 — `.table` layout family migrated (ui.css §4.6 `.table`/`.table > :not(caption) > * > *`/`.table > tbody`/`.table > thead`/`.table-sm > :not(caption) > * > *`/`.align-middle`/`.table-responsive` — all 7 selectors ported VERBATIM to end-of-file app.css; consumers: `.table-responsive` only at transactions/index:130; `.table` on the plain screens (sessions/online, 4 admin permission matrix tables, households/show members) AND all 11 DataTables id-tables (`table table-sm`/`table … align-middle`); `.align-middle` in 12 files all on table cells — datatables.css l.457-458 scoped rule re-covers DataTables cells; the element-level Reboot `table`/`caption`/`th`/`thead,tbody,tfoot,tr,td,th` rules (ui.css §6 l.513-516) NOT migrated — separate family whose byte-identical twins already exist in app.css `@layer base`; datatables.css UNTOUCHED [hash B82937375133CABD unchanged, B2 seam intact]; table parity fixture [plain + `table-sm` + `.table-responsive` wide + a DataTables-style wrapper with the REAL /css/datatables.css injected in the exact load order + `.align-middle` cells @375/768/1280] 246/246 assertions equal the 14px-root literals; deletion-sim 0 drift across the table matrix — `.table` blocker row cleared; see item 14).** → **M6.3.8 DONE 2026-09-09 — grid family migrated to app.css (the ui.css grid block [`.row`/`.row > *`/`.g-0…g-5`/`@media 576` `.col-sm-4/8`/`@media 768` `.col-md-3/4/6` — all 14 selectors incl. both breakpoints and the 6 gutter-token rules, 37 lines/1288 chars] ported VERBATIM to end-of-file app.css — the FINAL grid block, app.css had zero grid selectors before; `--ui-gutter-x/y` tokens declared ONLY inside the block so canonical token ownership moved with it; `.gap-3/4/5` spacing rules NOT moved [M6.3.9]; static `.row` consumers: grantee_update ×2 [self-update tab + self-service], unpaid_verifications ×2 [self-service + index], payouts/attendance, qr/viewer — exactly 4 `row g-2` + 6 plain `row`, `g-1/g-2` on non-row elements inert, columns `col-md-3`×24/`col-md-4`×18/`col-md-6`×8/`col-sm-4`×12/`col-sm-8`×12; 2 JS templates emit `dl.row` + `dt.col-sm-4`/`dd.col-sm-8`; no nested grids, no header grid; grid-parity fixture @375/576/768/1280 pre→post AND deletion-sim drift 0 at every viewport, 25/25 assertions equal the 14px-root literals; `rg` → 0 real grid selectors in ui.css; datatables.css untouched [full MD5 2EE627B74B0FD170727506AFD46C6C46, B2 seam intact]; prior-phase parity re-verified [buttons 25/25, alerts 29/29, dropdown 53/53, list-group 295/295, table 246/246, accordion/forms 0 drift]; git diff --check clean, view:cache OK, Pint passed, PHPUnit 301/1419/0 — grid blocker row cleared; see item 14). M6 execution (deletion) NOT started — gate remains BLOCKED by the remaining families (spacing-3–5/.rounded drift, text/display/position utilities, modal base).** → **M6.3.9 DONE 2026-09-09 — ALL remaining utility families (spacing, radius, text align/color, display, position, border, background, typography helpers) migrated to end-of-file app.css: 63 rules ported VERBATIM with `!important` intact — incl. the value-exact Tailwind twins (text-center/text-white/bg-white/bg-transparent/m-0/mb-0…4/mt-1/2/ms-1/2/p-1…4/px-0/1/3/4/5/py-2…5/w-auto/rounded-pill) because they carry `!important` (retiring = dropping the flag + proving no competing unlayered declaration; byte-verbatim port guarantees parity by construction, §19) — no RETIRE-to-Tailwind; 60 rules REMOVED as proven DEAD (0 Blade + 0 JS + 0 compiled-twin); unlayered-important beats the `@layer utilities` twins exactly as ui.css-last did; utils-parity @375/576/768/1280 CURRENT vs deletion-sim drift 0 (utilities + M6.3.8 grid slot re-probe) AND grid slot vs M6.3.8 baseline drift 0 AND sim chain post638→now 0, docOverflow 0, only the known aborted-stylesheet artifact in sim; rg → 0 real utility selectors in ui.css; datatables.css untouched (full MD5 2EE627B74B0FD170727506AFD46C6C46, B2 seam intact); prior-phase parity re-verified (buttons 25/25, alerts 29/29, dropdown 53/53, list-group 295/295, table 246/246, accordion/forms 0 drift); ui.css 662→483 lines, app.css 2488→2595 lines, build app-PXXoPz1A.css 80.08 kB (JS unchanged); git diff --check clean, view:cache OK, Pint passed, PHPUnit 301/1419/0 — utilities blocker row cleared; remaining blockers: modal base (M6.3.10, next) + §5 shared components / page-link / Reboot element rules (stay ui.css-owned); deletion still BLOCKED; see item 14).** → **M6.3.10 DONE 2026-09-09 — modal family VERIFIED ZERO-OWNED (0 modal CSS rules anywhere — all live modals are Alpine + Tailwind `z-[200]`/`z-[210]` + `bg-ink/40`; `modal639.mjs` deletion-sim parity @375/576/768/1280 drift 0; `modal-interact.mjs` real partial-bridge interactions 27/27 @375/@1280; only change = ui.css §4.x index comment corrected 483→493 lines MD5 5D6C2B20→6EB8DA64; build byte-identical; PHPUnit 301/1419/0; ui.css retained + linked; deletion gate BLOCKED; final dependency re-audit NOT STARTED; see item 14).**
Previously (2026-09-07): Phase 28 independently re-audited the post-Phase 27 state and confirmed the project is genuinely
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
isCustom` prechecks all byte-identical; show: definition grid +
   data-confirm delete. **Defect fixed:** page-header partial echoed
   `$actions` escaped (`{{ }}`) — header buttons rendered as literal text
   since Batch B adoption; now `{!!actions !!}` with contract comment;
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
`@section('content')` inside `@if (!isPanel)` but never closed it with
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
  - **Add Transaction** → `route('transactions.create',client)` (`btn-navy`),
    rendered only under `$acl->canAccessPage($user, 'all_transactions.php')` (the
    same condition full-page mode uses). Uses the existing `transactions.create`
    route with the client id; backend still enforces the `page:all_transactions.php`
    gate + `TransactionController::create()` per-record `canAccessRecord`. No
    bypass.
  - **Open Full Page** → `route('clients.show',client)` (`btn-subtle`) - the
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
    `admin.audit-logs.leaderboard` (`{ table:('#table').val() }` + CSRF
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

14. **ui.css retirement — M1+M2+M3+M4.1+M4.2+M4.3+M4.4+M4.5+M4.6+M5.1+M5.2+M5.3+M5.4+M5.5+M5.6+M5.7 LANDED (M5.7 2026-09-08, M5 COMPLETE); M6.1 deletion gate AUDITED 2026-09-09 (FAILED — deletion BLOCKED); M6.2 accordion token ownership RESOLVED 2026-09-09 (7 `--ui-accordion-*` tokens relocated verbatim to app.css, ui.css declares none, open/closed STATE survives simulated ui.css deletion, real-Alpine parity 0 drift); M6.3.1 accordion base+chevron family MIGRATED 2026-09-09 (full 22-token family + base formation verbatim to app.css, ui.css returns 0 accordion selectors/tokens, parity 0 drift, accordion survives simulated ui.css deletion INTACT — blocker row cleared); M6.3.2 forms family + design-token block MIGRATED 2026-09-09 (full `--ui-*` `:root` block relocated verbatim to app.css as canonical owner — ui.css declares 0 tokens — and the whole forms family ported verbatim; forms parity fixture @375/768/1280 equals every ui.css-derived expected value incl. navy border + gold 2.8px focus ring; deletion-sim 0 drift across the form matrix — forms blocker row cleared); M6.3.3 buttons base family MIGRATED 2026-09-09 (ui.css §4.1 `.btn` base + hover/focus/disabled + `.btn-sm` + `.btn-primary/-outline-primary/-danger/-outline-danger` + `.btn.btn-gold` focus glow ported verbatim and APPENDED at end of app.css so every same-specificity tie with the earlier app.css brand group resolves exactly as late-loaded ui.css did; buttons parity fixture @375/768/1280 25/25 assertions equal ui.css-derived literals; deletion-sim 0 drift across the whole button matrix — buttons base blocker row cleared); M6.3.4 alerts + dismissible placement MIGRATED 2026-09-09 (ui.css §4.5 `.alert` base + `.alert-dismissible` + the `.alert-dismissible .btn-close` placement rule + the four Bootstrap 5.3.2 emphasis variants ported verbatim to end-of-file app.css — `.btn-close` now FULLY app.css-owned; alerts parity fixture @375/768/1280 29/29 assertions equal ui.css-derived literals; deletion-sim 0 drift across the alert matrix — alerts blocker row cleared); M6.3.5 dropdown + `.btn-group` MIGRATED 2026-09-09 (ui.css §4.7 `.dropdown`/`.dropdown-toggle`+`::after` caret/`.dropdown-menu` base + `[data-bs-popper]` offsets/`.dropdown-item` states ported VERBATIM to end-of-file app.css together with the inseparable §4.4 `.btn-group` — the two export dropdowns' positioned wrapper (navbar uses `.dropdown`); all three Alpine menus still bind canonical `.dropdown-open` [M5.4], no `.show` reintroduced, no `data-bs-popper` in live consumers, global-search self-contained; dropdown parity fixture [5 structures @375/768/1280] 53/53 assertions equal ui.css-derived literals; deletion-sim 0 drift across the dropdown matrix — dropdown + `.btn-group` blocker rows cleared); M6.3.6 list-group MIGRATED 2026-09-09 (ui.css §4.8 all 12 selectors ported VERBATIM to end-of-file app.css — `.list-group`/`.list-group-item`+corner inheritance+adjacency collapse/`.disabled,`:disabled``/`.list-group-item-action`+`:hover`/`:focus`/`:active`/`.list-group-flush`; consumers: ONE static flush list [students/update-photo] + FOUR runtime client-autocomplete ULs whose JS classList class-name contract is unchanged — static + runtime-created items resolve identically; no `.btn` interplay, no `--ui-list-*` tokens; list-group parity fixture [3 structures @375/768/1280] 295/295 assertions equal ui.css-derived literals; deletion-sim 0 drift across the list-group matrix — list-group blocker row cleared); M6.3.7 `.table` layout MIGRATED 2026-09-09 (ui.css §4.6 `.table`/`.table > :not(caption) > * > *`/`.table > tbody`/`.table > thead`/`.table-sm`/`.align-middle`/`.table-responsive` — all 7 selectors ported VERBATIM to end-of-file app.css; consumers: `.table-responsive` only at transactions/index:130; `.table` on the plain screens [sessions/online, 4 admin permission-matrix tables, households/show members] AND all 11 DataTables id-tables; `.align-middle` in 12 files all on table cells, datatables.css l.457-458 scoped rule re-covers DataTables; the element Reboot `table`/`caption`/`th`/`thead,tbody,tfoot,tr,td,th` rules [ui.css §6 l.513-516] NOT migrated — separate family, app.css twins already exist; datatables.css untouched [hash `B82937375133CABD` unchanged, B2 seam intact]; table parity fixture incl. a DataTables-style wrapper with the REAL datatables.css injected in production load order @375/768/1280 246/246 assertions equal the 14px-root literals; deletion-sim 0 drift across the table matrix — `.table` blocker row cleared); M6.3.8 grid MIGRATED 2026-09-09 — ui.css grid block [.row/.row > */.g-0…g-5/@media 576 .col-sm-4/8/@media 768 .col-md-3/4/6, all 14 selectors incl. both breakpoints, 37 lines/1288 chars] ported VERBATIM to end-of-file app.css (canonical owner — the final grid block, app.css had zero grid selectors before; --ui-gutter-x/y tokens declared ONLY inside the block so token ownership moved with it; .gap-3/4/5 NOT moved → M6.3.9); consumers: 4 ow g-2 + 6 plain ow static across grantee_update ×2, unpaid_verifications ×2, payouts/attendance, qr/viewer, col usage col-md-3×24/col-md-4×18/col-md-6×8/col-sm-4×12/col-sm-8×12, g-1/g-2 on non-row elements inert, 2 JS dl.row + dt.col-sm-4/dd.col-sm-8 templates, no nested grids; grid-parity fixture @375/576/768/1280 pre→post AND deletion-sim drift 0 at every viewport, 25/25 assertions equal the 14px-root literals; g → 0 real grid selectors in ui.css; datatables.css untouched (full MD5 EE627B74B0FD170727506AFD46C6C46, B2 seam intact); prior-phase parity re-verified (buttons 25/25, alerts 29/29, dropdown 53/53, list-group 295/295, table 246/246, accordion/forms 0 drift); git diff --check clean, view:cache OK, Pint passed — grid blocker row CLEARED; remaining blocker families: spacing-3–5/.rounded drift, text/display/position utilities, modal base; suite still 301/1419/0.**
→ M6.3.9 utilities MIGRATED 2026-09-09 — ALL remaining ui.css §4.9 utility families to end-of-file app.css: 63 rules ported VERBATIM with Bootstrap `!important` intact (d-inline/d-flex/justify-content-center/-between/align-items-center/-end/position-relative/-absolute/text-start/text-center/text-primary…white/fs-4/small/min-vh-100/w-auto/img-fluid/img-thumbnail/bg-white/bg-transparent/border/border-2/rounded/rounded-pill/m-0/mt-0…5/mb-0…4/ms-1/2/p-1…4/pt-3/pb-1/3/px-0/1/3/4/5/py-1…5/gap-3/4 — incl. every value-exact Tailwind twin because the `!important` flag makes retirement require dropping it + proving no competing unlayered declaration; byte-verbatim port = parity by construction), 60 rules REMOVED as proven DEAD (0 Blade + 0 JS + 0 compiled-twin), NO RETIRE-to-Tailwind; unlayered-important beats the `@layer utilities` twins exactly as ui.css-last did (app.css loads first, ui.css second); live consumer counts (39 classes / 1,148 Blade refs + 115 JS refs), JS classList contract unchanged; ui.css 662→483 lines (0 real utility selectors left), app.css 2488→2595; build app-PXXoPz1A.css 80.08 kB (JS unchanged app-DqsLDVL_.js), compiled ports verified unlayered AFTER all @layer blocks; utils-parity @375/576/768/1280 CURRENT vs deletion-sim drift 0 (utils + grid slot re-probe) AND grid slot vs M6.3.8 baseline drift 0 AND sim chain post638→now 0, docOverflow 0, sim keeps only the known aborted-stylesheet console artifact (1/vp); rg → 0 real utility selectors in ui.css; datatables.css untouched (full MD5 2EE627B74B0FD170727506AFD46C6C46, B2 seam intact); prior-phase parity re-verified (buttons 25/25, alerts 29/29, dropdown 53/53, list-group 295/295, table 246/246, accordion/forms 0 drift); git diff --check clean, view:cache OK, Pint passed, PHPUnit 301/1419/0 — utilities blocker row CLEARED; remaining blocker families: modal base (M6.3.10, next) + §5 shared components / page-link / Reboot element rules (stay ui.css-owned).** → **M6.3.10 VERIFIED 2026-09-09 — modal family ZERO-OWNED: exhaustive inventory found 0 modal CSS rules in ui.css/app.css/compiled (every .modal/.fade/.show/backdrop hit is comment text); all live modals (confirm/record-view/client-form/client-feedback/photo-upload/scanners/gip/self-service/admin/audit_logs/scholars) are Alpine + Tailwind (`fixed inset-0 z-[200]`/`z-[210]` + `bg-ink/40` backdrop + `x-show`/`x-transition.opacity.duration.200ms` + store bridges `uiConfirm`/`uiViewModal`/`clientFormModal`/`clientFeedbackModal`/`window.uiConfirm`/`window.uiViewModal`/`window.showClientFeedback`); `modal639.mjs` deletion-sim parity @375/576/768/1280 drift 0 (computed styles + bbox + centering + scroll containment, open+closed); `modal-interact.mjs` real partial-bridge interactions 27/27 @375 AND @1280 (open/visible/wiring/scroll-lock+release/Esc→false/backdrop→false/Cancel→false/Confirm→true/tab-trap/reopen/85vh-90vh-60vh internal scroll/no-page-overflow/focus-in-dialog/onHidden/z-200-vs-210); ONLY change = ui.css §4.x index comment corrected (483→493 lines, MD5 5D6C2B20→6EB8DA64); build byte-identical (app-PXXoPz1A.css 80.08 kB / app-DqsLDVL_.js, 0 modal selectors compiled, z-[200]/z-[210]/bg-ink/40 confirmed); view:cache OK, Pint pass, diff --check clean, PHPUnit 301/1419/0; ui.css retained + linked; deletion gate BLOCKED; final dependency re-audit NOT STARTED.** → **M6.4 AUDIT-ONLY 2026-09-10 — final dependency re-audit VERDICT: NOT READY, gate REMAINS BLOCKED; deletion NOT STARTED.** Chromium deletion-sim harness (`m64-audit.mjs` → `m64-report.json`; 6 reachable public pages × 375/576/768/1280 × FULL vs SIM [ui.css aborted]; dashboard §5 composition replicated via §6.5 fixture on `/login`; 59 fixture probes; colors sRGB-normalized): 24/24 cells status 200, 0 page errors, 0 FULL console errors, 0 non-ui.css failed requests, `window.bootstrap` undefined in all 48 captures, 0 doc overflow. **Live drift — `.data-card` (all 7 reachable heads, every viewport): border 1px `#E2E5EA` → 0; shadow-set change (drops shadow-md frames, gains `#E2E5EA` ring); −2px height.** Fixture dashboard drift (28/59 probes): `.status-badge` dot 6→5.25px (13 variants) + badge −0.75px; `.metric-card` (4 variants) border 1→0, radius 12→16px, padding 20→17.5px, shadow+ring, height 73→66px, accent 373→375px; `.data-card-header/-body/-footer` padding/gap 16/20/12→14/17.5/10.5; `.ui-empty` 24/16→21/14 (dead, 0 consumers); `.ui-skip-link` focus `left` 0→14px. Interactions: metric-card hover loses `translateY(-2px)`+0.2s/0.2s (shadow composition changes); skip-link focus pill otherwise identical; reduced-motion honored both modes (1e-5s). **Value-identical:** `.ui-notice`, `.ui-micro-label`, `.metric-value`, Reboot/type, `:focus-visible`, real `.page-link` (scoped app.css twin + datatables.css cover it; fixture page-link drift = scope artifact). Verification: build green (`app-PXXoPz1A.css`), `view:cache` OK, Pint pass, PHPUnit **301/1419/0**, `git diff --check` clean. Only docs changed.**
    The removal audit (see audit entry) verdict was **NOT SAFE — MIGRATION REMAINING**.
    **M1/M2/M3/M4.1/M4.2** landed as logged. **M4.2 (forms family) is done:** `.form-label`
    migrated to Tailwind `mb-2` on **46** labels (`grantee_update/self-service` +
    `_self_update_tab`) — exact `.5rem` parity (root 14px ⇒ 7px, verified live equal on
    `/grantee-update` @ 375/768/1280); `.form-control` (189), `.form-select` (60),
    `.form-check-input` (37), `.input-group` (5), `-sm` (19) **retained with justification**
    (no Tailwind twin reproduces the Bootstrap control/chevron/native-checkbox metrics
    under Preflight-OFF + linked ui.css; `.input-group` tied to excluded `.btn` seam);
    `.is-invalid` is a custom non-ui.css contract, untouched; 0 JS selector refs to any
    target class. Build CSS hash unchanged (markup-only); Playwright 21/21 label parity +
    `.form-control` untouched + 0 console/errors/overflow; PHPUnit 301/1419/0, Pint clean.
    **M4.3 (layout/grid family) is done — RETAIN ALL** (valid per plan §19): `.row` (20),
    `.col-sm-*` (48), `.col-md-*` (54), `.g-2` (4) retained after full inspection. The grid
    is a genuine **gutter-compensation** model (ui.css L1227-1258; live-measured:
    `gap:normal`, `.row` negative `-3.5px`/`-10.5px` margins, `.row>*` child padding
    `3.5px`/`10.5px`, `flex-shrink:0`, `flex:0 0 auto` width-basis) — Tailwind `gap-*/w-*`
    cannot reproduce outer-edge compensation + wrap/shrink semantics without the bespoke
    `-mx-*`+`*:px-*` construction §7 forbids; `.col-sm-*` additionally sit in dynamic JS
    `<dl class="row">` DataTables modals (excluded). No spacing migration; no view/CSS/JS
    change; build clean (hashes unchanged), 0 console/overflow @375/768/1280, PHPUnit
    301/1419/0, Pint clean; `ui.css` unchanged+linked; Preflight OFF; M4.4+ untouched.
    **M4.3 gate PASS. Next: M4.4** (next M4 family per candidate order) — see
    `docs/UI_CSS_RETIREMENT_PLAN.md`. **Do NOT start M4.4 unless asked;**
    `ui.css` is retained and Bootstrap-parity vocabulary untouched until M4–M6.
    **M4.4 (buttons family) is done — RETAIN ALL** (valid per plan §19): `.btn` base (25,
    all in `btn <variant>` composites), `.btn-sm` (4), `.btn-primary` (1), `.btn-danger` (1),
    `.btn-outline-primary` (2), and project variants `.btn-gold` (34)/`.btn-navy` (47)/
    `.btn-red` (10)/`.btn-subtle` (81)/`.btn-outline` (3)/`.btn-outline-red` (3) retained.
    Live-cascade probe (elements injected on `/login`, default/hover/active/focus
    @375/768/1280) proved the `ui.css` `.btn` base (unlayered, linked after `@vite`, same
    0,1,0 specificity) **wins the metric conflicts** on every composite today — `btn btn-navy`
    renders the ghost Bootstrap metrics (transparent `#212529`, 5.25/10.5px padding, 5.25px
    radius, 14px/400, 1px border), so removing `.btn` flips display/padding/radius/font/color/
    bg/border/transition in **all** states (12+ props) — a §1-forbidden redesign; the pure
    Bootstrap variants have no canonical Tailwind twin (navy-light hover, `.5` focus ring,
    `.btn-sm` 3.5/7px) and live only in excluded JS DataTables/grantee-QR templates.
    `.btn-group` (2, Alpine) + `.btn-close` (15) excluded untouched; 0 JS selector refs.
    Zero view/CSS/JS changes; build hashes unchanged; 0 console/overflow @375/768/1280;
    PHPUnit 301/1419/0, Pint clean; `ui.css` unchanged+linked; Preflight OFF; M4.5+ untouched.
    **M4.4 gate PASS. Next: M4.5** — see `docs/UI_CSS_RETIREMENT_PLAN.md`.
    **Do NOT start M4.5 unless asked;** `ui.css` is retained and Bootstrap-parity
    vocabulary untouched until M4–M6.
    **M4.5 (alerts/feedback family) is done — RETAIN ALL** (valid per plan §19): `.alert`
    family = 17 class usages + 2 selector refs — 2 server-rendered (`layouts/app:101`
    `.alert.alert-danger.alert-dismissible` session-error list, Alpine `x-show` + excluded
    `.btn-close`; `unpaid/self-service:109` `#successBox` `.alert.alert-success.d-none` state
    hook, filled by JS) + 15 Blade-embedded JS template literals injecting `alert-danger` ×11 /
    `alert-success` ×3 / `alert-warning` ×1 into empty containers. `app.css` has **no `.alert`
    rules**; `.ui-notice` is a redesigned gold bar, not a twin; alerts use exact Bootstrap 5.3
    emphasis bytes (@ui.css L693-731) that would need §10-forbidden arbitrary-value stacks;
    `.alert` is a **live JS selector contract** (`msg.querySelector('.alert')` QR-error path,
    grantee_update self-service:444 + _self_update_tab:424); `-primary/secondary/light/dark/
    heading/link` = 0 consumers + 0 rules. Live in-flow probe on `/grantee-update` @375/768/1280
    matched ui.css byte-for-byte (14px/14px/5.25px; dismissible 42px right-pad, close z-2;
    0 console/overflow). Zero view/CSS/JS changes; build hashes unchanged; PHPUnit 301/1419/0,
    Pint clean; `ui.css` unchanged+linked; Preflight OFF; no `.btn`/`.btn-close`/toast/flash/
    `d-none` migration; M4.6+ untouched. **M4.5 gate PASS. Next: M4.6** — see
    `docs/UI_CSS_RETIREMENT_PLAN.md`. **Do NOT start M4.6 unless asked;** `ui.css` is
    retained and Bootstrap-parity vocabulary untouched until M4–M6.
    **M4.6 (remaining component families — FINAL M4 sub-step) is done — RETAIN ALL**
    (valid per plan §19): tables (`.table` ×21 [15 DataTables + 6 plain], `.table-sm` ×12,
    `.table-responsive` ×1, `.align-middle` ×12), dropdowns/btn-groups (3 Alpine
    `.dropdown-menu`+`.dropdown-item`+`.dropdown-toggle` on a `.show` state contract),
    `.btn-close` ×15 (SVG glyph, content-box 1em+.25em=21×21, opacity .5/.75, focus-ring,
    white variant; inside excluded alert/toast/sidebar/error-bar parents), accordion ×1
    (clients/_gip, Alpine x-show/x-collapse + `.collapsed` class-binding + chevron swap),
    list-group ×5 (autocomplete containers; items injected via JS `classList.add` contract),
    `.d-none` (74 + JS hooks, behavioral), remaining utilities. No canonical app.css twin for
    any family; Tailwind mapping would be §10-forbidden arbitrary-byte stacks; `.show`/
    `.collapsed`/`.d-none`/`list-group-item` are live JS/Alpine contracts; DataTables
    bootstrap5 CDN integration consumes `.table`; utility values diverge (`.mt-4`=24px vs 16px,
    `.text-danger`≠`--color-red`, `!important`). Live-tab probe @375/768/1280 matched every
    declaration (incl. `.show` toggle, 21×21 close box), 0 console/overflow. Zero view/CSS/JS
    changes; build hashes unchanged; PHPUnit 301/1419/0; Pint clean; `ui.css` unchanged+linked;
    Preflight OFF; `datatables.css` diff pre-existing (untouched); DataTables CDN intact;
    no backend/DB; no M5/M6. **M4.6 gate PASS. M4 COMPLETE. Next: M5.1** (behavioral-contract
    audit — see `docs/UI_CSS_RETIREMENT_PLAN.md`). **Do NOT start M5 unless asked;**
    `ui.css` is retained and Bootstrap-parity vocabulary untouched until M5–M6.
    **M5.1 (behavioral-contract & dependency audit, AUDIT ONLY) is done 2026-09-08.**
    Verdict: **ui.css still NOT SAFE to delete.** Captured: `.show` = Alpine state class on 3
    dropdowns (clients/index:269, transactions/index:98, navbar:86) with direct
    `:class="{ 'show': open }"` — renaming breaks menu visibility — while the toast `.show`
    `classList.add` markers (clients/index:771,795, clients/_details:842) are app-owned with
    **no CSS rule** (neither ui.css nor datatables.css has `.toast`/`.show`-for-toast);
    `.collapsed` = Alpine class-binding on the single accordion (clients/_gip, chevron swap +
    corner radius from ui.css §4.8; app.css has 0 accordion rules); `.d-none` = 21 static +
    ~74 refs, classList/jQuery toggles in 10+ files (Tailwind emits 0); `.btn-close` ×15
    (13 static + 2 JS-created `innerHTML` close buttons); `list-group-item`+`-action` =
    runtime `classList.add` ×4 files + static composite; `.alert` = live `querySelector('.alert')`
    hook ×2 (M4.5 unchanged). DataTables: 12 views/CDN, `datatables.css` B1/B3 chrome
    self-contained EXCEPT **B2 focus seam** → ui.css `.form-control:focus`/`.form-select:focus`
    (Category 2, folds into M5.2). Bundle: already emits `align-middle`/`ms-1`/`mt-4`/
    `text-center` (identical values); phantom `.w-100` (400px) — *attribution corrected by M5.2:
    emitted by the literal `w-100` token in the `scholars/_form.blade.php` Blade comment (a file
    on the `@source` allowlist), NOT the app.css comment, which is inert*; does NOT emit `.d-none`/`.show`/`.collapsed`/`.dropdown-menu`/
    `.accordion*`/`.list-group*`/`.row`/`.col-*`/`.text-muted`/`.form-*`/`.btn-close`/`.alert-*`
    → all genuine blockers. ZERO application files changed; hashes + PHPUnit 301/1419/0 unchanged.
    **M5.1 gate PASS. Next: M5.2** (datatables.css B2 focus seam + phantom `w-100`),
    then M5.3 `.d-none`→`hidden`, M5.4 dropdown `.show`, M5.5 accordion, M5.6 `.btn-close`
    `@utility`, M5.7 utility/grid/text re-scan). **M5.2 is DONE 2026-09-08** — `datatables.css`
    owns the DT filter/length focus ring (`.dataTables_wrapper .dataTables_filter input:focus,
    .dataTables_length select:focus { outline: 0; box-shadow: var(--shadow-focus); }` — scoped,
    non-DT controls still resolve in ui.css; color/background/border deliberately NOT re-declared,
    focused border stays `#dee2e6`). Phantom `.w-100` emitter identified & fixed = the literal
    `w-100` token inside the **`scholars/_form.blade.php` Blade comment** (file is on the `@source`
    allowlist; app.css comment proven inert — M5.1's attribution corrected); comment now reads
    "full-width Bootstrap utility", bundle `.w-100` = 0, real utilities (`.p-4`/`.gap-3`/`.w-full`)
    intact. `ui.css` + `app.css` byte-identical (net 0); Playwright computed-style parity harness
    **0 drift** (375/768/1280 × 4 elements × 22 props × focused/unfocused, 0 console errors, no
    overflow; ring `rgba(252,209,22,.3) 0 0 0 2.8px`, outline 0, border `#dee2e6`); view:cache OK;
    Pint pass; suite **301/1419/0**. Build `app-DjX_TTSN.css`. **M5.2 gate PASS. M5.3 DONE
    2026-09-08** — `.d-none` behavioral migration: all **72 live `.d-none` references** (17 static
    `class="… d-none"` + 55 runtime `classList.add/remove/toggle('d-none')` + jQuery two-arg
    `toggleClass('d-none', …)` ×2 in transactions inline-edit) across 9 views renamed to the
    native Tailwind **`hidden`** utility; the ui.css rule `.d-none { display: none !important; }`
    (ui.css:1057, unlayered, 0,1,0) was **REMOVED** (ui.css hash `ABB10A4B…` → `8D6682…`;
    1492→1491 lines). Strategy = native `hidden` (Option A), no `@utility d-none` bridge:
    exhaustive inventory proved zero dynamic/template-literal consumers and zero dedicated
    consumer selectors anywhere (`.suggestions-list` has no CSS; `.alert` sets no display), and
    the bundle already emitted layered non-important `.hidden{display:none}` from other consumers
    → cascade-equivalent on every consumer; `!important` not load-bearing. Remaining `d-none`
    text = 2 inert comments (`resources/css/app.css:96`, `clients/_details.blade.php:6`) —
    documented leftovers, not consumers. Build **byte-identical** (`app-DjX_TTSN.css`; bundle
    `.hidden` present, `.d-none`/`.w-100` absent — net 0 CSS bytes). Playwright lifecycle parity
    harness (ui-OLD.css w/ `.d-none` vs ui-NEW.css + bundle; 13 elements × 23 props × steps
    initial/reveal/re-hide/×4-toggle/two-arg-force/dynamic-insert @375/768/1280, final re-hide
    sweep): **0 drift, 0 console errors, 0 page errors, no overflow**; focused probes: hidden
    `display:none`/offset 0, revealed `block` (successBox 1280×52), img `inline`, re-add → all
    `none`, ×4 toggle deterministic. `view:cache` OK; Pint pass; suite **301/1419/0** (one
    transient mysqld-restart run excluded). M4.6's site map (dropdown `.show` ×3 Alpine,
    `.collapsed` accordion, `.btn-close` ×15, list-group, `.alert` querySelector, utilities) and
    M5.2's B2 focus seam remain intact. **M5.3 gate PASS. M5.4 DONE 2026-09-08** — dropdown
    `.show` behavioral migration. All **3 Alpine dropdowns** (navbar user menu, clients/Export,
    transactions/Export) bind the canonical app-owned **`.dropdown-open`** state class
    (`:class="{ 'dropdown-open': open }"`; clients/transactions inside Blade-string templates as
    `\'dropdown-open\'`); `app.css` gains exactly one narrowly-scoped rule
    `.dropdown-menu.dropdown-open { display: block; }` (top-level/unlayered, 0,2,0 — beats the
    linked-ui.css `.dropdown-menu{display:none}` base (0,1,0) on specificity regardless of load
    order); ui.css `.dropdown-menu.show { display: block; }` (0,2,0) **deleted** + §4.7 comment
    updated. Strategies rejected: plain `block`/`hidden` utilities (both unlayered and loaded
    BEFORE ui.css — `.block` (0,1,0) loses the source-order tie to the 0,1,0 base → menu never
    opens) and `x-show="open"` (init-flash risk + open state would fall back to the hidden base
    unless the base rule was also removed). **No generic `.show` replacement**, no arbitrary
    stacks; Alpine state/events/DOM and `.dropdown/.dropdown-toggle/.dropdown-menu-end/
    .dropdown-item` untouched; `aria-expanded`, `@click.outside`, `@keydown.escape` identical.
    **Toast `.show` explicitly untouched** (3 runtime `classList.add('show')` — clients/index ×2,
    _details ×1 — no CSS dependency). ui.css hash `8D6682…` → `2DE83CC8…`; app.css → `ADA35329…`;
    datatables.css `B82937…` unchanged. Build `app-kffPtn-M.css` (59.64 kB, +42 B = migration
    rule; `.hidden` present, no `.show{`, no `.w-100`). **Real-Alpine Playwright parity harness**
    (ui-OLD.css + re-inserted `.dropdown-menu.show` vs ui-NEW + new bundle; navbar + export menu
    composites; 26 props + bbox + aria + class + body overflow; lifecycle closed→open→
    outside-click→open→Escape→open-other @375/768/1280): **0 drift, 0 console errors, 0 page
    errors, no overflow**; the class token differs exactly `show`→`dropdown-open`. Focused
    probe: closed `display:none`/0×0; open `display:block`, absolute, z-index 1000, min-width
    140px, 7px pad, 5.25px radius, Bootstrap shadow, item 3.5/14px #212529; aria false⇄true;
    outside-click + Escape close identically. `view:cache` OK; Pint pass; suite **301/1419/0**
    (53.76s). Downstream: `.show` repo-wide now = 3 toast runtime ops only (no CSS rule);
    remaining ui.css blockers = `.collapsed` accordion, `.btn-close` ×15, list-group classList,
    `.alert` querySelector, utilities, `.table*`/grid/text. **M5.5 DONE 2026-09-08** — accordion
    `.collapsed` behavioral migration. The single live accordion (`clients/_gip`) now binds the
    canonical app-owned **`.accordion-open`** class (`:class="{ 'accordion-open': accordionOpen }"`),
    backed by three `.accordion`-scoped `app.css` rules: `.accordion .accordion-button.accordion-open`
    (open color #084298 / bg #cfe2ff / inset shadow, via the `--ui-accordion-*` tokens still owned by
    ui.css §4.8), `.accordion .accordion-button.accordion-open::after` (blue active-icon +
    `rotate(-180deg)`), `.accordion .accordion-item:last-of-type .accordion-button:not(.accordion-open)`
    (closed last-item corner; open re-routes the corner to the existing `.accordion-collapse` radius).
    **Visibility untouched** — the panel stays `x-show="accordionOpen" x-collapse` (bundled
    `@alpinejs/collapse`, 250 ms height transition); `.collapsed` was a pure visual-state predicate so
    no visibility change was needed. **Cascade proof:** the `.accordion` prefix raises specificity to
    0,3,0 so the open inset shadow keeps beating the LATER-loading ui.css `.accordion-button:focus`
    (0,2,0); pre-migration ui.css ordered `:focus` before `:not(.collapsed)`, so an unprefixed (0,2,0)
    app rule WOULD flip open+focused to the focus ring — the scope reproduces the old tie exactly
    (probed: open+focused box-shadow inset identical BEFORE/AFTER). ui.css §4.8 `:not(.collapsed)`,
    `:not(.collapsed)::after`, `.accordion-button.collapsed` **deleted** + comment updated; base
    `--ui-accordion-*` tokens and `.accordion*` formation retained (not `.collapsed`-dependent). ui.css
    hash `2DE83CC8…` → `05B6D4BC…`; app.css → `574AD8EA…`; datatables.css `B82937…` unchanged. Build
    `app-DjDvJDSm.css` (60.23 kB, +595 B = the three rules; `.hidden` present, no `.show{`, no
    `.w-100`). **Real-Alpine + `@alpinejs/collapse` Playwright parity harness** (`%TEMP%\opencode\m55`;
    before = ui.css + re-appended retired rules + bundle STRIPPED of the three `accordion-open` rules —
    stripping is mandatory or the new `:not(.accordion-open)` corner rule masks the old contract;
    16-row dl grid; lifecycle closed→open→closed→Enter→Space→open→open+FOCUS→closed @375/768/1280):
    **0 drift, 0 console errors, 0 page errors, no overflow**; token differs exactly
    `collapsed`→`accordion-open`; open chevron `matrix(-1,0,0,-1,0,0)` + active icon MATCH, closed
    `none`; corners 4.25/5.25 px identical. Accessibility: `aria-expanded` false⇄true, `aria-controls`/
    `aria-labelledby` intact; Enter + Space both toggle (native button activation); focus stays on the
    trigger. `view:cache` OK; Pint pass; suite **301/1419/0**. Downstream: `.collapsed` live consumers
    **1 → 0** (3 inert comment mentions remain); remaining ui.css blockers = `.btn-close` ×15,
    list-group classList, `.alert` querySelector, utilities, `.table*`/grid/text. **M5.5 gate PASS.**
    **M5.6 DONE 2026-09-08 — `.btn-close` ownership & behavioral migration.** The `.btn-close`/
    `.btn-close-white` SELF contract REMOVED from ui.css §4.3 → canonical `app.css`, plain
    top-level/unlayered rules, values byte-identical: box `content-box` `1em×1em` `.25em` padding,
    `color:#000`, Bootstrap 5.3.2 SVG data-URI chevron `center/1em auto no-repeat`, `border:0`,
    `border-radius:.375rem`, `opacity:.5`; `:hover` `.75`; `:focus` `outline:0` +
    `0 0 0 .25rem rgba(13,110,253,.25)` + `opacity:1`; `.disabled, :disabled`
    `pointer-events:none; user-select:none; opacity:.25`; `.btn-close-white`
    `filter:invert(1) grayscale(100%) brightness(200%)`. **Class literal RETAINED:** JS selector
    audit proved no contract on the name — both JS-created toast buttons are wired via
    `el.querySelector('button[aria-label="Close"]')` → `el.remove()` (clients/index `showToast`,
    clients/_details feedback toast; both build the identical `<button type="button" class="btn-close
    shrink-0" aria-label="Close"></button>` string and are destroyed/recreated deterministically),
    DetailsPanel via `#detailsClose` — so all 16 consumers (12 static + sidebar white companion + 2
    JS-created) keep byte-identical strings; **zero blade/JS file changed**. Plan deviation:
    plain rules instead of the previously-proposed `@utility btn-close` (a v4 `@utility` lives in the
    utilities LAYER and loses cascade priority to unlayered ui.css siblings; plain rules reproduce
    the exact unlayered conflict resolution) and no 13 static swaps (name is not a JS contract).
    `.alert-dismissible .btn-close` (absolute top 0 right 0, z-index 2, pad 1.25rem/1rem) REMAINS in
    ui.css §4.5 — it is the `.alert` parent's child-positioning contract (alerts stay ui.css-owned
    per M5.6 scope), and the probe shows the alert idle button at pad 17.5px/14px BEFORE==AFTER.
    **Parity harness** (`%TEMP%\opencode\m56`; before = ui.css + re-appended retired §4.3 block +
    bundle MINUS the six contiguous minified rules [61008→60229 B]; six static contexts — plain,
    modal-hdr flex, `.alert-dismissible`, `.sidebar` white, flash-toast flex, disabled clone — +
    JS-create/click-remove/recreate; lifecycle idle→hover→focus→create→remove→recreate→
    alert-click-close→Enter→Space, ~60 props × `::before`/`::after` + bbox) @375/768/1280:
    **0 drift, 0 console errors, 0 page errors, no overflow**; glyph = SAME `data:image/svg+xml` URI;
    pinned idle 14×14/content-box/pad 3.5px/border 0/radius 5.25px/op .5, focus ring
    `rgba(13,110,253,.25) 0 0 0 3.5px` + op 1. `view:cache` OK; Pint pass; suite **301/1419/0**;
    build `app-BZH684Eh.css` (61.01 kB, +779 B = the six rules; `.hidden` present, no `.show{`, no
    `.w-100`); datatables.css `B82937…` unchanged; ui.css `05B6D4BC…` → `36CCD82D…`, app.css
    `574AD8EA…` → `EC35FA3A…`. Downstream: `.btn-close` production selectors **6 → 0 in ui.css**
    (only the alert-family `.alert-dismissible .btn-close` positioning rule remains); `.btn-close-white`
    0 rules in ui.css; remaining ui.css blockers = list-group classList, `.alert` querySelector,
    utilities, `.table*`/grid/text (M5.7 re-scan), `.btn*`/`.form-*`/`.modal` base.
    **M5.6 gate PASS.**
    **M5.7 DONE 2026-09-08 — final utility/grid/text dependency re-scan.** Exhaustive value-level
    audit of every remaining §4.9 utility, grid and text family against the COMPILED Tailwind
    bundle (`app-BZH684Eh.css`) — values by compiled declaration, never class-name trust.
    **11 ui.css rules MIGRATED (zero Blade/JS edits — the consumer class literal IS the Tailwind
    class string, so the twins were already compiled):** `.gap-1`/`.gap-2`, `.flex-wrap`,
    `.top-0`/`.bottom-0`, `.overflow-hidden`/`-visible`/`-x-auto`/`-y-auto`, `.ms-auto`,
    `.mx-auto` — each value-exact AND cascade-isolated (no competing unlayered ui.css/app.css
    declaration targets any consumer; verified per family). **RETAINED with forensic reasons:**
    full spacing ladder (values 3–5 differ from Tailwind — `mt-3` 1rem vs .75rem, `mt-4` 1.5rem
    vs 1rem, `mt-5` 3rem vs 1.25rem; 0–2 are value-equal BUT unlayered element/component rules
    flip the layered twins: §4.10 `h1/p/dd` margins, `.list-group-item` padding beats `px-0`,
    `.table{vertical-align:top}` beats the `align-middle` twin — live flip proofs below);
    `.rounded` (.375rem vs Tailwind .25rem — 124 consumers keep .375rem); `.border` (twin has
    no `#dee2e6` color); text colors (`text-danger #dc3545` etc. — no Tailwind token);
    `.text-center` (unlayered `th{text-align:inherit}` beats the layered twin → headers snap);
    `.bg-transparent` (flips `.list-group-item` bg to #fff); `.bg-white`; `.align-middle`
    (the `.table align-middle` cell-centering MECHANISM); `d-*`/`position-*` (class-name renames,
    negligible value, rename risk); `fw-*`/`fs-4`/`.small`/`img-fluid`/`img-thumbnail`/
    `min-vh-100`/`w-auto`; grid `.row/.col-*/.g-*` + `.table*` (M4.3 + M4.6 ownership).
    **DEAD classified for M6:** `mt-auto`, `mb-auto`, `flex-nowrap`, `me-auto`,
    `overflow-auto`, `d-block`, `d-inline-block`, `d-inline-flex`, `position-static/-fixed`,
    `text-end`/`-body`/`-secondary`, `fw-light`/`-normal`, `fst-italic`, `rounded-1/2/3/-circle`,
    `border-1/-3`, `ps-0`, `pe-0`, `py-0`, `pt-0`, `pb-0`, `my-0`, `mx-0`, `g-0/-4/-5`, `gap-5`.
    **Parity harness** (`%TEMP%\opencode\m57`; before ui-BEFORE + current bundle / after edited
    ui.css + current bundle; full-body computed-style + bbox digest over `/login`, `/qr-viewer`,
    `/grantee-update`, `/unpaid-verification` @375/768/1280 + REP-CONTEXT mirroring the
    auth-gated shells — sidebar `fixed top-0 bottom-0 overflow-visible ms-auto`, sticky `top-0`
    nav, `data-card mx-auto`, `flex flex-wrap gap-3` quick actions, `gap-1` tabs, `justify-content-center
    gap-2` band, `h-2 overflow-hidden` metric strip, `overflow-x-auto` tables with `th.text-center`
    + `table.align-middle`): **0 drift, 0 console/page errors, no overflow**; interceptor proven
    live by poisoned probe (`.gap-2{gap:99px!important}` read 99px vs the Tailwind twin 7px).
    `view:cache` OK; Pint pass; suite **301/1419/0**; build byte-identical `app-BZH684Eh.css`;
    ui.css hash **`B593DDF8…`** (prev `4306871F…`); datatables.css intact (595 lines LF, sole
    HEAD-delta = M5.2 seam); M5.2–M5.6 guards re-verified. **M5.7 gate PASS. Next: M6
    (full-matrix regression + terminal deletion). M6 NOT started; `ui.css` retained and linked,
    Bootstrap-parity vocabulary untouched until M6.**
    **M6.1 DONE 2026-09-09 — deletion-readiness gate audit (AUDIT ONLY).** Executed the §9 M6
    deletion gate exactly as written. **Verdict: NOT SAFE — gate FAILS, deletion remains
    BLOCKED. Zero application files changed** (no deletion/unlink; all 8 `<link>`s + `ui.css`
    intact; no Blade/CSS/JS/PHP/DB edits). Evidence: PostCSS inventory **333 rules / 285 simple
    classes**; consumer scan **94 live / 191 dead** (21 with exactly 1); build-ownership grep
    (app.css emits only the M5.x ported set + matching value twins — nothing for btn base, forms,
    alerts, dropdown base, accordion base, list-group, `.table` layout, grid, live utilities);
    **Chromium simulated deletion** (`page.route('**/css/ui.css', abort)` vs normal) @375/768/1280
    × `/login`, `/qr-viewer`, `/grantee-update`, `/unpaid-verification` + auth-shell parity
    harness: 0 console/page errors, 0 overflow, `:focus-visible` gold seam identical — but
    measured drift on every retained family (`.btn`→UA button; form controls→intrinsic width/
    radius 0; `.alert*`+dismissible close placement lost; dropdown static/transparent/z-auto;
    accordion base + 22–28px button font; list-group flex→block; `table.table` width auto;
    **grid `.row/.col-*/.g-*` collapse to stacked blocks**; `text-danger`/`d-flex`/`position-relative`
    vanish; `.mt-3`/`.px-3`/`.gap-3` 14px⇒10.5px, `.rounded` 5.25px⇒3.5px value-drift). New
    blocker found: **app.css's M5.5 `.accordion-open` state consumes 7 `--ui-accordion-*`
    tokens declared only in ui.css `:root`** → deletion invalidates the migrated accordion
    active-state. Guards M5.2–M5.7 PASS (ui.css: 0 `.dataTables_*`/`.d-none`/`.show{`/
    `.collapsed{`/`.btn-close{` self; build: 0 `.w-100`/`.show{`/`.collapsed{`; datatables.css
    self-contained). SAFE/B verified: `.btn-gold/-navy/-red/-subtle/-outline(-red)`, `.btn-close`
    self, `.metric-card/-value`, `.status-badge`, `.data-card-*`, `h1–h6`, focus/reduced-motion,
    `hidden`, `.dropdown-open`/`.accordion-open` states, matching-value twins, DataTables seam,
    `.page-link` family (DEAD non-DT). e2e: `alpine-phase0` PASS; `smoke.spec` 4× FAIL at
    `signIn` only (`smoke_superadmin` absent from local DB copy — env, not CSS). PHPUnit
    **301/1419/0** unchanged. **M6.2 defined** (port the 7 accordion tokens → `@theme static`;
    port/rewrite accordion→forms→buttons→alerts→dropdown→list-group→table→grid→utilities with
    §6.2 allowlist; resolve spacing-3–5 drift; delete the DEAD set; re-run gate incl.
    auth-shell when a test account exists). **M6 NOT executed; `ui.css` retained and linked,
    Bootstrap-parity vocabulary untouched.**
    **M6.2 DONE 2026-09-09 — accordion token ownership resolution (canonical owner = app.css).**
    Scope: ONLY the M6.1 blocker — the M5.5 `.accordion-open` state consumed 7 `--ui-accordion-*`
    tokens declared only by ui.css. No deletion, no unlink, no other token cleanup, no component
    migration, no Blade/JS/PHP/DB change. **Scope correction:** the seven tokens live on **ui.css
    §4.8 `.accordion`** (scoped), not `:root` (as the M6.1 record worded it) — values and the move
    are unaffected. **Moved verbatim** (`--ui-accordion-border-color:#dee2e6; -border-width:1px;
    -inner-border-radius:calc(.375rem - 1px); -btn-icon-transform:rotate(-180deg);
    -btn-active-icon:url(…fill='%23084298'…); -active-color:#084298; -active-bg:#cfe2ff`) into a
    **new app.css `.accordion` block above the M5.5 rules** (same names, same byte-identical
    values, same `.accordion` scope; no `!important`; `@vite`→`ui.css`→screen order unchanged).
    ui.css §4.8 keeps the 15 other accordion tokens; its base-formation references to the moved
    tokens (`.accordion-item` border, first-of-type inner radius) resolve by inheritance from the
    app.css scope. Build `app-D8NYbDco.css` (61.57 kB) — seven canonical tokens + all three M5.5
    rules + `.hidden`/`.dropdown-open`/`.btn-close` present. **Parity harness** (real built Alpine
    + `@alpinejs/collapse` injected on the real `/login`, exact `_gip` markup/contract): baseline vs
    post-change **0 computed-style drift** @375/768/1280 across closed→open→closed + Enter→Space→
    Enter (final: color `rgb(8,66,152)`, bg `rgb(207,226,255)`, inset `rgb(222,226,230) 0px -1px
    0px 0px`, `::after` `rotate(-180deg)`, closed corner 4.25px, focus ring 3.5px; 0 errors, 0
    overflow). **Deletion simulation:** open/closed STATE now survives `ui.css` abort identically
    (color/bg/inset/active chevron/rotation/corner/panel); the **base formation** (button
    box/typography, item border, `::after` content/size/transition, focus ring) still needs ui.css
    §4.8 — recorded blocker (scope: not migrated). Pseudo nuance: computed `transform` reads
    `none` on the non-generated `::after`, but force-generating the pseudo returns the identical
    `rotate(-180deg)` — token-owned declarations resolve. **Regression:** `git diff --check` clean,
    `view:cache` OK, Pint passed, PHPUnit **301/1419/0** (unchanged). M5.2–M5.7 guards re-verified
    PASS. **M6.2 COMPLETE. M6.3 NOT STARTED. `ui.css` exists and is linked (all 8 `<link>`s
    intact); deletion gate remains BLOCKED by the remaining base families.**
    **M6.3.1 DONE 2026-09-09 — accordion base+chevron family migrated to app.css (canonical
    owner).** Scope: FIRST retained-family migration per the §M6.1 dependency order (accordion
    base+chevron first). No deletion, no unlink, no Blade/JS/PHP/DB change. **Source (two app
    files):** app.css — the `.accordion` token block grew 7 → **full 22-token family** (the 15
    remaining `--ui-accordion-*` moved VERBATIM: color/bg/transition/border-radius/btn-padding-x/y/
    btn-color/btn-bg/btn-icon/btn-icon-width/btn-icon-transition/btn-focus-border-color/
    btn-focus-box-shadow/body-padding-x/y) and a **base-formation section** was appended after the
    M5.5 rules porting the whole ui.css §4.8 formation **verbatim** (`.accordion-button`,
    `:focus`, `::after` chevron content/SVG, `.accordion-header`, `.accordion-item` + first/not-
    first/last-of-type corners + inner button corners, `.accordion-collapse` radius,
    `.accordion-body` padding); M5.5 selectors unchanged, stale "base stays in ui.css" comment
    phrase updated. ui.css — the 15-token block + all accordion base rules **removed** from §4.8,
    header comment trimmed (0 accordion selectors/tokens now; only the list-groups + `.form-label`
    remain in §4.8). **Why safe:** byte-identical port, single-ownership token blocks pre/post,
    and the late-loading ui.css source-order tie that motivated M5.5's 0,3,0 scope no longer exists
    (ui.css owns nothing accordion). **Build** `app-DspRAT0c.css` (64.27 kB, JS unchanged): 22/22
    tokens + all base selectors present, `.hidden`/`.dropdown-open`/`.btn-close`/`.accordion-open`
    retained. **Parity (Gate 1):** post-change vs pre-change M6.2 real-browser capture — **0
    computed drift** @375/768/1280 over every shared prop × 6 states. **New base props** (harness
    extended) asserted == ui.css-derived expected AND identical in full-load vs deletion-sim:
    button transition/`overflow-anchor:none`/`align-items:center`, `::after`
    `flex-shrink:0`/`background-repeat:no-repeat`/`content:""`, `.accordion-body` padding
    `14px 17.5px 14px 17.5px` (1rem/1.25rem @14px root — ported base still beats the later
    `p-[1rem]` twin exactly as late-loaded ui.css did), `.accordion-header` margin-bottom 0.
    **Deletion sim (Gate 2):** with `ui.css` aborted the accordion renders **0 drift** vs full-load
    (border/chevron/body/corners/typography intact); only sim delta = pre-existing aborted-stylesheet
    console artifact (1 error/viewport, same as pre-change baseline). **Regression:** `git diff
    --check` clean, `view:cache` OK, Pint passed, PHPUnit **301/1419/0** unchanged; M5.2–M5.7 guards
    re-verified PASS. **M6.3.1 COMPLETE. Accordion blocker row CLEARED.** Remaining blocker
    families: forms, buttons base, alerts+dismissible placement, dropdown base, list-group,
    `.table` layout, grid, spacing-3–5/.rounded, text/display/position utilities, modal base.
    **Next: M6.3.2 forms family.**
    **M6.3.2 DONE 2026-09-09 — forms family + ui.css `:root` design-token block relocated to
    app.css (canonical owner).** Two stages: **Stage A** — the full `--ui-*` Batch A `:root`
    token block (29 declarations, value-verbatim, ui.css historical lines 24–131) moved into
    `app.css` as a plain unlayered `:root` rule after `@theme static` (NOT remapped to the
    `--color-*` equivalents); ui.css now declares **zero** `--ui-*` tokens, and every still-ui.css
    family (buttons `--ui-focus-ring`/`--ui-red`, pagination `--ui-navy-light`, alerts…) resolves
    them unchanged from app.css — this also resolves the plan's flagged "fate of the remaining
    60-token :root block". **Stage B** — the whole forms family ported VERBATIM: ui.css §4.2
    (`.form-control` incl. `[type=file]`/:focus/`::placeholder`/:disabled/
    `::file-selector-button`/`.form-control-sm`; `.form-select` incl. `[multiple]`/:disabled/
    `.form-select-sm`; `.form-check`/`.form-check-input` incl. `:active`/:focus/:checked glyphs/
    `:indeterminate`/:disabled; `.input-group` + children + `.input-group-text`) **and** the
    `.form-label` rule (ui.css §4.8) into a new app.css forms section before the M5.6 `.btn-close`
    section; class names unchanged — zero Blade/JS edits. **Why safe:** byte-identical port;
    focus identity (navy + gold ring) traveled with the rules; bundle still beats the Bootstrap CDN
    on specificity ties exactly as ui.css did. **Build** `app-uoIBrx_c.css` (**71.18 kB**, from
    64.27 kB; JS unchanged): every forms selector/state + `.input-group` + relocated tokens in the
    compiled output. **Parity (Gate 1):** new `forms-parity.mjs` fixture on real `/login`
    @375/768/1280 — every measured value equals its ui.css-derived expected literal (`.form-control`
    5.25/10.5px + `#212529`; `.form-select` 31.5px arrow-right + SVG; sm 12.25px/3.5px;
    `::placeholder` `#6c757d`; `::file-selector-button` 5.25/10.5px + border-inline-end 1px;
    **`:focus` navy-light `rgb(22,74,156)` + gold `0 0 0 2.8px rgba(252,209,22,.3)` ring**
    [0.2rem @14px root]; `:disabled` `#e9ecef`; check/radio 14px + 3.5px/50% radius;
    `:checked`/`:indeterminate` navy + glyph SVGs; `.input-group-text` `#e9ecef`; `.input-group
    > .form-control` flex 1 1 auto + min-width 0; `.form-check` 21px; `.form-label` margin-bottom
    7px). **Deletion sim (Gate 2):** `ui.css` aborted — **0 computed-style drift** across the whole
    fixture/state matrix @375/768/1280; only delta = pre-existing aborted-stylesheet console
    artifact (1 error/viewport, same as M6.2/M6.3.1 baselines). **Authoring-bug caught by the build
    gate (recorded):** the forms header comment was initially missing its closing `*/` (swallowed
    `.form-label`→`.form-check` as comment content; isolated via a LightningCSS repro; fixed by
    closing the comment). **Regression:** `git diff --check` clean, `view:cache` OK, Pint passed,
    PHPUnit **301/1419/0** unchanged; M5.2–M5.7 guards re-verified PASS. **M6.3.2 COMPLETE. Forms
    blocker row CLEARED.** Remaining blocker families: buttons base, alerts+dismissible placement,
    dropdown base, list-group, `.table` layout, grid, spacing-3–5/.rounded, text/display/position
    utilities, modal base. **Next: M6.3.3 buttons base family.**
    **M6.3.3 DONE 2026-09-09 — buttons base family migrated to app.css (canonical owner).**
    Scope: THIRD retained-family migration per the §M6.1 dependency order (buttons base). No
    deletion, no unlink, no Blade/JS/PHP/DB change. **Source (two app files):** app.css — the
    whole ui.css §4.1 family ported VERBATIM into a new buttons-base section APPENDED at the very
    end of the file (after the M5.6 `.btn-close` self-contract): `.btn` base (display/padding
    `.375rem .75rem`/font metrics/`1px solid transparent`/radius `.375rem`/transition),
    `.btn:hover` (currentColor edge), `.btn:focus-visible` (outline 0), `.btn.disabled`/
    `.btn:disabled` (pointer-events none/opacity `.65`), `.btn-sm`, `.btn-primary`/
    `.btn-outline-primary` (navy family — `--ui-navy` source, hover `--ui-navy-light`, active
    `--ui-navy-hover`, ring `rgba(0,56,168,.5)`), `.btn-danger`/`.btn-outline-danger` (red
    family — `--ui-red` + darker `#A80F20`/`#900C1B` hover/active stops, ring
    `rgba(206,17,38,.5)`), and `.btn.btn-gold:focus-visible` (glow ring `rgba(220,180,0,.5)`);
    the brand variants (`.btn-gold/.btn-navy/.btn-red/.btn-subtle/.btn-outline-red/.btn-outline`)
    were already app.css-owned (self-contained group). ui.css — §4.1 header + all button rules
    **removed**, replaced by a pointer comment (0 button selectors remain); §4.4 `.btn-group`
    deliberately STAYS ui.css-owned (moves with the dropdown family). **Placement decision
    (cascade-critical): appended AFTER every existing app.css rule** because pre-port the ui.css
    `.btn` base (0,1,0) loaded after this bundle and won every equal-specificity tie (0,1,0)
    against the earlier brand group — `inline-block`, 5.25/10.5px padding, 5.25px radius,
    14px/400/1.5, `#212529`, 1px transparent border, `.15s ease-in-out` — including on
    `btn btn-gold`/`btn btn-navy` combos; appending last reproduces that later-rule-wins outcome
    bit-for-bit (Tailwind Preflight/utilities lose to these unlayered rules exactly as they did to
    ui.css). **Why safe:** byte-identical port + end-placement preserving v1 tie-breaks + tokens
    (`--ui-navy` family/`--ui-red`) now resolving from the M6.3.2 app.css `:root`. **Build**
    `app-QWOXOZW9.css` (**73.95 kB**, from 71.18 kB; JS unchanged): every `.btn*` selector/state
    incl. gold glow present; LightningCSS re-encodes the focus rings to 8-digit hex in compiled
    output (`#0038a880`/`#ce112680`/`#dcb40080`, equal resolved values). **Parity (Gate 1):** new
    `buttons-parity.mjs` — 9-button fixture (base, primary, primary-sm, outline-primary, danger,
    outline-danger, gold, navy, disabled) on real `/login` × base/hover/focus/disabled states
    @375/768/1280 — **0 computed-style drift** current vs deletion-sim AND **25/25 value
    assertions equal the ui.css-derived expected literals** (`.btn` `inline-block` + 5.25/10.5px +
    5.25px radius + `rgb(33,37,41)`; `.btn-primary` `rgb(0,56,168)` → hover `rgb(22,74,156)`;
    `.btn-sm` 12.25px/3.5px; `.btn-danger` `rgb(206,17,38)` → hover `rgb(168,15,32)`;
    outline variants fill navy/red on hover; disabled 0.65/none). **Deletion sim (Gate 2):**
    `ui.css` aborted — **0 computed-style drift** across every button × state @375/768/1280 incl.
    token resolution from the app.css `:root`; only delta = pre-existing aborted-stylesheet
    console artifact (1 error/viewport, same as M6.2/M6.3.1/M6.3.2 baselines). **Regression:**
    `git diff --check` clean, `view:cache` OK, Pint passed, PHPUnit **301/1419/0** unchanged;
    M5.2–M5.7 guards re-verified PASS. **M6.3.3 COMPLETE. Buttons base blocker row CLEARED.**
    Remaining blocker families: alerts+dismissible placement, dropdown base (+ §4.4 `.btn-group`),
    list-group, `.table` layout, grid, spacing-3–5/.rounded, text/display/position utilities,
    modal base. **Next: M6.3.4 alerts + dismissible placement.**
    **M6.3.4 DONE 2026-09-09 — alerts + dismissible placement family migrated to app.css
    (canonical owner).** Scope: FOURTH retained-family migration per the §M6.1 dependency order.
    No deletion, no unlink, no Blade/JS/PHP/DB change. **Source (two app files):** app.css —
    the whole ui.css §4.5 alert family ported VERBATIM into an alerts section APPENDED at the
    very end of the file (after the M6.3.3 buttons base): `.alert` base (relative/`1rem` padding/
    `1rem` margin-bottom/inherit color/transparent bg+border/`.375rem` radius),
    `.alert-dismissible` (`3rem` padding-right), the **`.alert-dismissible .btn-close` placement
    rule** (absolute 0/0, z-index 2, `1.25rem 1rem` padding — the `.alert` PARENT positioning
    contract M5.6 deliberately kept in ui.css), and the Bootstrap 5.3.2 emphasis variants
    `.alert-success/-danger/-warning/-info` (value-faithful: `#0a3622`/`#d1e7dd`/`#a3cfbb`,
    `#58151c`/`#f8d7da`/`#f1aeb5`, `#664d03`/`#fff3cd`/`#ffe69c`, `#055160`/`#cff4fc`/`#9eeaf9`);
    the M5.6 `.btn-close` header comment updated (alerts no longer ui.css-owned; the pre-existing
    `0,3,0` specificity typo there corrected to `0,2,0`). ui.css — §4.5 header + all alert rules
    **removed**, replaced by a pointer comment (0 alert selectors remain). **Why safe:**
    byte-identical port + end-placement preserving v1 tie-breaks; `.alert-dismissible .btn-close`
    (0,2,0) keeps beating the (0,1,0) `.btn-close` self contract for position/padding exactly as
    before — **the `.btn-close` family is now FULLY app.css-owned**. **Build** `app-D9Tw64t-.css`
    (**74.51 kB**, from 73.95 kB; JS unchanged): all seven alert selectors incl. the dismissible
    placement + four variants present; LightningCSS collapses the padding shorthand. **Parity
    (Gate 1):** new `alerts-parity.mjs` — six-alert fixture (base, success, danger, warning,
    info, and a dismissible `.alert-danger` with a `.btn-close` child) on real `/login`
    @375/768/1280 — **0 computed-style drift** current vs deletion-sim AND **29/29 value
    assertions equal the ui.css-derived expected literals** (`.alert` relative + 14px padding +
    14px mb + transparent + 5.25px radius; variants' exact color triples; `.alert-dismissible`
    42px padding-right; close button absolute/0/0/z2/17.5×14px with the M5.6 14×14px self box
    intact). **Deletion sim (Gate 2):** `ui.css` aborted — **0 computed-style drift** across every
    alert × prop @375/768/1280; only delta = pre-existing aborted-stylesheet console artifact
    (1 error/viewport, same as M6.2/M6.3.1–3 baselines). **Regression:** `git diff --check`
    clean, `view:cache` OK, Pint passed, PHPUnit **301/1419/0** unchanged; M5.2–M5.7 guards
    re-verified PASS. **M6.3.4 COMPLETE. Alerts blocker row CLEARED.** Remaining blocker
    families: dropdown base (+ §4.4 `.btn-group`), list-group, `.table` layout, grid,
    spacing-3–5/.rounded, text/display/position utilities, modal base. **Next: M6.3.5 dropdown
    base.**
    **M6.3.5 DONE 2026-09-09 — dropdown base + `.btn-group` family migrated to app.css
    (canonical owner).** Scope: FIFTH retained-family migration per the §M6.1 dependency order.
    No deletion, no unlink, no Blade/JS/PHP/DB change; `.dropdown-open` (M5.4) stays canonical.
    **Why `.btn-group` in scope:** the clients/export + transactions/export dropdowns use
    `.btn-group` as their positioned wrapper (`position:relative; display:inline-flex;
    vertical-align:middle` gives the absolutely-positioned `.dropdown-menu` its containing
    block / flex static-position context) and the §4.4 radius joins reference `.dropdown-toggle` —
    INSEPARABLE, shipped together. **Source (two app files):** app.css — the whole ui.css §4.7
    dropdown family + §4.4 `.btn-group` ported VERBATIM into an M6.3.5 section APPENDED at the
    very end (after M6.3.4 alerts): `.dropdown` (relative), `.dropdown-toggle` (nowrap) +
    `::after` caret + `:empty::after`, `.dropdown-menu` base (absolute/z1000/`display:none`/
    `10rem`/`.5rem 0`/0 margin/`1rem`/`#212529`/`#fff`/padding-box/1px `rgba(0,0,0,.175)`/
    `.375rem`/`0 .5rem 1rem rgba(0,0,0,.15)` shadow), popper offsets
    `.dropdown-menu[data-bs-popper]` (top 100%/left 0/`.125rem` mt) +
    `.dropdown-menu-end[data-bs-popper]` (right 0/left auto), the six `.dropdown-item` rules
    (base/`:hover`+`:focus`/`.active`+`:active`/`.disabled`+`:disabled`), and the `.btn-group`
    base + child flex/z-index + radius-join rules (`.dropdown-toggle`/`.btn-check` exclusions).
    M5.4 comment reworded to name the M6.3.5 section as the `.dropdown-menu` base owner (0,2,0
    open-state still wins its display conflict by specificity). ui.css — §4.4 + §4.7 removed
    (pointer comments; 0 dropdown / 0 `.btn-group` selectors remain); §4.x index paragraph
    updated; M5.7 comment updated (its component top/bottom examples are app.css-owned now).
    **Why safe:** byte-identical port + end-placement preserving v1 tie-breaks; parsed-consumer
    analysis confirmed the three Alpine dropdowns bind `:class="{ 'dropdown-open': open }"` with
    `@click.outside`/`@keydown.escape`/`:aria-expanded` — no `.show`, no `data-bs-popper`
    anywhere; global-search autocomplete is self-contained (inline styles + own display
    toggling), never used this family. **Build** `app-Cc-6A-2z.css` (**76.11 kB**, from 74.51 kB;
    JS unchanged) with `.btn-group` + all dropdown selectors incl. popper offsets + caret;
    braces-balanced (288/288, 234/234); both banner comments closed. **Parity (Gate 1):** new
    `dropdown-parity.mjs` — five-structure fixture (navbar `.dropdown` wrapper; export
    `.btn-group > .btn-subtle.dropdown-toggle + .dropdown-menu.dropdown-menu-end.dropdown-open`
    wrapper; `[data-bs-popper]` + end variants; closed menu) on real `/login` @375/768/1280 —
    **0 computed-style drift** current vs deletion-sim AND **53/53 value assertions equal the
    ui.css-derived expected literals** (`.dropdown` relative; `.btn-group` relative/inline-flex/
    middle; open menu block/absolute/z1000/140px/7px/`rgba(0,0,0,.176)`/5.25px/
    `rgba(0,0,0,.15) 0px 7px 14px 0px`; **closed display none**; popper `top:100%`/left 0/
    1.75px mt + end right 0/left auto; item hover `#f8f9fa`/active `#0d6efd`/disabled
    `#adb5bd`; geometry via menu↔wrapper deltas + self sizes — fixture absolute static-position
    `y` is page-flow dependent and shifts when unrelated retained families are aborted, not a
    family regression). **Deletion sim (Gate 2):** `ui.css` aborted — **0 computed-style drift**
    across every dropdown prop @375/768/1280; only delta = pre-existing aborted-stylesheet
    console artifact (1 error/viewport, same as M6.2–M6.3.4 baselines). **Selector guards:** `rg`
    — 0 dropdown/`.btn-group` selectors in ui.css (comment text only), 0 `.show` reintroduced;
    prior-phase parity re-verified (buttons 25/25, alerts 29/29, accordion 0 drift vs M6.2
    baseline, forms 0 style drift — its diff only flags the M6.3.2-era capture's missing
    console-error counter, the known artifact). **Regression:** `git diff --check` clean,
    `view:cache` OK, Pint passed, PHPUnit **301/1419/0** unchanged; M5.2–M5.7 guards re-verified
    PASS. **M6.3.5 COMPLETE. Dropdown + `.btn-group` blocker rows CLEARED.** Remaining blocker
    families: list-group, `.table` layout, grid, spacing-3–5/.rounded, text/display/position
    utilities, modal base. **Next: M6.3.6 list-group.** → **M6.3.6 DONE 2026-09-09 — list-group family migrated to app.css (canonical owner).** **Why safe:**
    byte-identical verbatim port of ui.css §4.8 (all 12 selectors: `.list-group` flex
    column/padding-left 0/margin-bottom 0/`.375rem` radius; `.list-group-item`
    relative/block/`.5rem 1rem`/`#212529`/no underline/`#fff`/1px `#dee2e6`; `:first-child` +
    `:last-child` corner inheritance; `.list-group-item + .list-group-item` adjacency collapse
    (border-top-width 0); `.disabled,`:disabled`` `#6c757d`/pointer-events none/`#fff`;
    `.list-group-item-action` width 100%/`rgba(33,37,41,.75)`/text-align inherit + `:hover`/`:focus`
    z1/`#000`/`#f8f9fa` + `:active` `#212529`/`#e9ecef`; `.list-group-flush` radius 0 + `> .list-group-item`
    border `0 0 1px` + `> .list-group-item:last-child` border-bottom 0) APPENDED at end of app.css
    (after M6.3.5 dropdown) — source order within the family preserved so every equal-specificity
    border/radius tie resolves exactly as late-loaded ui.css did (e.g. the 0,3,0 flush last-item
    border-bottom winner). **Consumers:** ONE static flush list (`students/update-photo`,
    `bg-transparent px-0` items + `.btn-gold` link — no `.btn` interplay, list items are never
    buttons) + FOUR runtime client-autocomplete ULs (`transactions/create`, `transactions/edit`,
    `scanners/scan`, `scholars/_form`) whose JS `li.classList.add('list-group-item',
    'list-group-item-action')` class-name contract is unchanged — static markup and runtime-created
    items resolve identically; inline `position-absolute` + `width:min(24rem,100%)`/
    `max-height:150px`/`overflow-y:auto`/`z-index:1000` container styles untouched; no tabindex/role
    added (the `:focus` half of `:hover,:focus` stays unreachable live, exactly as v1); `rg` — 0
    `--ui-list-*` tokens exist anywhere. **Source (two app files):** app.css — M6.3.6 list-group
    section APPENDED at end (after M6.3.5) with closed banner comment; ui.css — §4.8 header
    comment + the whole list-group block replaced by a pointer comment (0 list-group selectors
    remain) and §4.x index paragraph updated to the M6.2→M6.3.6 migrated set. **Build**
    `app-Cudm12ge.css` (**77.14 kB**, from 76.11 kB; JS unchanged); braces-balanced (300/300,
    222/222). **Parity (Gate 1):** new `listgroup-parity.mjs` — three-structure fixture (flush list,
    runtime autocomplete `<ul class="list-group position-absolute bg-white border">` with
    `width:min(24rem,100%)`, plain list + `.disabled` item) on real `/login` @375/768/1280 —
    **0 computed-style drift** current vs deletion-sim AND **295/295 value assertions equal the
    ui.css-derived expected literals** (flush radius 0 + `0 0 1px` item borders + last border-bottom
    0; autocomplete 5.25px/`#fff`/1px `#dee2e6`; items relative/block/7px 14px/`#212529`/`#fff`;
    adjacency border-top 0; action `rgba(33,37,41,.75)`/inherit; `:hover`/`:focus`
    `#000`/`#f8f9fa`/z1; `:active` `#212529`/`#e9ecef`; disabled `#6c757d`/pointer-events none; menu
    width 336px=24rem). **Deletion sim (Gate 2):** `ui.css` aborted — **0 computed-style drift**
    across every list-group prop @375/768/1280; only delta = pre-existing aborted-stylesheet
    console artifact (1 error/viewport). **Selector guards:** `rg` — 0 list-group selectors in
    ui.css (comment text only); prior-phase parity re-verified (buttons 25/25, alerts 29/29,
    dropdown 53/53, accordion 0 selector-level drift — its 3 diff lines are the known x-collapse
    fold/sweep subpixel artifact [63.1719px↔63.0156px open-panel + overflow visible↔hidden
    post-transition sweep] reproduced identically in a fresh re-run and string-proven unrelated
    (zero list-group classes in the accordion fixture), forms 0 style drift — its diff only flags
    the cross-mode console-counter artifact (A current 0↔B current 0, A sim 1↔B sim 1 identical)).
    **Regression:** `git diff --check` clean, `view:cache` OK, Pint passed, PHPUnit **301/1419/0**
    unchanged; M5.2–M5.7 guards re-verified PASS. **M6.3.6 COMPLETE. List-group blocker row
    CLEARED.** Remaining blocker families: `.table` layout, grid, spacing-3–5/.rounded,
    text/display/position utilities, modal base. **Next: M6.3.7 `.table` layout.** → **M6.3.7 DONE 2026-09-09 — `.table` layout family migrated to app.css (canonical owner).**
    **Why safe:** byte-identical verbatim port of ui.css §4.6 (all 7 selectors: `.table`
    width 100%/margin-bottom 1rem/vertical-align top/border-collapse collapse/border-color
    `#dee2e6`; `.table > :not(caption) > * > *` cell `.5rem .5rem`/`#000`/`#fff`/
    border-bottom-width 1px; `.table > tbody` vertical-align inherit; `.table > thead`
    vertical-align bottom; `.table-sm > :not(caption) > * > *` cell `.25rem .25rem`;
    `.align-middle` vertical-align middle `!important`; `.table-responsive` overflow-x auto +
    `-webkit-overflow-scrolling: touch`). The element Reboot table rules (ui.css §6 l.513-516)
    are a SEPARATE family — NOT migrated, app.css already has byte-identical twins
    (`@layer base` l.447-450). **Consumer scope (all unchanged, zero Blade/JS edits):**
    `.table-responsive` live at exactly ONE consumer — transactions/index.blade.php:130 (wraps
    the transactions DataTables). `.table` on the plain screens (sessions/online, the four admin
    permission-matrix tables, households/show members table) AND on all 11 DataTables id-tables
    (`class="table table-sm"`/`"table … align-middle"`: clients, households, transactions, scholars
    ×4, audit_logs, update_logs, unpaid, payouts/attendance, scholarship_reports, duplicates,
    administration/users). `.align-middle` in 12 files, ALL live usages on table cells (matrix
    cells, DataTables rows, dashboard dynamic `td.className = 'px-[16px] py-2 align-middle'`);
    datatables.css l.457-458 `.dataTables_wrapper table.dataTable.align-middle` already re-covers
    DataTables cells at higher specificity. Dashboard l.197 plain table is Tailwind-only (no
    `.table`). **DataTables boundary proves the exclusion:** `public/css/datatables.css` untouched
    (SHA256 `B82937375133CABD` identical to the M6.3.6-start baseline; B2 focus seam l.522-525 and
    the whole `.dataTables_wrapper`/`table.dataTable` skin still win via element+class-combined
    specificity and later load order). **Parity (Gate 1):** `table-parity.mjs` — five-structure
    fixture on real /login @375/768/1280: plain `.table` (caption+thead+tbody+tfoot),
    `.table.table-sm`, `.table-responsive > .table` (14-column wide; scroll contract — table
    scrolls inside the wrapper, page never widens), `.dataTables_wrapper > table.table.table-sm
    .align-middle.dataTable` with the REAL `/css/datatables.css` injected last (exact production
    load chain app.css → ui.css → datatables.css), and `.align-middle` cells; **246/246
    assertions equal the 14px-root literals** (table 100% width/14px margin-bottom (=1rem)/
    top/collapse/`#dee2e6`; caption bottom/`#6c757d`/7px; thead bottom, tbody inherit→top; cells
    7px/`#000`/`#fff`/1px; `table-sm` 3.5px; `.align-middle` middle; `.table-responsive`
    overflow-x auto; **datatables.css skin overrides verified still winning identically** —
    `table.dataTable` separate/middle/6px/.25rem/`#212529`). **Deletion sim (Gate 2):** `ui.css`
    aborted — **0 computed-style drift** across the whole table matrix @375/768/1280; only delta =
    the pre-existing aborted-stylesheet console artifact (1 error/viewport). **Selector guards:**
    `rg` — 0 `.table`/`.table-responsive`/`.align-middle` selectors in ui.css (comment text only);
    datatables.css hash unchanged; prior-phase parity re-verified (buttons 25/25, alerts 29/29,
    dropdown 53/53, list-group 295/295, accordion 0 selector-level drift — its 3 diff lines are the
    known x-collapse fold/sweep subpixel artifact, forms 0 style drift — the cross-mode
    console-counter artifact only). **Regression:** `git diff --check` clean, `view:cache` OK, Pint
    passed, PHPUnit **301/1419/0** unchanged; M5.2–M5.7 guards re-verified PASS. **M6.3.7
    COMPLETE. `.table` blocker row CLEARED.** Remaining blocker families: grid, spacing-3–5/.rounded,
    text/display/position utilities, modal base. **Next: M6.3.8 grid.** → **M6.3.8 DONE 2026-09-09 — grid family migrated to app.css (canonical owner).**
    **Why safe:** byte-identical verbatim port of the ui.css grid block (`.row` flex/wrap + `--ui-gutter-x` 1.5rem/`--ui-gutter-y` 0 + negative half-gutter margins; `.row > *` flex-shrink 0/100%/half-gutter child padding/`margin-top: var(--ui-gutter-y)`; the inseparable `.g-0…g-5` gutter-token overrides 0→3rem; the ONLY live breakpoints `@media 576` `.col-sm-4/8` and `@media 768` `.col-md-3/4/6`). The `--ui-gutter-x/y` custom properties are declared ONLY inside this block (no `:root` anywhere) so canonical ownership moved with it. Adjacent `.gap-3/4/5` spacing rules are a SEPARATE family (M6.3.9 — value-exact Bootstrap ladder must keep winning). **Consumer scope (all unchanged, zero Blade/JS edits):** static `.row` — grantee_update/`_self_update_tab` + grantee_update/`self-service` (each `row g-2 mb-3` + 7 plain `row`), unpaid_verifications/`self-service` (`row g-2`), unpaid_verifications/`index` (`row`), payouts/`attendance` (`row`), qr/`viewer` (`row g-2 mb-3`); exactly 4 `row g-2` consumers; columns `col-md-3`×24/`col-md-4`×18/`col-md-6`×8/`col-sm-4`×12/`col-sm-8`×12; `g-1/g-2` on non-row elements inert; `filter-chips-row`/`flex-row` false positives. Dynamic: 2 JS templates emit `<dl class="row">` with `dt.col-sm-4`/`dd.col-sm-8` (payouts/attendance l.277, unpaid_verifications/index l.259) — class contract unchanged, no classList mutation of row/col/g. No nested grids exist; `dl.row` semantics live; no header grid. **Parity (Gate 1):** `grid-parity.mjs` — seven-structure fixture on real /login @375/576/768/1280 (`row g-2 mb-3` + 2×`col-md-6`, plain `row` + 4×`col-md-3`/3×`col-md-4`/2×`col-md-6`, synthetic nested `row`, live-shape `dl.row` with 3× `dt.col-sm-4`/`dd.col-sm-8` pairs, `g-0..g-5` probes) **pre→post drift 0 AND deletion-sim drift 0 at every viewport**, **25/25 locked assertions equal the 14px-root literals** (default row margins -10.5px, `g-2` -3.5px, child padding 10.5px/3.5px, flex `0 0 auto`, col widths 25%/33.3333%/50%, dt 33.3%/dd 66.7%, nested inner ratio 0.25, gutter tokens, cols stack full-width at 375). **Deletion sim (Gate 2):** `ui.css` aborted — **0 computed-style drift** @all 4 viewports; only delta = the pre-existing aborted-stylesheet console artifact (1/viewport). **Selector guards:** `rg` — 0 real grid selectors in ui.css (pointer/index/comment text only); datatables.css untouched this phase (mtime pre-session; full MD5 `2EE627B74B0FD170727506AFD46C6C46`; B2 seam l.522-525 intact); prior-phase parity re-verified (buttons 25/25, alerts 29/29, dropdown 53/53, list-group 295/295, table 246/246, accordion/forms 0 drift). **Regression:** build `app-B2ZahIOJ.css` 78.37 kB (JS unchanged `app-DqsLDVL_.js`), braces-net-0 both files, `git diff --check` clean, `view:cache` OK, Pint passed, PHPUnit **301/1419/0** unchanged; M5.2–M5.7 guards re-verified PASS. **M6.3.8 COMPLETE. Grid blocker row CLEARED.** Remaining blocker families: spacing-3–5/.rounded drift, text/display/position utilities, modal base. **Next: M6.3.9 spacing-3–5/.rounded drift (NOT STARTED).**

Do not redesign behavior. Parity comes before optimization.

---

## Documentation Status

| Document | Status |
|---|---|
| `README.md` | Up to date (P7 + P12 complete; Phase 2 implementation authorized) |
| `ENGINEERING_BLUEPRINT.md` | P0–P7 rows done; P8 §1.12 pending build; Phase 2 inspection recorded |
| `IMPLEMENTATION_LOG.md` | P0–P7 + P12 + UI/UX Batches A–G + Phase 2 inspection + confirmation + Phase 2C FilterChips (+ test suite + positioning remediation) + **Phase 2D Responsive Polish** + **Phase 2E final verification** + **2026-08-29 P8 pre-flight + rehearsal** + **2026-08-30 Pre-P8 IA consolidation** + **2026-08-31 Clients modal-first** + **2026-08-31 Clients UX refinement** + **2026-08-31 Clients UI/UX Phase A→B** + **2026-08-31 Clients final verification + Pint cleanup** + **2026-09-02 CSS token cleanup** + **2026-09-02 Clients details-panel UI/UX restructure** + **2026-09-02 Clients details-panel follow-up (action bar + scrolling)** + **2026-09-02 Clients UX refinement (header category line, filter clears, barangay fix, photo-in-Edit-modal)** + **2026-09-02 Clients UX follow-up correction + verification (edit-modal duplicate root-cause, header photo-above-name, validation-feedback modal, button rules, filter clear syncing)** + **2026-09-02 Clients UX correction pass (photo root-cause `currentPhoto()`, sticky profile header, action-bar single row)** + **2026-09-03 Phase 8 client form modal → Alpine** + **2026-09-04 Phase 9 client feedback modal + toast → Alpine / pure JS** + **2026-09-04 Phase 10 client photo modal → Alpine** + **2026-09-04 Phase 11 (discovery only — Bootstrap interactive dependency inventory)** + **2026-09-04 Phase 12 admin/users password reset modal → Alpine** + **2026-09-04 Phase 13 scanner message modal → Alpine** + **2026-09-04 Phase 14 Bootstrap Alert dismissals → Alpine** + **2026-09-04 Phase 15 Scholars Client ID Prompt Modal → Alpine** + **2026-09-04 Phase 16 Audit Logs Leaderboard Modal → Alpine** + **2026-09-05 Phase 17 Unpaid Verification Dynamic Confirmation Modal → Alpine** + **2026-09-05 Phase 18 Students Photo Modal → Alpine** + **2026-09-05 Phase 19 Shared Layout Flash Toast → Alpine** + **2026-09-06 Phase 20 Transactions + Households success toasts → Alpine** + **2026-09-06 Phase 21 Bootstrap JS inventory + navbar hamburger Alpine scope fix** + **2026-09-06 Phase 22 Bootstrap Toast init loop removal** + **2026-09-06 Phase 23 Bootstrap removal-gate audit (audit only)** + **2026-09-06 Phase 24 Bootstrap JS bundle removal** + **2026-09-06 Phase 25 DataTables skin CSS ownership (CDN bootstrap5 skin → project `css/datatables.css`, 11 screens)** + **2026-09-07 Phase 26 Core Bootstrap CSS component ownership (ui.css §4, `--bs-*` 49 → 0, 48/48 Chromium checks + zero console errors)** + **2026-09-08 M5.2 entry (DataTables B2 focus seam + phantom w-100 eliminated)** + **2026-09-08 M5.3 entry (`.d-none` behavioral migration — 72 refs → Tailwind `hidden`, ui.css rule removed, parity harness 0 drift)** + **2026-09-08 M5.4 entry (dropdown `.show` → canonical `.dropdown-open`, ui.css state rule removed, toast `.show` untouched, real-Alpine parity 0 drift)** + **2026-09-08 M5.5 entry (accordion `.collapsed` → canonical `.accordion-open`, ui.css state rules removed, `@alpinejs/collapse` parity 0 drift)** + **2026-09-08 M5.6 entry (`.btn-close` ownership & behavioral migration — self contract → app.css plain rules, values byte-identical; class retained, 12 static + 2 JS-created consumers untouched; `.btn-close-white` companion migrated; `.alert-dismissible` parent-positioning stays with alerts; Playwright parity harness incl. dynamic toast create/remove/recreate 0 drift @375/768/1280)** + **2026-09-08 M5.7 (final utility/grid/text re-scan: 11 value-exact cascade-isolated rules migrated to existing Tailwind twins — `.gap-1/2`, `.flex-wrap`, `.top-0/.bottom-0`, `.overflow-hidden/-visible/-x-auto/-y-auto`, `.ms-auto`, `.mx-auto`; zero blade/JS edits — everything else retained with forensic value/layer reasons incl. spacing 3–5, `.rounded`, `.border`, text colors, `.align-middle`, `.bg-transparent`, `d-*`/`position-*`/`fw-*`, grid, tables, DEAD set classified for M6)** + **2026-09-09 M6.1 entry (deletion-readiness gate audit — AUDIT ONLY, verdict NOT SAFE/deletion BLOCKED: 333 rules/94 live/191 dead inventory, build-ownership grep, Chromium simulated deletion @375/768/1280 on 4 public pages + auth-shell parity harness — 0 errors/overflow, measured drift on every retained family incl. grid collapse and accordion 7-token `--ui-*` source dependency, M5.2–M5.7 guards PASS, M6.2 roadmap defined; no app changes)** + **2026-09-09 M6.2 entry (accordion token ownership resolution — the 7 `--ui-accordion-*` tokens consumed by the M5.5 open-state relocated VERBATIM into an app.css `.accordion` scoped block [same names/values/scope], ui.css §4.8 declares none of them; scope-correction note [`.accordion` scoped, not `:root`]; real-Alpine+`@alpinejs/collapse` parity via built bundle 0 drift @375/768/1280 incl. keyboard Enter/Space/Enter; build `app-D8NYbDco.css` 61.57 kB with tokens + `.hidden`/`.dropdown-open`/`.btn-close`; simulated ui.css deletion — open/closed STATE survives identically, base formation still ui.css (recorded remaining blocker, scope); `git diff --check` clean, `view:cache` OK, Pint pass, suite 301/1419/0; M5.2–M5.7 guards re-verified PASS; M6.3 NOT started)** + **2026-09-09 M6.3.1 entry (accordion base+chevron family migrated — full 22 `--ui-accordion-*` token family + §4.8 base formation ported VERBATIM to app.css, ui.css returns 0 accordion selectors/tokens; parity vs pre-change M6.2 capture 0 drift @375/768/1280; new base props asserted equal to ui.css-derived values; deletion-sim 0 drift for the accordion family, only the pre-existing aborted-stylesheet artifact; build `app-DspRAT0c.css` 64.27 kB; `git diff --check` clean, `view:cache` OK, Pint pass, suite 301/1419/0; M5 guards re-verified; accordion blocker row CLEARED; M6.3.2 forms next)** + **2026-09-09 M6.3.2 entry (forms family + ui.css `:root` design-token block relocated — full `--ui-*` Batch A `:root` block moved VERBATIM to app.css as canonical owner [ui.css declares 0 tokens, resolving the plan's flagged :root-block fate], whole forms family `form-label`/`form-control`+states/`form-select`+states/`form-check-input`+checked/indeterminate/`input-group`+children ported VERBATIM; forms parity fixture @375/768/1280 equals every ui.css-derived expected value incl. navy border + gold 2.8px focus ring; deletion-sim 0 drift across the form matrix, only the pre-existing aborted-stylesheet artifact; authoring-bug caught by build gate [unclosed header comment]; build `app-uoIBrx_c.css` 71.18 kB; `git diff --check` clean, `view:cache` OK, Pint pass, suite 301/1419/0; M5 guards re-verified; forms blocker row CLEARED; M6.3.3 buttons base next)** + **2026-09-09 M6.3.3 entry (buttons base family migrated — ui.css §4.1 `.btn` base + hover/focus/disabled + `.btn-sm` + `.btn-primary/-outline-primary/-danger/-outline-danger` + `.btn.btn-gold` focus glow ported VERBATIM and APPENDED at end of app.css so every same-specificity tie with the earlier app.css brand group resolves exactly as late-loaded ui.css did [inline-block/5.25px/5.25px radius/14px/400/1.5/#212529/.15s ease-in-out]; buttons parity fixture @375/768/1280 [9 buttons × base/hover/focus/disabled] 25/25 assertions equal ui.css-derived literals; deletion-sim 0 drift across the whole button matrix incl. token resolution from the app.css `:root`, only the pre-existing aborted-stylesheet artifact; build `app-QWOXOZW9.css` 73.95 kB; `git diff --check` clean, `view:cache` OK, Pint pass, suite 301/1419/0; M5 guards re-verified; buttons base blocker row CLEARED; M6.3.4 alerts + dismissible placement next)** + **2026-09-09 M6.3.4 entry (alerts + dismissible placement family migrated — ui.css §4.5 `.alert` base + `.alert-dismissible` + the `.alert-dismissible .btn-close` placement rule [parent contract M5.6 kept with alerts] + `.alert-success/-danger/-warning/-info` Bootstrap 5.3.2 emphasis variants ported VERBATIM to end-of-file app.css — `.btn-close` now FULLY app.css-owned [M6.3.4 + M5.6]; alerts parity fixture @375/768/1280 [6 alerts incl. dismissible child] 29/29 assertions equal ui.css-derived literals; deletion-sim 0 drift across the alert matrix, only the pre-existing aborted-stylesheet artifact; build `app-D9Tw64t-.css` 74.51 kB; `git diff --check` clean, `view:cache` OK, Pint pass, suite 301/1419/0; M5 guards re-verified; alerts blocker row CLEARED; M6.3.5 dropdown base next)** + **2026-09-09 M6.3.5 entry (dropdown base + `.btn-group` family migrated — ui.css §4.7 `.dropdown`/`.dropdown-toggle`+`::after` caret/`.dropdown-menu` base + `[data-bs-popper]` offsets/`.dropdown-item` states ported VERBATIM to end-of-file app.css together with the inseparable §4.4 `.btn-group` [the two export dropdowns' positioned wrapper — `position:relative; inline-flex` gives the abspos menu its containing block; navbar uses `.dropdown`; radius joins reference `.dropdown-toggle`]; all three Alpine menus still bind canonical `.dropdown-open` [M5.4], no `.show` reintroduced, no `data-bs-popper` in live consumers, global-search self-contained; dropdown parity fixture [5 structures @375/768/1280] 53/53 assertions equal ui.css-derived literals incl. `.btn-group` relative/inline-flex/middle, open menu block/absolute/z1000/140px/7px/`rgba(0,0,0,.176)`/5.25px/shadow, **closed display none**, popper top 100%/left 0/1.75px + end right 0/left auto, item hover `#f8f9fa`/active `#0d6efd`/disabled `#adb5bd`; deletion-sim 0 drift across the whole dropdown matrix [geometry = menu↔wrapper deltas + self sizes], only the pre-existing aborted-stylesheet artifact; build `app-Cc-6A-2z.css` 76.11 kB; `git diff --check` clean, `view:cache` OK, Pint pass, suite 301/1419/0; M5 guards re-verified; prior-phase parity re-run buttons 25/25 + alerts 29/29 + accordion 0 drift + forms 0 style drift; dropdown + `.btn-group` blocker rows CLEARED; M6.3.6 list-group next)** + **2026-09-09 M6.3.6 entry (list-group family migrated — ui.css §4.8 all 12 selectors ported VERBATIM to end-of-file app.css; consumers ONE static flush list [students/update-photo] + FOUR runtime client-autocomplete ULs [transactions create+edit, scanners/scan, scholars/_form] whose JS classList class contract is unchanged; no `.btn` interplay, no `--ui-list-*` tokens; list-group parity fixture @375/768/1280 295/295 assertions equal ui.css-derived literals; deletion-sim 0 drift across the list-group matrix, only the pre-existing aborted-stylesheet artifact; build `app-Cudm12ge.css` 77.14 kB; `git diff --check` clean, `view:cache` OK, Pint pass, suite 301/1419/0; M5 guards re-verified; prior-phase parity re-run buttons 25/25 + alerts 29/29 + dropdown 53/53 + accordion 0 selector-level drift [fold/sweep artifact bytes only] + forms 0 style drift; list-group blocker row CLEARED; M6.3.7 `.table` layout next)** + **2026-09-09 M6.3.7 entry (`.table` layout family migrated — ui.css §4.6 `.table`/`.table > :not(caption) > * > *`/`.table > tbody`/`.table > thead`/`.table-sm > :not(caption) > * > *`/`.align-middle`/`.table-responsive` — all 7 selectors ported VERBATIM to end-of-file app.css; consumers: `.table-responsive` only at transactions/index:130, `.table` on the plain screens [sessions/online, 4 admin permission-matrix tables, households/show members] AND all 11 DataTables id-tables, `.align-middle` in 12 files all on table cells — datatables.css l.457-458 scoped rule re-covers DataTables cells; the element Reboot table rules [ui.css §6 l.513-516] NOT migrated — separate family whose byte-identical twins already exist in app.css `@layer base`; datatables.css UNTOUCHED [hash B82937375133CABD unchanged, B2 seam intact]; table parity fixture [plain + `table-sm` + `.table-responsive` wide + a DataTables-style wrapper with the REAL /css/datatables.css injected in exact load order + `.align-middle` cells @375/768/1280] 246/246 assertions equal the 14px-root literals; deletion-sim 0 drift across the table matrix, only the pre-existing aborted-stylesheet artifact; build `app-4w0-fGky.css` 77.53 kB; `git diff --check` clean, `view:cache` OK, Pint pass, suite 301/1419/0; M5.2–M5.7 guards re-verified; prior-phase parity re-run buttons 25/25 + alerts 29/29 + dropdown 53/53 + list-group 295/295 + accordion 0 selector-level drift [fold/sweep artifact bytes only] + forms 0 style drift; `.table` blocker row CLEARED; M6.3.8 grid next)** entries recorded + **2026-09-09 M6.3.8 grid family migration** + **2026-09-10 M6.4 final dependency re-audit entry (audit-only; NOT READY — live `.data-card` drift on all public pages + §5 drift [status-badge dot, metric-card border/radius/padding/hover, data-card slots, ui-empty, skip-link focus]; value-identical families confirmed; deletion gate remains BLOCKED, deletion NOT STARTED)** |
| `TAILWIND_MIGRATION_EXECUTION_PLAN.md` | §O updated for Phase 21 + Phase 22 + **Phase 23** + **Phase 24** + **Phase 25** + **Phase 26** + **Phase 27** (status line, completed list, Phase 21 + Phase 22 + **Phase 23 + Phase 24 + Phase 25 + Phase 26 + Phase 27 paragraphs**; Phase 27 = Final Bootstrap CSS removal — all 8 CDN links removed, ui.css §4.8–4.10 ownership incl. Reboot/box-sizing parity, gate **READY / dependency 8 → 0**; §O next-pending rewritten to finalization); Phase 20 paragraphs/§D.3 tags already present; **§J updated 2026-09-07** with the ui.css removal-audit findings → `UI_CSS_RETIREMENT_PLAN.md` |
| `UI_CSS_RETIREMENT_PLAN.md` | **NEW 2026-09-07** — ui.css removal audit evidence + six-step retirement roadmap (M1 tokens→`@theme`, M2 Reboot/type port or §I Preflight decision, M3 a11y port, M4 Bootstrap-parity completion, M5 JS-contract trace, M6 deletion gate); verdict **NOT SAFE — MIGRATION REMAINING**; **M1 + M2 + M3 LANDED 2026-09-07 (gates PASS, Option B)**, **M4.1 LANDED 2026-09-07 (utility drop)**, **M4.2 LANDED 2026-09-07 (forms family — form-label→mb-2 x46, controls retained with justification)**, **M4.3 LANDED 2026-09-08 (layout/grid family — row/col-/g- RETAINED ALL: gutter-compensation model, valid retain-per-§19, zero changes)**, **M4.4 LANDED 2026-09-08 (buttons family — btn/btn-sm/btn-primary/btn-danger/btn-outline-primary + btn-gold/navy/red/subtle/outline(-red) RETAINED ALL: ui.css .btn wins the metric cascade on every composite (probed live), removal = visible redesign, zero changes)**, **M4.5 LANDED 2026-09-08 (alerts family — alert/alert-dismissible/-success/-danger/-warning RETAINED ALL: exact Bootstrap 5.3 emphasis palette, .alert = live JS selector contract on the QR-error path, no app.css alert twin, zero changes)**, **M4.6 LANDED 2026-09-08 (remaining families — tables/dropdowns/btn-close/accordion/list-group/d-none/utilities RETAINED ALL: no canonical twins, JS/Alpine state contracts (.show/.collapsed/.d-none/list-group-item), DataTables integration seam, §10 byte-stack prohibition, zero changes; M4 COMPLETE)**, **M5.1 LANDED 2026-09-08 (AUDIT ONLY — behavioral-contract & dependency audit: .show = Alpine state class on 3 dropdowns vs app-owned toast marker, .collapsed single accordion, .d-none 74+hooks, .btn-close 15 incl. 2 JS-created, list-group classList x4, datatables.css B2 focus seam Category 2, bundle already emits align-middle/ms-1/mt-4/text-center + phantom w-100 from the _form blade comment; M5.2+ sub-roadmap defined; verdict still NOT SAFE)**, **M5.2 LANDED 2026-09-08 (DataTables B2 focus seam now OWNED by datatables.css — outline:0 + box-shadow:var(--shadow-focus) scoped, non-DT controls still ui.css, focused border stays #dee2e6; phantom w-100 eliminated at source — literal token in scholars/_form.blade.php comment reworded, bundle w-100 = 0, real utilities intact; app.css + ui.css byte-identical; Playwright computed-style parity 0 drift @375/768/1280; build app-DjX_TTSN.css; suite 301/1419/0; M5.1's app.css-comment attribution corrected)**, **M5.3 LANDED 2026-09-08 (`.d-none` behavioral migration — 72 live refs [17 static + 55 runtime classList/jQuery incl. two-arg toggleClass] → native Tailwind `hidden` across 9 views; ui.css `.d-none` rule REMOVED [hash ABB10A4B→8D6682]; no `@utility` bridge — cascade-equivalent proven, bundle already emitted `.hidden`, build byte-identical; Playwright lifecycle harness 0 drift/no overflow @375/768/1280; Pint + suite 301/1419/0; `.d-none` consumers now 0, only 2 inert comments remain)**, **M5.4 LANDED 2026-09-08 (dropdown `.show` behavioral migration — 3 Alpine dropdowns bind canonical app-owned `.dropdown-open` [app.css `.dropdown-menu.dropdown-open{display:block}`], ui.css `.dropdown-menu.show` rule REMOVED, no generic `.show`; `x-show`/plain utilities rejected on cascade grounds; toast `.show` ops untouched; real-Alpine lifecycle harness 0 drift/no overflow @375/768/1280; build app-kffPtn-M.css; Pint + suite 301/1419/0; repo `.show`: 0 CSS rules, toast ops only)**, **M5.5 LANDED 2026-09-08 (accordion `.collapsed` behavioral migration — single live accordion `clients/_gip` binds canonical `.accordion-open` [app.css `.accordion .accordion-button.accordion-open` family, scoped 0,3,0 to keep beating the later-loading ui.css `:focus` tie; open chevron `::after`; `:not(.accordion-open)` closed corner], ui.css `.collapsed`/`:not(.collapsed)` state rules REMOVED, base tokens + `.accordion*` formation retained; `x-show`+`x-collapse` visibility untouched, no state/visibility conflation; real-Alpine+`@alpinejs/collapse` lifecycle parity 0 drift/no overflow @375/768/1280; build app-DjDvJDSm.css; Pint + suite 301/1419/0; repo `.collapsed`: 0 live consumers, 0 selectors)**, **M5.6 LANDED 2026-09-08 (`.btn-close` ownership & behavioral migration — `.btn-close`/`.btn-close-white` SELF contract moved from ui.css §4.3 to `app.css` as plain unlayered rules [box content-box 1em×1em/.25em pad, SVG custom-data-URI chevron, hover/focus ring/disabled, white filter] byte-identical; class literal RETAINED — no JS selector on it, JS-created toasts wire via `button[aria-label="Close"]`, DetailsPanel via `#detailsClose`; so 12 static + 2 JS-created consumers untouched; `.alert-dismissible .btn-close` child-positioning stays in ui.css with alerts; Playwright parity harness [six contexts + JS create/remove/recreate + hover/focus/click/Enter/Space] 0 drift/no overflow @375/768/1280; build app-BZH684Eh.css [+779 B]; Pint + suite 301/1419/0; repo `.btn-close` CSS ownership: 0 rules in ui.css, only alert-family positioning selector remains)**, **M5.7 LANDED 2026-09-08 (final utility/grid/text re-scan — 11 value-exact cascade-isolated rules migrated to existing Tailwind twins: `.gap-1/2`, `.flex-wrap`, `.top-0/.bottom-0`, `.overflow-hidden/-visible/-x-auto/-y-auto`, `.ms-auto`, `.mx-auto` [zero blade/JS edits]; all others RETAINED with forensic value/specificity reasons incl. spacing 3–5, `.rounded`, `.border`, Bootstrap text colors, `.align-middle` cell-centering mechanism, `.bg-transparent` list-group flip, `d-*`/`position-*` renames, grid `.row/.col-*/.g-*`, `.table*` + datatables.css seam, DEAD set `mt-auto`/`.me-auto`/`.flex-nowrap`/… classified for M6; Playwright parity 0 drift/no overflow @375/768/1280 incl. poisoned-interceptor proof; build byte-identical `app-BZH684Eh.css`; ui.css `B593DDF8…`; suite 301/1419/0)**, **M6.1 LANDED 2026-09-09 (deletion-readiness gate AUDIT — verdict NOT SAFE, deletion BLOCKED: 333 rules / 285 simple classes, 94 live / 191 dead consumers, app.css/build ownership grep [only M5.x ported set + matching twins emitted], Chromium simulated deletion [abort of /css/ui.css] @375/768/1280 × 4 public pages + auth-shell parity harness — 0 console/page errors, 0 overflow, `:focus-visible` seam identical, measured drift on every retained family incl. grid collapse, form-width collapse, UA-button regression, alert/dropdown/accordion/list-group/table contract losses, and new token-source blocker [app.css `.accordion-open` uses 7 `--ui-accordion-*` tokens that live only in ui.css `:root`]; M5.2–M5.7 guards PASS; `.page-link` family DEAD non-DT/covered by datatables.css; alpine-phase0 PASS / smoke.spec signIn-only env gap; PHPUnit 301/1419/0 unchanged; M6.2 roadmap defined — port accordion tokens→@theme, port/rewrite retained families, resolve spacing-3–5 drift, delete DEAD set, re-run gate with auth shell)**, **M6.2 LANDED 2026-09-09 (accordion token ownership resolved per scope — the 7 `--ui-accordion-*` tokens moved VERBATIM to an app.css `.accordion` scoped block [component-specific, so NOT `@theme static`; same names/values/scope, no `!important`, order unchanged; corrected the M6.1 `:root` wording → `.accordion` scoped], ui.css declares none of them [15 other accordion tokens retained]; real-Alpine+`@alpinejs/collapse` parity via the actual built JS on /login 0 computed-style drift @375/768/1280 closed→open→closed + Enter→Space→Enter; deletion sim — open/closed STATE survives ui.css abort identically [color/bg/inset/active chevron/rotation/corner/panel], accordion BASE formation remains ui.css §4.8 [recorded blocker, not migrated per scope]; build app-D8NYbDco.css [61.57 kB] with 7 canonical tokens + M5.5 rules + `.hidden`/`.dropdown-open`/`.btn-close`; `git diff --check` clean, `view:cache` OK, Pint pass, suite 301/1419/0; M5 guards re-verified)**, **M6.3.1 LANDED 2026-09-09 (accordion base+chevron family migrated per dependency order — the `.accordion` token block completed to the full 22-`--ui-accordion-*` family [the 15 remaining tokens joined the M6.2 seven VERBATIM] and the entire §4.8 base formation [`.accordion-button`/:focus/`::after` chevron/`.accordion-header`/`.accordion-item` corners/`.accordion-collapse`/`.accordion-body`] ported VERBATIM to app.css; ui.css §4.8 accordion sub-block REMOVED [0 accordion selectors/tokens remain, only list-groups + `.form-label`]; cascade safe [byte-identical port, single token ownership, late-loading ui.css tie for M5.5's 0,3,0 scope no longer exists]; parity vs pre-change M6.2 real-browser capture 0 drift @375/768/1280 over shared props; new base props [transition/overflow-anchor/align-items/flex-shrink/background-repeat/content/body padding `14px 17.5px`/header margin] asserted equal to ui.css-derived values and identical full-load vs deletion-sim; deletion-sim 0 drift for the accordion family [border/chevron/body/corners/typography intact] — accordion blocker row CLEARED; only sim delta = pre-existing aborted-stylesheet artifact; build app-DspRAT0c.css [64.27 kB]; `git diff --check` clean, `view:cache` OK, Pint pass, suite 301/1419/0; M5 guards re-verified)**, **M6.3.2 LANDED 2026-09-09 (forms family + `:root` design-token block moved per dependency order — the full `--ui-*` Batch A `:root` block relocated VERBATIM to app.css as canonical owner [plain unlayered `:root` rule after `@theme static`; NOT remapped to `--color-*`; ui.css declares 0 tokens; resolves the plan's flagged :root-block fate] and the entire forms family [`.form-label`, `.form-control`+`[type=file]`/:focus/`::placeholder`/:disabled/`::file-selector-button`/`.form-control-sm`, `.form-select`+`:focus`/`[multiple]`/:disabled/`.form-select-sm`, `.form-check`/`.form-check-input`+:active/:focus/:checked glyphs/`:indeterminate`/:disabled, `.input-group`+children+`.input-group-text`] ported VERBATIM; class names unchanged, zero blade/JS edits; parity fixture @375/768/1280 equals every ui.css-derived expected value incl. navy border + gold 2.8px ring focus identity; deletion-sim 0 drift across the whole form matrix — forms blocker row CLEARED; only sim delta = pre-existing aborted-stylesheet artifact; authoring-bug caught by build gate [unclosed header comment isolated via LightningCSS repro]; build app-uoIBrx_c.css [71.18 kB]; `git diff --check` clean, `view:cache` OK, Pint pass, suite 301/1419/0; M5 guards re-verified)**, **M6.3.3 LANDED 2026-09-09 (buttons base family migrated per dependency order — the entire ui.css §4.1 family [`.btn` base ∪ `:hover`/`:focus-visible`/`.disabled`/`:disabled` ∪ `.btn-sm` ∪ `.btn-primary`/`.btn-outline-primary` navy ∪ `.btn-danger`/`.btn-outline-danger` red ∪ `.btn.btn-gold:focus-visible` glow] ported VERBATIM, APPENDED at end of app.css after the M5.6 `.btn-close` self-contract so every equal-specificity tie (0,1,0) with the earlier app.css brand group resolves exactly as late-loaded ui.css did — inline-block/5.25·10.5px padding/5.25px radius/14px·400·1.5/#212529/1px transparent/.15s ease-in-out incl. `btn btn-gold`/`btn btn-navy` combos; ui.css §4.1 REMOVED [0 button selectors remain]; §4.4 `.btn-group` deliberately STAYS ui.css-owned [moves with dropdown]; brand variants `.btn-gold/-navy/-red/-subtle/-outline-red/-outline` were already app.css-owned; buttons parity fixture [9 buttons × base/hover/focus/disabled @375/768/1280] 25/25 assertions equal ui.css-derived literals [`.btn-primary` rgb(0,56,168)→hover rgb(22,74,156); `.btn-danger` rgb(206,17,38)→hover rgb(168,15,32); `.btn-sm` 12.25/3.5px; disabled 0.65/none]; deletion-sim 0 computed-style drift across the whole button matrix incl. token resolution from the app.css `:root` — buttons base blocker row CLEARED; only sim delta = pre-existing aborted-stylesheet artifact; LightningCSS re-encodes focus rings to 8-hex in compiled output [#0038a880/#ce112680/#dcb40080]; build app-QWOXOZW9.css [73.95 kB]; `git diff --check` clean, `view:cache` OK, Pint pass, suite 301/1419/0; M5 guards re-verified)**, **M6.3.4 LANDED 2026-09-09 (alerts + dismissible placement migrated per dependency order — ui.css §4.5 `.alert` base [relative/1rem padding/1rem mb/transparent/.375rem radius] + `.alert-dismissible` [3rem pr] + the `.alert-dismissible .btn-close` PLACEMENT rule [absolute 0/0, z2, 1.25rem/1rem — the parent contract M5.6 kept with alerts] + `.alert-success/-danger/-warning/-info` Bootstrap 5.3.2 emphasis variants ported VERBATIM, appended at end of app.css; `.btn-close` family now FULLY app.css-owned [M5.6 self + M6.3.4 placement]; ui.css §4.5 REMOVED [0 alert selectors remain]; M5.6 `.btn-close` header comment updated [0,3,0 typo → 0,2,0]; alerts parity fixture [6 alerts incl. dismissible child × @375/768/1280] 29/29 assertions equal ui.css-derived literals [close button absolute/0/0/z2/17.5×14px with M5.6 14×14px self box intact]; deletion-sim 0 computed-style drift across the alert matrix — alerts blocker row CLEARED; only sim delta = pre-existing aborted-stylesheet artifact; build app-D9Tw64t-.css [74.51 kB]; `git diff --check` clean, `view:cache` OK, Pint pass, suite 301/1419/0; M5 guards re-verified)** + **2026-09-09 M6.3.5 entry (dropdown base + `.btn-group` family LANDED — §4.7 `.dropdown`/`.dropdown-toggle`+`::after` caret/`.dropdown-menu` base + popper offsets/`.dropdown-item` states ported VERBATIM to end-of-file app.css together with §4.4 `.btn-group` [inseparable, the export dropdowns' positioned wrapper; navbar uses `.dropdown`]; `.dropdown-open` [M5.4] stays canonical, no `.show`, no `data-bs-popper` in live consumers, global-search self-contained; 53/53 parity assertions @375/768/1280 equal ui.css-derived literals; deletion-sim 0 drift across the dropdown matrix; build `app-Cc-6A-2z.css` 76.11 kB; `git diff --check` clean, `view:cache` OK, Pint pass, suite 301/1419/0; dropdown + `.btn-group` blocker rows CLEARED)** + **2026-09-09 M6.3.6 entry (list-group family LANDED — §4.8 all 12 selectors [`.list-group`/`.list-group-item`+`:first-child`/`:last-child` corner inheritance + adjacency collapse/`.disabled,`:disabled``/`.list-group-item-action`+`:hover`/`:focus`/`:active`/`.list-group-flush`+`> .list-group-item`+`> .list-group-item:last-child`] ported VERBATIM to end-of-file app.css so equal-specificity border/radius ties resolve exactly as late-loaded ui.css did; consumers — ONE static flush list [students/update-photo] + FOUR runtime client-autocomplete ULs [transactions create+edit, scanners/scan, scholars/_form] whose JS classList class contract is unchanged; no `.btn` interplay, no `--ui-list-*` tokens; 295/295 parity assertions @375/768/1280 equal ui.css-derived literals; deletion-sim 0 drift across the list-group matrix; build `app-Cudm12ge.css` 77.14 kB; `git diff --check` clean, `view:cache` OK, Pint pass, suite 301/1419/0; list-group blocker row CLEARED)**, **M6.3.7 entry (`.table` layout family LANDED — §4.6 `.table`/`.table > :not(caption) > * > *`/`.table > tbody`/`.table > thead`/`.table-sm`/`.align-middle`/`.table-responsive` — all 7 selectors ported VERBATIM to end-of-file app.css; consumers — `.table-responsive` only at transactions/index:130, `.table` on the plain screens + ALL 11 DataTables id-tables, `.align-middle` all on cells [datatables.css l.457-458 scoped rule re-covers DataTables]; element Reboot table rules NOT migrated [separate family, app.css twins exist]; datatables.css UNTOUCHED [hash B82937375133CABD, B2 seam intact]; 246/246 parity assertions incl. a real-datatables.css-injected DataTables wrapper @375/768/1280 equal the 14px-root literals; deletion-sim 0 drift across the table matrix; build `app-4w0-fGky.css` 77.53 kB; `git diff --check` clean, `view:cache` OK, Pint pass, suite 301/1419/0; `.table` blocker row CLEARED)** — M6 (deletion) execution still pending; gate remains BLOCKED by the remaining families (grid, utilities, spacing-3–5/.rounded, modal) + **M6.3.8 LANDED 2026-09-09 (grid family — all 14 selectors incl. the two @media breakpoints and the `.g-0…g-5` gutter tokens ported VERBATIM to end-of-file app.css; 4 `row g-2` + 6 plain `row` static consumers + 2 JS `dl.row` templates dynamic, no nested grids; parity @375/576/768/1280 pre→post AND deletion-sim 0 drift, 25/25 locked literals; grid blocker row CLEARED)**, **M6.4 LANDED 2026-09-10 (final dependency re-audit, AUDIT-ONLY — `m64-audit.mjs` Chromium deletion-sim [FULL vs ui.css aborted] on 6 public pages × 4 viewports + §5/§2/§3/§4.10 fixture on /login; PROVEN drift: live `.data-card` border 1→0/shadow-set/−2px on all public heads, `.status-badge` dot 6→5.25px, `.metric-card` border/radius 12→16/padding 20→17.5/hover-lift loss, `.data-card-header/-body/-footer` padding & gap shrink, `.ui-empty` padding [dead], `.ui-skip-link` focus `left` 0→14px; value-identical `.ui-notice`/`.ui-micro-label`/`.metric-value`/Reboot/type/focus-visible/reduced-motion/real page-link; 0 page errors/overflow, bootstrap undefined, 200 all cells; verdict NOT READY — deletion gate REMAINS BLOCKED, deletion NOT STARTED; build green, view:cache OK, Pint pass, PHPUnit 301/1419/0)** |
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
## M6.11 - `.ui-micro-label` family (single base rule) ownership migrated to app.css - COMPLETE (2026-09-12)

- **Gate-1 (A==B==C byte-identical):** snapshots byte-identical across 4 viewports x 5 rendering surfaces x 5-probe
  fixture set; `.ui-micro-label` computed value (10.08px / 600 / .6048px / uppercase / `#70798B`) FULL==SIM
  value-identical, 0 console/page errors, 0 overflow.
- **Gate-2 (deletion-sim parity):** deletion-sim re-run report byte-identical to M6.10-FINAL (24/24 cells, 0 deltas);
  m64 report 49134B md5 `216830713CE9A8E4A535F5E51CB8CA38` == m68 baseline.
- **Regression:** PHPUnit 301/1419/0; Pint --test --dirty passed; view:cache clean; build `app-WdxWzFTe.css`
  82.67 kB; 0 console errors; 24/24 status 200; reduced-motion + mcHover regression-free.
- **Ownership census:** `.ui-micro-label` - ui.css 0 live rules (pointer only), app.css 2 (M6.3 twin + M6.11 EOF
  port, canonical); datatables.css 0. `.metric-value`, `.ui-empty`, `.ui-notice`/inf, `.ui-skip-link` remain
  ui.css-owned; `ui.css` retained + linked.

**Next (exact phase):** `.metric-value` family (single rule) -> `.ui-empty` -> `.ui-notice-info` orphan/DEAD ->
DEAD (orphan `.ui-notice-info`) -> then `ui.css` final deletion once every owned family has an app.css twin + pointer.
Deletion of ui.css NOT STARTED; ui.css retained + linked.

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

## 2026-09-14 C1 LANDED — Component Containment & Visual Hierarchy Refinement

Three visual-architecture defects resolved (CSS + Blade only, zero schema/auth/JS/business-logic):

**C1-1: Modal Shell overflow-hidden (12 shells / 9 files)** — every modal shell element that had `rounded-panel bg-surface shadow-pop ring-1 ring-line` now also has `overflow-hidden`, which clips the navy header and grey footer backgrounds to the shell's 16px rounded corners. The 4 modals without `max-h` are unconstrained so the overflow-hidden is a harmless safety net; scrollable bodies with `overflow-y-auto` inside the shell are unaffected.

**C1-2: Filter Group Containment (app.css)** — the flat `border-top` dividers on `.filter-multi` sections replaced with per-group card containment: `background: var(--color-bg)` (#F0F2F5), `border: 1px solid var(--color-line-light)`, `border-radius: var(--radius-card)` (12px), `margin-bottom: 0.5rem`. Each filter group is now a visually distinct contained card within the popover.

**C1-3: Details-Panel Avatar Flex Fix (details-panel.blade.php)** — `.details-identity > div` selector changed to `> div:not(.details-avatar)` to prevent the `flex: 1` rule from stretching the avatar shell; `.details-avatar` gets defensive `flex: 0 0 auto`. Avatar shell now renders at 64px×64px desktop / 56px×56px mobile (was stretching to ~209px/195px wide).

VERIFICATION: 301 tests pass; Pint clean; view:cache OK; build `app-DN45AQrl.css` (85.09 kB); 30/30 Playwright assertions pass across 375/1280 viewports confirming overflow:hidden on shells, filter group bg/radius, and avatar dimensions. Smoke superadmin user deleted (id=13). FULL REPORT: `C:\Users\J\AppData\Local\Temp\opencode\c1-component-refinement\C1_COMPONENT_CONTAINMENT_VISUAL_REPORT.md`.

---
