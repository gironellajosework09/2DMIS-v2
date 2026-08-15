# P6 — Scholars Module

> **Status:** **COMPLETE.** Scholar registry v1-parity (Phase 2) + relink
> (Phase 3 step 1) + scholarship reports (Phase 3 step 3) + GIP profiles +
> grantee self-update + QR viewer all done (2026-08-07 → 2026-08-13).
> P6 is the scholars module — scholar enrollment, GIP profiles, grantee
> updates, scholarship reports, and QR viewer (Blueprint §1.7). Much of the
> **scan-time** scholar behavior already exists via the P4 scanner engine
> (CEAP/CEAP_NEW/CEDSSG/CEDSSG_NEW/OTEA/OTCES, `new_scholars`,
> `ongoing_scholars`, `cedssg_update`); P6 is the
> **enrollment/maintenance/reporting** side that the scanner keys presuppose.
> The P6 registry scaffold (models + scholar route shell + defective CRUD) was
> audited (`docs/SCHOLAR_ANALYSIS.md`) and the scholar CRUD reworked for v1 parity
> (2026-08-07); the relink action (`update_client_id.php` port) followed
> (2026-08-12), the scholarship reports screen + feed + CSV export
> (2026-08-12), and GIP, grantee self-update, and QR viewer (2026-08-13).
> Finalized 2026-08-13: full suite green (`132 passed / 668 assertions`),
> `pint` clean, all Blade views compile.
>
> This is a **hybrid** document: §2 is v1 ground truth, §3 lists what v2
> already touches, §4 onward is the build contract and extension points.

---

## 1. Purpose

P6 delivers the scholar lifecycle: register/update scholar profiles
(`tbl_scholar_info`), record GIP enrollment data (`tbl_gip_info`), let staff
and scholars update grantee details with an audit trail (`tbl_update_logs`),
produce scholarship reports with CSV export, and display a scholar's QR code
for use by the payout scanners.

---

## 2. Legacy v1 behavior (ground truth)

Blueprint §1.7 lists the major files:

| File | Role |
|---|---|
| `scholars.php` + `fetch_scholars.php` | scholar list + DataTables feed |
| `save_scholarship.php` | add/update scholar record (`tbl_scholar_info`) |
| `update_client_id.php` | relink a scholar row to a client |
| `scholarship_reports.php` + `fetch_scholarship_reports.php` + `export_scholarship_reports.php` | reports + feed + CSV (BOM) |
| `save_gip.php` | GIP profile (`tbl_gip_info`) |
| `save_grantee_update.php` | record a grantee detail update |
| `disabled_update_grantee.php` | **public** self-update form for grantees (no session check, like P5 `disabled_unpaid.php`); posts to `save_grantee_update.php` |
| `update_logs.php` + `fetch_update_logs.php` | update-history viewer |
| `view_qrcode.php` | render the scholar's QR code |

### 2.1 `tbl_scholar_info` (from the schema)

```
id, client_id (FK→tbl_clients, ON DELETE CASCADE),
full_name, program enum('CEDSSG','CEAP','CEDSSG_NEW','CEAP_NEW','OTEA','OTCES'),
school, school_type, campus, college_department, course, year_level,
is_regular tinyint default 1, year_started, landbank_no,
created_at, updated_at,
normalized_name (GENERATED lcase(trim(full_name)) STORED), match_name
```

Key facts:

- `normalized_name` is a **generated stored column** — do not write to it.
- `match_name` is a writable denormalized search key (like `tbl_clients.match_name`).
- The `program` enum matches the six P4 scholar scanner programs exactly — the
  scanner keys write transactions for these programs, and P6 must keep the same
  strings.

### 2.2 `tbl_gip_info` (from the schema)

```
id, client_id (FK, no cascade), full_name,
valid_govt_id, id_number, insurance_beneficiary, emergency_contact,
ecp_contact_number, ecp_address, college, course, year_graduated (YEAR),
high_school, elementary_school, latest_work_experience, position,
period_of_engagement, special_skills, achievements,
created_at, updated_at, normalized_name, match_name
```

- `normalized_name` here is a **plain varchar** (v1 populated it in PHP), not a
  generated column — the P3 `gip` CSV export already joins this table.

### 2.3 `tbl_update_logs` (from the schema)

```
id, client_id, full_name, ip_address, action, created_at (datetime)
```

- Append-only audit of grantee detail updates (`save_grantee_update.php` writes
  here; `update_logs.php`/`fetch_update_logs.php` display it).
- Distinct from `tbl_audit_logs` — this is a **module-specific** change log with
  an IP address, kept separate for the scholars screen.

### 2.4 `tbl_exam` / `tbl_results`

