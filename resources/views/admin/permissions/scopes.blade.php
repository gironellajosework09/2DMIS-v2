@extends('layouts.app')

@section('title', 'Manage Municipality Scope — 2D MIS')

{{-- Batch G migration (UI_UX_ANALYSIS §8.9 Group 4): municipality-scope
     matrix. Static server-rendered table, user-select GET form, check-all
     helper and POST update-scopes contract unchanged.
     SANCTIONED script delta: the all-municipalities toggle's native
     confirm() becomes the shared uiConfirm dialog (decline still reverts
     the box). --}}
@push('styles')
    <style>
        /* ── Municipality-scope skin. Prefixed with #perm-scopes-screen —
           nothing here can leak to other screens. ── */
        #perm-scopes-screen .matrix-table th {
            font-size: 0.85rem;
            background-color: var(--color-navy);
            color: #fff;
            border-bottom: 0;
            white-space: nowrap;
        }

        #perm-scopes-screen .matrix-table td {
            font-size: 0.875rem;
        }

        #perm-scopes-screen .matrix-table tbody tr:nth-child(odd) td {
            background-color: rgb(15 27 45 / 0.02);
        }

        #perm-scopes-screen .matrix-table th,
        #perm-scopes-screen .matrix-table td {
            border: 1px solid var(--color-line-light);
        }
    </style>
@endpush

@section('content')

    @include('partials.page-header', [
        'title' => 'Manage Municipality Scope',
        'subtitle' => 'Municipalities each user may access on scoped pages.',
    ])

    <div id="perm-scopes-screen" class="flex flex-col gap-[16px]">
        <section class="data-card" aria-label="Select user">
            <div class="data-card-body max-w-[420px]">
                <form method="GET" action="{{ route('admin.permissions.scopes') }}">
                    <label class="field-label" for="user_select">Select User</label>
                    <select name="user_id" id="user_select" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Select User --</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected($selectedUser?->id === $user->id)>{{ $user->username }}</option>
                        @endforeach
                    </select>
                </form>
                <p class="mb-0 mt-[12px] text-dense leading-snug text-ink-muted">
                    Municipalities this user may access on municipality-scoped pages (P12). "All Municipalities"
                    writes the reserved 0 marker and grants every municipality; a user with no rows matches no
                    records (fail closed).
                </p>
            </div>
        </section>

        @if ($selectedUser)
            <section class="data-card" aria-label="Municipality scope matrix">
                <div class="data-card-body flex flex-col gap-[16px]">
                    <form method="POST" action="{{ route('admin.permissions.update-scopes', $selectedUser->id) }}" class="flex flex-col items-start gap-[16px]">
                        @csrf

                        <div class="flex w-full items-start gap-[10px] rounded-[var(--radius-control)] border border-line bg-[rgb(252_209_22_/_0.12)] p-[14px]">
                            <input class="form-check-input mt-1" type="checkbox" name="all" value="1" id="all"
                                   @checked($hasAll) onchange="confirmAll(this)">
                            <div class="min-w-0">
                                <label class="form-check-label font-semibold text-ink" for="all">
                                    All Municipalities
                                </label>
                                <div class="text-dense text-ink-muted">
                                    Writes the reserved 0 marker in tbl_user_municipalities: the user's effective scope
                                    is every municipality. Leave unchecked and clear the list to restrict to no records.
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto w-full">
                            <table class="table matrix-table align-middle mb-0">
                                <thead>
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

                        <button type="submit" class="btn-navy">Save Municipality Scope</button>
                    </form>
                </div>
            </section>
        @endif
    </div>

    @include('partials.confirm-modal')
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
            window.uiConfirm({
                title: 'Municipality scope',
                message: message,
                confirmLabel: 'Confirm'
            }).then(function (ok) {
                if (!ok) {
                    cb.checked = !cb.checked;
                }
            });
        }
    </script>
@endpush
