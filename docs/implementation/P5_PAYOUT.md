# P5 — Payout Attendance Screens and Unpaid Verification (Planned)

> **Status:** **Not yet implemented** — this is the current milestone. The
> P4 scanner engine already provides the **write side** of payout attendance
> (the `payout` and `payout_unpaid` scanner keys append to
> `tbl_payout_scans2` / `tbl_payout_scans_unpaid`). P5 delivers the **read /
> verification side**: the three payout-attendance list screens, the unpaid
> grantee verification workflow, their DataTables feeds, and CSV exports.
>
> This document is a **hybrid**: §2 documents the v1 legacy behavior (ground
> truth for parity), §3 describes what already exists in v2, and §4 onward are
> the exact extension points for the P5 build. It is a forward engineering
> contract, not a change record.

---

## 1. Purpose

After a payout event, staff need to see **who actually showed up** (the
scanned attendance) and **who did not get paid** and why. v1 covered this with
three "scanned payouts" list screens and an "unpaid verification" workflow that
records grantees who could not be paid, including an optional proxy receiver.

P5 ports those screens onto the attendance data the P4 engine already writes.

---

## 2. Legacy v1 behavior (ground truth)

### 2.1 Payout attendance list screens

Three near-identical screens (Blueprint §1.9):

| File | Backing table | Meaning |
|---|---|---|
| `scanned_payouts.php` | `tbl_payout_scans` | legacy single-attendance list |
| `scanned_payouts2.php` | `tbl_payout_scans2` | current CEAP/CEDSSG/OTEA/OTCES payout attendance (written by the `payout` scanner) |
| `scanned_payouts_unpaid.php` | `tbl_payout_scans_unpaid` | unpaid-payout attendance (written by the `payout_unpaid` scanner) |

Each has a matching DataTables feed (`fetch_scanned_payouts*.php`). The screens
are **one-scan-per-transaction** lists: a row per transaction with the scan
timestamp, the scanner (staff `tbl_users`), the scanned text, and the joined
transaction/client context. The `UNIQUE(transaction_id)` constraint is the
anti-duplicate belt — the list views never see duplicate transactions.

### 2.2 Unpaid verification (Blueprint §1.10)

| File | Role |
|---|---|
| `unpaid_verifications.php` | main screen: table of unpaid grantees + "record verification" entry |
| `disabled_unpaid.php` | disables/removes an unpaid record |
| `unpaid_save.php` | saves a verification row to `tbl_unpaid_verifications` |
| `fetch_unpaid_verifications.php` | DataTables feed |
| `export_unpaid_verifications.php` | CSV export (UTF-8 BOM) |
| `search_grantee.php` | client search for picking the grantee |
| `search_unpaid_grantee.php` | search restricted to unpaid grantees |

Semantics (from the schema + Blueprint):

- A verification row captures **why/whether the grantee could not be paid** and,
  when `is_proxy = 1`, the full identity of the proxy receiver:
  `proxy_lastname/firstname/middlename/relationship/phone/birthdate/gender/
  occupation/monthlyincome`.
- `client_id` + `municipality_id` are FKs (both constrained) — every unpaid
  record belongs to a client and a municipality.
- `disabled_unpaid.php` effectively removes a verification (v1 used a
  disable/delete semantics; exact wording must be confirmed against the file).

### 2.3 Unpaid verification — table facts (from `database/schema/mysql-schema.sql`)

`tbl_unpaid_verifications`:

```
id, client_id (FK→tbl_clients, NO CASCADE), municipality_id (FK→tbl_municipalities),
is_proxy tinyint default 0,
proxy_lastname, proxy_firstname, proxy_middlename, proxy_relationship,
proxy_phone, proxy_birthdate, proxy_gender, proxy_occupation, proxy_monthlyincome,
created_at
```

No update/delete FKs on the proxies; the proxy block is a denormalized identity
snapshot — a deliberate v1 design (the receiver's identity at the time of
recording) and should be kept that way.

---

## 3. What already exists in v2 (P4 delivered the write side)

- **`config/scanner.php` keys `payout` and `payout_unpaid`** — attendance
  modes that `INSERT` into `tbl_payout_scans2` / `tbl_payout_scans_unpaid`
  with `(transaction_id, scanned_text, scanned_by)`, guarded by the DB unique
  constraint + app pre-check, and **no audit** (v1 parity).
- **`ScanService::lookupSeatAttendance` / `lookupTransactionPartial` /
  `lookupSeatIgnoreScan`** — the seat join (exact then partial name match on
  `tbl_seats2`) and the partial patient match used at scan time.
- **`tbl_payout_scans2` / `tbl_payout_scans_unpaid`** tables are ready
  (indexes, FKs, UNIQUE on `transaction_id`).
- **`tbl_unpaid_verifications`** exists untouched (no v2 writer yet).

So P5 is purely additive on the read side: no schema change required, and the
write path must not be duplicated.

---

## 4. Extension points (the P5 build contract)

### 4.1 Payout attendance list screens

Port `scanned_payouts.php` / `scanned_payouts2.php` / `scanned_payouts_unpaid.php`
+ `fetch_scanned_payouts*.php` into:

- `PayoutAttendanceController` — `index($variant)` for the three variants and a
  `data(Request, $variant)` DataTables feed. Blueprint's planned class name
  (`PayoutAttendanceController` + views + DataTables routes, P5 row).
- **One shared view** for the three variants (they differ only in the backing
  table and title) — mirror the P4 lesson: config/argument drives the view, not
  three copies.
- Route pattern to follow the P3/P4 conventions:

