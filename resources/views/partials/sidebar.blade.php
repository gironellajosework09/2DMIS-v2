<div x-data="{
        get open() { return $store.sidebar.open; },
        close() { $store.sidebar.close(); }
    }"
    x-init="$watch('open', (v) => { document.body.style.overflow = v ? 'hidden' : ''; requestAnimationFrame(adjustDataTables); })"
    @keydown.escape.window="if (open) close()">

    {{-- Mobile backdrop --}}
    <div x-show="open"
         x-transition.opacity.duration.250ms
         @click="close()"
         class="fixed inset-0 z-[90] bg-ink/50 lg:hidden"
         aria-hidden="true"></div>

    {{-- Sidebar --}}
    <aside id="appSidebar" aria-label="Primary navigation" tabindex="-1"
           :class="open ? 'translate-x-0' : '-translate-x-full'"
           class="fixed top-0 bottom-0 left-0 z-[100] flex w-[280px] flex-col bg-gradient-to-b from-navy to-[#0C1622] transition-transform duration-300 ease-in-out overflow-visible lg:w-[260px]! lg:translate-x-0">
        @php
            $acl = app(\App\Services\AccessControlService::class);
            $user = auth()->user();
        @endphp

        {{-- Aggregated gates for the two hub links. One canAccessPage check
             per backing page key at render time, exactly like the old
             scanner/aics loops — presentation data never widens access. --}}
        @php
            $anyScannerAccess = collect(config('scanner.scanners'))
                ->contains(fn ($scannerConfig) => $acl->canAccessPage($user, $scannerConfig['page']));

            $anyPayoutAccess = collect(config('payout.attendance'))
                    ->contains(fn ($payoutConfig) => $acl->canAccessPage($user, $payoutConfig['page']))
                || $acl->canAccessPage($user, 'unpaid_verifications.php')
                || $acl->canAccessPage($user, config('scanner.scanners.payout.page'))
                || $acl->canAccessPage($user, config('scanner.scanners.payout_unpaid.page'));

            $scannerHubActive = request()->routeIs('scanners.*');

            $payoutHubActive = request()->routeIs('payouts.index')
                || request()->routeIs('payout-attendance.*')
                || request()->routeIs('unpaid-verifications.*');
        @endphp

        <div class="flex items-center gap-3 border-b border-white/10 px-[1.375rem] py-5">
            <img src="{{ asset('seal_logo.png') }}" alt="" class="h-9 w-auto shrink-0">
            <div class="min-w-0 leading-tight">
                <p class="truncate text-base font-bold text-white">2D MIS</p>
                <p class="tracking-caps text-micro uppercase text-white/40">Ilocos Sur</p>
            </div>
            <button type="button" class="btn-close btn-close-white ms-auto lg:hidden" @click="close()" aria-label="Close"></button>
        </div>

        <div class="flex-1 overflow-y-auto px-3 py-4">
            @foreach ($shellSections as $section)
                <section class="mb-6 last:mb-0">
                    <h2 class="sidebar-section-label">{{ $section['label'] }}</h2>

                    @foreach ($section['items'] as $item)
                        @if (($item['type'] ?? null) === 'scanner-engine')
                            @if ($anyScannerAccess)
                                @include('partials.sidebar-link', [
                                    'href' => route('scanners.index'),
                                    'label' => 'Scanner Engine',
                                    'icon' => 'scan',
                                    'active' => $scannerHubActive,
                                ])
                            @endif
                        @elseif (($item['type'] ?? null) === 'payouts')
                            @if ($anyPayoutAccess)
                                @include('partials.sidebar-link', [
                                    'href' => route('payouts.index'),
                                    'label' => 'Payouts',
                                    'icon' => 'dollar',
                                    'active' => $payoutHubActive,
                                ])
                            @endif
                        @elseif (($item['type'] ?? null) === 'group')
                            @php
                                // Two-pass: link visibility first, then grouplabels
                                // keep only when a visible link follows them before
                                // the next grouplabel (no orphan section headers).
                                $processed = array_map(function ($child) use ($acl, $user) {
                                    if (isset($child['grouplabel'])) {
                                        return ['child' => $child, 'visible' => false];
                                    }
                                    $hasPageAccess = ! isset($child['page']) || $acl->canAccessPage($user, $child['page']);
                                    $fallbackSuppresses = isset($child['fallback']) && $acl->canAccessPage($user, $child['fallback']);
                                    return ['child' => $child, 'visible' => $hasPageAccess && ! $fallbackSuppresses];
                                }, $item['children']);

                                $flag = false;
                                for ($i = count($processed) - 1; $i >= 0; $i--) {
                                    if (isset($processed[$i]['child']['grouplabel'])) {
                                        $processed[$i]['visible'] = $flag;
                                        $flag = false;
                                    } elseif ($processed[$i]['visible']) {
                                        $flag = true;
                                    }
                                }

                                $groupHasVisible = collect($processed)->contains('visible', true);
                                $groupActive = collect($item['children'])->contains(function ($child) {
                                    return isset($child['is'])
                                        && collect($child['is'])->contains(fn ($pattern) => request()->routeIs($pattern));
                                });
                            @endphp
                            @if ($groupHasVisible)
                                <div x-data="{ expanded: {{ $groupActive ? 'true' : 'false' }} }">
                                    <button type="button"
                                            class="sidebar-link sidebar-group-toggle"
                                            :aria-expanded="expanded.toString()"
                                            @click="expanded = !expanded"
                                            aria-controls="{{ $item['id'] }}">
                                        @include('partials.sidebar-icon', ['name' => $item['icon']])
                                        {{ $item['label'] }}
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="chevron ms-auto" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                                    </button>
                                    <div id="{{ $item['id'] }}" x-show="expanded" x-collapse>
                                        <div class="ms-[0.75rem] flex flex-col">
                                            @foreach ($processed as $entry)
                                                @if ($entry['visible'])
                                                    @if (isset($entry['child']['grouplabel']))
                                                        <span class="sidebar-section-label mt-2 first:mt-0">{{ $entry['child']['grouplabel'] }}</span>
                                                    @else
                                                        @include('partials.sidebar-link', [
                                                            'href' => route($entry['child']['route']),
                                                            'label' => $entry['child']['label'],
                                                            'icon' => $entry['child']['icon'],
                                                            'active' => collect($entry['child']['is'])->contains(fn ($pattern) => request()->routeIs($pattern)),
                                                        ])
                                                    @endif
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @else
                            @php
                                $hasPageAccess = ! isset($item['page']) || $acl->canAccessPage($user, $item['page']);
                                $fallbackSuppresses = isset($item['fallback']) && $acl->canAccessPage($user, $item['fallback']);
                            @endphp
                            @if ($hasPageAccess && ! $fallbackSuppresses)
                                @include('partials.sidebar-link', [
                                    'href' => route($item['route']),
                                    'label' => $item['label'],
                                    'icon' => $item['icon'],
                                    'active' => collect($item['is'])->contains(fn ($pattern) => request()->routeIs($pattern)),
                                ])
                            @endif
                        @endif
                    @endforeach
                </section>
            @endforeach
        </div>

        <div class="border-t border-white/10 px-3 py-3">
            <div class="flex items-center gap-3">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-pill bg-gold-dim text-dense font-bold text-gold">{{ strtoupper(substr(auth()->user()->username, 0, 2)) }}</span>
                <span class="truncate text-dense font-semibold text-white">{{ auth()->user()->username }}</span>
            </div>
        </div>
    </aside>
</div>

<script>
    (function () {
        document.addEventListener('alpine:init', function () {
            Alpine.store('sidebar', {
                open: false,
                toggle: function () { this.open = !this.open; },
                close: function () { this.open = false; }
            });
        });
    })();
</script>
