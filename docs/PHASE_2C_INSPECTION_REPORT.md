# Phase 2C — FilterChips Pre-Implementation Inspection Report

**Status:** Inspection/planning only — NO code changed in this phase.
**Date:** 2026-08-27
**Scope:** Phase 2C shared FilterChips system (prototype §5.x / PHASE_2_PLAN §9–12).
**Hard constraint honored:** No application code, no `resources/js/components/FilterChips.js`, no
`resources/views/partials/filter-chips.blade.php`, no controller/route/DataTables-feeds/query/schema/filter-UI
or business-logic changes. This report inspects, compares, and documents only.

**Test baseline (verified this session):** `php artisan test` → **239 passed, 1 risky, 1137 assertions**
(matches SESSION_HANDOFF's "240 tests / 1137 assertions"); `vendor/bin/pint --test` → **passed**.
No tests were modified.

---

## 1. Purpose & Scope of This Report

Phase 2C replaces the inconsistent per-screen filter controls with a **shared, reusable FilterChips
interaction** modeled on the prototype. The prototype is **presentation-only** (PROTOTYPE_SPEC §1–3):
it renders a hard-coded in-memory record dataset entirely client-side. **v2 is inverted**: every list
screen is a **server-side DataTables feed** (CDN jQuery + DataTables, `@push('styles')` Bootstrap 5 CSS,
requested via AJAX `data:` endpoints). Therefore the shared FilterChips must **sit on top of the existing
server-side feeds and translate filter selections into the query parameters those feeds already accept** —
it must NOT reimplement client-side record filtering.

This report documents:
1. The prototype's FilterChips UX (interaction model, semantics, CSS).
2. The **current** v2 filter implementation of every affected module (10 modules).
3. The v1 semantics each v2 feed is contract-bound to mirror.
4. The backend contracts FilterChips must target.
5. A proposed component architecture/API (design only — not implemented here).
6. 13 explicitly-listed deferred decision points as `DECISION REQUIRED #N` blocks.

The rule from PHASE_2_PLAN §10.6 is treated as binding: *"Filter Chips must integrate with existing
data endpoints … adapt to the existing backend rather than requiring a new backend architecture."*

---

## 2. Method & Sources Used

| Source | Location | What it provided |
|---|---|---|
| Prototype spec | `prototype/PROTOTYPE_SPEC.md` | Presentation-only scope, constraints |
| Prototype engine | `prototype/js/app.js` (~744–1010, ~3268) | `FILTER_SPECS`, `filterMatches`, chips renderer, menu builder, audit date range |
| Prototype markup | `prototype/index.html` (§403–958 hosts) | `data-filter-host` placement per module |
| Prototype CSS | `prototype/css/style.css` (736, 759+, 1767–1841, responsive 1202+) | Filter-multi popover + chip styling + responsive rules |
| v2 views | `resources/views/{clients,households,transactions,scholars,scholarship_reports,payouts,unpaid_verifications,admin/audit_logs,admin/users}` | Current filter UI per module |
| v2 controllers | `app/Http/Controllers/{Client,Household,Transaction,Scholar,Report,Audit,User,UnpaidVerification,PayoutAttendance}Controller.php` | DataTables feed params/contracts |
| ACL | `app/Services/AccessControlService.php` | Municipality scope + program permission composition |
| Shared chip partial | `resources/views/partials/active-filters.blade.php` | Existing planned contract + Tailwind allowlist note |
| Chips CSS | `resources/css/app.css` (503–511) | Static `.filter-chip*` component classes |
| v1 (read-only) | `C:\xampp\htdocs\system\fetch_*.php`, `audit_logs.php`, list pages | Authoritative filter semantics |
| Prior plan | `docs/PHASE_2_PLAN.md` §9–12 | Approved filter interaction spec + module filter list |
| Prior report | `docs/PHASE_2_INSPECTION_REPORT.md` | Existing component sizing/footprint claims (treated as reference, re-verified) |
| State | `docs/SESSION_HANDOFF.md`, `docs/IMPLEMENTATION_LOG.md` | Milestone status, allowlist architecture, baseline |

---

## 3. Prototype FilterChips UX (the interaction target)

### 3.1 Interaction model
- A **Filter button** (`.filter-group-toggle`) per module opens a **popover** (`.filter-multi-menu`, width
  280px, max-width 90vw, `z-index:60`, absolutely positioned under the button).
- Inside the popover, each **filter category** is a group (`.filter-multi`) with a **header + search
  input** (when `searchable`) + a **Clear-per-category** control + a **checkbox option list** with counts.
- Each option line is a checkbox; a count badge is shown next to each option.
- The footer has a Clear-all for the whole popover (`[data-filter-clear-cat]` per category, footer for all).
- On mobile (< ~768px) the popover becomes a **bottom sheet** (`bottom:0; left:0; right:0;`), and
  `.filter-chip` padding grows (8px 14px) for touch targets.

### 3.2 Chip display
- Selected values render as removable **chips** (`.filter-chip`) with an × remove button (`filter-chip-x`,
  `data-filter-remove="cat|value"`).
- Chips sit in a chip row above the table toolbar (`renderFilterBadges`).
- The Filter button shows an **active-filter count badge** (`activeFilterCount`).
- A **Clear All** control appears only when at least one filter is active.

### 3.3 Semantics (confirmed by code, matches PHASE_2_PLAN §10.1–10.2)
- **Within a category: OR.** `filterMatches` (app.js ~794) — any option in the category matching the record
  makes the category match.
- **Across categories: AND.** All active categories must match.
- **Search composes AND with filters**: `filteredResidents()` (~1027) applies search, then `filterMatches`,
  then sort; page resets on filter change.
- **Audit date range**: `state.audit.dateFrom/dateTo` compared lexicographically against `l.ts.slice(0,10)`
  in `filteredAudit()` (~3268–3284).

### 3.4 Module configuration (`FILTER_SPECS`, ~755)
| Module | Categories exposed in prototype |
|---|---|
| clients | program, category, sex, civilStatus, status |
| transactions | program, type, status |
| households | muni |
| scholars | (program/status — via scholars screen) |
| payouts | (municipality/program/date) |
| users | (role/status) |
| audit | user, action, date range |

**NOTE (critical for decision points):** the prototype's `clients` category list (program/category/sex/
civilStatus/status) is **prototype-only fiction**. v1 and v2 clients filter on **municipality + barangay
only** (§6.1). Per the modernization principle these prototype categories must **not** be added silently —
flagged in DECISION #1.

