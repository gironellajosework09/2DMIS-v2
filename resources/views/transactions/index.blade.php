@extends('layouts.app')

@section('title', 'All Transactions — 2D MIS')

{{-- Phase 1: Prototype-aligned Transactions with shared details panel --}}
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/datatables.css') }}">
    <style>
        /* ── Transactions screen scope: token skin over the DataTables
           Bootstrap integration. Selectors are prefixed with
           #transactions-screen — nothing here can leak to other screens. ── */
        #transactions-screen table.dataTable td {
            font-size: 0.8rem;
        }

        #transactions-screen table.dataTable th {
            font-size: 0.85rem;
            background-color: var(--color-navy);
            color: #fff;
            border-bottom: 0;
            white-space: nowrap;
        }

        #transactions-screen table.dataTable tbody tr {
            cursor: pointer;
        }

        #transactions-screen table.dataTable tbody tr:nth-child(odd) td {
            background-color: rgb(15 27 45 / 0.02);
        }

        #transactions-screen table.dataTable tbody tr:hover td {
            background-color: rgb(37 99 235 / 0.06);
        }

        #transactions-screen table.dataTable td.num-cell {
            font-variant-numeric: tabular-nums;
        }

        #transactions-screen .actions-col {
            width: 120px;
            max-width: 120px;
            text-align: center;
            white-space: nowrap;
        }

        #transactions-screen .actions-col .btn {
            padding: 2px 6px;
            font-size: 11px;
        }

        /* DataTables chrome (length/info/pagination) aligned to tokens. */
        #transactions-screen .dataTables_wrapper .dataTables_length select,
        #transactions-screen .dataTables_wrapper .dataTables_filter input {
            border: 1px solid var(--color-line);
            border-radius: var(--radius-control);
            padding: 0.25rem 0.5rem;
            font-size: 0.85rem;
        }

        #transactions-screen .dataTables_wrapper .dataTables_paginate .page-item.active .page-link {
            background-color: var(--color-navy);
            border-color: var(--color-navy);
        }

        #transactions-screen .page-link {
            color: var(--color-navy);
        }

        #transactions-screen .dataTables_wrapper .dataTables_processing {
            background-color: var(--color-surface, #fff);
            border: 1px solid var(--color-line);
            border-radius: var(--radius-panel);
            box-shadow: var(--shadow-pop);
            color: var(--color-ink-muted);
            font-size: 0.85rem;
            padding: 0.5rem 1rem;
        }
    </style>
@endpush

