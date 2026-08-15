@extends('layouts.app')

@section('title', 'Manage Program Access — 2D MIS')

@section('content')
    <div class="card shadow-lg border-0 p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Manage Program Access</h3>
        </div>

        <form method="GET" action="{{ route('admin.program-permissions.pages') }}" class="mb-3">
            <label class="form-label">Select User</label>
            <select name="user_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- Select User --</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected($selectedUser?->id === $user->id)>{{ $user->username }}</option>
                @endforeach
            </select>
        </form>

        @if ($selectedUser)
            <form method="POST" action="{{ route('admin.program-permissions.update', $selectedUser->id) }}">
                @csrf

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Program</th>
                                <th class="text-center" style="width:130px;">
                                    <input type="checkbox" id="checkAllPrograms"> Check All
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($programs as $program)
                                <tr>
                                    <td>{{ $program }}</td>
                                    <td class="text-center">
                                        <input type="checkbox" name="programs[]" value="{{ $program }}"
                                               @checked(in_array($program, $userPrograms, true))>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="btn btn-primary">Save Program Access</button>
            </form>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        const checkAllPrograms = document.getElementById('checkAllPrograms');

        checkAllPrograms.addEventListener('change', function () {
            document.querySelectorAll('input[name="programs[]"]').forEach(function (cb) {
                cb.checked = this.checked;
            }, this);
        });
    </script>
@endpush
