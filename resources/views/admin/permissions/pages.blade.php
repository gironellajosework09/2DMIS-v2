@extends('layouts.app')

@section('title', 'Manage Page Access — 2D MIS')

@section('content')
    <div class="card shadow-lg border-0 p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Manage Page Access</h3>
        </div>

        <form method="GET" action="{{ route('admin.permissions.pages') }}" class="mb-3">
            <label class="form-label">Select User</label>
            <select name="user_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- Select User --</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected($selectedUser?->id === $user->id)>{{ $user->username }}</option>
                @endforeach
            </select>
        </form>

        @if ($selectedUser)
            <form method="POST" action="{{ route('admin.permissions.update-pages', $selectedUser->id) }}">
                @csrf

                <div class="form-check border rounded p-3 mb-3 bg-warning-subtle">
                    <input class="form-check-input" type="checkbox" name="super_admin" value="1" id="superAdmin"
                           @checked($isSuperAdmin) onchange="confirmSuperAdmin(this)">
                    <label class="form-check-label fw-semibold" for="superAdmin">
                        Super Admin — full access to every page
                    </label>
                    <div class="text-muted small">
                        Grants the '*' permission row: bypasses every page gate and exempts the user from the single-device check.
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Page</th>
                                <th class="text-center" style="width:130px;">
                                    <input type="checkbox" id="checkAllPages"> Check All
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($catalog as $page)
                                <tr>
                                    <td>{{ $labels[$page] ?? $page }}</td>
                                    <td class="text-center">
                                        <input type="checkbox" name="pages[]" value="{{ $page }}"
                                               @checked(in_array($page, $userPages, true))>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="btn btn-primary">Save Permissions</button>
            </form>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        const checkAllPages = document.getElementById('checkAllPages');

        checkAllPages.addEventListener('change', function () {
            document.querySelectorAll('input[name="pages[]"]').forEach(function (cb) {
                cb.checked = this.checked;
            }, this);
        });

        function confirmSuperAdmin(cb) {
            const message = cb.checked
                ? 'Grant full (super-admin) access to every page? This also exempts the user from the single-device check.'
                : 'Revoke super-admin access? The user will lose every page not otherwise granted.';
            if (!confirm(message)) {
                cb.checked = !cb.checked;
            }
        }
    </script>
@endpush
