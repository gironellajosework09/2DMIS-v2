@extends('layouts.app')

@section('title', 'Duplicate Clients — 2D MIS')

{{-- Batch G migration (UI_UX_ANALYSIS §8.9 Group 3 remainder): duplicate
     client cleanup. The server-side DataTables contract (POST duplicates.data
     with municipality/barangay payloads), geography.barangays cascade,
     hidden-field sync on filter apply/reset, checkbox selection counting and
     the delete form are byte-preserved; only the native confirm() moves to
     uiConfirm (zero-selection alert kept as-is). --}}
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/datatables.css') }}">
    <style>
        /* ── Duplicates scope. Prefixed with #duplicates-screen — nothing here
           can leak to other screens. ── */
        #duplicates-screen .dataTables_filter input {
            border: 1px solid var(--color-line);
            border-radius: var(--radius-control);
            padding: 4px 10px;
            font-size: 0.875rem;
            outline-color: rgb(0 56 168 / 0.35);
        }

        #duplicates-screen .dataTables_length select {
            border: 1px solid var(--color-line);
            border-radius: var(--radius-control);
            font-size: 0.875rem;
        }

        #duplicates-screen table.dataTable th,
        #duplicates-screen table.dataTable td {
            font-size: 0.85rem;
        }

        #duplicates-screen #dupTable thead th {
            background-color: var(--color-navy);
            color: #fff;
            border-bottom: 0;
            white-space: nowrap;
        }

        #duplicates-screen #dupTable tbody tr:nth-child(odd) td {
            background-color: rgb(15 27 45 / 0.02);
        }

        #duplicates-screen #dupTable tbody tr:hover td {
            background-color: rgb(37 99 235 / 0.06);
        }

        #duplicates-screen #dupTable th,
        #duplicates-screen #dupTable td {
            border: 1px solid var(--color-line-light);
        }
    </style>
@endpush

@section('content')
    @include('partials.breadcrumbs', [
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Clients', 'url' => route('clients.index')],
            ['label' => 'Duplicate Clients'],
        ],
    ])

    @include('partials.page-header', [
        'title' => 'Duplicate Clients',
        'subtitle' => 'Select to Delete — tick duplicate records and remove them.',
        'actions' => '
            <a href="'.route('clients.index').'" class="btn-subtle no-underline">⬅ Back to Clients</a>',
    ])

    <div id="duplicates-screen" class="flex flex-col gap-[16px]">
        @if (session('success'))
            <div class="flex items-start justify-between gap-2 rounded-[var(--radius-panel)] bg-surface p-[14px_18px] text-dense text-ink shadow-pop" x-data="{ open: true }" x-show="open">
                <span>{{ session('success') }}</span>
                <button type="button" class="btn-close" @click="open = false" aria-label="Close"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="flex items-start justify-between gap-2 rounded-[var(--radius-control)] border-l-[3px] border-[var(--color-red)] bg-[rgb(206_17_38_/_0.06)] p-[13px_16px] text-dense text-ink" role="alert" x-data="{ open: true }" x-show="open">
                <span>
                    @foreach ($errors->all() as $error)
                        {{ $error }}
                    @endforeach
                </span>
                <button type="button" class="btn-close" @click="open = false" aria-label="Close"></button>
            </div>
        @endif

        <section class="data-card" aria-label="Duplicate client records">
            <div class="data-card-body flex flex-col gap-[16px]">

                <div class="grid grid-cols-1 items-end gap-[12px] sm:grid-cols-2 lg:grid-cols-[minmax(200px,1fr)_minmax(200px,1fr)_auto_auto]">
                    <div>
                        <label for="filterMunicipality" class="field-label">Municipality</label>
                        <select id="filterMunicipality" class="form-select form-select-sm w-full">
                            <option value="">All Municipalities</option>
                            @foreach ($municipalities as $muni)
                                <option value="{{ $muni->id }}" @selected((string) $muni->id === $municipality)>
                                    {{ $muni->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="filterBarangay" class="field-label">Barangay</label>
                        <select id="filterBarangay" class="form-select form-select-sm w-full">
                            <option value="">All Barangays</option>
                        </select>
                    </div>
                    <button id="applyFilters" class="btn-navy h-[calc(1.8125rem+2px)]">Filter</button>
                    <button id="resetFilters" class="btn-subtle h-[calc(1.8125rem+2px)]">Reset</button>
                </div>

                <form method="POST" action="{{ route('duplicates.destroy') }}" id="deleteDuplicatesForm">
                    @csrf
                    <input type="hidden" name="municipality" id="formMunicipality" value="{{ $municipality }}">
                    <input type="hidden" name="barangay" id="formBarangay" value="{{ $barangay }}">

                    <div class="mb-[16px] flex flex-wrap items-center justify-between gap-2 rounded-[var(--radius-control)] border-l-[3px] border-[var(--color-amber)] bg-[rgb(212_169_0_/_0.08)] p-[12px_16px]">
                        <div class="text-dense text-ink">
                            Tick the checkboxes for records you want to delete.
                            <span class="status-badge is-info ms-1" id="selectedCount">0 selected</span>
                        </div>
                        <button type="submit" class="btn-outline-red">🗑 Delete Selected</button>
                    </div>

                    <div class="overflow-x-auto">
                        <table id="dupTable" class="table align-middle" style="width:100%;">
                            <thead>
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
        </section>
    </div>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    @include('partials.confirm-modal')
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

            $('#deleteDuplicatesForm').on('submit', async function(e) {
                var selected = document.querySelectorAll("input[name='delete_ids[]']:checked");
                if (selected.length === 0) {
                    alert("⚠ Please select at least one record to delete.");
                    e.preventDefault();
                    return;
                }
                var ok = await window.uiConfirm({
                    title: 'Delete selected record(s)',
                    message: '⚠ Are you sure you want to delete the selected record(s)? This action cannot be undone.',
                    confirmLabel: 'Delete',
                });
                if (!ok) e.preventDefault();
            });
        });
    </script>
@endpush
