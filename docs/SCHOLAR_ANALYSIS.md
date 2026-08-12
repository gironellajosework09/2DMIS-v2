# P6 Scholars Module Analysis — v1 vs v2

> **Status:** Canonical analysis for the P6 (Scholars / GIP) module.
> This document is the merged, restructured successor of the approved
> **P6 Phase 1 audit (2026-08-07, "REFACTOR + BUILD")** and the original
> scholars gap notes. It follows the naming convention of the other module
> analysis documents (`SCANNER_ANALYSIS.md`, `SCANNER_CONFIGURATION_MATRIX.md`).
>
> Analysis method: every v1 file below was read in full against the read-only
> legacy system at `C:\xampp\htdocs\system`, and the v2 implementation was
> inventoried against it, against `docs/implementation/P6_SCHOLARS.md`,
> `docs/ENGINEERING_BLUEPRINT.md`, the ADRs, and `AGENTS.md`. The audit
> produced no code; the subsequent Phase 2 cleanup applied the confirmed
> parity requirements and approved decisions (§8) to the scholar registry.
>
> Scope: scholar enrollment (`tbl_scholar_info`), GIP (`tbl_gip_info`),
> grantee self-updates (`tbl_update_logs`), scholarship reports, and the QR
> viewer. The scanner side of scholars (P4) is out of scope except where the
> QR payload depends on it.

---

## 1. V1 inventory and behavior

### 1.1 File inventory

| Category | Files |
|---|---|
| **Scholar Registry** | `scholars.php`, `fetch_scholars.php`, `save_scholarship.php`, `update_client_id.php` |
| **GIP** | `save_gip.php` |
| **Grantee Updates** | `save_grantee_update.php`, `disabled_update_grantee.php`, `update_logs.php`, `fetch_update_logs.php`, `verify_mobile.php` (dependency), `search_grantee.php` (shared) |
| **Reports** | `scholarship_reports.php`, `fetch_scholarship_reports.php`, `export_scholarship_reports.php` |
| **QR Features** | `view_qrcode.php` |
| **Scanner Engine Interactions** | `scanner_new_scholars.php`, `scanner_new_scholars_action.php`, `scanner_ongoing_scholars.php`, `scanner_ongoing_scholars_action.php`, `scanner_cedssg_update.php`, `scanner_cedssg_update_action.php` (P4, already ported) |

### 1.2 `scholars.php` + `fetch_scholars.php` — Scholar Registry

- Session + `restriction.php` gated; DataTables server-side.
- Columns: **ID, Client ID (inline "Edit" relink button → `update_client_id.php`),
  Full Name, Program, Barangay, Town**.
- **No "Add Scholar" button** (the link is commented out; creation happens from
  the `view_client.php#collapseScholarship` modal).
- Default order `[[1,'asc']]` (client_id); pageLength 25.
- `fetch_scholars.php`:
  - Search over `full_name`, `program`, `school` (LIKE).
  - **Subquery-paginate-then-LEFT-JOIN `tbl_exam`** on
    `TRIM(LOWER(si.full_name)) = TRIM(LOWER(ex.fullname))` → exposes
    `ex.barangay`, `ex.town`.
  - Order column default index 1 (`client_id`); `recordsFiltered ==
    recordsTotal` (v1 quirk — the filtered count is never recomputed).

### 1.3 `save_scholarship.php` — Scholar CRUD write

Form posted from `view_client.php#collapseScholarship` modal:
- POST-only; trims all inputs; `$is_regular = isset(...) ? intval(...) : 0`
  (**default 0 when absent**).
- `year_started` is a **varchar built as `"YYYY - YYYY"`** from `year_start` +
  `year_end` text inputs (one-sided allowed; `''` if both empty).
- Upsert on the **latest row for `(client_id, program)`** (`ORDER BY id DESC
  LIMIT 1`); UPDATE rewrites the editable fields; INSERT omits `full_name`.
- **Does NOT write `full_name`, `normalized_name`, or `match_name`.**
- Errors/success redirect back to `view_client.php?id=...#collapseScholarship`;
  error message via `$_SESSION['scholar_error']`.
- The modal marks only `client_id` (hidden) + `program` `required`; every other
  field is optional and stored as `''` when empty (non-strict mode).

