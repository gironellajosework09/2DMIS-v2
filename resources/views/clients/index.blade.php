@extends('layouts.app')

@section('title', 'Clients — 2D MIS')

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
            width: 170px !important;
            max-width: 170px !important;
            text-align: center;
            white-space: nowrap;
        }

        .actions-col .btn {
            padding: 2px 6px;
            font-size: 11px;
        }

        .actions-col form {
            display: inline;
        }

        #clientsTable tbody tr {
            cursor: pointer;
        }

        #clientDetailsPanel {
            width: min(680px, 94vw);
        }

        #clientDetailsPanel .offcanvas-body {
            overflow-y: auto;
        }
    </style>
@endpush

@section('content')
    <div class="card shadow-lg border-0 p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Clients</h3>
            <div class="d-flex gap-2">
                <a href="{{ route('duplicates.index') }}" class="btn btn-danger btn-sm">Remove Duplicates</a>
                <a href="{{ route('clients.create') }}" class="btn btn-success btn-sm">+ Add Client</a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="dt-filters d-flex flex-wrap align-items-end gap-3">
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
            <div class="ms-auto d-flex align-items-end gap-2">
                <button id="applyFilters" class="btn btn-primary btn-sm w-100 w-md-auto">Filter</button>
                <button id="resetFilters" class="btn btn-secondary btn-sm w-100 w-md-auto">Reset</button>
            </div>
        </div>

        <div class="table-responsive">
            <table id="clientsTable" class="table table-striped table-bordered table-sm w-100" style="font-size:12px;">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Lastname</th>
                        <th>Firstname</th>
                        <th>Middlename</th>
                        <th>Extension</th>
                        <th>Precinct No</th>
                        <th>Region</th>
                        <th>Province</th>
                        <th>Municipality</th>
                        <th>Barangay</th>
                        <th>House No</th>
                        <th>Mobile</th>
                        <th>Birthdate</th>
                        <th>Age</th>
                        <th>Sex</th>
                        <th>Civil Status</th>
                        <th>Occupation</th>
                        <th>Income</th>
                        <th>Voter ID</th>
                        <th class="actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Loaded via AJAX (server-side DataTables) --}}
                </tbody>
            </table>
        </div>
    </div>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="clientDetailsPanel" aria-labelledby="clientDetailsTitle">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title" id="clientDetailsTitle">Client Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body" id="clientDetailsBody">
            <div class="p-5 text-center text-muted">
                <p class="mb-0">Click a client row to view its details.</p>
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
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });

            var table = $('#clientsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('clients.data') }}',
                    type: 'POST',
                    data: function(d) {
                        d.municipality = $('#filterMunicipality').val();
                        d.barangay = $('#filterBarangay').val();
                    }
                },
                columns: [
                    { data: "id" },
                    { data: "fullname" },
                    { data: "lastname" },
                    { data: "firstname" },
                    { data: "middlename" },
                    { data: "extension" },
                    { data: "precinct" },
                    { data: "region" },
                    { data: "province" },
                    { data: "municipality" },
                    { data: "barangay" },
                    { data: "house_no" },
                    { data: "mobile" },
                    { data: "birthdate" },
                    { data: "age" },
                    { data: "sex" },
                    { data: "civil_status" },
                    { data: "occupation" },
                    { data: "income" },
                    { data: "voter_id" },
                    { data: "actions" }
                ],
                columnDefs: [{
                    targets: [2, 3, 4, 5, 7, 8, 11, 12, 13, 14, 15, 16, 17, 18, 19],
                    visible: false,
                    searchable: true
                }],
                order: [
                    [0, 'asc']
                ],
                pageLength: 25,
                lengthMenu: [25, 50, 100],
                createdRow: function(row, data) {
                    $(row).attr('data-id', data.id);
                }
            });

            function executeScripts(container) {
                container.querySelectorAll('script').forEach(function(oldScript) {
                    var fresh = document.createElement('script');
                    fresh.textContent = oldScript.textContent;
                    oldScript.parentNode.replaceChild(fresh, oldScript);
                });
            }

            window.openClientPanel = function(id) {
                var body = document.getElementById('clientDetailsBody');
                var offcanvas = document.getElementById('clientDetailsPanel');
                var url = '{{ route('clients.show', '__ID__') }}'.replace('__ID__', id) + '?panel=1';
                var instance = bootstrap.Offcanvas.getOrCreateInstance(offcanvas);

                document.getElementById('clientDetailsTitle').textContent = 'Client #' + id;
                body.innerHTML = '<div class="p-5 text-center text-muted">' +
                    '<div class="spinner-border text-secondary" role="status"></div>' +
                    '<p class="mt-3 mb-0">Loading client details…</p></div>';
                instance.show();

                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function(response) {
                        if (!response.ok) {
                            throw new Error('Failed to load client details');
                        }
                        return response.text();
                    })
                    .then(function(html) {
                        body.innerHTML = html;
                        executeScripts(body);
                    })
                    .catch(function() {
                        body.innerHTML = '<div class="p-5 text-center text-danger">' +
                            'Failed to load client details.</div>';
                    });
            };

            $('#clientsTable tbody').on('click', 'tr', function(e) {
                if ($(e.target).closest('.actions-col').length) {
                    return;
                }
                var id = $(this).data('id');
                if (id) {
                    openClientPanel(id);
                }
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
                                barangaySelect.append('<option value="' + b.id + '">' + safeName + '</option>');
                            });
                        })
                        .catch(err => console.error('Failed to load barangays', err));
                }
            });

            $('#resetFilters').on('click', function() {
                $('#filterMunicipality').val('');
                $('#filterBarangay').html('<option value="">All Barangays</option>').val('');
                table.draw();
            });
        });
    </script>
@endpush
