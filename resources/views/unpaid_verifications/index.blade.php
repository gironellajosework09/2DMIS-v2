@extends('layouts.app')

@section('title', 'Unpaid Verifications — 2D MIS')

{{-- Phase 1: Prototype-aligned Unpaid Verifications with shared details panel --}}
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/datatables.css') }}">
    <style>
        /* ── Unpaid verifications scope: token skin over the DataTables
           Bootstrap integration. Prefixed with #unpaid-screen —
           nothing here can leak to other screens. ── */
        #unpaid-screen table.dataTable td {
            font-size: 0.8rem;
        }

        #unpaid-screen table.dataTable th {
            font-size: 0.85rem;
            background-color: var(--color-navy);
            color: #fff;
            border-bottom: 0;
            white-space: nowrap;
        }

        #unpaid-screen table.dataTable tbody tr {
            cursor: pointer;
        }

        #unpaid-screen table.dataTable tbody tr:nth-child(odd) td {
            background-color: rgb(15 27 45 / 0.02);
        }

        #unpaid-screen table.dataTable tbody tr:hover td {
            background-color: rgb(37 99 235 / 0.06);
        }

        #unpaid-screen .actions-col {
            width: 170px;
            max-width: 170px;
            text-align: center;
            white-space: nowrap;
        }

        #unpaid-screen .actions-col .btn {
            padding: 2px 6px;
            font-size: 11px;
        }

        #unpaid-screen .dataTables_wrapper .dataTables_length select,
        #unpaid-screen .dataTables_wrapper .dataTables_filter input {
            border: 1px solid var(--color-line);
            border-radius: var(--radius-control);
            padding: 0.25rem 0.5rem;
            font-size: 0.85rem;
        }

        #unpaid-screen .dataTables_wrapper .dataTables_paginate .page-item.active .page-link {
            background-color: var(--color-navy);
            border-color: var(--color-navy);
        }

        #unpaid-screen .page-link {
            color: var(--color-navy);
        }
    </style>
@endpush