### 1.4 `update_client_id.php` — Relink

Session-gated POST; `UPDATE tbl_scholar_info SET client_id = :client_id
WHERE id = :id`; echoes `"success"` or HTTP 400 `"Invalid input"`. Triggered by
the registry's inline Client-ID edit button.

### 1.5 `save_gip.php` — GIP profile

Session-gated POST from `view_client.php#collapseGIP`:
- 17 fields, all `mb_strtoupper`'d (not `ecp_contact_number`, `year_graduated`).
- Upsert on the latest `tbl_gip_info` row per client.
- **Audits to `tbl_audit_logs`** via `log_action`: `UPDATE_GIP` (only if the row
  actually changed) / `ADD_GIP`; `table = 'tbl_clients'`, `record = client_id`,
  old/new full-row JSON.
- **Does NOT write `full_name`, `normalized_name`, or `match_name`.**
- Redirect back to `#collapseGIP`; error via `$_SESSION['gip_error']`.

### 1.6 Grantee self-service

- **`disabled_update_grantee.php`** — **public, standalone "Scholarship Grantee
  Self-Update" form.** The name is misleading; it is **not** a disable/delete
  action. Flow: name autocomplete → **mobile-number verification**
  (`verify_mobile.php?id=...&mobile_no=...`, regex `^09\d{9}$`, with a
  "forgot mobile" bypass link) → municipality verification
  (`search_grantee.php` POST `action=verify`) → editable form → POST
  `save_grantee_update.php` → on success renders a **QR code**
  (`api.qrserver.com`, payload `"LASTNAME, FIRSTNAME MIDDLENAME"` uppercase).
  Huge hardcoded school (~100) / course (~300) / year-level dropdowns.
- **`save_grantee_update.php`** — public JSON POST. Requires a client whose
  **latest** transaction is one of the six programs. Name, municipality and
  barangay are **server-preserved from the DB** (uneditable); editable are
  house_no, mobile_no, email, birthdate, age, sex, civil_status, pwd, ip,
  ip_group, occupation. **Required:** mobile_no, email, birthdate, sex,
  civil_status. Upserts the latest scholar_info for that program (on INSERT
  writes `full_name` as `"LAST, FIRST MIDDLE"` — comma form). **Writes
  `tbl_update_logs`** (`client_id`, `full_name` as `"FIRST MIDDLE LAST"` — no
  comma — `ip_address` = `REMOTE_ADDR`, `action = 'Grantee self-updated their
  information.'`). Transactional; generic failure message.
- **`search_grantee.php`** — public JSON: `?munis=1` municipality list;
  `?q=` autocomplete (clients with a six-program transaction, `full_name`/
  `match_name` LIKE, LIMIT 15); POST `action=verify` (client + municipality
  match + latest qualifying program + latest scholar_info).
- **`verify_mobile.php`** — mobile verification used by the self-update form
  (dependency).
- **`update_logs.php` + `fetch_update_logs.php`** — session-gated viewer:
  `tbl_update_logs` LEFT JOIN clients/municipalities; columns ID, Client ID,
  Full Name (with comma-vs-natural order formatting logic), Town (uppercased),
  IP, Action, Date/Time **converted to Asia/Manila** (`m/d/Y - h:i A`); date
  range filter on `DATE(created_at)`; default order `created_at DESC`.

### 1.7 Reports

- **`scholarship_reports.php`** — session + restriction gated. Filters:
  municipality, barangay (cascade via `get_barangays.php`), program (6),
  submitted (Yes/No), date_from/date_to. Columns: Program, Full Name, Mobile
  No, Sex, Birthdate (`%m/%d/%Y`), Civil Status, Town, Barangay, School, Course,
  Year Level, GWA, Units, Landbank No, Remarks, Date Applied, Regular
  (`Yes/No`), Submitted. Export CSV button.
- **`fetch_scholarship_reports.php`** — DataTables server-side. Base
  `tbl_transactions` (six programs) INNER JOIN clients, **LEFT JOIN scholar_info
  at `MAX(id)` per client**, LEFT JOIN geo. Program filter default = the six
  programs; `regular` = `CASE WHEN is_regular=1`; `submitted` hardcoded `'Yes'`.
  `recordsTotal` = raw transaction count in the six programs.
