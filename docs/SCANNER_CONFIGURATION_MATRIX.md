# Program Configuration Matrix — Scanner Engine (P4)

Status: **Analysis only.** Companion to `SCANNER_ANALYSIS.md`. This matrix
covers **all 17 programs** of the `tbl_transactions.program` enum and records,
per program, the exact behavior the P4 scanner config must reproduce. Programs
that v1 scanners do not reach are marked accordingly (they are served by the P3
transaction module / later phases, not the scanner engine).

Legend — columns:

- **Scanner path(s):** which v1 scanner(s) can produce/affect this program.
- **Transaction type:** `tbl_transactions.type` written by the scanner.
- **Duplicate policy:** rule family + exact key (see `SCANNER_ANALYSIS.md` §7).
- **Attendance table:** payout/unpaid scan table, or `—` if the scanner creates transactions.
- **Remarks value(s):** exact `remarks` string(s) per semester/context.
- **Validation strategy:** client-side (page) and server-side (action) checks.
- **Update strategy:** in-place update or none.
- **Special handling:** quirks that must be preserved byte-for-byte.
- **Required permissions:** v1 `page_name` ACL row(s) + program-permission note.
- **Audit events:** `tbl_audit_logs.action` values (or none).

---

## 1. CEAP

| Attribute | Value |
|-----------|-------|
| Scanner path(s) | `scanner_ceap` (1st sem), `scanner_ongoing_scholars` (2nd sem), `scanner_generic` (manual), `scanner_payout`, `scanner_payout_unpaid` (attendance) |
| Transaction type | `SCHOLARSHIP` |
| Duplicate policy | remark key — `client_id + program + remarks` (1st sem & generic none); ongoing adds `+ patient_name`. No DB constraint. |
| Attendance table | `tbl_payout_scans2` (payout), `tbl_payout_scans_unpaid` (unpaid) |
| Remarks value(s) | 1st sem: `1ST SEM SY2025-2026 DOCS SUBMITTED`; 2nd sem: `2ND SEM SY 2025-2026 DOCS SUBMITTED` |
| Validation strategy | 1st sem page: bare scan→save (no inputs). Action: client exists, `id>0`. |
| Update strategy | none |
| Special handling | 1st-sem scanner does **not** set `payout_date`. Suggested amount 5000 (both sems). |
| Required permissions | `scanner_ceap.php`, `scanner_ongoing_scholars.php`, `scanner_payout.php`, `scanner_payout_unpaid.php`, `scanner_generic.php` (v2 gate recommendation) |
| Audit events | `SCAN-CEAP` (1st sem). **None** for ongoing / payout / unpaid / generic. |

## 2. CEAP_NEW

| Attribute | Value |
|-----------|-------|
| Scanner path(s) | `scanner_ceap_new` (1st sem), `scanner_new_scholars` (exam-derived 1st sem), `scanner_ongoing_scholars` (2nd sem), `scanner_payout`, `scanner_payout_unpaid` |
| Transaction type | `SCHOLARSHIP` |
| Duplicate policy | remark key — `client_id + program + remarks`; ongoing adds `+ patient_name`. No DB constraint. |
| Attendance table | `tbl_payout_scans2`, `tbl_payout_scans_unpaid` |
| Remarks value(s) | 1st sem: `1ST SEM SY2025-2026 DOCS SUBMITTED`; 2nd sem: `2ND SEM SY 2025-2026 DOCS SUBMITTED` |
| Validation strategy | Action: client exists, `id>0`. `scanner_ceap_new` page has no inputs. |
| Update strategy | none |
| Special handling | 1st-sem scanner **sets `payout_date='2025-08-18'`** (distinct from CEAP). Suggested amount 5000. |
| Required permissions | `scanner_ceap_new.php`, `scanner_new_scholars.php`, `scanner_ongoing_scholars.php`, `scanner_payout.php`, `scanner_payout_unpaid.php` |
| Audit events | `SCAN-CEAP_NEW` (fixed scanner), `SCAN-CEAP_NEW` (new_scholars dynamic `SCAN-<PROGRAM>`). None for ongoing/payout/unpaid. |

## 3. CEDSSG

