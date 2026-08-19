<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActionPermissionRequest;
use App\Http\Requests\ExemptionToggleRequest;
use App\Http\Requests\MunicipalityScopeRequest;
use App\Http\Requests\PagePermissionRequest;
use App\Http\Requests\ProgramPermissionRequest;
use App\Models\ActionPermission;
use App\Models\MultiDeviceExemption;
use App\Models\Municipality;
use App\Models\Permission;
use App\Models\ProgramPermission;
use App\Models\User;
use App\Models\UserMunicipality;
use App\Services\AccessControlService;
use App\Services\AuditService;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * P7 permission administration — v1 manage_permissions.php +
 * manage_program_permissions.php + manage_multi_device_exemptions.php port.
 *
 * Every save is a full-replace (DELETE + INSERT) exactly like v1. All writes go
 * through the existing models inside a transaction, and every state change is
 * audited via AuditService with the canonical MANAGE_* action strings
 * (ADMIN_ANALYSIS Pass 6). The '*' row is the only super-admin marker and is
 * managed exclusively through the confirmed `super_admin` toggle, never the
 * page list.
 */
class AdminPermissionController extends Controller
{
    public const P7_KEYS = [
        'register.php',
        'manage_permissions.php',
        'manage_program_permissions.php',
        'manage_multi_device_exemptions.php',
        'audit_logs.php',
    ];

    private const PAGE_LABELS = [
        'dashboard.php' => 'Dashboard',
        'clients.php' => 'Clients',
        'add_client.php' => 'Add Client',
        'view_client.php' => 'View Client',
        'edit_client.php' => 'Edit Client',
        'all_transactions.php' => 'All Transactions',
        'add_transaction.php' => 'Add Transaction',
        'edit_transaction.php' => 'Edit Transaction',
        'view_transaction.php' => 'View Transaction',
        'scholarship_reports.php' => 'Scholarship Reports',
        'register.php' => 'Create User',
        'audit_logs.php' => 'Audit Logs',
        'scholars.php' => 'Manage Scholars Info',
        'scanned_payouts.php' => 'Payout Attendance',
        'scanned_payouts2.php' => 'Payout Attendance 2',
        'scanned_payouts_unpaid.php' => 'Payout Attendance Unpaid',
        'unpaid_verifications.php' => 'Unpaid Grantees',
        'manage_permissions.php' => 'Manage Permissions',
        'manage_program_permissions.php' => 'Manage Program Permissions',
        'manage_multi_device_exemptions.php' => 'Manage Multiple Device Exemptions',
        'currently_logged_users.php' => 'Currently Logged Users',
        'force_logout.php' => 'Force Logout',
    ];

    public function pages(Request $request): View
    {
        $users = User::query()->orderBy('username')->get(['id', 'username']);

        $selectedUserId = $request->integer('user_id');
        $selectedUser = $users->firstWhere('id', $selectedUserId);

        return view('admin.permissions.pages', [
            'users' => $users,
            'selectedUser' => $selectedUser,
            'userPages' => $selectedUser?->permissions()->pluck('page_name')->all() ?? [],
            'catalog' => $this->pageCatalog(),
            'labels' => $this->pageLabels(),
            'isSuperAdmin' => $selectedUser !== null && $this->accessControl()->isSuperAdmin($selectedUser),
        ]);
    }

    public function updatePages(PagePermissionRequest $request, User $user): RedirectResponse
    {
        $oldPages = $user->permissions()->pluck('page_name')->all();
        $hadSuperAdmin = in_array(AccessControlService::SUPER_ADMIN_PAGE, $oldPages, true);

        $pages = collect($request->input('pages', []))
            ->filter(fn (string $page) => $page !== AccessControlService::SUPER_ADMIN_PAGE)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($request->boolean('super_admin')) {
            $pages[] = AccessControlService::SUPER_ADMIN_PAGE;
        }

        DB::transaction(function () use ($request, $user, $oldPages, $hadSuperAdmin, $pages) {
            Permission::query()->where('user_id', $user->id)->delete();

            foreach ($pages as $page) {
                Permission::query()->create([
                    'user_id' => $user->id,
                    'page_name' => $page,
                    'can_access' => true,
                ]);
            }

            $this->audit()->log(
                $request->user()->id,
                'MANAGE_PAGE_PERMISSIONS',
                'tbl_permissions',
                $user->id,
                json_encode(['username' => $user->username, 'pages' => $oldPages]),
                json_encode(['username' => $user->username, 'pages' => $pages])
            );

            $nowSuperAdmin = in_array(AccessControlService::SUPER_ADMIN_PAGE, $pages, true);

            if ($nowSuperAdmin && ! $hadSuperAdmin) {
                $this->audit()->log(
                    $request->user()->id,
                    'MANAGE_SUPER_ADMIN_GRANT',
                    'tbl_permissions',
                    $user->id,
                    json_encode(['username' => $user->username, 'super_admin' => false]),
                    json_encode(['username' => $user->username, 'super_admin' => true])
                );
            } elseif (! $nowSuperAdmin && $hadSuperAdmin) {
                $this->audit()->log(
                    $request->user()->id,
                    'MANAGE_SUPER_ADMIN_REVOKE',
                    'tbl_permissions',
                    $user->id,
                    json_encode(['username' => $user->username, 'super_admin' => true]),
                    json_encode(['username' => $user->username, 'super_admin' => false])
                );
            }
        });

        return back()->with('login_status', 'Permissions updated successfully!');
    }

