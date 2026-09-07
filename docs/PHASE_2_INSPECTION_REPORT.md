# Phase 2 Pre-Implementation Inspection Report

**Date:** 2026-08-27  
**Status:** Complete — Awaiting Review  
---

# 1. Executive Summary

This report documents the complete pre-implementation inspection for Phase 2 of the 2DMIS v2 modernization. The inspection covered:

- The approved PHASE_2_PLAN.md (1,282 lines)
- The prototype (PROTOTYPE_SPEC.md, index.html, app.js, style.css)
- The entire current v2 codebase (24 controllers, 13 services, 25 models, 11 DataTables views, 8 partials)
- The v1 legacy codebase at `C:\xampp\htdocs\system` (~115 PHP files)
- The existing test suite (212 passed, 1 risky, 1,056 assertions)

**Key finding:** Phase 2 is achievable within the current architecture without database changes, new frameworks, or破坏性 refactoring. The prototype's UX patterns are adoptable while preserving all v1 functionality. However, several important gaps exist between the prototype's mock data and the actual v1 data model that must be resolved before implementation.

---

# 2. Current Phase 2 Readiness

## 2.1 What Exists (Ready)

| Component | Status | Notes |
|---|---|---|
| DetailsPanel.js | **Complete** | Shared, AJAX-driven, module-routed. Stable Phase 1 contract. |
| DetailsPanel partial | **Complete** | `data-panel-*` contract with all markers. |
| DetailsPanel CSS | **Complete** | Inline in details-panel.blade.php, responsive breakpoints defined. |
| Confirm modal | **Complete** | `window.uiConfirm()` shared confirmation dialog. |
| Global search | **Partial** | Client-registry-only search. No cross-module search. |
| Notification button | **Stub only** | Bell icon HTML exists. No dropdown, no data, no JS. |
| Breadcrumb partial | **Stub only** | Renders nothing for single-crumb pages. Navbar has hardcoded 2-level trail. |
| Active filters partial | **Stub only** | `active-filters.blade.php` exists but is unused by any screen. |
| Shared CSS tokens | **Complete** | `public/css/ui.css` has full design token system matching prototype. |
| ACL service | **Complete** | 5-dimension authorization with per-request caching. Singleton. |
| All DataTables feeds | **Complete** | Server-side POST with draw/recordsTotal/recordsFiltered/data contract. |

## 2.2 What Does Not Exist (Gaps)

| Component | Status | Notes |
|---|---|---|
| Dashboard KPIs | **Missing** | No total clients count, no transaction count, no disbursed amount, no pending count. |
| Dashboard program distribution | **Missing** | No GROUP BY program query exists anywhere in v1 or v2. |
| Dashboard announcements | **Missing** | No announcement system in v1 or v2. No database table. |
| Dashboard calendar | **Missing** | No calendar system in v1 or v2. No event data. |
| Dashboard activity feed | **Missing** | Audit logs exist on a separate page. Not on dashboard. |
| Shared FilterChips component | **Missing** | Each module has its own inline filter implementation. |
| Notification backend | **Missing** | No notification model, table, or controller. |
| Dashboard controller data | **Missing** | `DashboardController::index()` passes zero data to the view. |

## 2.3 Test Baseline

```
Tests:    1 risky, 212 passed (1056 assertions)
Duration: 31.09s
```

The risky test is `HouseholdTest::test_households_pages_load_for_permitted_user` (output-buffering warning). Investigation shows the current code does not have an obvious output-buffering cause; the issue may manifest only under certain DB conditions.

---

# 3. Current Architecture Relevant to Phase 2

## 3.1 File Structure Summary

```
app/
  Http/Controllers/     24 controllers
  Services/             13 services (AccessControlService is singleton)
  Models/               25 models
resources/
  views/
    layouts/app.blade.php     — single master layout
    partials/
      navbar.blade.php        — topbar with search + notification stub
      sidebar.blade.php       — ACL-driven navigation
      details-panel.blade.php — shared panel markup + CSS
      breadcrumbs.blade.php   — stub (renders nothing for 1 crumb)
      global-search.blade.php — client-only search
      page-header.blade.php   — title/subtitle/actions slot
      confirm-modal.blade.php — shared confirm dialog
      active-filters.blade.php — stub (unused)
    dashboard.blade.php       — quick actions + recent transactions only
    clients/index.blade.php   — DataTables with filters
    transactions/index.blade.php — DataTables with GET-based filters + chips
    ... (11 DataTables views total)
  js/
    components/DetailsPanel.js — shared panel controller (241 lines)
public/
  css/ui.css                  — design token system (499 lines)
```

## 3.2 Navigation Catalog

The `$shellSections` array in `layouts/app.blade.php` (lines 28-61) is the **single source of truth** for sidebar and topbar navigation. Both `sidebar.blade.php` and `navbar.blade.php` consume it. Any new navigation item must be added here.

Current sections: Overview (Dashboard), Registry (Clients, Households), Assistance (Scholars sub-items, Transactions, Scanner sub-items, Payout sub-items), Administration (Users, Audit Logs).

## 3.3 DataTables Pattern

All 11 DataTables views follow the same pattern:
1. CDN-loaded jQuery 3.7.1 + DataTables 1.13.6 + Bootstrap 5 integration
2. Server-side POST with `ajax.data` callback sending filters
3. Row click opens DetailsPanel via `data-panel-*` attributes
4. Municipality→barangay cascade via `geography.barangays` JSON endpoint
5. Per-view inline `<script>` blocks (no shared DataTables initializer)

## 3.4 Filter Architecture (Current)

Two competing models exist:

**Model A — AJAX apply+redraw (most screens):** Filter controls in a toolbar, `#applyFilters` button calls `table.draw()`. Used by: clients, households, scholars, payouts, duplicates, unpaid verifications, scholarship reports.

**Model B — GET deep-link (transactions only):** Filters submitted as GET query params. Server renders chips via `$appliedFilters` array. The `active-filters.blade.php` partial exists but transactions builds its own inline chips instead.

