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

    /**
     * P4.5 scanner-engine hub. Not gated by a single page middleware (no
     * universal "scanners" page key exists); instead it lists only the
     * scanner pages the signed-in user may already open, each re-checked
     * with the same canAccessPage call the sidebar loop performs.
     */
    public function index(Request $request): View
    {
        $acl = app(AccessControlService::class);

        $scanners = collect(config('scanner.scanners'))
            ->filter(fn (array $config) => $acl->canAccessPage($request->user(), $config['page']))
            ->map(function (array $config, string $key) {
                return [
                    'key' => $key,
                    'title' => $config['title'] ?? $config['key'] ?? $key,
                    'url' => route('scanners.'.$key),
                    'programs' => $this->normalizePrograms($config['programs'] ?? []),
                ];
            })
            ->values()
            ->all();

        return view('scanners.index', ['scanners' => $scanners]);
    }

    /**
     * Flattens a programs list that config may store either as a bare list
     * (['CEAP']) or as an assoc map (['CEAP' => [...template...]]) into the
     * plain program-name list the hub cards need.
     *
     * @return list<string>
     */
    private function normalizePrograms(array $programs): array
    {
        $names = [];
        foreach ($programs as $key => $value) {
            $names[] = is_int($key) ? $value : $key;
        }

        return $names;
    }

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
