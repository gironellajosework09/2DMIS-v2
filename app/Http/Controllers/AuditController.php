<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * P7 audit viewer — v1 audit_logs.php + fetch_logs.php + fetch_leaderboard.php
 * port. Both feeds live inside the page:audit_logs.php route group (v1's
 * fetch_leaderboard.php had no session check at all; v2 closes that gap).
 *
 * The feed preserves the v1 contract exactly: the business-table whitelist,
 * per-table display-name resolution, UTC -> Asia/Manila rendering, LIMIT 10000,
 * and the distinct users/actions filter arrays. The four P7 subject tables are
 * added so MANAGE_* rows are readable, with the subject username resolved from
 * tbl_users.target_id (ADMIN_ANALYSIS Pass 6 §9.4).
 */
class AuditController extends Controller
{
    /**
     * Viewer tables: the v1 feed whitelist plus the four P7 subject tables.
     *
     * @var array<string, string>
     */
    public const TABLES = [
        'tbl_clients' => 'Clients',
        'tbl_transactions' => 'Transactions',
        'tbl_cedssg' => 'Cedssg',
        'tbl_users' => 'Users',
        'tbl_permissions' => 'Page Permissions',
        'tbl_program_permissions' => 'Program Permissions',
        'tbl_multi_device_exemptions' => 'Multi-Device Exemptions',
    ];

    /**
     * P7 tables whose target_id is a subject user id (resolved to a username).
     *
     * @var list<string>
     */
    private const SUBJECT_TABLES = [
        'tbl_users',
        'tbl_permissions',
        'tbl_program_permissions',
        'tbl_multi_device_exemptions',
    ];

    public function index(Request $request): View
    {
        $targetTable = $request->query('table');
        $targetTable = isset(self::TABLES[$targetTable]) ? $targetTable : 'tbl_clients';

        return view('admin.audit_logs.index', [
            'tables' => self::TABLES,
            'targetTable' => $targetTable,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $targetTable = $request->input('table');
        $targetTable = isset(self::TABLES[$targetTable]) ? $targetTable : 'tbl_clients';

        $logs = $this->feedQuery($targetTable)->get();

        $data = $logs->map(function ($log) {
            $date = Carbon::parse($log->created_at, 'UTC')->setTimezone('Asia/Manila');

            return [
                'username' => $log->username,
                'action' => $log->action,
                'target' => $log->target_name,
                'date' => $date->format('m/d/Y - h:i A'),
                'date_raw' => $date->format('Y-m-d H:i:s'),
            ];
        })->values()->all();

        $users = DB::table('tbl_audit_logs as al')
            ->join('tbl_users as u', 'al.user_id', '=', 'u.id')
            ->where('al.target_table', $targetTable)
            ->distinct()
            ->orderBy('u.username')
            ->pluck('u.username')
            ->all();

        $actions = DB::table('tbl_audit_logs')
            ->where('target_table', $targetTable)
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->all();

        return response()->json([
            'data' => $data,
            'users' => $users,
            'actions' => $actions,
        ]);
    }

    public function leaderboard(Request $request): JsonResponse
    {
        $targetTable = $request->input('table');
        $targetTable = isset(self::TABLES[$targetTable]) ? $targetTable : 'tbl_clients';

        $rows = DB::table('tbl_audit_logs as l')
            ->select('u.username', DB::raw('COUNT(*) AS total_actions'))
            ->join('tbl_users as u', 'l.user_id', '=', 'u.id')
            ->where('l.target_table', $targetTable)
            ->groupBy('u.username')
            ->orderByDesc('total_actions')
            ->get();

        return response()->json($rows);
    }

    private function feedQuery(string $targetTable)
    {
        $base = DB::table('tbl_audit_logs as al')
            ->select('al.action', 'al.created_at', 'u.username')
            ->join('tbl_users as u', 'al.user_id', '=', 'u.id')
            ->where('al.target_table', $targetTable)
            ->orderByDesc('al.created_at')
            ->limit(10000);

        if ($targetTable === 'tbl_clients') {
            return $base->addSelect(DB::raw(
                "CONCAT(c.lastname, ', ', c.firstname, ' ', COALESCE(c.middlename, '')) AS target_name"
            ))->leftJoin('tbl_clients as c', 'al.target_id', '=', 'c.id');
        }

        if ($targetTable === 'tbl_transactions') {
            return $base->addSelect(DB::raw(
                "CONCAT(COALESCE(t.patient_name, ''), ' - ', COALESCE(t.program, 'No Program')) AS target_name"
            ))->leftJoin('tbl_transactions as t', 'al.target_id', '=', 't.id');
        }

        if (in_array($targetTable, self::SUBJECT_TABLES, true)) {
            return $base->addSelect('subject.username AS target_name')
                ->leftJoin('tbl_users as subject', 'al.target_id', '=', 'subject.id');
        }

        return $base->addSelect('al.target_id AS target_name');
    }
}
