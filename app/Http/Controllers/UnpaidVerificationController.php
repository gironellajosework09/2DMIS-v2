<?php

namespace App\Http\Controllers;

use App\Services\UnpaidService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * P5 unpaid verification admin screen (v1 unpaid_verifications.php + its feed
 * fetch_unpaid_verifications.php + export_unpaid_verifications.php).
 *
 * Column sets, the UPPER-concatenated name formatting, the nine-part search
 * clause, the municipality/date-range filters and the DataTables modes mirror
 * v1 exactly. created_at is returned raw (v1 does not convert it for this
 * screen). The CSV export streams with the UTF-8 BOM — the P5/P3 convention —
 * so Excel renders the names correctly.
 *
 * store() ports unpaid_save.php — the public self-service submit from the
 * disabled_unpaid.php form. v1 wrote no audit row for it (verified against the
 * source), so parity is preserved by UnpaidService::create not calling
 * AuditService, exactly like the P4 attendance write side.
 */
class UnpaidVerificationController extends Controller
{
    public function __construct(private readonly UnpaidService $unpaidService) {}

    public function index(): View
    {
        $municipalities = DB::table('tbl_municipalities')->orderBy('name')->get(['id', 'name']);

        return view('unpaid_verifications.index', ['municipalities' => $municipalities]);
    }

    public function selfService(): View
    {
        return view('unpaid_verifications.self-service');
    }

    public function store(Request $request): JsonResponse
    {
        $data = [
            'client_id' => (int) $request->input('client_id', 0),
            'municipality_id' => (int) $request->input('municipality_id', 0),
            'is_proxy' => (int) $request->input('is_proxy', 0) !== 0,
            'proxy_lastname' => $this->proxyValue($request, 'proxy_lastname'),
            'proxy_firstname' => $this->proxyValue($request, 'proxy_firstname'),
            'proxy_middlename' => $this->proxyValue($request, 'proxy_middlename'),
            'proxy_relationship' => $this->proxyValue($request, 'proxy_relationship'),
            'proxy_phone' => $this->proxyValue($request, 'proxy_phone'),
            'proxy_birthdate' => $this->proxyValue($request, 'proxy_birthdate'),
            'proxy_gender' => $this->proxyValue($request, 'proxy_gender'),
            'proxy_occupation' => $this->proxyValue($request, 'proxy_occupation'),
            'proxy_monthlyincome' => $this->proxyValue($request, 'proxy_monthlyincome'),
            'created_at' => now(),
        ];

        if ($data['client_id'] === 0 || $data['municipality_id'] === 0) {
            return response()->json(['success' => false, 'message' => 'Missing client or municipality.']);
        }

        // Display-only proxy name for the success message (v1 formats but does not store it).
        $proxyNameDisplay = trim("{$data['proxy_lastname']}, {$data['proxy_firstname']} {$data['proxy_middlename']}");
        $proxyNameDisplay = (string) preg_replace('/\s+/', ' ', $proxyNameDisplay);

        $result = $this->unpaidService->create($data);

        if (! $result['success']) {
            return response()->json(['success' => false, 'message' => $result['message']]);
        }

        $message = $data['is_proxy']
            ? "PROXY INFORMATION FOR {$proxyNameDisplay} RECORDED SUCCESSFULLY. YOU MAY NOW CLOSE THIS WINDOW."
            : 'ATTENDANCE CONFIRMED SUCCESSFULLY. YOU MAY NOW CLOSE THIS WINDOW.';

        return response()->json(['success' => true, 'message' => $message]);
    }

    private function proxyValue(Request $request, string $key): ?string
    {
        $value = strtoupper(trim((string) $request->input($key)));

        return $value === '' ? null : $value;
    }

    public function data(Request $request): JsonResponse
    {
        if ($request->filled('single_id')) {
            $row = $this->buildFeedQuery($request)
                ->where('uv.id', (int) $request->input('single_id'))
                ->first();

            return response()->json(['single' => $row]);
        }

        if ($request->filled('delete_id')) {
            DB::table('tbl_unpaid_verifications')
                ->where('id', (int) $request->input('delete_id'))
                ->delete();

            return response()->json(['success' => true]);
        }

        return response()->json($this->rowsForDatatable($request));
    }

