@extends('layouts.app')

@section('title', 'Currently Logged Users — 2D MIS')

@section('content')
    <div class="card shadow-lg border-0 p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Currently Logged Users</h3>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Username</th>
                        <th>Last Activity</th>
                        <th class="text-center">Force Logout</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (\App\Models\User::query()->orderBy('username')->get() as $loggedInUser)
                        <tr>
                            <td>{{ $loggedInUser->username }}</td>
                            <td>{{ $loggedInUser->last_activity ? $loggedInUser->last_activity : '—' }}</td>
                            <td class="text-center">
                                @if ($loggedInUser->id !== auth()->id())
                                    <form method="POST" action="{{ route('session.force-logout') }}" onsubmit="return confirm('Force logout {{ $loggedInUser->username }}?')">
                                        @csrf
                                        <input type="hidden" name="user_id" value="{{ $loggedInUser->id }}">
                                        <button type="submit" class="btn btn-sm btn-danger">Force Logout</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
