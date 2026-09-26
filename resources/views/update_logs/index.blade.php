@extends('layouts.app')

@section('title', 'Grantee Update Logs — 2D MIS')

{{-- Batch G migration (UI_UX_ANALYSIS §8.9 Group 4): grantee update logs.
     Server-rendered rows + client-side DataTables, GET date filter and
     the PHT note are unchanged; only presentation moved. --}}
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/datatables.css') }}">
    <style>
        /* ── Update-logs scope: token skin over the DataTables
           Bootstrap integration. Prefixed with #logs-screen —
           nothing here can leak to other screens. ── */
        #logs-screen table.dataTable td {
            font-size: 0.8rem;
            white-space: nowrap;
        }

        #logs-screen table.dataTable th {
            font-size: 0.85rem;
            background-color: var(--color-navy);
            color: #fff;
            border-bottom: 0;
            white-space: nowrap;
            text-align: center;
        }

        #logs-screen table.dataTable tbody tr:nth-child(odd) td {
            background-color: rgb(15 27 45 / 0.02);
        }

        #logs-screen table.dataTable tbody tr:hover td {
            background-color: rgb(37 99 235 / 0.06);
        }

        #logs-screen .dataTables_wrapper .dataTables_length select,
        #logs-screen .dataTables_wrapper .dataTables_filter input {
            border: 1px solid var(--color-line);
            border-radius: var(--radius-control);
            padding: 0.25rem 0.5rem;
            font-size: 0.85rem;
        }

        #logs-screen .dataTables_wrapper .dataTables_paginate .page-item.active .page-link {
            background-color: var(--color-navy);
            border-color: var(--color-navy);
        }

        #logs-screen .page-link {
            color: var(--color-navy);
        }
    </style>
@endpush

@section('content')

    @include('partials.page-header', [
        'title' => 'Grantee Update Logs',
        'subtitle' => 'Times shown in Philippine Time (PHT).',
    ])

    <div id="logs-screen">
        <section class="data-card" aria-label="Update logs">
            <div class="data-card-body grid grid-cols-1 items-end gap-[12px] sm:grid-cols-2 lg:grid-cols-[minmax(0,auto)_minmax(0,auto)_auto]">
                <div class="min-w-0">
                    <label class="field-label" for="start_date">From</label>
                    <input type="date" name="start_date" id="start_date" value="{{ $startDate }}" class="form-control form-control-sm" form="logsFilter">
                </div>
                <div class="min-w-0">
                    <label class="field-label" for="end_date">To</label>
                    <input type="date" name="end_date" id="end_date" value="{{ $endDate }}" class="form-control form-control-sm" form="logsFilter">
                </div>
                <div class="flex items-end gap-2 max-lg:w-full">
                    <button type="submit" form="logsFilter" class="btn-navy w-full lg:w-auto">Filter</button>
                    <a href="{{ route('update-logs.index') }}" class="btn-subtle no-underline w-full text-center lg:w-auto">Reset</a>
                </div>
            </div>

            <form id="logsFilter" method="get" action="{{ route('update-logs.index') }}" class="hidden"></form>

            <div class="overflow-x-auto px-[1.25rem] pb-[1.25rem]" tabindex="0" aria-label="Update logs table, scrolls horizontally on narrow screens">
                <table id="logsTable" class="table table-sm align-middle mb-0" style="width:100%;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client ID</th>
                            <th>Full Name</th>
                            <th>Town</th>
                            <th>IP Address</th>
                            <th>Action</th>
                            <th>Date/Time (PHT)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td class="text-center">{{ $log['id'] }}</td>
                                <td class="text-center">{{ $log['client_id'] }}</td>
                                <td>{{ $log['full_name'] }}</td>
                                <td class="text-center">{{ $log['town'] }}</td>
                                <td class="text-center">{{ $log['ip_address'] }}</td>
                                <td>{{ $log['action'] }}</td>
                                <td class="text-center">{{ $log['date_time'] }}</td>
                            </tr>
                        @endforeach
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
    <script>
        $(document).ready(function() {
            $('#logsTable').DataTable({
                pageLength: 25,
                autoWidth: false,
                order: [
                    [0, 'desc']
                ],
                language: {
                    search: 'Search Logs:',
                    lengthMenu: 'Show _MENU_ entries per page',
                    info: 'Showing _START_ to _END_ of _TOTAL_ logs',
                    paginate: { previous: 'Prev', next: 'Next' }
                }
            });
        });
    </script>
@endpush
