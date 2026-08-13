@extends('layouts.app')

@section('title', 'Grantee Update Logs — 2D MIS')

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
    </style>
@endpush

@section('content')
    <div class="card shadow-lg border-0 p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Grantee Update Logs</h3>
            <small>Times shown in Philippine Time (PHT)</small>
        </div>

        <form method="get" action="{{ route('update-logs.index') }}" class="row g-2 mb-3">
            <div class="col-md-3">
                <label class="form-label mb-0"><small>From:</small></label>
                <input type="date" name="start_date" value="{{ $startDate }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label mb-0"><small>To:</small></label>
                <input type="date" name="end_date" value="{{ $endDate }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-sm btn-primary me-2">Filter</button>
                <a href="{{ route('update-logs.index') }}" class="btn btn-sm btn-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table id="logsTable" class="table table-striped table-bordered table-sm align-middle mb-0 text-nowrap w-100">
                <thead class="table-dark text-center">
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
