# P2 — Client Registry, Households, Family Members, Duplicates, Photos, and Student Self-Service

> **Status:** Delivered, tested, and documented.
> **Scope of this document:** the client registry (index/add/edit/profile +
> server-side DataTables feed), household codegen/CRUD, family-member linking,
> duplicate detection/cleanup, client photo upload (file + camera), mobile
> verification, the client-details slide-over panel, and the public student
> self-service photo flow. This is the primary maintainer reference for the
> client domain in v2.

---

## 1. Purpose

P2 builds the client record — the entity nearly every other module hangs off.
It ports v1's `clients.php`, `add_client.php`, `edit_client.php`,
`view_client.php`, `delete_client.php`, `fetch_clients.php`,
`search_clients.php`, `preview_duplicates.php`/`fetch_duplicates.php`/
`delete_duplicates.php`, `save_client_photo.php`, `household.php`, and the
`student_*` self-service files — while centralizing derived-field computation
and keeping the frozen `main_system` database byte-identical.

---

## 2. Legacy v1 behavior (what we ported)

- **Clients** were edited with a large plain-PHP form; `full_name`,
  `match_name`, `age`, and `category` were derived **inline in the page** and
  written to `tbl_clients`. The edit path computed `match_name` as a no-space
  uppercase concatenation of last+first+middle; the **add path did not** (an
  inconsistency — see the A6 fix below). Affiliations were a delete-then-insert
  of `tbl_client_aff_orgs`.
- **Delete** (`delete_client.php`) ran a raw `DELETE FROM tbl_clients WHERE id=…`
  which **failed with a foreign-key constraint error** whenever the client had
  transactions (`tbl_transactions.client_id` has no `ON DELETE CASCADE`), and
  **left orphaned rows** in `tbl_family_members` (that table has no FK).
- **Households** used `CODE-00001`-style IDs; code came from the municipality
  `code` column, and there was a fallback derivation from the name when code was
  missing.
- **Family members** (`add_family_member.php`) wrote one direction of the
  relationship only; the inverse direction and sibling fan-out were
  inconsistent in v1.
- **Duplicates** (`preview_duplicates.php`) were a
  `GROUP BY lastname, firstname, middlename, city_municipality HAVING COUNT(*)>1`
  query; `delete_duplicates.php` deleted in bulk.
- **Photos** (`save_client_photo.php`) accepted an upload or a camera capture,
  stored the file under `uploads/client_photos/`, and kept only the filename in
  `tbl_client_photos.photo_path`.
- **Student self-service** (`student_update_photo.php` →
  `student_verify.php` → `student_photo_upload.php`) let a scholar search their
  name, prove identity with birthdate + mobile number, then update their own
  photo. Identity was held in a `verified_student` session key.

### Deviations v2 deliberately makes (all behavioral, audited)

| v1 | v2 |
|---|---|
| `match_name` derived on edit only | Derived on add **and** edit via `ClientService::deriveMatchName` (A6 fix) — same formula as v1 edit |
| Inline page-level derived fields | Single writer: `ClientService::attributes()` computes `age`, `category`, `full_name`, `match_name`; client-supplied age/category are ignored |
| Raw DELETE with FK error | Explicit guard in `ClientService::destroy` (throws `InvalidArgumentException`) surfaced as a form error |
| Orphaned family rows on client delete | Both-direction family links removed in the same DB transaction |
| Duplicates bulk delete | One-by-one via `ClientService::destroy` so a single FK-guarded row (client with transactions) does not abort the batch; failures are reported |
| `view_client.php` navigation | Full page (`clients.show`) **and** a right-side slide-over Offcanvas panel loading the shared `clients/_details` partial (ADR-010) |

---

## 3. Laravel architecture

```
clients.php            → route group middleware('page:clients.php')
                          ClientController (index/create/store/edit/update/show/destroy)
                          ClientService          (single write path + derived fields)
                          ClientRequest          (validation)
                          ClientPolicy           (delete gate)
                          PhotoController        (photo upload)
                          DuplicateController    (duplicates feed + destroy)
household.php          → HouseholdController + HouseholdService + HouseholdStoreRequest
clients.php (shared)   → FamilyMemberController (create/store/search)
```

