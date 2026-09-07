# P8 Cutover — Production Database Reconciliation Handoff (READ-ONLY)

> **For:** Production database administrator / system owner.
> **Prepared by:** Developer (2DMIS-v2 project).
> **Date:** 2026-08-29.
> **Status:** READY to hand off. The developer does **not** run any production
> operation; execution of this package on the production database is the
> DBA/administrator's action, under production-owner approval.
> Source baseline: `docs/implementation/P8_DECISION_PACKAGE.md` §C.9.
> Machine-copyable companion: `docs/reconciliation_queries.sql`.

---

## 1. Purpose

Before any cutover action on `main_system`, we must reconcile the **current**
production state against the v2 access model. The outputs of these queries tell
us: who exists and what they can do (Q1–Q2), who the super-admin is (Q3),
whether geography and program data are present (Q4–Q5), whether any client's
municipality is unresolvable (Q6), and the complete inventory of legacy
permission keys (Q7).

**This package contains SELECT/read-only queries only.** Nothing below creates,
drops, alters, truncates, updates, inserts, or deletes. Nothing requires a
temporary table. If any statement blocks or errors, skip it and report the
exact message — do **not** rewrite or "fix" it.

Run each statement against the production database **exactly as written** (one
at a time), in the given order. Copy the output verbatim.

### Optional safety wrapper (recommended)

If your MySQL/MariaDB supports read-only transactions (MySQL 5.7.2+ / MariaDB
10.0+), you may wrap the run so the session cannot write:

```sql
START TRANSACTION READ ONLY;
-- …run the queries below…
ROLLBACK; -- ends the read-only transaction; releases the snapshot
```

This is optional — the statements are already read-only on their own.

---

## 2. Query-by-query package

### Q1 — Full user × permission inventory

```sql
SELECT u.id, u.username, p.page_name
FROM tbl_users u
LEFT JOIN tbl_permissions p ON p.user_id = u.id AND p.can_access = 1
ORDER BY u.id, p.page_name;
```

**What it checks:** every user account and every page-permission each one has.

**Expected / acceptable result:** a complete roster. Each `page_name` value
must be one of: `'*'` (super-admin), one of the consumed application routes
(`clients.php`, `household.php`, `all_transactions.php`, `scholars.php`,
`register.php`, and the administration pages), or a known-but-inert v1
granular key (e.g. `add_client.php`, `edit_view_client.php`, etc.).

**Owner decision if…** any `page_name` value is unrecognised, or the roster
does not match the staff who are known to use the system.

---

### Q2 — Holders per pilot page

```sql
SELECT page_name, COUNT(*) AS holders
FROM tbl_permissions
WHERE can_access = 1 AND page_name IN
  ('clients.php','household.php','all_transactions.php','scholars.php','register.php')
GROUP BY page_name;
```

**What it checks:** how many users currently hold each of the five pilot pages
that will be governed by action/municipality enforcement after cutover.

**Expected / acceptable result:** for every page the municipality actually
uses, `holders` ≥ 1 and the set matches the expected roster.

**Owner decision if…** a page that staff use shows `0` holders (the grant
list for that page would be empty), or holders exist that nobody recognises.

---

### Q3 — Existing super-admin(s) — CRITICAL

```sql
SELECT u.id, u.username FROM tbl_users u
JOIN tbl_permissions p ON p.user_id = u.id
WHERE p.page_name = '*' AND p.can_access = 1;
```

**What it checks:** which account(s) currently hold the `'*'` super-admin key.

**Expected / acceptable result:** exactly the **existing production super-admin
account** (the account the owner intends to keep). Zero rows is also a valid
answer — it tells us v1 may treat super-admin implicitly.

**Owner decision if…**
- `0` rows → there is no `'*'` row today; after cutover the v2 admin screens
  are reachable only by whoever holds `'*'`. The v2 plan provides a guarded
  bootstrap (reviewed SQL, DBA-executed) for one existing account — that step
  is **only** needed in this case and will be supplied separately.
