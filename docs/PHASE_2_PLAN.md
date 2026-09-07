# 2DMIS v2 — Phase 2 Plan

**Status:** Approved — Ready for Inspection  
**Date:** 2026-08-27  
**Phase:** Phase 2  
**Previous Phase:** Phase 1 — Remediation  
**Project:** 2DMIS v2 Modernization

---

# 1. Purpose

Phase 2 continues the modernization of the legacy 2DMIS v1 application into 2DMIS v2.

The objective is **not to redesign or replace the business functionality of 2DMIS v1**.

The objective is to preserve the existing 2DMIS v1 functionality, fields, business rules, permissions, database relationships, and operational behavior while progressively adopting the structure, information architecture, interaction patterns, and user experience demonstrated by the approved prototype.

The prototype is the primary reference for:

- Application structure
- Information architecture
- Page organization
- User flow
- Interaction patterns
- Visual hierarchy
- Details-panel behavior
- Filtering interaction
- Dashboard organization
- Responsive behavior

The existing 2DMIS v1 application remains the primary reference for:

- Existing functionality
- Existing fields
- Existing business rules
- Existing permissions
- Existing database relationships
- Existing operational behavior
- Existing data

The modernization must bridge these two references rather than replacing one with the other.

---

# 2. Modernization Philosophy

## 2.1 Core Principle

> **Preserve functionality. Modernize structure, flow, architecture, and UX.**

2DMIS v2 should feel and behave like the approved prototype while continuing to perform the functions required by 2DMIS v1.

The goal is not to make the prototype's data model become the application's data model.

Instead:

```text
                    2DMIS v1
                       │
        ┌──────────────┼──────────────┐
        │              │              │
   Functionality     Fields      Business Rules
        │              │              │
        └──────────────┼──────────────┘
                       │
                       ▼
                  2DMIS v2
                       ▲
                       │
        ┌──────────────┼──────────────┐
        │              │              │
    Structure         UX           Flow
        │              │              │
        └──────────────┼──────────────┘
                       │
                   Prototype
```

2DMIS v2 should therefore be understood as:

> **The existing 2DMIS system modernized through the prototype's structural and UX language.**

## 2.2 Prototype vs. Legacy Authority

| Concern | Primary Authority |
|---|---|
| Business functionality | 2DMIS v1 |
| Existing fields | 2DMIS v1 |
| Existing database relationships | 2DMIS v1 |
| Existing business rules | 2DMIS v1 |
| Existing permissions | 2DMIS v1 |
| Existing operational behavior | 2DMIS v1 |
| Page organization | Prototype |
| Navigation structure | Prototype |
| User flow | Prototype |
| Visual hierarchy | Prototype |
| Details panel | Prototype |
| Filter interaction | Prototype |
| Dashboard organization | Prototype |
| Responsive behavior | Prototype |
| Interaction patterns | Prototype |

If the prototype does not contain a field or workflow that exists in v1, **do not remove it simply because the prototype does not show it**.

If the prototype presents existing functionality in a different way, modernize the presentation and flow while retaining the underlying functionality.

---

# 3. Non-Negotiable Constraints

The following constraints apply throughout Phase 2.

## 3.1 Preserve Existing Functionality

Do not remove, disable, simplify, or replace existing 2DMIS v1 functionality unless explicitly approved.

## 3.2 Preserve Existing Fields

Existing v1 fields must remain available where they are required by the existing functionality.

The prototype must not be treated as a replacement database specification.

## 3.3 Preserve Business Rules

Do not alter business rules merely to make the UI behave like the prototype.

If a prototype interaction conflicts with an existing business rule, preserve the business rule and adapt the UI.

## 3.4 Preserve Authorization

Existing authentication, authorization, ACL, page permissions, action permissions, municipality scope, and related security rules must remain intact.

## 3.5 Preserve Database Behavior

Do not redesign the database to match the prototype.

Do not introduce destructive migrations.

Do not rename or remove existing database fields simply for UI consistency.

## 3.6 No Unapproved Business Logic

Phase 2 is primarily a UX and structural modernization phase.

Do not invent new business rules.

Do not infer new workflows without inspecting the existing implementation and obtaining approval.

## 3.7 No Premature Refactoring

Do not perform unrelated architectural refactoring merely because an existing implementation could theoretically be cleaner.

