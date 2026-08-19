<?php

namespace App\Http\Middleware;

use App\Services\AccessControlService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Action-level access gate (P12 §7, P9 §13).
 *
 * Usage: ->middleware('action:clients.php:create') — the first argument is the
 * v1 page key, the second the canonical action name. Layers on top of the
 * page: middleware (page = entry/VIEW; action = the specific mutation/export).
 * Denied requests are redirected to the dashboard with a flash message, or
 * answered with 403 when the client expects JSON — identical to AuthorizePage.
 */
class AuthorizeAction
{
    public function handle(Request $request, Closure $next, string $pageName, string $action): Response
    {
        $user = $request->user();

        if ($user !== null && app(AccessControlService::class)->canAccessAction($user, $pageName, $action)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Access denied.');
        }

        return redirect()->route('dashboard')->with('login_status', 'denied');
    }
}