---

# 4. Prototype vs Current v2 Comparison

## 4.1 Dashboard

| Prototype Widget | v2 Current | Gap |
|---|---|---|
| KPI cards (4 metrics) | None | **Full gap** — no queries exist |
| Quick Actions | Exists (3 actions, ACL-gated) | **Aligned** |
| Recent Transactions | Exists (5 rows via AJAX) | **Aligned** — may need row layout update |
| Program Distribution | None | **Full gap** — no GROUP BY program query |
| Announcements | None | **Full gap** — no data source |
| Activity Calendar | None | **Full gap** — no event data |
| Activity Feed | None | **Gap** — audit data exists but not surfaced on dashboard |

## 4.2 Topbar / Global UX

| Prototype Feature | v2 Current | Gap |
|---|---|---|
| Breadcrumb: "2DMIS > Page" | Hardcoded in navbar (works) | **Minor** — needs consistency check |
| Global search input | Client-only search | **Partial** — no cross-module search |
| Notification bell + dropdown | Bell icon stub only | **Full gap** — no dropdown, no data |
| Online indicator | Not present | **Gap** — session status polling exists but no indicator |
| User dropdown | Bootstrap dropdown (works) | **Aligned** |

## 4.3 Filter Chips

| Prototype Pattern | v2 Current | Gap |
|---|---|---|
| Filter button → popover → checkboxes | Not implemented | **Full gap** per module |
| Searchable filter lists | Not implemented | **Full gap** per module |
| Active filter chips display | Stub exists, unused | **Gap** — needs adoption |
| Clear All button | Stub exists, unused | **Gap** — needs adoption |
| Multi-select OR within category | Not implemented client-side | **Full gap** |
| AND across categories | Not implemented client-side | **Full gap** |

## 4.4 Details Panel

| Prototype Pattern | v2 Current | Gap |
|---|---|---|
| 480px desktop panel | Implemented (Phase 1) | **Aligned** |
| 50vw tablet panel | Implemented (Phase 1) | **Aligned** |
| Full-width mobile drawer | Implemented (Phase 1) | **Aligned** |
| ESC close | Implemented (Phase 1) | **Aligned** |
| Focus trap | Implemented (Phase 1) | **Aligned** |
| Row click → panel | Implemented (Phase 1) | **Aligned** |

## 4.5 Responsive Behavior

| Prototype Pattern | v2 Current | Gap |
|---|---|---|
| Collapsible sidebar on mobile | Implemented via Bootstrap offcanvas | **Aligned** |
| Single-column mobile layout | Partially (uses Bootstrap grid) | **Verify** |
| Touch-friendly targets | Not verified | **Gap** — needs audit |
| Table horizontal scroll | Exists in prototype CSS | **Needs** verification in v2 |

---

# 5. v1 Functionality Preservation Analysis

## 5.1 Critical Finding: v1 Dashboard Has ZERO Metrics

The v1 `index.php` is a **minimal scanner-card landing page** with:
- A greeting card ("Hello, {username}")
- 13 scanner card links

There are **no** KPI counts, no charts, no aggregated metrics, no program distribution data, no announcements, no calendar, no activity feed in v1.

**Implication:** All dashboard metrics in Phase 2 are **net-new features**. They do not port v1 behavior — they create new functionality that the prototype proposes. The v1 system's "dashboard" is purely a navigation hub.

## 5.2 v1 Functionality That Must Be Preserved

| v1 Feature | v2 Status | Phase 2 Impact |
|---|---|---|
| Scanner card access from dashboard | Quick Actions exist | None — already preserved |
| Municipality-scoped data access | ACL service handles this | None — must not be broken |
| Program permission enforcement on transactions | TransactionController enforces this | None — must not be broken |
| Multi-column search on clients | ClientController::data handles this | None — already preserved |
| Audit log writing on all CRUD | AuditService handles this | None — already preserved |
| CSV export with UTF-8 BOM | All export controllers handle this | None — already preserved |

## 5.3 v1 Filter Fields vs Prototype Filter Fields

| Module | v1 Filters | Prototype Filters | Discrepancy |
|---|---|---|---|
| Clients | Municipality, Barangay | Municipality, Barangay | **Aligned** |
| Transactions | Program, Status, Municipality, Barangay, Date Applied, Date Paid | Program, Status, Municipality, Barangay, Date range | **v1 has more** — preserve all |
| Households | Municipality, Barangay | Municipality | **v1 has more** — preserve barangay |
| Scholars | Program, Status (in v2) | Program, Status | **Aligned** |
| Payouts | Municipality, Program, Date | Municipality, Program, Date | **Aligned** |
| Users | Role, Status (in v2) | Role, Status | **Aligned** |
| Audit | Module, Actor, Action, Date | Module, Actor, Action, Date | **Aligned** |
| Unpaid | Municipality, Date | Municipality, Date | **Aligned** |

---

# 6. Dashboard Data Mapping

## 6.1 KPI Cards

### Total Clients
- **Data source:** `COUNT(*) FROM tbl_clients` (with municipality scope)
- **Existing query:** `ClientController::data` line 175 (`$total` query)
- **New endpoint needed:** Yes — `DashboardController::index()` needs to pass count or new JSON endpoint
- **ACL constraint:** Municipality scope must apply
- **Approach:** Add a `DB::table('tbl_clients')` count query in DashboardController with `AccessControlService::applyMunicipalityScope()`

### Assistance Transactions
- **Data source:** `COUNT(*) FROM tbl_transactions` (with municipality scope + program permissions)
- **Existing query:** `TransactionController::data` line 310 (`$total` query)
- **New endpoint needed:** Yes
- **ACL constraint:** Municipality scope + program permissions
- **Approach:** Add a count query with scope and program permission enforcement

