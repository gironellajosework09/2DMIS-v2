@extends('layouts.app')

@section('title', 'Manage Action Permissions — 2D MIS')

{{-- Batch G migration (UI_UX_ANALYSIS §8.9 Group 4): action-permission
     matrix. Static server-rendered table, user-select GET form and POST
     update-actions contract unchanged. --}}
@push('styles')
    <style>
        /* ── Action-permissions scope: token skin over the matrix.
           Prefixed with #perm-actions-screen — nothing here can leak. ── */
        #perm-actions-screen .matrix-table th {
            font-size: 0.85rem;
            background-color: var(--color-navy);
            color: #fff;
            border-bottom: 0;
            white-space: nowrap;
        }

        #perm-actions-screen .matrix-table td {
            font-size: 0.875rem;
        }

        #perm-actions-screen .matrix-table tbody tr:nth-child(odd) td {
            background-color: rgb(15 27 45 / 0.02);
        }

        #perm-actions-screen .matrix-table th,
        #perm-actions-screen .matrix-table td {
            border: 1px solid var(--color-line-light);
        }

        #perm-actions-screen .enforcing-flag {
            color: var(--color-amber);
            font-weight: 600;
        }
    </style>
@endpush

@section('content')

    @include('partials.page-header', [
        'title' => 'Manage Action Permissions',
        'subtitle' => 'Phase-1 action grants for the adopted pages.',
    ])

    <div id="perm-actions-screen" class="flex flex-col gap-[16px]">
        <section class="data-card" aria-label="Select user">
            <div class="data-card-body max-w-[420px]">
                <form method="GET" action="{{ route('admin.permissions.actions') }}">
                    <label class="field-label" for="user_select">Select User</label>
                    <select name="user_id" id="user_select" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Select User --</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected($selectedUser?->id === $user->id)>{{ $user->username }}</option>
                        @endforeach
                    </select>
                </form>
                <p class="mb-0 mt-[12px] text-dense leading-snug text-ink-muted">
                    Phase-1 action grants for the adopted pages (P12). VIEW is the page grant itself and has no
                    checkbox here. Only pages with the S2 flag on actually enforce these rows — see
                    config/authorization.php.
                </p>
            </div>
        </section>

        @if ($selectedUser)
            <section class="data-card" aria-label="Action permission matrix">
                <div class="data-card-body">
                    <form method="POST" action="{{ route('admin.permissions.update-actions', $selectedUser->id) }}" class="flex flex-col items-start gap-[16px]">
                        @csrf

                        <div class="overflow-x-auto w-full">
                            <table class="table matrix-table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Page</th>
                                        @foreach ($pages as $pageName => $page)
                                            <th class="text-center">{{ $page['label'] }}
                                                @if ($page['enforcement'])
                                                    <div class="enforcing-flag text-dense">enforcing</div>
                                                @endif
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $actionNames = collect($pages)->flatMap(fn ($p) => $p['actions'])->unique()->values()->all(); @endphp
                                    @foreach ($actionNames as $actionName)
                                        <tr>
                                            <td>{{ $actionName }}</td>
                                            @foreach ($pages as $pageName => $page)
                                                <td class="text-center">
                                                    @if (in_array($actionName, $page['actions'], true))
                                                        <input type="checkbox"
                                                               name="actions[]"
                                                               value="{{ $pageName }}:{{ $actionName }}"
                                                               @checked(in_array($pageName . ':' . $actionName, $userActions, true))>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <button type="submit" class="btn-navy">Save Action Permissions</button>
                    </form>
                </div>
            </section>
        @endif
    </div>
@endsection
