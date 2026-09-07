@extends('layouts.app')

@section('title', 'Activity Logs — 2D MIS')

{{-- Phase 1: Prototype-aligned Audit Logs with shared details panel --}}
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/datatables.css') }}">
    <style>
        /* ── Audit-logs scope: token skin over the DataTables Bootstrap
           integration and the leaderboard modal. Prefixed with
           #audit-screen — nothing here can leak to other screens. ── */
        #audit-screen table.dataTable td {
            font-size: 0.8rem;
            padding: 0.35rem 0.5rem;
            line-height: 1.2;
        }

        #audit-screen table.dataTable th {
            font-size: 0.85rem;
            background-color: var(--color-navy);
            color: #fff;
            border-bottom: 0;
            white-space: nowrap;
        }

        #audit-screen table.dataTable tbody tr {
            cursor: pointer;
        }

        #audit-screen table.dataTable tbody tr:hover td {
            background-color: rgb(37 99 235 / 0.06);
        }

        #audit-screen .dataTables_wrapper .dataTables_length select,
        #audit-screen .dataTables_wrapper .dataTables_filter input {
            border: 1px solid var(--color-line);
            border-radius: var(--radius-control);
            padding: 0.25rem 0.5rem;
            font-size: 0.85rem;
        }

        #audit-screen .dataTables_wrapper .dataTables_paginate .page-item.active .page-link {
            background-color: var(--color-navy);
            border-color: var(--color-navy);
        }

        #audit-screen .page-link {
            color: var(--color-navy);
        }

        #leaderboardModal .leaderboard-table th {
            background-color: var(--color-navy);
            color: #fff;
            border-bottom: 0;
        }
    </style>
@endpush

