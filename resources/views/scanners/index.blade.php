@extends('layouts.app')

@section('title', 'Scanner Engine — 2D MIS')

@section('content')

    @include('partials.page-header', [
        'title' => 'Scanner Engine',
        'subtitle' => 'Pick a scanner to open',
    ])

    @if (empty($scanners))
        <div class="data-card">
            <div class="data-card-body">
                <p class="m-0 py-6 text-center text-ink-muted">No scanners are available for your account.</p>
            </div>
        </div>
    @else
        {{-- UX-5: unified scanner workspace. Presentation/navigation only:
             selecting a scanner opens the EXISTING config-driven scan shell
             (scanners.{key}). The 14 scanner configurations, per-key routes,
             permission keys, duplicate rules and scan semantics are untouched —
             options are the same ACL-filtered list the hub previously rendered. --}}
        <section class="data-card" aria-label="Scanner workspace">
            <div class="data-card-body">
                <label for="scannerSelect" class="field-label">Scanner type</label>
                <div class="flex flex-wrap items-end gap-3">
                    <select id="scannerSelect" class="field-control min-w-[260px]" aria-describedby="scannerHint">
                        @foreach ($scanners as $scanner)
                            <option value="{{ $scanner['url'] }}" data-programs="{{ implode('|', $scanner['programs']) }}">{{ $scanner['title'] }}</option>
                        @endforeach
                    </select>
                    <a href="{{ $scanners[0]['url'] }}" id="scannerOpen" class="btn-gold no-underline">Open Scanner</a>
                    <button type="button" id="scannerReset" class="btn-subtle">Reset</button>
                </div>
                <p id="scannerHint" class="m-0 mt-2 text-micro text-ink-muted">
                    Select the scanner you need, then open it. Access is governed by
                    your existing scanner permissions.
                </p>
                <div id="scannerPrograms" class="mt-3 flex flex-wrap gap-[0.375rem]" aria-live="polite"></div>
            </div>
        </section>

        @push('scripts')
            <script>
                (function () {
                    var select = document.getElementById('scannerSelect');
                    var open = document.getElementById('scannerOpen');
                    var reset = document.getElementById('scannerReset');
                    var programs = document.getElementById('scannerPrograms');
                    if (!select || !open || !programs) return;

                    function renderPrograms() {
                        var opt = select.options[select.selectedIndex];
                        if (!opt) return;
                        var list = (opt.getAttribute('data-programs') || '').split('|').filter(Boolean);
                        open.setAttribute('href', opt.value);
                        programs.innerHTML = '';
                        if (!list.length) {
                            programs.innerHTML = '<span class="text-micro text-ink-muted">General purpose</span>';
                            return;
                        }
                        list.forEach(function (p) {
                            var b = document.createElement('span');
                            b.className = 'status-badge is-neutral';
                            b.textContent = p;
                            programs.appendChild(b);
                        });
                    }
                    select.addEventListener('change', renderPrograms);
                    reset.addEventListener('click', function () { select.selectedIndex = 0; renderPrograms(); });
                    renderPrograms();
                })();
            </script>
        @endpush
    @endif
@endsection
