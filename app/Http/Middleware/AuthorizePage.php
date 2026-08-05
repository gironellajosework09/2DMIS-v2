<?php

namespace App\Http\Middleware;

use App\Services\AccessControlService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Page-level access gate (ADR-003).
 *
 * Usage: ->middleware('page:scanner_ceap.php') — the argument is the v1 page
 * key stored in tbl_permissions.page_name (kept identical to v1 on purpose).
 * Denied requests are redirected to the dashboard with a flash message, or
 * answered with 403 when the client expects JSON.
 */
class AuthorizePage
{
    public function handle(Request $request, Closure $next, string $pageName): Response
    {
        $user = $request->user();

        if ($user !== null && app(AccessControlService::class)->canAccessPage($user, $pageName)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Access denied.');
        }

        return redirect()->route('dashboard')->with('login_status', 'denied');
    }
}