- `tbl_exam`: `exam_no, fullname, barangay, town, email_address, contact,
  school, course, year, scholarship, exam_date, exam_time, permit_confirmed,
  score`, plus a **generated** `normalized_name` (STORED `lcase(trim(fullname))`).
- `tbl_results`: `exam_no, score, approved` — `approved` drives the
  `new_scholars` scanner's automatic program.

---

## 3. What already exists in v2 (P4 touchpoints)

- The **P4 scanner engine** reads/writes the transaction side of scholars:
  - `ceap`, `ceap_new`, `cedssg`, `cedssg_new`, `otea`, `otces` — fixed
    scholarship inserts (`type=SCHOLARSHIP`, `status=PENDING PAYOUT`).
  - `new_scholars` — exam→results→program derivation.
  - `ongoing_scholars` — program from the client's latest transaction.
  - `cedssg_update` — pays the 2nd-sem installment (in-place UPDATE).
- `TransactionService::PROGRAMS` and the `tbl_transactions.program` enum include
  the six scholar programs plus `GIP`.
- The P3 `custom2`/`gip` CSV exports already **read** `tbl_scholar_info` and
  `tbl_gip_info`.
- `PhotoService` / `StudentController` operate on scholar-program clients for
  the self-service photo flow (SCHOLAR_PROGRAMS whitelist).

P6 adds the management screens; it must not duplicate any of the above.

---

## 4. Extension points (the P6 build contract)

Port the v1 files into (Blueprint §2 rows):

- `ScholarController` + `ScholarService` — `scholars.php`, `save_scholarship.php`,
  `fetch_scholars.php`, `update_client_id.php`.
  - **Implemented (2026-08-07, v1-parity cleanup).** `ScholarService::save(...)`
    upserts the latest `(client_id, program)` row; it does **not** write
    `full_name` / `match_name` / `normalized_name` (v1 `save_scholarship.php`
    writes none of them), `is_regular` defaults to `0` when absent
    (`isset ? intval : 0`), and `year_started` is built as the `"YYYY - YYYY"`
    varchar from `year_start`/`year_end` (one-sided allowed, `''` if both
    empty). No `UpdateLog` is written on save.
  - Feed (`fetch_scholars.php` parity): default order `client_id` asc,
    pageLength 25, search over `full_name`/`program`/`school`, subquery-paginate
    then `LEFT JOIN tbl_exam` on `TRIM(LOWER(si.full_name)) =
    TRIM(LOWER(ex.fullname))` (via the generated `normalized_name` columns),
    exposing `ex.barangay`/`ex.town`; `recordsTotal == recordsFiltered` (v1
    quirk, preserved).
  - List columns = v1: ID, Client ID, Full Name, Program, Barangay, Town.
  - `update_client_id.php` → a relink action: verify the target client exists
    and is not already the scholar's client. **Implemented (2026-08-12)** —
    `ScholarController::updateClientId` (`POST scholars/update-client-id`,
    `page:scholars.php` gate) + inline Edit button in the registry Client ID
    column; returns `"success"` or HTTP 400 `"Invalid input"`; the scholar row
    and the target client must both exist.
  - **Implemented (2026-08-13) — client picker for the standalone create form.**
    `GET scholars/clients-search` in the `page:scholars.php` group reuses
    `TransactionController@searchClients` (same DRY picker the transactions
    module uses; scholars-gated so a scholar-only clerk does not need
    `all_transactions.php`). `scholars/_form.blade.php` replaces the empty
    `client_id` `<select>` with a search input + hidden `client_id` + live
    results list (same JS pattern as the transactions picker). Prefill: edit
    from the scholar's client (fixes the previously empty edit select), create
    from `?client_id=`. `ScholarController@create`/`@store`/`@edit`/`@update`
    unchanged — `ScholarRequest` still enforces `client_id` required + exists.
- `GipController` — `save_gip.php` upsert of `tbl_gip_info`. v1
  `save_gip.php` does **not** write `normalized_name`/`full_name` — leave them
  unset; do not sync them in PHP.
  - **Implemented (2026-08-13).** v1 GIP is **not a standalone page** — the
    form is an accordion + modal inside `view_client.php#collapseGIP`, shown
    only for clients with a `tbl_transactions.program = 'GIP'` row, saving via
    `save_gip.php` (upsert of the latest `tbl_gip_info` row per client).
    `GipService::save()` mirrors that: trim + `mb_strtoupper` (all fields
    **except** `ecp_contact_number` and `year_graduated`), upsert by
    `ORDER BY id DESC LIMIT 1`, and `ADD_GIP`/`UPDATE_GIP` audit rows to
    `tbl_audit_logs` (`target_table='tbl_clients'`, `target_id=client_id`,
    old/new JSON) — the update is logged only when something changed. Route:
    `POST clients/{client}/gip` (`GipController@store`) gated by
    `page:clients.php`, the same key that guards the client profile. View:
    `clients/_gip.blade.php` (accordion + modal, all 17 v1 fields) included
    from `clients/_details.blade.php`; `college`/`course` are free-text inputs
    matching the accepted scholars form convention.
