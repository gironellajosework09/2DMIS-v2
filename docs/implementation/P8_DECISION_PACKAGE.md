# P8 Decision Package — Hardening Scope + P12 S2 Cutover Readiness

**Prepared:** 2026-08-24 (decision-preparation pass only)
**Status:** AWAITING OWNER APPROVAL — HARD STOP honored
**Constraints honored:** enforcement flags remain OFF; no grants changed; no
production SQL executed; no production data touched; no application code
changed in this pass.

---

## 0. Evidence base for this package

| Source | Result |
|---|---|
| Full test suite re-run 2026-08-24 | **195 tests / 887 assertions green** on `main_system_test` |
| Local `main_system` inspection (read-only) | 2 users (`jordi` id 3 with `*`; `jiro` id 4 with nothing); `tbl_action_permissions` = 0 rows; `tbl_user_municipalities` = 0 rows; **`tbl_municipalities` = 0 rows; `tbl_barangays` = 0; ALL domain tables (clients/households/transactions/scholars) = 0 rows** |
| Code review | All five pilot controllers fully seamed (page + action + program + scope); `AccessControlService` truth table verified against config |
| Grep sweep | No hardcoded usernames / `user_id = 1` / inline username checks anywhere in `app/` |

**Consequence of the local-DB finding:** the local copy is a *schema-only*
copy plus two local dev users. It can verify **code behavior** (and does, via
the test suite on `main_system_test`), but it **cannot** verify production
user/permission/municipality data. Everything data-dependent below is marked
**VERIFY IN PRODUCTION** with exact read-only queries.

---

## A. P8 hardening scope (proposed)

Legend: **[REQ]** = required before any enforcement flip; **[REC]** =
recommended in P8, not a flip blocker; **[OPT]** = owner's call.

### A.1 [REQ] Client update can move a record out of scope

- **Current:** `ClientController@update` (app/Http/Controllers/ClientController.php:71)
  checks only the client's **current** municipality
  (`RecordMunicipality::ofClient`). The validated payload includes
  `city_municipality` (app/Http/Requests/ClientRequest.php:21), so an enforced,
  municipality-scoped user can relocate a client into a municipality they do
  not hold.
- **Existing coverage:** none — P12 contract §20 test plan explicitly listed
  "client update moving a record to an out-of-scope municipality → denied";
  this test was **not built** (contract deviation).
- **Risk:** scope integrity break on an enforced page; also contradicts the
  approved P12 contract.
- **Proposed fix/test:** in `update()`, additionally check the posted
  `city_municipality` via `canAccessRecord(..., 'clients.php')` before saving;
  add the missing `ScopeTest` case.
- **Before cutover?** YES.

### A.2 [REQ] Login throttling (ADR-007 / v1 gap C2)

- **Current:** `AuthController::login` has no rate limiting
  (app/Http/Controllers/AuthController.php:18) — unlimited password guessing.
- **Existing coverage:** none.
- **Risk:** brute force; ADR-007 explicitly requires "attempt + lockout on the
  login route"; v1 C2 remains open.
- **Proposed fix/test:** Laravel `throttle` middleware on `login.attempt`
  (e.g. 5 attempts/min per IP+username) + feature test asserting 429/lockout.
- **Before cutover?** YES (closes a known v1 critical; cheap).

### A.3 [REC] PAGE ∧ ACTION conjunction defense-in-depth

- **Current:** `canAccessAction` for non-VIEW actions checks only the action
  row (app/Services/AccessControlService.php:104); the page requirement is
  enforced solely by route grouping (every `action:` route sits inside its
  `page:` group — verified in routes/web.php).
- **Existing coverage:** structural (route file), not behavioral.
- **Risk:** low today (composition is correct), but a future route added
  outside its group silently weakens the model.
- **Proposed hardening/test:** either add the page-row conjunction inside
  `canAccessAction`, or (cheaper, no behavior change) a regression test that
  reflects over `routes/web.php` and asserts every `action:` middleware
  instance nests inside the matching `page:` group.
- **Before cutover?** No (recommended anyway).

### A.4 [REC] Config-shape regression guard

- **Current:** if a pilot key were accidentally removed from
  `config/authorization.php`, `canAccessAction` would fail **open** for that
  page (unknown page → true, by design for non-pilot pages).
