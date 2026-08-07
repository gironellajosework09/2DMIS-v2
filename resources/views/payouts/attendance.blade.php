@extends('layouts.app')

@section('title', $config['title'].' — 2D MIS')

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        table.dataTable td {
            font-size: 0.85rem;
        }

        table.dataTable th {
            font-size: 0.9rem;
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
            width: 100px !important;
            max-width: 100px !important;
            text-align: center;
            white-space: nowrap;
        }

        .actions-col .btn {
            padding: 2px 6px;
            font-size: 11px;
        }
    </style>
@endpush

@section('content')
    <div class="card shadow-lg border-0 p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">{{ $config['title'] }}</h3>
            <a href="{{ route($config['scanner_route']) }}" class="btn btn-primary btn-sm">{{ $config['scanner_label'] }}</a>
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

            <div style="min-width:180px;">
                <label class="form-label mb-1">Program</label>
                <select id="filterProgram" class="form-select form-select-sm">
                    <option value="">All Programs</option>
                    @foreach ($config['programs'] as $program)
                        <option value="{{ $program }}">{{ $program }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label mb-1">Scanned Date Start</label>
                <input type="date" id="scanned_start" class="form-control form-control-sm">
            </div>

            <div>
                <label class="form-label mb-1">Scanned Date End</label>
                <input type="date" id="scanned_end" class="form-control form-control-sm">
            </div>

            <div class="ms-auto d-flex align-items-end gap-2">
                <button id="applyFilters" class="btn btn-primary btn-sm">Filter</button>
                <button id="resetFilters" class="btn btn-secondary btn-sm">Reset</button>
            </div>
        </div>

        <div class="table-responsive">
            <table id="scannedTable" class="table table-striped table-bordered table-sm w-100">
                <thead class="table-dark">
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
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $config['modal_title'] }}</h5>
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
            var dataUrl = '{{ route('payout-attendance.'.$variant.'.data') }}';
            var showSeats = {{ $config['seat_table'] ? 'true' : 'false' }};

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
                ajax: {
                    url: dataUrl,
                    type: 'POST',
                    data: function(d) {
                        d.municipality = $('#filterMunicipality').val();
                        d.program = $('#filterProgram').val();
                        d.scanned_start = $('#scanned_start').val();
                        d.scanned_end = $('#scanned_end').val();
                    }
                },
                columns: columns,
                columnDefs: [{ targets: columns.length - 1, orderable: false, searchable: false }],
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
                $('#filterProgram').val('');
                $('#scanned_start').val('');
                $('#scanned_end').val('');
                table.draw();
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
                            var modal = new bootstrap.Modal(document.getElementById('viewModal'));
                            modal.show();
                        } else {
                            alert('Could not load details');
                        }
                    });
            });

            $('#scannedTable').on('click', '.delete-btn', function() {
                var id = $(this).data('id');

                if (!confirm('Are you sure you want to delete this scanned payout?')) return;

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
                            alert('Scanned payout deleted successfully.');
                            table.ajax.reload(null, false);
                        } else {
                            alert(resp.error || 'Failed to delete record.');
                        }
                    })
                    .catch(() => alert('Error deleting record.'));
            });
        });
    </script>
@endpush