### 3.5 Host placement (`data-filter-host`)
Prototype hosts `data-filter-host` for: clients, households, transactions, scholars, payouts, users, audit.
No host for GIP (GIP is a tab inside scholars).

---

## 4. Current v2 Filter Implementations (per module)

All modules are **server-side DataTables** unless noted. "Feed params" = the query/body keys the v2
`data()` method reads.

### 4.1 Clients (`clients/index.blade.php`, `ClientController::data`)
- **Mechanism:** server-side DataTables AJAX; **GET form** filter bar.
- **Feed params:** `municipality`, `barangay` (plus standard draw/start/length/search.value/order).
- **Filters present:** Municipality select, Barangay select.
- **ACL:** `applyMunicipalityScope(..., 'clients.php')` (and `canAccessRecord` on create/edit).
- **Chips:** **none** currently (plain selects; the `active-filters` partial is not wired in).
- **Get/post to `#clients-screen` CSS block.**

### 4.2 Households (`households/index.blade.php`, `HouseholdController::data`)
- **Mechanism:** server-side DataTables AJAX; GET filter bar.
- **Feed params:** `municipality`, `barangay`.
- **ACL:** municipality scope applied (`household.php`).
- **Chips:** none.

### 4.3 Transactions (`transactions/index.blade.php`, `TransactionController::data`)
- **Mechanism:** server-side DataTables AJAX; **GET form** with **8 filter fields**.
- **Feed params:** `program`, `status`, `municipality`, `barangay`, `date_applied_start`,
  `date_applied_end`, `date_paid_start`, `date_paid_end` (POSTed via `ajax.data` in the table, lines 269-280).
- **Chips:** **already implemented inline** — `$filterChipDefs` (lines 83–99) built from `request()`
  query params; chip removal links are GET deep-links that drop one query key (§ lines 140–154).
  Uses class `filter-chip` + **`filter-chip-clear`** (see inconsistency §7.2).
- **ACL:** program restrictions enforced in feed (`programsForUser`), municipality scope.

### 4.4 Scholars (`scholars/index.blade.php`, `ScholarController::data`)
- **Mechanism:** server-side DataTables AJAX; tabbed interface (5 tabs: Scholars / GIP Profiles /
  Scholarship Reports / Update Log / Grantee Self-Update).
- **Feed params:** search only (`search.value`); **no program/status/municipality filter params**.
- **ACL:** municipality scope applied via client IDs (`scholars.php`).
- **Chips:** none.

### 4.5 Scholarship Reports (`scholarship_reports/index.blade.php`, `ReportController::data`)
- **Mechanism:** server-side DataTables AJAX; GET filter bar.
- **Feed params:** `municipality`, `barangay` (cascade from municipality), `program`, `submitted`,
  `date_from`, `date_to`.