- **`export_scholarship_reports.php`** — CSV with **UTF-8 BOM**, filename
  `scholarship_reports<Ymd>.csv`. Base is **`tbl_scholar_info`** INNER JOIN
  clients (the inverse of the feed), with gwa/units/remarks/status/date_applied
  pulled via **correlated subqueries** on the latest matching transaction
  (`ORDER BY date_applied DESC, id DESC`); program/date/submitted filters via
  `EXISTS` subqueries; `full_name` includes extension name.

### 1.8 `view_qrcode.php` — QR viewer

Public page. Name autocomplete (`search_grantee.php?q=`), municipality select,
verify (`action=verify`), then builds **`"LASTNAME, FIRSTNAME MIDDLENAME"`**
(client-side, uppercased, **no extension name**) and renders a QR via
`https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=...` with a
download link and "search another" reset.

---

## 2. V2 current implementation

### 2.1 Implementation inventory (as audited)

| Area | v2 file(s) | Notes |
|---|---|---|
| Model — scholar | `app/Models/ScholarInfo.php` | `tbl_scholar_info`, `$timestamps=false`, `$guarded=[]`, `client()` BelongsTo. **Correct.** |
| Model — GIP | `app/Models/GipInfo.php` | `tbl_gip_info`, same shape. **Correct.** |
| Model — update log | `app/Models/UpdateLog.php` | `tbl_update_logs`, same shape. **Correct.** |
| Model — exam | `app/Models/Exam.php` | `tbl_exam`, same shape. **Correct.** |
| Model — results | `app/Models/ExamResult.php` | `tbl_results`, same shape. **Correct.** |
| Controller | `app/Http/Controllers/ScholarController.php` | index/data/create/store/edit/update. Shell existed; data() + save flow reworked for parity in Phase 2. |
| Service | `app/Services/ScholarService.php` | `save()` upsert. **Had 4 behavioral deviations; reworked for v1 parity.** |
| FormRequest | `app/Http/Requests/ScholarRequest.php` | 12 rules. **Rules deviated from v1; reworked to nullable.** |
| Views | `resources/views/scholars/{index,_form,create,edit}.blade.php` | Registry CRUD UI. **Deviated from v1 screen; reworked.** |
| Routes | `routes/web.php` `page:scholars.php` group | index/data/create/store/edit/update. **Had a missing class import (dead routes); fixed.** |
| Tests | `tests/Feature/ScholarTest.php` | 2 tests. **Both FAILED — never ran green; rewritten (8 tests).** |
| Factory | `database/factories/ClientFactory.php` | **Broken** (missing `aff_org`); fixed. |
| Model change | `app/Models/Client.php` | `HasFactory` added (plus `municipality()`/`barangayInfo()` relations). |
| Nav | `resources/views/partials/sidebar.blade.php` | **Had no Scholars entry — registry unreachable; entry added.** |

The five models and the route-group shell are sound. Everything else in the
module was either partial (registry CRUD) or absent (reports, QR, GIP, grantee
updates, update logs, relink).

### 2.2 Test health (as audited, 2026-08-07)

`php artisan test --filter=ScholarTest` failed both tests with
`SQLSTATE[HY000]: General error: 1364 Field 'aff_org' doesn't have a default
value` — `tbl_clients.aff_org` is `NOT NULL` with no default and
`ClientFactory::definition()` omitted it. The P6 code had therefore **never
been exercised end-to-end**; the failure occurred in test setup
(`Client::factory()->create()`), which also masked a dead-route bug (the
scholars routes referenced `ScholarController::class` without the class
import). After the Phase 2 cleanup the suite is green: **8 Scholar tests /
25 assertions**, full suite **97 passed / 516 assertions**.

---

## 3. V1-to-V2 gap analysis