| Attribute | Value |
|-----------|-------|
| Scanner path(s) | `scanner_cedssg` (1st sem), `scanner_cedssg_update` (2nd-sem **payment**), `scanner_ongoing_scholars` (2nd sem), `scanner_generic`, `scanner_payout` |
| Transaction type | `SCHOLARSHIP` |
| Duplicate policy | remark key — `client_id + program + remarks` (1st sem); ongoing adds `+ patient_name`. No DB constraint. |
| Attendance table | `tbl_payout_scans2` |
| Remarks value(s) | 1st sem: `1ST SEM SY2025-2026 DOCS SUBMITTED`; 2nd sem: `2ND SEM SY 2025-2026 DOCS SUBMITTED` |
| Validation strategy | 1st sem page: bare scan→save. `cedssg_update` page: `date_paid` required client-side. |
| Update strategy | `cedssg_update`: `UPDATE … SET status='PAID', date_paid=:input, amount_paid=12500 WHERE id=:id` — idempotent, no dup check. |
| Special handling | 1st-sem scanner does **not** set `payout_date`. Suggested amount 1st sem 10000; 2nd sem 11600; update-in-place **amount_paid 12500** (three distinct numbers, all preserved). Update lookup: `program IN ('CEDSSG','CEDSSG_NEW') AND remarks LIKE '%2ND SEM%' AND status='PENDING PAYOUT'`. |
| Required permissions | `scanner_cedssg.php`, `scanner_cedssg_update.php`, `scanner_ongoing_scholars.php`, `scanner_generic.php`, `scanner_payout.php` |
| Audit events | `SCAN-CEDSSG` (1st sem), `UPDATE-CEDSSG-PAYMENT` (update). None for ongoing/payout/generic. |

## 4. CEDSSG_NEW

| Attribute | Value |
|-----------|-------|
| Scanner path(s) | `scanner_cedssg_new` (1st sem), `scanner_new_scholars` (exam-derived 1st sem), `scanner_cedssg_update` (2nd-sem **payment**), `scanner_ongoing_scholars` (2nd sem), `scanner_payout` |
| Transaction type | `SCHOLARSHIP` |
| Duplicate policy | remark key — `client_id + program + remarks`; ongoing adds `+ patient_name`. No DB constraint. |
| Attendance table | `tbl_payout_scans2` |
| Remarks value(s) | 1st sem: `1ST SEM SY2025-2026 DOCS SUBMITTED`; 2nd sem: `2ND SEM SY 2025-2026 DOCS SUBMITTED` |
| Validation strategy | Action: client exists, `id>0`. Update page: `date_paid` required. |
| Update strategy | same as CEDSSG via `cedssg_update` (program list includes `CEDSSG_NEW`). |
| Special handling | 1st-sem scanner **sets `payout_date='2025-08-18'`** (distinct from CEDSSG). Suggested 1st sem 10000; 2nd sem 11600; update amount_paid 12500. |
| Required permissions | `scanner_cedssg_new.php`, `scanner_new_scholars.php`, `scanner_cedssg_update.php`, `scanner_ongoing_scholars.php`, `scanner_payout.php` |
| Audit events | `SCAN-CEDSSG_NEW` (fixed + new_scholars `SCAN-<PROGRAM>`), `UPDATE-CEDSSG-PAYMENT` (update). |

## 5. OTEA

| Attribute | Value |
|-----------|-------|
| Scanner path(s) | `scanner_otea` (SY docs), `scanner_new_scholars` (exam-derived), `scanner_payout`, `scanner_payout_unpaid` |
| Transaction type | `SCHOLARSHIP` |
| Duplicate policy | remark key — `client_id + program + remarks`. No DB constraint. |
| Attendance table | `tbl_payout_scans2`, `tbl_payout_scans_unpaid` |
| Remarks value(s) | `SCHOOL YEAR 2025-2026` |
| Validation strategy | Action: client exists, `id>0`. Page: bare scan→save. |
| Update strategy | none |
| Special handling | **Sets `payout_date='2025-08-18'`.** Suggested amount 5000. |
| Required permissions | `scanner_otea.php`, `scanner_new_scholars.php`, `scanner_payout.php`, `scanner_payout_unpaid.php` |
| Audit events | `SCAN-OTEA` (fixed + new_scholars). None for payout/unpaid. |

