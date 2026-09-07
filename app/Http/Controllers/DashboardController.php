<?php

namespace App\Http\Controllers;

use App\Services\AccessControlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AccessControlService $acl,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        // ── KPI queries ──────────────────────────────────────────────
        // All operational counts respect municipality scope via
        // applyMunicipalityScope(). Program permissions are enforced on
        // transaction-related KPIs.

        $totalClients = $this->scopedQuery('tbl_clients', 'c.city_municipality', 'clients.php', $user)
            ->count();

        $totalTransactions = $this->scopedTransactionQuery($user)
            ->count();

        $disbursedAmount = $this->scopedTransactionQuery($user)
            ->where('t.status', 'PAID')
            ->sum('t.amount_paid');

        $pendingCount = $this->scopedTransactionQuery($user)
            ->where('t.status', 'PENDING PAYOUT')
            ->count();

        // ── Program distribution ─────────────────────────────────────
        // GROUP BY program, ordered by count descending. All programs
        // visible to the user (respecting scope + permissions).
        $programDistribution = $this->scopedTransactionQuery($user)
            ->select('t.program', DB::raw('COUNT(*) as count'))
            ->groupBy('t.program')
            ->orderByDesc('count')
            ->get();

        // ── Activity feed ────────────────────────────────────────────
        // Most recent audit log entries. Gated by audit_logs.php page
        // permission. Joins tbl_users for display names.
        $activityFeed = collect();
        if ($this->acl->canAccessPage($user, 'audit_logs.php')) {
            $activityFeed = DB::table('tbl_audit_logs as al')
                ->join('tbl_users as u', 'al.user_id', '=', 'u.id')
                ->select([
                    'u.username',
                    'al.action',
                    'al.target_table',
                    'al.target_id',
                    'al.created_at',
                ])
                ->orderByDesc('al.created_at')
                ->limit(8)
                ->get();
        }

        return view('dashboard', [
            'totalClients' => $totalClients,
            'totalTransactions' => $totalTransactions,
            'disbursedAmount' => $disbursedAmount,
            'pendingCount' => $pendingCount,
            'programDistribution' => $programDistribution,
            'activityFeed' => $activityFeed,
        ]);
    }

    /**
     * Base scoped query for any table with a municipality column.
     */
    private function scopedQuery(string $table, string $column, string $page, $user)
    {
        $query = DB::table($table);
        $this->acl->applyMunicipalityScope($query, $user, $column, $page);

        return $query;
    }

    /**
     * Scoped transaction query with client join for municipality scope
     * and program permission enforcement.
     */
    private function scopedTransactionQuery($user)
    {
        $query = DB::table('tbl_transactions as t')
            ->leftJoin('tbl_clients as c', 't.client_id', '=', 'c.id');

        $this->acl->applyMunicipalityScope($query, $user, 'c.city_municipality', 'all_transactions.php');

        $allowedPrograms = $this->acl->permittedPrograms($user);
        if (! empty($allowedPrograms)) {
            $query->whereIn('t.program', $allowedPrograms);
        }

        return $query;
    }
}