### Total Amount Disbursed
- **Data source:** `SUM(amount_paid) FROM tbl_transactions WHERE status = 'PAID'` (with scope)
- **Existing query:** None. v1 has no such query.
- **New endpoint needed:** Yes
- **Business rule ambiguity:** Does "disbursed" mean `amount_paid` where status = PAID? Or `amount` (requested amount)? The prototype shows "₱2.4M" but has no definition.
- **Recommendation:** Use `SUM(amount_paid) WHERE status = 'PAID'` as the most accurate representation of actual disbursement. Flag this as a decision required.

### Pending Approvals
- **Data source:** `COUNT(*) FROM tbl_transactions WHERE status = 'PENDING'` (with scope)
- **Existing query:** None. v1 has no such query.
- **New endpoint needed:** Yes
- **Business rule ambiguity:** What statuses count as "pending"? The TransactionService defines statuses as: BOOKED, PAID, PENDING (3 values). The v2 system uses these exact strings. "Pending" likely means `status = 'PENDING'`.
- **Recommendation:** Count transactions where `status = 'PENDING'`. Confirm with user.

## 6.2 Quick Actions
- **Already implemented:** Add Client, Register Household, New Transaction
- **ACL-gated:** Yes, via `$acl->canAccessPage()` and `$acl->canAccessAction()`
- **No changes needed** beyond potential visual alignment with prototype

## 6.3 Recent Transactions
- **Already implemented:** Fetches 5 rows from `transactions.data` endpoint
- **No changes needed** beyond potential visual alignment

## 6.4 Program Distribution
- **Data source:** `GROUP BY program_name FROM tbl_transactions` with `COUNT(*)` per program
- **Existing query:** None anywhere in v1 or v2
- **New endpoint needed:** Yes — either a new JSON endpoint or embedded in dashboard controller
- **ACL constraint:** Municipality scope + program permissions must apply
- **Business rule:** This is entirely new functionality. No v1 behavior to preserve.
- **Approach:** New query in DashboardController or new JSON endpoint. Present as horizontal bar chart (matching prototype).

## 6.5 Announcements
- **Data source:** None exists
- **v1 behavior:** None
- **Prototype shows:** 2 hardcoded announcements with icon, title, description, date
- **Phase 2 plan says:** "If no existing announcement data source exists, a presentation-level/static implementation may be used temporarily."
- **Approach:** Static/hardcoded announcements in the Blade template. No backend needed.

## 6.6 Activity Calendar
- **Data source:** None exists
- **v1 behavior:** None
- **Prototype shows:** A monthly calendar with event dots on specific dates (10th, 18th, 25th)
- **Phase 2 plan says:** "If the prototype's calendar requires information that does not exist in 2DMIS, do not invent business semantics. Mock/static presentation data may be used."
- **Approach:** Static calendar widget with today highlighted and mock event dots. No backend needed.

## 6.7 Activity Feed
- **Data source:** Audit logs exist in `tbl_audit_logs`
- **Existing controller:** `AuditController::data()` returns rows with user names, actions, targets
- **v1 behavior:** Audit logs are on a separate page only
- **Phase 2 plan says:** "The activity feed should use existing audit information where appropriate."
- **Approach:** New JSON endpoint (or reuse `admin.audit-logs.data` with a limit) returning the 5 most recent audit entries. Display as avatar + action description + timestamp.

---

# 7. Global Search Analysis

## 7.1 Current Implementation

**File:** `resources/views/partials/global-search.blade.php` (55 lines)

- Client-side only (no backend search endpoint)
- Debounced input (250ms)
- On `/clients` or `/dashboard`: forwards to `window.clientsTable.search(query).draw()`
- On Enter: redirects to `/clients?search={query}`
- Does NOT search transactions, scholars, households, or any other module

## 7.2 Prototype Behavior

The prototype global search:
- Searches "clients, transactions, programs..."
- Shows results in a dropdown
- Client results are clickable

## 7.3 Phase 2 Requirements

The PHASE_2_PLAN says:
> "The topbar global search should provide a useful entry point into the Clients registry."

This means the search does NOT need to be cross-module. It should:
1. Accept input in the topbar
2. Debounce
3. Search clients
4. Optionally navigate to Clients page with search applied

## 7.4 Recommendation

Enhance the existing global-search partial:
- Keep the current client-only behavior
- Add a debounced AJAX call to `clients.data` endpoint for instant results
- Show a dropdown with top 5 matching clients
- Clicking a result opens the DetailsPanel for that client
- Enter navigates to `/clients?search={query}`
- The current implementation is 80% there — needs the dropdown UI

---

# 8. Notification UI Analysis

## 8.1 Current State

**File:** `resources/views/partials/navbar.blade.php` lines 55-62

- Bell icon button with `id="notifBtn"`
- Hidden badge showing count "0"
- No dropdown menu
- No JavaScript handlers
- No data source

## 8.2 Prototype Behavior

The prototype has:
- Bell button with red badge showing unread count
- Dropdown panel with header ("Notifications" + "Mark all read")
- 4 mock notification items with icon, title, text, time
- "View all notifications" footer link

## 8.3 Phase 2 Requirements

The PHASE_2_PLAN says:
> "Phase 2 may implement the notification dropdown as a UI-level component. A full notification persistence/backend subsystem is not required for Phase 2."

## 8.4 Recommendation

Implement a static notification dropdown:
- Add the dropdown HTML after the bell button in navbar.blade.php
- Add minimal JS to toggle the dropdown on click
- Use hardcoded mock notification items (matching prototype pattern)
- Badge shows mock unread count
- "Mark all read" visually clears the badge (client-side only)
- No backend, no model, no database changes

---

# 9. Filter Chips Architecture Analysis

## 9.1 The Core Challenge

The prototype proposes a **shared FilterChips component** that works identically across all modules. Currently:

- Each module has its own inline filter implementation
- Two competing paradigms exist (AJAX redraw vs GET deep-link)
- No shared JavaScript filter component exists
- The `active-filters.blade.php` partial is a stub

## 9.2 Proposed Architecture

