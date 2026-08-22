@extends('layouts.app')

@section('title', 'User Management — 2D MIS')

@section('content')
    <div class="card shadow-lg border-0 p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Super Admin Panel — User Management</h3>
        </div>

        @if (session('login_status'))
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                {{ session('login_status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ $errors->first() }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-secondary">
                    <tr>
                        <th>Username</th>
                        <th>Created</th>
                        <th class="text-center" style="width: 160px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $managedUser)
                        <tr>
                            <td>{{ $managedUser->username }}</td>
                            <td>{{ $managedUser->created_at }}</td>
                            <td class="text-center">
                                @if (in_array($managedUser->id, $protectedUserIds, true))
                                    <button class="btn btn-secondary btn-sm w-100" disabled>Reset Password</button>
                                @else
                                    <button class="btn btn-primary btn-sm w-100" data-bs-toggle="modal"
                                        data-bs-target="#passwordModal"
                                        data-id="{{ $managedUser->id }}"
                                        data-username="{{ $managedUser->username }}">
                                        Reset Password
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="passwordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content" id="passwordForm">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="user_id">
                    <div class="mb-2">
                        <label class="form-label">Username</label>
                        <input type="text" id="modal_username" class="form-control" disabled>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" id="password" class="form-control" required minlength="8">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Save</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('passwordModal').addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            const form = document.getElementById('passwordForm');
            form.action = '{{ url('admin/users') }}/' + btn.dataset.id + '/password';
            document.getElementById('user_id').value = btn.dataset.id;
            document.getElementById('modal_username').value = btn.dataset.username;
        });
    </script>
@endpush
