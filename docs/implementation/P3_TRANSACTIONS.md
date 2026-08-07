# P3 — Transaction Registry (17 Programs, CRUD, Feeds, CSV Exports)

> **Status:** Delivered, tested, and documented.
> **Scope of this document:** the transaction module — program/type/status
> catalogs, the add/edit/show/delete + inline-edit flows, the server-side
> DataTables feed, client/beneficiary search, program-level gating, and the
> four CSV export variants. This is the maintainer reference for how
> assistance transactions are recorded and how program permissions shape them.

---

## 1. Purpose

P3 ports v1's transaction subsystem — `all_transactions.php` (~1,400 lines),
`add_transaction.php`, `edit_transaction.php`, `view_transaction.php`,
`delete_transaction.php`, `update_transaction.php`, `all_transaction_edit.php`,
`all_transaction_delete.php`, `fetch_transactions.php`, and
`transaction_table.php` — into a single `TransactionService` plus one
`TransactionController`. Transactions are the records that scanners (P4),
payout screens (P5), and scholars (P6) all read and write, so the catalogs and
the audit contract here are shared vocabulary.

---

## 2. Legacy v1 behavior (what we ported)

- **`all_transactions.php`** was the central list with client filters, program
  filters, inline row editing (`update_transaction.php`/`all_transaction_edit.php`),
  row deletion, and CSV export. It was program-gated: restricted users only saw
  their own `tbl_program_permissions` programs.
