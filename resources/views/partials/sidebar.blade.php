<div class="sidebar" id="sidebar">
    @php($acl = app(\App\Services\AccessControlService::class))
    @php($user = auth()->user())

    <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>Dashboard</a>

    @if ($acl->canAccessPage($user, 'currently_logged_users.php'))
        <a href="{{ route('session.online') }}" @class(['active' => request()->routeIs('session.online')])>
            Currently Logged Users
        </a>
    @endif

    @if ($acl->canAccessPage($user, 'clients.php'))
        <a href="{{ route('clients.index') }}" @class(['active' => request()->routeIs('clients.*')])>
            Clients
        </a>
    @endif

    @if ($acl->canAccessPage($user, 'household.php'))
        <a href="{{ route('households.index') }}" @class(['active' => request()->routeIs('households.*')])>
            Households
        </a>
    @endif

    @if ($acl->canAccessPage($user, 'all_transactions.php'))
        <a href="{{ route('transactions.index') }}" @class(['active' => request()->routeIs('transactions.*')])>
            All Transactions
        </a>
    @endif

    @foreach (config('scanner.scanners') as $scannerKey => $scannerConfig)
        @if ($acl->canAccessPage($user, $scannerConfig['page']))
            <a href="{{ route('scanners.'.$scannerKey) }}" @class(['active' => request()->routeIs('scanners.'.$scannerKey)])>
                {{ $scannerConfig['title'] }}
            </a>
        @endif
    @endforeach

    @if ($acl->canAccessProgram($user, 'AICS'))
        <a href="#">AICS</a>
    @endif
</div>
