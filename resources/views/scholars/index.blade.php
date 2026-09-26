@extends('layouts.app')

@section('title', 'Scholars — 2D MIS')

{{-- Phase 1: Prototype-aligned Scholars with tabbed structure and shared details panel --}}
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/datatables.css') }}">
    <style>
        /* ── Scholars screen scope: token skin over the DataTables
           Bootstrap integration. Prefixed with #scholars-screen —
           nothing here can leak to other screens. ── */
        #scholars-screen table.dataTable td {
            font-size: 0.8rem;
        }

        #scholars-screen table.dataTable th {
            font-size: 0.85rem;
            background-color: var(--color-navy);
            color: #fff;
            border-bottom: 0;
            white-space: nowrap;
        }

        #scholars-screen table.dataTable tbody tr {
            cursor: pointer;
        }

        #scholars-screen table.dataTable tbody tr:nth-child(odd) td {
            background-color: rgb(15 27 45 / 0.02);
        }

        #scholars-screen table.dataTable tbody tr:hover td {
            background-color: rgb(37 99 235 / 0.06);
        }

        #scholars-screen .actions-col {
            white-space: nowrap;
        }

        #scholars-screen .dataTables_wrapper .dataTables_length select,
        #scholars-screen .dataTables_wrapper .dataTables_filter input {
            border: 1px solid var(--color-line);
            border-radius: var(--radius-control);
            padding: 0.25rem 0.5rem;
            font-size: 0.85rem;
        }

        #scholars-screen .dataTables_wrapper .dataTables_paginate .page-item.active .page-link {
            background-color: var(--color-navy);
            border-color: var(--color-navy);
        }

        #scholars-screen .page-link {
            color: var(--color-navy);
        }

        /* Tab navigation */
        .scholar-tabs {
            display: flex;
            gap: 0;
            border-bottom: 2px solid var(--color-line);
            margin-bottom: 16px;
            overflow-x: auto;
        }
        .scholar-tab-btn {
            padding: 12px 20px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--color-ink-secondary);
            background: transparent;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            transition: all 200ms cubic-bezier(0.4, 0, 0.2, 1);
            white-space: nowrap;
        }
        .scholar-tab-btn:hover { color: var(--color-navy); }
        .scholar-tab-btn.active {
            color: var(--color-navy);
            border-bottom-color: var(--color-navy);
        }
        .scholar-tab-btn:focus-visible {
            outline: 2px solid var(--color-gold);
            outline-offset: -2px;
            border-radius: 4px 4px 0 0;
        }

        .scholar-tab-panel { display: none; }
        .scholar-tab-panel.active { display: block; animation: fadeIn 200ms ease; }
    </style>
@endpush