    public function programs(Request $request): View
    {
        $users = User::query()->orderBy('username')->get(['id', 'username']);

        $selectedUserId = $request->integer('user_id');
        $selectedUser = $users->firstWhere('id', $selectedUserId);

        return view('admin.permissions.programs', [
            'users' => $users,
            'selectedUser' => $selectedUser,
            'userPrograms' => $selectedUser?->programPermissions()->pluck('program_name')->all() ?? [],
            'programs' => TransactionService::PROGRAMS,
        ]);
    }

    public function updatePrograms(ProgramPermissionRequest $request, User $user): RedirectResponse
    {
        $oldPrograms = $user->programPermissions()->pluck('program_name')->all();

        $programs = collect($request->input('programs', []))
            ->unique()
            ->sort()
            ->values()
            ->all();

        DB::transaction(function () use ($request, $user, $oldPrograms, $programs) {
            ProgramPermission::query()->where('user_id', $user->id)->delete();

            foreach ($programs as $program) {
                ProgramPermission::query()->create([
                    'user_id' => $user->id,
                    'program_name' => $program,
                ]);
            }

            $this->audit()->log(
                $request->user()->id,
                'MANAGE_PROGRAM_PERMISSIONS',
                'tbl_program_permissions',
                $user->id,
                json_encode(['username' => $user->username, 'programs' => $oldPrograms]),
                json_encode(['username' => $user->username, 'programs' => $programs])
            );
        });

        return back()->with('login_status', 'Program permissions updated successfully!');
    }

    /**
     * P12 action-permission screen. One grid row per adopted page (config
     * authorization.pages), one column per non-VIEW action in that page's
     * catalog. The selected user's granted (user, page, action) rows are
     * pre-checked; VIEW has no checkbox because the page row is the VIEW grant.
     */
    public function actions(Request $request): View
    {
        $users = User::query()->orderBy('username')->get(['id', 'username']);

        $selectedUserId = $request->integer('user_id');
        $selectedUser = $users->firstWhere('id', $selectedUserId);

        $pages = [];
        foreach (config('authorization.pages', []) as $pageName => $page) {
            $actions = array_values(array_filter(
                $page['actions'] ?? [],
                fn (string $action) => $action !== 'VIEW'
            ));

            if ($actions !== []) {
                $pages[$pageName] = [
                    'label' => $this->pageLabel($pageName),
                    'actions' => $actions,
                    'enforcement' => (bool) ($page['enforcement'] ?? false),
                ];
            }
        }

        $userActions = [];
        if ($selectedUser !== null) {
            foreach ($selectedUser->actionPermissions()->get(['page_name', 'action']) as $row) {
                $userActions[] = $row->page_name.':'.$row->action;
            }
        }

        return view('admin.permissions.actions', [
            'users' => $users,
            'selectedUser' => $selectedUser,
            'pages' => $pages,
            'userActions' => $userActions,
        ]);
    }

    public function updateActions(ActionPermissionRequest $request, User $user): RedirectResponse
    {
        $old = $user->actionPermissions()
            ->get(['page_name', 'action'])
            ->map(fn (ActionPermission $row) => $row->page_name.':'.$row->action)
            ->sort()
            ->values()
            ->all();

        $actions = collect($request->input('actions', []))
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($actions === $old) {
            return back()->with('login_status', 'No change — the action permissions were already in that state.');
        }

        DB::transaction(function () use ($request, $user, $old, $actions) {
            ActionPermission::query()->where('user_id', $user->id)->delete();

            foreach ($actions as $composite) {
                [$pageName, $action] = explode(':', $composite, 2);
                ActionPermission::query()->create([
                    'user_id' => $user->id,
                    'page_name' => $pageName,
                    'action' => $action,
                ]);
            }

            $this->audit()->log(
                $request->user()->id,
                'MANAGE_ACTION_PERMISSIONS',
                'tbl_action_permissions',
                $user->id,
                json_encode(['username' => $user->username, 'actions' => $old]),
                json_encode(['username' => $user->username, 'actions' => $actions])
            );
        });

        return back()->with('login_status', 'Action permissions updated successfully!');
    }

    /**
     * P12 municipality-scope screen. Real municipality rows are checkboxes;
     * "ALL" is a separate switch that writes the reserved 0 marker — it can
     * never be chosen from the municipality list, mirroring how '*' has its own
     * toggle on the page-permissions screen.
     */
    public function scopes(Request $request): View
    {
        $users = User::query()->orderBy('username')->get(['id', 'username']);

        $selectedUserId = $request->integer('user_id');
        $selectedUser = $users->firstWhere('id', $selectedUserId);

        $municipalities = Municipality::query()->orderBy('name')->get(['id', 'name']);

        $scope = $selectedUser?->municipalityScope()->pluck('municipality_id')->all() ?? [];

        return view('admin.permissions.scopes', [
            'users' => $users,
            'selectedUser' => $selectedUser,
            'municipalities' => $municipalities,
            'userScope' => array_map('intval', $scope),
            'hasAll' => in_array(AccessControlService::ALL_MUNICIPALITY_MARKER, $scope, true),
        ]);
    }

