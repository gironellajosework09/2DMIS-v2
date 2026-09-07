@extends('layouts.app')

@section('title', $config['title'].' — 2D MIS')

{{-- Phase 1: Prototype-aligned Payouts with shared details panel --}}
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/datatables.css') }}">
    <style>
        /* ── Payouts scope: token skin over the DataTables Bootstrap
           integration. Prefixed with #payouts-screen —
           nothing here can leak to other screens. ── */
        #payouts-screen table.dataTable td {
            font-size: 0.8rem;
        }

        #payouts-screen table.dataTable th {
            font-size: 0.85rem;
            background-color: var(--color-navy);
            color: #fff;
            border-bottom: 0;
            white-space: nowrap;
        }

        #payouts-screen table.dataTable tbody tr {
            cursor: pointer;
        }

        #payouts-screen table.dataTable tbody tr:nth-child(odd) td {
            background-color: rgb(15 27 45 / 0.02);
        }

        #payouts-screen table.dataTable tbody tr:hover td {
            background-color: rgb(37 99 235 / 0.06);
        }

        #payouts-screen .actions-col {
            width: 100px;
            max-width: 100px;
            text-align: center;
            white-space: nowrap;
        }

        #payouts-screen .actions-col .btn {
            padding: 2px 6px;
            font-size: 11px;
        }

        #payouts-screen .dataTables_wrapper .dataTables_length select,
        #payouts-screen .dataTables_wrapper .dataTables_filter input {
            border: 1px solid var(--color-line);
            border-radius: var(--radius-control);
            padding: 0.25rem 0.5rem;
            font-size: 0.85rem;
        }

        #payouts-screen .dataTables_wrapper .dataTables_paginate .page-item.active .page-link {
            background-color: var(--color-navy);
            border-color: var(--color-navy);
        }

        #payouts-screen .page-link {
            color: var(--color-navy);
        }
    </style>
@endpush

