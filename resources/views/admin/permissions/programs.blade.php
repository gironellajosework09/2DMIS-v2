@extends('layouts.app')

@section('title', 'Manage Program Access — 2D MIS')

{{-- Batch G migration (UI_UX_ANALYSIS §8.9 Group 4): program-access
     matrix. Static server-rendered table, user-select GET form, check-all
     helper and POST update contract unchanged. --}}
@push('styles')
    <style>
        /* ── Program-access skin. Prefixed with #perm-programs-screen —
           nothing here can leak to other screens. ── */
        #perm-programs-screen .matrix-table th {
            font-size: 0.85rem;
            background-color: var(--color-navy);
            color: #fff;
            border-bottom: 0;
            white-space: nowrap;
        }

        #perm-programs-screen .matrix-table td {
            font-size: 0.875rem;
        }

        #perm-programs-screen .matrix-table tbody tr:nth-child(odd) td {
            background-color: rgb(15 27 45 / 0.02);
        }

        #perm-programs-screen .matrix-table th,
        #perm-programs-screen .matrix-table td {
            border: 1px solid var(--color-line-light);
        }
    </style>
@endpush

@section('content')
    @include('partials.breadcrumbs', [
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Manage Program Access'],
        ],
    ])

    @include('partials.page-header', [
        'title' => 'Manage Program Access',
        'subtitle' => 'Programs each user may work with on program-scoped screens.',
    ])

    <div id="perm-programs-screen" class="flex flex-col gap-[16px]">
        <section class="data-card" aria-label="Select user">
            <div class="data-card-body max-w-[420px]">
                <form method="GET" action="{{ route('admin.program-permissions.pages') }}">
                    <label class="field-label" for="user_select">Select User</label>
                    <select name="user_id" id="user_select" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Select User --</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected($selectedUser?->id === $user->id)>{{ $user->username }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </section>

        @if ($selectedUser)
            <section class="data-card" aria-label="Program access matrix">
                <div class="data-card-body">
                    <form method="POST" action="{{ route('admin.program-permissions.update', $selectedUser->id) }}" class="flex flex-col items-start gap-[16px]">
                        @csrf

                        <div class="overflow-x-auto w-full">
                            <table class="table matrix-table align-middle mb-0">
                                <thead>
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

                        <button type="submit" class="btn-navy">Save Program Access</button>
                    </form>
                </div>
            </section>
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
