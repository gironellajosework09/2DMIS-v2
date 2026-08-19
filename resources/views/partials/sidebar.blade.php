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

    @if ($acl->canAccessPage($user, 'scholars.php'))
        <a href="{{ route('scholars.index') }}" @class(['active' => request()->routeIs('scholars.*')])>
            Scholars
        </a>
    @endif

    @if ($acl->canAccessPage($user, 'scholarship_reports.php'))
        <a href="{{ route('scholarship-reports.index') }}" @class(['active' => request()->routeIs('scholarship-reports.*')])>
            Scholarship Reports
        </a>
    @endif

    @if ($acl->canAccessPage($user, 'update_logs.php'))
        <a href="{{ route('update-logs.index') }}" @class(['active' => request()->routeIs('update-logs.*')])>
            Update Logs
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

    @php($payoutLinks = [
        'scanned_payouts' => ['page' => 'scanned_payouts.php', 'label' => 'Payout Attendance'],
        'scanned_payouts2' => ['page' => 'scanned_payouts2.php', 'label' => 'Payout Attendance 2'],
        'scanned_payouts_unpaid' => ['page' => 'scanned_payouts_unpaid.php', 'label' => 'Payout Attendance Unpaid'],
    ])
    @foreach ($payoutLinks as $variant => $link)
        @if ($acl->canAccessPage($user, $link['page']))
            <a href="{{ route('payout-attendance.'.$variant.'.index') }}" @class(['active' => request()->routeIs('payout-attendance.'.$variant.'.*')])>
                {{ $link['label'] }}
            </a>
        @endif
    @endforeach

    @if ($acl->canAccessPage($user, 'unpaid_verifications.php'))
        <a href="{{ route('unpaid-verifications.index') }}" @class(['active' => request()->routeIs('unpaid-verifications.*')])>
            Unpaid Grantees
        </a>
    @endif

    @if ($acl->canAccessPage($user, 'register.php'))
        <a href="{{ route('admin.users.create') }}" @class(['active' => request()->routeIs('admin.users.*')])>
            Create User
        </a>
    @endif

    @if ($acl->canAccessPage($user, 'manage_permissions.php'))
        <a href="{{ route('admin.permissions.pages') }}" @class(['active' => request()->routeIs('admin.permissions.pages') || request()->routeIs('admin.permissions.update-pages')])>
            Manage Permissions
        </a>
        <a href="{{ route('admin.permissions.actions') }}" @class(['active' => request()->routeIs('admin.permissions.actions') || request()->routeIs('admin.permissions.update-actions')])>
            Action Permissions
        </a>
        <a href="{{ route('admin.permissions.scopes') }}" @class(['active' => request()->routeIs('admin.permissions.scopes') || request()->routeIs('admin.permissions.update-scopes')])>
            Municipality Scope
        </a>
    @endif

    @if ($acl->canAccessPage($user, 'manage_program_permissions.php'))
        <a href="{{ route('admin.program-permissions.pages') }}" @class(['active' => request()->routeIs('admin.program-permissions.*')])>
            Manage Program Permissions
        </a>
    @endif

    @if ($acl->canAccessPage($user, 'manage_multi_device_exemptions.php'))
        <a href="{{ route('admin.exemptions.pages') }}" @class(['active' => request()->routeIs('admin.exemptions.*')])>
            Multi-Device Exemptions
        </a>
    @endif

    @if ($acl->canAccessPage($user, 'audit_logs.php'))
        <a href="{{ route('admin.audit-logs.index') }}" @class(['active' => request()->routeIs('admin.audit-logs.*')])>
            Audit Logs
        </a>
    @endif

    @if ($acl->canAccessProgram($user, 'AICS'))
        <a href="#">AICS</a>
    @endif
</div>
