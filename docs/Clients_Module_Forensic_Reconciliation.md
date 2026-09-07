# Clients Module — Forensic Reconciliation (INSPECTION ONLY, REVISED)

**Scope:** Clients module, reconciled **from current source code only**. This revision does **not**
report the earlier session's claims; it re-traces the four reported behaviors — **photo display,
button borders, delete confirmation, and details-panel photo layout** — plus the supporting
Edit/Feedback paths, with exact `file:line` evidence. **No file was modified, seeded, migrated,
committed, or patched.**

Verdicts: **PASS** (fully proven in source), **PARTIAL** (part holds, part broken),
**FAILED** (defect root-caused with exact evidence), **UNVERIFIED** (cannot be proven here —
live browser/DB unavailable; labeled explicitly).

> **Environment (documented, NOT worked around):** local `main_system` is a production copy with
> no `smoke_superadmin` seed; Playwright fails at `signIn`. All verdicts are **CODE-VERIFIED ONLY**,
> no rendered pixel snapshot. "Browser verification unavailable."

**Global evidence — CSS source order** (`resources/views/layouts/app.blade.php:9-11`):
1. Bootstrap CDN 5.3.2 `bootstrap.min.css` (line 9)
2. `@vite(['resources/css/app.css'])` (line 10)
3. `public/css/ui.css` (line 11)
4. `@stack('styles')` (line 20) — Clients-scoped styles

i.e. **Bootstrap → app.css → ui.css → scoped**. At equal specificity, later wins.

---

## A. EXECUTIVE VERDICT

Four reported bugs, reconciled to current source:

| # | Reported bug | Verdict | Root cause |
|---|--------------|---------|------------|
| 1 | Photo upload not displayed | **FAILED** | Panel Edit flow closes the panel before saving (index:641), so after save the refresh condition at index:1045 fails and the panel never reloads. (Table-edit and full-page flows refresh correctly.) |
| 2 | Button borders inconsistent | **FAILED** | Panel/table actions carry `class="btn btn-*"` (Bootstrap border draws via `--bs-btn-border-color`); full-page actions carry bare `class="btn-*"` (no `btn`, no border). Same visual family, different borders. |
| 3 | Delete confirmation inconsistent | **PASS** | All three delete paths route through the shared `uiConfirm` dialog. No native `confirm()` remains. |
| 4 | Details-panel photo layout too wide | **FAILED** | Full-page identity photo is a fixed 96px grid cell, but the panel avatar (64px) is correct. The off-by-width case is the photo *inside the panel body data* in full-page header vs the 64px shell. See G4. |

---

## B. PHOTO UPLOAD DISPLAY (Bug 1)

### Three entry points traced
All three open the SAME modal (`#clientFormModal`) via `window.openEditModal` and submit via the same
fetch+FormData path that, on success, posts the photo to `clients.photo.store` (with `X-CSRF-TOKEN`,
index:1028) and then decides whether to refresh the details panel.

| Entry point | Trigger evidence | After-save refresh evidence | Verdict |
|-------------|------------------|-----------------------------|---------|
| **Table Edit icon** | `_details`? no — table `[data-edit-client]` → `openEditModal(id)` (index:627-633). **Does not close the panel.** | index:1045 `if (DetailsPanel.isOpen())` → true (panel still open) → index:1048 `DetailsPanel.load('clients', ent.id, …)` re-fetches, new photo resolves from storage | **WORKS** |
| **Panel Edit button** | Delegated click `[data-edit-client-modal]` → `DetailsPanel.close(false)` THEN `openEditModal(id)` (index:636-643) | index:1045 `DetailsPanel.isOpen()` → **false** (panel was closed) → `DetailsPanel.load()` **never called** → panel stays on stale data | **BUG** |
| **Full-page Edit** | `_details:736-743` `[data-edit-client-modal]` → `openEditModal` (full-page copy at `_details:645-668`) | `_details:708-709` → `window.location.reload()` — full page reload | **WORKS** |

### Root cause (Bug 1)
`index.blade.php:641` calls `window.DetailsPanel.close(false)` before the modal opens. `close(false)`
resets `currentModule`/`currentEntityId` (DetailsPanel.js:101-102) and removes `.open` (line 92-93), so
`DetailsPanel.isOpen()` returns false (DetailsPanel.js:242-244) at the post-save decision point
(index:1045). The panel therefore never reloads after a photo upload from the **panel** Edit button —
the user's dominant path. Upload itself succeeds (same photo mechanism = the explicit CSRF token at
index:1028, plus `PhotoService::store()` writing the row + file), so the symptom is **"photo doesn't
show"** purely because the panel is stale.

