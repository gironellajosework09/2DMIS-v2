@extends('layouts.app')

@section('title', 'Scholarship Reports — 2D MIS')

{{-- Batch G migration (UI_UX_ANALYSIS §8.9 Group 4): scholarship reports
     matrix. Feed contract unchanged: POST scholarship-reports.data with
     municipality / barangay / program / submitted / date_from / date_to,
     the geography.barangays cascade and the filtered CSV export redirect.
     Presentation only. --}}
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/datatables.css') }}">
    <style>
        /* ── Scholarship-reports scope: token skin over the DataTables
           Bootstrap integration. Prefixed with #scholarship-reports-screen —
           nothing here can leak to other screens. ── */
        #scholarship-reports-screen table.dataTable td {
            font-size: 0.8rem;
        }

        #scholarship-reports-screen table.dataTable th {
            font-size: 0.85rem;
            background-color: var(--color-navy);
            color: #fff;
            border-bottom: 0;
            white-space: nowrap;
        }

        #scholarship-reports-screen table.dataTable tbody tr:nth-child(odd) td {
            background-color: rgb(15 27 45 / 0.02);
        }

        #scholarship-reports-screen table.dataTable tbody tr:hover td {
            background-color: rgb(37 99 235 / 0.06);
        }

        #scholarship-reports-screen .dataTables_wrapper .dataTables_length select,
        #scholarship-reports-screen .dataTables_wrapper .dataTables_filter input {
            border: 1px solid var(--color-line);
            border-radius: var(--radius-control);
            padding: 0.25rem 0.5rem;
            font-size: 0.85rem;
        }

        #scholarship-reports-screen .dataTables_wrapper .dataTables_paginate .page-item.active .page-link {
            background-color: var(--color-navy);
            border-color: var(--color-navy);
        }

        #scholarship-reports-screen .page-link {
            color: var(--color-navy);
        }
    </style>
@endpush

@section('content')

    @include('partials.page-header', [
        'title' => 'Scholarship Reports',
        'subtitle' => 'Program-wide scholarship data with filters and CSV export.',
    ])

    <div id="scholarship-reports-screen">
        <section class="data-card" aria-label="Scholarship report records">
            <div class="data-card-body flex flex-col gap-[14px]">
                @include('partials.filter-chips', ['filterChips' => $filterChips])

                <div class="flex flex-wrap items-end gap-2">
                    <button id="resetFilters" class="btn-subtle w-full lg:w-auto">Reset</button>
                    <button id="exportCsv" class="btn-gold w-full lg:w-auto">Export CSV</button>
                </div>
            </div>

            <div class="overflow-x-auto px-[1.25rem] pb-[1.25rem]" tabindex="0" aria-label="Scholarship reports table, scrolls horizontally on narrow screens">
                <table id="reportsTable" class="table table-sm" style="width:100%;">
                    <thead>
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
                    <tbody>
                        {{-- Loaded via AJAX (server-side DataTables) --}}
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="{{ asset('js/components/FilterChips.js') }}"></script>
    <script>
        $(document).ready(function() {
            var table = $('#reportsTable').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                ajax: {
                    url: '{{ route('scholarship-reports.data') }}',
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    data: function(d) {
                        var p = (window.reportFilters && window.reportFilters.getParams)
                            ? window.reportFilters.getParams()
                            : {};
                        d.municipality = p.municipality || '';
                        d.barangay = p.barangay || '';
                        d.program = p.program || '';
                        d.submitted = p.submitted || '';
                        d.date_from = p.date_from || '';
                        d.date_to = p.date_to || '';
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

            // FilterChips — shared Phase 2C component (server-side DataTables feed).
            if (window.FilterChips) {
                window.reportFilters = FilterChips.init({
                    id: 'scholarship-filters',
                    host: document.querySelector('[data-filter-host="scholarship-filters"]'),
                    onApply: function() { table.draw(); }
                });
            }

            $('#resetFilters').on('click', function() {
                if (window.reportFilters && window.reportFilters.clearAll) {
                    window.reportFilters.clearAll();
                }
                table.draw();
            });

            $('#exportCsv').on('click', function() {
                var p = (window.reportFilters && window.reportFilters.getParams)
                    ? window.reportFilters.getParams()
                    : {};
                var query = new URLSearchParams({
                    municipality: p.municipality || '',
                    barangay: p.barangay || '',
                    program: p.program || '',
                    submitted: p.submitted || '',
                    date_from: p.date_from || '',
                    date_to: p.date_to || ''
                }).toString();
                window.location.href = '{{ route('scholarship-reports.export') }}?' + query;
            });
        });
    </script>
@endpush