| Feature | v1 | v2 (as audited) | Gap |
|---|---|---|---|
| Scholar registry list | `scholars.php` server-side DataTables, 6 columns | Standalone index, 5 wrong columns + Add button | Columns/order/relink/sidebar |
| Scholar registry feed | `fetch_scholars.php` exam-join, client_id order, count quirk | Plain query, no join, full_name order | Feed parity |
| Scholar write | `save_scholarship.php` upsert, no name writes | Service upsert + derived names | 4 behavioral deviations (§5) |
| Relink | `update_client_id.php` | **Missing** | No route/action |
| GIP | `save_gip.php` + audit | **Model only** | Controller/service missing |
| Grantee self-update | `disabled_update_grantee.php` + `save_grantee_update.php` + `verify_mobile.php` | **Missing** | Entire public flow |
| Grantee search | `search_grantee.php` (munis/q/verify) | **Missing** | Shared endpoint missing |
| Update-log viewer | `update_logs.php` + `fetch_update_logs.php` | **Model only** | Viewer missing |
| Reports | screen + feed + CSV (BOM) | **Missing** | Whole feature |
| QR viewer | `view_qrcode.php` | **Missing** | Whole feature |
| Client picker | (via client modal) | Standalone create page has a single-option select | Needs `transactions.clients-search` pattern |

Roughly **~85% of the module was missing**; the registry CRUD that existed was
defective (see §5). Post-Phase-2, the registry list/feed/write are v1-parity;
the rest (relink, GIP, self-service, update logs, reports, QR) is still to
build.

---

## 4. Confirmed parity requirements

These v1 behaviors are ground truth and must hold byte-for-byte in v2:

1. **Registry screen:** columns ID, Client ID (inline relink), Full Name,
   Program, Barangay, Town; default order `client_id` asc; pageLength 25; no
   Add button (creation from the client page in v1; the v2 standalone page is a
   documented extension).
2. **Registry feed:** search over `full_name`/`program`/`school`; order column
   default index 1 (`client_id`); subquery-paginate then LEFT JOIN `tbl_exam`
   on `TRIM(LOWER(full_name)) = TRIM(LOWER(fullname))` exposing
   `ex.barangay`/`ex.town`; `recordsFiltered == recordsTotal` (preserved v1
   quirk).
3. **Scholar write:** POST-only, trim everything; `is_regular` defaults to `0`
   when absent (`isset ? intval : 0`); `year_started` stored as the
   `"YYYY - YYYY"` varchar built from `year_start`/`year_end` (one-sided
   allowed, `''` if both empty); upsert on the latest `(client_id, program)`
   row; **do not write `full_name`/`normalized_name`/`match_name`**; INSERT
   omits `full_name`.
4. **Validation strictness:** only `client_id` + `program` required; every other
   field optional and stored as `''` when empty (strict mode writes `''`, not
   NULL, to the NOT NULL columns).
5. **Relink (`update_client_id.php`):** simple `client_id` update by row id;
   `"success"` or HTTP 400.
6. **GIP (`save_gip.php`):** `mb_strtoupper` on 15 of 17 fields; upsert latest
   row per client; audit via `AuditService` (`ADD_GIP`/`UPDATE_GIP`, table
   `'tbl_clients'`, record = client_id, full-row old/new JSON); **no
   `full_name`/`normalized_name`/`match_name` writes**.
7. **Grantee self-update:** public; mobile verification (`^09\d{9}$`); server
   preserves name/municipality/barangay; required mobile_no/email/birthdate/
   sex/civil_status; upserts latest scholar_info (INSERT writes comma-form
   `full_name`); writes `tbl_update_logs` with IP + exact action string
   `'Grantee self-updated their information.'`; transactional.
8. **Update-log viewer:** name comma-vs-natural formatting, Town uppercased,
   date/time converted to **Asia/Manila** (`m/d/Y - h:i A`), date-range filter,
   `created_at DESC`.
9. **Reports:** feed = transactions-led with `MAX(id)` scholar LEFT JOIN;
   CSV = scholar_info-led with correlated transaction subqueries; UTF-8 BOM;
   `full_name` includes extension name; v1 column set.
10. **QR:** public search → verify → QR flow; payload must equal the name form
    the P4 `ScanService` / v1 payout scanners match against (see §8, decision
    C).

---

## 5. Implementation deviations (as audited)

The eight concrete deviations found in the existing registry code:

