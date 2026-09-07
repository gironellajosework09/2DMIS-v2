# Phase 2 — KPI Business Definitions

**Date:** 2026-08-27  
**Status:** Derived from v1 codebase inspection  
**Authority:** v1 source of truth for functionality; this document records what v1 actually does

---

## 1. Total Clients

| Attribute | Value |
|---|---|
| **Definition** | Count of all rows in `tbl_clients` |
| **Source table** | `tbl_clients` |
| **Source field** | `id` (COUNT) |
| **Status conditions** | None — every row is a live client (no soft-delete, no `is_active` flag) |
| **Municipality scope** | v1: none enforced. v2: `AccessControlService::applyMunicipalityScope()` on `c.city_municipality` |
| **Comes from v1?** | **Partial.** v1 has `SELECT COUNT(*) FROM tbl_clients` in `fetch_clients.php:114` for DataTables pagination only. v1 does NOT display this count on any dashboard. The dashboard KPI is a **new presentation** of an existing data concept. |
| **Ambiguity** | None. The count is unambiguous. The only decision is whether to scope it by municipality (recommended: yes, consistent with v2 ACL model). |
| **v2 query** | `DB::table('tbl_clients as c')->where('city_municipality', $scopeIds)->count()` with `applyMunicipalityScope()` |

---

## 2. Total Transactions

| Attribute | Value |
|---|---|
| **Definition** | Count of all rows in `tbl_transactions` |
| **Source table** | `tbl_transactions` |
| **Source field** | `id` (COUNT) |
| **Status conditions** | None — all transactions counted regardless of status |
| **Municipality scope** | v1: none enforced. v2: scope via `c.city_municipality` join |
| **Program scope** | v1: program permissions enforced in `fetch_transactions.php:19-53`. v2: `permittedPrograms()` enforced in `TransactionController::data()` |
| **Comes from v1?** | **Partial.** v1 has `SELECT COUNT(*) FROM tbl_transactions` in `fetch_transactions.php:198` for DataTables pagination only. Not displayed on any dashboard. |
| **Ambiguity** | None. |
| **v2 query** | `DB::table('tbl_transactions as t')->leftJoin('tbl_clients as c', ...)->where(...program permissions...)->count()` with scope |

---

## 3. Disbursed Amount

| Attribute | Value |
|---|---|
| **Definition** | Sum of `amount_paid` for transactions with `status = 'PAID'` |
| **Source table** | `tbl_transactions` |
| **Source fields** | `amount_paid` (SUM), `status` (WHERE = 'PAID') |
| **Status conditions** | `status = 'PAID'` only |
| **Municipality scope** | Via client join |
| **Program scope** | Via program permissions |
| **Comes from v1?** | **No.** v1 has NO `SUM(amount_paid)` query anywhere. The `amount_paid` field exists in `tbl_transactions` and is populated by TUPAD/TODA scanners (which insert with `status = 'PAID'` and `amount_paid` set) and by manual edits. The concept of "disbursed amount" is a **new presentation interpretation**. |
| **Ambiguity** | **Moderate.** Two possible interpretations: |
| | Option A: `SUM(amount_paid) WHERE status = 'PAID'` — represents actual money disbursed |
| | Option B: `SUM(suggested_amount) WHERE status = 'PAID'` — represents approved amounts |
| | **Recommendation:** Option A. `amount_paid` is the actual disbursement. `suggested_amount` is the requested/approved amount (which may differ). TUPAD inserts with `suggested_amount = 0` and `amount_paid = 4680`, so Option B would undercount TUPAD. |
| **v2 query** | `DB::table('tbl_transactions as t')->leftJoin(...)->where('t.status', 'PAID')->sum('t.amount_paid')` with scope |

---

## 4. Pending Approvals

| Attribute | Value |
|---|---|
| **Definition** | Count of transactions with `status = 'PENDING PAYOUT'` |
| **Source table** | `tbl_transactions` |
| **Source field** | `status` (WHERE = 'PENDING PAYOUT') |
| **Status values in v1** | Exactly two: `'PENDING PAYOUT'` and `'PAID'` (confirmed from `add_transaction.php:258-261`, `all_transactions.php:789-790`, `edit_transaction.php:269-270`) |
| **Status values in v2** | `TransactionService::STATUSES = ['PENDING PAYOUT', 'PAID']` (line 26) |
| **Municipality scope** | Via client join |
| **Program scope** | Via program permissions |
| **Comes from v1?** | **No.** v1 has no count of pending transactions on any dashboard. The status value `'PENDING PAYOUT'` is from v1, but the aggregation is new. |
| **Ambiguity** | **Low.** The only status besides `'PAID'` is `'PENDING PAYOUT'`. There is no `'APPROVED'`, `'REJECTED'`, or other status. "Pending" unambiguously means `'PENDING PAYOUT'`. |
| **v2 query** | `DB::table('tbl_transactions as t')->leftJoin(...)->where('t.status', 'PENDING PAYOUT')->count()` with scope |