- `GranteeUpdateController` — `save_grantee_update.php`,
  `disabled_update_grantee.php`, `update_logs.php`, `fetch_update_logs.php`.
  - `disabled_update_grantee.php` is the **public self-update form** grantees
    use at the payout venue (no session check, P5 `disabled_unpaid.php`
    precedent) — it does **not** disable or remove an update entry.
  - `save_grantee_update.php` **writes a log row** to `tbl_update_logs`
    (append-only; client_id, full_name, **ip_address**, action) — there is no
    deletion anywhere in this flow. Capture the request IP via `$request->ip()`.
  - **Implemented (2026-08-13).** `GranteeUpdateController` + new
    `GranteeUpdateService` (v1-exact transaction: `tbl_clients` update with the
    name parts / municipality / barangay preserved from the DB row; latest
    `tbl_scholar_info` upsert per `(client_id, program)` — UPDATE sets
    `updated_at = NOW()`, INSERT writes comma-form `full_name` +
    `created_at = NOW()`; append-only `tbl_update_logs` row with the
    space-joined `FIRST MIDDLE LAST` name, `$request->ip()`, action exactly
    `'Grantee self-updated their information.'`). Public top-level routes
    `GET grantee-update` (`selfService`,
    `grantee_update/self-service.blade.php`) and `POST grantee-update/save`
    (`store`) — no session check / CSRF, matching v1. Public aliases
    `grantee/verify-mobile` + `grantee/barangays` reuse `ClientController@verifyMobile`
    / `GeographyController@barangays` (v1's `verify_mobile.php` /
    `get_barangays.php` are public too). Required fields
    (`mobile_no/email/birthdate/sex/civil_status`) and all v1 error messages
    preserved. `update_logs.php` port: gated `GET update-logs`
    (`page:update_logs.php`), server-rendered rows + client-side DataTables,
    `DATE(created_at)` filter, v1 name formatting, UTC → Asia/Manila
    `m/d/Y - h:i A`; sidebar Update Logs link. **`fetch_update_logs.php` NOT
    ported** — dead in v1 (never referenced). Strict-mode parities: the scholar
    INSERT supplies `year_started`/`landbank_no = ''` (NOT NULL columns v1 lets
    its non-strict MySQL coerce), and `pwd`/`ip` are coerced to `'NO'` when the
    posted value isn't a valid `enum('YES','NO')` member.
- `ReportController` + `ReportService` — `scholarship_reports.php`,
  `fetch_scholarship_reports.php`, `export_scholarship_reports.php`:
  streamed CSV with UTF-8 BOM, reusing the P3 export pattern.
  **Implemented (2026-08-12)** — `ReportController::scholarship` /
  `scholarshipData` / `scholarshipExport` (`GET scholarship-reports`,
  `POST scholarship-reports/data`, `GET scholarship-reports/export`, all
  behind `page:scholarship_reports.php`). v1's asymmetric query shapes kept:
  the feed is transactions-led with the `MAX(id)` scholar_info join
  (`recordsTotal` = raw six-program count) and ignores the `submitted` filter
  (v1 never reads it); the export is scholar_info-led with correlated
  latest-transaction subqueries and `EXISTS`/`NOT EXISTS` program/date/submitted
  filters, streamed with the UTF-8 BOM as `scholarship_reports<Ymd>.csv`.
- `QrController` — `view_qrcode.php`: render a QR for the scholar (the P4
  payout scanners scan a QR whose payload is the **patient name** string, so the
  QR must encode the name in the exact `TRIM(full_name)`/patient-name form the
  lookups expect — verify against `ScanService` lookups before choosing the
  payload).
  - **Implemented (2026-08-13).** Public top-level route `GET qr-viewer`
    (`QrController@show`, `resources/views/qr/viewer.blade.php`) — v1
    `view_qrcode.php` has no session check, so it is public like the P5
    self-service pages (SCHOLAR_ANALYSIS §9 step 4 / Appendix A placement; no
    sidebar link — v1 does not link it either). The page reuses the shared
    `grantee-search` endpoints (`GranteeSearchController` = v1
    `search_grantee.php`, `kind=grantee`) for municipalities, autocomplete and
    verify — v1's QR page consumed `search_grantee.php` the same way. Verify now
    returns `client.full_name` (added to `CLIENT_COLUMNS`, additive); the QR
    encodes that persisted comma-form name (decision C), generated by v1's
    external `api.qrserver.com` (no package).

