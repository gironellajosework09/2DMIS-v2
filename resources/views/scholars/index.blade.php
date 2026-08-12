@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <h1>Scholars</h1>
        <a href="{{ route('scholars.create') }}" class="btn btn-primary mb-3">Add Scholar</a>

        <table id="scholarsTable" class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Client ID</th>
                    <th>Full Name</th>
                    <th>Program</th>
                    <th>Barangay</th>
                    <th>Town</th>
                </tr>
            </thead>
        </table>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $('#scholarsTable').DataTable({
                processing: true,
                serverSide: true,
                pageLength: 25,
                order: [
                    [1, 'asc']
                ],
                ajax: {
                    url: '{{ route('scholars.data') }}',
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                },
                columns: [
                    { data: 'id', name: 'id' },
                    {
                        data: 'client_id',
                        name: 'client_id',
                        render: function(data, type, row) {
                            return '<span class="client-id" data-id="' + row.id + '">' + data + '</span> ' +
                                '<button class="btn btn-sm btn-primary edit-client-id" data-id="' + row.id +
                                '" data-clientid="' + data + '">Edit</button>';
                        }
                    },
                    { data: 'full_name', name: 'full_name' },
                    { data: 'program', name: 'program' },
                    { data: 'barangay', name: 'barangay' },
                    { data: 'town', name: 'town' }
                ]
            });

            $(document).on('click', '.edit-client-id', function() {
                var id = $(this).data('id');
                var currentVal = $(this).data('clientid');
                var newVal = prompt('Enter new Client ID:', currentVal);

                if (newVal !== null && newVal !== '' && newVal !== currentVal) {
                    $.ajax({
                        url: '{{ route('scholars.update-client-id') }}',
                        type: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        data: { id: id, client_id: newVal },
                        success: function() {
                            $('#scholarsTable').DataTable().ajax.reload(null, false);
                        },
                        error: function() {
                            alert('Error updating Client ID');
                        }
                    });
                }
            });
            });
        });
    </script>
@endpush