- The `page:clients.php` gate protects clients, duplicates, photos, and family
  members alike (matching v1's page grouping). Households use
  `page:household.php`.
- Geography cascade is served by `GeographyController@barangays` (port of v1
  `get_barangays.php`), used by every form that needs a municipality→barangay
  pair.
- All P2 writes are audited through `AuditService` (§4.2 of P1 doc).

---

## 4. Services

### 4.1 `ClientService` (`app/Services/ClientService.php`)

The **single write path** for clients (ADR-003 heritage). Constants:

- `REGION = 'Region I'`, `PROVINCE = 'Ilocos Sur'` — hardcoded to match the
  municipality's fixed geography; the region/province fields always take these
  values.

Derived-field helpers (the "why" for each):

- `normalizeText(?string)` — upper + trim. All free text is normalized this way.
- `deriveFullName(last, first, middle, ext)` — v1 shape
  `"LASTNAME, FIRSTNAME MIDDLENAME EXTENSION"`; middlename omitted when blank
  or literally `N/A`; whitespace collapsed.
- `deriveMatchName(last, first, middle)` — no-space uppercase concatenation
  (the v1 edit-path formula, now applied consistently on add too).
- `deriveAge(birthdate)` — calendar-year difference (`DateTimeImmutable->diff`
  on `today`); 0 on unparseable input.
- `deriveCategory(age)` — the four v1 categories:
  `MINOR (0-17)`, `YOUTH (18-29)`, `ADULT (30-59)`, `SENIOR CITIZEN (60 AND ABOVE)`.
- `attributes(array $input)` — the **normalization pipeline**: turns raw form
  input into exactly the persisted attribute array. It overrides any
  client-supplied `age`/`category` with derived values, enforces `ip_group`
  only when `ip = YES`, coerces `city_municipality`/`barangay` to ints (the DB
  stores IDs in int columns here, unlike v1's varchar quirks elsewhere), and
  nulls empties for optional numerics.

Writes (each is a `DB::transaction`):

- `create(array $input, int $userId)` — persists the client, syncs
  affiliations (`aff_org` column forced to `''` — the real org list lives in
  `tbl_client_aff_orgs`), and audits `ADD_CLIENT` with the full payload.
- `update(Client, array $input, int $userId)` — persists + syncs affiliations,
  computes an old/new diff of the normalized attributes, and audits
  `EDIT_CLIENT` only when something actually changed (old→new maps).
- `destroy(Client, User)` — guard: throws when `transactions()->exists()`
  (v1 FK behavior surfaced explicitly). Otherwise, inside one transaction:
  delete both-direction `tbl_family_members` rows, delete the client (photos
  cascade via `ON DELETE CASCADE`), audit `DELETE_CLIENT` with the old
  attributes.

`syncAffiliations(clientId, orgs)` is the delete-then-insert v1 port; it
normalizes each org, dedupes, and returns the stored list (used in audit
payloads).

### 4.2 `HouseholdService` (`app/Services/HouseholdService.php`)

- `generateHouseholdId(int $municipalityId)` — takes the municipality `code`
  (uppercased) or a `generateFallbackCode` (strip `CITY/MUNICIPALITY/OF`,
  strip non-letters, first 3 letters, `HHD` fallback), finds the highest
  existing `CODE-#####` household id, and returns `CODE-%05d` of next+1.
- `create(int $headHousehold, User)` — guards: head client must exist, must not
  already be a household head, and must have a municipality on file (needed for
  codegen). Audits `ADD_HOUSEHOLD` with id + head + head name.
- `destroy(Household, User)` — detaches all `tbl_clients.household_id` pointing
  at this household (`→ null`), deletes the household, audits `DELETE_HOUSEHOLD`
  with the old values. Note the client `household_id` column references the
  household **row id** (`tbl_household.id`), not the human `household_id` code.

### 4.3 `FamilyMemberService` (`app/Services/FamilyMemberService.php`)

- `getRelationship(role, sex)` — gender-derived labels:
  `PARENT→FATHER/MOTHER`, `CHILD→SON/DAUGHTER`, `GRANDPARENT→GRANDFATHER/GRANDMOTHER`,
  `GRANDCHILD→GRANDSON/GRANDDAUGHTER`, `SIBLING`, `SPOUSE`, else upper-cased
  input verbatim.
- `getInverseRelationship(relationship, otherSex)` — the reverse mapping for
  the mirror row (e.g. `FATHER` on a male parent ⇒ `SON` on the child).
- `link(Client $parent, int $memberId, string $relationship, User)` — inside a
  transaction, `firstOrCreate` both directions (unique pair
  `(client_id, relative_id)` is the belt). **SIBLING fan-out:** if the parent
  has a `family_id`, every other client in that family is linked as a sibling
  to the new member (both directions). Audits `ADD_FAMILY_MEMBER` with the
  client/relative/relationship payload.

### 4.4 `DuplicateService` (`app/Services/DuplicateService.php`)

- `baseQuery()` — the v1 duplicate definition as a subquery:
  `GROUP BY lastname, firstname, middlename, city_municipality HAVING COUNT(*)>1`,
  joined back to clients × municipalities × barangays so every row in a
  duplicated group is returned.
- `countFiltered(municipality, barangay)` / `countTotal()` — feed counts; the
  total deliberately ignores municipality/barangay filters (v1 counted it that
  way) while `recordsFiltered` honors them.
- `destroyMany(array $ids, User)` — deletes one client at a time via
  `ClientService::destroy`; collects failed ids (FK-guarded rows) and returns
  `{deleted, failed}`.

### 4.5 `PhotoService` (`app/Services/PhotoService.php`)

- `store(int $clientId, ?UploadedFile $file, ?string $cameraImage)` — one
  method, two sources:
  - **UPLOAD:** extension must be in `[jpg, jpeg, png, gif]`, bytes read from
    the file.
  - **CAMERA:** base64 data-URL, spaces→`+`, `base64_decode(..., true)`, and a
    hard JPEG magic check (`\xFF\xD8\xFF` prefix) to reject garbage before it
    hits disk. Extension forced to `jpg`.
  - Filename `uniqid('', true).'.'.$extension`; stored under
    `uploads/client_photos/` on the `public` disk; only the filename persists in
    `tbl_client_photos.photo_path` (same storage contract as v1).
  - Throws `InvalidArgumentException` on any violation; callers turn that into
    form errors.

---

## 5. Controllers

### 5.1 `ClientController` (`app/Http/Controllers/ClientController.php`)

- `index()` / `create()` — municipality lists for dropdowns.
- `store(ClientRequest)` — `ClientService::create($request->validated(), user id)`
  then redirect to index with a flash containing the derived full name.
- `edit(Client)` — loads aff orgs + municipality + pre-selected barangays.
- `update(ClientRequest, Client)` — `ClientService::update`.
- `destroy(Request, Client)` — `$this->authorize('delete', $client)`
  (`ClientPolicy` → `clients.php`), catches `InvalidArgumentException` into a
  `delete` form error.
- `show(Request, Client)` — eager-loads municipality, barangay, household/head,
  aff orgs, photos, family members (with relatives), transactions. When
  `?panel=1` it returns just the `clients/_details` partial for the Offcanvas
  slide-over; otherwise the full `clients.show` page (the deep link).
- `verifyMobile(Request)` — JSON port of the v1 mobile check: empty stored
  mobile → `{success, skipped:true}`; exact match → `{skipped:false}`;
  mismatch → `{success:false, error}`.
- `data(Request)` — the **server-side DataTables feed** (v1
  `fetch_clients.php` contract): `draw/recordsTotal/recordsFiltered/data`,
  word-split **AND** search across name/geo/contact fields, municipality +
  barangay filters, and a smart ranking `CASE` when searching (firstname →
  lastname → full_name → municipality → barangay prefix match). Every string in
  the response is `htmlspecialchars`-encoded; the actions cell embeds the View
  (opens `openClientPanel(id)`), Edit, and Delete buttons.

### 5.2 `HouseholdController` (`app/Http/Controllers/HouseholdController.php`)

- `store(HouseholdStoreRequest)` — catches `InvalidArgumentException` into a
  `head_household` form error.
- `show(Household)` — loads head + municipality/barangay; members query = all
  clients with `household_id` = this row **plus** the head, head first.
- `destroy(Request, Household)` — JSON `{success}`; 422 on failure.
- `data(Request)` — DataTables feed with a computed `member_count` subquery
  (clients in the household, +1 if the head is not themselves attached),
  filters by municipality/barangay, search across household id + head name.
- `search(Request)` — head search for the create form (household id + name +
  geo), ranked like the client search.
- `clientOptions(Client)` — returns the client's full record + aff orgs for the
  head picker display.
- `searchClientsForHousehold(Request)` — client search that **excludes clients
  who are already a household head** (left join `tbl_household`, `whereNull`).

### 5.3 `FamilyMemberController` (`app/Http/Controllers/FamilyMemberController.php`)

- `create(Client)` — the add-member page.
- `store(Request, Client)` — validates `existing_client_id` (must exist) and
  `relationship`; **self-link guard** (a client cannot be their own family
  member) with a dedicated error message; then `FamilyMemberService::link`.
- `search(Request)` — ranked client search for the member picker.

### 5.4 `DuplicateController` (`app/Http/Controllers/DuplicateController.php`)

- `index(Request)` — municipality/barangay query presets.
- `data(Request)` — DataTables feed on `DuplicateService::baseQuery()` with
  search across name/geo/precinct; row zero is a delete checkbox.
- `destroy(Request)` — collects `delete_ids[]` (ints > 0), preserves current
  filters in the redirect, flashes the deleted/skipped summary.

### 5.5 `PhotoController` (`app/Http/Controllers/PhotoController.php`)

- `store(Request)` — validates `client_id` exists, `photo` nullable file image
  max 5 MB, `camera_image` nullable string; delegates to `PhotoService`; errors
  render back to the client profile page.

### 5.6 `StudentController` (`app/Http/Controllers/StudentController.php`)

Public self-service flow (no auth, by design — the student is the public user).

- `updatePhoto(Request)` — search box over **transactions joined to clients**,
  restricted to `SCHOLAR_PROGRAMS = [CEAP, CEAP_NEW, CEDSSG, CEDSSG_NEW, OTEA,
  OTCES]`, distinct client rows, lastname/firstname/`CONCAT(lastname, ', ',
  firstname)` LIKE.
- `verify(Request, Client)` — POST validates `birthdate` + `mobile`; a match
  against the client record sets `session('verified_student', client.id)` and
  redirects to photo upload; mismatch returns a `verification` error.
- `photoUpload()` — requires the `verified_student` session key, else redirects
  to the search page; shows the client with photos.
- `storePhoto(Request)` — requires the session key; validates `camera_image`;
  calls `PhotoService::store` with **no file** (camera only for students);
  forgets `verified_student` on success (one-shot).

### 5.7 `GeographyController` (`app/Http/Controllers/GeographyController.php`)

- `barangays(Request)` — validates `municipality_id` exists, returns the
  ordered barangay `{id, name}` list for the cascade dropdown.

---

## 6. Models

| Model | Table | Relations / notes |
|---|---|---|
| `Client` | `tbl_clients` | `municipality()` (belongsTo `tbl_municipalities` via `city_municipality`), `barangayInfo()` (via `barangay`), `household()` (via `household_id` → `tbl_household.id`), `affOrgs()`, `photos()`, `familyMembers()` (client_id side), `transactions()`. Ints: `household_id`, `city_municipality`, `barangay`, `age`; `monthly_income` decimal:2. **No `updated_at`.** |
| `Household` | `tbl_household` | `headClient()` (belongsTo Client via `head_household`). |
| `FamilyMember` | `tbl_family_members` | `client()` + `relative()` (two belongsTo Client). |
| `ClientPhoto` | `tbl_client_photos` | `client()`; `captured_from` enum `UPLOAD`/`CAMERA`; stores only filename. |
| `ClientAffOrg` | `tbl_client_aff_orgs` | `client()`. |
| `Municipality` | `tbl_municipalities` | `barangays()` hasMany. `code` (varchar) drives household codegen. |
| `Barangay` | `tbl_barangays` | `municipality()` belongsTo; FK CASCADE. |

All `$timestamps = false`; `$fillable` matches the legacy columns exactly.

---

## 7. Routes (P2 portion of `routes/web.php`)

```
page:clients.php group:
  GET  clients                       index
  GET  clients/create                create
  POST clients                       store
  GET  clients/verify-mobile         verifyMobile
  GET  clients/duplicates            duplicates.index
  POST clients/duplicates/data       duplicates.data
  POST clients/duplicates/delete     duplicates.destroy
  POST clients/photo                 clients.photo.store
  POST clients/data                  clients.data
  GET  clients/{client}/edit         clients.edit
  PUT  clients/{client}              clients.update
  POST clients/{client}              clients.destroy
  GET  clients/{client}              clients.show
page:household.php group:
  GET  households / households/create / households/{household}
  POST households / households/data / households/{household}
  GET  households/search / households/clients/search / households/clients/{client}
page:clients.php group (family):
  GET  family-members/search
  GET  family-members/{client}       family-members.create
  POST family-members/{client}       family-members.store
public (no auth):
  GET  student/update-photo          student.update-photo
  GET  student/verify/{client}       student.verify
  POST student/verify/{client}       student.verify.post
  GET  student/photo-upload          student.photo-upload
  POST student/photo-upload          student.photo-upload.store
shared: GET geography/barangays      geography.barangays (auth+single-device)
```

`clients/{client}` uses implicit route-model binding for GET/PUT and an
explicit `Client` for POST (delete).

---

## 8. DB tables involved

| Table | Key columns (beyond id) | Notes |
|---|---|---|
| `tbl_clients` | lastname/firstname/middlename/extensionname, region/province (fixed strings), `city_municipality`/`barangay` (ints), house_no, mobile_no, email, birthdate, age, sex, civil_status, pwd, ip, ip_group, occupation, monthly_income, category, aff_org (deprecated blob column, `''`), precinct_no, voter_id, family_id, household_id, **full_name**, **match_name** | `full_name`/`match_name` are denormalized and **derived on write by `ClientService`**. Indexes: `idx_clients_name`, `idx_clients_muni`, `idx_fullname_location`. |
| `tbl_client_aff_orgs` | client_id (FK CASCADE), organization | delete-then-insert on every save. |
| `tbl_client_photos` | client_id (FK CASCADE), photo_path (filename only), captured_from enum | Files on `storage/app/public/uploads/client_photos`. |
| `tbl_household` | household_id (code, e.g. `VIG-00001`), head_household (FK → clients) | `head_household` UNIQUE constraint prevents a second household for the same head (app also guards). |
| `tbl_family_members` | client_id, relative_id, relationship | UNIQUE `(client_id, relative_id)` = the pair belt. |
| `tbl_municipalities` / `tbl_barangays` | name, code / municipality_id, name | Geography cascade; municipality `code` drives household codegen. |

---

## 9. Business rules

1. Derived fields are computed in exactly one place: `ClientService`.
   Client-supplied `age`/`category` are ignored.
2. `full_name` shape: `LASTNAME, FIRSTNAME MIDDLENAME EXTENSION`; middlename
   omitted when blank or `N/A`.
3. `match_name` = no-space uppercase last+first+middle, applied on add **and**
   edit (A6 fix).
4. A client with transactions **cannot** be deleted (explicit guard mirroring
   v1's FK failure).
5. Deleting a client removes both-direction family links (v1 left orphans) and
   cascades photos; it does not touch transactions (there are none by guard).
6. One household head per client (`head_household` unique + service guard).
7. Household ID = `MUNICIPALITY_CODE-00001` sequence; missing code → name
   fallback → `HHD`.
8. Family links are stored in both directions; relationship labels are derived
   from the other party's sex; `SIBLING` fan-out applies to the parent's whole
   `family_id` group.
9. A duplicate is a **group** of clients sharing lastname+firstname+
   middlename+municipality with `COUNT(*) > 1`; every row of the group shows.
10. Student photo updates require the one-shot `verified_student` session key,
    which is consumed on success; only camera images are accepted from
    students, and only for the six scholar programs.
11. Region/Province are constants (`Region I` / `Ilocos Sur`) — the municipality
    is fixed to Ilocos Sur.

---

## 10. Validation

`ClientRequest` rules (the single source for add + edit):

- lastname/firstname required ≤100; middlename/extensionname nullable.
- `city_municipality`/`barangay` required integers that exist.
- `household_id` nullable int existing in `tbl_household`.
- `mobile_no` ≤15, `email` nullable valid email ≤255.
- `birthdate` required date **before today**.
- `sex` in `MALE,FEMALE`; `civil_status` in `SINGLE,MARRIED,WIDOWED`;
  `pwd`/`ip` in `YES,NO`.
- `ip_group` `required_if:ip,YES`.
- `monthly_income` nullable numeric ≥0; `aff_org` nullable array ≤5 items.
- `precinct_no`/`voter_id` nullable ≤50.

`HouseholdStoreRequest`: `head_household` required integer existing in
`tbl_clients`, with friendly messages ("Please search for and select a head of
household." / "Selected client was not found.").

Student verify: `birthdate` required date, `mobile` required ≤50.

Photo: `photo` nullable file image ≤5120 KB, `camera_image` nullable string.

---

## 11. Security notes

- Delete requires `ClientPolicy` → `clients.php` via `authorize()` — even
  though the route group is already page-gated, the policy is the documented
  decision point (defense in depth).
- DataTables feeds escape every rendered string with `htmlspecialchars` /
  Blade `e()` before embedding in HTML cells; delete buttons embed CSRF tokens
  server-side.
- Photo uploads are extension-allowlisted **and** (for camera) magic-byte
  checked before writing; filenames are server-generated (`uniqid`) — no
  client-controlled paths.
- The student flow is public but identity-gated (birthdate + mobile match) and
  limited to scholar programs; it never exposes other client data beyond the
  search results and the chosen profile.
- `verifyMobile` confirms a number without disclosing the stored value.
- All writes are DB-transactional; audit payloads use JSON `new_value`/`old_value`.

---

## 12. Performance notes

- The clients feed and all searches use a **single query** with ranked ordering
  (`orderByRaw CASE … prefix-match`), never fetching the whole table.
- Duplicate detection is a single `GROUP BY/HAVING` subquery + join; counts are
  cheap (`COUNT(*)` over the subquery).
- `ClientService`/`AccessControlService` caching (per-request singleton) avoids
  repeated permission queries on index pages.
- Eager loading in `show()` prevents N+1 on the profile and slide-over panel.
- `tbl_clients` name/municipality indexes back the LIKE searches and the
  duplicate GROUP BY.

---

## 13. Never-change list

- Never let any other code path write `full_name`/`match_name`/`age`/`category`
  — `ClientService::attributes()` is the single writer.
- Never change the `full_name`/`match_name` formulas without an ADR — scanners,
  student verification, and duplicate matching all depend on them.
- Never remove the "has transactions ⇒ cannot delete" guard.
- Never write to `tbl_client_aff_orgs` outside `syncAffiliations`.
- Never drop/alter the `UNIQUE (client_id, relative_id)` pair constraint or the
  `head_household` uniqueness — the app guards depend on them as the belt.
- Never relax the six-program whitelist for student photo self-service without
  an ADR.
- Never reuse the deprecated `aff_org` column for anything real (it stays `''`).

---

## 14. Common mistakes (observed or likely)

1. **Editing derived fields by hand in a migration/console** — always go through
   `ClientService::attributes()`.
2. **Assuming `household_id` on `tbl_clients` is the code string** — it is the
   `tbl_household.id` int; the code string lives in `tbl_household.household_id`.
3. **Writing only one direction of a family link** — always use
   `FamilyMemberService::link` (mirror + sibling fan-out).
4. **Deleting duplicates in a raw batch** — a single transaction-holding client
   would fail the whole DELETE; use `DuplicateService::destroyMany`.
5. **Forgetting `?panel=1` semantics** — `clients.show` renders a partial when
   `panel=true`; new shared content must go in `clients/_details.blade.php` so
   both the page and the Offcanvas stay in sync.
6. **Accepting camera data without the JPEG magic check** — the extension is
   meaningless for base64; `PhotoService` validates bytes.
7. **Reusing the framework `users` table or Laravel's `password_reset_tokens`
   for students** — the student flow has no account; it is session-key based.

---

## 15. Future improvements

- Soft-delete / client-merge is an **open decision** in AGENTS.md — if adopted,
  it belongs here (the delete guard already isolates the transaction check).
- Additive indexes on `tbl_clients` are the recommended open decision; add only
  via the additive-migration path and regenerate the baseline.
- The public student flow could gain CAPTCHA/rate limiting when public exposure
  is confirmed — needs an ADR.
- Photo thumbnails / EXIF stripping could reduce disk use during payout-season
  uploads.

---

## 16. Blueprint / ADR references

- `docs/ENGINEERING_BLUEPRINT.md` §2 rows for `clients.php`, `add_client.php`,
  `edit_client.php`, `view_client.php`, `delete_client.php`, `search_clients.php`,
  `preview_duplicates.php`/`fetch_duplicates.php`/`delete_duplicates.php`,
  `save_client_photo.php`, `student_*`, `household.php`, `add_family_member.php`,
  `get_barangays.php`; §3 table rows for `tbl_clients`, `tbl_household`,
  `tbl_family_members`, `tbl_client_aff_orgs`, `tbl_client_photos`,
  `tbl_municipalities`, `tbl_barangays`.
- `docs/ARCHITECTURE_DECISION.md` — ADR-006 (derived fields / single writer),
  ADR-010 (slide-over panel replacing navigation), ADR-003 (ACL behind delete
  policy).
- `docs/IMPLEMENTATION_LOG.md` — the dated P2 entry with the 7-test ClientTest
  results and the A6 `match_name` consistency fix.
- `docs/REQUIREMENTS_ANALYSIS.md` FRs for the client registry, duplicates, and
  photo handling.
