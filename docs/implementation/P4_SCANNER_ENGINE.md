# P4 — Scanner Engine (14 Scanner Pages, 8 Modes, Config-Driven)

> **Status:** Delivered, tested, and documented.
> **Scope of this document:** the P4 scanner engine — `config/scanner.php`
> (the single source of truth), `ScanService` (lookup + save, 8 behavioral
> modes), `ScannerController` (thin HTTP layer), the shared
> `scanners/scan.blade.php` view, the route registration loop, and the
> sidebar integration. v1's 14 scanner pages and 15 action handlers collapse
> into this one engine with every v1 quirk preserved.

---

## 1. Purpose

v1 had ~14 scanner pages (`scanner_ceap.php`, `scanner_tupad.php`, …) and 15
action handlers (`scanner_*_action.php`), each a near-copy of the same QR-scan
→ lookup → save flow with per-program differences in duplicate rules, insert
templates, audit events, and (for payout) attendance semantics. That
copy-paste was the single biggest maintenance risk in the codebase.

P4 replaces all of it with:

- **One config file** describing every scanner key: mode, program(s), lookup
  strategy, insert/update template, duplicate rule, audit event, attendance
  table, and UI options.
- **One service** implementing two verbs — `lookup()` and `save()` — with
  behavior dispatched purely on the config's `mode`.
- **One Blade view** plus **one controller**, and **literal per-key routes**
  that each carry their own `page:scanner_*.php` ACL gate.

Adding a new scanner is (usually) a config entry, not a new page.

---

## 2. Legacy v1 behavior (what we ported)

The v1 scanner family (documented exhaustively in
`docs/SCANNER_ANALYSIS.md` and `docs/SCANNER_CONFIGURATION_MATRIX.md`):

- **CEAP / CEAP_NEW / CEDSSG / CEDSSG_NEW / OTEA / OTCES** — fixed insert:
  `type=SCHOLARSHIP`, `status=PENDING PAYOUT`, fixed `remarks` string
  (semester-bound, e.g. `1ST SEM SY2025-2026 DOCS SUBMITTED`), fixed
  `suggested_amount` (5000/10000/3000/5000), and `payout_date` **present on the
  `_NEW`/OTEA/OTCES entries but absent (null) on plain CEAP/CEDSSG** — a
  column-drift quirk that must be preserved per-program.
- **CEDSSG_update** — lookup a pending `2ND SEM` transaction by patient name,
  then a single idempotent UPDATE setting `status=PAID`, `amount_paid=12500`,
  `date_paid` (user input). No duplicate check (the "record once" guarantee is
  that each pending 1st-sem transaction can be paid once).
- **TODA** — date-guarded insert, `type=CASH RELIEF ASSISTANCE`,
  `status=PAID`, `amount_paid` from input, duplicate keyed on
  `(client, program, date_applied)`.
- **TUPAD** — date-guarded insert, `type=CASH FOR WORK`, `status=PAID`,
  fixed `amount_paid=4680.00`, remarks `TUPAD LGBTQIA+`; duplicate keyed on
  `(client, program, date_applied)`, and **a v1 quirk: the audit payload's
  remarks differ from the stored remarks** (`TUPAD REGISTRATION 2025` in the
  log vs `TUPAD LGBTQIA+` on the row).
- **new_scholars** — `tbl_exam` → `tbl_results` → approved program derived
  server-side; insert template per approved program (CEAP_NEW/OTEA/OTCES/
  CEDSSG_NEW with their own remarks/amount/payout_date).
- **ongoing_scholars** — program derived from the client's latest transaction,
  `remarks` per program (`2ND SEM …`), duplicate = `remark_key + patient_name`,
  **no audit log**.
- **generic** — free-form form with exactly six programs (AICS, AKAP, MAIP,
  TUPAD, CEDSSG, CEAP), beneficiary options (self/custom/existing), **no
  duplicate rule, no audit**.
- **payout** — seat-aware attendance: `tbl_seats2` join on name vs
  `tbl_clients.full_name`, one scan per transaction recorded in
  `tbl_payout_scans2` (`UNIQUE(transaction_id)` is the belt, app pre-check the
  suspenders), **no audit**.
