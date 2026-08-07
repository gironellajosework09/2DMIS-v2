<?php

namespace App\Http\Controllers;

use App\Services\AccessControlService;
use App\Services\ScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * P4 scanner engine pages. Thin controller — all behavior lives in ScanService
 * driven by config/scanner.php. The 14 GET pages are individually gated with
 * the v1 page key (page:scanner_*.php) via the route middleware.
 */
class ScannerController extends Controller
{
    public function __construct(private readonly ScanService $scanner) {}

    public function show(string $key): View
    {
        $config = $this->scanner->config($key);

        abort_unless(! empty($config), 404);

        $scannerJs = [
            'key' => $key,
            'mode' => $config['mode'] ?? null,
            'lookupUrl' => route('scanners.'.$key.'.lookup'),
            'saveUrl' => route('scanners.'.$key.'.save'),
            'resume' => (bool) ($config['ui']['resume'] ?? false),
            'attendance' => isset($config['attendance']),
            'generic' => ($config['mode'] ?? null) === 'generic_form',
            'fields' => $config['ui']['fields'] ?? [],
            'successMessage' => $config['ui']['success_message'] ?? 'Transaction saved successfully!',
            'scanSuccessSound' => (bool) ($config['ui']['scan_success_sound'] ?? false),
        ];

        return view('scanners.scan', [
            'config' => $config,
            'key' => $key,
            'scannerJs' => $scannerJs,
        ]);
    }

    public function lookup(Request $request, string $key): JsonResponse
    {
        $this->requireAccess($key, $request->user());

        $scanned = trim((string) $request->input('scanned', ''));
        $action = (string) $request->input('action', 'lookup');

        if ($scanned === '') {
            return response()->json(['success' => false, 'message' => 'Scanned code is required.']);
        }

        return response()->json($this->scanner->lookup($key, $scanned, $action));
    }

    public function save(Request $request, string $key): JsonResponse
    {
        $this->requireAccess($key, $request->user());

        return response()->json($this->scanner->save($key, $request->all(), $request->user()));
    }

    private function requireAccess(string $key, $user): void
    {
        $config = $this->scanner->config($key);

        if (empty($config)) {
            abort(404);
        }

        $allowed = app(AccessControlService::class)->canAccessPage($user, $config['page']);

        abort_unless($allowed, 403, 'Access denied.');
    }
}
