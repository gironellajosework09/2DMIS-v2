@extends('layouts.app')

@section('title', 'Scholarship Reports — 2D MIS')

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
    </style>
@endpush

@section('content')
    <div class="card shadow-lg border-0 p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Scholarship Reports</h3>
        </div>

        <div class="dt-filters d-flex flex-wrap align-items-end gap-3 mb-3">
            <div class="flex-fill" style="min-width:200px;">
                <label class="form-label mb-1">Municipality</label>
                <select id="filterMunicipality" class="form-select form-select-sm">
                    <option value="">All Municipalities</option>
                    @foreach ($municipalities as $municipality)
                        <option value="{{ $municipality->id }}">{{ $municipality->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex-fill" style="min-width:200px;">
                <label class="form-label mb-1">Barangay</label>
                <select id="filterBarangay" class="form-select form-select-sm">
                    <option value="">All Barangays</option>
                </select>
            </div>

            <div class="flex-fill" style="min-width:200px;">
                <label class="form-label mb-1">Program</label>
                <select id="filterProgram" class="form-select form-select-sm">
                    <option value="">All Programs</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program }}">{{ $program }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex-fill" style="min-width:180px;">
                <label class="form-label mb-1">Submitted</label>
                <select id="filterSubmitted" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select>
            </div>

            <div class="flex-fill" style="min-width:200px;">
                <label class="form-label mb-1">Date Applied (From)</label>
                <input type="date" id="filterDateFrom" class="form-control form-control-sm">
            </div>

            <div class="flex-fill" style="min-width:200px;">
                <label class="form-label mb-1">Date Applied (To)</label>
                <input type="date" id="filterDateTo" class="form-control form-control-sm">
            </div>

            <div class="ms-auto d-flex align-items-end gap-2">
                <button id="applyFilters" class="btn btn-primary btn-sm">Filter</button>
                <button id="resetFilters" class="btn btn-secondary btn-sm">Reset</button>
                <button id="exportCsv" class="btn btn-success btn-sm">Export CSV</button>
            </div>
        </div>

        <div class="table-responsive">
            <table id="reportsTable" class="table table-striped table-bordered table-sm w-100">
                <thead class="table-dark">
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
            var table = $('#reportsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('scholarship-reports.data') }}',
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
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
                    { data: 'program' },
                    { data: 'full_name' },
                    { data: 'mobile_no' },
                    { data: 'sex' },
                    { data: 'birthdate' },
                    { data: 'civil_status' },
                    { data: 'municipality' },
                    { data: 'barangay' },
                    { data: 'school' },
                    { data: 'course' },
                    { data: 'year_level' },
                    { data: 'gwa' },
                    { data: 'units' },
                    { data: 'landbank_no' },
                    { data: 'remarks' },
                    { data: 'date_applied' },
                    { data: 'regular' },
                    { data: 'submitted' }
                ],
                order: [
                    [1, 'asc']
                ],
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                scrollX: true
            });

            $('#applyFilters').on('click', function() {
                table.draw();
            });

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
                                barangaySelect.append('<option value="' + b.id + '">' + safeName +
                                    '</option>');
                            });
                        });
                }
            });

            $('#resetFilters').on('click', function() {
                $('#filterMunicipality').val('');
                $('#filterBarangay').html('<option value="">All Barangays</option>').val('');
                $('#filterProgram').val('');
                $('#filterSubmitted').val('');
                $('#filterDateFrom').val('');
                $('#filterDateTo').val('');
                table.draw();
            });

            $('#exportCsv').on('click', function() {
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
        });
    </script>
@endpush
