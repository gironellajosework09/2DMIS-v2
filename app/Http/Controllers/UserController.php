<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordResetRequest;
use App\Http\Requests\UserCreateRequest;
use App\Models\User;
use App\Services\AccessControlService;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * P7 user administration — v1 register.php / add_user.php (creation) and
 * manage_php.php (password reset) ports. Creation is behind page:register.php
 * (or '*'); the password-reset screen is super-admin-only via page:*.
 *
 * v1's hardcoded `username === 'super_admin'` target guard becomes
 * data-driven: the ACL service identifies protected accounts by the '*'
 * permission row. The legacy password_resets log row is preserved alongside
 * a tbl_audit_logs entry written through the single AuditService.
 */
class UserController extends Controller
{
    public function __construct(
        private readonly AccessControlService $acl,
    ) {}

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(UserCreateRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            'username' => $request->validated('username'),
            'password' => $request->validated('password'),
        ]);

        app(AuditService::class)->log(
            $request->user()->id,
            'MANAGE_USER_CREATE',
            'tbl_users',
            $user->id,
            null,
            json_encode(['username' => $user->username])
        );

        return back()->with('login_status', "User {$user->username} created successfully.");
    }

    /**
     * Port of v1 manage_php.php — user list + administrator password reset.
     * Super-admin targets are shown but not resettable (v1 parity).
     */
    public function index(): View
    {
        $users = User::query()->orderBy('username')->get(['id', 'username', 'created_at']);

        $protected = User::query()
            ->whereHas('permissions', function ($query) {
                $query->where('page_name', AccessControlService::SUPER_ADMIN_PAGE)
                    ->where('can_access', true);
            })
            ->pluck('id')
            ->all();

        return view('admin.users.index', [
            'users' => $users,
            'protectedUserIds' => $protected,
        ]);
    }

    public function resetPassword(PasswordResetRequest $request, User $user): JsonResponse|RedirectResponse
    {
        if ($this->acl->isSuperAdmin($user)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot change the password of a super admin.',
                ], 422);
            }

            return back()->with('login_status', 'You cannot change the password of a super admin.');
        }

        DB::transaction(function () use ($request, $user) {
            // The 'hashed' cast bcrypts the value (v1: password_hash).
            $user->password = $request->validated('password');
            $user->save();

            DB::table('password_resets')->insert([
                'changed_by' => $request->user()->username,
                'changed_for' => $user->username,
                'changed_at' => now(),
            ]);

            app(AuditService::class)->log(
                $request->user()->id,
                'PASSWORD_RESET',
                'tbl_users',
                $user->id,
                null,
                json_encode(['username' => $user->username])
            );
        });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Password updated successfully for {$user->username} and logged.",
                'id' => $user->id,
            ]);
        }

        return back()->with('login_status', "Password updated successfully for {$user->username} and logged.");
    }

    public function data(Request $request): JsonResponse
    {
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        $search = $request->input('search.value');
        $orderIndex = (int) $request->input('order.0.column', 3);
        $orderDir = $request->input('order.0.dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $query = User::query()->select('id', 'username', 'role', 'status', 'created_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('role', 'like', "%{$search}%");
            });
        }

        $total = $query->count();

        $users = $query->orderBy('created_at', $orderDir)
            ->offset($start)
            ->limit($length)
            ->get();

        $protectedIds = User::query()
            ->whereHas('permissions', function ($q) {
                $q->where('page_name', AccessControlService::SUPER_ADMIN_PAGE)
                    ->where('can_access', true);
            })
            ->pluck('id')
            ->all();

        $rows = $users->map(function ($user) use ($protectedIds) {
            return [
                'id' => $user->id,
                'username' => $user->username,
                'role' => $user->role,
                'status' => $user->status,
                'created_at' => $user->created_at,
                'actions' => '<button class="btn btn-sm btn-primary reset-btn" data-id="'.$user->id.'" data-username="'.$user->username.'"'.(in_array($user->id, $protectedIds) ? ' disabled' : '').'>'.(in_array($user->id, $protectedIds) ? 'Protected' : 'Reset Password').'</button>',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $rows,
        ]);
    }

    public function show(User $user, Request $request): View
    {
        $isPanel = $request->boolean('panel');

        $protected = User::query()
            ->whereHas('permissions', function ($q) {
                $q->where('page_name', AccessControlService::SUPER_ADMIN_PAGE)
                    ->where('can_access', true);
            })
            ->pluck('id')
            ->all();

        $pagePerms = $user->permissions()->where('can_access', true)->pluck('page_name')->all();
        $actionPerms = $user->actionPermissions()->pluck('action_name')->all();
        $programPerms = $user->programPermissions()->pluck('program_name')->all();
        $muniScopes = $user->municipalityScopes()->pluck('municipality_id')->all();

        if ($isPanel) {
            return view('admin.users.show', [
                'managedUser' => $user,
                'protected' => $protected,
                'pagePerms' => $pagePerms,
                'actionPerms' => $actionPerms,
                'programPerms' => $programPerms,
                'muniScopes' => $muniScopes,
                'panel' => true,
            ]);
        }

        return view('admin.users.show', [
            'managedUser' => $user,
            'protected' => $protected,
            'pagePerms' => $pagePerms,
            'actionPerms' => $actionPerms,
            'programPerms' => $programPerms,
            'muniScopes' => $muniScopes,
            'panel' => false,
        ]);
    }
}