@section('content')
    @include('partials.breadcrumbs', [
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => $config['title']],
        ],
    ])

    @php
        $scannerRoute = $config['scanner_route'];
        $scannerLabel = $config['scanner_label'];
    @endphp
    @include('partials.page-header', [
        'title' => $config['title'],
        'subtitle' => 'Payout attendance records with filtering.',
        'actions' => '
            <a href="{{ route($scannerRoute) }}" class="btn-gold no-underline">{{ $scannerLabel }}</a>',
    ])

    <div id="payouts-screen">
        <section class="data-card" aria-label="Payout attendance">
            <div class="data-card-body flex flex-col gap-[14px]">
                @include('partials.filter-chips', ['filterChips' => $filterChips])

                <div class="flex items-end gap-2 max-lg:w-full">
                    <button id="resetFilters" class="btn-subtle w-full lg:w-auto">Reset</button>
                </div>
            </div>

            <div class="overflow-x-auto px-[1.25rem] pb-[1.25rem]" tabindex="0" aria-label="Payout attendance table, scrolls horizontally on narrow screens">
                <table id="scannedTable" class="table table-sm" style="width:100%;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Txn ID</th>
                            <th>Program</th>
                            <th>Full Name</th>
                            <th>Municipality</th>
                            @if ($config['seat_table'])
                                <th>Section</th>
                                <th>Box</th>
                                <th>Row</th>
                                <th>Seat</th>
                            @endif
                            <th>Scanned By</th>
                            <th>Scanned At</th>
                            <th class="actions-col">Actions</th>
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
    @include('partials.record-view-modal', ['title' => $config['modal_title']])
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="{{ asset('js/components/DetailsPanel.js') }}"></script>
    <script src="{{ asset('js/components/FilterChips.js') }}"></script>
    <script>
        $(document).ready(function() {
            document.body.dataset.payoutVariant = '{{ $variant }}';
            var dataUrl = '{{ route('payout-attendance.'.$variant.'.data') }}';
            var showSeats = {{ $config['seat_table'] ? 'true' : 'false' }};
            var showUrl = '{{ route('payout-attendance.'.$variant.'.show', '__ID__') }}';

            var columns = [
                { data: 'id' },
                { data: 'transaction_id' },
                { data: 'program' },
                { data: 'client_name' },
                { data: 'municipality_name' }
            ];

            if (showSeats) {
                columns.push(
                    { data: 'section' },
                    { data: 'box' },
                    { data: 'row' },
                    { data: 'seat' }
                );
            }

            columns.push(
                { data: 'scanned_by_name' },
                { data: 'scanned_at' },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'actions-col',
                    render: function(row) {
                        return '<button class="btn btn-sm btn-primary view-btn" data-id="' + row.id + '">View</button>' +
                            '<button class="btn btn-sm btn-danger delete-btn" data-id="' + row.id + '">Delete</button>';
                    }
                }
            );

            var table = $('#scannedTable').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                ajax: {
                    url: dataUrl,
                    type: 'POST',
                    data: function(d) {
                        var p = (window.payoutFilters && window.payoutFilters.getParams)
                            ? window.payoutFilters.getParams()
                            : {};
                        d.municipality = p.municipality || '';
                        d.program = p.program || '';
                        d.scanned_start = p.scanned_start || '';
                        d.scanned_end = p.scanned_end || '';
                    }
                },
                columns: columns,
                columnDefs: [
                    // UX-3 column consolidation: municipality + scanned-by move
                    // to the DetailsPanel. Kept searchable/sortable (feed unchanged).
                    { targets: [4, (showSeats ? 9 : 5)], visible: false, searchable: true },
                    { targets: columns.length - 1, orderable: false, searchable: false }
                ],
                order: [[0, 'desc']],
                pageLength: 25,
                lengthMenu: [25, 50, 100],
                scrollX: true,
                createdRow: function(row, data) {
                    $(row).attr({
                        'data-id': data.id,
                        'tabindex': 0,
                        'aria-label': 'Payout ' + data.id + ', open details'
                    });
                }
            });

            // FilterChips — shared Phase 2C component (server-side DataTables feed).
            if (window.FilterChips) {
                window.payoutFilters = FilterChips.init({
                    id: 'payout-filters-{{ $variant }}',
                    host: document.querySelector('[data-filter-host="payout-filters-{{ $variant }}"]'),
                    onApply: function() { table.draw(); }
                });
            }

            $('#resetFilters').on('click', function() {
                if (window.payoutFilters && window.payoutFilters.clearAll) {
                    window.payoutFilters.clearAll();
                }
                table.draw();
            });

            // Row click -> shared details panel
            $('#scannedTable tbody').on('click', 'tr', function(e) {
                if ($(e.target).closest('.actions-col').length) {
                    return;
                }
                var id = $(this).data('id');
                if (id) {
                    window.DetailsPanel.load('payouts', id, {
                        url: showUrl.replace('__ID__', id) + '?panel=1',
                        method: 'GET'
                    });
                }
            });

            // Keyboard twin (Enter / Space)
            $('#scannedTable tbody').on('keydown', 'tr[tabindex]', function(e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                if ($(e.target).closest('.actions-col').length) return;
                e.preventDefault();
                var id = $(this).data('id');
                if (id) {
                    window.DetailsPanel.load('payouts', id, {
                        url: showUrl.replace('__ID__', id) + '?panel=1',
                        method: 'GET'
                    });
                }
            });

            $('#scannedTable').on('click', '.view-btn', function() {
                var id = $(this).data('id');
                $('#viewBody').html('Loading...');
                fetch(dataUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'single_id=' + encodeURIComponent(id)
                    })
                    .then(r => r.json())
                    .then(resp => {
                        if (resp && resp.single) {
                            var d = resp.single;
                            var seatRows = '';
                            if (showSeats) {
                                seatRows = `
                                    <dt class="col-sm-4">Section</dt><dd class="col-sm-8">${d.section || '—'}</dd>
                                    <dt class="col-sm-4">Box</dt><dd class="col-sm-8">${d.box || '—'}</dd>
                                    <dt class="col-sm-4">Row</dt><dd class="col-sm-8">${d.row || '—'}</dd>
                                    <dt class="col-sm-4">Seat</dt><dd class="col-sm-8">${d.seat || '—'}</dd>`;
                            }
                            var html = `<dl class="row">
                                <dt class="col-sm-4">Scan ID</dt><dd class="col-sm-8">${d.id}</dd>
                                <dt class="col-sm-4">Transaction ID</dt><dd class="col-sm-8">${d.transaction_id}</dd>
                                <dt class="col-sm-4">Program</dt><dd class="col-sm-8">${d.program}</dd>
                                <dt class="col-sm-4">Full Name</dt><dd class="col-sm-8">${d.client_name}</dd>
                                <dt class="col-sm-4">Municipality</dt><dd class="col-sm-8">${d.municipality_name}</dd>
                                ${seatRows}
                                <dt class="col-sm-4">Scanned By</dt><dd class="col-sm-8">${d.scanned_by_name}</dd>
                                <dt class="col-sm-4">Scanned At</dt><dd class="col-sm-8">${d.scanned_at}</dd>
                                <dt class="col-sm-4">Scanned Text</dt><dd class="col-sm-8"><pre style="white-space:pre-wrap;">${d.scanned_text}</pre></dd>
                            </dl>`;
                            $('#viewBody').html(html);
                            window.uiViewModal.show();
                        } else {
                            alert('Could not load details');
                        }
                    });
            });

            $('#scannedTable').on('click', '.delete-btn', function() {
                var id = $(this).data('id');

                window.uiConfirm({
                    title: 'Delete scanned payout',
                    message: 'Are you sure you want to delete this scanned payout?',
                    confirmLabel: 'Delete'
                }).then(function(ok) {
                    if (!ok) return;

                    fetch(dataUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: 'delete_id=' + encodeURIComponent(id)
                        })
                        .then(r => r.json())
                        .then(resp => {
                            if (resp.success) {
                                table.ajax.reload(null, false);
                            } else {
                                alert(resp.error || 'Failed to delete record.');
                            }
                        })
                        .catch(() => alert('Error deleting record.'));
                });
            });
        });
    </script>
@endpush