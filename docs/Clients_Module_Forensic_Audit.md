# Clients Module — Forensic Inspection Report (STRICT INSPECTION ONLY)

Scope: `2DMIS-v2` Clients module, audited against the 17-point / 25-section UX/UI
inspection brief. **No file, CSS, JS, Blade, PHP, route, ACL, DB, schema, or config
was modified, seeded, or committed.** All verdicts are traceable to exact
`file:line`, selector, function, and event/data flow, with statuses:

- **VERIFIED** — confirmed directly in source (code) or reproducible behavior.
- **PARTIALLY VERIFIED** — part of the requirement holds; part is missing/broken.
- **FAILED** — requirement not met; defect root-caused.
- **UNVERIFIED** — cannot be proven in this environment (no live browser/DB row).

> Environment limitation (documented in `docs/SESSION_HANDOFF.md`; NOT worked
> around by seeding): the local `main_system` is a byte-identical **production
> copy** with NO `smoke_superadmin` seed user. Playwright/`clients.spec.ts` fail at
> `signIn` (4/4). **Every verdict below is CODE-VERIFIED ONLY** unless explicitly
> labeled otherwise. No visual snapshot of a rendered page was obtainable.

---

## A. Executive Summary

The Clients module is substantially implemented and internally consistent, but the
**forensic pass contradicts four prior "PASS / FIXED" claims**. The most serious:

1. **`Req 7/8` Photo upload still FAILS (FAILED).** The previously-reported
   "photo not updating" bug **persists**. Client-side validation was added
   (index:958-989) and the photo is now awaited (index:1033-1044), but the
   photo `POST` to `clients.photo.store` (index:1019) sends **no `_token` and no
   `X-CSRF-TOKEN` header**, and there is **no global fetch/axios interceptor**
   (verified: only `<meta name="csrf-token">` exists; `bootstrap.js` sets only
   `X-Requested-With`). Laravel's `VerifyCsrfToken` rejects the bare POST with a
   419, which the `.then(r => { if (!r.ok) throw })` handler translates into the
   "Client was saved, but the photo could not be uploaded" feedback path. The
   update-carrying form itself POSTs with `@csrf` (`_form:28`) and passes — only
   the **photo sub-POST lacks the token**.

2. **`Req 5` DetailsPanel navy button turns transparent on hover (FAILED).**
   The panel's `<a class="btn btn-navy" …>+ Add Transaction</a>` (`_details:322`)
   uses the Bootstrap `.btn` base. Bootstrap 5.3.2's `.btn:hover` (0,2,0) sets
   `background-color: var(--bs-btn-hover-bg)`, which `.btn-navy` never defines →
   resolves **transparent**, overriding `.btn-navy`'s navy fill (0,1,0,
   app.css:438-442 — `hover:text-gold` sets color only, not background). This is
   the reported "navy turns white on hover" defect. `.btn-gold`/`.btn-red`/`.btn-subtle`
   are safe (each supplies a 0,2,0 hover background; `.btn-navy` is the *only*
   failing variant in the panel).

