-- 2DMIS v2 — Production Reconciliation Queries (Read-Only)
-- Source: docs/implementation/P8_DECISION_PACKAGE.md §C.9
-- Authoritative explanation + expected results + owner-decision triggers:
--   docs/DBA_RECONCILIATION_HANDOFF.md (v1: 2026-08-29)
-- Verified against local schema: 2026-08-26
-- DO NOT EXECUTE AGAINST PRODUCTION WITHOUT OWNER APPROVAL
-- All queries are SELECT-only; no writes, no schema changes, no temp tables.

-- ============================================================
-- QUERY 1: Full user × permission inventory
-- Purpose: Establish every user and their granted page permissions
-- Tables verified: tbl_users (id, username), tbl_permissions (user_id, page_name, can_access)
-- Column types match JOIN condition (both INT for user_id)
-- ============================================================
SELECT u.id, u.username, p.page_name
FROM tbl_users u
LEFT JOIN tbl_permissions p ON p.user_id = u.id AND p.can_access = 1
ORDER BY u.id, p.page_name;

-- ============================================================
-- QUERY 2: Holders per pilot page
-- Purpose: Count holders for each of the 5 P12 pilot pages
-- Tables verified: tbl_permissions (page_name, can_access)
-- Pilot page keys match config/authorization.php exactly
-- ============================================================
SELECT page_name, COUNT(*) AS holders
FROM tbl_permissions
WHERE can_access = 1 AND page_name IN
  ('clients.php','household.php','all_transactions.php','scholars.php','register.php')
GROUP BY page_name;

-- ============================================================
-- QUERY 3: Existing super admins
-- Purpose: Identify any existing '*' (super-admin) permission rows
-- EXPECTED: exactly the existing production super-admin (0 rows is valid —
--          means v1 treats super-admin implicitly). Owner decision if 0 or >1.
-- Tables verified: tbl_users (id, username), tbl_permissions (page_name, can_access)
-- SUPER_ADMIN_PAGE = '*' per AccessControlService::SUPER_ADMIN_PAGE
-- ============================================================
SELECT u.id, u.username FROM tbl_users u
JOIN tbl_permissions p ON p.user_id = u.id
WHERE p.page_name = '*' AND p.can_access = 1;

-- ============================================================
-- QUERY 4: Geography populated?
-- Purpose: Verify tbl_municipalities and tbl_barangays have data
-- Required for scope checkboxes (admin.permissions.scopes) and client forms
-- Tables verified: tbl_municipalities (id), tbl_barangays (id)
-- ============================================================
SELECT (SELECT COUNT(*) FROM tbl_municipalities) AS municipalities,
       (SELECT COUNT(*) FROM tbl_barangays)   AS barangays;

-- ============================================================
-- QUERY 5: Program-permission inventory (already live)
-- Purpose: Check current program permission grants (P3 gating already enforced)
-- Tables verified: tbl_program_permissions (user_id, program_name)
-- ============================================================
SELECT COUNT(*) FROM tbl_program_permissions;

-- ============================================================
-- QUERY 6: Data-shape sanity for scope joins (CRITICAL TYPE MISMATCH)
-- Purpose: Find clients whose city_municipality does not resolve to a valid municipality
-- EXPECTED: 0. Owner decision (cleanse vs accept) if > 0.
-- Tables: tbl_clients (city_municipality VARCHAR(100)), tbl_municipalities (id INT)
-- 
-- ⚠️  VERIFIED DISCREPANCY:
--    - tbl_clients.city_municipality is VARCHAR(100) in the database
--    - tbl_municipalities.id is INT(11)
--    - Client model casts city_municipality to integer, but raw SQL compares STRING to INT
--    - This query may produce FALSE POSITIVES if city_municipality contains numeric strings
--    - RECOMMENDED FIX: Cast city_municipality to unsigned integer in the query
-- ============================================================
SELECT COUNT(*) AS clients_with_bad_municipality
FROM tbl_clients c
LEFT JOIN tbl_municipalities m ON m.id = CAST(c.city_municipality AS UNSIGNED)
WHERE m.id IS NULL;

-- ============================================================
-- QUERY 7: FULL legacy key inventory
-- Purpose: Inventory ALL distinct page_name values in tbl_permissions with holder counts
-- Identifies v1 granular keys (add/edit/view_client.php, add/edit/view_transaction.php)
-- that are grantable but inert in both v1 and v2 (ADR-003 record)
-- Tables verified: tbl_permissions (page_name, can_access)
-- ============================================================
SELECT p.page_name, COUNT(*) AS holders
FROM tbl_permissions p
WHERE p.can_access = 1
GROUP BY p.page_name
ORDER BY p.page_name;

-- ============================================================
-- ADDITIONAL VERIFICATION QUERIES (not in §C.9 but relevant)
-- ============================================================

-- Verify P12 additive tables exist and their structure
-- DESCRIBE tbl_action_permissions;
-- DESCRIBE tbl_user_municipalities;

-- Check current action grants (local dev data may not represent production)
-- SELECT user_id, page_name, action FROM tbl_action_permissions ORDER BY page_name, user_id;

-- Check current municipality scope grants
-- SELECT user_id, municipality_id FROM tbl_user_municipalities ORDER BY user_id;

-- Verify migration status (baseline + 6 additive fixes + 2 P12 tables)
-- SELECT * FROM migrations WHERE migration LIKE '%2026_08_15%' ORDER BY batch;