- **Proposed test:** unit test asserting all five pilot keys exist with their
  expected action catalogs and `enforcement => false` at rest.
- **Before cutover?** No.

### A.5 [OPT] Throttle public self-service write endpoints

- **Current:** `student/verify*`, `unpaid-verification/submit`,
  `grantee-update/save` are anonymous (v1 parity) and unthrottled.
- **Risk:** enumeration/abuse; v1 had the same exposure.
- **Note:** behavior change (429s) on public pages → owner decision; not an
  authz gap.

### A.6 Reviewed and NOT proposed (with reasons)

| Item | Reason not included |
|---|---|
| Deferred P7 audit enhancements (server-side date-range filter, leaderboard date-window, IP metadata) | No security/correctness necessity found; forensic-only value. Stay deferred per instruction. |
| Auditing authorization denials | v1 parity = no read/deny audits (P12 §17: "No read-audits"); would add noise. Owner may opt in later. |
| Program gating changes | Already live since P3 (not behind the enforcement flag) and tested; empty `tbl_program_permissions` = unrestricted is v1 parity. |
| Scanner/payout/unpaid/scholarship-reports scope | Phase-1 boundary per approved contract §15 — non-pilot pages stay page-gated byte-for-byte. |
| Schema changes (indexes etc.) | None required by anything above; avoids baseline regen churn this close to cutover. |

### Pre-cutover hardening summary

| # | Item | Class |
|---|---|---|
| A.1 | Out-of-scope client-move check + missing contract test | REQ |
| A.2 | Login throttling | REQ |
| A.3 | Route-composition regression test (or service conjunction) | REC |
| A.4 | Config-shape guard test | REC |
| A.5 | Public-endpoint throttling | OPT |

---

## B. Required pre-cutover items

Minimum gate before ANY flag flips:

1. A.1 + A.2 implemented and tested; full suite green on `main_system_test`.
2. Production backup taken and restore drill passed (MIGRATION_PLANNING §7).
3. Production additive migration applied (creates `tbl_action_permissions`,
   `tbl_user_municipalities` + Laravel infra tables — see F step order).
4. Production reconciliation queries run (C.9): user inventory, five-page
   holder list, `tbl_municipalities` population check.
5. Super-admin bootstrap executed and verified (F) — without it nobody can
   reach the grant screens.
6. Grants applied per (D/E) and verified.
7. Staging rehearsal of one full flip cycle (grant → flip → smoke → rollback),
   if staging is available (owner action: Hostinger SSH access still
   outstanding per ENGINEERING_BLUEPRINT §9.3).

---

## C. Five-page cutover-readiness matrix

Dimensions per page: **PAGE** (tbl_permissions row, live since P1), **ACTION**
(tbl_action_permissions, inert until flip), **PROGRAM** (tbl_program_permissions,
**already enforced live since P3 — NOT affected by the flag**), **SCOPE**
(tbl_user_municipalities, inert until flip).

| | clients.php | household.php | all_transactions.php | scholars.php | register.php |
|---|---|---|---|---|---|
| Page permission (entry/VIEW) | page row or `*` | page row or `*` | page row or `*` | page row or `*` | page row or `*` |
| Action catalog | VIEW, CREATE, EDIT, DELETE | VIEW, CREATE, DELETE | VIEW, CREATE, EDIT, DELETE, EXPORT | VIEW, CREATE, EDIT | CREATE only |
| Actions gated by routes | store=create; update=edit; destroy=delete; duplicates.destroy=delete; photo.store=edit; gip.store=create; family-members.store=create | store=create; destroy=delete | store=create; update=edit; inline-update=edit; destroy=delete; export=export | store=create; update=edit; update-client-id=edit | admin.users.store=create |
| Program dimension | n/a | n/a | **live already** (store/update authorizeProgram; feed/export filtered) | n/a | n/a |
| Municipality scope | feed + show/edit/update/destroy/store(posted mun.) + photo/gip/family-member/duplicates | feed/search/options + store(head)/show/destroy | feed/export/search + create/store/show/edit/update/inline/destroy (via transaction→client) | feed subquery + store/update/relink (both sides) | **none (metadata page)** |
| Users affected at flip | every non-`*` holder of the page key | same | same | same | same |
| Holders' grants today | **UNKNOWN — VERIFY IN PRODUCTION** (local DB has no representative rows) | same | same | same | same |
| Flip now = deny? | Yes for any holder without action rows (writes) and without scope rows (reads+writes fail closed). With zero grants: holders keep VIEW only if… **no**: VIEW needs page row (they have it) but feeds return EMPTY and detail/write denied | same pattern | same pattern (+ EXPORT denied) | same pattern | Denies only user creation (GET form still renders) |
| Dependencies on other pilots | family-member + GIP + photo + duplicates ride clients.php actions | none | beneficiary picker shared with scholars create (`scholars.clients-search` is under scholars group but uses TransactionController@searchClients scoped to all_transactions.php) | shares clients-search above | none (but bootstrap must precede ALL flips — grants are made through manage_permissions screens) |
| Rollout position | **1st** | **2nd** | **3rd** | **4th** | **5th** |
| Verification checks | C.9 queries + post-grant counts + smoke list (§E) | same | same + program-permission inventory | same | same |
| Rollback | flip flag false (instant, config-only) | same | same | same | same |