## 6. OTCES

| Attribute | Value |
|-----------|-------|
| Scanner path(s) | `scanner_otces` (SY docs), `scanner_new_scholars` (exam-derived), `scanner_payout`, `scanner_payout_unpaid` |
| Transaction type | `SCHOLARSHIP` |
| Duplicate policy | remark key — `client_id + program + remarks`. No DB constraint. |
| Attendance table | `tbl_payout_scans2`, `tbl_payout_scans_unpaid` |
| Remarks value(s) | `SCHOOL YEAR 2025-2026` |
| Validation strategy | Action: client exists, `id>0`. Page: bare scan→save. |
| Update strategy | none |
| Special handling | **Sets `payout_date='2025-08-25'`** (only program with this date). Suggested amount 3000. |
| Required permissions | `scanner_otces.php`, `scanner_new_scholars.php`, `scanner_payout.php`, `scanner_payout_unpaid.php` |
| Audit events | `SCAN-OTCES` (fixed + new_scholars). None for payout/unpaid. |

## 7. TODA

| Attribute | Value |
|-----------|-------|
| Scanner path(s) | `scanner_toda` |
| Transaction type | `CASH RELIEF ASSISTANCE` |
| Duplicate policy | date guard — `client_id + program + date_applied`. No DB constraint. |
| Attendance table | — |
| Remarks value(s) | `''` (empty string) |
| Validation strategy | Page: `date_applied`, `date_paid`, `amount_paid` all required client-side. Action: client exists; server falls back `date_applied`→today, `date_paid`→null. |
| Update strategy | none |
| Special handling | Inserted directly as **`status='PAID'`** with `suggested_amount=0` and user-entered `amount_paid`. Lookup also resolves municipality + barangay display names. Duplicate returns `alreadySaved:true`. |
| Required permissions | `scanner_toda.php` |
| Audit events | `SCAN-TODA` |

## 8. TUPAD

| Attribute | Value |
|-----------|-------|
| Scanner path(s) | `scanner_tupad`, `scanner_generic` (manual, free-form) |
| Transaction type | `CASH FOR WORK` (scanner); generic = form choice |
| Duplicate policy | date guard — `client_id + program + date_applied`. No DB constraint. |
| Attendance table | — |
| Remarks value(s) | `TUPAD LGBTQIA+` (scanner). **Audit payload remark diverges:** `TUPAD REGISTRATION 2025` — preserve as-is. |
| Validation strategy | Page: optional dates persisted in `localStorage`. Action: client exists; `date_applied` empty→today, `date_paid` empty→null. |
| Update strategy | none |
| Special handling | Inserted directly as **`status='PAID'`**, `suggested_amount=0`, **hard-coded `amount_paid=4680.00`**. Duplicate returns `alreadySaved:true` **plus the existing row** for display. |
| Required permissions | `scanner_tupad.php`, `scanner_generic.php` (v2 gate recommendation) |
| Audit events | `SCAN-TUPAD` |

## 9. AICS

| Attribute | Value |
|-----------|-------|
| Scanner path(s) | `scanner_generic` only |
| Transaction type | form choice (CRA/OCA/CASH FOR WORK/MEDICAL/BURIAL/FOOD SUBSIDY/SCHOLARSHIP) |
| Duplicate policy | none |
| Attendance table | — |
| Remarks value(s) | form input |
| Validation strategy | `client_id>0`; `patient_name` must resolve (self/custom/existing). |
| Update strategy | none |
| Special handling | Program selectable in the generic form only. |
| Required permissions | `scanner_generic.php` (v2 gate recommendation) |
| Audit events | none |

## 10. AKAP

Same as AICS (`scanner_generic` only, no dup, no audit). Type from form.

## 11. MAIP

Same as AICS (`scanner_generic` only, no dup, no audit). Type from form.

## 12. COFFEE GROWERS