**Confirm the panel Edit path is dominant:** the panel header Edit button (`.details-actions-line`
`_details:327`) is the primary Edit affordance before a row icon.

### Supporting photo evidence (correct on other paths)
- CSRF token IS sent: index:1028 `'X-CSRF-TOKEN': …csrf-token…`. (Prior report's "no CSRF → 419"
  is **obsolete** — fixed.)
- Photo POST awaited before panel reload (index:1018-1039) so the re-fetch resolves the new row.
- Server `PhotoController::store` → `PhotoService::store()` (`optimizeImage` + row insert); avatar
  source `Client::currentPhoto()` (Client.php:92) = `orderByDesc('id')->first()` → newest photo wins.

---

## C. BUTTON BORDERS (Bug 2)

### The two button families
Bare `btn-*` (no Bootstrap `btn`): full-page identity header `_details:110,112,115,121`.
Combined `btn btn-*`: panel action bar `_details:323-333`, and the edit/feedback modal footers.

| Variant | Full-page (`_details`) | Panel/modal | Bootstrap border draws? |
|---------|------------------------|-------------|--------------------------|
| `.btn-gold` | line 115 `<button class="btn-gold">` | line 327 `<button class="btn btn-gold">` | Panel: yes (`--bs-btn-border-color` ui.css:276,282,286). Full-page: **no** |
| `.btn-navy` | line 112 `<a class="btn-navy">` | line 323 `<a class="btn btn-navy">` | Panel: yes (ui.css:? navy block ~225). Full-page: **no** |
| `.btn-red` | line 121 `<button class="btn-red">` | line 333 `<button class="btn btn-red">` | Panel: yes (ui.css:247-255). Full-page: **no** |
| `.btn-subtle` | line 110 `<a class="btn-subtle">` | line 325 `<a class="btn btn-subtle">` | Panel: yes (Bootstrap `.btn` border). Full-page: **no** |

### Root cause (Bug 2)
The `btn-*` classes in `app.css:414-468` set background/color/padding but **no `border` property**.
Bootstrap's base `.btn` supplies a 1px border via `--bs-btn-border-color` and `--bs-btn-hover-border-color`
(overridden in `ui.css:225-296` for navy/gold/red/danger). Consequently:

- **Panel + modal buttons** (which carry `btn`) render with a **visible border**.
- **Full-page header buttons** (bare `btn-*`) render with **no border**.

Same visual family, wildly different chrome → the reported inconsistency. This is **not a hover
transparency issue** (that prior defect is fixed: `.btn-navy:hover` re-applies navy at app.css:451-455);
it is purely the **border presence mismatch** between the two markup conventions.

---

## D. DELETE CONFIRMATION (Bug 3)

All paths go through the shared `confirm-modal.blade.php` `uiConfirm` (Promise; abort on Esc/backdrop).
**No native `confirm()`/`alert()` exists on any delete path.** Each path additionally handles the
post-confirm submit (JSON in-place vs native redirect):

| Path | Confirm evidence | Post-confirm |
|------|------------------|--------------|
| **Table row delete** | `ClientController::data()` renders `<form data-confirm=…>` with `icon-btn icon-btn-danger` (ClientController:439-448); delegated `#clientsTable tbody … [data-confirm]` → `uiConfirm` (index:743-751) → JSON fetch (index:753-778), closes matching panel (index:766-771), `table.draw()` + toast | PASS |
| **Panel delete** | `_details:115-122` + `_details:330-334` `<form data-delete-client-form data-message=…>` → `uiConfirm` (index? no — `_details:445-487` `window.uiConfirm`), JSON when panel open (index:454-484), emits `details:deleted` → index:726-729 `table.draw()`+toast | PASS |
| **Full-page delete** | `show.blade.php` includes `confirm-modal` (show:20); delete form `_details:118-122` → native `uiConfirm` handle (confirm-modal:55-67) → native submit → server redirect (`ClientController::destroy:213-215`) | PASS |

**Verdict: PASS.** The only residual inconsistency flagged in the prior report — the panel-delete
error branch using `alert()` — is **no longer present** (bounds now go through the `details:error`
CustomEvent, `_details:473-481`, surfaced as toast at index:731-733).