---

## 5. Program Distribution

| Attribute | Value |
|---|---|
| **Definition** | Count of transactions grouped by `program` |
| **Source table** | `tbl_transactions` |
| **Source fields** | `program` (GROUP BY), `id` (COUNT) |
| **Status conditions** | None — all programs counted |
| **Municipality scope** | Via client join |
| **Program scope** | Via program permissions |
| **Programs in v1** | 17 values: AICS, AKAP, MAIP, TUPAD, CEDSSG, CEAP, CEAP_NEW, CEDSSG_NEW, OTEA, OTCES, COFFEE GROWERS, PUSO TI KABABAIHAN, PUSO TI AGTUTUBO, PUSO TI MANNALON, TESDA, GIP, TODA |
| **Comes from v1?** | **No.** v1 has NO `GROUP BY program` query anywhere. Program is used only for filtering. The distribution chart is a **net-new presentation**. |
| **Ambiguity** | None. The program column is well-defined. |
| **v2 query** | `DB::table('tbl_transactions as t')->leftJoin(...)->select('t.program', DB::raw('COUNT(*) as count'))->groupBy('t.program')->orderByDesc('count')` with scope |

---

## 6. Activity Feed

| Attribute | Value |
|---|---|
| **Definition** | Most recent audit log entries |
| **Source table** | `tbl_audit_logs` |
| **Source fields** | `user_id`, `action`, `target_table`, `target_id`, `created_at` |
| **Comes from v1?** | **Partial.** v1 has `tbl_audit_logs` with `log_action()` function. Audit logs are displayed on `audit_logs.php` (separate admin page). Not on dashboard. The dashboard feed is a **new presentation** of existing audit data. |
| **Ambiguity** | **Minor.** The audit log `target_table` values include: `tbl_clients`, `tbl_transactions`, `tbl_cedssg`, `tbl_users`, `tbl_permissions`, `tbl_program_permissions`, `tbl_action_permissions`, `tbl_user_municipalities`. The feed should show human-readable action descriptions. |
| **v2 query** | Reuse existing audit infrastructure: `DB::table('tbl_audit_logs')->join('tbl_users', ...)->orderByDesc('created_at')->limit(5)` |

---

## 7. Announcements

| Attribute | Value |
|---|---|
| **Definition** | N/A — no data source exists |
| **Source table** | None |
| **Comes from v1?** | **No.** No announcements system exists in v1 or v2. No database table. No PHP files. No UI elements. |
| **Ambiguity** | **N/A.** This is explicitly deferred per user directive. |
| **Decision** | **Deferred.** Static/hardcoded content only if implemented. |

---

## 8. Calendar

| Attribute | Value |
|---|---|
| **Definition** | N/A — no data source exists |
| **Source table** | None |
| **Comes from v1?** | **No.** No calendar or event system exists in v1 or v2. |
| **Ambiguity** | **N/A.** This is explicitly deferred per user directive. |
| **Decision** | **Deferred.** |

---

## Summary

| KPI | v1 Has It? | v1 Source | Nature |
|---|---|---|---|
| Total Clients | Count query (pagination only) | `fetch_clients.php:114` | New presentation of existing concept |
| Total Transactions | Count query (pagination only) | `fetch_transactions.php:198` | New presentation of existing concept |
| Disbursed Amount | `amount_paid` field exists; NO aggregation | Table column only | New interpretation (Option A recommended) |
| Pending Approvals | Status `'PENDING PAYOUT'` exists; NO count | Status values only | New presentation of existing concept |
| Program Distribution | No GROUP BY anywhere | N/A | Net-new presentation |
| Activity Feed | Audit logs exist; not on dashboard | `tbl_audit_logs` | New presentation of existing data |
| Announcements | Nothing exists | N/A | Deferred |
| Calendar | Nothing exists | N/A | Deferred |

---

*End of KPI Business Definitions*