```
FilterChips.js (new shared JS component)
  │
  ├── Reads module-specific filter config
  │     (filter categories, options, server-side field names)
  │
  ├── Manages filter state (selected values per category)
  │
  ├── Renders:
  │     ├── Filter buttons with popover menus
  │     ├── Searchable checkbox options
  │     ├── Active filter chips
  │     └── Clear All button
  │
  └── Integrates with existing DataTables:
        ├── Sends filter state as POST data
        └── Triggers table.draw() on change
```

## 9.3 Key Design Decision

The FilterChips component should work **client-side** but integrate with **server-side DataTables feeds**. The component manages UI state; the existing DataTables `ajax.data` callback reads that state and sends it to the server.

This means:
- No new backend endpoints needed for filter chips
- Existing DataTables feeds continue to work
- The component replaces the inline filter toolbars

## 9.4 Module Filter Config Required

For each module, a config object must specify:
- Filter categories (keys, labels, whether searchable)
- Option lists (static or dynamic from data)
- Server-side field names (what the DataTables feed expects)

---

# 10. Per-Module Filter Analysis

## 10.1 Clients

**Current filters:** Municipality (select), Barangay (cascading select), Search (text input)
**Prototype filters:** Municipality, Barangay (plus Category, Sex, Civil Status, Status — all client-side in prototype)
**v1 filters:** Municipality, Barangay
**Server-side fields:** `municipality`, `barangay`, `search`
**Recommendation:** Adopt Municipality + Barangay filter chips. Category/Sex/Civil Status/Status are prototype-only and would require new server-side filter support — defer unless explicitly requested.

## 10.2 Transactions

**Current filters:** Program (select), Status (select), Municipality (select), Barangay (cascading select), Date Applied range (2 date inputs), Date Paid range (2 date inputs). Uses GET form with `$appliedFilters` chips.
**Prototype filters:** Program, Status, Municipality, Barangay, Date range
**v1 filters:** Program, Status, Municipality, Barangay, Date Applied range, Date Paid range
**Server-side fields:** `program`, `status`, `municipality`, `barangay`, `date_applied_from`, `date_applied_to`, `date_paid_from`, `date_paid_to`
**Recommendation:** Adopt all existing filters as chips. Date ranges are not suited to checkbox chips — keep as separate date inputs alongside the chip bar. The existing GET-based chip pattern in transactions is closest to the prototype intent.

## 10.3 Households

**Current filters:** Municipality (select), Barangay (cascading select), Search (text input)
**Prototype filters:** Municipality
**v1 filters:** Municipality, Barangay
**Server-side fields:** `municipality`, `barangay`, `search`
**Recommendation:** Adopt Municipality + Barangay filter chips.

## 10.4 Scholars

**Current filters:** Search (text input, debounced), Program (in DataTables data callback), Status (in DataTables data callback)
**Prototype filters:** Program, Status
**v1 filters:** Program, Status (via DataTables)
**Server-side fields:** `search` (DataTables search), `program`, `status` (in draw callback)
**Recommendation:** Adopt Program + Status filter chips.

## 10.5 GIP Profiles

**Current filters:** Search only (within DataTables)
**Prototype filters:** None specific
**Recommendation:** No filter chips needed. Search only.

## 10.6 Scholarship Reports

**Current filters:** Municipality, Barangay, Program, Submitted status, Date range
**Prototype filters:** Municipality, Barangay, Program, Submission state, Date
**Server-side fields:** `municipality`, `barangay`, `program`, `submitted`, `date_from`, `date_to`
**Recommendation:** Adopt Municipality, Barangay, Program, Submitted as chips. Date range as separate inputs.

## 10.7 Payouts

**Current filters:** Municipality, Program, Date range
**Prototype filters:** Municipality, Program, Date
**Server-side fields:** `municipality`, `program`, `date_from`, `date_to`
**Recommendation:** Adopt Municipality + Program as chips. Date range as separate inputs.

## 10.8 Users

**Current filters:** Search only (within DataTables)
**Prototype filters:** Role, Status
**Server-side fields:** `search`
**Recommendation:** Adopt Role + Status filter chips. This requires adding server-side filter support to `UserController::data()`.

## 10.9 Audit Logs

**Current filters:** Module (target table select), Actor (column search), Action (column search), Date range (ext.search plugin)
**Prototype filters:** User, Action, Module, Date
**Server-side fields:** `target_table` (in data callback), column search, ext.search date range
**Recommendation:** Adopt Module, Actor, Action as filter chips. Date range as separate inputs. This requires restructuring the audit DataTables init to use standard filter parameters.

## 10.10 Unpaid Verifications

**Current filters:** Municipality, Date range
**Prototype filters:** Municipality, Date
**Server-side fields:** `municipality`, `date_from`, `date_to`
**Recommendation:** Adopt Municipality as chip. Date range as separate inputs.

---

# 11. DetailsPanel Stability Analysis

## 11.1 Current Contract

The DetailsPanel is a stable Phase 1 deliverable. The contract is:

**JavaScript:** `resources/js/components/DetailsPanel.js` (241 lines)
- `window.DetailsPanel` singleton
- `load(module, entityId, options)` method
- Module route map (8 modules)
- HTML parsing for `data-panel-*` markers
- Focus trap, ESC close, backdrop close, scroll lock
- Focus restoration on close

**HTML:** `resources/views/partials/details-panel.blade.php` (248 lines)
- `#detailsPanel` container
- `#detailsBackdrop` overlay
- Close button
- Title, sub, avatar, meta, actions, body slots
- Full CSS (responsive breakpoints for desktop/tablet/mobile)

**Per-module partials:** Each module has `_details.blade.php` or equivalent that outputs `data-panel-*` markers.

## 11.2 Phase 2 Impact

The PHASE_2_PLAN explicitly states:
> "No redesign of the DetailsPanel contract is required. Phase 2 should preserve and lightly polish the implementation established during Phase 1."

**Recommendation:** Do NOT modify DetailsPanel.js or the panel partial unless a specific regression is found. The panel is stable and well-tested.

## 11.3 Potential Minor Improvements (If Needed)

