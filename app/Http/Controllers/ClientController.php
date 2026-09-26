<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use App\Models\Barangay;
use App\Models\Client;
use App\Models\Municipality;
use App\Services\AccessControlService;
use App\Services\ClientService;
use App\Services\TransactionService;
use App\Support\FilterConfig;
use App\Support\RecordMunicipality;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientController extends Controller
{
    public function __construct(
        private readonly ClientService $clientService,
        private readonly AccessControlService $acl,
    ) {}

    public function index(Request $request): View
    {
        $filterChips = FilterConfig::geography(
            $request,
            $this->acl,
            'clients-filters',
            'clients.php',
        );

        // Program is an opt-in narrowing category for the client registry:
        // it does not add backend scope enforcement (the clients feed has no
        // program ACL), so the option list stays unscoped, mirroring the
        // "unscoped v1 modules keep unscoped options" Phase 2C precedent.
        $filterChips['categories'][] = FilterConfig::programCategory(
            $request,
            TransactionService::PROGRAMS,
        );

        // Category is a static, unscoped filter option list derived from the
        // authoritative deriveCategory catalog (not ACL-scoped — it has no
        // municipality dimension, mirroring the program precedent).
        $filterChips['categories'][] = FilterConfig::staticCategory(
            $request,
            ClientService::CATEGORIES,
        );

        return view('clients.index', [
            'municipalities' => Municipality::query()->orderBy('name')->get(),
            'filterChips' => $filterChips,
        ]);
    }

    public function create(Request $request): View
    {
        $view = $request->boolean('modal')
            ? view('clients._form', [
                'municipalities' => Municipality::query()->orderBy('name')->get(),
                'panel' => false,
                'modal' => true,
            ])
            : view('clients.create', [
                'municipalities' => Municipality::query()->orderBy('name')->get(),
            ]);

        return $view;
    }

    public function store(ClientRequest $request): JsonResponse|RedirectResponse
    {
        $this->acl->canAccessRecord(
            $request->user(),
            (int) $request->validated('city_municipality'),
            'clients.php',
        ) || abort(403, 'Access denied.');

        // High-confidence duplicate gate: when a matching name+birthdate
        // client exists and the user has not explicitly confirmed they are
        // adding a different person, the form re-renders with the existing
        // client(s) surfaced (no record is written). A hidden
        // duplicate_confirm=1 field bypasses the gate on re-submit.
        if (! $request->boolean('duplicate_confirm')) {
            $matches = $this->clientService->findPotentialDuplicates(
                $request->validated('lastname'),
                $request->validated('firstname'),
                $request->validated('middlename'),
                $request->validated('birthdate'),
            );

            if ($matches->isNotEmpty()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'duplicate_warning' => $matches->map(fn ($m) => [
                            'id' => $m->id,
                            'full_name' => $m->full_name,
                            'display_full_name' => $m->displayFullName(),
                            'birthdate' => $m->birthdate,
                            'sex' => $m->sex,
                        ])->all(),
                    ], 422);
                }

                return back()
                    ->withInput()
                    ->with('duplicate_warning', $matches);
            }
        }

        $client = $this->clientService->create($request->validated(), $request->user()->id);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Client {$client->full_name} added successfully.",
                'client_id' => $client->id,
            ]);
        }

        return redirect()
            ->route('clients.index')
            ->with('success', "Client {$client->full_name} added successfully.");
    }

    public function edit(Request $request, Client $client): View
    {
        $this->acl->canAccessRecord($request->user(), RecordMunicipality::ofClient($client->id), 'clients.php')
            || abort(403, 'Access denied.');

        $data = [
            'client' => $client,
            'affOrgs' => $client->affOrgs()->orderBy('id')->pluck('organization')->all(),
            'municipalities' => Municipality::query()->orderBy('name')->get(),
            'barangays' => Barangay::query()
                ->where('municipality_id', $client->city_municipality)
                ->orderBy('name')
                ->get(),
        ];

        if ($request->boolean('modal')) {
            $data['panel'] = false;
            $data['modal'] = true;
            // The modal reuses clients._form, whose default action is the
            // store route. Pin the edit modal to the update endpoint so it
            // PUTs to clients.update (which runs no duplicate gate) instead of
            // POSTing to clients.store (which would flag the same client as its
            // own duplicate and, if continued, create a second record).
            $data['action'] = route('clients.update', $client);
            $data['method'] = 'PUT';
        }

        return view($request->boolean('modal') ? 'clients._form' : 'clients.edit', $data);
    }

    public function update(ClientRequest $request, Client $client): JsonResponse|RedirectResponse
    {
        $this->acl->canAccessRecord($request->user(), RecordMunicipality::ofClient($client->id), 'clients.php')
            || abort(403, 'Access denied.');

        $this->acl->canAccessRecord($request->user(), (int) $request->validated('city_municipality'), 'clients.php')
            || abort(403, 'Access denied.');

        $client = $this->clientService->update($client, $request->validated(), $request->user()->id);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Client {$client->full_name} updated successfully.",
                'id' => $client->id,
            ]);
        }

        return redirect()
            ->route('clients.index')
            ->with('success', "Client {$client->full_name} updated successfully.");
    }

    public function destroy(Request $request, Client $client): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $client);

        $this->acl->canAccessRecord($request->user(), RecordMunicipality::ofClient($client->id), 'clients.php')
            || abort(403, 'Access denied.');

        try {
            $this->clientService->destroy($client, $request->user());
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()
                ->back()
                ->withErrors(['delete' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Client deleted successfully.',
            ]);
        }

        return redirect()
            ->route('clients.index')
            ->with('success', 'Client deleted successfully.');
    }

    public function show(Request $request, Client $client): View|Response
    {
        $this->acl->canAccessRecord($request->user(), RecordMunicipality::ofClient($client->id), 'clients.php')
            || abort(403, 'Access denied.');

        $client->load([
            'municipality',
            'barangayInfo',
            'household.headClient',
            'affOrgs',
            'photos',
            'familyMembers.relative',
            'transactions',
            'gipInfo',
        ]);

        $gip = $client->gipInfo->sortByDesc('id')->first();
        $hasGipTransaction = $client->transactions->contains('program', 'GIP');

        if ($request->boolean('panel')) {
            return response()->view('clients._details', [
                'client' => $client,
                'panel' => true,
                'gip' => $gip,
                'hasGipTransaction' => $hasGipTransaction,
            ]);
        }

        return view('clients.show', [
            'client' => $client,
            'gip' => $gip,
            'hasGipTransaction' => $hasGipTransaction,
        ]);
    }

    public function verifyMobile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:tbl_clients,id'],
            'mobile_no' => ['nullable', 'string', 'max:50'],
        ]);

        $client = Client::query()->findOrFail($validated['id']);

        if (empty($client->mobile_no)) {
            return response()->json(['success' => true, 'skipped' => true]);
        }

        if ($client->mobile_no === ($validated['mobile_no'] ?? null)) {
            return response()->json(['success' => true, 'skipped' => false]);
        }

        return response()->json(['success' => false, 'error' => 'Mobile number does not match']);
    }

    /**
     * Server-side DataTables feed — port of v1 fetch_clients.php contract
     * (draw/recordsTotal/recordsFiltered/data, word-split AND search,
     * municipality/barangay filters, smart ranking on search).
     */
    public function data(Request $request): JsonResponse
    {
        $draw = (int) $request->input('draw', 1);
        $start = (int) $request->input('start', 0);
        $length = max((int) $request->input('length', 25), 1);
        // The clients screen owns its single search field (#clientsSearch) and
        // sends it as the top-level `search` string (the built-in DataTables
        // 'f' box is omitted from the dom). DataTables-standard requests instead
        // send `search` as a { value, regex } object — accept both shapes.
        $searchRaw = $request->input('search', '');
        $search = trim(is_array($searchRaw)
            ? (string) ($searchRaw['value'] ?? '')
            : (string) $searchRaw);
        $municipality = (string) $request->input('municipality', '');
        $barangay = (string) $request->input('barangay', '');
        $program = (string) $request->input('program', '');
        $category = (string) $request->input('category', '');

        $base = DB::table('tbl_clients as c')
            ->leftJoin('tbl_municipalities as m', 'c.city_municipality', '=', 'm.id')
            ->leftJoin('tbl_barangays as b', 'c.barangay', '=', 'b.id')
            ->leftJoin('tbl_household as h', 'c.household_id', '=', 'h.id');

        $this->acl->applyMunicipalityScope($base, $request->user(), 'c.city_municipality', 'clients.php');

        $totalRecords = (clone $base)->count();

        if ($municipality !== '') {
            FilterConfig::applyMultiValue($base, 'c.city_municipality', $municipality);
        }

        if ($barangay !== '') {
            FilterConfig::applyMultiValue($base, 'c.barangay', $barangay);
        }

        if ($program !== '') {
            $base->whereExists(function ($query) use ($program) {
                $query->select(DB::raw(1))
                    ->from('tbl_transactions as tx')
                    ->whereColumn('tx.client_id', 'c.id');
                FilterConfig::applyMultiValue($query, 'tx.program', $program);
            });
        }

        if ($category !== '') {
            FilterConfig::applyMultiValue($base, 'c.category', $category);
        }

        $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $searching = $words !== [];

        if ($searching) {
            foreach ($words as $word) {
                $like = '%'.$word.'%';
                $base->where(function ($q) use ($like) {
                    $q->where('c.firstname', 'like', $like)
                        ->orWhere('c.lastname', 'like', $like)
                        ->orWhere('c.middlename', 'like', $like)
                        ->orWhere('c.extensionname', 'like', $like)
                        ->orWhere('c.full_name', 'like', $like)
                        ->orWhere('c.mobile_no', 'like', $like)
                        ->orWhere('c.voter_id', 'like', $like)
                        ->orWhere('c.precinct_no', 'like', $like)
                        ->orWhere('c.occupation', 'like', $like)
                        ->orWhere('m.name', 'like', $like)
                        ->orWhere('b.name', 'like', $like);
                });
            }
        }

        $totalRecords = (clone $base)->count();

        $totalFiltered = (clone $base)->count();

        $orderColumnIndex = (int) $request->input('order.0.column', 0);
        $orderDir = strtolower((string) $request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        // Column index map aligned to the simplified table's JS `columns`
        // array: Client (sort by lastname), Precinct, Municipality, Barangay,
        // then the interactive Actions column (never sortable).
        // The server-side word-split search still scans all client fields below —
        // DataTables search is server-side, so no hidden searchable columns
        // are needed in the JS definition.
        $columns = [
            'c.lastname', 'c.precinct_no', 'm.name', 'b.name', 'c.category', 'c.id',
        ];
        $orderColumn = $columns[$orderColumnIndex] ?? 'c.lastname';

        $dataQuery = clone $base;

        if ($searching) {
            $rank = $search.'%';
            $dataQuery
                ->orderByRaw(
                    'CASE
                        WHEN c.firstname LIKE ? THEN 0
                        WHEN c.lastname LIKE ? THEN 1
                        WHEN c.full_name LIKE ? THEN 2
                        WHEN m.name LIKE ? THEN 3
                        WHEN b.name LIKE ? THEN 4
                        ELSE 5
                    END',
                    [$rank, $rank, $rank, $rank, $rank],
                )
                ->orderBy('c.lastname')
                ->orderBy('c.firstname');
        } else {
            $dataQuery->orderByRaw($orderColumn.' '.$orderDir);
        }

        $rows = $dataQuery
            ->select([
                'c.id', 'c.lastname', 'c.firstname', 'c.middlename', 'c.extensionname',
                'c.precinct_no', 'c.region', 'c.province', 'c.house_no', 'c.mobile_no',
                'c.birthdate', 'c.age', 'c.sex', 'c.civil_status', 'c.occupation',
                'c.monthly_income', 'c.voter_id', 'c.category',
                'm.name as municipality_name', 'b.name as barangay_name',
                'h.household_id as household_code',
            ])
            ->offset($start)
            ->limit($length)
            ->get();

        $permittedActions = $this->acl->permittedActions($request->user(), 'clients.php');
        $canEdit = in_array('EDIT', $permittedActions, true);
        $canDelete = in_array('DELETE', $permittedActions, true);

        $data = $rows->map(function ($row) use ($canEdit, $canDelete) {
            $id = (string) $row->id;
            $fullname = $this->clientService->deriveDisplayName(
                $row->lastname,
                $row->firstname,
                $row->middlename,
                $row->extensionname,
            );

            // Client ID shown is the household code when one is linked
            // (the stable HH-… identifier v1 prints) else the numeric id.
            // Real data only — no invented CLNT-… format.
            $clientIdLabel = (string) ($row->household_code ?: $id);

            $actions = '';

            $actions .= '<button type="button" class="icon-btn" '
                .'data-view-client="'.$id.'" '
                .'aria-label="View '.htmlspecialchars($fullname).'" title="View">'
                .'<span class="icon-btn-glyph">'
                .'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
                .'</span></button>';

            if ($canEdit) {
                $actions .= '<a href="'.route('clients.edit', $id).'" class="icon-btn" '
                    .'aria-label="Edit '.htmlspecialchars($fullname).'" title="Edit"'
                    .'data-edit-client="'.$id.'">'
                    .'<span class="icon-btn-glyph">'
                    .'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>'
                    .'</span></a>';
            }

            if ($canDelete) {
                $actions .= '<form method="POST" action="'.route('clients.destroy', $id).'" '
                    .'data-confirm="Are you sure you want to delete this client? This cannot be undone." '
                    .'class="d-inline">'
                    .'<input type="hidden" name="_token" value="'.csrf_token().'">'
                    .'<button type="submit" class="icon-btn icon-btn-danger" '
                    .'aria-label="Delete '.htmlspecialchars($fullname).'" title="Delete">'
                    .'<span class="icon-btn-glyph">'
                    .'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>'
                    .'</span></button>'
                    .'</form>';
            }

            return [
                'id' => htmlspecialchars($id),
                'fullname' => htmlspecialchars($fullname),
                'client_id_label' => htmlspecialchars($clientIdLabel),
                'lastname' => htmlspecialchars((string) $row->lastname),
                'firstname' => htmlspecialchars((string) $row->firstname),
                'middlename' => htmlspecialchars((string) $row->middlename),
                'extension' => htmlspecialchars((string) $row->extensionname),
                'precinct' => htmlspecialchars((string) $row->precinct_no),
                'region' => htmlspecialchars((string) $row->region),
                'province' => htmlspecialchars((string) $row->province),
                'municipality' => htmlspecialchars((string) $row->municipality_name),
                'barangay' => htmlspecialchars((string) $row->barangay_name),
                'house_no' => htmlspecialchars((string) $row->house_no),
                'mobile' => htmlspecialchars((string) $row->mobile_no),
                'birthdate' => htmlspecialchars((string) $row->birthdate),
                'age' => htmlspecialchars((string) $row->age),
                'sex' => htmlspecialchars((string) $row->sex),
                'civil_status' => htmlspecialchars((string) $row->civil_status),
                'occupation' => htmlspecialchars((string) $row->occupation),
                'income' => htmlspecialchars((string) $row->monthly_income),
                'voter_id' => htmlspecialchars((string) $row->voter_id),
                'category' => htmlspecialchars((string) $row->category),
                'actions' => $actions,
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
        ]);
    }

    /**
     * Global search autocomplete — lightweight JSON endpoint for the topbar
     * search dropdown. Reuses the same word-split AND search semantics as
     * the DataTables data() feed, municipality scope via clients.php, and
     * smart ranking (prefix matches first). Returns at most 8 results.
     */
    public function globalSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $user = $request->user();

        if (! $this->acl->canAccessPage($user, 'clients.php')) {
            return response()->json(['results' => []]);
        }

        $base = DB::table('tbl_clients as c')
            ->leftJoin('tbl_municipalities as m', 'c.city_municipality', '=', 'm.id')
            ->leftJoin('tbl_barangays as b', 'c.barangay', '=', 'b.id');

        $this->acl->applyMunicipalityScope($base, $user, 'c.city_municipality', 'clients.php');

        $words = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($words as $word) {
            $like = '%'.$word.'%';
            $base->where(function ($query) use ($like) {
                $query->where('c.firstname', 'like', $like)
                    ->orWhere('c.lastname', 'like', $like)
                    ->orWhere('c.middlename', 'like', $like)
                    ->orWhere('c.extensionname', 'like', $like)
                    ->orWhere('c.full_name', 'like', $like)
                    ->orWhere('c.mobile_no', 'like', $like)
                    ->orWhere('m.name', 'like', $like)
                    ->orWhere('b.name', 'like', $like);
            });
        }

        $rank = $q.'%';

        $rows = (clone $base)
            ->select([
                'c.id',
                'c.full_name',
                'c.lastname',
                'c.firstname',
                'c.middlename',
                'c.extensionname',
                'c.age',
                'c.sex',
                'm.name as municipality_name',
                'b.name as barangay_name',
            ])
            ->orderByRaw(
                'CASE
                    WHEN c.firstname LIKE ? THEN 0
                    WHEN c.lastname LIKE ? THEN 1
                    WHEN c.full_name LIKE ? THEN 2
                    WHEN m.name LIKE ? THEN 3
                    WHEN b.name LIKE ? THEN 4
                    ELSE 5
                END',
                [$rank, $rank, $rank, $rank, $rank],
            )
            ->orderBy('c.lastname')
            ->orderBy('c.firstname')
            ->limit(8)
            ->get();

        $results = $rows->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'full_name' => (string) $row->full_name,
                'display_name' => $this->clientService->deriveDisplayName(
                    (string) $row->lastname,
                    (string) $row->firstname,
                    $row->middlename ?? null,
                    $row->extensionname ?? null,
                ),
                'age' => (int) $row->age,
                'sex' => (string) $row->sex,
                'municipality' => (string) $row->municipality_name,
                'barangay' => (string) $row->barangay_name,
                'url' => route('clients.show', $row->id),
            ];
        });

        return response()->json(['results' => $results]);
    }

    /**
     * CSV export of the client registry. Streamed with a UTF-8 BOM so Excel
     * decodes the Latin-1-era v1 data correctly, mirroring the scholarship
     * report export pipeline. Honors the current Municipality/Barangay/
     * Program/search filters unless export_all=1 (Export All). Municipality
     * ACL scope from clients.php always applies — a restricted user can
     * never export beyond their scope.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = DB::table('tbl_clients as c')
            ->leftJoin('tbl_municipalities as m', 'c.city_municipality', '=', 'm.id')
            ->leftJoin('tbl_barangays as b', 'c.barangay', '=', 'b.id')
            ->leftJoin('tbl_household as h', 'c.household_id', '=', 'h.id');

        $this->acl->applyMunicipalityScope($query, $request->user(), 'c.city_municipality', 'clients.php');

        if (! $request->boolean('export_all')) {
            $municipality = (string) $request->query('municipality', '');
            $barangay = (string) $request->query('barangay', '');
            $program = (string) $request->query('program', '');
            $category = (string) $request->query('category', '');
            $search = trim((string) $request->query('search', ''));

            if ($municipality !== '') {
                FilterConfig::applyMultiValue($query, 'c.city_municipality', $municipality);
            }

            if ($barangay !== '') {
                FilterConfig::applyMultiValue($query, 'c.barangay', $barangay);
            }

            if ($program !== '') {
                $query->whereExists(function ($sub) use ($program) {
                    $sub->select(DB::raw(1))
                        ->from('tbl_transactions as tx')
                        ->whereColumn('tx.client_id', 'c.id');
                    FilterConfig::applyMultiValue($sub, 'tx.program', $program);
                });
            }

            if ($category !== '') {
                FilterConfig::applyMultiValue($query, 'c.category', $category);
            }

            if ($search !== '') {
                $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                foreach ($words as $word) {
                    $like = '%'.$word.'%';
                    $query->where(function ($q) use ($like) {
                        $q->where('c.firstname', 'like', $like)
                            ->orWhere('c.lastname', 'like', $like)
                            ->orWhere('c.middlename', 'like', $like)
                            ->orWhere('c.extensionname', 'like', $like)
                            ->orWhere('c.full_name', 'like', $like)
                            ->orWhere('c.mobile_no', 'like', $like)
                            ->orWhere('c.voter_id', 'like', $like)
                            ->orWhere('c.precinct_no', 'like', $like)
                            ->orWhere('m.name', 'like', $like)
                            ->orWhere('b.name', 'like', $like);
                    });
                }
            }
        }

        $rows = $query
            ->select([
                'c.id',
                'c.lastname',
                'c.firstname',
                'c.middlename',
                'c.extensionname',
                'c.birthdate',
                'c.age',
                'c.sex',
                'c.civil_status',
                'm.name as municipality_name',
                'b.name as barangay_name',
                'c.house_no',
                'c.mobile_no',
                'c.email',
                'c.pwd',
                'c.ip',
                'c.ip_group',
                'c.occupation',
                'c.monthly_income',
                'c.precinct_no',
                'c.voter_id',
                'c.category',
                'h.household_id as household_code',
            ])
            ->orderBy('c.lastname')
            ->orderBy('c.firstname')
            ->get();

        $fileName = 'clients_export_'.date('Ymd');

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            if ($rows->isNotEmpty()) {
                fputcsv($out, array_keys((array) $rows->first()));
            }

            foreach ($rows as $row) {
                fputcsv($out, (array) $row);
            }

            fclose($out);
        }, $fileName.'.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