| # | Location | Problem | Phase 2 resolution |
|---|---|---|---|
| 1 | `ScholarService::save` | `is_regular` defaulted to `1`; v1 defaults to `0` when absent. | Fixed — `isset ? (int) : 0`. |
| 2 | `ScholarRequest` + `ScholarService` | `year_started` treated as integer `min:2000/max:2100`; v1 stores `"YYYY - YYYY"` in `varchar(255)`. Existing `"2025 - 2026"` rows would corrupt on edit. | Fixed — `year_start`/`year_end` inputs, v1 build logic. |
| 3 | `ScholarRequest` | `school_type`/`campus`/`college_department`/`course`/`year_level` marked `required`; v1 columns NULLABLE and empty allowed. | Fixed — nullable rules. |
| 4 | `ScholarService::save` | Always wrote `full_name`/`match_name`; v1 writes neither. | Fixed — removed derivation; INSERT omits `full_name` (explicit `''`). |
| 5 | `ScholarController::data` | No `tbl_exam` join (Barangay/Town missing); default order `full_name` vs v1 `client_id`; count semantics differed. | Fixed — v1 feed parity, quirk preserved. |
| 6 | `scholars/index.blade.php` + sidebar | Columns + Add button + no relink differed from v1; registry not on the sidebar. | Columns aligned; sidebar entry added; Add button kept as the documented v2 entry to create. |
| 7 | `tests/Feature/ScholarTest.php` + `ClientFactory` | Both tests failed (`aff_org` missing). Never green. | Fixed — `aff_org` added; tests rewritten (8 tests). |
| 8 | `ScholarRequest` `is_regular` boolean | The service default masked the missing-flag case. | Fixed — nullable|boolean + service `?? 0` path. |

Plus one routing defect not visible at audit time: the `page:scholars.php`
route group referenced `ScholarController::class` without the `use` import
(dead routes). Fixed in Phase 2.

---

## 6. Missing functionality (Phase 3 build list)

1. **Relink** — `update_client_id.php` port (registry inline edit + route).
2. **GIP** — `save_gip.php` port: `GipController` + `GipService` upsert,
   `mb_strtoupper`, `tbl_audit_logs` via `AuditService` (`ADD_GIP`/
   `UPDATE_GIP`, table `'tbl_clients'`, record = client_id), `#collapseGIP`
   redirect semantics (or equivalent).
3. **Grantee self-service** — public `disabled_update_grantee.php` flow:
   `search_grantee.php` (munis/q/verify), `verify_mobile.php`,
   `save_grantee_update.php` (server-preserved names, required fields,
   scholar_info upsert with comma-form `full_name`, `tbl_update_logs` write with
   IP + exact action string, transaction).
4. **Update logs viewer** — `update_logs.php`/`fetch_update_logs.php` port:
   date filter, name-formatting logic, Asia/Manila time conversion.
5. **Scholarship reports** — screen + server-side feed + CSV (BOM), matching the
   v1 columns/filters/subquery semantics.
6. **QR viewer** — public `view_qrcode.php` port (search → verify → QR), payload
   per decision C in §8.
7. **Sidebar navigation** — Scholars module links (registry, reports, update
   logs, QR viewer). (Registry link already added in Phase 2.)
8. **Feature tests** — add tests for every P6 feature (relink, GIP,
   self-service, update logs, reports, QR), run the full suite on
   `main_system_test`.
9. **Client-search for the standalone create form** — v1 creates from the client
   modal; v2's standalone `create` page needs a client picker (reuse the
   `transactions.clients-search` pattern).

---

## 7. Risks and parity concerns

- **Generated column:** never write `tbl_scholar_info.normalized_name` (kept —
  the service does not write it).
- **`year_started` format:** existing rows hold `"2025 - 2026"` strings; the
  original integer-based request/service would have mangled them on edit — the
  highest-priority correctness fix, now applied.
- **`is_regular` default flip:** the original wrote `1` where v1 writes `0`;
  affects the reports' Regular column — fixed.
- **QR payload drift:** v1 `view_qrcode.php` builds `"LASTNAME, FIRSTNAME
  MIDDLENAME"` (no extension name, comma form). The P4 `ScanService` and v1
  `scanner_payout_action.php` both match the scanned text against
  `tbl_clients.full_name` (exact `TRIM`, case-insensitive; seat names equal
  `full_name`), so the payload must be the **persisted comma-form
  `client.full_name`** (decision C, §8).
