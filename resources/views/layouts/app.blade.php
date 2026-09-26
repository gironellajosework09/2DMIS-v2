<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <title>@yield('title', '2D MIS')</title>
    {{-- Phase 27: the Bootstrap 5.3.2 CDN stylesheet is removed; ui.css §4.8–4.10
         now owns the last shared families (form-label, accordion, list-group,
         utility + Reboot/type parity). --}}
    @vite(['resources/css/app.css'])
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    {{-- Legacy content compatibility for unmigrated screens only.
         Shell chrome is token/utility-driven since Batch B. --}}
    <style>
        .card {
            border-radius: 1rem;
        }
    </style>
    @stack('styles')
</head>
<body class="bg-[#F0F2F5] font-body">
<a href="#main-content" class="ui-skip-link">Skip to main content</a>

{{-- Shared shell catalog: single source of truth for the sidebar groups
     and the topbar breadcrumb. Presentation data only — every access
     check still runs inside the sidebar loops exactly as before. --}}
@php
    $shellSections = [
        ['label' => 'Overview', 'items' => [
            ['label' => 'Dashboard', 'icon' => 'grid', 'route' => 'dashboard', 'is' => ['dashboard']],
        ]],
        ['label' => 'Registry', 'items' => [
            ['label' => 'Clients', 'icon' => 'users', 'page' => 'clients.php', 'route' => 'clients.index', 'is' => ['clients.*']],
            ['label' => 'Households', 'icon' => 'home', 'page' => 'household.php', 'route' => 'households.index', 'is' => ['households.*']],
        ]],
        ['label' => 'Assistance', 'items' => [
            ['label' => 'Scholars', 'icon' => 'cap', 'page' => 'scholars.php', 'route' => 'scholars.index', 'is' => ['scholars.*']],
            // Key-only fallbacks: users who can open the reports/logs screen
            // WITHOUT scholars.php keep a top-level link; scholars.php holders
            // reach the same screens as Scholars tabs instead, so the top-level
            // link is suppressed (the 'fallback' key is the scholars page).
            ['label' => 'Scholarship Reports', 'icon' => 'award', 'page' => 'scholarship_reports.php', 'fallback' => 'scholars.php', 'route' => 'scholarship-reports.index', 'is' => ['scholarship-reports.*']],
            ['label' => 'Update Logs', 'icon' => 'clock', 'page' => 'update_logs.php', 'fallback' => 'scholars.php', 'route' => 'update-logs.index', 'is' => ['update-logs.*']],
            ['label' => 'All Transactions', 'icon' => 'file-text', 'page' => 'all_transactions.php', 'route' => 'transactions.index', 'is' => ['transactions.*']],
            ['type' => 'scanner-engine'],
            ['type' => 'payouts'],
        ]],
        ['label' => 'Administration', 'items' => [
            ['type' => 'group', 'id' => 'admin-access-control', 'label' => 'Access Control', 'icon' => 'lock', 'children' => [
                ['grouplabel' => 'Users'],
                ['label' => 'Create User', 'icon' => 'user-plus', 'page' => 'register.php', 'route' => 'admin.users.create', 'is' => ['admin.users.create', 'admin.users.store']],
                ['label' => 'User Management', 'icon' => 'shield', 'page' => '*', 'route' => 'admin.users.index', 'is' => ['admin.users.index', 'admin.users.reset-password', 'admin.users.show']],
                ['grouplabel' => 'Permissions'],
                ['label' => 'Manage Permissions', 'icon' => 'lock', 'page' => 'manage_permissions.php', 'route' => 'admin.permissions.pages', 'is' => ['admin.permissions.pages', 'admin.permissions.update-pages']],
                ['label' => 'Action Permissions', 'icon' => 'check-square', 'page' => 'manage_permissions.php', 'route' => 'admin.permissions.actions', 'is' => ['admin.permissions.actions', 'admin.permissions.update-actions']],
                ['grouplabel' => 'Scope'],
                ['label' => 'Municipality Scope', 'icon' => 'map-pin', 'page' => 'manage_permissions.php', 'route' => 'admin.permissions.scopes', 'is' => ['admin.permissions.scopes', 'admin.permissions.update-scopes']],
                ['label' => 'Manage Program Permissions', 'icon' => 'layers', 'page' => 'manage_program_permissions.php', 'route' => 'admin.program-permissions.pages', 'is' => ['admin.program-permissions.*']],
                ['grouplabel' => 'Exemptions'],
                ['label' => 'Multi-Device Exemptions', 'icon' => 'smartphone', 'page' => 'manage_multi_device_exemptions.php', 'route' => 'admin.exemptions.pages', 'is' => ['admin.exemptions.*']],
                ['grouplabel' => 'Sessions'],
                ['label' => 'Currently Logged Users', 'icon' => 'monitor', 'page' => 'currently_logged_users.php', 'route' => 'session.online', 'is' => ['session.online']],
            ]],
            ['label' => 'Audit Logs', 'icon' => 'activity', 'page' => 'audit_logs.php', 'route' => 'admin.audit-logs.index', 'is' => ['admin.audit-logs.*']],
        ]],
    ];
