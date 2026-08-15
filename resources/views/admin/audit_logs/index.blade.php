@extends('layouts.app')

@section('title', 'Activity Logs — 2D MIS')

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        #logsTable {
            font-size: 0.75rem;
        }

        #logsTable th,
        #logsTable td {
            padding: 0.3rem 0.5rem;
            line-height: 1.2;
        }

        .leaderboard-table {
            font-size: 0.9rem;
        }
    </style>
@endpush

@section('content')
    <div class="card shadow-lg border-0 p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Activity Logs</h3>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#leaderboardModal">Leaderboard</button>
        </div>

        <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="mb-3">
            <label for="table" class="form-label">Select Table:</label>
            <select name="table" id="table" class="form-select w-auto d-inline-block" onchange="this.form.submit()">
                @foreach ($tables as $value => $label)
                    <option value="{{ $value }}" @selected($targetTable === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </form>

        <div class="row mb-3">
            <div class="col-md-3">
                <label>User:</label>
                <select id="userFilter" class="form-select">
                    <option value="">All</option>
                </select>
            </div>
            <div class="col-md-3">
                <label>Action:</label>
                <select id="actionFilter" class="form-select">
                    <option value="">All</option>
                </select>
            </div>
            <div class="col-md-3">
                <label>From Date:</label>
                <input type="date" id="minDate" class="form-control">
            </div>
            <div class="col-md-3">
                <label>To Date:</label>
                <input type="date" id="maxDate" class="form-control">
            </div>
        </div>

        <div class="table-responsive">
            <table id="logsTable" class="table table-bordered align-middle table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>User</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="leaderboardModal" tabindex="-1" aria-labelledby="leaderboardLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="leaderboardLabel">User Activity Leaderboard</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-striped leaderboard-table" id="leaderboardTable">
                        <thead class="table-dark">
                            <tr>
                                <th>Rank</th>
                                <th>User</th>
                                <th>Total Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
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
        $(document).ready(function () {
            const csrfToken = '{{ csrf_token() }}';
            const dataUrl = '{{ route('admin.audit-logs.data') }}';
            const leaderboardUrl = '{{ route('admin.audit-logs.leaderboard') }}';

            const table = $('#logsTable').DataTable({
                ajax: {
                    url: dataUrl,
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    data: function (d) {
                        d.table = $('#table').val();
                    },
                    dataSrc: function (json) {
                        const userFilter = $('#userFilter');
                        const actionFilter = $('#actionFilter');

                        const currentUser = userFilter.val();
                        const currentAction = actionFilter.val();

                        userFilter.find('option:not(:first)').remove();
                        actionFilter.find('option:not(:first)').remove();

                        $.each(json.users, function (i, user) {
                            userFilter.append(new Option(user, user));
                        });
                        $.each(json.actions, function (i, action) {
                            actionFilter.append(new Option(action, action));
                        });

                        if (currentUser && userFilter.find('option[value="' + currentUser + '"]').length) {
                            userFilter.val(currentUser);
                        }
                        if (currentAction && actionFilter.find('option[value="' + currentAction + '"]').length) {
                            actionFilter.val(currentAction);
                        }

                        return json.data;
                    }
                },
                columns: [
                    { data: 'username' },
                    { data: 'action' },
                    { data: 'target' },
                    {
                        data: 'date',
                        render: function (data, type, row) {
                            if (type === 'sort' || type === 'type') {
                                return row.date_raw;
                            }
                            return data;
                        }
                    }
                ],
                order: [[3, 'desc']]
            });

            setInterval(function () {
                table.ajax.reload(null, false);
            }, 5000);

            $('#userFilter').on('change', function () {
                table.column(0).search(this.value).draw();
            });

            $('#actionFilter').on('change', function () {
                table.column(1).search(this.value).draw();
            });

            $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                const min = $('#minDate').val();
                const max = $('#maxDate').val();

                if (!min && !max) {
                    return true;
                }

                const dateRaw = table.row(dataIndex).data().date_raw;
                if (!dateRaw) {
                    return false;
                }

                const dateVal = new Date(dateRaw);

                if ((min && dateVal < new Date(min)) || (max && dateVal > new Date(max))) {
                    return false;
                }

                return true;
            });

            $('#minDate, #maxDate').on('change', function () {
                table.draw();
            });

            $('#leaderboardModal').on('show.bs.modal', function () {
                $.ajax({
                    url: leaderboardUrl,
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    data: { table: $('#table').val() }
                }).done(function (rows) {
                    const tbody = $('#leaderboardTable tbody');
                    tbody.empty();

                    $.each(rows, function (index, row) {
                        tbody.append(
                            '<tr>' +
                            '<td>' + (index + 1) + '</td>' +
                            '<td>' + $('<div>').text(row.username).html() + '</td>' +
                            '<td>' + row.total_actions + '</td>' +
                            '</tr>'
                        );
                    });
                });
            });
        });
    </script>
@endpush
