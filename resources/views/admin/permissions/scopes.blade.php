@extends('layouts.app')

@section('title', 'Manage Municipality Scope — 2D MIS')

@section('content')
    <div class="card shadow-lg border-0 p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Manage Municipality Scope</h3>
        </div>

        <p class="text-muted">
            Municipalities this user may access on municipality-scoped pages (P12). "All Municipalities"
            writes the reserved 0 marker and grants every municipality; a user with no rows matches no
            records (fail closed).
        </p>

        <form method="GET" action="{{ route('admin.permissions.scopes') }}" class="mb-3">
            <label class="form-label">Select User</label>
            <select name="user_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- Select User --</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected($selectedUser?->id === $user->id)>{{ $user->username }}</option>
                @endforeach
            </select>
        </form>

        @if ($selectedUser)
            <form method="POST" action="{{ route('admin.permissions.update-scopes', $selectedUser->id) }}">
                @csrf

                <div class="form-check border rounded p-3 mb-3 bg-warning-subtle">
                    <input class="form-check-input" type="checkbox" name="all" value="1" id="all"
                           @checked($hasAll) onchange="confirmAll(this)">
                    <label class="form-check-label fw-semibold" for="all">
                        All Municipalities
                    </label>
                    <div class="text-muted small">
                        Writes the reserved 0 marker in tbl_user_municipalities: the user's effective scope
                        is every municipality. Leave unchecked and clear the list to restrict to no records.
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Municipality</th>
                                <th class="text-center" style="width:130px;">
                                    <input type="checkbox" id="checkAllScope"> Check All
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($municipalities as $municipality)
                                <tr>
                                    <td>{{ $municipality->name }}</td>
                                    <td class="text-center">
                                        <input type="checkbox" name="municipalities[]" value="{{ $municipality->id }}"
                                               @checked(in_array((int) $municipality->id, $userScope, true))>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="btn btn-primary">Save Municipality Scope</button>
            </form>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        const checkAllScope = document.getElementById('checkAllScope');

        checkAllScope.addEventListener('change', function () {
            document.querySelectorAll('input[name="municipalities[]"]').forEach(function (cb) {
                cb.checked = this.checked;
            }, this);
        });

        function confirmAll(cb) {
            const message = cb.checked
                ? 'Grant access to every municipality? Existing municipality checkboxes are ignored when this is set.'
                : 'Revoke all-municipality access? The user will only see municipalities checked below.';
            if (!confirm(message)) {
                cb.checked = !cb.checked;
            }
        }
    </script>
@endpush