- **payout_unpaid** — partial-match patient lookup on transactions, one scan
  per transaction in `tbl_payout_scans_unpaid`, **no audit**.
- **`lookup_ignore_scan`** — a special payout lookup action that re-displays a
  **previously scanned** QR's seat details without tripping the duplicate check.

---

## 3. Laravel architecture

```
config/scanner.php                ← 14 keys, modes, templates, gates
ScanService                       ← lookup() + save(), dispatched on mode
ScannerController                 ← show()/lookup()/save() + requireAccess()
resources/views/scanners/scan.blade.php  ← one view for all 14 pages
routes/web.php                    ← foreach over config registers literal URLs
partials/sidebar.blade.php        ← loops config for ACL-gated links
```

Routing detail (the subtle part): each scanner registers

```
GET  scanners/<key>        name=scanners.<key>        middleware page:<page>
POST scanners/<key>/lookup name=scanners.<key>.lookup  middleware page:<page>
POST scanners/<key>/save   name=scanners.<key>.save    middleware page:<page>
```

with `->defaults('key', $scannerKey)` so the controller still receives the key
as a route parameter while **route names need no argument**. The earlier
`{key}`+`->where` design produced URLs like `scanners/{key}/…` that did not
match `route('scanners.'.$key.'.lookup')`; the fix was literal URL segments +
`defaults()`. See common mistakes (§13).

---

## 4. Configuration: `config/scanner.php`

Top-level shape: `['scanners' => [<key> => <entry>]]`.

Each entry:

| Key | Meaning | Example |
|---|---|---|
| `key` | literal route/config key | `ceap` |
| `mode` | one of the 8 behavioral modes | `scholarship_transaction` |
| `title` | sidebar + page title | `CEAP Scholarship` |
| `page` | v1 page key for the ACL gate | `scanner_ceap.php` |
| `lookup` | lookup strategy | `client`, `client_geo`, `transaction`, `transaction_partial`, `exam_derived`, `existing_program`, `seat_join` |
| `lookup_miss_message` | miss message with `{scanned}` placeholder | `"Client not found for scanned code: '{scanned}'"` |
| `programs` | allowed program(s); list, single-list, or per-program map | `['CEAP']`; `['CEAP','CEDSSG',…]` (payout); `['CEAP_NEW' => […], …]` (new_scholars) |
| `insert` | insert template: `type`, `status`, `remarks`, `suggested_amount`, `payout_date`, `amount_paid` (`'input'` or fixed) | `{type:'SCHOLARSHIP', status:'PENDING PAYOUT', remarks:'…', suggested_amount:5000, payout_date:'2025-08-18'}` |
| `update` | in-place update template (cedssg_update): `status`, `amount_paid`, `date_paid:'input'` | — |
| `duplicate` | `rule` + `message` (+ optional `show_existing`) | `{rule:'remark_key', message:'Transaction already recorded for this client.'}` |
| `attendance` | payout-scan target table (presence turns on one-scan) | `tbl_payout_scans2` |
| `audit` | audit `action` (+ optional `fields`, `values`); `null` = no audit | `{action:'SCAN-CEAP', fields:[…]}`; null for ongoing/generic/payout |
| `ui` | view options: `fields`, `resume`, `success_message`, `amount_paid_readonly`, `scan_success_sound` | — |

**Duplicate rules:**

| Rule | Key |
|---|---|
| `remark_key` | `client_id + program + remarks` |
| `remark_key_with_name` | `remark_key + patient_name` |
| `date_applied` | `client_id + program + date_applied` |
| `one_scan` | DB `UNIQUE(transaction_id)` + app pre-check |
| `none` | no check |

**Semester-bound constants** (remarks strings, amounts, payout dates) live in
config so staff can move a batch without code changes — that is the whole point.

---

## 5. Service: `ScanService` (`app/Services/ScanService.php`)

Dependency-injected with `AuditService`. Two public verbs:

- `lookup(string $key, string $scanned, string $action = 'lookup')`
- `save(string $key, array $input, User $actor)`
- `config(string $key)` — returns the entry (or `[]`; `ScannerController`
  aborts 404 on empty).

### Lookup strategies