    public function export(Request $request)
    {
        $rows = $this->buildFeedQuery($request)
            ->orderByDesc('uv.id')
            ->get();

        $fileName = 'unpaid_verifications_'.date('Y-m-d_H-i-s');

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($out, [
                'ID', 'Client Name', 'Municipality', 'Is Proxy?', 'Proxy Name',
                'Relationship', 'Phone', 'Birthdate', 'Gender', 'Occupation',
                'Monthly Income', 'Submitted At',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->id,
                    $row->client_name,
                    $row->municipality_name,
                    $row->is_proxy_label,
                    $row->proxy_fullname,
                    $row->proxy_relationship,
                    $row->proxy_phone,
                    $row->proxy_birthdate,
                    $row->proxy_gender,
                    $row->proxy_occupation,
                    $row->proxy_monthlyincome,
                    $row->created_at,
                ]);
            }

            fclose($out);
        }, $fileName.'.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    private function rowsForDatatable(Request $request): array
    {
        $draw = $request->integer('draw', 1);
        $start = $request->integer('start', 0);
        $length = $request->integer('length', 25);

        $query = $this->buildFeedQuery($request);

        $recordsTotal = DB::table('tbl_unpaid_verifications')->count();
        $recordsFiltered = (clone $query)->count();

        $rows = (clone $query)
            ->orderByDesc('uv.id')
            ->limit($length)
            ->offset($start)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        return [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ];
    }

    private function buildFeedQuery(Request $request)
    {
        $municipality = trim((string) $request->input('municipality', ''));
        $dateStart = trim((string) $request->input('date_start', ''));
        $dateEnd = trim((string) $request->input('date_end', ''));
        $search = trim((string) $request->input('search.value', ''));

        $query = DB::table('tbl_unpaid_verifications as uv')
            ->leftJoin('tbl_clients as c', 'uv.client_id', '=', 'c.id')
            ->leftJoin('tbl_municipalities as m', 'uv.municipality_id', '=', 'm.id');

        if ($municipality !== '') {
            $query->where('uv.municipality_id', $municipality);
        }

        if ($dateStart !== '' && $dateEnd !== '') {
            $query->whereBetween(DB::raw('DATE(uv.created_at)'), [$dateStart, $dateEnd]);
        } elseif ($dateStart !== '') {
            $query->whereDate('uv.created_at', '>=', $dateStart);
        } elseif ($dateEnd !== '') {
            $query->whereDate('uv.created_at', '<=', $dateEnd);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('c.lastname', 'like', $like)
                    ->orWhere('c.firstname', 'like', $like)
                    ->orWhere('c.middlename', 'like', $like)
                    ->orWhereRaw('CONCAT(c.lastname, ", ", c.firstname, " ", c.middlename) LIKE ?', [$like])
                    ->orWhereRaw('CONCAT(c.lastname, c.firstname, c.middlename) LIKE ?', [$like])
                    ->orWhere('uv.proxy_lastname', 'like', $like)
                    ->orWhere('uv.proxy_firstname', 'like', $like)
                    ->orWhere('uv.proxy_middlename', 'like', $like)
                    ->orWhere('uv.proxy_phone', 'like', $like);
            });
        }

        return $query->select([
            'uv.id',
            DB::raw("UPPER(CONCAT(c.lastname, ', ', c.firstname, ' ', c.middlename)) AS client_name"),
            'm.name as municipality_name',
            DB::raw("CASE WHEN uv.is_proxy = 1 THEN 'YES' ELSE 'NO' END AS is_proxy_label"),
            DB::raw("TRIM(CONCAT(UPPER(uv.proxy_lastname), ', ', UPPER(uv.proxy_firstname), ' ', UPPER(uv.proxy_middlename))) AS proxy_fullname"),
            'uv.proxy_relationship',
            'uv.proxy_phone',
            'uv.proxy_birthdate',
            'uv.proxy_gender',
            'uv.proxy_occupation',
            'uv.proxy_monthlyincome',
            'uv.created_at',
        ]);
    }
}
