<?php

namespace App\Http\Controllers;

use App\Services\GranteeUpdateService;
use DateTime;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * P6 grantee self-update flow (v1 disabled_update_grantee.php +
 * save_grantee_update.php) and the update-logs viewer (v1 update_logs.php).
 *
 * The self-update form and save endpoint are public (no session check in v1 —
 * grantees use them at the payout venue), so they are top-level routes. The
 * logs screen is gated behind the v1 page key `update_logs.php`.
 *
 * logs() ports update_logs.php verbatim: DATE(created_at) filter clause, the
 * LEFT JOINs for the town, the comma-vs-space full-name formatting, the UTC →
 * Asia/Manila conversion formatted as 'm/d/Y - h:i A', and the server-side
 * rendered rows consumed by a client-side DataTables table. fetch_update_logs.php
 * is not ported — v1 never references it (update_logs.php renders its own rows).
 */
class GranteeUpdateController extends Controller
{
    private DateTimeZone $dbTimezone;

    private DateTimeZone $displayTimezone;

    public function __construct(private readonly GranteeUpdateService $granteeUpdateService)
    {
        $this->dbTimezone = new DateTimeZone('UTC');
        $this->displayTimezone = new DateTimeZone('Asia/Manila');
    }

    public function selfService(): View
    {
        return view('grantee_update.self-service');
    }

    public function store(Request $request): JsonResponse
    {
        $result = $this->granteeUpdateService->update($request->all(), (string) $request->ip());

        return response()->json($result);
    }

    public function logs(Request $request): View
    {
        $startDate = trim((string) $request->input('start_date', ''));
        $endDate = trim((string) $request->input('end_date', ''));

        $query = DB::table('tbl_update_logs as l')
            ->leftJoin('tbl_clients as c', 'l.client_id', '=', 'c.id')
            ->leftJoin('tbl_municipalities as m', 'c.city_municipality', '=', 'm.id');

        if ($startDate !== '' && $endDate !== '') {
            $query->whereBetween(DB::raw('DATE(l.created_at)'), [$startDate, $endDate]);
        } elseif ($startDate !== '') {
            $query->where(DB::raw('DATE(l.created_at)'), '>=', $startDate);
        } elseif ($endDate !== '') {
            $query->where(DB::raw('DATE(l.created_at)'), '<=', $endDate);
        }

        $logs = $query
            ->orderByDesc('l.created_at')
            ->get(['l.*', 'm.name as town']);

        $rows = $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'client_id' => $log->client_id,
                'full_name' => $this->formatName((string) $log->full_name),
                'town' => strtoupper(trim((string) ($log->town ?? 'N/A'))),
                'ip_address' => $log->ip_address,
                'action' => $log->action,
                'date_time' => $this->formatDisplayTime($log->created_at),
            ];
        });

        return view('update_logs.index', [
            'logs' => $rows,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    /**
     * v1's full-name formatting: comma form collapses whitespace + uppercases;
     * space form treats the last token as the lastname ("LASTNAME, REST").
     */
    private function formatName(string $rawName): string
    {
        $rawName = trim($rawName);

        if ($rawName === '') {
            return '';
        }

        if (str_contains($rawName, ',')) {
            return strtoupper((string) preg_replace('/\s+/', ' ', $rawName));
        }

        $parts = preg_split('/\s+/', $rawName);

        if (count($parts) === 1) {
            return strtoupper($parts[0]);
        }

        $lastname = array_pop($parts);
        $rest = implode(' ', $parts);

        return strtoupper(trim($lastname.', '.$rest));
    }

    private function formatDisplayTime(mixed $createdAt): string
    {
        if ($createdAt === null || $createdAt === '') {
            return '';
        }

        try {
            $dt = new DateTime((string) $createdAt, $this->dbTimezone);
            $dt->setTimezone($this->displayTimezone);

            return $dt->format('m/d/Y - h:i A');
        } catch (\Exception $e) {
            return (string) $createdAt;
        }
    }
}