- **Chips:** none (plain selects + date inputs).
- **`submitted` filter:** **accepted but ignored in the feed** (v1 parity — v1's feed never reads it); it
  IS honored in the filtered CSV export via EXISTS subqueries. → DECISION #8.

### 4.6 Payouts (Payout Attendance) (`payouts/attendance.blade.php`, `PayoutAttendanceController::data`)
- **Mechanism:** server-side DataTables AJAX; one shared view driven by `config('payout.attendance')`
  (3 variants: scanned_payouts, scanned_payouts2, scanned_payouts_unpaid).
- **Feed params:** `municipality`, `program`, `scanned_start`, `scanned_end`.
- **Chips:** none (Apply/Reset buttons). `#viewBody`/`viewModal` referenced by a `viewModal` variable but
  the modal is not declared in this view — minor pre-existing note.

### 4.7 Unpaid Verifications (`unpaid_verifications/index.blade.php`, `UnpaidVerificationController::data`)
- **Mechanism:** server-side DataTables AJAX; GET filter bar.
- **Feed params:** `municipality`, `date_from`, `date_to` (mirrors v1 `fetch_unpaid_verifications.php`).
- **Chips:** none.

### 4.8 Audit Logs (`admin/audit_logs/index.blade.php`, `AuditController::data`)
- **Mechanism:** **hybrid** — feed is server-side for the dataset and for the users/actions option arrays,
  but the **column filtering + date filtering are CLIENT-SIDE** over the server-fed rows (per work-state
  and v1 mirror: v1 `audit_logs.php` filters user/action client-side via jQuery).
- **Feed params:** (users/actions arrays + dataset; client-side `userFilter`/`actionFilter`/date range).
- **Chips:** none.

### 4.9 Users (`admin/users/index.blade.php`, `UserController::data`)
- **Mechanism:** server-side DataTables AJAX.
- **Feed params:** search only (`search.value` over username/role).
- **Filters present:** **none** beyond server-side search. → DECISION #3.

### 4.10 GIP Profiles (tab inside `scholars/index.blade.php`)
- **Mechanism:** server-side DataTables AJAX (GIP tab table).
- **Feed params:** search only (mirrors v1 scholar feed; no program filter in v1).
- **Chips:** none. Prototype exposes no GIP host. → DECISION #10.

### 4.11 Summary matrix

| Module | Mechanism | Current feed filter params | Current chips |
|---|---|---|---|
| Clients | server-side DT | municipality, barangay | no |
| Households | server-side DT | municipality, barangay | no |
| Transactions | server-side DT | program, status, municipality, barangay, date_applied_*, date_paid_* | **yes** (inline) |
| Scholars | server-side DT | (search only) | no |
| Scholarship Reports | server-side DT | municipality, barangay, program, submitted*, date_from, date_to | no |
| Payouts | server-side DT | municipality, program, scanned_start/end | no |
| Unpaid Verifications | server-side DT | municipality, date_from, date_to | no |
| Audit Logs | server feed + client filter | users/actions arrays; client-side user/action/date | no |
| Users | server-side DT | (search only) | no |
| GIP | server-side DT (tab) | (search only) | no |

---

## 5. v1 Semantics (authoritative — v2 must mirror)

Read from the read-only v1 codebase (`C:\xampp\htdocs\system`). All v1 feeds use **exact equality (`=`)**
for dropdown filters and `AND` across categories. Multi-select did not exist in v1 — each category was a
single-select (or none).

| v1 feed | Filter params | Notes |
|---|---|---|
| `fetch_clients.php` | `municipality`, `barangay` | No program/sex/civilStatus/category filters in v1 |
| `fetch_households.php` | `municipality`, `barangay` | |
| `fetch_transactions.php` | `program`, `municipality`, `barangay`, `status` | exact `=` each; `AND` across |
| `fetch_scholars.php` | search only | No program/status/municipality in v1 feed |
| `fetch_scholarship_reports.php` | `municipality`, `barangay`, `program`, `date_from`, `date_to` | `submitted` not read by feed |
| `fetch_scanned_payouts.php` | `municipality`, `program`, `scanned_start`, `scanned_end` | |
| `fetch_unpaid_verifications.php` | `municipality`, `date_from`, `date_to` | |
| `audit_logs.php` | **client-side** user + action filters; table whitelist (`tbl_clients`, `tbl_transactions`) | |
| users / manage | none | v1 had no user filters |
| GIP (`scholars.php`/gip) | search only | v1 no program filter implied |

**Modernization principle:** preserve v1 fields/business rules; adopt prototype structure/UX. New filters
not present in v1 must be flagged (see decision blocks) — never silently added.

---

## 6. Backend Contracts FilterChips Must Target

### 6.1 Server-side DataTables model
FilterChips must translate chip selections into the **feed params each v2 `data()` already reads**
(§4.1–4.10). Because feeds use single-select equality today, introducing **multi-select OR-within-category**
requires a feed-side addition (e.g. `whereIn`) OR a comma-joined param the feed must split. This is a
**deliberate contract extension** — additively to a `data()` method, never touching v1 tables/rows.

### 6.2 ACL composition (must be preserved exactly)
From `AccessControlService.php`:
- `SUPER_ADMIN_PAGE = '*'` (own page bypass).
- `ALL_MUNICIPALITY_MARKER = 0` (means "all municipalities", distinct from `'*'`).
- Municipality scope: `applyMunicipalityScope($query, $user, $column, $pageName)` — restricted users get an
  `IN` filter on their municipalities; marker `0` = all.
- Program permissions: `canAccessProgram($user, $programName)`; feeds use `programsForUser()` (e.g.
  `TransactionController`, `ScholarController`, `ReportController`).
- **FilterChips option lists must be ACL-filtered** (a user may not see filter values for programs/
  municipalities they cannot access). The feed-side filter application must happen **after** the ACL scope
  is applied, never before, so a hostile param cannot widen scope.

### 6.3 Existing shared chip partial & classes
- `resources/views/partials/active-filters.blade.php` defines the **planned shared contract**:
  `$activeFilters = [['label'=>, 'removeUrl'=>]]` + optional `$clearUrl`. It is a **stub**, not yet wired.
- `.filter-chip` / `.filter-chip-remove` are static component classes in `resources/css/app.css` (503–511)
  and present in the built stylesheet.

### 6.4 Tailwind explicit allowlist
- Production styling is Tailwind-first, **allowlist-scanned** (`source(none)` + explicit `@source` lines in
  `resources/css/app.css`).
- Any **new partial that includes Tailwind utility classes** (e.g. a new filter toolbar Blade partial) needs an
  `@source` line added **in the same change** that first includes it. `active-filters.blade.php` is NOT
  currently in the allowlist (comment on line 9-12 confirms this requirement).
- Static component classes (`.filter-chip*`) are already in the built stylesheet regardless of scanning.

### 6.5 JS delivery architecture (important caveat)
- Vite only builds `resources/js/app.js` (→ `public/build/assets/app-*.js`). There is **no** `public/js` and
  **no git-tracked** `resources/js/components/*` (DetailsPanel.js is untracked; `public/js` does not exist
  in the working tree).
- Yet every list view loads `<script src="{{ asset('js/components/DetailsPanel.js') }}">` (clients,
  households, transactions, scholars, payouts, unpaid, audit, users). **That asset currently resolves to
  `/js/components/DetailsPanel.js`, which 404s in a clean checkout.** The source lives at
  `resources/js/components/DetailsPanel.js` but is neither Vite-built nor copied to `public/js`.
- The prior PHASE_2_INSPECTION_REPORT (§873) and pre-implementation confirmation recommended placing
  `FilterChips.js` at **`public/js/components/FilterChips.js`**, matching the DetailsPanel `asset()` pattern.
- **This delivery gap must be resolved/applied to FilterChips** — see DECISION #11.

---

## 7. Component API Design (proposed — NOT implemented)

### 7.1 Conceptual architecture (PHASE_2_PLAN §12)
```
FilterChips.js (shared IIFE)
   ├── Clients
   ├── Households
   ├── Transactions
   ├── Scholars
   ├── GIP
   ├── Scholarship Reports
   ├── Payouts
   ├── Users
   ├── Audit Logs
   └── Unpaid Verifications
```
Each module supplies **configuration/data** (categories, options, feed-param mapping); the component owns
the interaction + chip rendering.

### 7.2 Inconsistency found in current Transactions chips
- Transactions inline chips use `filter-chip-clear` for the × glyph (line 150), but **no CSS rule exists**
  for `filter-chip-clear`; app.css defines `.filter-chip-remove`. So the Transactions × glyph is currently
  **unstyled** (falls back to default text `×`). A shared component should standardize on one remove class.

### 7.3 Data contract sketch (proposed)
```js
FilterChips.attach({ screen, endpoint, categories: [
  { key:'program', label:'Program', searchable:true,
    options: () => aclSafePrograms, feedParam:'program', mode:'or' },
  ...
], onFilter: (state) => dt.ajax.reload(), readFrom: (req) => decodeParams(req) })
```
- Multi-select category emits `feedParam` with selected values (exact feed contract TBD — see DECISION #6/#9/#11).
- Single-select (or no-option) categories remain compatible with existing feeds.

---

## 8. Accessibility Requirements

Derived from prototype CSS + DetailsPanel precedent (focus trap already in DetailsPanel.js:189–207, focus
restoration at 96–98, scroll lock at 47–61).

1. Popover must be `role="dialog"`/combobox with `aria-haspopup`/`aria-expanded` on the toggle (prototype
   uses `[data-filter-toggle]`, `aria-haspopup`, `aria-expanded`).
2. Checkbox options must be real `<input type="checkbox">` with `<label>` (keyboard toggling). `.filter-check`.
3. Per-category search input inside popover (`filter-multi-search`) with `aria-label`.
4. Chips must expose `aria-label="Remove filter {cat}: {value}"` and be reachable (link/button) — the
   Transactions chips already set `aria-label` correctly.
5. Active-count badge + chip row use `aria-live="polite"` (Transactions chips row already does).
6. Focus must be trapped inside the popover while open; `Escape` closes; focus returns to the toggle.
7. Mobile: popover → bottom sheet, touch target ≥ 8px 14px padding (prototype).
8. Focus-visible outlines (gold token) on all interactive chips/toggles (matches DetailsPanel pattern).
9. Live-region announcement when a filter is added/removed.

---

## 9. Responsive Requirements

From prototype §13 + CSS (1202, 1407–1459, 1561, 1757+):
- **Desktop:** popover 280px, table remains beside it.
- **Tablet:** `~50vw`-style adapted panel; popover may widen.
- **Mobile (<768px):** popover becomes a bottom sheet (`bottom:0; left:0; right:0;`); chip padding 8px 14px;
  filter toolbar stacks full-width.

---

## 10. Risks & Constraints

| # | Risk | Mitigation |
|---|---|---|
| R1 | Introducing multi-select OR requires feed-side changes beyond current single-select `=` | Additive `whereIn` / comma-split per feed; add tests; keep single-select backward-compat |
| R2 | Prototype-only client filter categories (e.g. sex/civilStatus) are NOT in v1/v2 clients | Do not add without decision (DECISION #1) |
| R3 | `submitted` report filter is ignored by the feed (v1 parity) | Surface as applied chip regardless; document discrepancy (DECISION #8) |
| R4 | Audit Logs is client-side filtered over server feed — FilterChips must bridge | Either feed filters or keep client-side; must match v1 (DECISION #4) |
| R5 | JS delivery: `asset('js/components/*.js')` currently 404s (DetailsPanel gap) | Resolve delivery for both DetailsPanel and FilterChips (DECISION #11) |
| R6 | Tailwind allowlist: new partials must add `@source` in same change | Add `@source` line(s) exactly when a new partial is first included |
| R7 | URL persistence / pagination / sort / deep-links interplay | Define persistence contract (DECISION #5); server-side reload keeps draw/start/order consistent |
| R8 | Restricted-user option leakage via API | ACL-scope option lists + apply scope before filter in feed |
| R9 | `filter-chip-clear` vs `filter-chip-remove` class mismatch | Standardize on one remove class; fix Transactions glyph |
| R10 | Tests must stay green / additive only | Run baseline before change; add new-feature tests, don't modify existing |

---

## 11. Test Baseline

- `php artisan test`: **239 passed, 1 risky, 1137 assertions** (2026-08-27). The 1 risky is pre-existing.
- `vendor/bin/pint --test`: **passed**.
- No test files modified this phase.
- Implied by these results: any Phase 2C implementation must run the full suite + Pint and stay green.

---

## 12–25. Required Sections (numbered to match the 25-section brief)

Sections 1–11 above cover the brief's first eleven mandated areas (purpose, method, prototype UX, current
v2 implementations, v1 semantics, backend contracts, component API design, accessibility, responsive,
risks, test baseline). The remaining mandated areas — per-module filtered scope, chip semantics mapping,
option-source strategy, backend param contracts, empty/zero-result handling, and the 13 decision blocks —
are enumerated below and addressed inline (S12 defines the chip semantics legend; S13–22 map each module;
S23 defines option sourcing; S24 backend param contracts; S25 empty/zero handling; the 13 DECISION REQUIRED
blocks follow).

### S12. Chip semantics legend
- **Category = source of one chip value.** Chips group values by category.
- Multiple values within a category → **OR**; multiple categories → **AND** (matches plan §10.1–10.2 and
  prototype `filterMatches`).
- Date ranges render as a **pair of chip**s (From/To) or a single "Applied: 2026-01-01 → 2026-02-01" chip
  (format TBD — DECISION #9).
- A feed that is currently single-select can be extended to multi-select OR only where v1 allowed a choice
  AND the business rule permits multiple (e.g. status is a true choice; date is a range).

### S13–S22. Per-module filtered scope (see matrix in §4.11, plus decisions)

| Module | Confirmed current scope | v1 parity | Prototype scope (may need trimming) | Decision |
|---|---|---|---|---|
| Clients | municipality, barangay | municipality, barangay | + program/category/sex/civilStatus/status | #1 |
| Households | municipality, barangay | municipality, barangay | muni (only) | — |
| Transactions | program, status, municipality, barangay, date_applied_*, date_paid_* | program, muni, brgy, status | program/type/status | #7 (replace vs adapt) |
| Scholars | search only | search only | program/status | #2, #13 |
| Scholarship Reports | muni, brgy, program, submitted*, date_from/to | same (minus submitted) | municipality/barangay/program/submission/date | #8 |
| Payouts | muni, program, scanned_start/end | same | muni/program/date | #6, #9 |
| Unpaid Verifications | muni, date_from/to | same | muni/date | #6 |
| Audit Logs | client-side user/action/date | client-side user/action | user/action/date | #4 |
| Users | search only | none | role/status | #3 |
| GIP | search only | search only | (no prototype host) | #10 |

### S23. Option-source strategy
- **Municipality/Barangay:** from `tbl_municipalities` / `tbl_barangays` (ACL-scoped; barangay cascades
  from municipality — see DECISION #6).
- **Program:** from `config('payout...')`/controller `programs` arrays, ACL-filtered via `programsForUser()`
  (e.g. Transaction=programsForUser; Scholar/Report=six `SCHOLAR_PROGRAMS`).
- **Status:** static enum (PAID / PENDING PAYOUT) — present in Transactions; not exposed elsewhere in v1.
- **Users/Action (Audit):** from `AuditController` users/actions arrays (already servable).
- **Client category/sex/civilStatus:** v1 defines these on the client record but they are **not** filterable
  in v1/v2 today; options are statically derivable from the enum columns → prototype-only (DECISION #1).
- Dates: native `<input type="date">` → chip (DECISION #9).

### S24. Backend param contracts (planned, pending DECISIONS #6/#9/#11)
- Single-select stays `feedParam=value` (unchanged).
- Multi-select category → `feedParam` repeated (DataTables `ajax.data` array) **or** comma-joined string
  that the feed `whereIn`s. Exact contract to be decided (DECISION #6/#9). Must be applied AFTER ACL scope.
- Date range → `{cat}_start` / `{cat}_end` (existing convention: Transactions `date_applied_start/end`,
  Payouts `scanned_start/end`, Unpaid `date_from/date_to`).

### S25. Empty / zero-result filters
- A filter category with **zero matching options** (e.g. restricted user, empty barangay cascade) renders as
  disabled/empty in the popover; the feed already handles no-rows. See DECISION #12.
- Prototype keeps options user-selectable even if 0 rows remain; v2 should reflect ACL reality (DECISION #12).

---

## DECISION REQUIRED blocks (13 deferred decisions)

### DECISION REQUIRED #1 — Clients Category / Sex / Civil Status
**Question:** Should FilterChips expose the prototype's `category` / `sex` / `civilStatus` filter categories
for Clients, which exist in neither v1 nor v2 today (v1/v2 Clients filter only municipality + barangay)?

**Current v1/v2 behavior:** Clients feed filters on `municipality` + `barangay` only. `sex`, `civil_status`,
and client `category` are display columns, not filters.
**Prototype behavior:** `FILTER_SPECS.clients` lists program, category, sex, civilStatus, status.
**Options:**
- A. Do NOT add them; Clients get municipality + barangay only (faithful to v1). (Recommended)
- B. Add them as feed-supported filters (requires additive feed work + a `category` source, which is not a
  clear v1 concept — needs a column inventory).
- C. Add as client-side-only display filters (inconsistent with the server-side model; rejected).
**Recommendation:** A. The modernization principle forbids silently adding business filters the prototype
introduced without v1 backing.
**Reason:** v1 defines no client category filter and no v2 feed reads it; adding it changes surfaced
functionality beyond parity and requires ambiguous data definitions.

### DECISION REQUIRED #2 — Scholars Program / Status
**Question:** Should Scholars FilterChips expose `program` and `status`, which v1's `fetch_scholars.php` and
v2's `ScholarController::data()` do not filter today (search only)?

**Current v1/v2:** Scholars feed is search-only (plus ACL municipality scope). No program/status param.
**Prototype:** scholars screen suggests program/status.
**Options:**
- A. Add `program`/`status` as feed-supported filters (additive `whereIn`; program from the six
  `SCHOLAR_PROGRAMS`; status from `tbl_scholar_info` columns). (Recommended)
- B. Keep search-only (v1 parity).
**Recommendation:** A — but ONLY if the Scholar tab's record set is the `tbl_scholar_info` list where program
is a real column; status needs a defined source (scholar row has no explicit status col today → must be
derived). Confirm source before building.
**Reason:** Scholars is a high-traffic list; program/status are natural cut points. Status column mapping
must be verified first (risk R2).

### DECISION REQUIRED #3 — Users filters (v1 has none)
**Question:** Add FilterChips (role/status) to the Users management screen, which has **no filters in v1**?
**Current v1/v2:** UserController::data() is search-over-username/role only; index has no filter controls.
**Prototype:** users section exposes role/status.
**Options:**
- A. Add `role` (and `status` if a status exists on `tbl_users` — verify) as feed filters. (Recommended)
- B. Leave Users with search only (v1 parity).
**Recommendation:** A, gated on confirming the `tbl_users` role/status column names (feed has `role`; verify
a status column/locked flag exists).
**Reason:** Users list is admin-level; role filtering is low-risk and matches prototype. Must confirm columns
before declaring parity-safe.

### DECISION REQUIRED #4 — Audit Logs chips (client-side vs server-side)
**Question:** Audit Logs currently filters user/action/date on the client over a server-fed dataset (v1
mirror). Should FilterChips filter client-side (preserving current behavior) or be pushed server-side?

**Current v1/v2:** `audit_logs.php` + v2 `audit_logs/index.blade.php` filter user/action + date in JS after
the feed returns rows.
**Prototype:** audit exposes user, action, and a date range as chips.
**Options:**
- A. Keep client-side filtering, add FilterChips as the chip/UI layer on top of it. (Recommended)
- B. Add server-side date/user/action params to the feed.
**Recommendation:** A.
**Reason:** v1 parity and simplest contract; the client-side approach already works. Server-side would
duplicate v1 divergence and is unnecessary risk (R4).

### DECISION REQUIRED #5 — URL persistence
**Question:** Should active filters persist in the URL (deep links, refresh, shareable), given Transactions
already uses GET query-string deep-links?

**Current v1/v2:** Transactions serializes filters into GET query params and read `request()` on load;
removal chips are GET deep-links. Other modules use transient DOM state / POST-only AJAX.
**Prototype:** fully client-side state, no URL persistence.
**Options:**
- A. Persist all active filters in the URL query string (consistent with Transactions). (Recommended)
- B. Keep filters in memory only (prototype-like; state lost on refresh).
- C. Hybrid: persist where a screen already GET-serializes (Transactions), keep others in-memory.
**Recommendation:** A — unify on URL persistence for the shared component.
**Reason:** Deep links, refresh survival, and matching the page-header/panel shareability are valuable; the
Transactions precedent already proves the pattern server-side.

### DECISION REQUIRED #6 — Municipality→Barangay cascade
**Question:** When Municipality and Barangay are both chips, must Barangay options **cascade** from the
selected Municipality (v1/v2 forms and Scholarship Reports do this), and how does that interact with
multi-select + URL persistence?

**Current v1/v2:** Scholarship Reports (`filterBarangay`) cascades from `filterMunicipality`; Transactions
and Clients pass both independently.
**Prototype:** households expose only `muni`; prototype has no barangay host (barangay is a geography
cascade in forms).
**Options:**
- A. Cascade: Barangay options derive from selected municipalities; clearing a municipality clears its
  barangays. (Recommended)
- B. Independent parallel selects (current Clients/Transactions behavior).
**Recommendation:** A.
**Reason:** Matches v1's cascade UX and avoids selecting meaningless barangays; must reconcile with
multi-select + URL state (a barangay chip implies a parent municipality chip).

### DECISION REQUIRED #7 — Replace vs adapt Transactions chips
**Question:** Transactions already ships a working inline server-side chip system (GET deep-links, 8
params, applied chips). Should FilterChips **replace** it with the shared component, or should the shared
component **adopt** it as the reference wiring and fold Transactions in with minimal delta?

**Current v2:** Inline `$filterChipDefs` + GET deep-links (§4.3) — functionally complete, unique among modules.
**Prototype:** same visual chip concept.
**Options:**
- A. Adopt: refactor Transactions onto the shared component, preserving its exact feed params and deep-link
  behavior (removal links = same query outcome). (Recommended)
- B. Replace from scratch, re-specifying params.
- C. Leave Transactions as the standalone exemplar and scope the shared component to the other modules.
**Recommendation:** A.
**Reason:** Preserves proven behavior and deep-linking; avoids double source of truth (§12 of plan says
"avoid creating ten unrelated implementations"); the shared component should absorb Transactions' contract.

### DECISION REQUIRED #8 — Date-range chip representation + `submitted` report filter
**Question:** How are date ranges represented as chips, and how is the `submitted` Scholarship Reports
filter (accepted but **ignored** by the feed, per v1) displayed?

**Current v1/v2:** Date fields are native `<input type="date">`; the report `submitted` select is accepted
but not read by the feed (export-only).
**Prototype:** audit uses a date range state; report screen lists submission state.
**Options:**
- A. Fold each date upper/lower bound into a single chip per range ("Applied 2026-01-01 → 2026-02-01"),
  and render `submitted` as an applied chip even though the feed ignores it (documenting the v1 parity
  discrepancy, keeping the chip honest to what was selected). (Recommended)
- B. Keep separate From/To chips; drop `submitted` from chips.
**Recommendation:** A.
**Reason:** A single range chip is cleaner and matches how a range is conceptually one filter; `submitted`
must surface as a chip (honoring user intent) while the feed's ignore-behavior is documented as v1 parity.

### DECISION REQUIRED #9 — Server-side vs Blade-rendered options
**Question:** Should FilterChips option lists (municipalities, programs, statuses, audit users/actions) be
rendered by **Blade into the page** (as today's selects) or fetched/derived **client-side from a server
endpoint**?

**Current v1/v2:** All option lists are Blade-rendered into the DOM from controller data (municipalities,
`programsForUser`, static enums; audit users/actions via a server array feed).
**Prototype:** all-options derived client-side from in-memory records.
**Options:**
- A. Blade-render options (extend existing pattern; ACL-scope in controller). (Recommended)
- B. Expose a JSON options endpoint the component fetches.
- C. Derive single-select options client-side from feed metadata.
**Recommendation:** A.
**Reason:** Reuses existing controller data, keeps ACL scoping in PHP (no privilege leak), minimal new
endpoints; search-within-popover only needs the already-rendered list.

### DECISION REQUIRED #10 — GIP Filters
**Question:** Does GIP Profiles (the tab inside Scholars, and the separate `clients/_gip.blade.php`) get
FilterChips, and what categories?

**Current v1/v2:** GIP is search-only (v1 scholar feed has no program/status filter); prototype exposes **no
GIP host**.
**Prototype:** no `data-filter-host` for GIP.
**Options:**
- A. GIP gets search only (no chips), matching prototype host absence and v1. (Recommended)
- B. Add a Program chip to GIP.
**Recommendation:** A.
**Reason:** No prototype host exists and v1 defines no GIP filter; adding one would be scope creep.

### DECISION REQUIRED #11 — FilterChips.js delivery location
**Question:** Where does the shared `FilterChips.js` live and how is it delivered, given the existing
DetailsPanel.js delivery gap (`asset('js/components/*.js')` 404s today)?

**Current state:** Vite builds only `resources/js/app.js`; `public/js/` does not exist; every index view
references non-existent `asset('js/components/DetailsPanel.js')`. Prior report recommends
`public/js/components/FilterChips.js`.
**Options:**
- A. Ship built/unbuilt JS from `public/js/components/*.js` via `asset()` and ALSO fix DetailsPanel's
  delivery (add to Vite input or a copy step). (Recommended)
- B. Fold both into the Vite bundle (`resources/js/`) and reference via `@vite`.
- C. Keep the current (broken) convention as-is for FilterChips.
**Recommendation:** A.
**Reason:** It matches the established `asset()` pattern and fixes the latent DetailsPanel 404; the copy/build
step must be deterministic (script or Vite public-dir copy) and documented.

### DECISION REQUIRED #12 — Empty / zero-result filter options
**Question:** Should filter options that yield no rows (empty cascade, ACL-hidden values) be
selectable/visible?

**Current v1/v2:** Cascade hides empty barangays; ACL already removes restricted options in Blade.
**Prototype:** options remain user-selectable regardless of count.
**Options:**
- A. Do not render options that are empty or ACL-forbidden (server-driven reality). (Recommended)
- B. Render all options, disabled when empty.
- C. Mirror prototype: render with counts, allow zero-count selection.
**Recommendation:** A.
**Reason:** ACL safety and UX (prevents dead-end selections); prototype's fixed dataset has no ACL concept.

### DECISION REQUIRED #13 — Search as chip vs separate field / Scholar tab persistence
**Question:** (a) Should the global search remain a **separate field** (prototype) composed AND with chips,
or become a chip? (b) Should active chips persist per Scholar sub-tab (the Scholars screen is tabbed)?

**Current v1/v2:** Search is a separate DataTables search box in every module (server-side `search.value`);
Scholars tabs are in-memory DOM toggles (no persistence).
**Prototype:** search is a separate field composed AND with filters; tab state is in-memory.
**Options:**
- (a) A. Keep search as a separate field composed AND with chips. (Recommended) / B. Search as a chip.
- (b) A. Persist chips per Scholar tab (each tab = its own feed/settings). (Recommended) / B. Single shared
  filter state for the whole Scholars screen.
**Recommendation:** A for both.
**Reason:** Matches prototype and DataTables model (search already server-side); per-tab persistence is
correct because each Scholar tab is a different feed (Scholars/GIP/Reports) with distinct filter scopes.

---

## 26. Follow-up / Next Steps (after decisions)

1. Render decisions; if any option requires feed changes, spec the **additive** `whereIn`/param additions
   (never touching v1 tables/rows) and confirm ACL-before-filter ordering.
2. Implement the shared `FilterChips.js` + `filter-chips.blade.php` (post-decision) and resolve the JS
   delivery gap (DECISION #11), including fixing the Transactions `filter-chip-clear` glyph.
3. Add the required `@source` line when a new partial is first included (R6).
4. Add new-feature tests; keep the existing suite green (R10). Pint clean.
5. Document the completed change in `docs/IMPLEMENTATION_LOG.md` and sync affected docs (README /
   BLUEPRINT / ARCHITECTURE_DECISION / MIGRATION docs) only if affected.
6. Update `docs/SESSION_HANDOFF.md` with Phase 2C status.
