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
     */
    public function online(): View
    {
        return view('sessions.online');
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