Route gating: use the v1 page keys from `tbl_permissions` (`scholars.php`,
`scholarship_reports.php`, `update_logs.php`, `view_qrcode.php`, etc.) via the
`page:` middleware, following the P3/P4 route-group patterns.

---

## 5. Business rules to honor

1. Scholar `program` values are exactly the six-enum strings; they must agree
   with the transaction program strings (scanner + transaction module).
2. `normalized_name` on `tbl_scholar_info` is generated — read-only.
3. v1's scholar write path stores neither `full_name` (INSERT omits it) nor
   `match_name` — do not derive or write them from the client in
   `ScholarService`; leave them unset (v1-parity decision, SCHOLAR_ANALYSIS §8).
4. GIP is one profile per client; saving re-upserts the same row. v1 does not
   populate GIP `normalized_name`/`full_name` in PHP.
5. Scholar **enrollment saves are not logged** (v1 `save_scholarship.php` writes
   nothing to `tbl_update_logs`). The grantee self-update flow
   (`save_grantee_update.php`) is what appends to `tbl_update_logs` with the
   client's IP — a module log, not `tbl_audit_logs` (keep the distinction).
6. Exam/results are read-only references for the scanner; P6 does not edit them.
7. QR codes encode the name form used by the scanners — never a bare id unless
   the scanner lookup is changed (an ADR would be required). Verified: both the
   P4 `ScanService` and v1 `scanner_payout_action.php` match the scanned text
   against `tbl_clients.full_name` (exact `TRIM`, case-insensitive; seat names
   equal `full_name`), so the QR payload must be the persisted comma-form
   `client.full_name`.

---

## 6. Security & validation expectations

- All P6 screens behind their v1 `page:` gates; no new permission rows needed
  (carried-over data already holds them).
- `save_scholarship`/`save_gip` validate: `client_id` exists; `program` in the
  enum. v1 marks only `client_id` + `program` as required in the form; every
  other field is optional and stored as `''` when empty — validation must be
  **nullable**, not `required`, for `school`, `school_type`, `campus`,
  `college_department`, `course`, `year_level`, `landbank_no`, `is_regular`,
  and the `year_start`/`year_end` pair (v1 builds `year_started` from them).
  GIP fields nullable strings, `year_graduated` 4-digit year. Use
  `FormRequest` classes in the P2/P3 style.
- Report feeds/CSV gated the same as their screens; all strings escaped;
  exports streamed with BOM.

---

## 7. Common mistakes to avoid

1. Writing `normalized_name` on `tbl_scholar_info` (generated column → error).
2. Introducing scholar program strings that drift from the scanner/transaction
   enum (they must be identical).
3. Deriving/writing `full_name` or `match_name` from the client in
   `ScholarService::save` — v1 writes neither (INSERT omits `full_name`).
4. Routing grantee-update writes to `tbl_audit_logs` instead of
   `tbl_update_logs` (the screens expect the module log with IP).
5. Encoding the wrong payload in the QR (see §5.7) — must be the persisted
   comma-form `client.full_name`; test a real scan through the `payout` lookup
   before releasing.
6. Duplicating the CSV export machinery — reuse the P3 streamed-download +
   BOM pattern.

---

## 8. Never-change list

- Never drop/rewrite the generated `normalized_name` columns.
- Never let P6 mutate `tbl_exam` / `tbl_results`.
- Never bypass `ScholarService`/`GipController`/`GranteeUpdateController` for
  writes — they are the single writers for their tables.
- Never relax the six-program enum agreement.

---

## 9. Verification / acceptance gates

- Scholar CRUD + relink round-trips; scholar feed matches `tbl_scholar_info`.
- GIP profile save/update idempotent per client.
- Grantee update recorded in `tbl_update_logs` with IP; update-log viewer
  renders it.
- Scholarship reports + CSV (BOM) match v1 columns.
- QR renders and scans through the existing `payout`/`ongoing_scholars` lookups.
- Full suite green on `main_system_test`.

---

## 10. Blueprint / ADR references

- `docs/ENGINEERING_BLUEPRINT.md` §1.7 (Scholars module), §2 rows for
  `scholars.php`/`save_scholarship.php`/`fetch_scholars.php`/`update_client_id.php`,
  `scholarship_reports.php`+feeds, `save_gip.php`, `save_grantee_update.php`/
  `disabled_update_grantee.php`/`update_logs.php`, `view_qrcode.php`; §3 rows for
  `tbl_scholar_info`, `tbl_gip_info`, `tbl_update_logs`, `tbl_exam`,
  `tbl_results`.
- `docs/ARCHITECTURE_DECISION.md` — ADR-004 (the scanner engine P6 depends on).
- `docs/IMPLEMENTATION_LOG.md` — append the P6 entry when delivered.
- `docs/REQUIREMENTS_ANALYSIS.md` — scholar-related FRs.