| Strategy | Used by | Behavior |
|---|---|---|
| `client` | CEAP family, OTEA/OTCES, generic | exact `TRIM(full_name) COLLATE utf8mb4_general_ci` match on `tbl_clients` → `{id, full_name}` |
| `client_geo` | TODA, TUPAD | client + municipality name (+ optional barangay when `include_barangay`) |
| `transaction` | cedssg_update | latest `PENDING PAYOUT` tx whose `patient_name` TRIM-matches, program in list, remarks LIKE `%2ND SEM%` → `{transaction_id, …}` |
| `transaction_partial` | payout_unpaid | latest tx where `LOWER(patient_name) LIKE %input%` in program list; already-scanned pre-check |
| `exam_derived` | new_scholars | client → `tbl_exam` (collation-aware exact fullname) → `tbl_results.approved` (must be non-empty) → program uppercased |
| `existing_program` | ongoing_scholars | client → latest tx program in map → program |
| `seat_join` | payout | `tbl_seats2` ⋈ clients (exact name, then partial) ⋈ transactions → seat block + amounts + comments; already-scanned pre-check |
| `lookup_ignore_scan` | payout (action) | latest tx by patient name LEFT JOIN seats2, **skips** the duplicate check — re-displays scanned details |

### Save modes (dispatch on `config['mode']`)

| Mode | Behavior |
|---|---|
| `scholarship_transaction` | `remark_key` dup check → create from `programs[0]` + `insert` template (+ `payout_date` only when configured) → audit |
| `date_guarded_transaction` | `date_applied` dup check (with optional `show_existing` snapshot) → create with user date/amount (`amount_paid:'input'` or fixed) → audit |
| `update_in_place` | idempotent `UPDATE` of the found transaction (status/amount_paid/date_paid) → audit |
| `exam_derived` | re-derive exam→results→program, per-program insert template, `remark_key` dup → audit with `SCAN-{program}` action interpolation |
| `validate_existing` | program from latest tx, per-program remarks/amount, `remark_key_with_name` dup, **no audit** |
| `seat_attendance` / `unpaid_attendance` | `INSERT` into the `attendance` table with `(transaction_id, scanned_text, scanned_by)`; catches `QueryException` code `23000` → "already been scanned"; **no audit** |
| `generic_form` | free-form `Transaction::create` from UI fields with `resolveGenericPatient` (self/custom/existing); **no dup, no audit** |

`writeAudit()` is the single audit writer for the scanner: expands
`{program}` in the action, builds the payload from `audit.fields` with
`audit.values` overrides (the TUPAD remarks divergence is a config `values`
entry), and calls `AuditService::log(actor, action, 'tbl_transactions', id,
null, json)`.

---

## 6. Controller: `ScannerController`

(`app/Http/Controllers/ScannerController.php`)

- `show(string $key)` — reads the config (404 if empty), builds the
  `$scannerJs` array (key, mode, `lookupUrl`/`saveUrl` via no-arg routes,
  resume, attendance flag, generic flag, fields, success message, scan sound),
  and renders the single Blade view. **The JS config is built here, not in
  Blade** — see common mistakes §13.
- `lookup(Request, string $key)` — `requireAccess`, requires non-blank
  `scanned`, returns `response()->json($scanner->lookup($key, $scanned, $action))`.
- `save(Request, string $key)` — `requireAccess`, returns
  `json($scanner->save($key, $request->all(), $request->user()))`.
- `requireAccess($key, $user)` — 404 on unknown key, 403 unless
  `AccessControlService->canAccessPage($user, $config['page'])`. Defense in
  depth under the route middleware gate.

---

## 7. View: `resources/views/scanners/scan.blade.php`

One view, all 14 pages. Renders from `$config`:

- Title + shared header.
- **Constant date inputs** (`date_applied`, `date_paid`) when `ui.fields`
  contains them; **amount-paid input** when `amount_paid` is a field, or a
  readonly amount box when `ui.amount_paid_readonly` is set (cedssg_update).
- The `#reader` QR element driven by `html5-qrcode` (unpkg CDN).
- `#scanResultArea` with mode-dependent detail rendering:
  - `seat_attendance` → program + full name + town + **SECTION/BOX/ROW/SEAT**
    highlighted lines + comments;
  - `unpaid_attendance` → program + name + status + comments;
  - `update_in_place` → name/program/remarks;
  - default → name + client id + optional municipality/barangay/program.
