@extends('layouts.app')

@section('title', 'Manage Multiple Device Exemptions — 2D MIS')

@section('content')
    <div class="card shadow-lg border-0 p-4" style="max-width: 640px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Manage Multiple Device Exemptions</h3>
        </div>

        <form method="GET" action="{{ route('admin.exemptions.pages') }}" class="mb-4">
            <label class="form-label">Select User</label>
            <select name="user_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- Select User --</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected($selectedUser?->id === $user->id)>{{ $user->username }}</option>
                @endforeach
            </select>
        </form>

        @if ($selectedUser)
            <form method="POST" action="{{ route('admin.exemptions.toggle', $selectedUser->id) }}">
                @csrf

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="grant" value="1" id="grant"
                           @checked($isExempt)>
                    <label class="form-check-label" for="grant">
                        Allow this user to login on multiple devices
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        @endif
    </div>
@endsection