- Verify responsive breakpoints match prototype (480px desktop, 50vw tablet, 100% mobile) — they already do
- Verify focus trap works with FilterChips popovers open
- No structural changes recommended

---

# 12. Responsive Behavior Analysis

## 12.1 Current Responsive Breakpoints

The prototype CSS defines:
- Desktop: 1024px+
- Tablet: 768px-1023px
- Mobile: below 768px

The v2 CSS (`ui.css` + `details-panel.blade.php`) defines:
- Desktop panel: 480px fixed width
- Tablet panel: 50vw
- Mobile panel: 100% width
- Sidebar: offcanvas on < lg (1024px)

## 12.2 Gaps to Verify

| Area | Status | Notes |
|---|---|---|
| Panel responsive | Implemented | Matches prototype spec |
| Sidebar responsive | Implemented | Bootstrap offcanvas |
| Table overflow | Exists in prototype CSS | Needs verification in v2 DataTables views |
| Touch targets | Not verified | Prototype requires ≥44px; v2 uses Bootstrap defaults |
| Filter popover on mobile | Not implemented | Filter chips need mobile-friendly popover positioning |

## 12.3 Recommendations

- Add `overflow-x: auto` wrapper on all DataTables containers (verify it exists)
- Audit touch target sizes on mobile breakpoints
- Ensure FilterChips popovers don't overflow viewport on mobile
- Test at 320px, 375px, 430px, 576px, 768px, 1024px, 1280px, 1440px

---

# 13. Existing Components That Should Be Reused

| Component | File | Why Reuse |
|---|---|---|
| DetailsPanel.js | `resources/js/components/DetailsPanel.js` | Stable, tested, module-routed |
| Details panel partial | `resources/views/partials/details-panel.blade.php` | Stable HTML + CSS |
| Confirm modal | `resources/views/partials/confirm-modal.blade.php` | `window.uiConfirm()` |
| Active filters partial | `resources/views/partials/active-filters.blade.php` | Designed for filter chips, just needs adoption |
| ACL service | `app/Services/AccessControlService.php` | Central authorization, do not duplicate |
| AuditService | `app/Services/AuditService.php` | Central audit logging |
| All DataTables feeds | Various controllers | Server-side POST contract is stable |
| GeographyController::barangays | `app/Http/Controllers/GeographyController.php` | Municipality→barangay cascade |
| CSS tokens | `public/css/ui.css` | Full design system, already loaded |
| Session status polling | `layouts/app.blade.php` | 2-second polling already works |

---

# 14. Files That Actually Need Modification

## 14.1 Dashboard

| File | Change | Risk | Why |
|---|---|---|---|
| `app/Http/Controllers/DashboardController.php` | Add KPI queries + data passing | Low | Currently passes nothing |
| `resources/views/dashboard.blade.php` | Major restructure: KPIs, program distribution, announcements, calendar, activity feed | Medium | Core Phase 2 deliverable |
| `routes/web.php` | Possibly add a dashboard data JSON endpoint | Low | If KPIs are loaded via AJAX |

## 14.2 Global Search

| File | Change | Risk | Why |
|---|---|---|---|
| `resources/views/partials/global-search.blade.php` | Add dropdown results UI + AJAX | Low | Enhance existing client-only search |

## 14.3 Notifications

| File | Change | Risk | Why |
|---|---|---|---|
| `resources/views/partials/navbar.blade.php` | Add dropdown menu + mock notifications + toggle JS | Low | Stub only exists today |

## 14.4 Filter Chips (New Shared Component)

| File | Change | Risk | Why |
|---|---|---|---|
| `resources/js/components/FilterChips.js` | **New file** — shared filter component | Medium | Core Phase 2 deliverable |
| `public/css/ui.css` | Add FilterChips CSS | Low | Design tokens exist |
| `resources/views/partials/filter-chips.blade.php` | **New file** — shared Blade partial | Low | Renders filter buttons + chips |

## 14.5 Per-Module Filter Integration

| File | Change | Risk | Why |
|---|---|---|---|
| `resources/views/clients/index.blade.php` | Replace inline filters with FilterChips | Medium | Adopt shared component |
| `resources/views/transactions/index.blade.php` | Replace inline filters with FilterChips | Medium | Adopt shared component |
| `resources/views/households/index.blade.php` | Replace inline filters with FilterChips | Medium | Adopt shared component |
| `resources/views/scholars/index.blade.php` | Replace inline filters with FilterChips | Medium | Adopt shared component |
| `resources/views/payouts/attendance.blade.php` | Replace inline filters with FilterChips | Medium | Adopt shared component |
| `resources/views/admin/users/index.blade.php` | Add FilterChips (Role, Status) | Low | Currently search-only |
| `resources/views/admin/audit_logs/index.blade.php` | Replace inline filters with FilterChips | Medium | Adopt shared component |
| `resources/views/scholarship_reports/index.blade.php` | Replace inline filters with FilterChips | Medium | Adopt shared component |
| `resources/views/unpaid_verifications/index.blade.php` | Replace inline filters with FilterChips | Medium | Adopt shared component |

## 14.6 Responsive Polish

| File | Change | Risk | Why |
|---|---|---|---|
| `public/css/ui.css` | Add responsive utilities, touch targets | Low | Design tokens exist |
| `resources/views/partials/details-panel.blade.php` | Verify responsive breakpoints | Low | Already implemented |
| Various DataTables views | Verify table overflow behavior | Low | Mostly already handled |

---

# 15. Files That Should NOT Be Modified