- **Public endpoints:** `search_grantee`, `save_grantee_update`, `verify_mobile`,
  `view_qrcode`, `disabled_update_grantee` are public in v1 (no session). CSRF
  exemption/throttling must mirror the P5 self-service decision.
- **`tbl_gip_info` FK:** `client_id` has no cascade — GIP save requires an
  existing client.
- **`tbl_exam` join:** registry Barangay/Town depends on `tbl_exam` rows
  matching `full_name` exactly (case/whitespace-insensitive); rows inserted by
  v1's save path have empty `full_name`, so the join yields nothing for them.
- **Two-log distinction:** `tbl_update_logs` (module log, IP) vs `tbl_audit_logs`
  (GIP/audit) must not be merged.
- **Doc-vs-v1 conflicts** that would have misled implementation were resolved
  (see §B): `disabled_update_grantee` semantics, GIP `normalized_name` sync,
  scholar `full_name` derivation.

---

## 8. Decisions requiring approval

Three behavioral decisions were surfaced by the audit; all three are now
**resolved (approved and applied in the Phase 2 cleanup)**:

- **A. Scholar `full_name` / `match_name` on writes.** v1 `save_scholarship.php`
  writes neither. Options were (a) follow the earlier P6 doc and derive from the
  client, or (b) follow v1 exactly. **Approved: v1 exact** — the service does
  not write them; new rows store `full_name = ''` (INSERT omits it in v1;
  explicit empty string keeps the NOT NULL column valid under strict mode),
  `match_name` stays `NULL`. (Note: the grantee self-update flow
  `save_grantee_update.php` is a separate v1 path that **does** write
  comma-form `full_name` — that stays.)
- **B. GIP `normalized_name` / `full_name`.** The earlier P6 doc claimed v1
  "keeps `normalized_name` in sync in PHP" — false; v1 `save_gip.php` writes
  none of `full_name`/`normalized_name`/`match_name`. **Approved: v1 exact** —
  leave them unset; do not sync in PHP.
- **C. QR payload.** v1 `view_qrcode.php` builds `"LASTNAME, FIRSTNAME
  MIDDLENAME"` (uppercase, no extension name) via `api.qrserver.com`. Verified
  that both the P4 `ScanService` and v1 payout scanners match the scanned text
  against `tbl_clients.full_name` (exact `TRIM`, collation-insensitive; seat
  names ≡ `full_name`). **Approved: encode the persisted comma-form
  `client.full_name`** so a real scan resolves through the existing lookups; do
  not invent a format.

(For completeness: `is_regular` default `0` and `year_started` as the
`"YYYY - YYYY"` string were confirmed as exact-v1 parity, not open decisions —
fixed in Phase 2.)

---

## 9. Recommended implementation sequence (Phase 3, incremental — verify each step)

