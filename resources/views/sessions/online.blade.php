@extends('layouts.app')

@section('title', 'Currently Logged Users — 2D MIS')

{{-- Batch G migration (UI_UX_ANALYSIS §8.9 Group 4): online sessions.
     Static server-rendered table; the force-logout POST contract is
     unchanged.
     SANCTIONED delta: the inline onsubmit confirm() becomes the shared
     declarative data-confirm attribute handled by the confirm-modal
     partial (native submit preserved via HTMLFormElement.submit). --}}
@push('styles')
    <style>
        /* ── Online-sessions skin. Prefixed with #sessions-screen —
           nothing here can leak to other screens. ── */
        #sessions-screen table th {
            font-size: 0.85rem;
            background-color: var(--color-navy);
            color: #fff;
            border-bottom: 0;
            white-space: nowrap;
        }

        #sessions-screen table td {
            font-size: 0.875rem;
        }

        #sessions-screen table tbody tr:nth-child(odd) td {
            background-color: rgb(15 27 45 / 0.02);
        }

        #sessions-screen table th,
        #sessions-screen table td {
            border: 1px solid var(--color-line-light);
        }
    </style>
@endpush

@section('content')

    @include('partials.page-header', [
        'title' => 'Currently Logged Users',
        'subtitle' => 'Accounts with an active session right now.',
    ])

    <div id="sessions-screen">
        <section class="data-card" aria-label="Online users">
            <div class="overflow-x-auto px-[1.25rem] pb-[1.25rem] pt-[4px]">
                <table class="table align-middle mb-0">
                    <thead class="text-center">
                        <tr>
                            <th>Username</th>
                            <th>Last Activity</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Force Logout</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($onlineUsers as $loggedInUser)
                            <tr>
                                <td>{{ $loggedInUser->username }}</td>
                                <td>{{ $loggedInUser->last_activity ? $loggedInUser->last_activity : '—' }}</td>
                                <td class="text-center"><span class="status-badge is-success">Online</span></td>
                                <td class="text-center">
                                    @if ($loggedInUser->id !== auth()->id())
                                        <form method="POST" action="{{ route('session.force-logout') }}" data-confirm="Force logout {{ $loggedInUser->username }}?">
                                            @csrf
                                            <input type="hidden" name="user_id" value="{{ $loggedInUser->id }}">
                                            <button type="submit" class="btn-outline-red">Force Logout</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-dense text-ink-muted">No users are currently online.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @include('partials.confirm-modal')
@endsection