Only refactor when it directly supports the approved Phase 2 scope or prevents a concrete problem.

## 3.8 Preserve Existing Tests

Existing passing tests must continue to pass.

Tests may be updated when UI changes legitimately invalidate UI-specific assertions, but business behavior must remain protected.

---

# 4. Current Baseline

Phase 1 and previous modernization work have already established the following baseline.

## Completed Foundation

- Laravel application scaffold
- Baseline database/schema understanding
- Existing database preservation strategy
- Authentication
- RBAC/ACL
- Application shell
- Clients
- Households
- Transactions
- Scanner engine
- Payouts
- Unpaid verification
- Scholars
- GIP
- Scholarship reports
- Administration
- Audit logs
- Municipality scope
- Action authorization
- Production hardening groundwork

## Phase 1 Remediation

The Details Panel remediation is complete.

The following have been established:

- Standardized `data-panel-*` contract
- Shared DetailsPanel behavior
- Panel-compatible detail partials
- Panel show endpoints
- Panel-aware views
- Prototype-aligned panel actions
- Prototype-aligned panel content sections
- Responsive drawer behavior
- Keyboard interaction
- Focus trapping
- ESC handling
- Backdrop handling
- Row-to-panel interaction

## Current Verification Baseline

Target baseline:

- Existing feature tests remain passing
- Pint remains clean
- No functional regressions
- DetailsPanel behavior remains stable

A currently identified PHPUnit risky test relates to output buffering:

```text
HouseholdTest::test_households_pages_load_for_permitted_user
```

The test currently passes functionally but reports an output-buffering warning.

Phase 2 should attempt to eliminate this risky-test condition.

---

# 5. Phase 2 Objective

Phase 2 will focus on:

1. Dashboard modernization
2. Global UX improvements
3. Shared Filter Chips system
4. Responsive UX verification and polish
5. Stabilization of the existing DetailsPanel contract

The intended result is a stronger, more cohesive v2 experience without changing the underlying 2DMIS functionality.

Phase 2 is therefore a **UX modernization phase**, not a business-logic rewrite.

---

# 6. Phase 2 Scope

## In Scope

### A. Dashboard

- KPI cards
- Quick actions
- Recent transactions
- Program distribution
- Announcements
- Activity calendar
- Activity feed
- Responsive dashboard layout

### B. Global UX

- Global search
- Notification dropdown UI
- Breadcrumb consistency
- Topbar interaction
- Responsive behavior

### C. Filter Chips

- Shared filter component
- Multi-select filters
- Active filter chips
- Remove individual filter
- Clear all filters
- Search within long filter lists
- Correct AND/OR filtering semantics
- Integration with existing DataTables/AJAX feeds

### D. Responsive Polish

- Desktop panel behavior
- Tablet panel behavior
- Mobile panel behavior
- Table overflow
- Touch target sizes
- Filter popover behavior

### E. DetailsPanel Stability

No redesign of the DetailsPanel contract is required.

Phase 2 should preserve and lightly polish the implementation established during Phase 1.

---

# 7. Dashboard Requirements

The Dashboard should follow the structure and visual hierarchy of the approved prototype while using real 2DMIS data where available.

## 7.1 KPI Cards

The dashboard should provide four primary KPI areas corresponding to the prototype:

- Total Clients
- Transactions
- Disbursed
- Pending

The actual calculations must be based on existing 2DMIS data and business rules.

Do not invent definitions for these metrics without inspecting the existing data and controller logic.

If an exact metric definition is ambiguous, report the ambiguity before implementation.

## 7.2 Quick Actions

Existing Quick Actions must remain functional and ACL-gated.

Examples include:

- Add Client
- New Transaction
- Register Household

Do not bypass existing authorization rules.

## 7.3 Recent Transactions

Display the most recent relevant transactions using the existing transaction data source where possible.

Do not create duplicate transaction logic merely for the dashboard.

## 7.4 Program Distribution

Provide a prototype-aligned visual representation of program distribution.

The data must come from existing 2DMIS records.

The implementation should avoid unnecessary new business logic.

## 7.5 Announcements

Implement the prototype's announcements area.

If no existing announcement data source exists, a presentation-level/static implementation may be used temporarily.

Do not introduce a new persistent announcement subsystem in Phase 2 unless explicitly approved.

## 7.6 Activity Calendar