- `>1` row → reconcile which account is the canonical super-admin.

**No account is created or modified by anything in this handoff.**

---

### Q4 — Geography data populated?

```sql
SELECT (SELECT COUNT(*) FROM tbl_municipalities) AS municipalities,
       (SELECT COUNT(*) FROM tbl_barangays)   AS barangays;
```

**What it checks:** that the municipality/barangay reference data exists.

**Expected / acceptable result:** both counts > 0.

**Owner decision if…** `municipalities` = 0 → the location-based scope screen
cannot grant per municipality; only a full-access marker would work, which
changes the day-one grant strategy.

---

### Q5 — Program-permission inventory (already live)

```sql
SELECT COUNT(*) FROM tbl_program_permissions;
```

**What it checks:** how many program-level permission rows exist. Program gating
is already enforced in production behavior parity (an empty table is
unrestricted — v1 parity).

**Expected / acceptable result:** any count is acceptable as long as it is
consistent with the live P3 behavior teams see today.

**Owner decision if…** the count contradicts what staff report, or rows exist
for programs nobody recognises.

---

### Q6 — Clients with unresolvable municipality (CAST-corrected)

```sql
SELECT COUNT(*) AS clients_with_bad_municipality
FROM tbl_clients c
LEFT JOIN tbl_municipalities m ON m.id = CAST(c.city_municipality AS UNSIGNED)
WHERE m.id IS NULL;
```

**What it checks:** how many clients carry a `city_municipality` that cannot be
matched to an existing municipality **using the corrected cast**. The `CAST(…
AS UNSIGNED)` is required: `tbl_clients.city_municipality` is `VARCHAR(100)`
while `tbl_municipalities.id` is `INT` — without the cast, string-to-integer
comparison produces false positives.

**Expected / acceptable result:** `0`.

**Owner decision if…** `>0` → each such client falls out of every
municipality-scoped user's view once enforcement is on for a page. The owner
must choose, per the P8 package §C.9-6 policy: **cleanse first** (record
references corrected before flip) vs **accept** (recognised limitation; only
`'*'`/full-access users see them). **No decision is made in this handoff.**

---

### Q7 — Full legacy key inventory

```sql
SELECT p.page_name, COUNT(*) AS holders
FROM tbl_permissions p
WHERE p.can_access = 1
GROUP BY p.page_name
ORDER BY p.page_name;
```

**What it checks:** every distinct `page_name` currently granted, with holder
counts — the complete ACL surface including v1 granular keys that are stored
but inert in both v1 and v2.

**Expected / acceptable result:** every value falls into one of the buckets in
Q1. No silent, unknown grants.

**Owner decision if…** a `page_name` appears that cannot be classified.

---

## 3. What to send back after running

Return a results block containing, **verbatim**:

1. Q1 — the full listing (paste or file export).
2. Q2 — the count(s).
3. **Q3 — the full account listing (this one is most important).**
4. Q4, Q5, Q6 — the count(s).
5. Q7 — the full listing.
6. Confirmation line: "All queries ran read-only; no data or schema changed"
   plus the execution date/time.
7. Any error messages exactly as printed (do not attempt to fix them).

## 4. Results that require owner approval before cutover proceeds

| Result | Consequence |
|---|---|
| Q3 returns **0** `'*'` holders | Super-admin bootstrap becomes required (DBA-executed, reviewed SQL supplied separately); owner confirms the account |
| Q3 returns **>1** holder | Owner picks the canonical super-admin |
| Q6 returns **> 0** clients | Owner picks cleanse-vs-accept (C.9-6 policy) before any scope grants |
| Q2 shows a used page with 0 holders | Owner confirms who should hold it for the grant list |
| Q4 shows 0 municipalities | Owner decides the scope-grant fallback |
| Q1/Q7 show an unrecognised `page_name` | Owner reviews before grants are applied |

---

*Prepared by the developer for the production administrator/owner. No
production queries in this package have been executed by the developer.*