| File | Reason |
|---|---|
| `app/Services/AccessControlService.php` | Central authorization — do not touch |
| `app/Services/ClientService.php` | Business logic — do not touch |
| `app/Services/TransactionService.php` | Business logic — do not touch |
| `app/Services/ScanService.php` | Complex scanner engine — do not touch |
| `app/Services/AuditService.php` | Central audit — do not touch |
| All model files | No schema changes allowed |
| `routes/web.php` (existing routes) | Do not change existing route signatures |
| `resources/js/components/DetailsPanel.js` | Stable Phase 1 contract — do not redesign |
| `resources/views/partials/details-panel.blade.php` | Stable Phase 1 contract |
| `resources/views/partials/confirm-modal.blade.php` | Stable shared component |
| `database/schema/mysql-schema.sql` | No schema changes |
| Any test file (without justification) | Preserve test baseline |
| `C:\xampp\htdocs\system\*` | Read-only v1 codebase |

---

# 16. Required Routes

## 16.1 Existing Routes That Serve Phase 2

| Route | Method | Purpose | Used By |
|---|---|---|---|
| `GET /` | Dashboard | DashboardController::index | Dashboard |
| `POST clients.data` | Client DataTables feed | ClientController::data | Global search, FilterChips |
| `POST transactions.data` | Transaction DataTables feed | TransactionController::data | Dashboard recent tx, FilterChips |
| `POST households.data` | Household DataTables feed | HouseholdController::data | FilterChips |
| `POST scholars.data` | Scholar DataTables feed | ScholarController::data | FilterChips |
| `POST admin.users.data` | User DataTables feed | UserController::data | FilterChips |
| `POST admin.audit-logs.data` | Audit log feed | AuditController::data | Activity feed, FilterChips |
| `POST scholarship-reports.data` | Report feed | ReportController::scholarshipData | FilterChips |
| `POST payout-attendance.*.data` | Payout feed | PayoutAttendanceController::data | FilterChips |
| `POST unpaid-verifications.data` | Unpaid feed | UnpaidVerificationController::data | FilterChips |
| `GET geography.barangays` | Barangay cascade | GeographyController::barangays | Filter chips (municipality→barangay) |

## 16.2 Potentially New Routes Needed

| Route | Method | Purpose | Priority |
|---|---|---|---|
| `GET dashboard.data` or `POST dashboard.data` | Dashboard KPI + widget data | DashboardController | High |
| (or embed in index) | Server-side rendering | DashboardController::index | High |

**Recommendation:** Keep it simple. Pass KPI data from `DashboardController::index()` directly to the Blade view. No new AJAX endpoint needed for KPIs. For the activity feed, reuse `admin.audit-logs.data` with a `limit=5` parameter.

---

# 17. Required Controller Changes

## 17.1 DashboardController

**Current:** 13 lines. Single `index()` method returning `view('dashboard')` with zero data.

**Required changes:**
1. Inject `AccessControlService` via constructor
2. In `index()`:
   - Query total clients (with municipality scope)
   - Query total transactions (with scope + program permissions)
   - Query disbursed amount (SUM of amount_paid where PAID, with scope)
   - Query pending count (COUNT where PENDING, with scope)
   - Pass all four to the view
3. Optionally: query top 5 recent audit entries for activity feed

**Risk:** Low. The controller is currently empty; adding queries is additive.

## 17.2 UserController::data() — Optional Enhancement

**Current:** Search-only filter (no Role or Status server-side filter).

**Required if** FilterChips for Users includes Role and Status filters. The existing DataTables feed would need to accept `role` and `status` POST parameters and apply WHERE clauses.

**Risk:** Low. Additive filter support.

## 17.3 No Other Controller Changes Required

All other controllers already have the filter support needed by their respective modules.

---

# 18. Required JavaScript Changes

## 18.1 New File: FilterChips.js

**Purpose:** Shared filter component for all list views.

**Architecture:**
- IIFE exposing `window.FilterChips`
- Constructor takes a config object: `{ moduleId, containerEl, filters: [...], onApply: callback }`
- Each filter: `{ key, label, searchable, options }`
- Manages: open/close popovers, checkbox state, search within options, chip rendering, clear-all
- Calls `onApply(filterState)` when filters change
- The callback triggers `table.draw()` with the new filter state

**Integration with DataTables:**
- Each DataTables view adds a `data` callback that reads `FilterChips.getState()` and sends it as POST parameters
- The existing server-side feeds already accept these parameters

**Estimated size:** ~200-300 lines

## 18.2 New File: Dashboard.js (or inline in dashboard.blade.php)

**Purpose:** Dashboard widget interactions.

**Components:**
- KPI card animations (number counting)
- Program distribution bar rendering
- Calendar widget (if dynamic)
- Activity feed rendering (if loaded via AJAX)
- Quick action click handlers (already exist via href links)

**Estimated size:** ~100-150 lines (or inline)

## 18.3 Modified: global-search.blade.php

**Changes:**
- Add a results dropdown container
- Modify the debounced search to fetch from `clients.data` and show top 5 results
- Add click handler to open DetailsPanel for a result
- Add Enter key handler to navigate to `/clients?search={query}`

**Estimated size:** ~50-80 lines added

## 18.4 Modified: navbar.blade.php

**Changes:**
- Add notification dropdown HTML after the bell button
- Add toggle JS for the dropdown
- Add mock notification items
- Add mark-all-read visual handler

**Estimated size:** ~60-100 lines added

## 18.5 Per-Module View Changes

Each DataTables view needs:
- Replace inline filter toolbar with `@include('partials.filter-chips', ['moduleId' => '...'])`
- Add `data` callback integration with FilterChips state
- Remove old inline filter HTML and JS

**Estimated change per view:** ~30-50 lines replaced

---

# 19. Required Blade Changes

## 19.1 New Partials

| File | Purpose |
|---|---|
| `resources/views/partials/filter-chips.blade.php` | Shared filter chips markup |

## 19.2 Modified Partials

| File | Change |
|---|---|
| `resources/views/partials/navbar.blade.php` | Add notification dropdown |
| `resources/views/partials/global-search.blade.php` | Add results dropdown |

## 19.3 Modified Views