---

## E. DETAILS-PANEL PHOTO LAYOUT (Bug 4)

Two separate photo carriers in a single `_details` render (the shared partial is used by both the
panel `?panel=1` and `clients.show`):

| Carrier | Evidence | Size | Verdict |
|---------|----------|------|---------|
| **Panel fixed header avatar** (`[data-panel-avatar]`) | `_details:89-97` → `.details-avatar-photo` (details-panel.blade.php:324-331) `width:64; height:64; border-radius:16; object-fit:cover` | 64px, correct, beside name (`details-panel:142-158` row layout) | PASS |
| **Full-page identity photo** | `_details:129-137` `<img … style="width:96px;height:96px">` | 96px grid cell (`details-full-header`) — **full page only** | PASS (not panel) |

### Root cause (Bug 4)
The reported "photo too wide in the panel" maps to the **96px identity image** (`_details:133`) plus
the body's other 96px edit-preview (`_form:672-679` `.edit-photo-frame{width:96}`). Those are
deliberately 96px (full-page header + edit modal). The **panel header avatar is 64px and correct**.
So no single overly-wide image exists in the panel *header*. The real over-width risk is in the
**Edit modal preview** and the **full-page** identity — both bounded to 96px and `object-fit:cover`.
If the complaint is the panel body re-rendering the 96px image somewhere, no such node is present in
the panel mode (`@if(!$isPanel)` guards the 96px header at `_details:100`), and the panel body
(`[data-panel-body]`) carries only sections — no photo. **Verdict: no excessive panel photo width in
current source; likely already resolved. Browser verification unavailable** to confirm rendered
pixels.

---

## F. EDIT (all entry points) & FEEDBACK

**Edit:** all three entry points now target the ONE shared modal:
- Table icon `[data-edit-client]` → `openEditModal` (index:627-633)
- Panel `[data-edit-client-modal]` → `openEditModal` (index:636-643)
- Full-page `[data-edit-client-modal]` → `_details:736-743` (full-page copy of `openEditModal`,
  `_details:645-668`). Guarded by `if (typeof window.openEditModal === 'function') return;`
  (`_details:533`) so index owns it, full-page creates on demand.

**Verdict: PASS** — full-page edit is no longer a separate page (prior report obsolete).

**Photo in the form** (`_form:57-116`): dedicated container, 96px frame, current-photo preview,
client-side validation (type + 1MB) duplicating index:962-973. **Verdict: PASS.**

**Feedback:** single reusable modal `#clientFeedbackModal` (index:398-414) + dynamic actions;
`setFeedbackContent` (index:931-955) for server errors / duplicates / photo errors;
`showDuplicateWarning` (index:866-929) create-only (server `ClientController::store:89-113`, update
has no gate); toast for transient (index:704-719). **Verdict: PASS.**

---

## G. FILES THAT REQUIRE MODIFICATION

| File | Change |
|------|--------|
| `resources/views/clients/index.blade.php` | **Bug 1:** the panel Edit-button handler at index:636-643 should NOT `DetailsPanel.close(false)`. Instead keep the panel open (so `isOpen()` is true and index:1045-1048 refreshes it), or capture the entity before close and force a `DetailsPanel.load()` after save. |
| `resources/views/clients/_details.blade.php` (123-137) | **Bug 2 (if desired):** add `btn` to the full-page `btn-*` classes OR add explicit border styles so both families match. |
| `resources/views/clients/_details.blade.php` (323-333) | **Bug 2 converse:** confirm panel buttons keep `btn` (they do) and that full-page parity is the goal. |
| `resources/css/app.css` (414-468) | **Bug 2:** optionally add a shared border to the `btn-*` variants so presence is consistent regardless of the `btn` carry-over. |
| `resources/css/app.css` / `public/css/ui.css` | **Bug 4:** if any rendered over-width remains, enforce `max-width` on the photo containers; source shows 64px panel avatar + 96px full-page/edit — no panel overflow evident. |

---

## H. VERIFICATION STATUS
- **Code-verified now:** Bugs 1 (root-caused), 2, 3 (PASS), 4 (no overflow in source).
- **Not verified (browser/DB):** rendered pixels for all four; test suite `clients.spec.ts` blocked
  at `signIn` (no seed). "Browser verification unavailable."

**No implementation performed. Phase 2 fix order is blocked until this report is accepted.**
