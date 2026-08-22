<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccessControlService;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SessionController extends Controller
{
    /**
     * Port of v1 check_session.php — polled by the front-end to detect a
     * forced / second-device logout without a full page reload.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['status' => 'logged_out']);
        }

        $accessControl = app(AccessControlService::class);

        if ($accessControl->isSingleDeviceExempt($user)) {
            return response()->json(['status' => 'ok']);
        }

        $sessionToken = $request->session()->get('session_token');
        $dbToken = $user->session_token;

        if (
            $sessionToken === null
            || $dbToken === null
            || ! hash_equals((string) $sessionToken, (string) $dbToken)
        ) {
            return response()->json(['status' => 'another_device']);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Port of v1 currently_logged_users.php (admin-only, page-gated).
     * Online = a session_token is present and last_activity falls within
     * v1's 20-minute activity window. The v1 username exclusion list
     * ('jordi', 'super_admin') becomes data-driven: super-admin status is
     * the '*' permission row, so its holders are excluded instead.
     */
    public function online(): View
    {
        $onlineUsers = User::query()
            ->whereNotNull('session_token')
            ->where('last_activity', '>=', now()->subMinutes(20))
            ->whereDoesntHave('permissions', function ($query) {
                $query->where('page_name', AccessControlService::SUPER_ADMIN_PAGE)
                    ->where('can_access', true);
            })
            ->orderBy('username')
            ->get(['id', 'username', 'last_activity']);

        return view('sessions.online', ['onlineUsers' => $onlineUsers]);
    }

    /**
     * Port of v1 force_logout.php — revokes a user's session_token so the next
     * request from any of their devices fails the single-device check.
     */
    public function forceLogout(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $target = User::query()->find($validated['user_id']);

        if ($target === null) {
            return back()->with('login_status', 'User not found.');
        }

        $target->session_token = null;
        $target->save();

        app(AuditService::class)->log(
            $request->user()->id,
            'FORCE_LOGOUT',
            'tbl_users',
            $target->id
        );

        return back()->with('login_status', "User {$target->username} has been forcefully logged out.");
    }
}