**Local vs production verifiability:**

- Verifiable locally: code truth tables, fail-closed behavior, flip/rollback
  mechanics, admin screens, audit events (all covered by the 195-test suite).
- VERIFY IN PRODUCTION (read-only, before grants):
  ```sql
  -- 1. Full user × permission inventory
  SELECT u.id, u.username, p.page_name
  FROM tbl_users u
  LEFT JOIN tbl_permissions p ON p.user_id = u.id AND p.can_access = 1
  ORDER BY u.id, p.page_name;

  -- 2. Holders per pilot page
  SELECT page_name, COUNT(*) AS holders
  FROM tbl_permissions
  WHERE can_access = 1 AND page_name IN
    ('clients.php','household.php','all_transactions.php','scholars.php','register.php')
  GROUP BY page_name;

  -- 3. Existing super admins
  SELECT u.id, u.username FROM tbl_users u
  JOIN tbl_permissions p ON p.user_id = u.id
  WHERE p.page_name = '*' AND p.can_access = 1;

  -- 4. Geography populated? (scope checkboxes + client forms need it)
  SELECT (SELECT COUNT(*) FROM tbl_municipalities) AS municipalities,
         (SELECT COUNT(*) FROM tbl_barangays)   AS barangays;

  -- 5. Program-permission inventory (already live)
  SELECT COUNT(*) FROM tbl_program_permissions;

  -- 6. Data-shape sanity for scope joins
  SELECT COUNT(*) AS clients_with_bad_municipality
  FROM tbl_clients c
  LEFT JOIN tbl_municipalities m ON m.id = c.city_municipality
  WHERE m.id IS NULL;

  -- 7. FULL legacy key inventory (added 2026-08-22): every distinct
  --    production page_name with holder counts. Any value that no v2 route
  --    consumes (see routes/web.php page: gates) must be reviewed before
  --    grants/flags flip — notably the six granular v1 keys (add/edit/view_
  --    client.php, add/edit/view_transaction.php), which were grantable-but-
  --    inert in v1 too and remain inert by design in v2 (ADR-003 record).
  SELECT p.page_name, COUNT(*) AS holders
  FROM tbl_permissions p
  WHERE p.can_access = 1
  GROUP BY p.page_name
  ORDER BY p.page_name;
  ```
- If query 6 returns rows, those records fall out of every scoped user's view
  once enforcement flips (they resolve to municipality 0). Decide: cleanse
  first, or accept (only `*`/ALL-marker users would see them).
- If query 7 lists page_name values outside the keys consumed by
  `routes/web.php` (plus `'*'`), review each holder list before the flip:
  those rows are legacy artifacts whose only effect is catalog noise, but they
  must be accounted for so nothing "reappears" unexpectedly post-cutover.

---

## D. Recommended rollout order (safest incremental)

Per ADMIN_ANALYSIS §13 recommendation, one page at a time:

**clients.php → household.php → all_transactions.php → scholars.php → register.php**

Rationale: clients.php has the smallest catalog spread and the most complete
test coverage; register.php last because it is the least used and its failure
mode (cannot create users) is recoverable via SQL while other pages are stable.
All-at-once is possible but not recommended (contract §13).

Per-page flip procedure (repeat for each page):

1. **Backup** — fresh `mysqldump` (or hoster snapshot).
2. **Grants** — via admin screens (audited `MANAGE_ACTION_PERMISSIONS` /
   `MANAGE_SCOPE_ASSIGNMENTS`), per matrix E.
