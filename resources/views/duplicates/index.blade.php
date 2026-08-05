@extends('layouts.app')

@section('title', 'Duplicate Clients — 2D MIS')

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        table.dataTable td,
        table.dataTable th {
            font-size: 0.85rem;
        }
    </style>
@endpush

@section('content')
    <div class="card shadow-lg border-0 p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Duplicate Clients (Select to Delete)</h3>
            <a href="{{ route('clients.index') }}" class="btn btn-secondary btn-sm">⬅ Back to Clients</a>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="dt-filters d-flex flex-wrap align-items-end gap-3 mb-3">
            <div class="flex-fill" style="min-width:200px;">
                <label class="form-label mb-1">Municipality</label>
                <select id="filterMunicipality" class="form-select form-select-sm">
                    <option value="">All Municipalities</option>
                    @foreach ($municipalities as $muni)
                        <option value="{{ $muni->id }}" @selected((string) $muni->id === $municipality)>
                            {{ $muni->name }}
                        </option>
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
                <button id="applyFilters" class="btn btn-primary btn-sm">Filter</button>
                <button id="resetFilters" class="btn btn-secondary btn-sm">Reset</button>
            </div>
        </div>

        <form method="POST" action="{{ route('duplicates.destroy') }}" id="deleteDuplicatesForm" onsubmit="return confirmDelete();">
            @csrf
            <input type="hidden" name="municipality" id="formMunicipality" value="{{ $municipality }}">
            <input type="hidden" name="barangay" id="formBarangay" value="{{ $barangay }}">

            <div class="alert alert-warning d-flex justify-content-between align-items-center">
                <div>
                    Tick the checkboxes for records you want to delete.
                    <span class="badge bg-info" id="selectedCount">0 selected</span>
                </div>
                <button type="submit" class="btn btn-danger">🗑 Delete Selected</button>
            </div>

            <div class="table-responsive">
                <table id="dupTable" class="table table-sm table-striped table-bordered align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th><input type="checkbox" id="checkAll"></th>
                            <th>ID</th>
                            <th>Lastname</th>
                            <th>Firstname</th>
                            <th>Middlename</th>
                            <th>Municipality</th>
                            <th>Barangay</th>
                            <th>Precinct</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </form>
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

            var table = $('#dupTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('duplicates.data') }}',
                    type: 'POST',
                    data: function(d) {
                        d.municipality = $('#filterMunicipality').val();
                        d.barangay = $('#filterBarangay').val();
                    }
                },
                columns: [
                    { data: 0, orderable: false },
                    { data: 1 },
                    { data: 2 },
                    { data: 3 },
                    { data: 4 },
                    { data: 5 },
                    { data: 6 },
                    { data: 7 }
                ],
                order: [
                    [2, 'asc']
                ],
                pageLength: 25,
                lengthMenu: [25, 50, 100, 200, 500]
            });

            function updateCount() {
                var count = $("input[name='delete_ids[]']:checked").length;
                $("#selectedCount").text(count + " selected");
            }

            $("#checkAll").on("change", function() {
                $("input[name='delete_ids[]']").prop("checked", this.checked);
                updateCount();
            });

            $(document).on("change", "input[name='delete_ids[]']", updateCount);

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

            $('#applyFilters').on('click', function() {
                $('#formMunicipality').val($('#filterMunicipality').val());
                $('#formBarangay').val($('#filterBarangay').val());
                table.draw();
            });

            $('#resetFilters').on('click', function() {
                $('#filterMunicipality').val('');
                $('#filterBarangay').html('<option value="">All Barangays</option>').val('');
                $('#formMunicipality').val('');
                $('#formBarangay').val('');
                table.draw();
            });
        });

        function confirmDelete() {
            var selected = document.querySelectorAll("input[name='delete_ids[]']:checked");
            if (selected.length === 0) {
                alert("⚠ Please select at least one record to delete.");
                return false;
            }
            return confirm("⚠ Are you sure you want to delete the selected record(s)? This action cannot be undone.");
        }
    </script>
@endpush