@endphp

@include('partials.sidebar', ['shellSections' => $shellSections])
@include('partials.details-panel')
@include('partials.unified-notify')

 {{-- Flash feedback renders through the ONE shared notification stack
      (partials/unified-notify — Phase 23). The layout no longer owns a
      separate top-anchored toast container; server flash contract is
      unchanged (controllers still flash `login_status`), only the
      presentation channel is shared. Validation errors below stay inline
      Bootstrap alerts by contract. --}}
@if (session('login_status'))
    <script>
        (function () {
            function pushOnReady() {
                if (typeof window.notify === 'function' && document.body) {
                    window.notify({ type: 'success', title: 'Welcome', message: {{ Js::from(session('login_status')) }} });
                } else {
                    setTimeout(pushOnReady, 60);
                }
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', pushOnReady);
            } else {
                pushOnReady();
            }
        })();
    </script>
@endif

</div>

<main id="main-content" tabindex="-1" class="lg:ml-[260px]">
    @include('partials.navbar', ['shellSections' => $shellSections])
    <div class="px-3 py-3 sm:px-7 sm:py-7">
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible" role="alert" x-data="{ open: true }" x-show="open">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
                <button type="button" class="btn-close" @click="open = false" aria-label="Close"></button>
            </div>
        @endif

        @yield('content')
    </div>
</main>

<script>
    async function checkSession() {
        try {
            const response = await fetch('{{ route('session.status') }}', { cache: 'no-store' });
            const data = await response.json();

            if (data.status === 'another_device') {
                alert('Your account was logged out by the system.');
                window.location.href = '{{ route('login') }}';
            } else if (data.status === 'logged_out') {
                window.location.href = '{{ route('login') }}';
            }
        } catch (error) {
            console.log(error);
        }
    }

    setInterval(checkSession, 2000);

    // Phase 22: no Bootstrap Toast initialization remains. The clients Phase 9
    // toast stack (clients/index showToast + wireFlashToast) and the dynamic
    // showToast channel reveal themselves with `.show` and dismiss manually;
    // the Phase 19 layout flash toast and Phase 20 page toasts are Alpine-owned.
    // Phase 24 removed the Bootstrap JS bundle (zero live consumers) and
    // Phase 27 removed the Bootstrap CSS CDN link (ui.css §4.8–4.10 parity).

    // Phase 2D — keep DataTables `scrollX` tables sized correctly when a layout
    // change occurs without a window resize (sidebar toggle,
    // vertical scrollbar appearing/disappearing on scroll-lock,
    // and the DetailsPanel overlap). Uses the v1 `columns().adjust()` pattern.
    // Guarded so this layout script runs safely on every screen: jQuery and
    // DataTables are loaded later by each screen's own script section.
    // Also called from the sidebar Alpine watcher on open/close.
    function adjustDataTables() {
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.dataTable) {
            window.jQuery.fn.dataTable.tables({ api: true }).columns.adjust();
        }
    }
    var detailsPanelEl = document.getElementById('detailsPanel');
    if (detailsPanelEl) {
        detailsPanelEl.addEventListener('transitionend', function (ev) {
            if (ev.propertyName === 'transform') {
                requestAnimationFrame(adjustDataTables);
            }
        });
    }

</script>
@vite(['resources/js/app.js'])
@stack('scripts')
</body>
</html>
