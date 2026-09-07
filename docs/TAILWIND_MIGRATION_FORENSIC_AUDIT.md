# Tailwind Migration Forensic Audit

> **Date**: September 2026
> **Type**: Read-only Bootstrap dependency audit
> **Goal**: Determine when Bootstrap 5.3.2 (CDN) can be completely removed and replaced by Tailwind v4 + custom JS
> **Status**: Complete

---

## Executive Summary

**Bootstrap 5.3.2** is loaded via CDN (not npm) and provides three capabilities:
1. **CSS utility classes** — used in 20+ Blade views (63+ distinct class names)
2. **JavaScript components** — Modal (9 screens), Toast (3 files), Offcanvas (2 files), Collapse (1 file), Dropdown (1 file)
3. **DataTables integration** — 11 index screens use `dataTables.bootstrap5.min.css` + `dataTables.bootstrap5.min.js`

**Tailwind Migration Status**: ~35% complete (Clients module partials migrated; auth/login, dashboard, scanners, sessions partially migrated)

**Bootstrap CDN can be removed when**:
1. All 20+ Blade views have equivalent Tailwind markup (no BS class remnants)
2. All 9 Modal JS instances are replaced with custom modal component or Alpine.js
3. All 3 Toast JS instances are replaced with custom toast component
4. All 11 DataTables screens are migrated from BS5 integration to plain DataTables + Tailwind styling
5. All 7 standalone public pages are updated to remove BS CDN `<script>` + `<link>` tags

**Recommended removal point**: After completion of **Phase 4** (shared components) and **Phase 5** (standalone pages) in the Tailwind migration roadmap.

---

## Table of Contents

