/* 2DMIS v2 — Shared FilterChips Component
   Prototype-aligned multi-select filter button + popover + applied chips.
   Sits ON TOP of existing server-side DataTables feeds: it never filters
   records client-side. It manages selection state, persists it to the URL
   query string (deep-link / refresh restore), and hands the active filter
   params to the hosting module via `onApply()` / `getParams()`, which then
   reload its existing feed with the same param names.

   Semantics (PHASE_2_PLAN §10, prototype filterMatches):
     - multiple values within one category  = OR
     - different active categories          = AND
     - global search stays a separate field and composes AND (module side)

   The municipality -> barangay cascade is handled here: barangay options
   carry their parent municipality id and are hidden/disabled when their
   parent municipality is not selected; clearing a municipality drops any
   barangay selection that no longer belongs to a selected municipality.
*/
const FilterChips = (function () {
    'use strict';

    const instances = {};

    /* ---------- helpers ---------- */

    function qs(scope, sel) {
        return scope.querySelector(sel);
    }
    function qsa(scope, sel) {
        return Array.prototype.slice.call(scope.querySelectorAll(sel));
    }
    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
    function parseCsv(value) {
        if (!value) return [];
        return String(value).split(',').map(function (v) { return v.trim(); }).filter(Boolean);
    }
    function joinCsv(values) {
        return values.join(',');
    }
    function getQuery() {
        return new URLSearchParams(window.location.search);
    }

    /* ---------- instance state ---------- */

    function createInstance(config) {
        const host = typeof config.host === 'string'
            ? document.querySelector(config.host)
            : config.host;
        if (!host) return null;

        const onApply = typeof config.onApply === 'function' ? config.onApply : function () {};
        const screen = host.closest('[data-filter-screen]') || document;

        /* Optional list of selectors whose clicks are treated as part of the
           filter popover's interactive surface. Used when a host drives the
           shared menu from external trigger buttons (e.g. the Clients module's
           per-filter pills): those pills live OUTSIDE the host, so the generic
           "click outside closes the menu" handler would otherwise close the
           menu on the very click that opened it. */
        const excludeClose = (config.excludeClose || []).map(function (s) {
            return typeof s === 'string' ? s : null;
        }).filter(Boolean);

        function isExcludedTarget(target) {
            if (!target || !target.closest) return false;
            if (host.contains(target)) return true;
            return excludeClose.some(function (sel) {
                return target.closest(sel) !== null;
            });
        }

        /* category definitions from the DOM */
        function readCategories() {
            const cats = [];
            qsa(host, '[data-filter-cat]').forEach(function (section) {
                const key = section.getAttribute('data-filter-cat');
                const catsDef = {
                    key: key,
                    label: qs(section, '.filter-multi-title')
                        ? qs(section, '.filter-multi-title').textContent.trim()
                        : key,
                    searchable: !!qs(section, '[data-filter-search]'),
                    dependsOn: section.getAttribute('data-filter-depends') || null,
                    feedParam: section.getAttribute('data-filter-feedparam') || key,
                    options: [],
                    values: [],
                };
                qsa(section, '[data-filter-option]').forEach(function (opt) {
                    catsDef.options.push({
                        value: opt.getAttribute('data-filter-value'),
                        label: opt.getAttribute('data-filter-label') || '',
                        muni: opt.getAttribute('data-filter-muni') || null,
                        el: opt,
                        check: qs(opt, '[data-filter-check]'),
                    });
                });
                cats.push(catsDef);
            });
            return cats;
        }

        function readDateRanges() {
            const ranges = [];
            qsa(host, '[data-filter-datecat]').forEach(function (section) {
                const key = section.getAttribute('data-filter-datecat');
                ranges.push({
                    key: key,
                    label: qs(section, '.filter-multi-title') ? qs(section, '.filter-multi-title').textContent.trim() : key,
                    feedParam: section.getAttribute('data-filter-feedparam') || key,
                    startParam: section.getAttribute('data-filter-startparam') || (key + '_start'),
                    endParam: section.getAttribute('data-filter-endparam') || (key + '_end'),
                    startInput: qs(section, '[data-filter-date-start="' + key + '"]'),
                    endInput: qs(section, '[data-filter-date-end="' + key + '"]'),
                    start: '',
                    end: '',
                });
            });
            return ranges;
        }

        const state = {
            toggle: qs(host, '[data-filter-toggle]'),
            menu: qs(host, '[data-filter-menu]'),
            chipsRow: qs(host, '[data-filter-chips]'),
            countBadge: qs(host, '[data-filter-count]'),
            clearAll: qs(host, '[data-filter-clear-all]'),
            clearCatBtns: qsa(host, '[data-filter-clear-cat]'),
            categories: readCategories(),
            dateRanges: readDateRanges(),
            lastApplied: null,
        };

        function categoryHasValues(key) {
            const cat = state.categories.find(function (c) { return c.key === key; });
            return !!(cat && cat.values.length > 0);
        }

        /* ---------- state <-> URL ---------- */

        function hydrateFromUrl() {
            const q = getQuery();
            state.categories.forEach(function (cat) {
                cat.values = parseCsv(q.get(cat.feedParam) || '');
            });
            state.dateRanges.forEach(function (range) {
                range.start = q.get(range.startParam) || '';
                range.end = q.get(range.endParam) || '';
            });
        }

        function activeCount() {
            let n = 0;
            state.categories.forEach(function (cat) { n += cat.values.length; });
            state.dateRanges.forEach(function (range) { if (range.start || range.end) n += 1; });
            return n;
        }

        function isValidBarangay(cat, value) {
            if (cat.dependsOn) {
                const parent = state.categories.find(function (c) { return c.key === cat.dependsOn; });
                if (!parent) return true;
                if (parent.values.length === 0) return false;
                const opt = cat.options.find(function (o) { return o.value === String(value); });
                return opt && parent.values.indexOf(opt.muni) !== -1;
            }
            return true;
        }

        function sanitize() {
            state.categories.forEach(function (cat) {
                if (!cat.dependsOn) return;
                cat.values = cat.values.filter(function (v) { return isValidBarangay(cat, v); });
            });
        }

        function toParams() {
            const params = {};
            state.categories.forEach(function (cat) {
                if (cat.values.length > 0) {
                    params[cat.feedParam] = cat.values.length === 1 ? cat.values[0] : joinCsv(cat.values);
                }
            });
            state.dateRanges.forEach(function (range) {
                if (range.start) params[range.startParam] = range.start;
                if (range.end) params[range.endParam] = range.end;
            });
            return params;
        }

        function currentUrl() {
            return window.location.pathname + '?' + new URLSearchParams(toParams()).toString();
        }

        function persistUrl() {
            const params = toParams();
            const qsString = new URLSearchParams(params).toString();
            const url = qsString ? window.location.pathname + '?' + qsString : window.location.pathname;
            history.replaceState(null, '', url);
        }

        /* ---------- rendering ---------- */

        function labelForValue(cat, value) {
            const opt = cat.options.find(function (o) { return o.value === String(value); });
            return opt ? opt.label : String(value);
        }

        function renderChips() {
            if (!state.chipsRow) return;
            state.chipsRow.innerHTML = '';

            if (activeCount() === 0) {
                state.chipsRow.hidden = true;
                return;
            }
            state.chipsRow.hidden = false;

            state.categories.forEach(function (cat) {
                cat.values.forEach(function (value) {
                    const chip = document.createElement('span');
                    chip.className = 'filter-chip';
                    chip.textContent = cat.label + ': ' + labelForValue(cat, value);
                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'filter-chip-remove';
                    remove.setAttribute('aria-label', 'Remove filter ' + cat.label + ': ' + labelForValue(cat, value));
                    remove.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
                    remove.addEventListener('click', function () {
                        removeValue(cat.key, value);
                    });
                    chip.appendChild(remove);
                    state.chipsRow.appendChild(chip);
                });
            });

            state.dateRanges.forEach(function (range) {
                if (!range.start && !range.end) return;
                const chip = document.createElement('span');
                chip.className = 'filter-chip';
                chip.textContent = range.label + ': ' + (range.start || '.....') + ' → ' + (range.end || '.....');
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'filter-chip-remove';
                remove.setAttribute('aria-label', 'Remove filter ' + range.label);
                remove.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
                remove.addEventListener('click', function () {
                    clearDate(range.key, true);
                });
                chip.appendChild(remove);
                state.chipsRow.appendChild(chip);
            });

            announce('Filters updated');
        }

        function renderCount() {
            const n = activeCount();
            if (state.countBadge) {
                state.countBadge.textContent = String(n);
                state.countBadge.hidden = n === 0;
            }
            if (state.clearAll) {
                state.clearAll.hidden = n === 0;
            }
            // Keep each filter's individual Clear control in step with the
            // current (possibly client-side, not-yet-reloaded) selection so a
            // filter never shows a stale Clear visibility after commit.
            state.clearCatBtns.forEach(function (btn) {
                btn.hidden = !categoryHasValues(btn.getAttribute('data-filter-clear-cat'));
            });
        }

        function syncChecks() {
            state.categories.forEach(function (cat) {
                cat.options.forEach(function (opt) {
                    if (opt.check) {
                        opt.check.checked = cat.values.indexOf(opt.value) !== -1;
                    }
                });
            });
            state.dateRanges.forEach(function (range) {
                if (range.startInput) range.startInput.value = range.start;
                if (range.endInput) range.endInput.value = range.end;
            });
        }

        function applyCascadeVisibility() {
            state.categories.forEach(function (cat) {
                if (!cat.dependsOn) return;
                const parent = state.categories.find(function (c) { return c.key === cat.dependsOn; });
                const hasSelection = parent ? parent.values.length > 0 : false;
                cat.options.forEach(function (opt) {
                    // When no parent municipality is selected the child options
                    // are shown unrestrained (a barangay selection is valid even
                    // without a municipality — see isValidBarangay). Once a
                    // municipality is chosen they narrow to that municipality's
                    // barangays. Without this, opening the Barangay filter with
                    // no municipality selected showed an empty list until the
                    // user typed in the search box.
                    const visible = !hasSelection || (opt.muni != null && parent.values.indexOf(opt.muni) !== -1);
                    opt.el.hidden = !visible;
                });
                const section = cat.options.length ? cat.options[0].el.closest('[data-filter-cat]') : null;
                if (section && parent) {
                    const noParent = parent.values.length === 0;
                    section.classList.toggle('is-disabled', noParent);
                }
            });
        }

        let announceTimer = null;
        function announce(message) {
            if (!state.chipsRow) return;
            let live = state.chipsRow.getAttribute('aria-live') ? state.chipsRow : null;
            if (!live) return;
            if (announceTimer) clearTimeout(announceTimer);
            announceTimer = setTimeout(function () {
                const line = document.createElement('span');
                line.className = 'sr-only';
                line.style.cssText = 'position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);';
                line.textContent = message;
                state.chipsRow.appendChild(line);
                setTimeout(function () { line.remove(); }, 500);
            }, 80);
        }

        /* ---------- mutations ---------- */

        function commit() {
            sanitize();
            persistUrl();
            renderChips();
            renderCount();
            syncChecks();
            applyCascadeVisibility();
            onApply(toParams());
        }

        function setCategoryValues(key, values) {
            const cat = state.categories.find(function (c) { return c.key === key; });
            if (!cat) return;
            cat.values = values.slice();
            commit();
        }

        function removeValue(key, value) {
            const cat = state.categories.find(function (c) { return c.key === key; });
            if (!cat) return;
            cat.values = cat.values.filter(function (v) { return v !== String(value); });
            commit();
        }

        function clearCategory(key) {
            setCategoryValues(key, []);
        }

        function clearDate(key, alsoCommit) {
            const range = state.dateRanges.find(function (r) { return r.key === key; });
            if (!range) return;
            range.start = '';
            range.end = '';
            if (alsoCommit) commit();
        }

        function clearAll() {
            state.categories.forEach(function (cat) { cat.values = []; });
            state.dateRanges.forEach(function (range) { range.start = ''; range.end = ''; });
            commit();
        }

        /* ---------- menu behavior ---------- */

        function showMenu() {
            state.categories.forEach(function (cat) {
                const sec = cat.options.length ? cat.options[0].el.closest('[data-filter-cat]') : null;
                if (sec) {
                    sec.hidden = false;
                    qsa(sec, '[data-filter-option]').forEach(function (opt) { opt.hidden = false; });
                }
            });
        }

        /* Reveal a single category section and hide the others, so an external
           per-filter pill opens a focused popover for just that filter. */
        function revealCategory(key) {
            qsa(host, '[data-filter-cat]').forEach(function (sec) {
                sec.hidden = sec.getAttribute('data-filter-cat') !== key;
            });
            applyCascadeVisibility();
            const input = qs(host, '[data-filter-search="' + key + '"]');
            if (input) input.value = '';
        }

        /* onlyKey: when given, open the popover showing just that category. */
        function openMenu(onlyKey) {
            state.menu.hidden = false;
            if (onlyKey) revealCategory(onlyKey);
            else showMenu();
            if (state.toggle) state.toggle.setAttribute('aria-expanded', 'true');
            document.addEventListener('keydown', trapKey);
            requestAnimationFrame(function () {
                const scope = onlyKey
                    ? qs(host, '[data-filter-cat="' + onlyKey + '"]') || state.menu
                    : state.menu;
                const first = qs(scope, 'input, button, select, [tabindex]:not([tabindex="-1"])');
                if (first) first.focus();
            });
        }

        function closeMenu() {
            state.menu.hidden = true;
            if (state.toggle) {
                state.toggle.setAttribute('aria-expanded', 'false');
                state.toggle.focus();
            }
            document.removeEventListener('keydown', trapKey);
        }

        function toggleMenu() {
            if (!state.menu || state.menu.hidden === true) openMenu();
            else closeMenu();
        }

        function trapKey(e) {
            if (e.key === 'Escape') { e.preventDefault(); closeMenu(); return; }
            if (e.key !== 'Tab') return;
            if (!state.menu || state.menu.hidden) return;
            const focusables = qsa(state.menu, 'input:not([disabled]), button:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])');
            if (!focusables.length) return;
            const first = focusables[0];
            const last = focusables[focusables.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }

        /* ---------- search within a category ---------- */

        function bindSearch() {
            qsa(host, '[data-filter-search]').forEach(function (input) {
                const key = input.getAttribute('data-filter-search');
                const optionsBox = qs(host, '[data-filter-options="' + key + '"]');
                if (!optionsBox) return;
                input.addEventListener('input', function () {
                    const term = input.value.trim().toLowerCase();
                    qsa(optionsBox, '[data-filter-option]').forEach(function (opt) {
                        if (!term) { opt.hidden = false; return; }
                        const label = (opt.getAttribute('data-filter-label') || '').toLowerCase();
                        opt.hidden = label.indexOf(term) === -1;
                    });
                });
            });
        }

        /* ---------- events ---------- */

        function bindEvents() {
            if (state.toggle) state.toggle.addEventListener('click', toggleMenu);

            if (state.clearAll) state.clearAll.addEventListener('click', clearAll);

            qsa(host, '[data-filter-clear-cat]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    clearCategory(btn.getAttribute('data-filter-clear-cat'));
                });
            });

            // Delegated change handling so options added later (e.g. audit
            // user/action lists fed from the feed) still apply.
            host.addEventListener('change', function (e) {
                const check = e.target.closest ? e.target.closest('[data-filter-check]') : null;
                if (!check) return;
                const ref = (check.getAttribute('data-filter-check') || '').split('|');
                const key = ref[0];
                const value = ref[1];
                const cat = state.categories.find(function (c) { return c.key === key; });
                if (!cat) return;
                const values = cat.values.slice();
                const idx = values.indexOf(String(value));
                if (check.checked) {
                    if (idx === -1) values.push(String(value));
                } else if (idx !== -1) {
                    values.splice(idx, 1);
                }
                setCategoryValues(key, values);
            });

            qsa(host, '[data-filter-date-clear]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    clearDate(btn.getAttribute('data-filter-date-clear'), true);
                });
            });

            state.dateRanges.forEach(function (range) {
                if (range.startInput) range.startInput.addEventListener('change', function () {
                    range.start = range.startInput.value;
                    commit();
                });
                if (range.endInput) range.endInput.addEventListener('change', function () {
                    range.end = range.endInput.value;
                    commit();
                });
            });

            document.addEventListener('click', function (e) {
                if (state.menu && state.menu.hidden === false && !isExcludedTarget(e.target)) {
                    closeMenu();
                }
            });
        }

        function syncFromDomSelected() {
            /* If server rendered checked boxes (deep-link hydration) match them in. */
            state.categories.forEach(function (cat) {
                cat.options.forEach(function (opt) {
                    if (opt.check && opt.check.checked && cat.values.indexOf(opt.value) === -1) {
                        cat.values.push(opt.value);
                    }
                });
            });
        }

        /* Replace a category's option list after init (feed-fed lists such as
           audit user/action options). Preserves existing selections that are
           still present; drops selections whose option disappeared. */
        function setOptions(key, options) {
            const cat = state.categories.find(function (c) { return c.key === key; });
            if (!cat) return;
            const box = qs(host, '[data-filter-options="' + key + '"]');
            if (!box) return;

            box.innerHTML = '';
            cat.options = [];

            (options || []).forEach(function (opt) {
                const value = String(opt.value);
                const label = String(opt.label);

                const labelEl = document.createElement('label');
                labelEl.className = 'filter-check';
                labelEl.setAttribute('data-filter-option', '');
                labelEl.setAttribute('data-filter-cat', key);
                labelEl.setAttribute('data-filter-value', value);
                labelEl.setAttribute('data-filter-label', label);
                if (opt.muni) labelEl.setAttribute('data-filter-muni', String(opt.muni));

                const check = document.createElement('input');
                check.type = 'checkbox';
                check.setAttribute('data-filter-check', key + '|' + value);
                check.checked = cat.values.indexOf(value) !== -1;

                const span = document.createElement('span');
                span.className = 'filter-check-label';
                span.textContent = label;

                labelEl.appendChild(check);
                labelEl.appendChild(span);
                box.appendChild(labelEl);

                cat.options.push({
                    value: value,
                    label: label,
                    muni: opt.muni ? String(opt.muni) : null,
                    el: labelEl,
                    check: check,
                });
            });

            cat.values = cat.values.filter(function (v) {
                return cat.options.some(function (o) { return o.value === v; });
            });

            renderChips();
            renderCount();
            applyCascadeVisibility();
        }

        function init() {
            hydrateFromUrl();
            syncFromDomSelected();
            sanitize();
            renderChips();
            renderCount();
            syncChecks();
            applyCascadeVisibility();
            bindEvents();
            bindSearch();
        }

        init();

        return {
            getParams: toParams,
            activeCount: activeCount,
            clearAll: clearAll,
            removeValue: removeValue,
            setCategoryValues: setCategoryValues,
            clearCategory: clearCategory,
            clearDate: clearDate,
            setOptions: setOptions,
            close: closeMenu,
            open: openMenu,
            openCategory: function (key) { openMenu(key); },
        };
    }

    return {
        init: function (config) {
            const id = config.id || (config.host && (typeof config.host === 'string' ? config.host : 'default'));
            instances[id] = createInstance(config);
            return instances[id];
        },
        get: function (id) {
            return instances[id] || null;
        },
    };
})();

if (typeof window !== 'undefined') {
    window.FilterChips = FilterChips;
}
