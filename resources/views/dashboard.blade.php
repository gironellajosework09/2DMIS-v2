@extends('layouts.app')

@section('title', 'Dashboard — 2D MIS')

@php
    $acl = app(\App\Services\AccessControlService::class);
    $user = auth()->user();

    // Quick actions reuse authorized routes only; every entry re-checks
    // page access at render time, and create-form entries also require
    // the matching action permission so no dead-end links are exposed.
    $quickActions = [];
    if ($acl->canAccessPage($user, 'clients.php') && $acl->canAccessAction($user, 'clients.php', 'create')) {
        $quickActions[] = [
            'label' => 'Add Client',
            'hint' => 'Register a new beneficiary',
            'route' => 'clients.create',
            'icon' => 'user-plus',
        ];
    }
    if ($acl->canAccessPage($user, 'household.php') && $acl->canAccessAction($user, 'household.php', 'create')) {
        $quickActions[] = [
            'label' => 'Register Household',
            'hint' => 'Create a household record',
            'route' => 'households.create',
            'icon' => 'home',
        ];
    }
    if ($acl->canAccessPage($user, 'all_transactions.php')) {
        // The transaction create form requires a beneficiary context
        // (transactions/create/{client}), so the workflow starts at the
        // transactions screen's search — same path as the sidebar link.
        $quickActions[] = [
            'label' => 'New Transaction',
            'hint' => 'Start from beneficiary search',
            'route' => 'transactions.index',
            'icon' => 'file-text',
        ];
    }

    // Placeholder-safe URL for row links: the JS swaps the sentinel for
    // the real id; the show route still enforces record-level ACL.
    $showTemplate = str_replace(
        urlencode('TRANSACTION_ID'),
        'TRANSACTION_ID',
        route('transactions.show', ['transaction' => 'TRANSACTION_ID']),
    );

    // Scanner deep links: identical gate loop to the sidebar (config-driven,
    // one canAccessPage check per scanner page at render time).
    $permittedScanners = collect(config('scanner.scanners'))
        ->filter(fn ($scannerConfig) => $acl->canAccessPage($user, $scannerConfig['page']));

    // Format currency for display
    $formatCurrency = fn ($amount) => '₱'.number_format((float) $amount, 2);

    // Format large numbers (e.g., 1247 → 1,247)
    $formatNumber = fn ($n) => number_format((int) $n);

    // Activity feed action labels (human-readable)
    $actionLabels = [
        'ADD_CLIENT' => 'added a client',
        'EDIT_CLIENT' => 'updated a client',
        'DELETE_CLIENT' => 'deleted a client',
        'ADD_TRANSACTION' => 'recorded a transaction',
        'EDIT_TRANSACTION' => 'updated a transaction',
        'DELETE_TRANSACTION' => 'deleted a transaction',
        'MANAGE_USER_CREATE' => 'created a user',
        'MANAGE_PERMISSIONS' => 'updated permissions',
        'MANAGE_ACTION_PERMISSIONS' => 'updated action permissions',
        'MANAGE_SCOPE_ASSIGNMENTS' => 'updated scope assignments',
        'MANAGE_PROGRAM_PERMISSIONS' => 'updated program permissions',
        'MANAGE_MULTI_DEVICE_EXEMPTION' => 'updated exemptions',
        'PASSWORD_RESET' => 'reset a password',
    ];

    // Color palette for activity avatars (cycled by index)
    $avatarColors = [
        'bg-navy/80',
        'bg-[#2D8B7A]/80',
        'bg-[#D4A900]/80',
        'bg-[#CE1126]/80',
        'bg-[#3B82F6]/80',
    ];

    // Program distribution color palette (cycled by index)
    $programColors = [
        ['from' => '#0038A8', 'to' => '#164A9C'],  // navy
        ['from' => '#FCD116', 'to' => '#FFF8D6'],  // gold
        ['from' => '#2D8B7A', 'to' => '#34A08C'],  // teal
        ['from' => '#3B82F6', 'to' => '#60A5FA'],  // blue
        ['from' => '#D4A900', 'to' => '#F5C462'],  // amber
        ['from' => '#CE1126', 'to' => '#E8463A'],  // red
    ];

    // Compute max program count for bar width calculation
    $maxProgramCount = $programDistribution->max('count') ?: 1;
