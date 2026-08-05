<div class="sidebar" id="sidebar">
    @php($acl = app(\App\Services\AccessControlService::class))
    @php($user = auth()->user())

    <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>Dashboard</a>

    @if ($acl->canAccessPage($user, 'currently_logged_users.php'))
        <a href="{{ route('session.online') }}" @class(['active' => request()->routeIs('session.online')])>
            Currently Logged Users
        </a>
    @endif

    @if ($acl->canAccessProgram($user, 'AICS'))
        <a href="#">AICS</a>
    @endif
</div>