1. [CDN Loading Points](#1-cdn-loading-points)
2. [Bootstrap JavaScript API Usage](#2-bootstrap-javascript-api-usage)
3. [Bootstrap CSS Class Usage](#3-bootstrap-css-class-usage)
4. [DataTables Integration Audit](#4-datatables-integration-audit)
5. [Shared Component Inventory](#5-shared-component-inventory)
6. [Module-by-Module Migration Matrix](#6-module-by-module-migration-matrix)
7. [Standalone Pages Audit](#7-standalone-pages-audit)
8. [Coexistence Architecture](#8-coexistence-architecture)
9. [Module Migration Order](#9-module-migration-order)
10. [Phase 1 Implementation Plan](#10-phase-1-implementation-plan)
11. [Risks and Constraints](#11-risks-and-constraints)
12. [Verification Checklist](#12-verification-checklist)

---

## 1. CDN Loading Points

**7 files** load Bootstrap CSS CDN; **7 files** load Bootstrap JS CDN.

### 1.1 Main Layout (affects ~20+ screens)

| File | Line | CDN Resource | Type |
|------|------|--------------|------|
| `resources/views/layouts/app.blade.php` | 9 | `bootstrap@5.3.2/dist/css/bootstrap.min.css` | CSS |
| `resources/views/layouts/app.blade.php` | 106 | `bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js` | JS |

**Impact**: Any view extending `@extends('layouts.app')` inherits both CSS and JS. Removing from this file affects **all authenticated screens**.

### 1.2 Standalone Public Pages (each loads CDN independently)

| File | CSS Line | JS Line | Standalone? |
|------|----------|---------|-------------|
| `resources/views/auth/login.blade.php` | — | — | Yes (no CDN JS) |
| `resources/views/qr/viewer.blade.php` | 10 | 364 | Yes |
| `resources/views/students/verify.blade.php` | — | — | Yes (CSS only) |
| `resources/views/students/update-photo.blade.php` | — | — | Yes (CSS only) |
| `resources/views/students/photo-upload.blade.php` | 10 | 364 | Yes |
| `resources/views/grantee_update/self-service.blade.php` | 10 | 364 | Yes |
| `resources/views/unpaid_verifications/self-service.blade.php` | 10 | 364 | Yes |

**Note**: `students/verify.blade.php` and `students/update-photo.blade.php` load CSS only (no Bootstrap JS).

---

## 2. Bootstrap JavaScript API Usage

### 2.1 Modal (bootstrap.Modal)

**9 screens** use `new bootstrap.Modal()` or `modal.show()`/`modal.hide()`:

| File | Usage Count | Pattern |
|------|-------------|---------|
| `resources/views/clients/index.blade.php` | 3 | `new bootstrap.Modal(...)`, `.show()`, `.hide()` |
| `resources/views/clients/_details.blade.php` | 2 | `modal.show()`, `.hide()` |
| `resources/views/transactions/index.blade.php` | 2 | `new bootstrap.Modal(...)`, `.show()` |
| `resources/views/scholars/index.blade.php` | 2 | `new bootstrap.Modal(...)`, `.show()` |
| `resources/views/payouts/attendance.blade.php` | 2 | `new bootstrap.Modal(...)`, `.show()` |
| `resources/views/admin/users/index.blade.php` | 2 | `new bootstrap.Modal(...)`, `.show()` |
| `resources/views/admin/audit_logs/index.blade.php` | 2 | `new bootstrap.Modal(...)`, `.show()` |
| `resources/views/unpaid_verifications/index.blade.php` | 2 | `new bootstrap.Modal(...)`, `.show()` |
| `resources/views/unpaid_verifications/self-service.blade.php` | 1 | `new bootstrap.Modal(...)` |
| `resources/views/duplicates/index.blade.php` | 1 | `new bootstrap.Modal(...)` |
| `resources/views/scanners/scan.blade.php` | 1 | `new bootstrap.Modal(...)` |

**Total**: 20 modal instantiation calls across 11 files.

**BS classes required for Modal**:
- `.modal`, `.modal-dialog`, `.modal-content`, `.modal-header`, `.modal-body`, `.modal-footer`
- `.btn-close`
- `data-bs-toggle="modal"`, `data-bs-target="#..."`, `data-bs-dismiss="modal"`

### 2.2 Toast (bootstrap.Toast)

**3 files** use `new bootstrap.Toast()`:

| File | Usage Count |
|------|-------------|
| `resources/views/clients/index.blade.php` | 2 |
| `resources/views/clients/_details.blade.php` | 1 |
| `resources/views/layouts/app.blade.php` | 2 (global toast container) |

**Total**: 5 toast instantiation calls across 3 files.

**BS classes required for Toast**:
- `.toast`, `.toast-container`, `.toast-body`
- `data-bs-autohide="true"`, `data-bs-delay="3000"`

### 2.3 Offcanvas (declarative only)

**2 files** use `data-bs-toggle="offcanvas"`:

| File | Element |
|------|---------|
| `resources/views/layouts/app.blade.php` | Sidebar (mobile) |
| `resources/views/partials/sidebar.blade.php` | Sidebar toggle |

**No JS API calls** — all offcanvas behavior is declarative via `data-bs-toggle="offcanvas"` and `data-bs-dismiss="offcanvas"`.

**BS classes required for Offcanvas**:
- `.offcanvas`, `.offcanvas-start`, `.offcanvas-lg-start`
- `.offcanvas-backdrop`

### 2.4 Collapse (declarative only)

**1 file** uses `data-bs-toggle="collapse"`:

| File | Element |
|------|---------|
| `resources/views/partials/sidebar.blade.php` | Sidebar menu sections |

**No JS API calls** — all collapse behavior is declarative.

**BS classes required for Collapse**:
- `.collapse`, `.collapsing`

### 2.5 Dropdown (declarative only)

**1 file** uses `data-bs-toggle="dropdown"`:

| File | Element |
|------|---------|
| `resources/views/partials/navbar.blade.php` | User menu dropdown |

**No JS API calls** — all dropdown behavior is declarative.

**BS classes required for Dropdown**:
- `.dropdown`, `.dropdown-menu`, `.dropdown-item`, `.dropdown-toggle`

---

## 3. Bootstrap CSS Class Usage

### 3.1 Usage Categories (by volume)

| Category | Approx Count | Primary Files |
|----------|---------------|---------------|
| Layout (row, col-*, container, d-*) | 35+ | self-service, _form, login, create, viewer |
| Typography (text-*, fs-*, fw-*) | 20+ | scanners, viewers, dashboard |
| Forms (form-control, form-select, form-check) | 30+ | _form, self-service, viewer |
| Tables (table, table-bordered) | 15+ | dashboard, sessions, index views |
| Utilities (m-*, p-*, mt-*, mb-*) | 25+ | scattered across all views |
| Components (alert, badge, card, accordion) | 10+ | _gip, self-service, viewers |
| Bootstrap Buttons (btn, btn-primary, etc.) | 45+ | replaced by ui.css variants |

### 3.2 Files with Heavy Bootstrap Usage (>30 classes)

| File | Approx Count | Priority |
|------|---------------|----------|
| `resources/views/grantee_update/self-service.blade.php` | ~100 | HIGH |
| `resources/views/grantee_update/_self_update_tab.blade.php` | ~100 | HIGH |
| `resources/views/students/photo-upload.blade.php` | ~40 | MEDIUM |
| `resources/views/qr/viewer.blade.php` | ~30 | MEDIUM |
| `resources/views/households/create.blade.php` | ~30 | MEDIUM |
| `resources/views/unpaid_verifications/self-service.blade.php` | ~25 | MEDIUM |
| `resources/views/scanners/scan.blade.php` | ~20 | MEDIUM |
| `resources/views/clients/_form.blade.php` | ~15 | LOW (partially migrated) |

### 3.3 Files with Light Bootstrap Usage (<20 classes)

| File | Approx Count | Notes |
|------|---------------|-------|
| `resources/views/auth/login.blade.php` | ~5 | d-flex, form-control, min-vh-100 |
| `resources/views/students/verify.blade.php` | ~3 | form-control |
| `resources/views/students/update-photo.blade.php` | ~5 | input-group, list-group, d-flex |
| `resources/views/sessions/online.blade.php` | ~3 | table, text-center |
| `resources/views/dashboard.blade.php` | ~5 | table, text-center, m-0 |

### 3.4 Bootstrap CSS Classes That Have No Direct Tailwind Equivalent

These classes require custom CSS or careful Tailwind configuration:

| BS Class | Purpose | Tailwind Approach |
|----------|---------|-------------------|
| `.form-control` | Text input styling | Custom class or `@apply` |
| `.form-select` | Select input styling | Custom class or `@apply` |
| `.form-check` | Checkbox/radio group | Custom class or `@apply` |
| `.input-group` | Input prepend/append | Custom flexbox layout |
| `.accordion` | Collapsible section | Custom component |
| `.list-group` | List container | Custom class |
| `.toast` | Notification popup | Custom component |
| `.offcanvas` | Slide-in panel | Custom component or Alpine.js |

**Note**: `ui.css` already provides `.form-control` and `.form-select` Tailwind equivalents (lines ~200-250).

---

## 4. DataTables Integration Audit

### 4.1 DataTables + Bootstrap 5 Integration

**11 index screens** use DataTables with Bootstrap 5 styling:

| File | DataTable CSS | DataTable JS | BS5 Integration |
|------|---------------|--------------|-----------------|
| `resources/views/clients/index.blade.php` | ✓ | ✓ | `dataTables.bootstrap5.min.css`, `dataTables.bootstrap5.min.js` |
| `resources/views/transactions/index.blade.php` | ✓ | ✓ | Same |
| `resources/views/scholars/index.blade.php` | ✓ | ✓ | Same |
| `resources/views/households/index.blade.php` | ✓ | ✓ | Same |
| `resources/views/payouts/attendance.blade.php` | ✓ | ✓ | Same |
| `resources/views/admin/users/index.blade.php` | ✓ | ✓ | Same |
| `resources/views/admin/audit_logs/index.blade.php` | ✓ | ✓ | Same |
| `resources/views/unpaid_verifications/index.blade.php` | ✓ | ✓ | Same |
| `resources/views/duplicates/index.blade.php` | ✓ | ✓ | Same |

### 4.2 DataTables BS5 Integration Files

**CSS** (`resources/views/layouts/app.blade.php` line ~10-11):
```
https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css
```

**JS** (`resources/views/layouts/app.blade.php` line ~107):
```
https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js
```

### 4.3 Migration Path for DataTables

**Option A (Recommended)**: Use plain DataTables CSS + custom Tailwind styling
1. Remove `dataTables.bootstrap5.min.css` and `dataTables.bootstrap5.min.js`
2. Keep `jquery.dataTables.min.js` (core)
3. Add custom DataTables Tailwind classes in `app.css` or `ui.css`

**Option B**: Use DataTables with Tailwind CSS plugin (third-party)
- Requires npm install of `datatables.net` + `datatables.net-tailwindcss`
- More invasive change; not recommended for this migration

---

## 5. Shared Component Inventory

### 5.1 Existing Shared Components (Bootstrap-dependent)

| Component | Location | BS Dependency | Migration Status |
|-----------|----------|---------------|------------------|
| Sidebar | `partials/sidebar.blade.php` | offcanvas, collapse | PENDING |
| Navbar | `partials/navbar.blade.php` | dropdown | PENDING |
| Confirm Modal | `partials/confirm-modal.blade.php` | modal | PENDING |
| Record View Modal | `partials/record-view-modal.blade.php` | modal | PENDING |
| Details Panel | `partials/details-panel.blade.php` | — | DONE (Bootstrap-free) |
| Filter Chips | `components/FilterChips.js` | — | DONE (Bootstrap-free) |

### 5.2 New Shared Components Required (Tailwind)

| Component | Purpose | Replacement For |
|-----------|---------|-----------------|
| `<x-tailwind.modal>` | Reusable modal | Bootstrap modal (20 calls) |
| `<x-tailwind.toast>` | Toast notifications | Bootstrap toast (5 calls) |
| `<x-tailwind.offcanvas>` | Slide-in panel | Bootstrap offcanvas (sidebar) |
| `<x-tailwind.dropdown>` | Dropdown menu | Bootstrap dropdown (navbar) |
| `<x-tailwind.accordion>` | Collapsible sections | Bootstrap accordion (_gip) |
| `<x-tailwind.input-group>` | Input prepend/append | Bootstrap input-group |
| `<x-tailwind.datatable>` | DataTable wrapper | Plain DataTables + custom styling |

---

## 6. Module-by-Module Migration Matrix

### 6.1 Client-Facing Modules

| Module | Bootstrap CSS | Bootstrap JS | DataTables BS5 | Tailwind Status |
|--------|---------------|--------------|----------------|-----------------|
| clients | ✓ (form) | ✓ (modal, toast) | ✓ | PARTIAL (Batch E/F/G done) |
| transactions | ✓ | ✓ (modal) | ✓ | NOT STARTED |
| scholars | ✓ | ✓ (modal) | ✓ | NOT STARTED |
| households | ✓ (create) | ✓ (toast) | ✓ | NOT STARTED |
| payouts | ✓ | ✓ (modal) | ✓ | NOT STARTED |
| unpaid_verifications | ✓ | ✓ (modal) | ✓ | NOT STARTED |

### 6.2 Admin Modules

| Module | Bootstrap CSS | Bootstrap JS | DataTables BS5 | Tailwind Status |
|--------|---------------|--------------|----------------|-----------------|
| admin/users | ✓ | ✓ (modal) | ✓ | NOT STARTED |
| admin/audit_logs | ✓ | ✓ (modal) | ✓ | NOT STARTED |
| duplicates | ✓ | ✓ (modal) | ✓ | NOT STARTED |

### 6.3 Scanner/QR Modules

| Module | Bootstrap CSS | Bootstrap JS | DataTables BS5 | Tailwind Status |
|--------|---------------|--------------|----------------|-----------------|
| scanners/scan | ✓ | ✓ (modal) | — | NOT STARTED |
| qr/viewer | ✓ | ✓ | — | NOT STARTED |

### 6.4 Student Modules

| Module | Bootstrap CSS | Bootstrap JS | DataTables BS5 | Tailwind Status |
|--------|---------------|--------------|----------------|-----------------|
| students/verify | ✓ | — | — | NOT STARTED |
| students/update-photo | ✓ | — | — | NOT STARTED |
| students/photo-upload | ✓ | ✓ | — | NOT STARTED |

### 6.5 Self-Service Modules

| Module | Bootstrap CSS | Bootstrap JS | DataTables BS5 | Tailwind Status |
|--------|---------------|--------------|----------------|-----------------|
| grantee_update/self-service | ✓✓ | ✓ | — | NOT STARTED (highest BS usage) |
| unpaid_verifications/self-service | ✓ | ✓ (modal) | — | NOT STARTED |

### 6.6 Core Modules

| Module | Bootstrap CSS | Bootstrap JS | DataTables BS5 | Tailwind Status |
|--------|---------------|--------------|----------------|-----------------|
| auth/login | ✓ | — | — | PARTIAL |
| dashboard | ✓ | — | — | PARTIAL |
| sessions/online | ✓ | — | — | PARTIAL |

---

## 7. Standalone Pages Audit

### 7.1 Pages with Full Bootstrap CDN (CSS + JS)

| Page | CSS | JS | BS Classes | BS JS API |
|------|-----|-----|------------|-----------|
| `qr/viewer.blade.php` | ✓ | ✓ | ~30 | None (declarative only) |
| `students/photo-upload.blade.php` | ✓ | ✓ | ~40 | modal (1) |
| `grantee_update/self-service.blade.php` | ✓ | ✓ | ~100 | None (declarative only) |
| `unpaid_verifications/self-service.blade.php` | ✓ | ✓ | ~25 | modal (1) |

### 7.2 Pages with Bootstrap CSS Only

| Page | CSS | BS Classes |
|------|-----|------------|
| `auth/login.blade.php` | ✓ | ~5 |
| `students/verify.blade.php` | ✓ | ~3 |
| `students/update-photo.blade.php` | ✓ | ~5 |

---

## 8. Coexistence Architecture

### 8.1 Current Load Order (via `layouts/app.blade.php`)

```
1. Bootstrap 5.3.2 CSS (CDN)              ← line 9
2. DataTables Bootstrap 5 CSS (CDN)        ← line 10
3. @vite('resources/css/app.css')          ← line 11 (Tailwind + custom)
4. public/css/ui.css                       ← line 12 (overrides)
```

### 8.2 CSS Cascade

```
Bootstrap 5.3.2 base styles
    ↓
DataTables BS5 styles
    ↓
Tailwind base layer (no Preflight!)
    ↓
app.css custom styles (.btn-gold, .btn-navy, etc.)
    ↓
ui.css overrides (Bootstrap variant overrides, tokens)
```

### 8.3 Critical: Preflight is Disabled

`resources/css/app.css` line ~1-10:
```css
@import "tailwindcss";
/* Note: Preflight is deliberately NOT imported to avoid fighting Bootstrap Reboot */
```

**Implication**: When Bootstrap is removed, Preflight **must** be enabled. This may cause:
- Margin/padding resets on `<body>`, `<h1>`-`<h6>`, `<p>`, etc.
- Box-sizing changes
- Image `display: block` default

**Mitigation**: Enable Preflight, then audit all views for regressions.

---

## 9. Module Migration Order

### Recommended Sequence

| Phase | Module | Rationale |
|-------|--------|-----------|
| 1 | DataTables screens (11 files) | Centralized change; highest ROI |
| 2 | Shared components (sidebar, navbar, modals) | Foundation for all modules |
| 3 | Light-usage modules (auth, sessions, dashboard) | Quick wins, low risk |
| 4 | Medium-usage modules (clients, scanners, students) | Moderate effort |
| 5 | Heavy-usage modules (grantee_update, self-service) | Highest effort, most BS classes |
| 6 | Standalone pages | Remove CDN `<script>` + `<link>` tags |
| 7 | Remove Bootstrap CDN | Final cleanup + Preflight enable |

---

## 10. Phase 1 Implementation Plan

### 10.1 DataTables Migration (11 screens)

**Step 1**: Create custom DataTables Tailwind CSS in `app.css` or `ui.css`

```css
/* DataTables Tailwind overrides */
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter {
  @apply mb-4 text-sm;
}

.dataTables_wrapper .dataTables_info {
  @apply text-sm text-gray-600;
}

.dataTables_wrapper .dataTables_paginate {
  @apply mt-4 flex justify-end gap-1;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
  @apply px-3 py-1 text-sm border border-gray-300 rounded hover:bg-gray-100;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
  @apply bg-blue-600 text-white border-blue-600;
}
```

**Step 2**: Remove BS5 DataTables integration from `layouts/app.blade.php`

- Line 10: Remove `dataTables.bootstrap5.min.css`
- Line 107: Remove `dataTables.bootstrap5.min.js`

**Step 3**: Verify all 11 index screens render correctly

---

## 11. Risks and Constraints

### 11.1 Prototype is Source of Truth

> "The prototype (referenced as a view-only Master in a separate repo) is the source of truth for UX and design. v2 must match the prototype's UX; it must not be redesigned."

**Impact**: Tailwind migration must preserve existing UX patterns, not introduce new ones.

### 11.2 Coexistence Required Until Full Migration

Bootstrap CDN must remain active until **all 20+ views** are migrated and **all JS API calls** (20 modal + 5 toast + 3 offcanvas + 1 collapse + 1 dropdown) are replaced.

### 11.3 DataTables BS5 Integration is Blocker

11 index screens depend on `dataTables.bootstrap5.min.css` + `dataTables.bootstrap5.min.js`. Removing these without replacement will break table styling.

### 11.4 Preflight Enablement Risk

Enabling Tailwind Preflight after Bootstrap removal may cause layout regressions in views that rely on Bootstrap's default margins/paddings.

### 11.5 Standalone Pages Have Independent CDN Loads

7 standalone pages load Bootstrap CDN independently. Each must be updated individually to remove `<script>` + `<link>` tags.

---

## 12. Verification Checklist

### Pre-Removal Verification

- [ ] All 20+ Blade views have equivalent Tailwind markup (no BS class remnants)
- [ ] All 20 modal JS instances replaced with custom modal component
- [ ] All 5 toast JS instances replaced with custom toast component
- [ ] All 11 DataTables screens migrated to plain DataTables + Tailwind styling
- [ ] All 7 standalone pages updated to remove BS CDN `<script>` + `<link>` tags
- [ ] Sidebar offcanvas replaced with custom Alpine.js or Tailwind solution
- [ ] Sidebar collapse replaced with custom Alpine.js or Tailwind solution
- [ ] Navbar dropdown replaced with custom Alpine.js or Tailwind solution
- [ ] Preflight enabled in `app.css`
- [ ] All views audited for Preflight regressions
- [ ] `ui.css` Bootstrap variant overrides removed or simplified
- [ ] No remaining `data-bs-*` attributes in any Blade or JS file
- [ ] No remaining `bootstrap.Modal`, `bootstrap.Toast`, etc. in any JS file
- [ ] `package.json` remains Bootstrap-free (already confirmed)
- [ ] All tests pass (`php artisan test`)

### Post-Removal Verification

- [ ] Bootstrap CDN `<script>` + `<link>` tags removed from `layouts/app.blade.php`
- [ ] Bootstrap CDN `<script>` + `<link>` tags removed from 7 standalone pages
- [ ] `dataTables.bootstrap5.min.css` and `dataTables.bootstrap5.min.js` removed
- [ ] No 404 errors in browser console
- [ ] All modals, toasts, dropdowns, offcanvas, collapse work correctly
- [ ] All DataTables render with correct styling
- [ ] All forms have correct input styling
- [ ] All buttons render with correct variants (btn-gold, btn-navy, etc.)
- [ ] All layout utilities (row, col-*, d-*, m-*, p-*) replaced with Tailwind equivalents

---

## Appendix A: Bootstrap CSS Class Inventory

### Classes with Direct Tailwind Equivalent

| BS Class | Tailwind Equivalent | Count |
|----------|---------------------|-------|
| `.d-flex` | `flex` | 15+ |
| `.d-none` | `hidden` | 10+ |
| `.d-block` | `block` | 5+ |
| `.row` | `grid grid-cols-12` or `flex flex-wrap` | 20+ |
| `.col-*` | `col-span-*` or `w-*/*` | 30+ |
| `.m-*` | `m-*` | 10+ |
| `.mt-*` | `mt-*` | 10+ |
| `.mb-*` | `mb-*` | 10+ |
| `.p-*` | `p-*` | 5+ |
| `.text-center` | `text-center` | 10+ |
| `.text-primary` | `text-blue-600` | 3+ |
| `.fw-bold` | `font-bold` | 5+ |
| `.fs-*` | `text-*` | 3+ |
| `.table` | `w-full` | 10+ |
| `.table-bordered` | `border border-gray-200` | 5+ |
| `.img-fluid` | `max-w-full h-auto` | 2+ |
| `.min-vh-100` | `min-h-screen` | 1+ |
| `.list-group` | Custom class needed | 2+ |
| `.list-group-item` | Custom class needed | 5+ |

### Classes Requiring Custom CSS

| BS Class | Purpose | Custom Approach |
|----------|---------|-----------------|
| `.form-control` | Text input styling | Already in `ui.css` |
| `.form-select` | Select input styling | Already in `ui.css` |
| `.form-check` | Checkbox/radio group | Need custom class |
| `.input-group` | Input prepend/append | Need custom flexbox |
| `.accordion` | Collapsible section | Need custom component |
| `.toast` | Notification popup | Need custom component |
| `.offcanvas` | Slide-in panel | Need custom component |
| `.btn-close` | Close button | Need custom class |

---

## Appendix B: Bootstrap JS API Inventory

### Modal (20 calls)

```javascript
// Pattern 1: Instantiation
const modal = new bootstrap.Modal(document.getElementById('modal-id'));
modal.show();

// Pattern 2: Hide
modal.hide();

// Pattern 3: Event listener
modalEl.addEventListener('hidden.bs.modal', function() { ... });
```

**Files**: clients/index, clients/_details, transactions/index, scholars/index, payouts/attendance, admin/users/index, admin/audit_logs/index, unpaid_verifications/index, unpaid_verifications/self-service, duplicates/index, scanners/scan

### Toast (5 calls)

```javascript
// Pattern 1: Instantiation + show
const toast = new bootstrap.Toast(document.getElementById('toast-id'));
toast.show();

// Pattern 2: Auto-hide
const toastEl = document.getElementById('toast-id');
const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
toast.show();
```

**Files**: clients/index, clients/_details, layouts/app

### Offcanvas (declarative only)

```html
<!-- Pattern: Toggle -->
<button data-bs-toggle="offcanvas" data-bs-target="#sidebar">Menu</button>

<!-- Pattern: Dismiss -->
<button data-bs-dismiss="offcanvas">Close</button>
```

**Files**: layouts/app, partials/sidebar

### Collapse (declarative only)

```html
<!-- Pattern: Toggle -->
<a data-bs-toggle="collapse" href="#section-id">Toggle</a>
```

**Files**: partials/sidebar

### Dropdown (declarative only)

```html
<!-- Pattern: Toggle -->
<button data-bs-toggle="dropdown">Menu</button>
<div class="dropdown-menu">...</div>
```

**Files**: partials/navbar

---

## Appendix C: DataTables Integration Details

### 11 Screens Using DataTables BS5

| # | Screen | Table ID | Columns |
|---|--------|----------|---------|
| 1 | clients/index | `clients-table` | Last Name, First Name, Sex, Barangay, Status |
| 2 | transactions/index | `transactions-table` | Date, Type, Client, Amount, Status |
| 3 | scholars/index | `scholars-table` | Last Name, First Name, School, Course, Year |
| 4 | households/index | `households-table` | Last Name, First Name, Barangay, Status |
| 5 | payouts/attendance | `attendance-table` | Last Name, First Name, Status |
| 6 | admin/users/index | `users-table` | Username, Name, Role, Status |
| 7 | admin/audit_logs/index | `audit-table` | Date, User, Action, Model, Details |
| 8 | unpaid_verifications/index | `unpaid-table` | Last Name, First Name, Amount, Status |
| 9 | duplicates/index | `duplicates-table` | Name, Similarity, Status |
| 10 | sessions/online | `online-table` | User, IP, Last Activity |
| 11 | dashboard | (multiple) | Various |

### DataTables Configuration Pattern

```javascript
$(document).ready(function() {
    $('#table-id').DataTable({
        processing: true,
        serverSide: true,
        ajax: { url: '...', type: 'GET' },
        columns: [ ... ],
        order: [[0, 'desc']],
        pageLength: 10
    });
});
```

---

## Appendix D: Shared Component Requirements

### Modal Component

**Required features**:
- Show/hide API
- Backdrop click to close
- Escape key to close
- Customizable size (sm, md, lg, xl)
- Header, body, footer slots
- Event callbacks (onShow, onHide, onHidden)

**BS classes to replace**: `.modal`, `.modal-dialog`, `.modal-content`, `.modal-header`, `.modal-body`, `.modal-footer`, `.btn-close`

### Toast Component

**Required features**:
- Auto-hide with configurable delay
- Manual dismiss
- Positioning (top-right, bottom-right)
- Variant support (success, error, warning, info)
- Stacking support

**BS classes to replace**: `.toast`, `.toast-container`, `.toast-body`

### Offcanvas Component

**Required features**:
- Slide-in from left/right/top/bottom
- Backdrop support
- Close on escape
- Close on backdrop click
- Responsive behavior (offcanvas-lg)

**BS classes to replace**: `.offcanvas`, `.offcanvas-start`, `.offcanvas-lg-start`, `.offcanvas-backdrop`

### Dropdown Component

**Required features**:
- Toggle on click
- Close on outside click
- Keyboard navigation
- Positioning (auto, top, bottom, left, right)

**BS classes to replace**: `.dropdown`, `.dropdown-menu`, `.dropdown-item`, `.dropdown-toggle`

---

## Appendix E: Migration Effort Estimates

| Phase | Scope | Files | Est. Effort |
|-------|-------|-------|-------------|
| Phase 1 | DataTables migration | 12 | Small |
| Phase 2 | Shared components | 5 | Medium |
| Phase 3 | Light-usage modules | 5 | Small |
| Phase 4 | Medium-usage modules | 10 | Medium |
| Phase 5 | Heavy-usage modules | 5 | Large |
| Phase 6 | Standalone pages | 7 | Medium |
| Phase 7 | Remove CDN + enable Preflight | 1 | Small |
| **Total** | | **45** | **Large** |

---

## Appendix F: Open Questions

1. **Alpine.js adoption**: Should we adopt Alpine.js for declarative interactivity (dropdowns, collapse, modals) or write custom vanilla JS components?
   - **Recommendation**: Alpine.js (lightweight, declarative, no build step)
   
2. **DataTables library**: Keep jQuery DataTables or migrate to vanilla JS alternative?
   - **Recommendation**: Keep jQuery DataTables (mature, well-documented, low risk)

3. **Preflight timing**: Enable Preflight immediately or wait until full migration?
   - **Recommendation**: Enable Preflight in Phase 7 (final) to minimize regressions during migration

4. **`ui.css` fate**: Keep, simplify, or remove after Bootstrap removal?
   - **Recommendation**: Simplify (remove Bootstrap variant overrides, keep tokens and custom components)

---

**End of Forensic Audit**