@section('content')
    @include('partials.breadcrumbs', [
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Transactions'],
        ],
    ])

    @include('partials.page-header', [
        'title' => 'All Transactions',
        'subtitle' => 'Assistance transactions across programs, with filtering and CSV exports.',
        'actions' => '
            <div class="btn-group" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                <button type="button" class="btn-subtle dropdown-toggle" @click="open = !open" :aria-expanded="open.toString()">
                    Export
                </button>
                <ul class="dropdown-menu dropdown-menu-end" :class="{ \'show\': open }">
                    <li><button type="button" class="dropdown-item export-link" data-mode="csv">Export CSV</button></li>
                    <li><button type="button" class="dropdown-item export-link" data-mode="custom">Export Custom CSV</button></li>
                    <li><button type="button" class="dropdown-item export-link" data-mode="custom2">Export CSV 2</button></li>
                    <li><button type="button" class="dropdown-item export-link" data-mode="gip">Export GIP Report</button></li>
                </ul>
            </div>',
    ])

    @if (session('success'))
        {{-- Same persistent toast pattern as the layout flash channel:
             manual dismiss, live-region semantics, no silent expiry.
             Phase 20: Alpine state owns visibility + dismissal (was
             bootstrap.Toast via the layout init loop); server flash
             contract unchanged. --}}
        <div class="pointer-events-none fixed inset-x-0 top-[76px] z-[1100] flex flex-col items-end gap-2 px-[1rem] sm:px-[1.75rem]" aria-live="polite">
            <div x-data="{ open: true }"
                 x-show="open"
                 class="pointer-events-auto flex w-full max-w-[420px] items-start gap-[12px] rounded-panel bg-surface p-[1rem] shadow-pop ring-1 ring-line"
                 role="status">
                <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-pill bg-teal/[0.12] text-teal" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                <span class="text-dense leading-snug text-ink">{{ session('success') }}</span>
                <button type="button" class="btn-close shrink-0" @click="open = false" aria-label="Close"></button>
            </div>
        </div>
    @endif

    <div id="transactions-screen" class="data-card p-[1.25rem]">
        @include('partials.filter-chips', ['filterChips' => $filterChips])

        <div class="table-responsive" tabindex="0" aria-label="All transactions table, scrollable horizontally">
            <table id="transactionsTable" class="table table-sm" style="width:100%;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Client ID</th>
                        <th>Date Applied</th>
                        <th>Program</th>
                        <th>Client Name</th>
                        <th>Beneficiary</th>
                        <th>Mobile No</th>
                        <th>Barangay</th>
                        <th>Municipality</th>
                        <th>Type</th>
                        <th>Remarks</th>
                        <th>Comments</th>
                        <th>Suggested Amount</th>
                        <th>Status</th>
                        <th>Amount Paid</th>
                        <th>Pay Out Date</th>
                        <th>Date Paid</th>
                        <th>GWA</th>
                        <th>Units</th>
                        <th>Created At</th>
                        <th class="actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    @include('partials.confirm-modal')
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="{{ asset('js/components/DetailsPanel.js') }}"></script>
    <script src="{{ asset('js/components/FilterChips.js') }}"></script>
    <script>
        $(document).ready(function() {
            var table = $('#transactionsTable').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                scrollX: true,
                ajax: {
                    url: '{{ route('transactions.data') }}',
                    type: 'POST',
                    data: function(d) {
                        var p = (window.transactionFilters && window.transactionFilters.getParams)
                            ? window.transactionFilters.getParams()
                            : {};
                        d.program = p.program || '';
                        d.status = p.status || '';
                        d.municipality = p.municipality || '';
                        d.barangay = p.barangay || '';
                        d.date_applied_start = p.date_applied_start || '';
                        d.date_applied_end = p.date_applied_end || '';
                        d.date_paid_start = p.date_paid_start || '';
                        d.date_paid_end = p.date_paid_end || '';
                    }
                },
                columns: [
                    { data: "id" },
                    { data: "client_id" },
                    { data: "date_applied" },
                    { data: "program" },
                    { data: "client_name" },
                    { data: "patient_name" },
                    { data: "mobile_no" },
                    { data: "barangay" },
                    { data: "city_municipality" },
                    { data: "type" },
                    { data: "remarks" },
                    { data: "comments" },
                    { data: "suggested_amount", className: "num-cell" },
                    { data: "status", render: function(data) {
                        if (!data) return '';
                        var cls = data === 'PAID' ? 'is-paid'
                            : (String(data).indexOf('PENDING') === 0 ? 'is-pending' : 'is-neutral');
                        return '<span class="status-badge ' + cls + '">' + data + '</span>';
                    } },
                    { data: "amount_paid", className: "num-cell" },
                    { data: "payout_date" },
                    { data: "date_paid" },
                    { data: "gwa" },
                    { data: "units" },
                    { data: "created_at" },
                    { data: "actions" }
                ],
                language: {
                    emptyTable: 'No transactions found.'
                },
                columnDefs: [
                    // UX-3 column consolidation: keep the transaction table
                    // readable (~13 visible). The hidden columns below remain
                    // fully searchable + sortable (server-side feed unchanged) so
                    // V1 sort/filter/search parity is preserved; they surface in
                    // the DetailsPanel and the full-page edit. Inline-edit cell
                    // columns (remarks/comments/suggested/status/amount/gwa/
                    // units) all stay VISIBLE so the td-click protocol is intact.
                    { targets: [2, 5, 6, 7, 8, 9, 15, 19], visible: false, searchable: true },
                    { targets: 20, orderable: false, searchable: false }
                ],
                order: [[4, 'asc']],
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                createdRow: function(row, data) {
                    $(row).attr({
                        'data-id': data.id,
                        'tabindex': 0,
                        'aria-label': 'Transaction ' + data.id + ', open details'
                    });
                }
            });

            function displayToIso(display) {
                if (!display) return '';
                var parts = String(display).split('/');
                if (parts.length !== 3) return '';
                var mm = ('0' + parseInt(parts[0], 10)).slice(-2);
                var dd = ('0' + parseInt(parts[1], 10)).slice(-2);
                return parts[2] + '-' + mm + '-' + dd;
            }

            function isoToDisplay(iso) {
                if (!iso) return '';
                var d = new Date(iso + 'T00:00:00');
                if (isNaN(d)) return iso;
                return (d.getMonth() + 1) + '/' + d.getDate() + '/' + d.getFullYear();
            }

            function formatCurrency(val) {
                if (val === null || val === undefined || val === '') return '';
                var n = String(val).replace(/,/g, '');
                var num = parseFloat(n);
                if (isNaN(num)) return '';
                return num.toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function currencyInput(value) {
                return '<input type="number" step="0.01" class="form-control form-control-sm" value="' + (value ?? '').replace(/,/g, '') + '">';
            }

            function showRowActions($row, editing) {
                $row.find('.edit-btn, .delete-btn').toggleClass('d-none', editing);
                $row.find('.save-btn, .cancel-btn').toggleClass('d-none', !editing);
            }

            $('#transactionsTable tbody').on('click', '.edit-btn', function() {
                var $row = $(this).closest('tr');
                var rowData = table.row($row).data();
                showRowActions($row, true);

                $row.find('td:eq(10)').html('<input type="text" class="form-control form-control-sm" value="' + (rowData.remarks ?? '') + '">');
                $row.find('td:eq(11)').html('<input type="text" class="form-control form-control-sm" value="' + (rowData.comments ?? '') + '">');
                $row.find('td:eq(12)').html(currencyInput(rowData.suggested_amount));
                $row.find('td:eq(13)').html(
                    '<select class="form-select form-select-sm">' +
                    '<option value="">-- Select --</option>' +
                    '<option value="PAID"' + (rowData.status === 'PAID' ? ' selected' : '') + '>PAID</option>' +
                    '<option value="PENDING PAYOUT"' + (rowData.status === 'PENDING PAYOUT' ? ' selected' : '') + '>PENDING PAYOUT</option>' +
                    '</select>'
                );
                $row.find('td:eq(14)').html(currencyInput(rowData.amount_paid));
                $row.find('td:eq(16)').html('<input type="date" class="form-control form-control-sm" value="' + displayToIso(rowData.date_paid) + '">');
                $row.find('td:eq(17)').html('<input type="number" step="0.01" class="form-control form-control-sm" value="' + (rowData.gwa ?? '') + '">');
                $row.find('td:eq(18)').html('<input type="number" step="1" class="form-control form-control-sm" value="' + (rowData.units ?? '') + '">');
            });

            $('#transactionsTable tbody').on('click', '.save-btn', function() {
                var $row = $(this).closest('tr');
                var rowData = table.row($row).data();

                var payload = {
                    id: rowData.id,
                    remarks: $row.find('td:eq(10) input').val(),
                    comments: $row.find('td:eq(11) input').val(),
                    suggested_amount: $row.find('td:eq(12) input').val(),
                    status: $row.find('td:eq(13) select').val(),
                    amount_paid: $row.find('td:eq(14) input').val(),
                    date_paid: $row.find('td:eq(16) input').val(),
                    gwa: $row.find('td:eq(17) input').val(),
                    units: $row.find('td:eq(18) input').val()
                };

                fetch('{{ route('transactions.inline-update') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            rowData.remarks = payload.remarks;
                            rowData.comments = payload.comments;
                            rowData.suggested_amount = payload.suggested_amount ? formatCurrency(payload.suggested_amount) : '';
                            rowData.status = payload.status;
                            rowData.amount_paid = payload.amount_paid ? formatCurrency(payload.amount_paid) : '';
                            rowData.date_paid = payload.date_paid ? isoToDisplay(payload.date_paid) : '';
                            rowData.gwa = payload.gwa === '' ? '' : payload.gwa;
                            rowData.units = payload.units === '' ? '' : payload.units;
                            table.row($row).data(rowData).draw(false);
                            showRowActions($row, false);
                        } else {
                            alert('Failed to update transaction. ' + (res.message || ''));
                        }
                    })
                    .catch(() => alert('Request failed.'));
            });

            $('#transactionsTable tbody').on('click', '.cancel-btn', function() {
                var $row = $(this).closest('tr');
                showRowActions($row, false);
                table.ajax.reload(null, false);
            });

            // FilterChips — shared Phase 2C component (server-side DataTables feed).
            if (window.FilterChips) {
                window.transactionFilters = FilterChips.init({
                    id: 'transactions-filters',
                    host: document.querySelector('[data-filter-host="transactions-filters"]'),
                    onApply: function() {
                        table.draw();
                    }
                });
            }

            $('.export-link').on('click', function() {
                var mode = $(this).data('mode');
                var p = (window.transactionFilters && window.transactionFilters.getParams)
                    ? window.transactionFilters.getParams()
                    : {};
                var query = new URLSearchParams({
                    export_mode: mode,
                    program: p.program || '',
                    status: p.status || '',
                    municipality: p.municipality || '',
                    barangay: p.barangay || '',
                    date_applied_start: p.date_applied_start || '',
                    date_applied_end: p.date_applied_end || '',
                    date_paid_start: p.date_paid_start || '',
                    date_paid_end: p.date_paid_end || ''
                }).toString();
                window.location.href = '{{ route('transactions.export') }}?' + query;
            });

            $('#transactionsTable').on('click', '.delete-transaction', function() {
                var id = $(this).data('id');
                window.uiConfirm({
                    title: 'Delete transaction',
                    message: 'Are you sure you want to delete this transaction? This cannot be undone.',
                    confirmLabel: 'Delete'
                }).then(function(ok) {
                    if (!ok) return;
                    fetch('{{ route('transactions.index') }}/' + id, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                        })
                        .then(r => r.json())
                        .then(res => {
                            if (res.success) {
                                table.draw();
                            } else {
                                alert(res.message || 'Failed to delete transaction.');
                            }
                        })
                        .catch(() => alert('Error deleting transaction.'));
                });
            });

            // Row click -> shared details panel
            $('#transactionsTable tbody').on('click', 'tr', function(e) {
                if ($(e.target).closest('.actions-col').length) {
                    return;
                }
                var id = $(this).data('id');
                if (id) {
                    window.DetailsPanel.load('transactions', id, {
                        url: '{{ route('transactions.show', '__ID__') }}'.replace('__ID__', id) + '?panel=1'
                    });
                }
            });

            // Keyboard twin (Enter / Space)
            $('#transactionsTable tbody').on('keydown', 'tr[tabindex]', function(e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                if ($(e.target).closest('.actions-col').length) return;
                e.preventDefault();
                var id = $(this).data('id');
                if (id) {
                    window.DetailsPanel.load('transactions', id, {
                        url: '{{ route('transactions.show', '__ID__') }}'.replace('__ID__', id) + '?panel=1'
                    });
                }
            });
        });
    </script>
@endpush