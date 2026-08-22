<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordResetRequest;
use App\Http\Requests\UserCreateRequest;
use App\Models\User;
use App\Services\AccessControlService;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
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

    public function resetPassword(PasswordResetRequest $request, User $user): RedirectResponse
    {
        if ($this->acl->isSuperAdmin($user)) {
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

        return back()->with('login_status', "Password updated successfully for {$user->username} and logged.");
    }
}