Implement the prototype's calendar presentation.

The calendar should use existing available data where practical.

If the prototype's calendar requires information that does not exist in 2DMIS, do not invent business semantics.

Mock/static presentation data may be used where explicitly identified as such.

## 7.7 Activity Feed

The activity feed should use existing audit information where appropriate.

Do not duplicate audit logging.

The dashboard should consume the existing audit infrastructure rather than creating a second activity-tracking mechanism.

---

# 8. Global UX

## 8.1 Global Search

The topbar global search should provide a useful entry point into the Clients registry.

The preferred behavior is:

```text
User types
    ↓
Debounced search
    ↓
Client search/filter
    ↓
Results
```

Pressing Enter may navigate to the Clients page with the search value applied.

The implementation must not interfere with module-specific searches.

## 8.2 Notifications

Phase 2 may implement the notification dropdown as a UI-level component.

The following are acceptable for Phase 2:

- Dropdown opens
- Notification items render
- Badge renders
- Mark-all-read visual interaction works

A full notification persistence/backend subsystem is **not required for Phase 2**.

Do not invent notification business rules.

## 8.3 Breadcrumb

Maintain consistent prototype-aligned breadcrumb presentation:

```text
2DMIS → Current Page
```

Breadcrumbs should accurately reflect the current location.

---

# 9. Filter Chips System

The Filter Chips system is a major Phase 2 shared UX component.

The objective is to replace inconsistent filter controls with a reusable interaction model inspired by the prototype.

The component should support:

- Filter button
- Popover
- Checkbox options
- Optional search field
- Multiple selections
- Active filter chips
- Individual removal
- Clear All

---

# 10. Filter Chips Behavior

## 10.1 Multiple Values Within One Category

Multiple selections within the same category use **OR** logic.

Example:

```text
Municipality:
    Candon
    Vigan
```

Means:

```text
Municipality = Candon OR Vigan
```

## 10.2 Multiple Categories

Different filter categories use **AND** logic.

Example:

```text
Municipality:
    Candon

Program:
    CEAP
```

Means:

```text
Municipality = Candon
AND
Program = CEAP
```

## 10.3 Active Chips

Selected filters should appear as removable chips.

Example:

```text
Filters:
[ Candon × ] [ CEAP × ] [ BOOKED × ]    Clear All
```

Removing a chip should immediately update the table/filter state.

## 10.4 Clear All

A single Clear All control should remove all active filters.

Clear All should only appear when at least one filter is active.

## 10.5 Searchable Filter Options

Long filter lists should support searching within the popover.

Example:

```text
Program
--------------------
[ Search programs ]

☐ CEAP
☐ CEAP_NEW
☐ CEDSSG
☐ CEDSSG_NEW
☐ OTEA
☐ OTCES
```

## 10.6 Server Integration

Filter Chips must integrate with existing data endpoints.

Do not replace existing business/data retrieval logic unnecessarily.

Preferred approach:

```text
FilterChips UI
      ↓
Filter state
      ↓
Existing DataTables/AJAX request
      ↓
Existing controller/feed
      ↓
Existing data
```

The component should adapt to the existing backend rather than requiring a new backend architecture.

---

# 11. Modules for Filter Chips

The Filter Chips system should be evaluated for all affected list views.

## 11.1 Clients

Potential filters:

- Municipality
- Barangay

## 11.2 Households

Potential filters:

- Municipality
- Barangay

## 11.3 Transactions

Potential filters:

- Program
- Status
- Municipality
- Barangay
- Date range

## 11.4 Scholars

Potential filters:

- Program
- Status

## 11.5 GIP Profiles

Potential filters:

- Program

## 11.6 Scholarship Reports

Potential filters:

- Municipality
- Barangay
- Program
- Submission state
- Date

## 11.7 Payouts

Potential filters:

- Municipality
- Program
- Date

## 11.8 Users

Potential filters:

- Role
- Status

## 11.9 Audit Logs

Potential filters:

- User
- Action
- Module
- Date

## 11.10 Unpaid Verifications

Potential filters:

- Municipality
- Date

The exact filter names and available options must be verified against the actual existing implementation before modification.

Do not assume that every proposed filter exists or has the same meaning in the current codebase.

---

# 12. Filter Implementation Rule

The FilterChips component should be shared.

Avoid creating ten unrelated implementations of the same interaction.

