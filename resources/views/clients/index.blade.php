@extends('layouts.app')

@section('title', 'Clients — 2D MIS')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/datatables.css') }}">
    <style>
        /* ── Clients screen scope: token skin over the DataTables
           Bootstrap integration. Selectors are prefixed with
           #clients-screen — nothing here can leak to other screens. ── */
        #clients-screen table.dataTable {
            font-size: 0.875rem;
        }

        #clients-screen table.dataTable thead th {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            background-color: var(--color-navy);
            color: #fff;
            border-bottom: 0;
            white-space: nowrap;
            text-transform: uppercase;
            padding-top: 0.625rem;
            padding-bottom: 0.625rem;
        }

        #clients-screen table.dataTable tbody td {
            padding-top: 0.625rem;
            padding-bottom: 0.625rem;
            vertical-align: middle;
        }

        #clients-screen table.dataTable tbody tr {
            cursor: pointer;
        }

        #clients-screen table.dataTable tbody tr:nth-child(odd) td {
            background-color: rgb(15 27 45 / 0.02);
        }

        #clients-screen table.dataTable tbody tr:hover td {
            background-color: rgb(37 99 235 / 0.06);
        }

        #clients-screen table.dataTable tbody tr:focus-visible {
            outline: 2px solid var(--shadow-focus);
            outline-offset: -2px;
        }

        /* Client cell: name over a muted "Client ID" caption line */
        #clients-screen .client-cell {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }

        #clients-screen .client-cell-name {
            font-weight: 600;
            color: var(--color-ink);
        }

        #clients-screen .client-cell-id {
            font-size: 0.72rem;
            color: var(--color-ink-muted);
        }

        /* Actions column: quiet icon buttons, never a row-click target */
        #clients-screen .actions-col {
            width: 112px;
            min-width: 112px;
            max-width: 112px;
            text-align: center;
            white-space: nowrap;
        }

        #clients-screen .actions-col form {
            display: inline;
        }

        #clients-screen .actions-col .icon-btn {
            margin: 0 2px;
            vertical-align: middle;
        }

        /* Segment filter buttons (Municipality / Barangay / Program / Category):
           pill-shaped pills in a shared filter toolbar. */
        #clients-screen .filter-toolbar {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px;
            /* background: var(--color-bg); */
            /* border: 1px solid var(--color-line); */
            border-radius: 9999px;
        }

        #clients-screen .seg-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0.45rem 0.9rem;
            border-radius: 9999px;
            background: var(--color-bg);
            border: 1px solid var(--color-line);
            color: var(--color-ink-secondary);
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: all 180ms cubic-bezier(0.4, 0, 0.2, 1);
        }

        #clients-screen .seg-btn .seg-btn-icon {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
            stroke: currentColor;
        }

        #clients-screen .seg-btn:hover {
            background: var(--color-surface);
            color: var(--color-navy);
        }

        #clients-screen .seg-btn[aria-pressed="true"],
        #clients-screen .seg-btn.is-active {
            background: var(--color-surface);
            border-color: var(--color-navy);
            color: var(--color-navy);
        }

        #clients-screen .seg-btn:focus-visible {
            outline: 2px solid var(--shadow-focus);
            outline-offset: 2px;
        }

        #clients-screen .seg-count {
            display: inline-grid;
            place-items: center;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 999px;
            background: var(--color-gold);
            color: var(--color-navy);
            font-size: 0.68rem;
            font-weight: 700;
            line-height: 1;
        }

        /* The segmented buttons replace the shared FilterChips toggle; the
           popover, chips row and Clear All stay component-owned. */
        #clients-screen .filter-chips-toolbar {
            display: none;
        }

        /* DataTables chrome (length/info/pagination) aligned to tokens. */
        #clients-screen .dataTables_wrapper .dataTables_length select,
        #clients-screen .dataTables_wrapper .dataTables_filter input {
            border: 1px solid var(--color-line);
            border-radius: var(--radius-control);
            /* padding: 0.25rem 0.5rem; */
            font-size: 0.85rem;
        }

        #clients-screen .dataTables_wrapper .dataTables_paginate .page-item.active .page-link {
            background-color: var(--color-navy);
            border-color: var(--color-navy);
        }

        #clients-screen .page-link {
            color: var(--color-navy);
        }

        /* ── Filter popover reliability ──
           The shared FilterChips menu is absolutely positioned inside the
           screen's .data-card, whose Tailwind `overflow-hidden` clips it
           (rounded-corner clipping) and makes the choices unreliable. Release
           the clip for the clients card only and lift the menu above the table
           chrome so every segment opens visibly. */
        #clients-screen .data-card {
            overflow: visible;
        }

        #clients-screen .filter-chips {
            position: relative;
        }

        #clients-screen .filter-chips .filter-multi-menu {
            z-index: 1080;
            left: 0;
        }

        /* Modal validation feedback: highlight invalid fields in the modal
           body (server-driven, modal-native — no native browser popups). */
        #clientFormModalBody .form-control.is-invalid,
        #clientFormModalBody .form-select.is-invalid {
            border-color: var(--color-red, #dc2626);
            box-shadow: 0 0 0 0.15rem rgb(220 38 38 / 0.12);
        }

        /* ── Pagination / entries presentation ──
           Top row: "Show entries" left + compact windowed pager right, all on
           one line. Bottom row: normal-size admin "Showing X to Y of Z
           entries" text + the same pager. windowing already uses a 5-number
           sliding window (numbers_length=5) with ellipsis from DataTables. */
        #clients-screen .dataTables_wrapper {
            position: relative;
        }

        #clients-screen .dataTables_wrapper .dataTables_info {
            font-size: 0.8125rem;
            color: var(--color-ink-muted);
            padding-top: 0.75rem;
        }

        #clients-screen .dataTables_wrapper .dataTables_length {
            font-size: 0.8125rem;
            color: var(--color-ink-muted);
            padding-top: 0.75rem;
        }

        #clients-screen .dataTables_wrapper .dataTables_length select {
            margin: 0 0.25rem;
        }

        #clients-screen .dataTables_wrapper .dataTables_paginate {
            padding-top: 0.75rem;
        }

        #clients-screen .dataTables_wrapper .page-link {
            font-size: 0.8125rem;
            min-width: 32px;
            text-align: center;
        }

        /* Top chrome (entry count) and bottom chrome (info + single pager)
           each sit on one horizontal line, aligned around the table. */
        #clients-screen .dataTables_wrapper .top-chrome,
        #clients-screen .dataTables_wrapper .bottom-chrome {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        /* UX polish: quiet "X of N clients" counter beside the filter pills,
           shown only while a filter/search narrows the result set. */
        #clients-screen .result-count {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--color-ink-muted);
            white-space: nowrap;
        }

        /* UX polish (mobile): enlarge the tap area of the segment filter pills
           without changing the desktop layout. */
        @media (max-width: 767px) {
            #clients-screen .seg-btn {
                min-height: 40px;
                padding-left: 1rem;
                padding-right: 1rem;
                font-size: 0.85rem;
            }
            #clients-screen .filter-toolbar {
                gap: 6px;
            }
        }

        /* Narrow screens: horizontal scrolling applies ONLY to the actual
           table when its columns don't fit. The table (block + its own
           overflow) becomes the scroll region, so the DataTables info text
           and Previous/page-number/Next pager (.bottom-chrome) remain fully
           visible beneath it and never scroll away with the columns. */
        @media (max-width: 767px) {
            #clients-screen #clientsTable {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
@endpush

@section('content')

    @include('partials.page-header', [
        'title' => 'Client Registry',
        'subtitle' => 'Click any row to open the resident details panel.',
        'actions' => '
            <button type="button" class="btn-gold" onclick="openAddClientModal()">+ Add Client</button>
            <div class="btn-group" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                <button type="button" class="btn-subtle dropdown-toggle" @click="open = !open" :aria-expanded="open.toString()">
                    Export CSV
                </button>
                <ul class="dropdown-menu dropdown-menu-end" data-bs-popper :class="{ \'dropdown-open\': open }">
                    <li><button type="button" class="dropdown-item" data-clients-export="filter">Export Current Filter</button></li>
                    <li><button type="button" class="dropdown-item" data-clients-export="all">Export All Clients</button></li>
                </ul>
            </div>',
    ])

    <div class="pointer-events-none fixed inset-x-0 top-[76px] z-[1100] flex flex-col items-end gap-2 px-[1rem] sm:px-[1.75rem]" id="clientsToastStack" aria-live="polite"></div>

    @if (session('success'))
        {{-- Same persistent toast pattern as the layout flash channel:
             manual dismiss, live-region semantics, no silent expiry.
             Phase 9: no Bootstrap Toast JS — revealed with `.show` by the
             wireFlashToast() helper, close handled manually. --}}
        <div class="pointer-events-none fixed inset-x-0 top-20 z-[1100] flex flex-col items-end gap-2 px-[1rem] sm:px-[1.75rem]" aria-live="polite">
            <div class="toast pointer-events-auto flex w-full max-w-[420px] items-start gap-[12px] rounded-panel bg-surface p-[1rem] shadow-pop ring-1 ring-line"
                 role="status">
                <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-pill bg-teal/[0.12] text-teal" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                <div class="min-w-0 flex-1 text-dense leading-snug text-ink">{{ session('success') }}</div>
                <button type="button" class="btn-close shrink-0" aria-label="Close"></button>
            </div>
        </div>
    @endif

    <div id="clients-screen">
        <section class="data-card" aria-label="Client registry">
            <div class="data-card-body flex flex-col gap-[14px]">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="filter-toolbar flex flex-wrap items-center gap-2">
                        <button type="button" class="seg-btn" data-filter-segment="municipality"
                                title="Filter by municipality" aria-label="Filter by municipality" aria-haspopup="dialog" aria-expanded="false" aria-pressed="false">
                            <svg viewBox="0 0 24 24" aria-hidden="true" class="seg-btn-icon" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/>
                            </svg>
                            Municipality <span class="seg-count" hidden aria-hidden="true"></span>
                        </button>
                        <button type="button" class="seg-btn" data-filter-segment="barangay"
                                title="Filter by barangay" aria-label="Filter by barangay" aria-haspopup="dialog" aria-expanded="false" aria-pressed="false">
                            <svg viewBox="0 0 24 24" aria-hidden="true" class="seg-btn-icon" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/>
                            </svg>
                            Barangay <span class="seg-count" hidden aria-hidden="true"></span>
                        </button>
                        <button type="button" class="seg-btn" data-filter-segment="program"
                                title="Filter by program" aria-label="Filter by program" aria-haspopup="dialog" aria-expanded="false" aria-pressed="false">
                            <svg viewBox="0 0 24 24" aria-hidden="true" class="seg-btn-icon" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/>
                            </svg>
                            Programs <span class="seg-count" hidden aria-hidden="true"></span>
                        </button>
                        <button type="button" class="seg-btn" data-filter-segment="category"
                                title="Filter by category" aria-label="Filter by category" aria-haspopup="dialog" aria-expanded="false" aria-pressed="false">
                            <svg viewBox="0 0 24 24" aria-hidden="true" class="seg-btn-icon" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/>
                            </svg>
                            Category <span class="seg-count" hidden aria-hidden="true"></span>
                        </button>
                        <button type="button" class="filter-clear-all" id="clientsClearAll" hidden
                                aria-label="Clear all filters">Clear All</button>
                        <span id="clientsResultCount" class="result-count" hidden aria-live="polite"></span>
                    </div>
                    <div class="min-w-0 flex-1 basis-56 max-w-md">
                        <div class="relative">
                            <svg viewBox="0 0 24 24" aria-hidden="true" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:14px;height:14px;stroke:var(--color-ink-muted);fill:none;stroke-width:2;pointer-events:none;">
                                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            </svg>
                            <input type="search" id="clientsSearch" placeholder="Search name, precinct no., municipality, barangay..."
                                   aria-label="Search clients"
                                   class="w-full pl-10 pr-4 py-2 text-sm border border-[#e2e5ea] rounded-full focus:border-[#fcd116] focus:ring-2 focus:ring-[#fcd116]/30 focus:bg-white outline-none transition-all duration-200"
                                   style="font-family:inherit;color:var(--color-ink);background:var(--color-bg);">
                        </div>
                    </div>
                </div>

                @include('partials.filter-chips', ['filterChips' => $filterChips])
            </div>

            <div class="px-[1.25rem] pb-[1.25rem]">
                <table id="clientsTable" class="table table-sm scrollbars-subtle" style="width:100%;" tabindex="0" aria-label="Client table, scrolls horizontally on narrow screens">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Precinct No</th>
                            <th>Municipality</th>
                            <th>Barangay</th>
                            <th>Category</th>
                            <th class="actions-col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Loaded via AJAX (server-side DataTables) --}}
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @include('partials.confirm-modal')

    {{-- Add/Edit Client Modal — Tailwind + Alpine.js (Phase 8). Kept in its
         own partial so Blade compiles its Alpine @directives in isolation
         (large-file PCRE interaction otherwise left a later @if un-compiled). --}}
    @include('partials.client-form-modal')

     {{-- Validation / feedback modal — separate from the client form so the
          user can review errors/messages, dismiss, and return to the
          (still-editable) form without losing entered values. Reuses the shared
          modal idiom (Tailwind + Alpine, Phase 9 → partial). Supports
          INFO / WARNING / ERROR. The footer action area is dynamic
          (#clientFeedbackActions); the X/close control dismisses back to the
          still-editable edit modal. --}}
    @include('partials.client-feedback-modal')
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="{{ asset('js/components/DetailsPanel.js') }}"></script>
    <script src="{{ asset('js/components/FilterChips.js') }}"></script>

    {{-- Alpine store + component for the client form modal (Phase 8).
         The store holds shared state; window.openAddClientModal / openEditModal /
         closeClientModal call store methods so imperative callers stay
         framework-agnostic. --}}
    <script>
    (function () {
        document.addEventListener('alpine:init', function () {
            Alpine.store('clientFormModal', {
                open: false,
                title: 'Add Client',
                subtitle: 'Register a new client in the registry',
                submitLabel: 'Add Client',
                _prevFocus: null,
                dirty: false,
                _stashed: null,
                _keepStash: false,

                show: function (mode, id) {
                    var isEdit = mode === 'edit';
                    this.title = isEdit ? 'Edit Client' : 'Add Client';
                    this.subtitle = isEdit ? 'Update client information' : 'Register a new client in the registry';
                    this.submitLabel = isEdit ? 'Save Client' : 'Add Client';
                    this._prevFocus = document.activeElement;
                    this.dirty = false;
                    document.body.style.overflow = 'hidden';
                    this.open = true;

                    // Load form via AJAX
                    var self = this;
                    var body = document.getElementById('clientFormModalBody');
                    if (body) {
                        body.innerHTML = '<div class="flex items-center justify-center py-[2rem] text-ink-muted"><span>Loading form...</span></div>';
                        var url = isEdit
                            ? '{{ route("clients.edit", "__ID__") }}'.replace('__ID__', id) + '?modal=1'
                            : '{{ route("clients.create") }}?modal=1';
                        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                            .then(function (r) { return r.text(); })
                            .then(function (html) {
                                body.innerHTML = html;
                                if (isEdit && id) {
                                    var form = body.querySelector('form');
                                    if (form) form.dataset.clientId = id;
                                }
                                body.querySelectorAll('script').forEach(function (old) {
                                    var fresh = document.createElement('script');
                                    fresh.textContent = old.textContent;
                                    old.parentNode.replaceChild(fresh, old);
                                });
                                // UX polish: track edits for the discard guard and
                                // move focus into the modal once the form is ready.
                                bindFormChanged(body, self);
                                focusFirstModalField(body);
                            })
                            .catch(function () {
                                body.innerHTML = '<div class="flex items-center justify-center py-[2rem] text-danger">Failed to load form.</div>';
                            });
                    }
                },

                hide: function () {
                    this.open = false;
                    this.dirty = false;
                    document.body.style.overflow = '';
                    if (this._prevFocus && typeof this._prevFocus.focus === 'function') {
                        this._prevFocus.focus();
                    }
                    this._prevFocus = null;
                    // Reset body to loading state UNLESS the form was stashed for
                    // a duplicate-review detour (restoreForm() re-injects it).
                    var body = document.getElementById('clientFormModalBody');
                    if (body && !this._keepStash) {
                        body.innerHTML = '<div class="flex items-center justify-center py-[2rem] text-ink-muted"><span>Loading form...</span></div>';
                    }
                    if (!this._keepStash) this._stashed = null;
                    this._keepStash = false;
                },

                // User-initiated close (Escape / X / Cancel): guard against losing
                // an in-progress form. Programmatic closes (success, duplicate
                // review detour) call hide() directly and skip the prompt.
                requestClose: function () {
                    var self = this;
                    if (!this.open) return;
                    if (!this.dirty) { this.hide(); return; }
                    window.uiConfirm({
                        title: 'Discard unsaved changes?',
                        message: 'Your changes will be lost if you close this form.',
                        confirmLabel: 'Discard'
                    }).then(function (ok) {
                        if (ok) {
                            self.hide();
                            return;
                        }
                        // Stay in the form: uiConfirm released the scroll lock.
                        document.body.style.overflow = 'hidden';
                        var body = document.getElementById('clientFormModalBody');
                        var first = body && body.querySelector('input:not([disabled]):not([type=hidden]), select, textarea, button:not([disabled])');
                        if (first && first.focus) first.focus();
                    });
                },

                // Snapshot the live form (DOM + field values) so a duplicate-review
                // detour can return the user to their untouched input.
                stashForm: function () {
                    if (!this.open) return;
                    var body = document.getElementById('clientFormModalBody');
                    var form = body && body.querySelector('form');
                    if (!body || !form) return;
                    this._stashed = {
                        html: body.innerHTML,
                        data: snapshotFormValues(form)
                    };
                    this._keepStash = true;
                    this.dirty = false;
                },

                restoreForm: function () {
                    var body = document.getElementById('clientFormModalBody');
                    var stash = this._stashed;
                    if (!body || !stash) return;
                    this._stashed = null;
                    this._keepStash = false;
                    this.title = 'Add Client';
                    this.subtitle = 'Register a new client in the registry';
                    this.submitLabel = 'Add Client';
                    body.innerHTML = stash.html;
                    body.querySelectorAll('script').forEach(function (old) {
                        var fresh = document.createElement('script');
                        fresh.textContent = old.textContent;
                        old.parentNode.replaceChild(fresh, old);
                    });
                    var form = body.querySelector('form');
                    if (form && stash.data) restoreFormValues(form, stash.data);
                    bindFormChanged(body, this);
                    this.dirty = true;
                    this.open = true;
                    document.body.style.overflow = 'hidden';
                    Alpine.nextTick(function () {
                        focusFirstModalField(body);
                    });
                }
            });

            function bindFormChanged(body, self) {
                if (!body) return;
                function markDirty() { self.dirty = true; }
                body.addEventListener('input', markDirty);
                body.addEventListener('change', markDirty);
            }

            function focusFirstModalField(body) {
                if (!body) return;
                var first = body.querySelector('input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])');
                if (first && first.focus) first.focus();
            }

            function snapshotFormValues(form) {
                var out = {};
                Array.prototype.forEach.call(form.querySelectorAll('input, select, textarea'), function (el) {
                    if (!el.name) return;
                    if (el.type === 'checkbox' || el.type === 'radio') {
                        if (!el.checked) return;
                        (out[el.name] = out[el.name] || []).push(el.value);
                    } else {
                        out[el.name] = el.value;
                    }
                });
                return out;
            }

            function restoreFormValues(form, data) {
                Object.keys(data || {}).forEach(function (name) {
                    var val = data[name];
                    var fields = form.querySelectorAll('[name="' + name.replace(/"/g, '\\"') + '"]');
                    Array.prototype.forEach.call(fields, function (el) {
                        if (Array.isArray(val)) {
                            if (el.type === 'checkbox' || el.type === 'radio') el.checked = val.indexOf(el.value) !== -1;
                            else el.value = val;
                        } else {
                            if (el.type === 'checkbox' || el.type === 'radio') el.checked = String(val) === String(el.value);
                            else el.value = val;
                        }
                    });
                });
            }
        });

        window.clientFormModalComponent = function () {
            return {
                handleTab: function (e) {
                    var dlg = document.getElementById('clientFormModal');
                    if (!dlg) return;
                    var focusables = dlg.querySelectorAll('button:not([disabled]), [href], input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
                    if (!focusables.length) return;
                    var first = focusables[0];
                    var last = focusables[focusables.length - 1];
                    if (e.shiftKey && document.activeElement === first) { last.focus(); }
                    else if (!e.shiftKey && document.activeElement === last) { first.focus(); }
                }
            };
        };
    })();
    </script>

    <script>
        $(document).ready(function() {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });

            // Compact pager: prev + a 5-number sliding window + next (DataTables
            // 1.13 emits ellipsis tokens for out-of-window ranges automatically
            // via its number callback).
            $.fn.dataTable.ext.pager.numbers_length = 5;
            $.fn.dataTable.ext.pager.compact = function(display, page, pages) {
                return ['previous']
                    .concat($.fn.dataTable.ext.pager.numbers(display, page, pages))
                    .concat(['next']);
            };

            function categoryBadge(cat) {
                // UX polish: client categories no longer borrow the transaction
                // status palette (is-paid / is-pending). All categories share one
                // neutral navy "category" badge.
                return '<span class="status-badge is-category">'
                    + $('<div>').text(cat).html()
                    + '</span>';
            }

            var table = $('#clientsTable').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                pagingType: 'compact',
                // Single-search contract: 'f' (DataTables built-in filter box)
                // is omitted — the workspace owns #clientsSearch. Chrome is
                // split so the pagination control appears ONCE (bottom row):
                // "Show entries" above the table, then info + the compact
                // pager below it. (Previously "lp" + "ip" rendered the pager
                // twice — a redundant, confusing duplicate control.)
                dom: "<'top-chrome'l>rt<'bottom-chrome'ip>",
                ajax: {
                    url: '{{ route('clients.data') }}',
                    type: 'POST',
                    data: function(d) {
                        var p = (window.clientFilters && window.clientFilters.getParams)
                            ? window.clientFilters.getParams()
                            : {};
                        d.municipality = p.municipality || '';
                        d.barangay = p.barangay || '';
                        d.program = p.program || '';
                        d.category = p.category || '';
                        d.search = $('#clientsSearch').val();
                    }
                },
                columns: [
                    {
                        data: 'fullname',
                        render: function(data, type, row) {
                            if (type === 'sort' || type === 'type') return data;
                            return '<div class="client-cell">'
                                + '<span class="client-cell-name">' + $('<div>').text(data).html() + '</span>'
                                + '<span class="client-cell-id">Client ID: ' + $('<div>').text(row.client_id_label || '').html() + '</span>'
                                + '</div>';
                        }
                    },
                    { data: 'precinct' },
                    { data: 'municipality' },
                    { data: 'barangay' },
                    {
                        data: 'category',
                        sortable: false,
                        render: function(data) {
                            return categoryBadge(data || '');
                        }
                    },
                    { data: 'actions', orderable: false }
                ],
                columnDefs: [{
                    targets: 5,
                    className: 'actions-col'
                }],
                order: [
                    [0, 'asc']
                ],
                pageLength: 25,
                lengthMenu: [25, 50, 100],
                createdRow: function(row, data) {
                    $(row).attr({
                        'data-id': data.id,
                        'tabindex': 0,
                        'aria-label': 'Client ' + data.fullname + ', open details'
                    });
                }
            });

            window.clientsTable = table;

            // FilterChips — shared Phase 2C component (server-side DataTables feed).
            // excludeClose: the per-filter pills live outside the FilterChips
            // host, so the shared "click outside closes" handler must treat
            // them as part of the popover surface (otherwise the menu closes on
            // the very click that opens it).
            if (window.FilterChips) {
                window.clientFilters = FilterChips.init({
                    id: 'clients-filters',
                    host: document.querySelector('[data-filter-host="clients-filters"]'),
                    excludeClose: ['[data-filter-segment]'],
                    onApply: function() {
                        refreshSegmentCounts();
                        table.draw();
                    }
                });
                // Sync the Clear All visibility for a deep-link/refresh restore
                // (init hydrates from the URL but does not fire onApply).
                refreshSegmentCounts();
            }

            function refreshSegmentCounts() {
                var p = (window.clientFilters && window.clientFilters.getParams)
                    ? window.clientFilters.getParams()
                    : {};
                var totalActive = 0;
                ['municipality', 'barangay', 'program', 'category'].forEach(function(key) {
                    var btn = document.querySelector('[data-filter-segment="' + key + '"]');
                    var badge = btn ? btn.querySelector('.seg-count') : null;
                    if (!btn) return;
                    var count = (p[key] || '').split(',').filter(Boolean).length;
                    totalActive += count;
                    if (badge) {
                        badge.textContent = String(count);
                        badge.hidden = count === 0;
                    }
                    if (count > 0) {
                        btn.classList.add('is-active');
                        btn.setAttribute('aria-pressed', 'true');
                    } else {
                        btn.classList.remove('is-active');
                        btn.setAttribute('aria-pressed', 'false');
                    }
                });
                var clearAllBtn = document.getElementById('clientsClearAll');
                if (clearAllBtn) clearAllBtn.hidden = totalActive === 0;
            }

            function openFilterSegment(key, btn) {
                if (!window.clientFilters || typeof window.clientFilters.openCategory !== 'function') {
                    return;
                }
                // openCategory reveals ONLY the clicked category's section, so
                // each pill opens a focused, self-contained popover for its own
                // options (Municipality pills -> municipality options, etc.).
                // The clicked pill is tracked so Esc/close returns focus to it
                // and its aria-expanded state stays accurate.
                window.clientFilters.openCategory(key, btn || null);
            }

            document.querySelectorAll('[data-filter-segment]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    openFilterSegment(btn.getAttribute('data-filter-segment'), btn);
                });
            });

            var clientsClearAllBtn = document.getElementById('clientsClearAll');
            if (clientsClearAllBtn) {
                clientsClearAllBtn.addEventListener('click', function() {
                    if (window.clientFilters && typeof window.clientFilters.clearAll === 'function') {
                        window.clientFilters.clearAll();
                    }
                });
            }

            function executeScripts(container) {
                container.querySelectorAll('script').forEach(function(oldScript) {
                    var fresh = document.createElement('script');
                    fresh.textContent = oldScript.textContent;
                    oldScript.parentNode.replaceChild(fresh, oldScript);
                });
            }

            function panelUrl(id) {
                return '{{ route('clients.show', '__ID__') }}'.replace('__ID__', id) + '?panel=1';
            }

            // Row click -> shared details panel
            $('#clientsTable tbody').on('click', 'tr', function(e) {
                if ($(e.target).closest('.actions-col').length) {
                    return;
                }
                var id = $(this).data('id');
                if (id) {
                    window.DetailsPanel.load('clients', id, { url: panelUrl(id) });
                }
            });

            // Actions-column View icon -> open the details panel
            $('#clientsTable tbody').on('click', '[data-view-client]', function(e) {
                e.stopPropagation();
                var id = $(this).data('view-client');
                if (!id) return;
                window.DetailsPanel.load('clients', id, { url: panelUrl(id) });
            });

            // Actions-column Edit icon -> open the edit modal. preventDefault
            // is essential: the action is an <a href="{clients.edit}"> (kept
            // for full-page parity), and without it the browser navigates to
            // /clients/{id}/edit right after the modal opens.
            $('#clientsTable tbody').on('click', '[data-edit-client]', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var id = $(this).data('edit-client');
                if (!id) return;
                openEditModal(id);
            });

            // DetailsPanel Edit button -> open the edit modal
            document.addEventListener('click', function(e) {
                var btn = e.target.closest('[data-edit-client-modal]');
                if (!btn) return;
                var id = btn.getAttribute('data-edit-client-modal');
                if (!id) return;
                window.DetailsPanel.close(false);
                openEditModal(id);
            });

            // Actions-column legacy "View" target kept wired (any tooling that
            // still calls it opens the same panel).
            window.openClientPanel = function(id) {
                if (!id) return;
                window.DetailsPanel.load('clients', id, { url: panelUrl(id) });
            };

            // Keyboard twin of the row-click handler (Enter / Space).
            $('#clientsTable tbody').on('keydown', 'tr[tabindex]', function(e) {
                if (e.key !== 'Enter' && e.key !== ' ') {
                    return;
                }
                if ($(e.target).closest('.actions-col').length) {
                    return;
                }
                e.preventDefault();
                var id = $(this).data('id');
                if (id) {
                    window.DetailsPanel.load('clients', id, { url: panelUrl(id) });
                }
            });

            // Toolbar search
            $('#clientsSearch').on('input', function() {
                clearTimeout(this.searchTimer);
                this.searchTimer = setTimeout(() => {
                    table.search(this.value).draw();
                }, 250);
            });

            // Filtered-result counter (UX polish): a small "X of N clients"
            // readout beside the filter pills, shown only while a filter or the
            // search narrows the result set. The DataTables bottom info stays.
            var clientsResultCount = document.getElementById('clientsResultCount');
            table.on('draw.dt', function () {
                if (!clientsResultCount) return;
                var info = table.page.info();
                var params = (window.clientFilters && window.clientFilters.getParams)
                    ? window.clientFilters.getParams()
                    : {};
                var hasFilter = Object.keys(params).length > 0 || $('#clientsSearch').val().length > 0;
                if (hasFilter) {
                    clientsResultCount.textContent = info.recordsFiltered + ' of ' + info.recordsTotal + ' clients';
                    clientsResultCount.hidden = false;
                } else {
                    clientsResultCount.hidden = true;
                }
            });

            // Municipality -> barangay cascade + clear-per-category all live
            // inside the shared FilterChips component (data-filter-depends).
            // There is intentionally NO global Reset: each filter owns its own
            // Clear action inside the FilterChips popover.

            // CSV export: mirror the active filter params (Export Current
            // Filter) or export the full scope (Export All).
            document.querySelectorAll('[data-clients-export]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var mode = btn.getAttribute('data-clients-export');
                    if (mode === 'all') {
                        window.location.href = '{{ route('clients.export') }}?export_all=1';
                        return;
                    }
                    var p = (window.clientFilters && window.clientFilters.getParams)
                        ? window.clientFilters.getParams()
                        : {};
                    var query = new URLSearchParams({
                        municipality: p.municipality || '',
                        barangay: p.barangay || '',
                        program: p.program || '',
                        category: p.category || '',
                        search: $('#clientsSearch').val()
                    }).toString();
                    window.location.href = '{{ route('clients.export') }}?' + query;
                });
            });

            // Post-panel-save / post-panel-delete refresh + toast. Phase 26:
            // success feedback renders through the ONE shared notification
            // stack (window.notify / partials.unified-notify). The function
            // name + call sites are unchanged; only the presentation channel
            // is unified (bottom-right success toast, auto-dismissed).
            function showToast(message) {
                if (!message) return;
                if (typeof window.notify === 'function') {
                    window.notify({ type: 'success', title: 'Success', message: message });
                    return;
                }
                var stack = document.getElementById('clientsToastStack');
                if (!stack || !message) return;
                var el = document.createElement('div');
                el.className = 'toast pointer-events-auto flex w-full max-w-[420px] items-start gap-[12px] rounded-panel bg-surface p-[1rem] shadow-pop ring-1 ring-line';
                el.setAttribute('role', 'status');
                el.innerHTML = '<span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-pill bg-teal/[0.12] text-teal" aria-hidden="true">'
                    + '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5"><polyline points="20 6 9 17 4 12"/></svg>'
                    + '</span>'
                    + '<div class="min-w-0 flex-1 text-dense leading-snug text-ink">' + $('<div>').text(message).html() + '</div>'
                    + '<button type="button" class="btn-close shrink-0" aria-label="Close"></button>';
                stack.appendChild(el);
                el.classList.add('show');
                el.querySelector('button[aria-label="Close"]').addEventListener('click', function() {
                    el.remove();
                });
            }

            document.addEventListener('details:updated', function(e) {
                table.draw();
                if (e.detail && e.detail.message) showToast(e.detail.message);
            });

            document.addEventListener('details:deleted', function(e) {
                table.draw();
                if (e.detail && e.detail.message) showToast(e.detail.message);
            });

            document.addEventListener('details:error', function(e) {
                if (e.detail && e.detail.message) showToast(e.detail.message);
            });

            // Reveal the server-rendered success toast (no Bootstrap Toast JS;
            // the element is shown with `.show` and dismissed manually).
            function wireFlashToast(stack) {
                if (!stack) return;
                stack.classList.add('show');
                var closeBtn = stack.querySelector('button[aria-label="Close"]');
                if (closeBtn) closeBtn.addEventListener('click', function() { stack.remove(); });
            }
            document.querySelectorAll('.toast').forEach(wireFlashToast);

            // Table Delete: confirm via uiConfirm, then submit as JSON so the
            // registry refreshes in place (no page navigation). ACL/CSRF/logic
            // stay on the existing destroy endpoint; only presentation changes.
            $('#clientsTable tbody').on('click', '[data-confirm]', function(e) {
                e.stopPropagation();
                var form = this.closest('form');
                if (!form || !window.uiConfirm) return;
                e.preventDefault();
                window.uiConfirm({
                    message: this.getAttribute('data-confirm') || 'Are you sure?',
                    confirmLabel: 'Delete'
                }).then(function(ok) {
                    if (!ok) return;
                    fetch(form.getAttribute('action'), {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        },
                        body: new FormData(form)
                    })
                    .then(function(r) { return r.json().catch(function() { return {}; }); })
                    .then(function(res) {
                        if (res.success) {
                            if (DetailsPanel.isOpen()) {
                                var ent = DetailsPanel.getCurrentEntity();
                                if (ent && ent.id == form.closest('tr').getAttribute('data-id')) {
                                    DetailsPanel.close();
                                }
                            }
                            table.draw();
                            showToast(res.message || 'Client deleted successfully.');
                        } else {
                            showToast(res.message || 'Could not delete client.');
                        }
                    })
                    .catch(function() { showToast('Could not delete client.'); });
                });
            });

            // Client form modal — Alpine.js store bridge (Phase 8)
            // The modal markup uses x-data="clientFormModalComponent()" which
            // reads from Alpine.store('clientFormModal'). These window.* functions
            // let imperative callers (details panel, row actions, form submission
            // handler) control the Alpine store without knowing about Alpine.
            window.openAddClientModal = function() {
                Alpine.store('clientFormModal').show('add');
            };

            window.openEditModal = function(id) {
                Alpine.store('clientFormModal').show('edit', id);
            };

            window.closeClientModal = function() {
                Alpine.store('clientFormModal').hide();
            };

            // Modal-native duplicate / identity warning. Presented in the shared
            // FEEDBACK modal (not embedded inside the edit modal) so the user
            // can review the existing record and explicitly choose whether to
            // continue — while the edit modal stays open underneath with their
            // entered values intact. No browser alert()/confirm(). The
            // duplicate detection business rule (name+birthdate gate) is
            // unchanged — only the presentation moved to the feedback modal.
            function showDuplicateWarning(matches, form, body) {
                var warn = document.createElement('div');
                warn.innerHTML = '<div class="flex items-start gap-2">'
                    + '<svg viewBox="0 0 24 24" aria-hidden="true" style="width:18px;height:18px;stroke:var(--color-amber);fill:none;stroke-width:2;margin-top:2px;flex-shrink:0;">'
                    + '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
                    + '<div class="min-w-0">'
                    + '<p class="mb-1 font-semibold text-ink">Possible existing client found</p>'
                    + '<p class="mb-2 text-dense leading-snug text-ink-secondary">'
                    + $('<div>').text(((matches[0] && (matches[0].display_full_name || matches[0].full_name)) || 'A similar client')).html()
                    + ' appears to already exist in the client registry.</p>'
                    + '<ul class="mb-0 list-disc space-y-1 pl-5">';
                matches.forEach(function(m) {
                    warn.innerHTML += '<li class="text-dense text-ink-secondary">'
                        + $('<div>').text((m.display_full_name || m.full_name) + (m.birthdate ? ' — born ' + m.birthdate + (m.sex ? ' (' + m.sex + ')' : '') : '')).html()
                        + '</li>';
                });
                warn.innerHTML += '</ul></div></div>';

                var actions = document.createElement('div');
                actions.className = 'flex flex-wrap items-center justify-end gap-[8px]';
                var reviewBtn = document.createElement('button');
                reviewBtn.type = 'button';
                reviewBtn.className = 'btn-subtle';
                reviewBtn.textContent = 'Review Existing Client';
                var continueBtn = document.createElement('button');
                continueBtn.type = 'button';
                continueBtn.className = 'btn-gold';
                continueBtn.textContent = 'Continue as New Client';
                actions.appendChild(reviewBtn);
                actions.appendChild(continueBtn);

                // Phase 26 restore (contextual gate): the duplicate warning
                // presents as a CONTEXTUAL WARNING inside the Add Client
                // workflow via the centered clientFeedbackModal (NOT a
                // bottom-right unified toast). Form stays open underneath
                // with entered values intact; both decisions preserved.
var fbStore = Alpine.store('clientFeedbackModal');
                reviewBtn.addEventListener('click', function () {
                    var first = matches[0];
                    // Preserve the user's in-progress Add form before the modal
                    // closes: it is re-opened with all entered values intact when
                    // the details panel is dismissed (details:closed).
                    Alpine.store('clientFormModal').stashForm();
                    fbStore.hide();
                    window.closeClientModal();
                    if (first && first.id) {
                        window.DetailsPanel.load('clients', first.id, {
                            url: panelUrl(first.id)
                        });
                    }
                });

                continueBtn.addEventListener('click', function() {
                    fbStore.hide();
                    if (!form.querySelector('input[name="duplicate_confirm"]')) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'duplicate_confirm';
                        input.value = '1';
                        form.appendChild(input);
                    }
                    form.requestSubmit();
                });

                fbStore.show({ title: 'Possible duplicate', type: 'warning', body: warn, actions: actions });
                reviewBtn.focus();
            }

            // Duplicate-review detour: when the details panel (opened by the
            // "Review Existing Client" button) is dismissed, re-open the Add
            // modal with the previously stashed form and values intact.
            document.addEventListener('details:closed', function () {
                var store = Alpine.store('clientFormModal');
                if (!store || !store._stashed || store.open) return;
                store.restoreForm();
            });

            // One reusable feedback modal. Shows INFO / WARNING / ERROR
            // messages (duplicate warning, validation errors, photo errors)
            // while keeping the edit modal open underneath. Closes back to
            // the still-editable form. setFeedbackType highlights a title.
            function setFeedbackContent(title, type, html, extraOnHidden) {
                var body = document.createElement('div');
                body.appendChild(html);
                Alpine.store('clientFeedbackModal').show({
                    title: title,
                    type: type,
                    body: body,
                    // On close, return focus to the underlying form's first
                    // focusable (launching the open form modal as before).
                    onHidden: function() {
                        var first = formBody.querySelector('input, select, textarea, button');
                        if (first && first.focus) first.focus();
                        if (typeof extraOnHidden === 'function') extraOnHidden();
                    }
                });
            }

            var formBody = document.getElementById('clientFormModalBody');

            // Validate a selected photo file client-side (type + 1MB). Returns
            // an error string or null when acceptable. Mirrors the server rules
            // so invalid files are never silently swallowed on the update path.
            function photoValidationError(input) {
                var f = input && input.files && input.files[0];
                if (!f) return null;
                var allowed = ['image/jpeg', 'image/png', 'image/gif'];
                if (allowed.indexOf(f.type) === -1) {
                    return 'Only JPG, PNG, or GIF images are allowed.';
                }
                if (f.size > 1024 * 1024) {
                    return 'Photo must be 1MB or smaller.';
                }
                return null;
            }

            // Handle modal form submissions
            formBody.addEventListener('submit', function(e) {
                var form = e.target;
                if (!(form instanceof HTMLFormElement)) return;
                e.preventDefault();

                // Client-side photo gate BEFORE any network call. If the user
                // picked an invalid / oversized file, surface it in the feedback
                // modal and keep the edit modal open — never silently ignore it.
                var photoInput = form.querySelector('input[name="photo"]');
                var photoFile = photoInput && photoInput.files && photoInput.files[0];
                var photoErr = photoValidationError(photoInput);
                if (photoErr) {
                    var warnP = document.createElement('p');
                    warnP.className = 'mb-0 font-medium text-red';
                    warnP.textContent = photoErr;
                    setFeedbackContent('Photo error', 'error', warnP);
                    return;
                }

                var submitBtn = form.querySelector('button[type="submit"]')
                    || document.getElementById('clientFormSubmit');
                if (submitBtn) submitBtn.disabled = true;
                var body = document.getElementById('clientFormModalBody');
                fetch(form.action, {
                    method: form.method || 'POST',
                    body: new FormData(form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) {
                    return r.json().catch(function() { return {}; });
                })
                .then(function(data) {
                    if (data.success) {
                        // If the edit form carried a new photo, push it to the
                        // existing clients.photo.store endpoint now that the
                        // client update succeeded (that route is gated by the
                        // same action:clients.php,edit ACL the update just passed).
                        // The photo POST is awaited so the details re-fetch (which
                        // resolves the current photo from storage) happens AFTER the
                        // new photo row is saved — otherwise the panel could reload
                        // the pre-upload photo and make the upload look as if it
                        // never applied.
                        var photoPost = Promise.resolve();
                        if (photoFile && form.dataset.clientId) {
                            var fd = new FormData();
                            fd.append('client_id', form.dataset.clientId);
                            fd.append('photo', photoFile);
                            photoPost = fetch('{{ route("clients.photo.store") }}', {
                                method: 'POST',
                                body: fd,
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                                }
                            }).then(function(r) {
                                // Surface a server-side photo failure instead of
                                // silently ignoring it (the previous failure mode
                                // that made uploads "look" successful without
                                // updating the photo).
                                if (!r.ok) {
                                    throw new Error('photo_upload_failed');
                                }
                            });
                        }
                        photoPost
                            .then(function() {
                                window.closeClientModal();
                                table.draw();
                                showToast(data.message || 'Client saved successfully.');
                                if (DetailsPanel.isOpen()) {
                                    var ent = DetailsPanel.getCurrentEntity();
                                    if (ent && ent.id && ent.id == form.dataset.clientId) {
                                        DetailsPanel.load('clients', ent.id, { url: panelUrl(ent.id) });
                                    }
                                }
                            })
                            .catch(function() {
                                // Client saved but the photo upload failed. Tell
                                // the user rather than silently pretending success.
                                var errP = document.createElement('p');
                                errP.className = 'mb-0 text-ink-secondary';
                                errP.textContent = 'Client was saved, but the photo could not be uploaded. Please try again from Edit.';
                                setFeedbackContent('Photo not saved', 'error', errP);
                            });
                    } else if (data.errors) {
                        // Field-level inline messages under each invalid field
                        // (mirrors the server-rendered per-field error message), kept
                        // alongside the existing feedback modal so both channels
                        // agree. Cleared as the user edits a field.
                        var list = document.createElement('ul');
                        list.className = 'list-disc list-inside mb-0 space-y-1';
                        var errorKeys = Object.keys(data.errors);
                        errorKeys.forEach(function(k) {
                            data.errors[k].forEach(function(msg) {
                                var li = document.createElement('li');
                                li.textContent = msg;
                                list.appendChild(li);
                            });
                            var field = form.querySelector('[name="' + k + '"]');
                            if (!field) return;
                            var host = field.closest('.field-wrapper') || field.closest('div') || field;
                            field.classList.add('is-invalid');
                            if (host.querySelector) {
                                var existing = host.querySelector('[data-field-error="' + k + '"]');
                                if (existing) existing.remove();
                            }
                            var tag = document.createElement('small');
                            tag.className = 'field-error';
                            tag.setAttribute('data-field-error', k);
                            tag.textContent = data.errors[k][0];
                            if (host.appendChild) host.appendChild(tag);
                            var clearOnce = function () {
                                field.classList.remove('is-invalid');
                                if (host.querySelector) {
                                    var tagged = host.querySelector('[data-field-error="' + k + '"]');
                                    if (tagged) tagged.remove();
                                }
                            };
                            field.addEventListener('input', clearOnce, { once: true });
                            field.addEventListener('change', clearOnce, { once: true });
                        });
                        var firstKey = errorKeys[0];
                        var firstField = firstKey ? form.querySelector('[name="' + firstKey + '"]') : null;
                        if (firstField && firstField.focus) {
                            // Focus the first invalid field once the feedback
                            // modal closes (mirrors the old hidden.bs.modal
                            // handler; runs after the default focus return so
                            // it wins as before).
                            setFeedbackContent('Please correct the following', 'error', list, function() {
                                firstField.focus();
                            });
                        } else {
                            setFeedbackContent('Please correct the following', 'error', list);
                        }
                    } else if (data.duplicate_warning) {
                        showDuplicateWarning(data.duplicate_warning, form, body);
                    }
                })
                .catch(function() {
                    var errDiv = document.createElement('div');
                    errDiv.className = 'form-errors mb-4 p-3 rounded-panel bg-danger/10 text-danger';
                    errDiv.textContent = 'An error occurred. Please try again.';
                    var existingErr = body.querySelector('.form-errors');
                    if (existingErr) existingErr.remove();
                    body.insertBefore(errDiv, body.firstChild);
                })
                .finally(function() {
                    if (submitBtn) submitBtn.disabled = false;
                });
            });
        });
    </script>
@endpush