```
GET  payout-attendance/{variant}        name=payout-attendance.index   (page:scanned_payouts2.php)
POST payout-attendance/{variant}/data  name=payout-attendance.data
```

  Gate each with the matching v1 page key (`scanned_payouts.php`,
  `scanned_payouts2.php`, `scanned_payouts_unpaid.php`) via `page:` middleware,
  exactly like the scanner route loop.

Feed columns (derive from the join):
`transaction_id`, scanned_at, scanned_by (user), scanned_text, program,
patient_name/client name, municipality/barangay, amount/suggested/status,
payout_date/date_paid. Escape all strings; format money with `number_format(…,2)`;
use `m/d/Y`-style dates consistent with the P3 feed.

### 4.2 Unpaid verification

Port `unpaid_verifications.php` / `unpaid_save.php` / `disabled_unpaid.php` /
`fetch_unpaid_verifications.php` / `export_unpaid_verifications.php` /
`search_grantee.php` / `search_unpaid_grantee.php` into:

- `UnpaidVerificationController` + `UnpaidService` (Blueprint P5 row).
- `UnpaidService::create(...)` — transactional insert + audit; the row records
  the grantee (client + municipality) and, when the staff member ticks proxy,
  the proxy identity block. Follow the v1 contract that the proxy fields are a
  snapshot, and that `client_id`/`municipality_id` must reference existing rows.
- `UnpaidService::disable(...)` — port the exact `disabled_unpaid.php`
  semantics (confirm from the v1 file whether it deletes the row or flips a
  flag; the schema has no `disabled` column, so v1 almost certainly **deletes**
  the row — verify before coding).
- Feed + CSV export (UTF-8 BOM) reusing the P3 streamed-download pattern.
- Two searches: full grantee search (`search_grantee.php`) and
  unpaid-only search (`search_unpaid_grantee.php` — restrict to clients who
  have an unpaid-verification or unpaid payout-scan, per v1).

### 4.3 Data rules to honor

- The three payout lists never show a transaction twice per payout event
  (unique constraint is the contract).
- Unpaid verification is **not** an attendance write — it records the grantee
  who could not be paid and optionally a proxy receiver; it must not touch
  `tbl_payout_scans*` or `tbl_transactions`.
- All writes audited (`AuditService`) with actions following the existing
  `*_PAYOUT`/`UNPAID_*` naming; confirm the exact v1 `action` strings from the
  source before inventing new ones.

---

## 5. DB tables involved (final)

| Table | Existing rows/constraints to reuse |
|---|---|
| `tbl_payout_scans` | legacy (variant 1) — verify its rows are still meaningful in production before wiring the first screen |
| `tbl_payout_scans2` | `UNIQUE(transaction_id)`, FK `scanned_by` → `tbl_users`, indexes on `scanned_by`/`scanned_at` |
| `tbl_payout_scans_unpaid` | same shape as above |
| `tbl_unpaid_verifications` | FKs to `tbl_clients` + `tbl_municipalities`; proxy snapshot columns |
| `tbl_transactions` / `tbl_clients` / `tbl_municipalities` / `tbl_barangays` | join context for the feeds |

---

## 6. Security & validation expectations

- Both list routes and all feeds gated by the v1 page keys (`page:scanned_payouts*.php`,
  `page:unpaid_verifications.php`). No new permissions rows are needed — the
  page rows already exist in the carried-over `tbl_permissions` data.
- `unpaid_save` validates: `client_id` exists, `municipality_id` exists,
  `is_proxy` boolean, proxy fields nullable strings/dates. Mirror the 
  P2/P3 request style with a `FormRequest`.
- All queries parameter-bound; feeds select from a fixed column map for
  ordering; exports reuse `applyExportFilters`-style gating.

---

## 7. Common mistakes to avoid (learned from P2/P3/P4)

1. Writing a third payout-scanner implementation instead of reading
   `tbl_payout_scans2` — the write side already exists (P4).
2. Copying the three list screens as three views — one view driven by a
   variant/config argument.
3. Breaking the one-scan-per-transaction contract by adding a dedup workaround
   instead of relying on the DB `UNIQUE`.
4. Inventing new audit action strings instead of porting v1's (check the v1
   files first).
5. Treating `tbl_unpaid_verifications` as a general proxy table — it records
   unpaid-grantee verifications only.

---

## 8. Never-change list

- Never relax `UNIQUE(transaction_id)` on the payout-scan tables.
- Never write `tbl_unpaid_verifications` from the scanner engine or the
  transaction module — it has one writer (the unpaid-verification service).
- Never add a `disabled`-style flag column via non-additive means; any schema
  change goes through the additive-migration path + baseline regen.

---

## 9. Verification / acceptance gates

- Port all three payout lists + feeds; counts match the scan tables exactly.
- Unpaid verification create/disable/search/export round-trips.
- CSV exports carry the UTF-8 BOM and match v1 column sets.
- Full PHPUnit suite stays green on `main_system_test` (do not touch the local
  `main_system` copy — AGENTS.md rule).

---

## 10. Blueprint / ADR references

- `docs/ENGINEERING_BLUEPRINT.md` §1.9 (Payout attendance), §1.10 (Unpaid
  verification), §2 rows for `scanned_payouts*.php`, `unpaid_verifications.php`
  and friends; §3 table rows for the payout-scan tables and
  `tbl_unpaid_verifications`.
- `docs/REQUIREMENTS_ANALYSIS.md` FR-6.4 / FR-6.5 / FR-6.6.
- `docs/IMPLEMENTATION_LOG.md` — append the P5 entry when delivered.
- `docs/SCANNER_ANALYSIS.md` §4.13/§4.14 — the scan-time behavior that produces
  the data these screens display.