- **Beneficiary resolution** used a `patient_option` radio group in the add
  form: **self** (stores the client's `"LASTNAME, FIRSTNAME MIDDLE"` string),
  **custom** (free-text typed name, uppercased), or **existing** (picked from a
  client search).
- **TUPAD quirk:** the v1 add/update paths stored `NULL` for `comments`,
  `payout_date`, `gwa`, and `units` regardless of input.
- **CSV exports** wrote a UTF-8 **BOM** (`\xEF\xBB\xBF`) and had several
  column variants (standard, custom, custom2 with scholar-info join, and the
  GIP report joining `tbl_scholar_info` + `tbl_gip_info`).
- Every transaction write went through `log_action()` into `tbl_audit_logs`
  (`ADD_TRANSACTION`, `EDIT_TRANSACTION`, `DELETE_TRANSACTION`).
- Dates were formatted `m/d/Y` in the feed; `created_at` was shown in
  Asia/Manila local time.

### Deviations v2 deliberately makes (all behavioral, audited)

| v1 | v2 |
|---|---|
| Inline edit returns HTML/redirect | `inlineUpdate` returns JSON `{success}` (the jQuery port branches on it) |
| Delete via form post + redirect | JSON `{success}` / 422 (DataTables row action) |
| Program gating scattered in the page | `AccessControlService->permittedPrograms()` + `authorizeProgram()` + feed-side `whereIn` — one decision point |
| `program`/`type` free strings | Validated against `TransactionService::PROGRAMS`/`TYPES`/`STATUSES` catalogs (unchanged values) |
| Search ranking inline | Ranked `CASE` ordering shared across feeds |

The **column values themselves are identical** to v1 (the `program` enum is a
fixed string list on `tbl_transactions`; types/statuses are free-form but v2
constrains them to the same catalog v1 used).

---

## 3. Laravel architecture

```
page:all_transactions.php group → TransactionController
                                    TransactionService  (CRUD + audit + patient resolution)
                                    AccessControlService (program gating)
                                    Transaction model    (tbl_transactions)
```

- The whole group sits behind `page:all_transactions.php` (the v1 page key).
- **Program gating is additive:** super-admins and users with no program rows
  see all 17 programs; a user with `tbl_program_permissions` rows sees only the
  intersection of those rows with the catalog (`programsForUser`) and can only
  store/update a program they are permitted to (`authorizeProgram`).
- The scanner generic form (P4) reuses `TransactionService::TYPES` and
  `::STATUSES` from the view config — keep the catalogs as constants.

---

## 4. Service: `TransactionService` (`app/Services/TransactionService.php`)

Constants (the shared vocabulary):

```php
PROGRAMS = ['AICS','AKAP','MAIP','TUPAD','CEDSSG','CEAP','CEAP_NEW','CEDSSG_NEW',
            'OTEA','OTCES','COFFEE GROWERS','PUSO TI KABABAIHAN','PUSO TI AGTUTUBO',
            'PUSO TI MANNALON','TESDA','GIP','TODA'];                       // 17
TYPES    = ['CRA','OCA','CASH FOR WORK','MEDICAL','BURIAL','FOOD SUBSIDY',
            'SCHOLARSHIP','MEMBERSHIP','SKILLS TRAINING','INTERNSHIP PROGRAM',
            'CASH RELIEF ASSISTANCE','ATTENDANCE'];                          // 12
STATUSES = ['PENDING PAYOUT','PAID'];
```

Methods (each write wrapped in `DB::transaction` + audited):

- `create(array $data, User $actor)` — inserts and audits `ADD_TRANSACTION`
  with a JSON `new_value` of all 14 data fields.
- `update(int $id, array $data, User $actor)` — `findOrFail`, snapshots the
  old row (13 fields), fills + saves, audits `EDIT_TRANSACTION` with
  `old_value` + `new_value`.
- `destroy(int $id, User $actor)` — `findOrFail`, snapshots (incl. `client_id`),
  deletes, audits `DELETE_TRANSACTION` (old only).
- `resolvePatientName(string $option, Client $client, ?array $input)` — the
  v1 `patient_option` semantics:
  - `self` → `trim(lastname.', '.firstname.' '.middlename)` (note: middlename
    blank-safe, and **not** the `full_name` shape — it keeps v1's transaction
    convention);
  - `custom` → uppercased trimmed free text, `null` when empty;
  - `existing` → the selected client's `"LAST, FIRST MIDDLE"` via
    `existingClientName()`.

---

## 5. Controller: `TransactionController`

(`app/Http/Controllers/TransactionController.php`)

- `index()` — passes municipalities + `programsForUser()` to the view.
- `create(Client)` — add form pre-scoped to a client.
- `store(Request)` — validates (see §8), loads the client, `authorizeProgram`,
  resolves the patient name, applies the **TUPAD nulling**, then
  `transactions->create()`. Redirects to the show page with a flash.
- `show(int $id)` / `edit(int $id)` — with client eager load / program list.
- `update(Request, int $id)` — validates the editable subset, re-authorizes the
  new program, re-resolves the patient name (fallback to `patient_name_custom`
  when the client is missing), applies TUPAD nulling, `transactions->update()`.
- `destroy(Request, int $id)` — JSON `{success}` / 422.
- `inlineUpdate(Request)` — the v1 `update_transaction.php` port. Normalizes
  comma-separated amounts (strip `,`, numeric→float, empty→null), parses
  `date_paid` with `strtotime`→`Y-m-d`, updates the editable row fields, JSON
  `{success}` / 422.
- `searchClients(Request)` — v1 `search_clients.php`; needs ≥2 chars, AND-word
  search over name columns, ranked, limit 15. Used by the beneficiary "existing"
  picker (and reused by the P4 generic scanner view).
- `data(Request)` — the v1 `fetch_transactions.php` feed. **Program access is
  enforced in the feed itself:** a restricted user's query is constrained to
  their `permittedPrograms` (`whereIn`), and requesting a forbidden program
  filter yields `{recordsTotal: 0, recordsFiltered: 0, data: []}`. Supports
  program/status/municipality/barangay filters, `date_applied` and `date_paid`
  ranges, word search across client + transaction columns, ordered by a
  21-column map, `m/d/Y` dates and `number_format(…, 2)` amounts, `created_at`
  in Asia/Manila, and an actions cell (Edit / Save / Cancel / Delete buttons for
  inline editing). All strings are `e()`-escaped.
- `export(Request)` — `response()->streamDownload` with a UTF-8 **BOM** prefix
  and four modes selected by `?export_mode=`:

| Mode | Filename | Columns |
|---|---|---|
| (default) standard | `transactions_YYYYMMDD.csv` | date_applied, program, client name, patient, mobile, geo, type, remarks, comments, amounts, dates, GWA, units, created_at |
| `custom` | `transactions_custom_YYYYMMDD.csv` | date/program/name-parts/birthdate/sex/civil/geo/province/suggested/contact/status/remarks/comments/full name |
| `custom2` | `transactions_custom2_YYYYMMDD.csv` | custom + IP/IP-group/email + `tbl_scholar_info` school/course/year_level |
| `gip` | `gip_report_YYYYMMDD.csv` | custom + scholar info + full `tbl_gip_info` profile block |

  `applyExportFilters()` enforces the **same program gating as the feed**.
- Private helpers `programsForUser()` / `authorizeProgram()` — the single
  program-gating decision points.

---

## 6. Model: `Transaction` (`app/Models/Transaction.php`)

- `$table = 'tbl_transactions'`, `$timestamps = false`.
- `$fillable`: `client_id, program, patient_name, date_applied, type, remarks,
  comments, suggested_amount, status, amount_paid, payout_date, date_paid, gwa,
  units`.
- Casts: `client_id` int, `suggested_amount` decimal:2, `amount_paid` decimal:2.
- `client()` belongsTo — the only relation.

---

## 7. Routes (P3 portion)

```
page:all_transactions.php group:
  GET  transactions                        index
  GET  transactions/create/{client}        transactions.create
  POST transactions                       store
  GET  transactions/export                export
  POST transactions/data                  data
  POST transactions/inline-update         inline-update
  GET  transactions/clients-search        searchClients
  GET  transactions/{transaction}/edit    transactions.edit
  PUT  transactions/{transaction}         transactions.update
  GET  transactions/{transaction}         transactions.show
  POST transactions/{transaction}         transactions.destroy
```

---

## 8. Validation

`store()`:

- `client_id` required int `exists:tbl_clients,id`
- `program` required string (then authorized via `authorizeProgram`)
- `patient_option` required `in:self,custom,existing`
- `date_applied` required date; `type` required string
- `remarks`/`comments` nullable string
- `suggested_amount`/`amount_paid`/`gwa`/`units` nullable numeric
- `status` required `in:PENDING PAYOUT,PAID`
- `payout_date`/`date_paid` nullable date

`update()` — the same minus `client_id`; `gwa`/`units` not editable on update.
`inlineUpdate()` — no `validate()` call (amounts/dates normalized manually to
mirror the v1 handler).

Normalization applied before persist: `type`/`remarks`/`comments` uppercased;
blank remarks/comments → `null`; empty numerics → `null`; **TUPAD** nulls
`comments`, `payout_date`, `gwa`, `units`.

---

## 9. Business rules

1. Only 17 programs / 12 types / 2 statuses exist; values must match the
   legacy `program` enum strings exactly (they are quoted in the schema dump).
2. Patient name on `self`/`existing` is `"LAST, FIRST MIDDLE"` — distinct from
   `tbl_clients.full_name` (`"LAST, FIRST MIDDLE EXTENSION"`). Preserved from
   v1; do not "fix" without an ADR.
3. TUPAD always stores nulls for comments/payout_date/gwa/units.
4. Program permissions filter **every** read (feed + export) and gate every
   write (store + update). A restricted user cannot even filter the list to a
   forbidden program.
5. All mutations are transactional and audited; `old_value`/`new_value` are
   JSON snapshots of the affected fields.
6. Inline edits may change status/amount/dates but not the client or program
   (no client_id field in the inline payload).
7. Amounts accept comma separators and convert to float; blank → null.
8. `date_paid` parses through `strtotime` for the inline path (mirrors v1
   tolerant parsing).
9. CSV exports are streamed, always start with the UTF-8 BOM, and share the
   program gating of the feed (no export-only backdoor).

---

## 10. Security notes

- Program gating is enforced server-side on both read and write paths — a user
  cannot reach a forbidden program by manipulating filters or form fields.
- Feed/export HTML strings are escaped; amounts are formatted, not concatenated
  raw.
- CSRF: all POST/PUT routes are behind the framework middleware; inline edit
  and delete use the `X-CSRF-TOKEN` header convention (the feeds' jQuery posts
  it).
- No client-controlled SQL: every WHERE/LIKE uses bound parameters; the order
  column is selected from a fixed map.
- Export honors the same ACL as the screen (program-scoped), so it is not a
  data-leak channel.

---

## 11. Performance notes

- The feed is a single joined query with filters and an ordered `LIMIT/OFFSET`;
  counts are separate cheap queries; no full-table fetch in PHP.
- `m/d/Y` and `number_format` formatting happens only on the page slice, not on
  the whole dataset.
- Streamed CSV writes directly to `php://output` — no memory blowup on big
  exports.
- Existing `tbl_transactions` indexes (`program`, `client_id`, `date_applied`,
  `payout_date`, `date_paid`) back the filters.

---

## 12. Never-change list

- Never add/remove/reword a value in `PROGRAMS`, `TYPES`, or `STATUSES` without
  checking the schema `enum` and the P4 scanner config first — the scanner
  engine and the DB column are coupled to these strings.
- Never bypass `TransactionService` for a transaction write; all writes must
  audit.
- Never relax the TUPAD nulling rule or the patient-name `self` shape.
- Never drop the feed/export program gating (removing it is a privilege
  escalation).
- Never touch `tbl_transactions` with `migrate:fresh`-style operations; any
  schema change is additive and reviewed (AGENTS.md rule).

---

## 13. Common mistakes (observed or likely)

1. **Storing `full_name` into `patient_name`** — the `self` resolution is
   `"LAST, FIRST MIDDLE"`, without extension; the P4 generic scanner and the
   P5 unpaid lookup rely on the patient_name convention.
2. **Skipping `authorizeProgram` on update** — a user could re-scope a
   transaction to a program they cannot see.
3. **Letting a blank amount become `0`** — v2 nulls empties; `0` changes CSV
   and feed output.
4. **Forgetting the TUPAD branch** — comments/payout_date/gwa/units must be
   null.
5. **Adding a 15th audit field ad hoc** — keep `TransactionService` audit
   payloads as the full-field JSON so P7's audit viewer renders them
   consistently.
6. **Reusing `programsForUser` logic inline in a view/controller** — it lives
   in `TransactionController`; the scanner generic form reads the catalogs from
   `config/scanner.php`/`TransactionService`, not from copies.

---

## 14. Future improvements

- P5 payout screens will mark transactions paid/attended against the same
  catalog programs.
- P6 scholar screens join `tbl_scholar_info`/`tbl_gip_info` (already referenced
  by the `custom2`/`gip` exports).
- The feed could gain saved filters or server-side export-of-current-view.
- Consider an ADR before introducing soft-delete of transactions (payout scan
  FKs point at them).

---

## 15. Blueprint / ADR references

- `docs/ENGINEERING_BLUEPRINT.md` §1.6 (Transactions module), §2 rows for
  `all_transactions.php` and friends, §3 `tbl_transactions` row.
- `docs/ARCHITECTURE_DECISION.md` — ADR-003 (program gating through the ACL
  service), ADR-009 (audit contract).
- `docs/IMPLEMENTATION_LOG.md` — the dated P3 entry with the 12-test
  TransactionTest results.
- `docs/REQUIREMENTS_ANALYSIS.md` FRs on transaction recording and exports.