3. **Verify grants** — read-back SQL:
   ```sql
   SELECT user_id, page_name, action FROM tbl_action_permissions WHERE page_name = '<page>';
   SELECT user_id, municipality_id FROM tbl_user_municipalities ORDER BY user_id;
   ```
4. **Flip** — set `'enforcement' => true` for that page only in
   `config/authorization.php`. **If production runs `php artisan config:cache`,
   re-run it** (otherwise the flip does not take effect).
5. **Smoke tests** (as a granted non-`*` user):
   - feed loads and shows in-scope rows; recordsTotal > 0;
   - open/create/edit(/delete/export per catalog) an in-scope record — allowed;
   - request an out-of-scope record id directly — denied;
   - as a page-holder WITHOUT action rows (if any exist): page opens, writes
     denied with dashboard redirect (HTML) / 403 (JSON);
   - as `*`: everything unchanged.
6. **Expected behavior** — matrix §C row "Flip now = deny?" avoided by step 2.
7. **Monitor** — `tbl_audit_logs` for unexpected denial complaints; v1 error
   log equivalent.
8. **Rollback** — flip the single flag back to `false` (instant; tables become
   inert; grants may stay). Escalation path: schema rollback exists
   (`down()` drops only the two pivot tables) but should never be needed.

**Block conditions (do not flip while any hold):**

- Any pilot-page holder lacks required grants (query C.9-2 vs grant read-back).
- `tbl_municipalities` empty (scope screen cannot grant; ALL-marker only).
- Backup/restore drill not done.
- Suite red on `main_system_test`.
- Bootstrap (F) not yet executed and verified.
- Unresolved rows from C.9-6 without an owner decision.

---

## E. Exact pre-grant requirements (per page, per user class)

Strategy: preserve today's ability exactly — every current holder gets the
full non-VIEW catalog of their page + the ALL-municipality marker. Restriction
to specific municipalities/actions can then be tightened gradually per user,
each change audited. Alternative (stricter day-one) available: grant only the
actions each user actually performs + explicit municipalities — more owner
effort, more denial risk.

| Page | User class | Page perm | Action perms to grant | Program perms | Scope | Flag |
|---|---|---|---|---|---|---|
| clients.php | `*` holder | has `*` | none needed | n/a | none needed | — |
| clients.php | page holder | already holds page row | CREATE + EDIT + DELETE | n/a | ALL marker `(user_id, 0)` | flip after grants |
| household.php | page holder | already holds | CREATE + DELETE | n/a | ALL marker | flip after grants |
| all_transactions.php | page holder | already holds | CREATE + EDIT + DELETE + EXPORT | **unchanged — already live** (empty table = unrestricted, v1 parity) | ALL marker | flip after grants |
| scholars.php | page holder | already holds | CREATE + EDIT | n/a | ALL marker | flip after grants |
| register.php | page holder | already holds | CREATE | n/a | none (metadata) | flip after grants |

Nothing has been inserted; all grants above await owner approval and are then
applied through the audited admin screens (never raw SQL, except the F
bootstrap which precedes screen availability).

---

## F. Production Super Admin bootstrap runbook (PREPARED — DO NOT EXECUTE)

Principle: super-admin status is a data row (`tbl_permissions.page_name = '*'`,
`can_access = 1`) — never code, never a seeder, never a username check. The
nominated user is a **deployment-time value** substituted into reviewed SQL at
the cutover window; nothing is hardcoded in the application.

**Step 0 — Preconditions (read-only):**

```sql
-- Nominee exists exactly once:
SELECT id, username FROM tbl_users WHERE username = '<NOMINATED_USERNAME>';

-- No working super admin exists (expected worst case):
SELECT u.id, u.username FROM tbl_users u
JOIN tbl_permissions p ON p.user_id = u.id
WHERE p.page_name = '*' AND p.can_access = 1;

-- Nominee currently holds no '*':
SELECT * FROM tbl_permissions WHERE user_id = <NOMINEE_ID> AND page_name = '*';
```

**Step 1 — Grant (guarded, idempotent, touches nothing else):**

