<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * P6 scholarship reports (v1 scholarship_reports.php + fetch_scholarship_reports.php
 * + export_scholarship_reports.php).
 *
 * The v1 query shapes are preserved exactly and asymmetrically:
 *
 * - Feed: transactions-led (six scholar programs) INNER JOIN clients, LEFT JOIN
 *   the MAX(id) scholar_info row per client, LEFT JOIN geo. recordsTotal is the
 *   raw six-program transaction count; recordsFiltered is the filtered count.
 *   The `submitted` filter is accepted but ignored — v1's feed never reads it.
 * - Export: scholar_info-led INNER JOIN clients, with gwa/units/remarks/status/
 *   date_applied pulled via correlated subqueries on the latest matching
 *   transaction, program/date/submitted filters via EXISTS subqueries. Streamed
 *   CSV with the UTF-8 BOM (P3/P5 convention).
 */
class ReportController extends Controller
{
    /**
     * The six scholar programs — identical to the P4 scanner keys and the
     * tbl_transactions.program enum strings.
     */
    private const SCHOLAR_PROGRAMS = ['CEAP', 'CEAP_NEW', 'CEDSSG', 'CEDSSG_NEW', 'OTEA', 'OTCES'];

    private const SIX_PROGRAMS_SQL = "('CEAP','CEAP_NEW','CEDSSG','CEDSSG_NEW','OTEA','OTCES')";

    /**
     * Orderable columns for the feed, kept identical to v1 fetch_scholarship_reports.php.
     *
     * @var list<string>
     */
    private const COLUMNS = [
        'tx.program',
        'full_name',
        'c.mobile_no',
        'c.sex',
        'c.birthdate',
        'c.civil_status',
        'municipality',
        'barangay',
        'si.school',
        'si.course',
        'si.year_level',
        'tx.gwa',
        'tx.units',
        'si.landbank_no',
        'tx.remarks',
        'tx.date_applied',
        'regular',
        'submitted',
    ];

    public function scholarship(): View
    {
        $municipalities = DB::table('tbl_municipalities')->orderBy('name')->get(['id', 'name']);

        return view('scholarship_reports.index', [
            'municipalities' => $municipalities,
            'programs' => self::SCHOLAR_PROGRAMS,
        ]);
    }

