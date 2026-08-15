<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserCreateRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * P7 user creation — v1 register.php + add_user.php port. One screen behind
 * page:register.php (or '*'). Users are created with username + password only
 * and start with zero permissions (v1 parity). Every successful creation is
 * audited as MANAGE_USER_CREATE.
 */
class UserController extends Controller
{
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
}