Preferred conceptual architecture:

```text
FilterChips.js
      │
      ├── Clients
      ├── Households
      ├── Transactions
      ├── Scholars
      ├── GIP
      ├── Reports
      ├── Payouts
      ├── Users
      ├── Audit Logs
      └── Unpaid Verifications
```

Each module should provide configuration/data rather than duplicate the component's core behavior.

The component must remain flexible enough to support different filter categories and option lists.

Do not create unnecessary coupling between the component and a specific module.

---

# 13. Responsive Requirements

The prototype establishes the following DetailsPanel behavior.

## Desktop

Approximately:

```text
480px
```

The main table remains visible beside the panel.

## Tablet

Approximately:

```text
50vw
```

The panel may occupy a larger portion of the viewport.

## Mobile

The panel becomes:

```text
100% width
```

The implementation must preserve:

- ESC closing
- Backdrop closing where appropriate
- Focus trap
- Focus restoration
- Keyboard accessibility
- Scroll locking
- Appropriate touch targets

Touch targets should generally be at least approximately 44px where practical.

Tables must remain usable on narrow screens through appropriate horizontal scrolling or responsive treatment.

Filter popovers must remain usable on tablet and mobile.

---

# 14. Details Panel Stability

The DetailsPanel implementation established during Phase 1 is considered a stable contract.

Phase 2 must not unnecessarily redesign or replace it.

The following contract must remain intact:

```text
data-panel-title
data-panel-sub
data-panel-avatar
data-panel-meta
data-panel-actions
data-panel-body
```

The panel must never fall back to dumping:

```javascript
document.body.innerHTML
```

or equivalent full-document content into the panel.

All existing panel functionality must remain operational.

Any changes to DetailsPanel.js must be minimal and justified.

---

# 15. Architecture Rules

## 15.1 Reuse Existing Backend

Reuse existing:

- Controllers
- Services
- Models
- Data feeds
- ACL services
- Audit infrastructure
- Existing queries

where appropriate.

Do not create duplicate implementations of existing business logic.

## 15.2 Keep Frontend Consistent

Shared interactions should use shared components.

Examples:

- DetailsPanel
- FilterChips
- Confirmation dialogs
- Toast infrastructure when eventually implemented

Do not create slightly different implementations of the same UX pattern per module without a concrete reason.

## 15.3 Avoid Framework Expansion

Phase 2 remains within the established application frontend approach.

Current baseline:

- Bootstrap 5
- CDN-based assets
- Existing JavaScript architecture
- Existing DataTables implementation

Do not introduce a new frontend framework merely to implement Phase 2.

Do not introduce a build system solely because another approach might be theoretically cleaner.

## 15.4 No Database Redesign

No database migrations should be required for the core Phase 2 scope.

If implementation appears to require a schema change:

1. Stop.
2. Document why it appears necessary.
3. Explain alternatives.
4. Request approval before proceeding.

## 15.5 Security

Do not weaken:

- Authentication
- Authorization
- ACL
- Municipality scope
- Action authorization
- CSRF protection
- Validation
- Existing security boundaries

UI visibility must never be treated as the only authorization mechanism.

---

# 16. Phase 2 Implementation Order

Implementation should proceed incrementally.

Recommended order:

```text
1. Inspect actual codebase
        ↓
2. Produce pre-implementation report
        ↓
3. Resolve ambiguities/decisions
        ↓
4. Dashboard structure
        ↓
5. Dashboard data integration
        ↓
6. Global Search
        ↓
7. Notification UI stub
        ↓
8. Shared FilterChips component
        ↓
9. Integrate FilterChips module by module
        ↓
10. Responsive polish
        ↓
11. DetailsPanel regression verification
        ↓
12. Automated tests
        ↓
13. Browser verification
        ↓
14. Final Phase 2 report
```

Do not modify all modules simultaneously before verifying the shared component.

Implement the FilterChips component first, validate it, then integrate it incrementally.

---

# 17. Testing Requirements

The existing test suite must remain healthy.

Target:

```text
212+ passing
0 risky
1,056+ assertions or more
```

Additional tests should cover the new Phase 2 functionality.

## Dashboard Tests

Verify:

- Dashboard loads
- KPI data renders
- Quick Actions remain available according to ACL
- Recent transactions render
- Program distribution renders
- Announcements render
- Calendar renders
- Activity feed renders