    public function scholarshipData(Request $request): JsonResponse
    {
        $draw = (int) $request->input('draw', 0);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $orderIndex = (int) $request->input('order.0.column', 1);
        $orderDir = $request->input('order.0.dir', 'asc') === 'desc' ? 'DESC' : 'ASC';
        $orderColumn = self::COLUMNS[$orderIndex] ?? 'tx.date_applied';

        $recordsTotal = DB::table('tbl_transactions')
            ->whereIn('program', self::SCHOLAR_PROGRAMS)
            ->count();

        $query = $this->buildFeedQuery($request);

        $recordsFiltered = (clone $query)->count();

        $rows = (clone $query)
            ->selectRaw($this->feedColumns())
            ->orderByRaw($orderColumn.' '.$orderDir)
            ->limit($length)
            ->offset($start)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ]);
    }

    public function scholarshipExport(Request $request)
    {
        $program = trim((string) $request->query('program'));
        $programProvided = $program !== '';

        $query = DB::table('tbl_scholar_info as si')
            ->join('tbl_clients as c', 'si.client_id', '=', 'c.id')
            ->leftJoin('tbl_municipalities as m', 'c.city_municipality', '=', 'm.id')
            ->leftJoin('tbl_barangays as b', 'c.barangay', '=', 'b.id');

        $this->applyExportFilters($query, $request, $program, $programProvided);

        $query->selectRaw($this->exportColumns($programProvided), $this->exportBindings($program, $programProvided))
            ->orderByRaw('full_name');

        $rows = $query->get();

        $fileName = 'scholarship_reports'.date('Ymd');

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

    private function buildFeedQuery(Request $request)
    {
        $municipality = trim((string) $request->input('municipality'));
        $barangay = trim((string) $request->input('barangay'));
        $program = trim((string) $request->input('program'));
        $dateFrom = trim((string) $request->input('date_from'));
        $dateTo = trim((string) $request->input('date_to'));
        $search = trim((string) $request->input('search.value'));

        $query = DB::table('tbl_transactions as tx')
            ->join('tbl_clients as c', 'tx.client_id', '=', 'c.id')
            ->leftJoin('tbl_scholar_info as si', function ($join) {
                $join->on('si.client_id', '=', 'c.id')
                    ->whereRaw('si.id = (SELECT MAX(id) FROM tbl_scholar_info WHERE client_id = c.id)');
            })
            ->leftJoin('tbl_municipalities as m', 'c.city_municipality', '=', 'm.id')
            ->leftJoin('tbl_barangays as b', 'c.barangay', '=', 'b.id')
            ->whereIn('tx.program', self::SCHOLAR_PROGRAMS);

        if ($municipality !== '') {
            $query->where('c.city_municipality', $municipality);
        }

        if ($barangay !== '') {
            $query->where('c.barangay', $barangay);
        }

        if ($program !== '') {
            $query->where('tx.program', $program);
        }

        if ($dateFrom !== '') {
            $query->whereRaw('DATE(tx.date_applied) >= ?', [$dateFrom]);
        }

        if ($dateTo !== '') {
            $query->whereRaw('DATE(tx.date_applied) <= ?', [$dateTo]);
        }

        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(function ($q) use ($like) {
                $q->whereRaw('CONCAT(c.lastname, ", ", c.firstname, " ", c.middlename) LIKE ?', [$like])
                    ->orWhere('si.school', 'like', $like)
                    ->orWhere('si.course', 'like', $like)
                    ->orWhere('c.mobile_no', 'like', $like);
            });
        }

        return $query;
    }

    private function feedColumns(): string
    {
        return 'tx.program, '
            ."CONCAT(c.lastname, ', ', c.firstname, ' ', c.middlename) AS full_name, "
            .'c.mobile_no, c.sex, DATE_FORMAT(c.birthdate, "%m/%d/%Y") AS birthdate, c.civil_status, '
            .'m.name AS municipality, b.name AS barangay, si.school, si.course, si.year_level, '
            .'tx.gwa, tx.units, si.landbank_no, tx.remarks, DATE(tx.date_applied) AS date_applied, '
            ."CASE WHEN si.is_regular = 1 THEN 'Yes' ELSE 'No' END AS regular, 'Yes' AS submitted";
    }

    private function applyExportFilters($query, Request $request, string $program, bool $programProvided): void
    {
        $municipality = trim((string) $request->query('municipality'));
        $barangay = trim((string) $request->query('barangay'));
        $dateFrom = trim((string) $request->query('date_from'));
        $dateTo = trim((string) $request->query('date_to'));
        $submitted = trim((string) $request->query('submitted'));

        if ($municipality !== '') {
            $query->where('c.city_municipality', $municipality);
        }

        if ($barangay !== '') {
            $query->where('c.barangay', $barangay);
        }

        if ($programProvided) {
            $query->whereRaw('EXISTS (
                SELECT 1 FROM tbl_transactions tx
                WHERE tx.client_id = si.client_id AND tx.program = ?
            )', [$program]);
        }

        if ($dateFrom !== '') {
            $query->whereRaw(
                $programProvided
                    ? 'EXISTS (SELECT 1 FROM tbl_transactions tx WHERE tx.client_id = si.client_id AND tx.program = ? AND DATE(tx.date_applied) >= ?)'
                    : 'EXISTS (SELECT 1 FROM tbl_transactions tx WHERE tx.client_id = si.client_id AND tx.program IN '.self::SIX_PROGRAMS_SQL.' AND DATE(tx.date_applied) >= ?)',
                $programProvided ? [$program, $dateFrom] : [$dateFrom]
            );
        }

        if ($dateTo !== '') {
            $query->whereRaw(
                $programProvided
                    ? 'EXISTS (SELECT 1 FROM tbl_transactions tx WHERE tx.client_id = si.client_id AND tx.program = ? AND DATE(tx.date_applied) <= ?)'
                    : 'EXISTS (SELECT 1 FROM tbl_transactions tx WHERE tx.client_id = si.client_id AND tx.program IN '.self::SIX_PROGRAMS_SQL.' AND DATE(tx.date_applied) <= ?)',
                $programProvided ? [$program, $dateTo] : [$dateTo]
            );
        }

        if ($submitted === 'Yes') {
            $query->whereRaw('EXISTS (
                SELECT 1 FROM tbl_transactions tx2
                WHERE tx2.client_id = si.client_id AND tx2.program IN '.self::SIX_PROGRAMS_SQL.'
            )');
        } elseif ($submitted === 'No') {
            $query->whereRaw('NOT EXISTS (
                SELECT 1 FROM tbl_transactions tx2
                WHERE tx2.client_id = si.client_id AND tx2.program IN '.self::SIX_PROGRAMS_SQL.'
            )');
        }
    }

    private function exportColumns(bool $programProvided): string
    {
        $txCondition = $programProvided ? 'tx.program = ?' : 'tx.program IN '.self::SIX_PROGRAMS_SQL;

        return 'si.program, c.lastname, c.firstname, c.middlename, c.extensionname, '
            ."CONCAT(c.lastname, ', ', c.firstname, ' ', c.middlename, "
            ."CASE WHEN c.extensionname IS NOT NULL AND c.extensionname != '' THEN CONCAT(' ', c.extensionname) ELSE '' END) AS full_name, "
            .'c.mobile_no, c.sex, DATE_FORMAT(c.birthdate, "%m/%d/%Y") AS birthdate, c.civil_status, '
            .'m.name AS municipality, b.name AS barangay, si.school, si.course, si.year_level, '
            ."(SELECT tx.gwa FROM tbl_transactions tx WHERE tx.client_id = si.client_id AND $txCondition ORDER BY tx.date_applied DESC, tx.id DESC LIMIT 1) AS gwa, "
            ."(SELECT tx.units FROM tbl_transactions tx WHERE tx.client_id = si.client_id AND $txCondition ORDER BY tx.date_applied DESC, tx.id DESC LIMIT 1) AS units, "
            .'si.landbank_no, '
            ."(SELECT tx.remarks FROM tbl_transactions tx WHERE tx.client_id = si.client_id AND $txCondition ORDER BY tx.date_applied DESC, tx.id DESC LIMIT 1) AS remarks, "
            ."(SELECT tx.status FROM tbl_transactions tx WHERE tx.client_id = si.client_id AND $txCondition ORDER BY tx.date_applied DESC, tx.id DESC LIMIT 1) AS status, "
            ."(SELECT DATE_FORMAT(tx.date_applied, '%Y-%m-%d') FROM tbl_transactions tx WHERE tx.client_id = si.client_id AND $txCondition ORDER BY tx.date_applied DESC, tx.id DESC LIMIT 1) AS date_applied, "
            ."CASE WHEN si.is_regular = 1 THEN 'Yes' ELSE 'No' END AS regular, "
            .'CASE WHEN EXISTS (SELECT 1 FROM tbl_transactions tx2 WHERE tx2.client_id = si.client_id AND tx2.program IN '.self::SIX_PROGRAMS_SQL.") THEN 'Yes' ELSE 'No' END AS submitted";
    }

    /**
     * One parameter per correlated subquery (gwa, units, remarks, status,
     * date_applied) when the program filter pins tx.program to a literal.
     *
     * @return list<string>
     */
    private function exportBindings(string $program, bool $programProvided): array
    {
        return $programProvided ? array_fill(0, 5, $program) : [];
    }
}