1. **Scholar Registry** — index view + `data()` feed parity (v1 columns,
   `tbl_exam` join for Barangay/Town, default client_id order), relink
   (`update_client_id` port + inline button), sidebar entry.
   *(Steps 1's list/feed + sidebar were completed in Phase 2; relink pending.)*
2. **Scholar CRUD** — `ScholarService::save`/`ScholarRequest`/`_form` parity:
   `is_regular` default 0, `year_start`/`year_end` → `"YYYY - YYYY"`, nullable
   rules, `full_name`/`match_name` decision (§8 A), client picker.
   *(Completed in Phase 2, except the client picker.)*
3. **Reports** — `scholarship_reports` screen + feed + CSV export (BOM),
   reusing the P3/P5 export pattern.
4. **QR viewer** — public `view_qrcode.php` + `search_grantee.php`
   (munis/q/verify) — built here because the search/verify endpoint is shared.
5. **GIP** — `GipController`/`GipService` + `save_gip.php` parity + audit.
6. **Grantee updates** — `verify_mobile.php`, `disabled_update_grantee.php`
   (public form), `save_grantee_update.php`, `update_logs.php` +
   `fetch_update_logs.php`.
7. **Tests** — add feature tests for each of the above; run the full suite on
   `main_system_test`.
8. **Docs** — P6 doc updates, blueprint status, IMPLEMENTATION_LOG, README,
   SESSION_HANDOFF.

---

## 10. Final implementation readiness

**Verdict: REFACTOR + BUILD (not a full rewrite, not a keep).**

- **KEEP:** the five models, the `page:scholars.php` route shell, the P4
  scanner integration, `TransactionService::PROGRAMS`, `PhotoService` whitelist,
  the P3/P5 export + public-route patterns.
- **REFACTOR:** the existing registry CRUD — all 8 deviations (§5) plus the
  dead-route import are **fixed and verified** (Phase 2 cleanup): Scholar
  service/request/controller/views, ClientFactory, tests, sidebar.
- **BUILD (Phase 3):** relink, GIP, grantee self-service + update-log viewer,
  scholarship reports, QR viewer — still ~85% of the module (§6).
- **Correct docs:** done in Phase 2 (P6_SCHOLARS.md, blueprint, README,
  IMPLEMENTATION_LOG, SESSION_HANDOFF).

**Current state:** scholar registry list/feed/write are v1-parity and green in
the test suite (97 passed / 516 assertions). The module is **not yet
production-ready** — the §6 feature set and its tests remain. Follow the §9
sequence, verify each feature on `main_system_test`, then update the six-doc
set.

---

## Appendix A — Code to reuse

| Item | Reuse for |
|---|---|
| 5 models | unchanged |
| `page:` middleware + route-group pattern (P3/P4) | all gated P6 screens |
| P5 public self-service route placement (`routes/web.php` top-level, no CSRF) | `disabled_update_grantee`, `view_qrcode`, `search_grantee`, `verify_mobile`, `save_grantee_update` |
| `response()->streamDownload` + BOM (P3 `TransactionController`, P5 `UnpaidVerificationController`) | `export_scholarship_reports` |
| `GeographyController` (get_barangays port) | municipality/barangay cascades in self-service + reports |
| `AuditService` | GIP audit rows (v1 `log_action` port) |
| `TransactionController::searchClients` pattern | client picker on the standalone scholar create form |
| `TransactionService::PROGRAMS` + six-program whitelist | report feed program filter, `search_grantee` allowed programs |
| P4 scanner engine (`ScanService`, scanner routes) | unchanged; QR payload verified against it (§8 C) |

## Appendix B — Documentation conflicts resolved

| Doc | Location | Conflict → resolution |
|---|---|---|
| `P6_SCHOLARS.md` | §2.3 table | `disabled_update_grantee.php` described as "disable/remove an update entry" → corrected: **public self-update form** (like P5 `disabled_unpaid.php`). |
| `P6_SCHOLARS.md` | §4 GranteeUpdateController | "deletion is the likely v1 behavior" → **no deletion**; it writes a log row. |
| `P6_SCHOLARS.md` | §4 GipController | "keeping `normalized_name` in sync in PHP" → v1 **does not** write `normalized_name`. |
| `P6_SCHOLARS.md` | §4 ScholarService | "derive `full_name` from the client" → v1 writes neither; decision A (§8). |
| `P6_SCHOLARS.md` | header | "Not yet implemented" → registry CRUD scaffolding existed; status refreshed. |
| `ENGINEERING_BLUEPRINT.md` | §2 rows 81–93 / §3 `tbl_update_logs` | "Planned" while scaffolding exists → rows 81–83 marked done; remaining planned. |

## Appendix C — Implementation notes

- **Single-writer service layer:** `ScholarService` (corrected), new
  `GipService`, `GranteeUpdateService`, `ReportService`. No controller bypasses.
- **Route groups per v1 permission key** (`scholars.php`,
  `scholarship_reports.php`, `update_logs.php`); public self-service routes
  top-level like P5.
- **Hardcoded dropdown data:** keep v1's school (~100) / course (~300) lists in a
  `config/scholars.php` (or equivalent) for parity + reuse across the scholar
  form and the self-update form; do not introduce a DB table.
- **QR:** keep `api.qrserver.com` (external) for v1 parity — no new package.
- **Reports:** port the v1 asymmetric query shapes (feed = transactions-led with
  `MAX(id)` scholar join; CSV = scholar_info-led with correlated transaction
  subqueries) for column/value parity.
- **Update-log name formatting + PHT conversion** logic from v1 must be ported
  verbatim (display-level concerns the DataTables feed handles in PHP).