## Global Search Tests

Verify:

- Search UI renders
- Search request behavior
- Client filtering/navigation behavior
- Search does not bypass authorization

## FilterChips Tests

Verify:

- Component renders
- Filter button opens
- Multiple options can be selected
- OR logic works within a category
- AND logic works across categories
- Active chips appear
- Individual chip removal works
- Clear All works
- Search within filter options works
- Existing data endpoints receive appropriate filter state

## Regression Tests

Existing tests for:

- Clients
- Households
- Transactions
- Scholars
- Payouts
- Unpaid verification
- Users
- Audit logs
- Authorization
- Municipality scope

must continue to pass.

---

# 18. Browser Verification

Automated tests alone are not sufficient for Phase 2.

Behavioral browser verification is required.

## Dashboard

Verify:

1. Dashboard loads without console errors.
2. KPI cards display.
3. Quick Actions work.
4. Recent Transactions loads.
5. Program Distribution renders.
6. Announcements render.
7. Calendar renders.
8. Activity Feed renders.
9. ACL restrictions remain respected.
10. Mobile layout remains usable.

## Global Search

Verify:

1. Search field accepts input.
2. Debouncing works.
3. Client results/filtering work.
4. Enter navigation works if implemented.
5. Search does not interfere with table-specific search.

## Notifications

Verify:

1. Bell button opens dropdown.
2. Dropdown closes appropriately.
3. Badge renders correctly.
4. Stub interactions do not cause errors.

## Filter Chips

For every affected module verify:

1. Filter button opens.
2. Popover renders.
3. Options render.
4. Multiple values can be selected.
5. Same-category OR behavior works.
6. Cross-category AND behavior works.
7. Active chips render.
8. Individual chip removal works.
9. Clear All works.
10. Table updates correctly.
11. No existing filter functionality is lost.
12. Search within long option lists works where applicable.

## DetailsPanel

Verify:

### Desktop

- Approximately 480px panel
- Table remains visible
- Row click opens panel
- ESC closes
- Focus restoration works

### Tablet

- Approximately 50vw
- Backdrop works
- Table remains appropriately usable

### Mobile

- Full-width panel
- Touch targets are usable
- Backdrop works
- ESC/close works where supported
- Page scroll locking works

---

# 19. Explicit Non-Goals

The following are **not part of Phase 2** unless separately approved.

## 19.1 CRUD Modals

Do not convert all Create/Edit pages into modal workflows during Phase 2.

This is deferred.

## 19.2 Full Notification Backend

Do not build a complete notification persistence/delivery system.

A UI stub is sufficient.

## 19.3 Toast System

Do not build a comprehensive client-side toast framework in Phase 2 unless required to support another approved feature.

## 19.4 Print/Export From Panel

Do not implement the complete prototype print/export panel functionality unless separately approved.

## 19.5 Saved Filters

Do not implement:

- Saved filters
- Filter presets
- User-specific filter persistence

## 19.6 Database Redesign

No schema redesign.

## 19.7 Business Logic Rewrite

No business logic rewrite.

## 19.8 Prototype Data Model

Do not force the prototype's data structures onto the existing 2DMIS database.

## 19.9 Unrelated Refactoring

Do not use Phase 2 as an excuse to rewrite unrelated portions of the application.

---

# 20. Decision Rules

When the prototype and 2DMIS v1 differ:

1. Preserve 2DMIS v1 functionality.
2. Preserve required 2DMIS v1 fields.
3. Preserve existing business rules.
4. Preserve existing authorization behavior.
5. Preserve existing database relationships.
6. Adopt the prototype's structure where practical.
7. Adopt the prototype's flow where it does not conflict with v1 behavior.
8. Adopt the prototype's UX and visual hierarchy.
9. Do not remove fields merely because they are absent from the prototype.
10. Do not invent business rules to make the UI match the prototype.
11. Do not alter permissions to make a prototype interaction possible.
12. Do not introduce schema changes simply to simplify frontend implementation.
13. If an existing v1 behavior is unclear, inspect the code and tests before deciding.
14. If the prototype and v1 have a genuine unresolved conflict, stop and report it.
15. Do not silently choose an interpretation for a significant business-rule conflict.

The implementation should favor:

> **Adapt the UI to the existing functionality rather than adapting the functionality to the prototype.**