| File | Change |
|---|---|
| `resources/views/dashboard.blade.php` | Major restructure |
| `resources/views/clients/index.blade.php` | Replace filters |
| `resources/views/transactions/index.blade.php` | Replace filters |
| `resources/views/households/index.blade.php` | Replace filters |
| `resources/views/scholars/index.blade.php` | Replace filters |
| `resources/views/payouts/attendance.blade.php` | Replace filters |
| `resources/views/admin/users/index.blade.php` | Add filters |
| `resources/views/admin/audit_logs/index.blade.php` | Replace filters |
| `resources/views/scholarship_reports/index.blade.php` | Replace filters |
| `resources/views/unpaid_verifications/index.blade.php` | Replace filters |

---

# 20. Required Tests

## 20.1 New Tests Needed

| Test File | Tests | Priority |
|---|---|---|
| `DashboardTest.php` | Dashboard loads, KPI data renders, Quick Actions ACL, Recent transactions render, Activity feed renders | High |
| `GlobalSearchTest.php` | Search renders, Search request behavior, Client filtering/navigation | Medium |
| `FilterChipsTest.php` | Component renders, Filter opens, Multiple options select, OR/AND logic, Chips appear, Individual removal, Clear All | Medium |

## 20.2 Existing Tests That Must Continue Passing

All 212 existing tests must continue passing. The filter chip replacement should not affect server-side behavior — only the UI changes.

## 20.3 Risky Test Resolution

`HouseholdTest::test_households_pages_load_for_permitted_user` — investigate and fix the output-buffering issue. This is a Phase 2 goal per the plan.

---

# 21. Risks

## 21.1 High Risk

| Risk | Mitigation |
|---|---|
| FilterChips replacement breaks existing DataTables functionality | Implement incrementally: one module at a time, verify after each |
| Dashboard KPI queries are slow on large datasets | Use simple COUNT/SUM with proper indexes; add caching if needed |
| FilterChips popover conflicts with DetailsPanel focus trap | Test keyboard navigation with both open |

## 21.2 Medium Risk

| Risk | Mitigation |
|---|---|
| Program distribution query returns empty if no transactions | Handle gracefully with "No data" state |
| Activity feed shows sensitive audit information to non-admins | Gate activity feed behind audit_logs page permission |
| Global search dropdown overlaps with other UI elements | Use proper z-index and positioning |
| Notification dropdown interferes with session status polling | Test concurrently |

## 21.3 Low Risk

| Risk | Mitigation |
|---|---|
| Calendar widget looks empty with no events | Show "No scheduled activities" or mock events |
| Announcements look stale with hardcoded content | Design for easy content updates |
| Responsive breakpoints need fine-tuning | Test at all specified viewport widths |

---

# 22. Architectural Concerns

## 22.1 Dashboard Controller Growth

The DashboardController will grow from 13 lines to potentially 80-100 lines with KPI queries. This is acceptable but should be monitored. If queries become complex, consider extracting a `DashboardService`.

**Recommendation:** Start with inline queries. Extract to a service only if complexity warrants it.

## 22.2 FilterChips JS Loading Strategy

DataTables and jQuery are loaded per-view via CDN `<script>` tags, not through Vite. The FilterChips component should follow the same pattern: a standalone JS file loaded via `<script>` tag in views that need it.

**Recommendation:** Place `FilterChips.js` in `public/js/components/FilterChips.js` (matching `DetailsPanel.js` pattern). Load via `<script src="{{ asset('js/components/FilterChips.js') }}"></script>` in each DataTables view.

## 22.3 No API Routes

All JSON endpoints live in `routes/web.php`. This is fine for Phase 2 — no need to create `routes/api.php` for dashboard data.

## 22.4 CSS Loading Order

The design tokens in `ui.css` are already loaded after Bootstrap. Any new FilterChips CSS should be added to `ui.css` to maintain a single stylesheet for shared components.

---

# 23. Decisions Required From You

## 23.1 Dashboard KPI Definitions

| Question | Options | Recommendation |
|---|---|---|
| What does "Total Amount Disbursed" mean? | A: `SUM(amount_paid) WHERE status='PAID'` | A — most accurate representation |
| What does "Pending Approvals" mean? | A: `COUNT WHERE status='PENDING'` | A — matches v2 status constants |
| Should KPIs respect municipality scope? | A: Yes (scoped to user) / B: No (all data) | A — consistent with ACL model |
| Should KPIs respect program permissions? | A: Yes / B: No | A — consistent with existing enforcement |

## 23.2 Filter Scope

| Question | Options | Recommendation |
|---|---|---|
| Should Clients gain Category/Sex/Civil Status filters? | A: Yes (server-side) / B: No (defer) | B — not in v1, would need new server-side support |
| Should Users gain Role/Status filter chips? | A: Yes / B: No | A — simple to add |
| Should Audit Logs gain filter chips? | A: Yes / B: No | A — aligns with prototype |

## 23.3 Notifications

| Question | Options | Recommendation |
|---|---|---|
| Should notifications be static/mock? | A: Yes (Phase 2 only) / B: Build backend | A — per plan |
| Should mark-all-read clear the badge? | A: Yes (client-side visual) | A — matches prototype |

## 23.4 Calendar

| Question | Options | Recommendation |
|---|---|---|
| Should the calendar show mock events? | A: Yes / B: Empty calendar | A — matches prototype visual |

## 23.5 Activity Feed on Dashboard

| Question | Options | Recommendation |
|---|---|---|
| Should the activity feed show real audit data? | A: Yes (recent 5 entries) / B: Mock | A — audit data exists and is reliable |
| Should the feed be gated by audit_logs permission? | A: Yes / B: Show to all | A — consistent with ACL model |

---

# 24. Recommended Decisions

Based on the modernization principle ("Preserve functionality. Modernize structure, flow, architecture, and UX."), the safest approach for each decision is:

1. **KPIs:** Use real data with scope. Simple COUNT/SUM queries. No invented definitions.
2. **Filters:** Adopt only filters that have existing server-side support. Defer new filter dimensions.
3. **Notifications:** Static mock. No backend.
4. **Calendar:** Static mock with today highlighted. No backend.
5. **Activity feed:** Real audit data, permission-gated. Reuse existing audit infrastructure.
6. **Announcements:** Static hardcoded content. No backend.
7. **Filter chips:** Implement as shared component. Replace inline filters incrementally.
8. **Global search:** Enhance existing client-only search with dropdown results. No cross-module search.

