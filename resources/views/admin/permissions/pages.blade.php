@extends('layouts.app')

@section('title', 'Manage Page Access — 2D MIS')

{{-- Batch G migration (UI_UX_ANALYSIS §8.9 Group 4): page-access matrix.
     Static server-rendered table (no DataTables), user-select GET form,
     check-all helper and POST update-pages contract unchanged.
     SANCTIONED script delta: the super-admin toggle's native confirm()
     becomes the shared uiConfirm dialog (decline still reverts the box). --}}
@push('styles')
    <style>
        /* ── Page-access scope: token skin over the permission matrix.
           Prefixed with #perm-pages-screen — nothing here can leak. ── */
        #perm-pages-screen .matrix-table th {
            font-size: 0.85rem;
            background-color: var(--color-navy);
            color: #fff;
            border-bottom: 0;
            white-space: nowrap;
        }

        #perm-pages-screen .matrix-table td {
            font-size: 0.875rem;
        }

        #perm-pages-screen .matrix-table tbody tr:nth-child(odd) td {
            background-color: rgb(15 27 45 / 0.02);
        }

        #perm-pages-screen .matrix-table th,
        #perm-pages-screen .matrix-table td {
            border: 1px solid var(--color-line-light);
        }
    </style>
@endpush

@section('content')

    @include('partials.page-header', [
        'title' => 'Manage Page Access',
        'subtitle' => 'Choose which pages each user may open.',
    ])

    <div id="perm-pages-screen" class="flex flex-col gap-[16px]">
        <section class="data-card" aria-label="Select user">
            <div class="data-card-body max-w-[420px]">
                <form method="GET" action="{{ route('admin.permissions.pages') }}">
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
            <section class="data-card" aria-label="Page access matrix">
                <div class="data-card-body flex flex-col gap-[16px]">
                    <form method="POST" action="{{ route('admin.permissions.update-pages', $selectedUser->id) }}" class="flex flex-col items-start gap-[16px]">
                        @csrf

                        <div class="flex w-full items-start gap-[10px] rounded-[var(--radius-control)] border border-line bg-[rgb(252_209_22_/_0.12)] p-[14px]">
                            <input class="form-check-input mt-1" type="checkbox" name="super_admin" value="1" id="superAdmin"
                                   @checked($isSuperAdmin) onchange="confirmSuperAdmin(this)">
                            <div class="min-w-0">
                                <label class="form-check-label font-semibold text-ink" for="superAdmin">
                                    Super Admin — full access to every page
                                </label>
                                <div class="text-dense text-ink-muted">
                                    Grants the '*' permission row: bypasses every page gate and exempts the user from the single-device check.
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto w-full">
                            <table class="table matrix-table align-middle mb-0">
                                <thead>
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

                        <button type="submit" class="btn-navy">Save Permissions</button>
                    </form>
                </div>
            </section>
        @endif
    </div>

    @include('partials.confirm-modal')
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
            window.uiConfirm({
                title: 'Super admin access',
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