| Attribute | Value |
|-----------|-------|
| Scanner path(s) | **none** — no v1 scanner reaches it |
| Transaction type | n/a (P3 transaction module / P6) |
| Duplicate policy | n/a |
| Attendance table | — |
| Remarks value(s) | n/a |
| Validation strategy | n/a |
| Update strategy | n/a |
| Special handling | Not a scanner program; excluded from the P4 config set. |
| Required permissions | n/a (no scanner page) |
| Audit events | n/a |

## 13. PUSO TI KABABAIHAN

Same as COFFEE GROWERS — no scanner; not in P4 config set.

## 14. PUSO TI AGTUTUBO

Same as COFFEE GROWERS — no scanner; not in P4 config set.

## 15. PUSO TI MANNALON

Same as COFFEE GROWERS — no scanner; not in P4 config set.

## 16. TESDA

Same as COFFEE GROWERS — no scanner; not in P4 config set.

## 17. GIP

| Attribute | Value |
|-----------|-------|
| Scanner path(s) | **none** (P3 has GIP transaction support; P6 handles GIP info) |
| Transaction type | n/a for scanner |
| Duplicate policy | n/a |
| Attendance table | — |
| Remarks value(s) | n/a |
| Validation strategy | n/a |
| Update strategy | n/a |
| Special handling | Not a scanner program. |
| Required permissions | n/a (no scanner page) |
| Audit events | n/a |

---

## Cross-program scanner-mode summary

| Mode (proposed config `mode`) | Programs | v1 scanners |
|-------------------------------|----------|-------------|
| `scholarship_transaction` | CEAP, CEAP_NEW, CEDSSG, CEDSSG_NEW, OTEA, OTCES | 1st-sem / SY docs fixed scanners |
| `exam_derived` | CEAP_NEW, OTEA, OTCES, CEDSSG_NEW | new_scholars |
| `validate_existing` | CEAP, CEDSSG, CEAP_NEW, CEDSSG_NEW (2nd sem) | ongoing_scholars |
| `update_in_place` | CEDSSG, CEDSSG_NEW (2nd-sem payment) | cedssg_update |
| `date_guarded_transaction` | TODA, TUPAD | toda, tupad |
| `seat_attendance` | CEAP, CEDSSG, CEAP_NEW, CEDSSG_NEW, OTEA, OTCES (attendance only) | payout |
| `unpaid_attendance` | CEAP, CEAP_NEW, OTEA, OTCES (attendance only) | payout_unpaid |
| `generic_form` | AICS, AKAP, MAIP, TUPAD, CEDSSG, CEAP (manual) | generic |
| **not in scanner set** | COFFEE GROWERS, PUSO TI KABABAIHAN, PUSO TI AGTUTUBO, PUSO TI MANNALON, TESDA, GIP | — |

## Payout/unpaid program allow-lists (exact, preserved)

- `scanner_payout` (seat_attendance): `CEAP, CEDSSG, CEAP_NEW, CEDSSG_NEW, OTEA, OTCES`
- `scanner_payout_unpaid` (unpaid_attendance): `CEAP, CEAP_NEW, OTEA, OTCES`
- `scanner_ongoing_scholars` (validate_existing): `CEAP, CEDSSG, CEAP_NEW, CEDSSG_NEW`
- `scanner_cedssg_update` (update_in_place): `CEDSSG, CEDSSG_NEW`
- `scanner_new_scholars` (exam_derived config map): `CEAP_NEW, OTEA, OTCES, CEDSSG_NEW`

## Semester-bound constants to keep in config (not code)

| Constant | Value(s) |
|----------|----------|
| 1st-sem remarks | `1ST SEM SY2025-2026 DOCS SUBMITTED` |
| SY remarks | `SCHOOL YEAR 2025-2026` |
| 2nd-sem remarks | `2ND SEM SY 2025-2026 DOCS SUBMITTED` |
| Suggested amounts | CEAP 5000, CEAP_NEW 5000, CEDSSG 10000, CEDSSG_NEW 10000, OTEA 5000, OTCES 3000, 2nd-sem CEDSSG* 11600 |
| Payout dates | CEAP_NEW/OTEA/CEDSSG_NEW `2025-08-18`, OTCES `2025-08-25`; CEAP/CEDSSG none |
| Paid amounts | TUPAD `4680.00`; CEDSSG update `12500`; TODA user-entered |
