<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * P5 payout attendance list screens (v1 scanned_payouts*.php).
 *
 * One controller + one shared view, driven by config('payout.attendance').
 * The three variants differ only in the backing table, seat table and title.
 * Every feed mirrors its v1 counterpart (fetch_scanned_payouts*.php): the
 * DELETE / single-record / DataTables modes, the municipality-program-date
 * filters, the client-name source (client concat vs t.patient_name) and the
 * batch seat lookup. Scans are written by the P4 scanner engine and never
 * audited here — v1 wrote no audit rows for these screens.
 */
class PayoutAttendanceController extends Controller
{
    public function index(Request $request, string $variant): View
    {
        $config = config('payout.attendance.'.$variant);

        abort_unless(! empty($config), 404);

        return view('payouts.attendance', [
            'variant' => $variant,
            'config' => $config,
            'municipalities' => DB::table('tbl_municipalities')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function data(Request $request, string $variant): JsonResponse
    {
        $config = config('payout.attendance.'.$variant);

        abort_unless(! empty($config), 404);

        if ($request->filled('delete_id')) {
            DB::table($config['table'])
                ->where('id', (int) $request->input('delete_id'))
                ->delete();

            return response()->json(['success' => true]);
        }

        if ($request->filled('single_id')) {
            return response()->json([
                'single' => $this->singleRecord($config, (int) $request->input('single_id')),
            ]);
        }

        return response()->json($this->rowsForDatatable($config, $request));
    }

    private function singleRecord(array $config, int $id): ?array
    {
        $clientName = $this->clientNameExpression($config);

        $scan = DB::selectOne("
            SELECT ps.*, t.program, {$clientName} AS client_name,
                   m.name AS municipality_name,
                   u.username AS scanned_by_name
            FROM {$config['table']} ps
            LEFT JOIN tbl_transactions t ON ps.transaction_id = t.id
            LEFT JOIN tbl_clients c ON t.client_id = c.id
            LEFT JOIN tbl_municipalities m ON c.city_municipality = m.id
            LEFT JOIN tbl_users u ON ps.scanned_by = u.id
            WHERE ps.id = ?
            LIMIT 1
        ", [$id]);

        if ($scan === null) {
            return null;
        }

        $record = (array) $scan;

        if (! empty($record['scanned_at'])) {
            $record['scanned_at'] = $this->formatScannedAt($record['scanned_at']);
        }

        if (! empty($config['seat_table']) && ! empty($record['program'])) {
            $seat = DB::table($config['seat_table'])
                ->where('program', $record['program'])
                ->where('name', $record['client_name'] ?? null)
                ->select(['section', 'box', 'row', 'seat'])
                ->first();

            if ($seat !== null) {
                $record = array_merge($record, (array) $seat);
            }
        }

        return $record;
    }

    private function rowsForDatatable(array $config, Request $request): array
    {
        $draw = $request->integer('draw', 1);
        $start = $request->integer('start', 0);
        $length = $request->integer('length', 25);
        $searchValue = trim((string) $request->input('search.value', ''));

        $municipality = trim((string) $request->input('municipality', ''));
        $program = trim((string) $request->input('program', ''));
        $scannedStart = trim((string) $request->input('scanned_start', ''));
        $scannedEnd = trim((string) $request->input('scanned_end', ''));

        $usesPatientName = $config['client_name'] === 'patient_name';

        $query = DB::table($config['table'].' as ps')
            ->join('tbl_transactions as t', 'ps.transaction_id', '=', 't.id')
            ->when(
                $usesPatientName,
                fn ($q) => $q->leftJoin('tbl_clients as c', 't.client_id', '=', 'c.id'),
                fn ($q) => $q->join('tbl_clients as c', 't.client_id', '=', 'c.id')
            )
            ->leftJoin('tbl_municipalities as m', 'c.city_municipality', '=', 'm.id')
            ->leftJoin('tbl_users as u', 'ps.scanned_by', '=', 'u.id');

        if ($municipality !== '') {
            $query->where('c.city_municipality', $municipality);
        }
        if ($program !== '') {
            $query->where('t.program', $program);
        }
        if ($scannedStart !== '') {
            $query->whereDate('ps.scanned_at', '>=', $scannedStart);
        }
        if ($scannedEnd !== '') {
            $query->whereDate('ps.scanned_at', '<=', $scannedEnd);
        }
        if ($searchValue !== '') {
            $like = '%'.$searchValue.'%';
            $query->where(function ($q) use ($like, $usesPatientName) {
                if ($usesPatientName) {
                    $q->where('t.patient_name', 'like', $like);
                } else {
                    $q->whereRaw('CONCAT(c.lastname, ", ", c.firstname, " ", c.middlename) LIKE ?', [$like]);
                }
                $q->orWhere('t.program', 'like', $like)
                    ->orWhere('u.username', 'like', $like);
            });
        }

        $recordsTotal = DB::table($config['table'])->count();
        $recordsFiltered = (clone $query)->count();

        $clientName = $this->clientNameExpression($config);

        $select = [
            'ps.id',
            'ps.transaction_id',
            't.program',
            DB::raw("{$clientName} AS client_name"),
            'm.name as municipality_name',
            'u.username as scanned_by_name',
            'ps.scanned_at',
        ];

        if ($usesPatientName) {
            $select[] = 'ps.scanned_text';
        }

        $rows = (clone $query)
            ->select($select)
            ->orderByDesc('ps.id')
            ->limit($length)
            ->offset($start)
            ->get();

        $this->attachSeats($rows, $config);

        $data = $rows->map(function ($row) use ($usesPatientName, $config) {
            $item = [
                'id' => (int) $row->id,
                'transaction_id' => (int) $row->transaction_id,
                'program' => e((string) $row->program),
                'client_name' => e((string) $row->client_name),
                'municipality_name' => e((string) $row->municipality_name),
                'scanned_by_name' => e((string) $row->scanned_by_name),
                'scanned_at' => $row->scanned_at ? $this->formatScannedAt($row->scanned_at) : '',
            ];

            if (! empty($config['seat_table'])) {
                $item['section'] = e((string) ($row->section ?? ''));
                $item['box'] = e((string) ($row->box ?? ''));
                $item['row'] = e((string) ($row->row ?? ''));
                $item['seat'] = e((string) ($row->seat ?? ''));
            }

            if ($usesPatientName) {
                $item['scanned_text'] = e((string) ($row->scanned_text ?? ''));
            }

            return $item;
        })->all();

        return [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ];
    }

    /**
     * v1 batch seat lookup: one query on the variant's seat table keyed by
     * client name, then mapped per (name, program) in PHP. Missing seats stay
     * null so the list shows empty cells.
     */
    private function attachSeats(Collection $rows, array $config): void
    {
        if (empty($config['seat_table']) || $rows->isEmpty()) {
            return;
        }

        $names = $rows->pluck('client_name')->filter()->unique()->values()->all();

        if (empty($names)) {
            return;
        }

        $seats = DB::table($config['seat_table'])
            ->whereIn('name', $names)
            ->get(['name', 'program', 'section', 'box', 'row', 'seat']);

        $seatMap = [];
        foreach ($seats as $seat) {
            $seatMap[$seat->name][$seat->program] = $seat;
        }

        foreach ($rows as $row) {
            $seat = $seatMap[$row->client_name][$row->program] ?? null;
            $row->section = $seat->section ?? null;
            $row->box = $seat->box ?? null;
            $row->row = $seat->row ?? null;
            $row->seat = $seat->seat ?? null;
        }
    }

    private function clientNameExpression(array $config): string
    {
        if ($config['client_name'] === 'patient_name') {
            return 't.patient_name';
        }

        return "CONCAT(c.lastname, ', ', c.firstname, CASE WHEN c.middlename IS NOT NULL AND c.middlename <> '' THEN CONCAT(' ', c.middlename) ELSE '' END)";
    }

    /**
     * v1 converts the stored scan timestamp from UTC to Asia/Manila and
     * renders it as `m/d/Y - h:i A` (DATE_FORMAT(CONVERT_TZ(...)) on the
     * feed, DateTime UTC->Manila on the single-record path — same result).
     */
    private function formatScannedAt(string $scannedAt): string
    {
        return Carbon::parse($scannedAt, 'UTC')->setTimezone('Asia/Manila')->format('m/d/Y - h:i A');
    }
}