@section('content')
    @php
        $acl = app(\App\Services\AccessControlService::class);
        $viewUser = auth()->user();
        $canViewReports = $acl->canAccessPage($viewUser, 'scholarship_reports.php');
        $canViewLogs = $acl->canAccessPage($viewUser, 'update_logs.php');
    @endphp


    @include('partials.page-header', [
        'title' => 'Scholars',
        'subtitle' => 'Educational assistance, scholarship profiles and GIP records',
        'actions' => '
            <a href="'.route('scholars.create').'" class="btn-gold no-underline">+ Add Scholar</a>',
    ])

    <div id="scholars-screen">
        <nav class="scholar-tabs" role="tablist" aria-label="Scholars sections">
            <button type="button" class="scholar-tab-btn active" data-tab="scholars" role="tab" aria-selected="true" aria-controls="panel-scholars">Scholars</button>
            <button type="button" class="scholar-tab-btn" data-tab="gip" role="tab" aria-selected="false" aria-controls="panel-gip">GIP Profiles</button>
            @if ($canViewReports)
                <button type="button" class="scholar-tab-btn" data-tab="reports" role="tab" aria-selected="false" aria-controls="panel-reports">Scholarship Reports</button>
            @endif
            @if ($canViewLogs)
                <button type="button" class="scholar-tab-btn" data-tab="logs" role="tab" aria-selected="false" aria-controls="panel-logs">Update Log</button>
            @endif
            <button type="button" class="scholar-tab-btn" data-tab="self-update" role="tab" aria-selected="false" aria-controls="panel-self-update">Grantee Self-Update</button>
        </nav>

        {{-- SCHOLARS TAB --}}
        <section class="scholar-tab-panel active" id="panel-scholars" role="tabpanel" aria-labelledby="tab-scholars">
            <div class="data-card" aria-label="Scholar registry">
                <div class="data-card-body grid grid-cols-1 items-end gap-[12px] sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                    <div class="min-w-0">
                        <label class="field-label" for="scholarsSearch">Search</label>
                        <div class="relative">
                            <svg viewBox="0 0 24 24" aria-hidden="true" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:14px;height:14px;stroke:var(--color-ink-muted);fill:none;stroke-width:2;pointer-events:none;">
                                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            </svg>
                            <input type="search" id="scholarsSearch" placeholder="Search scholars…"
                                   aria-label="Search scholars"
                                   class="w-full pl-10 pr-4 py-2 text-sm border border-[#e2e5ea] rounded-full focus:border-[#fcd116] focus:ring-2 focus:ring-[#fcd116]/30 focus:bg-white outline-none transition-all duration-200"
                                   style="font-family:inherit;color:var(--color-ink);background:var(--color-bg);">
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto px-[1.25rem] pb-[1.25rem]" tabindex="0" aria-label="Scholar table, scrolls horizontally on narrow screens">
                    <table id="scholarsTable" class="table table-sm" style="width:100%;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Client ID</th>
                                <th>Full Name</th>
                                <th>Program</th>
                                <th>Barangay</th>
                                <th>Town</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Loaded via AJAX (server-side DataTables) --}}
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- GIP PROFILES TAB --}}
        <section class="scholar-tab-panel" id="panel-gip" role="tabpanel" aria-labelledby="tab-gip">
            <div class="data-card" aria-label="GIP profiles">
                <div class="overflow-x-auto px-[1.25rem] pb-[1.25rem]" tabindex="0" aria-label="GIP profiles table">
                    <table id="gipTable" class="table table-sm" style="width:100%;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Intern</th>
                                <th>College / Course</th>
                                <th>Year Graduated</th>
                                <th>Work Experience</th>
                                <th>Achievements</th>
                                <th class="actions-col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Loaded via AJAX --}}
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- SCHOLARSHIP REPORTS TAB --}}
        @if ($canViewReports)
            <section class="scholar-tab-panel" id="panel-reports" role="tabpanel" aria-labelledby="tab-reports">
                <div class="data-card" aria-label="Scholarship reports">
                <div class="data-card-body grid grid-cols-1 items-end gap-[12px] sm:grid-cols-2 lg:grid-cols-4">
                    <div class="min-w-0">
                        <label class="field-label" for="filterMunicipality">Municipality</label>
                        <select id="filterMunicipality" class="form-select form-select-sm">
                            <option value="">All Municipalities</option>
                            @foreach ($municipalities as $municipality)
                                <option value="{{ $municipality->id }}">{{ $municipality->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label class="field-label" for="filterBarangay">Barangay</label>
                        <select id="filterBarangay" class="form-select form-select-sm">
                            <option value="">All Barangays</option>
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label class="field-label" for="filterProgram">Program</label>
                        <select id="filterProgram" class="form-select form-select-sm">
                            <option value="">All Programs</option>
                            @foreach ($programs as $program)
                                <option value="{{ $program }}">{{ $program }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label class="field-label" for="filterSubmitted">Submitted</label>
                        <select id="filterSubmitted" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="Yes">Yes</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label class="field-label" for="filterDateFrom">Date Applied (From)</label>
                        <input type="date" id="filterDateFrom" class="form-control form-control-sm">
                    </div>
                    <div class="min-w-0">
                        <label class="field-label" for="filterDateTo">Date Applied (To)</label>
                        <input type="date" id="filterDateTo" class="form-control form-control-sm">
                    </div>
                    <div class="flex items-end gap-2 max-lg:w-full lg:col-span-2 lg:justify-end">
                        <button id="applyReportFilters" class="btn-navy w-full lg:w-auto">Filter</button>
                        <button id="resetReportFilters" class="btn-subtle w-full lg:w-auto">Reset</button>
                        <button id="exportReportCsv" class="btn-gold w-full lg:w-auto">Export CSV</button>
                    </div>
                </div>

                <div class="overflow-x-auto px-[1.25rem] pb-[1.25rem]" tabindex="0" aria-label="Scholarship reports table">
                    <table id="reportsTable" class="table table-sm" style="width:100%;">
                        <thead>
                            <tr>
                                <th>Program</th>
                                <th>Full Name</th>
                                <th>Mobile No</th>
                                <th>Sex</th>
                                <th>Birthdate</th>
                                <th>Civil Status</th>
                                <th>Town</th>
                                <th>Barangay</th>
                                <th>School</th>
                                <th>Course</th>
                                <th>Year Level</th>
                                <th>GWA</th>
                                <th>Units</th>
                                <th>Landbank No</th>
                                <th>Remarks</th>
                                <th>Date Applied</th>
                                <th>Regular</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Loaded via AJAX --}}
                        </tbody>
                    </table>
                </div>
            </div>
            </section>
        @endif

        {{-- UPDATE LOG TAB --}}
        @if ($canViewLogs)
            <section class="scholar-tab-panel" id="panel-logs" role="tabpanel" aria-labelledby="tab-logs">
                <div class="data-card" aria-label="Update logs">
                <div class="data-card-body grid grid-cols-1 items-end gap-[12px] sm:grid-cols-2 lg:grid-cols-[minmax(0,auto)_minmax(0,auto)_auto]">
                    <div class="min-w-0">
                        <label class="field-label" for="logStartDate">From</label>
                        <input type="date" id="logStartDate" class="form-control form-control-sm">
                    </div>
                    <div class="min-w-0">
                        <label class="field-label" for="logEndDate">To</label>
                        <input type="date" id="logEndDate" class="form-control form-control-sm">
                    </div>
                    <div class="flex items-end gap-2 max-lg:w-full">
                        <button id="applyLogFilters" class="btn-navy w-full lg:w-auto">Filter</button>
                        <a href="{{ route('update-logs.index') }}" class="btn-subtle no-underline w-full text-center lg:w-auto">Reset</a>
                    </div>
                </div>

                <div class="overflow-x-auto px-[1.25rem] pb-[1.25rem]" tabindex="0" aria-label="Update logs table">
                    <table id="logsTable" class="table table-sm align-middle mb-0" style="width:100%;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Client ID</th>
                                <th>Full Name</th>
                                <th>Town</th>
                                <th>IP Address</th>
                                <th>Action</th>
                                <th>Date/Time (PHT)</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Server-rendered initial load, then DataTables --}}
                        </tbody>
                    </table>
