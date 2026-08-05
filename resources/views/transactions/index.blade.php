@extends('layouts.app')

@section('title', 'All Transactions — 2D MIS')

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        table.dataTable td {
            font-size: 0.8rem;
        }

        table.dataTable th {
            font-size: 0.85rem;
        }

        .actions-col {
            width: 120px !important;
            max-width: 120px !important;
            text-align: center;
            white-space: nowrap;
        }

        .actions-col .btn {
            padding: 2px 6px;
            font-size: 11px;
        }

        .form-label-sm {
            font-size: 0.8rem;
            margin-bottom: 0.2rem;
        }
    </style>
@endpush

@section('content')
    <div class="card shadow-lg border-0 p-4">
        <h3 class="mb-3">All Transactions</h3>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <form method="get" action="{{ route('transactions.index') }}" id="filtersForm" class="row g-3 mb-3">
            <div class="col">
                <label class="form-label-sm">Program</label>
                <select name="program" id="filterProgram" class="form-select form-select-sm">
                    <option value="">-- All --</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program }}" @selected(request('program') === $program)>{{ $program }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col">
                <label class="form-label-sm">Status</label>
                <select name="status" id="filterStatus" class="form-select form-select-sm">
                    <option value="">-- All --</option>
                    <option value="PAID" @selected(request('status') === 'PAID')>PAID</option>
                    <option value="PENDING PAYOUT" @selected(request('status') === 'PENDING PAYOUT')>PENDING PAYOUT</option>
                </select>
            </div>

            <div class="col">
                <label class="form-label-sm">Municipality</label>
                <select id="filterMunicipality" name="municipality" class="form-select form-select-sm">
                    <option value="">-- All --</option>
                    @foreach ($municipalities as $municipality)
                        <option value="{{ $municipality->id }}" @selected((string) request('municipality') === (string) $municipality->id)>
                            {{ $municipality->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col">
                <label class="form-label-sm">Barangay</label>
                <select id="filterBarangay" name="barangay" class="form-select form-select-sm">
                    <option value="">-- All --</option>
                </select>
            </div>

            <div class="w-100"></div>

            <div class="col">
                <label class="form-label-sm">Date Applied (Start)</label>
                <input type="date" name="date_applied_start" class="form-control form-control-sm" value="{{ request('date_applied_start') }}">
            </div>

            <div class="col">
                <label class="form-label-sm">Date Applied (End)</label>
                <input type="date" name="date_applied_end" class="form-control form-control-sm" value="{{ request('date_applied_end') }}">
            </div>

            <div class="col">
                <label class="form-label-sm">Date Paid (Start)</label>
                <input type="date" name="date_paid_start" class="form-control form-control-sm" value="{{ request('date_paid_start') }}">
            </div>

            <div class="col">
                <label class="form-label-sm">Date Paid (End)</label>
                <input type="date" name="date_paid_end" class="form-control form-control-sm" value="{{ request('date_paid_end') }}">
            </div>

            <div class="col d-flex gap-2 align-items-end justify-content-end">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="{{ route('transactions.index') }}" class="btn btn-secondary btn-sm">Reset</a>
                <div class="btn-group">
                    <button type="button" class="btn btn-success btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><button type="button" class="dropdown-item export-link" data-mode="csv">Export CSV</button></li>
                        <li><button type="button" class="dropdown-item export-link" data-mode="custom">Export Custom CSV</button></li>
                        <li><button type="button" class="dropdown-item export-link" data-mode="custom2">Export CSV 2</button></li>
                        <li><button type="button" class="dropdown-item export-link" data-mode="gip">Export GIP Report</button></li>
                    </ul>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table id="transactionsTable" class="table table-striped table-bordered table-sm w-100" style="font-size:12px;">
                <thead class="table-dark">
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
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            var table = $('#transactionsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('transactions.data') }}',
                    type: 'POST',
                    data: function(d) {
                        d.program = $('#filterProgram').val();
                        d.status = $('#filterStatus').val();
                        d.municipality = $('#filterMunicipality').val();
                        d.barangay = $('#filterBarangay').val();
                        d.date_applied_start = $('input[name="date_applied_start"]').val();
                        d.date_applied_end = $('input[name="date_applied_end"]').val();
                        d.date_paid_start = $('input[name="date_paid_start"]').val();
                        d.date_paid_end = $('input[name="date_paid_end"]').val();
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
                    { data: "suggested_amount" },
                    { data: "status" },
                    { data: "amount_paid" },
                    { data: "payout_date" },
                    { data: "date_paid" },
                    { data: "gwa" },
                    { data: "units" },
                    { data: "created_at" },
                    { data: "actions" }
                ],
                columnDefs: [{ targets: 20, orderable: false, searchable: false }],
                order: [[4, 'asc']],
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100]
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

            $('#filtersForm').on('submit', function(e) {
                e.preventDefault();
                table.draw();
            });

            $('#filterMunicipality').on('change', function() {
                var municipalityId = $(this).val();
                var barangaySelect = $('#filterBarangay');
                barangaySelect.html('<option value="">-- All --</option>');
                if (municipalityId) {
                    fetch('{{ route('geography.barangays') }}?municipality_id=' + municipalityId)
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

            @if (request('municipality'))
                $('#filterMunicipality').trigger('change');
            @endif

            $('.export-link').on('click', function() {
                var mode = $(this).data('mode');
                var query = new URLSearchParams({
                    export_mode: mode,
                    program: $('#filterProgram').val(),
                    status: $('#filterStatus').val(),
                    municipality: $('#filterMunicipality').val(),
                    barangay: $('#filterBarangay').val(),
                    date_applied_start: $('input[name="date_applied_start"]').val(),
                    date_applied_end: $('input[name="date_applied_end"]').val(),
                    date_paid_start: $('input[name="date_paid_start"]').val(),
                    date_paid_end: $('input[name="date_paid_end"]').val()
                }).toString();
                window.location.href = '{{ route('transactions.export') }}?' + query;
            });

            $('#transactionsTable').on('click', '.delete-transaction', function() {
                if (!confirm('Are you sure you want to delete this transaction?')) return;
                var id = $(this).data('id');
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
    </script>
@endpush
