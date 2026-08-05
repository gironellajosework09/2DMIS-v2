<?php

namespace App\Http\Middleware;

use App\Services\AccessControlService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single-device login contract (ADR-002).
 *
 * Port of v1 session.php: the token stored in the session must match the
 * session_token held in tbl_users, otherwise the session is destroyed and the
 * user is sent back to login. Super-admins and multi-device-exempt users skip
 * the check, exactly as v1 exempted them. last_activity is refreshed on every
 * authenticated request.
 */
class EnsureSingleDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $accessControl = app(AccessControlService::class);

        if (! $accessControl->isSingleDeviceExempt($user)) {
            $sessionToken = $request->session()->get('session_token');
            $dbToken = $user->session_token;

            if (
                $sessionToken === null
                || $dbToken === null
                || ! hash_equals((string) $sessionToken, (string) $dbToken)
            ) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with('login_status', 'expired');
            }
        }

        DB::table('tbl_users')
            ->where('id', $user->id)
            ->update(['last_activity' => now()]);

        return $next($request);
    }
}
