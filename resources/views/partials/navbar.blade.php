@php
    // Presentation-only breadcrumb resolution (no access checks here).
    // Hub pages and config-driven screens are matched explicitly first
    // (their labels come from route + config, independent of the shell
    // catalog); everything else falls back to the catalog, including
    // Access Control group children (grouplabels never label a crumb).
    $breadcrumbLabel = 'Dashboard';

    if (request()->routeIs('scanners.index')) {
        $breadcrumbLabel = 'Scanner Engine';
    } elseif (request()->routeIs('scanners.*')) {
        $breadcrumbLabel = config('scanner.scanners.'.request()->route('key').'.title') ?? 'Scanner';
    } elseif (request()->routeIs('payouts.index')) {
        $breadcrumbLabel = 'Payouts';
    } elseif (request()->routeIs('payout-attendance.*')) {
        $breadcrumbLabel = config('payout.attendance.'.request()->route('variant').'.title') ?? 'Payout Attendance';
    } elseif (request()->routeIs('unpaid-verifications.*')) {
        $breadcrumbLabel = 'Unpaid Grantees';
    } else {
        foreach ($shellSections as $shellSection) {
            foreach ($shellSection['items'] as $shellItem) {
                if (($shellItem['type'] ?? null) === 'group') {
                    foreach ($shellItem['children'] as $child) {
                        if (isset($child['grouplabel']) || ! isset($child['is'])) {
                            continue;
                        }
                        foreach ($child['is'] as $breadcrumbPattern) {
                            if (request()->routeIs($breadcrumbPattern)) {
                                $breadcrumbLabel = $child['label'];
                            }
                        }
                    }
                } elseif (isset($shellItem['is'])) {
                    foreach ($shellItem['is'] as $breadcrumbPattern) {
                        if (request()->routeIs($breadcrumbPattern)) {
                            $breadcrumbLabel = $shellItem['label'];
                        }
                    }
                }
            }
        }
    }
@endphp

<nav class="sticky top-0 z-50 flex h-16 items-center gap-4 border-b border-[var(--border,#E2E5EA)] bg-white px-7">
    <button
        type="button"
        class="rounded-btn p-2 text-ink/50 transition duration-200 ease-standard hover:bg-ink/5 hover:text-ink lg:hidden"
        title="Toggle navigation"
        aria-label="Toggle navigation"
        x-data
        @click="$store.sidebar.toggle()"
        :aria-controls="'appSidebar'"
        :aria-expanded="$store.sidebar.open.toString()"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
            <line x1="3" y1="6" x2="21" y2="6"/>
            <line x1="3" y1="12" x2="21" y2="12"/>
            <line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
    </button>

    <div class="hidden min-w-0 items-center gap-1.5 text-dense text-ink/40 sm:flex">
        <a href="{{ route('dashboard') }}" class="shrink-0 text-ink/40 no-underline transition hover:text-ink/60">2DMIS</a>
        <span aria-hidden="true" class="text-ink/25">›</span>
        <span class="truncate font-semibold text-ink/70">{{ $breadcrumbLabel }}</span>
    </div>

    @include('partials.global-search')

    <div class="ml-auto flex items-center gap-2">
        <button type="button" class="relative rounded-btn p-2 text-ink/40 transition duration-200 ease-standard hover:bg-ink/5 hover:text-ink/70" id="notifBtn" aria-label="Notifications" aria-expanded="false" aria-haspopup="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 01-3.46 0"/>
            </svg>
            <span class="notif-dot absolute top-1 right-1 min-w-[16px] h-4 px-1 rounded-full bg-red-600 text-white text-[0.6rem] font-bold flex items-center justify-center border-2 border-white" hidden>0</span>
        </button>

        <div class="dropdown" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
            <button class="dropdown-toggle flex items-center gap-2 rounded-btn px-3 py-2 text-dense font-medium text-ink/70 transition duration-200 ease-standard hover:bg-ink/5" type="button"
                    id="userDropdown" @click="open = !open" :aria-expanded="open.toString()">
                <span class="hidden sm:inline">Welcome, {{ auth()->user()->username }}</span>
                <span class="sm:hidden">{{ strtoupper(substr(auth()->user()->username, 0, 2)) }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" :class="{ 'show': open }" aria-labelledby="userDropdown">
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item">Logout</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</nav>