```sql
INSERT INTO tbl_permissions (user_id, page_name, can_access)
SELECT id, '*', 1 FROM tbl_users
WHERE username = '<NOMINATED_USERNAME>'
  AND NOT EXISTS (
    SELECT 1 FROM tbl_permissions p
    WHERE p.user_id = tbl_users.id AND p.page_name = '*'
  );
```

**Step 2 — Audit row** (`tbl_audit_logs.user_id`/`target_id` are NOT NULL →
real ids; reuses the canonical `MANAGE_SUPER_ADMIN_GRANT` string):

```sql
INSERT INTO tbl_audit_logs (user_id, action, target_table, target_id, old_value, new_value, created_at)
SELECT id, 'MANAGE_SUPER_ADMIN_GRANT', 'tbl_permissions', id,
       '{"super_admin":false}', '{"super_admin":true}', NOW()
FROM tbl_users WHERE username = '<NOMINATED_USERNAME>';
```

**Step 3 — Verify:**

```sql
SELECT u.username FROM tbl_users u
JOIN tbl_permissions p ON p.user_id = u.id
WHERE p.page_name = '*' AND p.can_access = 1;          -- exactly the nominee
SELECT action, created_at FROM tbl_audit_logs
WHERE action = 'MANAGE_SUPER_ADMIN_GRANT'
ORDER BY id DESC LIMIT 1;                               -- the bootstrap row
```

Then log in as the nominee: dashboard renders, admin sidebar links appear,
`admin/permissions` opens.

**Reversibility (revoke):**

```sql
DELETE FROM tbl_permissions
WHERE user_id = (SELECT id FROM (SELECT id FROM tbl_users WHERE username = '<NOMINATED_USERNAME>') t)
  AND page_name = '*';

INSERT INTO tbl_audit_logs (user_id, action, target_table, target_id, old_value, new_value, created_at)
SELECT id, 'MANAGE_SUPER_ADMIN_REVOKE', 'tbl_permissions', id,
       '{"super_admin":true}', '{"super_admin":false}', NOW()
FROM tbl_users WHERE username = '<NOMINATED_USERNAME>';
```