    public function updateScopes(MunicipalityScopeRequest $request, User $user): RedirectResponse
    {
        $old = $user->municipalityScope()
            ->pluck('municipality_id')
            ->map(fn (int $id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $ids = collect($request->input('municipalities', []))
            ->map(fn (mixed $id) => (int) $id)
            ->filter(fn (int $id) => $id > AccessControlService::ALL_MUNICIPALITY_MARKER)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($request->boolean('all')) {
            $ids[] = AccessControlService::ALL_MUNICIPALITY_MARKER;
        }

        sort($ids);

        if ($ids === $old) {
            return back()->with('login_status', 'No change — the municipality scope was already in that state.');
        }

        DB::transaction(function () use ($request, $user, $old, $ids) {
            UserMunicipality::query()->where('user_id', $user->id)->delete();

            foreach ($ids as $municipalityId) {
                UserMunicipality::query()->create([
                    'user_id' => $user->id,
                    'municipality_id' => $municipalityId,
                ]);
            }

            $this->audit()->log(
                $request->user()->id,
                'MANAGE_SCOPE_ASSIGNMENTS',
                'tbl_user_municipalities',
                $user->id,
                json_encode(['username' => $user->username, 'municipalities' => $old]),
                json_encode(['username' => $user->username, 'municipalities' => $ids])
            );
        });

        return back()->with('login_status', 'Municipality scope updated successfully!');
    }

    public function exemptions(Request $request): View
    {
        $acl = $this->accessControl();

        // Data-driven picker exclusion (Pass 5 §3): '*' holders are already
        // exempt via isSingleDeviceExempt -> isSuperAdmin, so they are omitted.
        $users = User::query()
            ->orderBy('username')
            ->get(['id', 'username'])
            ->reject(fn (User $user) => $acl->isSuperAdmin($user))
            ->values();

        $selectedUserId = $request->integer('user_id');
        $selectedUser = $users->firstWhere('id', $selectedUserId);

        return view('admin.permissions.exemptions', [
            'users' => $users,
            'selectedUser' => $selectedUser,
            'isExempt' => $selectedUser !== null && $acl->isMultiDeviceExempt($selectedUser),
        ]);
    }

    public function toggleExemption(ExemptionToggleRequest $request, User $user): RedirectResponse
    {
        $acl = $this->accessControl();

        if ($acl->isSuperAdmin($user)) {
            return back()->with('login_status', 'Super administrators are already exempt from the single-device check.');
        }

        $grant = $request->boolean('grant');
        $currentlyExempt = $acl->isMultiDeviceExempt($user);

        if ($grant === $currentlyExempt) {
            return back()->with(
                'login_status',
                'No change — the exemption was already '.($currentlyExempt ? 'granted' : 'revoked').'.'
            );
        }

        DB::transaction(function () use ($request, $user, $grant) {
            if ($grant) {
                MultiDeviceExemption::query()->create(['user_id' => $user->id]);

                $this->audit()->log(
                    $request->user()->id,
                    'MANAGE_EXEMPTION_GRANT',
                    'tbl_multi_device_exemptions',
                    $user->id,
                    null,
                    json_encode(['username' => $user->username])
                );
            } else {
                MultiDeviceExemption::query()->where('user_id', $user->id)->delete();

                $this->audit()->log(
                    $request->user()->id,
                    'MANAGE_EXEMPTION_REVOKE',
                    'tbl_multi_device_exemptions',
                    $user->id,
                    json_encode(['username' => $user->username]),
                    null
                );
            }
        });

        return back()->with('login_status', 'Multi-device exemption updated successfully!');
    }

    /**
     * The real page catalog: distinct tbl_permissions.page_name values plus the
     * five P7 keys, minus the '*' super-admin row (which has its own toggle).
     *
     * @return list<string>
     */
    public function pageCatalog(): array
    {
        return Permission::query()
            ->distinct()
            ->orderBy('page_name')
            ->pluck('page_name')
            ->merge(self::P7_KEYS)
            ->reject(fn (string $page) => $page === AccessControlService::SUPER_ADMIN_PAGE)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string> page key => display label
     */
    public function pageLabels(): array
    {
        $labels = [];

        foreach ($this->pageCatalog() as $page) {
            $labels[$page] = $this->pageLabel($page);
        }

        return $labels;
    }

    private function pageLabel(string $page): string
    {
        foreach (config('scanner.scanners') as $scanner) {
            if (($scanner['page'] ?? null) === $page) {
                return $scanner['title'];
            }
        }

        return self::PAGE_LABELS[$page] ?? $page;
    }

    private function accessControl(): AccessControlService
    {
        return app(AccessControlService::class);
    }

    private function audit(): AuditService
    {
        return app(AuditService::class);
    }
}