@section('content')
    @include('partials.breadcrumbs', [
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Activity Logs'],
        ],
    ])

    @include('partials.page-header', [
        'title' => 'Activity Logs',
        'subtitle' => 'Who did what, when — across audited tables.',
        'actions' => '
            <button type="button" class="btn-subtle" id="refreshAudit" title="Refresh the audit feed manually">Refresh</button>
            <button type="button" class="btn-gold" @click="$store.leaderboardModal.open()">Leaderboard</button>',
    ])

    <div id="audit-screen" class="flex flex-col gap-[16px]">
        <section class="data-card" aria-label="Log source">
            <div class="data-card-body max-w-[420px]">
                <form method="GET" action="{{ route('admin.audit-logs.index') }}">
                    <label for="table" class="field-label">Select Table</label>
                    <select name="table" id="table" class="form-select" onchange="this.form.submit()">
                        @foreach ($tables as $value => $label)
                            <option value="{{ $value }}" @selected($targetTable === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </section>

        <section class="data-card" aria-label="Activity log records">
            <div class="data-card-body flex flex-col gap-[14px]">
                @include('partials.filter-chips', ['filterChips' => $filterChips])

                <div class="flex items-end gap-2 max-lg:w-full">
                    <button id="resetFilters" class="btn-subtle w-full lg:w-auto">Reset</button>
                </div>
            </div>

            <div class="overflow-x-auto px-[1.25rem] pb-[1.25rem]" tabindex="0" aria-label="Activity logs table, scrolls horizontally on narrow screens">
                <table id="logsTable" class="table table-sm align-middle" style="width:100%;">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Action</th>
                            <th>Target</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Loaded via AJAX (server-side DataTables) --}}
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    {{-- Leaderboard modal — Tailwind + Alpine.js (Phase 16). Moved off
         bootstrap.Modal / show.bs.modal / data-bs-*. The server-rendered
         Leaderboard trigger (page header actions) now calls the Alpine
         leaderboardModal store. opening it also fires the existing jQuery
         $.ajax POST to admin.audit-logs.leaderboard exactly once per open
         (same URL/method/payload/CSRF/rendering), preserving the old
         show.bs.modal -> AJAX workflow. --}}
    <div x-data="leaderboardModalComponent()"
         x-cloak
         role="dialog"
         aria-modal="true"
         aria-labelledby="leaderboardLabel"
         class="pointer-events-none fixed inset-0 z-[200]">
        <div x-show="$store.leaderboardModal.open"
             x-transition.opacity.duration.200ms
             @click="$store.leaderboardModal.close()"
             class="pointer-events-auto absolute inset-0 bg-ink/40"
             aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center overflow-y-auto p-4">
            <div x-show="$store.leaderboardModal.open"
                 x-ref="dialog"
                 x-transition.opacity.duration.200ms
                 @keydown.escape.window="$store.leaderboardModal.close()"
                 @keydown.tab.prevent.stop="handleTab($event)"
                 class="pointer-events-auto flex max-h-[90vh] w-full max-w-[720px] flex-col rounded-panel bg-surface shadow-pop ring-1 ring-line">
                <div class="flex shrink-0 items-center justify-between gap-2 border-b border-line bg-navy px-[1.25rem] py-[1rem]">
                    <h5 id="leaderboardLabel" class="mb-0 text-dense font-heading font-semibold text-white">User Activity Leaderboard</h5>
                    <button type="button"
                        class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-btn text-white/70 transition duration-150 ease-standard hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-gold"
                        @click="$store.leaderboardModal.close()"
                        aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto p-[1.25rem]">
                    <table class="table leaderboard-table" id="leaderboardTable">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>User</th>
                                <th>Total Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="flex shrink-0 items-center justify-end gap-2 border-t border-line bg-neutral-100 px-[1.25rem] py-[0.9rem]">
                    <button type="button" class="btn-subtle" @click="$store.leaderboardModal.close()">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            document.addEventListener('alpine:init', function () {
                Alpine.store('leaderboardModal', {
                    open: false,
                    _prevFocus: null,
                    open: function () {
                        if (this.open) return;
                        this._prevFocus = document.activeElement;
                        document.body.style.overflow = 'hidden';
                        this.open = true;
                        this.load();
                        Alpine.nextTick(function () {
                            var closeBtn = document.querySelector('#leaderboardModal [aria-label="Close"]');
                            if (closeBtn) closeBtn.focus();
                        });
                    },
                    close: function () {
                        if (!this.open) return;
                        this.open = false;
                        document.body.style.overflow = '';
                        if (this._prevFocus && typeof this._prevFocus.focus === 'function') {
                            this._prevFocus.focus();
                        }
                        this._prevFocus = null;
                    },
                    load: function () {
                        // Existing jQuery AJAX leaderboard contract preserved
                        // (fires once per open — the modal never caches; a
                        // response that arrives after close still populates
                        // the tbody, exactly as before).
                        $.ajax({
                            url: '{{ route('admin.audit-logs.leaderboard') }}',
                            type: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                            data: { table: $('#table').val() }
                        }).done(function (rows) {
                            var tbody = $('#leaderboardTable tbody');
                            tbody.empty();
                            $.each(rows, function (index, row) {
                                tbody.append(
                                    '<tr>' +
                                    '<td>' + (index + 1) + '</td>' +
                                    '<td>' + $('<div>').text(row.username).html() + '</td>' +
                                    '<td>' + row.total_actions + '</td>' +
                                    '</tr>'
                                );
                            });
                        });
                    }
                });
            });

            window.leaderboardModalComponent = function () {
                return {
                    get open() { return this.$store.leaderboardModal.open; },
                    close: function () { this.$store.leaderboardModal.close(); },
                    handleTab: function (e) {
                        var dlg = this.$refs.dialog;
                        if (!dlg) return;
                        var focusables = dlg.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
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
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="{{ asset('js/components/DetailsPanel.js') }}"></script>
    <script src="{{ asset('js/components/FilterChips.js') }}"></script>
    <script>
        $(document).ready(function () {
            const csrfToken = '{{ csrf_token() }}';
            const dataUrl = '{{ route('admin.audit-logs.data') }}';
            const showUrl = '{{ route('admin.audit-logs.show', '__ID__') }}';

            const table = $('#logsTable').DataTable({
                autoWidth: false,
                ajax: {
                    url: dataUrl,
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    data: function (d) {
                        d.table = $('#table').val();
                    },
                    dataSrc: function (json) {
                        if (window.auditFilters && window.auditFilters.setOptions) {
                            window.auditFilters.setOptions('user', json.users.map(function (u) { return { value: u, label: u }; }));
                            window.auditFilters.setOptions('action', json.actions.map(function (a) { return { value: a, label: a }; }));
                        }
                        return json.data;
                    }
                },
                columns: [
                    { data: 'username' },
                    { data: 'action' },
                    { data: 'target' },
                    {
                        data: 'date',
                        render: function (data, type, row) {
                            if (type === 'sort' || type === 'type') {
                                return row.date_raw;
                            }
                            return data;
                        }
                    }
                ],
                order: [[3, 'desc']],
                createdRow: function(row, data) {
                    $(row).attr({
                        'data-id': data.id,
                        'tabindex': 0,
                        'aria-label': 'Audit entry ' + data.id + ', open details'
                    });
                }
            });

            // UX-1: audit table uses a MANUAL refresh only (owner-approved
            // replacement of the 5s auto re-fetch of the LIMIT 10000 feed).
            $('#refreshAudit').on('click', function () {
                table.ajax.reload(null, false);
            });


            function escapeRegex(v) {
                return String(v).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            }

            // FilterChips — shared Phase 2C component. For Audit Logs it is the
            // UI/state layer only: user/action options are fed in on each feed
            // load, and the ACTUAL filtering stays client-side (DataTables
            // column search for user/action, ext.search for the date range).
            if (window.FilterChips) {
                window.auditFilters = FilterChips.init({
                    id: 'audit-filters',
                    host: document.querySelector('[data-filter-host="audit-filters"]'),
                    onApply: function (p) {
                        var userVals = (p.user || '').split(',').filter(Boolean);
                        var actionVals = (p.action || '').split(',').filter(Boolean);

                        table.column(0).search(
                            userVals.length ? '^(' + userVals.map(function (v) { return escapeRegex(v); }).join('|') + ')$' : '',
                            true, false
                        );
                        table.column(1).search(
                            actionVals.length ? '^(' + actionVals.map(function (v) { return escapeRegex(v); }).join('|') + ')$' : '',
                            true, false
                        );
                        table.draw();
                    }
                });
            }

            $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                var params = (window.auditFilters && window.auditFilters.getParams)
                    ? window.auditFilters.getParams()
                    : {};
                var min = params.minDate || '';
                var max = params.maxDate || '';

                if (!min && !max) {
                    return true;
                }

                var dateRaw = table.row(dataIndex).data().date_raw;
                if (!dateRaw) {
                    return false;
                }

                var dateVal = new Date(dateRaw);

                if ((min && dateVal < new Date(min)) || (max && dateVal > new Date(max))) {
                    return false;
                }

                return true;
            });

            $('#resetFilters').on('click', function () {
                if (window.auditFilters && window.auditFilters.clearAll) {
                    window.auditFilters.clearAll();
                }
                table.draw();
            });


            // Row click -> shared details panel
            $('#logsTable tbody').on('click', 'tr', function(e) {
                var id = $(this).data('id');
                if (id) {
                    window.DetailsPanel.load('audit', id, {
                        url: showUrl.replace('__ID__', id) + '?table=' + encodeURIComponent($('#table').val()) + '&panel=1',
                        method: 'GET'
                    });
                }
            });

            // Keyboard twin (Enter / Space)
            $('#logsTable tbody').on('keydown', 'tr[tabindex]', function(e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                e.preventDefault();
                var id = $(this).data('id');
                if (id) {
                    window.DetailsPanel.load('audit', id, {
                        url: showUrl.replace('__ID__', id) + '?table=' + encodeURIComponent($('#table').val()) + '&panel=1',
                        method: 'GET'
                    });
                }
            });
        });
    </script>
@endpush