@endphp

@section('content')
    {{-- Root page: single crumb by design, so the partial renders nothing --}}
    @include('partials.breadcrumbs', ['breadcrumbs' => [['label' => 'Dashboard']]])

    @include('partials.page-header', [
        'title' => 'Dashboard',
        'subtitle' => 'Welcome back, '.$user->username.'.',
    ])

    {{-- ═══ KPI CARDS ═══════════════════════════════════════════════ --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {{-- Total Clients --}}
        <div class="metric-card">
            <div class="flex items-start justify-between" style="margin-bottom:14px">
                <div class="grid h-[42px] w-[42px] shrink-0 place-items-center rounded-[var(--ui-radius)] bg-navy/[0.07] text-navy">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ $formatNumber($totalClients) }}</div>
            <div class="mt-1 text-[0.78rem] font-medium text-[var(--ui-text-secondary)]">Total Registered Clients</div>
        </div>

        {{-- Total Transactions --}}
        <div class="metric-card accent-gold">
            <div class="flex items-start justify-between" style="margin-bottom:14px">
                <div class="grid h-[42px] w-[42px] shrink-0 place-items-center rounded-[var(--ui-radius)] bg-[var(--ui-gold-dim)] text-[var(--ui-gold)]">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ $formatNumber($totalTransactions) }}</div>
            <div class="mt-1 text-[0.78rem] font-medium text-[var(--ui-text-secondary)]">Assistance Transactions</div>
        </div>

        {{-- Disbursed Amount --}}
        <div class="metric-card accent-teal">
            <div class="flex items-start justify-between" style="margin-bottom:14px">
                <div class="grid h-[42px] w-[42px] shrink-0 place-items-center rounded-[var(--ui-radius)] bg-[rgba(45,139,122,0.08)] text-[var(--ui-teal)]">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ $formatCurrency($disbursedAmount) }}</div>
            <div class="mt-1 text-[0.78rem] font-medium text-[var(--ui-text-secondary)]">Total Amount Disbursed</div>
        </div>

        {{-- Pending Approvals --}}
        <div class="metric-card accent-red">
            <div class="flex items-start justify-between" style="margin-bottom:14px">
                <div class="grid h-[42px] w-[42px] shrink-0 place-items-center rounded-[var(--ui-radius)] bg-[rgba(206,17,38,0.06)] text-[var(--ui-red)]">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
            </div>
            <div class="metric-value">{{ $formatNumber($pendingCount) }}</div>
            <div class="mt-1 text-[0.78rem] font-medium text-[var(--ui-text-secondary)]">Pending Approvals</div>
        </div>
    </div>

    {{-- ═══ QUICK ACTIONS ═══════════════════════════════════════════ --}}
    @unless (empty($quickActions) && $permittedScanners->isEmpty())
        <div class="mb-6 flex flex-wrap gap-3" aria-label="Quick actions">
            @foreach ($quickActions as $action)
                <a href="{{ route($action['route']) }}"
                   class="group flex items-center gap-2.5 rounded-[var(--ui-radius-lg)] border border-[var(--ui-border-light)] bg-[var(--ui-card)] px-[18px] py-3 font-semibold text-[0.82rem] text-[var(--ui-text-primary)] shadow-[var(--ui-shadow-sm)] transition-all duration-[200ms] ease-[var(--ui-ease)] hover:-translate-y-px hover:border-[var(--ui-border)] hover:shadow-[var(--ui-shadow-md)]">
                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-[var(--ui-radius-sm)] bg-navy/[0.07] text-navy transition-colors duration-200 group-hover:bg-navy group-hover:text-white" aria-hidden="true">
                        @switch($action['icon'])
                            @case('home')<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>@break
                            @case('file-text')<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>@break
                            @default<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                        @endswitch
                    </span>
                    {{ $action['label'] }}
                </a>
            @endforeach

            @unless ($permittedScanners->isEmpty())
                @foreach ($permittedScanners as $scannerKey => $scannerConfig)
                    <a href="{{ route('scanners.'.$scannerKey) }}" class="btn-subtle no-underline">{{ $scannerConfig['title'] }}</a>
                @endforeach
            @endunless
        </div>
    @endunless

    {{-- ═══ RECENT TRANSACTIONS + PROGRAM DISTRIBUTION ═════════════ --}}
    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Recent Transactions --}}
        @if ($acl->canAccessPage($user, 'all_transactions.php'))
            {{-- Recent rows come from the SAME DataTables feed the
                 transactions screen uses (route transactions.data):
                 municipality scope + program restrictions are enforced
                 inside that endpoint, so this widget cannot widen any
                 user's visibility. The widget itself only renders for
                 users who already pass the all_transactions.php gate. --}}
            <section class="data-card min-w-0" aria-labelledby="recent-transactions-heading">
                <div class="data-card-header">
                    <h2 id="recent-transactions-heading" class="m-0 text-dense font-heading font-semibold text-ink">Recent transactions</h2>
                    <a href="{{ route('transactions.index') }}" class="btn-subtle no-underline">View all</a>
                </div>
                <div class="overflow-x-auto" id="recent-transactions-wrap" aria-busy="true">
                    <table class="w-full min-w-[42rem] border-collapse text-left text-dense">
                        <caption class="sr-only">Five most recently recorded assistance transactions</caption>
                        <thead>
                            <tr class="border-b border-line">
                                <th scope="col" class="ui-micro-label px-[16px] py-2">Date applied</th>
                                <th scope="col" class="ui-micro-label px-[16px] py-2">Program</th>
                                <th scope="col" class="ui-micro-label px-[16px] py-2">Client</th>
                                <th scope="col" class="ui-micro-label px-[16px] py-2">Status</th>
                                <th scope="col" class="ui-micro-label px-[16px] py-2 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="recent-transactions-body">
                            <tr>
                                <td colspan="5" class="px-[16px] py-[16px] text-center text-ink-muted" role="status">Loading recent transactions…</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <noscript>
                    <p class="m-0 border-t border-line-light px-[16px] py-[16px] text-center text-dense text-ink-muted">
                        JavaScript is required to load recent transactions.
                    </p>
                </noscript>
            </section>
        @endif

        {{-- Program Distribution --}}
        <section class="data-card min-w-0" aria-labelledby="program-dist-heading">
            <div class="data-card-header">
                <h2 id="program-dist-heading" class="m-0 text-dense font-heading font-semibold text-ink">Program distribution</h2>
            </div>
            <div class="data-card-body">
                @if ($programDistribution->isEmpty())
                    <p class="m-0 py-4 text-center text-[var(--ui-text-muted)]">No transactions recorded yet.</p>
                @else
                    @foreach ($programDistribution as $index => $program)
                        @php
                            $width = round(($program->count / $maxProgramCount) * 100);
                            $color = $programColors[$index % count($programColors)];
                        @endphp
                        <div @unless($loop->last) style="margin-bottom:18px" @endunless>
                            <div class="mb-1.5 flex items-center justify-between text-[0.82rem]">
                                <span class="font-semibold">{{ e($program->program) }}</span>
                                <span class="text-[var(--ui-text-muted)]">{{ $formatNumber($program->count) }} transactions</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-[var(--ui-bg-alt)]">
                                <div class="h-full rounded-full" style="width:{{ $width }}%;background:linear-gradient(90deg, {{ $color['from'] }}, {{ $color['to'] }})"></div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </section>
    </div>

    {{-- ═══ ACTIVITY FEED ═══════════════════════════════════════════ --}}
    @if ($acl->canAccessPage($user, 'audit_logs.php') && $activityFeed->isNotEmpty())
        <section class="data-card" aria-labelledby="activity-heading">
            <div class="data-card-header">
                <h2 id="activity-heading" class="m-0 text-dense font-heading font-semibold text-ink">Recent activity</h2>
                <a href="{{ route('admin.audit-logs.index') }}" class="btn-subtle no-underline">View all</a>
            </div>
            <div>
                @foreach ($activityFeed as $entry)
                    @php
                        $initials = strtoupper(substr($entry->username, 0, 2));
                        $colorClass = $avatarColors[$loop->index % count($avatarColors)];
                        $actionText = $actionLabels[$entry->action] ?? strtolower(str_replace('_', ' ', strtolower($entry->action)));
                        $tableName = str_replace('tbl_', '', $entry->target_table);
                    @endphp
                    <div class="flex items-start gap-3 border-b border-[var(--ui-border-light)] px-6 py-3 last:border-b-0">
                        <div class="grid h-8 w-8 min-w-8 shrink-0 place-items-center rounded-[var(--ui-radius)] text-[0.7rem] font-bold text-white {{ $colorClass }}">
                            {{ $initials }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[0.8rem] leading-[1.45] text-[var(--ui-text-primary)]">
                                <strong>{{ e($entry->username) }}</strong> {{ $actionText }}
                                <span class="text-[var(--ui-text-muted)]">{{ $tableName }} #{{ $entry->target_id }}</span>
                            </p>
                            <time class="text-[0.7rem] text-[var(--ui-text-muted)]" datetime="{{ $entry->created_at }}">
                                {{ \Carbon\Carbon::parse($entry->created_at)->diffForHumans() }}
                            </time>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
@endsection

{{-- Widget JS ships only to users who pass the all_transactions.php
     gate — unauthorized users get neither markup nor script. --}}
@if ($acl->canAccessPage($user, 'all_transactions.php'))
@push('scripts')
    <script>
        (function () {
            var body = document.getElementById('recent-transactions-body');
            var wrap = document.getElementById('recent-transactions-wrap');
            if (!body || !wrap) {
                return;
            }

            var showTemplate = @json($showTemplate);

            function badgeClass(status) {
                var value = String(status || '').toUpperCase();
                if (value === 'PAID') {
                    return 'status-badge is-paid';
                }
                if (value.indexOf('PENDING') === 0) {
                    return 'status-badge is-pending';
                }
                return 'status-badge is-neutral';
            }

            function replaceRows(rows) {
                body.textContent = '';
                rows.forEach(function (row) {
                    var tr = document.createElement('tr');
                    tr.className = 'border-b border-line last:border-b-0';

                    var cells = [
                        { text: row.date_applied || '\u2014' },
                        { text: row.program || '\u2014' },
                        { text: row.client_name || '\u2014', link: row.id ? showTemplate.replace('TRANSACTION_ID', row.id) : null },
                        { badge: row.status },
                        { text: row.suggested_amount ? '\u20B1 ' + row.suggested_amount : '\u2014', align: 'right' },
                    ];

                    cells.forEach(function (cell) {
                        var td = document.createElement('td');
                        td.className = 'px-[16px] py-2 align-middle' + (cell.align === 'right' ? ' text-right tabular-nums' : '');
                        if (cell.badge !== undefined) {
                            var span = document.createElement('span');
                            span.className = badgeClass(cell.badge);
                            span.textContent = cell.badge || 'UNKNOWN';
                            td.appendChild(span);
                        } else if (cell.link) {
                            var a = document.createElement('a');
                            a.href = cell.link;
                            a.className = 'font-medium text-navy no-underline hover:text-navy-hover hover:underline';
                            a.textContent = cell.text;
                            td.appendChild(a);
                        } else {
                            td.textContent = cell.text;
                        }
                        tr.appendChild(td);
                    });
                    body.appendChild(tr);
                });
            }

            function noticeRow(message) {
                body.textContent = '';
                var tr = document.createElement('tr');
                var td = document.createElement('td');
                td.colSpan = 5;
                td.className = 'px-[16px] py-[16px] text-center text-dense text-ink-muted';
                td.setAttribute('role', 'status');
                td.textContent = message;
                tr.appendChild(td);
                body.appendChild(tr);
            }

            fetch('{{ route('transactions.data') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: new URLSearchParams({
                    draw: '1',
                    start: '0',
                    length: '5',
                    'order[0][column]': '20',
                    'order[0][dir]': 'desc',
                }),
            })
                .then(function (response) {
                    if (! response.ok) {
                        throw new Error('feed unavailable');
                    }
                    return response.json();
                })
                .then(function (json) {
                    wrap.setAttribute('aria-busy', 'false');
                    var rows = json.data || [];
                    if (! rows.length) {
                        noticeRow('No assistance transactions have been recorded yet.');
                        return;
                    }
                    replaceRows(rows);
                })
                .catch(function () {
                    wrap.setAttribute('aria-busy', 'false');
                    noticeRow('Recent transactions could not be loaded.');
                });
        })();
    </script>
@endpush
@endif