---

# 21. Required Pre-Implementation Report

**OpenCode must inspect the actual project before implementing Phase 2.**

The Phase 2 planning report is a guide, not a substitute for source-code inspection.

Before modifying code, inspect:

## Dashboard

1. Current `DashboardController`
2. Current dashboard view
3. Existing dashboard queries
4. Existing transaction feeds
5. Existing audit/activity data
6. Existing program data
7. Existing ACL behavior
8. Existing routes

## Global UX

9. Current navbar/topbar
10. Current global-search implementation
11. Existing notification UI/stub
12. Existing breadcrumb implementation

## Filters

13. Every affected module's current filter implementation
14. Existing DataTables initialization
15. Existing AJAX/data endpoints
16. Existing server-side filtering
17. Existing client-side filtering
18. Existing query parameters
19. Existing filter-related tests

## Details Panel

20. DetailsPanel.js
21. Panel contract
22. All panel-compatible partials
23. Panel show endpoints
24. Existing panel-related tests

## Architecture

25. Existing shared JavaScript components
26. Existing Blade components/partials
27. Existing frontend conventions
28. Existing asset loading strategy
29. Existing controller/service boundaries

## Risk Assessment

Identify:

- Potential regressions
- Conflicting filter implementations
- Duplicate logic
- Existing code that should be reused
- Existing code that should not be modified
- Business-rule ambiguities
- Authorization concerns
- Performance concerns
- Mobile/responsive issues
- Testing gaps

## Required Report Format

The report should contain:

```text
1. Current implementation findings
2. Relevant files
3. Existing data flow
4. Existing filter architecture
5. Existing dashboard architecture
6. Existing global UX architecture
7. Existing DetailsPanel architecture
8. Prototype-to-v1 differences
9. Risks
10. Recommended implementation approach
11. Files expected to change
12. Files that should NOT be changed
13. Questions/decisions requiring approval
14. Proposed implementation order
```

**Do not begin broad implementation before completing this inspection.**

Do not assume that the planning report perfectly reflects the current source code.

Verify important claims against the actual implementation.

---

# 22. Definition of Done

Phase 2 is complete only when all applicable criteria are satisfied.

## Functional

- Dashboard matches the prototype's intended structure
- Dashboard uses appropriate existing 2DMIS data
- Quick Actions remain functional
- Global Search works
- Notification UI stub works
- Filter Chips are implemented as a shared component
- Filter Chips work across all applicable modules
- AND/OR behavior is correct
- Existing filters remain functional
- Responsive panel behavior is verified

## Preservation

- Existing 2DMIS functionality remains intact
- Existing fields remain intact
- Existing business rules remain intact
- Existing authorization remains intact
- Existing database relationships remain intact
- No destructive database changes
- No unauthorized business logic changes

## Quality

- Test suite passes
- Risky test is resolved where possible
- Pint is clean
- No new console errors
- No obvious JavaScript runtime errors
- No DetailsPanel regression
- No full-document HTML fallback
- Responsive behavior verified

## Documentation

A final Phase 2 implementation report should document:

- What was implemented
- What files changed
- What tests were added/updated
- What browser verification was performed
- Any known deviations from the prototype
- Any deferred items
- Any remaining technical debt
- Final test results
- Final Pint result

---

# 23. Final Principle

The modernization of 2DMIS v2 follows one central rule:

> **2DMIS v1 defines what the system must do.  
> The prototype defines how the system should be organized, presented, and experienced.**

Therefore:

```text
                2DMIS v1
        ┌─────────────────────┐
        │ Functionality       │
        │ Fields              │
        │ Business Rules      │
        │ Permissions         │
        │ Existing Data       │
        └──────────┬──────────┘
                   │
                   ▼
             ┌───────────┐
             │ 2DMIS v2  │
             └─────┬─────┘
                   ▲
                   │
        ┌──────────┴──────────┐
        │     Prototype       │
        │ Structure           │
        │ Information Arch.   │
        │ Flow                │
        │ UX                  │
        │ Interaction         │
        │ Visual Hierarchy    │
        │ Responsive Design   │
        └─────────────────────┘
```

The goal is not to make 2DMIS v2 a copy of the prototype.

The goal is to make 2DMIS v2 **feel like the prototype while remaining the 2DMIS system**.

> **Preserve the system. Modernize the experience.**
