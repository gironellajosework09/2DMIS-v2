@extends('layouts.app')

@section('title', 'Unpaid Verifications — 2D MIS')

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        table.dataTable {
            font-size: 0.75rem !important;
        }

        table.dataTable td {
            font-size: 0.75rem !important;
            padding: 4px 6px !important;
        }

        .dt-filters {
            gap: .5rem;
            align-items: center;
            margin-bottom: .75rem;
        }

        .dt-filters .form-select {
            min-width: 180px;
        }

        .actions-col {
            width: 90px !important;
            max-width: 90px !important;
            text-align: center;
            white-space: nowrap;
        }

        .actions-col .btn {
            padding: 2px 5px;
            font-size: 10px;
        }
    </style>
@endpush

@section('content')
    <div class="card shadow-lg border-0 p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Unpaid Grantees</h3>
        </div>

        <div class="dt-filters d-flex flex-wrap align-items-end gap-3 mb-3">
            <div style="min-width:200px;">
                <label class="form-label mb-1">Municipality</label>
                <select id="filterMunicipality" class="form-select form-select-sm">
                    <option value="">All Municipalities</option>
                    @foreach ($municipalities as $municipality)
                        <option value="{{ $municipality->id }}">{{ $municipality->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label mb-1">Date Start</label>
                <input type="date" id="date_start" class="form-control form-control-sm">
            </div>

            <div>
                <label class="form-label mb-1">Date End</label>
                <input type="date" id="date_end" class="form-control form-control-sm">
            </div>

            <div class="ms-auto d-flex align-items-end gap-2">
                <button id="applyFilters" class="btn btn-primary btn-sm">Filter</button>
                <button id="resetFilters" class="btn btn-secondary btn-sm">Reset</button>
                <button id="exportCsv" class="btn btn-success btn-sm">Export CSV</button>
            </div>
        </div>

        <div class="table-responsive">
            <table id="unpaidTable" class="table table-striped table-bordered table-sm w-100">
                <thead class="table-dark">
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
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Verification Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewBody">Loading...</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            var dataUrl = '{{ route('unpaid-verifications.data') }}';

            var table = $('#unpaidTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: dataUrl,
                    type: 'POST',
                    data: function(d) {
                        d.municipality = $('#filterMunicipality').val();
                        d.date_start = $('#date_start').val();
                        d.date_end = $('#date_end').val();
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
                            return '<div class="btn-group">' +
                                '<button class="btn btn-sm btn-primary view-btn" data-id="' + row.id + '">View</button>' +
                                '<button class="btn btn-sm btn-danger delete-btn" data-id="' + row.id + '">Delete</button>' +
                                '</div>';
                        }
                    }
                ],
                columnDefs: [{ targets: 12, orderable: false, searchable: false }],
                order: [[0, 'desc']],
                pageLength: 25,
                lengthMenu: [25, 50, 100],
                scrollX: true
            });

            $('#applyFilters').on('click', function() {
                table.draw();
            });

            $('#resetFilters').on('click', function() {
                $('#filterMunicipality').val('');
                $('#date_start').val('');
                $('#date_end').val('');
                table.draw();
            });

            $('#exportCsv').on('click', function() {
                var query = new URLSearchParams({
                    municipality: $('#filterMunicipality').val() || '',
                    date_start: $('#date_start').val() || '',
                    date_end: $('#date_end').val() || ''
                }).toString();
                window.location.href = '{{ route('unpaid-verifications.export') }}?' + query;
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
                            new bootstrap.Modal('#viewModal').show();
                        } else {
                            alert('Unable to load record.');
                        }
                    });
            });

            $('#unpaidTable').on('click', '.delete-btn', function() {
                var id = $(this).data('id');
                if (!confirm('Are you sure you want to delete this record?')) return;

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
                            alert('Record deleted successfully.');
                            table.ajax.reload(null, false);
                        } else {
                            alert('Failed to delete record.');
                        }
                    })
                    .catch(() => alert('Error deleting record.'));
            });
        });
    </script>
@endpush
