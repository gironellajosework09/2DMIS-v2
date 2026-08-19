<?php

namespace App\Http\Controllers;

use App\Services\AccessControlService;
use App\Services\DuplicateService;
use App\Support\RecordMunicipality;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DuplicateController extends Controller
{
    public function __construct(
        private readonly DuplicateService $duplicates,
        private readonly AccessControlService $acl,
    ) {}

    public function index(Request $request): View
    {
        $municipality = (string) $request->query('municipality', '');
        $barangay = (string) $request->query('barangay', '');

        return view('duplicates.index', [
            'municipalities' => DB::table('tbl_municipalities')->orderBy('name')->get(),
            'municipality' => $municipality,
            'barangay' => $barangay,
        ]);
    }

    /**
     * Server-side DataTables feed — port of v1 fetch_duplicates.php.
     */
    public function data(Request $request): JsonResponse
    {
        $draw = (int) $request->input('draw', 1);
        $start = (int) $request->input('start', 0);
        $length = max((int) $request->input('length', 25), 1);
        $search = trim((string) $request->input('search.value', ''));
        $municipality = (string) $request->input('municipality', '');
        $barangay = (string) $request->input('barangay', '');

        $query = $this->duplicates->baseQuery();

        $this->acl->applyMunicipalityScope($query, $request->user(), 'c.city_municipality', 'clients.php');

        if ($municipality !== '') {
            $query->where('c.city_municipality', $municipality);
        }

        if ($barangay !== '') {
            $query->where('c.barangay', $barangay);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('c.lastname', 'like', $like)
                    ->orWhere('c.firstname', 'like', $like)
                    ->orWhere('c.middlename', 'like', $like)
                    ->orWhere('m.name', 'like', $like)
                    ->orWhere('b.name', 'like', $like)
                    ->orWhere('c.precinct_no', 'like', $like);
            });
        }

        $recordsFiltered = (clone $query)->count();

        $totalQuery = $this->duplicates->baseQuery();
        $this->acl->applyMunicipalityScope($totalQuery, $request->user(), 'c.city_municipality', 'clients.php');
        $recordsTotal = $totalQuery->count();

        $columns = [
            'c.id', 'c.id', 'c.lastname', 'c.firstname', 'c.middlename',
            'm.name', 'b.name', 'c.precinct_no',
        ];
        $orderColumnIndex = (int) $request->input('order.0.column', 2);
        $orderDir = strtolower((string) $request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $orderColumn = $columns[$orderColumnIndex] ?? 'c.id';

        $rows = (clone $query)
            ->select([
                'c.id', 'c.lastname', 'c.firstname', 'c.middlename',
                'm.name as municipality_name', 'b.name as barangay_name', 'c.precinct_no',
            ])
            ->orderBy($orderColumn, $orderDir)
            ->offset($start)
            ->limit($length)
            ->get();

        $data = $rows->map(function ($row) {
            $id = (string) $row->id;

            return [
                '<input type="checkbox" name="delete_ids[]" value="'.htmlspecialchars($id).'">',
                htmlspecialchars($id),
                htmlspecialchars((string) $row->lastname),
                htmlspecialchars((string) $row->firstname),
                htmlspecialchars((string) $row->middlename),
                htmlspecialchars((string) $row->municipality_name),
                htmlspecialchars((string) $row->barangay_name),
                htmlspecialchars((string) $row->precinct_no),
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $ids = array_map('intval', (array) $request->input('delete_ids', []));
        $ids = array_filter($ids, fn ($id) => $id > 0);

        // Fail closed: only records inside the actor's municipality scope are
        // eligible for batch deletion; out-of-scope ids are dropped silently.
        $ids = array_values(array_filter(
            $ids,
            fn (int $id) => $this->acl->canAccessRecord(
                $request->user(),
                RecordMunicipality::ofClient($id),
                'clients.php',
            )
        ));

        $filters = array_filter([
            'municipality' => (string) $request->input('municipality', ''),
            'barangay' => (string) $request->input('barangay', ''),
        ]);

        if ($ids === []) {
            return redirect()->route('duplicates.index', $filters)
                ->withErrors(['delete' => 'No records selected for deletion.']);
        }

        $result = $this->duplicates->destroyMany($ids, $request->user());

        $message = "{$result['deleted']} record(s) deleted.";

        if ($result['failed'] !== []) {
            $message .= ' '.count($result['failed']).' skipped (has transactions).';
        }

        return redirect()->route('duplicates.index', $filters)->with('success', $message);
    }
}
