<?php

namespace App\Http\Controllers;

use App\Http\Requests\HouseholdStoreRequest;
use App\Models\Client;
use App\Models\ClientAffOrg;
use App\Models\Household;
use App\Models\Municipality;
use App\Services\HouseholdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HouseholdController extends Controller
{
    public function __construct(private readonly HouseholdService $households) {}

    public function index()
    {
        return view('households.index', [
            'municipalities' => Municipality::query()->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('households.create');
    }

    public function store(HouseholdStoreRequest $request)
    {
        try {
            $this->households->create($request->integer('head_household'), $request->user());
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['head_household' => $e->getMessage()])->withInput();
        }

        return redirect()->route('households.index')->with('success', 'Household added successfully!');
    }

    public function show(Household $household)
    {
        $household->load(['headClient.municipality', 'headClient.barangayInfo']);

        $members = Client::query()
            ->where('household_id', $household->id)
            ->orWhere('id', $household->head_household)
            ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END, lastname, firstname', [$household->head_household])
            ->get(['id', 'full_name', 'age', 'sex']);

        return view('households.show', compact('household', 'members'));
    }

    public function destroy(Request $request, Household $household): JsonResponse
    {
        try {
            $this->households->destroy($household, $request->user());

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function data(Request $request)
    {
        $columns = [
            'h.household_id',
            'c.full_name',
            'm.name',
            'b.name',
            'member_count',
        ];

        $draw = $request->integer('draw', 1);
        $start = $request->integer('start', 0);
        $length = $request->integer('length', 25);
        $searchValue = trim((string) $request->input('search.value', ''));
        $municipalityFilter = $request->integer('municipality', 0);
        $barangayFilter = $request->integer('barangay', 0);

        $orderColumnIndex = (int) $request->input('order.0.column', 0);
        if ($orderColumnIndex < 0 || $orderColumnIndex >= count($columns)) {
            $orderColumnIndex = 0;
        }
        $orderDir = strtolower((string) $request->input('order.0.dir', 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $orderColumn = $columns[$orderColumnIndex];

        $query = DB::table('tbl_household as h')
            ->join('tbl_clients as c', 'h.head_household', '=', 'c.id')
            ->leftJoin('tbl_municipalities as m', 'c.city_municipality', '=', 'm.id')
            ->leftJoin('tbl_barangays as b', 'c.barangay', '=', 'b.id')
            ->select([
                'h.id',
                'h.household_id',
                'c.full_name',
                'm.name as municipality_name',
                'b.name as barangay_name',
                DB::raw('(SELECT COUNT(*) FROM tbl_clients mc WHERE mc.household_id = h.id) + CASE WHEN c.household_id IS NULL OR c.household_id <> h.id THEN 1 ELSE 0 END AS member_count'),
            ]);

        $totalCount = (clone $query)->count();

        if ($municipalityFilter > 0) {
            $query->where('c.city_municipality', $municipalityFilter);
        }
        if ($barangayFilter > 0) {
            $query->where('c.barangay', $barangayFilter);
        }
        if ($searchValue !== '') {
            $like = '%'.$searchValue.'%';
            $query->where(function ($q) use ($like) {
                $q->where('h.household_id', 'like', $like)
                    ->orWhere('c.full_name', 'like', $like);
            });
        }

        $filteredCount = (clone $query)->count();

        $rows = (clone $query)
            ->orderBy($orderColumn, $orderDir)
            ->limit($length)
            ->offset($start)
            ->get();

        $data = [];
        foreach ($rows as $row) {
            $memberCount = (int) $row->member_count;
            $data[] = [
                'household_id' => e($row->household_id),
                'head_name' => e($row->full_name),
                'municipality' => e($row->municipality_name ?? ''),
                'barangay' => e($row->barangay_name ?? ''),
                'members' => $memberCount.' member'.($memberCount !== 1 ? 's' : ''),
                'actions' => '<a href="'.route('households.show', $row->id).'" class="btn btn-info btn-sm">View</a> '
                    .'<button type="button" class="btn btn-danger btn-sm delete-household" data-id="'.$row->id.'">Delete</button>',
            ];
        }

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalCount,
            'recordsFiltered' => $filteredCount,
            'data' => $data,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '' || strlen($q) < 2) {
            return response()->json([]);
        }

        $words = preg_split('/\s+/', strtoupper($q)) ?: [];

        $query = DB::table('tbl_household as h')
            ->join('tbl_clients as c', 'h.head_household', '=', 'c.id')
            ->leftJoin('tbl_municipalities as m', 'c.city_municipality', '=', 'm.id')
            ->leftJoin('tbl_barangays as b', 'c.barangay', '=', 'b.id')
            ->select(['h.id', 'h.household_id', 'h.head_household', 'c.full_name as head_name', 'c.firstname', 'c.lastname', 'm.name as municipality_name', 'b.name as barangay_name']);

        foreach ($words as $word) {
            $like = "%{$word}%";
            $query->where(function ($q) use ($like) {
                $q->where('h.household_id', 'like', $like)
                    ->orWhere('c.firstname', 'like', $like)
                    ->orWhere('c.lastname', 'like', $like)
                    ->orWhere('c.middlename', 'like', $like)
                    ->orWhere('c.extensionname', 'like', $like)
                    ->orWhere('c.full_name', 'like', $like)
                    ->orWhere('m.name', 'like', $like)
                    ->orWhere('b.name', 'like', $like);
            });
        }

        $rows = $query
            ->orderByRaw('CASE WHEN h.household_id LIKE ? THEN 0 WHEN c.lastname LIKE ? THEN 1 WHEN c.firstname LIKE ? THEN 2 ELSE 3 END, h.household_id', ["{$q}%", "{$q}%", "{$q}%"])
            ->limit(15)
            ->get();

        return response()->json($rows);
    }

    public function clientOptions(Client $client): JsonResponse
    {
        $client->load(['municipality', 'barangayInfo']);

        $orgs = ClientAffOrg::query()->where('client_id', $client->id)->pluck('organization');

        return response()->json($client->toArray() + ['aff_orgs' => $orgs->all()]);
    }

    public function searchClientsForHousehold(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '' || strlen($q) < 2) {
            return response()->json([]);
        }

        $words = preg_split('/\s+/', $q) ?: [];

        $query = DB::table('tbl_clients as c')
            ->leftJoin('tbl_municipalities as m', 'c.city_municipality', '=', 'm.id')
            ->leftJoin('tbl_barangays as b', 'c.barangay', '=', 'b.id')
            ->leftJoin('tbl_household as h', 'h.head_household', '=', 'c.id')
            ->select(['c.id', 'c.full_name', 'm.name as municipality_name', 'b.name as barangay_name'])
            ->whereNull('h.head_household');

        foreach ($words as $word) {
            $like = "%{$word}%";
            $query->where(function ($q) use ($like) {
                $q->where('c.firstname', 'like', $like)
                    ->orWhere('c.lastname', 'like', $like)
                    ->orWhere('c.middlename', 'like', $like)
                    ->orWhere('c.extensionname', 'like', $like);
            });
        }

        $rows = $query
            ->orderByRaw('CASE WHEN c.firstname LIKE ? THEN 0 WHEN c.lastname LIKE ? THEN 1 WHEN c.full_name LIKE ? THEN 2 ELSE 3 END, c.lastname, c.firstname', ["{$q}%", "{$q}%", "{$q}%"])
            ->limit(15)
            ->get();

        return response()->json($rows);
    }
}