**Least-privilege alternative:** instead of `'*'`, grant the nominee the five
P7 keys (`register.php`, `manage_permissions.php`,
`manage_program_permissions.php`,
`manage_multi_device_exemptions.php`, `audit_logs.php`) — full admin reach
without global `*`. Same guarded pattern, five rows. Owner picks `'*'`
(simplest, matches v1's implicit super-user) or the key set (least privilege).
**Recommendation: `'*'` for the bootstrap account**, tighten later via the
audited screens if desired.

**Ordering note:** this bootstrap MUST precede every §D flip — the grant
screens themselves sit behind `page:manage_permissions.php`.

---

## G. ADR-001..010 status review (statuses NOT changed)

| ADR | Title | Status today | Implemented? | Evidence | Recommendation | Reason |
|---|---|---|---|---|---|---|
| 001 | Framework: Laravel | Proposed | **Yes** | Whole codebase on Laravel 12 (P0–P12); hosting PHP 8.3+ owner-confirmed | **ACCEPT** | Decision fully executed; fallback condition moot |
| 002 | Auth & sessions (username + single-device) | Proposed | **Yes** | P1: username provider, `EnsureSingleDevice` w/ `hash_equals`, exemptions, force-logout; 6 auth tests green | **ACCEPT** | Contract ported and tested; no deviations |
| 003 | Single ACL service | Proposed | **Yes** | `AccessControlService` sole authority; gates `page`/`program`/`action`; `page:`/`action:` middleware; grep shows zero username checks / magic ids; extended P2/P3/P7/P12 | **ACCEPT** | Strongest-evidenced ADR in the repo |
| 004 | Scanner engine (config-driven) | Proposed | **Yes** | P4: 14 scanners via `config/scanner.php`; P5 payout variants same pattern; scanner/payout suites green | **ACCEPT** | Built and regression-covered |
| 005 | Baseline + additive-only migrations | Proposed | **Yes** | `database/schema/mysql-schema.sql` sentinel workflow; 6 additive fixes; P12's 2 additive tables; no destructive op ever run | **ACCEPT** | Guardrails enforced throughout |
| 006 | Front-end stack | **Superseded** (recorded) | Superseded by MODERNIZATION_PROPOSAL; Bootstrap deviation documented | Blade+Bootstrap shipped (P1–P7) | **KEEP AS-IS** (already superseded, not Proposed) | History correct; no action |
| 007 | Security hardening (CSRF, throttling, secrets, errors) | Proposed | **Partial** | CSRF ✓ (framework global), secrets ✓ (.env), generic errors ✓; **login throttling ✗ (A.2)**; credential rotation pending cutover | **KEEP PROPOSED** → ACCEPT after A.2 + rotation ship in P8 | Two decision elements outstanding |
| 008 | Audit & logging | Proposed | **Yes, with recorded deviation** | `AuditService` sole writer, v1 field contract, called from all write paths incl. P12 admin events; deviation: direct service calls instead of events/observers | **REVISE** (mechanism wording) → ACCEPT | Outcome (contract + coverage) met; mechanism differs from original text — amend text, don't rewrite history |
| 009 | Reporting & exports (BOM CSV) | Proposed | **Yes (in-scope reports)** | P3 four export modes; P6 scholarship reports + BOM CSV; export tests green | **ACCEPT** | Ported per contract; remaining report parity tracked under module gates |
| 010 | Environment & deploy | Proposed | **Partial** | Git ✓, .env ✓, additive migrations ✓; scheduled backups + restore drill ✗ (P8/cutover items) | **KEEP PROPOSED** until backup schedule + restore drill executed | Operational half pending by definition |

## H. Remaining owner decisions

1. Approve P8 hardening scope: A.1 + A.2 required; A.3/A.4 recommended;
   A.5 optional — confirm or amend.
2. Approve rollout order (§D) and the per-page flip procedure.
3. Approve grant strategy (§E default: full catalog + ALL marker preserving
   today's ability) or choose stricter day-one grants.
4. Nominate the production super-admin **username** and pick `'*'` vs the
   five-P7-keys least-privilege bootstrap (§F).
5. Decide on C.9-6 handling policy (clients with unresolvable municipality).
6. Confirm deferred P7 audit enhancements stay deferred (no security need found).
7. Optional: public-endpoint throttling (A.5), denial auditing (A.6) — in/out.
8. Which ADRs to flip to Accepted now (G recommends 001/002/003/004/005/009;
   007/010 after their P8 items; 008 revise-then-accept).
9. Staging availability for the rehearsal flip (Hostinger SSH — owner action).

## I. Confirmation

- Enforcement flags: **ALL OFF** (`config/authorization.php` untouched — all
  five pages `enforcement => false`).
- Grants: **none inserted/modified** — `tbl_action_permissions` = 0 rows,
  `tbl_user_municipalities` = 0 rows (verified read-only before and after).
- Production SQL: **none executed**; production database never connected to.
- Production data: **unchanged**; local `main_system` only read (SELECT/DESCRIBE);
  v1 untouched; no application code changed in this pass.
- Test suite re-verified green (195/887) purely as evidence; no state modified.

**HARD STOP honored — awaiting separate owner approval of the P8 hardening
scope and cutover plan before any implementation or cutover step.**

---

## BUILD RECORD — A.1–A.4 implemented 2026-08-24 (after owner approval)

Approved scope: A.1 + A.2 required; A.3 + A.4 recommended; **deferred
(confirmed):** A.5, deferred P7 audit enhancements, denial auditing,
non-pilot pages, program-gating redesign. No cutover actions taken.

- **A.1** — `ClientController@update` now checks the destination municipality
  in addition to the current record; missing P12 §20 regression pair added to
  `ScopeTest` (deny out-of-scope move / allow in-scope move).
- **A.2** — `AuthController::login` throttling: framework `RateLimiter`,
  5 attempts / 60 s per username+IP, cleared on success,
  `ValidationException` lockout message; 3 new `AuthTest` cases.
- **A.3/A.4** — new `tests/Feature/AuthorizationArchitectureTest.php`:
  page→action composition proven over the live route collection with full
  catalog coverage; config shape pinned (exact keys/catalogs/flags-off).
- **Verification:** targeted 24/150 green; full suite **202 tests / 984
  assertions**; pint passed; 18 `action:` instances unchanged; all five flags
  false; both pivot tables empty; production untouched.
- **ADRs:** 001/002/003/004/005/009 Accepted; 008 Accepted (revised — direct
  `AuditService` calls); 007 Proposed (rotation/HTTPS pending); 010 Proposed;
  006 Superseded.

Cutover remains gated on a separate owner-approved execution pass per §B–§F.