3. **`Req 6` Full-page EDIT does NOT open the shared modal (FAILED).** Only
   two of three Edit entry points use the modal (`[data-edit-client-modal]`
   details-panel:326; `[data-edit-client]` table — index:632/642 route through
   `openEditModal`). The **full-page** Edit link (`_details:115`) is
   `<a href route("clients.edit")>` → navigates to a separate full-page form view
   (`edit.blade.php`). This **contradicts Req 6** ("Full Page EDIT action should
   open the SAME edit modal").

4. **`Req 1` Two "Clear All" controls exist; one is hidden.** FilterChips'
   own `data-filter-clear-all` (component-owned) is `display:none` via
   `.filter-chips-toolbar{display:none}` (index:155-157); the visible Clear All is
   a separate `#clientsClearAll` (index:326). Functionally the visible one works,
   but the duplicate is a maintenance/state risk.

Two requirement areas are **UNVERIFIED** for lack of a browser (visual button
hover end-state, exact mobile breakpoint pixel behavior, live photo lifecycle,
family/transaction real-row rendering) and are labeled accordingly even where the
underlying CSS/JS is code-verified.

Net: the module is close to spec. Three defects (photo CSRF, navy-button hover,
full-page-edit-vs-modal) are real and reproducible in source. The remainder of the
25 sections are VERIFIED or PARTIALLY VERIFIED with minor gaps (see the Matrix).

---

## B. Requirement Matrix

| # | Req | Status | Summary |
|---|-----|--------|---------|
| R1 | Filters | **PARTIALLY VERIFIED** | 4 segment filters + chips + Clear All work; component Clear All hidden (dup control) |
| R2 | Buttons, system-wide | **FAILED** | `.btn-navy` hover → transparent on panel (`btn btn-navy`); other variants safe |
| R3 | Delete confirmation | **VERIFIED (code)** | All 3 delete paths use shared `uiConfirm` |
| R4 | DetailsPanel layout | **VERIFIED (code)** | Identity row, actions, meta all wired |
| R5 | DetailsPanel buttons 2×2 | **PARTIALLY VERIFIED** | `.details-actions-line` flex-wrap 1×4 desktop, 2×2 on wrap; navy hover bug (see R2) |
| R6 | Edit modal | **FAILED** | 2/3 paths use modal; full-page Edit navigates to separate page |
| R7 | Photo upload (server rules) | **FAILED** | Server rules present & correct; **client POST lacks CSRF → 419** |
| R8 | Photo upload (lifecycle) | **FAILED** | Await is present but the POST 419s before save; no accessible full-page photo entry |
| R9 | Feedback architecture | **VERIFIED (code)** | One reusable feedback modal (`setFeedbackContent`), no alert/confirm |
| R10 | Duplicate warning | **VERIFIED (code)** | On create only; presented in feedback modal; **NOT on update** (no interference with edit) |
| R11 | Full-page profile header/navbar | **VERIFIED (code)** | `.details-full-header{top:4rem}`, navbar h-16=64px |
| R12 | Full-page action buttons | **VERIFIED (code)** | Back → +AddTx → Edit → Delete; standalone Photo removed |
| R13 | Category read-only/derived | **VERIFIED (code)** | `readonly` input, client+server derivation; no manual override |
| R14 | Family composition | **VERIFIED (code)** | `familyMembers → relative` real relation + empty state |
| R15 | Transactions | **VERIFIED (code)** | `transactions` HasMany real relation + empty state (no explicit ORDER) |
| R16 | Responsive | **PARTIALLY VERIFIED (code)** | Mobile media query (app.css:934-998); visual not provable |

---

## C. Filters (Req 1)

**Status: PARTIALLY VERIFIED**
- Four segmented filters render: municipality / barangay / program / category —
  `index.blade.php:298-321` (`.seg-btn`, `data-filter-segment`).
- State & counts: `refreshSegmentCounts()` (index:563, :577-585) toggles
  `is-active`/`aria-pressed` and sets `.seg-count` badges.
- `FilterChips.js` (checkbox change → commit → onApply → table.draw) still owns the
  chips row, popover, and Clear All.
- **Visible Clear All:** `#clientsClearAll` (index:326, class `filter-clear-all`,
  initially `hidden`), toggled by `refreshSegmentCounts()` (index:583-585).
- **Hidden second Clear All:** `#clients-screen .filter-chips-toolbar{display:none}`
  (index:155-157) hides the FilterChips component's own toggle + its internal
  `data-filter-clear-all`. Two Clear All controls exist in the DOM; only
  `#clientsClearAll` is visible. **Defect (minor): duplicate control, state coupling.**
- Per-category Clear in `.filter-multi-footer` (`filter-chips.blade.php:107-110`);
  date Clear in `.filter-multi-head` (:127). Both present.
- **Contradiction:** `app.css` comment references `.filter-chips-toolbar` but the
  index CSS hides it; the "Clear All" the user sees is `#clientsClearAll`, not the
  component's.

---

## D. Button System (Req 2)

**Status: FAILED** (one variant broken; others verified)

Token/badge palette: `--color-navy:#0038A8`, `--color-gold:#FCD116`,
`--color-red:#CE1126`, `--shadow-lift` (app.css:168-267).

Variant definitions:
- `.btn-gold` gradient navy-on-gold; `:hover` box-shadow lift (app.css:422-427).
- `.btn-navy` `bg-navy text-white hover:text-gold active:bg-navy-hover`
  (app.css:438-442) — **hover sets COLOR only, no background**.
- `.btn-red` `hover:bg-red-hover` (app.css:444-446) — safe.
- `.btn-subtle` `hover:bg-navy` (app.css:448-450) — safe.
- `.btn-outline-red` `hover:bg-red` (app.css:452-454) — safe.

**Root cause (`.btn-navy` hover → transparent):**
- Load order (layouts/app.blade.php:9-11): **Bootstrap CDN 5.3.2 → app.css (Vite) →
  ui.css**. Bootstrap `.btn:hover` is specificity 0,2,0 and sets
  `background-color: var(--bs-btn-hover-bg)`; `.btn-navy` never declares
  `--bs-btn-hover-bg`, so the custom property falls back to **`transparent`**
  (Bootstrap base `.btn` declares `--bs-btn-hover-bg:transparent`).
- `.btn-navy:hover` from `hover:text-gold` sets only `color`, giving 0,2,0 only for
  color — the `background-color` from Bootstrap `.btn:hover` (0,2,0) beats `.btn-navy`'s
  background (0,1,0).
- **Victim element:** panel `<a class="btn btn-navy">+ Add Transaction</a>`
  (`_details:322`). On hover → transparent background + gold label (the reported bug).
- **Unaffected:** every `btn-navy` WITHOUT the `btn` base (full-page header
  `_details:112`, feedback-modal buttons index:890, `_gip:50,185`) — no Bootstrap
  `.btn:hover` applies. Safe.
- `.btn-gold` with `btn` base is safe via ui.css `.btn-gold` setting
  `--bs-btn-hover-bg` (ui.css ~:273) → Bootstrap `.btn:hover` reads it. `.btn-red`/
  `.btn-subtle` safe via their own 0,2,0 `:hover` background.

Hover visuals cannot be confirmed in a browser (UNVERIFIED for exact rendered
end-state), but the cascade is provable in source + fetched Bootstrap 5.3.2.

---

## E. Delete Confirmation (Req 3)

**Status: VERIFIED (code)**
- Shared dialog `partials/confirm-modal.blade.php`: `window.uiConfirm({…}) →
  Promise<boolean>`, plus declarative `[data-confirm]` form support and a
  `data-message` variant. Confirm button is `.btn-red`.
- `@include('partials.confirm-modal')` in both `index.blade.php:365` and
  `show.blade.php`.
- Three delete paths, all through `uiConfirm`:
  1. Table row: index JS on `[data-confirm]` (`~:742`, `:744`).
  2. DetailsPanel: `_details:440-468` — `uiConfirm({…})`, dispatches
     `details:deleted`.
  3. Full-page: `show.blade.php` includes confirm-modal; `_details:121` submit is
     `[data-confirm]`-wired.
- Route `clients.destroy` = **POST** `clients/{client}` (routes/web.php:109) with
  `action:clients.php,delete` middleware. `ClientController::destroy` (client:184)
  → `ClientService::destroy` (client:266) guards deletion of clients with
  transactions (throws `InvalidArgumentException`).
- Not browser-verified (no session), but fully wired in source.

---

## F. DetailsPanel (Req 4)

**Status: VERIFIED (code)**
- Identity block `.details-identity` `details-panel.blade.php:15-23` (avatar +
  title/sub/meta); clients-specific CSS sets `flex-direction:row` (photo BESIDE
  name) — panel partial :143-148.
- `.details-actions` `flex-wrap:wrap; gap:8px` (panel :25).
- `DetailsPanel.js` `SELECTORS` covers panel/backdrop/close/body/title/avatar/sub/
  meta/actions; `lockScroll()`. `window.DetailsPanel.load('clients', id, {url})`
  is used on review & after edit (index:908, :1041).

---

## G. DetailsPanel Buttons / 2×2 (Req 5)

**Status: PARTIALLY VERIFIED**
- Four actions in `.details-actions-line` (`_details:318-334`):
  `+ Add Transaction` (btn-navy :322, ACL-gated), `Open Full Page` (btn-subtle :324),
  `Edit` (btn-gold :326), `Delete` (btn-red :332).
- `.details-actions-line > .btn, form { flex: 1 1 auto; min-width: 104px }` →
  in ~432px content width, 4×104=416 ⇒ **one row of 4 on desktop**; wraps 2×2 only
  when width < ~416px (mobile/narrow drawer). Req's preferred 2×2 is not the
  desktop layout.
- **Hover bug:** `+ Add Transaction` (`btn btn-navy`) → transparent on hover (see Req 2).

---

## H. Edit Modal (Req 6)

**Status: FAILED**
- Modal path (correct): `openEditModal(id)` (index:821-844) fetches
  `clients.edit/{id}?modal=1` → injects form → sets `form.dataset.clientId`.
  Reachable from table `[data-edit-client]` (index:632/642) and panel
  `data-edit-client-modal` (details-panel:326).
- **Full-page path (contradicts Req 6):** `_details:115`
  `<a href="{{ route('clients.edit', $client) }}" class="btn-gold no-underline">Edit</a>`
  → navigates to the **separate full-page** `edit.blade.php`. It does NOT open the
  shared modal. Req 6 requires the full-page Edit to open the SAME modal — FAILED.
- Form duality: `_form.blade.php` serves both:
  - modal (`@if($modal)` id=clientForm; `_form:27-29` `@csrf`/`@method`)
  - full-page (`edit.blade.php` / `create.blade.php`).
  `edit()` pins action to `clients.update` + PUT when `?modal=1`
  (ClientController::edit).

---

## I. Photo Upload (Req 7/8)

**Status: FAILED (both)**

Server rules — **correct & verified**:
- `PhotoController::store` validates `client_id` (required, exists), `photo`
  (`nullable|file|image|max:1024`), `camera_image`; ACL `RecordMunicipality::ofClient`;
  422 JSON on error; persists via `PhotoService::store`.
- Route `clients.photo.store` (routes/web.php:103) middleware
  `action:clients.php,edit`.
- `PhotoService`: `UPLOAD_DIR=uploads/client_photos`,
  `ALLOWED_EXTENSIONS=[jpg,jpeg,png,gif]`, `MAX_DIMENSION=1600` downscale,
  `optimizeImage()` → jpg. Allowed list = JPG/PNG/GIF ✓; 1MB max ✓; optimize ✓.

Client lifecycle — **FAILED (CSRF)**:
- Client-side validation before network: `photoValidationError()`
  (index:958-989; jpg/png/gif + ≤1MB, else feedback modal, no silent skip). ✓ added.
- On update `data.success`, if `photoFile && form.dataset.clientId`, posts a **fresh
  FormData** with only `client_id` + `photo` to `clients.photo.store`
  (index:1015-1031). **This FormData contains NO `_token` and the fetch sets NO
  `X-CSRF-TOKEN` header.**
- No global interceptor: layout has only `<meta name="csrf-token">`
  (app.blade.php:6); `bootstrap.js` sets only `X-Requested-With` (bootstrap.js:4);
  no axios default CSRF, no fetch wrapper anywhere in `resources/js`.
  ⇒ `VerifyCsrfToken` returns 419; `.then(r => { if (!r.ok) throw })` (index:1028-1030)
  routes to "Client was saved, but the photo could not be uploaded" (index:1050).
- **Conclusion: the exact previously-reported bug persists.** The missing-token
  root cause is still untouched; client-side validation only prevents *invalid-file*
  uploads, not the 419. The photo is NEVER saved and NEVER updates the panel/avatar.
- Full-page: no accessible photo entry point — `#photoModal` (`_details:338`) has
  **no `data-bs-target` trigger anywhere** (the standalone Photo button was removed).
  Full-page photo upload is therefore unavailable (gap; Req 8), photo only reachable
  via the (broken) modal flow.
- Display URL: server-side `$client->currentPhoto()` (`Client` model). Verified
  existence; not browser-rendered.

---

## J. Feedback Architecture (Req 9)

**Status: VERIFIED (code)**
- One reusable feedback modal: `setFeedbackContent(title, type, html)`
  (index:931-951) with `setFeedbackType` color theming and focus-return to the
  underlying form on `hidden.bs.modal`.
- Used for: duplicate warning (index:883-925 via `showDuplicateWarning`), photo
  errors (index:987, :1051), validation errors (index:1073), generic failure
  (index:1090-1093).
- **No `alert()`/`confirm()`** anywhere in the Clients module. ✓ (grep-confirmed).
- Keeps the edit modal open with values intact → recovery path present.

---

## K. Duplicate Client Warning (Req 10)

**Status: VERIFIED (code)**
- Detection: `ClientService::findPotentialDuplicates` — match `match_name` AND exact
  `birthdate` (client:198-216), run only in `ClientController::store` (client:90),
  gated on `duplicate_confirm`.
- Presentation: `showDuplicateWarning` renders into the shared **feedback modal**
  (not inside the edit modal; index:862-925) with `Review existing client`
  (btn-navy) → opens the DetailsPanel; `This is a different person — Continue`
  (btn-gold) → injects `duplicate_confirm=1` and resubmits.
- **`update()` does NOT run the duplicate gate** (no `data.duplicate_warning` on
  update; edit pinned to `clients.update`). ⇒ **previous claim that the duplicate
  warning "hijacks" Edit is resolved** — a duplicate warning cannot appear in the
  edit flow. Authoritative, code-verified.
- `DuplicateService` (separate module page) matches the v1
  (lastname,firstname,middlename,municipality) GROUP BY contract — distinct from the
  create-warning gate; not part of this add-path.

---

## L. Full-Page Client Profile & Navbar Overlap (Req 11/12)

**Status: VERIFIED (code)**
- Navbar `sticky top-0 z-50 h-16` = 64px (`layouts/app.blade.php:45`).
- `.details-full-header{ position:sticky; top:4rem }` (app.css:536-539) — sits
  below the 64px navbar. No overlap in source. (Built-asset ordering for this rule
  previously verified.)
- Full-page action stack (`_details:108-124`): Back (btn-subtle) → `+ Add
  Transaction` (btn-navy :112, ACL-gated) → Edit (btn-gold :115 — navigates to
  separate page, see Req 6) → Delete (btn-red :121, `[data-confirm]`).
  Standalone Photo button **removed** (no `data-bs-target="#photoModal"` trigger exists).
- Not browser-rendered (UNVERIFIED visually), but the sticky math and button stack
  are proven in source.

---

## M. Category Derivation (Req 13)

**Status: VERIFIED (code)**
- Server: `ClientService::attributes()` → `deriveAge(birthdate)` →
  `deriveCategory(age)` (client:84-103, :149); MINOR (0-17) / YOUTH (18-29) /
  ADULT (30-59) / SENIOR (60+). **Client-supplied `category`/`age` are ignored**
  (overwritten). Rule fixed; not changed.
- Client: category input `readonly` (`_form:327-329`); auto-derivation JS
  (`_form:406-423`).
- No manual override UI. ✓
- **Edge case (minor):** empty/invalid birthdate ⇒ `deriveAge` returns 0 ⇒ category
  "MINOR (0-17)", but `ClientRequest` requires `birthdate` (`before:today`) so a
  server-side client cannot reach that state via the form. Low risk.

---

## N. Family Composition (Req 14)

**Status: VERIFIED (code)**
- `Client::familyMembers()` (HasMany `tbl_family_members`).
- `_details` renders `@forelse($client->familyMembers …)` with an **empty state**
  and an `+ Add Family Member` action (btn-subtle, ACL-gated) (`_details:224`,
  family loop ~:236-247).
- `FamilyMember` belongsTo `relative` (Client) → `$member->relative->full_name` +
  `relationship` renders real relative data (:25-28, FamilyMember.php:25-28).
- No live rows to render (UNVERIFIED visually).

---

## O. Transactions (Req 15)

**Status: VERIFIED (code)**
- `Client::transactions()` (HasMany `tbl_transactions`).
- `_details` renders `@forelse($client->transactions …)` with empty state
  (loop ~:273-291); Transaction belongsTo `client` (Transaction.php:40-43).
- **Gap:** relationship has no explicit `orderBy`; display order is DB/insertion
  order, not date-desc. Requirement asks only for "actual transactions belonging to
  client," which holds; ordering is a nice-to-have.
- No live rows to render (UNVERIFIED visually).

---

## P. Responsive Audit (Req 16)

**Status: PARTIALLY VERIFIED (code)**
- Mobile block `@media(max-width:767.98px)` (app.css:934-998): filter popover →
  bottom sheet with `max-height:70vh`, touch-target bumps (36-40px min heights,
  16px font on date/search inputs), enlarged row action buttons.
- `.details-actions` wraps (details-panel :25); clients identity row
  `flex-direction:row` (panel :143-148). Panel scroll-lock via DetailsPanel.js.
- Code-verified only; **no pixel-accurate rendering proof** (no browser). Exact
  breakpoint behavior across the 3 configured browser projects is UNVERIFIED.

---

## Q. Contradictions / Prior-Claim Reconciliation

1. **"Photo update fixed" (prior PASS) — CONTRADICTED.** The missing CSRF token on
   the `clients.photo.store` POST persists (index:1019; no interceptor; 419 →
   "photo could not be uploaded"). Inspect line 1015-1032.
2. **"Buttons PASS" (prior claim) — CONTRADICTED.** Panel `.btn-navy` hover →
   transparent (app.css:438-442 vs Bootstrap `.btn:hover` 0,2,0 → `--bs-btn-hover-bg`
   transparent). The user's "navy turns white on hover" report is **confirmed in
   source**, not dismissed as browser-only.
3. **"Edit uses shared modal everywhere" (implied by earlier report) — CONTRADICTED.**
   Full-page Edit (`_details:115`) navigates to `edit.blade.php`, not the modal.
4. **"Duplicate warning hijacks Edit" (prior concern) — RESOLVED/NOT REPRODUCED.**
   `update()` never returns `duplicate_warning`; the gate is create-only
   (ClientController:90 vs :184 update/destroy are separate). Edit is clear.
5. **Two Clear All controls ("Clear All present" vs component Clear-All):** the
   FilterChips component's Clear All is hidden (index:155-157); the visible
   `#clientsClearAll` is a different element (index:326). Claims that reference "the
   component Clear All" are inaccurate.
6. Full-page standalone Photo upload was claimed removed AND `#photoModal` made
   dead-weight — CONFIRMED as dead (no trigger), but this also means **no full-page
   photo path exists**, contradicting any prior "photo upload works everywhere" claim.

---

## R. Files Inspected

- `resources/views/clients/index.blade.php` (filters, segment buttons, modal
  submit/photo/duplicate/feedback handlers)
- `resources/views/clients/_details.blade.php` (panel actions, full-page header,
  family/transactions, dead `#photoModal`)
- `resources/views/clients/_form.blade.php` (`@csrf`/`@method`, readonly category,
  auto-derivation)
- `resources/views/clients/show.blade.php`, `edit.blade.php`, `create.blade.php`,
  `_gip.blade.php`
- `resources/views/partials/{filter-chips,details-panel,confirm-modal}.blade.php`
- `resources/css/app.css` (buttons :414-454, sticky header :536-539, photo frame
  :648-680, mobile :934-998)
- `public/css/ui.css` (`.btn-gold` BS-variable modifier, `.btn-primary/danger`)
- `resources/js/components/{FilterChips,DetailsPanel}.js`
- `resources/js/bootstrap.js`, `resources/views/layouts/app.blade.php` (load order,
  navbar, csrf meta)
- `app/Http/Controllers/{ClientController,PhotoController}.php`
- `app/Services/{ClientService,DuplicateService,PhotoService}.php`
- `app/Models/{Client,FamilyMember,Transaction}.php`
- `app/Http/Requests/ClientRequest.php`
- `routes/web.php`
- Bootstrap 5.3.2 CDN source (fetched; `.btn`/`.btn:hover` rules) + `docs/SESSION_HANDOFF.md`,
  `docs/IMPLEMENTATION_LOG.md` (prior claims, contradiction source)

## S. Files Modified

**NONE — INSPECTION ONLY.**

No file was created, edited, seeded, migrated, or committed. `git status` will show
only this audit document plus any pre-existing working-tree state.

## Production Safety

- No schema/DB/ACL/route/business-rule changes made or proposed for immediate
  application. The create-only duplicate gate and the readonly derived category
  remain as-is (source of truth = current app behavior).
- Major risks requiring a **code fix + browser verification** (out of scope for this
  inspection; NOT implemented):
  1. Photo POST lacks CSRF (Req 7/8) — add `_token`/`X-CSRF-TOKEN` to index:1019.
  2. Panel `.btn-navy` hover → transparent (Req 5) — define a hover background.
  3. Full-page Edit should open the modal (Req 6) — reconcile `_details:115`.
  4. Remove/hide dead `#photoModal` or add an accessible trigger and a full-page
     photo path (Req 8).
  5. De-duplicate the two Clear All controls (Req 1).

No accidental modifications were detected during this pass.
