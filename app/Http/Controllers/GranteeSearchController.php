<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * P5 grantee search endpoints (v1 search_grantee.php + search_unpaid_grantee.php).
 *
 * Both files are identical except for the allowed program list and whether the
 * client must have a 'PENDING PAYOUT' transaction, so one controller handles
 * both kinds. Parity notes:
 *
 * - No session check in v1 (search_unpaid_grantee.php is consumed by the public
 *   disabled_unpaid.php self-service form) — these stay public in v2.
 * - GET ?munis=1 lists municipalities; GET ?q= autocompletes (name LIKE on
 *   full_name / match_name, LIMIT 15); POST action=verify validates that the
 *   client exists, their municipality matches, and they have a qualifying
 *   transaction, then returns client + latest program + scholar_info.
 * - city_municipality is compared as an int even though the column is a
 *   varchar in the legacy schema (v1 intval() semantics).
 */
class GranteeSearchController extends Controller
{
    private const KINDS = [
        'grantee' => [
            'programs' => ['CEAP', 'CEAP_NEW', 'CEDSSG', 'CEDSSG_NEW', 'OTEA', 'OTCES'],
            'status_filter' => false,
        ],
        'unpaid' => [
            'programs' => ['CEAP', 'CEAP_NEW', 'OTEA', 'OTCES'],
            'status_filter' => true,
        ],
    ];

    private const CLIENT_COLUMNS = [
        'id', 'lastname', 'firstname', 'middlename', 'extensionname',
        'city_municipality', 'barangay', 'house_no', 'mobile_no', 'email',
        'birthdate', 'age', 'sex', 'civil_status', 'pwd', 'occupation',
    ];

    public function search(Request $request, string $kind): JsonResponse
    {
        $kindConfig = $this->kindConfig($kind);

        if ($request->has('munis')) {
            $municipalities = DB::table('tbl_municipalities')
                ->orderBy('name')
                ->get(['id', 'name']);

            return response()->json(['success' => true, 'municipalities' => $municipalities]);
        }

        $q = trim((string) $request->input('q', ''));

        if ($q === '') {
            return response()->json(['success' => true, 'results' => []]);
        }

        $query = DB::table('tbl_clients as c')
            ->join('tbl_transactions as t', 't.client_id', '=', 'c.id')
            ->whereIn('t.program', $kindConfig['programs'])
            ->where(function ($q2) use ($q) {
                $q2->where('c.full_name', 'like', "%{$q}%")
                    ->orWhere('c.match_name', 'like', "%{$q}%");
            });

        if ($kindConfig['status_filter']) {
            $query->where('t.status', 'PENDING PAYOUT');
        }

        $rows = $query
            ->distinct()
            ->select('c.id', 'c.full_name', 'c.city_municipality')
            ->orderBy('c.full_name')
            ->limit(15)
            ->get();

        $municipalities = DB::table('tbl_municipalities')
            ->get(['id', 'name'])
            ->pluck('name', 'id');

        $results = $rows->map(function ($row) use ($municipalities) {
            return [
                'id' => (int) $row->id,
                'full_name' => $row->full_name,
                'municipality' => $municipalities->get((int) $row->city_municipality, ''),
            ];
        });

        return response()->json(['success' => true, 'results' => $results]);
    }

    public function verify(Request $request, string $kind): JsonResponse
    {
        $kindConfig = $this->kindConfig($kind);

        if ($request->input('action') !== 'verify') {
            return response()->json(['success' => false, 'message' => 'Invalid action']);
        }

        $clientId = (int) $request->input('client_id', 0);
        $municipalityId = (int) $request->input('municipality_id', 0);

        if ($clientId === 0 || $municipalityId === 0) {
            return response()->json(['success' => false, 'message' => 'Missing required parameters']);
        }

        $client = DB::table('tbl_clients')->where('id', $clientId)->first();

        if ($client === null) {
            return response()->json(['success' => false, 'message' => 'Client not found']);
        }

        if ((int) $client->city_municipality !== $municipalityId) {
            return response()->json(['success' => false, 'message' => 'Municipality does not match our records.']);
        }

        $txQuery = DB::table('tbl_transactions')
            ->where('client_id', $clientId)
            ->whereIn('program', $kindConfig['programs']);

        if ($kindConfig['status_filter']) {
            $txQuery->where('status', 'PENDING PAYOUT');
        }

        $transaction = (clone $txQuery)
            ->orderByDesc('id')
            ->value('program');

        if ($transaction === null) {
            return response()->json(['success' => false, 'message' => 'No qualifying scholarship transaction found for this client.']);
        }

        $scholarship = DB::table('tbl_scholar_info')
            ->where('client_id', $clientId)
            ->where('program', $transaction)
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'success' => true,
            'client' => array_intersect_key((array) $client, array_flip(self::CLIENT_COLUMNS)),
            'program' => $transaction,
            'scholarship' => $scholarship,
        ]);
    }

    private function kindConfig(string $kind): array
    {
        abort_unless(array_key_exists($kind, self::KINDS), 404);

        return self::KINDS[$kind];
    }
}