- The **generic form** block (`mode === generic_form`): program select (the 6
  configured programs), beneficiary radios (self / custom name input /
  existing-client search reusing the `transactions.clients-search` route),
  date/type/status selects (types/statuses from `config['ui']`),
  remarks/comments, amounts, payout/date-paid dates, GWA, units.
- A shared message modal + success/error sound `<audio>` elements
  (`sounds/success.mp3`, `sounds/not_found.mp3`).

Client JS flow (`const SCANNER = @json($scannerJs)`):

1. `onScanSuccess` clears the scanner, POSTs `action=lookup&scanned=…` to
   `SCANNER.lookupUrl`.
2. On success: stash `lastScan`, show details (or the generic form).
3. On "already been scanned" **and** `SCANNER.attendance`: POST
   `action=lookup_ignore_scan` to re-render seat details with
   `alreadyScanned: true` (Confirm is blocked for those).
4. Save button → POST `action=save` with the mode-appropriate id field
   (`id` normally, `transaction_id` for `update_in_place`) plus configured
   date/amount fields and `scanned` for attendance modes.
5. After a modal, `SCANNER.resume` decides between re-rendering the scanner in
   place (`resumeAfterModal`) or a full page reload — reload is the default so
   every save is safe on a multi-user queue.

---

## 8. Sidebar integration

`partials/sidebar.blade.php` loops `config('scanner.scanners')` and renders a
link for every scanner the user may access (via
`$acl->canAccessPage($user, $scannerConfig['page'])`), with the active class
from `request()->routeIs('scanners.'.$scannerKey)`. Adding a scanner in config
automatically adds its menu link — no manual menu edit.

---

## 9. DB tables involved

| Table | Role | Notes |
|---|---|---|
| `tbl_clients` | lookup by `full_name` | collation-aware exact match; seats join on `full_name` |
| `tbl_transactions` | insert/update target, cedssg_update lookup, ongoing/program derivation, payout join | `remarks` carries scanner keys (`2ND SEM`, `1ST SEM …`, `TUPAD LGBTQIA+`) |
| `tbl_exam` / `tbl_results` | new_scholars program derivation | join on `exam_no`; `approved` non-empty |
| `tbl_seats2` | payout seat lookup | join `LOWER(TRIM(name))` vs `full_name` (exact then partial) |
| `tbl_payout_scans2` / `tbl_payout_scans_unpaid` | attendance writes | `transaction_id` **UNIQUE** = the belt; FK `scanned_by` → `tbl_users` |
| `tbl_audit_logs` | scanner audits (via `AuditService`) | actions `SCAN-*`, `UPDATE-CEDSSG-PAYMENT` |
| `tbl_municipalities` / `tbl_barangays` | client_geo lookup | name resolution |

`tbl_payout_scans` (no `2`) is a legacy sibling table **not used** by the
engine — leave untouched.

---

## 10. Business rules

1. **No key branching in code** — `ScanService::save()` is a `match` on
   `config['mode']`; behavior differences are config, not `if ($key === …)`.
2. `payout_date` is inserted only when configured (the CEAP/CEDSSG vs `_NEW`
   column drift is preserved per-program).
3. Duplicate semantics per rule (see §4 table); `one_scan` uses both the DB
   unique constraint (belt) and an app pre-check (suspenders).
4. Attendance modes never write `tbl_transactions`; they only append to the
   payout-scan tables. The scan **is** the attendance.
5. `ongoing_scholars`, `generic`, `payout`, `payout_unpaid` **do not audit**
   (v1 parity). Scholarship inserts and cedssg_update do.
6. TUPAD's stored remarks and audit remarks intentionally differ (config
   `audit.values` override).
7. Student/`exam_derived` program names are uppercased from `tbl_results.approved`.
8. Already-scanned QR codes can be re-displayed (lookup_ignore_scan) but never
   re-saved.

---

## 11. Security notes

- Every scanner route is gated twice: route middleware `page:scanner_*.php`
  **and** `ScannerController::requireAccess`.
