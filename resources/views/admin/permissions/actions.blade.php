@extends('layouts.app')

@section('title', 'Manage Action Permissions — 2D MIS')

@section('content')
    <div class="card shadow-lg border-0 p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Manage Action Permissions</h3>
        </div>

        <p class="text-muted">
            Phase-1 action grants for the adopted pages (P12). VIEW is the page grant itself and has no
            checkbox here. Only pages with the S2 flag on actually enforce these rows — see
            config/authorization.php.
        </p>

        <form method="GET" action="{{ route('admin.permissions.actions') }}" class="mb-3">
            <label class="form-label">Select User</label>
            <select name="user_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- Select User --</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected($selectedUser?->id === $user->id)>{{ $user->username }}</option>
                @endforeach
            </select>
        </form>

        @if ($selectedUser)
            <form method="POST" action="{{ route('admin.permissions.update-actions', $selectedUser->id) }}">
                @csrf

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Page</th>
                                @foreach ($pages as $pageName => $page)
                                    <th class="text-center">{{ $page['label'] }}
                                        @if ($page['enforcement'])
                                            <div class="small text-warning">enforcing</div>
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

                <button type="submit" class="btn btn-primary">Save Action Permissions</button>
            </form>
        @endif
    </div>
@endsection