---

# 25. Proposed Implementation Order

Following the PHASE_2_PLAN.md section 16:

```
1. ✅ Inspect actual codebase (this report)
        ↓
2. ✅ Produce pre-implementation report (this report)
        ↓
3. ⏳ Resolve ambiguities/decisions (awaiting your review)
        ↓
4. Dashboard structure (Blade restructure + KPI queries)
        ↓
5. Dashboard data integration (program distribution, activity feed)
        ↓
6. Global Search (dropdown results UI)
        ↓
7. Notification UI stub (dropdown + mock data)
        ↓
8. Shared FilterChips component (JS + CSS + Blade partial)
        ↓
9. Integrate FilterChips module by module:
   9a. Clients (simplest — 2 filters)
   9b. Households (similar to clients)
   9c. Scholars (2 filters)
   9d. Transactions (most complex — 6+ filters + date ranges)
   9e. Payouts (3 filters + date range)
   9f. Users (2 new filters)
   9g. Audit Logs (3 filters + date range)
   9h. Scholarship Reports (4 filters + date range)
   9i. Unpaid Verifications (1 filter + date range)
        ↓
10. Responsive polish (touch targets, overflow, popover positioning)
        ↓
11. DetailsPanel regression verification
        ↓
12. Automated tests (DashboardTest, GlobalSearchTest, FilterChipsTest)
        ↓
13. Browser verification (all viewports, all modules)
        ↓
14. Final Phase 2 report
```

**Critical rule:** Do NOT modify all modules simultaneously. Implement FilterChips first in one module (Clients), validate it works, then integrate into others one by one.

---

# 26. Phase 2 Definition of Done

## Functional

- [ ] Dashboard shows 4 KPI cards with real data
- [ ] Dashboard shows recent transactions (existing behavior preserved)
- [ ] Dashboard shows program distribution bars
- [ ] Dashboard shows announcements (static)
- [ ] Dashboard shows calendar widget (static with today)
- [ ] Dashboard shows activity feed (real audit data, permission-gated)
- [ ] Quick Actions remain functional and ACL-gated
- [ ] Global Search shows client results dropdown
- [ ] Notification dropdown opens/closes with mock items
- [ ] FilterChips component renders across all 10 modules
- [ ] FilterChips OR logic works within categories
- [ ] FilterChips AND logic works across categories
- [ ] Active chips display and individual removal works
- [ ] Clear All works
- [ ] Searchable filter lists work where applicable
- [ ] Existing DataTables functionality preserved in all modules
- [ ] Responsive panel behavior verified

## Preservation

- [ ] All 212+ tests pass
- [ ] 0 risky tests (HouseholdTest issue resolved)
- [ ] Pint remains clean
- [ ] No database schema changes
- [ ] No business logic changes
- [ ] No authorization changes
- [ ] No v1 functionality removed
- [ ] DetailsPanel contract intact
- [ ] All existing filters continue to work server-side

## Quality

- [ ] No console errors on any page
- [ ] No DetailsPanel regression
- [ ] Responsive verified at 320px, 375px, 430px, 576px, 768px, 1024px, 1280px, 1440px
- [ ] Keyboard navigation works (filter chips, panel, search)
- [ ] Touch targets ≥44px on mobile
- [ ] Filter popovers don't overflow viewport
- [ ] Table horizontal scroll works on narrow screens

---

# Appendix A: Key File Reference

| Purpose | File Path | Lines |
|---|---|---|
| Phase 2 Plan | `docs/PHASE_2_PLAN.md` | 1,282 |
| Dashboard Controller | `app/Http/Controllers/DashboardController.php` | 13 |
| Dashboard View | `resources/views/dashboard.blade.php` | 267 |
| Routes | `routes/web.php` | 267 |
| Layout Master | `resources/views/layouts/app.blade.php` | 136 |
| Navbar | `resources/views/partials/navbar.blade.php` | 80 |
| Sidebar | `resources/views/partials/sidebar.blade.php` | 77 |
| DetailsPanel JS | `resources/js/components/DetailsPanel.js` | 241 |
| DetailsPanel Partial | `resources/views/partials/details-panel.blade.php` | 248 |
| Global Search | `resources/views/partials/global-search.blade.php` | 55 |
| Active Filters Stub | `resources/views/partials/active-filters.blade.php` | 30 |
| Breadcrumbs Stub | `resources/views/partials/breadcrumbs.blade.php` | 19 |
| Confirm Modal | `resources/views/partials/confirm-modal.blade.php` | 69 |
| Shared CSS Tokens | `public/css/ui.css` | 499 |
| ACL Service | `app/Services/AccessControlService.php` | 307 |
| Client Controller | `app/Http/Controllers/ClientController.php` | 310 |
| Transaction Controller | `app/Http/Controllers/TransactionController.php` | 659 |
| Household Controller | `app/Http/Controllers/HouseholdController.php` | 259 |
| Scholar Controller | `app/Http/Controllers/ScholarController.php` | 267 |
| User Controller | `app/Http/Controllers/UserController.php` | 198 |
| Audit Controller | `app/Http/Controllers/AuditController.php` | 166 |
| Payout Controller | `app/Http/Controllers/PayoutAttendanceController.php` | 286 |
| Unpaid Controller | `app/Http/Controllers/UnpaidVerificationController.php` | 242 |
| Report Controller | `app/Http/Controllers/ReportController.php` | 285 |
| Prototype HTML | `prototype/index.html` | 949+ |
| Prototype JS | `prototype/js/app.js` | 3,828 |
| Prototype CSS | `prototype/css/style.css` | 1,496+ |
| Prototype Spec | `prototype/PROTOTYPE_SPEC.md` | 498 |

---

*End of Phase 2 Pre-Implementation Inspection Report*