- Lookup/dup queries are fully parameter-bound; the seat join builds
  placeholders from the config program list (no string interpolation).
- `scanned` text is trimmed and treated as opaque data; it is echoed only in
  server-generated messages (via `str_replace`, rendered by JS as text).
- POST save uses the framework CSRF middleware; the front-end sends
  `X-CSRF-TOKEN`.
- 404 on unknown keys prevents config enumeration; 403 on denied keys.
- The generic form's existing-client search reuses the ACL-gated
  `transactions.clients-search` route.

---

## 12. Performance notes

- Lookups are single indexed queries (`tbl_clients` name indexes,
  `tbl_transactions` program/date indexes); seat join is limited to 1 row.
- Duplicate checks use `exists()` (short-circuits at one row).
- `lookup_ignore_scan` is a bounded single-row query.
- The one shared view and one service keep the asset surface tiny vs v1's 30
  files.

---

## 13. Common mistakes (observed — these were real bugs)

1. **`{key}` + `->where` produced wrong URLs.** Route names were
   `scanners.<key>.lookup` but the registered URL became `scanners/{key}/lookup`
   literally, so `route()` output never matched and lookups 404'd. **Fix:
   literal URL segments + `->defaults('key', $scannerKey)`** (routes/web.php
   scanner loop).
2. **Multi-line `@json([…])` in Blade truncated.** Blade treats `@` directives
   as end-of-line; the formatted array broke into a `ParseError`. **Fix: build
   `$scannerJs` in the controller and emit `const SCANNER = @json($scannerJs);`
   on one line.**
3. **Passing `$key` as a route arg to `route('scanners.'.$key)`** after the
   defaults fix — the name has no `{key}` placeholder anymore; drop the arg.
4. **Assuming `patient_name` == `full_name`** in the attendance/update lookups
   — they use different name conventions; keep the trim/collation handling.
5. **Adding a new scanner without an entry in `config/scanner.php`** — a route
   is generated only from config; the page will 404.
6. **Editing the shared Blade per-key** — behavior must come from config; the
   view branches only on `mode`/`ui.fields`.

---

## 14. Never-change list

- Never reintroduce per-key branching in `ScanService` or the view.
- Never change duplicate rules / templates / audit events without an ADR and a
  regression test (14 `ScannerTest` cases pin each mode).
- Never relax the `UNIQUE(transaction_id)` on the payout-scan tables.
- Never change the lookup collation semantics (`utf8mb4_general_ci` exact
  name match) — scanner parity depends on it.
- Never write audit rows from a scanner directly; always through
  `ScanService::writeAudit` → `AuditService`.
- Never move the scanner route loop out of `foreach (config('scanner.scanners')…)`
  into hand-written routes (they would drift from config).
- Never bump `html5-qrcode`/CDN assets without testing the scan → lookup → save
  flow on a real device.

---

## 15. Future improvements

- P5 will add the **payout attendance list screens** (`scanned_payouts*.php`)
  on top of the same `tbl_payout_scans2`/`_unpaid` tables the engine already
  writes; the engine is the write side, P5 is the read/report side.
- Consider audio feedback preferences, offline buffer for flaky cameras, and a
  QR-preview debug page — all config-gated, no behavior change.
- Semester constants could move to a seasonal config file so the annual
  "roll to next SY" is a single edit.

---

## 16. Blueprint / ADR references

- `docs/ENGINEERING_BLUEPRINT.md` §1.8 (Scanner subsystem), §2 row for
  `scanner_*.php` + `scanner_*_action.php`, §3 rows for the scan/seat/audit
  tables.
- `docs/ARCHITECTURE_DECISION.md` — **ADR-004** (program config–driven scanner
  engine).
- `docs/SCANNER_ANALYSIS.md` — the exhaustive v1 scan behavior reference.
- `docs/SCANNER_CONFIGURATION_MATRIX.md` — the config↔v1 mapping matrix.
- `docs/IMPLEMENTATION_LOG.md` — the dated P4 entry with the 14-test ScannerTest
  results and the full-suite verification (74 tests).
- `docs/REQUIREMENTS_ANALYSIS.md` FR-6 (QR scanning & payout attendance).