</div>
            </section>
        @endif

        {{-- GRANTEE SELF-UPDATE TAB --}}
        <section class="scholar-tab-panel" id="panel-self-update" role="tabpanel" aria-labelledby="tab-self-update">
            @include('grantee_update._self_update_tab')
        </section>
    </div>

    {{-- C1: relink Client ID (replaces native prompt()) — Tailwind + Alpine
         (Phase 15). Moved off bootstrap.Modal / getOrCreateInstance /
         data-bs-*. Trigger contract preserved exactly: the .edit-client-id
         buttons (rendered inside DataTables AJAX rows) still carry data-id /
         data-clientid; the existing delegated jQuery click handler now hands
         those values explicitly into Alpine state (no relatedTarget). The
         jQuery$.ajax submission to scholars.update-client-id, the
         window.scholarsTable.ajax.reload(null, false) refresh, and the error
         alert are all preserved unchanged. --}}
    <div x-data="clientIdPromptModalComponent()"
         x-cloak
         role="dialog"
         aria-modal="true"
         aria-labelledby="clientIdPromptModalTitle"
         aria-describedby="clientIdPromptModalBody"
         class="pointer-events-none fixed inset-0 z-[200]">
        <div x-show="$store.clientIdPromptModal.open"
             x-transition.opacity.duration.200ms
             @click="$store.clientIdPromptModal.close()"
             class="pointer-events-auto absolute inset-0 bg-ink/40"
             aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center overflow-y-auto p-4">
            <div x-show="$store.clientIdPromptModal.open"
                 x-ref="dialog"
                 x-transition.opacity.duration.200ms
                 @keydown.escape.window="$store.clientIdPromptModal.close()"
                 @keydown.tab.prevent.stop="handleTab($event)"
                 class="pointer-events-auto flex max-h-[90vh] w-full max-w-[400px] flex-col overflow-hidden rounded-panel bg-surface shadow-pop ring-1 ring-line">
                <div class="flex shrink-0 items-center justify-between gap-2 border-b border-line bg-navy px-[1.25rem] py-[1rem]">
                    <h5 id="clientIdPromptModalTitle" class="mb-0 text-dense font-heading font-semibold text-white">Relink Client ID</h5>
                    <button type="button"
                        class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-btn text-white/70 transition duration-150 ease-standard hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-gold"
                        @click="$store.clientIdPromptModal.close()"
                        aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div id="clientIdPromptModalBody" class="min-h-0 flex-1 overflow-y-auto p-[1.25rem]">
                    <label class="field-label" for="clientIdPromptInput">New Client ID</label>
                    <input type="number" id="clientIdPromptInput" class="field-control" min="1">
                </div>
                <div class="flex shrink-0 items-center justify-end gap-2 border-t border-line bg-neutral-100 px-[1.25rem] py-[0.9rem]">
                    <button type="button" class="btn-subtle" @click="$store.clientIdPromptModal.close()">Cancel</button>
                    <button type="button" class="btn-navy" id="clientIdPromptConfirm">Relink</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            document.addEventListener('alpine:init', function () {
                Alpine.store('clientIdPromptModal', {
                    open: false,
                    _prevFocus: null,
                    relinkId: null,
                    relinkCurrent: null,
                    openFor: function (id, current) {
                        if (this.open) return;
                        this._prevFocus = document.activeElement;
                        this.relinkId = id;
                        this.relinkCurrent = current;
                        var input = document.getElementById('clientIdPromptInput');
                        if (input) input.value = (current == null ? '' : current);
                        document.body.style.overflow = 'hidden';
                        this.open = true;
                        Alpine.nextTick(function () {
                            var inputEl = document.getElementById('clientIdPromptInput');
                            if (inputEl) inputEl.focus();
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
                    }
                });
            });

            window.clientIdPromptModalComponent = function () {
                return {
                    get open() { return this.$store.clientIdPromptModal.open; },
                    close: function () { this.$store.clientIdPromptModal.close(); },
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
    <script>
        $(document).ready(function() {
            $.ajaxSetup({
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });

            // ── Tab switching ──
            $('.scholar-tab-btn').on('click', function() {
                var tab = $(this).data('tab');
                $('.scholar-tab-btn').removeClass('active').attr('aria-selected', 'false');
                $(this).addClass('active').attr('aria-selected', 'true');
                $('.scholar-tab-panel').removeClass('active');
                $('#panel-' + tab).addClass('active');

                // Initialize DataTable for the tab if not yet initialized
                if (tab === 'scholars' && !window.scholarsTable) initScholarsTable();
                if (tab === 'gip' && !window.gipTable) initGipTable();
                if (tab === 'reports' && !window.reportsTable) initReportsTable();
                if (tab === 'logs' && !window.logsTable) initLogsTable();
            });

            // ── SCHOLARS TABLE ──
            function initScholarsTable() {
                window.scholarsTable = $('#scholarsTable').DataTable({
                    processing: true,
                    serverSide: true,
                    autoWidth: false,
                    pageLength: 25,
                    order: [[1, 'asc']],
                    ajax: {
                        url: '{{ route('scholars.data') }}',
                        type: 'POST',
                        data: function(d) {
                            d.search = $('#scholarsSearch').val();
                        }
                    },
                    language: { emptyTable: 'No scholars found.' },
                    columns: [
                        { data: 'id', name: 'id' },
                        {
                            data: 'client_id', name: 'client_id',
                            render: function(data, type, row) {
                                return '<span class="client-id" data-id="' + row.id + '">' + data + '</span> ' +
                                    '<button class="btn-subtle edit-client-id" data-id="' + row.id +
                                    '" data-clientid="' + data + '">Edit</button>';
                            }
                        },
                        { data: 'full_name', name: 'full_name' },
                        { data: 'program', name: 'program' },
                        { data: 'barangay', name: 'barangay' },
                        { data: 'town', name: 'town' }
                    ],
                    createdRow: function(row, data) {
                        $(row).attr({
                            'data-id': data.id,
                            'tabindex': 0,
                            'aria-label': 'Scholar ' + data.id + ', open details'
                        });
                    }
                });

                // Toolbar search
                $('#scholarsSearch').on('input', function() {
                    clearTimeout(this.searchTimer);
                    this.searchTimer = setTimeout(() => {
                        window.scholarsTable.search(this.value).draw();
                    }, 250);
                });

                // Row click -> shared details panel
                $('#scholarsTable tbody').on('click', 'tr', function(e) {
                    if ($(e.target).closest('.actions-col').length) return;
                    var id = $(this).data('id');
                    if (id) {
                        window.DetailsPanel.load('scholars', id, {
                            url: '{{ route('scholars.show', '__ID__') }}'.replace('__ID__', id) + '?panel=1'
                        });
                    }
                });

                $('#scholarsTable tbody').on('keydown', 'tr[tabindex]', function(e) {
                    if (e.key !== 'Enter' && e.key !== ' ') return;
                    if ($(e.target).closest('.actions-col').length) return;
                    e.preventDefault();
                    var id = $(this).data('id');
                    if (id) {
                        window.DetailsPanel.load('scholars', id, {
                            url: '{{ route('scholars.show', '__ID__') }}'.replace('__ID__', id) + '?panel=1'
                        });
                    }
                });

                // Client ID relink (C1: modal replaces native prompt())
                // Phase 15: Bootstrap opening/hiding replaced by the Alpine
                // clientIdPromptModal store; the delegated trigger contract
                // (data-id / data-clientid on .edit-client-id) is preserved and
                // works for DataTables AJAX-generated rows. The jQuery$.ajax
                // submission, success table reload, and error alert are unchanged.
                $(document).on('click', '.edit-client-id', function() {
                    Alpine.store('clientIdPromptModal').openFor(
                        $(this).data('id'),
                        $(this).data('clientid')
                    );
                });

                $('#clientIdPromptConfirm').on('click', function() {
                    var store = Alpine.store('clientIdPromptModal');
                    var input = document.getElementById('clientIdPromptInput');
                    if (!store || !input) return;
                    var id = store.relinkId;
                    var currentVal = store.relinkCurrent;
                    var newVal = String(input.value || '').trim();
                    if (newVal === '' || newVal === String(currentVal)) {
                        return;
                    }
                    $.ajax({
                        url: '{{ route('scholars.update-client-id') }}',
                        type: 'POST',
                        data: { id: id, client_id: newVal },
                        success: function() {
                            store.close();
                            window.scholarsTable.ajax.reload(null, false);
                        },
                        error: function() {
                            store.close();
                            alert('Error updating Client ID');
                        }
                    });
                });
            }

            // ── GIP PROFILES TABLE ──
            function initGipTable() {
                window.gipTable = $('#gipTable').DataTable({
                    processing: true,
                    serverSide: true,
                    autoWidth: false,
                    ajax: {
                        url: '{{ route('scholars.gip-data') }}',
                        type: 'POST'
                    },
                    columns: [
                        { data: 'id' },
                        { data: 'intern_name' },
                        { data: 'college_course' },
                        { data: 'year_graduated' },
                        { data: 'work_experience' },
                        { data: 'achievements' },
                        { data: 'actions' }
                    ],
                    columnDefs: [{ targets: 6, orderable: false, searchable: false }],
                    order: [[0, 'asc']],
                    pageLength: 25,
                    lengthMenu: [25, 50, 100],
                    createdRow: function(row, data) {
                        $(row).attr({
                            'data-id': data.id,
                            'tabindex': 0,
                            'aria-label': 'GIP profile ' + data.id + ', open details'
                        });
                    }
                });

                $('#gipTable tbody').on('click', 'tr', function(e) {
                    if ($(e.target).closest('.actions-col').length) return;
                    var id = $(this).data('id');
                    if (id) {
                        window.DetailsPanel.load('gip', id, {
                            url: '{{ route('scholars.gip-show', '__ID__') }}'.replace('__ID__', id) + '?panel=1'
                        });
                    }
                });
            }

            // ── REPORTS TABLE ──
            function initReportsTable() {
                window.reportsTable = $('#reportsTable').DataTable({
                    processing: true,
                    serverSide: true,
                    autoWidth: false,
                    ajax: {
                        url: '{{ route('scholarship-reports.data') }}',
                        type: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        data: function(d) {
                            d.municipality = $('#filterMunicipality').val();
                            d.barangay = $('#filterBarangay').val();
                            d.program = $('#filterProgram').val();
                            d.submitted = $('#filterSubmitted').val();
                            d.date_from = $('#filterDateFrom').val();
                            d.date_to = $('#filterDateTo').val();
                        }
                    },
                    columns: [
                        { data: 'program' }, { data: 'full_name' }, { data: 'mobile_no' },
                        { data: 'sex' }, { data: 'birthdate' }, { data: 'civil_status' },
                        { data: 'municipality' }, { data: 'barangay' }, { data: 'school' },
                        { data: 'course' }, { data: 'year_level' }, { data: 'gwa' },
                        { data: 'units' }, { data: 'landbank_no' }, { data: 'remarks' },
                        { data: 'date_applied' }, { data: 'regular' }, { data: 'submitted' }
                    ],
                    order: [[1, 'asc']],
                    pageLength: 10,
                    lengthMenu: [10, 25, 50, 100],
                    scrollX: true
                });

                $('#applyReportFilters').on('click', function() { window.reportsTable.draw(); });

                $('#filterMunicipality').on('change', function() {
                    var selectedId = $(this).val();
                    var barangaySelect = $('#filterBarangay');
                    barangaySelect.html('<option value="">All Barangays</option>');
                    if (selectedId) {
                        fetch('{{ route('geography.barangays') }}?municipality_id=' + selectedId)
                            .then(r => r.json())
                            .then(data => {
                                data.forEach(function(b) {
                                    var safeName = $('<div/>').text(b.name).html();
                                    barangaySelect.append('<option value="' + b.id + '">' + safeName + '</option>');
                                });
                            })
                            .catch(err => console.error('Failed to load barangays', err));
                    }
                });

                $('#resetReportFilters').on('click', function() {
                    $('#filterMunicipality').val('');
                    $('#filterBarangay').html('<option value="">All Barangays</option>').val('');
                    $('#filterProgram').val('');
                    $('#filterSubmitted').val('');
                    $('#filterDateFrom').val('');
                    $('#filterDateTo').val('');
                    window.reportsTable.draw();
                });

                $('#exportReportCsv').on('click', function() {
                    var query = new URLSearchParams({
                        municipality: $('#filterMunicipality').val() || '',
                        barangay: $('#filterBarangay').val() || '',
                        program: $('#filterProgram').val() || '',
                        submitted: $('#filterSubmitted').val() || '',
                        date_from: $('#filterDateFrom').val() || '',
                        date_to: $('#filterDateTo').val() || ''
                    }).toString();
                    window.location.href = '{{ route('scholarship-reports.export') }}?' + query;
                });
            }

            // ── UPDATE LOGS TABLE ──
            function initLogsTable() {
                window.logsTable = $('#logsTable').DataTable({
                    pageLength: 25,
                    autoWidth: false,
                    order: [[0, 'desc']],
                    language: {
                        search: 'Search Logs:',
                        lengthMenu: 'Show _MENU_ entries per page',
                        info: 'Showing _START_ to _END_ of _TOTAL_ logs',
                        paginate: { previous: 'Prev', next: 'Next' }
                    }
                });

                $('#applyLogFilters').on('click', function() {
                    window.logsTable.draw();
                });

                $('#logStartDate, #logEndDate').on('change', function() {
                    window.logsTable.draw();
                });

                $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                    if (settings.nTable.id !== 'logsTable') return true;
                    var min = $('#logStartDate').val();
                    var max = $('#logEndDate').val();
                    if (!min && !max) return true;
                    var dateRaw = $(window.logsTable.row(dataIndex).node()).find('td:eq(6)').text();
                    if (!dateRaw) return false;
                    var dateVal = new Date(dateRaw);
                    if ((min && dateVal < new Date(min)) || (max && dateVal > new Date(max))) return false;
                    return true;
                });
            }

            // Initialize the default tab
            initScholarsTable();
        });
    </script>
@endpush
