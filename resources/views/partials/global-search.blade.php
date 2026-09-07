{{-- Prototype-aligned global search in topbar with autocomplete dropdown --}}
<div class="topbar-search relative hidden sm:block" style="width:320px;max-width:100%">
    <svg viewBox="0 0 24 24" aria-hidden="true" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);width:15px;height:15px;stroke:var(--color-ink-muted);fill:none;stroke-width:2">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
    </svg>
    <input type="search" id="globalSearch" placeholder="Search clients…"
           role="combobox" aria-label="Search clients" aria-autocomplete="list"
           aria-controls="globalSearchDropdown" aria-expanded="false"
           autocomplete="off"
           class="w-full py-[8px] pl-[36px] pr-[14px] text-[13px] leading-[20px] text-[var(--color-ink)] bg-[#f0f2f5] border border-[#e2e5ea] rounded-full outline-none transition-all duration-200 focus:border-[var(--ui-gold)] focus:bg-white focus:ring-2 focus:ring-[var(--ui-gold)]/30">

    <div id="globalSearchDropdown"
         role="listbox" aria-label="Search results"
         style="display:none;position:absolute;top:calc(100% + 6px);left:0;right:0;
                background:var(--ui-card,#fff);border:1px solid var(--ui-border-light,#e2e5ea);
                border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:1060;
                max-height:380px;overflow-y:auto;overflow-x:hidden">
    </div>
</div>

<script>
(function () {
    var input = document.getElementById('globalSearch');
    var dropdown = document.getElementById('globalSearchDropdown');
    if (!input || !dropdown) return;

    var searchEndpoint = '{{ route("global-search") }}';
    var timer = null;
    var activeIndex = -1;
    var currentResults = [];

    /* ── Helpers ────────────────────────────────────────────── */

    function esc(s) {
        var el = document.createElement('span');
        el.textContent = s;
        return el.innerHTML;
    }

    function hide() {
        dropdown.style.display = 'none';
        input.setAttribute('aria-expanded', 'false');
        activeIndex = -1;
        currentResults = [];
    }

    function show() {
        dropdown.style.display = 'block';
        input.setAttribute('aria-expanded', 'true');
    }

    function renderLoading() {
        dropdown.innerHTML =
            '<div style="padding:14px 16px;text-align:center;color:var(--ui-text-muted,#9ca3af);font-size:13px">Searching…</div>';
        show();
    }

    function renderResults(results) {
        if (results.length === 0) {
            dropdown.innerHTML =
                '<div style="padding:14px 16px;text-align:center;color:var(--ui-text-muted,#9ca3af);font-size:13px">No clients found</div>';
            show();
            return;
        }

        currentResults = results;
        activeIndex = -1;

        var html = '';
        for (var i = 0; i < results.length; i++) {
            var r = results[i];
            var sexLabel = r.sex === 'MALE' ? 'M' : r.sex === 'FEMALE' ? 'F' : r.sex || '';
            var location = [r.barangay, r.municipality].filter(Boolean).join(', ');
            html +=
                '<div role="option" data-index="' + i + '" data-url="' + esc(r.url) + '"' +
                ' style="display:flex;align-items:center;gap:10px;padding:10px 16px;cursor:pointer;border-bottom:1px solid var(--ui-border-light,#f0f2f5);transition:background .12s"' +
                ' onmouseenter="this.style.background=\'var(--ui-bg-alt,#f8f9fa)\';this._over=true"' +
                ' onmouseleave="this.style.background=\'transparent\';this._over=false">' +
                    '<div style="grid-column:span 2/place-items:center;width:34px;height:34px;min-width:34px;border-radius:9999px;background:rgba(0,56,168,.08);color:var(--ui-navy,#0038A8);font-size:12px;font-weight:600;display:grid">' +
                        esc(r.full_name.split(',')[0].substring(0, 2)) +
                    '</div>' +
                    '<div style="min-width:0;flex:1">' +
                        '<div style="font-size:13px;font-weight:600;color:var(--ui-text-primary,#111827);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' +
                            esc(r.full_name) +
                        '</div>' +
                        '<div style="font-size:11.5px;color:var(--ui-text-muted,#9ca3af);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px">' +
                            (sexLabel ? esc(sexLabel) + (r.age ? ' · ' + esc(String(r.age)) : '') : '') +
                            (location ? (sexLabel ? ' · ' : '') + esc(location) : '') +
                        '</div>' +
                    '</div>' +
                '</div>';
        }

        dropdown.innerHTML = html;
        show();
    }

    function setActive(index) {
        var items = dropdown.querySelectorAll('[role="option"]');
        for (var i = 0; i < items.length; i++) {
            items[i].style.background = i === index ? 'var(--ui-bg-alt,#f8f9fa)' : 'transparent';
        }
        activeIndex = index;
        if (index >= 0 && items[index]) {
            items[index].scrollIntoView({ block: 'nearest' });
        }
    }

    /* ── Fetch ──────────────────────────────────────────────── */

    function search(query) {
        if (query.length < 2) {
            hide();
            return;
        }

        renderLoading();

        fetch(searchEndpoint + '?q=' + encodeURIComponent(query), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (res) {
                if (!res.ok) throw new Error('network');
                return res.json();
            })
            .then(function (json) {
                renderResults(json.results || []);
            })
            .catch(function () {
                dropdown.innerHTML =
                    '<div style="padding:14px 16px;text-align:center;color:var(--ui-text-muted,#9ca3af);font-size:13px">Search unavailable</div>';
                show();
            });
    }

    /* ── Also filter client table when on clients page ──────── */

    function filterClientTable(query) {
        var currentRoute = window.location.pathname;
        if (currentRoute.startsWith('/clients') && window.clientsTable && typeof window.clientsTable.search === 'function') {
            window.clientsTable.search(query).draw();
        }
    }

    /* ── Event handlers ─────────────────────────────────────── */

    input.addEventListener('input', function () {
        clearTimeout(timer);
        var query = input.value.trim();
        timer = setTimeout(function () {
            search(query);
            filterClientTable(query);
        }, 250);
    });

    input.addEventListener('keydown', function (e) {
        var len = currentResults.length;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive(len > 0 ? (activeIndex + 1) % len : -1);
            return;
        }

        if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive(len > 0 ? (activeIndex - 1 + len) % len : -1);
            return;
        }

        if (e.key === 'Enter') {
            e.preventDefault();

            if (activeIndex >= 0 && currentResults[activeIndex]) {
                window.location.href = currentResults[activeIndex].url;
                return;
            }

            var query = input.value.trim();
            if (query) {
                window.location.href = '/clients?search=' + encodeURIComponent(query);
            }
            return;
        }

        if (e.key === 'Escape') {
            hide();
            input.blur();
        }
    });

    input.addEventListener('focus', function () {
        var query = input.value.trim();
        if (query.length >= 2 && dropdown.children.length > 0 && dropdown.style.display !== 'none') {
            show();
        }
    });

    /* ── Click on result ────────────────────────────────────── */

    dropdown.addEventListener('click', function (e) {
        var item = e.target.closest('[data-url]');
        if (item) {
            window.location.href = item.getAttribute('data-url');
        }
    });

    /* ── Outside click ──────────────────────────────────────── */

    document.addEventListener('mousedown', function (e) {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            hide();
        }
    });
})();
</script>
