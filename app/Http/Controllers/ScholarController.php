<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScholarRequest;
use App\Models\Client;
use App\Models\ScholarInfo;
use App\Services\ScholarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ScholarController extends Controller
{
    /**
     * Orderable columns, kept identical to v1 fetch_scholars.php.
     *
     * @var list<string>
     */
    private const COLUMNS = [
        'id', 'client_id', 'full_name', 'program', 'school', 'school_type',
        'campus', 'college_department', 'course', 'year_level', 'is_regular',
        'year_started', 'landbank_no', 'created_at', 'updated_at',
    ];

    public function __construct(private readonly ScholarService $scholarService) {}

    public function index(): View
    {
        return view('scholars.index');
    }

    /**
     * V1-parity server-side feed (fetch_scholars.php): paginate first, then
     * LEFT JOIN tbl_exam on TRIM(LOWER(full_name)) = TRIM(LOWER(fullname)).
     * recordsTotal and recordsFiltered both equal the filtered count, exactly
     * as v1 reports them.
     */
    public function data(Request $request): JsonResponse
    {
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        $search = $request->input('search.value');
        $orderIndex = (int) $request->input('order.0.column', 1);
        $orderDir = $request->input('order.0.dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $orderBy = self::COLUMNS[$orderIndex] ?? 'client_id';

        $query = ScholarInfo::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('program', 'like', "%{$search}%")
                    ->orWhere('school', 'like', "%{$search}%");
            });
        }

        $total = $query->count();

        $scholars = $query->orderBy($orderBy, $orderDir)
            ->offset($start)
            ->limit($length)
            ->get();

        $names = $scholars->map(fn (ScholarInfo $s) => $s->normalized_name)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $barangayBy = [];
        $townBy = [];
        if ($names !== []) {
            $examRows = DB::table('tbl_exam')
                ->whereIn('normalized_name', $names)
                ->get(['normalized_name', 'barangay', 'town']);
            $barangayBy = $examRows->pluck('barangay', 'normalized_name')->all();
            $townBy = $examRows->pluck('town', 'normalized_name')->all();
        }

        $rows = $scholars->map(fn (ScholarInfo $s) => [
            'id' => $s->id,
            'client_id' => $s->client_id,
            'full_name' => $s->full_name,
            'program' => $s->program,
            'barangay' => $barangayBy[$s->normalized_name] ?? null,
            'town' => $townBy[$s->normalized_name] ?? null,
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $rows,
        ]);
    }

    public function create(Request $request): View
    {
        return view('scholars.create', [
            'clientId' => $request->query('client_id'),
        ]);
    }

    public function store(ScholarRequest $request): RedirectResponse
    {
        $this->scholarService->save($request->validated());

        return redirect()
            ->route('scholars.index')
            ->with('success', 'Scholar record added successfully.');
    }

    public function edit(ScholarInfo $scholar): View
    {
        return view('scholars.edit', [
            'scholar' => $scholar,
        ]);
    }

    public function update(ScholarRequest $request): RedirectResponse
    {
        $this->scholarService->save($request->validated());

        return redirect()
            ->route('scholars.index')
            ->with('success', 'Scholar record updated successfully.');
    }

    /**
     * V1-parity port of update_client_id.php: relink a scholar row to a
     * different client. Returns "success" or HTTP 400 "Invalid input".
     * (Stricter than v1: the scholar and target client must both exist.)
     */
    public function updateClientId(Request $request): JsonResponse
    {
        $id = $request->input('id');
        $clientId = $request->input('client_id');

        if (! is_numeric($id) || ! is_numeric($clientId)) {
            return response()->json(['message' => 'Invalid input'], 400);
        }

        $scholar = ScholarInfo::query()->find((int) $id);
        $client = Client::query()->find((int) $clientId);

        if (! $scholar || ! $client) {
            return response()->json(['message' => 'Invalid input'], 400);
        }

        $scholar->update(['client_id' => (int) $clientId]);

        return response()->json(['message' => 'success']);
    }
}
