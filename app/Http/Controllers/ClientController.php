<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use App\Models\Barangay;
use App\Models\Client;
use App\Models\Municipality;
use App\Services\AccessControlService;
use App\Services\ClientService;
use App\Support\RecordMunicipality;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ClientController extends Controller
{
    public function __construct(
        private readonly ClientService $clientService,
        private readonly AccessControlService $acl,
    ) {}

    public function index(): View
    {
        return view('clients.index', [
            'municipalities' => Municipality::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('clients.create', [
            'municipalities' => Municipality::query()->orderBy('name')->get(),
        ]);
    }

    public function store(ClientRequest $request): RedirectResponse
    {
        $this->acl->canAccessRecord(
            $request->user(),
            (int) $request->validated('city_municipality'),
            'clients.php',
        ) || abort(403, 'Access denied.');

        $client = $this->clientService->create($request->validated(), $request->user()->id);

        return redirect()
            ->route('clients.index')
            ->with('success', "Client {$client->full_name} added successfully.");
    }

    public function edit(Request $request, Client $client): View
    {
        $this->acl->canAccessRecord($request->user(), RecordMunicipality::ofClient($client->id), 'clients.php')
            || abort(403, 'Access denied.');

        return view('clients.edit', [
            'client' => $client,
            'affOrgs' => $client->affOrgs()->orderBy('id')->pluck('organization')->all(),
            'municipalities' => Municipality::query()->orderBy('name')->get(),
            'barangays' => Barangay::query()
                ->where('municipality_id', $client->city_municipality)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(ClientRequest $request, Client $client): RedirectResponse
    {
        $this->acl->canAccessRecord($request->user(), RecordMunicipality::ofClient($client->id), 'clients.php')
            || abort(403, 'Access denied.');

        $client = $this->clientService->update($client, $request->validated(), $request->user()->id);

        return redirect()
            ->route('clients.index')
            ->with('success', "Client {$client->full_name} updated successfully.");
    }

    public function destroy(Request $request, Client $client): RedirectResponse
    {
        $this->authorize('delete', $client);

        $this->acl->canAccessRecord($request->user(), RecordMunicipality::ofClient($client->id), 'clients.php')
            || abort(403, 'Access denied.');

        try {
            $this->clientService->destroy($client, $request->user());
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withErrors(['delete' => $e->getMessage()]);
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
        $search = trim((string) $request->input('search.value', ''));
        $municipality = (string) $request->input('municipality', '');
        $barangay = (string) $request->input('barangay', '');

        $base = DB::table('tbl_clients as c')
            ->leftJoin('tbl_municipalities as m', 'c.city_municipality', '=', 'm.id')
            ->leftJoin('tbl_barangays as b', 'c.barangay', '=', 'b.id');

        $this->acl->applyMunicipalityScope($base, $request->user(), 'c.city_municipality', 'clients.php');

        $totalRecords = (clone $base)->count();

        if ($municipality !== '') {
            $base->where('c.city_municipality', $municipality);
        }

        if ($barangay !== '') {
            $base->where('c.barangay', $barangay);
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

        $columns = [
            'c.id', 'c.lastname', 'c.firstname', 'c.middlename', 'c.extensionname',
            'c.precinct_no', 'c.region', 'c.province', 'm.name', 'b.name',
            'c.house_no', 'c.mobile_no', 'c.birthdate', 'c.age', 'c.sex',
            'c.civil_status', 'c.occupation', 'c.monthly_income', 'c.voter_id',
        ];
        $orderColumn = $columns[$orderColumnIndex] ?? 'c.id';

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
                'c.monthly_income', 'c.voter_id', 'm.name as municipality_name',
                'b.name as barangay_name',
            ])
            ->offset($start)
            ->limit($length)
            ->get();

        $data = $rows->map(function ($row) {
            $fullname = trim(
                (string) preg_replace(
                    '/\s+/',
                    ' ',
                    $row->lastname.', '.$row->firstname.' '.$row->middlename.' '.$row->extensionname,
                ),
            );

            return [
                'id' => htmlspecialchars((string) $row->id),
                'fullname' => htmlspecialchars($fullname),
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
                'actions' => '<button type="button" class="btn btn-info btn-sm" onclick="openClientPanel('.$row->id.')">View</button> '
                    .'<a href="'.route('clients.edit', $row->id)
                    .'" class="btn btn-warning btn-sm">✎</a> '
                    .'<form method="POST" action="'.route('clients.destroy', $row->id)
                    .'" class="d-inline" onsubmit="return confirm(\'Are you sure you want to delete this client?\');">'
                    .'<input type="hidden" name="_token" value="'.csrf_token().'">'
                    .'<button type="submit" class="btn btn-danger btn-sm">🗑️</button>'
                    .'</form>',
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
        ]);
    }
}