@section('content')
    @include('partials.breadcrumbs', [
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Unpaid Grantees'],
        ],
    ])

    @include('partials.page-header', [
        'title' => 'Unpaid Grantees',
        'subtitle' => 'Self-service verification submissions from unpaid beneficiaries.',
    ])

    <div id="unpaid-screen">
        <section class="data-card" aria-label="Unpaid verification records">
            <div class="data-card-body flex flex-col gap-[14px]">
                @include('partials.filter-chips', ['filterChips' => $filterChips])

                <div class="flex flex-wrap items-end gap-2">
                    <button id="resetFilters" class="btn-subtle w-full lg:w-auto">Reset</button>
                    <button id="exportCsv" class="btn-gold w-full lg:w-auto">Export CSV</button>
                </div>
            </div>

            <div class="overflow-x-auto px-[1.25rem] pb-[1.25rem]" tabindex="0" aria-label="Unpaid verification table, scrolls horizontally on narrow screens">
                <table id="unpaidTable" class="table table-sm" style="width:100%;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client Name</th>
                            <th>Municipality</th>
                            <th>Proxy?</th>
                            <th>Proxy Name</th>
                            <th>Relationship</th>
                            <th>Phone</th>
                            <th>Birthdate</th>
                            <th>Gender</th>
                            <th>Occupation</th>
                            <th>Monthly Income</th>
                            <th>Submitted At</th>
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

    @include('partials.record-view-modal', ['title' => 'Verification Details'])

    @include('partials.confirm-modal')
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="{{ asset('js/components/DetailsPanel.js') }}"></script>
    <script src="{{ asset('js/components/FilterChips.js') }}"></script>    <script>
        $(document).ready(function() {
            var dataUrl = '{{ route('unpaid-verifications.data') }}';

            var table = $('#unpaidTable').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                ajax: {
                    url: dataUrl,
                    type: 'POST',
                    data: function(d) {
                        var p = (window.unpaidFilters && window.unpaidFilters.getParams)
                            ? window.unpaidFilters.getParams()
                            : {};
                        d.municipality = p.municipality || '';
                        d.date_start = p.date_start || '';
                        d.date_end = p.date_end || '';
                    }
                },
                columns: [
                    { data: 'id' },
                    { data: 'client_name' },
                    { data: 'municipality_name' },
                    { data: 'is_proxy_label' },
                    { data: 'proxy_fullname' },
                    { data: 'proxy_relationship' },
                    { data: 'proxy_phone' },
                    { data: 'proxy_birthdate' },
                    { data: 'proxy_gender' },
                    { data: 'proxy_occupation' },
                    { data: 'proxy_monthlyincome' },
                    { data: 'created_at' },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        className: 'actions-col',
                        render: function(row) {
                            return '<div class="inline-flex gap-1">' +
                                '<button class="btn-subtle view-btn" data-id="' + row.id + '">View</button>' +
                                '<button class="btn-outline-red delete-btn" data-id="' + row.id + '">Delete</button>' +
                                '</div>';
                        }
                    }
                ],
                columnDefs: [{ targets: 12, orderable: false, searchable: false }],
                order: [[0, 'desc']],
                pageLength: 25,
                lengthMenu: [25, 50, 100],
                scrollX: true,
                createdRow: function(row, data) {
                    $(row).attr({
                        'data-id': data.id,
                        'tabindex': 0,
                        'aria-label': 'Unpaid verification ' + data.id + ', open details'
                    });
                }
            });

            // FilterChips — shared Phase 2C component (server-side DataTables feed).
            if (window.FilterChips) {
                window.unpaidFilters = FilterChips.init({
                    id: 'unpaid-filters',
                    host: document.querySelector('[data-filter-host="unpaid-filters"]'),
                    onApply: function() { table.draw(); }
                });
            }

            $('#resetFilters').on('click', function() {
                if (window.unpaidFilters && window.unpaidFilters.clearAll) {
                    window.unpaidFilters.clearAll();
                }
                table.draw();
            });

            $('#exportCsv').on('click', function() {
                var p = (window.unpaidFilters && window.unpaidFilters.getParams)
                    ? window.unpaidFilters.getParams()
                    : {};
                var query = new URLSearchParams({
                    municipality: p.municipality || '',
                    date_start: p.date_start || '',
                    date_end: p.date_end || ''
                }).toString();
                window.location.href = '{{ route('unpaid-verifications.export') }}?' + query;
            });

            // Row click -> shared details panel
            $('#unpaidTable tbody').on('click', 'tr', function(e) {
                if ($(e.target).closest('.actions-col').length) {
                    return;
                }
                var id = $(this).data('id');
                if (id) {
                    window.DetailsPanel.load('unpaid', id, {
                        url: dataUrl,
                        method: 'POST'
                    });
                }
            });

            // Keyboard twin (Enter / Space)
            $('#unpaidTable tbody').on('keydown', 'tr[tabindex]', function(e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                if ($(e.target).closest('.actions-col').length) return;
                e.preventDefault();
                var id = $(this).data('id');
                if (id) {
                    window.DetailsPanel.load('unpaid', id, {
                        url: dataUrl,
                        method: 'POST'
                    });
                }
            });

            $('#unpaidTable').on('click', '.view-btn', function() {
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
                            var html = `<dl class="row">
                                <dt class="col-sm-4">ID</dt><dd class="col-sm-8">${d.id}</dd>
                                <dt class="col-sm-4">Client Name</dt><dd class="col-sm-8">${d.client_name}</dd>
                                <dt class="col-sm-4">Municipality</dt><dd class="col-sm-8">${d.municipality_name}</dd>
                                <dt class="col-sm-4">Is Proxy?</dt><dd class="col-sm-8">${d.is_proxy_label}</dd>
                                <dt class="col-sm-4">Proxy Name</dt><dd class="col-sm-8">${d.proxy_fullname || '—'}</dd>
                                <dt class="col-sm-4">Relationship</dt><dd class="col-sm-8">${d.proxy_relationship || '—'}</dd>
                                <dt class="col-sm-4">Phone</dt><dd class="col-sm-8">${d.proxy_phone || '—'}</dd>
                                <dt class="col-sm-4">Birthdate</dt><dd class="col-sm-8">${d.proxy_birthdate || '—'}</dd>
                                <dt class="col-sm-4">Gender</dt><dd class="col-sm-8">${d.proxy_gender || '—'}</dd>
                                <dt class="col-sm-4">Occupation</dt><dd class="col-sm-8">${d.proxy_occupation || '—'}</dd>
                                <dt class="col-sm-4">Monthly Income</dt><dd class="col-sm-8">${d.proxy_monthlyincome || '—'}</dd>
                                <dt class="col-sm-4">Created At</dt><dd class="col-sm-8">${d.created_at}</dd>
                            </dl>`;
                            $('#viewBody').html(html);
                            window.uiViewModal.show();
                        } else {
                            alert('Unable to load record.');
                        }
                    });
            });

            $('#unpaidTable').on('click', '.delete-btn', function() {
                var id = $(this).data('id');

                window.uiConfirm({
                    title: 'Delete verification',
                    message: 'Are you sure you want to delete this record? This cannot be undone.',
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
                                alert('Failed to delete record.');
                            }
                        })
                        .catch(() => alert('Error deleting record.'));
                });
            });
        });
    </script>
@endpush